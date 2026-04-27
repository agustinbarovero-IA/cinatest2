/* ============================================================
 *  costeo.js — Costeo de Operaciones PR-08
 *  Grupo CINA
 * ============================================================
 *  Tile en el menú:  COMERCIAL Y PLANIFICACION → COSTEO DE OPERACIONES
 *  Disparado desde app.js con action 'costeoOperaciones'.
 *  4 pestañas: Códigos | Catálogo de Ítems | Costeos (editor) | Reportes
 * ============================================================ */
(() => {
  const API = 'api_costeo.php';

  const $  = (sel, root=document) => root.querySelector(sel);
  const $$ = (sel, root=document) => [...root.querySelectorAll(sel)];
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
  }[c]));
  const fmt = v => {
    const n = Number(v);
    if (!isFinite(n)) return '—';
    return n.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  };
  const fmtKg = v => {
    const n = Number(v);
    if (!isFinite(n)) return '—';
    return n.toLocaleString('es-AR', {minimumFractionDigits: 4, maximumFractionDigits: 4});
  };

  async function apiGet(action, params = {}) {
    const qs = new URLSearchParams({ action, ...params }).toString();
    const r = await fetch(`${API}?${qs}`, { credentials: 'same-origin' });
    return r.json();
  }
  async function apiPost(action, data = {}) {
    const r = await fetch(`${API}?action=${encodeURIComponent(action)}`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    });
    return r.json();
  }
  function toast(msg, tipo='ok') {
    let t = document.getElementById('cost-toast');
    if (!t) {
      t = document.createElement('div');
      t.id = 'cost-toast';
      t.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);padding:12px 20px;border-radius:10px;color:#fff;font-weight:600;z-index:999999;box-shadow:0 8px 24px rgba(0,0,0,.3);transition:opacity .25s;pointer-events:none';
      document.body.appendChild(t);
    }
    t.textContent = msg;
    t.style.background = tipo === 'err' ? '#dc2626' : tipo === 'warn' ? '#d97706' : '#059669';
    t.style.opacity = '1';
    clearTimeout(t._to); t._to = setTimeout(() => t.style.opacity = '0', 2800);
  }

  // CSS
  (function css() {
    if (document.getElementById('cost-css')) return;
    const s = document.createElement('style');
    s.id = 'cost-css';
    s.textContent = `
      .cost-overlay { position:fixed; inset:0; background:rgba(0,0,0,.68); z-index:99998;
        display:flex; align-items:center; justify-content:center; padding:24px; }
      .cost-modal { background:#0a1430; color:#e7ecf7; border-radius:16px;
        max-width:1200px; width:100%; max-height:94vh; overflow:auto;
        box-shadow:0 20px 60px rgba(0,0,0,.5); border:1px solid rgba(255,255,255,.08); }
      .cost-modal header { padding:16px 24px; border-bottom:1px solid rgba(255,255,255,.08);
        display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; background:#0a1430; z-index:2; }
      .cost-modal header h3 { margin:0; font-size:1.05rem; font-weight:800; }
      .cost-modal .body { padding:16px 24px; }
      .cost-modal .footer { padding:12px 24px; border-top:1px solid rgba(255,255,255,.08);
        display:flex; gap:10px; justify-content:flex-end; background:rgba(255,255,255,.02); position:sticky; bottom:0; }
      .cost-btn { padding:9px 16px; border:none; border-radius:8px; font-weight:700; cursor:pointer; font-size:.86rem; font-family:inherit; }
      .cost-btn-primary { background:linear-gradient(135deg,#36B0C9,#00A887); color:#fff; }
      .cost-btn-ghost { background:rgba(255,255,255,.08); color:#fff; border:1px solid rgba(255,255,255,.14); }
      .cost-btn-danger { background:#dc2626; color:#fff; }
      .cost-btn:hover { opacity:.9; } .cost-btn:disabled { opacity:.5; cursor:not-allowed; }
      .cost-tabs { display:flex; gap:6px; margin-bottom:12px; border-bottom:1px solid rgba(255,255,255,.08); flex-wrap:wrap }
      .cost-tab { padding:9px 14px; cursor:pointer; border:none; background:none; color:#e7ecf7; font-weight:700;
        opacity:.55; font-size:.86rem; border-bottom:2px solid transparent; font-family:inherit; }
      .cost-tab.active { opacity:1; border-bottom-color:#36B0C9; }
      .cost-table { width:100%; border-collapse:collapse; font-size:.85rem; }
      .cost-table th, .cost-table td { padding:7px 9px; border-bottom:1px solid rgba(255,255,255,.08); text-align:left; vertical-align:top; }
      .cost-table th { opacity:.7; font-weight:700; font-size:.72rem; text-transform:uppercase; background:rgba(255,255,255,.02); }
      .cost-table td.n { text-align:right; font-family:ui-monospace, Consolas, monospace; }
      .cost-badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:.68rem; font-weight:700; }
      .cost-b-nac { background:rgba(54,176,201,.25); color:#7dd3fc; }
      .cost-b-fis { background:rgba(217,119,6,.25); color:#fcd34d; }
      .cost-b-ok  { background:rgba(5,150,105,.25); color:#6ee7b7; }
      .cost-b-off { background:rgba(255,255,255,.1); color:#cbd5e1; }
      .cost-grid2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
      .cost-grid3 { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; }
      .cost-field { display:flex; flex-direction:column; gap:4px; }
      .cost-field label { font-size:.72rem; opacity:.75; font-weight:600; }
      .cost-field input, .cost-field textarea, .cost-field select {
        padding:7px 9px; background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.12);
        color:#fff; border-radius:6px; font-family:inherit; font-size:.86rem; }
      .cost-seccion { margin-top:16px; padding:12px; background:rgba(255,255,255,.02); border-radius:8px;
        border:1px solid rgba(255,255,255,.06); }
      .cost-seccion h4 { margin:0 0 8px; font-size:.82rem; text-transform:uppercase; opacity:.7;
        letter-spacing:.5px; border-bottom:1px solid rgba(255,255,255,.08); padding-bottom:4px; }
      .cost-item-row { display:grid; grid-template-columns: 1fr 100px 120px 80px 120px 30px; gap:6px; align-items:center; padding:4px 0; }
      .cost-item-row input { padding:5px 7px; background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.1);
        color:#fff; border-radius:4px; font-size:.82rem; font-family:inherit; }
      .cost-totales { background:linear-gradient(135deg, rgba(54,176,201,.15), rgba(0,168,135,.15));
        border:1px solid rgba(54,176,201,.3); border-radius:10px; padding:14px 18px; margin-top:14px; }
      .cost-totales .row { display:flex; justify-content:space-between; padding:4px 0; font-size:.92rem; }
      .cost-totales .row.big { font-size:1.1rem; font-weight:800; border-top:1px solid rgba(255,255,255,.15); margin-top:6px; padding-top:10px; }
      .cost-note { font-size:.76rem; opacity:.7; padding:8px 12px; background:rgba(54,176,201,.08);
        border-left:3px solid #36B0C9; border-radius:6px; margin-bottom:10px; }
    `;
    document.head.appendChild(s);
  })();

  function abrirModal(titulo, subtitulo, html, opts={}) {
    cerrarModal();
    const o = document.createElement('div');
    o.className = 'cost-overlay';
    o.id = 'cost-overlay-modal';
    o.innerHTML = `
      <div class="cost-modal" role="dialog">
        <header>
          <div>
            <h3>${esc(titulo)}</h3>
            ${subtitulo ? `<div style="font-size:.72rem;opacity:.6;margin-top:2px">${esc(subtitulo)}</div>` : ''}
          </div>
          ${opts.cerrable !== false ? '<button class="cost-btn cost-btn-ghost" id="costBtnCerrar" style="padding:5px 10px">✕</button>' : ''}
        </header>
        <div class="body">${html}</div>
        ${opts.footer ? `<div class="footer">${opts.footer}</div>` : ''}
      </div>`;
    document.body.appendChild(o);
    const b = document.getElementById('costBtnCerrar');
    if (b) b.onclick = cerrarModal;
    return o;
  }
  function cerrarModal() {
    const m = document.getElementById('cost-overlay-modal');
    if (m) m.remove();
  }

  // ═════════════════════════════════════════════════════════════
  async function abrirCosteo() {
    abrirModal('💰 Costeo de Operaciones',
      'Códigos CN (nacional) / DF (fiscal) · respuestas registradas por PR-08',
      `<div class="cost-tabs">
         <button class="cost-tab active" data-t="codigos">📁 Códigos</button>
         <button class="cost-tab" data-t="items">📋 Catálogo ítems</button>
         <button class="cost-tab" data-t="indirectos">🏛️ Costos indirectos</button>
         <button class="cost-tab" data-t="costeos">💵 Costeos</button>
         <button class="cost-tab" data-t="pr08">🔗 PR-08 sin código</button>
         <button class="cost-tab" data-t="reportes">📥 Reportes</button>
       </div>
       <div id="cost-tab" style="padding:14px 0;opacity:.6">Cargando…</div>`);
    $$('.cost-tab').forEach(t => t.onclick = () => {
      $$('.cost-tab').forEach(x => x.classList.remove('active'));
      t.classList.add('active');
      render(t.dataset.t);
    });
    render('codigos');
  }

  async function render(tab) {
    const cont = $('#cost-tab');
    cont.innerHTML = '<div style="padding:20px;opacity:.6">Cargando…</div>';
    if (tab === 'codigos')    return renderCodigos(cont);
    if (tab === 'items')      return renderItems(cont);
    if (tab === 'indirectos') return renderIndirectos(cont);
    if (tab === 'costeos')    return renderCosteos(cont);
    if (tab === 'pr08')       return renderPr08(cont);
    if (tab === 'reportes')   return renderReportes(cont);
  }

  // ────── TAB: Códigos ──────
  async function renderCodigos(cont) {
    const { codigos } = await apiGet('cost_codigo_list');
    const agrup = { nacional: [], fiscal: [] };
    codigos.forEach(c => agrup[c.tipo_cliente].push(c));

    const blk = (titulo, arr, tipo) => `
      <h4 style="margin:16px 0 8px;font-size:.82rem;text-transform:uppercase;opacity:.7;letter-spacing:.5px">${titulo}</h4>
      <table class="cost-table">
        <thead><tr>
          <th>Código</th><th>Denominación</th><th>PR-08</th><th>Costeo vigente</th>
          <th>Total</th><th>$/Pallet</th><th>$/Kg</th><th>Activo</th><th></th>
        </tr></thead>
        <tbody>${arr.map(c=>`
          <tr>
            <td><strong>${esc(c.codigo)}</strong></td>
            <td>${esc(c.denominacion)}</td>
            <td class="n">${c.pr08_asignados}</td>
            <td>${c.costeo_vigente_num ? esc(c.costeo_vigente_num) + '<br><small style="opacity:.6">'+esc(c.costeo_vigente_fecha)+'</small>' : '—'}</td>
            <td class="n">${c.costeo_vigente_total ? fmt(c.costeo_vigente_total)+' '+esc(c.costeo_vigente_moneda||'') : '—'}</td>
            <td class="n">${c.costeo_vigente_pallet ? fmt(c.costeo_vigente_pallet) : '—'}</td>
            <td class="n">${c.costeo_vigente_kg ? fmtKg(c.costeo_vigente_kg) : '—'}</td>
            <td><span class="cost-badge ${c.activo?'cost-b-ok':'cost-b-off'}">${c.activo?'SÍ':'NO'}</span></td>
            <td>
              <button class="cost-btn cost-btn-ghost edCod" data-cod="${esc(c.codigo)}" style="padding:3px 8px;font-size:.72rem">Editar</button>
              <button class="cost-btn cost-btn-primary coCod" data-cod="${esc(c.codigo)}" style="padding:3px 8px;font-size:.72rem">Costear</button>
              <button class="cost-btn cost-btn-ghost hiCod" data-cod="${esc(c.codigo)}" style="padding:3px 8px;font-size:.72rem">Historial</button>
            </td>
          </tr>`).join('')}</tbody>
      </table>`;

    cont.innerHTML = `
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <div class="cost-note" style="margin:0">Códigos secuenciales: <strong>CN</strong> para nacional, <strong>DF</strong> para fiscal. Uno solo por PR-08.</div>
        <div style="display:flex;gap:6px">
          <button class="cost-btn cost-btn-primary" id="newCN">+ Nuevo CN (nacional)</button>
          <button class="cost-btn cost-btn-primary" id="newDF" style="background:linear-gradient(135deg,#d97706,#ea580c)">+ Nuevo DF (fiscal)</button>
        </div>
      </div>
      ${blk('Depósito Nacional (CN)', agrup.nacional, 'nacional')}
      ${blk('Depósito Fiscal (DF)', agrup.fiscal, 'fiscal')}`;

    $('#newCN').onclick = () => editarCodigo({tipo_cliente:'nacional', activo:1});
    $('#newDF').onclick = () => editarCodigo({tipo_cliente:'fiscal', activo:1});
    $$('.edCod', cont).forEach(b => b.onclick = () => {
      const c = codigos.find(x => x.codigo === b.dataset.cod);
      editarCodigo(c);
    });
    $$('.coCod', cont).forEach(b => b.onclick = () => abrirEditorCosteo(b.dataset.cod));
    $$('.hiCod', cont).forEach(b => b.onclick = () => verHistorialCodigo(b.dataset.cod));
  }

  function editarCodigo(c) {
    const esNuevo = !c.codigo;
    const html = `
      <div class="cost-grid2">
        <div class="cost-field"><label>Código</label>
          <input type="text" id="cCod" value="${esc(c.codigo || '(se asigna automáticamente)')}" disabled>
        </div>
        <div class="cost-field"><label>Tipo cliente</label>
          <input type="text" value="${esc(c.tipo_cliente || '')}" disabled>
        </div>
        <div class="cost-field" style="grid-column:1/-1"><label>Denominación *</label>
          <input type="text" id="cDen" value="${esc(c.denominacion || '')}">
        </div>
        <div class="cost-field" style="grid-column:1/-1"><label>Descripción</label>
          <textarea id="cDesc" rows="3">${esc(c.descripcion || '')}</textarea>
        </div>
        <div class="cost-field"><label>Pallets de referencia</label>
          <input type="number" id="cPR" value="${esc(c.cantidad_pallets_ref || '')}">
        </div>
        <div class="cost-field"><label>Peso/pallet de referencia (kg)</label>
          <input type="number" step="0.01" id="cPW" value="${esc(c.peso_pallet_ref || '')}">
        </div>
        <div class="cost-field"><label>Activo</label>
          <select id="cAct"><option value="1" ${c.activo?'selected':''}>Sí</option><option value="0" ${!c.activo?'selected':''}>No</option></select>
        </div>
      </div>`;
    const footer = `<button class="cost-btn cost-btn-primary" id="guardarCod">💾 Guardar</button>`;
    abrirModal(esNuevo ? 'Nuevo código' : 'Editar '+c.codigo, '', html, { footer });
    $('#guardarCod').onclick = async () => {
      const res = await apiPost('cost_codigo_save', {
        codigo: c.codigo || '',
        tipo_cliente: c.tipo_cliente,
        denominacion: $('#cDen').value,
        descripcion: $('#cDesc').value,
        cantidad_pallets_ref: +$('#cPR').value || null,
        peso_pallet_ref: +$('#cPW').value || null,
        activo: +$('#cAct').value,
      });
      if (res.ok) { toast('Código guardado: '+res.codigo); cerrarModal(); abrirCosteo(); }
      else toast(res.error || 'Error', 'err');
    };
  }

  // ────── TAB: Catálogo de ítems ──────
  async function renderItems(cont) {
    const { items } = await apiGet('cost_item_list');
    const tipos = ['IN','OUT','ALMACENAMIENTO','TUNEL'];
    const grp = Object.fromEntries(tipos.map(t => [t, []]));
    items.forEach(i => grp[i.tipo_operacion].push(i));
    cont.innerHTML = `
      <div class="cost-note">Cada ítem tiene unidad (pallet, kg, min, hs, día…), costo unitario de referencia y moneda.
        El costo unitario es editable y se usa como default al cargar un costeo.</div>
      <div style="margin-bottom:10px;display:flex;justify-content:flex-end">
        <button class="cost-btn cost-btn-primary" id="newItem">+ Nuevo ítem</button>
      </div>
      ${tipos.map(t => `
        <div class="cost-seccion"><h4>${t}</h4>
          <table class="cost-table">
            <thead><tr><th style="width:80px">Orden</th><th>Nombre</th><th>Unidad</th><th style="width:140px">Costo unit.</th><th style="width:80px">Mon.</th><th style="width:80px">Activo</th><th></th></tr></thead>
            <tbody>${grp[t].map(i=>`
              <tr>
                <td class="n">${i.orden}</td>
                <td>${esc(i.nombre)}${i.descripcion?'<br><small style="opacity:.55">'+esc(i.descripcion.substring(0,120))+'</small>':''}</td>
                <td>${esc(i.unidad)}</td>
                <td class="n">${fmt(i.costo_unitario)}</td>
                <td>${esc(i.moneda)}</td>
                <td><span class="cost-badge ${i.activo?'cost-b-ok':'cost-b-off'}">${i.activo?'SÍ':'NO'}</span></td>
                <td><button class="cost-btn cost-btn-ghost edIt" data-id="${i.id}" style="padding:3px 8px;font-size:.72rem">Editar</button></td>
              </tr>`).join('')}
              ${grp[t].length===0?'<tr><td colspan="7" style="text-align:center;opacity:.5;padding:10px">— vacío —</td></tr>':''}
            </tbody>
          </table>
        </div>`).join('')}`;
    $('#newItem').onclick = () => editarItem({});
    $$('.edIt', cont).forEach(b => b.onclick = () => {
      const it = items.find(x => x.id == b.dataset.id);
      editarItem(it);
    });
  }

  function editarItem(it) {
    const esNuevo = !it.id;
    const html = `
      <div class="cost-grid2">
        <div class="cost-field"><label>Tipo de operación *</label>
          <select id="iTipo">
            ${['IN','OUT','ALMACENAMIENTO','TUNEL'].map(t=>`<option value="${t}" ${it.tipo_operacion===t?'selected':''}>${t}</option>`).join('')}
          </select>
        </div>
        <div class="cost-field"><label>Orden</label>
          <input type="number" id="iOrd" value="${esc(it.orden || 0)}">
        </div>
        <div class="cost-field" style="grid-column:1/-1"><label>Nombre *</label>
          <input type="text" id="iNom" value="${esc(it.nombre || '')}">
        </div>
        <div class="cost-field" style="grid-column:1/-1"><label>Descripción</label>
          <textarea id="iDesc" rows="2">${esc(it.descripcion || '')}</textarea>
        </div>
        <div class="cost-field"><label>Unidad (ej. min, hs, día, kg, un)</label>
          <input type="text" id="iUni" value="${esc(it.unidad || 'min')}">
        </div>
        <div class="cost-field"><label>Moneda</label>
          <select id="iMon">
            <option value="ARS" ${it.moneda==='ARS'?'selected':''}>ARS</option>
            <option value="USD" ${it.moneda==='USD'?'selected':''}>USD</option>
          </select>
        </div>
        <div class="cost-field"><label>Costo unitario</label>
          <input type="number" step="0.0001" id="iCos" value="${esc(it.costo_unitario || 0)}">
        </div>
        <div class="cost-field"><label>Activo</label>
          <select id="iAct"><option value="1" ${it.activo!==0?'selected':''}>Sí</option><option value="0" ${it.activo===0?'selected':''}>No</option></select>
        </div>
      </div>`;
    const footer = `
      ${!esNuevo?'<button class="cost-btn cost-btn-danger" id="delIt">Eliminar</button>':''}
      <button class="cost-btn cost-btn-primary" id="saveIt">💾 Guardar</button>`;
    abrirModal(esNuevo ? 'Nuevo ítem' : 'Editar ítem', '', html, { footer });
    $('#saveIt').onclick = async () => {
      const res = await apiPost('cost_item_save', {
        id: it.id || 0,
        tipo_operacion: $('#iTipo').value,
        nombre: $('#iNom').value,
        descripcion: $('#iDesc').value,
        unidad: $('#iUni').value,
        costo_unitario: +$('#iCos').value,
        moneda: $('#iMon').value,
        orden: +$('#iOrd').value,
        activo: +$('#iAct').value,
      });
      if (res.ok) { toast('Ítem guardado'); cerrarModal(); abrirCosteo(); }
      else toast(res.error || 'Error', 'err');
    };
    const del = $('#delIt'); if (del) del.onclick = async () => {
      if (!confirm('¿Desactivar este ítem?')) return;
      const res = await apiPost('cost_item_delete', { id: it.id });
      if (res.ok) { toast('Ítem desactivado'); cerrarModal(); abrirCosteo(); }
      else toast(res.error || 'Error', 'err');
    };
  }

  // ────── TAB: Costeos ──────
  async function renderCosteos(cont) {
    const { costeos } = await apiGet('cost_costeo_list', { vigentes: 1 });
    cont.innerHTML = `
      <div class="cost-note">Se muestran los costeos vigentes (último por código). Para historial, entrá al código desde la pestaña Códigos.</div>
      <table class="cost-table">
        <thead><tr><th>Fecha</th><th>Nº Costeo</th><th>Código</th><th>Denominación</th><th>Pallets</th><th>Peso tot.</th><th>Total</th><th>$/Pallet</th><th>$/Kg</th><th>Mon.</th><th>Por</th><th></th></tr></thead>
        <tbody>${costeos.map(c=>`
          <tr>
            <td>${esc(c.fecha_costeo)}</td>
            <td>${esc(c.numero_costeo)}</td>
            <td><strong>${esc(c.codigo_operacion)}</strong> <span class="cost-badge ${c.tipo_cliente==='fiscal'?'cost-b-fis':'cost-b-nac'}">${c.tipo_cliente}</span></td>
            <td>${esc(c.denominacion||'')}</td>
            <td class="n">${c.cantidad_pallets}</td>
            <td class="n">${fmt(c.peso_total)}</td>
            <td class="n"><strong>${fmt(c.total_general)}</strong></td>
            <td class="n">${fmt(c.precio_por_pallet)}</td>
            <td class="n">${fmtKg(c.precio_por_kg)}</td>
            <td>${esc(c.moneda)}</td>
            <td>${esc(c.usuario_nombre||'—')}</td>
            <td><button class="cost-btn cost-btn-ghost vrC" data-id="${c.id}" style="padding:3px 8px;font-size:.72rem">Ver</button></td>
          </tr>`).join('')}</tbody>
      </table>
      ${costeos.length===0?'<div style="padding:20px;opacity:.5;text-align:center">Sin costeos registrados</div>':''}`;
    $$('.vrC', cont).forEach(b => b.onclick = () => verCosteo(b.dataset.id));
  }

  async function verCosteo(id) {
    const { costeo, items } = await apiGet('cost_costeo_get', { id });
    if (!costeo) return toast('No encontrado','err');
    const grupos = { IN:[], OUT:[], ALMACENAMIENTO:[], TUNEL:[] };
    items.forEach(i => grupos[i.tipo_operacion]?.push(i));
    const blk = (t) => grupos[t].length ? `
      <div class="cost-seccion"><h4>${t} — subtotal ${fmt(grupos[t].reduce((a,b)=>a+(+b.subtotal),0))}</h4>
        <table class="cost-table">
          <thead><tr><th>Ítem</th><th>Unidad</th><th>Cant.</th><th>Costo unit.</th><th>Mon.</th><th>Subtotal</th></tr></thead>
          <tbody>${grupos[t].map(i=>{
            const ind = +i.es_indirecto === 1;
            const badge = ind ? ' <span class="cost-badge" style="background:rgba(217,119,6,.25);color:#fcd34d;font-size:.6rem">IND</span>' : '';
            return `<tr ${ind?'style="background:rgba(217,119,6,.04)"':''}>
              <td>${esc(i.nombre_snapshot)}${badge}</td><td>${esc(i.unidad_snapshot)}</td>
              <td class="n">${fmt(i.cantidad)}</td><td class="n">${fmt(i.costo_unitario_snapshot)}</td>
              <td>${esc(i.moneda_snapshot)}</td><td class="n">${fmt(i.subtotal)}</td>
            </tr>`;
          }).join('')}</tbody>
        </table></div>` : '';
    abrirModal(`${costeo.numero_costeo} · ${costeo.codigo_operacion}`,
      `${costeo.denominacion} · ${costeo.tipo_cliente?.toUpperCase()} · ${costeo.fecha_costeo} · por ${costeo.usuario_nombre||'—'}`,
      `${blk('IN')}${blk('OUT')}${blk('ALMACENAMIENTO')}${blk('TUNEL')}
       <div class="cost-totales">
         <div class="row"><span>Cantidad de pallets</span><strong>${costeo.cantidad_pallets}</strong></div>
         <div class="row"><span>Peso por pallet</span><strong>${fmt(costeo.peso_pallet)} kg</strong></div>
         <div class="row"><span>Peso total</span><strong>${fmt(costeo.peso_total)} kg</strong></div>
         <div class="row big"><span>TOTAL GENERAL</span><strong>${fmt(costeo.total_general)} ${costeo.moneda}</strong></div>
         <div class="row"><span>Precio por pallet</span><strong>${fmt(costeo.precio_por_pallet)} ${costeo.moneda}</strong></div>
         <div class="row"><span>Precio por kg</span><strong>${fmtKg(costeo.precio_por_kg)} ${costeo.moneda}</strong></div>
         ${costeo.observaciones?`<div class="row"><span>Obs.</span><span style="text-align:right">${esc(costeo.observaciones)}</span></div>`:''}
       </div>`);
  }

  // ────── TAB: Costos indirectos + parámetros ──────
  async function renderIndirectos(cont) {
    const { indirectos, parametros, calculo } = await apiGet('cost_indirecto_list');
    const nac = calculo.nacional, fis = calculo.fiscal, p = calculo.parametros;

    cont.innerHTML = `
      <div class="cost-note">
        Costos fijos/indirectos imputados a cada operación según porcentajes. El total acumulado da el <strong>unitario</strong>
        que se carga automáticamente al costeo (botón "💹 Cargar costos indirectos" dentro del editor).<br>
        <strong>Fórmulas:</strong> $/mov = mensual × %nac × %inout / (ing_mes+egr_mes) · $/pos-día = mensual × %nac × %alm / (posiciones × días) · $/día túnel = mensual × %nac × %tunel / días
      </div>

      <div class="cost-seccion"><h4>📏 Parámetros</h4>
        <table class="cost-table">
          <thead><tr><th>Clave</th><th>Descripción</th><th>Valor</th><th>Unidad</th></tr></thead>
          <tbody>
          ${parametros.map(pr => `
            <tr data-clave="${esc(pr.clave)}">
              <td><strong>${esc(pr.clave)}</strong></td>
              <td>${esc(pr.descripcion||'')}</td>
              <td><input type="number" step="0.0001" class="p-val" value="${+pr.valor}" style="padding:4px 8px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.12);color:#fff;border-radius:4px;width:140px"></td>
              <td>${esc(pr.unidad||'')}</td>
            </tr>`).join('')}
          </tbody>
        </table>
        <div style="margin-top:8px;text-align:right"><button class="cost-btn cost-btn-primary" id="guardarParams">💾 Guardar parámetros</button></div>
      </div>

      <div class="cost-seccion"><h4>🏛️ Costos indirectos mensuales</h4>
        <table class="cost-table">
          <thead><tr>
            <th style="width:60px">Orden</th><th>Nombre</th>
            <th>Mensual</th><th>Mon.</th>
            <th>%Nac</th><th>%Fis</th>
            <th>%IN-OUT</th><th>%ALM</th><th>%TUN</th>
            <th>Activo</th><th></th>
          </tr></thead>
          <tbody>${indirectos.map(i=>`
            <tr>
              <td class="n">${i.orden}</td>
              <td>${esc(i.nombre)}${i.descripcion?'<br><small style="opacity:.55">'+esc(i.descripcion)+'</small>':''}</td>
              <td class="n">${fmt(i.costo_mensual)}</td>
              <td>${esc(i.moneda)}</td>
              <td class="n">${((+i.rep_nacional)*100).toFixed(0)}%</td>
              <td class="n">${((+i.rep_fiscal)*100).toFixed(0)}%</td>
              <td class="n">${((+i.rep_inout)*100).toFixed(0)}%</td>
              <td class="n">${((+i.rep_almacenamiento)*100).toFixed(0)}%</td>
              <td class="n">${((+i.rep_tunel)*100).toFixed(0)}%</td>
              <td><span class="cost-badge ${i.activo?'cost-b-ok':'cost-b-off'}">${i.activo?'SÍ':'NO'}</span></td>
              <td><button class="cost-btn cost-btn-ghost edInd" data-id="${i.id}" style="padding:3px 8px;font-size:.72rem">Editar</button></td>
            </tr>`).join('')}
          </tbody>
        </table>
        <div style="margin-top:8px;text-align:right"><button class="cost-btn cost-btn-primary" id="newInd">+ Nuevo costo indirecto</button></div>
      </div>

      <div class="cost-seccion"><h4>💹 Unitarios calculados (se aplican al costeo según tipo CN/DF)</h4>
        <div class="cost-note" style="margin:0 0 10px;opacity:.75">
          Calculados con: posiciones=${p.posiciones.toLocaleString('es-AR')} · días/mes=${p.dias_mes} · mov.nac=${p.mov_nac.toLocaleString('es-AR')} · mov.fis=${p.mov_fis.toLocaleString('es-AR')}
        </div>
        <table class="cost-table">
          <thead><tr><th>Tipo cliente</th><th>$/mov IN-OUT</th><th>$/pos-día ALM</th><th>$/día TÚNEL</th></tr></thead>
          <tbody>
            <tr><td><span class="cost-badge cost-b-nac">NACIONAL (CN)</span></td><td class="n">${fmt(nac.inout)}</td><td class="n">${fmt(nac.alm)}</td><td class="n">${fmt(nac.tunel)}</td></tr>
            <tr><td><span class="cost-badge cost-b-fis">FISCAL (DF)</span></td><td class="n">${fmt(fis.inout)}</td><td class="n">${fmt(fis.alm)}</td><td class="n">${fmt(fis.tunel)}</td></tr>
          </tbody>
        </table>
      </div>`;

    // Handlers parámetros
    $('#guardarParams').onclick = async () => {
      const rows = $$('tr[data-clave]', cont);
      for (const r of rows) {
        const clave = r.dataset.clave;
        const valor = +$('.p-val', r).value || 0;
        await apiPost('cost_parametro_save', { clave, valor });
      }
      toast('Parámetros guardados'); render('indirectos');
    };

    // Handlers costos indirectos
    $('#newInd').onclick = () => editarIndirecto({ activo:1, rep_nacional:0.7, rep_fiscal:0.3, rep_inout:0.6, rep_almacenamiento:0.3, rep_tunel:0.1 });
    $$('.edInd', cont).forEach(b => b.onclick = () => {
      const it = indirectos.find(x => x.id == b.dataset.id);
      editarIndirecto(it);
    });
  }

  function editarIndirecto(it) {
    const esNuevo = !it.id;
    const html = `
      <div class="cost-grid2">
        <div class="cost-field" style="grid-column:1/-1"><label>Nombre *</label>
          <input type="text" id="iiNom" value="${esc(it.nombre||'')}">
        </div>
        <div class="cost-field" style="grid-column:1/-1"><label>Descripción</label>
          <textarea id="iiDesc" rows="2">${esc(it.descripcion||'')}</textarea>
        </div>
        <div class="cost-field"><label>Costo mensual *</label>
          <input type="number" step="0.01" id="iiAn" value="${+it.costo_mensual||0}">
        </div>
        <div class="cost-field"><label>Moneda</label>
          <select id="iiMon"><option value="ARS" ${it.moneda==='ARS'?'selected':''}>ARS</option><option value="USD" ${it.moneda==='USD'?'selected':''}>USD</option></select>
        </div>
        <div class="cost-field"><label>% Nacional (0–1)</label><input type="number" step="0.01" id="iiN" value="${+it.rep_nacional||0}"></div>
        <div class="cost-field"><label>% Fiscal (0–1)</label><input type="number" step="0.01" id="iiF" value="${+it.rep_fiscal||0}"></div>
        <div class="cost-field"><label>% IN-OUT (0–1)</label><input type="number" step="0.01" id="iiIO" value="${+it.rep_inout||0}"></div>
        <div class="cost-field"><label>% Almacenamiento (0–1)</label><input type="number" step="0.01" id="iiA" value="${+it.rep_almacenamiento||0}"></div>
        <div class="cost-field"><label>% Túnel (0–1)</label><input type="number" step="0.01" id="iiT" value="${+it.rep_tunel||0}"></div>
        <div class="cost-field"><label>Orden</label><input type="number" id="iiOr" value="${+it.orden||0}"></div>
        <div class="cost-field"><label>Activo</label>
          <select id="iiAc"><option value="1" ${it.activo!==0?'selected':''}>Sí</option><option value="0" ${it.activo===0?'selected':''}>No</option></select>
        </div>
      </div>
      <div class="cost-note" style="margin-top:10px">
        La suma de %Nacional+%Fiscal debería ser 1.0 (100%). Igual para %IN-OUT+%ALM+%TUN.
      </div>`;
    const footer = `
      ${!esNuevo?'<button class="cost-btn cost-btn-danger" id="delInd">Eliminar</button>':''}
      <button class="cost-btn cost-btn-primary" id="saveInd">💾 Guardar</button>`;
    abrirModal(esNuevo ? 'Nuevo costo indirecto' : 'Editar: '+it.nombre, '', html, { footer });
    $('#saveInd').onclick = async () => {
      const res = await apiPost('cost_indirecto_save', {
        id: it.id || 0,
        nombre: $('#iiNom').value, descripcion: $('#iiDesc').value,
        costo_mensual: +$('#iiAn').value, moneda: $('#iiMon').value,
        rep_nacional: +$('#iiN').value, rep_fiscal: +$('#iiF').value,
        rep_inout: +$('#iiIO').value, rep_almacenamiento: +$('#iiA').value, rep_tunel: +$('#iiT').value,
        orden: +$('#iiOr').value, activo: +$('#iiAc').value,
      });
      if (res.ok) { toast('Costo indirecto guardado'); cerrarModal(); render('indirectos'); }
      else toast(res.error || 'Error', 'err');
    };
    const d = $('#delInd'); if (d) d.onclick = async () => {
      if (!confirm('¿Desactivar este costo indirecto?')) return;
      const res = await apiPost('cost_indirecto_delete', { id: it.id });
      if (res.ok) { toast('Desactivado'); cerrarModal(); render('indirectos'); }
      else toast(res.error || 'Error', 'err');
    };
  }

  async function abrirEditorCosteo(codigo) {
    // Trae último vigente (para usar como base) + catálogo
    const [{ costeo, items: prev }, { items: catalogo }, { codigos }] = await Promise.all([
      apiGet('cost_costeo_get', { codigo }),
      apiGet('cost_item_list'),
      apiGet('cost_codigo_list'),
    ]);
    const cod = codigos.find(c => c.codigo === codigo);
    let pallets = costeo?.cantidad_pallets || cod?.cantidad_pallets_ref || 22;
    let pesoP   = costeo?.peso_pallet || cod?.peso_pallet_ref || 800;

    let dias = costeo?.dias_almacenamiento || 30;

    // Armar líneas iniciales: si hay costeo previo, reutiliza TODO (tiempos, costos, indirectos); si no, arranca con todos los items del catálogo en 0
    let lineas = [];
    if (prev && prev.length) {
      lineas = prev.map(p => ({
        tipo_operacion: p.tipo_operacion, item_catalogo_id: p.item_catalogo_id,
        nombre: p.nombre_snapshot, unidad: p.unidad_snapshot,
        cantidad: +p.cantidad, costo_unitario: +p.costo_unitario_snapshot,
        moneda: p.moneda_snapshot, es_indirecto: !!(+p.es_indirecto),
      }));
    } else {
      lineas = catalogo.map(i => ({
        tipo_operacion: i.tipo_operacion, item_catalogo_id: i.id,
        nombre: i.nombre, unidad: i.unidad, cantidad: 0,
        costo_unitario: +i.costo_unitario, moneda: i.moneda, es_indirecto: false,
      }));
    }

    // Precargar unitarios de costos indirectos (según tipo del código)
    const unit = await apiGet('cost_indirectos_unitarios');
    const tipoCli = cod?.tipo_cliente || 'nacional';
    const uIO  = (tipoCli === 'fiscal' ? unit.fiscal.inout : unit.nacional.inout) || 0;
    const uALM = (tipoCli === 'fiscal' ? unit.fiscal.alm   : unit.nacional.alm)   || 0;
    const uTUN = (tipoCli === 'fiscal' ? unit.fiscal.tunel : unit.nacional.tunel) || 0;

    const html = `
      <div class="cost-note">
        <strong>Código:</strong> ${esc(codigo)} — ${esc(cod?.denominacion||'')}<br>
        Ingresá cantidades totales para la operación completa. Se divide al final por pallets y kg totales.
        <br>Los <em>costos indirectos</em> salen de la tabla de la pestaña 🏛️ Costos Indirectos. Unitarios para ${tipoCli.toUpperCase()}:
        $/mov IN-OUT = <strong>${fmt(uIO)}</strong> · $/pos-día ALM = <strong>${fmt(uALM)}</strong> · $/día TÚNEL = <strong>${fmt(uTUN)}</strong>
      </div>
      <div class="cost-grid3" style="grid-template-columns:repeat(4,1fr)">
        <div class="cost-field"><label>Cantidad de pallets *</label>
          <input type="number" id="coPal" value="${pallets}" min="1">
        </div>
        <div class="cost-field"><label>Peso por pallet (kg) *</label>
          <input type="number" step="0.01" id="coPW" value="${pesoP}" min="0.01">
        </div>
        <div class="cost-field"><label>Días almacenamiento</label>
          <input type="number" id="coDias" value="${dias}" min="1">
        </div>
        <div class="cost-field"><label>Peso total (kg)</label>
          <input type="text" id="coPT" value="${fmt(pallets*pesoP)}" disabled>
        </div>
      </div>
      <div style="display:flex;gap:8px;margin:10px 0">
        <button class="cost-btn cost-btn-ghost" id="coCargaInd">💹 Cargar costos indirectos (${tipoCli})</button>
        <span style="font-size:.74rem;opacity:.6;align-self:center">Agrega 3 líneas (IN-OUT, ALM, TÚNEL) con unitarios calculados.</span>
      </div>
      <div id="coLineas" style="margin-top:6px"></div>
      <div class="cost-field" style="margin-top:14px"><label>Observaciones</label>
        <textarea id="coObs" rows="2"></textarea>
      </div>
      <div class="cost-totales" id="coTot"></div>`;
    const footer = `
      <button class="cost-btn cost-btn-ghost" onclick="document.getElementById('cost-overlay-modal').remove()">Cancelar</button>
      <button class="cost-btn cost-btn-primary" id="coReg">💾 Registrar costos</button>`;
    abrirModal(`Costear · ${codigo}`, cod?.denominacion || '', html, { footer });

    const lineasEl = $('#coLineas');
    const totEl = $('#coTot');

    const dibujarLineas = () => {
      const tipos = ['IN','OUT','ALMACENAMIENTO','TUNEL'];
      lineasEl.innerHTML = tipos.map(t => {
        const list = lineas.filter(l => l.tipo_operacion === t);
        return `<div class="cost-seccion"><h4>${t}</h4>
          <div class="cost-item-row" style="opacity:.6;font-size:.7rem;font-weight:700;text-transform:uppercase">
            <div>Ítem</div><div>Cant.</div><div>Costo unit.</div><div>Mon.</div><div>Subtotal</div><div></div>
          </div>
          ${list.map((l, i) => {
            const globalIdx = lineas.indexOf(l);
            const indBadge = l.es_indirecto ? '<span class="cost-badge" style="background:rgba(217,119,6,.25);color:#fcd34d;margin-left:4px;font-size:.6rem">IND</span>' : '';
            const indStyle = l.es_indirecto ? 'background:rgba(217,119,6,.06);border-left:2px solid #d97706;padding-left:6px' : '';
            return `<div class="cost-item-row" data-i="${globalIdx}" style="${indStyle}">
              <div>${esc(l.nombre)}${indBadge} <small style="opacity:.5">(${esc(l.unidad)})</small></div>
              <input type="number" step="0.0001" class="co-cant" value="${l.cantidad||0}">
              <input type="number" step="0.0001" class="co-costo" value="${l.costo_unitario||0}">
              <select class="co-mon">
                <option value="ARS" ${l.moneda==='ARS'?'selected':''}>ARS</option>
                <option value="USD" ${l.moneda==='USD'?'selected':''}>USD</option>
              </select>
              <div class="co-sub" style="text-align:right;font-family:ui-monospace,Consolas,monospace">${fmt((l.cantidad||0)*(l.costo_unitario||0))}</div>
              <button class="cost-btn cost-btn-danger co-del" style="padding:3px 6px;font-size:.68rem">✕</button>
            </div>`;
          }).join('')}
          <div style="margin-top:6px"><button class="cost-btn cost-btn-ghost co-add" data-t="${t}" style="padding:4px 10px;font-size:.74rem">+ Agregar ${t}</button></div>
        </div>`;
      }).join('');

      $$('.co-cant', lineasEl).forEach(inp => inp.oninput = () => {
        const row = inp.closest('[data-i]'); const idx = +row.dataset.i;
        lineas[idx].cantidad = +inp.value || 0;
        row.querySelector('.co-sub').textContent = fmt(lineas[idx].cantidad * lineas[idx].costo_unitario);
        recalc();
      });
      $$('.co-costo', lineasEl).forEach(inp => inp.oninput = () => {
        const row = inp.closest('[data-i]'); const idx = +row.dataset.i;
        lineas[idx].costo_unitario = +inp.value || 0;
        row.querySelector('.co-sub').textContent = fmt(lineas[idx].cantidad * lineas[idx].costo_unitario);
        recalc();
      });
      $$('.co-mon', lineasEl).forEach(s => s.onchange = () => {
        const idx = +s.closest('[data-i]').dataset.i;
        lineas[idx].moneda = s.value;
        recalc();
      });
      $$('.co-del', lineasEl).forEach(b => b.onclick = () => {
        const idx = +b.closest('[data-i]').dataset.i;
        lineas.splice(idx, 1);
        dibujarLineas();
        recalc();
      });
      $$('.co-add', lineasEl).forEach(b => b.onclick = () => {
        const tipo = b.dataset.t;
        const disp = catalogo.filter(c => c.tipo_operacion === tipo);
        if (!disp.length) return toast('No hay ítems de '+tipo+' en el catálogo','warn');
        const sel = document.createElement('select');
        sel.innerHTML = disp.map(d => `<option value="${d.id}">${d.nombre} · ${fmt(d.costo_unitario)} ${d.moneda}/${d.unidad}</option>`).join('');
        const opcHtml = `<div class="cost-field"><label>Elegir ítem</label>${sel.outerHTML}</div>`;
        abrirModal('Agregar ítem a '+tipo, '', opcHtml, {
          footer: `<button class="cost-btn cost-btn-primary" id="okAdd">Agregar</button>`
        });
        $('#okAdd').onclick = () => {
          const idSel = +$('#cost-overlay-modal select').value;
          const it = disp.find(d => d.id === idSel);
          if (it) {
            lineas.push({ tipo_operacion: tipo, item_catalogo_id: it.id,
              nombre: it.nombre, unidad: it.unidad, cantidad: 0,
              costo_unitario: +it.costo_unitario, moneda: it.moneda });
          }
          cerrarModal(); dibujarLineas(); recalc();
        };
      });
    };

    const recalc = () => {
      const p = +$('#coPal').value || 1;
      const pw = +$('#coPW').value || 1;
      const pt = p * pw;
      $('#coPT').value = fmt(pt);
      const sumBy = t => lineas.filter(l=>l.tipo_operacion===t).reduce((a,b)=>a+(+b.cantidad)*(+b.costo_unitario), 0);
      const sIn = sumBy('IN'), sOut = sumBy('OUT'), sAlm = sumBy('ALMACENAMIENTO'), sTu = sumBy('TUNEL');
      const tot = sIn + sOut + sAlm + sTu;
      const mns = new Set(lineas.filter(l=>+l.cantidad>0).map(l=>l.moneda));
      const mon = mns.size === 1 ? [...mns][0] : (mns.size > 1 ? 'MIXTA' : 'ARS');
      totEl.innerHTML = `
        <div class="row"><span>IN</span><strong>${fmt(sIn)}</strong></div>
        <div class="row"><span>OUT</span><strong>${fmt(sOut)}</strong></div>
        <div class="row"><span>ALMACENAMIENTO</span><strong>${fmt(sAlm)}</strong></div>
        <div class="row"><span>TUNEL</span><strong>${fmt(sTu)}</strong></div>
        <div class="row big"><span>TOTAL GENERAL</span><strong>${fmt(tot)} ${mon}</strong></div>
        <div class="row"><span>Pallets</span><strong>${p}</strong></div>
        <div class="row"><span>Peso total</span><strong>${fmt(pt)} kg</strong></div>
        <div class="row"><span>Precio por pallet</span><strong>${fmt(tot/p)} ${mon}</strong></div>
        <div class="row"><span>Precio por kg</span><strong>${fmtKg(tot/pt)} ${mon}</strong></div>`;
    };

    $('#coPal').oninput = recalc;
    $('#coPW').oninput  = recalc;
    $('#coDias').oninput = recalc;

    // Cargar costos indirectos: agrega 3 líneas si no existen
    $('#coCargaInd').onclick = () => {
      const p  = +$('#coPal').value || 22;
      const d  = +$('#coDias').value || 30;
      const targets = [
        { tipo:'IN',              nombre:'COSTOS INDIRECTOS · IN-OUT',  unidad:'movimiento', cantidad: p*2, costo: uIO },
        { tipo:'ALMACENAMIENTO',  nombre:'COSTOS INDIRECTOS · ALM',     unidad:'pos-día',    cantidad: p*d, costo: uALM },
        { tipo:'TUNEL',           nombre:'COSTOS INDIRECTOS · TUNEL',   unidad:'día-túnel',  cantidad: 0,   costo: uTUN },
      ];
      let added = 0;
      targets.forEach(t => {
        const ya = lineas.find(l => l.es_indirecto && l.nombre === t.nombre);
        if (ya) { ya.cantidad = t.cantidad; ya.costo_unitario = t.costo; return; }
        lineas.push({
          tipo_operacion: t.tipo, item_catalogo_id: null, nombre: t.nombre, unidad: t.unidad,
          cantidad: t.cantidad, costo_unitario: t.costo, moneda: 'ARS', es_indirecto: true,
        });
        added++;
      });
      dibujarLineas(); recalc();
      toast(added > 0 ? `${added} líneas de costos indirectos agregadas` : 'Indirectos actualizados');
    };

    dibujarLineas(); recalc();

    $('#coReg').onclick = async () => {
      if (!confirm('¿Registrar este costeo? El anterior quedará como histórico no vigente.')) return;
      const p = +$('#coPal').value;
      const pw = +$('#coPW').value;
      const dd = +$('#coDias').value || 30;
      if (p <= 0 || pw <= 0) return toast('Pallets y peso deben ser > 0','err');
      const res = await apiPost('cost_costeo_save', {
        codigo_operacion: codigo,
        cantidad_pallets: p,
        peso_pallet: pw,
        dias_almacenamiento: dd,
        observaciones: $('#coObs').value,
        items: lineas,
      });
      if (res.ok) { toast('Costeo registrado: '+res.numero_costeo); cerrarModal(); abrirCosteo(); }
      else toast(res.error || 'Error', 'err');
    };
  }

  async function verHistorialCodigo(codigo) {
    const { historial } = await apiGet('cost_historial_codigo', { codigo });
    const html = `
      <div class="cost-note">Historial completo de costeos para <strong>${esc(codigo)}</strong>. El vigente es la primera fila.</div>
      <table class="cost-table">
        <thead><tr><th>Fecha</th><th>Nº Costeo</th><th>Pallets</th><th>Peso tot.</th><th>Total</th><th>$/Pallet</th><th>$/Kg</th><th>Mon.</th><th>Por</th><th>Vigente</th><th></th></tr></thead>
        <tbody>${historial.map(h=>`
          <tr>
            <td>${esc(h.fecha_costeo)}</td>
            <td>${esc(h.numero_costeo)}</td>
            <td class="n">${h.cantidad_pallets}</td>
            <td class="n">${fmt(h.peso_total)}</td>
            <td class="n"><strong>${fmt(h.total_general)}</strong></td>
            <td class="n">${fmt(h.precio_por_pallet)}</td>
            <td class="n">${fmtKg(h.precio_por_kg)}</td>
            <td>${esc(h.moneda)}</td>
            <td>${esc(h.usuario_nombre||'—')}</td>
            <td><span class="cost-badge ${h.vigente?'cost-b-ok':'cost-b-off'}">${h.vigente?'SÍ':'NO'}</span></td>
            <td><button class="cost-btn cost-btn-ghost vHist" data-id="${h.id}" style="padding:3px 8px;font-size:.72rem">Ver</button></td>
          </tr>`).join('')}</tbody>
      </table>
      ${historial.length===0?'<div style="padding:20px;opacity:.5;text-align:center">Sin historial</div>':''}`;
    abrirModal(`Historial · ${codigo}`, '', html);
    $$('.vHist', document).forEach(b => b.onclick = () => verCosteo(b.dataset.id));
  }

  // ────── TAB: PR-08 sin código ──────
  async function renderPr08(cont) {
    const [{ pr08 }, { codigos }] = await Promise.all([
      apiGet('cost_pr08_sin_codigo'), apiGet('cost_codigo_list'),
    ]);
    const codsNac = codigos.filter(c=>c.tipo_cliente==='nacional' && c.activo);
    const codsFis = codigos.filter(c=>c.tipo_cliente==='fiscal'   && c.activo);
    cont.innerHTML = `
      <div class="cost-note">Última fila de <code>cliente_frio</code> por cliente. Asigná un código a los que no tengan.</div>
      <table class="cost-table">
        <thead><tr><th>Cliente</th><th>Tipo</th><th>Producto</th><th>Fecha</th><th>Código</th><th></th></tr></thead>
        <tbody>${pr08.map(p=>{
          const esF = (+p.fiscal === 1);
          const cods = esF ? codsFis : codsNac;
          return `<tr data-cfid="${p.cliente_frio_id}">
            <td>${esc(p.ClienteNombre||'('+p.cliente_id+')')}</td>
            <td><span class="cost-badge ${esF?'cost-b-fis':'cost-b-nac'}">${esF?'FISCAL':'NACIONAL'}</span></td>
            <td style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(p.descripcion_producto||'—')}</td>
            <td>${esc(p.updated_at||p.fecha_registro||'')}</td>
            <td>
              <select class="selCod" style="padding:4px 8px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.12);color:#fff;border-radius:4px;font-size:.78rem">
                <option value="">— sin código —</option>
                ${cods.map(c=>`<option value="${c.codigo}" ${p.codigo_operacion===c.codigo?'selected':''}>${c.codigo} · ${esc(c.denominacion)}</option>`).join('')}
              </select>
            </td>
            <td><button class="cost-btn cost-btn-primary asgCod" style="padding:3px 10px;font-size:.72rem">Asignar</button></td>
          </tr>`;
        }).join('')}</tbody>
      </table>
      ${pr08.length===0?'<div style="padding:20px;opacity:.5;text-align:center">Sin PR-08 registrados</div>':''}`;
    $$('.asgCod', cont).forEach(b => b.onclick = async () => {
      const tr = b.closest('tr');
      const cfid = +tr.dataset.cfid;
      const cod = $('.selCod', tr).value;
      const res = await apiPost('cost_asignar_pr08', { cliente_frio_id: cfid, codigo: cod });
      if (res.ok) toast('Asignado: ' + (cod || '(ninguno)'));
      else toast(res.error || 'Error', 'err');
    });
  }

  // ────── TAB: Reportes ──────
  async function renderReportes(cont) {
    cont.innerHTML = `
      <div class="cost-note">
        El reporte incluye, para cada código con costeo vigente: fecha, número, denominación, totales por tipo (IN/OUT/ALM/TUNEL), total general,
        precio por pallet y por kg.
      </div>
      <div style="display:flex;gap:12px;margin-top:14px">
        <button class="cost-btn cost-btn-primary" id="repPdf">🖨️ Abrir reporte (imprimible a PDF)</button>
        <button class="cost-btn cost-btn-primary" id="repCsv" style="background:linear-gradient(135deg,#10b981,#059669)">📥 Descargar CSV</button>
      </div>`;
    $('#repPdf').onclick = () => window.open(`${API}?action=cost_reporte_html`, '_blank');
    $('#repCsv').onclick = () => { window.location = `${API}?action=cost_reporte_csv`; };
  }

  // API global
  window.EP08_COSTEO = { abrirCosteo };
})();
