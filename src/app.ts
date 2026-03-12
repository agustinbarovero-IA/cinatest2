import './index.css';

const menuTree: any = {
  title: 'INICIO',
  children: [
    { title: 'LOGISTICA NACIONAL', children: [
      { title: 'CARGAS', url: 'https://sistema.cinafrio.com/intranet/index.php/transporte_carga/index/fiscal/0' },
      { title: 'PRE ENTRADAS', url: 'https://sistema.cinafrio.com/intranet/index.php/entradacarga/list/clasificada/0/fiscal/0' },
      { title: 'ENTRADAS', url: 'https://sistema.cinafrio.com/intranet/index.php/entradacarga/list/clasificada/1/fiscal/0' },
      { title: 'PRE SALIDAS', url: 'https://sistema.cinafrio.com/intranet/index.php/presalidas/list/salida/0/fiscal/0' },
      { title: 'SALIDAS', url: 'https://sistema.cinafrio.com/intranet/index.php/presalidas/list/salida/1/fiscal/0' }
    ]},
    { title: 'LOGISTICA FISCAL', children: [
      { title: 'CARGAS', url: 'https://sistema.cinafrio.com/intranet/index.php/transporte_carga/index/fiscal/1' },
      { title: 'PRE ENTRADAS', url: 'https://sistema.cinafrio.com/intranet/index.php/entradacarga/list/clasificada/0/fiscal/1' },
      { title: 'ENTRADAS', url: 'https://sistema.cinafrio.com/intranet/index.php/entradacarga/list/clasificada/1/fiscal/1' },
      { title: 'PRE SALIDAS', url: 'https://sistema.cinafrio.com/intranet/index.php/presalidas/list/salida/0/fiscal/1' },
      { title: 'SALIDAS', url: 'https://sistema.cinafrio.com/intranet/index.php/presalidas/list/salida/1/fiscal/1' },
      { title: 'REPORTE DEPOSITO FISCAL AFIP', url: 'https://sistema.cinafrio.com/intranet2/app.php/reportestockafip/' }
    ]},
    { title: 'CALIDAD', children: [
      { title: 'CONTROL DE ENTRADAS', url: 'https://sistema.cinafrio.com/intranet/index.php/carga/list/type/E' },
      { title: 'CONTROL DE SALIDAS', url: 'https://sistema.cinafrio.com/intranet/index.php/carga/list/type/S' },
      { title: 'CONTROL DE INTERVENCIONES', url: 'https://sistema.cinafrio.com/intranet/index.php/carga/listIntervenciones' },
      { title: 'VENCIMIENTO DE PRODUCTOS', url: 'https://sistema.cinafrio.com/intranet/index.php/infostock/vencimientos' },
      { title: 'ESTADISTICAS DE INTERVENCIONES', url: 'https://sistema.cinafrio.com/intranet/index.php/carga/estadisticas' }
    ]},
    { title: 'PORTERIA', children: [
      { title: 'INGRESOS EGRESOS', url: 'https://sistema.cinafrio.com/intranet/index.php/ingreso/index' }
    ]},
    { title: 'TRANSPORTE', children: [
      { title: 'DATOS DE VEHICULOS', children: [
        { title: 'ASEGURADORAS', url: 'https://sistema.cinafrio.com/intranet/index.php/transporte_aseguradora' },
        { title: 'EMPRESAS', url: 'https://sistema.cinafrio.com/intranet/index.php/transporte_empresa' },
        { title: 'CONDUCTORES', url: 'https://sistema.cinafrio.com/intranet/index.php/transporte_conductor' },
        { title: 'VEHICULOS', url: 'https://sistema.cinafrio.com/intranet/index.php/transporte_vehiculo' },
        { title: 'ACOPLADOS', url: 'https://sistema.cinafrio.com/intranet/index.php/transporte_acoplado' }
      ]},
      { title: 'DATOS DE LA PLAYA DE MANIOBRA', children: [
        { title: 'BOXES', url: 'https://sistema.cinafrio.com/intranet/index.php/box' },
        { title: 'TRONERAS', url: 'https://sistema.cinafrio.com/intranet/index.php/tronera' }
      ]}
    ]},
    { title: 'ADMINISTRACION', children: [
      { title: 'FACTURACION', url: 'https://sistema.cinafrio.com/intranet/index.php/facturacion' },
      { title: 'REMITOS', url: 'https://sistema.cinafrio.com/intranet/index.php/facturacion/remitos' },
      { title: 'COMPRAS', url: 'https://sistema.cinafrio.com/intranet/index.php/pim/index/estadoPIM/1' }
    ]},
    { title: 'RRHH', children: [
      { title: 'PERSONAL', url: 'https://sistema.cinafrio.com/intranet2/app.php/persona/' },
      { title: 'AUSENTISMO', url: 'https://sistema.cinafrio.com/intranet2/app.php/ausentismo/dashboard' },
      { title: 'VACUNACIONES', url: 'https://sistema.cinafrio.com/intranet2/app.php/vacunacion/' },
      { title: 'EXAMENES ART', url: 'https://sistema.cinafrio.com/intranet2/app.php/examenmedicoart/' },
      { title: 'EXAMENES INGRESOS', url: 'https://sistema.cinafrio.com/intranet2/app.php/exameningreso/' },
      { title: 'LIBRETAS SANITARIAS', url: 'https://sistema.cinafrio.com/intranet2/app.php/libretasanitaria/' }
    ]},
    { title: 'MANTENIMIENTO', children: [
      { title: 'MANTENIMIENTO GENERAL', url: 'https://sistema.cinafrio.com/intranet2/app.php/mantenimiento/' },
      { title: 'PR 6 CONTRASTE DE TERMOMETROS', url: 'https://sistema.cinafrio.com/intranet2/app.php/sdmregcontraste/' },
      { title: 'PR 9 SEMANAL', url: 'https://sistema.cinafrio.com/intranet2/app.php/sdmegcsemanal/' },
      { title: 'PR 22 LIMPIEZA TANQUES', url: 'https://sistema.cinafrio.com/intranet2/app.php/sdmreglimpiezatanque/' },
      { title: 'PR 24 DIARIO', url: 'https://sistema.cinafrio.com/intranet2/app.php/sdmegcdiario/' },
      { title: 'PR 30 LIBRO DE GUARDIA', url: 'https://sistema.cinafrio.com/intranet2/app.php/sdmcontrol/' },
      { title: 'PR 80 PARADA DE CAMARAS', url: 'https://sistema.cinafrio.com/intranet2/app.php/sdmcamara/' },
      { title: 'PR 100 DESCONGELADO', url: 'https://sistema.cinafrio.com/intranet2/app.php/sdmdescongelado/' }
    ]},
    { title: 'PRODUCTOS', children: [
      { title: 'CREAR NUEVOS PRODUCTOS', url: 'https://sistema.cinafrio.com/intranet/index.php/producto' }
    ]},
    { title: 'DASHBOARD', children: [
      { title: 'MAPA DE BOXES' },
      { title: 'DASHBOARD LOGISTICA NACIONAL' },
      { title: 'DASHBOARD LOGISTICA FISCAL' },
      { title: 'DASHBOARD EQUIPAMIENTO' },
      { title: 'DASHBOARD GENERAL', url: 'https://sistema.cinafrio.com/intranet2/app.php/ausentismo/dashboard' }
    ]},
    { title: 'REPORTES', children: [
      { title: 'CAMARAS' },
      { title: 'STOCK', url: 'https://sistema.cinafrio.com/intranet/index.php/infostock/stockDetallado' },
      { title: 'MOVIMIENTOS' },
      { title: 'TIEMPOS CARGA DESCARGA', url: 'https://sistema.cinafrio.com/intranet/index.php/estiba/informes' },
      { title: 'AUSENTISMO' },
      { title: 'DEVOLUCIONES', url: 'https://sistema.cinafrio.com/intranet/index.php/infostock/devoluciones' }
    ]},
    { title: 'INDICADORES', children: [
      { title: 'MOVIMIENTOS' },
      { title: 'ESTADISTICAS DE PERSONAL' },
      { title: 'ALMACENAMIENTO DE POSICIONES' },
      { title: 'ESTIBAS CREADAS ELIMINADAS' },
      { title: 'AJUSTES DE STOCK', url: 'https://sistema.cinafrio.com/intranet/index.php/infostock/ajusteManual' },
      { title: 'CLIENTES QUE OPERARON' }
    ]},
    { title: 'SISTEMA DE GESTION', children: [
      { title: 'INFORMACION DOCUMENTADA', url: 'https://manualcinafrio.com.ar/documentos/' },
      { title: 'INDICADORES', url: 'https://manualcinafrio.com.ar/indicadores/' },
      { title: 'REGISTRO PR 44', url: 'https://sistema.cinafrio.com/intranet/index.php/registro' },
      { title: 'FORMACION', url: 'https://manualcinafrio.com.ar/formacion/' }
    ]},
    { title: 'SENASA', children: [
      { title: 'CERTIFICADOS', url: 'https://sistema.cinafrio.com/intranet/index.php/certificados/list' },
      { title: 'CERTIFICADOS INTERNOS', url: 'https://sistema.cinafrio.com/intranet/index.php/certificado_interno' }
    ]},
    { title: 'CONFIGURACION', children: [
      { title: 'VETERINARIOS', url: 'https://sistema.cinafrio.com/intranet/index.php/veterinario/index' },
      { title: 'CLIENTES', children: [
        { title: 'CLIENTES DEPOSITO NACIONAL', url: 'https://sistema.cinafrio.com/intranet/index.php/clientes/list?fiscal=0&page=1' },
        { title: 'CLIENTES DEPOSITO FISCAL', url: 'https://sistema.cinafrio.com/intranet/index.php/clientes/list/fiscal/1/page/1' },
        { title: 'GRUPO DE CLIENTES', url: 'https://sistema.cinafrio.com/intranet/index.php/grupo/list' }
      ]},
      { title: 'COMPRAS' },
      { title: 'ESTABLECIMIENTOS' },
      { title: 'LOGISTICA', children: [
        { title: 'CAMARAS', url: 'https://sistema.cinafrio.com/intranet/index.php/camaras' },
        { title: 'CONDUCTORES', url: 'https://sistema.cinafrio.com/intranet/index.php/conductor_autoelevador' },
        { title: 'RESPONSABLE DE CARGAS', url: 'https://sistema.cinafrio.com/intranet/index.php/responsable_logistica' },
        { title: 'TIPO DE CAMARAS', url: 'https://sistema.cinafrio.com/intranet/index.php/tiposcamara' },
        { title: 'TIPO DE ESTIBA', url: 'https://sistema.cinafrio.com/intranet/index.php/tiposestiba' },
        { title: 'TIPO DE CERTIFICADOS', url: 'https://sistema.cinafrio.com/intranet/index.php/tiposcertificados' },
        { title: 'TIPO DE INTERVENCIONES', url: 'https://sistema.cinafrio.com/intranet/index.php/intervenciones' },
        { title: 'TIPO DE PRODUCTOS', url: 'https://sistema.cinafrio.com/intranet/index.php/tiposproducto' }
      ]},
      { title: 'RRHH', children: [
        { title: 'DEPARTAMENTOS', url: 'https://sistema.cinafrio.com/intranet/index.php/departamento' },
        { title: 'EXAMENES', url: 'https://sistema.cinafrio.com/intranet2/app.php/examen/' },
        { title: 'LOCALIDADES', url: 'https://sistema.cinafrio.com/intranet/index.php/localidad/index' },
        { title: 'OBRA SOCIAL', url: 'https://sistema.cinafrio.com/intranet/index.php/obra_social' },
        { title: 'PAISES', url: 'https://sistema.cinafrio.com/intranet/index.php/pais' },
        { title: 'VACUNAS', url: 'https://sistema.cinafrio.com/intranet2/app.php/vacuna/' }
      ]},
      { title: 'SALA DE MAQUINAS', url: 'https://sistema.cinafrio.com/intranet2/app.php/sdmmaquinista/' },
      { title: 'SISTEMA INFORMATICO', children: [
        { title: 'RESPONSABLES', url: 'https://sistema.cinafrio.com/intranet2/app.php/controlcambioresponsables/' },
        { title: 'USUARIOS', url: 'https://sistema.cinafrio.com/intranet/index.php/usuarios/index' },
        { title: 'PERFILES', url: 'https://sistema.cinafrio.com/intranet/index.php/rol' }
      ]},
      { title: 'TRANSPORTE', children: [
        { title: 'AEROPUERTOS', url: 'https://sistema.cinafrio.com/intranet/index.php/transporte_aeropuerto' },
        { title: 'BUQUES', url: 'https://sistema.cinafrio.com/intranet/index.php/transporte_buque' },
        { title: 'DESTINOS', url: 'https://sistema.cinafrio.com/intranet/index.php/destinos/index/page/1' },
        { title: 'LINEAS AEREAS', url: 'https://sistema.cinafrio.com/intranet/index.php/transporte_linea_aerea' },
        { title: 'PUERTOS', url: 'https://sistema.cinafrio.com/intranet/index.php/transporte_puerto' }
      ]}
    ]},
    { title: 'OTROS SISTEMAS', children: [
      { title: 'INFORMACION DOCUMENTADA', url: 'https://manualcinafrio.com.ar/documentos/' },
      { title: 'INDICADORES', url: 'https://manualcinafrio.com.ar/indicadores/' },
      { title: 'REGISTRO PR 44', url: 'https://sistema.cinafrio.com/intranet/index.php/registro' },
      { title: 'FORMACION', url: 'https://manualcinafrio.com.ar/formacion/' }
    ]}
  ]
};

const mainColorMap: any = {
  'LOGISTICA NACIONAL':'#36B0C9',
  'LOGISTICA FISCAL':'#00BBB4',
  'CALIDAD':'#E36B2C',
  'PORTERIA':'#00A887',
  'TRANSPORTE':'#00A887',
  'ADMINISTRACION':'#001F60',
  'RRHH':'#001F60',
  'MANTENIMIENTO':'#001F60',
  'PRODUCTOS':'#36B0C9',
  'DASHBOARD':'#FFCC33',
  'REPORTES':'#FFCC33',
  'INDICADORES':'#FFCC33',
  'SISTEMA DE GESTION':'#001F60',
  'SENASA':'#23BAC4',
  'CONFIGURACION':'#001F60',
  'OTROS SISTEMAS':'#001F60'
};

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
  { tipo:'CAMARA', nombre:'DS', estibas:67,  temperatura:24, clientes:['MB','ME'], extra:25, puerta:true, ventilador:true },
  { tipo:'TUNEL',  nombre:'2',  estibas:35,  temperatura:-27, clientes:['MD'],      extra:25, puerta:true, ventilador:true },
  { tipo:'TUNEL',  nombre:'9',  estibas:45,  temperatura:-27, clientes:['MD'],      extra:25, puerta:true, ventilador:true },
  { tipo:'TUNEL',  nombre:'10', estibas:40,  temperatura:-27, clientes:['MD'],      extra:25, puerta:true, ventilador:true }
];

const dashboardEquipamientoData = [
  { tipo:'Autoelevador', icono:'🛗', denominacion:'AE-01', estado:'En marcha', planta:'Nacional', horas:'00:45hs', usuario:'jlopez' },
  { tipo:'Autoelevador', icono:'🛗', denominacion:'AE-02', estado:'Cargando', planta:'Nacional', horas:'01:20hs', usuario:'mrojas' },
  { tipo:'Autoelevador', icono:'🛗', denominacion:'AE-03', estado:'En marcha', planta:'Deposito Fiscal', horas:'00:15hs', usuario:'agarcia' },
  { tipo:'Autoelevador', icono:'🛗', denominacion:'AE-04', estado:'Falla', planta:'Mantenimiento', horas:'03:40hs', usuario:'lperez' },
  { tipo:'Autoelevador', icono:'🛗', denominacion:'AE-05', estado:'En marcha', planta:'Nacional', horas:'00:05hs', usuario:'jgomez' },
  { tipo:'Autoelevador', icono:'🛗', denominacion:'AE-06', estado:'Cargando', planta:'Otros', horas:'02:10hs', usuario:'mbazan' },
  { tipo:'Autoelevador', icono:'🛗', denominacion:'AE-07', estado:'En marcha', planta:'Deposito Fiscal', horas:'00:55hs', usuario:'fvera' },
  { tipo:'Autoelevador', icono:'🛗', denominacion:'AE-08', estado:'Falla', planta:'Mantenimiento', horas:'04:25hs', usuario:'acastro' },
  { tipo:'Zorra electrica', icono:'🟦', denominacion:'ZE-01', estado:'En marcha', planta:'Nacional', horas:'00:32hs', usuario:'jmartin' },
  { tipo:'Zorra electrica', icono:'🟦', denominacion:'ZE-02', estado:'Cargando', planta:'Nacional', horas:'01:05hs', usuario:'rsosa' },
  { tipo:'Zorra electrica', icono:'🟦', denominacion:'ZE-03', estado:'En marcha', planta:'Deposito Fiscal', horas:'00:11hs', usuario:'pdominguez' },
  { tipo:'Zorra electrica', icono:'🟦', denominacion:'ZE-04', estado:'Falla', planta:'Mantenimiento', horas:'05:50hs', usuario:'cmedina' },
  { tipo:'Zorra electrica', icono:'🟦', denominacion:'ZE-05', estado:'En marcha', planta:'Otros', horas:'00:48hs', usuario:'sgimenez' },
  { tipo:'Autoelevador diesel', icono:'⛽', denominacion:'AD-01', estado:'En marcha', planta:'Deposito Fiscal', horas:'02:30hs', usuario:'rbenitez' },
  { tipo:'Limpiador', icono:'🤖', denominacion:'RL-01', estado:'Cargando', planta:'Mantenimiento', horas:'00:25hs', usuario:'lortiz' },
  { tipo:'Camion', icono:'🚚', denominacion:'CM-01', estado:'En marcha', planta:'Nacional', horas:'06:10hs', usuario:'druiz' }
];

const boxesData = Array.from({length:16}, (_,i) => ({
  numero:i+1,
  titulo:`Contenedor ${i+1}`,
  ubicacion:`16-5-${Math.floor(i/4)+1}-${(i%4)+1}`,
  tipo:i+1 === 6 ? 'Pallet especial' : 'Pallet',
  ocupacion: i+1 === 6 ? 78 : (35 + (i*4)),
  estiba: i+1 === 6 ? '65984-3' : `6598${i+1}-1`
}));

const historyStack: any[] = [];
const menuGrid = document.getElementById('menuGrid')!;
const screenTitle = document.getElementById('screenTitle')!;
const screenSubtitle = document.getElementById('screenSubtitle')!;
const backBtn = document.getElementById('backBtn')!;
const homeBtn = document.getElementById('homeBtn')!;
const fullscreenBtn = document.getElementById('fullscreenBtn')!;
const userBtn = document.getElementById('userBtn')!;
const qrBox = document.getElementById('qrBox')!;
const sinUbicacionBox = document.getElementById('sinUbicacionBox')!;
const vencidasBox = document.getElementById('vencidasBox')!;

function getTextInitials(text: string){
  return text.split(/\s+/).filter(Boolean).slice(0,2).map(word => word[0]).join('').toUpperCase();
}

function getIconMarkup(title: string){
  const imageMap: any = {
    'SISTEMA DE GESTION': 'iso_logo.png',
    'SENASA': 'senasa_logo.png'
  };

  if(imageMap[title]){
    return `<img src="${imageMap[title]}" alt="${title}" onerror="this.style.display='none'; this.parentElement.textContent='${getTextInitials(title)}';">`;
  }

  const symbolMap: any = {
    'LOGISTICA NACIONAL':'🚛',
    'LOGISTICA FISCAL':'📦',
    'CALIDAD':'🌡',
    'PORTERIA':'🚪',
    'TRANSPORTE':'🚚',
    'INDICADORES':'📊',
    'REPORTES':'📑',
    'DASHBOARD':'🖥',
    'ADMINISTRACION':'🗂',
    'RRHH':'👥',
    'MANTENIMIENTO':'🔧',
    'CONFIGURACION':'⚙',
    'PRODUCTOS':'📦',
    'OTROS SISTEMAS':'🧩',
    'INFORMACION DOCUMENTADA':'📚',
    'REGISTRO PR 44':'🗂',
    'FORMACION':'👨‍🏫',
    'MAPA DE BOXES':'🗺'
  };

  return symbolMap[title] || getTextInitials(title);
}

function getGridLayout(count: number){
  return count > 8 ? 'layout-8x2' : 'layout-4x4';
}

function formatTitle(title: string){
  const words = title.trim().split(/\s+/);
  if(words.length <= 1) return title;
  if(words.length === 2) return words[0] + '<br>' + words[1];
  if(words.length === 3) return words[0] + ' ' + words[1] + '<br>' + words[2];
  if(words.length === 4) return words[0] + ' ' + words[1] + '<br>' + words[2] + ' ' + words[3];
  if(words.length === 5) return words[0] + ' ' + words[1] + '<br>' + words[2] + ' ' + words[3] + ' ' + words[4];
  const mid = Math.ceil(words.length / 2);
  return words.slice(0, mid).join(' ') + '<br>' + words.slice(mid).join(' ');
}

function titleClass(title: string){
  const words = title.trim().split(/\s+/).length;
  return words >= 2 ? 'two-lines' : 'one-line';
}

function openModule(url: string){
  window.open(url, '_blank', 'noopener,noreferrer');
}

function darkenColor(hex: string, amount: number){
  let color = hex.replace('#', '');
  if (color.length === 3) color = color.split('').map(c => c + c).join('');
  let r = parseInt(color.substring(0,2),16);
  let g = parseInt(color.substring(2,4),16);
  let b = parseInt(color.substring(4,6),16);
  r = Math.max(0, Math.min(255, r + amount));
  g = Math.max(0, Math.min(255, g + amount));
  b = Math.max(0, Math.min(255, b + amount));
  return '#' + [r,g,b].map(v => v.toString(16).padStart(2,'0')).join('');
}

function getTileColors(item: any, level: number){
  const base = level === 0 ? (mainColorMap[item.title] || '#001F60') : '#163A7A';
  const end = darkenColor(base, -28);
  return { start: base, end };
}

function buildClientSlots(clientes: string[]){
  const totalSlots = 6;
  const filled = clientes.slice(0, totalSlots);
  const emptyCount = totalSlots - filled.length;
  const slots = [];

  filled.forEach(cliente => {
    slots.push(`<div class="client-badge" title="${cliente}">${cliente}</div>`);
  });

  for(let i = 0; i < emptyCount; i++){
    slots.push(`<div class="client-badge empty"></div>`);
  }

  return slots.join('');
}

function getTemperatureStyle(temp: number){
  if(temp >= 0) return 'linear-gradient(135deg,#16a34a,#22c55e)';
  if(temp > -10) return 'linear-gradient(135deg,#0891b2,#06b6d4)';
  if(temp > -20) return 'linear-gradient(135deg,#2563eb,#3b82f6)';
  if(temp > -25) return 'linear-gradient(135deg,#4338ca,#6366f1)';
  return 'linear-gradient(135deg,#7e22ce,#a855f7)';
}

function formatTemperature(temp: number){
  return `${temp}°C`;
}

function buildStatusRow(item: any){
  const puertaClass = item.puerta ? 'true-state' : 'false-state';
  const ventiladorClass = item.ventilador ? 'true-state' : 'false-state';

  return `
    <div class="dashboard-status-row">
      <div class="status-box ${puertaClass}" title="Puerta">🚪</div>
      <div class="status-box ${ventiladorClass}" title="Ventilador">🌀</div>
    </div>
  `;
}

function renderDashboardLogisticaNacional(){
  screenTitle.textContent = 'DASHBOARD LOGISTICA NACIONAL';
  screenSubtitle.textContent = 'OCUPACION, TEMPERATURA Y CLIENTES';
  menuGrid.className = '';
  menuGrid.innerHTML = '';

  const dashboardGrid = document.createElement('div');
  dashboardGrid.className = 'dashboard-grid';

  dashboardLogisticaNacionalData.forEach(item => {
    const card = document.createElement('button');
    card.className = 'dashboard-card';
    card.type = 'button';

    card.innerHTML = `
      <div class="dashboard-left">
        <div class="dashboard-temp" style="background:${getTemperatureStyle(item.temperatura)}">
          <div class="dashboard-temp-label">Temp.</div>
          <div class="dashboard-temp-value">${formatTemperature(item.temperatura)}</div>
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

      <div class="dashboard-clients">
        ${buildClientSlots(item.clientes)}
      </div>
    `;

    dashboardGrid.appendChild(card);
  });

  menuGrid.appendChild(dashboardGrid);
  (backBtn as HTMLButtonElement).disabled = historyStack.length === 0;
}

function getEquipmentCardClass(estado: string){
  if(estado === 'En marcha') return 'state-running-card';
  if(estado === 'Cargando') return 'state-charging-card';
  return 'state-failure-card';
}

function renderDashboardEquipamiento(){
  screenTitle.textContent = 'DASHBOARD EQUIPAMIENTO';
  screenSubtitle.textContent = 'ESTADO DE EQUIPOS Y VEHICULOS';
  menuGrid.className = '';
  menuGrid.innerHTML = '';

  const equipmentGrid = document.createElement('div');
  equipmentGrid.className = 'equipment-grid';

  dashboardEquipamientoData.forEach(item => {
    const card = document.createElement('div');
    card.className = `equipment-card ${getEquipmentCardClass(item.estado)}`;

    card.innerHTML = `
      <div class="equipment-header">
        <div class="equipment-icon">${item.icono}</div>
        <div class="equipment-title-wrap">
          <div class="equipment-type">${item.tipo}</div>
          <div class="equipment-name">${item.denominacion}</div>
        </div>
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
      </div>
    `;

    equipmentGrid.appendChild(card);
  });

  menuGrid.appendChild(equipmentGrid);
  (backBtn as HTMLButtonElement).disabled = historyStack.length === 0;
}

function renderMapaBoxes(){
  screenTitle.textContent = 'MAPA DE BOXES';
  screenSubtitle.textContent = 'SELECCIONE UN CONTENEDOR';
  menuGrid.className = '';
  menuGrid.innerHTML = '';

  const wrap = document.createElement('div');
  wrap.className = 'boxes-wrap';
  wrap.innerHTML = `
    <div class="boxes-header">
      <h2>Mapa interno de contenedores</h2>
      <p>Presione el contenedor que desea consultar. El contenedor 6 abre el detalle solicitado.</p>
    </div>
    <div class="boxes-grid" id="boxesGrid"></div>
  `;

  const boxesGrid = wrap.querySelector('#boxesGrid')!;

  boxesData.forEach(box => {
    const btn = document.createElement('button');
    btn.className = 'box-card';
    btn.type = 'button';
    btn.innerHTML = `
      <div class="box-card-number">${box.numero}</div>
      <div class="box-card-title">${box.titulo}</div>
      <div class="box-card-meta">
        Estiba: ${box.estiba}<br>
        Ubicación: ${box.ubicacion}
      </div>
    `;

    btn.addEventListener('click', () => {
      historyStack.push({ title:'MAPA DE BOXES', custom:'mapa_boxes' });
      if(box.numero === 6){
        renderContenedor6Detail();
      }else{
        alert(`Contenedor ${box.numero} seleccionado`);
      }
    });

    boxesGrid.appendChild(btn);
  });

  menuGrid.appendChild(wrap);
  (backBtn as HTMLButtonElement).disabled = historyStack.length === 0;
}

function renderContenedor6Detail(){
  screenTitle.textContent = 'ESTIBA 65984-3';
  screenSubtitle.textContent = 'DETALLE DE CONTENEDOR 6';
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
          <button class="estiba-module-btn calidad" id="btnModuloCalidad">Calidad</button>
          <button class="estiba-module-btn salir" id="btnSalirMenuPrincipal">Salir al menú principal</button>
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
          <button class="estiba-action-btn intervenir" id="btnAbrirIntervencion">Intervenir</button>
          <button class="estiba-action-btn fotos">Cargar fotos</button>
          <button class="estiba-action-btn temperaturas">Cargar temperaturas</button>
        </div>
      </section>

      <section class="estiba-detail-card">
        <h3 class="estiba-detail-title">Detalle</h3>
        <div class="estiba-detail-table">
          <div class="estiba-detail-row">
            <div class="estiba-detail-product">Bondiola de cerdo congelada</div>
            <div class="estiba-detail-date">15/05/2026</div>
          </div>
          <div class="estiba-detail-row">
            <div class="estiba-detail-product">Pechito de cerdo congelado</div>
            <div class="estiba-detail-date">22/05/2026</div>
          </div>
          <div class="estiba-detail-row">
            <div class="estiba-detail-product">Pulpa de cerdo exportación</div>
            <div class="estiba-detail-date">03/06/2026</div>
          </div>
        </div>
      </section>
    </div>
  `;

  document.getElementById('btnAbrirIntervencion')?.addEventListener('click', () => {
    historyStack.push({ title:'ESTIBA 65984-3', custom:'detalle_estiba_6' });
    renderIntervencionForm();
  });

  document.getElementById('btnModuloLogistica')?.addEventListener('click', () => {
    alert('Abrir módulo Logística');
  });

  document.getElementById('btnModuloCalidad')?.addEventListener('click', () => {
    alert('Abrir módulo Calidad');
  });

  document.getElementById('btnSalirMenuPrincipal')?.addEventListener('click', () => {
    historyStack.length = 0;
    renderNode(menuTree);
  });

  (backBtn as HTMLButtonElement).disabled = historyStack.length === 0;
}

function renderIntervencionForm(){
  screenTitle.textContent = 'INTERVENCION DE ESTIBA';
  screenSubtitle.textContent = 'SELECCIONE TIPO, MOTIVO Y OBSERVACIONES';
  menuGrid.className = '';
  menuGrid.innerHTML = `
    <div class="intervencion-wrap">
      <section class="intervencion-card">
        <h2 class="intervencion-title">Intervenir estiba 65984-3</h2>
        <p class="intervencion-subtitle">Complete la información para registrar la intervención.</p>
      </section>

      <section class="intervencion-card">
        <h3 class="intervencion-section-title">Tipo de intervención</h3>

        <div class="intervencion-options">
          <label class="intervencion-option-row">
            <input type="radio" name="tipoIntervencion" value="estiba_completa" checked>
            <span class="intervencion-option-label">Intervenir estiba completa</span>
          </label>

          <label class="intervencion-option-row">
            <input type="radio" name="tipoIntervencion" value="estiba_parcial">
            <span class="intervencion-option-label">Intervenir estiba parcial</span>
            <input
              type="number"
              id="cantidadParcial"
              class="intervencion-partial-qty"
              min="1"
              placeholder="Cantidad"
              disabled
            >
          </label>

          <label class="intervencion-option-row">
            <input type="radio" name="tipoIntervencion" value="entrada_entera">
            <span class="intervencion-option-label">Intervenir entrada entera</span>
          </label>
        </div>
      </section>

      <section class="intervencion-card">
        <div class="intervencion-field">
          <label class="intervencion-label" for="motivoIntervencion">Motivo</label>
          <select id="motivoIntervencion" class="intervencion-select">
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
          <label class="intervencion-label" for="observacionesIntervencion">Observaciones</label>
          <textarea
            id="observacionesIntervencion"
            class="intervencion-textarea"
            placeholder="Escriba aquí las observaciones..."
          ></textarea>
        </div>
      </section>

      <section class="intervencion-card">
        <div class="intervencion-field">
          <button id="btnConfirmarIntervencion" class="intervencion-submit">Intervenir</button>
        </div>
      </section>
    </div>
  `;

  const radios = document.querySelectorAll('input[name="tipoIntervencion"]');
  const cantidadParcial = document.getElementById('cantidadParcial') as HTMLInputElement;
  const btnConfirmar = document.getElementById('btnConfirmarIntervencion')!;

  function updateCantidadParcial(){
    const selected = (document.querySelector('input[name="tipoIntervencion"]:checked') as HTMLInputElement)?.value;
    const isParcial = selected === 'estiba_parcial';
    cantidadParcial.disabled = !isParcial;

    if(isParcial){
      cantidadParcial.focus();
    }else{
      cantidadParcial.value = '';
    }
  }

  radios.forEach(radio => {
    radio.addEventListener('change', updateCantidadParcial);
  });

  updateCantidadParcial();

  btnConfirmar.addEventListener('click', () => {
    const tipo = (document.querySelector('input[name="tipoIntervencion"]:checked') as HTMLInputElement)?.value || '';
    const motivo = (document.getElementById('motivoIntervencion') as HTMLSelectElement)?.value || '';

    if(!motivo){
      alert('Seleccione un motivo de intervención.');
      return;
    }

    if(tipo === 'estiba_parcial'){
      const cantidad = Number(cantidadParcial.value);
      if(!cantidad || cantidad <= 0){
        alert('Ingrese la cantidad para la intervención parcial.');
        return;
      }
    }

    historyStack.length = 0;
    renderNode(menuTree);
  });

  (backBtn as HTMLButtonElement).disabled = historyStack.length === 0;
}

function renderNode(node: any){
  if(node.custom === 'mapa_boxes'){
    renderMapaBoxes();
    return;
  }

  if(node.custom === 'detalle_estiba_6'){
    renderContenedor6Detail();
    return;
  }

  const items = node.children || [];
  const level = historyStack.length;

  screenTitle.textContent = level ? node.title : 'BIENVENIDOS AL SISTEMA DE GRUPO CINA';
  screenSubtitle.textContent = 'SISTEMA DE GESTION DE OPERACIONES';

  menuGrid.className = `menu-grid ${getGridLayout(items.length || 1)}`;
  menuGrid.innerHTML = '';

  if(!items.length){
    const tile = document.createElement('button');
    tile.className = 'tile';
    tile.type = 'button';
    tile.style.background = 'linear-gradient(135deg,#163A7A,#0C2554)';
    tile.innerHTML = `
      <div class="tile-icon">${getIconMarkup(node.title)}</div>
      <div class="tile-title ${titleClass(node.title)}">${formatTitle(node.title)}</div>
    `;
    if(node.url) tile.addEventListener('click', () => openModule(node.url));
    menuGrid.appendChild(tile);
  } else {
    items.forEach((item: any) => {
      const colors = getTileColors(item, level);
      const tile = document.createElement('button');
      tile.className = 'tile';
      tile.type = 'button';
      tile.style.background = `linear-gradient(135deg,${colors.start},${colors.end})`;
      tile.innerHTML = `
        <div class="tile-icon">${getIconMarkup(item.title)}</div>
        <div class="tile-title ${titleClass(item.title)}">${formatTitle(item.title)}</div>
      `;

      tile.addEventListener('click', () => {
        if(item.title === 'MAPA DE BOXES'){
          historyStack.push(node);
          renderMapaBoxes();
          return;
        }

        if(item.title === 'DASHBOARD LOGISTICA NACIONAL'){
          historyStack.push(node);
          renderDashboardLogisticaNacional();
          return;
        }

        if(item.title === 'DASHBOARD EQUIPAMIENTO'){
          historyStack.push(node);
          renderDashboardEquipamiento();
          return;
        }

        if(item.children){
          historyStack.push(node);
          renderNode(item);
          return;
        }

        if(item.url){
          openModule(item.url);
          return;
        }

        historyStack.push(node);
        renderNode({ title: item.title, url: item.url, children: [] });
      });

      menuGrid.appendChild(tile);
    });
  }

  (backBtn as HTMLButtonElement).disabled = historyStack.length === 0;
}

function updateAdaptiveLayout(){
  const isFullscreen = !!document.fullscreenElement;
  const isSmallWindow = window.innerHeight < 920 || window.innerWidth < 1500;

  if(!isFullscreen || isSmallWindow){
    document.body.classList.add('compact-mode');
  } else {
    document.body.classList.remove('compact-mode');
  }
}

backBtn.addEventListener('click', () => {
  if(!historyStack.length) return;
  const previous = historyStack.pop();
  renderNode(previous);
});

homeBtn.addEventListener('click', () => {
  historyStack.length = 0;
  renderNode(menuTree);
});

fullscreenBtn.addEventListener('click', async () => {
  try{
    if(!document.fullscreenElement){
      await document.documentElement.requestFullscreen();
    }else{
      await document.exitFullscreen();
    }
  }catch(e){
    console.error('No se pudo cambiar el modo de pantalla completa', e);
  }finally{
    updateAdaptiveLayout();
  }
});

userBtn.addEventListener('click', () => openModule('https://sistema.cinafrio.com/intranet2/app.php/persona/306/show'));

qrBox.addEventListener('click', () => {
  historyStack.push(menuTree);
  renderContenedor6Detail();
});

sinUbicacionBox.addEventListener('click', () => openModule('https://sistema.cinafrio.com/intranet/index.php/estiba/sinPosicion'));
vencidasBox.addEventListener('click', () => openModule('https://sistema.cinafrio.com/intranet/index.php/infostock/vencimientos'));

document.addEventListener('fullscreenchange', updateAdaptiveLayout);
window.addEventListener('resize', updateAdaptiveLayout);

updateAdaptiveLayout();
renderNode(menuTree);
