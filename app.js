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
        { title: 'FACTURACION', url: 'https://sistema.cinafrio.com/intranet/index.php/facturacion' },
        { title: 'REMITOS',     url: 'https://sistema.cinafrio.com/intranet/index.php/facturacion/remitos' },
        { title: 'COMPRAS',     url: 'https://sistema.cinafrio.com/intranet/index.php/pim/index/estadoPIM/1' }
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
        { title: 'MOVIMIENTOS' },
        { title: 'ESTADISTICAS DE PERSONAL' },
        { title: 'ALMACENAMIENTO DE POSICIONES' },
        { title: 'ESTIBAS CREADAS ELIMINADAS' },
        { title: 'AJUSTES DE STOCK', url: 'https://sistema.cinafrio.com/intranet/index.php/infostock/ajusteManual' },
        { title: 'CLIENTES QUE OPERARON' }
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
  { tipo:'Zorra electrica',    icono:'img/apilador.png',denominacion:'ZE-01', estado:'En marcha', planta:'Nacional',       horas:'00:32hs', usuario:'jmartin' },
  { tipo:'Zorra electrica',    icono:'img/apilador.png',denominacion:'ZE-02', estado:'Cargando',  planta:'Nacional',       horas:'01:05hs', usuario:'rsosa' },
  { tipo:'Zorra electrica',    icono:'img/apilador.png',denominacion:'ZE-03', estado:'En marcha', planta:'Deposito Fiscal',horas:'00:11hs', usuario:'pdominguez' },
  { tipo:'Zorra electrica',    icono:'img/apilador.png',denominacion:'ZE-04', estado:'Falla',     planta:'Mantenimiento',  horas:'05:50hs', usuario:'cmedina' },
  { tipo:'Zorra electrica',    icono:'img/apilador.png',denominacion:'ZE-05', estado:'En marcha', planta:'Otros',          horas:'00:48hs', usuario:'sgimenez' },
  { tipo:'Autoelevador diesel', icono:'img/autoelevadores.png',denominacion:'AD-01', estado:'En marcha', planta:'Deposito Fiscal',horas:'02:30hs', usuario:'rbenitez' },
  { tipo:'Limpiador',           icono:'img/robotlimpieza.png',denominacion:'RL-01', estado:'Cargando',  planta:'Mantenimiento',  horas:'00:25hs', usuario:'lortiz' },
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
  return count > 8 ? 'layout-8x2' : 'layout-4x4';
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
        <p class="intervencion-subtitle">Adjunte imágenes o evidencias de la estiba.</p>
      </section>
      <section class="intervencion-card">
        <div class="intervencion-field">
          <label class="intervencion-label">Seleccionar archivos</label>
          <input type="file" multiple accept="image/*" class="intervencion-select">
        </div>
      </section>
      <section class="intervencion-card">
        <div class="intervencion-field">
          <label class="intervencion-label">Observaciones</label>
          <textarea class="intervencion-textarea" placeholder="Escriba aquí un detalle de las fotos cargadas..."></textarea>
        </div>
      </section>
      <section class="intervencion-card">
        <div class="intervencion-actions">
          <button id="btnGuardarFotos" class="intervencion-submit">Guardar fotos</button>
        </div>
      </section>
    </div>`;

  document.getElementById('btnGuardarFotos').addEventListener('click', () => {
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
  menuGrid.className = `menu-grid ${getGridLayout(items.length || 1)}`;
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
      tile.innerHTML = `
        <div class="tile-icon">${getIconMarkup(item.title)}</div>
        <div class="tile-title ${titleClass(item.title)}">${formatTitle(item.title)}</div>`;

      tile.addEventListener('click', () => {
        if (item.title === 'MAPA DE BOXES')                  { historyStack.push(node); renderMapaBoxes();                    return; }
        if (item.title === 'DASHBOARD LOGISTICA NACIONAL')   { historyStack.push(node); renderDashboardLogisticaNacional();   return; }
        if (item.title === 'DASHBOARD EQUIPAMIENTO')         { historyStack.push(node); renderDashboardEquipamiento();        return; }
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
