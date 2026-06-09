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

    // ── LocalStorage Mirror Backup Helpers ─────────────────────────────────
    async function updateLocalStorageBackup() {
        try {
            const all = await idbGetAll('marcaciones_queue');
            const pending = all.filter(i => i.status === 'pending');
            if (pending.length > 0) {
                localStorage.setItem('pitaya_offline_queue_backup', JSON.stringify(pending));
            } else {
                localStorage.removeItem('pitaya_offline_queue_backup');
            }
        } catch (e) {
            console.warn('[PitayaOffline] Error al actualizar backup en localStorage:', e.message);
        }
    }

    async function restoreFromLocalStorageBackup() {
        try {
            const backupStr = localStorage.getItem('pitaya_offline_queue_backup');
            if (!backupStr) return;
            const backup = JSON.parse(backupStr);
            if (!Array.isArray(backup) || backup.length === 0) return;

            const current = await idbGetAll('marcaciones_queue');
            let restoredCount = 0;

            for (const item of backup) {
                // Si el item no existe en IndexedDB, restaurarlo
                if (!current.some(i => i.local_id === item.local_id)) {
                    await idbPut('marcaciones_queue', item);
                    restoredCount++;
                }
            }

            if (restoredCount > 0) {
                console.log(`[PitayaOffline] Se restauraron ${restoredCount} marcaciones desde el backup de localStorage.`);
                await updateBannerCount();
            }
        } catch (e) {
            console.warn('[PitayaOffline] Error al restaurar desde backup de localStorage:', e.message);
        }
    }

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
    function escapeHTML(str) {
        return str.replace(/[&<>'"]/g, 
            tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
        );
    }
    function abrirModalCola() {
        const modal = document.getElementById('modalColaOffline');
        const listContainer = document.getElementById('listaColaOffline');
        if (!modal || !listContainer) return;

        listContainer.innerHTML = '<div style="text-align:center; padding:20px; color:var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Cargando cola...</div>';
        modal.style.display = 'flex';

        idbGetAll('marcaciones_queue').then(all => {
            const pending = all.filter(i => i.status === 'pending');
            listContainer.innerHTML = '';

            if (pending.length === 0) {
                listContainer.innerHTML = '<div style="text-align:center; padding:30px; color:var(--text-muted);"><i class="fas fa-check-circle" style="color:var(--primary-color); font-size:1.8rem; display:block; margin-bottom:8px;"></i>No hay marcaciones pendientes de sincronizar</div>';
            } else {
                pending.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'item-cola-offline';
                    div.innerHTML = `
                        <div>
                            <div class="item-cola-offline-nombre">${escapeHTML(item.nombre_completo)}</div>
                            <div class="item-cola-offline-meta">
                                <i class="far fa-clock"></i> ${item.fecha} ${item.hora}
                            </div>
                        </div>
                        <div>
                            <span class="item-cola-offline-badge badge-entrada">Pendiente</span>
                        </div>
                    `;
                    listContainer.appendChild(div);
                });
            }
        }).catch(err => {
            listContainer.innerHTML = `<div style="text-align:center; padding:20px; color:red;">Error al cargar la cola: ${escapeHTML(err.message)}</div>`;
        });
    }
    function cerrarModalCola() {
        const modal = document.getElementById('modalColaOffline');
        if (modal) modal.style.display = 'none';
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
    let lastSearchId = 0;
    async function checkPasswordOffline(clave) {
        const bc = getBcrypt();
        if (!bc || !db) return { found: false };

        lastSearchId++;
        const searchId = lastSearchId;

        const operarios = await idbGetAll('operarios_cache');
        for (const op of operarios) {
            // Abortar si ya se inició una nueva búsqueda de clave
            if (searchId !== lastSearchId) return { found: false, aborted: true };

            try {
                const isMatch = await new Promise(resolve => {
                    bc.compare(clave, op.hash_clave, (err, res) => resolve(!err && res));
                });
                if (isMatch) {
                    if (searchId !== lastSearchId) return { found: false, aborted: true };
                    return { found: true, CodOperario: op.CodOperario, nombre: op.nombre_completo };
                }
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
        await updateLocalStorageBackup();

        // Registrar Background Sync: el SW sincronizará aunque el tab esté minimizado
        try {
            if ('serviceWorker' in navigator) {
                const reg = await navigator.serviceWorker.ready;
                if (reg.sync) {
                    await reg.sync.register('sync-marcaciones-pitaya');
                    console.log('[PitayaOffline] Background Sync registrado ✓');
                }
            }
        } catch (e) {
            console.warn('[PitayaOffline] Background Sync no disponible:', e.message);
        }

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

            let tokenExpirado = (ping.status === 401);
            if (ping.ok) {
                try {
                    const pingData = await ping.clone().json();
                    if (!pingData.success && (
                        pingData.message.includes('Dispositivo no autorizado') || 
                        pingData.message.includes('token inválido') || 
                        pingData.message.includes('no configurado')
                    )) {
                        tokenExpirado = true;
                    }
                } catch (e) {}
            }

            if (tokenExpirado) {
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
            await updateLocalStorageBackup();
            await refreshCache();
            if (ok > 0) {
                const msg = `✅ ${ok} marcación${ok>1?'es':''} sincronizada${ok>1?'s':''}` + (err ? ` — ${err} con error (preservada${err>1?'s':''})` : '');
                showBanner('sync-ok', msg);
                // Recargar la página después de 2.5 segundos para reflejar los operarios "En Turno" en el panel lateral
                setTimeout(() => location.reload(), 2500);
            } else {
                const msg = `⚠️ No se pudo sincronizar ${err} marcación${err>1?'es':''} (preservada${err>1?'s':''})`;
                showBanner('sync-ok', msg);
                setTimeout(hideBanner, 6000);
            }
        } catch (e) {
            console.warn('[PitayaOffline] Error sync:', e.message);
            showBanner('offline', '⚡ Sin conexión — <strong class="offline-queue-count">?</strong> marcaciones en espera <button class="btn-ver-cola-link" onclick="PitayaOffline.abrirModalCola()">Ver cola</button>');
            updateBannerCount();
        } finally {
            syncInProgress = false;
        }
    }

    // ── Init ─────────────────────────────────────────────────────────────
    async function init() {
        // Solicitar almacenamiento persistente (protege IndexedDB de desalojo en Android/Chrome)
        if (navigator.storage && navigator.storage.persist) {
            navigator.storage.persist().then(granted => {
                console.log('[PitayaOffline] Almacenamiento persistente:', granted ? 'concedido ✓' : 'no concedido');
            });
        }

        db = await openDB();
        await restoreFromLocalStorageBackup();

        // Configurar botones del modal de cola offline
        const btnCerrarCola = document.getElementById('btnCerrarColaOffline');
        const btnAceptarCola = document.getElementById('btnAceptarColaOffline');
        if (btnCerrarCola) btnCerrarCola.addEventListener('click', cerrarModalCola);
        if (btnAceptarCola) btnAceptarCola.addEventListener('click', cerrarModalCola);

        window.addEventListener('online',  () => syncQueue());
        window.addEventListener('offline', async () => {
            const n = await updateBannerCount();
            showBanner('offline', `⚡ Sin conexión — <strong class="offline-queue-count">${n}</strong> marcaciones en espera <button class="btn-ver-cola-link" onclick="PitayaOffline.abrirModalCola()">Ver cola</button>`);
        });

        // Escuchar mensajes del Service Worker (ej: Background Sync con tab abierto)
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('message', event => {
                if (event.data?.type === 'SYNC_QUEUE') {
                    console.log('[PitayaOffline] Sync disparado por Service Worker');
                    syncQueue();
                }
            });
        }

        if (!navigator.onLine) {
            const n = await updateBannerCount();
            showBanner('offline', `⚡ Sin conexión — <strong class="offline-queue-count">${n}</strong> marcaciones en espera <button class="btn-ver-cola-link" onclick="PitayaOffline.abrirModalCola()">Ver cola</button>`);
        } else {
            setTimeout(() => { syncQueue(); }, 2000);
            setInterval(refreshCache, 600000);
        }

        // Bucle de auto-recuperación: verificar la cola cada 10 segundos y sincronizar si estamos online
        setInterval(async () => {
            if (navigator.onLine && db) {
                const all = await idbGetAll('marcaciones_queue');
                const pending = all.filter(i => i.status === 'pending');
                if (pending.length > 0) {
                    syncQueue();
                }
            }
        }, 10000);

        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/modulos/sucursales/sw_marcacion.js', {
                scope: '/modulos/sucursales/'
            }).catch(e => console.warn('[PitayaOffline] SW no registrado:', e.message));
        }
    }

    // ── API pública ──────────────────────────────────────────────────────
    window.PitayaOffline = { init, checkPasswordOffline, enqueue, syncQueue, updateBannerCount, abrirModalCola, cerrarModalCola };
})();
