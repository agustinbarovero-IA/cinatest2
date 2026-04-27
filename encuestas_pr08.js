/* ============================================================
 *  encuestas_pr08.js — Encuesta de Satisfacción + PR-08/B
 *  Grupo CINA
 * ============================================================
 *  Tiles en el menú (disparados desde app.js):
 *    COMERCIAL Y PLANIFICACION → ENCUESTA SATISFACCION
 *    CALIDAD                    → PR 08 CONDICIONES DE CONTRATACION
 *
 *  Encuesta: tablas propias (encuesta_*).
 *  PR-08: respuestas en `cliente_frio` (tabla histórica).
 * ============================================================ */

(() => {
  const API = 'api_encuestas_pr08.php';

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
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    });
    return r.json();
  }

  function toast(msg, tipo='ok') {
    let t = document.getElementById('ep08-toast');
    if (!t) {
      t = document.createElement('div');
      t.id = 'ep08-toast';
      t.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);padding:12px 20px;border-radius:10px;color:#fff;font-weight:600;z-index:999999;box-shadow:0 8px 24px rgba(0,0,0,.3);transition:opacity .25s;pointer-events:none';
      document.body.appendChild(t);
    }
    t.textContent = msg;
    t.style.background = tipo === 'err' ? '#dc2626' : tipo === 'warn' ? '#d97706' : '#059669';
    t.style.opacity = '1';
    clearTimeout(t._to);
    t._to = setTimeout(() => t.style.opacity = '0', 2800);
  }

  // ── CSS ──────────────────────────────────────────────────────
  (function inyectarCSS() {
    if (document.getElementById('ep08-css')) return;
    const css = document.createElement('style');
    css.id = 'ep08-css';
    css.textContent = `
      .ep08-overlay { position:fixed; inset:0; background:rgba(0,0,0,.68); z-index:99998;
        display:flex; align-items:center; justify-content:center; padding:24px; }
      .ep08-modal { background:#0a1430; color:#e7ecf7; border-radius:16px;
        max-width:960px; width:100%; max-height:92vh; overflow:auto;
        box-shadow:0 20px 60px rgba(0,0,0,.5); border:1px solid rgba(255,255,255,.08); }
      .ep08-modal header { padding:18px 24px; border-bottom:1px solid rgba(255,255,255,.08);
        display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; background:#0a1430; z-index:2; }
      .ep08-modal header h3 { margin:0; font-size:1.1rem; font-weight:800; letter-spacing:.2px; }
      .ep08-modal header .sub { font-size:.74rem; opacity:.65; margin-top:2px; }
      .ep08-modal .body { padding:20px 24px; }
      .ep08-modal .footer { padding:14px 24px; border-top:1px solid rgba(255,255,255,.08);
        display:flex; gap:10px; justify-content:flex-end; background:rgba(255,255,255,.02); position:sticky; bottom:0; }
      .ep08-btn { padding:10px 18px; border:none; border-radius:8px; font-weight:700; cursor:pointer; font-size:.86rem; font-family:inherit; }
      .ep08-btn-primary { background:linear-gradient(135deg,#36B0C9,#00A887); color:#fff; }
      .ep08-btn-ghost { background:rgba(255,255,255,.08); color:#fff; border:1px solid rgba(255,255,255,.14); }
      .ep08-btn-danger { background:#dc2626; color:#fff; }
      .ep08-btn:hover { opacity:.9; }
      .ep08-btn:disabled { opacity:.5; cursor:not-allowed; }
      .ep08-desc { white-space:pre-wrap; }
      .ep08-pregunta { padding:14px 16px; border:1px solid rgba(255,255,255,.08); border-radius:10px; margin-bottom:10px;
        background:rgba(255,255,255,.02); }
      .ep08-pregunta .ptitle { font-weight:700; margin-bottom:8px; font-size:.92rem; }
      .ep08-pregunta .ptitle .obl { color:#ef4444; }
      .ep08-pregunta .opciones label { display:flex; align-items:center; gap:8px; padding:5px 0; cursor:pointer; font-size:.88rem; }
      .ep08-pregunta input[type=text], .ep08-pregunta textarea { width:100%; padding:8px 10px; background:rgba(255,255,255,.05);
        border:1px solid rgba(255,255,255,.12); color:#fff; border-radius:6px; font-family:inherit; font-size:.88rem; }
      .ep08-seccion { margin-top:18px; }
      .ep08-seccion h4 { margin:0 0 10px; font-size:.82rem; text-transform:uppercase; opacity:.65;
        letter-spacing:.5px; border-bottom:1px solid rgba(255,255,255,.08); padding-bottom:6px; }
      .ep08-grid2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
      @media(max-width:640px){ .ep08-grid2 { grid-template-columns:1fr; } }
      .ep08-field { display:flex; flex-direction:column; gap:4px; padding:6px 0; }
      .ep08-field label { font-size:.76rem; opacity:.75; font-weight:600; }
      .ep08-field input, .ep08-field textarea, .ep08-field select {
        padding:8px 10px; background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.12);
        color:#fff; border-radius:6px; font-family:inherit; font-size:.88rem; }
      .ep08-table { width:100%; border-collapse:collapse; font-size:.85rem; }
      .ep08-table th, .ep08-table td { padding:8px 10px; border-bottom:1px solid rgba(255,255,255,.08); text-align:left; vertical-align:top; }
      .ep08-table th { opacity:.7; font-weight:700; font-size:.74rem; text-transform:uppercase; }
      .ep08-tabs { display:flex; gap:8px; margin-bottom:14px; border-bottom:1px solid rgba(255,255,255,.08); }
      .ep08-tab { padding:9px 16px; cursor:pointer; border:none; background:none; color:#e7ecf7; font-weight:700;
        opacity:.55; font-size:.88rem; border-bottom:2px solid transparent; font-family:inherit; }
      .ep08-tab.active { opacity:1; border-bottom-color:#36B0C9; }
      .ep08-badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:.7rem; font-weight:700; }
      .ep08-badge.ok { background:rgba(5,150,105,.25); color:#6ee7b7; }
      .ep08-badge.warn { background:rgba(217,119,6,.25); color:#fcd34d; }
      .ep08-badge.off { background:rgba(255,255,255,.1); color:#cbd5e1; }
      .ep08-note { font-size:.76rem; opacity:.7; padding:8px 12px; background:rgba(54,176,201,.08);
        border-left:3px solid #36B0C9; border-radius:6px; margin-bottom:14px; }
    `;
    document.head.appendChild(css);
  })();

  function abrirModal(titulo, subtitulo, contenidoHTML, opts={}) {
    cerrarModal();
    const o = document.createElement('div');
    o.className = 'ep08-overlay';
    o.id = 'ep08-overlay-modal';
    o.innerHTML = `
      <div class="ep08-modal" role="dialog" aria-modal="true">
        <header>
          <div>
            <h3>${esc(titulo)}</h3>
            ${subtitulo ? `<div class="sub">${esc(subtitulo)}</div>` : ''}
          </div>
          ${opts.cerrable !== false ? '<button class="ep08-btn ep08-btn-ghost" id="ep08BtnCerrar" style="padding:6px 12px">✕</button>' : ''}
        </header>
        <div class="body">${contenidoHTML}</div>
        ${opts.footer ? `<div class="footer">${opts.footer}</div>` : ''}
      </div>`;
    document.body.appendChild(o);
    const btnCerrar = document.getElementById('ep08BtnCerrar');
    if (btnCerrar) btnCerrar.onclick = cerrarModal;
    return o;
  }
  function cerrarModal() {
    const m = document.getElementById('ep08-overlay-modal');
    if (m) m.remove();
  }

  // ═════════════════════════════════════════════════════════════
  //   ADMIN — Encuesta (igual que antes)
  // ═════════════════════════════════════════════════════════════
  async function abrirAdminEncuesta() {
    const tabsHtml = `
      <div class="ep08-tabs">
        <button class="ep08-tab active" data-tab="config">⚙️ Configuración</button>
        <button class="ep08-tab" data-tab="preguntas">❓ Preguntas</button>
        <button class="ep08-tab" data-tab="resultados">📊 Resultados</button>
      </div>
      <div id="ep08-tab-content" style="padding:20px 0;opacity:.6">Cargando…</div>`;
    abrirModal('📋 Encuesta de Satisfacción del Cliente (PR-105)', '', tabsHtml);

    const { config } = await apiGet('encuesta_config_get');
    const subEl = $('.ep08-modal header .sub');
    if (subEl) subEl.textContent = config?.actualizado_en
      ? `Última edición: ${config.actualizado_en}${config.actualizado_por_nombre ? ' · '+config.actualizado_por_nombre : ''}`
      : 'Sin cambios previos';
    const cont = $('#ep08-tab-content');

    const renderConfig = () => {
      const c = config || {};
      cont.innerHTML = `
        <div class="ep08-grid2">
          <div class="ep08-field"><label>Estado</label>
            <select id="encActiva">
              <option value="0" ${!c.activa?'selected':''}>Inactiva</option>
              <option value="1" ${c.activa?'selected':''}>Activa (los clientes la verán al iniciar)</option>
            </select>
          </div>
          <div class="ep08-field"><label>Año vigente</label>
            <input type="number" id="encAnio" value="${esc(c.anio_vigente || new Date().getFullYear())}" min="2020" max="2099">
          </div>
          <div class="ep08-field"><label>Fecha de vencimiento (opcional)</label>
            <input type="date" id="encVenc" value="${esc(c.fecha_vencimiento || '')}">
          </div>
          <div class="ep08-field"><label>Título</label>
            <input type="text" id="encTit" value="${esc(c.titulo || 'Encuesta de Satisfacción del Cliente')}">
          </div>
          <div class="ep08-field" style="grid-column:1/-1"><label>Descripción</label>
            <textarea id="encDesc" rows="3">${esc(c.descripcion || '')}</textarea>
          </div>
        </div>
        <div style="margin-top:18px;display:flex;gap:8px;justify-content:flex-end">
          <button class="ep08-btn ep08-btn-primary" id="encGuardar">💾 Guardar configuración</button>
        </div>`;
      $('#encGuardar').onclick = async () => {
        const res = await apiPost('encuesta_config_save', {
          activa: +$('#encActiva').value,
          anio_vigente: +$('#encAnio').value,
          fecha_vencimiento: $('#encVenc').value || null,
          titulo: $('#encTit').value,
          descripcion: $('#encDesc').value,
        });
        if (res.ok) { toast('Configuración guardada');
          Object.assign(config || {}, {
            activa: +$('#encActiva').value, anio_vigente: +$('#encAnio').value,
            fecha_vencimiento: $('#encVenc').value || null,
            titulo: $('#encTit').value, descripcion: $('#encDesc').value,
          });
        } else toast(res.error || 'Error al guardar', 'err');
      };
    };

    const renderPreguntas = async () => {
      cont.innerHTML = '<div style="padding:20px;opacity:.6">Cargando preguntas…</div>';
      const { preguntas: list } = await apiGet('encuesta_preguntas_get');
      const tipos = ['radio','checkbox','escala','texto','textarea'];
      const pregItemHTML = (p, idx) => {
        const op = Array.isArray(p.opciones) ? p.opciones.join('\n') : '';
        return `
          <div class="ep08-pregunta" data-pid="${esc(p.id||'')}">
            <div class="ep08-grid2">
              <div class="ep08-field"><label class="label-num">Pregunta ${idx+1}</label><input type="text" class="pqt" value="${esc(p.texto||'')}"></div>
              <div class="ep08-field"><label>Tipo</label>
                <select class="pqp">${tipos.map(t=>`<option value="${t}" ${p.tipo===t?'selected':''}>${t}</option>`).join('')}</select>
              </div>
            </div>
            <div class="ep08-field" style="margin-top:6px"><label>Texto de ayuda (subtítulo, opcional)</label><input type="text" class="pqh" value="${esc(p.ayuda||'')}"></div>
            <div class="ep08-field" style="margin-top:6px"><label>Opciones (una por línea — sólo radio/checkbox; escala usa 1-10 fijo)</label><textarea class="pqop" rows="3">${esc(op)}</textarea></div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px;flex-wrap:wrap;gap:10px">
              <div style="display:flex;gap:14px;align-items:center">
                <label style="display:flex;align-items:center;gap:6px;font-size:.85rem"><input type="checkbox" class="pqo" ${p.obligatoria?'checked':''}> Obligatoria</label>
                <label style="display:flex;align-items:center;gap:6px;font-size:.85rem"><input type="checkbox" class="pqot" ${p.permite_otros?'checked':''}> Permite "Otros:" con texto libre</label>
              </div>
              <button type="button" class="ep08-btn ep08-btn-danger pqdel" style="padding:4px 10px;font-size:.74rem">Eliminar</button>
            </div>
          </div>`;
      };
      cont.innerHTML = `
        <div style="margin-bottom:10px;display:flex;justify-content:flex-end">
          <button class="ep08-btn ep08-btn-ghost" id="encRestaurar" style="font-size:.78rem">♻️ Restaurar preguntas por defecto</button>
        </div>
        <div id="encPregList">${list.map((p, idx) => pregItemHTML(p, idx)).join('')}</div>
        <div style="margin-top:10px;display:flex;gap:8px;justify-content:space-between">
          <button class="ep08-btn ep08-btn-ghost" id="encAdd">+ Agregar pregunta</button>
          <button class="ep08-btn ep08-btn-primary" id="encGuardarP">💾 Guardar preguntas</button>
        </div>`;
      const listRoot = $('#encPregList');
      const renumerar = () => $$('.ep08-pregunta', listRoot).forEach((el,i) => {
        const lbl = el.querySelector('.label-num'); if (lbl) lbl.textContent = 'Pregunta '+(i+1);
      });
      const bindDel = el => {
        const del = el.querySelector('.pqdel');
        if (del) del.onclick = () => { el.remove(); renumerar(); };
      };
      $$('.ep08-pregunta', listRoot).forEach(bindDel);
      $('#encAdd').onclick = () => {
        const wrap = document.createElement('div');
        wrap.innerHTML = pregItemHTML({texto:'',tipo:'radio',opciones:[],obligatoria:1,permite_otros:0}, $$('.ep08-pregunta', listRoot).length);
        const el = wrap.firstElementChild;
        listRoot.appendChild(el); bindDel(el); renumerar();
      };
      $('#encRestaurar').onclick = async () => {
        if (!confirm('¿Restaurar las preguntas por defecto (Encuesta abril 2026)?\n\nLas preguntas actuales se desactivan (se mantienen las respuestas históricas) y se cargan las 9 preguntas estándar.')) return;
        const res = await apiPost('encuesta_restaurar_default');
        if (res.ok) { toast(`Restauradas ${res.cantidad} preguntas`); renderPreguntas(); }
        else toast(res.error || 'Error', 'err');
      };
      $('#encGuardarP').onclick = async () => {
        const preguntasOut = $$('.ep08-pregunta', listRoot).map((el, i) => ({
          id: el.dataset.pid ? +el.dataset.pid : null,
          orden: i+1,
          texto: $('.pqt', el).value.trim(),
          ayuda: $('.pqh', el).value.trim(),
          tipo: $('.pqp', el).value,
          opciones: $('.pqop', el).value.split('\n').map(s=>s.trim()).filter(Boolean),
          obligatoria: $('.pqo', el).checked ? 1 : 0,
          permite_otros: $('.pqot', el).checked ? 1 : 0,
        })).filter(p => p.texto);
        const res = await apiPost('encuesta_preguntas_save', { preguntas: preguntasOut });
        if (res.ok) toast('Preguntas guardadas');
        else toast(res.error || 'Error', 'err');
      };
    };

    const renderResultados = async () => {
      cont.innerHTML = '<div style="padding:20px;opacity:.6">Cargando resultados…</div>';
      const anio = new Date().getFullYear();
      const { resultados, preguntas: preg } = await apiGet('encuesta_resultados', { anio });
      if (!resultados.length) {
        cont.innerHTML = `<div style="padding:20px;opacity:.6">Sin respuestas para ${anio}.</div>`;
        return;
      }
      const resumen = preg.map(p => {
        const conteo = {};
        resultados.forEach(r => {
          const v = r.respuestas[p.id];
          if (Array.isArray(v)) v.forEach(x => conteo[x] = (conteo[x]||0)+1);
          else if (v) conteo[v] = (conteo[v]||0)+1;
        });
        return { p, conteo };
      });
      cont.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
          <div><strong>${resultados.length}</strong> respuestas · Año ${anio}</div>
          <button class="ep08-btn ep08-btn-ghost" id="encExp">📥 Exportar Excel (CSV)</button>
        </div>
        ${resumen.map(({p, conteo}) => {
          if (p.tipo === 'texto' || p.tipo === 'textarea') {
            const coms = resultados.map(r => r.respuestas[p.id]).filter(Boolean);
            return `<div class="ep08-seccion"><h4>${esc(p.texto)}</h4>${
              coms.length ? coms.map(c=>`<div style="padding:6px 10px;background:rgba(255,255,255,.04);margin:4px 0;border-radius:4px;font-size:.85rem;white-space:pre-wrap">${esc(c)}</div>`).join('') : '<div style="opacity:.5;font-size:.82rem">Sin comentarios</div>'
            }</div>`;
          }
          if (p.tipo === 'escala') {
            // Promedio + distribución 1-10
            const nums = resultados.map(r => +r.respuestas[p.id]).filter(n => n>=1 && n<=10);
            const prom = nums.length ? (nums.reduce((a,b)=>a+b,0)/nums.length) : 0;
            const dist = {};
            for (let i=1; i<=10; i++) dist[i] = 0;
            nums.forEach(n => dist[n]++);
            const maxV = Math.max(...Object.values(dist), 1);
            return `<div class="ep08-seccion"><h4>${esc(p.texto)}</h4>
              <div style="font-size:.9rem;margin-bottom:6px"><strong>Promedio: ${prom.toFixed(2)}</strong> / 10 · ${nums.length} respuestas</div>
              <div style="display:flex;gap:4px;align-items:flex-end;height:80px;padding:4px 0">
                ${Object.entries(dist).map(([k,v]) => {
                  const h = Math.round(v*100/maxV);
                  return `<div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:3px">
                    <span style="font-size:.68rem;opacity:.7">${v||''}</span>
                    <div style="width:100%;height:${h}%;min-height:2px;background:linear-gradient(180deg,#36B0C9,#00A887);border-radius:3px 3px 0 0"></div>
                    <span style="font-size:.68rem;opacity:.6">${k}</span>
                  </div>`;
                }).join('')}
              </div></div>`;
          }
          const total = Object.values(conteo).reduce((a,b)=>a+b, 0) || 1;
          return `<div class="ep08-seccion"><h4>${esc(p.texto)}</h4>
            ${Object.entries(conteo).map(([k,v]) => {
              const pct = Math.round(v*100/total);
              return `<div style="margin:6px 0"><div style="display:flex;justify-content:space-between;font-size:.82rem"><span>${esc(k)}</span><span>${v} · ${pct}%</span></div>
              <div style="background:rgba(255,255,255,.08);height:8px;border-radius:4px;overflow:hidden"><div style="width:${pct}%;height:100%;background:linear-gradient(90deg,#36B0C9,#00A887)"></div></div></div>`;
            }).join('')}</div>`;
        }).join('')}
        <div class="ep08-seccion">
          <h4>Respuestas individuales</h4>
          <table class="ep08-table">
            <thead><tr><th>Cliente</th><th>Respondido por</th><th>Fecha</th></tr></thead>
            <tbody>${resultados.map(r=>`<tr><td>${esc(r.ClienteNombre||'('+r.cliente_id+')')}</td><td>${esc(r.usuario_nombre||'—')}</td><td>${esc(r.fecha_respuesta||'')}</td></tr>`).join('')}</tbody>
          </table>
        </div>`;
      $('#encExp').onclick = () => { window.location = `${API}?action=encuesta_resultados_excel&anio=${anio}`; };
    };

    renderConfig();
    $$('.ep08-tab').forEach(t => {
      t.onclick = () => {
        $$('.ep08-tab').forEach(x => x.classList.remove('active'));
        t.classList.add('active');
        if (t.dataset.tab === 'config') renderConfig();
        else if (t.dataset.tab === 'preguntas') renderPreguntas();
        else renderResultados();
      };
    });
  }

  // ═════════════════════════════════════════════════════════════
  //   ADMIN — PR-08 / PR-08B
  // ═════════════════════════════════════════════════════════════
  async function abrirAdminPr08() {
    abrirModal('📄 PR-08 / PR-08B · Condiciones de Contratación',
      'Las respuestas se almacenan en la tabla cliente_frio (histórico)',
      `<div class="ep08-tabs">
         <button class="ep08-tab active" data-tab="nac">🌎 PR-08 Nacional</button>
         <button class="ep08-tab" data-tab="fis">🛃 PR-08B Fiscal</button>
         <button class="ep08-tab" data-tab="resp">📋 Respuestas</button>
       </div>
       <div id="ep08-pr08-tab" style="padding:20px 0;opacity:.6">Cargando…</div>`);

    const renderPlantilla = async (tipo) => {
      const cont = $('#ep08-pr08-tab');
      cont.innerHTML = '<div style="padding:20px;opacity:.6">Cargando…</div>';
      const { plantilla, columnas_validas } = await apiGet('pr08_plantilla_get', { tipo });
      const config = plantilla?.campos || {};
      const secciones = config.secciones || [];

      cont.innerHTML = `
        <div class="ep08-note">
          <strong>Importante:</strong> al guardar una plantilla y activarla, quienes ya hayan completado
          el formulario <em>antes</em> de la nueva fecha de lanzamiento volverán a verlo pendiente.
          Cuando un cliente responde, se inserta una nueva fila en <code>cliente_frio</code>.
        </div>
        <div style="margin-bottom:10px;font-size:.78rem;opacity:.7">
          ${plantilla?.actualizado_en ? ('Última edición: '+plantilla.actualizado_en+' · '+(plantilla.actualizado_por_nombre||'')) : 'Sin cambios previos'}
          · versión ${plantilla?.version || 1}
          ${plantilla?.fecha_lanzamiento ? ' · lanzada: ' + plantilla.fecha_lanzamiento : ''}
        </div>
        <div class="ep08-grid2">
          <div class="ep08-field"><label>Estado</label>
            <select id="pr08Act">
              <option value="0" ${!plantilla?.activa?'selected':''}>Inactivo</option>
              <option value="1" ${plantilla?.activa?'selected':''}>Activo (clientes ${tipo==='fiscal'?'fiscales':'nacionales'} lo verán al iniciar)</option>
            </select>
          </div>
          <div class="ep08-field"><label>Fecha de vencimiento</label>
            <input type="date" id="pr08Venc" value="${esc(plantilla?.fecha_vencimiento || '')}">
          </div>
          <div class="ep08-field" style="grid-column:1/-1"><label>Título</label>
            <input type="text" id="pr08Tit" value="${esc(plantilla?.titulo || '')}">
          </div>
        </div>
        <div style="margin-top:14px;font-size:.78rem;opacity:.7">
          <strong>Plantilla (JSON)</strong> — columnas válidas de <code>cliente_frio</code>:
          <code style="display:block;margin-top:4px;padding:4px 8px;background:rgba(255,255,255,.04);border-radius:4px;font-size:.72rem">${(columnas_validas||[]).join(', ')}</code>
          <br>Estructura: <code>{ secciones:[{ titulo, campos:[{col, label, tipo, opciones?, coding?, obligatorio}] }] }</code>.
          Tipos: <code>text | textarea | number | decimal | date | radio | checkbox | checkbox_single</code>.
          Coding: <code>letters</code> (concat letras A,B,C…) o <code>index1</code> (índice 1-based).
        </div>
        <div class="ep08-field"><label>Plantilla (JSON)</label>
          <textarea id="pr08Campos" rows="22" style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.76rem">${esc(JSON.stringify(config, null, 2))}</textarea>
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:10px">
          <button class="ep08-btn ep08-btn-primary" id="pr08Guardar">💾 Guardar ${tipo==='fiscal'?'PR-08B':'PR-08'}</button>
        </div>`;
      $('#pr08Guardar').onclick = async () => {
        let campos;
        try { campos = JSON.parse($('#pr08Campos').value); }
        catch(e){ return toast('JSON inválido: '+e.message, 'err'); }
        const res = await apiPost('pr08_plantilla_save', {
          tipo, activa: +$('#pr08Act').value,
          fecha_vencimiento: $('#pr08Venc').value || null,
          titulo: $('#pr08Tit').value, campos,
        });
        if (res.ok) toast('Plantilla guardada (v'+res.version+')');
        else toast(res.error || 'Error', 'err');
      };
    };

    const renderRespuestas = async () => {
      const cont = $('#ep08-pr08-tab');
      cont.innerHTML = '<div style="padding:20px;opacity:.6">Cargando…</div>';
      const { respuestas } = await apiGet('pr08_respuestas_lista');
      cont.innerHTML = `
        <div class="ep08-note">Se muestra la <strong>última fila</strong> de <code>cliente_frio</code> por cliente (es tabla histórica).</div>
        <div style="display:flex;gap:8px;margin-bottom:10px">
          <button class="ep08-btn ep08-btn-ghost" id="expNac">📥 Exportar Nacional (CSV)</button>
          <button class="ep08-btn ep08-btn-ghost" id="expFis">📥 Exportar Fiscal (CSV)</button>
        </div>
        <table class="ep08-table">
          <thead><tr><th>Cliente</th><th>Tipo</th><th>Producto</th><th>Editado por</th><th>Fecha registro</th><th>Actualizado</th><th></th></tr></thead>
          <tbody>${respuestas.map(r=>`
            <tr>
              <td>${esc(r.ClienteNombre||'('+r.cliente_id+')')}</td>
              <td><span class="ep08-badge ${r.tipo==='fiscal'?'warn':'ok'}">${r.tipo.toUpperCase()}</span></td>
              <td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(r.descripcion_producto||'—')}</td>
              <td>${esc(r.usuario_nombre||r.trato_cliente||'—')}</td>
              <td>${esc(r.fecha_registro||'')}</td>
              <td>${esc(r.updated_at||r.created_at||'')}</td>
              <td><button class="ep08-btn ep08-btn-ghost verResp" data-cid="${r.cliente_id}" style="padding:4px 10px;font-size:.74rem">Ver</button></td>
            </tr>`).join('')}</tbody>
        </table>
        ${respuestas.length === 0 ? '<div style="padding:20px;opacity:.55;text-align:center">Sin respuestas cargadas</div>' : ''}`;
      $('#expNac').onclick = () => { window.location = `${API}?action=pr08_respuestas_excel&tipo=nacional`; };
      $('#expFis').onclick = () => { window.location = `${API}?action=pr08_respuestas_excel&tipo=fiscal`; };
      $$('.verResp', cont).forEach(b => b.onclick = async () => {
        const { datos, plantilla, cliente, tipo } = await apiGet('pr08_respuesta_get', { cliente_id: b.dataset.cid });
        const secciones = plantilla?.campos?.secciones || [];
        const html = secciones.map(s => `
          <div class="ep08-seccion"><h4>${esc(s.titulo || s.seccion || '')}</h4>
            <div class="ep08-grid2">${(s.campos||[]).map(c=>{
              let v = datos?.[c.col];
              if (Array.isArray(v)) v = v.join(', ');
              else if (v === true) v = 'SÍ';
              else if (v === false) v = 'NO';
              return `<div class="ep08-field"><label>${esc(c.label)}</label><div style="padding:6px 0;font-size:.88rem;white-space:pre-wrap">${esc(v ?? '—')}</div></div>`;
            }).join('')}</div>
          </div>`).join('');
        abrirModal(`${tipo.toUpperCase()} · ${cliente?.ClienteNombre || ''}`,
          `Cliente #${b.dataset.cid}`, html || '<div style="opacity:.6">Sin datos</div>');
      });
    };

    $$('.ep08-tab').forEach(t => {
      t.onclick = () => {
        $$('.ep08-tab').forEach(x => x.classList.remove('active'));
        t.classList.add('active');
        if (t.dataset.tab === 'nac') renderPlantilla('nacional');
        else if (t.dataset.tab === 'fis') renderPlantilla('fiscal');
        else renderRespuestas();
      };
    });
    renderPlantilla('nacional');
  }

  // ═════════════════════════════════════════════════════════════
  //   PORTAL CLIENTE
  // ═════════════════════════════════════════════════════════════
  function esCliente() {
    try { return typeof esClientePortal === 'function' && esClientePortal(); }
    catch(e){ return false; }
  }

  async function checkearPendientesCliente() {
    if (!esCliente()) return;
    try {
      const stPR = await apiGet('pr08_estado_cliente');
      if (stPR.estado === 'pendiente') { mostrarFormCliente_PR08(stPR); return; }
      const stEnc = await apiGet('encuesta_estado_cliente');
      if (stEnc.estado === 'pendiente') { mostrarFormCliente_Encuesta(stEnc); return; }
    } catch(e) { /* silencioso */ }
  }

  // ── ENCUESTA CLIENTE ──
  function mostrarFormCliente_Encuesta(st) {
    const cfg = st.config;
    const anio = cfg.anio_vigente;
    apiGet('encuesta_preguntas_get').then(({preguntas}) => {
      const html = `
        <div class="ep08-desc" style="margin-bottom:14px;font-size:.88rem;line-height:1.45">${esc(cfg.descripcion || '')}</div>
        <form id="encFormCli">
          ${preguntas.map(renderPreguntaEncuestaCli).join('')}
        </form>`;
      const footer = `
        <button class="ep08-btn ep08-btn-ghost" id="encPosponer">⏰ Recordar más tarde</button>
        <button class="ep08-btn ep08-btn-primary" id="encEnviar">✅ Enviar encuesta</button>`;
      abrirModal(`${cfg.titulo} · ${anio}`, 'Completá la encuesta para continuar operando', html, { cerrable:false, footer });
      $('#encPosponer').onclick = async () => {
        const res = await apiPost('encuesta_posponer');
        if (res.ok) { toast('Recordaremos más tarde'); cerrarModal(); }
        else toast(res.error || 'Error', 'err');
      };
      $('#encEnviar').onclick = async (e) => {
        e.preventDefault();
        const respuestas = {};
        for (const p of preguntas) {
          respuestas[p.id] = capturarRespuestaCli(p);
        }
        // Validación de obligatorias
        for (const p of preguntas) {
          if (p.obligatoria) {
            const v = respuestas[p.id];
            const vacio = v === null || v === '' || (Array.isArray(v) && !v.length);
            if (vacio) return toast('Falta responder: ' + p.texto, 'err');
          }
        }
        const res = await apiPost('encuesta_responder', { respuestas });
        if (res.ok) { toast('¡Gracias por tu respuesta!'); cerrarModal(); setTimeout(checkearPendientesCliente, 600); }
        else toast(res.error || 'Error', 'err');
      };
    });
  }

  // Captura la respuesta del cliente para una pregunta (aplica a radio, checkbox, escala, texto, textarea)
  function capturarRespuestaCli(p) {
    if (p.tipo === 'radio') {
      const sel = $(`input[name="p${p.id}"]:checked`);
      if (!sel) return null;
      if (sel.value === '__otros__') {
        const txt = $(`input[name="p${p.id}_otros_txt"]`)?.value.trim() || '';
        return 'Otros: ' + txt;
      }
      return sel.value;
    }
    if (p.tipo === 'checkbox') {
      const arr = $$(`input[name="p${p.id}"]:checked`).map(x => x.value);
      const otrosIdx = arr.indexOf('__otros__');
      if (otrosIdx >= 0) {
        const txt = $(`input[name="p${p.id}_otros_txt"]`)?.value.trim() || '';
        arr[otrosIdx] = 'Otros: ' + txt;
      }
      return arr;
    }
    if (p.tipo === 'escala') {
      const sel = $(`input[name="p${p.id}"]:checked`);
      return sel ? sel.value : null;
    }
    const el = $(`[name="p${p.id}"]`);
    return el ? el.value.trim() : '';
  }

  function renderPreguntaEncuestaCli(p) {
    const obl = p.obligatoria ? '<span class="obl"> *</span>' : '';
    const ayuda = p.ayuda ? `<div style="font-size:.78rem;opacity:.7;margin-bottom:6px">${esc(p.ayuda)}</div>` : '';
    const otrosReq = p.obligatoria ? '' : '';

    if (p.tipo === 'escala') {
      const nums = [1,2,3,4,5,6,7,8,9,10];
      return `<div class="ep08-pregunta"><div class="ptitle">${esc(p.texto)}${obl}</div>${ayuda}
        <div style="display:flex;gap:4px;justify-content:space-between;flex-wrap:wrap;margin-top:6px">
          ${nums.map(n => `
            <label style="display:flex;flex-direction:column;align-items:center;gap:4px;cursor:pointer;font-size:.78rem;min-width:32px">
              <span style="opacity:.7">${n}</span>
              <input type="radio" name="p${p.id}" value="${n}" ${p.obligatoria?'required':''} style="margin:0">
            </label>`).join('')}
        </div></div>`;
    }

    if (p.tipo === 'radio') {
      const ops = (p.opciones||[]).map(o => `
        <label><input type="radio" name="p${p.id}" value="${esc(o)}" ${p.obligatoria?'required':''}> ${esc(o)}</label>`).join('');
      const otros = p.permite_otros ? `
        <label style="display:flex;align-items:center;gap:8px">
          <input type="radio" name="p${p.id}" value="__otros__" ${p.obligatoria?'required':''}>
          <span>Otros:</span>
          <input type="text" name="p${p.id}_otros_txt" placeholder="Escribí acá" style="flex:1;padding:4px 8px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.12);color:#fff;border-radius:4px" onclick="this.previousElementSibling.previousElementSibling.checked=true">
        </label>` : '';
      return `<div class="ep08-pregunta"><div class="ptitle">${esc(p.texto)}${obl}</div>${ayuda}
        <div class="opciones">${ops}${otros}</div></div>`;
    }

    if (p.tipo === 'checkbox') {
      const ops = (p.opciones||[]).map(o => `
        <label><input type="checkbox" name="p${p.id}" value="${esc(o)}"> ${esc(o)}</label>`).join('');
      const otros = p.permite_otros ? `
        <label style="display:flex;align-items:center;gap:8px">
          <input type="checkbox" name="p${p.id}" value="__otros__">
          <span>Otros:</span>
          <input type="text" name="p${p.id}_otros_txt" placeholder="Escribí acá" style="flex:1;padding:4px 8px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.12);color:#fff;border-radius:4px" onclick="this.previousElementSibling.previousElementSibling.checked=true">
        </label>` : '';
      return `<div class="ep08-pregunta"><div class="ptitle">${esc(p.texto)}${obl}</div>${ayuda}
        <div class="opciones">${ops}${otros}</div></div>`;
    }

    if (p.tipo === 'textarea') {
      return `<div class="ep08-pregunta"><div class="ptitle">${esc(p.texto)}${obl}</div>${ayuda}
        <textarea name="p${p.id}" rows="3" ${p.obligatoria?'required':''}></textarea></div>`;
    }
    return `<div class="ep08-pregunta"><div class="ptitle">${esc(p.texto)}${obl}</div>${ayuda}
      <input type="text" name="p${p.id}" ${p.obligatoria?'required':''}></div>`;
  }

  // ── PR-08 CLIENTE ──
  function mostrarFormCliente_PR08(st) {
    const tipo = st.tipo;
    const pl = st.plantilla;
    const cli = st.cliente || {};
    const prev = st.respuesta_previa || {};
    const secciones = pl.campos?.secciones || [];
    const html = `
      <div class="ep08-desc" style="margin-bottom:14px;font-size:.88rem;opacity:.85">
        Antes de seguir operando, por favor confirmá/actualizá las condiciones de contratación (${tipo === 'fiscal' ? 'PR-08B' : 'PR-08'}).
        Los datos se guardan en el registro de condiciones frío del cliente.
      </div>
      <form id="pr08FormCli">
        ${secciones.map(sec => renderSeccionCli(sec, prev, cli)).join('')}
      </form>`;
    const footer = `
      <button class="ep08-btn ep08-btn-ghost" id="pr08Posponer">⏰ Recordar más tarde</button>
      <button class="ep08-btn ep08-btn-primary" id="pr08Enviar">✅ Guardar y continuar</button>`;
    abrirModal(`${pl.titulo || (tipo==='fiscal'?'PR-08B':'PR-08')}`,
      `Cliente: ${cli.ClienteNombre || ''}`, html, { cerrable:false, footer });

    $('#pr08Posponer').onclick = async () => {
      const res = await apiPost('pr08_posponer');
      if (res.ok) { toast('Recordaremos más tarde'); cerrarModal(); }
      else toast(res.error || 'Error', 'err');
    };
    $('#pr08Enviar').onclick = async (e) => {
      e.preventDefault();
      const datos = {};
      secciones.forEach(sec => (sec.campos||[]).forEach(c => {
        const col = c.col;
        if (c.tipo === 'checkbox' && c.opciones) {
          datos[col] = $$(`input[name="f${col}"]:checked`).map(x=>x.value);
        } else if (c.tipo === 'checkbox_single') {
          datos[col] = $(`input[name="f${col}"]`)?.checked ? 1 : 0;
        } else if (c.tipo === 'radio') {
          const v = $(`input[name="f${col}"]:checked`);
          datos[col] = v ? v.value : '';
        } else {
          const el = $(`[name="f${col}"]`);
          datos[col] = el ? el.value.trim() : '';
        }
      }));
      const res = await apiPost('pr08_responder', { datos });
      if (res.ok) { toast('Datos guardados, gracias'); cerrarModal(); setTimeout(checkearPendientesCliente, 600); }
      else toast(res.error || 'Error', 'err');
    };
  }

  function renderSeccionCli(sec, prev, cli) {
    return `<div class="ep08-seccion"><h4>${esc(sec.titulo || sec.seccion || '')}</h4>
      <div class="ep08-grid2">${(sec.campos||[]).map(c => renderCampoCli(c, prev, cli)).join('')}</div>
    </div>`;
  }

  function renderCampoCli(c, prev, cli) {
    const col = c.col;
    let val = prev[col];
    // Auto-relleno con datos del cliente para despachante/producto está OK con prev
    const req = c.obligatorio ? 'required' : '';
    const obl = c.obligatorio ? ' <span style="color:#ef4444">*</span>' : '';
    const nombre = `f${esc(col)}`;

    if (c.tipo === 'textarea') {
      return `<div class="ep08-field" style="grid-column:1/-1"><label>${esc(c.label)}${obl}</label>
        <textarea name="${nombre}" rows="3" ${req}>${esc(val||'')}</textarea></div>`;
    }
    if (c.tipo === 'radio' && c.opciones) {
      return `<div class="ep08-field" style="grid-column:1/-1"><label>${esc(c.label)}${obl}</label>
        <div>${c.opciones.map(o=>`<label style="margin-right:14px;font-size:.86rem;cursor:pointer"><input type="radio" name="${nombre}" value="${esc(o)}" ${val===o?'checked':''} ${req}> ${esc(o)}</label>`).join('')}</div></div>`;
    }
    if (c.tipo === 'checkbox' && c.opciones) {
      const arr = Array.isArray(val) ? val : [];
      return `<div class="ep08-field" style="grid-column:1/-1"><label>${esc(c.label)}${obl}</label>
        <div>${c.opciones.map(o=>`<label style="margin-right:14px;font-size:.86rem;cursor:pointer"><input type="checkbox" name="${nombre}" value="${esc(o)}" ${arr.includes(o)?'checked':''}> ${esc(o)}</label>`).join('')}</div></div>`;
    }
    if (c.tipo === 'checkbox_single') {
      return `<div class="ep08-field" style="grid-column:1/-1"><label style="cursor:pointer"><input type="checkbox" name="${nombre}" ${val===true || val==='1' || val===1?'checked':''}> ${esc(c.label)}${obl}</label></div>`;
    }
    if (c.tipo === 'date') {
      return `<div class="ep08-field"><label>${esc(c.label)}${obl}</label><input type="date" name="${nombre}" value="${esc(val||'')}" ${req}></div>`;
    }
    if (c.tipo === 'number') {
      return `<div class="ep08-field"><label>${esc(c.label)}${obl}</label><input type="number" step="1" name="${nombre}" value="${esc(val??'')}" ${req}></div>`;
    }
    if (c.tipo === 'decimal') {
      return `<div class="ep08-field"><label>${esc(c.label)}${obl}</label><input type="number" step="0.01" name="${nombre}" value="${esc(val??'')}" ${req}></div>`;
    }
    return `<div class="ep08-field"><label>${esc(c.label)}${obl}</label><input type="text" name="${nombre}" value="${esc(val||'')}" ${req}></div>`;
  }

  // ═════════════════════════════════════════════════════════════
  //   Bootstrap
  // ═════════════════════════════════════════════════════════════
  function bootstrap() {
    let tries = 0;
    const iv = setInterval(() => {
      tries++;
      const listo = (typeof esClientePortal === 'function') || tries > 40;
      if (listo) {
        clearInterval(iv);
        if (esCliente()) setTimeout(checkearPendientesCliente, 600);
      }
    }, 250);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bootstrap);
  else bootstrap();

  window.EP08 = {
    abrirAdminEncuesta,
    abrirAdminPr08,
    checkearPendientesCliente,
  };
})();
