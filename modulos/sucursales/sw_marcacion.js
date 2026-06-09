/**
 * sw_marcacion.js — Service Worker para Marcación Express Offline
 * Pitaya ERP · /modulos/sucursales/sw_marcacion.js
 */

const CACHE_NAME = 'pitaya-marcacion-v1';

const PRECACHE_ASSETS = [
    '/core/assets/img/Logo.svg',
    '/core/assets/img/icon12.png',
    'https://cdnjs.cloudflare.com/ajax/libs/bcryptjs/2.4.3/bcrypt.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache =>
            Promise.allSettled(PRECACHE_ASSETS.map(url =>
                cache.add(url).catch(e => console.warn('[SW] No se pudo cachear:', url))
            ))
        ).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys()
            .then(keys => Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', event => {
    const req = event.request;

    // Solo se permite cachear peticiones GET (requisito de la Cache API del navegador).
    // Las peticiones POST (como la verificación online y sincronización) van directo a la red.
    if (req.method !== 'GET') {
        event.respondWith(
            fetch(req).catch(() =>
                new Response(
                    JSON.stringify({ success: false, offline: true }),
                    { status: 503, headers: { 'Content-Type': 'application/json' } }
                )
            )
        );
        return;
    }

    const url = new URL(req.url);

    // Página principal: network-first + cache fallback
    if (url.pathname.includes('marcacion_express.php')) {
        event.respondWith(
            fetch(req)
                .then(response => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then(cache => cache.put(req, clone));
                    }
                    return response;
                })
                .catch(async () => {
                    const cached = await caches.match(req);
                    if (cached) return cached;
                    return new Response(
                        `<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width,initial-scale=1">
                        <title>Sin conexión</title>
                        <style>body{font-family:sans-serif;display:flex;align-items:center;
                        justify-content:center;min-height:100vh;background:#eef7f6;margin:0}
                        .card{background:white;padding:40px;border-radius:20px;text-align:center;
                        max-width:400px;box-shadow:0 8px 30px rgba(0,0,0,.1)}
                        h2{color:#0E544C}p{color:#666}
                        button{background:#51B8AC;color:white;border:none;padding:12px 30px;
                        border-radius:50px;cursor:pointer;margin-top:20px;font-size:1rem}</style>
                        </head><body><div class="card">
                        <h2>📡 Sin conexión</h2>
                        <p>No se cargó la página de marcación.<br>Verifica tu conexión e intenta de nuevo.</p>
                        <button onclick="location.reload()">🔄 Reintentar</button>
                        </div></body></html>`,
                        { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
                    );
                })
        );
        return;
    }

    // Assets estáticos: cache-first
    event.respondWith(
        caches.match(req).then(cached => {
            if (cached) return cached;
            return fetch(req).then(response => {
                if (response.ok) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(req, clone));
                }
                return response;
            }).catch(() => new Response('', { status: 503 }));
        })
    );
});

self.addEventListener('message', event => {
    if (event.data?.type === 'SKIP_WAITING') self.skipWaiting();
});

// ── Background Sync ───────────────────────────────────────────────────────────
const DB_NAME = 'pitaya_offline_v1';
const DB_VERSION = 1;

function openDB() {
    return new Promise((res, rej) => {
        const req = indexedDB.open(DB_NAME, DB_VERSION);
        req.onsuccess = e => res(e.target.result);
        req.onerror  = e => rej(e.target.error);
    });
}

function getPendingMarcaciones(db) {
    return new Promise((res, rej) => {
        try {
            const tx = db.transaction('marcaciones_queue', 'readonly');
            const store = tx.objectStore('marcaciones_queue');
            const req = store.getAll();
            req.onsuccess = e => {
                const all = e.target.result || [];
                res(all.filter(i => i.status === 'pending'));
            };
            req.onerror = e => rej(e.target.error);
        } catch (e) {
            rej(e);
        }
    });
}

function deleteMarcacion(db, local_id) {
    return new Promise((res, rej) => {
        try {
            const tx = db.transaction('marcaciones_queue', 'readwrite');
            const store = tx.objectStore('marcaciones_queue');
            const req = store.delete(local_id);
            req.onsuccess = () => res();
            req.onerror = e => rej(e.target.error);
        } catch (e) {
            rej(e);
        }
    });
}

function updateMarcacionToError(db, item, errorMsg) {
    return new Promise((res, rej) => {
        try {
            const tx = db.transaction('marcaciones_queue', 'readwrite');
            const store = tx.objectStore('marcaciones_queue');
            item.status = 'error';
            item.error_msg = errorMsg;
            item.intentos = (item.intentos || 0) + 1;
            const req = store.put(item);
            req.onsuccess = () => res();
            req.onerror = e => rej(e.target.error);
        } catch (e) {
            rej(e);
        }
    });
}

async function syncQueueFromSW() {
    try {
        const db = await openDB();
        const pending = await getPendingMarcaciones(db);
        if (pending.length === 0) return;

        const res = await fetch('/modulos/sucursales/ajax/marcacion_offline_sync.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ queue: pending })
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();

        for (const r of (data.results || [])) {
            if (r.success) {
                await deleteMarcacion(db, r.local_id);
            } else {
                const it = pending.find(i => i.local_id === r.local_id);
                if (it) {
                    await updateMarcacionToError(db, it, r.error);
                }
            }
        }
    } catch (e) {
        console.warn('[SW Background Sync] Error al sincronizar cola:', e.message);
    }
}

// Se dispara cuando el navegador detecta conexión y hay una tarea 'sync-marcaciones-pitaya'
// registrada. Funciona aunque el tab esté minimizado o en segundo plano.
self.addEventListener('sync', event => {
    if (event.tag === 'sync-marcaciones-pitaya') {
        event.waitUntil(
            self.clients.matchAll({ type: 'window', includeUncontrolled: true })
                .then(clients => {
                    if (clients.length > 0) {
                        // Hay tab(s) abiertos: pedirles que ejecuten el sync
                        clients.forEach(client => client.postMessage({ type: 'SYNC_QUEUE' }));
                        return Promise.resolve();
                    } else {
                        // Sin tabs abiertos: sync directo desde el SW
                        return syncQueueFromSW();
                    }
                })
        );
    }
});
