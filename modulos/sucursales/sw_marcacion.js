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
