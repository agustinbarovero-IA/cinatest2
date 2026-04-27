/* ============================================================
 *  cina_datepicker.js — Date picker custom (estilo LATAM)
 *  Grupo CINA
 * ============================================================
 *  Upgrade automático de TODOS los <input type="date"> del
 *  sistema a un picker con flujo de 3 pasos:
 *
 *    1. Año  (lista scrollable, default = año actual)
 *    2. Mes  (grilla 3×4, abreviados ENE/FEB/…)
 *    3. Día  (grilla 7×6 estilo calendario)
 *
 *  - Muestra la fecha al usuario como dd/mm/aaaa
 *  - Internamente .value sigue siendo YYYY-MM-DD (compat con
 *    el resto del código y con el server)
 *  - Dispara eventos change e input al seleccionar
 *  - Para EXCLUIR un input puntual: agregá data-no-cdp="1"
 * ============================================================ */

(() => {
  if (window.__cdpReady) return;
  window.__cdpReady = true;

  const MESES_CORTOS = ['ENE','FEB','MAR','ABR','MAY','JUN','JUL','AGO','SEP','OCT','NOV','DIC'];
  const MESES_LARGOS = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
  const DIAS_SEM     = ['L','M','M','J','V','S','D'];

  // ── Helpers ─────────────────────────────────────────────────
  const pad = n => String(n).padStart(2, '0');
  const toISO = (y, m, d) => `${y}-${pad(m+1)}-${pad(d)}`;
  const isoToParts = iso => {
    if (!iso || typeof iso !== 'string') return null;
    const s = iso.split('T')[0];
    if (!/^\d{4}-\d{2}-\d{2}$/.test(s)) return null;
    const [y, m, d] = s.split('-').map(Number);
    return { y, m: m - 1, d };
  };
  const fmtDisplay = iso => {
    const p = isoToParts(iso);
    return p ? `${pad(p.d)}/${pad(p.m+1)}/${p.y}` : '';
  };
  const diasDelMes = (y, m) => new Date(y, m + 1, 0).getDate();

  // ── Estilos ─────────────────────────────────────────────────
  (function css() {
    if (document.getElementById('cdp-css')) return;
    const s = document.createElement('style');
    s.id = 'cdp-css';
    s.textContent = `
      .cdp-input { cursor: pointer !important; background-image:
        linear-gradient(to right, transparent calc(100% - 30px), rgba(79,70,229,.6) calc(100% - 30px));
      }
      #cdp-panel * { box-sizing: border-box; }
      #cdp-panel {
        position: fixed; z-index: 9999999;
        background: #fff; color: #111;
        border: 1px solid rgba(0,0,0,.1); border-radius: 14px;
        box-shadow: 0 14px 40px rgba(0,0,0,.28);
        min-width: 300px; max-width: 340px; padding: 16px;
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        animation: cdp-fade-in .14s ease;
      }
      @keyframes cdp-fade-in { from { opacity:0; transform: translateY(-4px) } to { opacity:1; transform: translateY(0) } }
      #cdp-panel .cdp-title {
        text-align: center; font-weight: 700; color: #475569;
        font-size: .74rem; text-transform: uppercase; letter-spacing: .05em;
        margin-bottom: 10px;
      }
      #cdp-panel .cdp-back {
        color: #4F46E5; font-weight: 700; font-size: 1rem;
        cursor: pointer; padding: 4px 0; display: inline-flex; align-items: center; gap: 6px;
        border: none; background: none; font-family: inherit;
      }
      #cdp-panel .cdp-back:hover { text-decoration: underline; }
      #cdp-panel .cdp-years {
        max-height: 260px; overflow-y: auto; text-align: center;
        padding: 4px 0; scrollbar-width: thin;
      }
      #cdp-panel .cdp-year {
        padding: 10px; font-size: 1.15rem; font-weight: 700; color: #334155;
        cursor: pointer; border-radius: 24px; transition: background .1s;
      }
      #cdp-panel .cdp-year:hover { background: rgba(79,70,229,.08); }
      #cdp-panel .cdp-year.sel { background: #4F46E5; color: #fff; }
      #cdp-panel .cdp-months {
        display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; padding: 6px 0;
      }
      #cdp-panel .cdp-month {
        padding: 14px 0; text-align: center; font-weight: 700; color: #334155;
        cursor: pointer; border-radius: 10px; font-size: .92rem; letter-spacing: .04em;
        transition: background .1s;
      }
      #cdp-panel .cdp-month:hover { background: rgba(79,70,229,.08); }
      #cdp-panel .cdp-month.sel { background: #4F46E5; color: #fff; }
      #cdp-panel .cdp-days-head, #cdp-panel .cdp-days {
        display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; padding: 3px 0;
      }
      #cdp-panel .cdp-days-head div {
        text-align: center; font-size: .68rem; font-weight: 700; color: #94a3b8;
        padding: 4px 0;
      }
      #cdp-panel .cdp-day {
        padding: 8px 0; text-align: center; font-size: .86rem; font-weight: 600; color: #334155;
        cursor: pointer; border-radius: 50%; transition: background .1s;
      }
      #cdp-panel .cdp-day:hover { background: rgba(79,70,229,.12); }
      #cdp-panel .cdp-day.sel { background: #4F46E5; color: #fff; }
      #cdp-panel .cdp-day.hoy { border: 1px solid #4F46E5; }
      #cdp-panel .cdp-actions {
        display: flex; justify-content: space-between; align-items: center;
        margin-top: 10px; padding-top: 8px; border-top: 1px solid rgba(0,0,0,.06);
      }
      #cdp-panel .cdp-btn {
        background: none; border: none; cursor: pointer; font-weight: 700;
        font-size: .78rem; padding: 6px 10px; border-radius: 6px; font-family: inherit;
      }
      #cdp-panel .cdp-btn-clear { color: #dc2626; }
      #cdp-panel .cdp-btn-clear:hover { background: rgba(220,38,38,.08); }
      #cdp-panel .cdp-btn-today { color: #4F46E5; }
      #cdp-panel .cdp-btn-today:hover { background: rgba(79,70,229,.08); }
      #cdp-panel .cdp-btn-close { color: #64748b; }
      #cdp-panel .cdp-btn-close:hover { background: rgba(100,116,139,.08); }
    `;
    document.head.appendChild(s);
  })();

  // ── Upgrade de inputs ───────────────────────────────────────
  function upgrade(input) {
    if (!input || input.dataset.cdp === '1') return;
    if (input.dataset.noCdp === '1' || input.hasAttribute('data-no-cdp')) return;

    // Guardar valor inicial (puede venir del atributo "value" HTML o del .value)
    let initialISO = input.getAttribute('value') || input.value || '';
    if (initialISO && !/^\d{4}-\d{2}-\d{2}/.test(initialISO)) initialISO = '';

    input.dataset.cdp = '1';
    input.type = 'text';
    input.readOnly = true;
    input.classList.add('cdp-input');
    input.setAttribute('autocomplete', 'off');
    input.setAttribute('inputmode', 'none');
    if (!input.placeholder) input.placeholder = 'dd/mm/aaaa';

    // Backing store
    input._cdpISO = '';

    // Override .value
    const nativeDesc = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
    Object.defineProperty(input, 'value', {
      configurable: true,
      get() { return this._cdpISO || ''; },
      set(v) {
        const iso = (v && /^\d{4}-\d{2}-\d{2}/.test(v)) ? v.split('T')[0] : '';
        this._cdpISO = iso;
        this.setAttribute('data-cdp-iso', iso);
        nativeDesc.set.call(this, fmtDisplay(iso));
      }
    });

    // Set inicial
    input.value = initialISO;

    // Handlers de apertura
    const openIt = (e) => {
      e.preventDefault();
      e.stopPropagation();
      openPicker(input);
    };
    input.addEventListener('click', openIt);
    input.addEventListener('focus', () => { /* sin autoopen en focus */ });
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
        e.preventDefault();
        openPicker(input);
      } else if (e.key === 'Escape') {
        closePicker();
      } else if (e.key === 'Delete' || e.key === 'Backspace') {
        e.preventDefault();
        if (input._cdpISO) {
          input.value = '';
          input.dispatchEvent(new Event('input', { bubbles: true }));
          input.dispatchEvent(new Event('change', { bubbles: true }));
        }
      }
    });
  }

  // ── Picker ──────────────────────────────────────────────────
  function openPicker(input) {
    closePicker();

    const hoy = new Date();
    const hoyY = hoy.getFullYear(), hoyM = hoy.getMonth(), hoyD = hoy.getDate();
    const parts = isoToParts(input._cdpISO) || { y: hoyY, m: hoyM, d: hoyD };
    let y = parts.y, m = parts.m, d = parts.d;

    const panel = document.createElement('div');
    panel.id = 'cdp-panel';
    document.body.appendChild(panel);
    positionPanel(panel, input);

    let step = 'year';

    function render() {
      if (step === 'year')  return renderYear();
      if (step === 'month') return renderMonth();
      return renderDay();
    }

    function renderYear() {
      const from = hoyY - 100, to = hoyY + 20;
      const years = [];
      for (let yy = to; yy >= from; yy--) years.push(yy);
      panel.innerHTML = `
        <div class="cdp-title">Elegí el año</div>
        <div class="cdp-years">
          ${years.map(yy => `<div class="cdp-year ${yy===y?'sel':''}" data-y="${yy}">${yy}</div>`).join('')}
        </div>`;
      const selEl = panel.querySelector('.cdp-year.sel');
      if (selEl) setTimeout(() => selEl.scrollIntoView({ block: 'center' }), 0);
      panel.querySelectorAll('.cdp-year').forEach(el => el.onclick = () => {
        y = +el.dataset.y; step = 'month'; render();
      });
    }

    function renderMonth() {
      panel.innerHTML = `
        <div style="margin-bottom:6px">
          <button class="cdp-back">${y} ▼</button>
        </div>
        <div class="cdp-months">
          ${MESES_CORTOS.map((mm, i) => `<div class="cdp-month ${i===m?'sel':''}" data-m="${i}">${mm}</div>`).join('')}
        </div>`;
      panel.querySelector('.cdp-back').onclick = () => { step = 'year'; render(); };
      panel.querySelectorAll('.cdp-month').forEach(el => el.onclick = () => {
        m = +el.dataset.m; step = 'day'; render();
      });
    }

    function renderDay() {
      const dim = diasDelMes(y, m);
      // Offset: lunes=0, domingo=6
      const primerDia = new Date(y, m, 1).getDay();
      const offset = (primerDia + 6) % 7;
      const cells = [];
      for (let i = 0; i < offset; i++) cells.push(null);
      for (let dd = 1; dd <= dim; dd++) cells.push(dd);

      panel.innerHTML = `
        <div style="margin-bottom:6px">
          <button class="cdp-back">${MESES_LARGOS[m]} ${y} ▼</button>
        </div>
        <div class="cdp-days-head">${DIAS_SEM.map(x=>`<div>${x}</div>`).join('')}</div>
        <div class="cdp-days">
          ${cells.map(c => {
            if (c === null) return '<div></div>';
            const esHoy = (y===hoyY && m===hoyM && c===hoyD);
            const esSel = (c===d && input._cdpISO);
            const cls = [(esSel?'sel':''), (esHoy?'hoy':'')].filter(Boolean).join(' ');
            return `<div class="cdp-day ${cls}" data-d="${c}">${c}</div>`;
          }).join('')}
        </div>
        <div class="cdp-actions">
          <button class="cdp-btn cdp-btn-clear">Limpiar</button>
          <button class="cdp-btn cdp-btn-today">Hoy</button>
          <button class="cdp-btn cdp-btn-close">Cerrar</button>
        </div>`;
      panel.querySelector('.cdp-back').onclick = () => { step = 'month'; render(); };
      panel.querySelectorAll('.cdp-day').forEach(el => el.onclick = () => {
        d = +el.dataset.d;
        aplicar(toISO(y, m, d));
      });
      panel.querySelector('.cdp-btn-clear').onclick = () => aplicar('');
      panel.querySelector('.cdp-btn-today').onclick = () => aplicar(toISO(hoyY, hoyM, hoyD));
      panel.querySelector('.cdp-btn-close').onclick = closePicker;
    }

    function aplicar(iso) {
      input.value = iso;
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
      closePicker();
      input.focus();
    }

    render();

    // Cerrar al clickear afuera
    setTimeout(() => {
      document.addEventListener('mousedown', outside, true);
      document.addEventListener('touchstart', outside, true);
      window.addEventListener('resize', onResize);
      window.addEventListener('scroll', onScroll, true);
    }, 0);

    function outside(e) {
      if (!panel.contains(e.target) && e.target !== input) closePicker();
    }
    function onResize() { positionPanel(panel, input); }
    function onScroll() { positionPanel(panel, input); }

    panel._cleanup = () => {
      document.removeEventListener('mousedown', outside, true);
      document.removeEventListener('touchstart', outside, true);
      window.removeEventListener('resize', onResize);
      window.removeEventListener('scroll', onScroll, true);
    };
  }

  function positionPanel(panel, input) {
    const rect = input.getBoundingClientRect();
    const pw = panel.offsetWidth || 320;
    const ph = panel.offsetHeight || 360;
    const vw = window.innerWidth, vh = window.innerHeight;

    let left = rect.left;
    let top  = rect.bottom + 4;
    // Evitar overflow horizontal
    if (left + pw > vw - 8) left = Math.max(8, vw - pw - 8);
    if (left < 8) left = 8;
    // Si no entra abajo, abrir arriba
    if (top + ph > vh - 8 && rect.top - ph - 4 > 8) {
      top = rect.top - ph - 4;
    } else if (top + ph > vh - 8) {
      top = Math.max(8, vh - ph - 8);
    }
    panel.style.left = left + 'px';
    panel.style.top  = top  + 'px';
  }

  function closePicker() {
    const p = document.getElementById('cdp-panel');
    if (!p) return;
    if (p._cleanup) p._cleanup();
    p.remove();
  }

  // ── Scan global + MutationObserver ──────────────────────────
  function scan(root = document) {
    if (!root.querySelectorAll) return;
    root.querySelectorAll('input[type="date"]').forEach(upgrade);
  }

  function bootstrap() {
    scan();
    const obs = new MutationObserver(muts => {
      for (const mu of muts) {
        for (const n of mu.addedNodes) {
          if (n.nodeType !== 1) continue;
          if (n.tagName === 'INPUT' && n.type === 'date') upgrade(n);
          else scan(n);
        }
      }
    });
    obs.observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrap);
  } else {
    bootstrap();
  }

  // API global por si alguien quiere usar el picker manualmente
  window.CDP = {
    upgrade, scan,
    open: (input) => { upgrade(input); openPicker(input); },
    close: closePicker,
    format: fmtDisplay,
  };
})();
