/* ═══════════════════════════════════════════════════════════════
   MÓDULO PARADA DE CÁMARAS
   ─────────────────────────────────────────────────────────────
   INSTRUCCIONES DE INTEGRACIÓN:
   1. En MOBILE_MENU_ITEMS reemplazá la línea:
         action: () => showMobileComingSoon('Solicitar Parada de Cámara'),
      por:
         action: () => renderSolicitarParadaCamara(),

   2. En el menuTree (MANTENIMIENTO > children) reemplazá:
         { title: 'PR 80 PARADA DE CAMARAS', url: '...' },
      por:
         { title: 'PR 80 PARADA DE CAMARAS', action: 'paradaCamaras' },

   3. En el switch de renderNode (o en getCustomTileHTML / handleAction)
      donde se evalúan las acciones del menú PC, agregar:
         case 'paradaCamaras': renderParadaCamarasPC(); break;

   4. Pegar este bloque completo al final de app.js (antes del cierre).
   ═══════════════════════════════════════════════════════════════ */


/* ── SOLICITAR PARADA — Vista Móvil ────────────────────────── */
async function renderSolicitarParadaCamara() {
  historyStack.push({ __mobileRoot: true });

  // Mostrar skeleton
  menuGrid.className = 'menu-grid';
  menuGrid.innerHTML = `
    <div style="display:flex;flex-direction:column;align-items:center;gap:14px;padding:24px;color:rgba(255,255,255,.6)">
      <div style="font-size:2rem">🛑</div>
      <div style="font-weight:700;font-size:1rem;color:#fff">SOLICITAR PARADA DE CÁMARA</div>
      <div style="font-size:.8rem;opacity:.6">Cargando cámaras...</div>
    </div>`;
  syncBackBtn();

  let camaras = [];
  try {
    const res  = await fetch(`${API_EQUIPOS_URL}?action=parcamara_camaras`, { credentials: 'same-origin' });
    const data = await res.json();
    camaras = Array.isArray(data) ? data : [];
  } catch(e) {
    menuGrid.innerHTML = `<div style="padding:24px;color:#f87171;text-align:center">Error al cargar cámaras.<br><small>${e.message}</small></div>`;
    return;
  }

  if (!camaras.length) {
    menuGrid.innerHTML = `<div style="padding:24px;color:rgba(255,255,255,.5);text-align:center">No hay cámaras disponibles.</div>`;
    return;
  }

  // Helpers de color
  const colorMap = {
    green:  { bg: '#14532d', border: '#22c55e', dot: '#4ade80', label: 'En marcha' },
    yellow: { bg: '#713f12', border: '#f59e0b', dot: '#fbbf24', label: 'En deshielo' },
    red:    { bg: '#7f1d1d', border: '#ef4444', dot: '#f87171', label: 'Sin inyección' },
  };

  const buildCard = (cam) => {
    const c = colorMap[cam.btn_color] || colorMap.green;
    const tempStr = cam.temp_actual != null ? `${parseFloat(cam.temp_actual).toFixed(1)} °C` : '—';
    return `
      <button
        class="parcam-btn"
        data-camara-id="${cam.CamaraID}"
        data-camara-num="${cam.CamaraNumero}"
        data-estado-temp="${cam.temp_estado ?? ''}"
        style="
          display:flex;align-items:center;gap:14px;
          width:100%;padding:16px 18px;margin-bottom:10px;
          background:${c.bg};border:2px solid ${c.border};
          border-radius:14px;cursor:pointer;text-align:left;
          color:#fff;font-size:.95rem;font-weight:600;
          transition:filter .15s;
        "
        onmousedown="this.style.filter='brightness(1.2)'"
        onmouseup="this.style.filter=''"
        ontouchstart="this.style.filter='brightness(1.2)'"
        ontouchend="this.style.filter=''"
      >
        <span style="font-size:1.6rem;line-height:1">❄️</span>
        <div style="flex:1;min-width:0">
          <div>CÁMARA ${cam.CamaraNumero}</div>
          <div style="font-size:.75rem;font-weight:400;opacity:.8;margin-top:2px">
            Temp: ${tempStr} &nbsp;·&nbsp; Estado: ${cam.temp_estado ?? '—'}
          </div>
        </div>
        <span style="
          display:inline-block;width:12px;height:12px;
          border-radius:50%;background:${c.dot};flex-shrink:0;
          box-shadow:0 0 6px ${c.dot};
        "></span>
      </button>`;
  };

  menuGrid.innerHTML = `
    <div style="padding:18px 14px;max-width:480px;margin:0 auto;width:100%;box-sizing:border-box">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px">
        <span style="font-size:1.5rem">🛑</span>
        <div>
          <div style="font-weight:700;font-size:1rem;color:#fff">Solicitar Parada de Cámara</div>
          <div style="font-size:.75rem;color:rgba(255,255,255,.5)">Seleccioná la cámara que querés detener</div>
        </div>
      </div>
      <div style="margin-bottom:14px;display:flex;gap:10px;font-size:.72rem;flex-wrap:wrap">
        ${Object.values(colorMap).map(c=>`
          <span style="display:flex;align-items:center;gap:5px;color:rgba(255,255,255,.65)">
            <span style="width:9px;height:9px;border-radius:50%;background:${c.dot};display:inline-block"></span>
            ${c.label}
          </span>`).join('')}
      </div>
      <div id="parcam-list">
        ${camaras.map(buildCard).join('')}
      </div>
    </div>`;

  // Eventos de los botones
  document.querySelectorAll('.parcam-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const camaraId  = parseInt(btn.dataset.camaraId);
      const camaraNum = parseInt(btn.dataset.camaraNum);
      const estado    = btn.dataset.estadoTemp;
      confirmarSolicitudParada(camaraId, camaraNum, estado);
    });
  });
}


/* ── Confirmación antes de enviar la solicitud ─────────────── */
function confirmarSolicitudParada(camaraId, camaraNum, estadoTemp) {
  // Modal de confirmación simple inline
  const overlay = document.createElement('div');
  overlay.style.cssText = `
    position:fixed;inset:0;z-index:9999;
    background:rgba(0,0,0,.75);backdrop-filter:blur(4px);
    display:flex;align-items:center;justify-content:center;padding:20px;
  `;
  overlay.innerHTML = `
    <div style="
      background:#1e293b;border:1px solid rgba(239,68,68,.4);border-radius:18px;
      padding:28px 24px;max-width:360px;width:100%;text-align:center;
    ">
      <div style="font-size:2.5rem;margin-bottom:8px">🛑</div>
      <div style="font-weight:700;font-size:1.05rem;color:#fff;margin-bottom:6px">
        ¿Solicitár parada?
      </div>
      <div style="font-size:.85rem;color:rgba(255,255,255,.65);margin-bottom:20px">
        CÁMARA ${camaraNum}<br>
        <span style="color:rgba(255,255,255,.4);font-size:.78rem">Estado actual: ${estadoTemp || '—'}</span>
      </div>
      <div style="display:flex;gap:10px;justify-content:center">
        <button id="parcam-cancel" style="
          padding:10px 22px;border-radius:10px;border:1px solid rgba(255,255,255,.2);
          background:transparent;color:rgba(255,255,255,.6);font-size:.85rem;cursor:pointer;font-weight:600
        ">Cancelar</button>
        <button id="parcam-confirm" style="
          padding:10px 22px;border-radius:10px;border:none;
          background:#dc2626;color:#fff;font-size:.85rem;cursor:pointer;font-weight:700
        ">Solicitar</button>
      </div>
      <div id="parcam-msg" style="margin-top:12px;font-size:.8rem;min-height:20px"></div>
    </div>`;
  document.body.appendChild(overlay);

  overlay.querySelector('#parcam-cancel').onclick = () => overlay.remove();

  overlay.querySelector('#parcam-confirm').onclick = async () => {
    const btn = overlay.querySelector('#parcam-confirm');
    const msg = overlay.querySelector('#parcam-msg');
    btn.disabled = true;
    btn.textContent = 'Enviando...';
    msg.style.color = 'rgba(255,255,255,.5)';
    msg.textContent = '';

    try {
      const res  = await fetch(`${API_EQUIPOS_URL}?action=parcamara_solicitar`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ camara_id: camaraId, camara_numero: camaraNum, estado_temp: estadoTemp }),
      });
      const data = await res.json();
      if (data.ok) {
        msg.style.color = '#4ade80';
        msg.textContent = '✓ Solicitud enviada correctamente';
        btn.textContent = '✓ Enviado';
        btn.style.background = '#15803d';
        setTimeout(() => overlay.remove(), 1800);
      } else {
        throw new Error(data.error || 'Error desconocido');
      }
    } catch(e) {
      btn.disabled = false;
      btn.textContent = 'Reintentar';
      msg.style.color = '#f87171';
      msg.textContent = '✗ ' + e.message;
    }
  };
}


/* ── PANEL PC — PR 80 PARADA DE CÁMARAS ────────────────────── */
async function renderParadaCamarasPC() {
  setHeader('PR 80 — PARADA DE CÁMARAS', 'Gestión de solicitudes');
  setExpandedMode(true);
  showMetaPanel(false);

  menuGrid.innerHTML = `
    <div style="padding:24px;color:rgba(255,255,255,.5);text-align:center">Cargando solicitudes...</div>`;

  let lista = [];
  let puedeResponder = false;

  try {
    const res  = await fetch(`${API_EQUIPOS_URL}?action=parcamara_lista&limite=100`, { credentials: 'same-origin' });
    const data = await res.json();
    lista = data.solicitudes || [];
    puedeResponder = !!data.puede_responder;
  } catch(e) {
    menuGrid.innerHTML = `<div style="padding:24px;color:#f87171">Error al cargar: ${e.message}</div>`;
    return;
  }

  // Badget de estado
  const badgeEstado = (est) => {
    const cfg = {
      'Pendiente':            { bg:'#1e3a5f', border:'#3b82f6', color:'#93c5fd', icon:'⏳' },
      'Parada Informada':     { bg:'#14532d', border:'#22c55e', color:'#4ade80', icon:'✅' },
      'No Parada Informada':  { bg:'#7f1d1d', border:'#ef4444', color:'#f87171', icon:'❌' },
    };
    const c = cfg[est] || cfg['Pendiente'];
    return `<span style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;
      background:${c.bg};border:1px solid ${c.border};color:${c.color};font-size:.72rem;font-weight:700">
      ${c.icon} ${est}</span>`;
  };

  // Color del estado de temperatura actual
  const dotTemp = (estadoActual) => {
    if (!estadoActual) return '';
    const e = estadoActual.toLowerCase();
    const color = (e === 'sin inyección' || e === 'sin inyeccion')
      ? '#ef4444'
      : (e.includes('deshelo') || e.includes('desheló') || e.includes('descongelado'))
        ? '#f59e0b'
        : '#22c55e';
    return `<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${color};margin-right:4px;vertical-align:middle"></span>`;
  };

  const formatFecha = (dt) => {
    if (!dt) return '—';
    const d = new Date(dt);
    return d.toLocaleDateString('es-AR', { day:'2-digit', month:'2-digit', year:'numeric' })
      + ' ' + d.toLocaleTimeString('es-AR', { hour:'2-digit', minute:'2-digit' });
  };

  // Filas de la tabla
  const filas = lista.map(s => {
    const btnResponder = puedeResponder && s.estado_solicitud === 'Pendiente'
      ? `<div style="display:flex;gap:6px;margin-top:4px">
           <button
             onclick="informarParadaCamara(${s.id}, 'Parada Informada')"
             style="padding:6px 12px;border-radius:8px;border:none;background:#15803d;color:#fff;font-size:.75rem;font-weight:700;cursor:pointer"
           >✅ Informar Parada</button>
           <button
             onclick="informarParadaCamara(${s.id}, 'No Parada Informada')"
             style="padding:6px 12px;border-radius:8px;border:none;background:#991b1b;color:#fff;font-size:.75rem;font-weight:700;cursor:pointer"
           >❌ Informar No Parada</button>
         </div>`
      : '';

    const respuestaInfo = s.estado_solicitud !== 'Pendiente'
      ? `<div style="font-size:.72rem;color:rgba(255,255,255,.45);margin-top:2px">
           ${s.respondio ? `👤 ${s.respondio}` : ''} ${s.fecha_respuesta ? '· ' + formatFecha(s.fecha_respuesta) : ''}
           ${s.observaciones ? `<br><em>${s.observaciones}</em>` : ''}
         </div>`
      : '';

    return `
      <tr data-id="${s.id}" style="border-bottom:1px solid rgba(255,255,255,.06);vertical-align:top">
        <td style="padding:12px 10px;font-size:.85rem;font-weight:700;white-space:nowrap;color:#93c5fd">
          Cámara ${s.camara_numero}
        </td>
        <td style="padding:12px 10px;font-size:.78rem;color:rgba(255,255,255,.7)">
          ${formatFecha(s.fecha_solicitud)}
        </td>
        <td style="padding:12px 10px;font-size:.8rem;color:rgba(255,255,255,.85)">
          ${s.solicitante}
        </td>
        <td style="padding:12px 10px;font-size:.78rem;color:rgba(255,255,255,.65)">
          ${s.estado_temp || '—'}
        </td>
        <td style="padding:12px 10px;font-size:.78rem">
          ${dotTemp(s.temp_estado_actual)}${s.temp_estado_actual || '—'}
          ${s.temp_actual != null ? `<br><span style="font-size:.72rem;opacity:.5">${parseFloat(s.temp_actual).toFixed(1)}°C</span>` : ''}
        </td>
        <td style="padding:12px 10px">
          ${badgeEstado(s.estado_solicitud)}
          ${respuestaInfo}
        </td>
        <td style="padding:12px 10px">
          ${btnResponder}
        </td>
      </tr>`;
  }).join('');

  menuGrid.innerHTML = `
    <div style="padding:20px;max-width:1200px;margin:0 auto;width:100%;box-sizing:border-box">

      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
        <div>
          <div style="font-weight:700;font-size:1.1rem;color:#fff">🛑 PR 80 — Parada de Cámaras</div>
          <div style="font-size:.78rem;color:rgba(255,255,255,.45);margin-top:2px">
            ${lista.length} solicitud${lista.length !== 1 ? 'es' : ''}
            ${puedeResponder ? ' · <span style="color:#4ade80">Podés informar resultados</span>' : ''}
          </div>
        </div>
        <button
          onclick="renderParadaCamarasPC()"
          style="padding:8px 16px;border-radius:10px;border:1px solid rgba(255,255,255,.2);
            background:rgba(255,255,255,.07);color:rgba(255,255,255,.7);
            font-size:.8rem;cursor:pointer;font-weight:600"
        >↻ Actualizar</button>
      </div>

      ${lista.length === 0
        ? `<div style="text-align:center;padding:48px;color:rgba(255,255,255,.3);font-size:.9rem">
             Sin solicitudes registradas
           </div>`
        : `<div style="overflow-x:auto;border-radius:14px;border:1px solid rgba(255,255,255,.1)">
             <table style="width:100%;border-collapse:collapse;font-size:.82rem">
               <thead>
                 <tr style="background:rgba(255,255,255,.06);text-align:left">
                   <th style="padding:12px 10px;color:rgba(255,255,255,.5);font-weight:600;font-size:.75rem;white-space:nowrap">Cámara</th>
                   <th style="padding:12px 10px;color:rgba(255,255,255,.5);font-weight:600;font-size:.75rem;white-space:nowrap">Fecha Solicitud</th>
                   <th style="padding:12px 10px;color:rgba(255,255,255,.5);font-weight:600;font-size:.75rem">Solicitó</th>
                   <th style="padding:12px 10px;color:rgba(255,255,255,.5);font-weight:600;font-size:.75rem">Estado al pedir</th>
                   <th style="padding:12px 10px;color:rgba(255,255,255,.5);font-weight:600;font-size:.75rem">Estado actual</th>
                   <th style="padding:12px 10px;color:rgba(255,255,255,.5);font-weight:600;font-size:.75rem">Resultado</th>
                   ${puedeResponder ? `<th style="padding:12px 10px;color:rgba(255,255,255,.5);font-weight:600;font-size:.75rem">Acciones</th>` : ''}
                 </tr>
               </thead>
               <tbody style="color:rgba(255,255,255,.85)">
                 ${filas}
               </tbody>
             </table>
           </div>`
      }
    </div>`;
}


/* ── Informar resultado (desde panel PC, RolID=9) ───────────── */
async function informarParadaCamara(solicitudId, estadoRespuesta) {
  // Pedir observaciones opcionales
  const obs = window.prompt(
    `Informar: "${estadoRespuesta}"\n\nObservaciones (opcional):`,
    ''
  );
  if (obs === null) return; // canceló

  try {
    const res  = await fetch(`${API_EQUIPOS_URL}?action=parcamara_responder`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        solicitud_id:    solicitudId,
        estado_solicitud: estadoRespuesta,
        observaciones:   obs.trim(),
      }),
    });
    const data = await res.json();
    if (data.ok) {
      // Notificación visual breve y refrescar tabla
      const row = document.querySelector(`tr[data-id="${solicitudId}"]`);
      if (row) {
        row.style.transition = 'opacity .4s';
        row.style.opacity = '.4';
      }
      // Refresca el panel completo tras breve pausa
      setTimeout(() => renderParadaCamarasPC(), 600);
    } else {
      alert('Error al informar: ' + (data.error || 'Desconocido'));
    }
  } catch(e) {
    alert('Error de conexión: ' + e.message);
  }
}


/*
 * ──────────────────────────────────────────────────────────────
 *  INTEGRACIÓN EN EL MENÚ PC (handleAction / renderNode)
 *  ─────────────────────────────────────────────────────────────
 *  En el bloque donde se procesa item.action en renderNode,
 *  agregar este case (buscá donde se manejan acciones como
 *  'panolComingSoon' u otras):
 *
 *    if (node.action === 'paradaCamaras') {
 *      historyStack.push(parent_node_or_menuTree);
 *      renderParadaCamarasPC();
 *      return;
 *    }
 *
 *  O si usás un switch centralizado:
 *    case 'paradaCamaras': renderParadaCamarasPC(); break;
 * ──────────────────────────────────────────────────────────────
 */
