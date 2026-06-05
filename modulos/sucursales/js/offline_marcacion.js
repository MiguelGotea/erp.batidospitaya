/**
 * offline_marcacion.js — Módulo offline para Marcación Express
 * Pitaya ERP · /modulos/sucursales/js/offline_marcacion.js
 */
(function () {
    const DB_NAME = 'pitaya_offline_v1';
    const DB_VERSION = 1;
    let db = null, syncInProgress = false;

    function getBcrypt() { return window.dcodeIO?.bcrypt || window.bcrypt || null; }

    function uuid() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
            const r = Math.random() * 16 | 0;
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
    }

    // ── IndexedDB helpers ────────────────────────────────────────────────
    function openDB() {
        return new Promise((res, rej) => {
            const req = indexedDB.open(DB_NAME, DB_VERSION);
            req.onupgradeneeded = e => {
                const d = e.target.result;
                if (!d.objectStoreNames.contains('operarios_cache'))
                    d.createObjectStore('operarios_cache', { keyPath: 'CodOperario' });
                if (!d.objectStoreNames.contains('marcaciones_queue'))
                    d.createObjectStore('marcaciones_queue', { keyPath: 'local_id' });
                if (!d.objectStoreNames.contains('config'))
                    d.createObjectStore('config', { keyPath: 'key' });
            };
            req.onsuccess = e => res(e.target.result);
            req.onerror  = e => rej(e.target.error);
        });
    }
    const tx = (store, mode) => db.transaction(store, mode).objectStore(store);
    const idbGet    = (s, k) => new Promise((r, j) => { const q = tx(s,'readonly').get(k);       q.onsuccess = e=>r(e.target.result); q.onerror=e=>j(e.target.error); });
    const idbPut    = (s, v) => new Promise((r, j) => { const q = tx(s,'readwrite').put(v);      q.onsuccess = e=>r(e.target.result); q.onerror=e=>j(e.target.error); });
    const idbGetAll = (s)    => new Promise((r, j) => { const q = tx(s,'readonly').getAll();     q.onsuccess = e=>r(e.target.result); q.onerror=e=>j(e.target.error); });
    const idbDel    = (s, k) => new Promise((r, j) => { const q = tx(s,'readwrite').delete(k);   q.onsuccess = ()=>r();               q.onerror=e=>j(e.target.error); });
    const idbClear  = (s)    => new Promise((r, j) => { const q = tx(s,'readwrite').clear();     q.onsuccess = ()=>r();               q.onerror=e=>j(e.target.error); });

    // ── UI ───────────────────────────────────────────────────────────────
    function showBanner(mode, html) {
        const b = document.getElementById('offlineBanner');
        if (!b) return;
        b.className = 'offline-banner mode-' + mode;
        b.innerHTML = html;
        b.style.display = 'block';
    }
    function hideBanner() {
        const b = document.getElementById('offlineBanner');
        if (b) b.style.display = 'none';
    }
    async function updateBannerCount() {
        const all = await idbGetAll('marcaciones_queue');
        const n = all.filter(i => i.status === 'pending').length;
        document.querySelectorAll('.offline-queue-count').forEach(el => el.textContent = n);
        return n;
    }

    // ── Caché de operarios ───────────────────────────────────────────────
    async function refreshCache() {
        try {
            const res = await fetch('ajax/marcacion_offline_cache.php');
            if (!res.ok) return;
            const data = await res.json();
            if (!data.success || !Array.isArray(data.operarios)) return;

            await idbClear('operarios_cache');
            for (const op of data.operarios) await idbPut('operarios_cache', op);
            await idbPut('config', { key: 'ultimo_cache', value: data.timestamp_cache || new Date().toISOString() });
            await idbPut('config', { key: 'token_estado', value: 'valido' });
            console.log('[PitayaOffline] Caché renovado:', data.total, 'operarios');
        } catch (e) {
            console.warn('[PitayaOffline] Caché no renovado:', e.message);
        }
    }

    // ── Validación offline (bcrypt local) ────────────────────────────────
    async function checkPasswordOffline(clave) {
        const bc = getBcrypt();
        if (!bc || !db) return { found: false };
        const operarios = await idbGetAll('operarios_cache');
        for (const op of operarios) {
            try {
                if (bc.compareSync(clave, op.hash_clave))
                    return { found: true, CodOperario: op.CodOperario, nombre: op.nombre_completo };
            } catch (e) { /* hash inválido, omitir */ }
        }
        return { found: false };
    }

    // ── Cola offline ─────────────────────────────────────────────────────
    async function enqueue(clave, CodOperario, nombre) {
        const now = new Date();
        const pad = n => String(n).padStart(2, '0');
        const fecha = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}`;
        const hora  = `${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
        const item  = {
            local_id: uuid(), CodOperario, nombre_completo: nombre,
            clave, fecha, hora,
            timestamp_iso: now.toISOString(),
            status: 'pending', intentos: 0,
        };
        await idbPut('marcaciones_queue', item);
        await updateBannerCount();
        return item;
    }

    // ── Sincronización ───────────────────────────────────────────────────
    async function syncQueue() {
        if (syncInProgress) return;
        syncInProgress = true;
        try {
            // Verificar token: ping silencioso
            const pingFd = new FormData();
            pingFd.append('clave', '_ping_');
            const ping = await fetch('ajax/marcacion_express_verificar.php', { method: 'POST', body: pingFd });

            if (ping.status === 401) {
                await idbPut('config', { key: 'token_estado', value: 'expirado' });
                const n = await updateBannerCount();
                showBanner('token-expired',
                    `⚠️ Dispositivo necesita re-autorización — <strong class="offline-queue-count">${n}</strong> marcaciones en cola preservadas`);
                const kb = document.getElementById('virtualKeyboard');
                if (kb) kb.style.pointerEvents = 'none';
                return;
            }

            const all = await idbGetAll('marcaciones_queue');
            const pending = all.filter(i => i.status === 'pending');
            if (pending.length === 0) { await refreshCache(); hideBanner(); return; }

            showBanner('syncing', `🔄 Sincronizando <strong>${pending.length}</strong> marcación${pending.length > 1 ? 'es' : ''}...`);

            const res = await fetch('ajax/marcacion_offline_sync.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ queue: pending }),
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();

            let ok = 0, err = 0;
            for (const r of (data.results || [])) {
                if (r.success) { await idbDel('marcaciones_queue', r.local_id); ok++; }
                else {
                    const it = all.find(i => i.local_id === r.local_id);
                    if (it) { it.status = 'error'; it.error_msg = r.error; it.intentos++; await idbPut('marcaciones_queue', it); }
                    err++;
                }
            }
            await refreshCache();
            const msg = ok > 0
                ? `✅ ${ok} marcación${ok>1?'es':''} sincronizada${ok>1?'s':''}`+ (err ? ` — ${err} con error (preservada${err>1?'s':''})` : '')
                : `⚠️ No se pudo sincronizar ${err} marcación${err>1?'es':''} (preservada${err>1?'s':''})`;
            showBanner('sync-ok', msg);
            setTimeout(hideBanner, 6000);
        } catch (e) {
            console.warn('[PitayaOffline] Error sync:', e.message);
            showBanner('offline', '⚡ Sin conexión — <strong class="offline-queue-count">?</strong> marcaciones en espera');
            updateBannerCount();
        } finally {
            syncInProgress = false;
        }
    }

    // ── Init ─────────────────────────────────────────────────────────────
    async function init() {
        db = await openDB();

        window.addEventListener('online',  () => syncQueue());
        window.addEventListener('offline', async () => {
            const n = await updateBannerCount();
            showBanner('offline', `⚡ Sin conexión — <strong class="offline-queue-count">${n}</strong> marcaciones en espera`);
        });

        if (!navigator.onLine) {
            const n = await updateBannerCount();
            showBanner('offline', `⚡ Sin conexión — <strong class="offline-queue-count">${n}</strong> marcaciones en espera`);
        } else {
            setTimeout(refreshCache, 2000);
            setInterval(refreshCache, 600000);
        }

        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/modulos/sucursales/sw_marcacion.js', {
                scope: '/modulos/sucursales/'
            }).catch(e => console.warn('[PitayaOffline] SW no registrado:', e.message));
        }
    }

    // ── API pública ──────────────────────────────────────────────────────
    window.PitayaOffline = { init, checkPasswordOffline, enqueue, syncQueue, updateBannerCount };
})();
