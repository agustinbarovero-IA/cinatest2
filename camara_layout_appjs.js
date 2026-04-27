
/* ═══════════════════════════════════════════════════════════════════════════
   LAYOUT DE CÁMARA — Vista de pilas por calle/fila con detalle de estibas
   Agregar en app.js, justo antes de la función renderHistorialCamara
   ═══════════════════════════════════════════════════════════════════════════ */

/* ─── Buscador global de estibas ─────────────────────────────────────────── */
let _busquedaEstibaTimer = null;

function renderBuscadorEstibas() {
  return `
    <div class="cam-buscador-wrap" id="camBuscadorWrap">
      <div class="cam-buscador-inner">
        <span class="cam-buscador-icon">🔍</span>
        <input
          type="text"
          id="camBuscadorInput"
          class="cam-buscador-input"
          placeholder="Buscar estiba en todas las cámaras..."
          autocomplete="off"
          spellcheck="false"
        />
        <button class="cam-buscador-clear" id="camBuscadorClear" style="display:none">✕</button>
      </div>
      <div class="cam-buscador-resultados" id="camBuscadorResultados" style="display:none"></div>
    </div>`;
}

function initBuscadorEstibas(onSelectCamara) {
  const input   = document.getElementById('camBuscadorInput');
  const clear   = document.getElementById('camBuscadorClear');
  const results = document.getElementById('camBuscadorResultados');
  if (!input) return;

  input.addEventListener('input', () => {
    const q = input.value.trim();
    clear.style.display = q ? 'flex' : 'none';
    clearTimeout(_busquedaEstibaTimer);
    if (q.length < 2) { results.style.display = 'none'; return; }
    _busquedaEstibaTimer = setTimeout(() => buscarEstibaGlobal(q, results, onSelectCamara), 280);
  });

  clear.addEventListener('click', () => {
    input.value = '';
    clear.style.display = 'none';
    results.style.display = 'none';
    input.focus();
  });

  document.addEventListener('click', e => {
    if (!document.getElementById('camBuscadorWrap')?.contains(e.target)) {
      if (results) results.style.display = 'none';
    }
  }, { capture: true });
}

async function buscarEstibaGlobal(q, resultsEl, onSelectCamara) {
  if (!resultsEl) return;
  resultsEl.innerHTML = `<div class="cam-bus-loading">Buscando...</div>`;
  resultsEl.style.display = 'block';

  try {
    const res  = await fetch(`${API_EQUIPOS_URL}?action=buscar_estiba&q=${encodeURIComponent(q)}`, { credentials: 'same-origin' });
    const data = await res.json();

    if (!data.length) {
      resultsEl.innerHTML = `<div class="cam-bus-empty">Sin resultados para <strong>${q}</strong></div>`;
      return;
    }

    resultsEl.innerHTML = data.map(r => `
      <button class="cam-bus-item" data-camara-id="${r.camara_id}" data-pila="${r.calle}_${r.fila}" data-estiba-id="${r.estiba_id}">
        <div class="cam-bus-item-left">
          <span class="cam-bus-estiba-id">#${r.estiba_id}</span>
          <span class="cam-bus-producto">${r.producto || 'Sin producto'}</span>
        </div>
        <div class="cam-bus-item-right">
          <span class="cam-bus-camara">${r.camara_nombre}</span>
          <span class="cam-bus-pos">C${r.calle} · F${r.fila} · N${r.nivel}</span>
        </div>
      </button>`).join('');

    resultsEl.querySelectorAll('.cam-bus-item').forEach(btn => {
      btn.addEventListener('click', () => {
        resultsEl.style.display = 'none';
        document.getElementById('camBuscadorInput').value = `#${btn.dataset.estibaId}`;
        onSelectCamara(parseInt(btn.dataset.camaraId), btn.dataset.pila);
      });
    });

  } catch(e) {
    resultsEl.innerHTML = `<div class="cam-bus-empty">Error al buscar</div>`;
  }
}

/* ─── Render del layout de cámara ────────────────────────────────────────── */
async function renderLayoutCamaras(camara) {
  menuGrid.className = 'menu-grid';
  historyStack.push({ __custom: 'dashboard_nacional' });
  setHeader(camara.nombre_display + ' — Mapa de pilas');

  menuGrid.innerHTML = `
    <div class="cam-layout-wrap" id="camLayoutWrap">
      ${renderBuscadorEstibas()}

      <div class="cam-layout-topbar">
        <button class="mod-back-btn" id="camLayoutBack">← Volver</button>
        <div class="cam-layout-info">
          <span class="cam-layout-nombre">${camara.nombre_display}</span>
          <span class="cam-layout-temp" style="background:${getTemperatureStyle(camara.temperatura)}">
            ${camara.temperatura !== null ? camara.temperatura + '°C' : '—'}
          </span>
        </div>
        <div class="cam-layout-leyenda">
          <span class="cam-ley-item"><span class="cam-ley-dot" style="background:#22C55E"></span>1 alt.</span>
          <span class="cam-ley-item"><span class="cam-ley-dot" style="background:#FACC15"></span>2–3 alt.</span>
          <span class="cam-ley-item"><span class="cam-ley-dot" style="background:#F97316"></span>4–5 alt.</span>
          <span class="cam-ley-item"><span class="cam-ley-dot" style="background:#EF4444"></span>6+ alt.</span>
          <span class="cam-ley-item"><span class="cam-ley-dot" style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15)"></span>Libre</span>
        </div>
      </div>

      <div class="cam-layout-body">
        <div class="cam-layout-grid-wrap" id="camLayoutGridWrap">
          <div class="cam-layout-loading" id="camLayoutLoading">
            <div class="cam-spin"></div>
            <span>Cargando mapa de la cámara...</span>
          </div>
        </div>

        <div class="cam-pila-panel" id="camPilaPanel" style="display:none">
          <div class="cam-pila-panel-header">
            <span class="cam-pila-panel-title" id="camPilaTitulo">Pila</span>
            <button class="cam-pila-panel-close" id="camPilaPanelClose">✕</button>
          </div>
          <div class="cam-pila-panel-body" id="camPilaBody">
          </div>
        </div>
      </div>

      <div class="cam-layout-stats" id="camLayoutStats" style="display:none">
        <span id="camStatTotal">—</span>
        <span id="camStatOcup">—</span>
        <span id="camStatPct">—</span>
      </div>
    </div>`;

  document.getElementById('camLayoutBack').addEventListener('click', () => {
    renderHistorialCamara(camara);
  });
  document.getElementById('camPilaPanelClose').addEventListener('click', () => {
    document.getElementById('camPilaPanel').style.display = 'none';
    document.querySelectorAll('.cam-pila-square.selected').forEach(el => el.classList.remove('selected'));
  });

  initBuscadorEstibas((camaraId, pilaKey) => {
    if (camaraId !== camara.camara_id) {
      // Ir a otra cámara
      const otraCamara = dashboardLogisticaNacionalData.find(c => c.camara_id === camaraId);
      if (otraCamara) renderLayoutCamaras(otraCamara);
    } else {
      // Resaltar pila en esta cámara
      const [c, f] = pilaKey.split('_');
      const target = document.querySelector(`.cam-pila-square[data-calle="${c}"][data-fila="${f}"]`);
      if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        target.classList.add('highlight-pulse');
        setTimeout(() => target.classList.remove('highlight-pulse'), 2000);
        target.click();
      }
    }
  });

  await cargarMapaCamara(camara);
}

async function cargarMapaCamara(camara) {
  const wrap = document.getElementById('camLayoutGridWrap');
  const loading = document.getElementById('camLayoutLoading');

  try {
    const res  = await fetch(`${API_EQUIPOS_URL}?action=camara_mapa&camara_id=${camara.camara_id}`, { credentials: 'same-origin' });
    const data = await res.json();

    if (loading) loading.style.display = 'none';

    const calles  = data.calles  || camara.calles  || 0;
    const filas   = data.filas   || camara.filas   || 0;
    const pilas   = data.pilas   || {};

    if (!calles || !filas) {
      wrap.innerHTML = `<div class="cam-layout-empty">Esta cámara no tiene estructura de calles/filas configurada.<br><span style="opacity:.5;font-size:.8rem">calles: ${calles}, filas: ${filas}</span></div>`;
      return;
    }

    // Stats
    const statsEl = document.getElementById('camLayoutStats');
    if (statsEl) {
      statsEl.style.display = 'flex';
      document.getElementById('camStatTotal').textContent = `${data.total_posiciones || 0} pos.`;
      document.getElementById('camStatOcup').textContent  = `${data.total_ocupadas || 0} ocupadas`;
      const pct = data.total_posiciones > 0 ? Math.round(data.total_ocupadas / data.total_posiciones * 100) : 0;
      document.getElementById('camStatPct').textContent   = `${pct}% llena`;
    }

    // Construir grilla: eje X = filas, eje Y = calles
    let html = `
      <div class="cam-grid-container">
        <div class="cam-grid-labels-filas">
          ${Array.from({length: filas}, (_, i) => `<span class="cam-grid-label-fila">F${i+1}</span>`).join('')}
        </div>
        <div class="cam-grid-rows">`;

    for (let c = 1; c <= calles; c++) {
      html += `<div class="cam-grid-row">
        <span class="cam-grid-label-calle">C${c}</span>
        <div class="cam-grid-cells">`;

      for (let f = 1; f <= filas; f++) {
        const key  = `${c}_${f}`;
        const pila = pilas[key] || { alturas: 0, estibas: [] };
        const alt  = pila.alturas || 0;
        const bg   = colorPorAltura(alt);
        const libre = alt === 0;

        html += `
          <button
            class="cam-pila-square${libre ? ' libre' : ''}"
            data-calle="${c}"
            data-fila="${f}"
            data-key="${key}"
            title="C${c} · F${f} · ${alt > 0 ? alt + ' altura' + (alt > 1 ? 's' : '') : 'Libre'}"
            style="${libre ? '' : `background:${bg}`}"
          >
            <span class="cam-pila-alt">${alt > 0 ? alt : ''}</span>
          </button>`;
      }

      html += `</div></div>`;
    }

    html += `</div></div>`;
    wrap.innerHTML = html;

    // Click en pila
    wrap.querySelectorAll('.cam-pila-square:not(.libre)').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.cam-pila-square.selected').forEach(el => el.classList.remove('selected'));
        btn.classList.add('selected');
        const key = btn.dataset.key;
        mostrarDetallePila(pilas[key] || {}, btn.dataset.calle, btn.dataset.fila, camara);
      });
    });

  } catch(e) {
    if (loading) loading.style.display = 'none';
    wrap.innerHTML = `<div class="cam-layout-empty">Error al cargar el mapa: ${e.message}</div>`;
  }
}

function colorPorAltura(alt) {
  if (alt === 0) return 'transparent';
  if (alt === 1) return '#15803d';
  if (alt <= 3)  return '#a16207';
  if (alt <= 5)  return '#c2410c';
  return '#b91c1c';
}

function mostrarDetallePila(pila, calle, fila, camara) {
  const panel  = document.getElementById('camPilaPanel');
  const titulo = document.getElementById('camPilaTitulo');
  const body   = document.getElementById('camPilaBody');
  if (!panel || !titulo || !body) return;

  titulo.textContent = `Calle ${calle} · Fila ${fila} — ${pila.alturas || 0} altura${pila.alturas !== 1 ? 's' : ''}`;

  const estibas = pila.estibas || [];
  if (!estibas.length) {
    body.innerHTML = `<div class="cam-pila-empty">Sin estibas en esta pila</div>`;
  } else {
    // Agrupar por nivel (altura)
    const porNivel = {};
    estibas.forEach(e => {
      const n = e.nivel || e.altura || 1;
      if (!porNivel[n]) porNivel[n] = [];
      porNivel[n].push(e);
    });

    body.innerHTML = Object.entries(porNivel)
      .sort(([a], [b]) => parseInt(b) - parseInt(a))
      .map(([nivel, ests]) => `
        <div class="cam-pila-nivel-group">
          <div class="cam-pila-nivel-label">Altura ${nivel}</div>
          ${ests.map(e => `
            <div class="cam-pila-estiba-card">
              <div class="cam-pila-estiba-header">
                <span class="cam-pila-estiba-id">#${e.estiba_id}</span>
                ${e.cliente_siglas ? `<span class="cam-pila-estiba-cliente" style="background:${e.cliente_color || '#36B0C9'}22;color:${e.cliente_color || '#36B0C9'};border-color:${e.cliente_color || '#36B0C9'}44">${e.cliente_siglas}</span>` : ''}
              </div>
              ${e.producto ? `<div class="cam-pila-estiba-prod">${e.producto}</div>` : ''}
              ${e.cantidad ? `<div class="cam-pila-estiba-meta">${e.cantidad} unid.${e.peso ? ' · ' + e.peso + ' kg' : ''}</div>` : ''}
              ${e.fecha_ingreso ? `<div class="cam-pila-estiba-fecha">Ingresó: ${formatFechaCorta(e.fecha_ingreso)}</div>` : ''}
              ${e.vencimiento ? `<div class="cam-pila-estiba-vence${esVencida(e.vencimiento) ? ' vencida' : ''}">Vence: ${formatFechaCorta(e.vencimiento)}</div>` : ''}
            </div>`).join('')}
        </div>`).join('');
  }

  panel.style.display = 'flex';
}

function formatFechaCorta(f) {
  if (!f) return '';
  const d = new Date(f);
  if (isNaN(d)) return f;
  return d.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: '2-digit' });
}

function esVencida(f) {
  if (!f) return false;
  return new Date(f) < new Date();
}

/* ─── Modificación en renderHistorialCamara ─────────────────────────────────
   Agregar dentro de renderHistorialCamara() en la sección de botones, luego
   de la línea de "← Dashboard", agregar el botón de "Mapa de Pilas".

   Buscar la línea del topbar:
     <button class="mod-back-btn" id="histCamBack">← Dashboard</button>

   Y agregar después:
     <button class="mod-back-btn" id="btnMapaPilas" style="margin-left:auto;background:rgba(54,176,201,.15);border-color:rgba(54,176,201,.35);color:#36B0C9">
       🗺️ Mapa de pilas
     </button>

   Y en los addEventListener, agregar:
     document.getElementById('btnMapaPilas')?.addEventListener('click', () => renderLayoutCamaras(camara));
   ─────────────────────────────────────────────────────────────────────────── */
