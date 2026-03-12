/**
 * Sistema Grupo CINA - Lógica de Navegación
 */
import './index.css';

const menuTree = {
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
      { title: 'SALIDAS', url: 'https://sistema.cinafrio.com/intranet/index.php/presalidas/list/salida/1/fiscal/1' }
    ]},
    { title: 'CALIDAD', children: [
      { title: 'CONTROL DE ENTRADAS', url: 'https://sistema.cinafrio.com/intranet/index.php/carga/list/type/E' },
      { title: 'CONTROL DE SALIDAS', url: 'https://sistema.cinafrio.com/intranet/index.php/carga/list/type/S' },
      { title: 'CONTROL DE INTERVENCIONES', url: 'https://sistema.cinafrio.com/intranet/index.php/carga/listIntervenciones' }
    ]},
    { title: 'DASHBOARD', children: [
      { title: 'MAPA DE BOXES', custom: 'mapa_boxes' },
      { title: 'DASHBOARD LOGISTICA NACIONAL', custom: 'dash_logistica' },
      { title: 'DASHBOARD EQUIPAMIENTO', custom: 'dash_equipos' }
    ]}
  ]
};

const mainColorMap: Record<string, string> = {
  'LOGISTICA NACIONAL': '#36B0C9',
  'LOGISTICA FISCAL': '#00BBB4',
  'CALIDAD': '#E36B2C',
  'DASHBOARD': '#FFCC33'
};

const historyStack: any[] = [];
const menuGrid = document.getElementById('menuGrid')!;
const screenTitle = document.getElementById('screenTitle')!;
const backBtn = document.getElementById('backBtn') as HTMLButtonElement;
const homeBtn = document.getElementById('homeBtn')!;

function renderNode(node: any) {
  const items = node.children || [];
  const level = historyStack.length;

  screenTitle.textContent = level ? node.title : 'BIENVENIDOS AL SISTEMA DE GRUPO CINA';
  menuGrid.innerHTML = '';
  menuGrid.className = `menu-grid ${items.length > 8 ? 'layout-8x2' : 'layout-4x4'}`;

  items.forEach((item: any) => {
    const tile = document.createElement('button');
    tile.className = 'tile';
    const baseColor = level === 0 ? (mainColorMap[item.title] || '#163A7A') : '#163A7A';
    tile.style.background = baseColor;

    tile.innerHTML = `
      <div class="tile-icon">${getIcon(item.title)}</div>
      <div class="tile-title">${item.title}</div>
    `;

    tile.onclick = () => {
      if (item.custom) {
        alert(`Abriendo Dashboard: ${item.title}`);
      } else if (item.children) {
        historyStack.push(node);
        renderNode(item);
      } else if (item.url) {
        window.open(item.url, '_blank');
      }
    };
    menuGrid.appendChild(tile);
  });

  backBtn.disabled = historyStack.length === 0;
}

function getIcon(title: string) {
  const icons: Record<string, string> = {
    'LOGISTICA NACIONAL': '🚛',
    'LOGISTICA FISCAL': '📦',
    'CALIDAD': '🌡',
    'DASHBOARD': '🖥',
    'MAPA DE BOXES': '🗺'
  };
  return icons[title] || '📄';
}

backBtn.onclick = () => {
  const prev = historyStack.pop();
  if (prev) renderNode(prev);
};

homeBtn.onclick = () => {
  historyStack.length = 0;
  renderNode(menuTree);
};

// Inicializar
renderNode(menuTree);
