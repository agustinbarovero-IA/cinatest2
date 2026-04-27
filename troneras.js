/**
 * ══════════════════════════════════════════════════════════════════
 *  troneras.js — Módulo Troneras para el nuevo sistema CINA
 *
 *  Agregar en index.php:
 *    <script src="troneras.js"></script>
 *
 *  Agregar en app.js (menuItems o equivalente) una entrada para
 *  el módulo, por ejemplo:
 *    { id: 'troneras', label: 'Troneras', icon: '🚛', modulo: 'troneras' }
 *
 *  Depende de:
 *    - API_BASE_URL (o la constante que uses para api_2026ia.php)
 *    - menuGrid, setHeader, historyStack (globales de app.js)
 *    - window.__cinaSession
 * ══════════════════════════════════════════════════════════════════
 */

/* ─── Constante de API — ajustar si el nombre del archivo difiere ── */
const TRONERA_API = typeof API_EQUIPOS_URL !== 'undefined'
    ? API_EQUIPOS_URL
    : 'api_2026ia.php';

/* ─── Estado reactivo del módulo ────────────────────────────────── */
const troneraState = {
    lista:        [],       // troneras del índice
    filtroPlanta: 'N',
    filtroEstado: '',
    formModo:     'crear',  // 'crear' | 'editar'
    formData:     {},
};

/* ─── Helpers ───────────────────────────────────────────────────── */
const trFetch = async (params = {}, metodo = 'GET', body = null) => {
    const url = new URL(TRONERA_API, location.origin);
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
    const opts = { method: metodo, credentials: 'same-origin' };
    if (body) {
        opts.headers = { 'Content-Type': 'application/json' };
        opts.body    = JSON.stringify(body);
    }
    const res  = await fetch(url, opts);
    return res.json();
};

const trBadge = (estado) => {
    const map = {
        A: { txt: 'Activa',        cls: 'badge-green'  },
        M: { txt: 'Mantenimiento', cls: 'badge-yellow' },
        I: { txt: 'Inactiva',      cls: 'badge-gray'   },
    };
    const b = map[estado] || { txt: estado, cls: 'badge-gray' };
    return `<span class="tr-badge ${b.cls}">${b.txt}</span>`;
};

const trBadgeAsig = (estado) => {
    const map = {
        A: { txt: 'Asignado',  cls: 'badge-blue'   },
        U: { txt: 'En uso',    cls: 'badge-orange' },
        L: { txt: 'Liberado',  cls: 'badge-green'  },
        C: { txt: 'Cancelado', cls: 'badge-red'    },
    };
    const b = map[estado] || { txt: estado, cls: 'badge-gray' };
    return `<span class="tr-badge ${b.cls}">${b.txt}</span>`;
};

const trFmtHora = (iso) => {
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
};

const trFmtFecha = (iso) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('es-AR',
        { day: '2-digit', month: '2-digit', year: '2-digit' });
};

const trToast = (msg, tipo = 'ok') => {
    const el = document.createElement('div');
    el.className = `tr-toast tr-toast-${tipo}`;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3500);
};

/* ══════════════════════════════════════════════════════════════════
   PANTALLA PRINCIPAL — índice de troneras
   ══════════════════════════════════════════════════════════════════ */
async function renderTroneras() {
    setHeader('Troneras', 'Gestión de troneras físicas');
    historyStack.push({ fn: renderTroneras });
    menuGrid.className = 'menu-grid full-width';

    menuGrid.innerHTML = `
        <div class="tr-wrap">
            <div class="tr-toolbar">
                <div class="tr-filters">
                    <select id="trFiltroPlanta" class="tr-select">
                        <option value="">Todas las plantas</option>
                        <option value="N" ${troneraState.filtroPlanta==='N'?'selected':''}>Nacional</option>
                        <option value="F" ${troneraState.filtroPlanta==='F'?'selected':''}>Fiscal</option>
                    </select>
                    <select id="trFiltroEstado" class="tr-select">
                        <option value="">Todos los estados</option>
                        <option value="A" ${troneraState.filtroEstado==='A'?'selected':''}>Activas</option>
                        <option value="M" ${troneraState.filtroEstado==='M'?'selected':''}>Mantenimiento</option>
                        <option value="I" ${troneraState.filtroEstado==='I'?'selected':''}>Inactivas</option>
                    </select>
                    <button id="trBtnFiltrar" class="tr-btn tr-btn-secondary">Filtrar</button>
                </div>
                <div class="tr-toolbar-right">
                    <button id="trBtnNueva" class="tr-btn tr-btn-primary">+ Nueva tronera</button>
                </div>
            </div>

            <div id="trTabla" class="tr-tabla-wrap">
                <div class="tr-loading">Cargando...</div>
            </div>
        </div>`;

    // Estilos inline (se inyectan una sola vez)
    trInjectStyles();

    document.getElementById('trBtnFiltrar').addEventListener('click', () => {
        troneraState.filtroPlanta = document.getElementById('trFiltroPlanta').value;
        troneraState.filtroEstado = document.getElementById('trFiltroEstado').value;
        trCargarLista();
    });

    document.getElementById('trBtnNueva').addEventListener('click', () => {
        troneraState.formModo = 'crear';
        troneraState.formData = {};
        renderTroneraForm();
    });

    trCargarLista();
}

async function trCargarLista() {
    const tabla = document.getElementById('trTabla');
    if (!tabla) return;
    tabla.innerHTML = '<div class="tr-loading">Cargando...</div>';

    const params = { action: 'troneras_lista' };
    if (troneraState.filtroPlanta) params.planta = troneraState.filtroPlanta;
    if (troneraState.filtroEstado) params.estado  = troneraState.filtroEstado;

    try {
        const data = await trFetch(params);
        troneraState.lista = Array.isArray(data) ? data : [];
        trRenderTabla();
    } catch (e) {
        tabla.innerHTML = `<div class="tr-error">Error al cargar: ${e.message}</div>`;
    }
}

function trRenderTabla() {
    const tabla = document.getElementById('trTabla');
    if (!tabla) return;

    if (!troneraState.lista.length) {
        tabla.innerHTML = '<div class="tr-empty">No hay troneras con los filtros aplicados.</div>';
        return;
    }

    tabla.innerHTML = `
        <table class="tr-table">
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Planta</th>
                    <th>Andén</th>
                    <th>Posición</th>
                    <th>Estado</th>
                    <th>Asignación</th>
                    <th>Carga</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                ${troneraState.lista.map(t => `
                    <tr>
                        <td><strong>${t.numero}</strong></td>
                        <td>${t.tipo_texto || t.tipo}</td>
                        <td>${t.anden_texto || t.anden}</td>
                        <td class="tr-pos">${t.fila}:${t.columna}:${t.grado}</td>
                        <td>${trBadge(t.estado)}</td>
                        <td>${t.tt_estado ? trBadgeAsig(t.tt_estado) : '<span class="tr-libre">Libre</span>'}</td>
                        <td>${t.tt_carga_numero ? `<strong>#${t.tt_carga_numero}</strong>` : '—'}</td>
                        <td class="tr-acciones">
                            <button class="tr-btn tr-btn-xs tr-btn-secondary"
                                    onclick="trAbrirEditar(${t.id})">Editar</button>
                            ${t.tt_estado === 'A' || t.tt_estado === 'U' ? `
                                <button class="tr-btn tr-btn-xs tr-btn-warning"
                                        onclick="trDesasignar(${t.id}, ${t.tt_carga_numero})">Desasignar</button>
                                <button class="tr-btn tr-btn-xs tr-btn-success"
                                        onclick="trLiberar(${t.id}, ${t.tt_carga_numero})">Liberar</button>
                            ` : ''}
                            ${t.estado !== 'I' ? `
                                <button class="tr-btn tr-btn-xs tr-btn-danger"
                                        onclick="trEliminar(${t.id}, '${t.numero}')">Desactivar</button>
                            ` : ''}
                        </td>
                    </tr>`).join('')}
            </tbody>
        </table>`;
}

/* ══════════════════════════════════════════════════════════════════
   FORMULARIO — crear / editar
   ══════════════════════════════════════════════════════════════════ */
function renderTroneraForm(tronera = null) {
    const d = tronera || troneraState.formData;
    const esEditar = troneraState.formModo === 'editar';

    const overlay = document.createElement('div');
    overlay.className = 'tr-overlay';
    overlay.id = 'trOverlay';
    overlay.innerHTML = `
        <div class="tr-modal">
            <div class="tr-modal-header">
                <h3>${esEditar ? 'Editar' : 'Nueva'} Tronera</h3>
                <button class="tr-modal-close" onclick="trCerrarModal()">✕</button>
            </div>
            <div class="tr-modal-body">
                <div class="tr-form-grid">
                    <div class="tr-form-group">
                        <label>Número *</label>
                        <input type="text" id="trFNumero" value="${d.numero || ''}"
                               placeholder="Ej: T1" class="tr-input" />
                    </div>
                    <div class="tr-form-group">
                        <label>Planta *</label>
                        <select id="trFTipo" class="tr-input">
                            <option value="N" ${d.tipo==='N'?'selected':''}>Nacional</option>
                            <option value="F" ${d.tipo==='F'?'selected':''}>Fiscal</option>
                        </select>
                    </div>
                    <div class="tr-form-group">
                        <label>Andén *</label>
                        <select id="trFAnden" class="tr-input">
                            <option value="N" ${d.anden==='N'?'selected':''}>Norte</option>
                            <option value="S" ${d.anden==='S'?'selected':''}>Sur</option>
                            <option value="F" ${d.anden==='F'?'selected':''}>Frío</option>
                            <option value="E" ${d.anden==='E'?'selected':''}>Seco</option>
                        </select>
                    </div>
                    <div class="tr-form-group">
                        <label>Estado</label>
                        <select id="trFEstado" class="tr-input">
                            <option value="A" ${d.estado==='A'||!d.estado?'selected':''}>Activa</option>
                            <option value="M" ${d.estado==='M'?'selected':''}>Mantenimiento</option>
                            <option value="I" ${d.estado==='I'?'selected':''}>Inactiva</option>
                        </select>
                    </div>
                    <div class="tr-form-group">
                        <label>Fila</label>
                        <input type="number" id="trFFila" value="${d.fila || 0}"
                               min="0" class="tr-input" />
                    </div>
                    <div class="tr-form-group">
                        <label>Columna</label>
                        <input type="number" id="trFColumna" value="${d.columna || 0}"
                               min="0" class="tr-input" />
                    </div>
                    <div class="tr-form-group">
                        <label>Grado (rotación SVG)</label>
                        <input type="number" id="trFGrado" value="${d.grado || 0}"
                               step="45" class="tr-input" />
                    </div>
                    <div class="tr-form-group tr-form-group-full">
                        <label>Comentarios</label>
                        <textarea id="trFComentarios" rows="2" class="tr-input"
                                  placeholder="Observaciones opcionales...">${d.comentarios || ''}</textarea>
                    </div>
                </div>
            </div>
            <div class="tr-modal-footer">
                <button class="tr-btn tr-btn-secondary" onclick="trCerrarModal()">Cancelar</button>
                <button class="tr-btn tr-btn-primary" id="trBtnGuardar">
                    ${esEditar ? 'Guardar cambios' : 'Crear tronera'}
                </button>
            </div>
        </div>`;

    document.body.appendChild(overlay);

    document.getElementById('trBtnGuardar').addEventListener('click', async () => {
        const payload = {
            id:          esEditar ? (d.id || 0) : 0,
            numero:      document.getElementById('trFNumero').value.trim(),
            tipo:        document.getElementById('trFTipo').value,
            anden:       document.getElementById('trFAnden').value,
            fila:        parseInt(document.getElementById('trFFila').value)    || 0,
            columna:     parseInt(document.getElementById('trFColumna').value) || 0,
            grado:       parseInt(document.getElementById('trFGrado').value)   || 0,
            estado:      document.getElementById('trFEstado').value,
            comentarios: document.getElementById('trFComentarios').value.trim(),
        };

        if (!payload.numero) { trToast('El número es obligatorio', 'error'); return; }

        const btn = document.getElementById('trBtnGuardar');
        btn.disabled = true;
        btn.textContent = 'Guardando...';

        try {
            const res = await trFetch({ action: 'tronera_guardar' }, 'POST', payload);
            if (res.ok) {
                trToast(esEditar ? 'Tronera actualizada' : 'Tronera creada', 'ok');
                trCerrarModal();
                trCargarLista();
            } else {
                trToast(res.error || 'Error al guardar', 'error');
                btn.disabled = false;
                btn.textContent = esEditar ? 'Guardar cambios' : 'Crear tronera';
            }
        } catch (e) {
            trToast('Error de red: ' + e.message, 'error');
            btn.disabled = false;
        }
    });
}

async function trAbrirEditar(id) {
    const tronera = troneraState.lista.find(t => t.id === id);
    if (!tronera) { trToast('Tronera no encontrada', 'error'); return; }
    troneraState.formModo = 'editar';
    troneraState.formData = { ...tronera };
    renderTroneraForm(tronera);
}

function trCerrarModal() {
    document.getElementById('trOverlay')?.remove();
}

/* ══════════════════════════════════════════════════════════════════
   ACCIONES — desasignar, liberar, eliminar
   ══════════════════════════════════════════════════════════════════ */
async function trDesasignar(troneraId, cargaNumero) {
    if (!confirm(`¿Cancelar la asignación de la carga #${cargaNumero}?`)) return;
    try {
        const res = await trFetch({ action: 'tronera_desasignar' }, 'POST', { carga_numero: cargaNumero });
        if (res.ok) {
            trToast('Asignación cancelada', 'ok');
            trCargarLista();
        } else {
            trToast(res.error || 'Error al desasignar', 'error');
        }
    } catch (e) {
        trToast('Error de red', 'error');
    }
}

async function trLiberar(troneraId, cargaNumero) {
    if (!confirm(`¿Liberar la tronera de la carga #${cargaNumero}? Esto marca la operación como finalizada.`)) return;
    try {
        const res = await trFetch({ action: 'tronera_liberar' }, 'POST', { carga_numero: cargaNumero });
        if (res.ok) {
            trToast('Tronera liberada', 'ok');
            trCargarLista();
        } else {
            trToast(res.error || 'Error al liberar', 'error');
        }
    } catch (e) {
        trToast('Error de red', 'error');
    }
}

async function trEliminar(id, numero) {
    if (!confirm(`¿Desactivar la tronera ${numero}? No se puede si tiene una asignación activa.`)) return;
    try {
        const res = await trFetch({ action: 'tronera_eliminar' }, 'POST', { id });
        if (res.ok) {
            trToast('Tronera desactivada', 'ok');
            trCargarLista();
        } else {
            trToast(res.error || 'Error al desactivar', 'error');
        }
    } catch (e) {
        trToast('Error de red', 'error');
    }
}

/* ══════════════════════════════════════════════════════════════════
   PANEL DE ASIGNACIÓN — usado desde el módulo de cargas
   Llama a renderTroneraAsignar(carga_numero, planta) para mostrar
   un select con troneras disponibles y asignar.
   ══════════════════════════════════════════════════════════════════ */
async function renderTroneraAsignar(cargaNumero, planta = 'N', onSuccess = null) {
    document.getElementById('calidadModalOverlay')?.remove();
    const overlay = document.createElement('div');
    overlay.id = 'calidadModalOverlay';
    overlay.className = 'calidad-modal-overlay';
    document.body.appendChild(overlay);
    requestAnimationFrame(() => overlay.classList.add('calidad-modal-visible'));

    overlay.innerHTML = `<div class="calidad-modal" style="width:min(480px,94vw)">
        <div class="calidad-modal-header">🚪 Asignar Tronera — Carga #${cargaNumero} <button class="chk-close" id="trAsigClose">✕</button></div>
        <div class="calidad-modal-body" id="trAsignarCuerpo">
            <div class="ge-loading">Cargando troneras y responsables...</div>
        </div>
    </div>`;

    const close = () => { overlay.classList.remove('calidad-modal-visible'); setTimeout(()=>overlay.remove(),200); };
    overlay.querySelector('#trAsigClose').onclick = close;
    overlay.addEventListener('click', e => { if(e.target===overlay) close(); });

    try {
        // Cargar troneras disponibles, catálogos de cargas (responsables) y carga actual en paralelo
        const [disponibles, catalogos, cargaData] = await Promise.all([
            trFetch({ action: 'troneras_disponibles', tipo_planta: planta }),
            fetch(`${API_EQUIPOS_URL}?action=cargas_catalogos`, { credentials:'same-origin' }).then(r=>r.json()).catch(()=>({})),
            fetch(`${API_EQUIPOS_URL}?action=cargas_ver&id=${cargaNumero}`, { credentials:'same-origin' }).then(r=>r.json()).catch(()=>({}))
        ]);
        const responsables = catalogos.responsables || [];
        const respActualId = cargaData?.carga?.responsable_id || '';

        const cuerpo = document.getElementById('trAsignarCuerpo');
        if (!cuerpo) return;

        if (!Array.isArray(disponibles) || !disponibles.length) {
            cuerpo.innerHTML = '<div style="opacity:.4;text-align:center;padding:20px">No hay troneras disponibles</div>';
            return;
        }

        cuerpo.innerHTML = `
            <div class="calidad-modal-field">
                <label>TRONERA</label>
                <select id="trSelectDisp">
                    <option value="">— Seleccionar tronera —</option>
                    ${disponibles.map(t => `<option value="${t.id}">Tronera ${t.numero} — Andén ${t.anden_texto || t.anden}</option>`).join('')}
                </select>
            </div>
            <div class="calidad-modal-field">
                <label>RESPONSABLE DE CARGA${respActualId ? ' <span style="font-size:.65rem;opacity:.5">(pre-asignado)</span>' : ''}</label>
                <select id="trSelectResp">
                    <option value="">— Asignar al usuario actual —</option>
                    ${responsables.map(r => `<option value="${r.id}" ${String(r.id)===String(respActualId)?'selected':''}>${r.nombre}</option>`).join('')}
                </select>
            </div>
            <div class="calidad-modal-footer" style="margin-top:12px;padding:0;border:none">
                <button class="calidad-modal-btn calidad-modal-btn-cancel" id="trAsigCancel">Cancelar</button>
                <button class="calidad-modal-btn calidad-modal-btn-save" id="trBtnConfAsignar">🚪 Asignar Tronera</button>
            </div>`;

        overlay.querySelectorAll('select').forEach(el => { el.onkeydown = e => e.stopPropagation(); });
        document.getElementById('trAsigCancel').onclick = close;

        document.getElementById('trBtnConfAsignar').addEventListener('click', async () => {
            const troneraId = parseInt(document.getElementById('trSelectDisp').value);
            if (!troneraId) { if(typeof showToast==='function') showToast('Seleccioná una tronera'); else trToast('Seleccioná una tronera','error'); return; }

            const respUserId = document.getElementById('trSelectResp')?.value || '';
            const btn = document.getElementById('trBtnConfAsignar');
            btn.disabled = true; btn.textContent = 'Asignando...';

            try {
                const res = await trFetch(
                    { action: 'tronera_asignar' }, 'POST',
                    { carga_numero: cargaNumero, tronera_id: troneraId, responsable_id: respUserId ? parseInt(respUserId) : null }
                );
                if (res.ok) {
                    if(typeof showToast==='function') showToast('🚪 Tronera ' + (res.tronera_numero||'') + ' asignada a carga #' + cargaNumero);
                    else trToast('Tronera asignada', 'ok');
                    close();
                    if (typeof onSuccess === 'function') onSuccess(res);
                } else {
                    if(typeof showToast==='function') showToast(res.error || 'Error', 'error');
                    else trToast(res.error || 'Error', 'error');
                    btn.disabled = false; btn.textContent = '🚪 Asignar Tronera';
                }
            } catch (e) {
                if(typeof showToast==='function') showToast('Error: ' + e.message, 'error');
                btn.disabled = false;
            }
        });

    } catch (e) {
        const cuerpo = document.getElementById('trAsignarCuerpo');
        if(cuerpo) cuerpo.innerHTML = `<div class="ge-empty">Error: ${e.message}</div>`;
    }
}

/* ══════════════════════════════════════════════════════════════════
   ESTILOS — inyectados una vez
   ══════════════════════════════════════════════════════════════════ */
function trInjectStyles() {
    if (document.getElementById('tr-styles')) return;
    const style = document.createElement('style');
    style.id = 'tr-styles';
    style.textContent = `
        .tr-wrap { padding: 16px; }
        .tr-toolbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; gap:12px; flex-wrap:wrap; }
        .tr-filters { display:flex; gap:8px; flex-wrap:wrap; }
        .tr-toolbar-right { display:flex; gap:8px; }

        .tr-select, .tr-input {
            padding: 6px 10px; border-radius: 6px;
            border: 1px solid var(--color-border-secondary, #ccc);
            background: var(--color-background-primary, #fff);
            color: var(--color-text-primary, #000);
            font-size: 13px; min-width: 130px;
        }
        textarea.tr-input { resize: vertical; font-family: inherit; }

        .tr-btn {
            padding: 6px 14px; border-radius: 6px; font-size: 13px;
            font-weight: 500; cursor: pointer; border: 1px solid transparent;
            transition: opacity .15s;
        }
        .tr-btn:disabled { opacity:.5; cursor:default; }
        .tr-btn-primary   { background:#2563EB; color:#fff; border-color:#2563EB; }
        .tr-btn-secondary { background:transparent; color:var(--color-text-primary,#333); border-color:var(--color-border-secondary,#ccc); }
        .tr-btn-warning   { background:#F59E0B; color:#fff; border-color:#F59E0B; }
        .tr-btn-success   { background:#10B981; color:#fff; border-color:#10B981; }
        .tr-btn-danger    { background:#EF4444; color:#fff; border-color:#EF4444; }
        .tr-btn-xs        { padding:3px 8px; font-size:12px; }

        .tr-table { width:100%; border-collapse:collapse; font-size:13px; }
        .tr-table th { background:var(--color-background-secondary,#f5f5f5); padding:8px 10px; text-align:left; font-weight:600; border-bottom:2px solid var(--color-border-tertiary,#e5e5e5); }
        .tr-table td { padding:8px 10px; border-bottom:1px solid var(--color-border-tertiary,#e5e5e5); vertical-align:middle; }
        .tr-table tr:hover td { background:var(--color-background-secondary,#fafafa); }
        .tr-acciones { display:flex; gap:4px; flex-wrap:wrap; }
        .tr-pos { font-family:monospace; color:var(--color-text-secondary,#666); }
        .tr-libre { color:var(--color-text-secondary,#888); font-size:12px; }

        .tr-badge { display:inline-block; padding:2px 8px; border-radius:20px; font-size:11px; font-weight:600; }
        .badge-green  { background:#D1FAE5; color:#065F46; }
        .badge-yellow { background:#FEF3C7; color:#92400E; }
        .badge-gray   { background:#F3F4F6; color:#6B7280; border:1px solid #E5E7EB; }
        .badge-blue   { background:#DBEAFE; color:#1E40AF; }
        .badge-orange { background:#FEF3C7; color:#B45309; }
        .badge-red    { background:#FEE2E2; color:#991B1B; }

        .tr-loading { padding:32px; text-align:center; color:var(--color-text-secondary,#666); }
        .tr-empty   { padding:32px; text-align:center; color:var(--color-text-secondary,#999); font-size:14px; }
        .tr-error   { padding:16px; color:#DC2626; background:#FEF2F2; border-radius:6px; font-size:13px; }

        .tr-overlay {
            position:fixed; inset:0; background:rgba(0,0,0,.45);
            display:flex; align-items:center; justify-content:center;
            z-index:9000;
        }
        .tr-modal {
            background:var(--color-background-primary,#fff);
            border-radius:12px; padding:0; max-width:640px; width:94%;
            max-height:90vh; overflow-y:auto;
            box-shadow:0 8px 32px rgba(0,0,0,.18);
        }
        .tr-modal-sm { max-width:440px; }
        .tr-modal-header {
            display:flex; justify-content:space-between; align-items:center;
            padding:16px 20px; border-bottom:1px solid var(--color-border-tertiary,#eee);
        }
        .tr-modal-header h3 { margin:0; font-size:16px; font-weight:600; }
        .tr-modal-close {
            background:none; border:none; font-size:18px; cursor:pointer;
            color:var(--color-text-secondary,#666); padding:4px;
        }
        .tr-modal-body { padding:20px; }
        .tr-modal-footer {
            display:flex; justify-content:flex-end; gap:8px;
            padding:16px 20px; border-top:1px solid var(--color-border-tertiary,#eee);
        }

        .tr-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        .tr-form-group { display:flex; flex-direction:column; gap:5px; }
        .tr-form-group label { font-size:12px; font-weight:600; color:var(--color-text-secondary,#555); }
        .tr-form-group-full { grid-column:1/-1; }

        .tr-toast {
            position:fixed; bottom:24px; right:24px; z-index:9999;
            padding:10px 18px; border-radius:8px; font-size:13px; font-weight:500;
            box-shadow:0 4px 12px rgba(0,0,0,.15); animation:trSlideIn .2s ease;
        }
        .tr-toast-ok    { background:#10B981; color:#fff; }
        .tr-toast-error { background:#EF4444; color:#fff; }
        @keyframes trSlideIn { from { transform:translateY(16px); opacity:0; } to { transform:none; opacity:1; } }
    `;
    document.head.appendChild(style);
}

/* ══════════════════════════════════════════════════════════════════
   INTEGRACIÓN CON app.js
   Agregar este bloque donde se define el menú principal (menuItems)
   o equivalente en app.js:

   {
       id:     'troneras',
       label:  'TRONERAS',
       icon:   '🚛',
       color:  '#7C3AED',
       fn:     renderTroneras,
       modulo: 'troneras',
   }

   También se puede llamar desde el módulo de cargas para asignar:
       renderTroneraAsignar(cargaNumero, planta, (res) => {
           // actualizar la UI de cargas
       });
   ══════════════════════════════════════════════════════════════════ */
