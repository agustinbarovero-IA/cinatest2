/* ============================================================
 *  pr59_generadores.js — PR 59 Registro Control Generadores
 *  Grupo CINA
 * ============================================================
 *  Dos generadores: 775 KVA y 343 KVA
 *  Tres bloques de checks por generador: antes / en marcha 10' / cierre
 * ============================================================ */

(() => {
  const API = 'api_pr59.php';

  // ── Utilidades ──────────────────────────────────────────────
  const $  = (sel, root=document) => root.querySelector(sel);
  const $$ = (sel, root=document) => [...root.querySelectorAll(sel)];
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
  }[c]));
  async function apiGet(action, params = {}) {
    const qs = new URLSearchParams({ action, ...params }).toString();
    const r = await fetch(`${API}?${qs}`, { credentials: 'same-origin' });
    return r.json();
  }
  async function apiPost(action, data = {}) {
    const r = await fetch(`${API}?action=${encodeURIComponent(action)}`, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    });
    return r.json();
  }
  function toast(msg, tipo='ok') {
    let t = document.getElementById('pr59-toast');
    if (!t) {
      t = document.createElement('div');
      t.id = 'pr59-toast';
      t.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);padding:12px 20px;border-radius:10px;color:#fff;font-weight:600;z-index:999999;box-shadow:0 8px 24px rgba(0,0,0,.3);transition:opacity .25s;pointer-events:none';
      document.body.appendChild(t);
    }
    t.textContent = msg;
    t.style.background = tipo === 'err' ? '#dc2626' : tipo === 'warn' ? '#d97706' : '#059669';
    t.style.opacity = '1';
    clearTimeout(t._to); t._to = setTimeout(() => t.style.opacity = '0', 2800);
  }

  // ── CSS ─────────────────────────────────────────────────────
  (function css() {
    if (document.getElementById('pr59-css')) return;
    const s = document.createElement('style');
    s.id = 'pr59-css';
    s.textContent = `
      .pr59-wrap { max-width: 1100px; margin: 0 auto; padding: 16px 20px; font-family: 'Segoe UI', system-ui, sans-serif; }
      .pr59-top { display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap; margin-bottom:16px; }
      .pr59-title { font-size:1.05rem; font-weight:800; color:#fff; }
      .pr59-sub   { font-size:.76rem; opacity:.55; margin-top:3px; }
      .pr59-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:14px; }
      .pr59-tab  { padding:9px 16px; border:none; background:rgba(255,255,255,.06); color:#e7ecf7; font-weight:700;
                   border-radius:8px; font-size:.82rem; cursor:pointer; font-family:inherit;
                   border:1px solid rgba(255,255,255,.08); }
      .pr59-tab.active { background:linear-gradient(135deg,#36B0C9,#00A887); color:#fff; border-color:transparent; }
      .pr59-btn { padding:10px 18px; border:none; border-radius:8px; font-weight:700; cursor:pointer; font-size:.86rem; font-family:inherit; }
      .pr59-btn-primary { background:linear-gradient(135deg,#36B0C9,#00A887); color:#fff; }
      .pr59-btn-ghost   { background:rgba(255,255,255,.08); color:#fff; border:1px solid rgba(255,255,255,.14); }
      .pr59-btn-danger  { background:#dc2626; color:#fff; }
      .pr59-btn:hover { opacity:.9; }
      .pr59-seccion { background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.08);
                      border-radius: 10px; padding: 14px 16px; margin-bottom: 12px; }
      .pr59-seccion h4 { margin:0 0 10px; font-size:.78rem; color:#36B0C9; text-transform: uppercase;
                         letter-spacing:.06em; font-weight: 800; padding-bottom:6px;
                         border-bottom: 1px solid rgba(54,176,201,.25); }
      .pr59-row { display: grid; grid-template-columns: 1fr 110px 1fr; gap: 10px;
                  align-items: center; padding: 6px 0; border-bottom: 1px dashed rgba(255,255,255,.06); }
      .pr59-row:last-child { border-bottom: none; }
      .pr59-row .lbl { font-size:.88rem; color:#cbd5e1; font-weight:600; }
      .pr59-row .bm  { display:flex; gap:6px; justify-content:center; }
      .pr59-row .bm button { flex:1; padding:7px 0; font-weight:800; font-size:.78rem;
                             background:rgba(255,255,255,.05); color:#cbd5e1;
                             border:1px solid rgba(255,255,255,.12); border-radius:6px;
                             cursor:pointer; font-family:inherit; }
      .pr59-row .bm button.sel-b { background:#059669; color:#fff; border-color:#059669; }
      .pr59-row .bm button.sel-m { background:#dc2626; color:#fff; border-color:#dc2626; }
      .pr59-row .obs { padding:6px 10px; background:rgba(255,255,255,.05); color:#fff;
                       border:1px solid rgba(255,255,255,.1); border-radius:6px; font-family:inherit; font-size:.84rem; }
      .pr59-row-text { display:grid; grid-template-columns: 1fr 1fr 1fr; gap:10px; padding:6px 0;
                       border-bottom: 1px dashed rgba(255,255,255,.06); align-items:center; }
      .pr59-row-text .lbl { font-size:.88rem; color:#cbd5e1; font-weight:600; }
      .pr59-row-text input { padding:6px 10px; background:rgba(255,255,255,.05); color:#fff;
                             border:1px solid rgba(255,255,255,.1); border-radius:6px; font-family:inherit; font-size:.84rem; }
      .pr59-footer { display:flex; justify-content:flex-end; gap:10px; margin-top:16px;
                     padding:12px 16px; background:rgba(255,255,255,.02); border-radius:10px;
                     border:1px solid rgba(255,255,255,.08); }
      .pr59-grid2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
      .pr59-field { display:flex; flex-direction:column; gap:4px; }
      .pr59-field label { font-size:.72rem; opacity:.75; font-weight:600; }
      .pr59-field input, .pr59-field textarea {
        padding:8px 10px; background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.12);
        color:#fff; border-radius:6px; font-family:inherit; font-size:.88rem; }
      .pr59-table { width:100%; border-collapse:collapse; font-size:.85rem; margin-top:10px; }
      .pr59-table th, .pr59-table td { padding:8px 10px; border-bottom:1px solid rgba(255,255,255,.08); text-align:left; }
      .pr59-table th { opacity:.7; font-weight:700; font-size:.72rem; text-transform:uppercase; background:rgba(255,255,255,.03); }
      .pr59-badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:.7rem; font-weight:700; }
      .pr59-badge.gen1 { background:rgba(54,176,201,.25); color:#7dd3fc; }
      .pr59-badge.gen2 { background:rgba(168,85,247,.25); color:#d8b4fe; }
      @media (max-width: 700px) {
        .pr59-row { grid-template-columns: 1fr; }
        .pr59-row-text { grid-template-columns: 1fr; }
        .pr59-grid2 { grid-template-columns: 1fr; }
      }
    `;
    document.head.appendChild(s);
  })();

  // ── Definición de secciones del form ────────────────────────
  const SEC_ANTES = [
    { k: 'nivel_aceite',          label: 'Verificación Nivel de Aceite' },
    { k: 'nivel_agua_radiador',   label: 'Verificación Nivel de agua en Radiador' },
    { k: 'nivel_electrolito',     label: 'Nivel Electrolito Baterías' },
    { k: 'cargador_baterias',     label: 'Funcionamiento cargador de Baterías' },
    { k: 'nivel_gasoil',          label: 'Verificación Nivel de Gasoil en Tanque' },
  ];
  const SEC_ANTES_TEXT = [
    { k: 'horas_trabajo',   label: 'Horas de trabajo',   ph: 'ej. 2847 hs' },
    { k: 'proximo_service', label: 'Próximo service',    ph: 'ej. 3000 hs / 15-jun-2026' },
  ];
  const SEC_MARCHA = [
    { k: 'presion_aceite',      label: 'Presión de Aceite' },
    { k: 'temp_refrigerante',   label: 'Temperatura del Refrigerante' },
    { k: 'perdida_fluidos',     label: 'Pérdida de Fluidos' },
    { k: 'regulacion_voltaje',  label: 'Regulación Voltaje' },
    { k: 'carga_baterias',      label: 'Carga de Baterías' },
  ];

  // ── Render listado ──────────────────────────────────────────
  async function renderLista(container, filtroGen = '') {
    container.innerHTML = '<div style="padding:24px;text-align:center;opacity:.5">Cargando…</div>';
    const { registros } = await apiGet('pr59_lista', filtroGen ? { generador: filtroGen } : {});
    const rows = registros || [];
    container.innerHTML = `
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;gap:10px;flex-wrap:wrap">
        <div style="font-size:.84rem;opacity:.8"><strong>${rows.length}</strong> registro${rows.length!==1?'s':''}${filtroGen?' · '+esc(filtroGen):''}</div>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
          <button class="pr59-btn pr59-btn-ghost" id="pr59FNew775">+ Nuevo 775 KVA</button>
          <button class="pr59-btn pr59-btn-ghost" id="pr59FNew343">+ Nuevo 343 KVA</button>
        </div>
      </div>
      ${rows.length === 0 ? '<div style="padding:32px;text-align:center;opacity:.4">Sin registros</div>' : `
      <table class="pr59-table">
        <thead><tr>
          <th>Fecha</th><th>Generador</th><th>Realizó</th><th>Cargado por</th><th>Fallas</th><th></th>
        </tr></thead>
        <tbody>${rows.map(r => `
          <tr>
            <td>${esc(r.fecha)}</td>
            <td><span class="pr59-badge ${r.generador==='775 KVA'?'gen1':'gen2'}">${esc(r.generador)}</span></td>
            <td>${esc(r.realizo_nombre || '—')}</td>
            <td>${esc(r.usuario_nombre || '—')}</td>
            <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(r.otras_fallas || r.observaciones || '—')}</td>
            <td><button class="pr59-btn pr59-btn-ghost verReg" data-id="${r.id}" style="padding:4px 10px;font-size:.72rem">Ver</button></td>
          </tr>`).join('')}</tbody>
      </table>`}`;
    $('#pr59FNew775').onclick = () => abrirFormulario('775 KVA', null);
    $('#pr59FNew343').onclick = () => abrirFormulario('343 KVA', null);
    $$('.verReg', container).forEach(b => b.onclick = () => verRegistro(+b.dataset.id));
  }

  // ── Modal contenedor ────────────────────────────────────────
  function abrirModal(title, sub, html, opts={}) {
    cerrarModal();
    const o = document.createElement('div');
    o.id = 'pr59-modal';
    o.style.cssText = `position:fixed;inset:0;background:rgba(0,0,0,.68);z-index:99998;
      display:flex;align-items:center;justify-content:center;padding:24px;`;
    o.innerHTML = `
      <div style="background:#0a1430;color:#e7ecf7;border-radius:14px;max-width:980px;width:100%;
           max-height:94vh;overflow:auto;box-shadow:0 20px 60px rgba(0,0,0,.5);
           border:1px solid rgba(255,255,255,.08);">
        <header style="padding:14px 22px;border-bottom:1px solid rgba(255,255,255,.08);
             display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;background:#0a1430;z-index:2">
          <div>
            <div style="font-weight:800;font-size:1.02rem">${esc(title)}</div>
            ${sub ? `<div style="font-size:.72rem;opacity:.65;margin-top:2px">${esc(sub)}</div>` : ''}
          </div>
          <button class="pr59-btn pr59-btn-ghost" id="pr59Close" style="padding:6px 12px">✕</button>
        </header>
        <div class="pr59-wrap" style="padding:20px 24px">${html}</div>
      </div>`;
    document.body.appendChild(o);
    $('#pr59Close').onclick = cerrarModal;
    return o;
  }
  function cerrarModal() { document.getElementById('pr59-modal')?.remove(); }

  // ── Formulario ──────────────────────────────────────────────
  function abrirFormulario(generador, registro) {
    const reg = registro || {};
    const hoy = new Date().toISOString().slice(0,10);
    const fmtBM = (seccion, prefix) => seccion.map(s => `
        <div class="pr59-row" data-k="${s.k}">
          <div class="lbl">${esc(s.label)}</div>
          <div class="bm">
            <button type="button" data-v="B">B</button>
            <button type="button" data-v="M">M</button>
          </div>
          <input type="text" class="obs" placeholder="Observaciones" data-obs="${s.k}_obs" value="${esc(reg[s.k+'_obs'] || '')}">
        </div>`).join('');

    const html = `
      <div class="pr59-grid2">
        <div class="pr59-field"><label>Fecha</label>
          <input type="date" id="pr59Fecha" value="${esc(reg.fecha || hoy)}">
        </div>
        <div class="pr59-field"><label>Generador</label>
          <input type="text" value="${esc(generador)}" disabled>
        </div>
      </div>

      <div class="pr59-seccion" style="margin-top:14px">
        <h4>1️⃣ Controles antes de la puesta en marcha</h4>
        <div class="pr59-row" style="opacity:.55;font-size:.7rem;font-weight:700;text-transform:uppercase">
          <div>Tarea</div><div style="text-align:center">Estado (B/M)</div><div>Observaciones</div>
        </div>
        ${fmtBM(SEC_ANTES)}
        ${SEC_ANTES_TEXT.map(s => `
          <div class="pr59-row-text">
            <div class="lbl">${esc(s.label)}</div>
            <input type="text" data-k="${s.k}" placeholder="${esc(s.ph||'')}" value="${esc(reg[s.k] || '')}">
            <input type="text" data-k="${s.k}_obs" placeholder="Observaciones" value="${esc(reg[s.k+'_obs'] || '')}">
          </div>`).join('')}
      </div>

      <div class="pr59-seccion">
        <h4>2️⃣ Puesta en marcha — control luego de 10 minutos</h4>
        <div class="pr59-row" style="opacity:.55;font-size:.7rem;font-weight:700;text-transform:uppercase">
          <div>Control</div><div style="text-align:center">Estado (B/M)</div><div>Observaciones</div>
        </div>
        ${fmtBM(SEC_MARCHA)}
      </div>

      <div class="pr59-seccion">
        <h4>3️⃣ Cierre</h4>
        <div class="pr59-grid2">
          <div class="pr59-field"><label>Realizó</label>
            <input type="text" id="pr59Realizo" placeholder="Nombre de quien realizó" value="${esc(reg.realizo_nombre || '')}">
          </div>
          <div class="pr59-field"><label>Firma</label>
            <input type="text" id="pr59Firma" placeholder="Firma / aclaración" value="${esc(reg.firma || '')}">
          </div>
          <div class="pr59-field" style="grid-column:1/-1"><label>Otras fallas</label>
            <textarea id="pr59Fallas" rows="2" placeholder="Describir fallas detectadas (si las hubo)">${esc(reg.otras_fallas || '')}</textarea>
          </div>
          <div class="pr59-field" style="grid-column:1/-1"><label>Observaciones generales</label>
            <textarea id="pr59Obs" rows="2" placeholder="Comentarios adicionales">${esc(reg.observaciones || '')}</textarea>
          </div>
        </div>
      </div>

      <div class="pr59-footer">
        <button class="pr59-btn pr59-btn-ghost" id="pr59Cancelar">Cancelar</button>
        <button class="pr59-btn pr59-btn-primary" id="pr59Guardar">💾 Guardar registro</button>
      </div>`;

    abrirModal(`PR-59 · Generador ${generador}`, 'Registro de control y puesta en marcha', html);

    // Bindings de botones B/M
    $$('.pr59-row[data-k] .bm button').forEach(btn => {
      btn.onclick = () => {
        const row = btn.closest('.pr59-row');
        row.querySelectorAll('.bm button').forEach(b => b.classList.remove('sel-b','sel-m'));
        btn.classList.add(btn.dataset.v === 'B' ? 'sel-b' : 'sel-m');
        row.dataset.sel = btn.dataset.v;
      };
    });
    // Pre-cargar valores si estamos editando
    if (registro) {
      $$('.pr59-row[data-k]').forEach(row => {
        const k = row.dataset.k;
        const v = reg[k];
        if (v === 'B' || v === 'M') {
          const btn = row.querySelector(`.bm button[data-v="${v}"]`);
          if (btn) { btn.classList.add(v==='B'?'sel-b':'sel-m'); row.dataset.sel = v; }
        }
      });
    }

    $('#pr59Cancelar').onclick = cerrarModal;
    $('#pr59Guardar').onclick = async () => {
      const payload = {
        generador,
        fecha: $('#pr59Fecha').value,
        realizo_nombre: $('#pr59Realizo').value.trim(),
        firma: $('#pr59Firma').value.trim(),
        otras_fallas: $('#pr59Fallas').value.trim(),
        observaciones: $('#pr59Obs').value.trim(),
      };
      // BM campos
      $$('.pr59-row[data-k]').forEach(row => {
        payload[row.dataset.k] = row.dataset.sel || null;
        const obs = row.querySelector('[data-obs]');
        if (obs) payload[obs.dataset.obs] = obs.value.trim();
      });
      // Campos de texto (horas, proximo_service)
      $$('.pr59-row-text input[data-k]').forEach(inp => {
        payload[inp.dataset.k] = inp.value.trim();
      });
      const res = await apiPost('pr59_guardar', payload);
      if (res.ok) {
        toast('✓ Registro guardado');
        cerrarModal();
        if (window.EP_PR59?.refresh) window.EP_PR59.refresh();
      } else {
        toast(res.error || 'Error al guardar', 'err');
      }
    };
  }

  // ── Ver registro existente (solo lectura) ───────────────────
  async function verRegistro(id) {
    const { registro } = await apiGet('pr59_detalle', { id });
    if (!registro) return toast('No encontrado', 'err');
    const r = registro;
    const fmtEst = v => v === 'B' ? '<span style="color:#4ade80;font-weight:700">Bien (B)</span>'
                     : v === 'M' ? '<span style="color:#f87171;font-weight:700">Mal (M)</span>' : '—';
    const itemRO = (lbl, v, obs) => `
      <div class="pr59-row">
        <div class="lbl">${esc(lbl)}</div>
        <div class="bm" style="justify-content:center">${fmtEst(v)}</div>
        <div style="font-size:.82rem;opacity:.75">${esc(obs||'—')}</div>
      </div>`;
    const html = `
      <div class="pr59-grid2">
        <div><strong>Fecha:</strong> ${esc(r.fecha)}</div>
        <div><strong>Generador:</strong> ${esc(r.generador)}</div>
      </div>
      <div class="pr59-seccion" style="margin-top:14px">
        <h4>Controles antes de la puesta en marcha</h4>
        ${SEC_ANTES.map(s => itemRO(s.label, r[s.k], r[s.k+'_obs'])).join('')}
        ${SEC_ANTES_TEXT.map(s => `<div class="pr59-row-text">
          <div class="lbl">${esc(s.label)}</div>
          <div style="font-size:.86rem">${esc(r[s.k] || '—')}</div>
          <div style="font-size:.82rem;opacity:.75">${esc(r[s.k+'_obs'] || '—')}</div>
        </div>`).join('')}
      </div>
      <div class="pr59-seccion">
        <h4>Puesta en marcha — 10 minutos</h4>
        ${SEC_MARCHA.map(s => itemRO(s.label, r[s.k], r[s.k+'_obs'])).join('')}
      </div>
      <div class="pr59-seccion">
        <h4>Cierre</h4>
        <div class="pr59-grid2">
          <div><strong>Realizó:</strong> ${esc(r.realizo_nombre || '—')}</div>
          <div><strong>Firma:</strong> ${esc(r.firma || '—')}</div>
        </div>
        <div style="margin-top:8px"><strong>Otras fallas:</strong><br>
          <div style="white-space:pre-wrap;opacity:.85;margin-top:4px">${esc(r.otras_fallas || '—')}</div>
        </div>
        <div style="margin-top:8px"><strong>Observaciones:</strong><br>
          <div style="white-space:pre-wrap;opacity:.85;margin-top:4px">${esc(r.observaciones || '—')}</div>
        </div>
        <div style="margin-top:10px;font-size:.72rem;opacity:.6">
          Cargado por: ${esc(r.usuario_nombre || '—')} · ${esc(r.created_at || '')}
        </div>
      </div>`;
    abrirModal(`PR-59 #${r.id}`, `${r.generador} · ${r.fecha}`, html);
  }

  // ── API pública ─────────────────────────────────────────────
  async function abrir() {
    // Vista principal: pestañas por generador + listado
    abrirModal('🏭 PR-59 · Control y Puesta en Marcha de Generadores',
      'Registros históricos por generador',
      `<div class="pr59-tabs">
         <button class="pr59-tab active" data-gen="">Todos</button>
         <button class="pr59-tab" data-gen="775 KVA">Generador 775 KVA</button>
         <button class="pr59-tab" data-gen="343 KVA">Generador 343 KVA</button>
       </div>
       <div id="pr59Cont"></div>`);
    const cont = $('#pr59Cont');
    const load = (gen) => renderLista(cont, gen);
    $$('.pr59-tab').forEach(t => t.onclick = () => {
      $$('.pr59-tab').forEach(x => x.classList.remove('active'));
      t.classList.add('active');
      load(t.dataset.gen);
    });
    load('');
    window.EP_PR59 = { refresh: () => load($('.pr59-tab.active')?.dataset.gen || '') };
  }

  // Exponer globalmente para que app.js pueda llamarlo desde el menú
  window.EP_PR59 = { abrir };
})();
