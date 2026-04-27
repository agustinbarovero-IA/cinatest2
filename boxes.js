/**
 * ══════════════════════════════════════════════════════════════════
 *  boxes.js — Módulo Boxes + Troneras del nuevo sistema CINA
 *
 *  Agregar en index.php:  <script src="boxes.js"></script>
 *
 *  Funciones públicas:
 *    renderMapaBoxes()      — mapa SVG (desde DASHBOARD → MAPA DE BOXES)
 *    renderBoxesCrud()      — índice CRUD (desde TRANSPORTE → BOXES)
 *    renderTronerasCrud()   — índice CRUD (desde TRANSPORTE → TRONERAS)
 *
 *  Depende de:  API_EQUIPOS_URL, menuGrid, setHeader, historyStack
 * ══════════════════════════════════════════════════════════════════
 */

// ── Helpers de fetch ─────────────────────────────────────────────
async function bxGet(action, params = {}) {
    const url = new URL(API_EQUIPOS_URL, location.origin);
    url.searchParams.set('action', action);
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
    const r = await fetch(url, { credentials: 'same-origin' });
    return r.json();
}
async function bxPost(action, body = {}) {
    const r = await fetch(`${API_EQUIPOS_URL}?action=${action}`, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    });
    return r.json();
}

// ── Toast ────────────────────────────────────────────────────────
function bxToast(msg, tipo = 'ok') {
    const el = document.createElement('div');
    el.className = `bx-toast bx-toast-${tipo}`;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3500);
}

// ══════════════════════════════════════════════════════════════════
//   MAPA DE BOXES — SVG interactivo (migrado de _mapBoxesTroneras.php)
// ══════════════════════════════════════════════════════════════════
let _bxEstado        = {};   // { 'B1': { estado, patente, entrada, carga_numero }, 'T1': {...} }
let _bxLayout        = [];   // items del mapa
let _bxPlanta        = 'N';  // planta activa
let _bxRefreshId     = null; // setInterval handle
let _bxTimerId       = null; // setInterval handle para timers de troneras
let _bxRefreshStop   = null; // setTimeout para detener auto-refresh

async function renderMapaBoxes(plantaInicial = 'N') {
    setHeader('MAPA DE BOXES');
    historyStack.push({ fn: () => renderMapaBoxes(_bxPlanta) });
    if (typeof showMetaPanel === 'function') showMetaPanel(false);
    menuGrid.className = 'menu-grid full-width';
    _bxPlanta = plantaInicial;

    menuGrid.innerHTML = `
        <div class="bx-mapa-wrap" id="bxMapaWrap">
            <div class="bx-panel-header">
                <form id="bxFormPlanta" class="bx-form-planta">
                    <table class="bx-filter-table">
                        <tbody><tr>
                            <th>Planta</th>
                            <td>
                                <select name="planta" id="bxSelPlanta" class="bx-select" onchange="bxCambiarPlanta(this.value)">
                                    <option value="">— Todas —</option>
                                    <option value="N" ${_bxPlanta==='N'?'selected':''}>Nacional</option>
                                    <option value="F" ${_bxPlanta==='F'?'selected':''}>Fiscal</option>
                                </select>
                            </td>
                        </tr></tbody>
                    </table>
                </form>
            </div>

            <div class="bx-panel-body">
                <!-- Leyenda y botón refresh -->
                <div class="bx-toolbar">
                    <div class="bx-leyenda">
                        <span class="bx-ley-item"><span class="bx-ley-dot" style="background:white;border:2px solid green"></span>Libre</span>
                        <span class="bx-ley-item"><span class="bx-ley-dot" style="background:#f9736f"></span>Ocupado Entrada</span>
                        <span class="bx-ley-item"><span class="bx-ley-dot" style="background:#a9b3ef"></span>Ocupado Salida</span>
                    </div>
                    <button id="bxBtnRefresh" class="bx-btn bx-btn-sm">
                        ↻ Actualizar
                    </button>
                </div>

                <!-- Contenedor del SVG -->
                <div class="bx-mapa-container">
                    <svg id="bxSvg" viewBox="0 0 1450 900" style="width:100%;height:100%;display:block">
                        <g id="bxFondo"></g>
                        <g id="bxItems"></g>
                    </svg>
                    <div id="bxTip" class="bx-tip"></div>
                </div>
            </div>

            <!-- Lista de espera -->
            <div class="bx-lista-espera-wrap">
                <div class="bx-lista-header">Lista de espera</div>
                <div id="bxListaEspera" class="bx-lista-body">
                    <div class="bx-loading">Cargando...</div>
                </div>
            </div>
        </div>`;

    bxInjectStyles();

    document.getElementById('bxBtnRefresh').addEventListener('click', () => bxActualizarEstados());

    // Dibujar fondo SVG de la planta
    bxDibujarFondo(_bxPlanta);

    // Cargar layout y estados
    await bxCargarLayout(_bxPlanta);
    bxRenderItems();
    await bxActualizarEstados();

    // Auto-refresh cada 5 min, máx 2 horas
    bxIniciarAutoRefresh();

    // Timer de troneras cada segundo
    _bxTimerId = setInterval(bxActualizarTimers, 1000);
}

function bxCambiarPlanta(planta) {
    _bxPlanta = planta;
    bxDetenerAutoRefresh();
    clearInterval(_bxTimerId);
    renderMapaBoxes(planta);
}

// ── Fondo SVG de cada planta ─────────────────────────────────────
function bxDibujarFondo(planta) {
    const fondo = document.getElementById('bxFondo');
    if (!fondo) return;
    const SVG_NS = 'http://www.w3.org/2000/svg';

    if (planta === 'N' || planta === '') {
        // Playa de maniobras
        const circ = document.createElementNS(SVG_NS, 'circle');
        circ.setAttribute('cx', 500); circ.setAttribute('cy', 350); circ.setAttribute('r', 120);
        circ.setAttribute('fill', '#eee'); circ.setAttribute('stroke', '#999'); circ.setAttribute('stroke-width', 2);
        fondo.appendChild(circ);

        const txt = document.createElementNS(SVG_NS, 'text');
        txt.setAttribute('x', 430); txt.setAttribute('y', 350);
        txt.setAttribute('fill', '#999'); txt.setAttribute('font-size', 16);
        txt.textContent = 'PLAYA DE MANIOBRAS';
        fondo.appendChild(txt);

        // Frigorífico en L
        const path = document.createElementNS(SVG_NS, 'path');
        path.setAttribute('d', 'M960 10 H1150 V420 H1280 V640 H960 Z');
        path.setAttribute('fill', '#cfe8ff'); path.setAttribute('stroke', '#1e88e5'); path.setAttribute('stroke-width', 2);
        fondo.appendChild(path);

        // Depósito secos
        const rect = document.createElementNS(SVG_NS, 'rect');
        rect.setAttribute('x', 1280); rect.setAttribute('y', 310); rect.setAttribute('width', 80); rect.setAttribute('height', 330);
        rect.setAttribute('fill', '#2196f3'); rect.setAttribute('stroke', '#0d47a1'); rect.setAttribute('stroke-width', 2);
        fondo.appendChild(rect);

        const lbl = document.createElementNS(SVG_NS, 'text');
        lbl.setAttribute('x', 1090); lbl.setAttribute('y', 530);
        lbl.setAttribute('fill', '#1565c0'); lbl.setAttribute('font-size', 16); lbl.setAttribute('font-weight', 'bold');
        lbl.textContent = 'PLANTA NACIONAL';
        fondo.appendChild(lbl);

    } else {
        // Fiscal
        const circ = document.createElementNS(SVG_NS, 'circle');
        circ.setAttribute('cx', 250); circ.setAttribute('cy', 550); circ.setAttribute('r', 120);
        circ.setAttribute('fill', '#eee'); circ.setAttribute('stroke', '#999'); circ.setAttribute('stroke-width', 2);
        fondo.appendChild(circ);

        const tc = document.createElementNS(SVG_NS, 'text');
        tc.setAttribute('x', 185); tc.setAttribute('y', 556);
        tc.setAttribute('fill', '#999'); tc.setAttribute('font-size', 14);
        tc.textContent = 'PLAYA DE MANIOBRAS';
        fondo.appendChild(tc);

        const rectF = document.createElementNS(SVG_NS, 'rect');
        rectF.setAttribute('x', 730); rectF.setAttribute('y', 400);
        rectF.setAttribute('width', 300); rectF.setAttribute('height', 200);
        rectF.setAttribute('fill', '#cfe8ff'); rectF.setAttribute('stroke', '#1e88e5'); rectF.setAttribute('stroke-width', 2);
        rectF.setAttribute('transform', 'rotate(45, 1000, 350)');
        fondo.appendChild(rectF);

        const lblF = document.createElementNS(SVG_NS, 'text');
        lblF.setAttribute('x', 820); lblF.setAttribute('y', 540);
        lblF.setAttribute('fill', '#1565c0'); lblF.setAttribute('font-size', 16); lblF.setAttribute('font-weight', 'bold');
        lblF.setAttribute('transform', 'rotate(45, 1040, 360)');
        lblF.textContent = 'PLANTA FISCAL';
        fondo.appendChild(lblF);
    }
}

// ── Cargar boxes y troneras de la planta ─────────────────────────
async function bxCargarLayout(planta) {
    _bxLayout = [];
    const data = await bxGet('box_estado_general', { planta: planta || 'N' });

    const todosItems = [
        ...(data.boxes    || []).map(b => ({ fila: parseFloat(b.fila), columna: parseFloat(b.columna) })),
        ...(data.troneras || []).map(t => ({ fila: parseFloat(t.fila), columna: parseFloat(t.columna) })),
    ].filter(i => !isNaN(i.fila) && !isNaN(i.columna));

    if (!todosItems.length) return;

    const W_UTIL = 1310, H_UTIL = 760, MARGIN_X = 70, MARGIN_Y = 70;
    const col_min = Math.min(...todosItems.map(i => i.columna));
    const col_max = Math.max(...todosItems.map(i => i.columna));
    const fil_min = Math.min(...todosItems.map(i => i.fila));
    const fil_max = Math.max(...todosItems.map(i => i.fila));
    const scale = Math.min((W_UTIL - 120) / (col_max - col_min || 1), (H_UTIL - 26) / (fil_max - fil_min || 1));
    const ox = MARGIN_X + (W_UTIL - (col_max - col_min) * scale - 120) / 2;
    const oy = MARGIN_Y + (H_UTIL - (fil_max - fil_min) * scale - 26) / 2;
    const toX = col => ox + (parseFloat(col) - col_min) * scale;
    const toY = fil => oy + (parseFloat(fil) - fil_min) * scale;
    const boxW = Math.max(70, Math.min(160, scale * 2.5));
    const boxH = Math.max(16, Math.min(30, scale * 0.65));

    (data.boxes || []).forEach(b => {
        _bxLayout.push({
            id: 'B'+b.numero, tipo:'box', tipoBox:b.tipo,
            x: toX(b.columna), y: toY(b.fila), w: boxW, h: boxH,
            r: parseFloat(b.grado)||0, fila: parseFloat(b.fila),
            columna: parseFloat(b.columna), nombre: 'Box '+b.numero,
        });
    });
    (data.troneras || []).forEach(t => {
        _bxLayout.push({
            id: 'T'+t.numero, tipo:'tronera',
            x: toX(t.columna), y: toY(t.fila), w: boxW, h: boxH,
            r: parseFloat(t.grado)||0, fila: parseFloat(t.fila),
            columna: parseFloat(t.columna),
        });
    });
    _bxEstado = {};
    (data.boxes    || []).forEach(b => { _bxEstado['B' + b.numero] = b; });
    (data.troneras || []).forEach(t => { _bxEstado['T' + t.numero] = t; });
}

// ── Crear elementos SVG vacíos ────────────────────────────────────
function bxRenderItems() {
    const g = document.getElementById('bxItems');
    if (!g) return;
    g.innerHTML = '';

    const SVG_NS = 'http://www.w3.org/2000/svg';
    _bxLayout.forEach(item => {
        const grupo = document.createElementNS(SVG_NS, 'g');
        grupo.setAttribute('transform', `translate(${item.x},${item.y}) rotate(${item.r || 0})`);
        grupo.setAttribute('data-id', item.id);
        grupo.style.cursor = 'pointer';
        grupo.addEventListener('click', () => bxMostrarDetalle(item.id));
        g.appendChild(grupo);
    });

    bxPintarEstados();
}

// ── Pintar cada item según su estado ─────────────────────────────
function bxPintarEstados() {
    _bxLayout.forEach(item => {
        const grupo = document.querySelector(`#bxItems [data-id="${item.id}"]`);
        if (!grupo) return;

        if (item.tipo === 'tronera') {
            bxPintarTronera(item, grupo);
        } else {
            bxPintarBox(item, grupo);
        }
    });
}

function bxPintarBox(item, group) {
    const est    = _bxEstado[item.id] || {};
    const libre  = !est.estado || est.estado === 'libre';
    const color  = est.entrada === '1' ? '#f9736f' : '#a9b3ef';
    const rot    = item.r || 0;
    const adjust = (rot > 90 || rot < -90) ? 180 : 0;
    const xtext  = (rot > 90 || rot < -90) ? -25 : -60;

    if (libre) {
        const esEnchufe = item.tipoBox === 'E';
        if (!esEnchufe) {
            group.innerHTML = `
                <rect x="${-item.w/2}" y="${-item.h/2}" width="${item.w}" height="${item.h}"
                      fill="white" stroke="green" stroke-width="3" rx="4" ry="4"/>
                <g transform="rotate(${adjust})">
                    <text x="0" y="5" fill="black" text-anchor="middle"
                          font-size="15" font-weight="bold">${item.nombre}</text>
                </g>`;
        } else {
            const ancho = item.w * 0.6;
            group.innerHTML = `
                <rect x="${-ancho/2}" y="${-item.h/2}" width="${ancho}" height="${item.h}"
                      fill="#e7f1ff" stroke="#0d6efd" stroke-width="3" rx="6" ry="6"/>
                <rect x="-8" y="-4" width="3" height="8" fill="#0d6efd"/>
                <rect x="5"  y="-4" width="3" height="8" fill="#0d6efd"/>
                <polygon points="0,-8 -3,0 2,0 -2,8 4,-2 -1,-2" fill="#0d6efd"/>
                <g transform="rotate(${adjust})">
                    <text x="0" y="${item.h/2 + 14}" fill="#0d6efd"
                          text-anchor="middle" font-size="12" font-weight="bold">${item.nombre}</text>
                </g>`;
        }
    } else {
        const icono = item.tipoBox === 'E' ? '⚡' : '';
        group.innerHTML = `
            <rect x="${-item.w/2}" y="${-item.h/2}" width="${item.w * 0.72}" height="${item.h}" fill="${color}"/>
            <rect x="${-item.w/2 + item.w * 0.77}" y="${-item.h/2}"
                  width="${item.w * 0.20}" height="${item.h}" fill="#999"/>
            <polygon points="
                ${-item.w/2 + item.w * 0.97},${-item.h/2}
                ${-item.w/2 + item.w},${-item.h/2 + item.h * 0.2}
                ${-item.w/2 + item.w},${item.h/2 - item.h * 0.2}
                ${-item.w/2 + item.w * 0.97},${item.h/2}
            " fill="#777"/>
            <g transform="rotate(${adjust})">
                <text x="${xtext}" y="4" font-size="15" text-anchor="left">
                    <tspan font-weight="bold">${icono}${item.id}</tspan>
                    <tspan dx="4">${est.patente || ''}</tspan>
                </text>
            </g>`;
    }

    // Tooltip con carga
    if (est.carga_numero) {
        group.setAttribute('title', `Carga N° ${est.carga_numero}`);
    }
}

function bxPintarTronera(item, group) {
    const t      = _bxEstado[item.id] || {};
    const libre  = t.estado === 'L' || !t.estado;
    const color  = t.entrada === '1' ? '#f9736f' : '#a9b3ef';
    const colorL = t.entrada === '1' ? '#ff7976' : '#c9d3ff';
    const sizeW = 25, sizeH = 25;
    const halfW = sizeW / 2, halfH = sizeH / 2;
    const inv = -(item.r || 0);

    group.innerHTML = `
        <rect x="${-halfW}" y="${-halfH}" width="${sizeW}" height="${sizeH}" rx="4" ry="4"
              fill="${libre ? '#8cE88c' : colorL}" stroke="#333" stroke-width="1.5"/>
        <polygon points="${halfW-2},0 ${-halfW+2},${-halfH+2} ${-halfW+2},${halfH-2}"
                 fill="white" opacity="0.9"/>
        <g transform="rotate(${inv})">
            <text x="0" y="3" text-anchor="middle" fill="#555" font-size="15" font-weight="bold">
                ${item.id}
            </text>
            <rect x="-20" y="12" width="40" height="16" rx="3" ry="3"
                  fill="rgba(255,255,255,0.7)" class="bx-timer-bg" data-id="${item.id}"/>
            <text x="0" y="24" text-anchor="middle" fill="#000"
                  font-size="12" class="bx-timer" data-id="${item.id}">0:00</text>
        </g>`;

    // Camión si está ocupada
    if (!libre) {
        const SVG_NS = 'http://www.w3.org/2000/svg';
        const camion = document.createElementNS(SVG_NS, 'g');
        camion.setAttribute('transform', `translate(100, 0)`);
        const rot    = item.r || 0;
        const adjust = (rot > 90 && rot < 270) ? 180 : 0;
        const xtext  = (rot > 90 && rot < 270) ? -25 : -63;
        camion.innerHTML = `
            <rect x="${-item.w/2}" y="${-item.h/2}" width="${item.w * 0.72}" height="${item.h}" fill="${color}"/>
            <rect x="${-item.w/2 + item.w * 0.77}" y="${-item.h/2}"
                  width="${item.w * 0.20}" height="${item.h}" fill="#999"/>
            <polygon points="
                ${-item.w/2 + item.w * 0.97},${-item.h/2}
                ${-item.w/2 + item.w},${-item.h/2 + item.h * 0.2}
                ${-item.w/2 + item.w},${item.h/2 - item.h * 0.2}
                ${-item.w/2 + item.w * 0.97},${item.h/2}
            " fill="#777"/>
            <g transform="rotate(${adjust})">
                <text x="${xtext}" y="4" font-size="15" text-anchor="left">
                    <tspan font-weight="bold">${item.id}</tspan>
                    <tspan dx="4">${t.patente || ''}</tspan>
                </text>
            </g>`;
        group.appendChild(camion);
    }
}

// ── Fetch de estados y repintado ─────────────────────────────────
async function bxActualizarEstados() {
    const data = await bxGet('box_estado_general', { planta: _bxPlanta || 'N' });

    _bxEstado = {};
    (data.boxes    || []).forEach(b => { _bxEstado['B' + b.numero] = b; });
    (data.troneras || []).forEach(t => {
        _bxEstado['T' + t.numero] = { ...t, inicio_ts: t.inicio_operacion };
    });

    bxPintarEstados();
    await bxCargarListaEspera();
}

// ── Timers de troneras (cada segundo, solo JS) ───────────────────
function bxActualizarTimers() {
    document.querySelectorAll('.bx-timer').forEach(el => {
        const id   = el.getAttribute('data-id');
        const info = _bxEstado[id];

        if (!info || !info.entrada) { el.textContent = ''; return; }
        const ts = Number(info.inicio_ts || info.inicio_operacion);
        if (!ts || isNaN(ts) || ts <= 0) { el.textContent = '0:00'; return; }

        const diff = Math.floor(Date.now() / 1000) - ts;
        if (diff < 0) { el.textContent = '0:00'; return; }
        const min = Math.floor(diff / 60);
        const sec = diff % 60;
        el.textContent = `${min}:${sec.toString().padStart(2, '0')}`;
    });
}

// ── Lista de espera ──────────────────────────────────────────────
async function bxCargarListaEspera() {
    const wrap = document.getElementById('bxListaEspera');
    if (!wrap) return;

    const data = await bxGet('lista_espera', { planta: _bxPlanta || 'N' });
    const espera = Array.isArray(data) ? data : [];

    if (!espera.length) {
        wrap.innerHTML = '<p class="bx-lista-empty">No hay transportes en espera.</p>';
        return;
    }

    // Colores de prioridad: 1=rojo, 10=azul
    const prioColor = (p) => {
        const hue = Math.round(0 + (210 - 0) * ((p - 1) / 9));
        return `hsl(${hue}, 85%, 45%)`;
    };

    wrap.innerHTML = `
        <table class="bx-espera-table">
            <thead>
                <tr><th>#</th><th>Carga</th><th>Patente</th><th>Llegada</th><th>Prioridad</th></tr>
            </thead>
            <tbody>
                ${espera.map((tb, i) => {
                    const prio  = parseInt(tb.prioridad) || 5;
                    const color = prioColor(prio);
                    const hora  = tb.fecha_llegada
                        ? new Date(tb.fecha_llegada).toLocaleTimeString('es-AR', { hour:'2-digit', minute:'2-digit' })
                        : '—';
                    return `<tr>
                        <td>${i + 1}</td>
                        <td><strong>#${tb.carga_numero}</strong></td>
                        <td>${tb.patente || '—'}</td>
                        <td>${hora}</td>
                        <td>
                            ${prio < 10 ? `<button class="bx-prio-btn"
                                onclick="bxCambiarPrioridad(${tb.id}, 'down')">↓</button>` : '<span class="bx-prio-gap"></span>'}
                            <span class="bx-prio-badge" style="background:${color}">${prio}</span>
                            ${prio > 1  ? `<button class="bx-prio-btn"
                                onclick="bxCambiarPrioridad(${tb.id}, 'up')">↑</button>`   : '<span class="bx-prio-gap"></span>'}
                        </td>
                    </tr>`;
                }).join('')}
            </tbody>
        </table>`;
}

async function bxCambiarPrioridad(tbId, dir) {
    await bxPost('box_cambiar_prioridad', { transporte_box_id: tbId, direccion: dir });
    bxCargarListaEspera();
}

// ── Detalle al hacer click en un item ────────────────────────────
function bxMostrarDetalle(id) {
    const est = _bxEstado[id];
    if (!est) return;

    const tipo   = id.startsWith('T') ? 'Tronera' : 'Box';
    const titulo = `${tipo} ${id.slice(1)}`;

    const lineas = [];
    if (est.carga_numero) lineas.push(`Carga: #${est.carga_numero}`);
    if (est.patente)      lineas.push(`Patente: ${est.patente}`);
    if (est.estado)       lineas.push(`Estado: ${est.estado}`);
    if (est.entrada !== null && est.entrada !== undefined) {
        lineas.push(est.entrada === '1' ? 'Tipo: Entrada' : 'Tipo: Salida');
    }

    const texto = lineas.length ? lineas.join('\n') : 'Libre';
    bxToast(`${titulo} — ${texto}`, est.estado === 'libre' || est.estado === 'L' ? 'ok' : 'info');
}

// ── Auto-refresh ─────────────────────────────────────────────────
function bxIniciarAutoRefresh() {
    bxDetenerAutoRefresh();
    _bxRefreshId   = setInterval(bxActualizarEstados, 5 * 60 * 1000);
    _bxRefreshStop = setTimeout(() => { bxDetenerAutoRefresh(); }, 2 * 60 * 60 * 1000);
}
function bxDetenerAutoRefresh() {
    clearInterval(_bxRefreshId);
    clearTimeout(_bxRefreshStop);
    _bxRefreshId = null; _bxRefreshStop = null;
}


// ══════════════════════════════════════════════════════════════════
//   CRUD BOXES
// ══════════════════════════════════════════════════════════════════
let _bxCrudState = { lista: [], filtroPlanta: '', filtroTipo: '', filtroActivo: '1' };

async function renderBoxesCrud() {
    setHeader('BOXES', 'Gestión de boxes y enchufes');
    historyStack.push({ fn: renderBoxesCrud });
    menuGrid.className = 'menu-grid full-width';

    menuGrid.innerHTML = `
        <div class="bx-wrap" id="bxCrudWrap">
            <div class="bx-toolbar">
                <div class="bx-filters">
                    <select id="bxCFPlanta" class="bx-select">
                        <option value="">Todas las plantas</option>
                        <option value="N">Nacional</option>
                        <option value="F">Fiscal</option>
                    </select>
                    <select id="bxCFTipo" class="bx-select">
                        <option value="">Todos los tipos</option>
                        <option value="B">Box</option>
                        <option value="E">Enchufe</option>
                    </select>
                    <select id="bxCFActivo" class="bx-select">
                        <option value="1" selected>Activos</option>
                        <option value="0">Inactivos</option>
                        <option value="">Todos</option>
                    </select>
                    <button class="bx-btn bx-btn-secondary" onclick="bxCrudFiltrar()">Filtrar</button>
                </div>
                <button class="bx-btn bx-btn-primary" onclick="bxAbrirFormBox()">+ Nuevo box</button>
            </div>
            <div id="bxCrudTabla"><div class="bx-loading">Cargando...</div></div>
        </div>`;

    bxInjectStyles();
    bxCrudFiltrar();
}

async function bxCrudFiltrar() {
    _bxCrudState.filtroPlanta = document.getElementById('bxCFPlanta')?.value || '';
    _bxCrudState.filtroTipo   = document.getElementById('bxCFTipo')?.value   || '';
    _bxCrudState.filtroActivo = document.getElementById('bxCFActivo')?.value  ?? '1';

    const params = { action: 'boxes_lista' };
    if (_bxCrudState.filtroPlanta) params.planta = _bxCrudState.filtroPlanta;
    if (_bxCrudState.filtroTipo)   params.tipo   = _bxCrudState.filtroTipo;
    if (_bxCrudState.filtroActivo !== '') params.activo = _bxCrudState.filtroActivo;

    const data = await bxGet('boxes_lista', params);
    _bxCrudState.lista = Array.isArray(data) ? data : [];
    bxRenderCrudTabla();
}

function bxRenderCrudTabla() {
    const wrap = document.getElementById('bxCrudTabla');
    if (!wrap) return;

    if (!_bxCrudState.lista.length) {
        wrap.innerHTML = '<div class="bx-empty">No hay boxes con los filtros aplicados.</div>';
        return;
    }

    const badgeActivo = (a) => a
        ? `<span class="bx-badge badge-green">Sí</span>`
        : `<span class="bx-badge badge-gray">No</span>`;

    const badgeAsig = (estado) => {
        const m = { A:'Asignado', U:'En uso' };
        if (!estado) return '<span class="bx-libre">Libre</span>';
        return `<span class="bx-badge badge-blue">${m[estado] || estado}</span>`;
    };

    wrap.innerHTML = `
        <table class="bx-table" id="bxCrudTable">
            <thead>
                <tr>
                    <th>Número</th><th>Planta</th><th>Tipo</th><th>Posición</th>
                    <th>Activo</th><th>Asignación</th><th>Carga</th><th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                ${_bxCrudState.lista.map(b => `
                    <tr>
                        <td><strong>${b.numero}</strong></td>
                        <td>${b.planta_texto}</td>
                        <td>${b.tipo_texto}</td>
                        <td class="bx-pos">${b.fila}:${b.columna}:${b.grado}</td>
                        <td>${badgeActivo(b.activo)}</td>
                        <td>${badgeAsig(b.tb_estado)}</td>
                        <td>${b.tb_carga_numero ? `<strong>#${b.tb_carga_numero}</strong>` : '—'}</td>
                        <td class="bx-acciones">
                            <button class="bx-btn bx-btn-xs bx-btn-secondary"
                                    onclick="bxAbrirFormBox(${b.id})">Editar</button>
                            ${b.tb_estado === 'A' || b.tb_estado === 'U' ? `
                                <button class="bx-btn bx-btn-xs bx-btn-success"
                                        onclick="bxLiberarBox(${b.id})">Liberar</button>
                            ` : ''}
                            ${b.activo ? `
                                <button class="bx-btn bx-btn-xs bx-btn-danger"
                                        onclick="bxEliminarBox(${b.id}, '${b.numero}')">Desactivar</button>
                            ` : ''}
                        </td>
                    </tr>`).join('')}
            </tbody>
        </table>`;

    // Ordenamiento por columna
    bxAddSortableHeaders('bxCrudTable');
}

function bxAddSortableHeaders(tableId) {
    const table   = document.getElementById(tableId);
    if (!table) return;
    const headers = table.querySelectorAll('th');
    const tbody   = table.querySelector('tbody');
    let sortDir   = Array(headers.length).fill(true);

    headers.forEach((th, idx) => {
        th.style.cursor = 'pointer';
        th.addEventListener('click', () => {
            headers.forEach(h => { h.innerHTML = h.innerHTML.replace(/[▲▼]/g, ''); });
            const rows = Array.from(tbody.querySelectorAll('tr'));
            rows.sort((a, b) => {
                let cA = a.children[idx].textContent.trim();
                let cB = b.children[idx].textContent.trim();
                if (!isNaN(parseFloat(cA)) && !isNaN(parseFloat(cB))) {
                    cA = parseFloat(cA); cB = parseFloat(cB);
                }
                if (cA < cB) return sortDir[idx] ? -1 : 1;
                if (cA > cB) return sortDir[idx] ?  1 : -1;
                return 0;
            });
            th.innerHTML += sortDir[idx] ? ' ▲' : ' ▼';
            sortDir[idx] = !sortDir[idx];
            rows.forEach(r => tbody.appendChild(r));
        });
    });
}

// ── Formulario box ───────────────────────────────────────────────
function bxAbrirFormBox(id = null) {
    const box = id ? _bxCrudState.lista.find(b => b.id === id) : null;
    const es  = !!box;

    const overlay = document.createElement('div');
    overlay.className = 'bx-overlay';
    overlay.id = 'bxFormOverlay';
    overlay.innerHTML = `
        <div class="bx-modal">
            <div class="bx-modal-header">
                <h3>${es ? 'Editar' : 'Nuevo'} Box</h3>
                <button class="bx-modal-close" onclick="document.getElementById('bxFormOverlay')?.remove()">✕</button>
            </div>
            <div class="bx-modal-body">
                <div class="bx-form-grid">
                    <div class="bx-form-group">
                        <label>Número *</label>
                        <input id="bxFN" type="text" class="bx-input" value="${box?.numero||''}" placeholder="Ej: 1"/>
                    </div>
                    <div class="bx-form-group">
                        <label>Planta *</label>
                        <select id="bxFP" class="bx-input">
                            <option value="N" ${box?.planta==='N'||!box?'selected':''}>Nacional</option>
                            <option value="F" ${box?.planta==='F'?'selected':''}>Fiscal</option>
                        </select>
                    </div>
                    <div class="bx-form-group">
                        <label>Tipo *</label>
                        <select id="bxFT" class="bx-input">
                            <option value="B" ${box?.tipo==='B'||!box?'selected':''}>Box</option>
                            <option value="E" ${box?.tipo==='E'?'selected':''}>Enchufe</option>
                        </select>
                    </div>
                    <div class="bx-form-group">
                        <label>Activo</label>
                        <select id="bxFA" class="bx-input">
                            <option value="1" ${box?.activo||!box?'selected':''}>Sí</option>
                            <option value="0" ${box?.activo===0?'selected':''}>No</option>
                        </select>
                    </div>
                    <div class="bx-form-group">
                        <label>Fila</label>
                        <input id="bxFFi" type="number" class="bx-input" value="${box?.fila||0}" min="0"/>
                    </div>
                    <div class="bx-form-group">
                        <label>Columna</label>
                        <input id="bxFCo" type="number" class="bx-input" value="${box?.columna||0}" min="0"/>
                    </div>
                    <div class="bx-form-group">
                        <label>Grado (rotación)</label>
                        <input id="bxFG" type="number" class="bx-input" value="${box?.grado||0}" step="45"/>
                    </div>
                </div>
            </div>
            <div class="bx-modal-footer">
                <button class="bx-btn bx-btn-secondary" onclick="document.getElementById('bxFormOverlay')?.remove()">Cancelar</button>
                <button class="bx-btn bx-btn-primary" id="bxBtnGBox">${es?'Guardar cambios':'Crear box'}</button>
            </div>
        </div>`;
    document.body.appendChild(overlay);

    document.getElementById('bxBtnGBox').addEventListener('click', async () => {
        const payload = {
            id:      es ? id : 0,
            numero:  document.getElementById('bxFN').value.trim(),
            planta:  document.getElementById('bxFP').value,
            tipo:    document.getElementById('bxFT').value,
            fila:    parseInt(document.getElementById('bxFFi').value) || 0,
            columna: parseInt(document.getElementById('bxFCo').value) || 0,
            grado:   parseInt(document.getElementById('bxFG').value)  || 0,
            activo:  parseInt(document.getElementById('bxFA').value),
        };
        if (!payload.numero) { bxToast('El número es obligatorio', 'error'); return; }
        const btn = document.getElementById('bxBtnGBox');
        btn.disabled = true; btn.textContent = 'Guardando...';
        const res = await bxPost('box_guardar', payload);
        if (res.ok) {
            bxToast(es ? 'Box actualizado' : 'Box creado', 'ok');
            document.getElementById('bxFormOverlay')?.remove();
            bxCrudFiltrar();
        } else {
            bxToast(res.error || 'Error', 'error');
            btn.disabled = false; btn.textContent = es ? 'Guardar cambios' : 'Crear box';
        }
    });
}

async function bxLiberarBox(boxId) {
    if (!confirm('¿Liberar este box? Se asignará automáticamente al próximo en espera si lo hay.')) return;
    const res = await bxPost('box_liberar', { box_id: boxId });
    if (res.ok) {
        bxToast(res.mensaje, 'ok');
        bxCrudFiltrar();
    } else {
        bxToast(res.error || 'Error al liberar', 'error');
    }
}

async function bxEliminarBox(id, numero) {
    if (!confirm(`¿Desactivar Box ${numero}?`)) return;
    const res = await bxPost('box_eliminar', { id });
    if (res.ok) { bxToast('Box desactivado', 'ok'); bxCrudFiltrar(); }
    else bxToast(res.error || 'Error', 'error');
}


// ══════════════════════════════════════════════════════════════════
//   CRUD TRONERAS
// ══════════════════════════════════════════════════════════════════
let _trCrudState = { lista: [], filtroPlanta: '', filtroEstado: '' };

async function renderTronerasCrud() {
    setHeader('TRONERAS', 'Gestión de troneras físicas');
    historyStack.push({ fn: renderTronerasCrud });
    menuGrid.className = 'menu-grid full-width';

    menuGrid.innerHTML = `
        <div class="bx-wrap" id="trCrudWrap">
            <div class="bx-toolbar">
                <div class="bx-filters">
                    <select id="trCFPlanta" class="bx-select">
                        <option value="">Todas las plantas</option>
                        <option value="N">Nacional</option>
                        <option value="F">Fiscal</option>
                    </select>
                    <select id="trCFEstado" class="bx-select">
                        <option value="">Todos los estados</option>
                        <option value="A">Activas</option>
                        <option value="M">Mantenimiento</option>
                        <option value="I">Inactivas</option>
                    </select>
                    <button class="bx-btn bx-btn-secondary" onclick="trCrudFiltrar()">Filtrar</button>
                </div>
                <button class="bx-btn bx-btn-primary" onclick="trAbrirForm()">+ Nueva tronera</button>
            </div>
            <div id="trCrudTabla"><div class="bx-loading">Cargando...</div></div>
        </div>`;

    bxInjectStyles();
    trCrudFiltrar();
}

async function trCrudFiltrar() {
    _trCrudState.filtroPlanta = document.getElementById('trCFPlanta')?.value || '';
    _trCrudState.filtroEstado = document.getElementById('trCFEstado')?.value || '';

    const params = {};
    if (_trCrudState.filtroPlanta) params.planta = _trCrudState.filtroPlanta;
    if (_trCrudState.filtroEstado) params.estado  = _trCrudState.filtroEstado;

    const data = await bxGet('troneras_lista', params);
    _trCrudState.lista = Array.isArray(data) ? data : [];
    trRenderCrudTabla();
}

function trRenderCrudTabla() {
    const wrap = document.getElementById('trCrudTabla');
    if (!wrap) return;

    if (!_trCrudState.lista.length) {
        wrap.innerHTML = '<div class="bx-empty">No hay troneras con los filtros aplicados.</div>';
        return;
    }

    const badgeEstado = (e) => {
        const m = { A: ['Activa','badge-green'], M: ['Mantenimiento','badge-yellow'], I: ['Inactiva','badge-gray'] };
        const [txt, cls] = m[e] || [e,'badge-gray'];
        return `<span class="bx-badge ${cls}">${txt}</span>`;
    };
    const badgeAsig = (e) => {
        const m = { A:['Asignado','badge-blue'], U:['En uso','badge-orange'] };
        if (!e) return '<span class="bx-libre">Libre</span>';
        const [txt, cls] = m[e] || [e,'badge-gray'];
        return `<span class="bx-badge ${cls}">${txt}</span>`;
    };

    wrap.innerHTML = `
        <table class="bx-table" id="trCrudTable">
            <thead>
                <tr>
                    <th>Andén</th><th>Número</th><th>Planta</th><th>Posición</th>
                    <th>Estado</th><th>Asignación</th><th>Carga</th><th>Comentarios</th><th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                ${_trCrudState.lista.map(t => `
                    <tr>
                        <td>${t.anden_texto}</td>
                        <td><strong>${t.numero}</strong></td>
                        <td>${t.tipo_texto}</td>
                        <td class="bx-pos">${t.fila}:${t.columna}:${t.grado}</td>
                        <td>${badgeEstado(t.estado)}</td>
                        <td>${badgeAsig(t.tt_estado)}</td>
                        <td>${t.tt_carga_numero ? `<strong>#${t.tt_carga_numero}</strong>` : '—'}</td>
                        <td>${t.comentarios || '—'}</td>
                        <td class="bx-acciones">
                            <button class="bx-btn bx-btn-xs bx-btn-secondary"
                                    onclick="trAbrirForm(${t.id})">Editar</button>
                            ${t.tt_estado === 'A' || t.tt_estado === 'U' ? `
                                <button class="bx-btn bx-btn-xs bx-btn-warning"
                                        onclick="trDesasignar(${t.tt_carga_numero})">Desasignar</button>
                                <button class="bx-btn bx-btn-xs bx-btn-success"
                                        onclick="trLiberar(${t.tt_carga_numero})">Liberar</button>
                            ` : ''}
                            ${t.estado !== 'I' ? `
                                <button class="bx-btn bx-btn-xs bx-btn-danger"
                                        onclick="trEliminar(${t.id})">Desactivar</button>
                            ` : ''}
                        </td>
                    </tr>`).join('')}
            </tbody>
        </table>`;

    bxAddSortableHeaders('trCrudTable');
}

function trAbrirForm(id = null) {
    const t   = id ? _trCrudState.lista.find(x => x.id === id) : null;
    const es  = !!t;

    const overlay = document.createElement('div');
    overlay.className = 'bx-overlay';
    overlay.id = 'trFormOverlay';
    overlay.innerHTML = `
        <div class="bx-modal">
            <div class="bx-modal-header">
                <h3>${es ? 'Editar' : 'Nueva'} Tronera</h3>
                <button class="bx-modal-close" onclick="document.getElementById('trFormOverlay')?.remove()">✕</button>
            </div>
            <div class="bx-modal-body">
                <div class="bx-form-grid">
                    <div class="bx-form-group">
                        <label>Número *</label>
                        <input id="trFN" type="text" class="bx-input" value="${t?.numero||''}" placeholder="Ej: T1"/>
                    </div>
                    <div class="bx-form-group">
                        <label>Planta *</label>
                        <select id="trFT" class="bx-input">
                            <option value="N" ${t?.tipo==='N'||!t?'selected':''}>Nacional</option>
                            <option value="F" ${t?.tipo==='F'?'selected':''}>Fiscal</option>
                        </select>
                    </div>
                    <div class="bx-form-group">
                        <label>Andén *</label>
                        <select id="trFA" class="bx-input">
                            <option value="N" ${t?.anden==='N'?'selected':''}>Norte</option>
                            <option value="S" ${t?.anden==='S'?'selected':''}>Sur</option>
                            <option value="F" ${t?.anden==='F'?'selected':''}>Frío</option>
                            <option value="E" ${t?.anden==='E'?'selected':''}>Seco</option>
                        </select>
                    </div>
                    <div class="bx-form-group">
                        <label>Estado</label>
                        <select id="trFE" class="bx-input">
                            <option value="A" ${t?.estado==='A'||!t?'selected':''}>Activa</option>
                            <option value="M" ${t?.estado==='M'?'selected':''}>Mantenimiento</option>
                            <option value="I" ${t?.estado==='I'?'selected':''}>Inactiva</option>
                        </select>
                    </div>
                    <div class="bx-form-group">
                        <label>Fila</label>
                        <input id="trFFi" type="number" class="bx-input" value="${t?.fila||0}" min="0"/>
                    </div>
                    <div class="bx-form-group">
                        <label>Columna</label>
                        <input id="trFCo" type="number" class="bx-input" value="${t?.columna||0}" min="0"/>
                    </div>
                    <div class="bx-form-group">
                        <label>Grado</label>
                        <input id="trFG" type="number" class="bx-input" value="${t?.grado||0}" step="45"/>
                    </div>
                    <div class="bx-form-group bx-form-full">
                        <label>Comentarios</label>
                        <textarea id="trFC" rows="2" class="bx-input">${t?.comentarios||''}</textarea>
                    </div>
                </div>
            </div>
            <div class="bx-modal-footer">
                <button class="bx-btn bx-btn-secondary" onclick="document.getElementById('trFormOverlay')?.remove()">Cancelar</button>
                <button class="bx-btn bx-btn-primary" id="trBtnG">${es?'Guardar cambios':'Crear tronera'}</button>
            </div>
        </div>`;
    document.body.appendChild(overlay);

    document.getElementById('trBtnG').addEventListener('click', async () => {
        const payload = {
            id:          es ? id : 0,
            numero:      document.getElementById('trFN').value.trim(),
            tipo:        document.getElementById('trFT').value,
            anden:       document.getElementById('trFA').value,
            fila:        parseInt(document.getElementById('trFFi').value) || 0,
            columna:     parseInt(document.getElementById('trFCo').value) || 0,
            grado:       parseInt(document.getElementById('trFG').value)  || 0,
            estado:      document.getElementById('trFE').value,
            comentarios: document.getElementById('trFC').value.trim(),
        };
        if (!payload.numero) { bxToast('El número es obligatorio', 'error'); return; }
        const btn = document.getElementById('trBtnG');
        btn.disabled = true; btn.textContent = 'Guardando...';
        const res = await bxPost('tronera_guardar', payload);
        if (res.ok) {
            bxToast(es ? 'Tronera actualizada' : 'Tronera creada', 'ok');
            document.getElementById('trFormOverlay')?.remove();
            trCrudFiltrar();
        } else {
            bxToast(res.error || 'Error', 'error');
            btn.disabled = false; btn.textContent = es ? 'Guardar cambios' : 'Crear tronera';
        }
    });
}

async function trDesasignar(cargaNumero) {
    if (!confirm(`¿Cancelar asignación de carga #${cargaNumero}?`)) return;
    const res = await bxPost('tronera_desasignar', { carga_numero: cargaNumero });
    if (res.ok) { bxToast('Asignación cancelada', 'ok'); trCrudFiltrar(); }
    else bxToast(res.error || 'Error', 'error');
}
async function trLiberar(cargaNumero) {
    if (!confirm(`¿Liberar tronera de la carga #${cargaNumero}?`)) return;
    const res = await bxPost('tronera_liberar', { carga_numero: cargaNumero });
    if (res.ok) { bxToast('Tronera liberada', 'ok'); trCrudFiltrar(); }
    else bxToast(res.error || 'Error', 'error');
}
async function trEliminar(id) {
    if (!confirm('¿Desactivar esta tronera?')) return;
    const res = await bxPost('tronera_eliminar', { id });
    if (res.ok) { bxToast('Tronera desactivada', 'ok'); trCrudFiltrar(); }
    else bxToast(res.error || 'Error', 'error');
}


// ══════════════════════════════════════════════════════════════════
//   ESTILOS — inyectados una vez
// ══════════════════════════════════════════════════════════════════
function bxInjectStyles() {
    if (document.getElementById('bx-styles')) return;
    const s = document.createElement('style');
    s.id = 'bx-styles';
    s.textContent = `
        /* ── Contenedores ─────────────────────────── */
        .bx-wrap { padding:16px; }
        .bx-mapa-wrap {
            display:flex; flex-direction:column; gap:8px;
            width:100%; height:calc(100dvh - 72px);
            box-sizing:border-box; padding:6px 10px;
        }
        .bx-panel-header { flex-shrink:0; }
        .bx-panel-body   { flex:1; min-height:0; display:flex; flex-direction:column; gap:6px; }
        .bx-filter-table { border-collapse:collapse; }
        .bx-filter-table th { padding:4px 8px 4px 0; font-size:13px; }

        /* ── Toolbar ───────────────────────────────── */
        .bx-toolbar { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; flex-shrink:0; }
        .bx-filters { display:flex; gap:8px; flex-wrap:wrap; }
        .bx-leyenda { display:flex; gap:12px; flex-wrap:wrap; font-size:12px; color:var(--color-text-secondary,#666); align-items:center; }
        .bx-ley-item { display:flex; align-items:center; gap:5px; }
        .bx-ley-dot  { width:14px; height:14px; border-radius:3px; display:inline-block; }

        /* ── Mapa SVG — ocupa todo el espacio restante ── */
        .bx-mapa-container {
            position:relative; flex:1; min-height:0;
            background:#f8f9fb;
            border:1px solid #d1d9e6;
            border-radius:12px; overflow:hidden;
            box-shadow:inset 0 1px 3px rgba(0,0,0,.06);
        }
        #bxSvg { width:100%; height:100%; display:block; }
        .bx-tip {
            position:absolute; top:8px; left:8px; opacity:0; pointer-events:none;
            padding:4px 8px; background:#333; color:#fff;
            border-radius:4px; font-size:12px; transition:opacity .15s;
        }

        /* ── Lista de espera ───────────────────────── */
        .bx-lista-espera-wrap { flex-shrink:0; max-height:140px; overflow-y:auto; }
        .bx-lista-header { font-weight:600; font-size:14px; margin-bottom:8px; padding:8px 0;
            border-bottom:1px solid var(--color-border-tertiary,#eee); }
        .bx-lista-body   { overflow-x:auto; }
        .bx-lista-empty  { text-align:center; color:var(--color-text-secondary,#999); font-size:13px; padding:16px; }

        .bx-espera-table { width:100%; border-collapse:collapse; font-size:13px; }
        .bx-espera-table th { background:var(--color-background-secondary,#f5f5f5); padding:6px 10px;
            text-align:left; font-weight:600; border-bottom:2px solid var(--color-border-tertiary,#e5e5e5); }
        .bx-espera-table td { padding:6px 10px; border-bottom:1px solid var(--color-border-tertiary,#eee); vertical-align:middle; }

        .bx-prio-btn  { background:none; border:1px solid var(--color-border-secondary,#ccc); border-radius:4px;
            padding:1px 6px; cursor:pointer; font-size:13px; }
        .bx-prio-btn:hover { background:var(--color-background-secondary,#f5f5f5); }
        .bx-prio-badge { display:inline-block; color:#fff; font-weight:bold; min-width:24px;
            text-align:center; border-radius:4px; padding:1px 6px; margin:0 4px; font-size:12px; }
        .bx-prio-gap { display:inline-block; width:28px; }

        /* ── Tabla CRUD ────────────────────────────── */
        .bx-table { width:100%; border-collapse:collapse; font-size:13px; }
        .bx-table th { background:var(--color-background-secondary,#f5f5f5); padding:8px 10px;
            text-align:left; font-weight:600; border-bottom:2px solid var(--color-border-tertiary,#e5e5e5);
            cursor:pointer; user-select:none; }
        .bx-table th:hover { background:var(--color-background-info,#eff6ff); }
        .bx-table td { padding:8px 10px; border-bottom:1px solid var(--color-border-tertiary,#eee); vertical-align:middle; }
        .bx-table tr:hover td { background:var(--color-background-secondary,#fafafa); }
        .bx-acciones { display:flex; gap:4px; flex-wrap:wrap; }
        .bx-pos  { font-family:monospace; color:var(--color-text-secondary,#666); }
        .bx-libre { color:var(--color-text-secondary,#888); font-size:12px; }

        /* ── Badges ────────────────────────────────── */
        .bx-badge { display:inline-block; padding:2px 8px; border-radius:20px; font-size:11px; font-weight:600; }
        .badge-green  { background:#D1FAE5; color:#065F46; }
        .badge-yellow { background:#FEF3C7; color:#92400E; }
        .badge-gray   { background:#F3F4F6; color:#6B7280; border:1px solid #E5E7EB; }
        .badge-blue   { background:#DBEAFE; color:#1E40AF; }
        .badge-orange { background:#FEF3C7; color:#B45309; }
        .badge-red    { background:#FEE2E2; color:#991B1B; }

        /* ── Controles ─────────────────────────────── */
        .bx-select, .bx-input {
            padding:6px 10px; border-radius:6px; font-size:13px;
            border:1px solid var(--color-border-secondary,#ccc);
            background:var(--color-background-primary,#fff);
            color:var(--color-text-primary,#000);
        }
        .bx-input  { min-width:0; width:100%; box-sizing:border-box; }
        textarea.bx-input { resize:vertical; font-family:inherit; }

        .bx-btn {
            padding:6px 14px; border-radius:6px; font-size:13px; font-weight:500;
            cursor:pointer; border:1px solid transparent; transition:opacity .15s;
        }
        .bx-btn:disabled { opacity:.5; cursor:default; }
        .bx-btn-primary   { background:#2563EB; color:#fff; }
        .bx-btn-secondary { background:transparent; color:var(--color-text-primary,#333); border-color:var(--color-border-secondary,#ccc); }
        .bx-btn-warning   { background:#F59E0B; color:#fff; }
        .bx-btn-success   { background:#10B981; color:#fff; }
        .bx-btn-danger    { background:#EF4444; color:#fff; }
        .bx-btn-sm  { font-size:12px; padding:4px 10px; }
        .bx-btn-xs  { font-size:12px; padding:3px 8px; }

        /* ── Estado ────────────────────────────────── */
        .bx-loading { padding:32px; text-align:center; color:var(--color-text-secondary,#666); }
        .bx-empty   { padding:32px; text-align:center; color:var(--color-text-secondary,#999); font-size:14px; }

        /* ── Modal ─────────────────────────────────── */
        .bx-overlay {
            position:fixed; inset:0; background:rgba(0,0,0,.45);
            display:flex; align-items:center; justify-content:center; z-index:9000;
        }
        .bx-modal {
            background:var(--color-background-primary,#fff);
            border-radius:12px; max-width:640px; width:94%;
            max-height:90vh; overflow-y:auto;
            box-shadow:0 8px 32px rgba(0,0,0,.18);
        }
        .bx-modal-header {
            display:flex; justify-content:space-between; align-items:center;
            padding:16px 20px; border-bottom:1px solid var(--color-border-tertiary,#eee);
        }
        .bx-modal-header h3 { margin:0; font-size:16px; font-weight:600; }
        .bx-modal-close { background:none; border:none; font-size:18px; cursor:pointer; color:var(--color-text-secondary,#666); padding:4px; }
        .bx-modal-body  { padding:20px; }
        .bx-modal-footer { display:flex; justify-content:flex-end; gap:8px;
            padding:16px 20px; border-top:1px solid var(--color-border-tertiary,#eee); }

        .bx-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        .bx-form-group { display:flex; flex-direction:column; gap:5px; }
        .bx-form-group label { font-size:12px; font-weight:600; color:var(--color-text-secondary,#555); }
        .bx-form-full { grid-column:1/-1; }

        /* ── Toast ─────────────────────────────────── */
        .bx-toast {
            position:fixed; bottom:24px; right:24px; z-index:9999;
            padding:10px 18px; border-radius:8px; font-size:13px; font-weight:500;
            box-shadow:0 4px 12px rgba(0,0,0,.15); animation:bxSlide .2s ease;
            max-width:320px; white-space:pre-line;
        }
        .bx-toast-ok   { background:#10B981; color:#fff; }
        .bx-toast-error { background:#EF4444; color:#fff; }
        .bx-toast-info  { background:#3B82F6; color:#fff; }
        @keyframes bxSlide { from{transform:translateY(16px);opacity:0} to{transform:none;opacity:1} }
    `;
    document.head.appendChild(s);
}

/*
 * ══════════════════════════════════════════════════════════════════
 *  INTEGRACIÓN CON app.js
 *
 *  En el switch de renderNode / getCustomTileAction agregar:
 *
 *  case 'MAPA DE BOXES':        return renderMapaBoxes;
 *  case 'BOXES':                return renderBoxesCrud;
 *  case 'TRONERAS':             return renderTronerasCrud;
 *
 *  (buscar el bloque donde se mapean títulos a funciones en app.js,
 *   generalmente cerca de las líneas 4500-4600)
 * ══════════════════════════════════════════════════════════════════
 */

// ══════════════════════════════════════════════════════════════════
//  CONFIGURACIÓN BOXES — desde CONFIGURACION → LOGISTICA → BOXES
// ══════════════════════════════════════════════════════════════════
async function renderBoxesCrud() {
    setHeader('CONFIGURACIÓN — BOXES');
    showMetaPanel(true);
    setExpandedMode(false);
    menuGrid.className = '';
    menuGrid.innerHTML = '';
    const wrap = document.createElement('div');
    wrap.className = 'bx-wrap';
    menuGrid.appendChild(wrap);
    bxInjectStyles();
    syncBackBtn();
    await _cfgRenderBoxesTabla(wrap);
}

async function _cfgRenderBoxesTabla(wrap) {
    wrap.innerHTML = `
        <div class="bx-toolbar">
            <div class="bx-filters">
                <select id="cfgBxPlanta" class="bx-select">
                    <option value="">Todas las plantas</option>
                    <option value="N">Nacional</option>
                    <option value="F">Fiscal</option>
                </select>
                <select id="cfgBxTipo" class="bx-select">
                    <option value="">Todos los tipos</option>
                    <option value="B">Box</option>
                    <option value="E">Enchufe</option>
                </select>
                <select id="cfgBxActivo" class="bx-select">
                    <option value="1" selected>Activos</option>
                    <option value="0">Inactivos</option>
                    <option value="">Todos</option>
                </select>
                <button class="bx-btn bx-btn-secondary" onclick="_cfgFiltrarBoxes()">Filtrar</button>
            </div>
            <button class="bx-btn bx-btn-primary" onclick="_cfgFormBox(null)">+ Nuevo box</button>
        </div>
        <div id="cfgBxTabla"><div class="bx-loading">Cargando...</div></div>`;
    await _cfgFiltrarBoxes();
}

async function _cfgFiltrarBoxes() {
    const tabla = document.getElementById('cfgBxTabla');
    if (!tabla) return;
    const planta = document.getElementById('cfgBxPlanta')?.value || '';
    const tipo   = document.getElementById('cfgBxTipo')?.value   || '';
    const activo = document.getElementById('cfgBxActivo')?.value ?? '1';
    const params = new URLSearchParams({ action: 'boxes_lista' });
    if (planta) params.set('planta', planta);
    if (tipo)   params.set('tipo', tipo);
    if (activo !== '') params.set('activo', activo);

    tabla.innerHTML = '<div class="bx-loading">Cargando...</div>';
    const lista = await fetch(`${API_EQUIPOS_URL}?${params}`, {credentials:'same-origin'}).then(r=>r.json()).catch(()=>[]);

    if (!Array.isArray(lista) || !lista.length) {
        tabla.innerHTML = '<div class="bx-empty">No hay boxes con esos filtros.</div>'; return;
    }
    tabla.innerHTML = `
        <table class="bx-table">
            <thead><tr>
                <th>N°</th><th>Planta</th><th>Tipo</th>
                <th>Fila</th><th>Columna</th><th>Grado</th>
                <th>Estado</th><th>Carga activa</th><th>Acciones</th>
            </tr></thead>
            <tbody>
                ${lista.map(b => `<tr>
                    <td><strong>${b.numero}</strong></td>
                    <td>${b.planta_texto}</td><td>${b.tipo_texto}</td>
                    <td>${b.fila}</td><td>${b.columna}</td><td>${b.grado}°</td>
                    <td>${b.activo ? '<span class="bx-badge badge-green">Activo</span>' : '<span class="bx-badge badge-gray">Inactivo</span>'}</td>
                    <td>${b.tb_carga_numero ? `<strong>#${b.tb_carga_numero}</strong>` : '—'}</td>
                    <td class="bx-acciones">
                        <button class="bx-btn bx-btn-xs bx-btn-secondary" onclick="_cfgFormBox(${b.id})">✏ Editar</button>
                        ${b.activo
                            ? `<button class="bx-btn bx-btn-xs bx-btn-danger" onclick="_cfgToggleBox(${b.id},0,'${b.numero}')">Desactivar</button>`
                            : `<button class="bx-btn bx-btn-xs bx-btn-success" onclick="_cfgToggleBox(${b.id},1,'${b.numero}')">Activar</button>`}
                    </td>
                </tr>`).join('')}
            </tbody>
        </table>`;
}

async function _cfgFormBox(id) {
    let b = { numero:'', planta:'N', tipo:'B', fila:0, columna:0, grado:160, activo:1 };
    if (id) {
        const d = await fetch(`${API_EQUIPOS_URL}?action=boxes_lista`, {credentials:'same-origin'}).then(r=>r.json()).catch(()=>[]);
        const found = Array.isArray(d) ? d.find(x => x.id == id) : null;
        if (found) b = { ...b, numero:found.numero, planta:found.planta, tipo:found.tipo, fila:found.fila, columna:found.columna, grado:found.grado, activo:found.activo };
    }
    const overlay = document.createElement('div');
    overlay.className = 'bx-overlay'; overlay.id = 'cfgBoxOverlay';
    overlay.innerHTML = `
        <div class="bx-modal">
            <div class="bx-modal-header">
                <h3>${id ? 'Editar' : 'Nuevo'} Box</h3>
                <button class="bx-modal-close" onclick="document.getElementById('cfgBoxOverlay')?.remove()">✕</button>
            </div>
            <div class="bx-modal-body">
                <div class="bx-form-grid">
                    <div class="bx-form-group"><label>Número *</label><input id="cfgBxN" class="bx-input" value="${b.numero}" placeholder="Ej: 1"/></div>
                    <div class="bx-form-group"><label>Planta *</label>
                        <select id="cfgBxP" class="bx-input">
                            <option value="N" ${b.planta==='N'?'selected':''}>Nacional</option>
                            <option value="F" ${b.planta==='F'?'selected':''}>Fiscal</option>
                        </select></div>
                    <div class="bx-form-group"><label>Tipo *</label>
                        <select id="cfgBxT" class="bx-input">
                            <option value="B" ${b.tipo==='B'?'selected':''}>Box</option>
                            <option value="E" ${b.tipo==='E'?'selected':''}>Enchufe</option>
                        </select></div>
                    <div class="bx-form-group"><label>Activo</label>
                        <select id="cfgBxA" class="bx-input">
                            <option value="1" ${b.activo?'selected':''}>Sí</option>
                            <option value="0" ${!b.activo?'selected':''}>No</option>
                        </select></div>
                    <div class="bx-form-group"><label>Fila</label><input id="cfgBxFi" type="number" class="bx-input" value="${b.fila}" step="0.5"/></div>
                    <div class="bx-form-group"><label>Columna</label><input id="cfgBxCo" type="number" class="bx-input" value="${b.columna}" step="0.5"/></div>
                    <div class="bx-form-group"><label>Grado</label>
                        <select id="cfgBxG" class="bx-input">
                            ${[0,45,90,135,160,180,225,270,315].map(g=>`<option value="${g}" ${b.grado==g?'selected':''}>${g}°</option>`).join('')}
                        </select></div>
                </div>
                <p style="font-size:.72rem;color:rgba(255,255,255,.4);margin-top:8px">💡 Fila y Columna posicionan el ícono en el mapa SVG.</p>
            </div>
            <div class="bx-modal-footer">
                <button class="bx-btn bx-btn-secondary" onclick="document.getElementById('cfgBoxOverlay')?.remove()">Cancelar</button>
                <button class="bx-btn bx-btn-primary" id="cfgBxSave">${id ? 'Guardar' : 'Crear'}</button>
            </div>
        </div>`;
    document.body.appendChild(overlay);
    document.getElementById('cfgBxSave').onclick = async () => {
        const payload = { id:id||0, numero:document.getElementById('cfgBxN').value.trim(),
            planta:document.getElementById('cfgBxP').value, tipo:document.getElementById('cfgBxT').value,
            fila:parseFloat(document.getElementById('cfgBxFi').value)||0,
            columna:parseFloat(document.getElementById('cfgBxCo').value)||0,
            grado:parseInt(document.getElementById('cfgBxG').value)||0,
            activo:parseInt(document.getElementById('cfgBxA').value) };
        if (!payload.numero) { bxToast('El número es obligatorio','error'); return; }
        const btn = document.getElementById('cfgBxSave');
        btn.disabled = true; btn.textContent = 'Guardando...';
        const res = await fetch(`${API_EQUIPOS_URL}?action=box_guardar`,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)}).then(r=>r.json());
        if (res.ok) { bxToast(id?'Box actualizado':'Box creado','ok'); overlay.remove(); _cfgFiltrarBoxes(); }
        else { bxToast(res.error||'Error','error'); btn.disabled=false; btn.textContent=id?'Guardar':'Crear'; }
    };
}

async function _cfgToggleBox(id, activo, numero) {
    if (!confirm(`¿${activo?'Activar':'Desactivar'} Box ${numero}?`)) return;
    const res = await fetch(`${API_EQUIPOS_URL}?action=box_guardar`,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({id,activo})}).then(r=>r.json());
    if (res.ok) { bxToast(`Box ${activo?'activado':'desactivado'}`,'ok'); _cfgFiltrarBoxes(); }
    else bxToast(res.error||'Error','error');
}

// ══════════════════════════════════════════════════════════════════
//  CONFIGURACIÓN TRONERAS
// ══════════════════════════════════════════════════════════════════
async function renderTronerasCrud() {
    setHeader('CONFIGURACIÓN — TRONERAS');
    showMetaPanel(true);
    setExpandedMode(false);
    menuGrid.className = '';
    menuGrid.innerHTML = '';
    const wrap = document.createElement('div');
    wrap.className = 'bx-wrap';
    menuGrid.appendChild(wrap);
    bxInjectStyles();
    syncBackBtn();
    await _cfgRenderTronerasTabla(wrap);
}

async function _cfgRenderTronerasTabla(wrap) {
    wrap.innerHTML = `
        <div class="bx-toolbar">
            <div class="bx-filters">
                <select id="cfgTrPlanta" class="bx-select">
                    <option value="">Todas las plantas</option>
                    <option value="N">Nacional</option>
                    <option value="F">Fiscal</option>
                </select>
                <select id="cfgTrEstado" class="bx-select">
                    <option value="">Todos los estados</option>
                    <option value="A">Activas</option>
                    <option value="M">Mantenimiento</option>
                    <option value="I">Inactivas</option>
                </select>
                <button class="bx-btn bx-btn-secondary" onclick="_cfgFiltrarTroneras()">Filtrar</button>
            </div>
            <button class="bx-btn bx-btn-primary" onclick="_cfgFormTronera(null)">+ Nueva tronera</button>
        </div>
        <div id="cfgTrTabla"><div class="bx-loading">Cargando...</div></div>`;
    await _cfgFiltrarTroneras();
}

async function _cfgFiltrarTroneras() {
    const tabla = document.getElementById('cfgTrTabla');
    if (!tabla) return;
    const planta = document.getElementById('cfgTrPlanta')?.value || '';
    const estado = document.getElementById('cfgTrEstado')?.value || '';
    const params = new URLSearchParams({ action: 'troneras_lista' });
    if (planta) params.set('planta', planta);
    if (estado) params.set('estado', estado);

    tabla.innerHTML = '<div class="bx-loading">Cargando...</div>';
    const lista = await fetch(`${API_EQUIPOS_URL}?${params}`, {credentials:'same-origin'}).then(r=>r.json()).catch(()=>[]);

    if (!Array.isArray(lista) || !lista.length) {
        tabla.innerHTML = '<div class="bx-empty">No hay troneras con esos filtros.</div>'; return;
    }
    const eColor = {A:'badge-green',M:'badge-yellow',I:'badge-gray'};
    const eTxt   = {A:'Activa',M:'Mantenimiento',I:'Inactiva'};
    tabla.innerHTML = `
        <table class="bx-table">
            <thead><tr>
                <th>N°</th><th>Planta</th><th>Andén</th>
                <th>Fila</th><th>Columna</th><th>Grado</th>
                <th>Estado</th><th>Carga activa</th><th>Comentarios</th><th>Acciones</th>
            </tr></thead>
            <tbody>
                ${lista.map(t => `<tr>
                    <td><strong>${t.numero}</strong></td>
                    <td>${t.tipo_texto}</td><td>${t.anden_texto}</td>
                    <td>${t.fila}</td><td>${t.columna}</td><td>${t.grado}°</td>
                    <td><span class="bx-badge ${eColor[t.estado]||'badge-gray'}">${eTxt[t.estado]||t.estado}</span></td>
                    <td>${t.tt_carga_numero?`<strong>#${t.tt_carga_numero}</strong>`:'—'}</td>
                    <td style="font-size:.7rem;color:rgba(255,255,255,.5)">${t.comentarios||'—'}</td>
                    <td class="bx-acciones">
                        <button class="bx-btn bx-btn-xs bx-btn-secondary" onclick="_cfgFormTronera(${t.id})">✏ Editar</button>
                        ${t.estado!=='I'
                            ? `<button class="bx-btn bx-btn-xs bx-btn-danger" onclick="_cfgSetEstadoTronera(${t.id},'I','${t.numero}')">Desactivar</button>`
                            : `<button class="bx-btn bx-btn-xs bx-btn-success" onclick="_cfgSetEstadoTronera(${t.id},'A','${t.numero}')">Activar</button>`}
                        ${t.estado==='A'?`<button class="bx-btn bx-btn-xs bx-btn-warning" onclick="_cfgSetEstadoTronera(${t.id},'M','${t.numero}')">Mant.</button>`:''}
                    </td>
                </tr>`).join('')}
            </tbody>
        </table>`;
}

async function _cfgFormTronera(id) {
    let t = { numero:'', tipo:'N', anden:'N', fila:0, columna:0, grado:180, estado:'A', comentarios:'' };
    if (id) {
        const d = await fetch(`${API_EQUIPOS_URL}?action=troneras_lista`,{credentials:'same-origin'}).then(r=>r.json()).catch(()=>[]);
        const found = Array.isArray(d) ? d.find(x=>x.id==id) : null;
        if (found) t = {...t,...found,tipo:found.tipo};
    }
    const overlay = document.createElement('div');
    overlay.className = 'bx-overlay'; overlay.id = 'cfgTrOverlay';
    overlay.innerHTML = `
        <div class="bx-modal">
            <div class="bx-modal-header">
                <h3>${id?'Editar':'Nueva'} Tronera</h3>
                <button class="bx-modal-close" onclick="document.getElementById('cfgTrOverlay')?.remove()">✕</button>
            </div>
            <div class="bx-modal-body">
                <div class="bx-form-grid">
                    <div class="bx-form-group"><label>Número *</label><input id="cfgTrN" class="bx-input" value="${t.numero}" placeholder="Ej: 1"/></div>
                    <div class="bx-form-group"><label>Planta *</label>
                        <select id="cfgTrP" class="bx-input">
                            <option value="N" ${t.tipo==='N'?'selected':''}>Nacional</option>
                            <option value="F" ${t.tipo==='F'?'selected':''}>Fiscal</option>
                        </select></div>
                    <div class="bx-form-group"><label>Andén *</label>
                        <select id="cfgTrA" class="bx-input">
                            <option value="N" ${t.anden==='N'?'selected':''}>Norte</option>
                            <option value="S" ${t.anden==='S'?'selected':''}>Sur</option>
                            <option value="F" ${t.anden==='F'?'selected':''}>Frío</option>
                            <option value="E" ${t.anden==='E'?'selected':''}>Seco</option>
                        </select></div>
                    <div class="bx-form-group"><label>Estado</label>
                        <select id="cfgTrE" class="bx-input">
                            <option value="A" ${t.estado==='A'?'selected':''}>Activa</option>
                            <option value="M" ${t.estado==='M'?'selected':''}>Mantenimiento</option>
                            <option value="I" ${t.estado==='I'?'selected':''}>Inactiva</option>
                        </select></div>
                    <div class="bx-form-group"><label>Fila</label><input id="cfgTrFi" type="number" class="bx-input" value="${t.fila}" step="0.5"/></div>
                    <div class="bx-form-group"><label>Columna</label><input id="cfgTrCo" type="number" class="bx-input" value="${t.columna}" step="0.5"/></div>
                    <div class="bx-form-group"><label>Grado</label>
                        <select id="cfgTrG" class="bx-input">
                            ${[0,45,90,135,180,225,270,315].map(g=>`<option value="${g}" ${t.grado==g?'selected':''}>${g}°</option>`).join('')}
                        </select></div>
                    <div class="bx-form-group bx-form-full"><label>Comentarios</label>
                        <textarea id="cfgTrC" rows="2" class="bx-input">${t.comentarios||''}</textarea></div>
                </div>
            </div>
            <div class="bx-modal-footer">
                <button class="bx-btn bx-btn-secondary" onclick="document.getElementById('cfgTrOverlay')?.remove()">Cancelar</button>
                <button class="bx-btn bx-btn-primary" id="cfgTrSave">${id?'Guardar':'Crear'}</button>
            </div>
        </div>`;
    document.body.appendChild(overlay);
    document.getElementById('cfgTrSave').onclick = async () => {
        const payload = { id:id||0, numero:document.getElementById('cfgTrN').value.trim(),
            tipo:document.getElementById('cfgTrP').value, anden:document.getElementById('cfgTrA').value,
            fila:parseFloat(document.getElementById('cfgTrFi').value)||0,
            columna:parseFloat(document.getElementById('cfgTrCo').value)||0,
            grado:parseInt(document.getElementById('cfgTrG').value)||0,
            estado:document.getElementById('cfgTrE').value,
            comentarios:document.getElementById('cfgTrC').value.trim() };
        if (!payload.numero) { bxToast('El número es obligatorio','error'); return; }
        const btn = document.getElementById('cfgTrSave');
        btn.disabled=true; btn.textContent='Guardando...';
        const res = await fetch(`${API_EQUIPOS_URL}?action=tronera_guardar`,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)}).then(r=>r.json());
        if (res.ok) { bxToast(id?'Tronera actualizada':'Tronera creada','ok'); overlay.remove(); _cfgFiltrarTroneras(); }
        else { bxToast(res.error||'Error','error'); btn.disabled=false; btn.textContent=id?'Guardar':'Crear'; }
    };
}

async function _cfgSetEstadoTronera(id, estado, numero) {
    const labels = {A:'activar',M:'poner en mantenimiento',I:'desactivar'};
    if (!confirm(`¿${labels[estado]} Tronera ${numero}?`)) return;
    // Fetch current data then save with new estado
    const d = await fetch(`${API_EQUIPOS_URL}?action=troneras_lista`,{credentials:'same-origin'}).then(r=>r.json()).catch(()=>[]);
    const t = Array.isArray(d) ? d.find(x=>x.id==id) : null;
    if (!t) { bxToast('No se encontró la tronera','error'); return; }
    const payload = { id, numero:t.numero, tipo:t.tipo, anden:t.anden,
        fila:t.fila, columna:t.columna, grado:t.grado, estado, comentarios:t.comentarios||'' };
    const res = await fetch(`${API_EQUIPOS_URL}?action=tronera_guardar`,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)}).then(r=>r.json());
    if (res.ok) { bxToast('Tronera actualizada','ok'); _cfgFiltrarTroneras(); }
    else bxToast(res.error||'Error','error');
}
