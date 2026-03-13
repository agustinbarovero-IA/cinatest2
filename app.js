/* ═══════════════════════════════════════════════════════════
   SISTEMA GRUPO CINA — app.js
   ═══════════════════════════════════════════════════════════ */

const USER_NAME = 'agustin.barovero';

/* ── ÁRBOL DE MENÚ ──────────────────────────────────────────── */
const menuTree = {
  title: 'INICIO',
  children: [
    {
      title: 'LOGISTICA NACIONAL',
      children: [
        { title: 'CARGAS',       url: 'https://sistema.cinafrio.com/intranet/index.php/transporte_carga/index/fiscal/0' },
        { title: 'PRE ENTRADAS', url: 'https://sistema.cinafrio.com/intranet/index.php/entradacarga/list/clasificada/0/fiscal/0' },
        { title: 'ENTRADAS',     url: 'https://sistema.cinafrio.com/intranet/index.php/entradacarga/list/clasificada/1/fiscal/0' },
        { title: 'PRE SALIDAS',  url: 'https://sistema.cinafrio.com/intranet/index.php/presalidas/list/salida/0/fiscal/0' },
        { title: 'SALIDAS',      url: 'https://sistema.cinafrio.com/intranet/index.php/presalidas/list/salida/1/fiscal/0' }
      ]
    },
    {
      title: 'LOGISTICA FISCAL',
      children: [
        { title: 'CARGAS',                    url: 'https://sistema.cinafrio.com/intranet/index.php/transporte_carga/index/fiscal/1' },
        { title: 'PRE ENTRADAS',              url: 'https://sistema.cinafrio.com/intranet/index.php/entradacarga/list/clasificada/0/fiscal/1' },
        { title: 'ENTRADAS',                  url: 'https://sistema.cinafrio.com/intranet/index.php/entradacarga/list/clasificada/1/fiscal/1' },
        { title: 'PRE SALIDAS',               url: 'https://sistema.cinafrio.com/intranet/index.php/presalidas/list/salida/0/fiscal/1' },
        { title: 'SALIDAS',                   url: 'https://sistema.cinafrio.com/intranet/index.php/presalidas/list/salida/1/fiscal/1' },
        { title: 'REPORTE DEPOSITO FISCAL AFIP', url: 'https://sistema.cinafrio.com/intranet2/app.php/reportestockafip/' }
      ]
    },
    {
      title: 'CALIDAD',
      children: [
        { title: 'CONTROL DE ENTRADAS',           url: 'https://sistema.cinafrio.com/intranet/index.php/carga/list/type/E' },
        { title: 'CONTROL DE SALIDAS',            url: 'https://sistema.cinafrio.com/intranet/index.php/carga/list/type/S' },
        { title: 'CONTROL DE INTERVENCIONES',     url: 'https://sistema.cinafrio.com/intranet/index.php/carga/listIntervenciones' },
        { title: 'VENCIMIENTO DE PRODUCTOS',      url: 'https://sistema.cinafrio.com/intranet/index.php/infostock/vencimientos' },
        { title: 'ESTADISTICAS DE INTERVENCIONES',url: 'https://sistema.cinafrio.com/intranet/index.php/carga/estadisticas' }
      ]
    },
    {
      title: 'PORTERIA',
      children: [
        { title: 'INGRESOS EGRESOS', url: 'https://sistema.cinafrio.com/intranet/index.php/ingreso/index' }
      ]
    },
    {
      title: 'TRANSPORTE',
      children: [
        {
          title: 'DATOS DE VEHICULOS',
          children: [
            { title: 'ASEGURADORAS', url: 'https://sistema.cinafrio.com/intranet/index.php/transporte_aseguradora' },
            { title: 'EMPRESAS',     url: 'https://sistema.cinafrio.com/intranet/index.php/transporte_empresa' },
            { title: 'CONDUCTORES',  url: 'https://sistema.cinafrio.com/intranet/index.php/transporte_conductor' },
            { title: 'VEHICULOS',    url: 'https://sistema.cinafrio.com/intranet/index.php/transporte_vehiculo' },
            { title: 'ACOPLADOS',    url: 'https://sistema.cinafrio.com/intranet/index.php/transporte_acoplado' }
          ]
        },
        {
          title: 'DATOS DE LA PLAYA DE MANIOBRA',
          children: [
            { title: 'BOXES',    url: 'https://sistema.cinafrio.com/intranet/index.php/box' },
            { title: 'TRONERAS', url: 'https://sistema.cinafrio.com/intranet/index.php/tronera' }
          ]
        }
      ]
    },
    {
      title: 'ADMINISTRACION',
      children: [
        { title: 'FACTURACION' },
        { title: 'REMITOS' },
        { title: 'COMPRAS' }
      ]
    },
    {
      title: 'RRHH',
      children: [
        { title: 'PERSONAL',            url: 'https://sistema.cinafrio.com/intranet2/app.php/persona/' },
        { title: 'AUSENTISMO',          url: 'https://sistema.cinafrio.com/intranet2/app.php/ausentismo/dashboard' },
        { title: 'VACUNACIONES',        url: 'https://sistema.cinafrio.com/intranet2/app.php/vacunacion/' },
        { title: 'EXAMENES ART',        url: 'https://sistema.cinafrio.com/intranet2/app.php/examenmedicoart/' },
        { title: 'EXAMENES INGRESOS',   url: 'https://sistema.cinafrio.com/intranet2/app.php/exameningreso/' },
        { title: 'LIBRETAS SANITARIAS', url: 'https://sistema.cinafrio.com/intranet2/app.php/libretasanitaria/' }
      ]
    },
    {
      title: 'MANTENIMIENTO',
      children: [
        { title: 'MANTENIMIENTO GENERAL',        url: 'https://sistema.cinafrio.com/intranet2/app.php/mantenimiento/' },
        { title: 'PR 6 CONTRASTE DE TERMOMETROS',url: 'https://sistema.cinafrio.com/intranet2/app.php/sdmregcontraste/' },
        { title: 'PR 9 SEMANAL',                 url: 'https://sistema.cinafrio.com/intranet2/app.php/sdmegcsemanal/' },
        { title: 'PR 22 LIMPIEZA TANQUES',        url: 'https://sistema.cinafrio.com/intranet2/app.php/sdmreglimpiezatanque/' },
        { title: 'PR 24 DIARIO',                  url: 'https://sistema.cinafrio.com/intranet2/app.php/sdmegcdiario/' },
        { title: 'PR 30 LIBRO DE GUARDIA',        url: 'https://sistema.cinafrio.com/intranet2/app.php/sdmcontrol/' },
        { title: 'PR 80 PARADA DE CAMARAS',       url: 'https://sistema.cinafrio.com/intranet2/app.php/sdmcamara/' },
        { title: 'PR 100 DESCONGELADO',           url: 'https://sistema.cinafrio.com/intranet2/app.php/sdmdescongelado/' }
      ]
    },
    {
      title: 'PRODUCTOS',
      children: [
        { title: 'CREAR NUEVOS PRODUCTOS', url: 'https://sistema.cinafrio.com/intranet/index.php/producto' }
      ]
    },
    {
      title: 'DASHBOARD',
      children: [
        { title: 'MAPA DE BOXES' },
        { title: 'DASHBOARD LOGISTICA NACIONAL' },
        { title: 'DASHBOARD LOGISTICA FISCAL' },
        { title: 'DASHBOARD EQUIPAMIENTO' },
        { title: 'DASHBOARD GENERAL', url: 'https://sistema.cinafrio.com/intranet2/app.php/ausentismo/dashboard' }
      ]
    },
    {
      title: 'REPORTES',
      children: [
        { title: 'CAMARAS' },
        { title: 'STOCK',               url: 'https://sistema.cinafrio.com/intranet/index.php/infostock/stockDetallado' },
        { title: 'MOVIMIENTOS' },
        { title: 'TIEMPOS CARGA DESCARGA', url: 'https://sistema.cinafrio.com/intranet/index.php/estiba/informes' },
        { title: 'AUSENTISMO' },
        { title: 'DEVOLUCIONES',        url: 'https://sistema.cinafrio.com/intranet/index.php/infostock/devoluciones' }
      ]
    },
    {
      title: 'INDICADORES',
      children: [
        { title: 'CARGAS I-2' },
        { title: 'MOVIMIENTOS' },
        { title: 'ESTADISTICAS DE PERSONAL' },
        { title: 'ALMACENAMIENTO DE POSICIONES' },  // I-36
        { title: 'POSICIONES INGRESADAS EGRESADAS' },
        { title: 'AJUSTES DE STOCK' },
        { title: 'CLIENTES QUE OPERARON' },
        { title: 'USO DE EQUIPOS' },
        { title: 'ESTIBAS CONGELADAS' }
      ]
    },
    {
      title: 'SISTEMA DE GESTION',
      children: [
        { title: 'INFORMACION DOCUMENTADA', url: 'https://manualcinafrio.com.ar/documentos/' },
        { title: 'INDICADORES',             url: 'https://manualcinafrio.com.ar/indicadores/' },
        { title: 'REGISTRO PR 44',          url: 'https://sistema.cinafrio.com/intranet/index.php/registro' },
        { title: 'FORMACION',               url: 'https://manualcinafrio.com.ar/formacion/' }
      ]
    },
    {
      title: 'SENASA',
      children: [
        { title: 'CERTIFICADOS',          url: 'https://sistema.cinafrio.com/intranet/index.php/certificados/list' },
        { title: 'CERTIFICADOS INTERNOS', url: 'https://sistema.cinafrio.com/intranet/index.php/certificado_interno' }
      ]
    },
    {
      title: 'CONFIGURACION',
      children: [
        { title: 'VETERINARIOS', url: 'https://sistema.cinafrio.com/intranet/index.php/veterinario/index' },
        {
          title: 'CLIENTES',
          children: [
            { title: 'CLIENTES DEPOSITO NACIONAL', url: 'https://sistema.cinafrio.com/intranet/index.php/clientes/list?fiscal=0&page=1' },
            { title: 'CLIENTES DEPOSITO FISCAL',   url: 'https://sistema.cinafrio.com/intranet/index.php/clientes/list/fiscal/1/page/1' },
            { title: 'GRUPO DE CLIENTES',          url: 'https://sistema.cinafrio.com/intranet/index.php/grupo/list' }
          ]
        },
        { title: 'COMPRAS' },
        { title: 'ESTABLECIMIENTOS' },
        {
          title: 'LOGISTICA',
          children: [
            { title: 'CAMARAS',               url: 'https://sistema.cinafrio.com/intranet/index.php/camaras' },
            { title: 'CONDUCTORES',           url: 'https://sistema.cinafrio.com/intranet/index.php/conductor_autoelevador' },
            { title: 'RESPONSABLE DE CARGAS', url: 'https://sistema.cinafrio.com/intranet/index.php/responsable_logistica' },
            { title: 'TIPO DE CAMARAS',       url: 'https://sistema.cinafrio.com/intranet/index.php/tiposcamara' },
            { title: 'TIPO DE ESTIBA',        url: 'https://sistema.cinafrio.com/intranet/index.php/tiposestiba' },
            { title: 'TIPO DE CERTIFICADOS',  url: 'https://sistema.cinafrio.com/intranet/index.php/tiposcertificados' },
            { title: 'TIPO DE INTERVENCIONES',url: 'https://sistema.cinafrio.com/intranet/index.php/intervenciones' },
            { title: 'TIPO DE PRODUCTOS',     url: 'https://sistema.cinafrio.com/intranet/index.php/tiposproducto' }
          ]
        }
      ]
    },
    {
      title: 'OTROS SISTEMAS',
      children: [
        { title: 'INFORMACION DOCUMENTADA', url: 'https://manualcinafrio.com.ar/documentos/' },
        { title: 'INDICADORES',             url: 'https://manualcinafrio.com.ar/indicadores/' },
        { title: 'REGISTRO PR 44',          url: 'https://sistema.cinafrio.com/intranet/index.php/registro' },
        { title: 'FORMACION',               url: 'https://manualcinafrio.com.ar/formacion/' }
      ]
    }
  ]
};

/* ── COLORES DE MENÚ ────────────────────────────────────────── */
const mainColorMap = {
  'LOGISTICA NACIONAL': '#36B0C9',
  'LOGISTICA FISCAL':   '#00BBB4',
  'CALIDAD':            '#E36B2C',
  'PORTERIA':           '#00A887',
  'TRANSPORTE':         '#00A887',
  'ADMINISTRACION':     '#001F60',
  'RRHH':               '#001F60',
  'MANTENIMIENTO':      '#001F60',
  'PRODUCTOS':          '#36B0C9',
  'DASHBOARD':          '#FFCC33',
  'REPORTES':           '#FFCC33',
  'INDICADORES':        '#FFCC33',
  'SISTEMA DE GESTION': '#001F60',
  'SENASA':             '#23BAC4',
  'CONFIGURACION':      '#001F60',
  'OTROS SISTEMAS':     '#001F60'
};

/* ── DATOS LOGÍSTICA ────────────────────────────────────────── */
const dashboardLogisticaNacionalData = [
  { tipo:'CAMARA', nombre:'1',  estibas:150, temperatura:-18, clientes:['MB','ME'], extra:25, puerta:true, ventilador:true },
  { tipo:'CAMARA', nombre:'3',  estibas:200, temperatura:-18, clientes:['MB','ME'], extra:25, puerta:true, ventilador:true },
  { tipo:'CAMARA', nombre:'4',  estibas:75,  temperatura:-18, clientes:['MB','ME'], extra:25, puerta:true, ventilador:true },
  { tipo:'CAMARA', nombre:'56', estibas:200, temperatura:-18, clientes:['MB','ME'], extra:25, puerta:true, ventilador:true },
  { tipo:'CAMARA', nombre:'7',  estibas:120, temperatura:2,   clientes:['MI'],      extra:25, puerta:true, ventilador:true },
  { tipo:'CAMARA', nombre:'8',  estibas:850, temperatura:-21, clientes:['MB','ME'], extra:25, puerta:true, ventilador:true },
  { tipo:'CAMARA', nombre:'11', estibas:760, temperatura:-21, clientes:['MB','ME'], extra:25, puerta:true, ventilador:true },
  { tipo:'CAMARA', nombre:'12', estibas:300, temperatura:-18, clientes:['MB','ME'], extra:25, puerta:true, ventilador:true },
  { tipo:'CAMARA', nombre:'14', estibas:243, temperatura:-18, clientes:['MB','ME'], extra:25, puerta:true, ventilador:true },
  { tipo:'CAMARA', nombre:'15', estibas:200, temperatura:-18, clientes:['MB','ME'], extra:25, puerta:true, ventilador:true },
  { tipo:'CAMARA', nombre:'16', estibas:350, temperatura:-18, clientes:['MB','ME'], extra:25, puerta:true, ventilador:true },
  { tipo:'CAMARA', nombre:'17', estibas:75,  temperatura:-18, clientes:['MB','ME'], extra:25, puerta:true, ventilador:true },
  { tipo:'CAMARA', nombre:'DS', estibas:67,  temperatura:24,  clientes:['MB','ME'], extra:25, puerta:true, ventilador:true },
  { tipo:'TUNEL',  nombre:'2',  estibas:35,  temperatura:-27, clientes:['MD'],      extra:25, puerta:true, ventilador:true },
  { tipo:'TUNEL',  nombre:'9',  estibas:45,  temperatura:-27, clientes:['MD'],      extra:25, puerta:true, ventilador:true },
  { tipo:'TUNEL',  nombre:'10', estibas:40,  temperatura:-27, clientes:['MD'],      extra:25, puerta:true, ventilador:true }
];

/* ── DATOS EQUIPAMIENTO ─────────────────────────────────────── */
// Array mutable — los cambios del usuario se persisten aquí en memoria
const dashboardEquipamientoData = [
  { tipo:'Autoelevador',       icono:'img/autoelevadores.png', denominacion:'AE-01', estado:'En marcha', planta:'Nacional',       horas:'00:45hs', usuario:'jlopez',     isImage:true },
  { tipo:'Autoelevador',       icono:'img/autoelevadores.png', denominacion:'AE-02', estado:'Cargando',  planta:'Nacional',       horas:'01:20hs', usuario:'mrojas',     isImage:true },
  { tipo:'Autoelevador',       icono:'img/autoelevadores.png', denominacion:'AE-03', estado:'En marcha', planta:'Deposito Fiscal',horas:'00:15hs', usuario:'agarcia',    isImage:true },
  { tipo:'Autoelevador',       icono:'img/autoelevadores.png', denominacion:'AE-04', estado:'Falla',     planta:'Mantenimiento',  horas:'03:40hs', usuario:'lperez',     isImage:true },
  { tipo:'Autoelevador',       icono:'img/autoelevadores.png', denominacion:'AE-05', estado:'En marcha', planta:'Nacional',       horas:'00:05hs', usuario:'jgomez',     isImage:true },
  { tipo:'Autoelevador',       icono:'img/autoelevadores.png', denominacion:'AE-06', estado:'Cargando',  planta:'Otros',          horas:'02:10hs', usuario:'mbazan',     isImage:true },
  { tipo:'Autoelevador',       icono:'img/autoelevadores.png', denominacion:'AE-07', estado:'En marcha', planta:'Deposito Fiscal',horas:'00:55hs', usuario:'fvera',      isImage:true },
  { tipo:'Autoelevador',       icono:'img/autoelevadores.png', denominacion:'AE-08', estado:'Falla',     planta:'Mantenimiento',  horas:'04:25hs', usuario:'acastro',    isImage:true },
  { tipo:'Zorra electrica',    icono:'img/apilador.png', isImage:true,                   denominacion:'ZE-01', estado:'En marcha', planta:'Nacional',       horas:'00:32hs', usuario:'jmartin' },
  { tipo:'Zorra electrica',    icono:'img/apilador.png', isImage:true,                   denominacion:'ZE-02', estado:'Cargando',  planta:'Nacional',       horas:'01:05hs', usuario:'rsosa' },
  { tipo:'Zorra electrica',    icono:'img/apilador.png', isImage:true,                   denominacion:'ZE-03', estado:'En marcha', planta:'Deposito Fiscal',horas:'00:11hs', usuario:'pdominguez' },
  { tipo:'Zorra electrica',    icono:'img/apilador.png', isImage:true,                   denominacion:'ZE-04', estado:'Falla',     planta:'Mantenimiento',  horas:'05:50hs', usuario:'cmedina' },
  { tipo:'Zorra electrica',    icono:'img/apilador.png', isImage:true,                   denominacion:'ZE-05', estado:'En marcha', planta:'Otros',          horas:'00:48hs', usuario:'sgimenez' },
  { tipo:'Autoelevador diesel', icono:'⛽',                  denominacion:'AD-01', estado:'En marcha', planta:'Deposito Fiscal',horas:'02:30hs', usuario:'rbenitez' },
  { tipo:'Limpiador',           icono:'img/robotlimpieza.png', isImage:true,                  denominacion:'RL-01', estado:'Cargando',  planta:'Mantenimiento',  horas:'00:25hs', usuario:'lortiz' },
  { tipo:'Camion',              icono:'🚚',                  denominacion:'CM-01', estado:'En marcha', planta:'Nacional',       horas:'06:10hs', usuario:'druiz' }
];

/* Opciones fijas para selects del editor */
const ESTADOS_EQUIPO = ['En marcha', 'Cargando', 'Falla'];
const PLANTAS_EQUIPO = ['Nacional', 'Deposito Fiscal', 'Mantenimiento', 'Otros'];

/* ── DATOS BOXES ────────────────────────────────────────────── */
const boxesData = Array.from({length: 16}, (_, i) => ({
  numero:    i + 1,
  titulo:    `Contenedor ${i + 1}`,
  ubicacion: `16-5-${Math.floor(i / 4) + 1}-${(i % 4) + 1}`,
  tipo:      i + 1 === 6 ? 'Pallet especial' : 'Pallet',
  ocupacion: i + 1 === 6 ? 78 : Math.min(100, 35 + i * 4),
  estiba:    i + 1 === 6 ? '65984-3' : `6598${i + 1}-1`
}));

const detailProducts = [
  { producto: 'Bondiola de cerdo congelada', fecha: '15/05/2026' },
  { producto: 'Pechito de cerdo congelado',  fecha: '22/05/2026' },
  { producto: 'Pulpa de cerdo exportación',  fecha: '03/06/2026' }
];

/* ── DOM REFS ───────────────────────────────────────────────── */
const historyStack   = [];
const menuGrid       = document.getElementById('menuGrid');
const menuWrap       = document.querySelector('.menu-wrap');
const screenTitle    = document.getElementById('screenTitle');
const screenSubtitle = document.getElementById('screenSubtitle');
const backBtn        = document.getElementById('backBtn');
const homeBtn        = document.getElementById('homeBtn');
const fullscreenBtn  = document.getElementById('fullscreenBtn');
const userBtn        = document.getElementById('userBtn');
const qrBox          = document.getElementById('qrBox');
const sinUbicacionBox= document.getElementById('sinUbicacionBox');
const vencidasBox    = document.getElementById('vencidasBox');
const metaPanel      = document.querySelector('.meta-panel');

/* ── HELPERS GENERALES ──────────────────────────────────────── */
function setHeader(title) {
  screenTitle.textContent    = title;
  screenSubtitle.textContent = USER_NAME;
}

function syncBackBtn() {
  backBtn.disabled = historyStack.length === 0;
}

/* Muestra / oculta la tira de datos superior */
function showMetaPanel(visible) {
  metaPanel.style.display = visible ? 'block' : 'none';
}

/* Modo "expandido" para formularios y vistas de detalle */
function setExpandedMode(enabled) {
  menuWrap.classList.toggle('expanded', enabled);
}

function getTextInitials(text) {
  return text.split(/\s+/).filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase();
}

function getCustomTileHTML(item) {
  // CARGAS I-2: mostrar % cumplimiento actual
  if (item.title === 'CARGAS I-2') {
    const pct   = Math.round((cargasI2Data.cumplidas / (cargasI2Data.planificadas || 1)) * 100);
    const color = pct >= 80 ? '#00A887' : pct >= 60 ? '#F97316' : '#DC2626';
    return `
      <div class="tile-kpi-wrap">
        <div class="tile-kpi-top">
          <span class="tile-kpi-badge">I-2</span>
        </div>
        <div class="tile-kpi-value tile-kpi-value--lg" style="color:${color}">${pct}<span class="tile-kpi-sym">%</span></div>
        <div class="tile-kpi-sublabel">CUMPLIMIENTO</div>
        <div class="tile-title one-line tile-label">CARGAS</div>
      </div>`;
  }
  // ALMACENAMIENTO DE POSICIONES I-36: mostrar valor actual del contenedor 5
  if (item.title === 'ALMACENAMIENTO DE POSICIONES') {
    const estEl  = document.getElementById('estibas-en-planta');
    const actual = estEl ? parseInt(estEl.textContent.replace(/\D/g,'')) || 6500 : 6500;
    const color  = actual >= 6200 ? '#00A887' : actual >= 5500 ? '#36B0C9' : '#F97316';
    return `
      <div class="tile-kpi-wrap">
        <div class="tile-kpi-top">
          <span class="tile-kpi-badge">I-36</span>
        </div>
        <div class="tile-kpi-value tile-kpi-value--md" style="color:${color}">${actual.toLocaleString('es-AR')}</div>
        <div class="tile-kpi-sublabel">POSICIONES</div>
        <div class="tile-title one-line tile-label">ALMACENAMIENTO</div>
      </div>`;
  }
  // POSICIONES INGRESADAS/EGRESADAS: dos valores verde/rojo con barra
  if (item.title === 'POSICIONES INGRESADAS EGRESADAS') {
    return `
      <div class="tile-kpi-wrap">
        <div class="tile-kpi-top"><span class="tile-kpi-badge">I/E</span></div>
        <div class="tile-kpi-dual">
          <div class="tile-kpi-dual-item green">
            <span class="tile-kpi-dual-val">14.340</span>
            <span class="tile-kpi-dual-lbl">INGR.</span>
          </div>
          <div class="tile-kpi-dual-sep"></div>
          <div class="tile-kpi-dual-item red">
            <span class="tile-kpi-dual-val">15.405</span>
            <span class="tile-kpi-dual-lbl">EGR.</span>
          </div>
        </div>
        <div class="tile-title one-line tile-label">POS. INGR / EGR</div>
      </div>`;
  }
  // CLIENTES QUE OPERARON: cantidad de clientes
  if (item.title === 'CLIENTES QUE OPERARON') {
    const n = clientesOperaron.length;
    return `
      <div class="tile-kpi-wrap">
        <div class="tile-kpi-top"><span class="tile-kpi-badge">CLI</span></div>
        <div class="tile-kpi-value tile-kpi-value--lg" style="color:#36B0C9">${n}</div>
        <div class="tile-kpi-sublabel">CLIENTES</div>
        <div class="tile-title one-line tile-label">QUE OPERARON</div>
      </div>`;
  }
  // MOVIMIENTOS (dentro de INDICADORES): 6 tiempos promedio
  if (item.title === 'MOVIMIENTOS') {
    const mes = movData.meses[movData.meses.length - 1];
    const veh = movData.vehiculos;
    return `
      <div class="tile-kpi-wrap tile-mov-wrap">
        <div class="tile-kpi-top"><span class="tile-kpi-badge">MOV</span></div>
        <div class="tile-mov-grid">
          ${veh.map(v => `
            <div class="tile-mov-item">
              <span class="tile-mov-code">${v.code}</span>
              <span class="tile-mov-val">${Math.round(mes.promedios[v.id].prom * 60)}m</span>
            </div>`).join('')}
        </div>
        <div class="tile-title one-line tile-label">MOVIMIENTOS</div>
      </div>`;
  }
  // AJUSTES DE STOCK: I-3 y I-4 con 2 números
  if (item.title === 'AJUSTES DE STOCK') {
    return `
      <div class="tile-kpi-wrap">
        <div class="tile-kpi-top"><span class="tile-kpi-badge">AJS</span></div>
        <div class="tile-adj-dual">
          <div class="tile-adj-row"><span class="tile-adj-code">I-3</span><span class="tile-adj-val">1</span></div>
          <div class="tile-adj-row"><span class="tile-adj-code">I-49</span><span class="tile-adj-val">2</span></div>
        </div>
        <div class="tile-title one-line tile-label">AJUSTES STOCK</div>
      </div>`;
  }
  // ESTADISTICAS DE PERSONAL: total usuarios online
  if (item.title === 'ESTADISTICAS DE PERSONAL') {
    const total = personalData.usuarios.length;
    return `
      <div class="tile-kpi-wrap">
        <div class="tile-kpi-top"><span class="tile-kpi-badge">RRHH</span></div>
        <div class="tile-kpi-value tile-kpi-value--lg" style="color:#A78BFA">${total}</div>
        <div class="tile-kpi-sublabel">USUARIOS</div>
        <div class="tile-title one-line tile-label">ESTADÍSTICAS</div>
      </div>`;
  }
  // USO DE EQUIPOS
  if (item.title === 'USO DE EQUIPOS') {
    const ue = usoEquiposData;
    const mes = ue.meses[ue.meses.length-1];
    return `
      <div class="tile-kpi-wrap tile-mov-wrap">
        <div class="tile-kpi-top"><span class="tile-kpi-badge">EQP</span></div>
        <div class="tile-mov-grid">
          <div class="tile-mov-item"><span class="tile-mov-code">BOX</span><span class="tile-mov-val" style="color:#36B0C9">${mes.boxes}h</span></div>
          <div class="tile-mov-item"><span class="tile-mov-code">TRON</span><span class="tile-mov-val" style="color:#36B0C9">${mes.troneras}h</span></div>
          <div class="tile-mov-item"><span class="tile-mov-code">AE</span><span class="tile-mov-val" style="color:#36B0C9">${mes.autoelevadores}h</span></div>
          <div class="tile-mov-item"><span class="tile-mov-code">CAM</span><span class="tile-mov-val" style="color:#36B0C9">${mes.camion}h</span></div>
          <div class="tile-mov-item"><span class="tile-mov-code">LIM</span><span class="tile-mov-val" style="color:#36B0C9">${mes.limpieza}h</span></div>
        </div>
        <div class="tile-title one-line tile-label">USO EQUIPOS</div>
      </div>`;
  }
  // ESTIBAS CONGELADAS: valor del mes en curso
  if (item.title === 'ESTIBAS CONGELADAS') {
    const actual = estibasCongeladasData.meses[estibasCongeladasData.meses.length-1].valor;
    return `
      <div class="tile-kpi-wrap">
        <div class="tile-kpi-top"><span class="tile-kpi-badge">❄</span></div>
        <div class="tile-kpi-value tile-kpi-value--md" style="color:#60c8e0">${actual.toLocaleString('es-AR')}</div>
        <div class="tile-kpi-sublabel">ESTIBAS MES</div>
        <div class="tile-title one-line tile-label">CONGELADAS</div>
      </div>`;
  }
  // FACTURACIÓN
  if (item.title === 'FACTURACION') {
    return `
      <div class="tile-kpi-wrap">
        <div class="tile-kpi-top"><span class="tile-kpi-badge" style="background:rgba(249,115,22,.2);color:#F97316;border-color:rgba(249,115,22,.4)">FAC</span></div>
        <div style="font-size:2rem;line-height:1">🧾</div>
        <div class="tile-kpi-sublabel">RESÚMENES</div>
        <div class="tile-title one-line tile-label">FACTURACIÓN</div>
      </div>`;
  }
  // REMITOS
  if (item.title === 'REMITOS') {
    return `
      <div class="tile-kpi-wrap">
        <div class="tile-kpi-top"><span class="tile-kpi-badge" style="background:rgba(167,139,250,.2);color:#A78BFA;border-color:rgba(167,139,250,.4)">REM</span></div>
        <div style="font-size:2rem;line-height:1">📋</div>
        <div class="tile-kpi-sublabel">DESCARGA</div>
        <div class="tile-title one-line tile-label">REMITOS</div>
      </div>`;
  }
  // COMPRAS
  if (item.title === 'COMPRAS') {
    const pendientes = (typeof comprasDB !== 'undefined') ? comprasDB.ordenes.filter(o => o.estado === 'PENDIENTE').length : 0;
    return `
      <div class="tile-kpi-wrap">
        <div class="tile-kpi-top"><span class="tile-kpi-badge" style="background:rgba(250,204,21,.15);color:#FACC15;border-color:rgba(250,204,21,.3)">OC</span></div>
        <div class="tile-kpi-value tile-kpi-value--lg" style="color:#FACC15">${pendientes > 0 ? pendientes : '—'}</div>
        <div class="tile-kpi-sublabel">${pendientes > 0 ? 'PENDIENTES' : 'ÓRDENES'}</div>
        <div class="tile-title one-line tile-label">COMPRAS</div>
      </div>`;
  }
  return null; // usar HTML por defecto
}

function getIconMarkup(title) {
  const imageMap = {
    'SISTEMA DE GESTION': 'img/iso_logo.png',
    'SENASA':             'img/senasa_logo.png'
  };
  if (imageMap[title]) {
    return `<img src="${imageMap[title]}" alt="${title}" onerror="this.style.display='none';this.parentElement.textContent='${getTextInitials(title)}';">`;
  }
  const symbolMap = {
    'LOGISTICA NACIONAL':     '🚛',
    'LOGISTICA FISCAL':       '📦',
    'CALIDAD':                '🌡',
    'PORTERIA':               '🚪',
    'TRANSPORTE':             '🚚',
    'INDICADORES':            '📊',
    'REPORTES':               '📑',
    'DASHBOARD':              '🖥',
    'ADMINISTRACION':         '🗂',
    'RRHH':                   '👥',
    'MANTENIMIENTO':          '🔧',
    'CONFIGURACION':          '⚙',
    'PRODUCTOS':              '📦',
    'OTROS SISTEMAS':         '🧩',
    'INFORMACION DOCUMENTADA':'📚',
    'REGISTRO PR 44':         '🗂',
    'FORMACION':              '👨‍🏫',
    'MAPA DE BOXES':          '🗺'
  };
  return symbolMap[title] || getTextInitials(title);
}

function getEquipmentIconMarkup(item) {
  if (item.isImage) {
    return `<img src="${item.icono}" alt="${item.tipo}" onerror="this.style.display='none';this.parentElement.textContent='🛗';">`;
  }
  return item.icono;
}

function getGridLayout(count) {
  // Returns a CSS class and also sets a data-count attribute on menuGrid
  // so CSS can use the right tile size
  if (count === 1)  return 'layout-1';
  if (count === 2)  return 'layout-2';
  if (count === 3)  return 'layout-3';
  if (count <= 4)   return 'layout-4';
  if (count <= 5)   return 'layout-5';
  if (count <= 6)   return 'layout-6';
  if (count <= 8)   return 'layout-8';
  if (count <= 9)   return 'layout-9';
  if (count <= 12)  return 'layout-12';
  if (count <= 14)  return 'layout-14';
  return 'layout-16';
}

function formatTitle(title) {
  const words = title.trim().split(/\s+/);
  if (words.length <= 1) return title;
  if (words.length === 2) return `${words[0]}<br>${words[1]}`;
  const mid = Math.ceil(words.length / 2);
  return `${words.slice(0, mid).join(' ')}<br>${words.slice(mid).join(' ')}`;
}

function titleClass(title) {
  return title.trim().split(/\s+/).length >= 2 ? 'two-lines' : 'one-line';
}

function openModule(url) {
  // Si es URL del sistema interno CINA, embeber en iframe
  if (url && url.includes('sistema.cinafrio.com')) {
    renderIframe(url);
    return;
  }
  window.open(url, '_blank', 'noopener,noreferrer');
}

/* ── IFRAME EMBEBIDO ─────────────────────────────────────────
   Carga páginas internas del sistema dentro del workspace,
   ocultando el sidebar y el botón de toggle de AdminLTE.
   ──────────────────────────────────────────────────────────── */
function renderIframe(url) {
  showMetaPanel(true);
  setExpandedMode(false);

  workspace.innerHTML = `
    <div class="iframe-wrap">
      <div class="iframe-toolbar">
        <span class="iframe-url-label">🌐 sistema.cinafrio.com</span>
        <a class="iframe-open-btn" href="${url}" target="_blank" title="Abrir en pestaña nueva">
          ↗ Nueva pestaña
        </a>
      </div>
      <iframe
        id="cinaFrame"
        src="${url}"
        class="cina-iframe"
        frameborder="0"
      ></iframe>
    </div>
  `;

  // Inyectar CSS en el iframe para ocultar sidebar AdminLTE
  const frame = document.getElementById('cinaFrame');
  if (frame) {
    frame.addEventListener('load', () => {
      try {
        const doc = frame.contentDocument || frame.contentWindow.document;
        const style = doc.createElement('style');
        style.textContent = `
          .main-sidebar { display: none !important; }
          .sidebar-toggle { display: none !important; }
          .content-wrapper { margin-left: 0 !important; }
          .main-header .logo { display: none !important; }
          .main-header .navbar { margin-left: 0 !important; }
          body { overflow-y: auto !important; }
          .main-footer { display: none !important; }
        `;
        doc.head.appendChild(style);
        // También ocultar via DOM directo
        ['aside.main-sidebar', '.sidebar-toggle', '.logo'].forEach(sel => {
          const el = doc.querySelector(sel);
          if (el) el.style.cssText += 'display:none!important';
        });
        const cw = doc.querySelector('.content-wrapper');
        if (cw) cw.style.marginLeft = '0';
        const nav = doc.querySelector('.navbar');
        if (nav) nav.style.marginLeft = '0';
      } catch(e) {
        // Cross-origin: no se puede modificar — igualmente el iframe carga
        console.warn('iframe cross-origin, no se pudo inyectar CSS:', e);
      }
    });
  }

  historyStack.push({ title: 'INICIO', children: menuTree });
  syncBackBtn();
}

function darkenColor(hex, amount) {
  let color = hex.replace('#', '');
  if (color.length === 3) color = color.split('').map(c => c + c).join('');
  let r = parseInt(color.substring(0, 2), 16);
  let g = parseInt(color.substring(2, 4), 16);
  let b = parseInt(color.substring(4, 6), 16);
  r = Math.max(0, Math.min(255, r + amount));
  g = Math.max(0, Math.min(255, g + amount));
  b = Math.max(0, Math.min(255, b + amount));
  return '#' + [r, g, b].map(v => v.toString(16).padStart(2, '0')).join('');
}

function getTileColors(item, level) {
  const base = level === 0 ? (mainColorMap[item.title] || '#001F60') : '#163A7A';
  return { start: base, end: darkenColor(base, -28) };
}

function buildClientSlots(clientes) {
  const total  = 6;
  const filled = clientes.slice(0, total);
  const slots  = filled.map(c => `<div class="client-badge" title="${c}">${c}</div>`);
  for (let i = slots.length; i < total; i++) slots.push('<div class="client-badge empty"></div>');
  return slots.join('');
}

function getTemperatureStyle(temp) {
  if (temp >= 0)   return 'linear-gradient(135deg,#16a34a,#22c55e)';
  if (temp > -10)  return 'linear-gradient(135deg,#0891b2,#06b6d4)';
  if (temp > -20)  return 'linear-gradient(135deg,#2563eb,#3b82f6)';
  if (temp > -25)  return 'linear-gradient(135deg,#4338ca,#6366f1)';
  return 'linear-gradient(135deg,#7e22ce,#a855f7)';
}

function buildStatusRow(item) {
  return `
    <div class="dashboard-status-row">
      <div class="status-box ${item.puerta    ? 'true-state' : 'false-state'}" title="Puerta">🚪</div>
      <div class="status-box ${item.ventilador ? 'true-state' : 'false-state'}" title="Ventilador">🌀</div>
    </div>`;
}

function getEquipmentCardClass(estado) {
  if (estado === 'En marcha') return 'state-running-card';
  if (estado === 'Cargando')  return 'state-charging-card';
  return 'state-failure-card';
}

/* ── MODAL DE EDICIÓN DE EQUIPO ─────────────────────────────── */
function openEquipmentModal(index) {
  const item = dashboardEquipamientoData[index];

  // Eliminar modal previo si existe
  document.getElementById('equipModal')?.remove();

  const modal = document.createElement('div');
  modal.id = 'equipModal';
  modal.className = 'equip-modal-overlay';

  modal.innerHTML = `
    <div class="equip-modal">
      <div class="equip-modal-header">
        <div class="equip-modal-title">
          <span class="equip-modal-denom">${item.denominacion}</span>
          <span class="equip-modal-type">${item.tipo}</span>
        </div>
        <button class="equip-modal-close" id="equipModalClose">✕</button>
      </div>

      <div class="equip-modal-body">

        <div class="equip-modal-field">
          <label class="equip-modal-label" for="em_estado">Estado</label>
          <div class="equip-modal-select-wrap">
            ${ESTADOS_EQUIPO.map(e => `
              <label class="equip-state-opt ${e === item.estado ? 'selected' : ''} state-opt-${e.toLowerCase().replace(/ /g,'-')}">
                <input type="radio" name="em_estado" value="${e}" ${e === item.estado ? 'checked' : ''}>
                ${e}
              </label>`).join('')}
          </div>
        </div>

        <div class="equip-modal-field">
          <label class="equip-modal-label" for="em_planta">Planta</label>
          <select id="em_planta" class="equip-modal-select">
            ${PLANTAS_EQUIPO.map(p => `<option value="${p}" ${p === item.planta ? 'selected' : ''}>${p}</option>`).join('')}
          </select>
        </div>

        <div class="equip-modal-field">
          <label class="equip-modal-label" for="em_usuario">Usuario</label>
          <input id="em_usuario" class="equip-modal-input" type="text" value="${item.usuario}" placeholder="Nombre de usuario">
        </div>

        <div class="equip-modal-field">
          <label class="equip-modal-label" for="em_horas">Horas en estado</label>
          <input id="em_horas" class="equip-modal-input" type="text" value="${item.horas}" placeholder="Ej: 01:30hs">
        </div>

      </div>

      <div class="equip-modal-footer">
        <button class="equip-modal-btn cancel" id="equipModalCancel">Cancelar</button>
        <button class="equip-modal-btn save"   id="equipModalSave">💾 Guardar cambios</button>
      </div>
    </div>
  `;

  document.body.appendChild(modal);

  /* Resaltar opción de estado al hacer click */
  modal.querySelectorAll('input[name="em_estado"]').forEach(radio => {
    radio.addEventListener('change', () => {
      modal.querySelectorAll('.equip-state-opt').forEach(l => l.classList.remove('selected'));
      radio.parentElement.classList.add('selected');
    });
  });

  /* Cerrar al click fuera */
  modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });

  document.getElementById('equipModalClose').addEventListener('click', closeModal);
  document.getElementById('equipModalCancel').addEventListener('click', closeModal);

  document.getElementById('equipModalSave').addEventListener('click', () => {
    const nuevoEstado  = modal.querySelector('input[name="em_estado"]:checked')?.value || item.estado;
    const nuevaPlanta  = document.getElementById('em_planta').value.trim();
    const nuevoUsuario = document.getElementById('em_usuario').value.trim();
    const nuevasHoras  = document.getElementById('em_horas').value.trim();

    // Validaciones básicas
    if (!nuevoUsuario) { showModalError('El campo usuario no puede estar vacío.'); return; }
    if (!nuevasHoras)  { showModalError('El campo horas no puede estar vacío.');  return; }

    // Persistir en el array fuente
    dashboardEquipamientoData[index].estado  = nuevoEstado;
    dashboardEquipamientoData[index].planta  = nuevaPlanta;
    dashboardEquipamientoData[index].usuario = nuevoUsuario;
    dashboardEquipamientoData[index].horas   = nuevasHoras;

    closeModal();

    // Re-renderizar el dashboard con los datos actualizados
    renderDashboardEquipamiento();

    showToast(`✓ ${item.denominacion} actualizado correctamente`);
  });

  // Animar entrada
  requestAnimationFrame(() => modal.classList.add('visible'));

  function closeModal() {
    modal.classList.remove('visible');
    setTimeout(() => modal.remove(), 220);
  }

  function showModalError(msg) {
    let err = modal.querySelector('.equip-modal-error');
    if (!err) {
      err = document.createElement('p');
      err.className = 'equip-modal-error';
      modal.querySelector('.equip-modal-footer').prepend(err);
    }
    err.textContent = msg;
  }
}

/* Toast de confirmación */
function showToast(msg) {
  document.getElementById('cinaToast')?.remove();
  const toast = document.createElement('div');
  toast.id = 'cinaToast';
  toast.className = 'cina-toast';
  toast.textContent = msg;
  document.body.appendChild(toast);
  requestAnimationFrame(() => toast.classList.add('visible'));
  setTimeout(() => {
    toast.classList.remove('visible');
    setTimeout(() => toast.remove(), 300);
  }, 2800);
}

/* ── RENDERS ────────────────────────────────────────────────── */
function renderDashboardLogisticaNacional() {
  setHeader('DASHBOARD LOGISTICA NACIONAL');
  setExpandedMode(false);
  showMetaPanel(true);
  menuGrid.className = '';
  menuGrid.innerHTML = '';

  const grid = document.createElement('div');
  grid.className = 'dashboard-grid';

  dashboardLogisticaNacionalData.forEach(item => {
    const card = document.createElement('button');
    card.className = 'dashboard-card';
    card.type = 'button';
    card.innerHTML = `
      <div class="dashboard-left">
        <div class="dashboard-temp" style="background:${getTemperatureStyle(item.temperatura)}">
          <div class="dashboard-temp-label">Temp.</div>
          <div class="dashboard-temp-value">${item.temperatura}°C</div>
        </div>
        <div class="dashboard-alert">
          <div class="dashboard-alert-value">${item.extra}</div>
        </div>
      </div>
      <div class="dashboard-main">
        <div class="dashboard-title">${item.tipo} ${item.nombre}</div>
        <div class="dashboard-number">${item.estibas}</div>
        <div class="dashboard-sub">ESTIBAS</div>
        ${buildStatusRow(item)}
      </div>
      <div class="dashboard-clients">${buildClientSlots(item.clientes)}</div>`;
    grid.appendChild(card);
  });

  menuGrid.appendChild(grid);
  syncBackBtn();
}

function renderDashboardEquipamiento() {
  setHeader('DASHBOARD EQUIPAMIENTO');
  setExpandedMode(false);
  showMetaPanel(true);
  menuGrid.className = '';
  menuGrid.innerHTML = '';

  const grid = document.createElement('div');
  grid.className = 'equipment-grid';

  dashboardEquipamientoData.forEach((item, index) => {
    const card = document.createElement('div');
    card.className = `equipment-card ${getEquipmentCardClass(item.estado)}`;

    card.innerHTML = `
      <div class="equipment-header">
        <div class="equipment-icon">${getEquipmentIconMarkup(item)}</div>
        <div class="equipment-title-wrap">
          <div class="equipment-type">${item.tipo}</div>
          <div class="equipment-name">${item.denominacion}</div>
        </div>
        <button class="equipment-edit-btn" title="Editar equipo" data-index="${index}">✏️</button>
      </div>
      <div class="equipment-row">
        <div class="equipment-label">Estado</div>
        <div class="equipment-value"><span class="state-badge">${item.estado}</span></div>
      </div>
      <div class="equipment-row">
        <div class="equipment-label">Planta</div>
        <div class="equipment-value"><span class="plant-badge">${item.planta}</span></div>
      </div>
      <div class="equipment-row">
        <div class="equipment-label">Hs. estado</div>
        <div class="equipment-value">${item.horas}</div>
      </div>
      <div class="equipment-row">
        <div class="equipment-label">Usuario</div>
        <div class="equipment-value">${item.usuario}</div>
      </div>`;

    // Botón editar dentro de la card
    card.querySelector('.equipment-edit-btn').addEventListener('click', e => {
      e.stopPropagation();
      openEquipmentModal(index);
    });

    // Click en la card también abre el editor
    card.addEventListener('click', () => openEquipmentModal(index));
    card.style.cursor = 'pointer';

    grid.appendChild(card);
  });

  menuGrid.appendChild(grid);
  syncBackBtn();
}


/* ═══════════════════════════════════════════════════════════════
   INDICADOR: CARGAS I-2
   ═══════════════════════════════════════════════════════════════ */

// Datos base del indicador
const cargasI2Data = {
  planificadas: 10,
  cumplidas: 7,
  pendientes: 12,
  postergadas: 2,
  enEjecucion: 2,
};

// Datos históricos mensuales (últimos 12 meses) — cumplidas / total
const cargasI2Mensual = [
  { mes: 'Abr 24', cumplidas: 42, total: 58 },
  { mes: 'May 24', cumplidas: 55, total: 70 },
  { mes: 'Jun 24', cumplidas: 38, total: 52 },
  { mes: 'Jul 24', cumplidas: 61, total: 74 },
  { mes: 'Ago 24', cumplidas: 49, total: 63 },
  { mes: 'Sep 24', cumplidas: 53, total: 68 },
  { mes: 'Oct 24', cumplidas: 47, total: 60 },
  { mes: 'Nov 24', cumplidas: 66, total: 79 },
  { mes: 'Dic 24', cumplidas: 44, total: 58 },
  { mes: 'Ene 25', cumplidas: 58, total: 71 },
  { mes: 'Feb 25', cumplidas: 51, total: 65 },
  { mes: 'Mar 25', cumplidas: 7,  total: 33 },
];


// Datos cargas por depósito (misma proporción, dividida Nacional 60% / Fiscal 40%)
const cargasI2Nacional = [
  { mes: 'Abr 24', cumplidas: 25, total: 35 },
  { mes: 'May 24', cumplidas: 33, total: 42 },
  { mes: 'Jun 24', cumplidas: 23, total: 31 },
  { mes: 'Jul 24', cumplidas: 37, total: 44 },
  { mes: 'Ago 24', cumplidas: 29, total: 38 },
  { mes: 'Sep 24', cumplidas: 32, total: 41 },
  { mes: 'Oct 24', cumplidas: 28, total: 36 },
  { mes: 'Nov 24', cumplidas: 40, total: 47 },
  { mes: 'Dic 24', cumplidas: 26, total: 35 },
  { mes: 'Ene 25', cumplidas: 35, total: 43 },
  { mes: 'Feb 25', cumplidas: 31, total: 39 },
  { mes: 'Mar 25', cumplidas: 4,  total: 20 },
];
const cargasI2Fiscal = [
  { mes: 'Abr 24', cumplidas: 17, total: 23 },
  { mes: 'May 24', cumplidas: 22, total: 28 },
  { mes: 'Jun 24', cumplidas: 15, total: 21 },
  { mes: 'Jul 24', cumplidas: 24, total: 30 },
  { mes: 'Ago 24', cumplidas: 20, total: 25 },
  { mes: 'Sep 24', cumplidas: 21, total: 27 },
  { mes: 'Oct 24', cumplidas: 19, total: 24 },
  { mes: 'Nov 24', cumplidas: 26, total: 32 },
  { mes: 'Dic 24', cumplidas: 18, total: 23 },
  { mes: 'Ene 25', cumplidas: 23, total: 28 },
  { mes: 'Feb 25', cumplidas: 20, total: 26 },
  { mes: 'Mar 25', cumplidas: 3,  total: 13 },
];
function renderIndicadorCargasI2() {
  setHeader('INDICADORES');
  setExpandedMode(false);
  showMetaPanel(true);
  menuGrid.className = '';
  menuGrid.innerHTML = '';

  const wrap = document.createElement('div');
  wrap.className = 'indicador-wrap';

  // ── CARD PRINCIPAL ──────────────────────────────────────────
  const card = document.createElement('button');
  card.type = 'button';
  card.className = 'indicador-card';
  card.title = 'Ver detalle por período';

  const total = cargasI2Data.planificadas + cargasI2Data.cumplidas +
                cargasI2Data.pendientes  + cargasI2Data.postergadas +
                cargasI2Data.enEjecucion;
  const pct = Math.round((cargasI2Data.cumplidas / (cargasI2Data.planificadas || 1)) * 100);

  card.innerHTML = `
    <div class="indicador-card-header">
      <div class="indicador-card-title">
        <span class="indicador-badge">I-2</span>
        CARGAS
      </div>
      <span class="indicador-hint">Toca para ver detalle por período →</span>
    </div>
    <div class="indicador-card-body">
      <canvas id="cargasPieChart" width="220" height="220"></canvas>
      <div class="indicador-legend">
        <div class="ind-leg-item"><span class="ind-leg-dot" style="background:#36B0C9"></span>Planificadas<strong>${cargasI2Data.planificadas}</strong></div>
        <div class="ind-leg-item"><span class="ind-leg-dot" style="background:#00A887"></span>Cumplidas<strong>${cargasI2Data.cumplidas}</strong></div>
        <div class="ind-leg-item"><span class="ind-leg-dot" style="background:#F97316"></span>Pendientes<strong>${cargasI2Data.pendientes}</strong></div>
        <div class="ind-leg-item"><span class="ind-leg-dot" style="background:#DC2626"></span>Postergadas<strong>${cargasI2Data.postergadas}</strong></div>
        <div class="ind-leg-item"><span class="ind-leg-dot" style="background:#FACC15"></span>En ejecución<strong>${cargasI2Data.enEjecucion}</strong></div>
        <div class="ind-leg-sep"></div>
        <div class="ind-leg-item ind-leg-pct"><span>Cumpl. / Planif.</span><strong>${pct}%</strong></div>
      </div>
    </div>
  `;

  card.addEventListener('click', () => openCargasI2Modal());
  wrap.appendChild(card);

  // ── TABLA MENSUAL ────────────────────────────────────────────
  const mensualSection = document.createElement('div');
  mensualSection.className = 'indicador-mensual';
  mensualSection.innerHTML = `
    <div class="indicador-mensual-title">📅 Promedio mensual — Cumplidas / Total (últimos 12 meses)</div>
    <div class="indicador-mensual-grid">
      ${cargasI2Mensual.map(m => {
        const p = Math.round((m.cumplidas / m.total) * 100);
        const color = p >= 80 ? '#00A887' : p >= 60 ? '#F97316' : '#DC2626';
        return `
          <div class="ind-mes-card">
            <div class="ind-mes-label">${m.mes}</div>
            <div class="ind-mes-pct" style="color:${color}">${p}%</div>
            <div class="ind-mes-bar-wrap">
              <div class="ind-mes-bar-fill" style="width:${p}%;background:${color}"></div>
            </div>
            <div class="ind-mes-detail">${m.cumplidas}/${m.total}</div>
          </div>`;
      }).join('')}
    </div>
  `;
  wrap.appendChild(mensualSection);

  // ── SEPARADOR DEPOSITOS ──────────────────────────────────────
  const depTitle = document.createElement('div');
  depTitle.className = 'indicador-mensual';
  depTitle.innerHTML = '<div class="indicador-mensual-title">🏭 Cargas por Depósito — Depósito Nacional vs Depósito Fiscal</div>';
  wrap.appendChild(depTitle);

  // Helper: build deposito card without nested template literals
  const buildDepCard = (datos, label, badgeColor, pieId) => {
    const total  = datos.reduce((s,m)=>s+m.total,0);
    const cumpl  = datos.reduce((s,m)=>s+m.cumplidas,0);
    const pctG   = Math.round((cumpl/total)*100);
    const colorG = pctG>=80?'#00A887':pctG>=60?'#F97316':'#DC2626';

    const mesCards = datos.map(m => {
      const p = Math.round((m.cumplidas/m.total)*100);
      const c = p>=80?'#00A887':p>=60?'#F97316':'#DC2626';
      return '<div class="ind-mes-card">'
           + '<div class="ind-mes-label">'+m.mes+'</div>'
           + '<div class="ind-mes-pct" style="color:'+c+'">'+p+'%</div>'
           + '<div class="ind-mes-bar-wrap"><div class="ind-mes-bar-fill" style="width:'+p+'%;background:'+c+'"></div></div>'
           + '<div class="ind-mes-detail">'+m.cumplidas+'/'+m.total+'</div>'
           + '</div>';
    }).join('');

    const col = document.createElement('div');
    col.className = 'cargas-dep-col';
    col.innerHTML =
      '<div class="indicador-card indicador-card-static cargas-dep-card">'
    + '  <div class="indicador-card-header">'
    + '    <div class="indicador-card-title">'
    + '      <span class="indicador-badge" style="background:'+badgeColor+'20;color:'+badgeColor+';border-color:'+badgeColor+'40">I-2</span>'
    + '      '+label
    + '    </div>'
    + '  </div>'
    + '  <div class="indicador-card-body" style="gap:12px">'
    + '    <canvas id="'+pieId+'" width="160" height="160"></canvas>'
    + '    <div class="indicador-legend">'
    + '      <div class="ind-leg-item"><span class="ind-leg-dot" style="background:#36B0C9"></span>Planif.<strong>'+Math.round(total*0.3)+'</strong></div>'
    + '      <div class="ind-leg-item"><span class="ind-leg-dot" style="background:#00A887"></span>Cumpl.<strong>'+cumpl+'</strong></div>'
    + '      <div class="ind-leg-item"><span class="ind-leg-dot" style="background:#F97316"></span>Pend.<strong>'+Math.round(total*0.18)+'</strong></div>'
    + '      <div class="ind-leg-item"><span class="ind-leg-dot" style="background:#DC2626"></span>Post.<strong>'+Math.round(total*0.06)+'</strong></div>'
    + '      <div class="ind-leg-sep"></div>'
    + '      <div class="ind-leg-item ind-leg-pct"><span>Cumpl.</span><strong style="color:'+colorG+'">'+pctG+'%</strong></div>'
    + '    </div>'
    + '  </div>'
    + '  <div class="indicador-mensual-grid cargas-dep-grid" style="margin-top:10px">'
    + mesCards
    + '  </div>'
    + '</div>';
    return col;
  };

  const depRow = document.createElement('div');
  depRow.className = 'cargas-dep-row';
  depRow.appendChild(buildDepCard(cargasI2Nacional, 'Depósito Nacional', '#36B0C9', 'pieNacional'));
  depRow.appendChild(buildDepCard(cargasI2Fiscal,   'Depósito Fiscal',   '#A78BFA', 'pieFiscal'));
  wrap.appendChild(depRow);

  menuGrid.appendChild(wrap);
  syncBackBtn();

  // Draw all pies after DOM is ready
  requestAnimationFrame(() => {
    drawCargasPie();
    drawDepPie('pieNacional', cargasI2Nacional, '#36B0C9');
    drawDepPie('pieFiscal',   cargasI2Fiscal,   '#A78BFA');
  });
  syncBackBtn();
}


function drawDepPie(canvasId, datos, accentColor) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const W = canvas.width, H = canvas.height;
  const cx = W/2, cy = H/2;
  const outerR = Math.min(W,H)/2 - 4;
  const innerR = outerR * 0.52;
  const total  = datos.reduce((s,m)=>s+m.total,0);
  const cumpl  = datos.reduce((s,m)=>s+m.cumplidas,0);
  const pend   = Math.round(total*0.18);
  const post   = Math.round(total*0.06);
  const planif = total - cumpl - pend - post;
  const slices = [
    { value: planif, color: '#36B0C9' },
    { value: cumpl,  color: '#00A887' },
    { value: pend,   color: '#F97316' },
    { value: post,   color: '#DC2626' },
  ];
  const sum = slices.reduce((s,sl)=>s+sl.value,0);
  let angle = -Math.PI/2;
  ctx.clearRect(0,0,W,H);
  slices.forEach(sl => {
    const sweep = (sl.value/sum)*2*Math.PI;
    ctx.beginPath();
    ctx.moveTo(cx,cy);
    ctx.arc(cx,cy,outerR,angle,angle+sweep);
    ctx.closePath();
    ctx.fillStyle = sl.color;
    ctx.fill();
    angle += sweep;
  });
  ctx.beginPath();
  ctx.arc(cx,cy,innerR,0,2*Math.PI);
  ctx.fillStyle = '#0f1e35';
  ctx.fill();
  const pct = Math.round((cumpl/sum)*100);
  const color = pct>=80?'#00A887':pct>=60?'#F97316':'#DC2626';
  ctx.fillStyle = color;
  ctx.font = 'bold 22px Segoe UI';
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.fillText(pct+'%', cx, cy-8);
  ctx.fillStyle = 'rgba(255,255,255,.5)';
  ctx.font = '10px Segoe UI';
  ctx.fillText('cumpl.', cx, cy+10);
}
function drawCargasPie() {
  const canvas = document.getElementById('cargasPieChart');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const W = canvas.width, H = canvas.height;
  const cx = W / 2, cy = H / 2;
  const outerR = Math.min(W, H) / 2 - 6;
  const innerR = outerR * 0.55;

  const slices = [
    { value: cargasI2Data.planificadas, color: '#36B0C9' },
    { value: cargasI2Data.cumplidas,    color: '#00A887' },
    { value: cargasI2Data.pendientes,   color: '#F97316' },
    { value: cargasI2Data.postergadas,  color: '#DC2626' },
    { value: cargasI2Data.enEjecucion,  color: '#FACC15' },
  ];
  const total = slices.reduce((s, x) => s + x.value, 0);

  ctx.clearRect(0, 0, W, H);
  let startAngle = -Math.PI / 2;
  slices.forEach(slice => {
    const angle = (slice.value / total) * Math.PI * 2;
    ctx.beginPath();
    ctx.moveTo(cx, cy);
    ctx.arc(cx, cy, outerR, startAngle, startAngle + angle);
    ctx.closePath();
    ctx.fillStyle = slice.color;
    ctx.fill();
    startAngle += angle;
  });
  // Donut hole
  ctx.beginPath();
  ctx.arc(cx, cy, innerR, 0, Math.PI * 2);
  ctx.fillStyle = 'rgba(0,20,60,0.92)';
  ctx.fill();
  // Centro: total
  ctx.fillStyle = '#fff';
  ctx.font = 'bold 28px Segoe UI, sans-serif';
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.fillText(total, cx, cy - 10);
  ctx.font = '11px Segoe UI, sans-serif';
  ctx.fillStyle = 'rgba(255,255,255,.6)';
  ctx.fillText('TOTAL', cx, cy + 14);
}

/* ── MODAL DETALLE POR PERÍODO ─────────────────────────────── */
function openCargasI2Modal() {
  // Datos simulados por día (se reemplazarán al filtrar)
  const today = new Date();
  const defEnd = today.toISOString().split('T')[0];
  const defStart = new Date(today - 30 * 864e5).toISOString().split('T')[0];

  const overlay = document.createElement('div');
  overlay.className = 'equip-modal-overlay';
  overlay.id = 'cargasI2Overlay';
  document.body.appendChild(overlay);

  overlay.innerHTML = `
    <div class="equip-modal cargas-modal">
      <div class="equip-modal-header">
        <div class="equip-modal-title">
          <span class="equip-modal-denom">CARGAS I-2</span>
          <span class="equip-modal-type">Detalle por período</span>
        </div>
        <button class="equip-modal-close" id="cargasModalClose">✕</button>
      </div>
      <div class="equip-modal-body cargas-modal-body">
        <div class="cargas-filtros">
          <div class="equip-modal-field">
            <label class="equip-modal-label">Fecha inicio</label>
            <input type="date" class="equip-modal-input" id="cargasFechaInicio" value="${defStart}">
          </div>
          <div class="equip-modal-field">
            <label class="equip-modal-label">Fecha fin</label>
            <input type="date" class="equip-modal-input" id="cargasFechaFin" value="${defEnd}">
          </div>
          <button class="equip-modal-btn save cargas-apply-btn" id="cargasApplyBtn">Ver gráfico</button>
        </div>
        <div class="cargas-chart-wrap">
          <canvas id="cargasLineChart"></canvas>
        </div>
        <div class="cargas-chart-legend">
          <span><span class="ind-leg-dot" style="background:#36B0C9;display:inline-block;width:12px;height:12px;border-radius:50%;margin-right:4px"></span>Planificadas</span>
          <span><span class="ind-leg-dot" style="background:#00A887;display:inline-block;width:12px;height:12px;border-radius:50%;margin-right:4px"></span>Cumplidas</span>
          <span><span class="ind-leg-dot" style="background:#F97316;display:inline-block;width:12px;height:12px;border-radius:50%;margin-right:4px"></span>Pendientes</span>
          <span><span class="ind-leg-dot" style="background:#DC2626;display:inline-block;width:12px;height:12px;border-radius:50%;margin-right:4px"></span>Postergadas</span>
          <span><span class="ind-leg-dot" style="background:#FACC15;display:inline-block;width:12px;height:12px;border-radius:50%;margin-right:4px"></span>En ejecución</span>
        </div>
      </div>
    </div>
  `;

  requestAnimationFrame(() => overlay.classList.add('visible'));

  document.getElementById('cargasModalClose').addEventListener('click', () => {
    overlay.classList.remove('visible');
    setTimeout(() => overlay.remove(), 250);
  });
  overlay.addEventListener('click', e => {
    if (e.target === overlay) {
      overlay.classList.remove('visible');
      setTimeout(() => overlay.remove(), 250);
    }
  });

  // Dibujar al abrir
  drawCargasLine(defStart, defEnd);

  document.getElementById('cargasApplyBtn').addEventListener('click', () => {
    const fi = document.getElementById('cargasFechaInicio').value;
    const ff = document.getElementById('cargasFechaFin').value;
    if (fi && ff && fi <= ff) drawCargasLine(fi, ff);
  });
}

function generateDayData(startStr, endStr) {
  // Genera datos simulados día a día entre las fechas
  const start = new Date(startStr);
  const end   = new Date(endStr);
  const days  = [];
  const rng   = (min, max) => Math.floor(Math.random() * (max - min + 1)) + min;
  let seed    = start.getTime(); // pseudo-seed para consistencia
  const rand  = () => { seed = (seed * 1664525 + 1013904223) & 0xffffffff; return Math.abs(seed) / 0xffffffff; };

  for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
    const plan = Math.floor(rand() * 8) + 3;
    const cum  = Math.floor(rand() * plan);
    const pend = Math.floor(rand() * 6) + 1;
    const post = Math.floor(rand() * 3);
    const ejec = Math.floor(rand() * 3);
    days.push({
      label: d.toLocaleDateString('es-AR', { day:'2-digit', month:'2-digit' }),
      planificadas: plan, cumplidas: cum,
      pendientes: pend, postergadas: post, enEjecucion: ejec
    });
  }
  return days;
}

function drawCargasLine(startStr, endStr) {
  const canvas = document.getElementById('cargasLineChart');
  if (!canvas) return;

  const days = generateDayData(startStr, endStr);
  const ctx  = canvas.getContext('2d');

  // Ajustar tamaño al contenedor
  const wrap = canvas.parentElement;
  canvas.width  = wrap.clientWidth  || 600;
  canvas.height = wrap.clientHeight || 280;
  const W = canvas.width, H = canvas.height;
  const PAD = { top: 20, right: 20, bottom: 42, left: 36 };
  const chartW = W - PAD.left - PAD.right;
  const chartH = H - PAD.top  - PAD.bottom;

  const series = [
    { key: 'planificadas', color: '#36B0C9' },
    { key: 'cumplidas',    color: '#00A887' },
    { key: 'pendientes',   color: '#F97316' },
    { key: 'postergadas',  color: '#DC2626' },
    { key: 'enEjecucion',  color: '#FACC15' },
  ];

  // Max value for scale
  const maxVal = Math.max(...days.flatMap(d => series.map(s => d[s.key]))) + 2;
  const xStep = chartW / Math.max(days.length - 1, 1);

  ctx.clearRect(0, 0, W, H);

  // Background grid lines
  ctx.strokeStyle = 'rgba(255,255,255,.08)';
  ctx.lineWidth = 1;
  for (let i = 0; i <= 4; i++) {
    const y = PAD.top + chartH - (i / 4) * chartH;
    ctx.beginPath(); ctx.moveTo(PAD.left, y); ctx.lineTo(PAD.left + chartW, y); ctx.stroke();
    ctx.fillStyle = 'rgba(255,255,255,.45)';
    ctx.font = '10px Segoe UI';
    ctx.textAlign = 'right';
    ctx.fillText(Math.round((i / 4) * maxVal), PAD.left - 4, y + 4);
  }

  // X axis labels (show every N days to avoid crowding)
  const step = Math.max(1, Math.ceil(days.length / 12));
  ctx.fillStyle = 'rgba(255,255,255,.55)';
  ctx.font = '10px Segoe UI';
  ctx.textAlign = 'center';
  days.forEach((d, i) => {
    if (i % step === 0 || i === days.length - 1) {
      const x = PAD.left + i * xStep;
      ctx.fillText(d.label, x, H - PAD.bottom + 14);
    }
  });

  // Draw lines + fill areas
  series.forEach(s => {
    // Fill area
    ctx.beginPath();
    days.forEach((d, i) => {
      const x = PAD.left + i * xStep;
      const y = PAD.top + chartH - (d[s.key] / maxVal) * chartH;
      i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
    });
    ctx.lineTo(PAD.left + (days.length - 1) * xStep, PAD.top + chartH);
    ctx.lineTo(PAD.left, PAD.top + chartH);
    ctx.closePath();
    ctx.fillStyle = s.color + '22';
    ctx.fill();

    // Line
    ctx.beginPath();
    ctx.strokeStyle = s.color;
    ctx.lineWidth = 2;
    ctx.lineJoin = 'round';
    days.forEach((d, i) => {
      const x = PAD.left + i * xStep;
      const y = PAD.top + chartH - (d[s.key] / maxVal) * chartH;
      i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
    });
    ctx.stroke();

    // Dots (only if few days)
    if (days.length <= 20) {
      days.forEach((d, i) => {
        const x = PAD.left + i * xStep;
        const y = PAD.top + chartH - (d[s.key] / maxVal) * chartH;
        ctx.beginPath();
        ctx.arc(x, y, 3, 0, Math.PI * 2);
        ctx.fillStyle = s.color;
        ctx.fill();
      });
    }
  });
}


/* ═══════════════════════════════════════════════════════════════
   INDICADOR: POSICIONES ALMACENADAS I-36
   ═══════════════════════════════════════════════════════════════ */

const posicionesI36Mensual = [
  { mes: 'Abr 24', valor: 5820 },
  { mes: 'May 24', valor: 6100 },
  { mes: 'Jun 24', valor: 5430 },
  { mes: 'Jul 24', valor: 6350 },
  { mes: 'Ago 24', valor: 5980 },
  { mes: 'Sep 24', valor: 6220 },
  { mes: 'Oct 24', valor: 5760 },
  { mes: 'Nov 24', valor: 6480 },
  { mes: 'Dic 24', valor: 5510 },
  { mes: 'Ene 25', valor: 6040 },
  { mes: 'Feb 25', valor: 5890 },
  { mes: 'Mar 25', valor: 6500 },
];

function renderIndicadorPosiciones() {
  setHeader('INDICADORES');
  setExpandedMode(false);
  showMetaPanel(true);
  menuGrid.className = '';
  menuGrid.innerHTML = '';

  // Leer el valor actual del contenedor 5 (ESTIBAS EN PLANTA)
  const estEl  = document.getElementById('estibas-en-planta');
  const actual = estEl ? parseInt(estEl.textContent.replace(/\D/g,'')) || 6500 : 6500;

  // Calcular promedio de los 12 meses
  const promedio = Math.round(posicionesI36Mensual.reduce((s,m) => s + m.valor, 0) / posicionesI36Mensual.length);
  const minVal   = 5000;
  const maxVal   = 6500;
  const pct      = Math.round(((actual - minVal) / (maxVal - minVal)) * 100);
  const color    = actual >= 6200 ? '#00A887' : actual >= 5500 ? '#36B0C9' : '#F97316';

  const wrap = document.createElement('div');
  wrap.className = 'indicador-wrap';

  // ── Card principal ──────────────────────────────────────────
  const card = document.createElement('div');
  card.className = 'indicador-card indicador-card-static';
  card.innerHTML = `
    <div class="indicador-card-header">
      <div class="indicador-card-title">
        <span class="indicador-badge">I-36</span>
        POSICIONES ALMACENADAS
      </div>
      <span class="indicador-hint">Promedio 12 meses: ${promedio.toLocaleString('es-AR')}</span>
    </div>
    <div class="indicador-card-body">
      <div class="indicador-big-pct">
        <svg viewBox="0 0 120 120" class="indicador-ring-svg">
          <circle cx="60" cy="60" r="52" fill="none" stroke="rgba(255,255,255,.10)" stroke-width="10"/>
          <circle cx="60" cy="60" r="52" fill="none" stroke="${color}"
            stroke-width="10" stroke-linecap="round"
            stroke-dasharray="${Math.round(Math.min(pct,100) * 3.267)} 326.7"
            transform="rotate(-90 60 60)"/>
        </svg>
        <div class="indicador-ring-center">
          <span class="indicador-ring-val" style="font-size:1.6rem">${actual.toLocaleString('es-AR')}</span>
          <span class="indicador-ring-label">POSICIONES</span>
        </div>
      </div>
      <div class="indicador-legend">
        <div class="ind-leg-item"><span class="ind-leg-dot" style="background:${color}"></span>Actuales<strong>${actual.toLocaleString('es-AR')}</strong></div>
        <div class="ind-leg-item"><span class="ind-leg-dot" style="background:#36B0C9"></span>Promedio 12m<strong>${promedio.toLocaleString('es-AR')}</strong></div>
        <div class="ind-leg-sep"></div>
        <div class="ind-leg-item"><span style="color:rgba(255,255,255,.5);font-size:.75rem">Mín referencia</span><strong style="color:rgba(255,255,255,.5)">${minVal.toLocaleString('es-AR')}</strong></div>
        <div class="ind-leg-item"><span style="color:rgba(255,255,255,.5);font-size:.75rem">Máx referencia</span><strong style="color:rgba(255,255,255,.5)">${maxVal.toLocaleString('es-AR')}</strong></div>
      </div>
    </div>
  `;
  wrap.appendChild(card);

  // ── Gráfico de barras mensual ───────────────────────────────
  const chartSection = document.createElement('div');
  chartSection.className = 'indicador-mensual';
  chartSection.innerHTML = `
    <div class="indicador-mensual-title">📦 Posiciones promedio por mes — últimos 12 meses</div>
    <div class="posiciones-chart-wrap">
      <canvas id="posicionesBarChart"></canvas>
    </div>
  `;
  wrap.appendChild(chartSection);

  // ── Mini-cards mensuales ────────────────────────────────────
  const gridSection = document.createElement('div');
  gridSection.className = 'indicador-mensual';
  gridSection.innerHTML = `
    <div class="indicador-mensual-title">📊 Detalle mensual</div>
    <div class="indicador-mensual-grid">
      ${posicionesI36Mensual.map(m => {
        const p   = Math.round(((m.valor - minVal) / (maxVal - minVal)) * 100);
        const col = m.valor >= 6200 ? '#00A887' : m.valor >= 5500 ? '#36B0C9' : '#F97316';
        const isActual = m.mes === posicionesI36Mensual[posicionesI36Mensual.length-1].mes;
        return `
          <div class="ind-mes-card${isActual ? ' ind-mes-actual' : ''}">
            <div class="ind-mes-label">${m.mes}</div>
            <div class="ind-mes-pct" style="color:${col}">${m.valor.toLocaleString('es-AR')}</div>
            <div class="ind-mes-bar-wrap">
              <div class="ind-mes-bar-fill" style="width:${p}%;background:${col}"></div>
            </div>
            <div class="ind-mes-detail">${isActual ? '← actual' : ''}</div>
          </div>`;
      }).join('')}
    </div>
  `;
  wrap.appendChild(gridSection);
  menuGrid.appendChild(wrap);
  syncBackBtn();

  // Dibujar gráfico de barras
  requestAnimationFrame(() => drawPosicionesBar(minVal, maxVal));
}

function drawPosicionesBar(minRef, maxRef) {
  const canvas = document.getElementById('posicionesBarChart');
  if (!canvas) return;
  const wrap = canvas.parentElement;
  canvas.width  = wrap.clientWidth  || 700;
  canvas.height = 220;
  const W = canvas.width, H = canvas.height;
  const PAD = { top: 20, right: 16, bottom: 38, left: 54 };
  const chartW = W - PAD.left - PAD.right;
  const chartH = H - PAD.top  - PAD.bottom;
  const ctx = canvas.getContext('2d');
  const data = posicionesI36Mensual;
  const n = data.length;
  const barW  = (chartW / n) * 0.62;
  const gap   = (chartW / n) * 0.38;
  const scale = 7000; // Y max

  ctx.clearRect(0, 0, W, H);

  // Grid lines + Y labels
  [5000, 5500, 6000, 6500].forEach(v => {
    const y = PAD.top + chartH - ((v - 0) / scale) * chartH;
    ctx.strokeStyle = 'rgba(255,255,255,.08)';
    ctx.lineWidth = 1;
    ctx.beginPath(); ctx.moveTo(PAD.left, y); ctx.lineTo(PAD.left + chartW, y); ctx.stroke();
    ctx.fillStyle = 'rgba(255,255,255,.45)';
    ctx.font = '10px Segoe UI';
    ctx.textAlign = 'right';
    ctx.fillText(v.toLocaleString('es-AR'), PAD.left - 6, y + 4);
  });

  // Promedio line
  const promedio = Math.round(data.reduce((s,m) => s + m.valor, 0) / data.length);
  const promY = PAD.top + chartH - (promedio / scale) * chartH;
  ctx.strokeStyle = 'rgba(54,176,201,.6)';
  ctx.lineWidth = 1.5;
  ctx.setLineDash([5, 4]);
  ctx.beginPath(); ctx.moveTo(PAD.left, promY); ctx.lineTo(PAD.left + chartW, promY); ctx.stroke();
  ctx.setLineDash([]);
  ctx.fillStyle = 'rgba(54,176,201,.9)';
  ctx.font = 'bold 10px Segoe UI';
  ctx.textAlign = 'left';
  ctx.fillText('Prom. ' + promedio.toLocaleString('es-AR'), PAD.left + 4, promY - 4);

  // Bars
  data.forEach((m, i) => {
    const x    = PAD.left + i * (barW + gap) + gap / 2;
    const barH = (m.valor / scale) * chartH;
    const y    = PAD.top + chartH - barH;
    const col  = m.valor >= 6200 ? '#00A887' : m.valor >= 5500 ? '#36B0C9' : '#F97316';
    const isLast = i === data.length - 1;

    // Bar fill
    ctx.fillStyle = isLast ? col : col + 'BB';
    ctx.beginPath();
    ctx.roundRect ? ctx.roundRect(x, y, barW, barH, [4,4,0,0]) : ctx.rect(x, y, barW, barH);
    ctx.fill();

    // Value on top
    ctx.fillStyle = isLast ? '#fff' : 'rgba(255,255,255,.72)';
    ctx.font = (isLast ? 'bold ' : '') + '9px Segoe UI';
    ctx.textAlign = 'center';
    ctx.fillText(m.valor.toLocaleString('es-AR'), x + barW / 2, y - 4);

    // X label
    ctx.fillStyle = 'rgba(255,255,255,.55)';
    ctx.font = '9px Segoe UI';
    ctx.fillText(m.mes, x + barW / 2, H - PAD.bottom + 13);
  });
}


/* ═══════════════════════════════════════════════════════════════
   DATOS COMPARTIDOS
   ═══════════════════════════════════════════════════════════════ */

const clientesOperaron = [
  'ARCOR S.A.',
  'CINA S.R.L.',
  'CONGELADOS DEL SUR S.A. EXP.',
  'DROGUERÍA KELLERHOFF S.A.',
  'ETHICAL NUTRITION S.A.',
  'FROILÁN',
  'GLUFREEZ (PAULA SILNIK)',
  'HELACOR S.A. COMERCIO EXTERIOR',
  'HELACOR S.A. DEPÓSITO NACIONAL',
  'HELADOS ESTHER SRL',
  'IPANCO SRL DF',
  'IPANCO SRL DN',
  'KECLON S.A.',
  'LA SIBILA SA',
  'LOGÍSTICA RR CONGELADOS SA',
  'MCCAIN ARGENTINA SA',
  'MINERVA ARGENTINA S.A. (INSUMOS)',
  'MINERVA BEEF FIESTA',
  'MINERVA FOOD (0013 PLANTA SWIFT)',
  'MINERVA FOODS (1113 PLANTA VILLA MERCEDES)',
  'MINERVA FOODS (1373 PLANTA VENADO TUERTO)',
  'MINERVA FOODS (CTRO. DE DIST.) AMBIENTE',
  'MINERVA FOODS (CTRO. DE DIST.) CONGELADO',
  'MINERVA FOODS (CTRO. DE DIST.) ENFRIADO',
  'MINERVA FOODS (IMPORTACIÓN)',
  'MINERVA FOODS (MERCADERÍA DE TERCEROS)',
  'MINERVA FOODS (ROSARIO MERCADO EXTERNO)',
  'MINERVA FOODS BEEF CONGELADO',
  'MINERVA FOODS EXTERNO FISCAL (DEVOLUCIÓN)',
  'MINERVA FOODS IMPO FISCAL',
  'MINERVA FOODS MERCADO BEEF (CORTES ENFRIADOS)',
  'QUICKFOOD S.A. (MATERIA PRIMA)',
  'QUICKFOOD S.A. VEGETALES',
  'QUICKFOOD S.A. (SAN JORGE)',
  'QUICKFOOD SAN JORGE DF',
  'RAFAELA ALIMENTOS S.A. (CASILDA)',
  'SAVAZ SRL',
  'SAVAZ SRL EXP.',
  'SEJAS, BOGDANICH Y FLORES GALARZA S.H.',
  'SUDAMERICANA DE LÁCTEOS S.A.',
  'TERRAGENE ENF S.A.',
  'TERRAGENE SA.',
  'TERRAGENE SECO S.A.',
  'ULTRACONGELADOS ROSARIO',
  'ULTRACONGELADOS ROSARIO S.A. EXP.',
];

const ingEgrData = [
  { mes:'Abr 24', estIn:1820, estOut:1950, kgIn:2980000, kgOut:3120000 },
  { mes:'May 24', estIn:2100, estOut:2240, kgIn:3280000, kgOut:3410000 },
  { mes:'Jun 24', estIn:1650, estOut:1580, kgIn:2760000, kgOut:2690000 },
  { mes:'Jul 24', estIn:2350, estOut:2480, kgIn:3540000, kgOut:3680000 },
  { mes:'Ago 24', estIn:1980, estOut:2050, kgIn:3050000, kgOut:3190000 },
  { mes:'Sep 24', estIn:2210, estOut:2180, kgIn:3310000, kgOut:3280000 },
  { mes:'Oct 24', estIn:1760, estOut:1840, kgIn:2850000, kgOut:2940000 },
  { mes:'Nov 24', estIn:2440, estOut:2560, kgIn:3620000, kgOut:3750000 },
  { mes:'Dic 24', estIn:1590, estOut:1620, kgIn:2720000, kgOut:2800000 },
  { mes:'Ene 25', estIn:2080, estOut:2150, kgIn:3160000, kgOut:3240000 },
  { mes:'Feb 25', estIn:1920, estOut:1990, kgIn:2990000, kgOut:3080000 },
  { mes:'Mar 25', estIn:2260, estOut:2380, kgIn:3400000, kgOut:3520000 },
];

/* ═══════════════════════════════════════════════════════════════
   INDICADOR: CLIENTES QUE OPERARON
   ═══════════════════════════════════════════════════════════════ */
function renderIndicadorClientes() {
  setHeader('INDICADORES');
  setExpandedMode(false);
  showMetaPanel(true);
  menuGrid.className = '';
  menuGrid.innerHTML = '';

  const wrap = document.createElement('div');
  wrap.className = 'indicador-wrap';

  // Header card
  const header = document.createElement('div');
  header.className = 'indicador-card indicador-card-static';
  header.innerHTML = `
    <div class="indicador-card-header">
      <div class="indicador-card-title">
        <span class="indicador-badge">CLI</span>
        CLIENTES QUE OPERARON
      </div>
      <span class="indicador-hint">${clientesOperaron.length} clientes activos este período</span>
    </div>`;
  wrap.appendChild(header);

  // Search + list
  const listSection = document.createElement('div');
  listSection.className = 'indicador-mensual clientes-section';
  listSection.innerHTML = `
    <div class="clientes-search-wrap">
      <input type="text" id="clientesSearch" class="equip-modal-input clientes-search"
        placeholder="🔍  Buscar cliente..." autocomplete="off">
      <span class="clientes-count" id="clientesCount">${clientesOperaron.length} clientes</span>
    </div>
    <div class="clientes-grid" id="clientesGrid">
      ${clientesOperaron.map((c, i) => `
        <div class="cliente-row" data-name="${c.toLowerCase()}">
          <span class="cliente-num">${String(i+1).padStart(2,'0')}</span>
          <span class="cliente-name">${c}</span>
          <span class="cliente-dot"></span>
        </div>`).join('')}
    </div>
  `;
  wrap.appendChild(listSection);
  menuGrid.appendChild(wrap);
  syncBackBtn();

  // Live search
  requestAnimationFrame(() => {
    const input = document.getElementById('clientesSearch');
    const grid  = document.getElementById('clientesGrid');
    const count = document.getElementById('clientesCount');
    if (!input) return;
    input.addEventListener('input', () => {
      const q = input.value.toLowerCase().trim();
      let visible = 0;
      grid.querySelectorAll('.cliente-row').forEach(row => {
        const match = !q || row.dataset.name.includes(q);
        row.style.display = match ? '' : 'none';
        if (match) visible++;
      });
      count.textContent = visible + ' clientes';
    });
  });
}

/* ═══════════════════════════════════════════════════════════════
   INDICADOR: POSICIONES INGRESADAS / EGRESADAS
   ═══════════════════════════════════════════════════════════════ */
function renderIndicadorIngEgr() {
  setHeader('INDICADORES');
  setExpandedMode(false);
  showMetaPanel(true);
  menuGrid.className = '';
  menuGrid.innerHTML = '';

  const totalIn  = ingEgrData.reduce((s,m) => s + m.estIn,  0);
  const totalOut = ingEgrData.reduce((s,m) => s + m.estOut, 0);
  const totalKgIn  = ingEgrData.reduce((s,m) => s + m.kgIn,  0);
  const totalKgOut = ingEgrData.reduce((s,m) => s + m.kgOut, 0);

  const wrap = document.createElement('div');
  wrap.className = 'indicador-wrap';

  // Header card con totales
  const header = document.createElement('div');
  header.className = 'indicador-card indicador-card-static';
  header.innerHTML = `
    <div class="indicador-card-header">
      <div class="indicador-card-title">
        <span class="indicador-badge">I/E</span>
        POSICIONES INGRESADAS / EGRESADAS
      </div>
      <span class="indicador-hint">Últimos 12 meses</span>
    </div>
    <div class="inegr-totales">
      <div class="inegr-total-item">
        <span class="inegr-total-label">Total Estibas Ingresadas</span>
        <span class="inegr-total-val green">${totalIn.toLocaleString('es-AR')}</span>
      </div>
      <div class="inegr-total-sep"></div>
      <div class="inegr-total-item">
        <span class="inegr-total-label">Total Estibas Egresadas</span>
        <span class="inegr-total-val red">${totalOut.toLocaleString('es-AR')}</span>
      </div>
      <div class="inegr-total-sep"></div>
      <div class="inegr-total-item">
        <span class="inegr-total-label">Total Kg Ingresados</span>
        <span class="inegr-total-val green">${(totalKgIn/1e6).toFixed(1)}M kg</span>
      </div>
      <div class="inegr-total-sep"></div>
      <div class="inegr-total-item">
        <span class="inegr-total-label">Total Kg Egresados</span>
        <span class="inegr-total-val red">${(totalKgOut/1e6).toFixed(1)}M kg</span>
      </div>
    </div>`;
  wrap.appendChild(header);

  // ── 3 gráficos de ESTIBAS ──────────────────────────────────
  const chartsTitles = [
    { id:'chartEstIn',    title:'📦 Estibas Ingresadas — mes a mes',  key:'estIn',  color:'#00A887' },
    { id:'chartEstOut',   title:'📤 Estibas Egresadas — mes a mes',   key:'estOut', color:'#DC2626' },
    { id:'chartEstSum',   title:'📊 Estibas Totales (Ingr + Egr)',    key:null,     color:'#36B0C9', isSum:true },
  ];
  chartsTitles.forEach(cfg => {
    const sec = document.createElement('div');
    sec.className = 'indicador-mensual';
    sec.innerHTML = `
      <div class="indicador-mensual-title">${cfg.title}</div>
      <div class="inegr-chart-wrap"><canvas id="${cfg.id}"></canvas></div>`;
    wrap.appendChild(sec);
  });

  // ── 3 gráficos de KG ──────────────────────────────────────
  const kgCharts = [
    { id:'chartKgIn',  title:'⚖ Kg Ingresados — mes a mes',    key:'kgIn',  color:'#00A887' },
    { id:'chartKgOut', title:'⚖ Kg Egresados — mes a mes',     key:'kgOut', color:'#DC2626' },
    { id:'chartKgSum', title:'⚖ Kg Totales (Ingr + Egr)',      key:null,    color:'#36B0C9', isSum:true, isKg:true },
  ];
  kgCharts.forEach(cfg => {
    const sec = document.createElement('div');
    sec.className = 'indicador-mensual';
    sec.innerHTML = `
      <div class="indicador-mensual-title">${cfg.title}</div>
      <div class="inegr-chart-wrap"><canvas id="${cfg.id}"></canvas></div>`;
    wrap.appendChild(sec);
  });

  menuGrid.appendChild(wrap);
  syncBackBtn();

  // Dibujar todos los gráficos
  requestAnimationFrame(() => {
    [...chartsTitles, ...kgCharts].forEach(cfg => drawIngEgrBar(cfg));
  });
}

function drawIngEgrBar(cfg) {
  const canvas = document.getElementById(cfg.id);
  if (!canvas) return;
  const wrap = canvas.parentElement;
  canvas.width  = wrap.clientWidth || 700;
  canvas.height = 200;
  const W = canvas.width, H = canvas.height;
  const PAD = { top:22, right:16, bottom:38, left: cfg.isKg ? 68 : 48 };
  const chartW = W - PAD.left - PAD.right;
  const chartH = H - PAD.top  - PAD.bottom;
  const ctx = canvas.getContext('2d');
  const data = ingEgrData;
  const n = data.length;

  // Build values array
  const vals = data.map(m => {
    if (cfg.isSum) return cfg.isKg ? m.kgIn + m.kgOut : m.estIn + m.estOut;
    return m[cfg.key];
  });
  const maxVal = Math.max(...vals) * 1.12;
  const barW = (chartW / n) * 0.58;
  const gap  = (chartW / n) * 0.42;

  ctx.clearRect(0, 0, W, H);

  // Grid
  for (let i = 0; i <= 4; i++) {
    const y = PAD.top + chartH - (i/4) * chartH;
    ctx.strokeStyle = 'rgba(255,255,255,.07)';
    ctx.lineWidth = 1;
    ctx.beginPath(); ctx.moveTo(PAD.left, y); ctx.lineTo(PAD.left + chartW, y); ctx.stroke();
    const lbl = cfg.isKg
      ? ((maxVal * i/4)/1e6).toFixed(1) + 'M'
      : Math.round(maxVal * i/4).toLocaleString('es-AR');
    ctx.fillStyle = 'rgba(255,255,255,.42)';
    ctx.font = '9px Segoe UI';
    ctx.textAlign = 'right';
    ctx.fillText(lbl, PAD.left - 5, y + 3);
  }

  // Bars
  vals.forEach((v, i) => {
    const x    = PAD.left + i * (barW + gap) + gap/2;
    const bH   = (v / maxVal) * chartH;
    const y    = PAD.top + chartH - bH;
    const isLast = i === n - 1;

    ctx.fillStyle = isLast ? cfg.color : cfg.color + 'AA';
    ctx.beginPath();
    if (ctx.roundRect) ctx.roundRect(x, y, barW, bH, [3,3,0,0]);
    else ctx.rect(x, y, barW, bH);
    ctx.fill();

    // Value on top
    ctx.fillStyle = isLast ? '#fff' : 'rgba(255,255,255,.7)';
    ctx.font = (isLast ? 'bold ' : '') + '9px Segoe UI';
    ctx.textAlign = 'center';
    const vlbl = cfg.isKg ? (v/1e6).toFixed(2)+'M' : v.toLocaleString('es-AR');
    ctx.fillText(vlbl, x + barW/2, y - 4);

    // X label
    ctx.fillStyle = 'rgba(255,255,255,.52)';
    ctx.font = '9px Segoe UI';
    ctx.fillText(data[i].mes, x + barW/2, H - PAD.bottom + 13);
  });

  // Promedio line
  const prom = vals.reduce((s,v) => s+v, 0) / vals.length;
  const promY = PAD.top + chartH - (prom / maxVal) * chartH;
  ctx.strokeStyle = 'rgba(255,255,255,.3)';
  ctx.lineWidth = 1.2;
  ctx.setLineDash([4,4]);
  ctx.beginPath(); ctx.moveTo(PAD.left, promY); ctx.lineTo(PAD.left + chartW, promY); ctx.stroke();
  ctx.setLineDash([]);
  const promLbl = cfg.isKg ? 'Prom ' + (prom/1e6).toFixed(2)+'M' : 'Prom ' + Math.round(prom).toLocaleString('es-AR');
  ctx.fillStyle = 'rgba(255,255,255,.55)';
  ctx.font = '9px Segoe UI';
  ctx.textAlign = 'left';
  ctx.fillText(promLbl, PAD.left + 4, promY - 4);
}


/* ═══════════════════════════════════════════════════════════════
   INDICADOR: MOVIMIENTOS I-4/5/6/6B/7/8
   ═══════════════════════════════════════════════════════════════ */

const movData = {
  vehiculos: [
    { id:'todos',    code:'I-4',  label:'Prom. Todos los Vehículos' },
    { id:'cont',     code:'I-5',  label:'Contenedores' },
    { id:'semi',     code:'I-6',  label:'Semiremolques' },
    { id:'semiSeca', code:'I-6B', label:'Semirrem. Cargas Secas' },
    { id:'balan',    code:'I-7',  label:'Balancines' },
    { id:'chasis',   code:'I-8',  label:'Chasis' },
  ],
  meses: (() => {
    const nombres = ['Abr 24','May 24','Jun 24','Jul 24','Ago 24','Sep 24',
                     'Oct 24','Nov 24','Dic 24','Ene 25','Feb 25','Mar 25'];
    // Genera datos simulados consistentes para cargas/bultos/kg x entrada/salida x vehículo
    const vIds = ['todos','cont','semi','semiSeca','balan','chasis'];
    const seed0 = 42;
    let seed = seed0;
    const rnd = () => { seed=(seed*1664525+1013904223)&0xffffffff; return Math.abs(seed)/0xffffffff; };
    const rv = (min,max) => +(min + rnd()*(max-min)).toFixed(1);

    return nombres.map(mes => {
      const promedios = {};
      vIds.forEach(id => {
        promedios[id] = {
          // [unidad][tipo] = horas promedio
          cargas:  { entrada: rv(1.5,6.5), salida: rv(1.2,5.8), prom: 0 },
          bultos:  { entrada: rv(2.0,7.5), salida: rv(1.8,6.9), prom: 0 },
          kg:      { entrada: rv(1.8,7.0), salida: rv(1.5,6.4), prom: 0 },
        };
        // prom = mean of the 6 values (entry+exit x 3 units)
        ['cargas','bultos','kg'].forEach(u => {
          promedios[id][u].prom = +((promedios[id][u].entrada + promedios[id][u].salida)/2).toFixed(1);
        });
        // top-level prom (for tile) = mean of cargas prom
        promedios[id].prom = promedios[id].cargas.prom;
      });
      return { mes, promedios };
    });
  })(),
};

function renderIndicadorMovimientos() {
  setHeader('INDICADORES');
  setExpandedMode(false);
  showMetaPanel(true);
  menuGrid.className = '';
  menuGrid.innerHTML = '';

  // Estado reactivo
  let unidad = 'cargas'; // cargas | bultos | kg

  const wrap = document.createElement('div');
  wrap.className = 'indicador-wrap';

  // ── Header + selector de unidad ─────────────────────────────
  const header = document.createElement('div');
  header.className = 'indicador-card indicador-card-static';
  header.innerHTML = `
    <div class="indicador-card-header">
      <div class="indicador-card-title">
        <span class="indicador-badge">MOV</span>
        MOVIMIENTOS — TIEMPOS DE OPERACIÓN
      </div>
      <span class="indicador-hint">Promedio mensual por tipo de vehículo (horas)</span>
    </div>
    <div class="mov-unidad-selector">
      <button class="mov-unidad-btn active" data-u="cargas">📦 Cargas</button>
      <button class="mov-unidad-btn" data-u="bultos">🎁 Bultos</button>
      <button class="mov-unidad-btn" data-u="kg">⚖ Kg</button>
    </div>`;
  wrap.appendChild(header);

  // ── 6 bloques de vehículo, cada uno con 3 gráficos ──────────
  const chartsWrap = document.createElement('div');
  chartsWrap.id = 'movChartsWrap';
  chartsWrap.className = 'mov-charts-outer';
  movData.vehiculos.forEach(v => {
    const block = document.createElement('div');
    block.className = 'indicador-mensual mov-vehiculo-block';
    block.dataset.vid = v.id;
    block.innerHTML = `
      <div class="indicador-mensual-title mov-veh-title">
        <span class="indicador-badge" style="font-size:.68rem">${v.code}</span>
        ${v.label}
      </div>
      <div class="mov-three-charts">
        <div class="mov-chart-col">
          <div class="mov-chart-label mov-label-entrada">↓ Entradas</div>
          <div class="inegr-chart-wrap"><canvas id="mov_${v.id}_entrada"></canvas></div>
        </div>
        <div class="mov-chart-col">
          <div class="mov-chart-label mov-label-salida">↑ Salidas</div>
          <div class="inegr-chart-wrap"><canvas id="mov_${v.id}_salida"></canvas></div>
        </div>
        <div class="mov-chart-col">
          <div class="mov-chart-label mov-label-prom">⇄ Promedio</div>
          <div class="inegr-chart-wrap"><canvas id="mov_${v.id}_prom"></canvas></div>
        </div>
      </div>`;
    chartsWrap.appendChild(block);
  });
  wrap.appendChild(chartsWrap);
  menuGrid.appendChild(wrap);
  syncBackBtn();

  const drawAll = () => {
    movData.vehiculos.forEach(v => {
      ['entrada','salida','prom'].forEach(tipo => {
        drawMovBar(`mov_${v.id}_${tipo}`, v.id, tipo, unidad);
      });
    });
  };

  requestAnimationFrame(drawAll);

  // Selector de unidad
  header.querySelectorAll('.mov-unidad-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      header.querySelectorAll('.mov-unidad-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      unidad = btn.dataset.u;
      drawAll();
    });
  });
}

function drawMovBar(canvasId, vId, tipo, unidad) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;
  const wrap = canvas.parentElement;
  canvas.width  = wrap.clientWidth || 280;
  canvas.height = 160;
  const W = canvas.width, H = canvas.height;
  const PAD = { top:18, right:8, bottom:32, left:34 };
  const chartW = W - PAD.left - PAD.right;
  const chartH = H - PAD.top  - PAD.bottom;
  const ctx = canvas.getContext('2d');
  const meses = movData.meses;
  const n = meses.length;
  const barW = (chartW / n) * 0.6;
  const gap  = (chartW / n) * 0.4;

  const colores = { entrada:'#00A887', salida:'#DC2626', prom:'#36B0C9' };
  const color   = colores[tipo];

  const vals = meses.map(m => m.promedios[vId][unidad] ? m.promedios[vId][unidad][tipo] : m.promedios[vId]['cargas'][tipo]);
  const maxVal = Math.max(...vals, 1) * 1.18;

  ctx.clearRect(0, 0, W, H);

  // Grid
  for (let i=0; i<=3; i++) {
    const y = PAD.top + chartH - (i/3)*chartH;
    ctx.strokeStyle = 'rgba(255,255,255,.07)';
    ctx.lineWidth = 1;
    ctx.beginPath(); ctx.moveTo(PAD.left, y); ctx.lineTo(PAD.left+chartW, y); ctx.stroke();
    ctx.fillStyle = 'rgba(255,255,255,.38)';
    ctx.font = '8px Segoe UI';
    ctx.textAlign = 'right';
    ctx.fillText(((maxVal*i/3)).toFixed(1)+'h', PAD.left-3, y+3);
  }

  vals.forEach((v, i) => {
    const x   = PAD.left + i*(barW+gap) + gap/2;
    const bH  = (v/maxVal)*chartH;
    const y   = PAD.top + chartH - bH;
    const isLast = i === n-1;

    ctx.fillStyle = isLast ? color : color+'99';
    ctx.beginPath();
    if (ctx.roundRect) ctx.roundRect(x, y, barW, bH, [2,2,0,0]);
    else ctx.rect(x, y, barW, bH);
    ctx.fill();

    // Valor encima
    ctx.fillStyle = isLast ? '#fff' : 'rgba(255,255,255,.62)';
    ctx.font = (isLast ? 'bold ' : '') + '7px Segoe UI';
    ctx.textAlign = 'center';
    ctx.fillText(v+'h', x+barW/2, y-3);

    // Mes
    ctx.fillStyle = 'rgba(255,255,255,.45)';
    ctx.font = '7px Segoe UI';
    ctx.fillText(meses[i].mes.split(' ')[0], x+barW/2, H-PAD.bottom+11);
  });

  // Promedio line
  const prom = vals.reduce((s,v)=>s+v,0)/vals.length;
  const promY = PAD.top + chartH - (prom/maxVal)*chartH;
  ctx.strokeStyle = 'rgba(255,255,255,.28)';
  ctx.lineWidth = 1;
  ctx.setLineDash([3,3]);
  ctx.beginPath(); ctx.moveTo(PAD.left, promY); ctx.lineTo(PAD.left+chartW, promY); ctx.stroke();
  ctx.setLineDash([]);
}


/* ═══════════════════════════════════════════════════════════════
   DATOS: AJUSTES DE STOCK
   ═══════════════════════════════════════════════════════════════ */
const ajustesData = {
  meses: ['Abr 24','May 24','Jun 24','Jul 24','Ago 24','Sep 24','Oct 24','Nov 24','Dic 24','Ene 25','Feb 25','Mar 25'],
  cargas:  [3,5,2,7,4,6,3,8,5,4,6,1],
  bultos:  [42,68,31,95,57,74,45,102,63,51,78,12],
  totalCargas: 28940,
  totalBultos: 338421,
  ajustesDetalle: [
    // mes 0=Abr24 .. 11=Mar25
    // { mes, carga, bultos, usuario, motivo }
    {mes:0,  carga:'C-48201', bultos:12, usuario:'mario.garcia',    motivo:'Error conteo entrada'},
    {mes:0,  carga:'C-48356', bultos:8,  usuario:'laura.benitez',   motivo:'Daño en estiba'},
    {mes:0,  carga:'C-48490', bultos:22, usuario:'mario.garcia',    motivo:'Faltante descarga'},
    {mes:1,  carga:'C-49102', bultos:5,  usuario:'pedro.suarez',    motivo:'Error conteo entrada'},
    {mes:1,  carga:'C-49250', bultos:15, usuario:'mario.garcia',    motivo:'Sobrante en recuento'},
    {mes:1,  carga:'C-49380', bultos:18, usuario:'laura.benitez',   motivo:'Daño en estiba'},
    {mes:1,  carga:'C-49510', bultos:9,  usuario:'ana.torres',      motivo:'Error etiquetado'},
    {mes:1,  carga:'C-49620', bultos:21, usuario:'pedro.suarez',    motivo:'Faltante descarga'},
    {mes:2,  carga:'C-50100', bultos:7,  usuario:'ana.torres',      motivo:'Error conteo entrada'},
    {mes:2,  carga:'C-50280', bultos:24, usuario:'mario.garcia',    motivo:'Reclamo cliente'},
    {mes:3,  carga:'C-51001', bultos:11, usuario:'laura.benitez',   motivo:'Error conteo entrada'},
    {mes:3,  carga:'C-51200', bultos:19, usuario:'pedro.suarez',    motivo:'Daño en estiba'},
    {mes:3,  carga:'C-51350', bultos:28, usuario:'mario.garcia',    motivo:'Sobrante en recuento'},
    {mes:3,  carga:'C-51480', bultos:14, usuario:'ana.torres',      motivo:'Faltante descarga'},
    {mes:3,  carga:'C-51600', bultos:10, usuario:'laura.benitez',   motivo:'Error etiquetado'},
    {mes:3,  carga:'C-51750', bultos:13, usuario:'mario.garcia',    motivo:'Reclamo cliente'},
    {mes:4,  carga:'C-52100', bultos:16, usuario:'pedro.suarez',    motivo:'Error conteo entrada'},
    {mes:4,  carga:'C-52250', bultos:22, usuario:'mario.garcia',    motivo:'Daño en estiba'},
    {mes:4,  carga:'C-52400', bultos:9,  usuario:'ana.torres',      motivo:'Error etiquetado'},
    {mes:4,  carga:'C-52580', bultos:10, usuario:'laura.benitez',   motivo:'Sobrante en recuento'},
    {mes:5,  carga:'C-53050', bultos:18, usuario:'mario.garcia',    motivo:'Error conteo entrada'},
    {mes:5,  carga:'C-53200', bultos:25, usuario:'pedro.suarez',    motivo:'Reclamo cliente'},
    {mes:5,  carga:'C-53380', bultos:14, usuario:'ana.torres',      motivo:'Faltante descarga'},
    {mes:5,  carga:'C-53500', bultos:17, usuario:'laura.benitez',   motivo:'Daño en estiba'},
    {mes:6,  carga:'C-54020', bultos:8,  usuario:'mario.garcia',    motivo:'Error conteo entrada'},
    {mes:6,  carga:'C-54200', bultos:20, usuario:'ana.torres',      motivo:'Error etiquetado'},
    {mes:6,  carga:'C-54380', bultos:17, usuario:'pedro.suarez',    motivo:'Sobrante en recuento'},
    {mes:7,  carga:'C-55001', bultos:30, usuario:'mario.garcia',    motivo:'Error conteo entrada'},
    {mes:7,  carga:'C-55180', bultos:22, usuario:'laura.benitez',   motivo:'Reclamo cliente'},
    {mes:7,  carga:'C-55320', bultos:28, usuario:'pedro.suarez',    motivo:'Daño en estiba'},
    {mes:8,  carga:'C-56010', bultos:19, usuario:'mario.garcia',    motivo:'Error conteo entrada'},
    {mes:8,  carga:'C-56180', bultos:24, usuario:'ana.torres',      motivo:'Faltante descarga'},
    {mes:8,  carga:'C-56350', bultos:20, usuario:'laura.benitez',   motivo:'Error etiquetado'},
    {mes:9,  carga:'C-57001', bultos:14, usuario:'pedro.suarez',    motivo:'Sobrante en recuento'},
    {mes:9,  carga:'C-57200', bultos:22, usuario:'mario.garcia',    motivo:'Error conteo entrada'},
    {mes:9,  carga:'C-57380', bultos:15, usuario:'laura.benitez',   motivo:'Reclamo cliente'},
    {mes:10, carga:'C-58020', bultos:28, usuario:'mario.garcia',    motivo:'Daño en estiba'},
    {mes:10, carga:'C-58200', bultos:33, usuario:'pedro.suarez',    motivo:'Error conteo entrada'},
    {mes:10, carga:'C-58380', bultos:17, usuario:'ana.torres',      motivo:'Error etiquetado'},
    {mes:11, carga:'C-59001', bultos:12, usuario:'mario.garcia',    motivo:'Ajuste manual'},
  ],
};

/* ═══════════════════════════════════════════════════════════════
   DATOS: ESTADÍSTICAS DE PERSONAL
   ═══════════════════════════════════════════════════════════════ */
const personalData = {
  perfiles: [
    'Administrador Principal','Administrador Calidad','Administrador Sala de Maquinas',
    'Administrador Logística','Administrador Recursos Humanos',
    'Operador Logistica Avanzado','Operador Logistica','Operador Calidad',
    'Operador Sala de Maquinas','Operador Recursos Humanos','Operador Básico',
    'Compras - Operador','Compras - Revisor','Compras - Autorizador',
    'Operador Calidad Avanzado','Operador Administracion','Auditoria',
    'Operador Logistica Especial','Logística - Avanzado','Cliente',
    'Operador Logistica DF','Logística - Encargado',
  ],
  usuarios: [
    {user:'alan.perez',          perfil:'Operador Logistica'},
    {user:'alfredo.perichon',    perfil:'Operador Logistica'},
    {user:'brandon.duarte',      perfil:'Operador Logistica'},
    {user:'brandon.peña',        perfil:'Operador Logistica'},
    {user:'carlos.aguirre',      perfil:'Operador Logistica'},
    {user:'celina.tasinazzo',    perfil:'Operador Logistica'},
    {user:'daniela.ojeda',       perfil:'Operador Logistica'},
    {user:'diego.corvalan',      perfil:'Operador Logistica'},
    {user:'diego.stoffel',       perfil:'Operador Logistica'},
    {user:'gabriel.cristaldo',   perfil:'Operador Logistica'},
    {user:'guillermo.aguirre',   perfil:'Operador Logistica'},
    {user:'hector.sanabria',     perfil:'Operador Logistica'},
    {user:'ignacio.rodriguez',   perfil:'Operador Logistica'},
    {user:'ivan.casalicchio',    perfil:'Operador Logistica'},
    {user:'javier.diaz',         perfil:'Operador Logistica'},
    {user:'jeremias.moyano',     perfil:'Operador Logistica'},
    {user:'jesus.centurion',     perfil:'Operador Logistica'},
    {user:'agustin.barovero',    perfil:'Administrador Principal'},
    {user:'mario.garcia',        perfil:'Administrador Logística'},
    {user:'laura.benitez',       perfil:'Operador Calidad'},
    {user:'pedro.suarez',        perfil:'Operador Logistica Avanzado'},
    {user:'ana.torres',          perfil:'Administrador Calidad'},
  ],
  // Movimientos mensuales por usuario (base ~250 variados)
  movimientos: (() => {
    const meses = ['Abr 24','May 24','Jun 24','Jul 24','Ago 24','Sep 24','Oct 24','Nov 24','Dic 24','Ene 25','Feb 25','Mar 25'];
    let s = 7;
    const r = () => { s=(s*1664525+1013904223)&0xffffffff; return Math.abs(s)/0xffffffff; };
    return meses.map(mes => {
      const vals = {};
      ['alan.perez','alfredo.perichon','brandon.duarte','brandon.peña','carlos.aguirre',
       'celina.tasinazzo','daniela.ojeda','diego.corvalan','diego.stoffel','gabriel.cristaldo',
       'guillermo.aguirre','hector.sanabria','ignacio.rodriguez','ivan.casalicchio',
       'javier.diaz','jeremias.moyano','jesus.centurion'].forEach(u => {
        vals[u] = Math.round(200 + r()*100);
      });
      return { mes, vals };
    });
  })(),
};

/* ═══════════════════════════════════════════════════════════════
   DATOS: USO DE EQUIPOS
   ═══════════════════════════════════════════════════════════════ */
const usoEquiposData = {
  meses: (() => {
    const ns = ['Abr 24','May 24','Jun 24','Jul 24','Ago 24','Sep 24','Oct 24','Nov 24','Dic 24','Ene 25','Feb 25','Mar 25'];
    let s = 13;
    const r = () => { s=(s*1664525+1013904223)&0xffffffff; return Math.abs(s)/0xffffffff; };
    const rv = (a,b) => Math.round(a + r()*(b-a));
    return ns.map(mes => ({
      mes,
      boxes:          rv(180,320),
      troneras:       rv(80,160),
      autoelevadores: rv(200,400),
      camion:         rv(60,140),
      limpieza:       rv(40,100),
      // detalle boxes 1-27
      boxDetalle: Array.from({length:27}, (_,i) => ({ id:`Box ${i+1}`, horas: rv(4,18) })),
      // detalle troneras 1-8 + DF1-DF3
      tronDetalle: [
        ...Array.from({length:8}, (_,i) => ({ id:`Tronera ${i+1}`, horas: rv(3,14) })),
        ...Array.from({length:3}, (_,i) => ({ id:`DF ${i+1}`, horas: rv(2,10) })),
      ],
      // AE ya los tenemos de dashboardEquipamientoData — simulamos por nombre
      aeDetalle: ['AE-01','AE-02','AE-03','AE-04','AE-05','AE-06','AE-07','AE-08']
                   .map(id => ({ id, horas: rv(20,80) })),
      camionDetalle: [{ id:'CM-01', horas: rv(40,130) }],
      limpiezaDetalle: [{ id:'RL-01', horas: rv(30,90) }],
    }));
  })(),
};

/* ═══════════════════════════════════════════════════════════════
   RENDER: AJUSTES DE STOCK
   ═══════════════════════════════════════════════════════════════ */
function renderIndicadorAjustes() {
  setHeader('INDICADORES');
  setExpandedMode(false);
  showMetaPanel(true);
  menuGrid.className = '';
  menuGrid.innerHTML = '';

  let mesFiltro = null; // null = todos
  const wrap = document.createElement('div');
  wrap.className = 'indicador-wrap';

  // Card resumen
  const card = document.createElement('div');
  card.className = 'indicador-card indicador-card-static';
  card.innerHTML = `
    <div class="indicador-card-header">
      <div class="indicador-card-title">
        <span class="indicador-badge">AJS</span>
        AJUSTES DE STOCK
      </div>
      <span class="indicador-hint">Desviaciones en cargas y bultos — últimos 12 meses</span>
    </div>
    <div class="inegr-totales">
      <div class="inegr-total-item">
        <span class="inegr-total-label">I-3 · Cargas con desvío</span>
        <span class="inegr-total-val" style="color:#F97316">${ajustesData.cargas.reduce((s,v)=>s+v,0)}</span>
      </div>
      <div class="inegr-total-sep"></div>
      <div class="inegr-total-item">
        <span class="inegr-total-label">I-49 · Bultos en desvío</span>
        <span class="inegr-total-val" style="color:#DC2626">${ajustesData.bultos.reduce((s,v)=>s+v,0)}</span>
      </div>
    </div>`;
  wrap.appendChild(card);

  // Gráficos cantidad
  [
    { id:'ajCargasChart', title:'📦 I-3 — Cargas con desvío por mes', vals: ajustesData.cargas, color:'#F97316' },
    { id:'ajBultosChart', title:'🎁 I-49 — Bultos en desvío por mes',  vals: ajustesData.bultos, color:'#DC2626' },
  ].forEach(cfg => {
    const sec = document.createElement('div');
    sec.className = 'indicador-mensual';
    sec.innerHTML = `<div class="indicador-mensual-title">${cfg.title}</div><div class="inegr-chart-wrap"><canvas id="${cfg.id}"></canvas></div>`;
    wrap.appendChild(sec);
  });

  // Gráficos porcentaje sobre total
  const totalCargasMes = ingEgrData.map(m => m.estIn + m.estOut);
  const totalBultosMes = ingEgrData.map(m => Math.round((m.kgIn + m.kgOut) / 25));
  [
    { id:'ajPctCargasChart', title:'📊 % Cargas con desvío / Total cargas del mes',
      vals: ajustesData.cargas.map((v,i) => +((v/Math.max(totalCargasMes[i],1))*100).toFixed(1)), color:'#F97316', isPct:true },
    { id:'ajPctBultosChart', title:'📊 I-49 % Bultos en desvío / Total bultos del mes',
      vals: ajustesData.bultos.map((v,i) => +((v/Math.max(totalBultosMes[i],1))*100).toFixed(1)), color:'#DC2626', isPct:true },
  ].forEach(cfg => {
    const sec = document.createElement('div');
    sec.className = 'indicador-mensual';
    sec.innerHTML = `<div class="indicador-mensual-title">${cfg.title}</div><div class="inegr-chart-wrap"><canvas id="${cfg.id}"></canvas></div>`;
    wrap.appendChild(sec);
  });

  // Selector de mes
  const selSec = document.createElement('div');
  selSec.className = 'indicador-mensual';
  selSec.innerHTML = `
    <div class="indicador-mensual-title">📅 Filtrar por mes — listado de ajustes realizados</div>
    <div class="ajustes-mes-selector" id="ajMesSel">
      <button class="ajustes-mes-btn active" data-mes="-1">Todos</button>
      ${ajustesData.meses.map((m,i)=>`<button class="ajustes-mes-btn" data-mes="${i}">${m}</button>`).join('')}
    </div>
    <div class="clientes-grid" id="ajustesList" style="margin-top:10px">
    </div>`;
  wrap.appendChild(selSec);
  menuGrid.appendChild(wrap);
  syncBackBtn();

  const renderList = (filtro) => {
    const list = document.getElementById('ajustesList');
    if (!list) return;
    const items = filtro === -1
      ? ajustesData.ajustesDetalle
      : ajustesData.ajustesDetalle.filter(a => a.mes === filtro);
    list.innerHTML = items.length === 0
      ? '<div style="color:rgba(255,255,255,.4);padding:16px;text-align:center">Sin ajustes en este período</div>'
      : items.map((a,i) => `
        <div class="cliente-row">
          <span class="cliente-num">${String(i+1).padStart(2,'0')}</span>
          <span class="cliente-name">
            <strong>${a.carga}</strong>
            <span style="color:rgba(255,255,255,.5);font-size:.78rem;margin-left:8px">${ajustesData.meses[a.mes]}</span>
          </span>
          <span style="color:#F97316;font-weight:800;font-size:.85rem;white-space:nowrap">${a.bultos} bts</span>
          <span style="color:rgba(255,255,255,.55);font-size:.78rem;white-space:nowrap">${a.usuario}</span>
        </div>`).join('');
  };

  requestAnimationFrame(() => {
    [
      { id:'ajCargasChart',    vals: ajustesData.cargas, color:'#F97316', isPct:false },
      { id:'ajBultosChart',    vals: ajustesData.bultos, color:'#DC2626', isPct:false },
      { id:'ajPctCargasChart', vals: ajustesData.cargas.map((v,i)=>+((v/Math.max((ingEgrData[i].estIn+ingEgrData[i].estOut),1))*100).toFixed(1)), color:'#F97316', isPct:true },
      { id:'ajPctBultosChart', vals: ajustesData.bultos.map((v,i)=>+((v/Math.max(Math.round((ingEgrData[i].kgIn+ingEgrData[i].kgOut)/25),1))*100).toFixed(1)), color:'#DC2626', isPct:true },
    ].forEach(cfg => drawSimpleBar(cfg.id, ajustesData.meses, cfg.vals, cfg.color, cfg.isPct));
    renderList(-1);
  });

  // Mes buttons
  wrap.addEventListener('click', e => {
    const btn = e.target.closest('.ajustes-mes-btn');
    if (!btn) return;
    wrap.querySelectorAll('.ajustes-mes-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    renderList(parseInt(btn.dataset.mes));
  });
}

/* ═══════════════════════════════════════════════════════════════
   RENDER: ESTADÍSTICAS DE PERSONAL
   ═══════════════════════════════════════════════════════════════ */
function renderIndicadorPersonal() {
  setHeader('INDICADORES');
  setExpandedMode(false);
  showMetaPanel(true);
  menuGrid.className = '';
  menuGrid.innerHTML = '';

  let perfilFiltro = 'Operador Logistica';
  const wrap = document.createElement('div');
  wrap.className = 'indicador-wrap';

  // Header
  const header = document.createElement('div');
  header.className = 'indicador-card indicador-card-static';
  header.innerHTML = `
    <div class="indicador-card-header">
      <div class="indicador-card-title">
        <span class="indicador-badge">RRHH</span>
        ESTADÍSTICAS DE PERSONAL
      </div>
      <span class="indicador-hint">${personalData.usuarios.length} usuarios en el sistema</span>
    </div>`;
  wrap.appendChild(header);

  // Selector de perfil
  const selSec = document.createElement('div');
  selSec.className = 'indicador-mensual';
  selSec.innerHTML = `
    <div class="indicador-mensual-title">👤 Filtrar por perfil</div>
    <div class="personal-perfil-wrap" id="perfilSel">
      ${personalData.perfiles.map(p => `
        <button class="ajustes-mes-btn personal-perfil-btn${p===perfilFiltro?' active':''}" data-p="${p}">${p}</button>
      `).join('')}
    </div>`;
  wrap.appendChild(selSec);

  // Gráfico movimientos
  const movSec = document.createElement('div');
  movSec.className = 'indicador-mensual';
  movSec.innerHTML = `
    <div class="indicador-mensual-title">📈 Movimientos mensuales — usuarios del perfil seleccionado</div>
    <div class="inegr-chart-wrap" style="height:220px"><canvas id="personalMovChart"></canvas></div>`;
  wrap.appendChild(movSec);

  // Cards de usuarios del perfil
  const usersSec = document.createElement('div');
  usersSec.className = 'indicador-mensual';
  usersSec.id = 'personalUsersSec';
  wrap.appendChild(usersSec);

  menuGrid.appendChild(wrap);
  syncBackBtn();

  const renderPersonalData = (perfil) => {
    const usuarios = personalData.usuarios.filter(u => u.perfil === perfil);
    const meses = personalData.movimientos.map(m => m.mes);
    // Suma de movimientos de todos los usuarios del perfil por mes
    const totalPorMes = personalData.movimientos.map(m => {
      return usuarios.reduce((s,u) => s + (m.vals[u.user] || 0), 0);
    });

    requestAnimationFrame(() => {
      drawSimpleBar('personalMovChart', meses, totalPorMes, '#A78BFA', false, 'mov');
    });

    const sec = document.getElementById('personalUsersSec');
    if (!sec) return;
    sec.innerHTML = `
      <div class="indicador-mensual-title">👥 Detalle por usuario — ${perfil} (${usuarios.length})</div>
      <div class="clientes-grid">
        ${usuarios.map((u,i) => {
          const totalMov = personalData.movimientos.reduce((s,m) => s+(m.vals[u.user]||0), 0);
          const horas    = Math.round(totalMov * 0.18);
          const cargas   = Math.round(totalMov * 0.62);
          return `
            <div class="cliente-row personal-user-row">
              <span class="cliente-num">${String(i+1).padStart(2,'0')}</span>
              <span class="cliente-name">${u.user}</span>
              <span class="personal-stat" style="color:#A78BFA">${totalMov.toLocaleString('es-AR')} mov</span>
              <span class="personal-stat" style="color:#36B0C9">${horas}h</span>
              <span class="personal-stat" style="color:#00A887">${cargas} cargas</span>
            </div>`;
        }).join('')}
      </div>`;
  };

  renderPersonalData(perfilFiltro);

  wrap.addEventListener('click', e => {
    const btn = e.target.closest('.personal-perfil-btn');
    if (!btn) return;
    wrap.querySelectorAll('.personal-perfil-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    perfilFiltro = btn.dataset.p;
    renderPersonalData(perfilFiltro);
  });
}

/* ═══════════════════════════════════════════════════════════════
   RENDER: USO DE EQUIPOS
   ═══════════════════════════════════════════════════════════════ */
function renderIndicadorUsoEquipos() {
  setHeader('INDICADORES');
  setExpandedMode(false);
  showMetaPanel(true);
  menuGrid.className = '';
  menuGrid.innerHTML = '';

  const wrap = document.createElement('div');
  wrap.className = 'indicador-wrap';

  // Header
  const header = document.createElement('div');
  header.className = 'indicador-card indicador-card-static';
  header.innerHTML = `
    <div class="indicador-card-header">
      <div class="indicador-card-title">
        <span class="indicador-badge">EQP</span>
        USO DE EQUIPOS
      </div>
      <span class="indicador-hint">Horas de uso acumuladas por tipo — últimos 12 meses</span>
    </div>
    <div class="inegr-totales">
      ${[
        {k:'boxes',color:'#36B0C9',label:'Boxes'},
        {k:'troneras',color:'#A78BFA',label:'Troneras'},
        {k:'autoelevadores',color:'#F97316',label:'Autoelevadores'},
        {k:'camion',color:'#FACC15',label:'Camión'},
        {k:'limpieza',color:'#00A887',label:'Limpieza'},
      ].map(e => `
        <div class="inegr-total-item">
          <span class="inegr-total-label">${e.label}</span>
          <span class="inegr-total-val" style="color:${e.color}">
            ${usoEquiposData.meses.reduce((s,m)=>s+m[e.k],0)}h
          </span>
        </div>`).join('<div class="inegr-total-sep"></div>')}
    </div>`;
  wrap.appendChild(header);

  // 5 gráficos resumen + botón de detalle
  const equipos = [
    {key:'boxes',         color:'#36B0C9', label:'📦 Boxes',          detalleKey:'boxDetalle'},
    {key:'troneras',      color:'#A78BFA', label:'🔩 Troneras',        detalleKey:'tronDetalle'},
    {key:'autoelevadores',color:'#F97316', label:'🏗 Autoelevadores',   detalleKey:'aeDetalle'},
    {key:'camion',        color:'#FACC15', label:'🚚 Camión',           detalleKey:'camionDetalle'},
    {key:'limpieza',      color:'#00A887', label:'🤖 Limpieza',         detalleKey:'limpiezaDetalle'},
  ];

  equipos.forEach(eq => {
    const sec = document.createElement('div');
    sec.className = 'indicador-mensual';
    const vals = usoEquiposData.meses.map(m => m[eq.key]);
    const meses = usoEquiposData.meses.map(m => m.mes);
    sec.innerHTML = `
      <div class="indicador-mensual-title" style="display:flex;align-items:center;justify-content:space-between">
        ${eq.label} — horas de uso por mes
        <button class="ajustes-mes-btn" style="font-size:.72rem;padding:5px 12px;min-width:0"
          data-eq="${eq.key}" data-dk="${eq.detalleKey}">Ver detalle →</button>
      </div>
      <div class="inegr-chart-wrap"><canvas id="eqChart_${eq.key}"></canvas></div>`;
    wrap.appendChild(sec);

    sec.querySelector('[data-eq]').addEventListener('click', () => {
      renderUsoDetalleEquipo(eq.key, eq.detalleKey, eq.label, eq.color);
    });
  });

  menuGrid.appendChild(wrap);
  syncBackBtn();

  requestAnimationFrame(() => {
    equipos.forEach(eq => {
      drawSimpleBar(`eqChart_${eq.key}`, usoEquiposData.meses.map(m=>m.mes),
        usoEquiposData.meses.map(m=>m[eq.key]), eq.color, false, 'h');
    });
  });
}

function renderUsoDetalleEquipo(equipoKey, detalleKey, label, color) {
  setHeader('USO DE EQUIPOS');
  menuGrid.className = '';
  menuGrid.innerHTML = '';

  const wrap = document.createElement('div');
  wrap.className = 'indicador-wrap';

  // Selector de mes
  const meses = usoEquiposData.meses.map(m => m.mes);
  let mesIdx = usoEquiposData.meses.length - 1;

  const selSec = document.createElement('div');
  selSec.className = 'indicador-mensual';
  selSec.innerHTML = `
    <div class="indicador-mensual-title" style="display:flex;align-items:center;justify-content:space-between">
      ${label} — detalle por unidad
      <button class="ajustes-mes-btn" style="font-size:.72rem;padding:5px 12px;min-width:0"
        id="backToEqMain">← Volver</button>
    </div>
    <div class="ajustes-mes-selector" id="eqMesSel">
      ${meses.map((m,i) => `<button class="ajustes-mes-btn${i===mesIdx?' active':''}" data-i="${i}">${m}</button>`).join('')}
    </div>
    <div class="inegr-chart-wrap" style="height:240px;margin-top:12px"><canvas id="eqDetalleChart"></canvas></div>`;
  wrap.appendChild(selSec);
  menuGrid.appendChild(wrap);
  syncBackBtn();

  const drawDetalle = (idx) => {
    const mesData = usoEquiposData.meses[idx];
    const items   = mesData[detalleKey];
    const labels  = items.map(x => x.id);
    const vals    = items.map(x => x.horas);
    drawHorizontalBar('eqDetalleChart', labels, vals, color);
  };

  requestAnimationFrame(() => drawDetalle(mesIdx));

  document.getElementById('backToEqMain').addEventListener('click', () => {
    historyStack.push({ title:'INDICADORES', children:[] });
    renderIndicadorUsoEquipos();
  });

  selSec.addEventListener('click', e => {
    const btn = e.target.closest('[data-i]');
    if (!btn || btn.id === 'backToEqMain') return;
    selSec.querySelectorAll('[data-i]').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    mesIdx = parseInt(btn.dataset.i);
    drawDetalle(mesIdx);
  });
}

/* ═══════════════════════════════════════════════════════════════
   HELPERS DE GRÁFICOS
   ═══════════════════════════════════════════════════════════════ */
function drawSimpleBar(id, meses, vals, color, isPct, unit) {
  const canvas = document.getElementById(id);
  if (!canvas) return;
  const wrap = canvas.parentElement;
  canvas.width  = wrap.clientWidth || 700;
  canvas.height = wrap.clientHeight || 200;
  const W = canvas.width, H = canvas.height;
  const PAD = { top:22, right:12, bottom:36, left: isPct ? 34 : 46 };
  const chartW = W - PAD.left - PAD.right;
  const chartH = H - PAD.top  - PAD.bottom;
  const ctx = canvas.getContext('2d');
  const n = meses.length;
  const maxVal = Math.max(...vals, 1) * 1.15;
  const barW = (chartW/n)*0.6;
  const gap  = (chartW/n)*0.4;

  ctx.clearRect(0, 0, W, H);

  for (let i=0; i<=4; i++) {
    const y = PAD.top + chartH - (i/4)*chartH;
    ctx.strokeStyle = 'rgba(255,255,255,.07)';
    ctx.lineWidth = 1;
    ctx.beginPath(); ctx.moveTo(PAD.left, y); ctx.lineTo(PAD.left+chartW, y); ctx.stroke();
    const lv = (maxVal*i/4);
    const lbl = isPct ? lv.toFixed(1)+'%' : (unit ? Math.round(lv)+unit : Math.round(lv).toLocaleString('es-AR'));
    ctx.fillStyle = 'rgba(255,255,255,.42)';
    ctx.font = '9px Segoe UI';
    ctx.textAlign = 'right';
    ctx.fillText(lbl, PAD.left-4, y+3);
  }

  vals.forEach((v, i) => {
    const x   = PAD.left + i*(barW+gap) + gap/2;
    const bH  = (v/maxVal)*chartH;
    const y   = PAD.top + chartH - bH;
    const isLast = i === n-1;
    ctx.fillStyle = isLast ? color : color+'99';
    ctx.beginPath();
    if (ctx.roundRect) ctx.roundRect(x, y, barW, bH, [3,3,0,0]);
    else ctx.rect(x, y, barW, bH);
    ctx.fill();
    ctx.fillStyle = isLast ? '#fff' : 'rgba(255,255,255,.65)';
    ctx.font = (isLast?'bold ':'')+'9px Segoe UI';
    ctx.textAlign = 'center';
    const vl = isPct ? v+'%' : (unit ? v+unit : v.toLocaleString('es-AR'));
    ctx.fillText(vl, x+barW/2, y-4);
    ctx.fillStyle = 'rgba(255,255,255,.45)';
    ctx.font = '8px Segoe UI';
    ctx.fillText(meses[i].split(' ')[0], x+barW/2, H-PAD.bottom+12);
  });

  const prom = vals.reduce((s,v)=>s+v,0)/vals.length;
  const promY = PAD.top + chartH - (prom/maxVal)*chartH;
  ctx.strokeStyle = 'rgba(255,255,255,.25)';
  ctx.lineWidth = 1;
  ctx.setLineDash([4,4]);
  ctx.beginPath(); ctx.moveTo(PAD.left, promY); ctx.lineTo(PAD.left+chartW, promY); ctx.stroke();
  ctx.setLineDash([]);
}

function drawHorizontalBar(id, labels, vals, color) {
  const canvas = document.getElementById(id);
  if (!canvas) return;
  const wrap = canvas.parentElement;
  const n    = labels.length;
  canvas.width  = wrap.clientWidth || 600;
  canvas.height = Math.max(wrap.clientHeight || 240, n * 24 + 20);
  const W = canvas.width, H = canvas.height;
  const PAD = { top:10, right:16, bottom:10, left:90 };
  const chartW = W - PAD.left - PAD.right;
  const rowH   = (H - PAD.top - PAD.bottom) / n;
  const barH   = rowH * 0.55;
  const maxVal = Math.max(...vals, 1) * 1.12;
  const ctx    = canvas.getContext('2d');

  ctx.clearRect(0, 0, W, H);

  vals.forEach((v, i) => {
    const y    = PAD.top + i * rowH + (rowH - barH) / 2;
    const bW   = (v / maxVal) * chartW;
    ctx.fillStyle = color + '88';
    if (ctx.roundRect) ctx.roundRect(PAD.left, y, bW, barH, [0,3,3,0]);
    else ctx.rect(PAD.left, y, bW, barH);
    ctx.fill();
    // label izq
    ctx.fillStyle = 'rgba(255,255,255,.7)';
    ctx.font = '10px Segoe UI';
    ctx.textAlign = 'right';
    ctx.fillText(labels[i], PAD.left-6, y + barH/2 + 4);
    // valor der
    ctx.fillStyle = '#fff';
    ctx.font = 'bold 10px Segoe UI';
    ctx.textAlign = 'left';
    ctx.fillText(v+'h', PAD.left + bW + 6, y + barH/2 + 4);
  });
}


function renderMapaBoxes() {
  setHeader('MAPA DE BOXES');
  setExpandedMode(true);
  showMetaPanel(true);
  menuGrid.className = '';
  menuGrid.innerHTML = '';

  const wrap = document.createElement('div');
  wrap.className = 'boxes-wrap';
  wrap.innerHTML = `
    <div class="boxes-header">
      <h2>Mapa interno de contenedores</h2>
      <p>Presione el contenedor que desea consultar. El contenedor 6 abre el detalle solicitado.</p>
    </div>
    <div class="boxes-grid" id="boxesGrid"></div>`;

  const boxesGrid = wrap.querySelector('#boxesGrid');

  boxesData.forEach(box => {
    const btn = document.createElement('button');
    btn.className = 'box-card';
    btn.type = 'button';
    btn.innerHTML = `
      <div class="box-card-number">${box.numero}</div>
      <div class="box-card-title">${box.titulo}</div>
      <div class="box-card-meta">Estiba: ${box.estiba}<br>Ubicación: ${box.ubicacion}</div>`;
    btn.addEventListener('click', () => {
      historyStack.push({ title: 'MAPA DE BOXES', custom: 'mapa_boxes' });
      if (box.numero === 6) { renderContenedor6Detail(); }
      else                  { alert(`Contenedor ${box.numero} seleccionado`); }
    });
    boxesGrid.appendChild(btn);
  });

  menuGrid.appendChild(wrap);
  syncBackBtn();
}

function renderContenedor6Detail() {
  setHeader('ESTIBA 65984-3');
  setExpandedMode(true);
  showMetaPanel(true);
  menuGrid.className = '';
  menuGrid.innerHTML = `
    <div class="estiba-detail-wrap">
      <section class="estiba-header-card">
        <div>
          <h2 class="estiba-title">ESTIBA 65984-3</h2>
          <p class="estiba-subtitle">Contenedor 6 · Información operativa y de calidad</p>
        </div>
        <div class="estiba-module-actions">
          <button class="estiba-module-btn logistica" id="btnModuloLogistica">Logística</button>
          <button class="estiba-module-btn calidad"   id="btnModuloCalidad">Calidad</button>
          <button class="estiba-module-btn salir"     id="btnSalirMenuPrincipal">Salir al menú principal</button>
        </div>
      </section>

      <section class="estiba-info-card">
        <div class="estiba-info-grid">
          <div class="estiba-info-box">
            <div class="estiba-info-label">Ubicación</div>
            <div class="estiba-info-value">16-5-4-2</div>
          </div>
          <div class="estiba-info-box">
            <div class="estiba-info-label">Tipo</div>
            <div class="estiba-info-value">Pallet</div>
          </div>
          <div class="estiba-info-box" style="grid-column:1 / -1;">
            <div class="estiba-ocupacion">
              <div class="estiba-ocupacion-top">
                <div class="estiba-info-label">Ocupación</div>
                <div class="estiba-info-value" style="margin-top:0;">78%</div>
              </div>
              <div class="estiba-progress">
                <div class="estiba-progress-bar" style="width:78%;"></div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="estiba-actions-card">
        <div class="estiba-actions-grid">
          <button class="estiba-action-btn intervenir"   id="btnAbrirIntervencion">Intervenir</button>
          <button class="estiba-action-btn liberar"      id="btnAbrirLiberacion">Liberar</button>
          <button class="estiba-action-btn imprimir"     id="btnImprimirEstiba">Imprimir estiba</button>
          <button class="estiba-action-btn fotos"        id="btnAbrirFotos">Cargar fotos</button>
          <button class="estiba-action-btn temperaturas" id="btnAbrirTemperaturas">Cargar temperaturas</button>
          <button class="estiba-action-btn certificado" id="btnAbrirCertificado">Control certificado</button>
        </div>
      </section>

      <section class="estiba-detail-card">
        <h3 class="estiba-detail-title">Detalle</h3>
        <div class="estiba-detail-table">
          ${detailProducts.map(item => `
            <div class="estiba-detail-row">
              <div class="estiba-detail-product">${item.producto}</div>
              <div class="estiba-detail-date">${item.fecha}</div>
            </div>`).join('')}
        </div>
      </section>
    </div>`;

  document.getElementById('btnAbrirIntervencion').addEventListener('click', () => {
    historyStack.push({ title: 'ESTIBA 65984-3', custom: 'detalle_estiba_6' });
    renderAccionEstibaForm('intervenir');
  });
  document.getElementById('btnAbrirLiberacion').addEventListener('click', () => {
    historyStack.push({ title: 'ESTIBA 65984-3', custom: 'detalle_estiba_6' });
    renderAccionEstibaForm('liberar');
  });
  document.getElementById('btnAbrirTemperaturas').addEventListener('click', () => {
    historyStack.push({ title: 'ESTIBA 65984-3', custom: 'detalle_estiba_6' });
    renderTemperaturasForm();
  });
  document.getElementById('btnImprimirEstiba').addEventListener('click', () => { window.print(); });
  document.getElementById('btnAbrirFotos').addEventListener('click', () => {
    historyStack.push({ title: 'ESTIBA 65984-3', custom: 'detalle_estiba_6' });
    renderFotosForm();
  });
  document.getElementById('btnAbrirCertificado').addEventListener('click', () => {
    historyStack.push({ title: 'ESTIBA 65984-3', custom: 'detalle_estiba_6' });
    renderControlCertificado();
  });
  document.getElementById('btnModuloLogistica').addEventListener('click', () => { alert('Abrir módulo Logística'); });
  document.getElementById('btnModuloCalidad').addEventListener('click',   () => { alert('Abrir módulo Calidad'); });
  document.getElementById('btnSalirMenuPrincipal').addEventListener('click', () => {
    historyStack.length = 0;
    renderNode(menuTree);
  });

  syncBackBtn();
}

function renderAccionEstibaForm(tipoAccion) {
  const esLiberacion = tipoAccion === 'liberar';
  const titulo       = esLiberacion ? 'LIBERACION DE ESTIBA' : 'INTERVENCION DE ESTIBA';
  const verbo        = esLiberacion ? 'Liberar' : 'Intervenir';
  const descripcion  = esLiberacion
    ? 'Complete la información para registrar la liberación.'
    : 'Complete la información para registrar la intervención.';

  setHeader(titulo);
  setExpandedMode(true);
  showMetaPanel(false);   // ← ocultar tira de datos en formularios
  menuGrid.className = '';
  menuGrid.innerHTML = `
    <div class="intervencion-wrap">
      <section class="intervencion-card">
        <h2 class="intervencion-title">${verbo} estiba 65984-3</h2>
        <p class="intervencion-subtitle">${descripcion}</p>
      </section>

      <section class="intervencion-card">
        <h3 class="intervencion-section-title">Tipo de ${esLiberacion ? 'liberación' : 'intervención'}</h3>
        <div class="intervencion-options">
          <label class="intervencion-option-row">
            <input type="radio" name="tipoAccionEstiba" value="estiba_completa" checked>
            <span class="intervencion-option-label">${verbo} estiba completa</span>
          </label>
          <label class="intervencion-option-row">
            <input type="radio" name="tipoAccionEstiba" value="estiba_parcial">
            <span class="intervencion-option-label">${verbo} estiba parcial</span>
            <input type="number" id="cantidadParcial" class="intervencion-partial-qty" min="1" placeholder="Cantidad" disabled>
          </label>
          <label class="intervencion-option-row">
            <input type="radio" name="tipoAccionEstiba" value="entrada_entera">
            <span class="intervencion-option-label">${verbo} entrada entera</span>
          </label>
        </div>
      </section>

      <section class="intervencion-card">
        <div class="intervencion-field">
          <label class="intervencion-label" for="motivoAccionEstiba">Motivo</label>
          <select id="motivoAccionEstiba" class="intervencion-select">
            <option value="">Seleccione una opción</option>
            <option>A pedido del cliente</option>
            <option>Calidad</option>
            <option>Documentación</option>
            <option>Envases rotos y/o con faltante</option>
            <option>Mercadería vencida o pronta a vencer</option>
            <option>Senasa</option>
          </select>
        </div>
      </section>

      <section class="intervencion-card">
        <div class="intervencion-field">
          <label class="intervencion-label" for="observacionesAccionEstiba">Observaciones</label>
          <textarea id="observacionesAccionEstiba" class="intervencion-textarea" placeholder="Escriba aquí las observaciones..."></textarea>
        </div>
      </section>

      <section class="intervencion-card">
        <div class="intervencion-actions">
          <button id="btnConfirmarAccionEstiba" class="intervencion-submit">${verbo}</button>
        </div>
      </section>
    </div>`;

  const radios          = document.querySelectorAll('input[name="tipoAccionEstiba"]');
  const cantidadParcial = document.getElementById('cantidadParcial');

  function updateCantidadParcial() {
    const isParcial = document.querySelector('input[name="tipoAccionEstiba"]:checked')?.value === 'estiba_parcial';
    cantidadParcial.disabled = !isParcial;
    if (isParcial) cantidadParcial.focus();
    else cantidadParcial.value = '';
  }
  radios.forEach(r => r.addEventListener('change', updateCantidadParcial));
  updateCantidadParcial();

  document.getElementById('btnConfirmarAccionEstiba').addEventListener('click', () => {
    const tipo   = document.querySelector('input[name="tipoAccionEstiba"]:checked')?.value || '';
    const motivo = document.getElementById('motivoAccionEstiba')?.value || '';
    if (!motivo) { alert(`Seleccione un motivo para ${esLiberacion ? 'la liberación' : 'la intervención'}.`); return; }
    if (tipo === 'estiba_parcial') {
      const cantidad = Number(cantidadParcial.value);
      if (!cantidad || cantidad <= 0) { alert(`Ingrese la cantidad para ${esLiberacion ? 'la liberación' : 'la intervención'} parcial.`); return; }
    }
    showMetaPanel(true);
    historyStack.length = 0;
    renderNode(menuTree);
  });

  syncBackBtn();
}

function renderTemperaturasForm() {
  setHeader('CARGA DE TEMPERATURAS');
  setExpandedMode(true);
  showMetaPanel(false);
  menuGrid.className = '';
  menuGrid.innerHTML = `
    <div class="temperaturas-wrap">
      <section class="temperaturas-card">
        <h2 class="temperaturas-title">Cargar temperaturas de estiba 65984-3</h2>
        <p class="temperaturas-subtitle">Seleccione hasta 10 productos y cargue 2 temperaturas por cada uno.</p>
      </section>
      <section class="temperaturas-card">
        <h3 class="temperaturas-section-title">Temperaturas</h3>
        <div class="temperaturas-list">
          ${Array.from({length: 10}, (_, i) => `
            <div class="temperaturas-row">
              <div class="temperaturas-row-title">Registro ${i + 1}</div>
              <div class="temperaturas-field">
                <label class="temperaturas-label" for="producto_${i}">Producto</label>
                <select id="producto_${i}" class="temperaturas-select">
                  <option value="">Seleccione un producto</option>
                  ${detailProducts.map(p => `<option value="${p.producto}">${p.producto}</option>`).join('')}
                </select>
              </div>
              <div class="temperaturas-field">
                <label class="temperaturas-label" for="temp1_${i}">Temperatura 1</label>
                <input id="temp1_${i}" class="temperaturas-input" type="number" step="0.1" placeholder="Ej: -18.5">
              </div>
              <div class="temperaturas-field">
                <label class="temperaturas-label" for="temp2_${i}">Temperatura 2</label>
                <input id="temp2_${i}" class="temperaturas-input" type="number" step="0.1" placeholder="Ej: -17.9">
              </div>
            </div>`).join('')}
        </div>
      </section>
      <section class="temperaturas-card">
        <div class="temperaturas-actions">
          <button id="btnCargarTemperaturas" class="temperaturas-submit">Cargar datos</button>
        </div>
      </section>
    </div>`;

  document.getElementById('btnCargarTemperaturas').addEventListener('click', () => {
    let validos = 0;
    for (let i = 0; i < 10; i++) {
      const prod = document.getElementById(`producto_${i}`)?.value || '';
      const t1   = document.getElementById(`temp1_${i}`)?.value   || '';
      const t2   = document.getElementById(`temp2_${i}`)?.value   || '';
      if (prod || t1 || t2) {
        if (!prod) { alert(`Seleccione el producto en el registro ${i + 1}.`); return; }
        if (t1 === '' || t2 === '') { alert(`Complete las 2 temperaturas del registro ${i + 1}.`); return; }
        validos++;
      }
    }
    if (validos === 0) { alert('Cargue al menos un registro de temperatura.'); return; }
    showMetaPanel(true);
    historyStack.length = 0;
    renderNode(menuTree);
  });

  syncBackBtn();
}

function renderFotosForm() {
  setHeader('CARGA DE FOTOS');
  setExpandedMode(true);
  showMetaPanel(false);
  menuGrid.className = '';
  menuGrid.innerHTML = `
    <div class="intervencion-wrap">
      <section class="intervencion-card">
        <h2 class="intervencion-title">Cargar fotos de estiba 65984-3</h2>
        <p class="intervencion-subtitle">Adjuntá imágenes desde archivo o tomá una foto directamente con la cámara.</p>
      </section>

      <section class="intervencion-card">
        <div class="foto-upload-row">
          <!-- Desde archivo -->
          <label class="foto-source-btn foto-btn-archivo" for="fotoFileInput">
            <span class="foto-btn-icon">🖼</span>
            <span class="foto-btn-label">Seleccionar<br>desde archivo</span>
            <input type="file" id="fotoFileInput" multiple accept="image/*" style="display:none" />
          </label>
          <!-- Desde cámara -->
          <label class="foto-source-btn foto-btn-camara" for="fotoCameraInput">
            <span class="foto-btn-icon">📷</span>
            <span class="foto-btn-label">Tomar foto<br>con cámara</span>
            <input type="file" id="fotoCameraInput" accept="image/*" capture="environment" style="display:none" />
          </label>
        </div>
      </section>

      <!-- Preview de fotos cargadas -->
      <section class="intervencion-card" id="fotoPreviewSection" style="display:none">
        <div class="intervencion-field">
          <label class="intervencion-label">Fotos seleccionadas (<span id="fotoCount">0</span>)</label>
          <div class="foto-preview-grid" id="fotoPreviewGrid"></div>
        </div>
      </section>

      <section class="intervencion-card">
        <div class="intervencion-field">
          <label class="intervencion-label">Observaciones</label>
          <textarea class="intervencion-textarea" id="fotoObs" placeholder="Escriba aquí un detalle de las fotos cargadas..."></textarea>
        </div>
      </section>
      <section class="intervencion-card">
        <div class="intervencion-actions">
          <button id="btnGuardarFotos" class="intervencion-submit" disabled>Guardar fotos</button>
        </div>
      </section>
    </div>`;

  // Acumular fotos de ambas fuentes
  const fotosAcumuladas = [];

  const renderPreviews = () => {
    const grid    = document.getElementById('fotoPreviewGrid');
    const section = document.getElementById('fotoPreviewSection');
    const countEl = document.getElementById('fotoCount');
    const saveBtn = document.getElementById('btnGuardarFotos');
    if (!grid) return;
    grid.innerHTML = '';
    fotosAcumuladas.forEach((file, i) => {
      const url = URL.createObjectURL(file);
      const div = document.createElement('div');
      div.className = 'foto-preview-item';
      div.innerHTML =
        '<img src="' + url + '" class="foto-preview-img" />'
        + '<button class="foto-remove-btn" data-i="' + i + '" title="Eliminar">✕</button>'
        + '<span class="foto-preview-name">' + file.name + '</span>';
      grid.appendChild(div);
    });
    if (countEl) countEl.textContent = fotosAcumuladas.length;
    if (section) section.style.display = fotosAcumuladas.length ? '' : 'none';
    if (saveBtn) saveBtn.disabled = fotosAcumuladas.length === 0;
  };

  const addFiles = (files) => {
    Array.from(files).forEach(f => fotosAcumuladas.push(f));
    renderPreviews();
  };

  const fileInput   = document.getElementById('fotoFileInput');
  const cameraInput = document.getElementById('fotoCameraInput');
  if (fileInput)   fileInput.addEventListener('change',   e => addFiles(e.target.files));
  if (cameraInput) cameraInput.addEventListener('change', e => addFiles(e.target.files));

  document.getElementById('fotoPreviewGrid')?.addEventListener('click', e => {
    const btn = e.target.closest('.foto-remove-btn');
    if (!btn) return;
    fotosAcumuladas.splice(parseInt(btn.dataset.i), 1);
    renderPreviews();
  });

  document.getElementById('btnGuardarFotos').addEventListener('click', () => {
    showToast('✅ ' + fotosAcumuladas.length + ' foto' + (fotosAcumuladas.length > 1 ? 's' : '') + ' guardada' + (fotosAcumuladas.length > 1 ? 's' : ''));
    showMetaPanel(true);
    setExpandedMode(false);
    historyStack.length = 0;
    renderNode(menuTree);
  });

  syncBackBtn();
}

/* ── RENDER PRINCIPAL ───────────────────────────────────────── */
function renderNode(node) {
  setExpandedMode(false);
  showMetaPanel(true);

  if (node.custom === 'mapa_boxes')      { renderMapaBoxes();          return; }
  if (node.custom === 'detalle_estiba_6'){ renderContenedor6Detail();  return; }

  const items = node.children || [];
  const level = historyStack.length;

  setHeader(level ? node.title : 'BIENVENIDOS AL SISTEMA DE GRUPO CINA');
  const cnt = items.length || 1;
  menuGrid.className = `menu-grid ${getGridLayout(cnt)}`;
  menuGrid.dataset.count = cnt;
  menuGrid.innerHTML = '';

  if (!items.length) {
    const tile = document.createElement('button');
    tile.className = 'tile';
    tile.type = 'button';
    tile.style.background = 'linear-gradient(135deg,#163A7A,#0C2554)';
    tile.innerHTML = `
      <div class="tile-icon">${getIconMarkup(node.title)}</div>
      <div class="tile-title ${titleClass(node.title)}">${formatTitle(node.title)}</div>`;
    if (node.url) tile.addEventListener('click', () => openModule(node.url));
    menuGrid.appendChild(tile);
  } else {
    items.forEach(item => {
      const colors = getTileColors(item, level);
      const tile   = document.createElement('button');
      tile.className = 'tile';
      tile.type = 'button';
      tile.style.background = `linear-gradient(135deg,${colors.start},${colors.end})`;
      tile.innerHTML = getCustomTileHTML(item) || `
        <div class="tile-icon">${getIconMarkup(item.title)}</div>
        <div class="tile-title ${titleClass(item.title)}">${formatTitle(item.title)}</div>`;

      tile.addEventListener('click', () => {
        if (item.title === 'MAPA DE BOXES')                  { historyStack.push(node); renderMapaBoxes();                    return; }
        if (item.title === 'DASHBOARD LOGISTICA NACIONAL')   { historyStack.push(node); renderDashboardLogisticaNacional();   return; }
        if (item.title === 'DASHBOARD EQUIPAMIENTO')         { historyStack.push(node); renderDashboardEquipamiento();        return; }
        if (item.title === 'CARGAS I-2')                     { historyStack.push(node); renderIndicadorCargasI2();            return; }
        if (item.title === 'ALMACENAMIENTO DE POSICIONES')   { historyStack.push(node); renderIndicadorPosiciones();          return; }
        if (item.title === 'POSICIONES INGRESADAS EGRESADAS') { historyStack.push(node); renderIndicadorIngEgr();             return; }
        if (item.title === 'MOVIMIENTOS' && node.title === 'INDICADORES') { historyStack.push(node); renderIndicadorMovimientos();      return; }
        if (item.title === 'CLIENTES QUE OPERARON')           { historyStack.push(node); renderIndicadorClientes();            return; }
        if (item.title === 'AJUSTES DE STOCK')               { historyStack.push(node); renderIndicadorAjustes();             return; }
        if (item.title === 'ESTADISTICAS DE PERSONAL')       { historyStack.push(node); renderIndicadorPersonal();             return; }
        if (item.title === 'USO DE EQUIPOS')                 { historyStack.push(node); renderIndicadorUsoEquipos();           return; }
        if (item.title === 'ESTIBAS CONGELADAS')             { historyStack.push(node); renderIndicadorEstibasCongeladas();   return; }
        if (item.title === 'FACTURACION' && node.title === 'ADMINISTRACION') { historyStack.push(node); renderFacturacion(); return; }
        if (item.title === 'REMITOS'     && node.title === 'ADMINISTRACION') { historyStack.push(node); renderRemitos();      return; }
        if (item.title === 'COMPRAS'     && node.title === 'ADMINISTRACION') { historyStack.push(node); renderCompras();      return; }
        if (item.children)                                   { historyStack.push(node); renderNode(item);                    return; }
        if (item.url)                                        { openModule(item.url);                                         return; }
        historyStack.push(node);
        renderNode({ title: item.title, url: item.url, children: [] });
      });

      menuGrid.appendChild(tile);
    });
  }

  syncBackBtn();
}

/* ── LAYOUT ADAPTATIVO ──────────────────────────────────────── */
function updateAdaptiveLayout() {
  const isPortrait  = window.innerHeight > window.innerWidth;
  const isSmall     = window.innerWidth < 1400 || window.innerHeight < 860;
  document.body.classList.toggle('orient-portrait',  isPortrait);
  document.body.classList.toggle('orient-landscape', !isPortrait && isSmall);
  document.body.classList.toggle('compact-mode',     isSmall);
}

/* ── EVENTOS GLOBALES ───────────────────────────────────────── */
backBtn.addEventListener('click', () => {
  if (!historyStack.length) return;
  showMetaPanel(true);
  const previous = historyStack.pop();
  renderNode(previous);
});

homeBtn.addEventListener('click', () => {
  historyStack.length = 0;
  showMetaPanel(true);
  renderNode(menuTree);
});

fullscreenBtn.addEventListener('click', async () => {
  try {
    if (!document.fullscreenElement) await document.documentElement.requestFullscreen();
    else                             await document.exitFullscreen();
  } catch (e) {
    console.warn('Fullscreen no disponible:', e);
  } finally {
    updateAdaptiveLayout();
  }
});

userBtn.addEventListener('click', () => openModule('https://sistema.cinafrio.com/intranet2/app.php/persona/306/show'));
qrBox.addEventListener('click', () => { historyStack.push(menuTree); renderContenedor6Detail(); });
sinUbicacionBox.addEventListener('click', () => openModule('https://sistema.cinafrio.com/intranet/index.php/estiba/sinPosicion'));
vencidasBox.addEventListener('click',     () => openModule('https://sistema.cinafrio.com/intranet/index.php/infostock/vencimientos'));

document.addEventListener('fullscreenchange', updateAdaptiveLayout);
window.addEventListener('resize', updateAdaptiveLayout);

/* ── INICIO ─────────────────────────────────────────────────── */
updateAdaptiveLayout();
renderNode(menuTree);

/* ═══════════════════════════════════════════════════════════════
   DATOS + RENDER: ESTIBAS CONGELADAS
   ═══════════════════════════════════════════════════════════════ */
const estibasCongeladasData = {
  meses: [
    { mes:'Abr 24', valor:1456 },
    { mes:'May 24', valor:941  },
    { mes:'Jun 24', valor:1188 },
    { mes:'Jul 24', valor:1050 },
    { mes:'Ago 24', valor:1320 },
    { mes:'Sep 24', valor:1503 },
    { mes:'Oct 24', valor:1180 },
    { mes:'Nov 24', valor:1264 },
    { mes:'Dic 24', valor:1390 },
    { mes:'Ene 25', valor:1046 },
    { mes:'Feb 25', valor:1120 },
    { mes:'Mar 25', valor:874  },
  ],
};

function renderIndicadorEstibasCongeladas() {
  setHeader('INDICADORES');
  setExpandedMode(false);
  showMetaPanel(true);
  menuGrid.className = '';
  menuGrid.innerHTML = '';

  const wrap = document.createElement('div');
  wrap.className = 'indicador-wrap';

  const data   = estibasCongeladasData.meses;
  const actual = data[data.length - 1];
  const prom   = Math.round(data.reduce((s,m)=>s+m.valor,0) / data.length);
  const max    = data.reduce((a,m) => m.valor>a.valor?m:a, data[0]);
  const min    = data.reduce((a,m) => m.valor<a.valor?m:a, data[0]);

  // Card resumen
  const card = document.createElement('div');
  card.className = 'indicador-card indicador-card-static';
  card.innerHTML = `
    <div class="indicador-card-header">
      <div class="indicador-card-title">
        <span class="indicador-badge" style="background:rgba(96,200,224,.15);color:#60c8e0;border-color:rgba(96,200,224,.3)">❄</span>
        ESTIBAS CONGELADAS — TUNELES
      </div>
      <span class="indicador-hint">Estibas que pasaron por túneles de congelamiento</span>
    </div>
    <div class="inegr-totales">
      <div class="inegr-total-item">
        <span class="inegr-total-label">Mes actual (${actual.mes})</span>
        <span class="inegr-total-val" style="color:#60c8e0">${actual.valor.toLocaleString('es-AR')}</span>
      </div>
      <div class="inegr-total-sep"></div>
      <div class="inegr-total-item">
        <span class="inegr-total-label">Promedio 12m</span>
        <span class="inegr-total-val" style="color:#A78BFA">${prom.toLocaleString('es-AR')}</span>
      </div>
      <div class="inegr-total-sep"></div>
      <div class="inegr-total-item">
        <span class="inegr-total-label">Máx (${max.mes})</span>
        <span class="inegr-total-val" style="color:#00A887">${max.valor.toLocaleString('es-AR')}</span>
      </div>
      <div class="inegr-total-sep"></div>
      <div class="inegr-total-item">
        <span class="inegr-total-label">Mín (${min.mes})</span>
        <span class="inegr-total-val" style="color:#F97316">${min.valor.toLocaleString('es-AR')}</span>
      </div>
    </div>`;
  wrap.appendChild(card);

  // Gráfico de línea mensual
  const chartSec = document.createElement('div');
  chartSec.className = 'indicador-mensual';
  chartSec.innerHTML = `
    <div class="indicador-mensual-title">📈 Estibas congeladas por mes — últimos 12 meses</div>
    <div class="inegr-chart-wrap" style="height:240px"><canvas id="estibasLineChart"></canvas></div>`;
  wrap.appendChild(chartSec);

  // Cards mensuales
  const mesGrid = document.createElement('div');
  mesGrid.className = 'indicador-mensual';
  mesGrid.innerHTML = `
    <div class="indicador-mensual-title">📅 Detalle mensual</div>
    <div class="indicador-mensual-grid">
      ${data.map((m,i) => {
        const isActual = i === data.length-1;
        const diffPct  = i===0 ? 0 : Math.round(((m.valor - data[i-1].valor)/data[i-1].valor)*100);
        const diffColor = diffPct>=0?'#00A887':'#DC2626';
        const diffStr  = i===0 ? '' : (diffPct>=0?'+':'')+diffPct+'%';
        return `
          <div class="ind-mes-card${isActual?' ind-mes-actual':''}">
            <div class="ind-mes-label">${m.mes}</div>
            <div class="ind-mes-pct" style="color:#60c8e0;font-size:1rem">${m.valor.toLocaleString('es-AR')}</div>
            <div class="ind-mes-bar-wrap">
              <div class="ind-mes-bar-fill" style="width:${Math.round((m.valor/max.valor)*100)}%;background:#60c8e0"></div>
            </div>
            <div class="ind-mes-detail" style="color:${diffColor}">${diffStr || '—'}</div>
          </div>`;
      }).join('')}
    </div>`;
  wrap.appendChild(mesGrid);

  menuGrid.appendChild(wrap);
  syncBackBtn();

  requestAnimationFrame(() => drawEstibasLine('estibasLineChart'));
}

function drawEstibasLine(id) {
  const canvas = document.getElementById(id);
  if (!canvas) return;
  const wrap = canvas.parentElement;
  canvas.width  = wrap.clientWidth  || 700;
  canvas.height = wrap.clientHeight || 240;
  const W = canvas.width, H = canvas.height;
  const PAD = { top:28, right:16, bottom:38, left:52 };
  const chartW = W - PAD.left - PAD.right;
  const chartH = H - PAD.top  - PAD.bottom;
  const ctx  = canvas.getContext('2d');
  const data = estibasCongeladasData.meses;
  const vals = data.map(m => m.valor);
  const n    = vals.length;
  const minV = Math.min(...vals) * 0.88;
  const maxV = Math.max(...vals) * 1.08;
  const range = maxV - minV;

  ctx.clearRect(0, 0, W, H);

  const xOf = i => PAD.left + (i / (n-1)) * chartW;
  const yOf = v => PAD.top  + chartH - ((v - minV) / range) * chartH;

  // Grid horizontal
  for (let i=0; i<=4; i++) {
    const v = minV + (range * i/4);
    const y = yOf(v);
    ctx.strokeStyle = 'rgba(255,255,255,.07)';
    ctx.lineWidth = 1;
    ctx.beginPath(); ctx.moveTo(PAD.left, y); ctx.lineTo(PAD.left+chartW, y); ctx.stroke();
    ctx.fillStyle = 'rgba(255,255,255,.4)';
    ctx.font = '9px Segoe UI';
    ctx.textAlign = 'right';
    ctx.fillText(Math.round(v).toLocaleString('es-AR'), PAD.left-5, y+3);
  }

  // Area fill
  ctx.beginPath();
  ctx.moveTo(xOf(0), yOf(vals[0]));
  vals.forEach((v,i) => ctx.lineTo(xOf(i), yOf(v)));
  ctx.lineTo(xOf(n-1), PAD.top+chartH);
  ctx.lineTo(xOf(0),   PAD.top+chartH);
  ctx.closePath();
  const grad = ctx.createLinearGradient(0, PAD.top, 0, PAD.top+chartH);
  grad.addColorStop(0,   'rgba(96,200,224,.35)');
  grad.addColorStop(1,   'rgba(96,200,224,.02)');
  ctx.fillStyle = grad;
  ctx.fill();

  // Line
  ctx.beginPath();
  ctx.moveTo(xOf(0), yOf(vals[0]));
  vals.forEach((v,i) => ctx.lineTo(xOf(i), yOf(v)));
  ctx.strokeStyle = '#60c8e0';
  ctx.lineWidth = 2.5;
  ctx.lineJoin = 'round';
  ctx.stroke();

  // Promedio dashed
  const prom = vals.reduce((s,v)=>s+v,0)/n;
  const promY = yOf(prom);
  ctx.strokeStyle = 'rgba(167,139,250,.45)';
  ctx.lineWidth = 1;
  ctx.setLineDash([5,5]);
  ctx.beginPath(); ctx.moveTo(PAD.left, promY); ctx.lineTo(PAD.left+chartW, promY); ctx.stroke();
  ctx.setLineDash([]);

  // Dots + valores
  vals.forEach((v,i) => {
    const x = xOf(i), y = yOf(v);
    ctx.beginPath();
    ctx.arc(x, y, 4, 0, 2*Math.PI);
    ctx.fillStyle = '#60c8e0';
    ctx.fill();
    ctx.strokeStyle = '#0f1e35';
    ctx.lineWidth = 1.5;
    ctx.stroke();
    // valor encima
    ctx.fillStyle = '#fff';
    ctx.font = (i===n-1?'bold ':'')+'9px Segoe UI';
    ctx.textAlign = 'center';
    ctx.fillText(v.toLocaleString('es-AR'), x, y-10);
    // mes abajo
    ctx.fillStyle = 'rgba(255,255,255,.45)';
    ctx.font = '8px Segoe UI';
    ctx.fillText(data[i].mes.split(' ')[0], x, H-PAD.bottom+12);
  });
}

/* ═══════════════════════════════════════════════════════════════
   MÓDULO: FACTURACIÓN
   ═══════════════════════════════════════════════════════════════ */
function renderFacturacion() {
  setHeader('ADMINISTRACION');
  setExpandedMode(false);
  showMetaPanel(true);
  menuGrid.className = '';
  menuGrid.innerHTML = '';

  // Estado
  let seleccionados = new Set();
  let busqueda = '';

  // ── Fecha default: 1ro del mes actual hasta hoy ──────────────
  const hoy     = new Date();
  const primeroDeMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
  const fmtDate = d => d.toISOString().split('T')[0];

  const wrap = document.createElement('div');
  wrap.className = 'fact-wrap';

  wrap.innerHTML = `
    <div class="fact-header">
      <div class="fact-header-title">
        <span class="indicador-badge" style="background:rgba(249,115,22,.2);color:#F97316;border-color:rgba(249,115,22,.35)">FAC</span>
        FACTURACIÓN — RESÚMENES POR CLIENTE
      </div>
      <span class="indicador-hint">Seleccioná clientes, período y descargá los resúmenes</span>
    </div>

    <!-- ── BLOQUE 1: SELECCIÓN DE CLIENTES ── -->
    <div class="fact-section">
      <div class="fact-section-title">
        <span class="fact-section-icon">👥</span>
        Clientes que operaron en el mes
        <span class="fact-sel-count" id="factSelCount">0 seleccionados</span>
        <button class="fact-sel-all-btn" id="factSelAll">Seleccionar todos</button>
        <button class="fact-sel-none-btn" id="factSelNone">Limpiar</button>
      </div>

      <div class="fact-search-wrap">
        <input class="fact-search" id="factSearch" type="text" placeholder="🔍  Buscar cliente..." autocomplete="off" />
      </div>

      <div class="fact-clients-grid" id="factClientsGrid">
        ${clientesOperaron.map((c, i) => `
          <button class="fact-client-chip" data-idx="${i}" data-name="${c.toLowerCase()}" title="${c}">
            <span class="fact-chip-check">✓</span>
            <span class="fact-chip-name">${c}</span>
          </button>`).join('')}
      </div>
    </div>

    <!-- ── BLOQUE 2: PERÍODO ── -->
    <div class="fact-section fact-period-section">
      <div class="fact-section-title">
        <span class="fact-section-icon">📅</span>
        Período de facturación
      </div>
      <div class="fact-period-row">
        <div class="fact-date-group">
          <label class="fact-date-label">Desde</label>
          <input class="fact-date-input" id="factDesde" type="date" value="${fmtDate(primeroDeMes)}" />
        </div>
        <div class="fact-period-sep">→</div>
        <div class="fact-date-group">
          <label class="fact-date-label">Hasta</label>
          <input class="fact-date-input" id="factHasta" type="date" value="${fmtDate(hoy)}" />
        </div>
        <div class="fact-period-shortcuts">
          <button class="fact-shortcut-btn" data-range="mes">Mes actual</button>
          <button class="fact-shortcut-btn" data-range="mesant">Mes anterior</button>
          <button class="fact-shortcut-btn" data-range="trim">Trimestre</button>
          <button class="fact-shortcut-btn" data-range="anio">Año</button>
        </div>
      </div>
    </div>

    <!-- ── BLOQUE 3: DESCARGA ── -->
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
          ⬇ Descargar resúmenes
        </button>
      </div>
    </div>
  `;

  menuGrid.appendChild(wrap);
  syncBackBtn();

  // ── Referencias ──────────────────────────────────────────────
  const grid      = wrap.querySelector('#factClientsGrid');
  const searchEl  = wrap.querySelector('#factSearch');
  const countEl   = wrap.querySelector('#factSelCount');
  const previewEl = wrap.querySelector('#factSummaryPreview');
  const dlBtn     = wrap.querySelector('#factDownloadBtn');
  const hintEl    = wrap.querySelector('#factResumenHint');
  const selAllBtn = wrap.querySelector('#factSelAll');
  const selNoneBtn= wrap.querySelector('#factSelNone');

  // ── Actualizar contador y preview ───────────────────────────
  const updateUI = () => {
    const n = seleccionados.size;
    countEl.textContent = n === 0 ? '0 seleccionados'
                        : n === 1 ? '1 seleccionado'
                        : `${n} seleccionados`;
    countEl.style.color = n > 0 ? '#36B0C9' : 'rgba(255,255,255,.4)';

    dlBtn.disabled = n === 0;
    hintEl.textContent = n === 0
      ? 'Seleccioná al menos un cliente'
      : `${n} resumen${n>1?'es':''} · período ${wrap.querySelector('#factDesde').value} → ${wrap.querySelector('#factHasta').value}`;
    hintEl.style.color = n > 0 ? '#00A887' : 'rgba(255,255,255,.35)';

    if (n === 0) {
      previewEl.innerHTML = '<span class="fact-preview-empty">— sin clientes seleccionados —</span>';
    } else {
      const names = [...seleccionados].map(i => clientesOperaron[i]);
      previewEl.innerHTML = names.map(name => `
        <div class="fact-preview-chip">
          <span class="fact-preview-name">${name}</span>
          <span class="fact-preview-period">${wrap.querySelector('#factDesde').value} → ${wrap.querySelector('#factHasta').value}</span>
        </div>`).join('');
    }
  };

  // ── Toggle chip ──────────────────────────────────────────────
  const toggleChip = (chip, idx) => {
    if (seleccionados.has(idx)) {
      seleccionados.delete(idx);
      chip.classList.remove('selected');
    } else {
      seleccionados.add(idx);
      chip.classList.add('selected');
    }
    updateUI();
  };

  grid.addEventListener('click', e => {
    const chip = e.target.closest('.fact-client-chip');
    if (!chip) return;
    toggleChip(chip, parseInt(chip.dataset.idx));
  });

  // ── Buscar ───────────────────────────────────────────────────
  searchEl.addEventListener('input', () => {
    busqueda = searchEl.value.toLowerCase().trim();
    grid.querySelectorAll('.fact-client-chip').forEach(chip => {
      chip.style.display = (!busqueda || chip.dataset.name.includes(busqueda)) ? '' : 'none';
    });
  });

  // ── Seleccionar / limpiar todos ──────────────────────────────
  selAllBtn.addEventListener('click', () => {
    grid.querySelectorAll('.fact-client-chip:not([style*="none"])').forEach(chip => {
      const idx = parseInt(chip.dataset.idx);
      seleccionados.add(idx);
      chip.classList.add('selected');
    });
    updateUI();
  });
  selNoneBtn.addEventListener('click', () => {
    seleccionados.clear();
    grid.querySelectorAll('.fact-client-chip').forEach(c => c.classList.remove('selected'));
    updateUI();
  });

  // ── Atajos de fecha ──────────────────────────────────────────
  wrap.querySelectorAll('.fact-shortcut-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      wrap.querySelectorAll('.fact-shortcut-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const now   = new Date();
      const desde = wrap.querySelector('#factDesde');
      const hasta = wrap.querySelector('#factHasta');
      switch (btn.dataset.range) {
        case 'mes':
          desde.value = fmtDate(new Date(now.getFullYear(), now.getMonth(), 1));
          hasta.value = fmtDate(now);
          break;
        case 'mesant': {
          const pm = new Date(now.getFullYear(), now.getMonth() - 1, 1);
          desde.value = fmtDate(pm);
          hasta.value = fmtDate(new Date(now.getFullYear(), now.getMonth(), 0));
          break;
        }
        case 'trim':
          desde.value = fmtDate(new Date(now.getFullYear(), now.getMonth() - 2, 1));
          hasta.value = fmtDate(now);
          break;
        case 'anio':
          desde.value = fmtDate(new Date(now.getFullYear(), 0, 1));
          hasta.value = fmtDate(now);
          break;
      }
      updateUI();
    });
  });

  // Actualizar hint cuando cambian fechas
  wrap.querySelector('#factDesde').addEventListener('change', updateUI);
  wrap.querySelector('#factHasta').addEventListener('change', updateUI);

  // ── Botón descargar ──────────────────────────────────────────
  dlBtn.addEventListener('click', () => {
    const desde  = wrap.querySelector('#factDesde').value;
    const hasta  = wrap.querySelector('#factHasta').value;
    const names  = [...seleccionados].map(i => clientesOperaron[i]);

    // Simula descarga: muestra toast + abre URL del sistema por cliente
    const plural = names.length > 1 ? 'es' : '';
    showToast('Generando ' + names.length + ' resumen' + plural + '...');

    // En producción esto llamaría a la API real por cada cliente.
    // Por ahora abre el módulo de facturación del sistema.
    setTimeout(() => {
      openModule(`https://sistema.cinafrio.com/intranet/index.php/facturacion?desde=${desde}&hasta=${hasta}`);
    }, 800);
  });

  updateUI();
}

/* ═══════════════════════════════════════════════════════════════
   MÓDULO: REMITOS (igual a facturación, descarga remitos)
   ═══════════════════════════════════════════════════════════════ */
function renderRemitos() {
  setHeader('ADMINISTRACION');
  setExpandedMode(false);
  showMetaPanel(true);
  menuGrid.className = '';
  menuGrid.innerHTML = '';

  let seleccionados = new Set();
  const hoy          = new Date();
  const primeroDeMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
  const fmtDate      = d => d.toISOString().split('T')[0];

  const wrap = document.createElement('div');
  wrap.className = 'fact-wrap';
  wrap.innerHTML = `
    <div class="fact-header">
      <div class="fact-header-title">
        <span class="indicador-badge" style="background:rgba(167,139,250,.2);color:#A78BFA;border-color:rgba(167,139,250,.35)">REM</span>
        REMITOS — RESÚMENES POR CLIENTE
      </div>
      <span class="indicador-hint">Seleccioná clientes, período y descargá los remitos</span>
    </div>
    <div class="fact-section">
      <div class="fact-section-title">
        <span class="fact-section-icon">👥</span>
        Clientes que operaron en el mes
        <span class="fact-sel-count" id="remSelCount">0 seleccionados</span>
        <button class="fact-sel-all-btn" id="remSelAll">Seleccionar todos</button>
        <button class="fact-sel-none-btn" id="remSelNone">Limpiar</button>
      </div>
      <div class="fact-search-wrap">
        <input class="fact-search" id="remSearch" type="text" placeholder="🔍  Buscar cliente..." autocomplete="off" />
      </div>
      <div class="fact-clients-grid" id="remClientsGrid">
        ${clientesOperaron.map((c, i) => `
          <button class="fact-client-chip" data-idx="${i}" data-name="${c.toLowerCase()}" title="${c}">
            <span class="fact-chip-check">✓</span>
            <span class="fact-chip-name">${c}</span>
          </button>`).join('')}
      </div>
    </div>
    <div class="fact-section fact-period-section">
      <div class="fact-section-title">
        <span class="fact-section-icon">📅</span>
        Período
      </div>
      <div class="fact-period-row">
        <div class="fact-date-group">
          <label class="fact-date-label">Desde</label>
          <input class="fact-date-input" id="remDesde" type="date" value="${fmtDate(primeroDeMes)}" />
        </div>
        <div class="fact-period-sep">→</div>
        <div class="fact-date-group">
          <label class="fact-date-label">Hasta</label>
          <input class="fact-date-input" id="remHasta" type="date" value="${fmtDate(hoy)}" />
        </div>
        <div class="fact-period-shortcuts">
          <button class="fact-shortcut-btn" data-range="mes">Mes actual</button>
          <button class="fact-shortcut-btn" data-range="mesant">Mes anterior</button>
          <button class="fact-shortcut-btn" data-range="trim">Trimestre</button>
          <button class="fact-shortcut-btn" data-range="anio">Año</button>
        </div>
      </div>
    </div>
    <div class="fact-section fact-download-section">
      <div class="fact-section-title">
        <span class="fact-section-icon">📄</span>
        Remitos a generar
        <span class="fact-hint-small" id="remHint">Seleccioná al menos un cliente</span>
      </div>
      <div class="fact-download-row">
        <div class="fact-summary-preview" id="remPreview">
          <span class="fact-preview-empty">— sin clientes seleccionados —</span>
        </div>
        <button class="fact-download-btn" id="remDownBtn" style="background:linear-gradient(135deg,#7C3AED,#5B21B6);box-shadow:0 6px 20px rgba(124,58,237,.3)" disabled>
          ⬇ Descargar remitos
        </button>
      </div>
    </div>`;

  menuGrid.appendChild(wrap);
  syncBackBtn();

  const grid      = wrap.querySelector('#remClientsGrid');
  const searchEl  = wrap.querySelector('#remSearch');
  const countEl   = wrap.querySelector('#remSelCount');
  const previewEl = wrap.querySelector('#remPreview');
  const dlBtn     = wrap.querySelector('#remDownBtn');
  const hintEl    = wrap.querySelector('#remHint');

  const updateUI = () => {
    const n = seleccionados.size;
    countEl.textContent = n === 0 ? '0 seleccionados' : n === 1 ? '1 seleccionado' : n + ' seleccionados';
    countEl.style.color = n > 0 ? '#A78BFA' : 'rgba(255,255,255,.4)';
    dlBtn.disabled = n === 0;
    hintEl.textContent = n === 0 ? 'Seleccioná al menos un cliente'
      : n + ' remito' + (n>1?'s':'') + ' · ' + wrap.querySelector('#remDesde').value + ' → ' + wrap.querySelector('#remHasta').value;
    hintEl.style.color = n > 0 ? '#A78BFA' : 'rgba(255,255,255,.35)';
    if (n === 0) {
      previewEl.innerHTML = '<span class="fact-preview-empty">— sin clientes seleccionados —</span>';
    } else {
      previewEl.innerHTML = [...seleccionados].map(i =>
        '<div class="fact-preview-chip" style="background:rgba(124,58,237,.15);border-color:rgba(124,58,237,.3)">'
        + '<span class="fact-preview-name">' + clientesOperaron[i] + '</span>'
        + '<span class="fact-preview-period">' + wrap.querySelector('#remDesde').value + ' → ' + wrap.querySelector('#remHasta').value + '</span>'
        + '</div>'
      ).join('');
    }
  };

  grid.addEventListener('click', e => {
    const chip = e.target.closest('.fact-client-chip');
    if (!chip) return;
    const idx = parseInt(chip.dataset.idx);
    if (seleccionados.has(idx)) { seleccionados.delete(idx); chip.classList.remove('selected'); }
    else { seleccionados.add(idx); chip.classList.add('selected'); }
    updateUI();
  });

  searchEl.addEventListener('input', () => {
    const q = searchEl.value.toLowerCase().trim();
    grid.querySelectorAll('.fact-client-chip').forEach(c => {
      c.style.display = (!q || c.dataset.name.includes(q)) ? '' : 'none';
    });
  });

  wrap.querySelector('#remSelAll').addEventListener('click', () => {
    grid.querySelectorAll('.fact-client-chip:not([style*="none"])').forEach(c => {
      seleccionados.add(parseInt(c.dataset.idx)); c.classList.add('selected');
    });
    updateUI();
  });
  wrap.querySelector('#remSelNone').addEventListener('click', () => {
    seleccionados.clear();
    grid.querySelectorAll('.fact-client-chip').forEach(c => c.classList.remove('selected'));
    updateUI();
  });

  wrap.querySelectorAll('.fact-shortcut-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      wrap.querySelectorAll('.fact-shortcut-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const now = new Date();
      const desde = wrap.querySelector('#remDesde');
      const hasta = wrap.querySelector('#remHasta');
      if (btn.dataset.range === 'mes') {
        desde.value = fmtDate(new Date(now.getFullYear(), now.getMonth(), 1)); hasta.value = fmtDate(now);
      } else if (btn.dataset.range === 'mesant') {
        desde.value = fmtDate(new Date(now.getFullYear(), now.getMonth()-1, 1));
        hasta.value = fmtDate(new Date(now.getFullYear(), now.getMonth(), 0));
      } else if (btn.dataset.range === 'trim') {
        desde.value = fmtDate(new Date(now.getFullYear(), now.getMonth()-2, 1)); hasta.value = fmtDate(now);
      } else {
        desde.value = fmtDate(new Date(now.getFullYear(), 0, 1)); hasta.value = fmtDate(now);
      }
      updateUI();
    });
  });

  wrap.querySelector('#remDesde').addEventListener('change', updateUI);
  wrap.querySelector('#remHasta').addEventListener('change', updateUI);

  dlBtn.addEventListener('click', () => {
    const plural = seleccionados.size > 1 ? 's' : '';
    showToast('Generando ' + seleccionados.size + ' remito' + plural + '...');
    setTimeout(() => openModule('https://sistema.cinafrio.com/intranet/index.php/facturacion/remitos'), 800);
  });

  updateUI();
}


/* ═══════════════════════════════════════════════════════════════
   MÓDULO: COMPRAS
   Datos persistidos en memoria durante la sesión
   ═══════════════════════════════════════════════════════════════ */

const comprasDB = {
  ordenes: [],
  nextNum: 1001,

  empresas: [
    'ARCOR S.A.', 'CINA S.R.L.', 'QUICKFOOD S.A.', 'MCCAIN ARGENTINA SA',
    'MINERVA FOODS', 'TERRAGENE S.A.', 'SAVAZ SRL', 'LA SIBILA SA',
    'HELACOR S.A.', 'RAFAELA ALIMENTOS S.A.', 'SUDAMERICANA DE LÁCTEOS S.A.',
    'LOGÍSTICA RR CONGELADOS SA', 'ULTRACONGELADOS ROSARIO', 'OTRA EMPRESA'
  ],
  destinos: [
    'Depósito Nacional', 'Depósito Fiscal', 'Sala de Máquinas',
    'Administración', 'RRHH', 'Mantenimiento', 'Portería', 'Logística'
  ],
  sectores: [
    'Logística', 'Administración', 'RRHH', 'Calidad', 'Mantenimiento',
    'Sala de Máquinas', 'Portería', 'Gerencia', 'Compras', 'IT'
  ],
  proveedores: [
    'Proveedor A - CUIT 20-12345678-9', 'Proveedor B - CUIT 30-87654321-0',
    'Distribuidora Norte SRL', 'Suministros del Sur S.A.',
    'Tech Solutions SA', 'Servicios Integrales SRL',
    'Materiales & Más SA', 'Importadora del Centro',
    'Logística Express SRL', 'Otro proveedor'
  ],

  ESTADOS: {
    BORRADOR:    { label: 'Borrador',          color: '#6B7280', icon: '📝' },
    PENDIENTE:   { label: 'Pendiente autorización', color: '#F97316', icon: '⏳' },
    EN_REVISION: { label: 'En revisión',        color: '#FACC15', icon: '🔄' },
    RECHAZADA:   { label: 'Rechazada',          color: '#DC2626', icon: '❌' },
    AUTORIZADA:  { label: 'Autorizada',         color: '#36B0C9', icon: '✅' },
    SIN_PROV:    { label: 'Esperando proveedor',color: '#A78BFA', icon: '🏭' },
    CONFIRMADA:  { label: 'Recepción confirmada', color: '#00A887', icon: '📦' },
    FACTURADA:   { label: 'Factura recibida',   color: '#4ade80', icon: '🧾' },
    ABONADA:     { label: 'Abonada',            color: '#86efac', icon: '💰' },
  }
};

function renderCompras() {
  setHeader('ADMINISTRACION');
  setExpandedMode(false);
  showMetaPanel(true);
  menuGrid.className = '';
  menuGrid.innerHTML = '';

  const wrap = document.createElement('div');
  wrap.className = 'fact-wrap';
  wrap.innerHTML = `
    <div class="fact-header">
      <div class="fact-header-title">
        <span class="indicador-badge" style="background:rgba(250,204,21,.15);color:#FACC15;border-color:rgba(250,204,21,.3)">OC</span>
        COMPRAS — ÓRDENES DE COMPRA
      </div>
      <span class="indicador-hint">Gestión completa del flujo de compras</span>
    </div>
    <div class="compras-actions-row">
      <button class="compras-main-btn compras-btn-nueva" id="btnNuevaOC">
        <span>＋</span> Emitir nueva orden de compra
      </button>
      <button class="compras-main-btn compras-btn-gestionar" id="btnGestionar">
        <span>📋</span> Gestionar órdenes
      </button>
    </div>`;

  menuGrid.appendChild(wrap);
  syncBackBtn();

  wrap.querySelector('#btnNuevaOC').addEventListener('click',  () => renderNuevaOC());
  wrap.querySelector('#btnGestionar').addEventListener('click', () => renderGestionOC());
}

/* ── NUEVA ORDEN DE COMPRA ────────────────────────────────────── */
function renderNuevaOC(editData) {
  setHeader('COMPRAS');
  menuGrid.className = '';
  menuGrid.innerHTML = '';

  const esEdicion   = !!editData;
  const numOrden    = esEdicion ? editData.numero : comprasDB.nextNum;
  let   renglones   = esEdicion ? JSON.parse(JSON.stringify(editData.items)) : [{ desc:'', cant:1, precio:0 }];
  let   tieneProveedor = esEdicion ? editData.tieneProveedor : false;

  const opt = arr => arr.map(v => '<option value="' + v + '">' + v + '</option>').join('');

  const wrap = document.createElement('div');
  wrap.className = 'fact-wrap';
  wrap.innerHTML = `
    <div class="fact-header">
      <div class="fact-header-title">
        <span class="indicador-badge" style="background:rgba(250,204,21,.15);color:#FACC15;border-color:rgba(250,204,21,.3)">OC</span>
        ${esEdicion ? 'EDITAR' : 'NUEVA'} ORDEN DE COMPRA
      </div>
      <button class="fact-sel-none-btn" id="ocBackBtn">← Volver</button>
    </div>

    <!-- Número + bloqueado -->
    <div class="fact-section">
      <div class="fact-section-title"><span class="fact-section-icon">🔢</span> Datos de la orden</div>
      <div class="oc-fields-grid">
        <div class="oc-field-group">
          <label class="fact-date-label">N° de orden</label>
          <input class="fact-date-input oc-input-locked" value="OC-${numOrden}" readonly />
        </div>
        <div class="oc-field-group">
          <label class="fact-date-label">Empresa</label>
          <select class="oc-select" id="ocEmpresa">
            <option value="">— Seleccionar —</option>${opt(comprasDB.empresas)}
          </select>
        </div>
        <div class="oc-field-group">
          <label class="fact-date-label">Destino</label>
          <select class="oc-select" id="ocDestino">
            <option value="">— Seleccionar —</option>${opt(comprasDB.destinos)}
          </select>
        </div>
        <div class="oc-field-group">
          <label class="fact-date-label">Centro de costo / Sector</label>
          <select class="oc-select" id="ocSector">
            <option value="">— Seleccionar —</option>${opt(comprasDB.sectores)}
          </select>
        </div>
      </div>
    </div>

    <!-- Proveedor -->
    <div class="fact-section">
      <div class="fact-section-title"><span class="fact-section-icon">🏭</span> Proveedor</div>
      <div class="oc-prov-row">
        <span class="fact-date-label" style="font-size:.8rem">¿Tengo proveedor definido?</span>
        <div class="oc-toggle-group">
          <button class="oc-toggle-btn${tieneProveedor ? '' : ' active'}" data-val="no" id="ocProvNo">NO</button>
          <button class="oc-toggle-btn${tieneProveedor ? ' active' : ''}" data-val="si" id="ocProvSi">SÍ</button>
        </div>
        <select class="oc-select oc-prov-select${tieneProveedor ? '' : ' hidden'}" id="ocProvSelect">
          <option value="">— Seleccionar proveedor —</option>${opt(comprasDB.proveedores)}
        </select>
      </div>
    </div>

    <!-- Detalle de compra -->
    <div class="fact-section">
      <div class="fact-section-title">
        <span class="fact-section-icon">📦</span> Detalle de la compra
        <button class="fact-sel-all-btn" id="ocAddRenglon" style="margin-left:auto">+ Agregar ítem</button>
      </div>
      <div class="oc-table-wrap">
        <table class="oc-table">
          <thead>
            <tr>
              <th class="oc-th-desc">Descripción</th>
              <th class="oc-th-num">Cantidad</th>
              <th class="oc-th-num">Precio unit. (sin IVA)</th>
              <th class="oc-th-num">Total</th>
              <th class="oc-th-del"></th>
            </tr>
          </thead>
          <tbody id="ocRenglones"></tbody>
          <tfoot>
            <tr class="oc-total-row">
              <td colspan="3" class="oc-total-label">TOTAL (sin IVA)</td>
              <td class="oc-total-val" id="ocTotal">$ 0,00</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- Comentarios -->
    <div class="fact-section">
      <div class="fact-section-title"><span class="fact-section-icon">💬</span> Comentarios</div>
      <textarea class="oc-textarea" id="ocComentarios" placeholder="Observaciones, referencias, condiciones especiales..." rows="3">${esEdicion ? (editData.comentarios||'') : ''}</textarea>
    </div>

    <!-- Botón solicitar -->
    <div class="fact-section" style="background:transparent;border:none;padding:4px 0">
      <button class="fact-download-btn oc-submit-btn" id="ocSubmitBtn">
        📨 Solicitar autorización
      </button>
    </div>`;

  menuGrid.appendChild(wrap);
  syncBackBtn();

  // Restaurar valores si es edición
  if (esEdicion) {
    wrap.querySelector('#ocEmpresa').value = editData.empresa || '';
    wrap.querySelector('#ocDestino').value = editData.destino || '';
    wrap.querySelector('#ocSector').value  = editData.sector  || '';
    if (editData.proveedor) wrap.querySelector('#ocProvSelect').value = editData.proveedor;
  }

  // ── Renglones ─────────────────────────────────────────────────
  const tbody   = wrap.querySelector('#ocRenglones');
  const totalEl = wrap.querySelector('#ocTotal');

  const fmtPeso = n => '$ ' + parseFloat(n||0).toLocaleString('es-AR', {minimumFractionDigits:2, maximumFractionDigits:2});

  const recalcTotal = () => {
    let sum = 0;
    tbody.querySelectorAll('tr').forEach(tr => {
      const c = parseFloat(tr.querySelector('.oc-cant')?.value) || 0;
      const p = parseFloat(tr.querySelector('.oc-precio')?.value) || 0;
      sum += c * p;
      const tEl = tr.querySelector('.oc-row-total');
      if (tEl) tEl.textContent = fmtPeso(c * p);
    });
    totalEl.textContent = fmtPeso(sum);
  };

  const addRenglon = (item) => {
    const tr = document.createElement('tr');
    tr.className = 'oc-row';
    tr.innerHTML = `
      <td><input class="oc-input oc-desc" type="text" placeholder="Descripción del ítem" value="${item ? item.desc : ''}" /></td>
      <td><input class="oc-input oc-cant" type="number" min="1" value="${item ? item.cant : 1}" /></td>
      <td><input class="oc-input oc-precio" type="number" min="0" step="0.01" placeholder="0,00" value="${item ? item.precio : ''}" /></td>
      <td class="oc-row-total">${fmtPeso(item ? item.cant * item.precio : 0)}</td>
      <td><button class="oc-del-btn" title="Eliminar">✕</button></td>`;
    tr.querySelector('.oc-cant').addEventListener('input', recalcTotal);
    tr.querySelector('.oc-precio').addEventListener('input', recalcTotal);
    tr.querySelector('.oc-del-btn').addEventListener('click', () => {
      if (tbody.children.length > 1) { tr.remove(); recalcTotal(); }
    });
    tbody.appendChild(tr);
    recalcTotal();
  };

  renglones.forEach(r => addRenglon(r));
  wrap.querySelector('#ocAddRenglon').addEventListener('click', () => addRenglon(null));

  // ── Toggle proveedor ──────────────────────────────────────────
  const provSelect = wrap.querySelector('#ocProvSelect');
  wrap.querySelector('#ocProvNo').addEventListener('click', () => {
    tieneProveedor = false;
    wrap.querySelector('#ocProvNo').classList.add('active');
    wrap.querySelector('#ocProvSi').classList.remove('active');
    provSelect.classList.add('hidden');
  });
  wrap.querySelector('#ocProvSi').addEventListener('click', () => {
    tieneProveedor = true;
    wrap.querySelector('#ocProvSi').classList.add('active');
    wrap.querySelector('#ocProvNo').classList.remove('active');
    provSelect.classList.remove('hidden');
  });

  // ── Volver ────────────────────────────────────────────────────
  wrap.querySelector('#ocBackBtn').addEventListener('click', () => renderCompras());

  // ── Submit ────────────────────────────────────────────────────
  wrap.querySelector('#ocSubmitBtn').addEventListener('click', () => {
    const empresa = wrap.querySelector('#ocEmpresa').value;
    const destino = wrap.querySelector('#ocDestino').value;
    const sector  = wrap.querySelector('#ocSector').value;
    if (!empresa || !destino || !sector) {
      showToast('⚠ Completá empresa, destino y sector'); return;
    }
    // Recolectar ítems
    const items = [];
    let totalVal = 0;
    tbody.querySelectorAll('tr').forEach(tr => {
      const desc  = tr.querySelector('.oc-desc')?.value.trim();
      const cant  = parseFloat(tr.querySelector('.oc-cant')?.value) || 0;
      const precio= parseFloat(tr.querySelector('.oc-precio')?.value) || 0;
      if (desc) { items.push({ desc, cant, precio }); totalVal += cant * precio; }
    });
    if (!items.length) { showToast('⚠ Agregá al menos un ítem'); return; }

    const proveedor = tieneProveedor ? wrap.querySelector('#ocProvSelect').value : '';
    const comentarios = wrap.querySelector('#ocComentarios').value.trim();
    const now = new Date().toLocaleDateString('es-AR');

    if (esEdicion) {
      // Update existing
      const ord = comprasDB.ordenes.find(o => o.numero === numOrden);
      if (ord) {
        Object.assign(ord, { empresa, destino, sector, tieneProveedor, proveedor, items, totalVal, comentarios,
          estado: 'PENDIENTE', historial: [...(ord.historial||[]), { accion:'Reenviado a autorización', fecha: now, user:'agustin.barovero' }]
        });
      }
    } else {
      comprasDB.ordenes.push({
        numero: numOrden, empresa, destino, sector, tieneProveedor, proveedor,
        items, totalVal, comentarios, estado: 'PENDIENTE',
        fechaCreacion: now,
        historial: [{ accion:'Orden creada y enviada a autorización', fecha: now, user:'agustin.barovero' }]
      });
      comprasDB.nextNum++;
    }

    showToast('✅ OC-' + numOrden + ' enviada a autorización');
    setTimeout(() => renderGestionOC(), 600);
  });
}

/* ── GESTIÓN DE ÓRDENES ──────────────────────────────────────── */
function renderGestionOC() {
  setHeader('COMPRAS');
  menuGrid.className = '';
  menuGrid.innerHTML = '';

  const estados = comprasDB.ESTADOS;

  const wrap = document.createElement('div');
  wrap.className = 'fact-wrap';

  const buildList = () => {
    const filas = comprasDB.ordenes.length === 0
      ? '<tr><td colspan="10" class="oc-empty">No hay órdenes de compra registradas</td></tr>'
      : comprasDB.ordenes.slice().reverse().map(o => {
          const est = estados[o.estado] || estados.BORRADOR;
          const tot = '$ ' + (o.totalVal||0).toLocaleString('es-AR', {minimumFractionDigits:2});
          return `<tr class="oc-list-row" data-num="${o.numero}">
            <td class="oc-td-num">OC-${o.numero}</td>
            <td class="oc-td-empresa">${o.empresa||'—'}</td>
            <td class="oc-td-dest">${o.destino||'—'}</td>
            <td class="oc-td-total">${tot}</td>
            <td class="oc-td-fecha">${o.fechaCreacion||'—'}</td>
            <td class="oc-td-nfact">${o.numFactura ? '<span class="oc-fact-num">' + o.numFactura + '</span>' : '<span class="oc-fact-empty">—</span>'}</td>
            <td class="oc-td-fecha">${o.fechaFactura ? o.fechaFactura.split('-').reverse().join('/') : '—'}</td>
            <td class="oc-td-venc${o.vencimientoPago && o.estado !== 'ABONADA' ? ' oc-td-venc-activo' : ''}">${o.vencimientoPago||'—'}</td>
            <td class="oc-td-estado">
              <span class="oc-estado-badge" style="color:${est.color};border-color:${est.color}40;background:${est.color}18">
                ${est.icon} ${est.label}
              </span>
            </td>
            <td class="oc-td-actions">
              <button class="oc-action-btn" data-action="ver" data-num="${o.numero}">Ver →</button>
            </td>
          </tr>`;
        }).join('');

    return `<table class="oc-table oc-list-table">
      <thead>
        <tr>
          <th>N° Orden</th><th>Empresa</th><th>Destino</th>
          <th>Total</th><th>Creación</th>
          <th>N° Factura</th><th>Fec. Factura</th><th>Vencimiento</th>
          <th>Estado</th><th></th>
        </tr>
      </thead>
      <tbody>${filas}</tbody>
    </table>`;
  };

  wrap.innerHTML = `
    <div class="fact-header">
      <div class="fact-header-title">
        <span class="indicador-badge" style="background:rgba(250,204,21,.15);color:#FACC15;border-color:rgba(250,204,21,.3)">OC</span>
        GESTIÓN DE ÓRDENES
      </div>
      <button class="compras-main-btn compras-btn-nueva" id="gcNewBtn" style="padding:8px 16px;font-size:.8rem">
        ＋ Nueva OC
      </button>
    </div>
    <div class="fact-section">
      <div class="fact-section-title"><span class="fact-section-icon">📋</span>
        Todas las órdenes
        <span style="margin-left:auto;color:rgba(255,255,255,.4);font-weight:700">${comprasDB.ordenes.length} órdenes</span>
      </div>
      <div class="oc-table-wrap" id="gcListWrap">${buildList()}</div>
    </div>`;

  menuGrid.appendChild(wrap);
  syncBackBtn();

  wrap.querySelector('#gcNewBtn').addEventListener('click', () => renderNuevaOC());

  wrap.addEventListener('click', e => {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const num = parseInt(btn.dataset.num);
    const ord = comprasDB.ordenes.find(o => o.numero === num);
    if (ord) renderDetalleOC(ord);
  });
}

/* ── DETALLE / FLUJO DE UNA ORDEN ────────────────────────────── */
function renderDetalleOC(ord) {
  setHeader('COMPRAS');
  menuGrid.className = '';
  menuGrid.innerHTML = '';

  const est    = comprasDB.ESTADOS[ord.estado] || comprasDB.ESTADOS.BORRADOR;
  const fmtP   = n => '$ ' + parseFloat(n||0).toLocaleString('es-AR', {minimumFractionDigits:2});
  const now    = () => new Date().toLocaleDateString('es-AR');

  const puedeAutorizar  = ['PENDIENTE','EN_REVISION'].includes(ord.estado);
  const puedeCargarProv = ord.estado === 'AUTORIZADA' && !ord.proveedor;
  const puedeFactura    = ord.estado === 'AUTORIZADA' && ord.proveedor;
  const puedeAbonar     = ord.estado === 'FACTURADA';

  const wrap = document.createElement('div');
  wrap.className = 'fact-wrap';

  wrap.innerHTML = `
    <div class="fact-header">
      <div class="fact-header-title">
        <span class="indicador-badge" style="background:rgba(250,204,21,.15);color:#FACC15;border-color:rgba(250,204,21,.3)">OC</span>
        OC-${ord.numero} — ${ord.empresa||'Sin empresa'}
        <span class="oc-estado-badge" style="color:${est.color};border-color:${est.color}40;background:${est.color}18;margin-left:8px">
          ${est.icon} ${est.label}
        </span>
      </div>
      <button class="fact-sel-none-btn" id="detBackBtn">← Volver</button>
    </div>

    <!-- Resumen -->
    <div class="fact-section">
      <div class="fact-section-title"><span class="fact-section-icon">📄</span> Resumen de la orden</div>
      <div class="oc-fields-grid">
        <div class="oc-field-group"><label class="fact-date-label">Empresa</label><div class="oc-val">${ord.empresa||'—'}</div></div>
        <div class="oc-field-group"><label class="fact-date-label">Destino</label><div class="oc-val">${ord.destino||'—'}</div></div>
        <div class="oc-field-group"><label class="fact-date-label">Sector / CC</label><div class="oc-val">${ord.sector||'—'}</div></div>
        <div class="oc-field-group"><label class="fact-date-label">Proveedor</label><div class="oc-val">${ord.proveedor||'<span style="color:rgba(255,255,255,.35)">Sin proveedor</span>'}</div></div>
      </div>
      <table class="oc-table" style="margin-top:10px">
        <thead><tr><th class="oc-th-desc">Ítem</th><th class="oc-th-num">Cant.</th><th class="oc-th-num">P. Unit.</th><th class="oc-th-num">Total</th></tr></thead>
        <tbody>
          ${(ord.items||[]).map(i => `<tr class="oc-row">
            <td>${i.desc}</td><td>${i.cant}</td><td>${fmtP(i.precio)}</td><td>${fmtP(i.cant*i.precio)}</td>
          </tr>`).join('')}
        </tbody>
        <tfoot>
          <tr class="oc-total-row"><td colspan="3" class="oc-total-label">TOTAL (sin IVA)</td>
          <td class="oc-total-val">${fmtP(ord.totalVal)}</td></tr>
        </tfoot>
      </table>
      ${ord.comentarios ? '<div class="oc-comentario-box">💬 ' + ord.comentarios + '</div>' : ''}
    </div>

    <!-- Acciones según estado -->
    <div class="fact-section" id="detAcciones">
      <div class="fact-section-title"><span class="fact-section-icon">⚙</span> Acciones</div>

      ${puedeAutorizar ? `
        <div class="oc-accion-bloque">
          <textarea class="oc-textarea" id="detObs" placeholder="Observaciones del autorizador..." rows="2"></textarea>
          <div class="oc-accion-btns">
            <button class="fact-download-btn oc-btn-aprobar" data-act="aprobar">✅ Aprobar</button>
            <button class="fact-download-btn oc-btn-revision" data-act="revision">🔄 Pedir revisión</button>
            <button class="fact-download-btn oc-btn-rechazar" data-act="rechazar">❌ Rechazar</button>
          </div>
        </div>` : ''}

      ${puedeCargarProv ? `
        <div class="oc-accion-bloque">
          <div class="oc-comentario-box" style="margin-bottom:8px">
            🏭 Orden autorizada sin proveedor. Asigná uno para poder registrar la factura.
          </div>
          <label class="fact-date-label">Proveedor</label>
          <select class="oc-select" id="detProvSelect" style="margin-top:6px">
            <option value="">— Seleccionar proveedor —</option>
            ${comprasDB.proveedores.map(p => '<option value="' + p + '"' + (ord.proveedor===p?' selected':'') + '>' + p + '</option>').join('')}
          </select>
          <div class="oc-accion-btns" style="margin-top:10px">
            <button class="fact-download-btn oc-btn-aprobar" data-act="guardarProv">💾 Guardar proveedor</button>
          </div>
        </div>` : ''}


      ${puedeFactura ? `
        <div class="oc-accion-bloque">
          <div class="oc-comentario-box" style="margin-bottom:10px">
            ✅ Orden autorizada &nbsp;·&nbsp; Proveedor: ${ord.proveedor}
          </div>
          <div class="oc-fields-grid oc-fields-3col">
            <div class="oc-field-group">
              <label class="fact-date-label">N° de factura</label>
              <input class="oc-input" id="detNumFact" type="text" placeholder="ej. 0001-00012345" style="padding:9px 12px;font-size:.88rem;font-weight:800" />
            </div>
            <div class="oc-field-group">
              <label class="fact-date-label">Fecha de la factura</label>
              <input class="fact-date-input" id="detFechaFact" type="date" value="${new Date().toISOString().split('T')[0]}" />
            </div>
            <div class="oc-field-group">
              <label class="fact-date-label">Plazo de pago</label>
              <select class="oc-select" id="detPlazo">
                <option value="0">Contado</option>
                <option value="15">15 días</option>
                <option value="30">30 días</option>
                <option value="45">45 días</option>
                <option value="60">60 días</option>
                <option value="90">90 días</option>
              </select>
            </div>
          </div>
          <div class="oc-accion-btns" style="margin-top:10px">
            <button class="fact-download-btn" style="background:linear-gradient(135deg,#1A4E8A,#0E3060)" data-act="imprimir">🖨 Imprimir OC</button>
            <button class="fact-download-btn oc-btn-aprobar" data-act="recibirFactura">🧾 Registrar factura recibida</button>
          </div>
        </div>` : ''}

      ${puedeAbonar ? `
        <div class="oc-accion-bloque">
          <div class="oc-comentario-box">
            📅 Vencimiento: ${ord.vencimientoPago||'—'} &nbsp;|&nbsp; Plazo: ${ord.plazo||'—'}
          </div>
          <div class="oc-accion-btns" style="margin-top:10px">
            <button class="fact-download-btn oc-btn-aprobar" data-act="abonar">💰 Marcar como abonada</button>
          </div>
        </div>` : ''}

      ${['RECHAZADA','ABONADA'].includes(ord.estado) ? `
        <div class="oc-comentario-box" style="color:rgba(255,255,255,.55)">
          ${ord.estado === 'ABONADA' ? '💰 Orden abonada el ' + (ord.fechaAbono||'—') : '❌ Orden rechazada'}
        </div>` : ''}

      ${['PENDIENTE','EN_REVISION'].includes(ord.estado) ? `
        <div style="margin-top:8px">
          <button class="fact-sel-all-btn" data-act="editar">✏ Editar orden</button>
        </div>` : ''}
    </div>

    <!-- Historial -->
    <div class="fact-section">
      <div class="fact-section-title"><span class="fact-section-icon">📜</span> Historial</div>
      <div class="oc-historial" id="detHistorial">
        ${(ord.historial||[]).slice().reverse().map(h => `
          <div class="oc-hist-row">
            <span class="oc-hist-fecha">${h.fecha}</span>
            <span class="oc-hist-accion">${h.accion}</span>
            <span class="oc-hist-user">${h.user||''}</span>
            ${h.obs ? '<span class="oc-hist-obs">' + h.obs + '</span>' : ''}
          </div>`).join('')}
      </div>
    </div>`;

  menuGrid.appendChild(wrap);
  syncBackBtn();

  wrap.querySelector('#detBackBtn').addEventListener('click', () => renderGestionOC());

  wrap.addEventListener('click', e => {
    const btn = e.target.closest('[data-act]');
    if (!btn) return;
    const act = btn.dataset.act;
    const obs = wrap.querySelector('#detObs')?.value.trim();
    const n   = now();

    if (act === 'aprobar') {
      ord.estado = 'AUTORIZADA';
      ord.historial.push({ accion:'Aprobada — en espera de factura', fecha:n, user:'autorizador', obs });
      showToast('✅ Orden autorizada');
      setTimeout(() => renderDetalleOC(ord), 400);
    } else if (act === 'revision') {
      ord.estado = 'EN_REVISION';
      ord.historial.push({ accion:'Enviada a revisión', fecha:n, user:'autorizador', obs });
      showToast('🔄 Enviada a revisión');
      setTimeout(() => renderDetalleOC(ord), 400);
    } else if (act === 'rechazar') {
      ord.estado = 'RECHAZADA';
      ord.historial.push({ accion:'Rechazada', fecha:n, user:'autorizador', obs });
      showToast('❌ Orden rechazada');
      setTimeout(() => renderDetalleOC(ord), 400);
    } else if (act === 'editar') {
      renderNuevaOC(ord);
    } else if (act === 'guardarProv') {
      const sel = wrap.querySelector('#detProvSelect')?.value;
      if (!sel) { showToast('⚠ Seleccioná un proveedor'); return; }
      ord.proveedor = sel; ord.tieneProveedor = true;
      ord.historial.push({ accion:'Proveedor asignado: ' + sel, fecha:n, user:'agustin.barovero' });
      showToast('🏭 Proveedor guardado — ya podés registrar la factura');
      setTimeout(() => renderDetalleOC(ord), 400);
    } else if (act === 'imprimir') {
      showToast('🖨 Abriendo impresión...');
      setTimeout(() => window.print(), 600);
    } else if (act === 'confirmarRecep') {
      ord.estado = 'CONFIRMADA';
      ord.historial.push({ accion:'Recepción confirmada', fecha:n, user:'agustin.barovero' });
      showToast('📦 Recepción confirmada');
      setTimeout(() => renderDetalleOC(ord), 400);
    } else if (act === 'recibirFactura') {
      const nf   = wrap.querySelector('#detNumFact')?.value.trim();
      const ff   = wrap.querySelector('#detFechaFact')?.value;
      const pl   = wrap.querySelector('#detPlazo')?.value;
      if (!nf) { showToast('⚠ Ingresá el N° de factura'); return; }
      if (!ff) { showToast('⚠ Indicá la fecha de la factura'); return; }
      ord.estado = 'FACTURADA';
      ord.numFactura = nf; ord.fechaFactura = ff; ord.plazo = pl;
      const dias = parseInt(pl)||0;
      const venc = new Date(ff);
      venc.setDate(venc.getDate() + dias);
      ord.vencimientoPago = dias === 0 ? 'Contado' : venc.toLocaleDateString('es-AR');
      ord.historial.push({ accion:'Factura N° ' + nf + ' registrada — vence ' + ord.vencimientoPago, fecha:n, user:'agustin.barovero' });
      showToast('🧾 Factura ' + nf + ' registrada');
      setTimeout(() => renderDetalleOC(ord), 400);
    } else if (act === 'abonar') {
      ord.estado = 'ABONADA';
      ord.fechaAbono = n;
      ord.historial.push({ accion:'Marcada como abonada', fecha:n, user:'agustin.barovero' });
      showToast('💰 Orden abonada');
      setTimeout(() => renderDetalleOC(ord), 400);
    }
  });
}

/* ═══════════════════════════════════════════════════════════════
   MÓDULO: CONTROL DE CALIDAD — CERTIFICADO DE TEMPERATURA
   Basado en formulario físico SENASA / CINA
   ═══════════════════════════════════════════════════════════════ */
function renderControlCertificado() {
  setHeader('CONTROL CERTIFICADO');
  setExpandedMode(true);
  showMetaPanel(false);
  menuGrid.className = '';

  const checkItems = [
    { id: 'condTransporte',   label: 'Cond. de Transporte',               opA: 'Aceptable',         opB: 'Inaceptable'      },
    { id: 'examOrgano',       label: 'Exámen Organoléptico',              opA: 'Aceptable',         opB: 'Inaceptable'      },
    { id: 'estadoEnvases',    label: 'Estado de los Envases',             opA: 'Aceptable',         opB: 'Inaceptable'      },
    { id: 'estadoTarimas',    label: 'Estado de las Tarimas',             opA: 'Aceptable',         opB: 'Inaceptable'      },
    { id: 'estadoSanitario',  label: 'Estado Sanitario del Transporte',   opA: 'Aceptable',         opB: 'Inaceptable'      },
    { id: 'estadoParedes',    label: 'Estado de las Paredes, Piso y Techo', opA: 'Aceptable',       opB: 'Inaceptable'      },
    { id: 'equipoFrio',       label: 'Funcionamiento del Equipo de Frío', opA: 'En funcionamiento', opB: 'Fuera de servicio' },
    { id: 'setPoint',         label: 'Set Point Equipo de Frío vs. Carga',opA: 'Correcto',          opB: 'Incorrecto'       },
  ];

  menuGrid.innerHTML = `
    <div class="cert-wrap">
      <div class="cert-header">
        <div class="cert-header-title">
          <span class="indicador-badge" style="background:rgba(0,168,135,.2);color:#00A887;border-color:rgba(0,168,135,.4)">QR</span>
          CONTROL DE CERTIFICADO — ESTIBA 65984-3
        </div>
        <span class="indicador-hint">Completá los datos del certificado de temperatura al momento de la recepción</span>
      </div>

      <!-- Temperatura certificado + Termómetro -->
      <div class="cert-section">
        <div class="cert-section-title">
          <span class="cert-section-icon">🌡</span> Temperatura y termómetro
        </div>
        <div class="cert-top-grid">
          <div class="cert-field-group">
            <label class="cert-label">Temperatura indicada en el certificado (°C)</label>
            <div class="cert-temp-input-wrap">
              <input class="cert-temp-input" id="certTemp" type="number" step="0.1" placeholder="ej. −18.5" />
              <span class="cert-temp-unit">°C</span>
            </div>
          </div>
          <div class="cert-field-group">
            <label class="cert-label">Termómetro N°</label>
            <input class="cert-text-input" id="certTermometro" type="text" placeholder="N° de termómetro" />
          </div>
        </div>
      </div>

      <!-- Checklist -->
      <div class="cert-section">
        <div class="cert-section-title">
          <span class="cert-section-icon">✅</span> Checklist de recepción
          <span class="cert-check-count" id="certCheckCount">0 / ${checkItems.length} completados</span>
        </div>
        <div class="cert-checklist" id="certChecklist">
          ${checkItems.map((item, idx) => `
            <div class="cert-check-row" id="row_${item.id}">
              <div class="cert-check-label">${idx + 1}. ${item.label}</div>
              <div class="cert-check-options">
                <button class="cert-opt-btn cert-opt-a" data-id="${item.id}" data-val="A">
                  <span class="cert-opt-icon">○</span> ${item.opA}
                </button>
                <button class="cert-opt-btn cert-opt-b" data-id="${item.id}" data-val="B">
                  <span class="cert-opt-icon">○</span> ${item.opB}
                </button>
              </div>
            </div>`).join('')}
        </div>
      </div>

      <!-- Observaciones -->
      <div class="cert-section">
        <div class="cert-section-title"><span class="cert-section-icon">💬</span> Observaciones</div>
        <textarea class="oc-textarea" id="certObs" placeholder="Observaciones sobre el estado de la mercadería..." rows="3"></textarea>
      </div>

      <!-- Guardar -->
      <div class="cert-section" style="background:transparent;border:none;padding:4px 0">
        <button class="fact-download-btn cert-save-btn" id="certSaveBtn" disabled>
          💾 Guardar control de certificado
        </button>
      </div>
    </div>`;

  syncBackBtn();

  const selections = {};
  const total      = checkItems.length;
  const countEl    = document.getElementById('certCheckCount');
  const saveBtn    = document.getElementById('certSaveBtn');

  const updateCount = () => {
    const done = Object.keys(selections).length;
    countEl.textContent = done + ' / ' + total + ' completados';
    countEl.style.color = done === total ? '#00A887' : 'rgba(255,255,255,.4)';
    saveBtn.disabled = done < total;
  };

  document.getElementById('certChecklist').addEventListener('click', e => {
    const btn = e.target.closest('.cert-opt-btn');
    if (!btn) return;
    const id  = btn.dataset.id;
    const val = btn.dataset.val;
    selections[id] = val;

    // Toggle visual en la fila
    const row = document.getElementById('row_' + id);
    row.querySelectorAll('.cert-opt-btn').forEach(b => {
      b.classList.remove('selected-a', 'selected-b');
      b.querySelector('.cert-opt-icon').textContent = '○';
    });
    btn.classList.add(val === 'A' ? 'selected-a' : 'selected-b');
    btn.querySelector('.cert-opt-icon').textContent = '●';
    row.classList.add('row-done');

    updateCount();
  });

  saveBtn.addEventListener('click', () => {
    const temp    = document.getElementById('certTemp').value;
    const termom  = document.getElementById('certTermometro').value;
    if (!temp) { showToast('⚠ Ingresá la temperatura del certificado'); return; }

    // Verificar si hay inaceptables
    const inaceptables = checkItems.filter(item => selections[item.id] === 'B').map(i => i.label);
    if (inaceptables.length > 0) {
      showToast('⚠ Hay ' + inaceptables.length + ' ítem(s) inaceptable(s)');
    } else {
      showToast('✅ Control guardado — todos los ítems aceptables');
    }

    setTimeout(() => {
      historyStack.length = 0;
      renderContenedor6Detail();
    }, 800);
  });

  updateCount();
}
