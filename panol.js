/**
 * ============================================================
 *  panol.js — Módulo Pañol v2 | Grupo CINA
 * ============================================================
 *
 *  ROLES  (mapeados a RolID de tabla Roles):
 *  ─────────────────────────────────────────────────────────
 *  OPERARIO  (todos los demás) → solo puede SOLICITAR
 *  SUPERVISOR/ENCARGADO        → SOLICITAR + APROBAR + RECHAZAR
 *  PAÑOLERO  (RolID=32)        → SOLICITAR + APROBAR + RECHAZAR
 *                                + ENTREGAR + DEVOLVER + AJUSTAR STOCK
 *  ADMIN     (RolID=1,3,4,5)   → acceso total + NUEVO ÍTEM + EDITAR ÍTEM
 *
 *  El rol llega en  window.__cinaSession.rol_id  (número entero).
 *  La sesión la inyecta index.php con window.__cinaSession = {...}.
 * ============================================================
 */

// ── Mapa de permisos por RolID ────────────────────────────────
const PERMS_MAP = (() => {
    const ADMIN_PRINCIPAL_ID = 1;                  // Administrador Principal — acceso total
    const ADMIN_IDS          = [1, 3, 4, 5];       // Administradores (acceso general)
    const ENCARGADO_IDS      = [2, 6, 9, 19, 30];  // Supervisores / jefes de área
    const PANOLERO_IDS       = [32];               // Pañolero

    // Map de permisos del pañol → claves de rol_permisos_extra (checkboxes en Configuración → Roles)
    const EXTRA_KEY = {
        solicitar:  'panol_solicitar',
        aprobar:    'panol_aprobar',
        rechazar:   'panol_aprobar',
        entregar:   'panol_entregar',
        devolver:   'panol_entregar',
        ajustar:    'panol_ajustar',
        recibir:    'panol_entregar',
        nuevoItem:  'panol_nuevo_item',
        editarItem: 'panol_nuevo_item',
    };

    return {
        isAdminPrincipal(rid) { return rid === ADMIN_PRINCIPAL_ID; },
        isAdmin(rid)          { return ADMIN_IDS.includes(rid); },
        isPanolero(rid)       { return PANOLERO_IDS.includes(rid); },
        isEncargado(rid)      { return ENCARGADO_IDS.includes(rid); },

        can(rid, perm) {
            // 1) Admins del sistema (RolID 1/5/10) siempre tienen acceso
            if ([1,5,10].includes(rid)) return true;

            // 2) Prioridad: consultar permisos extra configurados desde "Roles"
            //    (window.perfilData.permisos_extra es inyectado por app.js)
            const extraKey = EXTRA_KEY[perm];
            const extras   = (window.perfilData && window.perfilData.permisos_extra) || {};
            if (extraKey && extras[extraKey] === true) return true;

            // 3) Fallback legacy: reglas por RolID hardcoded (compatibilidad)
            const ap = this.isAdminPrincipal(rid);
            const a  = this.isAdmin(rid);
            const p  = this.isPanolero(rid);
            const e  = this.isEncargado(rid);
            switch (perm) {
                case 'solicitar':  return true;           // todos pueden solicitar
                case 'aprobar':    return a || p || e;
                case 'rechazar':   return a || p || e;
                case 'entregar':   return a || p;
                case 'devolver':   return a || p;
                case 'ajustar':    return a || p;
                case 'recibir':    return a || p;
                case 'nuevoItem':  return ap;
                case 'editarItem': return a || p;
                default:           return false;
            }
        },

        label(rid) {
            if (this.isAdminPrincipal(rid)) return { txt: 'Administrador', cls: 'role-admin' };
            if (this.isAdmin(rid))          return { txt: 'Administrador', cls: 'role-admin' };
            if (this.isPanolero(rid))       return { txt: 'Pañolero',      cls: 'role-panolero' };
            if (this.isEncargado(rid))      return { txt: 'Encargado',     cls: 'role-encargado' };
            return                                 { txt: 'Operario',      cls: 'role-operario' };
        },
    };
})();

function getRolId() {
    const s = window.__cinaSession || {};
    // Prueba todas las claves posibles que el PHP puede emitir
    const raw = s.rol_id    !== undefined ? s.rol_id    :
                s.RolID     !== undefined ? s.RolID     :
                s.rolID     !== undefined ? s.rolID     :
                s.role_id   !== undefined ? s.role_id   :
                s.rolusuario!== undefined ? s.rolusuario:
                s.rol       !== undefined ? s.rol       : 0;
    const rid = parseInt(raw, 10) || 0;
    // Log de diagnóstico (se puede quitar en producción)
    if (rid === 0 && Object.keys(s).length) {
        console.warn('[Pañol] rol_id no encontrado en __cinaSession. Claves disponibles:', Object.keys(s));
    }
    return rid;
}
function can(perm)  { return PERMS_MAP.can(getRolId(), perm); }
function rolLabel() { return PERMS_MAP.label(getRolId()); }

// ── API helper ────────────────────────────────────────────────
const PanolAPI = {
    BASE: 'api_panol.php',
    async get(action, params = {}) {
        const qs = new URLSearchParams({ action, ...params }).toString();
        const r  = await fetch(`${this.BASE}?${qs}`);
        const d  = await r.json();
        if (!r.ok) throw d;
        return d;
    },
    async post(action, data = {}) {
        const r = await fetch(`${this.BASE}?action=${action}`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(data),
        });
        const d = await r.json();
        if (!r.ok) throw d;
        return d;
    },
};

// ── Estado global ─────────────────────────────────────────────
const PS = {
    tab:         'stock',
    items:       [],
    prestamos:   [],
    solicitudes: [],
    kpis:        {},
    catFilter:   'Todos',
    searchQ:     '',
    estadoFilter:'',   // 'stock_bajo' | 'sin_stock' | ''
    carrito:     [],   // [{ item_id, nombre, numero_item, cantidad, motivo_item, tipo_pedido, dias_prestamo }]
    personas:    [],   // lista de personas activas para selector
};

const CATS    = ['Herramientas','Consumibles','EPP','Eléctrico','Vestimenta','Jardín','Otros'];
const CAT_CLS = {
    'Herramientas':'chip-herr','Consumibles':'chip-cons','EPP':'chip-epis',
    'Eléctrico':'chip-elec','Vestimenta':'chip-vest','Jardín':'chip-jard',
    'Limpieza':'chip-lim','Otros':'chip-cons',
};
const TIPO_COLOR = {
    egreso:'#f87171', devolucion:'#4ade80', ingreso:'#60a5fa',
    ajuste_ingreso:'#fbbf24', ajuste_egreso:'#fb923c',
};
const TIPO_LABEL = {
    egreso:'Entrega', devolucion:'Devolución', ingreso:'Ingreso',
    ajuste_ingreso:'Ajuste +', ajuste_egreso:'Ajuste –',
};

// ════════════════════════════════════════════════════════════
//  PUNTO DE ENTRADA
// ════════════════════════════════════════════════════════════
window.renderPanol = async function () {
    const wrap = document.querySelector('.menu-wrap');
    if (!wrap) return;
    wrap.classList.add('expanded');

    injectStyles();
    wrap.innerHTML = shellHTML();

    document.getElementById('panolTabs').addEventListener('click', e => {
        const btn = e.target.closest('[data-tab]');
        if (!btn) return;
        document.querySelectorAll('.panol-tab').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        PS.tab = btn.dataset.tab;
        renderTab();
    });

    document.getElementById('panolDetail')?.addEventListener('click', e => {
        if (e.target.id === 'detailClose') closeDetail();
    });
    document.getElementById('panolModal')?.addEventListener('click', e => {
        if (e.target.id === 'panolModal') closeModal();
    });

    // Actualizar título del sistema en el header global de index.php
    const titleEl    = document.getElementById('screenTitle');
    const subtitleEl = document.getElementById('screenSubtitle');
    if (titleEl)    titleEl.textContent    = 'PAÑOL — Control de Herramientas e Insumos';
    if (subtitleEl) subtitleEl.textContent = 'Gestión de stock, préstamos y solicitudes';

    await Promise.all([loadKpis(), loadItems(), loadPersonas()]);
    renderBottomNav();
};

// ── Shell HTML principal ──────────────────────────────────────
function shellHTML() {
    const rl = rolLabel();
    const s  = window.__cinaSession || {};

    const tabSolicitudes = can('aprobar')
        ? `<button class="panol-tab" data-tab="solicitudes">
               Solicitudes <span class="tab-badge" id="tabBadgeSol">0</span>
           </button>` : '';

    return `
<div class="panol-wrap" id="panolRoot">

  <header class="panol-topbar">
    <div class="panol-brand">
      <div class="panol-icon">🔧</div>
    </div>

    <nav class="panol-tabs" id="panolTabs">
      <button class="panol-tab active" data-tab="stock">Stock</button>
      <button class="panol-tab" data-tab="prestamos">
        Préstamos <span class="tab-badge" id="tabBadgePrest">0</span>
      </button>
      ${tabSolicitudes}
      <button class="panol-tab" data-tab="historial">Historial</button>
    </nav>

    <div class="panol-user-badge">
      <span class="role-chip ${rl.cls}">${rl.txt}</span>
      <span class="user-name">${esc(s.nombre || s.usuario || '')}</span>
    </div>
  </header>

  <div class="panol-kpi-strip" id="panolKpis">${kpiSkeleton()}</div>

  <div class="panol-layout">
    <main class="panol-body" id="panolBody">
      <div class="panol-loading"><div class="spin"></div>Cargando...</div>
    </main>

    <aside class="panol-detail" id="panolDetail">
      <div class="detail-header">
        <div class="detail-title" id="dNombre">—</div>
        <button class="detail-close" id="detailClose">✕</button>
      </div>
      <div class="detail-body" id="detailBody"></div>
      <div class="detail-actions" id="detailActions"></div>
    </aside>
  </div>

  <!-- Bottom nav (solo mobile) -->
  <nav class="panol-bottom-nav" id="panolBottomNav"></nav>

</div>

<div id="panolModal" style="display:none;position:fixed;inset:0;z-index:500;
     background:rgba(0,0,0,.72);backdrop-filter:blur(6px);
     align-items:center;justify-content:center;padding:16px">
  <div class="modal-box" id="panolModalBox"></div>
</div>`;
}

// ── Bottom nav mobile ────────────────────────────────────────
function renderBottomNav() {
    const nav = document.getElementById('panolBottomNav');
    if (!nav) return;

    const tabs = [
        { tab: 'stock',       icon: '📦', label: 'Stock',      badgeId: '' },
        { tab: 'prestamos',   icon: '🔄', label: 'Préstamos',  badgeId: 'bnBadgePrest' },
        ...(can('aprobar') ? [{ tab: 'solicitudes', icon: '📋', label: 'Solicitudes', badgeId: 'bnBadgeSol' }] : []),
        { tab: 'historial',   icon: '📜', label: 'Historial',  badgeId: '' },
        ...(can('recibir') ? [{ tab: '__carrito', icon: '🛒', label: 'Pedido', badgeId: 'bnBadgeCart' }] : []),
    ];

    nav.innerHTML = tabs.map(t => `
        <button class="pbn-btn${PS.tab === t.tab ? ' active' : ''}"
                data-tab="${t.tab}" onclick="pbnClick('${t.tab}')">
            <span class="pbn-icon">${t.icon}</span>
            <span>${t.label}</span>
            ${t.badgeId ? `<span class="pbn-badge" id="${t.badgeId}" style="display:none">0</span>` : ''}
        </button>`).join('');
}

window.pbnClick = function(tab) {
    if (tab === '__carrito') { openModalCarrito(); return; }
    document.querySelectorAll('.panol-tab,.pbn-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
    PS.tab = tab;
    renderTab();
    renderBottomNav();
};

// ── KPI Strip ─────────────────────────────────────────────────
function kpiSkeleton() {
    return Array(6).fill(0).map(() =>
        `<div class="kpi-card"><div class="kpi-val skeleton">—</div><div class="kpi-lbl">...</div></div>`
    ).join('');
}

async function loadKpis() {
    try {
        const k = await PanolAPI.get('kpis');
        PS.kpis = k;

        const bs = document.getElementById('tabBadgeSol');
        const bp = document.getElementById('tabBadgePrest');
        if (bs) { bs.textContent = k.solicitudes_pendientes ?? 0; bs.classList.toggle('has-items', (k.solicitudes_pendientes ?? 0) > 0); }
        if (bp) { bp.textContent = k.prestamos_activos ?? 0; bp.classList.toggle('has-items', (k.prestamos_activos ?? 0) > 0); }
        // Bottom-nav badges
        const bnSol  = document.getElementById('bnBadgeSol');
        const bnPrest= document.getElementById('bnBadgePrest');
        const bnCart = document.getElementById('bnBadgeCart');
        if (bnSol)   { const v=k.solicitudes_pendientes??0; bnSol.textContent=v; bnSol.style.display=v>0?'flex':'none'; }
        if (bnPrest) { const v=k.prestamos_activos??0; bnPrest.textContent=v; bnPrest.style.display=v>0?'flex':'none'; }
        if (bnCart)  { const v=PS.carrito.reduce((s,r)=>s+r.cantidad,0); bnCart.textContent=v; bnCart.style.display=v>0?'flex':'none'; }

        document.getElementById('panolKpis').innerHTML = [
            { val: k.total_items ?? '—',             lbl: 'Ítems en catálogo', cls: '',           click: '' },
            { val: k.prestamos_activos ?? '—',        lbl: 'Préstamos activos', cls: 'kpi-blue',  click: `onclick="pSetTab('prestamos')"` },
            { val: k.stock_bajo ?? '—',               lbl: 'Stock bajo mínimo', cls: (k.stock_bajo>0?'kpi-yellow':''), click: `onclick="pFiltrarEstado('stock_bajo')"` },
            { val: k.sin_stock ?? '—',                lbl: 'Sin stock',         cls: (k.sin_stock>0?'kpi-red':''),   click: `onclick="pFiltrarEstado('sin_stock')"` },
            { val: k.solicitudes_pendientes ?? '—',   lbl: 'Solicitudes pend.', cls: (k.solicitudes_pendientes > 0 ? 'kpi-orange kpi-pulse' : ''), click: can('aprobar') ? `onclick="pSetTab('solicitudes')"` : '' },
            { val: k.prestamos_vencidos ?? '—',       lbl: 'Vencidos',          cls: (k.prestamos_vencidos > 0 ? 'kpi-red' : ''), click: `onclick="pSetTab('prestamos')"` },
        ].map(c => `
            <div class="kpi-card ${c.cls}" ${c.click} style="${c.click ? 'cursor:pointer' : ''}">
              <div class="kpi-val">${c.val}</div>
              <div class="kpi-lbl">${c.lbl}</div>
            </div>`).join('');
    } catch(e) { console.error('KPI error', e); }
}

// ── Cargar personas activas ──────────────────────────────────
async function loadPersonas() {
    try {
        // Endpoint genérico de personas activas del sistema CINA
        const d = await fetch('api_2026ia.php?action=personas_activas');
        if (!d.ok) return;
        const json = await d.json();
        PS.personas = (json.personas ?? json.data ?? []).map(p =>
            typeof p === 'string' ? p : (p.nombre_completo || p.nombre || p.NombreCompleto || `${p.nombre||''} ${p.apellido||''}`.trim())
        ).filter(Boolean).sort();
    } catch(e) {
        // Si el endpoint no existe todavía, silenciar el error
        PS.personas = [];
    }
}

function personasSelect(idEl, placeholder = 'Seleccionar persona…') {
    if (PS.personas.length) {
        return `<select class="mf-select" id="${idEl}">
            <option value="">— ${placeholder} —</option>
            ${PS.personas.map(p => `<option value="${p}">${p}</option>`).join('')}
        </select>`;
    }
    // Fallback: input libre si la API no devolvió personas
    return `<input class="mf-input" type="text" id="${idEl}" placeholder="${placeholder}">`;
}

// ── Router de tabs ────────────────────────────────────────────
async function renderTab() {
    closeDetail();
    renderBottomNav();
    const body = document.getElementById('panolBody');
    if (!body) return;
    body.innerHTML = `<div class="panol-loading"><div class="spin"></div>Cargando...</div>`;
    if (PS.tab === 'stock')       { await loadItems();       return; }
    if (PS.tab === 'prestamos')   { await loadPrestamos();   return; }
    if (PS.tab === 'solicitudes') { await loadSolicitudes(); return; }
    if (PS.tab === 'historial')   { await loadHistorial();   return; }
}

window.pSetTab = function (tab) {
    document.querySelectorAll('.panol-tab').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
    PS.tab = tab;
    renderTab();
};

// ════════════════════════════════════════════════════════════
//  TAB STOCK
// ════════════════════════════════════════════════════════════
async function loadItems() {
    try {
        const p = {};
        if (PS.catFilter !== 'Todos') p.cat = PS.catFilter;
        if (PS.searchQ) p.q = PS.searchQ;
        const d = await PanolAPI.get('items', p);
        PS.items = d.items ?? [];
        renderStock();
    } catch(e) { bodyError('Error cargando ítems.'); }
}

function renderStock() {
    const body  = document.getElementById('panolBody');
    if (!body) return;
    const items = filteredItems();

    const alertPend = can('aprobar') && (PS.kpis.solicitudes_pendientes ?? 0) > 0
        ? `<div class="alert-bar alert-orange">
             <span class="alert-icon">🔔</span>
             <span><strong>${PS.kpis.solicitudes_pendientes} solicitud${PS.kpis.solicitudes_pendientes !== 1 ? 'es' : ''}</strong> esperan aprobación</span>
             <button class="alert-action" onclick="pSetTab('solicitudes')">Ver ahora →</button>
           </div>` : '';

    const alertVenc = can('devolver') && (PS.kpis.prestamos_vencidos ?? 0) > 0
        ? `<div class="alert-bar alert-red">
             <span class="alert-icon">⚠</span>
             <span><strong>${PS.kpis.prestamos_vencidos} préstamo${PS.kpis.prestamos_vencidos !== 1 ? 's' : ''}</strong> vencido${PS.kpis.prestamos_vencidos !== 1 ? 's' : ''} sin devolver</span>
             <button class="alert-action" onclick="pSetTab('prestamos')">Gestionar →</button>
           </div>` : '';

    const filtroEstadoBadge = PS.estadoFilter
        ? `<div class="alert-bar" style="background:rgba(54,176,201,.1);border:1px solid rgba(54,176,201,.25);color:#36B0C9;padding:7px 14px">
             <span>Filtrando: <strong>${PS.estadoFilter === 'sin_stock' ? 'Sin stock' : 'Stock bajo mínimo'}</strong></span>
             <button class="alert-action" onclick="PS.estadoFilter='';renderStock()">✕ Quitar filtro</button>
           </div>` : '';

    body.innerHTML = `
      ${alertPend}${alertVenc}${filtroEstadoBadge}
      <div class="panol-toolbar">
        <div class="search-wrap">
          <span class="search-icon">⌕</span>
          <input class="panol-search" placeholder="Buscar ítem, número o categoría…"
                 value="${esc(PS.searchQ)}" oninput="pDebounce(this.value)">
        </div>
        <div class="filter-chips">
          <button class="filter-chip ${PS.catFilter === 'Todos' ? 'active' : ''}" onclick="pFilterCat('Todos')">Todos</button>
          ${CATS.map(c => `<button class="filter-chip ${PS.catFilter === c ? 'active' : ''}" onclick="pFilterCat('${c}')">${c}</button>`).join('')}
        </div>
        ${can('nuevoItem') ? `<button class="btn-primary" onclick="openModalNuevoItem()">+ Nuevo ítem</button>` : ''}
        ${can('recibir') ? `<button class="btn-recepcion" onclick="openModalRecepcion()">📥 Recepción</button>` : ''}
        ${can('solicitar') ? `<button class="btn-carrito" id="btnCarrito" onclick="openModalCarrito()">
          🛒 <span id="carritoCount">0</span> artículo(s)
        </button>` : ''}
      </div>

      <div class="panol-table-wrap">
        <table class="panol-table">
          <thead><tr>
            <th>N° Ítem</th><th>Nombre</th><th>Categoría</th>
            <th class="ta-r">Stock</th><th class="ta-r">Mín.</th><th class="ta-r">Prest.</th>
            <th class="ta-c">Estado</th><th>Ubicación</th><th class="ta-c">Acciones</th>
          </tr></thead>
          <tbody>
            ${items.length ? items.map(rowStock).join('') :
              '<tr><td colspan="9" class="panol-empty">No se encontraron ítems</td></tr>'}
          </tbody>
        </table>
      </div>
      <div class="panol-footer">
        ${items.length} ítem${items.length !== 1 ? 's' : ''} ·
        Actualizado ${new Date().toLocaleTimeString('es-AR',{hour:'2-digit',minute:'2-digit'})}
      </div>`;
}

function rowStock(it) {
    const est  = estadoChip(it);
    const catC = CAT_CLS[it.categoria] ?? 'chip-cons';
    const sinStock = it.stock_actual <= 0;
    return `<tr class="pr" style="cursor:pointer" onclick="openDetail(${it.id})">
      <td><span class="item-num">${esc(it.numero_item)}</span></td>
      <td class="item-name">${esc(it.nombre)}</td>
      <td><span class="chip ${catC}">${it.categoria}</span></td>
      <td class="ta-r fw-bold ${it.stock_actual < it.stock_minimo ? 'text-red' : ''}">${it.stock_actual}</td>
      <td class="ta-r text-muted">${it.stock_minimo}</td>
      <td class="ta-r ${(it.prestados ?? 0) > 0 ? 'text-purple' : 'text-muted'}">${it.prestados ?? 0}</td>
      <td class="ta-c"><span class="estado-chip ${est.cls}">${est.txt}</span></td>
      <td class="text-muted text-sm">${esc(it.ubicacion || '—')}</td>
      <td class="ta-c">
        <div class="acc-row">
          ${can('solicitar') ? `<button class="acc-btn ${sinStock ? 'acc-disabled' : 'acc-add'}"
              ${sinStock ? 'disabled title="Sin stock"' : `onclick="event.stopPropagation();agregarAlCarrito(${it.id})"`}>
              + Pedido</button>` : ''}
          <button class="acc-btn acc-secondary"
              onclick="event.stopPropagation();openDetail(${it.id})">Ver</button>
        </div>
      </td>
    </tr>`;
}

// ════════════════════════════════════════════════════════════
//  TAB PRÉSTAMOS
// ════════════════════════════════════════════════════════════
async function loadPrestamos() {
    try {
        const params = can('devolver') ? {} : { usuario_id: (window.__cinaSession?.usuario_id ?? 0) };
        const d = await PanolAPI.get('prestamos', params);
        PS.prestamos = d.prestamos ?? [];
        const body = document.getElementById('panolBody');
        if (!body) return;

        body.innerHTML = `
          <div class="panol-table-wrap" style="flex:1">
            <table class="panol-table">
              <thead><tr>
                <th>N° Ítem</th><th>Herramienta</th>
                <th class="ta-r">Cant.</th><th>Operario</th>
                <th>Entrega</th><th>Vence</th>
                <th class="ta-c">Días</th><th class="ta-c">Estado</th>
                ${can('devolver') ? '<th class="ta-c">Acciones</th>' : ''}
              </tr></thead>
              <tbody>
                ${PS.prestamos.length ? PS.prestamos.map(rowPrestamo).join('') :
                  `<tr><td colspan="${can('devolver') ? 9 : 8}" class="panol-empty">
                     ${can('devolver') ? 'No hay préstamos activos' : 'No tenés herramientas en préstamo'}
                   </td></tr>`}
              </tbody>
            </table>
          </div>
          <div class="panol-footer">${PS.prestamos.length} préstamo${PS.prestamos.length !== 1 ? 's' : ''} activo${PS.prestamos.length !== 1 ? 's' : ''}</div>`;
    } catch(e) { bodyError('Error cargando préstamos.'); }
}

function rowPrestamo(p) {
    const dias = p.dias_prestado ?? 0;
    const venc = p.vencido == 1;
    const warn = !venc && dias > 5;
    const fVnc = p.fecha_limite ? new Date(p.fecha_limite).toLocaleDateString('es-AR') : '—';
    return `<tr class="pr">
      <td><span class="item-num">${esc(p.numero_item)}</span></td>
      <td class="item-name">${esc(p.item_nombre)}</td>
      <td class="ta-r fw-bold">${p.cantidad}</td>
      <td class="text-sm">${esc(p.usuario_nombre)}</td>
      <td class="text-muted text-sm">${new Date(p.fecha_entrega).toLocaleDateString('es-AR')}</td>
      <td class="text-sm ${venc ? 'text-red' : 'text-muted'}">${fVnc}</td>
      <td class="ta-c"><span class="dias-badge ${venc ? 'text-red fw-bold' : warn ? 'text-yellow fw-bold' : ''}">${dias}d</span></td>
      <td class="ta-c">
        ${venc  ? '<span class="estado-chip est-critico">Vencido</span>'
        : warn  ? '<span class="estado-chip est-bajo">Por vencer</span>'
                : '<span class="estado-chip est-ok">Al día</span>'}
      </td>
      ${can('devolver') ? `
      <td class="ta-c">
        <button class="acc-btn acc-primary"
          onclick="openModalDevolucion(${p.id},'${esc(p.item_nombre)}','${esc(p.usuario_nombre)}')">
          ↩ Devolver
        </button>
      </td>` : ''}
    </tr>`;
}

// ════════════════════════════════════════════════════════════
//  TAB SOLICITUDES  (encargados / pañolero / admin)
// ════════════════════════════════════════════════════════════
async function loadSolicitudes() {
    if (!can('aprobar')) { bodyError('No tenés permisos para ver solicitudes.'); return; }
    try {
        const [dPend, dAprobadas] = await Promise.all([
            PanolAPI.get('solicitudes', { estado: 'pendiente' }),
            PanolAPI.get('solicitudes', { estado: 'aprobada' }),
        ]);
        PS.solicitudes = dPend.solicitudes ?? [];
        const aprobadas = (dAprobadas.solicitudes ?? []).slice(0, 10);
        const body = document.getElementById('panolBody');
        if (!body) return;

        body.innerHTML = `
          ${PS.solicitudes.length > 0 ? `
          <div class="section-header">
            <span class="section-badge badge-orange">PENDIENTES</span>
            <span class="section-count">${PS.solicitudes.length} solicitud${PS.solicitudes.length !== 1 ? 'es' : ''}</span>
          </div>
          <div class="panol-table-wrap">
            <table class="panol-table">
              <thead><tr>
                <th>N° Ítem</th><th>Herramienta</th>
                <th class="ta-r">Cant.</th><th class="ta-r">Stock</th>
                <th>Solicitante</th><th>Motivo</th>
                <th>Hora</th><th class="ta-c">Acciones</th>
              </tr></thead>
              <tbody>${PS.solicitudes.map(rowSolicitudPendiente).join('')}</tbody>
            </table>
          </div>` : `
          <div class="empty-state">
            <div class="empty-icon">✓</div>
            <div class="empty-title">Sin solicitudes pendientes</div>
            <div class="empty-sub">Todas las solicitudes fueron procesadas</div>
          </div>`}

          ${aprobadas.length > 0 ? `
          <div class="section-header" style="margin-top:20px">
            <span class="section-badge badge-green">APROBADAS RECIENTES</span>
          </div>
          <div class="panol-table-wrap">
            <table class="panol-table">
              <thead><tr>
                <th>Herramienta</th><th class="ta-r">Cant.</th>
                <th>Operario</th><th>Aprobado por</th><th>Hora</th>
              </tr></thead>
              <tbody>${aprobadas.map(rowSolicitudAprobada).join('')}</tbody>
            </table>
          </div>` : ''}
          <div class="panol-footer">${PS.solicitudes.length} pendiente${PS.solicitudes.length !== 1 ? 's' : ''}</div>`;
    } catch(e) { bodyError('Error cargando solicitudes.'); }
}

function rowSolicitudPendiente(s) {
    const sinStock = s.stock_actual < s.cantidad_solicitada;
    return `<tr class="pr ${sinStock ? 'row-dim' : ''}">
      <td><span class="item-num">${esc(s.numero_item)}</span></td>
      <td class="item-name">${esc(s.item_nombre)}</td>
      <td class="ta-r fw-bold">${s.cantidad_solicitada}</td>
      <td class="ta-r fw-bold ${sinStock ? 'text-red' : 'text-green'}">${s.stock_actual}</td>
      <td class="text-sm">${esc(s.usuario_nombre)}</td>
      <td class="text-sm text-muted">${esc(s.motivo ?? '')}</td>
      <td class="text-sm text-muted">${new Date(s.created_at).toLocaleTimeString('es-AR',{hour:'2-digit',minute:'2-digit'})}</td>
      <td class="ta-c">
        <div class="acc-row">
          ${sinStock
            ? `<span class="acc-label-red">Sin stock</span>`
            : `<button class="acc-btn acc-green"
                 onclick="openModalAprobar(${s.id},'${esc(s.item_nombre)}',${s.cantidad_solicitada},'${esc(s.usuario_nombre)}')">
                 ✓ Aprobar
               </button>`}
          <button class="acc-btn acc-red"
                  onclick="openModalRechazar(${s.id},'${esc(s.item_nombre)}','${esc(s.usuario_nombre)}')">
            ✕ Rechazar
          </button>
        </div>
      </td>
    </tr>`;
}

function rowSolicitudAprobada(s) {
    return `<tr class="pr row-dim">
      <td class="item-name">${esc(s.item_nombre)}</td>
      <td class="ta-r fw-bold">${s.cantidad_solicitada}</td>
      <td class="text-sm">${esc(s.usuario_nombre)}</td>
      <td class="text-sm text-muted">${esc(s.encargado_nombre ?? '—')}</td>
      <td class="text-sm text-muted">${new Date(s.created_at).toLocaleTimeString('es-AR',{hour:'2-digit',minute:'2-digit'})}</td>
    </tr>`;
}

// ════════════════════════════════════════════════════════════
//  TAB HISTORIAL
// ════════════════════════════════════════════════════════════
async function loadHistorial() {
    try {
        const d    = await PanolAPI.get('historial', { limit: 100 });
        const movs = d.movimientos ?? [];
        const body = document.getElementById('panolBody');
        if (!body) return;

        body.innerHTML = `
          <div class="panol-table-wrap" style="flex:1">
            <table class="panol-table">
              <thead><tr>
                <th>Fecha / Hora</th><th class="ta-c">Tipo</th>
                <th>N° Ítem</th><th>Herramienta</th>
                <th class="ta-r">Cant.</th><th class="ta-r">Antes</th><th class="ta-r">Después</th>
                <th>Usuario</th><th>Motivo</th>
              </tr></thead>
              <tbody>
                ${movs.length ? movs.map(rowHistorial).join('') :
                  '<tr><td colspan="9" class="panol-empty">Sin movimientos registrados</td></tr>'}
              </tbody>
            </table>
          </div>
          <div class="panol-footer">${movs.length} movimiento${movs.length !== 1 ? 's' : ''}</div>`;
    } catch(e) { bodyError('Error cargando historial.'); }
}

function rowHistorial(m) {
    const col  = TIPO_COLOR[m.tipo] ?? '#94a3b8';
    const sube = m.stock_despues >= m.stock_antes;
    return `<tr class="pr">
      <td class="text-sm text-muted" style="white-space:nowrap">
        ${new Date(m.created_at).toLocaleDateString('es-AR')}<br>
        <span style="font-size:.62rem">${new Date(m.created_at).toLocaleTimeString('es-AR',{hour:'2-digit',minute:'2-digit'})}</span>
      </td>
      <td class="ta-c">
        <span class="chip" style="background:${col}22;color:${col};border:1px solid ${col}55">
          ${TIPO_LABEL[m.tipo] ?? m.tipo}
        </span>
      </td>
      <td><span class="item-num">${esc(m.numero_item)}</span></td>
      <td class="item-name">${esc(m.item_nombre)}</td>
      <td class="ta-r fw-bold">${m.cantidad}</td>
      <td class="ta-r text-muted">${m.stock_antes}</td>
      <td class="ta-r fw-bold ${sube ? 'text-green' : 'text-red'}">${m.stock_despues}</td>
      <td class="text-sm">${esc(m.usuario_nombre)}</td>
      <td class="text-sm text-muted">${esc(m.motivo ?? '')}</td>
    </tr>`;
}

// ════════════════════════════════════════════════════════════
//  PANEL DETALLE
// ════════════════════════════════════════════════════════════
window.openDetail = async function (itemId) {
    try {
        const it  = await PanolAPI.get('item', { id: itemId });
        const est = estadoChip(it);
        const pct = Math.min(100, Math.round(it.stock_actual / Math.max(it.stock_minimo * 2, 1) * 100));
        const barC = it.stock_actual < it.stock_minimo ? '#fb923c' : '#4ade80';

        document.getElementById('dNombre').textContent = it.nombre;

        // Foto del ítem (base64 o URL si el backend la devuelve)
        const fotoHTML = it.foto_url
            ? `<div class="detail-foto-wrap">
                 <img src="${esc(it.foto_url)}" class="detail-foto" alt="${esc(it.nombre)}"
                      onclick="${can('editarItem') ? `openModalFoto(${it.id})` : ''}">
                 ${can('editarItem') ? `<button class="detail-foto-edit" onclick="openModalFoto(${it.id})" title="Cambiar foto">✎</button>` : ''}
               </div>`
            : can('editarItem')
                ? `<button class="detail-foto-placeholder" onclick="openModalFoto(${it.id})">
                     <span style="font-size:1.6rem;opacity:.4">📷</span>
                     <span class="text-sm" style="color:rgba(255,255,255,.3)">Agregar foto</span>
                   </button>`
                : '';

        // Botón editar al tope (si tiene permiso)
        const editBtnTop = can('editarItem')
            ? `<button class="detail-edit-top" onclick="openModalEditarItem(${it.id})" title="Editar ítem">🖉 Editar</button>`
            : '';

        document.getElementById('detailBody').innerHTML = `
          <div class="detail-top-row">
            <div class="detail-num">${esc(it.numero_item)}</div>
            ${editBtnTop}
          </div>
          ${fotoHTML}
          <div class="stock-visual">
            <div class="sv-row">
              <div><div class="sv-num">${it.stock_actual}</div><div class="sv-lbl">en stock</div></div>
              <div style="text-align:right">
                <div class="sv-num" style="opacity:.45">${it.prestados ?? 0}</div>
                <div class="sv-lbl">prestados</div>
              </div>
            </div>
            <div class="sv-bar"><div class="sv-fill" style="width:${pct}%;background:${barC}"></div></div>
            <div class="sv-meta">Mín: ${it.stock_minimo} · <span style="color:${est.color}">${est.txt}</span></div>
          </div>
          <div id="detailActsInline" class="detail-acts-inline"></div>
          <div class="detail-field"><span class="detail-lbl">Categoría</span><span>${esc(it.categoria)}</span></div>
          <div class="detail-field"><span class="detail-lbl">Ubicación</span><span>${esc(it.ubicacion || '—')}</span></div>
          <div class="detail-field"><span class="detail-lbl">Unidad</span><span>${esc(it.unidad || '—')}</span></div>
          ${it.descripcion ? `<div class="detail-field"><span class="detail-lbl">Descripción</span><span class="text-muted text-sm">${esc(it.descripcion)}</span></div>` : ''}
          ${(it.prestamos_activos ?? []).length ? `
          <div class="detail-section-title">En poder de</div>
          <div class="detail-movs">
            ${it.prestamos_activos.map(p => `
              <div class="mov-row">
                <span class="mov-dot" style="background:#a78bfa"></span>
                <div class="mov-info">
                  <div class="mov-who">${esc(p.usuario_nombre)}</div>
                  <div class="mov-meta">${p.dias_prestado}d en préstamo</div>
                </div>
                <div class="mov-qty" style="color:#a78bfa">×${p.cantidad}</div>
                ${can('devolver') ? `
                <button class="acc-btn acc-primary" style="padding:3px 8px;font-size:.68rem"
                  onclick="openModalDevolucion(${p.id},'${esc(it.nombre)}','${esc(p.usuario_nombre)}')">
                  ↩
                </button>` : ''}
              </div>`).join('')}
          </div>` : ''}
          <div class="detail-section-title">Últimos movimientos</div>
          <div class="detail-movs">
            ${(it.movimientos ?? []).slice(0, 6).map(m => {
                const col  = TIPO_COLOR[m.tipo] ?? '#60a5fa';
                const sign = (m.tipo === 'egreso' || m.tipo === 'ajuste_egreso') ? '–' : '+';
                return `<div class="mov-row">
                  <span class="mov-dot" style="background:${col}"></span>
                  <div class="mov-info">
                    <div class="mov-who">${TIPO_LABEL[m.tipo] ?? m.tipo} · ${esc(m.usuario_nombre)}</div>
                    <div class="mov-meta">${new Date(m.created_at).toLocaleDateString('es-AR')}</div>
                  </div>
                  <div class="mov-qty" style="color:${col}">${sign}${m.cantidad}</div>
                </div>`;
            }).join('') || '<div class="text-muted text-sm">Sin movimientos</div>'}
          </div>`;

        // Acciones operativas (sin Editar — ya está arriba)
        const acts = [];
        if (can('solicitar')) {
            const disp = it.stock_actual > 0;
            acts.push(`<button class="da-btn ${disp ? 'da-primary' : 'da-disabled'}"
                ${disp ? `onclick="openModalSolicitar(${it.id})"` : 'disabled'}>
                📋 Solicitar</button>`);
        }
        if (can('entregar')) {
            acts.push(`<button class="da-btn da-green" onclick="openModalEntregarDirecto(${it.id},'${esc(it.nombre)}',${it.stock_actual})">
                📦 Entrega directa</button>`);
        }
        if (can('devolver') && (it.prestamos_activos ?? []).length > 0) {
            acts.push(`<button class="da-btn da-secondary" onclick="abrirDevolucionDesdeDetalle(${it.id})">
                ↩ Devolución</button>`);
        }
        if (can('ajustar')) {
            acts.push(`<button class="da-btn da-ghost" onclick="openModalAjuste(${it.id},'${esc(it.nombre)}',${it.stock_actual})">
                ✎ Ajustar stock</button>`);
        }
        if (can('recibir')) {
            acts.push(`<button class="da-btn da-ghost" style="border-color:rgba(96,165,250,.35);color:#93c5fd"
                onclick="openModalRecepcionItem(${it.id},'${esc(it.nombre)}')">
                📥 Recibir</button>`);
        }

        const actsHTML = acts.join('');
        const inlineEl = document.getElementById('detailActsInline');
        if (inlineEl) inlineEl.innerHTML = actsHTML;
        document.getElementById('detailActions').innerHTML = '';  // vacío — acciones están arriba
        document.getElementById('panolDetail').classList.add('open');
    } catch(e) { console.error(e); toast('Error cargando detalle.', 'error'); }
};

window.abrirDevolucionDesdeDetalle = async function (itemId) {
    try {
        const d     = await PanolAPI.get('prestamos');
        const prest = (d.prestamos ?? []).filter(p => p.item_id == itemId);
        if (!prest.length) { toast('No hay préstamos activos para este ítem.', 'warning'); return; }
        if (prest.length === 1) {
            openModalDevolucion(prest[0].id, prest[0].item_nombre, prest[0].usuario_nombre);
        } else {
            openModal(`
              <div class="modal-header">
                <div class="modal-title">Seleccionar préstamo a devolver</div>
                <button class="modal-close" onclick="closeModal()">✕</button>
              </div>
              <div class="modal-body" style="gap:8px">
                ${prest.map(p => `
                  <button class="select-row"
                    onclick="closeModal();openModalDevolucion(${p.id},'${esc(p.item_nombre)}','${esc(p.usuario_nombre)}')">
                    <div style="font-weight:700">${esc(p.usuario_nombre)}</div>
                    <div class="text-muted text-sm">Cant: ${p.cantidad} · ${p.dias_prestado}d prestado</div>
                  </button>`).join('')}
              </div>`);
        }
    } catch(e) { toast('Error cargando préstamos.', 'error'); }
};

function closeDetail() { document.getElementById('panolDetail')?.classList.remove('open'); }

// ════════════════════════════════════════════════════════════
//  MODALES
// ════════════════════════════════════════════════════════════
function openModal(html) {
    document.getElementById('panolModalBox').innerHTML = html;
    document.getElementById('panolModal').style.display = 'flex';
}
window.closeModal = function () { document.getElementById('panolModal').style.display = 'none'; };

// ── SOLICITAR ─────────────────────────────────────────────────
window.openModalSolicitar = function (itemId) {
    const it = PS.items.find(i => i.id == itemId);
    const stockMax = it ? (it.stock_disponible ?? it.stock_actual) : 99;
    openModal(`
      <div class="modal-header">
        <div class="modal-title">📋 Solicitar herramienta</div>
        <button class="modal-close" onclick="closeModal()">✕</button>
      </div>
      <div class="modal-body">
        ${it ? `<div class="modal-item-card">
          <div class="mic-name">${esc(it.nombre)}</div>
          <div class="mic-meta">N° ${esc(it.numero_item)} · Disponible: <strong style="color:#4ade80">${stockMax}</strong></div>
        </div>` : ''}
        <div class="mf-group">
          <label class="mf-label">Herramienta *</label>
          <select class="mf-select" id="mSolItem" onchange="mSolActualizarMax(this)">
            ${PS.items.filter(i => i.stock_actual > 0).map(i =>
              `<option value="${i.id}" data-max="${i.stock_disponible ?? i.stock_actual}" ${i.id == itemId ? 'selected' : ''}>${esc(i.numero_item)} — ${esc(i.nombre)} (disp: ${i.stock_disponible ?? i.stock_actual})</option>`
            ).join('')}
          </select>
        </div>
        <div class="mf-group">
          <label class="mf-label">Tipo de solicitud *</label>
          <div class="tipo-pedido-sel">
            <button class="tipo-opt active" data-tipo="retiro" onclick="solSelTipo(this)">📤 Retiro permanente</button>
            <button class="tipo-opt" data-tipo="prestamo" onclick="solSelTipo(this)">🔄 Préstamo</button>
          </div>
          <input type="hidden" id="mSolTipo" value="retiro">
        </div>
        <div id="mSolDiasWrap" style="display:none">
          <div class="mf-group">
            <label class="mf-label">Días de préstamo <span class="mf-hint">(0 = sin límite)</span></label>
            <input class="mf-input" type="number" id="mSolDias" min="0" value="7">
          </div>
        </div>
        <div class="mf-row">
          <div class="mf-group">
            <label class="mf-label">Cantidad * <span class="mf-hint" id="mSolMaxHint">(máx ${stockMax})</span></label>
            <input class="mf-input" type="number" id="mSolCant" min="1" max="${stockMax}" value="1">
          </div>
        </div>
        <div class="mf-group">
          <label class="mf-label">Motivo / Tarea *</label>
          <input class="mf-input" type="text" id="mSolMotivo" placeholder="Ej: Mantenimiento compresor cámara 3">
        </div>
        <div class="modal-info-note">
          Tu solicitud queda pendiente hasta que el Pañolero o Encargado la apruebe y realice la entrega física.
        </div>
      </div>
      <div class="modal-footer">
        <button class="mf-cancel" onclick="closeModal()">Cancelar</button>
        <button class="mf-submit" onclick="submitSolicitud()">Enviar solicitud</button>
      </div>`);
};

window.solSelTipo = function(btn) {
    document.querySelectorAll('.tipo-opt').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('mSolTipo').value = btn.dataset.tipo;
    document.getElementById('mSolDiasWrap').style.display = btn.dataset.tipo === 'prestamo' ? 'block' : 'none';
};

window.mSolActualizarMax = function(sel) {
    const opt = sel.options[sel.selectedIndex];
    const max = parseInt(opt?.dataset?.max ?? 99);
    const inp = document.getElementById('mSolCant');
    const hint = document.getElementById('mSolMaxHint');
    if (inp)  { inp.max = max; if (parseInt(inp.value) > max) inp.value = max; }
    if (hint) hint.textContent = `(máx ${max})`;
};

window.submitSolicitud = async function () {
    const item_id  = parseInt(document.getElementById('mSolItem')?.value);
    const cantidad = parseInt(document.getElementById('mSolCant')?.value);
    const motivo   = document.getElementById('mSolMotivo')?.value?.trim();
    const tipo     = document.getElementById('mSolTipo')?.value || 'retiro';
    const dias     = tipo === 'prestamo' ? parseInt(document.getElementById('mSolDias')?.value ?? 0) : 0;

    // Validar máximo
    const it = PS.items.find(i => i.id == item_id);
    const max = it ? (it.stock_disponible ?? it.stock_actual) : 9999;
    if (!item_id || !cantidad || !motivo) { toast('Completá todos los campos.', 'warning'); return; }
    if (cantidad > max) { toast(`Máximo disponible: ${max}`, 'warning'); return; }

    const motivoFinal = tipo === 'prestamo' ? `[PRÉSTAMO ${dias > 0 ? dias+'d' : 'indefinido'}] ${motivo}` : `[RETIRO] ${motivo}`;
    try {
        const r = await PanolAPI.post('solicitar', { item_id, cantidad, motivo: motivoFinal, dias_limite: dias });
        closeModal(); toast(r.mensaje, 'success');
        await loadKpis();
    } catch(e) { toast(e.error ?? 'Error al enviar solicitud.', 'error'); }
};

// ── APROBAR ───────────────────────────────────────────────────
window.openModalAprobar = function (solId, itemNombre, cantidad, solicitante) {
    openModal(`
      <div class="modal-header">
        <div class="modal-title">✓ Aprobar solicitud</div>
        <button class="modal-close" onclick="closeModal()">✕</button>
      </div>
      <div class="modal-body">
        <div class="modal-item-card" style="border-color:rgba(74,222,128,.3);background:rgba(74,222,128,.07)">
          <div class="mic-name">${esc(itemNombre)}</div>
          <div class="mic-meta">Cantidad: <strong>${cantidad}</strong> · Solicitado por: <strong>${esc(solicitante)}</strong></div>
        </div>
        <div class="mf-row">
          <div class="mf-group">
            <label class="mf-label">Días de préstamo <span class="mf-hint">(0 = sin límite)</span></label>
            <input class="mf-input" type="number" id="mAprobDias" min="0" value="0">
          </div>
        </div>
        <div class="mf-group">
          <label class="mf-label">Observaciones del pañolero</label>
          <input class="mf-input" type="text" id="mAprobObs" placeholder="Notas opcionales...">
        </div>
        <div class="modal-info-note" style="border-color:rgba(74,222,128,.25);background:rgba(74,222,128,.07)">
          Al aprobar se descuenta del stock y queda registrado el préstamo activo.
        </div>
      </div>
      <div class="modal-footer">
        <button class="mf-cancel" onclick="closeModal()">Cancelar</button>
        <button class="mf-submit mf-green" onclick="submitAprobar(${solId})">✓ Aprobar y entregar</button>
      </div>`);
};

window.submitAprobar = async function (solicitud_id) {
    const dias_limite   = parseInt(document.getElementById('mAprobDias')?.value ?? 0);
    const observaciones = document.getElementById('mAprobObs')?.value?.trim() ?? '';
    try {
        const r = await PanolAPI.post('aprobar', { solicitud_id, dias_limite, observaciones });
        closeModal(); toast(r.mensaje, 'success');
        await Promise.all([loadKpis(), renderTab()]);
    } catch(e) { toast(e.error ?? 'Error al aprobar.', 'error'); }
};

// ── RECHAZAR ──────────────────────────────────────────────────
window.openModalRechazar = function (solId, itemNombre, solicitante) {
    openModal(`
      <div class="modal-header">
        <div class="modal-title">✕ Rechazar solicitud</div>
        <button class="modal-close" onclick="closeModal()">✕</button>
      </div>
      <div class="modal-body">
        <div class="modal-item-card" style="border-color:rgba(248,113,113,.3);background:rgba(248,113,113,.07)">
          <div class="mic-name">${esc(itemNombre ?? '')}</div>
          <div class="mic-meta">Solicitado por: <strong>${esc(solicitante ?? '')}</strong></div>
        </div>
        <div class="mf-group">
          <label class="mf-label">Motivo del rechazo *</label>
          <input class="mf-input" type="text" id="mRechMotivo" placeholder="Ej: Herramienta en reparación, stock reservado...">
        </div>
      </div>
      <div class="modal-footer">
        <button class="mf-cancel" onclick="closeModal()">Cancelar</button>
        <button class="mf-submit mf-red" onclick="submitRechazar(${solId})">Rechazar</button>
      </div>`);
};

window.submitRechazar = async function (solicitud_id) {
    const motivo_rechazo = document.getElementById('mRechMotivo')?.value?.trim();
    if (!motivo_rechazo) { toast('Ingresá el motivo.', 'warning'); return; }
    try {
        const r = await PanolAPI.post('rechazar', { solicitud_id, motivo_rechazo });
        closeModal(); toast(r.mensaje, 'success');
        await Promise.all([loadKpis(), renderTab()]);
    } catch(e) { toast(e.error ?? 'Error al rechazar.', 'error'); }
};

// ── ENTREGA DIRECTA (pañolero / admin) ───────────────────────
window.openModalEntregarDirecto = function (itemId, nombre, stockActual) {
    openModal(`
      <div class="modal-header">
        <div class="modal-title">📦 Entrega directa</div>
        <button class="modal-close" onclick="closeModal()">✕</button>
      </div>
      <div class="modal-body">
        <div class="modal-item-card">
          <div class="mic-name">${esc(nombre)}</div>
          <div class="mic-meta">Stock disponible: <strong style="color:#4ade80">${stockActual}</strong></div>
        </div>
        <div class="mf-group">
          <label class="mf-label">Tipo de entrega *</label>
          <div class="tipo-pedido-sel">
            <button class="tipo-opt active" data-tipo="retiro" onclick="edirSelTipo(this)">📤 Retiro permanente</button>
            <button class="tipo-opt" data-tipo="prestamo" onclick="edirSelTipo(this)">🔄 Préstamo</button>
          </div>
          <input type="hidden" id="mEdirTipo" value="retiro">
        </div>
        <div id="mEdirDiasWrap" style="display:none">
          <div class="mf-group">
            <label class="mf-label">Días de préstamo <span class="mf-hint">(0 = sin límite)</span></label>
            <input class="mf-input" type="number" id="mEdirDias" min="0" value="7">
          </div>
        </div>
        <div class="mf-group">
          <label class="mf-label">Persona receptora *</label>
          ${personasSelect('mEdirOperario', 'Seleccionar persona activa')}
        </div>
        <div class="mf-row">
          <div class="mf-group">
            <label class="mf-label">Cantidad * <span class="mf-hint">(máx ${stockActual})</span></label>
            <input class="mf-input" type="number" id="mEdirCant" min="1" max="${stockActual}" value="1">
          </div>
        </div>
        <div class="mf-group">
          <label class="mf-label">Motivo / Tarea *</label>
          <input class="mf-input" type="text" id="mEdirMotivo" placeholder="Ej: Reparación compresor">
        </div>
        <div class="mf-group">
          <label class="mf-label">Observaciones del pañolero</label>
          <input class="mf-input" type="text" id="mEdirObs" placeholder="Notas opcionales...">
        </div>
        <div class="modal-info-note">
          Sin solicitud previa. El stock se descuenta inmediatamente al confirmar.
        </div>
      </div>
      <div class="modal-footer">
        <button class="mf-cancel" onclick="closeModal()">Cancelar</button>
        <button class="mf-submit mf-green" onclick="submitEntregarDirecto(${itemId},${stockActual})">📦 Confirmar entrega</button>
      </div>`);
};

window.edirSelTipo = function(btn) {
    document.querySelectorAll('.tipo-opt').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('mEdirTipo').value = btn.dataset.tipo;
    document.getElementById('mEdirDiasWrap').style.display = btn.dataset.tipo === 'prestamo' ? 'block' : 'none';
};

window.submitEntregarDirecto = async function (item_id, stockMax) {
    const usuario_nombre = document.getElementById('mEdirOperario')?.value?.trim();
    const cantidad       = parseInt(document.getElementById('mEdirCant')?.value ?? 0);
    const tipo           = document.getElementById('mEdirTipo')?.value || 'retiro';
    const dias_limite    = tipo === 'prestamo' ? parseInt(document.getElementById('mEdirDias')?.value ?? 0) : 0;
    const motivo         = document.getElementById('mEdirMotivo')?.value?.trim();
    const observaciones  = document.getElementById('mEdirObs')?.value?.trim() ?? '';
    if (!usuario_nombre || !cantidad || !motivo) { toast('Completá los campos obligatorios.', 'warning'); return; }
    if (stockMax && cantidad > stockMax) { toast(`Máximo disponible: ${stockMax}`, 'warning'); return; }
    const motivoFinal = tipo === 'prestamo' ? `[PRÉSTAMO ${dias_limite > 0 ? dias_limite+'d' : 'indefinido'}] ${motivo}` : `[RETIRO] ${motivo}`;
    try {
        const sol = await PanolAPI.post('solicitar', { item_id, cantidad, motivo: motivoFinal });
        await PanolAPI.post('aprobar', { solicitud_id: sol.solicitud_id, dias_limite, observaciones });
        closeModal(); closeDetail(); toast('Entrega registrada correctamente.', 'success');
        await Promise.all([loadKpis(), renderTab()]);
    } catch(e) { toast(e.error ?? 'Error al registrar entrega.', 'error'); }
};

// ── DEVOLUCIÓN ────────────────────────────────────────────────
window.openModalDevolucion = function (prestamoId, itemNombre, usuarioNombre) {
    openModal(`
      <div class="modal-header">
        <div class="modal-title">↩ Registrar devolución</div>
        <button class="modal-close" onclick="closeModal()">✕</button>
      </div>
      <div class="modal-body">
        <div class="modal-item-card" style="border-color:rgba(96,165,250,.3);background:rgba(96,165,250,.07)">
          <div class="mic-name">${esc(itemNombre)}</div>
          <div class="mic-meta">Devuelto por: <strong>${esc(usuarioNombre)}</strong></div>
        </div>
        <div class="mf-group">
          <label class="mf-label">Estado de la herramienta al devolver *</label>
          <div class="estado-selector">
            <button class="estado-opt active" data-val="bueno"   onclick="selectEstadoDev(this)">✓ Buen estado</button>
            <button class="estado-opt"        data-val="dañado"  onclick="selectEstadoDev(this)">⚠ Con daños</button>
            <button class="estado-opt estado-opt-red" data-val="perdido" onclick="selectEstadoDev(this)">✕ Extraviado</button>
          </div>
          <input type="hidden" id="mDevEstado" value="bueno">
        </div>
        <div class="mf-group">
          <label class="mf-label">Observaciones</label>
          <input class="mf-input" type="text" id="mDevObs" placeholder="Detalle de estado, daños, etc...">
        </div>
      </div>
      <div class="modal-footer">
        <button class="mf-cancel" onclick="closeModal()">Cancelar</button>
        <button class="mf-submit" style="background:linear-gradient(135deg,#36B0C9,#0284c7)"
                onclick="submitDevolucion(${prestamoId})">Confirmar devolución</button>
      </div>`);
};

window.selectEstadoDev = function (btn) {
    document.querySelectorAll('.estado-opt').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('mDevEstado').value = btn.dataset.val;
};

window.submitDevolucion = async function (prestamo_id) {
    const estado_devolucion = document.getElementById('mDevEstado')?.value;
    const observaciones     = document.getElementById('mDevObs')?.value?.trim() ?? '';
    try {
        const r = await PanolAPI.post('devolucion', { prestamo_id, estado_devolucion, observaciones });
        closeModal(); closeDetail(); toast(r.mensaje, 'success');
        await Promise.all([loadKpis(), renderTab()]);
    } catch(e) { toast(e.error ?? 'Error al registrar.', 'error'); }
};

// ── AJUSTE DE STOCK ───────────────────────────────────────────
window.openModalAjuste = function (itemId, nombre, stockActual) {
    openModal(`
      <div class="modal-header">
        <div class="modal-title">✎ Ajuste de stock</div>
        <button class="modal-close" onclick="closeModal()">✕</button>
      </div>
      <div class="modal-body">
        <div class="modal-item-card">
          <div class="mic-name">${esc(nombre)}</div>
          <div class="mic-meta">Stock actual: <strong style="color:#fff">${stockActual}</strong></div>
        </div>
        <div class="mf-group">
          <label class="mf-label">Nuevo stock *</label>
          <input class="mf-input" type="number" id="mAjNuevo" min="0" value="${stockActual}">
        </div>
        <div class="mf-group">
          <label class="mf-label">Motivo del ajuste *</label>
          <input class="mf-input" type="text" id="mAjMotivo" placeholder="Ej: Inventario físico, merma, compra nueva...">
        </div>
      </div>
      <div class="modal-footer">
        <button class="mf-cancel" onclick="closeModal()">Cancelar</button>
        <button class="mf-submit" onclick="submitAjuste(${itemId})">Confirmar ajuste</button>
      </div>`);
};

window.submitAjuste = async function (item_id) {
    const nuevo_stock = parseInt(document.getElementById('mAjNuevo')?.value);
    const motivo      = document.getElementById('mAjMotivo')?.value?.trim();
    if (isNaN(nuevo_stock) || nuevo_stock < 0) { toast('Stock inválido.', 'warning'); return; }
    if (!motivo) { toast('Ingresá el motivo.', 'warning'); return; }
    try {
        const r = await PanolAPI.post('ajuste_stock', { item_id, nuevo_stock, motivo });
        closeModal(); closeDetail(); toast(r.mensaje, 'success');
        await Promise.all([loadKpis(), loadItems()]);
    } catch(e) { toast(e.error ?? 'Error al ajustar.', 'error'); }
};

// ── NUEVO ÍTEM ────────────────────────────────────────────────
window.openModalNuevoItem = function () {
    openModal(`
      <div class="modal-header">
        <div class="modal-title">+ Nuevo ítem</div>
        <button class="modal-close" onclick="closeModal()">✕</button>
      </div>
      <div class="modal-body">
        <div class="mf-row">
          <div class="mf-group">
            <label class="mf-label">N° de ítem *</label>
            <input class="mf-input" type="text" id="mNiNum" placeholder="Ej: 1015">
          </div>
          <div class="mf-group">
            <label class="mf-label">Categoría *</label>
            <select class="mf-select" id="mNiCat">${CATS.map(c=>`<option value="${c}">${c}</option>`).join('')}</select>
          </div>
        </div>
        <div class="mf-group">
          <label class="mf-label">Nombre *</label>
          <input class="mf-input" type="text" id="mNiNombre" placeholder="Nombre de la herramienta o insumo">
        </div>
        <div class="mf-row">
          <div class="mf-group">
            <label class="mf-label">Stock inicial *</label>
            <input class="mf-input" type="number" id="mNiStock" min="0" value="1">
          </div>
          <div class="mf-group">
            <label class="mf-label">Stock mínimo *</label>
            <input class="mf-input" type="number" id="mNiMin" min="0" value="1">
          </div>
          <div class="mf-group">
            <label class="mf-label">Unidad</label>
            <input class="mf-input" type="text" id="mNiUnidad" value="unidad">
          </div>
        </div>
        <div class="mf-group">
          <label class="mf-label">Ubicación en pañol</label>
          <input class="mf-input" type="text" id="mNiUbic" placeholder="Ej: Estante A – Cajón 3">
        </div>
        <div class="mf-group">
          <label class="mf-label">Descripción</label>
          <input class="mf-input" type="text" id="mNiDesc" placeholder="Detalles, marca, modelo...">
        </div>
      </div>
      <div class="modal-footer">
        <button class="mf-cancel" onclick="closeModal()">Cancelar</button>
        <button class="mf-submit" onclick="submitNuevoItem()">Guardar ítem</button>
      </div>`);
};

window.submitNuevoItem = async function () {
    const payload = {
        numero_item:  document.getElementById('mNiNum')?.value?.trim(),
        nombre:       document.getElementById('mNiNombre')?.value?.trim(),
        categoria:    document.getElementById('mNiCat')?.value,
        stock_actual: parseInt(document.getElementById('mNiStock')?.value ?? 0),
        stock_minimo: parseInt(document.getElementById('mNiMin')?.value ?? 0),
        unidad:       document.getElementById('mNiUnidad')?.value?.trim() || 'unidad',
        ubicacion:    document.getElementById('mNiUbic')?.value?.trim(),
        descripcion:  document.getElementById('mNiDesc')?.value?.trim(),
    };
    if (!payload.numero_item || !payload.nombre) { toast('N° de ítem y nombre son obligatorios.', 'warning'); return; }
    try {
        const r = await PanolAPI.post('nuevo_item', payload);
        closeModal(); toast(r.mensaje, 'success');
        await Promise.all([loadKpis(), loadItems()]);
    } catch(e) { toast(e.error ?? 'Error al crear ítem.', 'error'); }
};

// ── EDITAR ÍTEM ───────────────────────────────────────────────
window.openModalEditarItem = async function (itemId) {
    const it = await PanolAPI.get('item', { id: itemId });
    openModal(`
      <div class="modal-header">
        <div class="modal-title">🖉 Editar ítem</div>
        <button class="modal-close" onclick="closeModal()">✕</button>
      </div>
      <div class="modal-body">
        <div class="mf-group">
          <label class="mf-label">Nombre *</label>
          <input class="mf-input" type="text" id="mEdNombre" value="${esc(it.nombre)}">
        </div>
        <div class="mf-row">
          <div class="mf-group">
            <label class="mf-label">Categoría</label>
            <select class="mf-select" id="mEdCat">${CATS.map(c=>`<option value="${c}" ${it.categoria===c?'selected':''}>${c}</option>`).join('')}</select>
          </div>
          <div class="mf-group">
            <label class="mf-label">Stock mínimo</label>
            <input class="mf-input" type="number" id="mEdMin" min="0" value="${it.stock_minimo}">
          </div>
          <div class="mf-group">
            <label class="mf-label">Unidad</label>
            <input class="mf-input" type="text" id="mEdUnidad" value="${esc(it.unidad||'unidad')}">
          </div>
        </div>
        <div class="mf-group">
          <label class="mf-label">Ubicación</label>
          <input class="mf-input" type="text" id="mEdUbic" value="${esc(it.ubicacion||'')}">
        </div>
        <div class="mf-group">
          <label class="mf-label">Descripción</label>
          <input class="mf-input" type="text" id="mEdDesc" value="${esc(it.descripcion||'')}">
        </div>
        <div class="mf-group">
          <label class="mf-label">Foto del producto</label>
          <div class="foto-upload-area" id="fotoUploadArea" onclick="document.getElementById('mEdFotoInput').click()">
            ${it.foto_url
              ? `<img id="fotoPreview" src="${esc(it.foto_url)}" class="foto-preview-img">`
              : `<div id="fotoPreview" class="foto-placeholder"><span style="font-size:2rem;opacity:.35">📷</span><span class="text-sm" style="color:rgba(255,255,255,.3)">Clic para subir foto</span></div>`}
          </div>
          <input type="file" id="mEdFotoInput" accept="image/*" style="display:none"
                 onchange="previewFoto(this)">
          <div style="display:flex;gap:6px;margin-top:5px">
            <span class="text-sm text-muted">JPG / PNG / WEBP · máx 2MB</span>
            ${it.foto_url ? `<button class="mf-cancel" style="padding:3px 10px;font-size:.68rem;margin-left:auto"
                onclick="quitarFoto(${itemId})">Quitar foto</button>` : ''}
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="mf-cancel" onclick="closeModal()">Cancelar</button>
        <button class="mf-submit" onclick="submitEditarItem(${itemId})">Guardar cambios</button>
      </div>`);
};

window.previewFoto = function(input) {
    if (!input.files?.[0]) return;
    const file = input.files[0];
    if (file.size > 2 * 1024 * 1024) { toast('La foto no puede superar 2MB.', 'warning'); input.value = ''; return; }
    const reader = new FileReader();
    reader.onload = e => {
        const area = document.getElementById('fotoPreview');
        if (!area) return;
        if (area.tagName === 'IMG') {
            area.src = e.target.result;
        } else {
            area.outerHTML = `<img id="fotoPreview" src="${e.target.result}" class="foto-preview-img">`;
        }
    };
    reader.readAsDataURL(file);
};

window.quitarFoto = async function(itemId) {
    if (!confirm('¿Quitar la foto de este ítem?')) return;
    try {
        await PanolAPI.post('editar_item', { id: itemId, quitar_foto: true });
        closeModal(); toast('Foto eliminada.', 'success');
        openDetail(itemId);
    } catch(e) { toast(e.error ?? 'Error al quitar foto.', 'error'); }
};

// Modal rápido solo para foto (desde el placeholder en el detalle)
window.openModalFoto = function(itemId) { openModalEditarItem(itemId); };

window.submitEditarItem = async function (id) {
    const fotoInput = document.getElementById('mEdFotoInput');
    let foto_base64 = null;

    // Convertir foto a base64 si se seleccionó una nueva
    if (fotoInput?.files?.[0]) {
        foto_base64 = await new Promise((res, rej) => {
            const r = new FileReader();
            r.onload  = e => res(e.target.result);  // data:image/...;base64,...
            r.onerror = () => rej(new Error('Error leyendo imagen'));
            r.readAsDataURL(fotoInput.files[0]);
        });
    }

    const payload = {
        id,
        nombre:       document.getElementById('mEdNombre')?.value?.trim(),
        categoria:    document.getElementById('mEdCat')?.value,
        stock_minimo: parseInt(document.getElementById('mEdMin')?.value ?? 0),
        unidad:       document.getElementById('mEdUnidad')?.value?.trim() || 'unidad',
        ubicacion:    document.getElementById('mEdUbic')?.value?.trim(),
        descripcion:  document.getElementById('mEdDesc')?.value?.trim(),
        ...(foto_base64 && { foto_base64 }),
    };
    try {
        const r = await PanolAPI.post('editar_item', payload);
        closeModal(); toast(r.mensaje, 'success');
        await Promise.all([loadKpis(), loadItems()]);
        openDetail(id);
    } catch(e) { toast(e.error ?? 'Error al editar.', 'error'); }
};



// ════════════════════════════════════════════════════════════
//  RECEPCIÓN MULTI-SKU
//  Pañolero / Admin registran ingreso de múltiples productos
//  en un solo acto (ej: recepción de compra, reposición)
// ════════════════════════════════════════════════════════════

// Estado del formulario de recepción
const REC = { filas: [] }; // [{ item_id, nombre, numero_item, cantidad, observacion }]

function recRenderFilas() {
    const cont = document.getElementById('recFilas');
    if (!cont) return;
    if (!REC.filas.length) {
        cont.innerHTML = `<div class="rec-empty">Buscá productos arriba para agregarlos</div>`;
        return;
    }
    cont.innerHTML = REC.filas.map((r, i) => `
      <div class="rec-fila" id="recFila_${i}">
        <div class="rec-fila-top">
          <div class="rec-fila-info">
            <span class="item-num">${esc(r.numero_item)}</span>
            <span class="rec-fila-nombre">${esc(r.nombre)}</span>
          </div>
          <div class="rec-fila-controls">
            <button class="carrito-qty-btn" onclick="recCambiarCant(${i},-1)">−</button>
            <input  class="carrito-qty-inp" type="number" min="1" value="${r.cantidad}"
                    onchange="recSetCant(${i},this.value)">
            <button class="carrito-qty-btn" onclick="recCambiarCant(${i},1)">+</button>
            <button class="carrito-del-btn" onclick="recQuitarFila(${i})">✕</button>
          </div>
        </div>
        <input class="mf-input" type="text" style="margin-top:5px;font-size:.72rem"
               placeholder="Obs. (opcional)" value="${esc(r.observacion)}"
               onchange="REC.filas[${i}].observacion=this.value">
      </div>`).join('');
    document.getElementById('recTotal').textContent =
        `${REC.filas.length} SKU · ${REC.filas.reduce((s,r)=>s+r.cantidad,0)} unidades totales`;
}

window.recCambiarCant  = (i, d) => { REC.filas[i].cantidad = Math.max(1, REC.filas[i].cantidad + d); recRenderFilas(); };
window.recSetCant      = (i, v) => { REC.filas[i].cantidad = Math.max(1, parseInt(v)||1); };
window.recQuitarFila   = (i)    => { REC.filas.splice(i, 1); recRenderFilas(); };

function recBuscar(q) {
    const res = document.getElementById('recResultados');
    if (!res) return;
    const matches = PS.items.filter(it =>
        it.nombre.toLowerCase().includes(q.toLowerCase()) ||
        it.numero_item.toLowerCase().includes(q.toLowerCase())
    ).slice(0, 8);
    if (!q.trim()) { res.innerHTML = ''; return; }
    res.innerHTML = matches.length
        ? matches.map(it => `
            <button class="rec-resultado" onclick="recAgregar(${it.id})">
              <span class="item-num">${esc(it.numero_item)}</span>
              <span style="flex:1;font-size:.78rem">${esc(it.nombre)}</span>
              <span class="text-muted text-sm">stock: ${it.stock_actual}</span>
            </button>`).join('')
        : `<div class="rec-empty">Sin resultados</div>`;
}

window.recAgregar = function(itemId) {
    const it = PS.items.find(i => i.id == itemId);
    if (!it) return;
    const existe = REC.filas.find(r => r.item_id == itemId);
    if (existe) { existe.cantidad++; }
    else { REC.filas.push({ item_id: it.id, nombre: it.nombre, numero_item: it.numero_item, cantidad: 1, observacion: '' }); }
    document.getElementById('recSearch').value = '';
    document.getElementById('recResultados').innerHTML = '';
    recRenderFilas();
};

window.openModalRecepcion = function() {
    REC.filas = [];
    openModal(`
      <div class="modal-header">
        <div class="modal-title">📥 Recepción de productos</div>
        <button class="modal-close" onclick="closeModal()">✕</button>
      </div>
      <div class="modal-body">
        <div class="mf-group">
          <label class="mf-label">Buscar productos a recibir</label>
          <div class="rec-search-wrap">
            <input class="mf-input" type="text" id="recSearch"
                   placeholder="Nombre o código interno…"
                   oninput="recBuscar(this.value)" autocomplete="off">
            <div class="rec-resultados" id="recResultados"></div>
          </div>
        </div>
        <div class="rec-filas-wrap" id="recFilas">
          <div class="rec-empty">Buscá productos arriba para agregarlos</div>
        </div>
        <div class="rec-total" id="recTotal">0 SKU · 0 unidades totales</div>
        <div class="mf-group">
          <label class="mf-label">Remito / N° de orden (opcional)</label>
          <input class="mf-input" type="text" id="recRemito" placeholder="Ej: Remito 00123, OC-2026-045…">
        </div>
        <div class="mf-group">
          <label class="mf-label">Proveedor / Origen</label>
          <input class="mf-input" type="text" id="recProveedor" placeholder="Ej: Ferretería XYZ, Depósito central…">
        </div>
        <div class="modal-info-note">
          Cada producto recibido suma al stock actual y queda registrado en el historial como ingreso.
        </div>
      </div>
      <div class="modal-footer">
        <button class="mf-cancel" onclick="closeModal()">Cancelar</button>
        <button class="mf-submit mf-green" onclick="submitRecepcion()">📥 Confirmar recepción</button>
      </div>`);
};

// Recepción rápida desde el detalle de un ítem específico
window.openModalRecepcionItem = function(itemId, nombre) {
    REC.filas = [];
    const it = PS.items.find(i => i.id == itemId);
    if (it) REC.filas.push({ item_id: it.id, nombre: it.nombre, numero_item: it.numero_item, cantidad: 1, observacion: '' });
    openModalRecepcion();
    // Forzar render con el ítem ya cargado
    setTimeout(() => recRenderFilas(), 50);
};

window.submitRecepcion = async function() {
    if (!REC.filas.length) { toast('Agregá al menos un producto.', 'warning'); return; }

    // Capturar obs finales antes de enviar (inputs pueden no haber disparado onchange)
    REC.filas.forEach((r, i) => {
        const inp = document.querySelector(`#recFila_${i} input[type=text]`);
        if (inp) r.observacion = inp.value.trim();
    });

    const remito    = document.getElementById('recRemito')?.value?.trim()    ?? '';
    const proveedor = document.getElementById('recProveedor')?.value?.trim() ?? '';
    const sufijo    = [proveedor && `Proveedor: ${proveedor}`, remito && `Remito: ${remito}`].filter(Boolean).join(' · ');

    let ok = 0, err = 0;
    for (const r of REC.filas) {
        const motivo = [`Recepción de mercadería`, sufijo, r.observacion].filter(Boolean).join(' — ');
        try {
            await PanolAPI.post('ajuste_stock', {
                item_id:     r.item_id,
                nuevo_stock: (PS.items.find(i => i.id == r.item_id)?.stock_actual ?? 0) + r.cantidad,
                motivo,
            });
            ok++;
        } catch(e) {
            toast(`Error en ${r.nombre}: ${e.error ?? 'desconocido'}`, 'error');
            err++;
        }
    }

    if (ok > 0) {
        REC.filas = [];
        closeModal();
        toast(`Recepción confirmada: ${ok} producto${ok !== 1 ? 's' : ''} ingresado${ok !== 1 ? 's' : ''}.${err > 0 ? ` ${err} con error.` : ''}`, 'success');
        await Promise.all([loadKpis(), loadItems()]);
    }
};

// ════════════════════════════════════════════════════════════
//  CARRITO / PEDIDO MULTI-ARTÍCULO
// ════════════════════════════════════════════════════════════

function actualizarBadgeCarrito() {
    const total = PS.carrito.reduce((s, r) => s + r.cantidad, 0);
    const btn   = document.getElementById('btnCarrito');
    const cnt   = document.getElementById('carritoCount');
    if (cnt) cnt.textContent = total;
    if (btn) {
        btn.classList.toggle('carrito-lleno', total > 0);
        btn.title = total > 0 ? `${total} unidad(es) en el pedido` : 'Pedido vacío';
    }
    // bottom-nav cart badge
    const bnCart = document.getElementById('bnBadgeCart');
    if (bnCart) { bnCart.textContent = total; bnCart.style.display = total > 0 ? 'flex' : 'none'; }
}

window.agregarAlCarrito = function (itemId) {
    const it = PS.items.find(i => i.id == itemId);
    if (!it || it.stock_actual <= 0) return;
    const existing = PS.carrito.find(r => r.item_id == itemId);
    if (existing) {
        // Si ya está, aumentar cantidad (respetando stock)
        if (existing.cantidad < it.stock_actual) {
            existing.cantidad++;
            toast(`${it.nombre} ×${existing.cantidad} en el pedido`, 'info');
        } else {
            toast(`Stock máximo disponible: ${it.stock_actual}`, 'warning');
        }
    } else {
        PS.carrito.push({ item_id: it.id, nombre: it.nombre, numero_item: it.numero_item, cantidad: 1, motivo_item: '', stock_max: it.stock_actual });
        toast(`${it.nombre} agregado al pedido`, 'success');
    }
    actualizarBadgeCarrito();
};

window.quitarDelCarrito = function (itemId) {
    PS.carrito = PS.carrito.filter(r => r.item_id != itemId);
    actualizarBadgeCarrito();
    // refrescar modal si está abierto
    if (document.getElementById('panolModal').style.display === 'flex') openModalCarrito();
};

window.cambiarCantCarrito = function (itemId, val) {
    const r  = PS.carrito.find(r => r.item_id == itemId);
    if (!r) return;
    const n = Math.max(1, Math.min(parseInt(val) || 1, r.stock_max));
    r.cantidad = n;
    // actualizar input sin cerrar modal
    const inp = document.getElementById(`cant_${itemId}`);
    if (inp) inp.value = n;
    actualizarBadgeCarrito();
};

window.openModalCarrito = function () {
    if (!PS.carrito.length) {
        toast('El pedido está vacío. Agregá ítems con el botón + Pedido.', 'info');
        return;
    }
    const filas = PS.carrito.map(r => `
      <div class="carrito-row">
        <div class="carrito-info">
          <span class="item-num">${esc(r.numero_item)}</span>
          <span class="carrito-nombre">${esc(r.nombre)}</span>
          <span class="text-muted text-sm">Máx: ${r.stock_max}</span>
        </div>
        <div class="carrito-controls">
          <button class="carrito-qty-btn" onclick="cambiarCantCarrito(${r.item_id}, document.getElementById('cant_${r.item_id}').value - 1)">−</button>
          <input  class="carrito-qty-inp" id="cant_${r.item_id}" type="number" min="1" max="${r.stock_max}"
                  value="${r.cantidad}" onchange="cambiarCantCarrito(${r.item_id}, this.value)">
          <button class="carrito-qty-btn" onclick="cambiarCantCarrito(${r.item_id}, document.getElementById('cant_${r.item_id}').value - -1)">+</button>
          <button class="carrito-del-btn" onclick="quitarDelCarrito(${r.item_id})" title="Quitar">✕</button>
        </div>
      </div>
      <div style="display:flex;gap:6px;margin:4px 0 4px">
        <button class="tipo-opt-sm ${r.tipo_pedido !== 'prestamo' ? 'active' : ''}"
                onclick="carritoSetTipo(${r.item_id},'retiro',this)">📤 Retiro</button>
        <button class="tipo-opt-sm ${r.tipo_pedido === 'prestamo' ? 'active' : ''}"
                onclick="carritoSetTipo(${r.item_id},'prestamo',this)">🔄 Préstamo</button>
        <input class="mf-input" type="number" id="dias_${r.item_id}" min="0" value="${r.dias_prestamo ?? 7}"
               placeholder="días" style="width:64px;display:${r.tipo_pedido === 'prestamo' ? 'block' : 'none'}"
               onchange="PS.carrito.find(x=>x.item_id==${r.item_id}).dias_prestamo=parseInt(this.value)||0">
      </div>
      <div class="mf-group" style="padding:0 0 8px 0">
        <input class="mf-input" type="text" id="mot_${r.item_id}"
               value="${esc(r.motivo_item)}"
               placeholder="Motivo para este ítem (opcional)…"
               onchange="PS.carrito.find(x=>x.item_id==${r.item_id}).motivo_item=this.value">
      </div>`).join('<div class="carrito-sep"></div>');

    const totalUds = PS.carrito.reduce((s, r) => s + r.cantidad, 0);

    openModal(`
      <div class="modal-header">
        <div class="modal-title">🛒 Pedido multi-artículo</div>
        <button class="modal-close" onclick="closeModal()">✕</button>
      </div>
      <div class="modal-body" style="max-height:55vh;overflow-y:auto">
        ${filas}
      </div>
      <div class="modal-body" style="padding-top:0;border-top:1px solid rgba(255,255,255,.08)">
        <div class="mf-group">
          <label class="mf-label">Motivo general del pedido *</label>
          <input class="mf-input" type="text" id="mCarritoMotivo"
                 placeholder="Ej: Mantenimiento preventivo cámara 3 — Turno mañana">
        </div>
        <div class="modal-info-note">
          ${totalUds} unidad${totalUds !== 1 ? 'es' : ''} en ${PS.carrito.length} ítem${PS.carrito.length !== 1 ? 's' : ''}.
          El pedido queda pendiente hasta que el Pañolero o Encargado lo apruebe ítem por ítem.
        </div>
      </div>
      <div class="modal-footer">
        <button class="mf-cancel" onclick="closeModal()">Cancelar</button>
        <button class="mf-cancel" style="color:#f87171;border-color:rgba(248,113,113,.3)"
                onclick="PS.carrito=[];actualizarBadgeCarrito();closeModal();toast('Pedido vaciado','info')">
          Vaciar
        </button>
        <button class="mf-submit" onclick="submitCarrito()">Enviar pedido</button>
      </div>`);
};

window.carritoSetTipo = function(itemId, tipo, btn) {
    const r = PS.carrito.find(x => x.item_id == itemId);
    if (!r) return;
    r.tipo_pedido = tipo;
    // Actualizar botones hermanos
    const parent = btn.parentElement;
    parent.querySelectorAll('.tipo-opt-sm').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const diasInp = document.getElementById(`dias_${itemId}`);
    if (diasInp) diasInp.style.display = tipo === 'prestamo' ? 'block' : 'none';
};

window.submitCarrito = async function () {
    const motivoGral = document.getElementById('mCarritoMotivo')?.value?.trim();
    if (!motivoGral) { toast('Ingresá el motivo general del pedido.', 'warning'); return; }

    // Capturar valores finales de inputs
    PS.carrito.forEach(r => {
        const mot  = document.getElementById(`mot_${r.item_id}`);
        const dias = document.getElementById(`dias_${r.item_id}`);
        if (mot)  r.motivo_item  = mot.value.trim();
        if (dias) r.dias_prestamo = parseInt(dias.value) || 0;
    });

    let ok = 0, err = 0;
    for (const r of PS.carrito) {
        const tipo   = r.tipo_pedido || 'retiro';
        const dias   = tipo === 'prestamo' ? (r.dias_prestamo ?? 0) : 0;
        const sufijo = tipo === 'prestamo' ? `[PRÉSTAMO ${dias > 0 ? dias+'d' : 'indefinido'}]` : '[RETIRO]';
        const motivo = r.motivo_item ? `${sufijo} ${motivoGral} — ${r.motivo_item}` : `${sufijo} ${motivoGral}`;
        try {
            await PanolAPI.post('solicitar', { item_id: r.item_id, cantidad: r.cantidad, motivo, dias_limite: dias });
            ok++;
        } catch(e) {
            toast(`Error en ${r.nombre}: ${e.error ?? 'Error desconocido'}`, 'error');
            err++;
        }
    }

    if (ok > 0) {
        PS.carrito = [];
        actualizarBadgeCarrito();
        closeModal();
        toast(`Pedido enviado: ${ok} ítem${ok !== 1 ? 's' : ''} solicitado${ok !== 1 ? 's' : ''}.${err > 0 ? ` ${err} con error.` : ''}`, ok > 0 ? 'success' : 'error');
        await loadKpis();
    }
};

// ════════════════════════════════════════════════════════════
//  UTILIDADES
// ════════════════════════════════════════════════════════════
function estadoChip(it) {
    if (it.stock_actual <= 0)               return { txt:'Sin stock',   cls:'est-critico', color:'#f87171' };
    if (it.stock_actual  < it.stock_minimo) return { txt:'Stock bajo',  cls:'est-bajo',    color:'#fb923c' };
    if ((it.prestados ?? 0) > 0)            return { txt:'En préstamo', cls:'est-prestado', color:'#a78bfa' };
    return                                         { txt:'Disponible',  cls:'est-ok',       color:'#4ade80' };
}

function filteredItems() {
    return PS.items.filter(it => {
        const matchCat = PS.catFilter === 'Todos' || it.categoria === PS.catFilter;
        const q        = PS.searchQ.toLowerCase();
        const matchQ   = !q
            || it.nombre.toLowerCase().includes(q)
            || it.numero_item.toLowerCase().includes(q)
            || (it.descripcion ?? '').toLowerCase().includes(q);
        let matchEstado = true;
        if (PS.estadoFilter === 'sin_stock')  matchEstado = it.stock_actual <= 0;
        if (PS.estadoFilter === 'stock_bajo') matchEstado = it.stock_actual > 0 && it.stock_actual < it.stock_minimo;
        return matchCat && matchQ && matchEstado;
    });
}

window.pFilterCat = function (cat) { PS.catFilter = cat; PS.estadoFilter = ''; renderStock(); };

window.pFiltrarEstado = function (estado) {
    // Ir a tab stock y aplicar filtro de estado
    PS.tab = 'stock';
    PS.estadoFilter = estado;
    PS.catFilter    = 'Todos';
    document.querySelectorAll('.panol-tab').forEach(b => b.classList.toggle('active', b.dataset.tab === 'stock'));
    loadItems();
};

let _searchTimer = null;
window.pDebounce = function (q) {
    clearTimeout(_searchTimer);
    _searchTimer = setTimeout(() => { PS.searchQ = q; PS.estadoFilter = ''; renderStock(); }, 280);
};

function esc(str) {
    if (!str && str !== 0) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function bodyError(msg) {
    const b = document.getElementById('panolBody');
    if (b) b.innerHTML = `<div class="panol-empty" style="color:#fca5a5">${msg}</div>`;
}

function toast(msg, tipo = 'success') {
    if (typeof window.showCinaToast === 'function') { window.showCinaToast(msg, tipo); return; }
    const colors = { success:'#16A34A', error:'#DC2626', warning:'#EA580C', info:'#36B0C9' };
    const t = document.createElement('div');
    t.style.cssText = `position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
        background:${colors[tipo]||colors.info};color:#fff;padding:11px 24px;border-radius:10px;
        font-weight:700;font-size:.85rem;z-index:9999;box-shadow:0 8px 28px rgba(0,0,0,.35);
        white-space:nowrap;transition:opacity .3s`;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, 3000);
}

// ════════════════════════════════════════════════════════════
//  CSS  (inyectado una sola vez)
// ════════════════════════════════════════════════════════════
function injectStyles() {
    if (document.getElementById('panol-v2-css')) return;
    const s = document.createElement('style');
    s.id = 'panol-v2-css';
    s.textContent = `
.panol-wrap{display:flex;flex-direction:column;width:100%;height:100%;min-height:0;gap:0;font-family:"Segoe UI",system-ui,sans-serif;font-size:.82rem}
.panol-topbar{display:flex;align-items:center;gap:12px;padding:10px 20px;background:rgba(255,255,255,.055);border-bottom:1px solid rgba(255,255,255,.09);flex-shrink:0;flex-wrap:wrap}
.panol-brand{display:flex;align-items:center;gap:10px;flex-shrink:0}
.panol-icon{font-size:1.35rem;width:34px;height:34px;display:flex;align-items:center;justify-content:center;background:rgba(54,176,201,.16);border-radius:9px}
.panol-brand-title{font-size:.92rem;font-weight:800;letter-spacing:.04em}
.panol-brand-sub{font-size:.66rem;color:rgba(255,255,255,.4);margin-top:1px}
.panol-tabs{display:flex;gap:4px;flex-wrap:wrap;flex:1}
.panol-tab{padding:6px 13px;border-radius:8px;border:1px solid rgba(255,255,255,.12);background:transparent;color:rgba(255,255,255,.42);font-size:.75rem;font-weight:600;cursor:pointer;transition:all .15s;white-space:nowrap;display:flex;align-items:center;gap:5px}
.panol-tab:hover{background:rgba(255,255,255,.09);color:#fff}
.panol-tab.active{background:rgba(54,176,201,.15);border-color:rgba(54,176,201,.45);color:#36B0C9}
.tab-badge{display:inline-flex;align-items:center;justify-content:center;min-width:16px;height:16px;padding:0 4px;border-radius:99px;font-size:.6rem;font-weight:900;background:rgba(255,255,255,.1);color:rgba(255,255,255,.45)}
.tab-badge.has-items{background:#f97316;color:#fff}
.panol-user-badge{display:flex;align-items:center;gap:8px;flex-shrink:0;margin-left:auto}
.user-name{font-size:.73rem;color:rgba(255,255,255,.45)}
.role-chip{padding:3px 9px;border-radius:99px;font-size:.63rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em}
.role-admin    {background:rgba(251,191,36,.16);color:#fbbf24;border:1px solid rgba(251,191,36,.28)}
.role-panolero {background:rgba(54,176,201,.16);color:#36B0C9;border:1px solid rgba(54,176,201,.28)}
.role-encargado{background:rgba(167,139,250,.16);color:#a78bfa;border:1px solid rgba(167,139,250,.28)}
.role-operario {background:rgba(255,255,255,.08);color:rgba(255,255,255,.5);border:1px solid rgba(255,255,255,.14)}
.panol-kpi-strip{display:flex;gap:8px;padding:10px 20px;flex-shrink:0;border-bottom:1px solid rgba(255,255,255,.06);overflow-x:auto;scrollbar-width:none}
.panol-kpi-strip::-webkit-scrollbar{display:none}
.kpi-card{flex:1;min-width:96px;max-width:150px;padding:9px 13px;border-radius:11px;background:rgba(255,255,255,.055);border:1px solid rgba(255,255,255,.09);transition:background .15s;cursor:default}
.kpi-card:hover{background:rgba(255,255,255,.09)}
.kpi-val{font-size:1.45rem;font-weight:900;line-height:1;margin-bottom:2px}
.kpi-lbl{font-size:.61rem;color:rgba(255,255,255,.4);font-weight:600;text-transform:uppercase;letter-spacing:.05em}
.kpi-blue .kpi-val{color:#36B0C9}
.kpi-yellow{border-color:rgba(251,191,36,.18)}.kpi-yellow .kpi-val{color:#fbbf24}
.kpi-red{border-color:rgba(248,113,113,.18)}.kpi-red .kpi-val{color:#f87171}
.kpi-orange{border-color:rgba(249,115,22,.18)}.kpi-orange .kpi-val{color:#fb923c}
.kpi-pulse{animation:kpip 2s infinite}
@keyframes kpip{0%,100%{border-color:rgba(249,115,22,.18)} 50%{border-color:rgba(249,115,22,.48)}}
.skeleton{color:rgba(255,255,255,.18)!important}
.panol-layout{display:flex;flex:1;min-height:0;overflow:hidden}
.panol-body{flex:1;min-width:0;overflow-y:auto;padding:14px 20px;display:flex;flex-direction:column;gap:10px;scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.12) transparent}
.panol-body::-webkit-scrollbar{width:4px}
.panol-body::-webkit-scrollbar-thumb{background:rgba(255,255,255,.15);border-radius:99px}
.panol-toolbar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;flex-shrink:0}
.search-wrap{position:relative;flex:1;min-width:160px}
.search-icon{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:rgba(255,255,255,.3);font-size:1rem;pointer-events:none}
.panol-search{width:100%;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.13);border-radius:8px;padding:7px 10px 7px 32px;color:#fff;font-size:.77rem;outline:none}
.panol-search::placeholder{color:rgba(255,255,255,.28)}
.panol-search:focus{border-color:rgba(54,176,201,.45);background:rgba(255,255,255,.1)}
.filter-chips{display:flex;gap:4px;flex-wrap:wrap}
.filter-chip{padding:5px 11px;border-radius:99px;border:1px solid rgba(255,255,255,.13);background:transparent;color:rgba(255,255,255,.42);font-size:.71rem;font-weight:600;cursor:pointer;transition:all .14s}
.filter-chip:hover,.filter-chip.active{background:rgba(54,176,201,.14);border-color:#36B0C9;color:#36B0C9}
.btn-primary{padding:7px 13px;border-radius:8px;border:none;background:linear-gradient(135deg,#36B0C9,#0ea5e9);color:#fff;font-size:.75rem;font-weight:700;cursor:pointer;transition:filter .15s;white-space:nowrap;flex-shrink:0}
.btn-primary:hover{filter:brightness(1.1)}
.alert-bar{display:flex;align-items:center;gap:10px;padding:9px 15px;border-radius:9px;font-size:.77rem;font-weight:600;flex-shrink:0}
.alert-orange{background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.22);color:#fb923c}
.alert-red{background:rgba(248,113,113,.09);border:1px solid rgba(248,113,113,.22);color:#f87171}
.alert-icon{font-size:.95rem;flex-shrink:0}
.alert-bar > span{flex:1}
.alert-action{margin-left:auto;padding:4px 11px;border-radius:6px;border:1px solid currentColor;background:transparent;color:inherit;font-size:.7rem;font-weight:700;cursor:pointer;transition:background .14s;flex-shrink:0}
.alert-action:hover{background:rgba(255,255,255,.1)}
.panol-table-wrap{overflow-x:auto;border-radius:11px;border:1px solid rgba(255,255,255,.08)}
.panol-table{width:100%;border-collapse:collapse;font-size:.75rem}
.panol-table thead th{padding:9px 11px;text-align:left;font-size:.62rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:rgba(255,255,255,.38);border-bottom:1px solid rgba(255,255,255,.07);background:rgba(255,255,255,.038);white-space:nowrap}
.panol-table tbody tr.pr{transition:background .12s}
.panol-table tbody tr.pr:hover{background:rgba(255,255,255,.046)}
.panol-table td{padding:9px 11px;border-bottom:1px solid rgba(255,255,255,.045);vertical-align:middle}
.panol-table tbody tr:last-child td{border-bottom:none}
.ta-r{text-align:right!important}.ta-c{text-align:center!important}
.fw-bold{font-weight:800}.text-red{color:#f87171}.text-green{color:#4ade80}
.text-yellow{color:#fbbf24}.text-purple{color:#a78bfa}
.text-muted{color:rgba(255,255,255,.38)}.text-sm{font-size:.7rem}
.item-num{font-weight:900;color:#36B0C9;font-size:.72rem}
.item-name{font-weight:600}
.row-dim{opacity:.5}
.chip{display:inline-block;padding:2px 8px;border-radius:99px;font-size:.63rem;font-weight:700}
.chip-herr{background:rgba(59,130,246,.16);color:#93c5fd;border:1px solid rgba(59,130,246,.22)}
.chip-cons{background:rgba(34,197,94,.14);color:#86efac;border:1px solid rgba(34,197,94,.18)}
.chip-epis{background:rgba(234,179,8,.13);color:#fde68a;border:1px solid rgba(234,179,8,.18)}
.chip-elec{background:rgba(249,115,22,.13);color:#fdba74;border:1px solid rgba(249,115,22,.18)}
.chip-lim{background:rgba(167,139,250,.13);color:#c4b5fd;border:1px solid rgba(167,139,250,.18)}
.chip-vest{background:rgba(236,72,153,.13);color:#f9a8d4;border:1px solid rgba(236,72,153,.18)}
.chip-jard{background:rgba(34,197,94,.14);color:#6ee7b7;border:1px solid rgba(34,197,94,.2)}
.estado-chip{display:inline-block;padding:2px 8px;border-radius:99px;font-size:.64rem;font-weight:700;white-space:nowrap}
.est-ok     {background:rgba(74,222,128,.13);color:#4ade80;border:1px solid rgba(74,222,128,.22)}
.est-bajo   {background:rgba(251,146,60,.13);color:#fb923c;border:1px solid rgba(251,146,60,.22)}
.est-critico{background:rgba(248,113,113,.13);color:#f87171;border:1px solid rgba(248,113,113,.22)}
.est-prestado{background:rgba(167,139,250,.13);color:#a78bfa;border:1px solid rgba(167,139,250,.22)}
.dias-badge{font-weight:800;font-size:.77rem}
.acc-row{display:flex;gap:4px;justify-content:center;align-items:center;flex-wrap:wrap}
.acc-btn{padding:4px 9px;border-radius:6px;border:1px solid rgba(255,255,255,.16);background:rgba(255,255,255,.06);color:rgba(255,255,255,.7);font-size:.69rem;font-weight:700;cursor:pointer;transition:all .13s;white-space:nowrap}
.acc-btn:hover{background:rgba(255,255,255,.14);color:#fff}
.acc-primary{border-color:rgba(54,176,201,.38);color:#36B0C9;background:rgba(54,176,201,.1)}
.acc-primary:hover{background:rgba(54,176,201,.18)}
.acc-secondary{border-color:rgba(255,255,255,.18)}
.acc-green{border-color:rgba(74,222,128,.38);color:#4ade80;background:rgba(74,222,128,.09)}
.acc-green:hover{background:rgba(74,222,128,.18)}
.acc-red{border-color:rgba(248,113,113,.35);color:#f87171;background:rgba(248,113,113,.07)}
.acc-red:hover{background:rgba(248,113,113,.16)}
.acc-disabled{opacity:.3;cursor:not-allowed!important}
.acc-label-red{font-size:.67rem;font-weight:800;color:#f87171}
.section-header{display:flex;align-items:center;gap:10px;margin-bottom:6px}
.section-badge{padding:3px 10px;border-radius:6px;font-size:.61rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
.badge-orange{background:rgba(249,115,22,.16);color:#f97316;border:1px solid rgba(249,115,22,.28)}
.badge-green{background:rgba(74,222,128,.11);color:#4ade80;border:1px solid rgba(74,222,128,.22)}
.section-count{font-size:.73rem;color:rgba(255,255,255,.38);font-weight:600}
.panol-empty{text-align:center;padding:32px;color:rgba(255,255,255,.28);font-size:.79rem}
.empty-state{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:48px 24px;gap:8px}
.empty-icon{font-size:1.8rem;width:52px;height:52px;display:flex;align-items:center;justify-content:center;background:rgba(74,222,128,.1);border-radius:50%;color:#4ade80}
.empty-title{font-size:.88rem;font-weight:700;color:rgba(255,255,255,.65)}
.empty-sub{font-size:.74rem;color:rgba(255,255,255,.32)}
.panol-loading{display:flex;align-items:center;gap:10px;padding:32px;color:rgba(255,255,255,.32);font-size:.79rem;justify-content:center}
.spin{width:15px;height:15px;border:2px solid rgba(255,255,255,.13);border-top-color:#36B0C9;border-radius:50%;animation:spin .7s linear infinite;flex-shrink:0}
@keyframes spin{to{transform:rotate(360deg)}}
.panol-footer{font-size:.67rem;color:rgba(255,255,255,.28);text-align:right;padding-top:4px;flex-shrink:0}
.panol-detail{width:0;overflow:hidden;transition:width .22s ease;background:rgba(0,12,40,.65);border-left:1px solid rgba(255,255,255,.08);display:flex;flex-direction:column;flex-shrink:0}
.panol-detail.open{width:292px;overflow-y:auto;overflow-x:hidden}
.detail-header{display:flex;align-items:center;justify-content:space-between;padding:13px 15px;border-bottom:1px solid rgba(255,255,255,.08);flex-shrink:0}
.detail-title{font-size:.86rem;font-weight:800;flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.detail-close{background:none;border:none;color:rgba(255,255,255,.38);font-size:1rem;cursor:pointer;padding:2px;transition:color .13s}
.detail-close:hover{color:#fff}
.detail-body{padding:13px 15px;display:flex;flex-direction:column;gap:10px;flex:1}
.detail-num{display:inline-block;padding:3px 9px;border-radius:6px;background:rgba(54,176,201,.13);color:#36B0C9;font-size:.7rem;font-weight:900;border:1px solid rgba(54,176,201,.28)}
.stock-visual{background:rgba(255,255,255,.045);border-radius:9px;padding:11px;border:1px solid rgba(255,255,255,.07)}
.sv-row{display:flex;justify-content:space-between;margin-bottom:7px}
.sv-num{font-size:1.55rem;font-weight:900;line-height:1}
.sv-lbl{font-size:.61rem;color:rgba(255,255,255,.38);text-transform:uppercase;letter-spacing:.06em}
.sv-bar{height:5px;background:rgba(255,255,255,.09);border-radius:99px;overflow:hidden;margin-bottom:5px}
.sv-fill{height:100%;border-radius:99px;transition:width .4s ease}
.sv-meta{font-size:.67rem;color:rgba(255,255,255,.38)}
.detail-field{display:flex;gap:8px;justify-content:space-between;align-items:baseline;font-size:.75rem;border-bottom:1px solid rgba(255,255,255,.045);padding-bottom:5px}
.detail-lbl{font-size:.63rem;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.05em;flex-shrink:0}
.detail-section-title{font-size:.62rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.28);margin-top:4px}
.detail-movs{display:flex;flex-direction:column;gap:5px}
.detail-actions{padding:11px 15px;border-top:1px solid rgba(255,255,255,.07);display:flex;flex-direction:column;gap:6px;flex-shrink:0}
.da-btn{width:100%;padding:8px 13px;border-radius:8px;border:none;font-size:.76rem;font-weight:700;cursor:pointer;transition:filter .14s;text-align:center}
.da-primary {background:linear-gradient(135deg,#36B0C9,#0ea5e9);color:#fff}
.da-green   {background:linear-gradient(135deg,#16a34a,#059669);color:#fff}
.da-secondary{background:rgba(255,255,255,.09);border:1px solid rgba(255,255,255,.18);color:rgba(255,255,255,.75)}
.da-ghost   {background:transparent;border:1px solid rgba(255,255,255,.14);color:rgba(255,255,255,.5)}
.da-disabled{background:rgba(255,255,255,.04);color:rgba(255,255,255,.22);cursor:not-allowed}
.da-btn:not(.da-disabled):hover{filter:brightness(1.12)}
.mov-row{display:flex;align-items:center;gap:7px;padding:4px 0}
.mov-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}
.mov-info{flex:1;min-width:0}
.mov-who{font-size:.73rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.mov-meta{font-size:.63rem;color:rgba(255,255,255,.32)}
.mov-qty{font-size:.77rem;font-weight:800;white-space:nowrap}
.modal-box{background:linear-gradient(145deg,#001848,#000c28);border:1px solid rgba(255,255,255,.13);border-radius:17px;width:100%;max-width:510px;max-height:90vh;overflow-y:auto;box-shadow:0 32px 80px rgba(0,0,0,.6)}
.modal-header{display:flex;align-items:center;justify-content:space-between;padding:15px 19px;border-bottom:1px solid rgba(255,255,255,.08)}
.modal-title{font-size:.86rem;font-weight:800;color:#fff}
.modal-close{background:none;border:none;color:rgba(255,255,255,.38);font-size:1.05rem;cursor:pointer;padding:2px;transition:color .13s}
.modal-close:hover{color:#fff}
.modal-body{padding:17px 19px;display:flex;flex-direction:column;gap:13px}
.modal-footer{padding:13px 19px;border-top:1px solid rgba(255,255,255,.08);display:flex;gap:8px;justify-content:flex-end}
.modal-item-card{background:rgba(54,176,201,.07);border:1px solid rgba(54,176,201,.18);border-radius:9px;padding:9px 13px}
.mic-name{font-size:.84rem;font-weight:800}
.mic-meta{font-size:.71rem;color:rgba(255,255,255,.48);margin-top:2px}
.modal-info-note{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.09);border-radius:7px;padding:8px 11px;font-size:.71rem;color:rgba(255,255,255,.45)}
.mf-group{display:flex;flex-direction:column;gap:4px}
.mf-row{display:flex;gap:9px}.mf-row .mf-group{flex:1}
.mf-label{font-size:.68rem;font-weight:700;color:rgba(255,255,255,.46);text-transform:uppercase;letter-spacing:.05em}
.mf-hint{font-size:.63rem;font-weight:400;color:rgba(255,255,255,.28);text-transform:none;letter-spacing:0}
.mf-input,.mf-select{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);border-radius:7px;padding:8px 11px;color:#fff;font-size:.79rem;outline:none;transition:border-color .14s;width:100%}
.mf-input:focus,.mf-select:focus{border-color:rgba(54,176,201,.5);background:rgba(255,255,255,.11)}
.mf-input::placeholder{color:rgba(255,255,255,.22)}
.mf-select option{background:#001848}
.mf-cancel{padding:8px 17px;border-radius:7px;border:1px solid rgba(255,255,255,.16);background:transparent;color:rgba(255,255,255,.55);font-size:.76rem;font-weight:600;cursor:pointer}
.mf-cancel:hover{background:rgba(255,255,255,.07)}
.mf-submit{padding:8px 19px;border-radius:7px;border:none;background:linear-gradient(135deg,#36B0C9,#0ea5e9);color:#fff;font-size:.76rem;font-weight:700;cursor:pointer;transition:filter .14s}
.mf-submit:hover{filter:brightness(1.1)}
.mf-submit.mf-green{background:linear-gradient(135deg,#16a34a,#059669)}
.mf-submit.mf-red  {background:linear-gradient(135deg,#dc2626,#991b1b)}
.estado-selector{display:flex;gap:6px;flex-wrap:wrap}
.estado-opt{flex:1;padding:8px 9px;border-radius:7px;border:1px solid rgba(255,255,255,.16);background:rgba(255,255,255,.05);color:rgba(255,255,255,.55);font-size:.73rem;font-weight:600;cursor:pointer;transition:all .13s;text-align:center}
.estado-opt.active{background:rgba(74,222,128,.13);border-color:#4ade80;color:#4ade80}
.estado-opt-red.active{background:rgba(248,113,113,.13);border-color:#f87171;color:#f87171}
.estado-opt:hover:not(.active){background:rgba(255,255,255,.09)}
.select-row{width:100%;background:rgba(255,255,255,.055);border:1px solid rgba(255,255,255,.13);border-radius:9px;padding:10px 13px;color:#fff;text-align:left;cursor:pointer;transition:background .13s}
.select-row:hover{background:rgba(255,255,255,.11)}
.btn-recepcion{padding:7px 13px;border-radius:8px;border:1px solid rgba(96,165,250,.35);background:rgba(96,165,250,.09);color:#93c5fd;font-size:.75rem;font-weight:700;cursor:pointer;transition:all .18s;white-space:nowrap;flex-shrink:0}
.btn-recepcion:hover{background:rgba(96,165,250,.18);color:#bfdbfe}
.rec-search-wrap{position:relative}
.rec-resultados{position:absolute;top:100%;left:0;right:0;z-index:50;background:linear-gradient(145deg,#001848,#000c28);border:1px solid rgba(255,255,255,.15);border-radius:8px;margin-top:3px;max-height:220px;overflow-y:auto;display:flex;flex-direction:column;gap:2px;padding:4px}
.rec-resultado{width:100%;display:flex;align-items:center;gap:8px;padding:8px 10px;border:none;background:rgba(255,255,255,.05);color:#fff;border-radius:6px;cursor:pointer;text-align:left;transition:background .12s}
.rec-resultado:hover{background:rgba(54,176,201,.15)}
.rec-filas-wrap{display:flex;flex-direction:column;gap:6px;max-height:280px;overflow-y:auto;padding:2px 0;scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.12) transparent}
.rec-fila{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.09);border-radius:9px;padding:10px 12px;display:flex;flex-direction:column;gap:6px}
.rec-fila-top{display:flex;align-items:center;justify-content:space-between;gap:10px}
.rec-fila-info{display:flex;align-items:center;gap:8px;flex:1;min-width:0}
.rec-fila-nombre{font-size:.78rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rec-fila-controls{display:flex;align-items:center;gap:4px;flex-shrink:0}
.rec-total{font-size:.72rem;color:rgba(255,255,255,.45);font-weight:600;text-align:right;padding:4px 0}
.rec-empty{font-size:.74rem;color:rgba(255,255,255,.28);text-align:center;padding:16px}
.btn-carrito{padding:7px 13px;border-radius:8px;border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.07);color:rgba(255,255,255,.55);font-size:.75rem;font-weight:700;cursor:pointer;transition:all .18s;white-space:nowrap;flex-shrink:0;display:flex;align-items:center;gap:5px}
.btn-carrito:hover{background:rgba(255,255,255,.13);color:#fff}
.btn-carrito.carrito-lleno{border-color:rgba(54,176,201,.5);color:#36B0C9;background:rgba(54,176,201,.12)}
.acc-add{border-color:rgba(54,176,201,.35);color:#36B0C9;background:rgba(54,176,201,.09)}
.acc-add:hover{background:rgba(54,176,201,.18)}
.carrito-row{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:4px 0}
.carrito-info{display:flex;align-items:center;gap:8px;flex:1;min-width:0;flex-wrap:wrap}
.carrito-nombre{font-size:.8rem;font-weight:600;flex:1;min-width:80px}
.carrito-controls{display:flex;align-items:center;gap:4px;flex-shrink:0}
.carrito-qty-btn{width:26px;height:26px;border-radius:6px;border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.08);color:#fff;font-size:1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .13s;line-height:1}
.carrito-qty-btn:hover{background:rgba(255,255,255,.18)}
.carrito-qty-inp{width:44px;height:26px;border-radius:6px;border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.08);color:#fff;font-size:.8rem;text-align:center;outline:none;padding:0 4px}
.carrito-del-btn{width:26px;height:26px;border-radius:6px;border:1px solid rgba(248,113,113,.3);background:rgba(248,113,113,.08);color:#f87171;font-size:.75rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .13s}
.carrito-del-btn:hover{background:rgba(248,113,113,.2)}
.carrito-sep{height:1px;background:rgba(255,255,255,.07);margin:6px 0}
.detail-top-row{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:2px}
.detail-edit-top{padding:4px 10px;border-radius:6px;border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.07);color:rgba(255,255,255,.55);font-size:.68rem;font-weight:700;cursor:pointer;transition:all .14s;white-space:nowrap;flex-shrink:0}
.detail-edit-top:hover{background:rgba(255,255,255,.14);color:#fff}
.detail-foto-wrap{position:relative;border-radius:10px;overflow:hidden;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);margin-bottom:4px}
.detail-foto{width:100%;max-height:180px;object-fit:cover;display:block;cursor:pointer;transition:filter .2s}
.detail-foto:hover{filter:brightness(1.08)}
.detail-foto-edit{position:absolute;bottom:7px;right:7px;padding:4px 9px;border-radius:6px;border:none;background:rgba(0,0,0,.55);color:#fff;font-size:.7rem;font-weight:700;cursor:pointer;backdrop-filter:blur(4px)}
.detail-foto-placeholder{width:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;padding:20px;border:2px dashed rgba(255,255,255,.12);border-radius:10px;background:rgba(255,255,255,.03);cursor:pointer;transition:border-color .15s;margin-bottom:4px}
.detail-foto-placeholder:hover{border-color:rgba(54,176,201,.4)}
.foto-upload-area{border:2px dashed rgba(255,255,255,.15);border-radius:9px;overflow:hidden;cursor:pointer;min-height:80px;display:flex;align-items:center;justify-content:center;transition:border-color .15s;background:rgba(255,255,255,.03)}
.foto-upload-area:hover{border-color:rgba(54,176,201,.4)}
.foto-placeholder{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;padding:20px;width:100%}
.foto-preview-img{width:100%;max-height:200px;object-fit:cover;display:block}
.tipo-pedido-sel{display:flex;gap:6px;flex-wrap:wrap}
.tipo-opt{flex:1;padding:8px 10px;border-radius:8px;border:1px solid rgba(255,255,255,.16);background:rgba(255,255,255,.05);color:rgba(255,255,255,.5);font-size:.74rem;font-weight:700;cursor:pointer;transition:all .14s;text-align:center;min-width:120px}
.tipo-opt.active{background:rgba(54,176,201,.16);border-color:#36B0C9;color:#36B0C9}
.tipo-opt:hover:not(.active){background:rgba(255,255,255,.1)}
.tipo-opt-sm{padding:4px 10px;border-radius:6px;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.04);color:rgba(255,255,255,.42);font-size:.68rem;font-weight:700;cursor:pointer;transition:all .13s;white-space:nowrap}
.tipo-opt-sm.active{background:rgba(54,176,201,.14);border-color:#36B0C9;color:#36B0C9}
.tipo-opt-sm:hover:not(.active){background:rgba(255,255,255,.09)}
.detail-acts-inline{display:flex;flex-direction:column;gap:6px;margin-bottom:6px}
    `;
    document.head.appendChild(s);
}
