/**
 * ══════════════════════════════════════════════════════════════════
 *  recepcion.js — Módulo Recepción de Transportes
 *  Portería: flujo táctil paso a paso
 *
 *  Pasos:
 *    1. Patente tractor
 *    2. Patente acoplado (opcional)
 *    3. DNI conductor (tipo + número)
 *    4. Celular conductor (verificar/completar)
 *    5. Planta (N/F) + Tipo (Entrada/Salida)
 *    6. Resultado: box asignado / en espera / ya ingresado
 *
 *  Agregar en index.php: <script src="recepcion.js"></script>
 *  Agregar en app.js renderNode: if (item.title === 'INGRESOS EGRESOS') { ... renderRecepcion(); ... }
 * ══════════════════════════════════════════════════════════════════
 */

// ── Estado de sesión del flujo ───────────────────────────────────
let _recState = {
    patente:        '',
    patenteAcoplado:'',
    conductorId:    null,
    conductorNombre:'',
    celular:        '',
};

// ── Helper fetch ─────────────────────────────────────────────────
async function recAPI(action, body = {}) {
    const r = await fetch(`${API_EQUIPOS_URL}?action=${action}`, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    });
    return r.json();
}

// ── Entrada principal ─────────────────────────────────────────────
function renderRecepcion() {
    setHeader('RECEPCIÓN DE TRANSPORTES');
    showMetaPanel(false);
    setExpandedMode(true);
    menuGrid.className = 'menu-grid full-width';
    _recState = { patente:'', patenteAcoplado:'', conductorId:null, conductorNombre:'', celular:'' };
    _recPaso1();
    syncBackBtn();
}

// ══════════════════════════════════════════════════════════════════
//  PASO 1 — Patente TRACTOR
// ══════════════════════════════════════════════════════════════════
function _recPaso1(error = '') {
    menuGrid.innerHTML = `
        <div class="rec-wrap" id="recWrap">
            <div class="rec-titulo">Recepción de Transportes</div>
            <div class="rec-paso-label">Paso 1 de 5 — Patente del TRACTOR</div>
            ${error ? `<div class="rec-error">${error}</div>` : ''}
            <div class="rec-display" id="recDisplay">ABC123</div>
            <div class="rec-display-val" id="recVal"></div>
            ${_recTeclado()}
            <div class="rec-acciones">
                <button class="rec-btn rec-btn-back" id="recBorrar">←</button>
                <button class="rec-btn rec-btn-clear" id="recLimpiar">C</button>
                <button class="rec-btn rec-btn-ok" id="recSiguiente">Ingresar →</button>
            </div>
        </div>`;

    _recInjectStyles();
    _recBindTeclado('recVal');

    document.getElementById('recSiguiente').onclick = async () => {
        const pat = document.getElementById('recVal').textContent.trim().toUpperCase();
        if (!pat) { _recPaso1('Ingresá la patente del tractor.'); return; }
        const btn = document.getElementById('recSiguiente');
        btn.disabled = true; btn.textContent = 'Verificando...';
        const r = await recAPI('rec_verificar_patente', { patente: pat, tipo: 'vehiculo' });
        if (r.ok) {
            _recState.patente = pat;
            _recPaso2();
        } else {
            _recPaso1(r.error || 'Patente no registrada.');
        }
    };
}

// ══════════════════════════════════════════════════════════════════
//  PASO 2 — Patente ACOPLADO (opcional)
// ══════════════════════════════════════════════════════════════════
function _recPaso2(error = '') {
    menuGrid.innerHTML = `
        <div class="rec-wrap" id="recWrap">
            <div class="rec-titulo">Recepción de Transportes</div>
            <div class="rec-paso-label">Paso 2 de 5 — Patente del ACOPLADO / SEMI <span class="rec-opcional">(opcional)</span></div>
            ${error ? `<div class="rec-error">${error}</div>` : ''}
            <div class="rec-display-val" id="recVal"></div>
            ${_recTeclado()}
            <div class="rec-acciones">
                <button class="rec-btn rec-btn-volver" id="recVolver">← Volver</button>
                <button class="rec-btn rec-btn-back" id="recBorrar">←</button>
                <button class="rec-btn rec-btn-clear" id="recLimpiar">C</button>
                <button class="rec-btn rec-btn-skip" id="recSaltear">Sin acoplado →</button>
                <button class="rec-btn rec-btn-ok" id="recSiguiente">Continuar →</button>
            </div>
        </div>`;

    _recInjectStyles();
    _recBindTeclado('recVal');

    document.getElementById('recVolver').onclick = () => _recPaso1();
    document.getElementById('recSaltear').onclick = () => {
        _recState.patenteAcoplado = '';
        _recPaso3();
    };
    document.getElementById('recSiguiente').onclick = async () => {
        const pat = document.getElementById('recVal').textContent.trim().toUpperCase();
        if (!pat) { _recState.patenteAcoplado = ''; _recPaso3(); return; }
        const btn = document.getElementById('recSiguiente');
        btn.disabled = true; btn.textContent = 'Verificando...';
        const r = await recAPI('rec_verificar_patente', { patente: pat, tipo: 'acoplado' });
        if (r.ok) {
            _recState.patenteAcoplado = pat;
            _recPaso3();
        } else {
            _recPaso2(r.error || 'Patente de acoplado no registrada.');
        }
    };
}

// ══════════════════════════════════════════════════════════════════
//  PASO 3 — DNI conductor
// ══════════════════════════════════════════════════════════════════
function _recPaso3(error = '') {
    menuGrid.innerHTML = `
        <div class="rec-wrap" id="recWrap">
            <div class="rec-titulo">Recepción de Transportes</div>
            <div class="rec-paso-label">Paso 3 de 5 — Documento del CONDUCTOR</div>
            ${error ? `<div class="rec-error">${error}</div>` : ''}

            <div class="rec-tipo-doc" id="recTipoDocWrap">
                <div class="rec-subtitulo">Tipo de documento:</div>
                <div class="rec-tipo-btns">
                    <button class="rec-tipo-btn" data-tipo="1">DNI</button>
                    <button class="rec-tipo-btn" data-tipo="2">CUIL</button>
                    <button class="rec-tipo-btn" data-tipo="3">Pasaporte</button>
                </div>
                <input type="hidden" id="recTipoDoc" value="">
            </div>

            <div class="rec-subtitulo" style="margin-top:20px">Número de documento:</div>
            <div class="rec-display-val rec-display-num" id="recVal"></div>
            ${_recTecladoNum()}
            <div class="rec-acciones">
                <button class="rec-btn rec-btn-volver" id="recVolver">← Volver</button>
                <button class="rec-btn rec-btn-back" id="recBorrar">←</button>
                <button class="rec-btn rec-btn-clear" id="recLimpiar">C</button>
                <button class="rec-btn rec-btn-ok" id="recSiguiente">Continuar →</button>
            </div>
        </div>`;

    _recInjectStyles();
    _recBindTecladoNum('recVal');

    // Selección de tipo de doc
    document.querySelectorAll('.rec-tipo-btn').forEach(btn => {
        btn.onclick = () => {
            document.querySelectorAll('.rec-tipo-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('recTipoDoc').value = btn.dataset.tipo;
        };
    });
    // Seleccionar DNI por defecto
    document.querySelector('[data-tipo="1"]')?.click();

    document.getElementById('recVolver').onclick = () => _recPaso2();
    document.getElementById('recSiguiente').onclick = async () => {
        const tipo = document.getElementById('recTipoDoc').value;
        const nro  = document.getElementById('recVal').textContent.trim();
        if (!tipo) { _recPaso3('Seleccioná el tipo de documento.'); return; }
        if (!nro)  { _recPaso3('Ingresá el número de documento.'); return; }
        const btn = document.getElementById('recSiguiente');
        btn.disabled = true; btn.textContent = 'Verificando...';
        const r = await recAPI('rec_verificar_conductor', { tipo_doc: tipo, nro_doc: nro });
        if (r.ok) {
            _recState.conductorId     = r.conductor_id;
            _recState.conductorNombre = r.nombre;
            _recState.celular         = r.celular || '';
            _recPaso4();
        } else {
            _recPaso3(r.error || 'Conductor no encontrado.');
        }
    };
}

// ══════════════════════════════════════════════════════════════════
//  PASO 4 — Celular conductor
// ══════════════════════════════════════════════════════════════════
function _recPaso4(error = '') {
    menuGrid.innerHTML = `
        <div class="rec-wrap" id="recWrap">
            <div class="rec-titulo">Recepción de Transportes</div>
            <div class="rec-paso-label">Paso 4 de 5 — Número de celular</div>
            ${error ? `<div class="rec-error">${error}</div>` : ''}
            <div class="rec-conductor-nombre">Conductor: <strong>${_recState.conductorNombre}</strong></div>
            <div class="rec-subtitulo">Verifique/complete su número de celular:</div>
            <input type="tel" id="recCelular" class="rec-input-cel"
                   value="${_recState.celular}"
                   placeholder="Ej: 3462123456"/>
            <div class="rec-acciones">
                <button class="rec-btn rec-btn-volver" id="recVolver">← Volver</button>
                <button class="rec-btn rec-btn-ok" id="recSiguiente">Continuar →</button>
            </div>
        </div>`;

    _recInjectStyles();
    document.getElementById('recVolver').onclick = () => _recPaso3();
    document.getElementById('recSiguiente').onclick = async () => {
        const cel = document.getElementById('recCelular').value.trim();
        if (!cel) { _recPaso4('Ingresá un número de celular.'); return; }
        const btn = document.getElementById('recSiguiente');
        btn.disabled = true; btn.textContent = 'Guardando...';
        await recAPI('rec_actualizar_celular', { conductor_id: _recState.conductorId, celular: cel });
        _recState.celular = cel;
        _recPaso5();
    };
}

// ══════════════════════════════════════════════════════════════════
//  PASO 5 — Planta + Tipo de movimiento
// ══════════════════════════════════════════════════════════════════
function _recPaso5(error = '') {
    menuGrid.innerHTML = `
        <div class="rec-wrap" id="recWrap">
            <div class="rec-titulo">Recepción de Transportes</div>
            <div class="rec-paso-label">Paso 5 de 5 — Planta y Tipo de movimiento</div>
            ${error ? `<div class="rec-error">${error}</div>` : ''}
            <div class="rec-conductor-nombre">
                🚛 <strong>${_recState.patente}</strong>
                ${_recState.patenteAcoplado ? ` + <strong>${_recState.patenteAcoplado}</strong>` : ''}
                &nbsp;·&nbsp; ${_recState.conductorNombre}
            </div>

            <div class="rec-seleccion-grid">
                <div class="rec-seccion">
                    <div class="rec-subtitulo">Planta:</div>
                    <div class="rec-sel-btns">
                        <button class="rec-sel-btn" data-group="planta" data-val="N">🏭 Nacional</button>
                        <button class="rec-sel-btn" data-group="planta" data-val="F">🏗️ Fiscal</button>
                    </div>
                    <input type="hidden" id="recPlanta" value="">
                </div>
                <div class="rec-seccion">
                    <div class="rec-subtitulo">Tipo de movimiento:</div>
                    <div class="rec-sel-btns">
                        <button class="rec-sel-btn entrada" data-group="entrada" data-val="1">⬇ Ingreso de mercadería</button>
                        <button class="rec-sel-btn salida"  data-group="entrada" data-val="0">⬆ Retiro de mercadería</button>
                    </div>
                    <input type="hidden" id="recEntrada" value="">
                </div>
            </div>

            <div class="rec-acciones">
                <button class="rec-btn rec-btn-volver" id="recVolver">← Volver</button>
                <button class="rec-btn rec-btn-ok rec-btn-grande" id="recConfirmar">✔ Confirmar ingreso</button>
            </div>
        </div>`;

    _recInjectStyles();
    document.getElementById('recVolver').onclick = () => _recPaso4();

    document.querySelectorAll('.rec-sel-btn').forEach(btn => {
        btn.onclick = () => {
            const group = btn.dataset.group;
            document.querySelectorAll(`[data-group="${group}"]`).forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(group === 'planta' ? 'recPlanta' : 'recEntrada').value = btn.dataset.val;
        };
    });

    document.getElementById('recConfirmar').onclick = async () => {
        const planta  = document.getElementById('recPlanta').value;
        const entrada = document.getElementById('recEntrada').value;
        if (!planta)  { _recPaso5('Seleccioná la planta.'); return; }
        if (entrada === '') { _recPaso5('Seleccioná el tipo de movimiento.'); return; }

        const btn = document.getElementById('recConfirmar');
        btn.disabled = true; btn.textContent = 'Procesando...';

        const r = await recAPI('rec_registrar_ingreso', {
            patente:         _recState.patente,
            patente_acoplado:_recState.patenteAcoplado,
            conductor_id:    _recState.conductorId,
            planta,
            entrada:         parseInt(entrada),
        });

        if (r.error && !r.estado) {
            _recPaso5(r.error);
            return;
        }
        _recResultado(r);
    };
}

// ══════════════════════════════════════════════════════════════════
//  RESULTADO
// ══════════════════════════════════════════════════════════════════
function _recResultado(r) {
    const estado = r.estado; // 'asignado' | 'en_espera' | 'ya_ingresado'

    const config = {
        asignado:     { icon:'✅', cls:'rec-res-ok',      titulo:'Box asignado',               color:'#22c55e' },
        en_espera:    { icon:'⏳', cls:'rec-res-espera',   titulo:'En lista de espera',         color:'#f59e0b' },
        ya_ingresado: { icon:'⚠️', cls:'rec-res-aviso',   titulo:'Ya se encuentra ingresado',  color:'#f97316' },
    }[estado] || { icon:'✅', cls:'rec-res-ok', titulo:'Registrado', color:'#22c55e' };

    const detalle = estado === 'asignado'
        ? `Box asignado: <strong style="font-size:2.5rem;color:${config.color}">${r.box_numero}</strong>`
        : estado === 'en_espera'
            ? 'No hay boxes disponibles. El transporte quedó en lista de espera.'
            : 'El vehículo ya se encuentra registrado en planta. Consulte en portería.';

    menuGrid.innerHTML = `
        <div class="rec-wrap rec-resultado-wrap" id="recWrap">
            <div class="rec-titulo">Recepción de Transportes</div>
            <div class="rec-resultado ${config.cls}">
                <div class="rec-res-icon">${config.icon}</div>
                <div class="rec-res-titulo">${config.titulo}</div>
                <div class="rec-conductor-nombre" style="margin:8px 0">
                    🚛 <strong>${_recState.patente}</strong>
                    ${_recState.patenteAcoplado ? ` + ${_recState.patenteAcoplado}` : ''}
                    &nbsp;·&nbsp; ${_recState.conductorNombre}
                </div>
                <div class="rec-res-detalle">${detalle}</div>
            </div>
            <div class="rec-countdown" id="recCountdown">Volviendo al inicio en <strong id="recSecs">30</strong>s</div>
            <button class="rec-btn rec-btn-ok rec-btn-grande" id="recNuevo" style="margin-top:24px">
                ← Nueva recepción
            </button>
        </div>`;

    _recInjectStyles();
    document.getElementById('recNuevo').onclick = () => renderRecepcion();

    // Notificar al mapa SVG y al módulo de cargas si están activos
    if (r.estado === 'asignado' || r.estado === 'en_espera') {
        if (typeof bxRefrescarDesdeExterno === 'function') bxRefrescarDesdeExterno();
        if (typeof cargasRefrescarSiActivo  === 'function') cargasRefrescarSiActivo();
    }

    // Countdown 30s
    let secs = 30;
    const secsEl = document.getElementById('recSecs');
    const timer = setInterval(() => {
        secs--;
        if (secsEl) secsEl.textContent = secs;
        if (secs <= 0) { clearInterval(timer); renderRecepcion(); }
    }, 1000);
}

// ══════════════════════════════════════════════════════════════════
//  HELPERS — Teclados
// ══════════════════════════════════════════════════════════════════
function _recTeclado() {
    const filas = ['QWERTYUIOP', 'ASDFGHJKL', 'ZXCVBNM'];
    const numRows = [[1,2,3],[4,5,6],[7,8,9],[0]];
    return `
        <div class="rec-teclado-wrap">
            <div class="rec-teclado-letras">
                ${filas.map(f => `
                    <div class="rec-fila">
                        ${f.split('').map(k => `<button class="rec-key" data-key="${k}">${k}</button>`).join('')}
                    </div>`).join('')}
            </div>
            <div class="rec-teclado-nums">
                ${numRows.map(row => `
                    <div class="rec-fila">
                        ${row.map(n => `<button class="rec-key" data-key="${n}">${n}</button>`).join('')}
                    </div>`).join('')}
            </div>
        </div>`;
}

function _recTecladoNum() {
    const rows = [[1,2,3],[4,5,6],[7,8,9],[0]];
    return `
        <div class="rec-teclado-solo-num">
            ${rows.map(row => `
                <div class="rec-fila">
                    ${row.map(n => `<button class="rec-key-num" data-key="${n}">${n}</button>`).join('')}
                </div>`).join('')}
        </div>`;
}

function _recBindTeclado(targetId) {
    const el = document.getElementById(targetId);
    document.querySelectorAll('.rec-key').forEach(btn => {
        btn.onclick = () => {
            const k = btn.dataset.key;
            if (!k) return;
            el.textContent += k;
        };
    });
    document.getElementById('recBorrar')?.addEventListener('click', () => {
        el.textContent = el.textContent.slice(0, -1);
    });
    document.getElementById('recLimpiar')?.addEventListener('click', () => {
        el.textContent = '';
    });
}

function _recBindTecladoNum(targetId) {
    const el = document.getElementById(targetId);
    document.querySelectorAll('.rec-key-num').forEach(btn => {
        btn.onclick = () => { el.textContent += btn.dataset.key; };
    });
    document.getElementById('recBorrar')?.addEventListener('click', () => {
        el.textContent = el.textContent.slice(0, -1);
    });
    document.getElementById('recLimpiar')?.addEventListener('click', () => {
        el.textContent = '';
    });
}

// ══════════════════════════════════════════════════════════════════
//  ESTILOS
// ══════════════════════════════════════════════════════════════════
function _recInjectStyles() {
    if (document.getElementById('rec-styles')) return;
    const s = document.createElement('style');
    s.id = 'rec-styles';
    s.textContent = `
        /* ── Contenedor principal ── */
        .rec-wrap {
            display: flex; flex-direction: column; align-items: center;
            width: 100%; min-height: calc(100dvh - 72px);
            padding: 24px 16px; box-sizing: border-box;
            gap: 16px;
        }
        .rec-titulo {
            font-size: 2rem; font-weight: 800; letter-spacing: .5px;
            color: var(--color-text-primary, #fff);
        }
        .rec-paso-label {
            font-size: .85rem; font-weight: 600; color: rgba(255,255,255,.5);
            letter-spacing: .3px;
        }
        .rec-opcional { font-weight: 400; opacity: .7; }
        .rec-error {
            background: rgba(239,68,68,.15); border: 1px solid rgba(239,68,68,.4);
            color: #fca5a5; border-radius: 10px; padding: 10px 20px;
            font-size: .9rem; font-weight: 600; text-align: center;
        }
        .rec-conductor-nombre {
            font-size: 1rem; color: rgba(255,255,255,.7); text-align: center;
        }
        .rec-subtitulo {
            font-size: 1.1rem; font-weight: 700; color: rgba(255,255,255,.8);
            text-align: center;
        }

        /* ── Display valor ingresado ── */
        .rec-display {
            font-size: 2rem; color: rgba(255,255,255,.2);
            font-weight: 700; letter-spacing: 4px;
        }
        .rec-display-val {
            min-width: 320px; min-height: 60px;
            background: rgba(255,255,255,.08);
            border: 2px solid rgba(255,255,255,.2);
            border-radius: 12px; text-align: center;
            font-size: 2.2rem; font-weight: 800;
            color: #fff; letter-spacing: 6px;
            padding: 8px 24px; line-height: 1.2;
        }
        .rec-display-num { letter-spacing: 4px; font-size: 2rem; }

        /* ── Teclado QWERTY ── */
        .rec-teclado-wrap {
            display: flex; gap: 32px; align-items: flex-start;
            flex-wrap: wrap; justify-content: center;
        }
        .rec-teclado-letras, .rec-teclado-nums { display: flex; flex-direction: column; gap: 6px; }
        .rec-teclado-solo-num { display: flex; flex-direction: column; gap: 8px; align-items: center; }
        .rec-fila { display: flex; gap: 6px; justify-content: center; }

        .rec-key {
            width: 56px; height: 56px; border-radius: 10px;
            border: 1px solid rgba(255,255,255,.2);
            background: rgba(37,99,235,.6);
            color: #fff; font-size: 1.2rem; font-weight: 700;
            cursor: pointer; transition: background .1s;
        }
        .rec-key:hover, .rec-key:active { background: rgba(37,99,235,.9); }

        .rec-key-num {
            width: 80px; height: 70px; border-radius: 12px;
            border: 1px solid rgba(255,255,255,.2);
            background: rgba(37,99,235,.6);
            color: #fff; font-size: 1.6rem; font-weight: 700;
            cursor: pointer; transition: background .1s;
        }
        .rec-key-num:hover, .rec-key-num:active { background: rgba(37,99,235,.9); }

        /* ── Botones de acción ── */
        .rec-acciones {
            display: flex; gap: 12px; flex-wrap: wrap; justify-content: center;
            margin-top: 8px;
        }
        .rec-btn {
            height: 60px; padding: 0 28px; border-radius: 12px;
            font-size: 1.1rem; font-weight: 700; cursor: pointer;
            border: none; transition: opacity .15s;
        }
        .rec-btn:disabled { opacity: .5; cursor: default; }
        .rec-btn-back   { background: #ef4444; color: #fff; width: 80px; }
        .rec-btn-clear  { background: #f59e0b; color: #fff; width: 80px; }
        .rec-btn-ok     { background: #22c55e; color: #fff; min-width: 200px; }
        .rec-btn-skip   { background: #6b7280; color: #fff; }
        .rec-btn-volver { background: transparent; color: rgba(255,255,255,.6);
                          border: 1px solid rgba(255,255,255,.2); }
        .rec-btn-grande { height: 72px; font-size: 1.3rem; min-width: 280px; }

        /* ── Selección planta / tipo ── */
        .rec-seleccion-grid {
            display: flex; gap: 48px; flex-wrap: wrap; justify-content: center;
        }
        .rec-seccion { display: flex; flex-direction: column; align-items: center; gap: 12px; }
        .rec-sel-btns { display: flex; gap: 12px; flex-wrap: wrap; justify-content: center; }
        .rec-sel-btn {
            min-width: 180px; height: 80px; border-radius: 14px;
            font-size: 1.1rem; font-weight: 700; cursor: pointer;
            border: 2px solid rgba(255,255,255,.2);
            background: rgba(255,255,255,.06); color: rgba(255,255,255,.8);
            transition: all .15s;
        }
        .rec-sel-btn.active {
            border-color: #36b0c9; background: rgba(54,176,201,.25); color: #fff;
        }
        .rec-sel-btn.entrada.active { border-color: #22c55e; background: rgba(34,197,94,.2); }
        .rec-sel-btn.salida.active  { border-color: #f59e0b; background: rgba(245,158,11,.2); }

        /* ── Tipo documento ── */
        .rec-tipo-btns { display: flex; gap: 12px; flex-wrap: wrap; justify-content: center; }
        .rec-tipo-btn {
            min-width: 140px; height: 64px; border-radius: 12px;
            font-size: 1.05rem; font-weight: 700; cursor: pointer;
            border: 2px solid rgba(255,255,255,.2);
            background: rgba(255,255,255,.06); color: rgba(255,255,255,.8);
            transition: all .15s;
        }
        .rec-tipo-btn.active { border-color: #36b0c9; background: rgba(54,176,201,.25); color: #fff; }

        /* ── Input celular ── */
        .rec-input-cel {
            width: 360px; height: 64px; border-radius: 12px;
            border: 2px solid rgba(255,255,255,.2);
            background: rgba(255,255,255,.08); color: #fff;
            font-size: 1.6rem; font-weight: 700; text-align: center;
            padding: 0 16px; letter-spacing: 2px;
        }
        .rec-input-cel:focus { outline: none; border-color: #36b0c9; }

        /* ── Resultado ── */
        .rec-resultado {
            border-radius: 20px; padding: 36px 48px;
            text-align: center; display: flex; flex-direction: column;
            align-items: center; gap: 12px; min-width: 360px;
        }
        .rec-res-ok     { background: rgba(34,197,94,.12);  border: 2px solid rgba(34,197,94,.4);  }
        .rec-res-espera { background: rgba(245,158,11,.12); border: 2px solid rgba(245,158,11,.4); }
        .rec-res-aviso  { background: rgba(249,115,22,.12); border: 2px solid rgba(249,115,22,.4); }
        .rec-res-icon   { font-size: 4rem; line-height: 1; }
        .rec-res-titulo { font-size: 1.8rem; font-weight: 800; color: #fff; }
        .rec-res-detalle { font-size: 1.1rem; color: rgba(255,255,255,.75); text-align: center; }
        .rec-countdown  { font-size: .85rem; color: rgba(255,255,255,.4); margin-top: 8px; }

        @media (max-width: 700px) {
            .rec-key { width: 44px; height: 44px; font-size: 1rem; }
            .rec-teclado-wrap { gap: 16px; }
            .rec-seleccion-grid { gap: 24px; }
        }
    `;
    document.head.appendChild(s);
}
