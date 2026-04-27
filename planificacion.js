// ════════════════════════════════════════════════════════════════
//  MÓDULO PLANIFICACIÓN SEMANAL
//  Vista 1: renderPlanificacion()       — Operaciones (gestión)
//  Vista 2: renderPlanificacionCliente() — Cliente (solicitud)
// ════════════════════════════════════════════════════════════════

const PLAN_API = '/api_planificacion.php';

const FRANJAS = [
  '00:00 – 03:00','03:00 – 06:00','06:00 – 09:00','03:00 – 12:00',
  '12:00 – 15:00','15:00 – 18:00','18:00 – 21:00','21:00 – 24:00',
];
const FRANJAS_LABEL = [
  '🌙 00–03','🌙 03–06','🌅 06–09','☀️ 09–12',
  '☀️ 12–15','🌆 15–18','🌆 18–21','🌙 21–24',
];
// Tipos de transporte cargados dinámicamente desde API
let TIPOS_VEHICULO  = [];
let TIPOS_ACOPLADO  = [];
let TIPOS_TRATAMIENTO = [];
const ESTADO_COLOR = {
  pendiente: '#F59E0B', aceptado: '#10B981',
  rechazado: '#EF4444', asignado: '#3B82F6',
};

async function planAPI(action, params = {}) {
  const qs = new URLSearchParams({ action, ...params }).toString();
  const r  = await fetch(`${PLAN_API}?${qs}`, { credentials: 'same-origin' });
  const d  = await r.json();
  if (d.error) throw new Error(d.error);
  return d;
}
async function planAPIPost(action, body = {}) {
  const r = await fetch(`${PLAN_API}?action=${action}`, {
    method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  const d = await r.json();
  if (d.error) throw new Error(d.error);
  return d;
}

// ── Próximo cierre: viernes 10:00 ────────────────────────────
function proximoCierre() {
  const now = new Date();
  const cierre = new Date(now);
  // Avanzar al viernes de esta semana
  const dow = cierre.getDay(); // 0=dom,1=lun,...,5=vie
  const diasHastaVie = (5 - dow + 7) % 7 || 7;
  cierre.setDate(cierre.getDate() + diasHastaVie);
  cierre.setHours(10, 0, 0, 0);
  // Si ya pasó el viernes de esta semana, ir al siguiente
  if (cierre <= now) cierre.setDate(cierre.getDate() + 7);
  return cierre;
}

function fmtCuenta(ms) {
  if (ms <= 0) return '⏰ CIERRE ALCANZADO';
  const d = Math.floor(ms / 86400000);
  const h = Math.floor((ms % 86400000) / 3600000);
  const m = Math.floor((ms % 3600000) / 60000);
  const s = Math.floor((ms % 60000) / 1000);
  if (d > 0) return `${d}d ${h}h ${String(m).padStart(2,'0')}m`;
  return `${h}h ${String(m).padStart(2,'0')}m ${String(s).padStart(2,'0')}s`;
}

// ════════════════════════════════════════════════════════════
//  VISTA 1: PLANIFICACIÓN (OPERACIONES)
// ════════════════════════════════════════════════════════════
async function renderPlanificacion() {
  setHeader('PLANIFICACIÓN', 'Gestión semanal de operaciones');
  setExpandedMode(false);
  showMetaPanel(true);
  menuGrid.className = '';
  menuGrid.innerHTML = '<div class="plan-loading"><div class="spin"></div> Cargando semanas...</div>';
  syncBackBtn();

  let semanas = [], semanaActiva = null, turnos = [];

  const wrap = document.createElement('div');
  wrap.className = 'plan-wrap';
  menuGrid.innerHTML = '';
  menuGrid.appendChild(wrap);

  const renderSemanas = () => {
    const pills = semanas.map(s => {
      const ini  = s.fecha_inicio.slice(5).replace('-','/');
      const fin  = s.fecha_fin.slice(5).replace('-','/');
      const actv = semanaActiva?.semana_id === s.semana_id ? 'active' : '';
      const est  = s.estado === 'abierta' ? '🟢' : s.estado === 'cerrada' ? '🔴' : '🟡';
      return `<button class="plan-semana-pill ${actv}" data-sid="${s.semana_id}">
        ${est} ${ini} – ${fin}
        <span class="plan-pill-badge">${s.total_turnos || 0}</span>
      </button>`;
    }).join('');

    wrap.innerHTML = `
      <div class="plan-header-bar">
        <div class="plan-semanas-scroll">${pills}</div>
        <button class="plan-nueva-btn" id="planNuevo">＋ Nuevo turno</button>
        <button class="plan-refresh-btn" id="planRefresh">↻</button>
      </div>

      ${semanaActiva ? `
      <div class="plan-semana-info">
        <span class="plan-semana-label">
          Semana ${semanaActiva.fecha_inicio} → ${semanaActiva.fecha_fin}
        </span>
        <span class="plan-cierre-badge ${semanaActiva.estado !== 'abierta' ? 'cerrada' : ''}">
          ${semanaActiva.estado === 'abierta' ? '⏳ Cierre: ' + semanaActiva.fecha_cierre.slice(0,16) : '🔒 Cerrada'}
        </span>
        <button class="plan-cierre-edit-btn" id="planEditarCierre" title="Modificar fecha límite">✏</button>
        <span class="plan-stats">
          <span class="stat-chip" style="color:#F59E0B">⏳ ${semanaActiva.pendientes||0} pend.</span>
          <span class="stat-chip" style="color:#10B981">✓ ${semanaActiva.aceptados||0} acept.</span>
          <span class="stat-chip" style="color:#EF4444">✗ ${semanaActiva.rechazados||0} rech.</span>
          <span class="stat-chip" style="color:#3B82F6">📦 ${semanaActiva.asignados||0} asig.</span>
        </span>
      </div>

      <div class="plan-grid-wrap" id="planGrid">${renderGrid()}</div>
      ` : '<div class="plan-empty">Seleccioná una semana</div>'}
    `;

    // Eventos
    wrap.querySelectorAll('.plan-semana-pill').forEach(btn => {
      btn.onclick = () => {
        semanaActiva = semanas.find(s => s.semana_id == btn.dataset.sid);
        loadTurnos();
      };
    });
    wrap.querySelector('#planRefresh')?.addEventListener('click', loadTurnos);
    wrap.querySelector('#planNuevo')?.addEventListener('click', () => modalNuevoTurno());
    wrap.querySelector('#planEditarCierre')?.addEventListener('click', () => modalEditarCierre());

    // Auto-scroll al pill de la semana activa
    requestAnimationFrame(() => {
      const activePill = wrap.querySelector('.plan-semana-pill.active');
      if (activePill) activePill.scrollIntoView({ behavior:'smooth', inline:'center', block:'nearest' });
    });

    // Acciones de turnos
    wrap.querySelectorAll('[data-turno-id]').forEach(btn => {
      const tid   = parseInt(btn.dataset.turnoId);
      const accion = btn.dataset.accion;
      if (accion === 'gestionar') btn.onclick = () => modalGestionar(tid, turnos.find(t=>t.turno_id==tid));
      if (accion === 'docs')      btn.onclick = () => modalDocs(tid);
    });
  };

  const renderGrid = () => {
    if (!turnos.length) return '<div class="plan-empty">No hay turnos para esta semana</div>';

    // Agrupar por fecha
    const porFecha = {};
    turnos.forEach(t => {
      if (!porFecha[t.fecha]) porFecha[t.fecha] = [];
      porFecha[t.fecha].push(t);
    });

    const hoyStr = new Date().toISOString().slice(0,10);
    return Object.entries(porFecha).map(([fecha, ts]) => {
      const esPasado = fecha < hoyStr;
      const dia = new Date(fecha + 'T12:00:00').toLocaleDateString('es-AR', {weekday:'long',day:'2-digit',month:'2-digit'});
      const filas = ts.map(t => {
        const franja = t.franja_modificada !== null ? t.franja_modificada : t.franja;
        const franjaOrig = t.franja_modificada !== null ? `<s class="text-muted">${FRANJAS_LABEL[t.franja]}</s>` : '';
        const estado = t.estado;
        const color  = ESTADO_COLOR[estado] || '#888';
        const fueraProg = t.fuera_programa == 1 ? '<span class="plan-fp-badge">FUERA PROG.</span>' : '';
        const asignado  = t.carga_numero ? `<span class="plan-carga-badge" style="cursor:pointer" onclick="planIrACarga(${t.carga_numero})" title="Ver carga #${t.carga_numero}">🚛 ${t.carga_numero} ↗</span>` : '';
        const docs = t.cant_docs > 0 ? `<span class="plan-doc-count">📎${t.cant_docs}</span>` : '';

        return `<div class="plan-turno-row" style="border-left-color:${color}">
          <div class="pt-franja">
            ${franjaOrig}
            <strong>${FRANJAS_LABEL[franja]}</strong>
          </div>
          <div class="pt-tipo">
            <span class="pt-badge ${t.tipo==='F'?'fiscal':'nac'}">${t.tipo==='F'?'FISCAL':'NAC'}</span>
            <span class="pt-badge ${t.entrada==1?'entrada':'salida'}">${t.entrada==1?'⬇ ENT':'⬆ SAL'}</span>
            ${t.requiere_acondicionamiento==1?'<span class="pt-badge acond">❄ ACOND</span>':''}
          </div>
          <div class="pt-cliente"><strong>${t.cliente_nombre}</strong></div>
          <div class="pt-transp">${t.tipo_transporte||'—'}</div>
          <div class="pt-estado">
            <span class="plan-estado-chip" style="background:${color}22;color:${color};border-color:${color}55">
              ${estado}
            </span>
            ${fueraProg}${asignado}${docs}
          </div>
          <div class="pt-notas">${t.notas_cliente ? `💬 ${t.notas_cliente}` : ''}</div>
          <div class="pt-actions">
            <button class="pt-btn" data-turno-id="${t.turno_id}" data-accion="gestionar" title="Gestionar">✏</button>
            <button class="pt-btn pt-btn-doc" data-turno-id="${t.turno_id}" data-accion="docs" title="Documentos">📎</button>
          </div>
        </div>`;
      }).join('');

      return `<div class="plan-dia-block${esPasado?' plan-dia-pasado':''}">
        <div class="plan-dia-header">${dia}${esPasado?' <span style="font-size:.7rem;opacity:.5">(pasado)</span>':''} <span class="plan-dia-count">${ts.length} turno${ts.length!==1?'s':''}</span></div>
        ${filas}
      </div>`;
    }).join('');
  };

  // ── Modal: editar fecha límite de cierre de la semana ─────────
  const modalEditarCierre = () => {
    if (!semanaActiva) return;

    // Fecha actual de cierre formateada para datetime-local input
    const cierreActual = semanaActiva.fecha_cierre
      ? semanaActiva.fecha_cierre.slice(0, 16)   // 'YYYY-MM-DD HH:MM'
      : '';

    // Calcular default original (viernes 10:00 de semana anterior) para poder restaurar
    const lunes          = new Date(semanaActiva.fecha_inicio + 'T12:00:00');
    const viernesDefault = new Date(lunes);
    viernesDefault.setDate(lunes.getDate() - 3);
    const defaultISO = viernesDefault.toISOString().slice(0,10) + 'T10:00';

    const modal = document.createElement('div');
    modal.className = 'plan-modal-overlay';
    modal.innerHTML = `
      <div class="plan-modal" style="max-width:420px">
        <div class="plan-modal-header">
          <span>📅 Fecha límite de planificación</span>
          <button class="plan-modal-close" id="ecClose">✕</button>
        </div>
        <div class="plan-modal-body" style="padding:20px">
          <div style="margin-bottom:14px;font-size:.82rem;color:rgba(255,255,255,.55);line-height:1.5">
            Semana <strong style="color:#fff">${semanaActiva.fecha_inicio} → ${semanaActiva.fecha_fin}</strong><br>
            Por defecto: viernes anterior a las 10:00 hs.<br>
            Modificar extiende o acorta el plazo para que los clientes soliciten turnos.
          </div>

          <label style="display:block;font-size:.75rem;color:rgba(255,255,255,.45);margin-bottom:6px;text-transform:uppercase;letter-spacing:.06em">
            Nueva fecha y hora de cierre
          </label>
          <input type="datetime-local" id="ecFecha" value="${cierreActual}"
            style="width:100%;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.15);
                   border-radius:8px;color:#fff;font-size:.9rem;padding:9px 12px;
                   outline:none;box-sizing:border-box;color-scheme:dark">

          <div style="display:flex;align-items:center;gap:8px;margin-top:10px">
            <button id="ecRestaurar"
              style="font-size:.72rem;padding:4px 10px;background:rgba(255,255,255,.06);
                     border:1px solid rgba(255,255,255,.12);border-radius:6px;color:rgba(255,255,255,.5);cursor:pointer">
              ↺ Restaurar default
            </button>
            <span style="font-size:.68rem;color:rgba(255,255,255,.3)">${viernesDefault.toLocaleDateString('es-AR',{weekday:'short',day:'2-digit',month:'2-digit'})} 10:00</span>
          </div>

          <div id="ecError" style="color:#f87171;font-size:.78rem;margin-top:8px;min-height:18px"></div>
        </div>
        <div class="plan-modal-footer">
          <button class="ct-acc-btn" id="ecCancelar">Cancelar</button>
          <button class="ct-acc-btn ct-acc-btn-primary" id="ecGuardar">Guardar</button>
        </div>
      </div>`;

    document.body.appendChild(modal);
    const close = () => modal.remove();
    modal.querySelector('#ecClose').onclick    = close;
    modal.querySelector('#ecCancelar').onclick = close;

    modal.querySelector('#ecRestaurar').onclick = () => {
      modal.querySelector('#ecFecha').value = defaultISO;
    };

    modal.querySelector('#ecGuardar').onclick = async () => {
      const errEl     = modal.querySelector('#ecError');
      const nuevaFecha = modal.querySelector('#ecFecha').value;
      if (!nuevaFecha) { errEl.textContent = 'Ingresá una fecha válida.'; return; }

      // Convertir datetime-local a 'YYYY-MM-DD HH:MM:SS'
      const fechaDB = nuevaFecha.replace('T', ' ') + ':00';

      try {
        const r = await fetch(`${PLAN_API}?action=semana_editar_cierre`, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            semana_id:    semanaActiva.semana_id,
            fecha_cierre: fechaDB,
          }),
        });
        const d = await r.json();
        if (d.error) { errEl.textContent = d.error; return; }

        // Actualizar estado local sin recargar todo
        semanaActiva.fecha_cierre = fechaDB;
        semanaActiva.estado       = d.estado;
        // Actualizar en el array también
        const idx = semanas.findIndex(s => s.semana_id === semanaActiva.semana_id);
        if (idx !== -1) { semanas[idx].fecha_cierre = fechaDB; semanas[idx].estado = d.estado; }

        close();
        showToast('✅ Fecha de cierre actualizada');
        renderSemanas();
      } catch(e) { errEl.textContent = 'Error: ' + e.message; }
    };
  };

  const loadTurnos = async () => {
    if (!semanaActiva) return;
    try {
      const d = await planAPI('turnos_lista', { semana_id: semanaActiva.semana_id });
      turnos = d.turnos || [];
      renderSemanas();
    } catch(e) { showToast('Error: ' + e.message, 'error'); }
  };

  const modalNuevoTurno = async () => {
    // Cargar catálogos
    let clientes = [], tipos = { vehiculos: [], acoplados: [] };
    try {
      const [cd, td] = await Promise.all([planAPI('mis_clientes'), planAPI('tipos_transporte')]);
      clientes = cd.clientes || [];
      tipos    = td;
    } catch(e) { showToast('Error cargando catálogos', 'error'); return; }

    if (!semanaActiva) { showToast('Seleccioná una semana primero', 'error'); return; }

    const dias = [];
    let d = new Date(semanaActiva.fecha_inicio + 'T12:00:00');
    for (let i = 0; i < 7; i++) {
      dias.push({ value: d.toISOString().split('T')[0], label: d.toLocaleDateString('es-AR',{weekday:'short',day:'2-digit',month:'2-digit'}) });
      d.setDate(d.getDate()+1);
    }

    const modal = document.createElement('div');
    modal.className = 'plan-modal-overlay';
    modal.innerHTML = `
      <div class="plan-modal">
        <div class="plan-modal-header">
          <span>＋ Nuevo turno — semana ${semanaActiva.fecha_inicio.slice(5).replace('-','/')}</span>
          <button class="plan-modal-close" id="ntClose">✕</button>
        </div>
        <div class="plan-modal-body">
          <div class="plan-form-grid" style="gap:10px">
            <div class="cf-field">
              <label class="cf-lbl">Cliente *</label>
              <select class="cf-select" id="ntCliente">
                <option value="">— Seleccioná —</option>
                ${clientes.map(c => `<option value="${c.id}">${c.nombre}</option>`).join('')}
              </select>
            </div>
            <div class="cf-field">
              <label class="cf-lbl">Fecha *</label>
              <select class="cf-select" id="ntFecha">
                ${dias.map(d => `<option value="${d.value}">${d.label}</option>`).join('')}
              </select>
            </div>
            <div class="cf-field">
              <label class="cf-lbl">Franja horaria *</label>
              <select class="cf-select" id="ntFranja">
                ${FRANJAS_LABEL.map((f,i) => `<option value="${i}">${f}</option>`).join('')}
              </select>
            </div>
            <div class="cf-field">
              <label class="cf-lbl">Movimiento</label>
              <div class="plan-toggle-group" id="ntEntrada">
                <button class="plan-toggle active" data-val="1">⬇ Entrada</button>
                <button class="plan-toggle" data-val="0">⬆ Salida</button>
              </div>
            </div>
            <div class="cf-field">
              <label class="cf-lbl">Depósito</label>
              <div class="plan-toggle-group" id="ntTipo">
                <button class="plan-toggle active" data-val="N">Nacional</button>
                <button class="plan-toggle" data-val="F">Fiscal</button>
              </div>
            </div>
            <div class="cf-field">
              <label class="cf-lbl">Tipo de vehículo</label>
              <select class="cf-select" id="ntVehiculo">
                <option value="">— Seleccioná —</option>
                ${tipos.vehiculos.map(t => `<option value="${t.id}">${t.nombre}</option>`).join('')}
              </select>
            </div>
            <div class="cf-field">
              <label class="cf-lbl">Tipo de acoplado</label>
              <select class="cf-select" id="ntAcoplado">
                <option value="">— Sin acoplado —</option>
                ${tipos.acoplados.map(t => `<option value="${t.id}">${t.nombre}</option>`).join('')}
              </select>
            </div>
            ${tipos.tratamientos?.length ? `
            <div class="cf-field">
              <label class="cf-lbl">Tratamiento</label>
              <select class="cf-select" id="ntTratamiento">
                <option value="">— Sin especificar —</option>
                ${tipos.tratamientos.map(t => `<option value="${t.id}">${t.nombre}</option>`).join('')}
              </select>
            </div>` : ''}
            <div class="cf-field">
              <label class="cf-lbl">Estado</label>
              <select class="cf-select" id="ntEstado">
                <option value="aceptado">Aceptado</option>
                <option value="pendiente">Pendiente</option>
              </select>
            </div>
            <div class="cf-field cf-full">
              <label class="cf-lbl">Notas</label>
              <input class="cf-input" type="text" id="ntNotas" placeholder="Observaciones...">
            </div>
          </div>
        </div>
        <div class="plan-modal-footer">
          <button class="ct-acc-btn" id="ntCancel">Cancelar</button>
          <button class="ct-acc-btn ct-acc-ver" id="ntGuardar">＋ Crear turno</button>
        </div>
      </div>`;
    document.body.appendChild(modal);

    // Toggle groups
    modal.querySelectorAll('.plan-toggle-group').forEach(grp => {
      grp.querySelectorAll('.plan-toggle').forEach(btn => {
        btn.onclick = () => {
          grp.querySelectorAll('.plan-toggle').forEach(b => b.classList.remove('active'));
          btn.classList.add('active');
        };
      });
    });

    const close = () => modal.remove();
    modal.querySelector('#ntClose').onclick  =
    modal.querySelector('#ntCancel').onclick = close;

    modal.querySelector('#ntGuardar').onclick = async () => {
      const clienteId = modal.querySelector('#ntCliente').value;
      const fecha     = modal.querySelector('#ntFecha').value;
      if (!clienteId) { showToast('Seleccioná un cliente', 'error'); return; }

      const btn = modal.querySelector('#ntGuardar');
      btn.disabled = true; btn.textContent = 'Creando...';
      try {
        const d = await planAPIPost('turno_crear', {
          semana_id:    semanaActiva.semana_id,
          cliente_id:   clienteId,
          fecha,
          franja:       modal.querySelector('#ntFranja').value,
          entrada:      modal.querySelector('#ntEntrada .plan-toggle.active').dataset.val,
          tipo:         modal.querySelector('#ntTipo .plan-toggle.active').dataset.val,
          tipo_vehiculo_id: modal.querySelector('#ntVehiculo')?.value  || null,
          tipo_acoplado_id: modal.querySelector('#ntAcoplado')?.value  || null,
          tipo_transporte:  '',
          tratamiento_id:   modal.querySelector('#ntTratamiento')?.value || null,
          notas_cliente: modal.querySelector('#ntNotas').value,
          cantidad: 1,
        });
        // Si operaciones lo crea → aceptarlo automáticamente
        if (d.ids?.[0]) {
          const est = modal.querySelector('#ntEstado').value;
          if (est === 'aceptado') {
            await planAPIPost('turno_gestionar', { turno_id: d.ids[0], estado: 'aceptado', usuario_gestiona_id: null });
          }
        }
        showToast('Turno creado ✓', 'success');
        close();
        loadTurnos();
      } catch(e) { showToast('Error: ' + e.message, 'error'); btn.disabled=false; btn.textContent='＋ Crear turno'; }
    };
  };

  const modalGestionar = (tid, t) => {
    if (!t) return;
    const franja = t.franja_modificada ?? t.franja;
    const color  = ESTADO_COLOR[t.estado] || '#888';

    const modal = document.createElement('div');
    modal.className = 'plan-modal-overlay';
    modal.innerHTML = `
      <div class="plan-modal">
        <div class="plan-modal-header">
          <span>Gestionar turno #${tid}</span>
          <button class="plan-modal-close" id="pmClose">✕</button>
        </div>
        <div class="plan-modal-body">
          <div class="pm-info-row">
            <strong>${t.cliente_nombre}</strong> ·
            ${t.tipo==='F'?'Fiscal':'Nacional'} ·
            ${t.entrada==1?'Entrada':'Salida'} ·
            ${t.tipo_transporte||'—'}
          </div>
          <div class="pm-info-row text-muted">${t.fecha} · Franja original: ${FRANJAS_LABEL[t.franja]}</div>

          <label class="cf-lbl">Estado</label>
          <select class="cf-select" id="pmEstado">
            ${['pendiente','aceptado','rechazado','asignado'].map(e =>
              `<option value="${e}" ${e===t.estado?'selected':''}>${e}</option>`
            ).join('')}
          </select>

          <label class="cf-lbl" style="margin-top:10px">Ajustar franja horaria</label>
          <select class="cf-select" id="pmFranja">
            ${FRANJAS_LABEL.map((f,i) => `<option value="${i}" ${i===franja?'selected':''}>${f}</option>`).join('')}
          </select>

          <label class="cf-lbl" style="margin-top:10px">Asignar a carga N°</label>
          <input class="cf-input" type="number" id="pmCarga" value="${t.carga_numero||''}" placeholder="N° carga transporte_carga">

          <label class="cf-lbl" style="margin-top:10px">Notas de operaciones</label>
          <textarea class="cf-input" id="pmNotas" rows="3">${t.notas_operaciones||''}</textarea>
        </div>
        <div class="plan-modal-footer">
          <button class="ct-acc-btn" id="pmCancel">Cancelar</button>
          <button class="ct-acc-btn ct-acc-ver" id="pmGuardar">💾 Guardar</button>
        </div>
      </div>`;

    document.body.appendChild(modal);
    modal.querySelector('#pmClose').onclick  =
    modal.querySelector('#pmCancel').onclick = () => modal.remove();
    modal.onclick = e => { if (e.target === modal) modal.remove(); };

    modal.querySelector('#pmGuardar').onclick = async () => {
      try {
        await planAPIPost('turno_gestionar', {
          turno_id:          tid,
          estado:            modal.querySelector('#pmEstado').value,
          franja_modificada: parseInt(modal.querySelector('#pmFranja').value),
          notas_operaciones: modal.querySelector('#pmNotas').value,
          carga_numero:      parseInt(modal.querySelector('#pmCarga').value) || null,
        });
        showToast('Turno actualizado ✓', 'success');
        modal.remove();
        loadTurnos();
      } catch(e) { showToast('Error: ' + e.message, 'error'); }
    };
  };

  const modalDocs = async (tid) => {
    const modal = document.createElement('div');
    modal.className = 'plan-modal-overlay';
    modal.innerHTML = `
      <div class="plan-modal">
        <div class="plan-modal-header">
          <span>📎 Documentos — Turno #${tid}</span>
          <button class="plan-modal-close" id="mdClose">✕</button>
        </div>
        <div class="plan-modal-body" id="mdBody"><div class="spin"></div></div>
        <div class="plan-modal-footer">
          <input type="file" id="mdFile" accept=".pdf,.xls,.xlsx,.doc,.docx,.csv,.jpg,.jpeg,.png,.gif,.bmp,.webp,.txt,.zip,.rar" style="display:none">
          <button class="ct-acc-btn" onclick="document.getElementById('mdFile').click()">📎 Subir archivo</button>
          <button class="ct-acc-btn" id="mdClose2">Cerrar</button>
        </div>
      </div>`;
    document.body.appendChild(modal);
    modal.querySelector('#mdClose').onclick  =
    modal.querySelector('#mdClose2').onclick = () => modal.remove();

    const loadDocs = async () => {
      const d = await planAPI('docs_turno', { turno_id: tid });
      const body = modal.querySelector('#mdBody');
      if (!d.docs.length) { body.innerHTML = '<div class="plan-empty">Sin documentos</div>'; return; }
      body.innerHTML = d.docs.map(doc =>
        `<div class="plan-doc-row">
          <span class="plan-doc-icon">${doc.tipo==='pdf'?'📄':doc.tipo==='excel'?'📊':'📎'}</span>
          <a href="/${doc.path}" target="_blank" class="plan-doc-nombre">${doc.nombre}</a>
          <span class="text-muted" style="font-size:.7rem">${new Date(doc.created_at).toLocaleDateString('es-AR')}</span>
        </div>`
      ).join('');
    };

    modal.querySelector('#mdFile').onchange = async e => {
      const file = e.target.files[0];
      if (!file) return;
      const fd = new FormData();
      fd.append('archivo', file);
      fd.append('turno_id', tid);
      try {
        const r = await fetch(`${PLAN_API}?action=doc_subir`, { method:'POST', credentials:'same-origin', body: fd });
        const d = await r.json();
        if (d.error) throw new Error(d.error);
        showToast('Documento subido ✓', 'success');
        loadDocs();
      } catch(e) { showToast('Error: ' + e.message, 'error'); }
    };

    await loadDocs();
  };

  // Cargar todo
  try {
    const d = await planAPI('semanas');
    semanas = d.semanas || [];
    // Activar semana actual (o próxima abierta)
    // Seleccionar la semana que contiene la fecha actual, o la primera abierta
    const hoy = new Date().toISOString().split('T')[0];
    semanaActiva = semanas.find(s => s.fecha_inicio <= hoy && s.fecha_fin >= hoy)
                || semanas.find(s => s.estado === 'abierta')
                || semanas[0];
    if (semanaActiva) await loadTurnos();
    else renderSemanas();
  } catch(e) {
    showToast('Error: ' + e.message, 'error');
    menuGrid.innerHTML = `<div class="plan-empty">Error cargando planificación: ${e.message}</div>`;
  }
}

// ════════════════════════════════════════════════════════════
//  VISTA 2: PLANIFICACIÓN CLIENTE
// ════════════════════════════════════════════════════════════
async function renderPlanificacionCliente() {
  setHeader('PLANIFICACIÓN', 'Solicitud de turnos');
  setExpandedMode(false);
  showMetaPanel(true);
  menuGrid.className = '';
  menuGrid.innerHTML = '<div class="plan-loading"><div class="spin"></div> Cargando...</div>';
  syncBackBtn();

  const wrap = document.createElement('div');
  wrap.className = 'plan-wrap plan-cliente-wrap';
  menuGrid.innerHTML = '';
  menuGrid.appendChild(wrap);

  let semanas = [], clientes = [], semanaActiva = null, turnos = [], misClientes = [];
  let clockInterval = null;

  const renderClock = () => {
    const cierre = proximoCierre();
    const ahora  = new Date();
    const diff   = cierre - ahora;
    const pct    = Math.max(0, Math.min(100, (1 - diff / (7 * 24 * 3600 * 1000)) * 100));
    const urgente = diff < 24 * 3600 * 1000;

    return `<div class="plan-clock ${urgente?'urgente':''}">
      <div class="plan-clock-titulo">⏰ Próximo cierre de planificación</div>
      <div class="plan-clock-fecha">${cierre.toLocaleDateString('es-AR',{weekday:'long',day:'2-digit',month:'long'})} · 10:00 hs</div>
      <div class="plan-clock-cuenta" id="clockCountdown">${fmtCuenta(diff)}</div>
      <div class="plan-clock-bar">
        <div class="plan-clock-prog" style="width:${pct}%"></div>
      </div>
      <div class="plan-clock-hint">⚠ Los turnos solicitados después del cierre serán <strong>FUERA DE PROGRAMA</strong> y CINA no se compromete a poder cumplirlo.</div>
    </div>`;
  };

  const renderTurnosCliente = () => {
    if (!turnos.length) return '<div class="plan-empty">No tenés turnos solicitados para esta semana</div>';
    return turnos.map(t => {
      const franja = t.franja_modificada !== null ? t.franja_modificada : t.franja;
      const color  = ESTADO_COLOR[t.estado] || '#888';
      const canDel = t.estado !== 'asignado';

      return `<div class="plan-turno-card" style="border-left-color:${color}">
        <div class="ptc-header">
          <span class="plan-estado-chip" style="background:${color}22;color:${color};border-color:${color}55">${t.estado}</span>
          ${t.fuera_programa==1?'<span class="plan-fp-badge">FUERA PROG.</span>':''}
          ${t.carga_numero?`<span class="plan-carga-badge" style="cursor:pointer" onclick="planIrACarga(${t.carga_numero})" title="Ver carga #${t.carga_numero}">🚛 ${t.carga_numero} ↗</span>`:''}
          ${(t.estado==='pendiente')?`<button class="pt-btn" data-edit="${t.turno_id}" title="Editar">✏</button>`:''}
          ${canDel?`<button class="pt-btn pt-btn-del" data-del="${t.turno_id}" title="Eliminar">✕</button>`:''}
        </div>
        <div class="ptc-body">
          <div class="ptc-row"><span>📅</span><strong>${t.fecha}</strong></div>
          <div class="ptc-row"><span>🕐</span>
            ${t.franja_modificada !== null
              ? `<s class="text-muted">${FRANJAS_LABEL[t.franja]}</s> → <strong>${FRANJAS_LABEL[t.franja_modificada]}</strong> <span style="color:#F59E0B;font-size:.7rem">(ajustado por operaciones)</span>`
              : `<strong>${FRANJAS_LABEL[t.franja]}</strong>`}
          </div>
          <div class="ptc-row"><span>🏭</span>${t.tipo==='F'?'Fiscal':'Nacional'} · ${t.entrada==1?'Entrada':'Salida'}</div>
          <div class="ptc-row"><span>🚛</span>${t.tipo_transporte||'—'}</div>
          ${t.requiere_acondicionamiento==1?'<div class="ptc-row"><span>❄</span>Requiere acondicionamiento</div>':''}
          ${t.notas_cliente?`<div class="ptc-row"><span>💬</span>${t.notas_cliente}</div>`:''}
          ${t.notas_operaciones?`<div class="ptc-row" style="color:#36B0C9"><span>📋</span>${t.notas_operaciones}</div>`:''}
        </div>
        <div class="ptc-footer">
          <button class="pt-btn pt-btn-doc" data-docs="${t.turno_id}">📎 Documentos ${t.cant_docs>0?`(${t.cant_docs})`:''}
          </button>
        </div>
      </div>`;
    }).join('');
  };

  const renderForm = () => {
    if (!semanaActiva) return '';
    const isCerrada = semanaActiva.estado === 'cerrada';
    const dias = [];
    let d = new Date(semanaActiva.fecha_inicio + 'T12:00:00');
    for (let i = 0; i < 7; i++) {
      dias.push({
        value: d.toISOString().split('T')[0],
        label: d.toLocaleDateString('es-AR',{weekday:'short',day:'2-digit',month:'2-digit'})
      });
      d.setDate(d.getDate()+1);
    }

    return `
      <div class="plan-form-section">
        <div class="plan-form-title">
          ＋ Solicitar turno
          ${isCerrada?'<span class="plan-fp-badge" style="font-size:.7rem">Planificación cerrada — será FUERA DE PROGRAMA</span>':''}
        </div>

        <div class="plan-form-grid">
          <div class="cf-field">
            <label class="cf-lbl">Cliente *</label>
            <select class="cf-select" id="pfCliente">
              <option value="">— Seleccioná —</option>
              ${misClientes.map(c => `<option value="${c.id}">${c.nombre}</option>`).join('')}
            </select>
          </div>

          <div class="cf-field">
            <label class="cf-lbl">Fecha *</label>
            <select class="cf-select" id="pfFecha">
              ${dias.map(d => `<option value="${d.value}">${d.label}</option>`).join('')}
            </select>
          </div>

          <div class="cf-field">
            <label class="cf-lbl">Franja horaria *</label>
            <select class="cf-select" id="pfFranja">
              ${FRANJAS_LABEL.map((f,i) => `<option value="${i}">${f}</option>`).join('')}
            </select>
          </div>

          <div class="cf-field">
            <label class="cf-lbl">Tipo de operación *</label>
            <div class="plan-toggle-group" id="pfEntrada">
              <button class="plan-toggle active" data-val="1">⬇ Entrada</button>
              <button class="plan-toggle" data-val="0">⬆ Salida</button>
            </div>
          </div>

          <div class="cf-field">
            <label class="cf-lbl">Depósito *</label>
            <div class="plan-toggle-group" id="pfTipo">
              <button class="plan-toggle active" data-val="N">Nacional</button>
              <button class="plan-toggle" data-val="F">Fiscal</button>
            </div>
          </div>

          <div class="cf-field">
            <label class="cf-lbl">Tipo de vehículo</label>
            <select class="cf-select" id="pfVehiculo">
              <option value="">— Seleccioná —</option>
              ${TIPOS_VEHICULO.map(t => `<option value="${t.id}">${t.nombre}</option>`).join('')}
            </select>
          </div>

          <div class="cf-field">
            <label class="cf-lbl">Tipo de acoplado</label>
            <select class="cf-select" id="pfAcoplado">
              <option value="">— Sin acoplado —</option>
              ${TIPOS_ACOPLADO.map(t => `<option value="${t.id}">${t.nombre}</option>`).join('')}
            </select>
          </div>

          ${TIPOS_TRATAMIENTO.length ? `
          <div class="cf-field">
            <label class="cf-lbl">Tratamiento de carga</label>
            <select class="cf-select" id="pfTratamiento">
              <option value="">— Sin especificar —</option>
              ${TIPOS_TRATAMIENTO.map(t => `<option value="${t.id}">${t.nombre}</option>`).join('')}
            </select>
          </div>` : ''}

          <div class="cf-field cf-full">
            <label class="cf-lbl">Notas / Referencia</label>
            <input class="cf-input" type="text" id="pfNotas" placeholder="Referencia, instrucciones especiales...">
          </div>
        </div>

        <div style="display:flex;justify-content:flex-end;margin-top:10px">
          <button class="cargas-nueva-btn" id="pfEnviar">Solicitar turno →</button>
        </div>
      </div>`;
  };

  const render = () => {
    const pills = semanas.map(s => {
      const ini  = s.fecha_inicio.slice(5).replace('-','/');
      const fin  = s.fecha_fin.slice(5).replace('-','/');
      const actv = semanaActiva?.semana_id === s.semana_id ? 'active' : '';
      const est  = s.estado === 'abierta' ? '🟢' : '🔴';
      return `<button class="plan-semana-pill ${actv}" data-sid="${s.semana_id}">
        ${est} ${ini}–${fin}
        <span class="plan-pill-badge">${s.total_turnos||0}</span>
      </button>`;
    }).join('');

    wrap.innerHTML = `
      ${renderClock()}

      <div class="plan-semanas-scroll" style="margin:12px 0">${pills}</div>

      ${semanaActiva ? `
      <div class="plan-cliente-cols">
        <div class="plan-cliente-izq">
          ${renderForm()}
        </div>
        <div class="plan-cliente-der">
          <div class="plan-form-title">📋 Mis turnos — semana ${semanaActiva.fecha_inicio.slice(5).replace('-','/')}</div>
          <div id="planMisTurnos">${renderTurnosCliente()}</div>
        </div>
      </div>
      ` : '<div class="plan-empty">Seleccioná una semana</div>'}
    `;

    // Toggle groups
    wrap.querySelectorAll('.plan-toggle-group').forEach(grp => {
      grp.querySelectorAll('.plan-toggle').forEach(btn => {
        btn.onclick = () => {
          grp.querySelectorAll('.plan-toggle').forEach(b => b.classList.remove('active'));
          btn.classList.add('active');
        };
      });
    });

    // Semana pills
    wrap.querySelectorAll('.plan-semana-pill').forEach(btn => {
      btn.onclick = () => {
        semanaActiva = semanas.find(s => s.semana_id == btn.dataset.sid);
        loadTurnosCliente();
      };
    });

    // Formulario enviar
    wrap.querySelector('#pfEnviar')?.addEventListener('click', async () => {
      const clienteId = wrap.querySelector('#pfCliente')?.value;
      const fecha     = wrap.querySelector('#pfFecha')?.value;
      const franja    = wrap.querySelector('#pfFranja')?.value;
      const entrada   = wrap.querySelector('#pfEntrada .plan-toggle.active')?.dataset.val;
      const tipo      = wrap.querySelector('#pfTipo .plan-toggle.active')?.dataset.val;
      const transp    = wrap.querySelector('#pfVehiculo')?.value;
      const acopl     = wrap.querySelector('#pfAcoplado')?.value;
      const tratam    = wrap.querySelector('#pfTratamiento')?.value || '';
      const notas     = wrap.querySelector('#pfNotas')?.value;

      if (!clienteId || !fecha) { showToast('Seleccioná cliente y fecha', 'error'); return; }

      const btn = wrap.querySelector('#pfEnviar');
      btn.disabled = true; btn.textContent = 'Enviando...';
      try {
        const d = await planAPIPost('turno_crear', {
          semana_id: semanaActiva.semana_id,
          cliente_id: clienteId, fecha, franja, entrada, tipo,
          tipo_vehiculo_id: transp || null,
          tipo_acoplado_id: acopl  || null,
          tipo_transporte: '',
          tratamiento_id: tratam || null,
          requiere_acondicionamiento: 0,
          notas_cliente: notas, cantidad: 1,
        });
        showToast(d.mensaje || 'Turno solicitado ✓', 'success');
        wrap.querySelector('#pfNotas').value = '';
        loadTurnosCliente();
      } catch(e) { showToast('Error: ' + e.message, 'error'); }
      finally { btn.disabled = false; btn.textContent = 'Solicitar turno →'; }
    });

    // Eliminar turno
    wrap.querySelectorAll('[data-del]').forEach(btn => {
      btn.onclick = async () => {
        if (!confirm('¿Eliminar este turno?')) return;
        try {
          await planAPIPost('turno_eliminar', { turno_id: parseInt(btn.dataset.del) });
          showToast('Turno eliminado', 'success');
          loadTurnosCliente();
        } catch(e) { showToast('Error: ' + e.message, 'error'); }
      };
    });

    // Editar turno (solo estado pendiente)
    wrap.querySelectorAll('[data-edit]').forEach(btn => {
      btn.onclick = () => modalEditarTurnoCliente(parseInt(btn.dataset.edit), turnos.find(t=>t.turno_id==btn.dataset.edit));
    });

    // Docs
    wrap.querySelectorAll('[data-docs]').forEach(btn => {
      btn.onclick = () => modalDocsCliente(parseInt(btn.dataset.docs));
    });

    // Clock tick
    if (clockInterval) clearInterval(clockInterval);
    clockInterval = setInterval(() => {
      const el = document.getElementById('clockCountdown');
      if (el) el.textContent = fmtCuenta(proximoCierre() - new Date());
    }, 1000);
  };

  const modalDocsCliente = async (tid) => {
    const modal = document.createElement('div');
    modal.className = 'plan-modal-overlay';
    modal.innerHTML = `
      <div class="plan-modal">
        <div class="plan-modal-header">
          <span>📎 Documentos — Turno #${tid}</span>
          <button class="plan-modal-close" id="mdcClose">✕</button>
        </div>
        <div class="plan-modal-body" id="mdcBody"><div class="spin"></div></div>
        <div class="plan-modal-footer">
          <input type="file" id="mdcFile" accept=".pdf,.xls,.xlsx,.doc,.docx,.csv,.jpg,.jpeg,.png,.gif,.bmp,.webp,.txt,.zip,.rar">
          <button class="cargas-nueva-btn" id="mdcSubir">📎 Subir</button>
          <button class="ct-acc-btn" id="mdcClose2">Cerrar</button>
        </div>
      </div>`;
    document.body.appendChild(modal);
    const close = () => { modal.remove(); };
    modal.querySelector('#mdcClose').onclick  =
    modal.querySelector('#mdcClose2').onclick = close;

    const loadDocs = async () => {
      const d = await planAPI('docs_turno', { turno_id: tid });
      const body = modal.querySelector('#mdcBody');
      body.innerHTML = d.docs.length
        ? d.docs.map(doc => `<div class="plan-doc-row">
            <span>${doc.tipo==='pdf'?'📄':doc.tipo==='excel'?'📊':'📎'}</span>
            <a href="/${doc.path}" target="_blank" class="plan-doc-nombre">${doc.nombre}</a>
            <span class="text-muted" style="font-size:.7rem">${doc.created_at.slice(0,10)}</span>
          </div>`).join('')
        : '<div class="plan-empty">Sin documentos todavía</div>';
    };

    modal.querySelector('#mdcSubir').onclick = async () => {
      const file = modal.querySelector('#mdcFile').files[0];
      if (!file) { showToast('Seleccioná un archivo', 'error'); return; }
      const fd = new FormData();
      fd.append('archivo', file); fd.append('turno_id', tid);
      try {
        const r = await fetch(`${PLAN_API}?action=doc_subir`, { method:'POST', credentials:'same-origin', body: fd });
        const d = await r.json();
        if (d.error) throw new Error(d.error);
        showToast('Subido ✓', 'success');
        modal.querySelector('#mdcFile').value = '';
        loadDocs();
      } catch(e) { showToast(e.message, 'error'); }
    };

    await loadDocs();
  };

  const modalEditarTurnoCliente = (tid, t) => {
    if (!t || t.estado !== 'pendiente') {
      showToast('Solo se pueden editar turnos pendientes', 'error'); return;
    }
    const dias = [];
    let d = new Date(semanaActiva.fecha_inicio + 'T12:00:00');
    for (let i = 0; i < 7; i++) {
      dias.push({ value: d.toISOString().split('T')[0], label: d.toLocaleDateString('es-AR',{weekday:'short',day:'2-digit',month:'2-digit'}) });
      d.setDate(d.getDate()+1);
    }

    const modal = document.createElement('div');
    modal.className = 'plan-modal-overlay';
    modal.innerHTML = `
      <div class="plan-modal">
        <div class="plan-modal-header">
          <span>✏ Editar turno #${tid}</span>
          <button class="plan-modal-close" id="etClose">✕</button>
        </div>
        <div class="plan-modal-body">
          <div class="pm-info-row" style="color:#F59E0B">⚠ Solo podés editar turnos pendientes. Una vez aprobado o rechazado, ya no es editable.</div>
          <div class="plan-form-grid" style="gap:10px;margin-top:8px">
            <div class="cf-field">
              <label class="cf-lbl">Fecha</label>
              <select class="cf-select" id="etFecha">
                ${dias.map(d => `<option value="${d.value}" ${d.value===t.fecha?'selected':''}>${d.label}</option>`).join('')}
              </select>
            </div>
            <div class="cf-field">
              <label class="cf-lbl">Franja horaria</label>
              <select class="cf-select" id="etFranja">
                ${FRANJAS_LABEL.map((f,i) => `<option value="${i}" ${i==t.franja?'selected':''}>${f}</option>`).join('')}
              </select>
            </div>
            <div class="cf-field">
              <label class="cf-lbl">Movimiento</label>
              <div class="plan-toggle-group" id="etEntrada">
                <button class="plan-toggle ${t.entrada==1?'active':''}" data-val="1">⬇ Entrada</button>
                <button class="plan-toggle ${t.entrada==0?'active':''}" data-val="0">⬆ Salida</button>
              </div>
            </div>
            <div class="cf-field">
              <label class="cf-lbl">Depósito</label>
              <div class="plan-toggle-group" id="etTipo">
                <button class="plan-toggle ${t.tipo==='N'?'active':''}" data-val="N">Nacional</button>
                <button class="plan-toggle ${t.tipo==='F'?'active':''}" data-val="F">Fiscal</button>
              </div>
            </div>
            <div class="cf-field">
              <label class="cf-lbl">Tipo de vehículo</label>
              <select class="cf-select" id="etVehiculo">
                <option value="">— Seleccioná —</option>
                ${TIPOS_VEHICULO.map(tv => `<option value="${tv.id}" ${tv.id==t.tipo_vehiculo_id?'selected':''}>${tv.nombre}</option>`).join('')}
              </select>
            </div>
            <div class="cf-field">
              <label class="cf-lbl">Tipo de acoplado</label>
              <select class="cf-select" id="etAcoplado">
                <option value="">— Sin acoplado —</option>
                ${TIPOS_ACOPLADO.map(ta => `<option value="${ta.id}" ${ta.id==t.tipo_acoplado_id?'selected':''}>${ta.nombre}</option>`).join('')}
              </select>
            </div>
            ${TIPOS_TRATAMIENTO.length ? `
            <div class="cf-field">
              <label class="cf-lbl">Tratamiento</label>
              <select class="cf-select" id="etTratamiento">
                <option value="">— Sin especificar —</option>
                ${TIPOS_TRATAMIENTO.map(tr => `<option value="${tr.id}" ${tr.id===t.tratamiento_id?'selected':''}>${tr.nombre}</option>`).join('')}
              </select>
            </div>` : ''}
            <div class="cf-field cf-full">
              <label class="cf-lbl">Notas / Referencia</label>
              <input class="cf-input" type="text" id="etNotas" value="${t.notas_cliente||''}" placeholder="Observaciones...">
            </div>
          </div>
        </div>
        <div class="plan-modal-footer">
          <button class="ct-acc-btn" id="etCancel">Cancelar</button>
          <button class="ct-acc-btn ct-acc-ver" id="etGuardar">💾 Guardar cambios</button>
        </div>
      </div>`;

    document.body.appendChild(modal);

    modal.querySelectorAll('.plan-toggle-group').forEach(grp => {
      grp.querySelectorAll('.plan-toggle').forEach(btn => {
        btn.onclick = () => { grp.querySelectorAll('.plan-toggle').forEach(b=>b.classList.remove('active')); btn.classList.add('active'); };
      });
    });

    const close = () => modal.remove();
    modal.querySelector('#etClose').onclick  =
    modal.querySelector('#etCancel').onclick = close;

    modal.querySelector('#etGuardar').onclick = async () => {
      const btn = modal.querySelector('#etGuardar');
      btn.disabled = true; btn.textContent = 'Guardando...';
      try {
        await planAPIPost('turno_editar_cliente', {
          turno_id:                    tid,
          fecha:                       modal.querySelector('#etFecha').value,
          franja:                      modal.querySelector('#etFranja').value,
          entrada:                     modal.querySelector('#etEntrada .plan-toggle.active')?.dataset.val,
          tipo:                        modal.querySelector('#etTipo .plan-toggle.active')?.dataset.val,
          tipo_vehiculo_id:            modal.querySelector('#etVehiculo')?.value || null,
          tipo_acoplado_id:            modal.querySelector('#etAcoplado')?.value || null,
          tratamiento_id:              modal.querySelector('#etTratamiento')?.value || null,
          requiere_acondicionamiento:  0,
          notas_cliente:               modal.querySelector('#etNotas').value,
        });
        showToast('Turno actualizado ✓', 'success');
        close();
        loadTurnosCliente();
      } catch(e) { showToast('Error: '+e.message,'error'); btn.disabled=false; btn.textContent='💾 Guardar cambios'; }
    };
  };

  const loadTurnosCliente = async () => {
    if (!semanaActiva) return;
    try {
      const d = await planAPI('turnos_lista', { semana_id: semanaActiva.semana_id });
      turnos = d.turnos || [];
    } catch(e) { turnos = []; }
    render();
  };

  // Limpiar reloj al salir
  const origBack = window._planBackCleanup;
  window._planBackCleanup = () => { if (clockInterval) clearInterval(clockInterval); };

  try {
    const [sd, cd, td] = await Promise.all([
      planAPI('semanas'),
      planAPI('mis_clientes'),
      planAPI('tipos_transporte'),
    ]);
    semanas     = sd.semanas  || [];
    misClientes = cd.clientes || [];
    TIPOS_VEHICULO    = td.vehiculos    || [];
    TIPOS_ACOPLADO    = td.acoplados    || [];
    TIPOS_TRATAMIENTO = td.tratamientos || [];

    // Empleados sin clientes asignados no acceden al módulo cliente
    if (!cd.es_cliente && !cd.es_operaciones) {
      wrap.innerHTML = '<div class="plan-empty">Este módulo es solo para usuarios cliente.<br>Para gestión de operaciones usá el módulo Planificación.</div>';
      return;
    }

    semanaActiva = semanas.find(s => s.estado === 'abierta') || semanas[0];
    if (semanaActiva) {
      const d = await planAPI('turnos_lista', { semana_id: semanaActiva.semana_id });
      turnos = d.turnos || [];
    }
    render();
  } catch(e) {
    showToast('Error: ' + e.message, 'error');
    wrap.innerHTML = `<div class="plan-empty">Error: ${e.message}</div>`;
  }
}


// ════════════════════════════════════════════════════════════════
//  MÓDULO: PRUEBA DE TRAZABILIDAD
// ════════════════════════════════════════════════════════════════



// ════════════════════════════════════════════════════════════════
//  MÓDULO: PRUEBA DE TRAZABILIDAD — v3 (sin librerías externas)
// ════════════════════════════════════════════════════════════════
async function renderTrazabilidad() {
  setHeader('TRAZABILIDAD', 'Control y verificación de posiciones');
  showMetaPanel(false);
  setExpandedMode(true);
  menuGrid.className = '';
  menuGrid.innerHTML = '';
  syncBackBtn();

  // ── Construir el DOM manualmente (evita problemas con template literals) ──
  const wrap = document.createElement('div');
  wrap.className = 'fact-wrap';
  wrap.style.maxWidth = 'none';
  wrap.style.width    = '100%';
  menuGrid.appendChild(wrap);
  syncBackBtn();

  // Header
  const header = document.createElement('div');
  header.className = 'fact-header';
  header.style.cssText = 'flex-direction:row;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px';
  header.innerHTML = `
    <div class="fact-header-title">
      <span class="indicador-badge" style="background:rgba(99,179,237,.15);color:#63b3ed;border-color:rgba(99,179,237,.3)">📍</span>
      PRUEBA DE TRAZABILIDAD
    </div>
    <button id="trazBtnReporte" class="fact-sel-none-btn" style="border-color:rgba(99,179,237,.3);color:#63b3ed">📊 Reporte</button>`;
  wrap.appendChild(header);

  // Panel scan
  const panelScan = document.createElement('div');
  panelScan.id = 'trazPanelScan';
  wrap.appendChild(panelScan);

  // Zona QR
  const zonaQR = document.createElement('div');
  zonaQR.className = 'traz-qr-zona';
  zonaQR.innerHTML = `
    <div style="color:#63b3ed;margin-bottom:10px;opacity:.75">
      <svg viewBox="0 0 24 24" width="56" height="56" fill="none" stroke="currentColor" stroke-width="1.5">
        <rect x="3" y="3" width="7" height="7" rx="1"/>
        <rect x="14" y="3" width="7" height="7" rx="1"/>
        <rect x="3" y="14" width="7" height="7" rx="1"/>
        <circle cx="17.5" cy="17.5" r="2.5"/>
      </svg>
    </div>
    <div id="trazQrLabel" style="font-size:1rem;font-weight:700;color:rgba(255,255,255,.75);margin-bottom:6px">
      Escanear código QR
    </div>
    <div style="font-size:.8rem;color:#64748b;margin-bottom:18px">
      Apuntá la cámara al código QR de la estiba
    </div>
    <div id="trazError" style="color:#f87171;font-size:.84rem;margin-bottom:10px;display:none"></div>`;
  panelScan.appendChild(zonaQR);

  // Botón abrir cámara — creado por JS puro, no template
  const btnCam = document.createElement('button');
  btnCam.id = 'trazBtnCamara';
  btnCam.className = 'traz-btn-cam';
  btnCam.innerHTML = '📷 Abrir cámara';
  btnCam.type = 'button';
  zonaQR.appendChild(btnCam);

  // Input oculto para pistola lectora desktop
  const hiddenInput = document.createElement('input');
  hiddenInput.type = 'text';
  hiddenInput.id   = 'trazCodigoHidden';
  hiddenInput.autocomplete = 'off';
  hiddenInput.style.cssText = 'position:absolute;opacity:0;width:1px;height:1px;pointer-events:none';
  hiddenInput.setAttribute('tabindex', '-1');
  zonaQR.appendChild(hiddenInput);

  // Contenedor del video (oculto inicialmente)
  const camView = document.createElement('div');
  camView.id = 'trazCamView';
  camView.style.display = 'none';
  camView.style.width   = '100%';
  camView.innerHTML = `
    <video id="trazVideo" playsinline muted
      style="width:100%;max-height:320px;border-radius:10px;background:#000;display:block"></video>
    <canvas id="trazCanvas" style="display:none"></canvas>
    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none">
      <div style="width:200px;height:200px;border:2px solid rgba(99,179,237,.8);border-radius:12px;
                  box-shadow:0 0 0 9999px rgba(0,0,0,.45)"></div>
    </div>
    <button id="trazCerrarCam" type="button"
      style="position:absolute;top:8px;right:8px;background:rgba(0,0,0,.6);
             border:1px solid rgba(255,255,255,.3);color:#fff;border-radius:6px;
             padding:6px 14px;cursor:pointer;font-size:.85rem;z-index:10">
      ✕ Cerrar
    </button>`;
  panelScan.appendChild(camView);

  // Panel resultado (oculto)
  const resultado = document.createElement('div');
  resultado.id = 'trazResultado';
  resultado.style.display = 'none';
  resultado.className = 'traz-panel';
  resultado.innerHTML = `
    <div class="traz-card" id="trazInfoEstiba"></div>
    <div class="traz-seccion">
      <div class="traz-sec-titulo">📌 Posición según sistema</div>
      <div id="trazPosSistema"></div>
    </div>
    <div class="traz-seccion">
      <div class="traz-sec-titulo">📍 Posición real</div>
      <div class="traz-pos-real-grid">
        <div class="oc-field-group">
          <label class="fact-date-label">Cámara</label>
          <select class="oc-select" id="trazRealCamara"><option value="">— Seleccionar —</option></select>
        </div>
        <div class="oc-field-group">
          <label class="fact-date-label">Calle</label>
          <input class="oc-input" type="number" min="1" id="trazRealCalle" placeholder="5" />
        </div>
        <div class="oc-field-group">
          <label class="fact-date-label">Fila</label>
          <input class="oc-input" type="number" min="1" id="trazRealFila" placeholder="3" />
        </div>
        <div class="oc-field-group">
          <label class="fact-date-label">Nivel</label>
          <input class="oc-input" type="number" min="1" id="trazRealNivel" placeholder="2" />
        </div>
      </div>
    </div>
    <div class="traz-seccion" id="trazSecCriticidad" style="display:none">
      <div class="traz-sec-titulo">⚠ Análisis de incidencia</div>
      <div id="trazCriticidadWrap"></div>
      <div class="oc-field-group" style="margin-top:10px">
        <label class="fact-date-label">Observaciones (opcional)</label>
        <textarea class="oc-textarea" id="trazObservacion" rows="2" placeholder="Nota adicional..."></textarea>
      </div>
    </div>
    <div style="margin-top:16px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <button type="button" class="fact-download-btn" id="trazAnalizar"
        style="background:linear-gradient(135deg,#0369a1,#0891b2);min-width:160px">
        🔍 Analizar
      </button>
      <button type="button" class="fact-download-btn" id="trazGuardar"
        style="min-width:180px;display:none">💾 Registrar control</button>
      <button type="button" class="fact-sel-none-btn" id="trazNuevo">🔄 Nuevo escaneo</button>
      <span id="trazGuardadoOk" style="color:#22c55e;font-size:.85rem;display:none">✅ Registrado</span>
    </div>`;
  panelScan.appendChild(resultado);

  // Panel reporte (oculto)
  const panelReporte = document.createElement('div');
  panelReporte.id = 'trazPanelReporte';
  panelReporte.style.display = 'none';
  panelReporte.style.width = '100%';
  panelReporte.innerHTML = `
    <div class="traz-seccion" style="max-width:none">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
        <div class="traz-sec-titulo" style="margin:0">📊 Reporte de controles</div>
        <button type="button" class="fact-sel-none-btn" id="trazBtnVolver">← Volver</button>
      </div>
      <div class="traz-filtros">
        <div class="oc-field-group">
          <label class="fact-date-label">Desde</label>
          <input class="oc-input" type="date" id="trazDesde" />
        </div>
        <div class="oc-field-group">
          <label class="fact-date-label">Hasta</label>
          <input class="oc-input" type="date" id="trazHasta" />
        </div>
        <div class="oc-field-group">
          <label class="fact-date-label">Incidencia</label>
          <select class="oc-select" id="trazFiltCrit">
            <option value="">Todas</option>
            <option value="ok">✅ OK</option>
            <option value="baja">🟡 Baja</option>
            <option value="media">🟠 Media</option>
            <option value="alta">🔴 Alta</option>
          </select>
        </div>
        <button type="button" class="fact-download-btn" id="trazCargarReporte" style="align-self:flex-end">Cargar</button>
      </div>
      <div id="trazResumenCards" style="display:none;margin:14px 0"></div>
      <div id="trazTablaWrap" style="overflow-x:auto;margin-top:10px"></div>
    </div>`;
  wrap.appendChild(panelReporte);

  // ── Cargar cámaras ──────────────────────────────────────────
  try {
    const r  = await fetch(`${API_EQUIPOS_URL}?action=camaras`, { credentials: 'same-origin' });
    const dc = await r.json();
    const sel = wrap.querySelector('#trazRealCamara');
    (Array.isArray(dc) ? dc : []).forEach(c => {
      const opt = document.createElement('option');
      opt.value            = c.CamaraID;
      opt.dataset.numero   = c.CamaraNumero;
      opt.textContent      = c.nombre_display || `Cámara ${c.CamaraNumero}`;
      sel.appendChild(opt);
    });
  } catch(e) {}

  // ── Estado ────────────────────────────────────────────────────
  let datosSistema = null;
  let scanStream   = null;
  let scanRafId    = null;

  const errEl   = wrap.querySelector('#trazError');
  const qrLabel = wrap.querySelector('#trazQrLabel');

  // ── Helpers ───────────────────────────────────────────────────
  function parsearCodigoQR(raw) {
    raw = (raw || '').trim();
    // URL completa del QR: ?estibaid=1757928 → extraer solo el número (= EstibaID directo)
    const urlMatch = raw.match(/[?&]estibaid=(\d+)/i);
    if (urlMatch) return urlMatch[1];
    // Solo dígitos: ya es el EstibaID
    if (/^\d+$/.test(raw)) return raw;
    return raw;
  }

  function calcularCriticidad(sis, real, sinPosicion) {
    if (sinPosicion) return { cumple: false, criticidad: 'alta', motivos: ['Sin posición en el sistema'] };
    if (!sis) return { cumple: true, criticidad: 'ok', motivos: [] };
    const motivos = [];
    // Comparar por CamaraNumero (número físico de la cámara)
    const sisCam  = parseInt(sis.camara_numero || 0);
    const realCam = parseInt(real.camara_numero || 0);
    if (sisCam !== realCam)
      motivos.push(`No está en la cámara correspondiente (sistema: Cámara ${sisCam} / real: Cámara ${realCam || '—'})`);
    if (sis.calle !== parseInt(real.calle || 0))
      motivos.push(`Calle incorrecta (sistema: ${sis.calle} / real: ${real.calle || '—'})`);
    if (sis.fila  !== parseInt(real.fila  || 0))
      motivos.push(`Fila incorrecta (sistema: ${sis.fila} / real: ${real.fila || '—'})`);
    if (sis.nivel !== parseInt(real.nivel || 0))
      motivos.push(`Nivel incorrecto (sistema: ${sis.nivel} / real: ${real.nivel || '—'})`);
    if (!motivos.length) return { cumple: true, criticidad: 'ok', motivos: [] };
    const difCamara = motivos.some(m => m.startsWith('No está en la cámara'));
    return { cumple: false, criticidad: difCamara ? 'alta' : motivos.length > 1 ? 'media' : 'baja', motivos };
  }

  const CRIT_CFG = {
    ok:    { label: '✅ CUMPLE',           color: '#22c55e', bg: 'rgba(34,197,94,.1)'   },
    baja:  { label: '🟡 INCIDENCIA BAJA',  color: '#FACC15', bg: 'rgba(250,204,21,.1)' },
    media: { label: '🟠 INCIDENCIA MEDIA', color: '#f97316', bg: 'rgba(249,115,22,.1)' },
    alta:  { label: '🔴 INCIDENCIA ALTA',  color: '#ef4444', bg: 'rgba(239,68,68,.1)'  },
  };

  function getRealValues() {
    const sel = wrap.querySelector('#trazRealCamara');
    return {
      camara_id:     parseInt(sel.value) || null,
      camara_numero: parseInt(sel.selectedOptions[0]?.dataset.numero || 0) || null,
      camara_nombre: sel.selectedOptions[0]?.text || '',
      calle:         parseInt(wrap.querySelector('#trazRealCalle').value)  || null,
      fila:          parseInt(wrap.querySelector('#trazRealFila').value)   || null,
      nivel:         parseInt(wrap.querySelector('#trazRealNivel').value)  || null,
    };
  }

  function actualizarAnalisis() {
    if (!datosSistema) return;
    const real = getRealValues();
    const sec  = wrap.querySelector('#trazSecCriticidad');
    if (!real.camara_id && !real.calle && !real.fila && !real.nivel) { sec.style.display='none'; return; }
    sec.style.display = '';
    const { criticidad, motivos } = calcularCriticidad(datosSistema.posicion_sistema, real, datosSistema.sin_posicion);
    const cfg = CRIT_CFG[criticidad];
    wrap.querySelector('#trazCriticidadWrap').innerHTML =
      `<div class="traz-crit-badge" style="background:${cfg.bg};border:1px solid ${cfg.color}40;color:${cfg.color}">${cfg.label}</div>
       ${motivos.length ? '<ul class="traz-motivos">' + motivos.map(m=>`<li>${m}</li>`).join('') + '</ul>' : ''}`;
  }

  // El análisis se activa solo al presionar "Analizar" — no en tiempo real

  // ── Buscar estiba ─────────────────────────────────────────────
  async function buscarEstiba(codigo) {
    errEl.style.display = 'none';
    qrLabel.textContent = `🔄 Buscando #${codigo}...`;
    try {
      const res  = await fetch(`${PLAN_API}?action=trazabilidad_estiba&codigo=${encodeURIComponent(codigo)}`, { credentials: 'same-origin' });
      const data = await res.json();
      if (data.error) throw new Error(data.error);
      datosSistema = data;

      wrap.querySelector('#trazInfoEstiba').innerHTML = `
        <div class="traz-info-row"><span class="traz-info-label">Estiba</span>
          <span class="traz-info-val"><strong>#${data.carga_id}</strong> &nbsp;·&nbsp; ${data.nro_estiba}</span></div>
        <div class="traz-info-row"><span class="traz-info-label">Producto</span>
          <span class="traz-info-val">${data.producto || '—'}</span></div>
        <div class="traz-info-row"><span class="traz-info-label">Cliente</span>
          <span class="traz-info-val">
            ${data.cliente_color ? `<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${data.cliente_color};margin-right:5px"></span>` : ''}
            ${data.cliente_siglas || ''} ${data.cliente || '—'}
          </span></div>
        <div class="traz-info-row"><span class="traz-info-label">Ingreso</span>
          <span class="traz-info-val">${data.fecha_alta || '—'}</span></div>`;

      const posSisEl = wrap.querySelector('#trazPosSistema');
      if (data.sin_posicion) {
        const ult = data.ultima_posicion;
        posSisEl.innerHTML = `<div class="traz-badge traz-alta">⚠ SIN POSICIÓN EN EL SISTEMA</div>
          ${ult ? `<div class="traz-ult-pos"><span class="fact-date-label">Última:</span>
            <strong>${ult.camara_nombre}</strong> — Calle ${ult.calle} / Fila ${ult.fila} / Nivel ${ult.nivel}</div>` : ''}`;
      } else if (data.posicion_sistema) {
        const p = data.posicion_sistema;
        posSisEl.innerHTML = `<div class="traz-pos-tag">
          <span class="traz-pos-cam">${p.camara_nombre}</span>
          <span class="traz-pos-dato">Calle <strong>${p.calle}</strong></span>
          <span class="traz-pos-dato">Fila <strong>${p.fila}</strong></span>
          <span class="traz-pos-dato">Nivel <strong>${p.nivel}</strong></span></div>`;
        // Precompletar: buscar la opción cuyo data-numero coincida con camara_numero del sistema
        const selCam = wrap.querySelector('#trazRealCamara');
        Array.from(selCam.options).forEach(opt => {
          if (parseInt(opt.dataset.numero) === p.camara_numero) selCam.value = opt.value;
        });
        wrap.querySelector('#trazRealCalle').value = p.calle;
        wrap.querySelector('#trazRealFila').value  = p.fila;
        wrap.querySelector('#trazRealNivel').value = p.nivel;
        // No analizar automáticamente — esperar que el usuario presione Analizar
      }

      resultado.style.display = '';
      wrap.querySelector('#trazGuardadoOk').style.display = 'none';
    } catch(e) {
      qrLabel.textContent = 'Escanear código QR';
      errEl.textContent   = e.message || 'Error al buscar';
      errEl.style.display = 'block';
    }
  }

  // ── Cámara — usa BarcodeDetector nativo (sin librerías) ────────
  async function abrirCamara() {
    errEl.style.display = 'none';
    btnCam.disabled    = true;
    btnCam.textContent = '⏳ Iniciando...';

    try {
      scanStream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 } }
      });
      const video  = wrap.querySelector('#trazVideo');
      const canvas = wrap.querySelector('#trazCanvas');
      video.srcObject = scanStream;
      await video.play();
      camView.style.display = '';
      zonaQR.style.display  = 'none';

      // Intentar BarcodeDetector nativo (Chrome Android, Safari 17+)
      if ('BarcodeDetector' in window) {
        const detector = new BarcodeDetector({ formats: ['qr_code'] });
        async function detectLoop() {
          if (!scanStream) return;
          try {
            const codes = await detector.detect(video);
            if (codes.length > 0) {
              cerrarCamara();
              buscarEstiba(parsearCodigoQR(codes[0].rawValue));
              return;
            }
          } catch(e) {}
          scanRafId = requestAnimationFrame(detectLoop);
        }
        scanRafId = requestAnimationFrame(detectLoop);
      } else {
        // Fallback: cargar jsQR desde cdn en segundo plano
        const ctx = canvas.getContext('2d');
        let decoder = null;
        // Intentar cargar jsQR
        const tryLoad = (url) => new Promise((ok, fail) => {
          const s = document.createElement('script');
          s.src = url; s.onload = ok; s.onerror = fail;
          document.head.appendChild(s);
        });
        for (const url of [
          'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js',
          'https://unpkg.com/jsqr@1.4.0/dist/jsQR.js',
        ]) {
          try { await tryLoad(url); if (window.jsQR) { decoder = window.jsQR; break; } }
          catch(e) {}
        }
        if (!decoder) {
          cerrarCamara();
          errEl.innerHTML = '⚠ Tu navegador no soporta lectura de QR nativa.<br>' +
            '<small>Usá Chrome en Android o actualizá el navegador.</small>';
          errEl.style.display = 'block';
          return;
        }
        function scanLoop() {
          if (!scanStream) return;
          if (video.readyState === video.HAVE_ENOUGH_DATA) {
            canvas.width  = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const decoded = decoder(imgData.data, canvas.width, canvas.height);
            if (decoded?.data) { cerrarCamara(); buscarEstiba(parsearCodigoQR(decoded.data)); return; }
          }
          scanRafId = requestAnimationFrame(scanLoop);
        }
        scanRafId = requestAnimationFrame(scanLoop);
      }
    } catch(e) {
      cerrarCamara();
      errEl.textContent = e.name === 'NotAllowedError'
        ? '⚠ Permiso de cámara denegado. Habilitalo en Configuración → Privacidad → Cámara.'
        : '⚠ No se pudo abrir la cámara: ' + e.message;
      errEl.style.display = 'block';
    }
  }

  function cerrarCamara() {
    if (scanRafId) { cancelAnimationFrame(scanRafId); scanRafId = null; }
    if (scanStream) { scanStream.getTracks().forEach(t => t.stop()); scanStream = null; }
    camView.style.display = 'none';
    zonaQR.style.display  = '';
    btnCam.disabled       = false;
    btnCam.textContent    = '📷 Abrir cámara';
  }

  btnCam.addEventListener('click', abrirCamara);
  wrap.querySelector('#trazCerrarCam').addEventListener('click', cerrarCamara);

  // ── Pistola lectora (desktop) ─────────────────────────────────
  setTimeout(() => hiddenInput.focus(), 200);
  document.addEventListener('keydown', _trazKeyCapture);
  function _trazKeyCapture(e) {
    if (resultado.style.display !== 'none') return;
    if (e.target === hiddenInput) return;
    if (['INPUT','TEXTAREA','SELECT'].includes(e.target.tagName)) return;
    hiddenInput.focus();
  }

  let scanTimer = null;
  hiddenInput.addEventListener('keydown', e => {
    if (e.key === 'Enter') {
      e.preventDefault();
      const raw = hiddenInput.value.trim();
      hiddenInput.value = '';
      if (raw) buscarEstiba(parsearCodigoQR(raw));
    }
  });
  hiddenInput.addEventListener('input', () => {
    clearTimeout(scanTimer);
    scanTimer = setTimeout(() => {
      const raw = hiddenInput.value.trim();
      if (!raw) return;
      if (/estibaid=/i.test(raw) || /^\d{5,10}$/.test(raw) || /^\d{4,7}-\d{1,3}$/.test(raw)) {
        hiddenInput.value = '';
        buscarEstiba(parsearCodigoQR(raw));
      }
    }, 500);
  });

  // ── Guardar ───────────────────────────────────────────────────
  wrap.querySelector('#trazAnalizar').addEventListener('click', () => {
    actualizarAnalisis();
    wrap.querySelector('#trazGuardar').style.display = '';
  });

  wrap.querySelector('#trazGuardar').addEventListener('click', async () => {
    if (!datosSistema) { showToast('⚠ Primero escaneá una estiba'); return; }
    const real = getRealValues();
    const { cumple, criticidad, motivos } = calcularCriticidad(datosSistema.posicion_sistema, real, datosSistema.sin_posicion);
    const btn = wrap.querySelector('#trazGuardar');
    btn.disabled = true; btn.textContent = '⏳ Guardando...';
    try {
      const res = await fetch(`${PLAN_API}?action=trazabilidad_guardar`, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          estiba_id: datosSistema.estiba_id, codigo: String(datosSistema.estiba_id),
          posicion_sistema: datosSistema.posicion_sistema,
          sin_posicion: datosSistema.sin_posicion ? 1 : 0,
          ultima_posicion: datosSistema.ultima_posicion,
          posicion_real: real, cumple: cumple ? 1 : 0,
          criticidad, motivos: motivos.join(' | '),
          observacion: wrap.querySelector('#trazObservacion').value.trim(),
        }),
      });
      const data = await res.json();
      if (data.error) throw new Error(data.error);
      wrap.querySelector('#trazGuardadoOk').style.display = 'inline';
      showToast('✅ Control registrado');
    } catch(e) { showToast('❌ ' + e.message); }
    finally { btn.disabled=false; btn.textContent='💾 Registrar control'; }
  });

  // ── Nuevo escaneo ─────────────────────────────────────────────
  wrap.querySelector('#trazNuevo').addEventListener('click', () => {
    datosSistema = null;
    cerrarCamara();
    resultado.style.display = 'none';
    wrap.querySelector('#trazSecCriticidad').style.display = 'none';
    wrap.querySelector('#trazGuardar').style.display = 'none';
    ['#trazRealCamara','#trazRealCalle','#trazRealFila','#trazRealNivel'].forEach(s => { const el=wrap.querySelector(s); if(el) el.value=''; });
    qrLabel.textContent = 'Escanear código QR';
    errEl.style.display = 'none';
    hiddenInput.value = '';
    hiddenInput.focus();
  });

  // Limpiar al salir
  const _cleanupTraz = () => { document.removeEventListener('keydown', _trazKeyCapture); cerrarCamara(); };
  const _mo = new MutationObserver(() => { if (!document.body.contains(wrap)) { _cleanupTraz(); _mo.disconnect(); } });
  _mo.observe(document.body, { childList: true, subtree: true });

  // ── Reporte ───────────────────────────────────────────────────
  const hoy   = new Date().toISOString().split('T')[0];
  const hace7 = new Date(Date.now() - 7*86400000).toISOString().split('T')[0];
  wrap.querySelector('#trazDesde').value = hace7;
  wrap.querySelector('#trazHasta').value = hoy;

  wrap.querySelector('#trazBtnReporte').addEventListener('click', () => {
    cerrarCamara();
    panelScan.style.display    = 'none';
    panelReporte.style.display = '';
    document.removeEventListener('keydown', _trazKeyCapture);
  });
  wrap.querySelector('#trazBtnVolver').addEventListener('click', () => {
    panelScan.style.display    = '';
    panelReporte.style.display = 'none';
    document.addEventListener('keydown', _trazKeyCapture);
    hiddenInput.focus();
  });

  wrap.querySelector('#trazCargarReporte').addEventListener('click', async () => {
    const desde = wrap.querySelector('#trazDesde').value;
    const hasta = wrap.querySelector('#trazHasta').value;
    const crit  = wrap.querySelector('#trazFiltCrit').value;
    const btn   = wrap.querySelector('#trazCargarReporte');
    btn.disabled=true; btn.textContent='⏳';
    try {
      const res  = await fetch(`${PLAN_API}?action=trazabilidad_reporte&desde=${desde}&hasta=${hasta}&criticidad=${crit}`, { credentials:'same-origin' });
      const data = await res.json();
      const r    = data.resumen || {};
      wrap.querySelector('#trazResumenCards').style.display = '';
      wrap.querySelector('#trazResumenCards').innerHTML = `
        <div class="traz-resumen-grid">
          <div class="traz-res-card" style="border-color:#64748b40"><div class="traz-res-num">${r.total??0}</div><div class="traz-res-label">Total</div></div>
          <div class="traz-res-card" style="border-color:#22c55e40"><div class="traz-res-num" style="color:#22c55e">${r.cumple??0}</div><div class="traz-res-label">✅ Cumplen</div></div>
          <div class="traz-res-card" style="border-color:#ef444440"><div class="traz-res-num" style="color:#ef4444">${r.no_cumple??0}</div><div class="traz-res-label">❌ No cumplen</div></div>
          <div class="traz-res-card" style="border-color:#63b3ed40"><div class="traz-res-num" style="color:#63b3ed">${r.pct_ok??0}%</div><div class="traz-res-label">Cumplimiento</div></div>
          <div class="traz-res-card" style="border-color:#FACC1540"><div class="traz-res-num" style="color:#FACC15">${r.por_criticidad?.baja??0}</div><div class="traz-res-label">🟡 Baja</div></div>
          <div class="traz-res-card" style="border-color:#f9731640"><div class="traz-res-num" style="color:#f97316">${r.por_criticidad?.media??0}</div><div class="traz-res-label">🟠 Media</div></div>
          <div class="traz-res-card" style="border-color:#ef444440"><div class="traz-res-num" style="color:#ef4444">${r.por_criticidad?.alta??0}</div><div class="traz-res-label">🔴 Alta</div></div>
        </div>`;
      const tablaWrap = wrap.querySelector('#trazTablaWrap');
      if (!data.registros?.length) {
        tablaWrap.innerHTML = '<div style="color:#64748b;padding:20px;text-align:center">Sin registros para el período</div>';
      } else {
        tablaWrap.innerHTML = `<table class="oc-table" style="font-size:.8rem;width:100%;table-layout:fixed">
          <colgroup>
            <col style="width:130px"><col style="width:110px"><col style="width:90px">
            <col style="width:160px"><col style="width:160px">
            <col style="width:40px"><col style="width:110px">
          </colgroup>
          <thead><tr>
            <th>Fecha</th><th>Usuario</th><th>Estiba</th>
            <th>Pos. Sistema</th><th>Pos. Real</th>
            <th>Res.</th><th>Incidencia</th>
          </tr></thead>
          <tbody>${data.registros.map(reg => {
            const cfg = CRIT_CFG[reg.criticidad] || CRIT_CFG.ok;
            const ps = reg.sin_posicion
              ? '<span style="color:#f97316">Sin pos.</span>'
              : (reg.sis_camara_nombre ? `${reg.sis_camara_nombre} C${reg.sis_calle}/F${reg.sis_fila}/N${reg.sis_nivel}` : '—');
            const pr = reg.real_camara_nombre
              ? `${reg.real_camara_nombre} C${reg.real_calle}/F${reg.real_fila}/N${reg.real_nivel}` : '—';
            const estiba = reg.carga_id
              ? `<strong>#${reg.carga_id}</strong>-${reg.nro_estiba}`
              : `<strong>#${reg.estiba_id}</strong>`;
            return `<tr>
              <td style="white-space:nowrap;font-size:.76rem">${(reg.fecha_control||'').slice(0,16).replace('T',' ')}</td>
              <td style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${reg.usuario_login||''}">${reg.usuario_login||''}</td>
              <td>${estiba}</td>
              <td style="font-size:.76rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${ps.replace(/<[^>]+>/g,'')}">${ps}</td>
              <td style="font-size:.76rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${pr}">${pr}</td>
              <td style="text-align:center">${reg.cumple?'✅':'❌'}</td>
              <td><span style="color:${cfg.color};font-weight:700;font-size:.75rem">${cfg.label.split(' ').slice(1).join(' ')}</span></td>
            </tr>`;
          }).join('')}</tbody></table>`;
      }
    } catch(e) { showToast('❌ '+e.message); }
    finally { btn.disabled=false; btn.textContent='Cargar'; }
  });
}

console.log('✓ renderTrazabilidad() cargado v3');


// ── Ir a la carga desde planificación ────────────────────────────
function planIrACarga(cargaNumero) {
    // Navegar al módulo de cargas y ver esa carga específica
    // Determinar planta (fiscal o nacional) según el contexto actual
    const fiscal = typeof _planEsFiscal !== 'undefined' ? _planEsFiscal : false;
    if (typeof renderCargas === 'function') {
        historyStack.push({ fn: () => renderPlanificacion() });
        renderCargas(fiscal);
        // Esperar a que cargue y buscar la carga
        setTimeout(() => {
            const searchEl = document.getElementById('cSearch');
            if (searchEl) {
                searchEl.value = String(cargaNumero);
                searchEl.dispatchEvent(new Event('input'));
            }
        }, 800);
    } else {
        showToast('Navegá a Logística Nacional → Cargas para ver la carga #' + cargaNumero, 'info');
    }
}
