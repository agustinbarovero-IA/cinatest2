/* ═══════════════════════════════════════════════════════════════
   MÓDULO: FACTURACIÓN — facturacion.js
   Sistema Grupo CINA
   ───────────────────────────────────────────────────────────────
   Uso:
     1) Incluir este archivo en index.php DESPUÉS de app.js:
        <script src="facturacion.js"></script>

     2) En app.js, reemplazar el bloque del click de FACTURACION
        (línea ~4690) por:
        renderFacturacion();    ← ya existe, no hay que cambiar nada

     3) El módulo extiende renderFacturacion() — al cargarse este
        script, sobreescribe la función original con la versión
        mejorada que genera los resúmenes en el navegador.
   ═══════════════════════════════════════════════════════════════ */

/* ── Referencia a la URL de la API (ya definida en app.js) ─────
   Si este archivo se usa standalone, definir:
   const API_EQUIPOS_URL = '/api_2026ia.php';
   ─────────────────────────────────────────────────────────────── */

/* ══════════════════════════════════════════════════════════════
   ESTILOS PROPIOS DEL MÓDULO
   Se inyectan una sola vez en el <head>
   ══════════════════════════════════════════════════════════════ */
(function inyectarEstilos() {
  if (document.getElementById('fact-mod-styles')) return;
  const style = document.createElement('style');
  style.id = 'fact-mod-styles';
  style.textContent = `
/* ── Layout general ─────────────────────────────────────────── */
.fact-wrap          { padding: 18px 22px; max-width: 1100px; }
.fact-header        { display:flex; flex-direction:column; gap:4px; margin-bottom:18px; }
.fact-header-title  { display:flex; align-items:center; gap:10px; font-size:1.05rem;
                      font-weight:800; letter-spacing:.04em; color:#fff; }
.fact-section       { background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08);
                      border-radius:12px; padding:16px 18px; margin-bottom:14px; }
.fact-section-title { display:flex; align-items:center; gap:8px; font-size:.78rem;
                      font-weight:800; letter-spacing:.06em; color:rgba(255,255,255,.55);
                      text-transform:uppercase; margin-bottom:12px; flex-wrap:wrap; }
.fact-section-icon  { font-size:1rem; }

/* ── Período ────────────────────────────────────────────────── */
.fact-period-row     { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.fact-date-group     { display:flex; flex-direction:column; gap:4px; }
.fact-date-label     { font-size:.68rem; font-weight:700; color:rgba(255,255,255,.4);
                       text-transform:uppercase; letter-spacing:.05em; }
.fact-date-input     { background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.14);
                       border-radius:7px; color:#fff; font-size:.88rem; font-weight:600;
                       padding:7px 11px; outline:none; cursor:pointer; }
.fact-date-input:focus { border-color:rgba(54,176,201,.5); }
.fact-period-sep     { font-size:1.1rem; color:rgba(255,255,255,.3); margin-top:18px; }
.fact-period-shortcuts { display:flex; gap:6px; flex-wrap:wrap; margin-top:18px; }
.fact-shortcut-btn   { background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.12);
                       border-radius:6px; color:rgba(255,255,255,.6); font-size:.72rem;
                       font-weight:700; padding:5px 11px; cursor:pointer; transition:all .15s; }
.fact-shortcut-btn:hover  { background:rgba(54,176,201,.15); border-color:rgba(54,176,201,.35); color:#36B0C9; }
.fact-shortcut-btn.active { background:rgba(54,176,201,.2); border-color:#36B0C9; color:#36B0C9; }

/* ── Grilla de clientes ─────────────────────────────────────── */
.fact-search-wrap  { margin-bottom:10px; }
.fact-search       { width:100%; max-width:340px; background:rgba(255,255,255,.07);
                     border:1px solid rgba(255,255,255,.12); border-radius:8px;
                     color:#fff; font-size:.85rem; padding:8px 12px; outline:none;
                     box-sizing:border-box; }
.fact-search:focus { border-color:rgba(54,176,201,.45); }
.fact-clients-grid { display:flex; flex-wrap:wrap; gap:7px; min-height:48px; }
.fact-client-chip  { display:flex; align-items:center; gap:6px; background:rgba(255,255,255,.06);
                     border:1px solid rgba(255,255,255,.12); border-radius:20px;
                     padding:6px 13px 6px 9px; cursor:pointer; font-size:.78rem;
                     font-weight:700; color:rgba(255,255,255,.75); transition:all .15s;
                     white-space:nowrap; max-width:220px; overflow:hidden; }
.fact-client-chip:hover   { background:rgba(54,176,201,.12); border-color:rgba(54,176,201,.35); color:#fff; }
.fact-client-chip.selected{ background:rgba(54,176,201,.22); border-color:#36B0C9; color:#fff; }
.fact-client-chip.opero   { border-color:rgba(34,197,94,.35); }
.fact-client-chip.opero:not(.selected) { background:rgba(34,197,94,.08); }
.fact-client-chip.opero.selected { background:rgba(34,197,94,.25); border-color:#22c55e; }
.fact-chip-check   { font-size:.7rem; opacity:0; transition:opacity .12s; color:#36B0C9; }
.fact-client-chip.selected .fact-chip-check { opacity:1; }
.fact-chip-name    { overflow:hidden; text-overflow:ellipsis; }
.fact-sel-count    { font-size:.75rem; font-weight:700; color:rgba(255,255,255,.4); margin-left:6px; }
.fact-sel-all-btn, .fact-sel-none-btn {
  font-size:.68rem; font-weight:700; padding:3px 9px; border-radius:5px;
  cursor:pointer; border:1px solid rgba(255,255,255,.15); background:rgba(255,255,255,.06);
  color:rgba(255,255,255,.5); transition:all .12s; margin-left:6px; }
.fact-sel-all-btn:hover  { background:rgba(54,176,201,.15); color:#36B0C9; border-color:rgba(54,176,201,.3); }
.fact-sel-none-btn:hover { background:rgba(239,68,68,.1);   color:#EF4444; border-color:rgba(239,68,68,.25); }

/* ── Preview y descarga ─────────────────────────────────────── */
.fact-download-section .fact-section-title { margin-bottom:8px; }
.fact-download-row   { display:flex; align-items:flex-start; gap:14px; flex-wrap:wrap; }
.fact-summary-preview{ display:flex; flex-wrap:wrap; gap:6px; flex:1; min-height:38px; align-items:center; }
.fact-preview-empty  { font-size:.8rem; color:rgba(255,255,255,.25); font-style:italic; }
.fact-preview-chip   { background:rgba(249,115,22,.12); border:1px solid rgba(249,115,22,.3);
                       border-radius:6px; padding:4px 10px; }
.fact-preview-name   { font-size:.75rem; font-weight:700; color:#F97316; display:block; }
.fact-preview-period { font-size:.63rem; color:rgba(255,255,255,.4); display:block; }
.fact-download-btn   { background:linear-gradient(135deg,#F97316,#ea580c);
                       border:none; border-radius:9px; color:#fff; font-size:.85rem;
                       font-weight:800; padding:11px 22px; cursor:pointer;
                       letter-spacing:.03em; white-space:nowrap; transition:opacity .15s;
                       box-shadow:0 4px 14px rgba(249,115,22,.35); }
.fact-download-btn:disabled { opacity:.35; cursor:not-allowed; box-shadow:none; }
.fact-download-btn:not(:disabled):hover { opacity:.88; }
.fact-hint-small     { font-size:.7rem; color:rgba(255,255,255,.35); font-weight:500;
                       font-style:italic; margin-left:4px; }

/* ── Overlay de resumen generado ────────────────────────────── */
.fact-overlay        { position:fixed; inset:0; background:rgba(0,0,0,.72);
                       z-index:9900; display:flex; align-items:center; justify-content:center;
                       padding:20px; animation:factFadeIn .18s ease; }
@keyframes factFadeIn { from{opacity:0} to{opacity:1} }
.fact-modal          { background:#0d1b35; border:1px solid rgba(255,255,255,.12);
                       border-radius:16px; width:100%; max-width:780px;
                       max-height:90vh; display:flex; flex-direction:column;
                       box-shadow:0 24px 60px rgba(0,0,0,.7); }
.fact-modal-header   { display:flex; align-items:center; justify-content:space-between;
                       padding:16px 20px 12px; border-bottom:1px solid rgba(255,255,255,.08); }
.fact-modal-title    { font-size:.95rem; font-weight:800; color:#fff;
                       display:flex; align-items:center; gap:8px; }
.fact-modal-nav      { display:flex; align-items:center; gap:8px; }
.fact-modal-nav-btn  { background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.12);
                       border-radius:6px; color:rgba(255,255,255,.7); font-size:.78rem;
                       font-weight:700; padding:5px 12px; cursor:pointer; transition:all .12s; }
.fact-modal-nav-btn:hover:not(:disabled) { background:rgba(54,176,201,.2); border-color:#36B0C9; color:#36B0C9; }
.fact-modal-nav-btn:disabled { opacity:.3; cursor:not-allowed; }
.fact-modal-nav-info { font-size:.75rem; color:rgba(255,255,255,.4); font-weight:600; }
.fact-modal-close    { background:rgba(239,68,68,.12); border:1px solid rgba(239,68,68,.25);
                       border-radius:6px; color:#EF4444; font-size:.78rem; font-weight:700;
                       padding:5px 12px; cursor:pointer; }
.fact-modal-body     { flex:1; overflow-y:auto; padding:18px 20px; }
.fact-modal-footer   { padding:12px 20px; border-top:1px solid rgba(255,255,255,.08);
                       display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap; }
.fact-print-btn      { background:rgba(54,176,201,.18); border:1px solid rgba(54,176,201,.4);
                       border-radius:8px; color:#36B0C9; font-size:.82rem; font-weight:700;
                       padding:8px 18px; cursor:pointer; }
.fact-print-btn:hover { background:rgba(54,176,201,.3); }
.fact-pdf-btn        { background:linear-gradient(135deg,#F97316,#ea580c);
                       border:none; border-radius:8px; color:#fff; font-size:.82rem;
                       font-weight:700; padding:8px 18px; cursor:pointer;
                       box-shadow:0 3px 10px rgba(249,115,22,.3); }
.fact-pdf-btn:hover   { opacity:.88; }

/* ── Contenido del resumen ─────────────────────────────────── */
.fact-resumen        { font-family: Arial, sans-serif; color:#e2e8f0; font-size:.82rem; }
.fact-res-title      { font-size:1.05rem; font-weight:800; color:#fff; margin-bottom:2px; }
.fact-res-period     { font-size:.75rem; color:rgba(255,255,255,.45); margin-bottom:16px; }
.fact-res-section    { margin-bottom:18px; }
.fact-res-sec-title  { font-size:.7rem; font-weight:800; text-transform:uppercase;
                       letter-spacing:.07em; color:#36B0C9; margin-bottom:8px;
                       padding-bottom:5px; border-bottom:1px solid rgba(54,176,201,.2); }
.fact-res-table      { width:100%; border-collapse:collapse; font-size:.78rem; }
.fact-res-table th   { background:rgba(255,255,255,.06); font-weight:700; font-size:.68rem;
                       text-transform:uppercase; letter-spacing:.04em; color:rgba(255,255,255,.5);
                       padding:7px 10px; text-align:left; border-bottom:1px solid rgba(255,255,255,.08); }
.fact-res-table td   { padding:6px 10px; border-bottom:1px solid rgba(255,255,255,.05);
                       color:rgba(255,255,255,.82); }
.fact-res-table td.num { text-align:right; font-variant-numeric:tabular-nums; font-weight:600; }
.fact-res-table tr:last-child td { border-bottom:none; font-weight:700; color:#fff;
                                   border-top:1px solid rgba(255,255,255,.12); }
.row-stock-inicial td { background:rgba(255,255,255,.14) !important;
                      color:rgba(255,255,255,.98) !important;
                      font-weight:700 !important;
                      border-top: 1px solid rgba(255,255,255,.2) !important;
                      border-bottom: 1px solid rgba(255,255,255,.2) !important; }
.fact-res-empty      { text-align:center; color:rgba(255,255,255,.3); font-style:italic;
                       padding:12px 0; }
.fact-loading-msg    { text-align:center; padding:40px; color:rgba(255,255,255,.4);
                       font-size:.9rem; }
.fact-res-kpis       { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:14px; }
.fact-res-kpi        { background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.1);
                       border-radius:9px; padding:10px 14px; min-width:90px; }
.fact-res-kpi-val    { font-size:1.3rem; font-weight:800; color:#36B0C9; line-height:1; }
.fact-res-kpi-lbl    { font-size:.62rem; text-transform:uppercase; letter-spacing:.05em;
                       color:rgba(255,255,255,.4); margin-top:2px; }
.fact-res-error      { background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.25);
                       border-radius:8px; padding:12px 16px; color:#FCA5A5; font-size:.82rem; }
  `;
  document.head.appendChild(style);
})();


/* ══════════════════════════════════════════════════════════════
   FUNCIÓN PRINCIPAL — sobreescribe renderFacturacion() de app.js
   ══════════════════════════════════════════════════════════════ */
function renderFacturacion() {
  // Guardia: funciones que existen en app.js
  if (typeof setHeader        === 'function') setHeader('ADMINISTRACION');
  if (typeof setExpandedMode  === 'function') setExpandedMode(false);
  if (typeof showMetaPanel    === 'function') showMetaPanel(true);
  if (typeof syncBackBtn      === 'function') syncBackBtn();

  const menuGrid = document.getElementById('menuGrid');
  menuGrid.className = '';
  menuGrid.innerHTML = '';

  /* ── Estado interno ────────────────────────────────────────── */
  let seleccionados   = new Set();   // índices de clientesFactura[]
  let clientesFactura = [];          // [{cliente_id, nombre, estibas_creadas, ...}]

  /* ── Fechas por defecto: 1ro del mes hasta hoy ─────────────── */
  const hoy          = new Date();
  const primerDeMes  = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
  const fmtDate      = d => d.toISOString().split('T')[0];

  /* ── Construir HTML del módulo ─────────────────────────────── */
  const wrap = document.createElement('div');
  wrap.className = 'fact-wrap';
  wrap.innerHTML = `
    <div class="fact-header">
      <div class="fact-header-title">
        <span class="indicador-badge" style="background:rgba(249,115,22,.2);color:#F97316;border-color:rgba(249,115,22,.35)">FAC</span>
        FACTURACIÓN — RESÚMENES POR CLIENTE
      </div>
      <span class="indicador-hint">Elegí el período, seleccioná los clientes y generá los resúmenes</span>
    </div>

    <!-- BLOQUE 1: PERÍODO -->
    <div class="fact-section fact-period-section">
      <div class="fact-section-title">
        <span class="fact-section-icon">📅</span>
        Período de facturación
        <span class="indicador-hint" id="factPeriodoHint" style="margin-left:8px">Elegí las fechas y se cargarán los clientes</span>
      </div>
      <div class="fact-period-row">
        <div class="fact-date-group">
          <label class="fact-date-label">Desde</label>
          <input class="fact-date-input" id="factDesde" type="date" value="${fmtDate(primerDeMes)}" />
        </div>
        <div class="fact-period-sep">→</div>
        <div class="fact-date-group">
          <label class="fact-date-label">Hasta</label>
          <input class="fact-date-input" id="factHasta" type="date" value="${fmtDate(hoy)}" />
        </div>
        <div class="fact-period-shortcuts">
          <button class="fact-shortcut-btn active" data-range="mes">Mes actual</button>
          <button class="fact-shortcut-btn" data-range="mesant">Mes anterior</button>
          <button class="fact-shortcut-btn" data-range="trim">Trimestre</button>
          <button class="fact-shortcut-btn" data-range="anio">Año</button>
        </div>
      </div>
    </div>

    <!-- BLOQUE 2: CLIENTES -->
    <div class="fact-section">
      <div class="fact-section-title">
        <span class="fact-section-icon">👥</span>
        Clientes que operaron en el período
        <span class="fact-sel-count" id="factSelCount">0 seleccionados</span>
        <button class="fact-sel-all-btn"  id="factSelAll">Seleccionar todos</button>
        <button class="fact-sel-none-btn" id="factSelNone">Limpiar</button>
      </div>
      <div class="fact-search-wrap">
        <input class="fact-search" id="factSearch" type="text" placeholder="🔍  Buscar cliente..." autocomplete="off" />
      </div>
      <div class="fact-clients-grid" id="factClientsGrid">
        <div class="ge-loading" style="padding:16px;text-align:center">Cargando clientes...</div>
      </div>
    </div>

    <!-- BLOQUE 3: GENERAR -->
    <div class="fact-section fact-download-section">
      <div class="fact-section-title">
        <span class="fact-section-icon">📄</span>
        Resúmenes a generar
        <span class="fact-hint-small" id="factResumenHint">Seleccioná al menos un cliente</span>
      </div>
      <div class="fact-download-row">
        <div class="fact-summary-preview" id="factSummaryPreview">
          <span class="fact-preview-empty">— sin clientes seleccionados —</span>
        </div>
        <button class="fact-download-btn" id="factDownloadBtn" disabled>
          📊 Ver resúmenes
        </button>
      </div>
    </div>
  `;

  menuGrid.appendChild(wrap);

  /* ── Referencias al DOM ──────────────────────────────────────── */
  const grid       = wrap.querySelector('#factClientsGrid');
  const searchEl   = wrap.querySelector('#factSearch');
  const countEl    = wrap.querySelector('#factSelCount');
  const previewEl  = wrap.querySelector('#factSummaryPreview');
  const dlBtn      = wrap.querySelector('#factDownloadBtn');
  const hintEl     = wrap.querySelector('#factResumenHint');
  const selAllBtn  = wrap.querySelector('#factSelAll');
  const selNoneBtn = wrap.querySelector('#factSelNone');
  const periodoHint= wrap.querySelector('#factPeriodoHint');

  /* ── Cargar clientes desde API ──────────────────────────────── */
  const cargarClientes = async () => {
    const desde = wrap.querySelector('#factDesde').value;
    const hasta  = wrap.querySelector('#factHasta').value;
    if (!desde || !hasta) return;

    seleccionados.clear();
    grid.innerHTML = '<div class="ge-loading" style="padding:16px;text-align:center">Cargando clientes...</div>';
    periodoHint.textContent = 'Cargando...';

    const url = typeof API_EQUIPOS_URL !== 'undefined' ? API_EQUIPOS_URL : '/api_2026ia.php';
    const fiscal = typeof clientesFiscal !== 'undefined' ? clientesFiscal : 0;

    try {
      // Traer TODOS los activos + los que operaron en paralelo
      const [resActivos, resOperaron] = await Promise.all([
        fetch(`${url}?action=clientes_lista&buscar=&fiscal=${fiscal}&pagina=1&por_pagina=500`, { credentials: 'same-origin' }),
        fetch(`${url}?action=clientes_operaron_rango&desde=${desde}&hasta=${hasta}`, { credentials: 'same-origin' }),
      ]);
      const dataActivos   = await resActivos.json();
      const dataOperaron  = await resOperaron.json();

      // Set de IDs que operaron
      const operaronIds = new Set((dataOperaron.clientes || []).map(c => c.cliente_id));
      const operaronMap = {};
      (dataOperaron.clientes || []).forEach(c => { operaronMap[c.cliente_id] = c; });

      // Solo clientes activos, ordenados: primero los que operaron, luego el resto
      const activos = (dataActivos.clientes || []).filter(c => parseInt(c.Activo) === 1);
      activos.sort((a, b) => {
        const aOp = operaronIds.has(a.ClienteID) ? 0 : 1;
        const bOp = operaronIds.has(b.ClienteID) ? 0 : 1;
        if (aOp !== bOp) return aOp - bOp;
        return a.ClienteNombre.localeCompare(b.ClienteNombre, 'es');
      });

      // Construir clientesFactura usando datos del operaron para los que operaron
      clientesFactura = activos.map(c => {
        const op = operaronMap[c.ClienteID];
        return {
          cliente_id      : c.ClienteID,
          nombre          : c.ClienteNombre,
          factura_por     : c.factura_por     || op?.factura_por     || 'P',
          tipo_medicion   : c.TipoMedicionTemperatura || op?.tipo_medicion || 'S',
          estibas_creadas : op?.estibas_creadas   || 0,
          opero           : operaronIds.has(c.ClienteID),
        };
      });

      const [dy, dm, dd] = desde.split('-');
      const [hy, hm, hd] = hasta.split('-');
      const cantOpero = operaronIds.size;
      periodoHint.textContent =
        `${activos.length} clientes activos · ${cantOpero} operaron · ${dd}/${dm}/${dy} → ${hd}/${hm}/${hy}`;

      if (!clientesFactura.length) {
        grid.innerHTML = '<div style="opacity:.4;padding:16px;text-align:center;font-size:.85rem">Sin clientes activos</div>';
        actualizarUI();
        return;
      }

      grid.innerHTML = clientesFactura.map((c, i) => `
        <button class="fact-client-chip${c.opero ? ' opero' : ''}"
          data-idx="${i}" data-name="${(c.nombre || '').toLowerCase()}" title="${c.nombre}">
          <span class="fact-chip-check">✓</span>
          <span class="fact-chip-name">${c.nombre}</span>
          ${c.opero
            ? `<span style="font-size:.6rem;color:#22c55e;margin-left:4px" title="Operó en el período">●</span>`
            : `<span style="font-size:.6rem;opacity:.3;margin-left:4px">○</span>`}
        </button>`
      ).join('');

      actualizarUI();
    } catch (e) {
      grid.innerHTML = `<div style="opacity:.5;padding:16px;text-align:center">Error al cargar clientes: ${e.message}</div>`;
      periodoHint.textContent = 'Error al cargar';
    }
  };

  /* ── Actualizar UI (contador + preview + botón) ─────────────── */
  const actualizarUI = () => {
    const n = seleccionados.size;
    const desde = wrap.querySelector('#factDesde').value;
    const hasta  = wrap.querySelector('#factHasta').value;
    const fmtD = v => { const [y,m,d] = v.split('-'); return `${d}/${m}/${y}`; };

    countEl.textContent = n === 0 ? '0 seleccionados' : n === 1 ? '1 seleccionado' : `${n} seleccionados`;
    countEl.style.color = n > 0 ? '#36B0C9' : 'rgba(255,255,255,.4)';

    dlBtn.disabled = n === 0;
    hintEl.textContent = n === 0
      ? 'Seleccioná al menos un cliente'
      : `${n} resumen${n > 1 ? 'es' : ''} · ${fmtD(desde)} → ${fmtD(hasta)}`;
    hintEl.style.color = n > 0 ? '#00A887' : 'rgba(255,255,255,.35)';

    if (n === 0) {
      previewEl.innerHTML = '<span class="fact-preview-empty">— sin clientes seleccionados —</span>';
    } else {
      previewEl.innerHTML = [...seleccionados].map(i => {
        const c = clientesFactura[i];
        if (!c) return '';
        return `
          <div class="fact-preview-chip">
            <span class="fact-preview-name">${c.nombre}</span>
            <span class="fact-preview-period">${fmtD(desde)} → ${fmtD(hasta)}</span>
          </div>`;
      }).join('');
    }
  };

  /* ── Toggle chip ─────────────────────────────────────────────── */
  grid.addEventListener('click', e => {
    const chip = e.target.closest('.fact-client-chip');
    if (!chip) return;
    const idx = parseInt(chip.dataset.idx);
    if (seleccionados.has(idx)) {
      seleccionados.delete(idx);
      chip.classList.remove('selected');
    } else {
      seleccionados.add(idx);
      chip.classList.add('selected');
    }
    actualizarUI();
  });

  /* ── Búsqueda ────────────────────────────────────────────────── */
  searchEl.addEventListener('input', () => {
    const q = searchEl.value.toLowerCase().trim();
    grid.querySelectorAll('.fact-client-chip').forEach(chip => {
      chip.style.display = (!q || chip.dataset.name.includes(q)) ? '' : 'none';
    });
  });

  /* ── Seleccionar / limpiar todos ─────────────────────────────── */
  selAllBtn.addEventListener('click', () => {
    grid.querySelectorAll('.fact-client-chip:not([style*="none"])').forEach(chip => {
      seleccionados.add(parseInt(chip.dataset.idx));
      chip.classList.add('selected');
    });
    actualizarUI();
  });
  selNoneBtn.addEventListener('click', () => {
    seleccionados.clear();
    grid.querySelectorAll('.fact-client-chip').forEach(c => c.classList.remove('selected'));
    actualizarUI();
  });

  /* ── Atajos de fecha ─────────────────────────────────────────── */
  wrap.querySelectorAll('.fact-shortcut-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      wrap.querySelectorAll('.fact-shortcut-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const now   = new Date();
      const desdeEl = wrap.querySelector('#factDesde');
      const hastaEl = wrap.querySelector('#factHasta');
      switch (btn.dataset.range) {
        case 'mes':
          desdeEl.value = fmtDate(new Date(now.getFullYear(), now.getMonth(), 1));
          hastaEl.value = fmtDate(now);
          break;
        case 'mesant': {
          const pm = new Date(now.getFullYear(), now.getMonth() - 1, 1);
          desdeEl.value = fmtDate(pm);
          hastaEl.value = fmtDate(new Date(now.getFullYear(), now.getMonth(), 0));
          break;
        }
        case 'trim':
          desdeEl.value = fmtDate(new Date(now.getFullYear(), now.getMonth() - 2, 1));
          hastaEl.value = fmtDate(now);
          break;
        case 'anio':
          desdeEl.value = fmtDate(new Date(now.getFullYear(), 0, 1));
          hastaEl.value = fmtDate(now);
          break;
      }
      cargarClientes();
    });
  });

  wrap.querySelector('#factDesde').addEventListener('change', cargarClientes);
  wrap.querySelector('#factHasta').addEventListener('change', cargarClientes);

  /* ── Botón principal: abrir modal de resúmenes ───────────────── */
  dlBtn.addEventListener('click', () => {
    const desde  = wrap.querySelector('#factDesde').value;
    const hasta  = wrap.querySelector('#factHasta').value;
    const lista  = [...seleccionados].map(i => clientesFactura[i]).filter(Boolean);
    abrirModalResumenes(lista, desde, hasta);
  });

  /* ── Carga inicial ───────────────────────────────────────────── */
  cargarClientes();
}


/* ══════════════════════════════════════════════════════════════
   MODAL DE RESÚMENES
   Muestra un resumen por cliente, con navegación entre ellos
   ══════════════════════════════════════════════════════════════ */
function abrirModalResumenes(clientes, desde, hasta) {
  let idx = 0;
  // Cache de datos ya cargados por índice — evita recargar al navegar
  const dataCache = {};

  const fmtD = v => {
    if (!v) return '—';
    const [y, m, d] = v.split('-');
    return `${d}/${m}/${y}`;
  };
  const fmtN = (n, dec = 0) => {
    if (n === null || n === undefined || n === '' || isNaN(n)) return '—';
    return parseFloat(n).toLocaleString('es-AR', { minimumFractionDigits: dec, maximumFractionDigits: dec });
  };

  const overlay = document.createElement('div');
  overlay.className = 'fact-overlay';
  overlay.innerHTML = `
    <div class="fact-modal">
      <div class="fact-modal-header">
        <div class="fact-modal-title">
          <span style="font-size:1.1rem">🧾</span>
          <span id="fmTitle">Cargando...</span>
        </div>
        <div class="fact-modal-nav">
          <button class="fact-modal-nav-btn" id="fmPrev">← Anterior</button>
          <span class="fact-modal-nav-info" id="fmNavInfo">1 de ${clientes.length}</span>
          <button class="fact-modal-nav-btn" id="fmNext">Siguiente →</button>
        </div>
        <button class="fact-modal-close" id="fmClose">✕ Cerrar</button>
      </div>
      <div class="fact-modal-body" id="fmBody">
        <div class="fact-loading-msg">⏳ Cargando datos del cliente...</div>
      </div>
      <div class="fact-modal-footer" style="flex-wrap:wrap;gap:8px">

        <!-- Botones impresión individual -->
        <button class="fact-print-btn" id="fmPrint" title="Imprimir resumen del cliente actual">
          🖨️ Imprimir este
        </button>
        <button class="fact-pdf-btn" id="fmPdf" title="Descargar Excel del cliente actual">
          ⬇ Excel
        </button>

        <!-- Separador -->
        <div style="width:1px;background:rgba(255,255,255,.1);margin:0 4px;align-self:stretch"></div>

        <!-- Botones impresión múltiple — solo visibles con 2+ clientes -->
        ${clientes.length > 1 ? `
        <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
          <span style="font-size:.65rem;color:rgba(255,255,255,.35);white-space:nowrap">Todos (${clientes.length}):</span>
          <button class="fact-print-btn" id="fmPrintTodosSep"
            style="font-size:.75rem;padding:6px 12px"
            title="Imprime un PDF separado por cada cliente">
            🖨️ PDF por cliente
          </button>
          <button class="fact-print-btn" id="fmPrintTodosJuntos"
            style="font-size:.75rem;padding:6px 12px;background:rgba(54,176,201,.25)"
            title="Imprime todos en un solo PDF con resumen final">
            🖨️ PDF consolidado + resumen
          </button>
        </div>` : ''}

      </div>
    </div>
  `;
  document.body.appendChild(overlay);

  const bodyEl    = overlay.querySelector('#fmBody');
  const titleEl   = overlay.querySelector('#fmTitle');
  const navInfoEl = overlay.querySelector('#fmNavInfo');
  const prevBtn   = overlay.querySelector('#fmPrev');
  const nextBtn   = overlay.querySelector('#fmNext');

  overlay.querySelector('#fmClose').addEventListener('click', () => overlay.remove());
  overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });

  /* ── Imprimir cliente actual ─────────────────────────────────── */
  overlay.querySelector('#fmPrint').addEventListener('click', () => {
    const c    = clientes[idx];
    const data = dataCache[idx] || null;
    imprimirResumen(c, desde, hasta, bodyEl.innerHTML, data);
  });

  /* ── Excel cliente actual ───────────────────────────────────── */
  overlay.querySelector('#fmPdf').addEventListener('click', () => {
    const c  = clientes[idx];
    const fp = c.factura_por || 'P';
    window.location.href = `/facturacion_excel.php?cliente_id=${c.cliente_id}&desde=${desde}&hasta=${hasta}&fp=${fp}`;
  });

  /* ── PDF separado por cliente ─────────────────────────────── */
  if (clientes.length > 1) {
    overlay.querySelector('#fmPrintTodosSep').addEventListener('click', async () => {
      await imprimirMultiples(clientes, desde, hasta, dataCache, 'separados');
    });

    /* ── PDF consolidado con resumen final ────────────────────── */
    overlay.querySelector('#fmPrintTodosJuntos').addEventListener('click', async () => {
      await imprimirMultiples(clientes, desde, hasta, dataCache, 'consolidado');
    });
  }

  /* ── Navegación ─────────────────────────────────────────────── */
  const navActualizar = () => {
    prevBtn.disabled = idx === 0;
    nextBtn.disabled = idx === clientes.length - 1;
    navInfoEl.textContent = `${idx + 1} de ${clientes.length}`;
  };

  prevBtn.addEventListener('click', () => { if (idx > 0)                   { idx--; cargarResumen(); } });
  nextBtn.addEventListener('click', () => { if (idx < clientes.length - 1) { idx++; cargarResumen(); } });

  /* ── Cargar resumen del cliente actual ──────────────────────── */
  const cargarResumen = async () => {
    const c = clientes[idx];
    titleEl.textContent = c.nombre;
    navActualizar();

    // Usar cache si ya cargamos este cliente
    if (dataCache[idx]) {
      bodyEl.innerHTML = renderResumenCompleto(c, desde, hasta, dataCache[idx]);
      return;
    }

    bodyEl.innerHTML = '<div class="fact-loading-msg">⏳ Cargando datos de ' + c.nombre + '...</div>';
    const url = typeof API_EQUIPOS_URL !== 'undefined' ? API_EQUIPOS_URL : '/api_2026ia.php';

    try {
      const res  = await fetch(
        `${url}?action=facturacion_resumen&cliente_id=${c.cliente_id}&desde=${desde}&hasta=${hasta}`,
        { credentials: 'same-origin' }
      );
      const text = await res.text();
      let data;
      try { data = JSON.parse(text); } catch(_) { data = { error: 'Respuesta inválida: ' + text.substring(0, 300) }; }

      if (!res.ok || data.error) {
        const msg = data?.error || ('HTTP ' + res.status);
        bodyEl.innerHTML =
          '<div class="fact-res-error" style="margin:16px 0">'
          + '<strong>\u26a0\ufe0f Error del servidor:</strong><br>'
          + '<code style="font-size:.78rem;word-break:break-all">' + msg + '</code>'
          + '</div>'
          + renderResumenBasico(c, desde, hasta, null);
        return;
      }

      dataCache[idx] = data;
      bodyEl.innerHTML = renderResumenCompleto(c, desde, hasta, data);
    } catch (e) {
      bodyEl.innerHTML =
        '<div class="fact-res-error" style="margin:16px 0">'
        + '<strong>\u26a0\ufe0f Error de conexión:</strong><br>'
        + '<code style="font-size:.78rem">' + e.message + '</code>'
        + '</div>'
        + renderResumenBasico(c, desde, hasta, null);
    }
  };

  cargarResumen();
}


/* ══════════════════════════════════════════════════════════════
   RENDERIZAR RESUMEN COMPLETO (cuando la API devuelve datos)
   Equivalente visual al Excel generado por facturarSuccess.php
   ══════════════════════════════════════════════════════════════ */
function renderResumenCompleto(cliente, desde, hasta, data) {
  const fmtD  = v => { if (!v) return '—'; const p = String(v).split(' ')[0].split('-'); return `${p[2]}/${p[1]}/${p[0]}`; };
  const fmtN  = (n, dec=0) => (n === null || n === undefined || n === '' || isNaN(n)) ? '—'
    : parseFloat(n).toLocaleString('es-AR', { minimumFractionDigits: dec, maximumFractionDigits: dec });
  const fmtM  = n => (n === null || n === undefined || isNaN(n)) ? '—'
    : '$ ' + parseFloat(n).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  const entradas  = data.cant_entradas || [];
  const salidas   = data.cant_salidas  || [];
  const ingresos  = data.mant_ingresos || [];
  const egresos   = data.mant_egresos  || [];
  const precios   = data.precios       || {};
  const stock     = data.stock_previo  || {};
  const cong      = data.congelado     || {};
  const dias      = data.dias          || 0;
  const factPicking = !!(data.fact_picking);

  // Totales entradas / salidas
  const sumEnt = arr => arr.reduce((s, r) => s + (parseFloat(r.Bultos)    || 0), 0);
  const sumSal = arr => arr.reduce((s, r) => s + (parseFloat(r.Bultos)    || 0), 0);
  const sumK   = (arr, k) => arr.reduce((s, r) => s + (parseFloat(r[k])   || 0), 0);
  const sumP   = arr => arr.reduce((s, r) => s + (parseFloat(r.Posiciones)|| 0), 0);

  // factura_por: P=Posición | B=Bultos | K=Kilos
  const fp = (data.cliente?.factura_por || cliente.factura_por || 'P');
  // toNum: igual al PHP del Excel — convierte string o número al valor correcto
  const toNum = v => {
    if (v === null || v === undefined || v === '') return 0;
    if (typeof v === 'number') return v;
    const s = String(v);
    if (s.includes(',') && s.includes('.')) return parseFloat(s.replace(/\./g,'').replace(',','.')) || 0;
    if (s.includes(',')) return parseFloat(s.replace(',','.')) || 0;
    return parseFloat(s) || 0;
  };
  const getBase = r => fp === 'K' ? toNum(r.Kilos)
                     : fp === 'B' ? toNum(r.Bultos)
                     :              toNum(r.Posiciones);
  const fpLabel = fp === 'K' ? 'Kilos' : fp === 'B' ? 'Bultos' : 'Pos.';

  // Monto MO descargas y cargas según factura_por — con soporte picking
  let montoMODesc = 0, montoMOCarg = 0;
  if (!factPicking) {
    montoMODesc = entradas.reduce((s, r) => s + getBase(r) * (parseFloat(precios.mo_descargas)||0), 0);
    montoMOCarg = salidas.reduce((s, r) => s + getBase(r) * (parseFloat(precios.mo_cargas)||0), 0);
  } else {
    entradas.forEach(r => {
      const det = r.picking_detalle || { pallet:{estibas:0,bultos:0,kilos:0}, picking:{estibas:0,bultos:0,kilos:0} };
      const bP  = fp==='K'?toNum(det.pallet.kilos) :fp==='B'?toNum(det.pallet.bultos) :toNum(det.pallet.estibas);
      const bPk = fp==='K'?toNum(det.picking.kilos):fp==='B'?toNum(det.picking.bultos):toNum(det.picking.estibas);
      montoMODesc += bP*(parseFloat(precios.mo_descargas)||0) + bPk*(parseFloat(precios.descarga_picking)||0);
    });
    salidas.forEach(r => {
      const det = r.picking_detalle || { pallet:{estibas:0,bultos:0,kilos:0}, picking:{estibas:0,bultos:0,kilos:0} };
      const bP  = fp==='K'?toNum(det.pallet.kilos) :fp==='B'?toNum(det.pallet.bultos) :toNum(det.pallet.estibas);
      const bPk = fp==='K'?toNum(det.picking.kilos):fp==='B'?toNum(det.picking.bultos):toNum(det.picking.estibas);
      montoMOCarg += bP*(parseFloat(precios.mo_cargas)||0) + bPk*(parseFloat(precios.carga_picking)||0);
    });
  }

  // Monto almacenamiento
  // Base stock previo según factura_por
  const getStockBase = s => fp === 'K' ? parseFloat(s?.kilos      || 0)
                           : fp === 'B' ? parseFloat(s?.cantidad   || 0)
                           :              parseFloat(s?.posiciones || 0);
  const posInicCong = getStockBase(stock.congelado);
  const posInicEnfr = getStockBase(stock.enfriado);
  const montoAlmCong = posInicCong * dias * (parseFloat(precios.mant_congelado) || 0);
  const montoAlmEnfr = posInicEnfr * dias * (parseFloat(precios.mant_enfriado)  || 0);

  // Monto mantenimiento ingresos
  const montoMantIn = ingresos.reduce((s, r) => {
    const p = parseInt(r.Congelado) === 1 ? (parseFloat(precios.mant_congelado)||0) : (parseFloat(precios.mant_enfriado)||0);
    return s + getBase(r) * (parseFloat(r.Dias)||0) * p;
  }, 0);
  const montoMantEg = egresos.reduce((s, r) => {
    const p = parseInt(r.Congelado) === 1 ? (parseFloat(precios.mant_congelado)||0) : (parseFloat(precios.mant_enfriado)||0);
    return s - getBase(r) * (parseFloat(r.Dias)||0) * p;
  }, 0);
  const montoAlmTotal = montoAlmCong + montoAlmEnfr + montoMantIn + montoMantEg;

  // Stock final = inicial + ingresos - egresos (en la unidad de facturación)
  const totalIngBultos = ingresos.reduce((s,r) => s + (parseInt(r.Cantidad)||0), 0);
  const totalIngKilos  = ingresos.reduce((s,r) => s + (parseFloat(r.Kilos)||0), 0);
  const totalIngPos    = ingresos.reduce((s,r) => s + (parseInt(r.Posiciones)||0), 0);
  const totalEgrBultos = egresos.reduce((s,r) => s + (parseInt(r.Cantidad)||0), 0);
  const totalEgrKilos  = egresos.reduce((s,r) => s + (parseFloat(r.Kilos)||0), 0);
  const totalEgrPos    = egresos.reduce((s,r) => s + (parseInt(r.Posiciones)||0), 0);
  const stockFinalCongBultos = (parseInt(stock.congelado?.cantidad||0)) + ingresos.filter(r=>parseInt(r.Congelado)===1).reduce((s,r)=>s+(parseInt(r.Cantidad)||0),0) - egresos.filter(r=>parseInt(r.Congelado)===1).reduce((s,r)=>s+(parseInt(r.Cantidad)||0),0);
  const stockFinalCongKilos  = (parseFloat(stock.congelado?.kilos||0)) + ingresos.filter(r=>parseInt(r.Congelado)===1).reduce((s,r)=>s+(parseFloat(r.Kilos)||0),0) - egresos.filter(r=>parseInt(r.Congelado)===1).reduce((s,r)=>s+(parseFloat(r.Kilos)||0),0);
  const stockFinalCongPos    = (parseInt(stock.congelado?.posiciones||0)) + ingresos.filter(r=>parseInt(r.Congelado)===1).reduce((s,r)=>s+(parseInt(r.Posiciones)||0),0) - egresos.filter(r=>parseInt(r.Congelado)===1).reduce((s,r)=>s+(parseInt(r.Posiciones)||0),0);
  const stockFinalEnfrBultos = (parseInt(stock.enfriado?.cantidad||0)) + ingresos.filter(r=>parseInt(r.Congelado)!==1).reduce((s,r)=>s+(parseInt(r.Cantidad)||0),0) - egresos.filter(r=>parseInt(r.Congelado)!==1).reduce((s,r)=>s+(parseInt(r.Cantidad)||0),0);
  const stockFinalEnfrKilos  = (parseFloat(stock.enfriado?.kilos||0)) + ingresos.filter(r=>parseInt(r.Congelado)!==1).reduce((s,r)=>s+(parseFloat(r.Kilos)||0),0) - egresos.filter(r=>parseInt(r.Congelado)!==1).reduce((s,r)=>s+(parseFloat(r.Kilos)||0),0);
  const stockFinalEnfrPos    = (parseInt(stock.enfriado?.posiciones||0)) + ingresos.filter(r=>parseInt(r.Congelado)!==1).reduce((s,r)=>s+(parseInt(r.Posiciones)||0),0) - egresos.filter(r=>parseInt(r.Congelado)!==1).reduce((s,r)=>s+(parseInt(r.Posiciones)||0),0);

  // Congelado total — suma de todos los rangos × getBase × precio
  const calcCongTotal = (rows, precio) =>
    rows.reduce((s, r) => s + getBase(r) * (parseFloat(precio) || 0), 0);
  const precDesc2 = (parseFloat(precios.mant_congelado)||0);
  let montoCongelado = 0;
  if (cong.tipo === 'simple') {
    montoCongelado =
      calcCongTotal(cong.normal2||[], (parseFloat(precios.cong_norm2)||0) - 2*precDesc2) +
      calcCongTotal(cong.normal1||[], (parseFloat(precios.cong_norm1)||0) - 2*precDesc2);
  } else {
    montoCongelado =
      calcCongTotal(cong.detalle3||[], (parseFloat(precios.cong_det3)||0) - 2*precDesc2) +
      calcCongTotal(cong.detalle2||[], (parseFloat(precios.cong_det2)||0) - 2*precDesc2) +
      calcCongTotal(cong.detalle1||[], (parseFloat(precios.cong_det1)||0) - 2*precDesc2);
  }

  // Tabla helper
  const tablaVacia = n => `<tr><td colspan="${n}" class="fact-res-empty">Sin movimientos en el período</td></tr>`;

  // Sección congelado — filas según tipo de medición
  let seccionCongelado = '';
  if (cong.tipo === 'simple') {
    const renderCongRow = (rows, precio, label) => rows.length ? rows.map(r => `
      <tr>
        <td>${r.CargaID}</td><td>${r.NroRemito||'—'}</td>
        <td>${fmtD(r.fecha_movimiento)}</td>
        <td class="num">${fmtN(r.Cantidad)}</td><td class="num">${fmtN(r.Kilos,1)}</td>
        <td class="num">${fmtN(r.Posiciones)}</td><td class="num">${fmtN(r.Temperatura,1)}°</td>
        <td class="num">${fmtM(precio)}</td>
        <td class="num">${fmtM(getBase(r)*(parseFloat(precio)||0))}</td>
      </tr>`).join('') + `<tr>
        <td colspan="3"><strong>TOTAL</strong></td>
        <td class="num">${fmtN(sumK(rows,'Cantidad'))}</td><td class="num">${fmtN(sumK(rows,'Kilos'),1)}</td>
        <td class="num">${fmtN(sumP(rows))}</td><td></td><td></td>
        <td class="num">${fmtM(rows.reduce((s,r)=>(s+getBase(r)*(parseFloat(precio)||0)),0))}</td>
      </tr>` : tablaVacia(9);

    const thCong = `<th>Entrada</th><th>Remito</th><th>Fecha</th><th class="num">Bultos</th><th class="num">Kilos</th><th class="num">Pos.</th><th class="num">Temp.</th><th class="num">P.Unit.</th><th class="num">Total</th>`;
    seccionCongelado = `
      <div class="fact-res-section">
        <div class="fact-res-sec-title">🌡 Congelado Normal (mayor -4°)</div>
        <table class="fact-res-table"><thead><tr>${thCong}</tr></thead><tbody>
          ${renderCongRow(cong.normal2||[], precios.cong_norm2, '> -4°')}
        </tbody></table>
      </div>
      <div class="fact-res-section">
        <div class="fact-res-sec-title">🌡 Congelado Normal (-12° a -4°)</div>
        <table class="fact-res-table"><thead><tr>${thCong}</tr></thead><tbody>
          ${renderCongRow(cong.normal1||[], precios.cong_norm1, '-12° a -4°')}
        </tbody></table>
      </div>`;
  } else if (cong.tipo === 'detallado') {
    const renderCongRowD = (rows, precio, label) => rows.length ? rows.map(r => `
      <tr>
        <td>${r.CargaID}</td><td>${r.NroRemito||r[6]||'—'}</td>
        <td>${fmtD(r.fecha_movimiento)}</td>
        <td class="num">${fmtN(r.Cantidad)}</td><td class="num">${fmtN(r.Kilos,1)}</td>
        <td class="num">${fmtN(r.Posiciones)}</td><td class="num">${fmtN(r.Temperatura,1)}°</td>
        <td class="num">${fmtM(precio)}</td>
        <td class="num">${fmtM(getBase(r)*(parseFloat(precio)||0))}</td>
      </tr>`).join('') + `<tr>
        <td colspan="3"><strong>TOTAL</strong></td>
        <td class="num">${fmtN(sumK(rows,'Cantidad'))}</td><td class="num">${fmtN(sumK(rows,'Kilos'),1)}</td>
        <td class="num">${fmtN(sumP(rows))}</td><td></td><td></td>
        <td class="num">${fmtM(rows.reduce((s,r)=>(s+getBase(r)*(parseFloat(precio)||0)),0))}</td>
      </tr>` : tablaVacia(9);

    const thCong = `<th>Entrada</th><th>Remito</th><th>Fecha</th><th class="num">Bultos</th><th class="num">Kilos</th><th class="num">Pos.</th><th class="num">Temp.</th><th class="num">P.Unit.</th><th class="num">Total</th>`;
    seccionCongelado = `
      <div class="fact-res-section">
        <div class="fact-res-sec-title">🌡 Congelado (mayor -5°)</div>
        <table class="fact-res-table"><thead><tr>${thCong}</tr></thead><tbody>
          ${renderCongRowD(cong.detalle3||[], precios.cong_det3)}
        </tbody></table>
      </div>
      <div class="fact-res-section">
        <div class="fact-res-sec-title">🌡 Congelado (-10° a -5°)</div>
        <table class="fact-res-table"><thead><tr>${thCong}</tr></thead><tbody>
          ${renderCongRowD(cong.detalle2||[], precios.cong_det2)}
        </tbody></table>
      </div>
      <div class="fact-res-section">
        <div class="fact-res-sec-title">🌡 Congelado (-15° a -10°)</div>
        <table class="fact-res-table"><thead><tr>${thCong}</tr></thead><tbody>
          ${renderCongRowD(cong.detalle1||[], precios.cong_det1)}
        </tbody></table>
      </div>`;
  }

  // Totales finales
  const subtotal    = montoAlmTotal + montoCongelado + montoMOCarg + montoMODesc;
  const iva         = subtotal * 0.21;
  const total       = subtotal + iva;

  return `
    <div class="fact-resumen">
      <div class="fact-res-title">${data.cliente?.ClienteNombre || cliente.nombre}</div>
      <div class="fact-res-period">Período: ${fmtD(desde)} → ${fmtD(hasta)} · ${dias} días</div>

      <!-- KPIs -->
      <div class="fact-res-kpis">
        <div class="fact-res-kpi"><div class="fact-res-kpi-val">${entradas.length}</div><div class="fact-res-kpi-lbl">Entradas</div></div>
        <div class="fact-res-kpi"><div class="fact-res-kpi-val">${fmtN(sumK(entradas,'Bultos'))}</div><div class="fact-res-kpi-lbl">Bultos ent.</div></div>
        <div class="fact-res-kpi"><div class="fact-res-kpi-val">${salidas.length}</div><div class="fact-res-kpi-lbl">Salidas</div></div>
        <div class="fact-res-kpi"><div class="fact-res-kpi-val">${fmtN(sumK(salidas,'Bultos'))}</div><div class="fact-res-kpi-lbl">Bultos sal.</div></div>
        <div class="fact-res-kpi" style="border-color:rgba(249,115,22,.4)">
          <div class="fact-res-kpi-val" style="color:#F97316">${fmtM(total)}</div>
          <div class="fact-res-kpi-lbl">TOTAL c/IVA</div>
        </div>
      </div>

      <!-- Descargas (entradas) -->
      <div class="fact-res-section">
        <div class="fact-res-sec-title">📥 Descargas — Mano de obra (${entradas.length}) · ×${fpLabel}${factPicking ? ' · <span style="color:#F97316">con picking</span>' : ''}</div>
        <table class="fact-res-table">
          <thead><tr><th>Entrada</th><th>Remito</th><th>Fecha</th>
            <th class="num">Bultos</th><th class="num">Kilos</th><th class="num">Pos.</th>
            ${factPicking
              ? `<th class="num">Tarifa Normal</th><th class="num">Tarifa Picking</th>`
              : `<th class="num">P.Unit.</th>`}
            <th class="num">Total ×${fpLabel}</th></tr></thead>
          <tbody>
            ${entradas.length ? entradas.map(r => {
              if (!factPicking) {
                return `<tr>
                  <td>${r.CargaID}</td><td>${r.NroRemito||'—'}</td>
                  <td>${fmtD(r.fecha_movimiento)}</td>
                  <td class="num">${fmtN(r.Bultos)}</td><td class="num">${fmtN(r.Kilos,1)}</td>
                  <td class="num">${fmtN(r.Posiciones)}</td>
                  <td class="num">${fmtM(precios.mo_descargas)}</td>
                  <td class="num">${fmtM(getBase(r)*(parseFloat(precios.mo_descargas)||0))}</td>
                </tr>`;
              }
              const det = r.picking_detalle || { pallet:{estibas:0,bultos:0,kilos:0}, picking:{estibas:0,bultos:0,kilos:0} };
              const bP  = fp==='K'?toNum(det.pallet.kilos) :fp==='B'?toNum(det.pallet.bultos) :toNum(det.pallet.estibas);
              const bPk = fp==='K'?toNum(det.picking.kilos):fp==='B'?toNum(det.picking.bultos):toNum(det.picking.estibas);
              const mP  = bP  * (parseFloat(precios.mo_descargas)||0);
              const mPk = bPk * (parseFloat(precios.descarga_picking)||0);
              return `<tr>
                <td>${r.CargaID}</td><td>${r.NroRemito||'—'}</td>
                <td>${fmtD(r.fecha_movimiento)}</td>
                <td class="num">${fmtN(r.Bultos)}</td><td class="num">${fmtN(r.Kilos,1)}</td>
                <td class="num">${fmtN(r.Posiciones)}</td>
                <td class="num" style="font-size:.72rem">
                  ${bP>0  ? `<span style="color:#36B0C9">${fmtN(bP)} ${fpLabel} × ${fmtM(precios.mo_descargas)} = ${fmtM(mP)}</span>` : '—'}<br>
                  ${bPk>0 ? `<span style="color:#F97316">${fmtN(bPk)} ${fpLabel} × ${fmtM(precios.descarga_picking)} = ${fmtM(mPk)}</span>` : '—'}
                </td>
                <td class="num">${fmtM(mP+mPk)}</td>
              </tr>`;
            }).join('') + `<tr>
              <td colspan="5"><strong>SUBTOTAL</strong></td>
              <td class="num">${fmtN(sumP(entradas))}</td>
              ${factPicking ? `<td></td>` : `<td></td>`}
              <td class="num">${fmtM(montoMODesc)}</td>
            </tr>` : tablaVacia(factPicking ? 8 : 8)}
          </tbody>
        </table>
      </div>

      <!-- Cargas (salidas) -->
      <div class="fact-res-section">
        <div class="fact-res-sec-title">📤 Cargas — Mano de obra (${salidas.length}) · ×${fpLabel}${factPicking ? ' · <span style="color:#F97316">con picking</span>' : ''}</div>
        <table class="fact-res-table">
          <thead><tr><th>Salida</th><th>Remito</th><th>Fecha</th>
            <th class="num">Bultos</th><th class="num">Kilos</th><th class="num">Pos.</th>
            ${factPicking
              ? `<th class="num">Tarifa Normal</th><th class="num">Tarifa Picking</th>`
              : `<th class="num">P.Unit.</th>`}
            <th class="num">Total ×${fpLabel}</th></tr></thead>
          <tbody>
            ${salidas.length ? salidas.map(r => {
              if (!factPicking) {
                return `<tr>
                  <td>${r.PreSalidaID}</td><td>${r.Remito||'—'}</td>
                  <td>${fmtD(r.fecha_movimiento)}</td>
                  <td class="num">${fmtN(r.Bultos)}</td><td class="num">${fmtN(r.Kilos,1)}</td>
                  <td class="num">${fmtN(r.Posiciones)}</td>
                  <td class="num">${fmtM(precios.mo_cargas)}</td>
                  <td class="num">${fmtM(getBase(r)*(parseFloat(precios.mo_cargas)||0))}</td>
                </tr>`;
              }
              const det = r.picking_detalle || { pallet:{estibas:0,bultos:0,kilos:0}, picking:{estibas:0,bultos:0,kilos:0} };
              const bP  = fp==='K'?toNum(det.pallet.kilos) :fp==='B'?toNum(det.pallet.bultos) :toNum(det.pallet.estibas);
              const bPk = fp==='K'?toNum(det.picking.kilos):fp==='B'?toNum(det.picking.bultos):toNum(det.picking.estibas);
              const mP  = bP  * (parseFloat(precios.mo_cargas)||0);
              const mPk = bPk * (parseFloat(precios.carga_picking)||0);
              return `<tr>
                <td>${r.PreSalidaID}</td><td>${r.Remito||'—'}</td>
                <td>${fmtD(r.fecha_movimiento)}</td>
                <td class="num">${fmtN(r.Bultos)}</td><td class="num">${fmtN(r.Kilos,1)}</td>
                <td class="num">${fmtN(r.Posiciones)}</td>
                <td class="num" style="font-size:.72rem">
                  ${bP>0  ? `<span style="color:#36B0C9">${fmtN(bP)} ${fpLabel} × ${fmtM(precios.mo_cargas)} = ${fmtM(mP)}</span>` : '—'}<br>
                  ${bPk>0 ? `<span style="color:#F97316">${fmtN(bPk)} ${fpLabel} × ${fmtM(precios.carga_picking)} = ${fmtM(mPk)}</span>` : '—'}
                </td>
                <td class="num">${fmtM(mP+mPk)}</td>
              </tr>`;
            }).join('') + `<tr>
              <td colspan="5"><strong>SUBTOTAL</strong></td>
              <td class="num">${fmtN(sumP(salidas))}</td>
              ${factPicking ? `<td></td>` : `<td></td>`}
              <td class="num">${fmtM(montoMOCarg)}</td>
            </tr>` : tablaVacia(factPicking ? 8 : 8)}
          </tbody>
        </table>
      </div>

      <!-- Congelado por temperatura -->
      ${seccionCongelado}

      <!-- Almacenamiento: stock previo + movimientos -->
      <div class="fact-res-section">
        <div class="fact-res-sec-title">🏭 Almacenamiento</div>
        <table class="fact-res-table">
          <thead><tr><th>Concepto</th><th class="num">Bultos</th><th class="num">Kilos</th>
            <th class="num">Pos.</th><th class="num">Días</th>
            <th class="num">P.Unit.</th><th class="num">Total</th></tr></thead>
          <tbody>
            ${posInicCong > 0 ? `<tr class="row-stock-inicial" style="background:#e8e8e8">
              <td><strong>Stock Inicial CONGELADO</strong></td>
              <td class="num">${fmtN(stock.congelado.cantidad)}</td>
              <td class="num">${fmtN(stock.congelado.kilos,1)}</td>
              <td class="num">${fmtN(stock.congelado.posiciones)}</td>
              <td class="num">${dias}</td>
              <td class="num">${fmtM(precios.mant_congelado)}</td>
              <td class="num">${fmtM(montoAlmCong)}</td>
            </tr>` : ''}
            ${posInicEnfr > 0 ? `<tr class="row-stock-inicial" style="background:#e8e8e8">
              <td><strong>Stock Inicial ENFRIADO</strong></td>
              <td class="num">${fmtN(stock.enfriado.cantidad)}</td>
              <td class="num">${fmtN(stock.enfriado.kilos,1)}</td>
              <td class="num">${fmtN(stock.enfriado.posiciones)}</td>
              <td class="num">${dias}</td>
              <td class="num">${fmtM(precios.mant_enfriado)}</td>
              <td class="num">${fmtM(montoAlmEnfr)}</td>
            </tr>` : ''}
            ${ingresos.map(r => {
              const p = parseInt(r.Congelado)===1 ? precios.mant_congelado : precios.mant_enfriado;
              const m = getBase(r)*(parseFloat(r.Dias)||0)*(parseFloat(p)||0);
              return `<tr>
                <td>▲ Ingreso ${parseInt(r.Congelado)===1?'CONG.':'ENFR.'} — Carga ${r.CargaID}</td>
                <td class="num">${fmtN(r.Cantidad)}</td><td class="num">${fmtN(r.Kilos,1)}</td>
                <td class="num">${fmtN(r.Posiciones)}</td><td class="num">${fmtN(r.Dias)}</td>
                <td class="num">${fmtM(p)}</td><td class="num">${fmtM(m)}</td>
              </tr>`;
            }).join('')}
            ${egresos.map(r => {
              const p = parseInt(r.Congelado)===1 ? precios.mant_congelado : precios.mant_enfriado;
              const m = -(getBase(r))*(parseFloat(r.Dias)||0)*(parseFloat(p)||0);
              return `<tr>
                <td>▼ Egreso ${parseInt(r.Congelado)===1?'CONG.':'ENFR.'} — Salida ${r.PreSalidaID}</td>
                <td class="num">${fmtN(r.Cantidad)}</td><td class="num">${fmtN(r.Kilos,1)}</td>
                <td class="num">${fmtN(r.Posiciones)}</td><td class="num">${fmtN(r.Dias)}</td>
                <td class="num" style="color:#ef4444">${fmtM(-parseFloat(p)||0)}</td>
                <td class="num" style="color:#ef4444">${fmtM(m)}</td>
              </tr>`;
            }).join('')}
            <tr><td colspan="6"><strong>TOTAL ALMACENAMIENTO</strong></td>
              <td class="num">${fmtM(montoAlmTotal)}</td></tr>
            <tr style="background:rgba(54,176,201,.08);border-top:2px solid rgba(54,176,201,.3)">
              <td><strong style="color:#36B0C9">Stock Final CONGELADO</strong></td>
              <td class="num"><strong>${fmtN(stockFinalCongBultos)}</strong></td>
              <td class="num"><strong>${fmtN(stockFinalCongKilos,1)}</strong></td>
              <td class="num"><strong>${fmtN(stockFinalCongPos)}</strong></td>
              <td colspan="3"></td>
            </tr>
            ${stockFinalEnfrBultos || stockFinalEnfrKilos || stockFinalEnfrPos ? `<tr style="background:rgba(54,176,201,.05)">
              <td><strong style="color:#36B0C9">Stock Final ENFRIADO</strong></td>
              <td class="num"><strong>${fmtN(stockFinalEnfrBultos)}</strong></td>
              <td class="num"><strong>${fmtN(stockFinalEnfrKilos,1)}</strong></td>
              <td class="num"><strong>${fmtN(stockFinalEnfrPos)}</strong></td>
              <td colspan="3"></td>
            </tr>` : ''}
          </tbody>
        </table>
      </div>

      <!-- Resumen final -->
      <div class="fact-res-section" style="border-color:rgba(249,115,22,.25)">
        <div class="fact-res-sec-title" style="color:#F97316">💰 Resumen de montos</div>
        <table class="fact-res-table">
          <tbody>
            <tr><td>Almacenamiento</td><td class="num">${fmtM(montoAlmTotal)}</td></tr>
            <tr><td>Congelado</td><td class="num">${fmtM(montoCongelado)}</td></tr>
            <tr><td>Carga / Descarga</td><td class="num">${fmtM(montoMODesc + montoMOCarg)}</td></tr>
            <tr><td><strong>SUBTOTAL</strong></td><td class="num"><strong>${fmtM(subtotal)}</strong></td></tr>
            <tr><td>IVA 21%</td><td class="num">${fmtM(iva)}</td></tr>
            <tr><td style="font-size:1rem;font-weight:800;color:#F97316">TOTAL</td>
                <td class="num" style="font-size:1rem;font-weight:800;color:#F97316">${fmtM(total)}</td></tr>
          </tbody>
        </table>
      </div>

      <!-- Productos sin cajas_por_pallet — solo cuando factura_picking -->
      ${(data.productos_sin_cpp || []).length > 0 ? `
      <div class="fact-res-section" style="border-color:rgba(239,68,68,.2)">
        <div class="fact-res-sec-title" style="color:#FCA5A5">
          ⚠️ Productos sin cajas por pallet definidas (${data.productos_sin_cpp.length})
        </div>
        <div style="font-size:.72rem;color:rgba(255,255,255,.4);margin-bottom:8px">
          Estos productos operaron en el período pero tienen <code>cajas_por_pallet = 0</code>.
          No se les aplicó precio — revisar en el módulo de Productos.
        </div>
        <table class="fact-res-table">
          <thead><tr>
            <th>ID</th><th>Código</th><th>Descripción</th><th>Marca</th>
          </tr></thead>
          <tbody>
            ${data.productos_sin_cpp.map(p => `<tr>
              <td style="opacity:.4;font-size:.7rem">${p.ProductosID}</td>
              <td style="font-family:monospace;font-size:.72rem;opacity:.7">${p.CodigoProducto||'—'}</td>
              <td style="color:#FCA5A5">${p.Descripcion1||'—'}</td>
              <td style="opacity:.6">${p.Marca||'—'}</td>
            </tr>`).join('')}
          </tbody>
        </table>
      </div>` : ''}

      <div style="margin-top:10px;font-size:.68rem;color:rgba(255,255,255,.25);text-align:right">
        Generado ${new Date().toLocaleString('es-AR')} · Sistema Grupo CINA
      </div>
    </div>`;
}


/* ══════════════════════════════════════════════════════════════
   RESUMEN BÁSICO
   Se muestra cuando el endpoint facturacion_resumen no existe
   aún en la API, o falla. Muestra los datos disponibles del
   listado de clientes y un enlace al sistema viejo.
   ══════════════════════════════════════════════════════════════ */
function renderResumenBasico(cliente, desde, hasta, data) {
  const fmtD = v => { if (!v) return '—'; const [y,m,d] = v.split('-'); return `${d}/${m}/${y}`; };

  return `
    <div class="fact-resumen">
      <div class="fact-res-title">${cliente.nombre}</div>
      <div class="fact-res-period">Período: ${fmtD(desde)} → ${fmtD(hasta)}</div>

      <div class="fact-res-kpis">
        <div class="fact-res-kpi">
          <div class="fact-res-kpi-val">${cliente.estibas_creadas ?? '—'}</div>
          <div class="fact-res-kpi-lbl">Estibas ingresadas</div>
        </div>
        <div class="fact-res-kpi">
          <div class="fact-res-kpi-val">${cliente.estibas_eliminadas ?? '—'}</div>
          <div class="fact-res-kpi-lbl">Estibas retiradas</div>
        </div>
      </div>

      <div class="fact-res-error" style="margin-top:12px">
        <strong>ℹ️ Datos detallados no disponibles aún en esta versión.</strong><br>
        Para ver el resumen completo (entradas, salidas, mantenimiento, precios) usá el botón
        <em>"Abrir en sistema"</em> — abre la pantalla de facturación del sistema anterior para este cliente y período.<br><br>
        Para activar los datos completos inline, el endpoint <code>facturacion_resumen</code>
        debe agregarse a <code>api_2026ia.php</code> (ver sección de integración más abajo).
      </div>

      <div style="margin-top:16px;padding:12px;background:rgba(255,255,255,.04);border-radius:8px;font-size:.78rem;color:rgba(255,255,255,.5)">
        <strong style="color:rgba(255,255,255,.7)">📌 Para integración completa:</strong><br>
        Agregar en <code>api_2026ia.php</code> el case <code>'facturacion_resumen'</code> que reciba
        <code>cliente_id</code>, <code>desde</code>, <code>hasta</code> y devuelva:
        <code>cant_entradas</code>, <code>cant_salidas</code>, <code>mant_ingresos</code>,
        <code>mant_egresos</code>, <code>dias</code> — equivalentes a las queries del
        módulo Symfony <code>facturacionActions::executeFacturar()</code>.
      </div>

      <div style="margin-top:10px;font-size:.68rem;color:rgba(255,255,255,.25);text-align:right">
        Generado ${new Date().toLocaleString('es-AR')} · Sistema Grupo CINA
      </div>
    </div>`;
}


/* ══════════════════════════════════════════════════════════════
   IMPRIMIR RESUMEN — cliente individual
   ══════════════════════════════════════════════════════════════ */
function imprimirResumen(cliente, desde, hasta, contenidoHTML, data) {
  const fmtD = v => { if (!v) return '—'; const [y,m,d] = v.split('-'); return `${d}/${m}/${y}`; };
  const fechaImpresion = new Date().toLocaleDateString('es-AR', { day:'2-digit', month:'2-digit', year:'numeric' });

  const html = _buildPrintDoc([{ cliente, desde, hasta, contenidoHTML }], fechaImpresion, false);

  const win = window.open('', '_blank', 'width=900,height=700');
  if (!win) {
    if (typeof showToast === 'function') showToast('Habilitá los pop-ups para imprimir', 'error');
    return;
  }
  win.document.write(html);
  win.document.close();
  win.document.title = 'Resumen de Facturación — Sistema Grupo CINA';
  win.focus();
}


/* ══════════════════════════════════════════════════════════════
   IMPRIMIR MÚLTIPLES CLIENTES
   modo: 'separados' → una ventana por cliente
         'consolidado' → un solo PDF con resumen final
   ══════════════════════════════════════════════════════════════ */
async function imprimirMultiples(clientes, desde, hasta, dataCache, modo) {
  const url = typeof API_EQUIPOS_URL !== 'undefined' ? API_EQUIPOS_URL : '/api_2026ia.php';
  const fechaImpresion = new Date().toLocaleDateString('es-AR', { day:'2-digit', month:'2-digit', year:'numeric' });

  // Mostrar overlay de progreso
  const progOv = document.createElement('div');
  progOv.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:9999;display:flex;align-items:center;justify-content:center';
  progOv.innerHTML = `<div style="background:#0d1b35;border:1px solid rgba(255,255,255,.15);border-radius:14px;padding:28px 36px;text-align:center;min-width:300px">
    <div style="font-size:.9rem;font-weight:700;color:#fff;margin-bottom:12px">Preparando reportes...</div>
    <div style="font-size:.75rem;color:rgba(255,255,255,.5)" id="progMsg">Cargando datos...</div>
    <div style="margin-top:14px;background:rgba(255,255,255,.08);border-radius:6px;height:6px;overflow:hidden">
      <div id="progBar" style="height:100%;background:#36B0C9;border-radius:6px;transition:width .3s;width:0%"></div>
    </div>
  </div>`;
  document.body.appendChild(progOv);

  const progMsg = progOv.querySelector('#progMsg');
  const progBar = progOv.querySelector('#progBar');

  try {
    // Cargar datos de todos los clientes que no estén en cache
    const allData = [];
    for (let i = 0; i < clientes.length; i++) {
      const c = clientes[i];
      progMsg.textContent = `Cargando ${c.nombre} (${i+1}/${clientes.length})...`;
      progBar.style.width = `${Math.round(((i+1)/clientes.length)*80)}%`;

      let data = dataCache[i] || null;
      if (!data) {
        try {
          const res  = await fetch(`${url}?action=facturacion_resumen&cliente_id=${c.cliente_id}&desde=${desde}&hasta=${hasta}`, { credentials:'same-origin' });
          const text = await res.text();
          data = JSON.parse(text);
          if (data.error) data = null;
          else dataCache[i] = data;
        } catch(_) { data = null; }
      }
      allData.push({ cliente: c, desde, hasta, data });
    }

    progMsg.textContent = 'Generando documento...';
    progBar.style.width = '95%';

    if (modo === 'separados') {
      // Abrir una ventana por cliente
      for (const item of allData) {
        const html = _buildPrintDoc([{
          cliente: item.cliente,
          desde:   item.desde,
          hasta:   item.hasta,
          contenidoHTML: item.data
            ? _renderHTMLParaImpresion(item.cliente, item.desde, item.hasta, item.data)
            : `<p style="color:red">Error al cargar datos de ${item.cliente.nombre}</p>`,
        }], fechaImpresion, false);
        const win = window.open('', '_blank', 'width=900,height=700');
        if (win) { win.document.write(html); win.document.close(); win.document.title = 'Resumen de Facturación — Sistema Grupo CINA'; win.focus(); }
      }
    } else {
      // Consolidado: todos en una sola ventana con resumen final
      const items = allData.map(item => ({
        cliente: item.cliente,
        desde:   item.desde,
        hasta:   item.hasta,
        data:    item.data,
        contenidoHTML: item.data
          ? _renderHTMLParaImpresion(item.cliente, item.desde, item.hasta, item.data)
          : `<p style="color:red">Error al cargar datos de ${item.cliente.nombre}</p>`,
      }));
      const html = _buildPrintDoc(items, fechaImpresion, true);
      const win = window.open('', '_blank', 'width=900,height=700');
      if (win) { win.document.write(html); win.document.close(); win.document.title = 'Resumen de Facturación — Sistema Grupo CINA'; win.focus(); }
    }

    progBar.style.width = '100%';
    setTimeout(() => progOv.remove(), 400);

  } catch(e) {
    progOv.remove();
    alert('Error al generar reportes: ' + e.message);
  }
}


/* ══════════════════════════════════════════════════════════════
   HELPER: renderizar resumen como HTML limpio para impresión
   (misma lógica que renderResumenCompleto pero sin clases dark)
   ══════════════════════════════════════════════════════════════ */
function _renderHTMLParaImpresion(cliente, desde, hasta, data) {
  // Reusamos renderResumenCompleto y lo limpiamos con el mismo método de imprimirResumen
  const tmp = document.createElement('div');
  tmp.innerHTML = renderResumenCompleto(cliente, desde, hasta, data);
  return tmp.innerHTML;
}


/* ══════════════════════════════════════════════════════════════
   HELPER: construir el documento HTML completo para impresión
   items: [{ cliente, desde, hasta, contenidoHTML, data? }]
   conResumenFinal: true → agrega hoja de totales consolidados
   ══════════════════════════════════════════════════════════════ */
function _buildPrintDoc(items, fechaImpresion, conResumenFinal) {
  const fmtD = v => { if (!v) return '—'; if(v.includes('-')){const[y,m,d]=v.split('-');return`${d}/${m}/${y}`;}return v; };
  const fmtM = n => (n===null||n===undefined||isNaN(n)) ? '—'
    : '$ ' + parseFloat(n).toLocaleString('es-AR',{minimumFractionDigits:2,maximumFractionDigits:2});

  // CSS común
  const css = `
    * { box-sizing:border-box; margin:0; padding:0; }
    body { font-family:Arial,sans-serif; font-size:11px; color:#000; background:#fff; padding:16px 20px; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .no-print { margin-bottom:14px; display:flex; gap:10px; }
    .page-break { page-break-before:always; padding-top:20px; }
    .header-table { width:100%; border-collapse:collapse; margin-bottom:16px; }
    .header-table td { border:1px solid #000; padding:5px 10px; vertical-align:middle; }
    .logo-cell  { font-size:20px; font-weight:900; text-align:center; width:140px; }
    .title-cell { text-align:center; font-size:12px; font-weight:600; }
    .meta-cell  { font-size:10px; }
    .meta-label { color:#555; }
    .meta-val   { font-weight:700; float:right; }
    table { width:100%; border-collapse:collapse; margin-bottom:10px; }
    th { background:#f0f0f0; font-weight:700; font-size:10px; text-transform:uppercase;
         padding:5px 8px; text-align:left; border:1px solid #ccc; }
    td { padding:5px 8px; border:1px solid #eee; font-size:10px; vertical-align:middle; }
    .row-stock-inicial td { background:#e8e8e8 !important; font-weight:700 !important; color:#000 !important; }
    td.num { text-align:right; font-variant-numeric:tabular-nums; }
    tr:last-child td { font-weight:700; border-top:2px solid #ccc; }
    .kpis { display:flex; gap:10px; margin-bottom:12px; flex-wrap:wrap; }
    .kpi  { border:1px solid #ccc; border-radius:5px; padding:7px 12px; min-width:80px; }
    .kpi-val { font-size:1.1rem; font-weight:800; color:#0C2554; }
    .kpi-lbl { font-size:.65rem; text-transform:uppercase; color:#666; }
    .footer  { margin-top:16px; font-size:9px; color:#888; text-align:right; }
    .sin-cpp { background:#fff3f3; border:1px solid #fcc; border-radius:4px; padding:8px 10px; margin-top:10px; }
    .sin-cpp h4 { color:#c00; font-size:10px; margin-bottom:4px; }
    .sin-cpp td { color:#c00; }
    .resumen-global { background:#f0f7ff; border:2px solid #0C2554; border-radius:6px; padding:14px 18px; margin-top:14px; }
    .resumen-global h2 { font-size:13px; color:#0C2554; margin-bottom:10px; }
    .resumen-global table th { background:#dde8f5; }
    .resumen-global .grand-total td { font-size:12px; font-weight:800; background:#0C2554; color:#fff; border-color:#0C2554; }
    @page {
      size: A4;
      margin: 15mm 12mm 15mm 12mm;
    }
    @page :footer { display: none; }
    @page :header { display: none; }
    @media print {
      .no-print { display:none !important; }
      body { padding: 0 !important; font-size: 10px; }
      /* page-break entre clientes, NO bloquear corte de tablas largas */
      .page-break { page-break-before: always; padding-top: 10px; }
      /* Solo las secciones pequeñas (KPIs, encabezados) evitan el corte */
      .kpis       { page-break-inside: avoid; }
      .header-table { page-break-inside: avoid; }
      /* Las tablas largas SÍ se pueden cortar entre páginas */
      table       { page-break-inside: auto; }
      thead       { display: table-header-group; }  /* repetir cabecera en cada página */
      tr          { page-break-inside: avoid; page-break-after: auto; }
      /* Forzar colores en impresión */
      * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    }`;

  // Función para limpiar el HTML dark del modal
  const limpiar = html => html
    .replace(/class="fact-resumen"/g, '')
    .replace(/class="fact-res-title"/g, 'style="font-size:13px;font-weight:800;margin-bottom:2px"')
    .replace(/class="fact-res-period"/g, 'style="font-size:10px;color:#666;margin-bottom:10px"')
    .replace(/class="fact-res-kpis"/g, 'class="kpis"')
    .replace(/class="fact-res-kpi"/g, 'class="kpi"')
    .replace(/class="fact-res-kpi-val"/g, 'class="kpi-val"')
    .replace(/class="fact-res-kpi-lbl"/g, 'class="kpi-lbl"')
    .replace(/class="fact-res-section"[^>]*>/g, 'style="margin-bottom:14px">')
    .replace(/class="fact-res-sec-title"[^>]*>/g, 'style="font-size:10px;font-weight:700;text-transform:uppercase;border-bottom:1px solid #ccc;padding-bottom:3px;margin-bottom:6px;color:#333">')
    .replace(/class="fact-res-table"/g, '')
    .replace(/class="num"/g, 'class="num"')
    .replace(/class="fact-res-empty"/g, 'style="text-align:center;color:#999;font-style:italic;padding:8px 0"')
    .replace(/class="fact-res-error"[^>]*>/g, 'style="display:none">')
    .replace(/style="[^"]*color:#FCA5A5[^"]*"/g, 'style="color:#c00"')
    .replace(/style="[^"]*color:#F97316[^"]*"/g, 'style="color:#b85000"')
    .replace(/style="[^"]*color:#36B0C9[^"]*"/g, 'style="color:#0C2554"')
    .replace(/style="[^"]*color:rgba\(255,255,255[^"]*"/g, 'style="color:#555"')
    .replace(/style="[^"]*opacity:[0-9.]+[^"]*"/g, '')
    .replace(/style="[^"]*background:rgba[^"]*"/g, '');

  // Cabecera por cliente
  const headerCliente = (c, desde, hasta) => `
    <table class="header-table">
      <tr>
        <td class="logo-cell" rowspan="2">CINA S.R.L</td>
        <td class="title-cell" rowspan="2">Resumen de Facturación<br><strong>${c.nombre}</strong></td>
        <td class="meta-cell"><span class="meta-label">Período desde</span><span class="meta-val">${fmtD(desde)}</span></td>
      </tr>
      <tr>
        <td class="meta-cell"><span class="meta-label">Período hasta</span><span class="meta-val">${fmtD(hasta)}</span></td>
      </tr>
      <tr>
        <td class="logo-cell" style="font-size:10px;font-weight:400">SENASA ESTAB. N° 3540</td>
        <td class="title-cell" style="font-size:10px">Sistema Grupo CINA</td>
        <td class="meta-cell"><span class="meta-label">Fecha impresión</span><span class="meta-val">${fechaImpresion}</span></td>
      </tr>
    </table>`;

  // Construir páginas de clientes
  let paginasHtml = items.map((item, i) => {
    const breakCss = i > 0 ? ' class="page-break"' : '';
    return `<div${breakCss}>
      ${headerCliente(item.cliente, item.desde, item.hasta)}
      ${limpiar(item.contenidoHTML)}
    </div>`;
  }).join('\n');

  // Resumen global (solo en modo consolidado)
  let resumenGlobalHtml = '';
  if (conResumenFinal && items.length > 1) {
    const fmtN = (n,d=0)=>(n===null||n===undefined||isNaN(n))?'—':parseFloat(n).toLocaleString('es-AR',{minimumFractionDigits:d,maximumFractionDigits:d});
    // toNum: los valores vienen del JSON como números simples, no formateados
    const toNum = v => { if(v===null||v===undefined||v==='')return 0; const n=parseFloat(v); return isNaN(n)?0:n; };

    // Extraer totales de cada item que tenga data
    let totalAlm=0, totalCong=0, totalMO=0, totalSub=0, totalIva=0, totalTotal=0;
    let filas = '';

    for (const item of items) {
      if (!item.data) continue;
      const d       = item.data;
      const precios = d.precios || {};
      const fp      = d.cliente?.factura_por || item.cliente.factura_por || 'P';
      const toBase  = r => fp==='K'?toNum(r.Kilos):fp==='B'?toNum(r.Bultos):toNum(r.Posiciones);
      const entradas= d.cant_entradas||[];
      const salidas = d.cant_salidas||[];
      const ingresos= d.mant_ingresos||[];
      const egresos = d.mant_egresos||[];
      const stock   = d.stock_previo||{};
      const dias    = d.dias||0;
      const cong    = d.congelado||{};

      // MO
      let moDesc=0, moCarg=0;
      if (!d.fact_picking) {
        moDesc = entradas.reduce((s,r)=>s+toBase(r)*(parseFloat(precios.mo_descargas)||0),0);
        moCarg = salidas.reduce((s,r)=>s+toBase(r)*(parseFloat(precios.mo_cargas)||0),0);
      } else {
        entradas.forEach(r=>{
          const det=r.picking_detalle||{pallet:{estibas:0,bultos:0,kilos:0},picking:{estibas:0,bultos:0,kilos:0}};
          const bP = fp==='K'?toNum(det.pallet.kilos):fp==='B'?toNum(det.pallet.bultos):toNum(det.pallet.estibas);
          const bPk= fp==='K'?toNum(det.picking.kilos):fp==='B'?toNum(det.picking.bultos):toNum(det.picking.estibas);
          moDesc += bP*(parseFloat(precios.mo_descargas)||0) + bPk*(parseFloat(precios.descarga_picking)||0);
        });
        salidas.forEach(r=>{
          const det=r.picking_detalle||{pallet:{estibas:0,bultos:0,kilos:0},picking:{estibas:0,bultos:0,kilos:0}};
          const bP = fp==='K'?toNum(det.pallet.kilos):fp==='B'?toNum(det.pallet.bultos):toNum(det.pallet.estibas);
          const bPk= fp==='K'?toNum(det.picking.kilos):fp==='B'?toNum(det.picking.bultos):toNum(det.picking.estibas);
          moCarg += bP*(parseFloat(precios.mo_cargas)||0) + bPk*(parseFloat(precios.carga_picking)||0);
        });
      }

      // Almacenamiento
      const getStockBase = s => fp==='K'?parseFloat(s?.kilos||0):fp==='B'?parseFloat(s?.cantidad||0):parseFloat(s?.posiciones||0);
      const almCong = getStockBase(stock.congelado)*dias*(parseFloat(precios.mant_congelado)||0);
      const almEnfr = getStockBase(stock.enfriado)*dias*(parseFloat(precios.mant_enfriado)||0);
      const mantIn  = ingresos.reduce((s,r)=>{const p=parseInt(r.Congelado)===1?(parseFloat(precios.mant_congelado)||0):(parseFloat(precios.mant_enfriado)||0);return s+toBase(r)*(parseFloat(r.Dias)||0)*p;},0);
      const mantEg  = egresos.reduce((s,r)=>{const p=parseInt(r.Congelado)===1?(parseFloat(precios.mant_congelado)||0):(parseFloat(precios.mant_enfriado)||0);return s-toBase(r)*(parseFloat(r.Dias)||0)*p;},0);
      const alm     = almCong+almEnfr+mantIn+mantEg;

      // Congelado
      const precD2 = parseFloat(precios.mant_congelado)||0;
      const calcC  = (rows,p)=>rows.reduce((s,r)=>s+toBase(r)*(parseFloat(p)||0),0);
      let congTotal = 0;
      if(cong.tipo==='simple'){
        congTotal = calcC(cong.normal2||[],(parseFloat(precios.cong_norm2)||0)-2*precD2)
                  + calcC(cong.normal1||[],(parseFloat(precios.cong_norm1)||0)-2*precD2);
      } else {
        congTotal = calcC(cong.detalle3||[],(parseFloat(precios.cong_det3)||0)-2*precD2)
                  + calcC(cong.detalle2||[],(parseFloat(precios.cong_det2)||0)-2*precD2)
                  + calcC(cong.detalle1||[],(parseFloat(precios.cong_det1)||0)-2*precD2);
      }

      const mo  = moDesc+moCarg;
      const sub = alm+congTotal+mo;
      const iva = sub*0.21;
      const tot = sub+iva;

      totalAlm   += alm;
      totalCong  += congTotal;
      totalMO    += mo;
      totalSub   += sub;
      totalIva   += iva;
      totalTotal += tot;

      filas += `<tr>
        <td style="font-weight:600">${item.cliente.nombre}</td>
        <td class="num">${fmtM(alm)}</td>
        <td class="num">${fmtM(congTotal)}</td>
        <td class="num">${fmtM(mo)}</td>
        <td class="num">${fmtM(sub)}</td>
        <td class="num">${fmtM(iva)}</td>
        <td class="num" style="font-weight:800">${fmtM(tot)}</td>
      </tr>`;
    }

    resumenGlobalHtml = `
      <div class="page-break">
        <table class="header-table">
          <tr>
            <td class="logo-cell" rowspan="2">CINA S.R.L</td>
            <td class="title-cell" rowspan="2"><strong>RESUMEN CONSOLIDADO</strong><br>${items[0]?.desde ? fmtD(items[0].desde)+' → '+fmtD(items[0].hasta) : ''}</td>
            <td class="meta-cell"><span class="meta-label">Clientes</span><span class="meta-val">${items.length}</span></td>
          </tr>
          <tr>
            <td class="meta-cell"><span class="meta-label">Fecha impresión</span><span class="meta-val">${fechaImpresion}</span></td>
          </tr>
        </table>

        <div class="resumen-global">
          <h2>📊 Resumen de facturación por cliente</h2>
          <table>
            <thead><tr>
              <th>Cliente</th>
              <th class="num">Almacenamiento</th>
              <th class="num">Congelado</th>
              <th class="num">Carga/Desc.</th>
              <th class="num">Subtotal</th>
              <th class="num">IVA 21%</th>
              <th class="num">TOTAL</th>
            </tr></thead>
            <tbody>
              ${filas}
              <tr class="grand-total">
                <td>TOTAL GENERAL (${items.length} clientes)</td>
                <td class="num">${fmtM(totalAlm)}</td>
                <td class="num">${fmtM(totalCong)}</td>
                <td class="num">${fmtM(totalMO)}</td>
                <td class="num">${fmtM(totalSub)}</td>
                <td class="num">${fmtM(totalIva)}</td>
                <td class="num">${fmtM(totalTotal)}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>`;
  }

  return `<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Resumen de Facturación — Sistema Grupo CINA</title>
  <style>${css}</style>
</head>
<body>
  <div class="no-print" style="position:sticky;top:0;background:#fff;z-index:100;padding:10px 0;border-bottom:1px solid #eee">
    <button onclick="window.print()" style="background:#0C2554;color:#fff;border:none;border-radius:7px;padding:9px 22px;font-size:12px;font-weight:700;cursor:pointer">🖨️ Imprimir / PDF</button>
    <button onclick="window.close()" style="background:#eee;color:#333;border:1px solid #ccc;border-radius:7px;padding:9px 18px;font-size:12px;cursor:pointer">✕ Cerrar</button>
    ${items.length > 1 ? `<span style="font-size:11px;color:#666;margin-left:12px">${items.length} clientes · ${conResumenFinal ? 'con resumen consolidado' : 'reportes separados'}</span>` : ''}
  </div>
  ${paginasHtml}
  ${resumenGlobalHtml}
</body>
</html>`;
}



/* ══════════════════════════════════════════════════════════════
   LOG DE CARGA
   ══════════════════════════════════════════════════════════════ */


/* ══════════════════════════════════════════════════════════════
   MÓDULO: GESTIÓN DE FACTURAS
   ══════════════════════════════════════════════════════════════ */

async function renderGestionFacturas() {
  setHeader('GESTIÓN DE FACTURAS');
  showMetaPanel(false);
  setExpandedMode(true);
  menuGrid.className = '';
  menuGrid.innerHTML = '';
  syncBackBtn();

  const esCliente = esClientePortal();

  const wrap = document.createElement('div');
  wrap.className = 'fact-wrap gf-full-wrap';
  wrap.innerHTML = `
    <div class="fact-header">
      <div class="fact-header-title">
        <span class="indicador-badge" style="background:rgba(34,197,94,.12);color:#22c55e;border-color:rgba(34,197,94,.3)">$</span>
        GESTIÓN DE FACTURAS
      </div>
      ${!esCliente ? `<button class="compras-main-btn compras-btn-nueva" id="btnNuevaFactura" style="margin-left:auto">
        + Nueva factura
      </button>` : ''}
    </div>

    <div class="gf-filtros-row">
      <input class="oc-input gf-search" id="gfSearch" placeholder="🔍 Buscar por cliente, N° factura..." />
      <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
        <label style="display:flex;align-items:center;gap:3px;font-size:.75rem;cursor:pointer;color:#facc15"><input type="checkbox" class="gf-estado-chk" value="PENDIENTE" checked> Pendiente</label>
        <label style="display:flex;align-items:center;gap:3px;font-size:.75rem;cursor:pointer;color:#22c55e"><input type="checkbox" class="gf-estado-chk" value="PAGADA"> Pagada</label>
        <label style="display:flex;align-items:center;gap:3px;font-size:.75rem;cursor:pointer;color:#f87171"><input type="checkbox" class="gf-estado-chk" value="VENCIDA" checked> Vencida</label>
        <label style="display:flex;align-items:center;gap:3px;font-size:.75rem;cursor:pointer;color:#94a3b8"><input type="checkbox" class="gf-estado-chk" value="ANULADA"> Anulada</label>
      </div>
      <input type="month" class="oc-input gf-month" id="gfFiltroPeriodo" title="Filtrar por período" />
    </div>
    <div id="gfResumen" style="display:flex;gap:16px;flex-wrap:wrap;padding:8px 12px;background:rgba(255,255,255,.04);border-radius:8px;margin-bottom:8px;font-size:.78rem;color:rgba(255,255,255,.7)"></div>

    <div class="gf-tabla-wrap">
      <div id="gfCargando" style="text-align:center;padding:40px;color:#94a3b8">Cargando facturas...</div>
      <table class="oc-table gf-tabla" id="gfTabla" style="display:none">
        <thead>
          <tr>
            <th>N° Factura</th>
            <th>Cliente / Grupo</th>
            <th>Período</th>
            <th>Emisión</th>
            <th style="text-align:right">Sin IVA</th>
            <th style="text-align:right">Total</th>
            <th>Vencimiento</th>
            <th>Estado</th>
            ${esCliente ? '<th>OC / Pago est.</th>' : '<th>Plazo</th>'}
            <th style="text-align:center">PDF</th>
            ${!esCliente ? '<th></th>' : ''}
          </tr>
        </thead>
        <tbody id="gfTbody"></tbody>
      </table>
      <div id="gfVacio" style="display:none;text-align:center;padding:40px;color:#94a3b8">
        No hay facturas que coincidan con los filtros.
      </div>
    </div>`;

  menuGrid.appendChild(wrap);
  syncBackBtn();

  // ── Cargar datos ──────────────────────────────────────────────
  let facturas = [];
  try {
    const res  = await fetch(`${API_EQUIPOS_URL}?action=gestion_facturas_lista`, { credentials: 'same-origin' });
    const data = await res.json();
    facturas   = Array.isArray(data.facturas) ? data.facturas : [];
  } catch(e) { facturas = []; }

  const cargandoEl = wrap.querySelector('#gfCargando');
  const tablaEl    = wrap.querySelector('#gfTabla');
  const tbodyEl    = wrap.querySelector('#gfTbody');
  const vacioEl    = wrap.querySelector('#gfVacio');

  const estadoColor = {
    PENDIENTE: '#FACC15', PAGADA: '#22c55e', VENCIDA: '#f87171', ANULADA: '#94a3b8'
  };

  const fmtMonto = n => (n === null || n === undefined || n === '')
    ? '—'
    : '$ ' + parseFloat(n).toLocaleString('es-AR', { minimumFractionDigits:2, maximumFractionDigits:2 });

  function renderTabla(lista) {
    cargandoEl.style.display = 'none';
    if (!lista.length) {
      tablaEl.style.display = 'none';
      vacioEl.style.display = 'block';
      return;
    }
    vacioEl.style.display = 'none';
    tablaEl.style.display = '';

    tbodyEl.innerHTML = lista.map(f => {
      const color = estadoColor[f.estado] || '#94a3b8';

      const adjs = f.adjuntos || [];
      let adjCell = '';
      if (adjs.length) {
        adjCell = adjs.map(a =>
          `<a href="${a.ruta}" target="_blank" title="${a.nombre_original||'Archivo'}" style="display:inline-flex;align-items:center;gap:3px;padding:2px 8px;background:rgba(56,189,248,.1);border:1px solid rgba(56,189,248,.25);border-radius:5px;color:#38bdf8;font-size:.68rem;font-weight:700;text-decoration:none;margin:1px 0">${a.tipo==='pdf'?'📄':'📎'} ${(a.nombre_original||'archivo').slice(0,15)}</a>`
        ).join('');
      } else if (f.url_factura) {
        adjCell = `<a href="${f.url_factura}" target="_blank" style="display:inline-flex;align-items:center;gap:3px;padding:2px 8px;background:rgba(56,189,248,.1);border:1px solid rgba(56,189,248,.25);border-radius:5px;color:#38bdf8;font-size:.68rem;font-weight:700;text-decoration:none">📄 PDF</a>`;
      }
      if (!esCliente) {
        adjCell += `<label style="display:inline-flex;align-items:center;gap:2px;padding:2px 6px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:5px;color:rgba(255,255,255,.4);font-size:.65rem;cursor:pointer;margin:1px 0" title="Subir archivo">＋<input type="file" class="gf-upload-pdf" data-id="${f.id}" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" style="display:none"></label>`;
      }
      if (!adjCell) adjCell = '<span style="color:#475569;font-size:.7rem">—</span>';

      const extraCell = esCliente
        ? `<td>
            ${f.nro_oc
              ? `<div style="font-size:.8rem">OC: <strong>${f.nro_oc}</strong></div>
                 ${f.fecha_pago_estimada ? `<div style="font-size:.76rem;color:#94a3b8">${f.fecha_pago_estimada}</div>` : ''}`
              : `<button class="gf-btn-cargar-oc oc-adj-remove" data-id="${f.id}"
                   style="color:#63b3ed;font-size:.78rem;background:rgba(99,179,237,.1);
                          border:1px solid rgba(99,179,237,.3);padding:3px 8px;border-radius:4px;cursor:pointer">
                   ✏ Cargar OC
                 </button>`}
           </td>`
        : `<td style="font-size:.82rem">${f.plazo_dias ? f.plazo_dias + ' días' : '—'}</td>`;

      const editCell = !esCliente
        ? `<td><button class="oc-del-btn gf-btn-editar" data-id="${f.id}" title="Editar" style="color:#63b3ed">✏</button></td>`
        : '';

      return `<tr data-id="${f.id}">
        <td><strong>${f.nro_factura || '—'}</strong></td>
        <td>
          <div>${f.cliente_nombre || '—'}</div>
          ${f.grupo_nombre ? `<div style="font-size:.75rem;color:#94a3b8">${f.grupo_nombre}</div>` : ''}
        </td>
        <td style="font-size:.8rem;white-space:nowrap">
          ${f.periodo_desde || '—'}<br><span style="color:#94a3b8">→ ${f.periodo_hasta || '—'}</span>
        </td>
        <td style="font-size:.82rem;white-space:nowrap">${f.fecha_factura || '—'}</td>
        <td style="text-align:right">${fmtMonto(f.monto_sin_iva)}</td>
        <td style="text-align:right;font-weight:600">${fmtMonto(f.monto_final)}</td>
        <td style="font-size:.82rem;white-space:nowrap">${f.fecha_vencimiento || '—'}</td>
        <td><span class="indicador-badge"
              style="background:${color}22;color:${color};border-color:${color}44;font-size:.73rem"
              >${f.estado}</span></td>
        ${extraCell}
        <td style="text-align:center">${adjCell}</td>
        ${editCell}
      </tr>`;
    }).join('');

    // Eventos botones
    tbodyEl.querySelectorAll('.gf-btn-cargar-oc').forEach(btn => {
      btn.addEventListener('click', () => {
        const fac = lista.find(f => String(f.id) === btn.dataset.id);
        abrirModalOC(btn.dataset.id, fac);
      });
    });
    tbodyEl.querySelectorAll('.gf-btn-editar').forEach(btn => {
      btn.addEventListener('click', () => {
        const fac = lista.find(f => String(f.id) === btn.dataset.id);
        abrirModalFactura(fac);
      });
    });
    // Upload PDF inline
    tbodyEl.querySelectorAll('.gf-upload-pdf').forEach(inp => {
      inp.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file) return;
        const id = inp.dataset.id;
        const fd = new FormData();
        fd.append('id', id);
        fd.append('pdf', file);
        try {
          const r = await fetch(`${API_EQUIPOS_URL}?action=gestion_factura_subir_pdf`, {
            method:'POST', credentials:'same-origin', body: fd
          });
          const d = await r.json();
          if (d.error) throw new Error(d.error);
          showToast('📄 PDF subido correctamente');
          cargarFacturas();
        } catch(err) { showToast('Error: '+err.message, 'error'); }
      });
    });
  }

  renderTabla(facturas);

  // ── Filtros ───────────────────────────────────────────────────
  function filtrar() {
    const q   = (wrap.querySelector('#gfSearch').value || '').toLowerCase();
    const mes = wrap.querySelector('#gfFiltroPeriodo').value;
    const estadosSel = [...wrap.querySelectorAll('.gf-estado-chk:checked')].map(c => c.value);
    const hayFiltroEstado = estadosSel.length > 0 && estadosSel.length < 4;

    const filtrados = facturas.filter(f => {
      const matchQ = !q
        || (f.nro_factura||'').toLowerCase().includes(q)
        || (f.cliente_nombre||'').toLowerCase().includes(q)
        || (f.grupo_nombre||'').toLowerCase().includes(q);
      const matchE = !hayFiltroEstado || estadosSel.includes(f.estado);
      const matchM = !mes
        || (f.periodo_desde||'').startsWith(mes)
        || (f.periodo_hasta||'').startsWith(mes)
        || (f.fecha_factura||'').startsWith(mes);
      return matchQ && matchE && matchM;
    });
    renderTabla(filtrados);

    // Resumen por estado
    const resEl = wrap.querySelector('#gfResumen');
    if (resEl) {
      const fmtM = v => '$' + v.toLocaleString('es-AR',{minimumFractionDigits:2});
      const cnt = filtrados.length;
      const totalFinal = filtrados.reduce((s,f) => s + (parseFloat(f.monto_final)||0), 0);
      // Desglose por estado
      const porEstado = {};
      filtrados.forEach(f => {
        const e = f.estado || 'PENDIENTE';
        if (!porEstado[e]) porEstado[e] = { cnt:0, total:0 };
        porEstado[e].cnt++;
        porEstado[e].total += parseFloat(f.monto_final)||0;
      });
      const colores = {PENDIENTE:'#facc15',PAGADA:'#22c55e',VENCIDA:'#f87171',ANULADA:'#94a3b8'};
      const desglose = Object.entries(porEstado).map(([e,d]) =>
        `<span style="color:${colores[e]||'#fff'}">${e}: ${d.cnt} · ${fmtM(d.total)}</span>`
      ).join('');
      resEl.innerHTML = `<span><strong>${cnt}</strong> factura${cnt!==1?'s':''}</span>
        <span>Total: <strong style="color:#4ade80">${fmtM(totalFinal)}</strong></span>
        <span style="margin-left:8px;display:flex;gap:12px;flex-wrap:wrap">${desglose}</span>`;
    }
  }
  wrap.querySelector('#gfSearch').addEventListener('input', filtrar);
  wrap.querySelectorAll('.gf-estado-chk').forEach(c => c.addEventListener('change', filtrar));
  wrap.querySelector('#gfFiltroPeriodo').addEventListener('change', filtrar);
  filtrar(); // calcular resumen inicial

  // ── Modal crear / editar factura (solo admin/interno) ─────────
  // Cache para no pedir dos veces en la misma sesión de la página
  let _gfClientesCache = null;

  async function cargarClientesSelect() {
    if (_gfClientesCache) return _gfClientesCache;
    try {
      const r = await fetch(`${API_EQUIPOS_URL}?action=grupos_clientes_lista`, { credentials: 'same-origin' });
      const d = await r.json();
      _gfClientesCache = { grupos: d.grupos || [], clientesPorGrupo: d.clientes_por_grupo || {} };
      return _gfClientesCache;
    } catch(e) { return { grupos: [], clientesPorGrupo: {} }; }
  }

  function abrirModalFactura(datos = null) {
    const esEdit = !!datos;
    const hoyISO = new Date().toISOString().split('T')[0];
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.innerHTML = `
      <div class="modal-box" style="max-width:640px;width:96vw;max-height:90vh;overflow-y:auto">
        <div class="modal-header">
          <span>${esEdit ? 'Editar factura' : 'Nueva factura'}</span>
          <button class="modal-close-btn" id="gfMClose">✕</button>
        </div>
        <div style="padding:16px 20px;display:flex;flex-direction:column;gap:12px">

          <div class="oc-fields-grid" style="grid-template-columns:1fr 1fr;gap:12px">

            <div class="oc-field-group">
              <label class="fact-date-label">N° Factura *</label>
              <input class="oc-input" id="gfMNro" placeholder="0001-00012345"
                value="${datos?.nro_factura || ''}" />
            </div>

            <div class="oc-field-group">
              <label class="fact-date-label">Fecha de emisión *</label>
              <input class="oc-input" type="date" id="gfMFecha"
                value="${datos?.fecha_factura || hoyISO}" />
            </div>

            <div class="oc-field-group" style="grid-column:span 2">
              <label class="fact-date-label">Cliente / Grupo *</label>
              <div style="display:flex;gap:8px;align-items:center;margin-bottom:4px">
                <label style="display:flex;align-items:center;gap:4px;font-size:.8rem;font-weight:400;cursor:pointer">
                  <input type="radio" name="gfMTipoCli" id="gfMTipoCliente" value="cliente" checked /> Cliente
                </label>
                <label style="display:flex;align-items:center;gap:4px;font-size:.8rem;font-weight:400;cursor:pointer">
                  <input type="radio" name="gfMTipoCli" id="gfMTipoGrupo" value="grupo" /> Grupo
                </label>
              </div>
              <select class="oc-select" id="gfMCliente">
                <option value="">— Cargando... —</option>
              </select>
            </div>

            <div class="oc-field-group">
              <label class="fact-date-label">Período desde</label>
              <input class="oc-input" type="date" id="gfMDesde"
                value="${datos?.periodo_desde || ''}" />
            </div>

            <div class="oc-field-group">
              <label class="fact-date-label">Período hasta</label>
              <input class="oc-input" type="date" id="gfMHasta"
                value="${datos?.periodo_hasta || ''}" />
            </div>

            <div class="oc-field-group">
              <label class="fact-date-label">Monto sin IVA</label>
              <input class="oc-input" type="number" min="0" step="0.01" id="gfMSinIva"
                placeholder="0,00" value="${datos?.monto_sin_iva || ''}" />
            </div>

            <div class="oc-field-group">
              <label class="fact-date-label">Monto final (con IVA) *</label>
              <input class="oc-input" type="number" min="0" step="0.01" id="gfMFinal"
                placeholder="0,00" value="${datos?.monto_final || ''}" />
            </div>

            <div class="oc-field-group">
              <label class="fact-date-label">Plazo de pago (días)</label>
              <input class="oc-input" type="number" min="0" id="gfMPlazo"
                placeholder="30" value="${datos?.plazo_dias || 30}" />
            </div>

            <div class="oc-field-group">
              <label class="fact-date-label">Fecha de vencimiento</label>
              <input class="oc-input" type="date" id="gfMVenc"
                value="${datos?.fecha_vencimiento || ''}" />
            </div>

            <div class="oc-field-group" style="grid-column:span 2">
              <label class="fact-date-label">Estado</label>
              <select class="oc-select" id="gfMEstado">
                <option value="PENDIENTE" ${(!datos||datos.estado==='PENDIENTE')?'selected':''}>Pendiente</option>
                <option value="PAGADA"    ${datos?.estado==='PAGADA'   ?'selected':''}>Pagada</option>
                <option value="VENCIDA"   ${datos?.estado==='VENCIDA'  ?'selected':''}>Vencida</option>
                <option value="ANULADA"   ${datos?.estado==='ANULADA'  ?'selected':''}>Anulada</option>
              </select>
            </div>

            <div class="oc-field-group" style="grid-column:span 2">
              <label class="fact-date-label">📎 Adjuntar factura PDF</label>
              <label class="oc-adjunto-label" style="padding:8px 14px" for="gfMPdfInput">
                <span>📄</span>
                <span id="gfMPdfTexto">${datos?.url_factura ? 'Tiene PDF — click para reemplazar' : 'Seleccionar PDF (máx. 15 MB)'}</span>
                <input type="file" id="gfMPdfInput" accept="application/pdf,.pdf" style="display:none" />
              </label>
              ${datos?.url_factura
                ? `<div style="display:flex;gap:8px;align-items:center;margin-top:4px">
                     <a href="${datos.url_factura}" target="_blank" class="oc-adj-link" style="font-size:.8rem">Ver PDF actual →</a>
                     <button type="button" id="gfMEliminarPdf" style="font-size:.7rem;padding:3px 8px;background:rgba(239,68,68,.12);color:#f87171;border:1px solid rgba(239,68,68,.3);border-radius:5px;cursor:pointer">🗑 Eliminar PDF</button>
                   </div>`
                : ''}
            </div>

          </div>

          <div id="gfMError" style="color:#f87171;font-size:.84rem;display:none"></div>
        </div>

        <div style="display:flex;gap:8px;justify-content:flex-end;padding:12px 20px;
                    border-top:1px solid rgba(255,255,255,.07)">
          <button class="fact-sel-none-btn" id="gfMCancelar">Cancelar</button>
          <button class="fact-download-btn" id="gfMGuardar" style="min-width:130px">
            ${esEdit ? '💾 Guardar cambios' : '➕ Crear factura'}
          </button>
        </div>
      </div>`;

    document.body.appendChild(overlay);

    // Poblar select de clientes/grupos
    const poblarSelect = (tipo, selData) => {
      const sel = overlay.querySelector('#gfMCliente');
      if (tipo === 'grupo') {
        sel.innerHTML = '<option value="">— Seleccionar grupo —</option>' +
          selData.grupos.map(g =>
            `<option value="${g.GrupoID}" ${datos?.grupo_id == g.GrupoID ? 'selected' : ''}>${g.Nombre}</option>`
          ).join('');
      } else {
        // Mostrar agrupado: optgroup por grupo, más clientes sin grupo
        let opts = '<option value="">— Seleccionar cliente —</option>';
        selData.grupos.forEach(g => {
          const hijos = selData.clientesPorGrupo[g.GrupoID] || [];
          if (!hijos.length) return;
          opts += `<optgroup label="${g.Nombre}">`;
          hijos.forEach(c => {
            opts += `<option value="${c.ClienteID}" ${datos?.cliente_id == c.ClienteID ? 'selected' : ''}>${c.ClienteNombre}</option>`;
          });
          opts += '</optgroup>';
        });
        sel.innerHTML = opts;
      }
    };

    cargarClientesSelect().then(selData => {
      // Determinar tipo inicial según datos existentes
      const tipoInicial = (datos?.cliente_tipo === 'grupo') ? 'grupo' : 'cliente';
      if (tipoInicial === 'grupo') {
        const radGrupo = overlay.querySelector('#gfMTipoGrupo');
        if (radGrupo) radGrupo.checked = true;
      }
      poblarSelect(tipoInicial, selData);

      // Cambio de tipo
      overlay.querySelectorAll('input[name="gfMTipoCli"]').forEach(radio => {
        radio.addEventListener('change', () => poblarSelect(radio.value, selData));
      });
    });

    // Auto-calcular vencimiento
    const calcVenc = () => {
      const fecha = overlay.querySelector('#gfMFecha').value;
      const plazo = parseInt(overlay.querySelector('#gfMPlazo').value) || 0;
      if (fecha && plazo) {
        const d = new Date(fecha);
        d.setDate(d.getDate() + plazo);
        overlay.querySelector('#gfMVenc').value = d.toISOString().split('T')[0];
      }
    };
    overlay.querySelector('#gfMFecha').addEventListener('change', calcVenc);
    overlay.querySelector('#gfMPlazo').addEventListener('input', calcVenc);
    // Calcular vencimiento inicial si hay fecha y plazo
    calcVenc();

    // PDF file pick
    let pdfFile = null;
    overlay.querySelector('#gfMPdfInput').addEventListener('change', e => {
      pdfFile = e.target.files[0] || null;
      overlay.querySelector('#gfMPdfTexto').textContent = pdfFile
        ? `📄 ${pdfFile.name}`
        : (datos?.url_factura ? 'Tiene PDF — click para reemplazar' : 'Seleccionar PDF (máx. 15 MB)');
    });

    const cerrar = () => overlay.remove();
    overlay.querySelector('#gfMClose').addEventListener('click', cerrar);
    overlay.querySelector('#gfMCancelar').addEventListener('click', cerrar);
    overlay.addEventListener('click', e => { if (e.target === overlay) cerrar(); });

    // Eliminar PDF adjunto
    overlay.querySelector('#gfMEliminarPdf')?.addEventListener('click', async () => {
      if (!confirm('¿Eliminar el PDF adjunto de esta factura?')) return;
      try {
        const r = await fetch(`${API_EQUIPOS_URL}?action=gestion_factura_eliminar_pdf`, {
          method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'},
          body: JSON.stringify({ id: datos.id })
        });
        const d = await r.json();
        if (d.error) throw new Error(d.error);
        showToast('🗑 PDF eliminado');
        cerrar();
        cargarFacturas();
      } catch(e) { showToast('Error: '+e.message, 'error'); }
    });

    overlay.querySelector('#gfMGuardar').addEventListener('click', async () => {
      const errEl = overlay.querySelector('#gfMError');
      errEl.style.display = 'none';

      const nro    = (overlay.querySelector('#gfMNro').value || '').trim();
      const fecha  = overlay.querySelector('#gfMFecha').value;
      const cliId  = parseInt(overlay.querySelector('#gfMCliente').value) || 0;
      const mFinal = parseFloat(overlay.querySelector('#gfMFinal').value) || 0;

      if (!nro)    { errEl.textContent = 'El N° de factura es obligatorio'; errEl.style.display='block'; return; }
      if (!fecha)  { errEl.textContent = 'La fecha de emisión es obligatoria'; errEl.style.display='block'; return; }
      if (!cliId)  { errEl.textContent = 'Seleccioná un cliente o grupo'; errEl.style.display='block'; return; }
      if (!mFinal) { errEl.textContent = 'El monto final es obligatorio'; errEl.style.display='block'; return; }

      const btn = overlay.querySelector('#gfMGuardar');
      btn.disabled = true;
      btn.textContent = '⏳ Guardando...';

      try {
        const tipoSel = overlay.querySelector('input[name="gfMTipoCli"]:checked')?.value || 'cliente';
        const action  = esEdit ? 'gestion_factura_editar' : 'gestion_factura_crear';
        const payload = {
          id:               datos?.id || null,
          nro_factura:      nro,
          fecha_factura:    fecha,
          cliente_tipo:     tipoSel,
          cliente_id:       tipoSel === 'cliente' ? cliId : 0,
          grupo_id:         tipoSel === 'grupo'   ? cliId : 0,
          periodo_desde:    overlay.querySelector('#gfMDesde').value || null,
          periodo_hasta:    overlay.querySelector('#gfMHasta').value || null,
          monto_sin_iva:    parseFloat(overlay.querySelector('#gfMSinIva').value) || null,
          monto_final:      mFinal,
          plazo_dias:       parseInt(overlay.querySelector('#gfMPlazo').value) || null,
          fecha_vencimiento:overlay.querySelector('#gfMVenc').value || null,
          estado:           overlay.querySelector('#gfMEstado').value,
        };

        const res  = await fetch(`${API_EQUIPOS_URL}?action=${action}`, {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.error) throw new Error(data.error);

        // Subir PDF si hay archivo seleccionado (después de crear para tener el ID)
        const facturaId = data.id || datos?.id;
        if (pdfFile && facturaId) {
          const fd = new FormData();
          fd.append('id', facturaId);
          fd.append('pdf', pdfFile);
          await fetch(`${API_EQUIPOS_URL}?action=gestion_factura_subir_pdf`, {
            method: 'POST', credentials: 'same-origin', body: fd
          });
        }

        showToast(`✅ Factura ${esEdit ? 'actualizada' : 'creada'} correctamente`);
        cerrar();
        renderGestionFacturas();
      } catch(err) {
        errEl.textContent = err.message || 'Error al guardar';
        errEl.style.display = 'block';
        btn.disabled = false;
        btn.textContent = esEdit ? '💾 Guardar cambios' : '➕ Crear factura';
      }
    });
  }

  // ── Modal cargar OC + fecha pago estimada (vista cliente) ─────
  function abrirModalOC(facturaId, factura) {
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.innerHTML = `
      <div class="modal-box" style="max-width:420px">
        <div class="modal-header">
          <span>Datos de pago — ${factura?.nro_factura || ''}</span>
          <button class="modal-close-btn" id="gfOCClose">✕</button>
        </div>
        <div style="padding:16px 20px;display:flex;flex-direction:column;gap:14px">
          <div class="oc-field-group">
            <label class="fact-date-label">N° Orden de compra</label>
            <input class="oc-input" id="gfOCNro" placeholder="OC-2025-XXXX"
              value="${factura?.nro_oc || ''}" />
          </div>
          <div class="oc-field-group">
            <label class="fact-date-label">Fecha estimada de pago</label>
            <input class="oc-input" type="date" id="gfOCFecha"
              value="${factura?.fecha_pago_estimada || ''}" />
          </div>
          <div id="gfOCErr" style="color:#f87171;font-size:.84rem;display:none"></div>
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end;padding:12px 20px;
                    border-top:1px solid rgba(255,255,255,.07)">
          <button class="fact-sel-none-btn" id="gfOCCancelar">Cancelar</button>
          <button class="fact-download-btn" id="gfOCGuardar">💾 Guardar</button>
        </div>
      </div>`;

    document.body.appendChild(overlay);
    const cerrar = () => overlay.remove();
    overlay.querySelector('#gfOCClose').addEventListener('click', cerrar);
    overlay.querySelector('#gfOCCancelar').addEventListener('click', cerrar);
    overlay.addEventListener('click', e => { if (e.target === overlay) cerrar(); });

    overlay.querySelector('#gfOCGuardar').addEventListener('click', async () => {
      const nroOC  = (overlay.querySelector('#gfOCNro').value || '').trim();
      const fechaP = overlay.querySelector('#gfOCFecha').value;
      const errEl  = overlay.querySelector('#gfOCErr');
      if (!nroOC && !fechaP) {
        errEl.textContent = 'Completá al menos el N° de OC o la fecha estimada';
        errEl.style.display = 'block'; return;
      }
      try {
        const res  = await fetch(`${API_EQUIPOS_URL}?action=gestion_factura_cargar_oc`, {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: facturaId, nro_oc: nroOC, fecha_pago_estimada: fechaP })
        });
        const data = await res.json();
        if (data.error) throw new Error(data.error);
        showToast('✅ Datos de pago guardados');
        cerrar();
        renderGestionFacturas();
      } catch(err) {
        errEl.textContent = err.message || 'Error';
        errEl.style.display = 'block';
      }
    });
  }

  // ── Botón nueva factura ───────────────────────────────────────
  if (!esCliente) {
    wrap.querySelector('#btnNuevaFactura')?.addEventListener('click', () => abrirModalFactura());
  }
}


console.log('✓ facturacion.js cargado — renderFacturacion() + renderGestionFacturas() disponibles');