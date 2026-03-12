/**
 * Sistema Grupo CINA - Lógica de Navegación y Dashboards
 */

interface MenuNode {
  title: string;
  url?: string;
  children?: MenuNode[];
  custom?: string;
}

const menuTree: MenuNode = {
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

const historyStack: MenuNode[] = [];
const menuGrid = document.getElementById('menuGrid')!;
const screenTitle = document.getElementById('screenTitle')!;
const backBtn = document.getElementById('backBtn') as HTMLButtonElement;
const homeBtn = document.getElementById('homeBtn')!;

function renderNode(node: MenuNode) {
  const items = node.children || [];
  const isRoot = historyStack.length === 0;

  screenTitle.textContent = isRoot ? 'BIENVENIDOS AL SISTEMA DE GRUPO CINA' : node.title;
  menuGrid.innerHTML = '';
  menuGrid.className = `menu-grid ${items.length > 8 ? 'layout-8x2' : 'layout-4x4'}`;

  items.forEach(item => {
    const tile = document.createElement('button');
    tile.className = 'tile';
    tile.innerHTML = `
      <div class="tile-icon">${getIcon(item.title)}</div>
      <div class="tile-title">${item.title}</div>
    `;

    tile.onclick = () => {
      if (item.custom) {
        handleCustomAction(item.custom);
      } else if (item.children) {
        historyStack.push(node);
        renderNode(item);
      } else if (item.url) {
        window.open(item.url, '_blank');
      }
    };

    menuGrid.appendChild(tile);
  });

  backBtn.disabled = isRoot;
}

function handleCustomAction(action: string) {
  alert(`Abriendo: ${action}`);
  // Aquí irían las funciones específicas de cada dashboard
}

function getIcon(title: string): string {
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

// Inicialización
renderNode(menuTree);
