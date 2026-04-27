<?php
session_start();
if (!empty($_SESSION['cina_usuario_id'])) {
    // Si ya está logueado y viene con redirect, mandarlo directo
    $redir = $_GET['redirect'] ?? '';
    if ($redir && preg_match('/^[a-zA-Z0-9\/_\-\.]+$/', $redir)) {
        header('Location: index.php#' . $redir); exit;
    }
    header('Location: index.php'); exit;
}
$error = $_GET['error'] ?? '';
$redirect = $_GET['redirect'] ?? '';  // hash destino pasado como query param
$errorMsg = match($error) {
    'invalid'      => 'Usuario o contraseña incorrectos.',
    'google_email' => 'Tu cuenta de Google no está registrada en el sistema.',
    'google_error' => 'Error al autenticar con Google. Intentá de nuevo.',
    'inactive'     => 'Tu cuenta está inactiva. Contactá al administrador.',
    default        => ''
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CINA — Acceso</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --bg:       #00103a;
      --accent:   #36B0C9;
      --accent2:  #00A887;
      --white:    #ffffff;
      --dim:      rgba(255,255,255,.5);
      --border:   rgba(255,255,255,.13);
      --inputbg:  rgba(255,255,255,.07);
      --error:    #f87171;
      --r:        14px;
    }
    body {
      min-height: 100vh;
      background: var(--bg);
      display: grid;
      grid-template-columns: 1fr 1fr;
      font-family: 'Segoe UI', system-ui, sans-serif;
      overflow: hidden;
    }

    /* Panel izquierdo — marca */
    .left-panel {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 48px;
      background: linear-gradient(160deg, #001a5e 0%, #00103a 100%);
      position: relative;
      overflow: hidden;
    }
    .left-panel::before {
      content: '';
      position: absolute;
      width: 600px; height: 600px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(54,176,201,.18) 0%, transparent 70%);
      top: -100px; left: -100px;
      pointer-events: none;
    }
    .left-panel::after {
      content: '';
      position: absolute;
      width: 400px; height: 400px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(0,168,135,.12) 0%, transparent 70%);
      bottom: -80px; right: -80px;
      pointer-events: none;
    }
    .brand-wrap {
      position: relative;
      z-index: 1;
      text-align: center;
    }
    .brand-logo {
      width: 120px;
      height: 120px;
      object-fit: contain;
      filter: drop-shadow(0 8px 24px rgba(54,176,201,.3));
      margin-bottom: 24px;
    }
    /* Fallback si no carga la imagen */
    .brand-logo-fallback {
      width: 120px; height: 120px;
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      border-radius: 24px;
      display: flex; align-items: center; justify-content: center;
      font-size: 2.5rem; font-weight: 900; color: #fff;
      margin: 0 auto 24px;
      box-shadow: 0 8px 32px rgba(54,176,201,.35);
    }
    .brand-name {
      font-size: 2rem;
      font-weight: 800;
      color: var(--white);
      letter-spacing: -.5px;
      line-height: 1.1;
      margin-bottom: 10px;
    }
    .brand-tagline {
      font-size: .85rem;
      color: var(--dim);
      font-weight: 500;
      letter-spacing: .3px;
      line-height: 1.5;
      max-width: 280px;
    }
    .brand-dots {
      display: flex;
      gap: 8px;
      justify-content: center;
      margin-top: 32px;
    }
    .brand-dot {
      width: 6px; height: 6px; border-radius: 50%;
      background: rgba(255,255,255,.2);
    }
    .brand-dot.active { background: var(--accent); }

    /* Panel derecho — formulario */
    .right-panel {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 48px 40px;
      background: #020e2e;
      overflow-y: auto;
    }
    .login-box {
      width: 100%;
      max-width: 380px;
    }
    .login-heading {
      font-size: 1.4rem;
      font-weight: 800;
      color: var(--white);
      margin-bottom: 4px;
    }
    .login-sub {
      font-size: .8rem;
      color: var(--dim);
      margin-bottom: 28px;
    }

    /* Error */
    .error-box {
      background: rgba(248,113,113,.12);
      border: 1px solid rgba(248,113,113,.3);
      border-radius: 10px;
      padding: 11px 14px;
      font-size: .82rem;
      color: var(--error);
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    /* Campos */
    .field { margin-bottom: 14px; }
    .field label {
      display: block;
      font-size: .68rem;
      font-weight: 700;
      color: var(--dim);
      text-transform: uppercase;
      letter-spacing: .6px;
      margin-bottom: 6px;
    }
    .field input {
      width: 100%;
      background: var(--inputbg);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 12px 14px;
      font-size: .92rem;
      color: var(--white);
      outline: none;
      transition: border-color .15s, background .15s;
      font-family: inherit;
    }
    .field input::placeholder { color: rgba(255,255,255,.25); }
    .field input:focus {
      border-color: var(--accent);
      background: rgba(255,255,255,.1);
    }

    /* Botón ingresar */
    .btn-primary {
      width: 100%;
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      border: none;
      border-radius: 10px;
      padding: 13px;
      font-size: .95rem;
      font-weight: 700;
      color: #fff;
      cursor: pointer;
      transition: opacity .15s, transform .1s;
      margin-top: 4px;
      font-family: inherit;
      letter-spacing: .2px;
    }
    .btn-primary:hover  { opacity: .88; }
    .btn-primary:active { transform: scale(.98); }
    .btn-primary:disabled { opacity: .6; cursor: not-allowed; }

    /* Divisor */
    .divider {
      display: flex;
      align-items: center;
      gap: 10px;
      margin: 18px 0;
      color: var(--dim);
      font-size: .72rem;
    }
    .divider::before, .divider::after {
      content: ''; flex: 1; height: 1px; background: var(--border);
    }

    /* Botón Google */
    .btn-google {
      width: 100%;
      background: rgba(255,255,255,.06);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 12px;
      font-size: .88rem;
      font-weight: 600;
      color: var(--white);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      transition: background .15s, border-color .15s;
      font-family: inherit;
    }
    .btn-google:hover {
      background: rgba(255,255,255,.11);
      border-color: rgba(255,255,255,.25);
    }
    .btn-google svg { width: 18px; height: 18px; flex-shrink: 0; }

    .login-footer {
      margin-top: 28px;
      font-size: .68rem;
      color: rgba(255,255,255,.25);
      text-align: center;
      line-height: 1.6;
    }

    /* Responsive — mobile una sola columna */
    @media(max-width: 768px) {
      body { grid-template-columns: 1fr; }
      .left-panel {
        padding: 36px 24px 28px;
        min-height: auto;
      }
      .brand-logo { width: 80px; height: 80px; margin-bottom: 16px; }
      .brand-logo-fallback { width: 80px; height: 80px; font-size: 1.8rem; }
      .brand-name { font-size: 1.5rem; }
      .brand-dots { display: none; }
      .right-panel { padding: 32px 24px 40px; }
    }
  </style>
</head>
<body>

<!-- Panel izquierdo: marca -->
<div class="left-panel">
  <div class="brand-wrap">
    <img src="img/CINA.webp"
         alt="Grupo CINA"
         class="brand-logo"
         onerror="this.src='img/cina_logo_principal.png'; this.onerror=null;">

    <div class="brand-name">Grupo CINA</div>
    <div class="brand-tagline">
      Sistema Integral de<br>Operaciones y Gestión
    </div>

  </div>
</div>

<!-- Panel derecho: login -->
<div class="right-panel">
  <div class="login-box">
    <div class="login-heading">Bienvenido al sistema</div>
    <div class="login-sub">Recordá que las acciones realizadas con tu usuario serán exclusiva responsabilidad tuya. No compartas tu contraseña ni la guardes en equipos compartidos.</div>

    <?php if ($errorMsg): ?>
    <div class="error-box">
      <span>⚠</span>
      <span><?= htmlspecialchars($errorMsg) ?></span>
    </div>
    <?php endif; ?>

    <form id="loginForm" action="api_2026ia.php?action=login_session" method="POST">
      <?php if ($redirect): ?>
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
      <?php endif; ?>
      <div class="field">
        <label>Usuario</label>
        <input type="text" name="usuario" placeholder="Tu usuario" autocomplete="username" required autofocus>
      </div>
      <div class="field">
        <label>Contraseña</label>
        <input type="password" name="password" id="pwdInput" placeholder="••••••••" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn-primary" id="btnLogin">Ingresar</button>
    </form>

    <div class="divider">o continuá con</div>

    <button class="btn-google" id="btnGoogle">
      <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
      </svg>
      Continuar con Google
    </button>

    <div class="login-footer">
      Grupo CINA SRL · Pueblo Esther, Santa Fe<br>
      Sistema de uso interno — acceso restringido<br><br>
      <button type="button" onclick="document.getElementById('modalCalidad').style.display='flex'" 
        style="background:none;border:none;color:rgba(255,255,255,.4);font-size:.72rem;cursor:pointer;text-decoration:underline;padding:0;">
        Ver Política de Calidad
      </button>
    </div>

  <!-- Modal Política de Calidad -->
  <div id="modalCalidad" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:999;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;color:#111;border-radius:14px;max-width:720px;width:100%;max-height:90vh;overflow-y:auto;padding:36px 40px;position:relative;">
      <button onclick="document.getElementById('modalCalidad').style.display='none'"
        style="position:absolute;top:16px;right:20px;background:none;border:none;font-size:1.4rem;cursor:pointer;color:#666;">✕</button>
      
      <div style="text-align:right;font-size:.82rem;font-weight:700;margin-bottom:16px;">SG-C-10, Rev. 4, Jun-23</div>
      
      <h2 style="font-size:1.2rem;font-weight:700;margin-bottom:20px;">Política de la Calidad</h2>
      
      <p style="text-align:justify;margin-bottom:14px;"><strong>CINA SRL</strong> persigue consolidarse como una empresa de servicios, cuyas prestaciones alcancen el mayor nivel de calidad y eficacia en las operatorias de recepción, almacenamiento, acondicionamiento de temperatura, congelamiento y despacho de mercadería congelada y enfriada, para el mercado interno y comercio exterior.</p>
      
      <p style="text-align:justify;margin-bottom:14px;">A través de su Sistema de Gestión basado en la normativa ISO 9001:2015, <strong>CINA SRL</strong> adquiere el firme compromiso de satisfacer los requisitos aplicables al cliente y partes interesadas, incluidos los legales y reglamentarios aplicables a su actividad y de mejorar su eficacia de forma continua y planificada.</p>
      
      <p style="margin-bottom:10px;">Para ello, <strong>CINA SRL</strong> se basa en:</p>
      <ul style="padding-left:20px;line-height:1.8;margin-bottom:14px;">
        <li>La Calidad y su mejora es responsabilidad de todos los integrantes de la ORGANIZACIÓN.</li>
        <li>La Calidad se obtiene planificando, ejecutando, revisando y mejorando el Sistema de Gestión, teniendo presente en todo momento el contexto de la organización, tanto interno como externo.</li>
        <li>El desarrollo, capacitación y participación de nuestro equipo en sus áreas de responsabilidad.</li>
        <li>La seguridad en las operaciones, manteniendo equipamiento e instalaciones en funcionamiento seguro y brindando elementos de protección personal.</li>
        <li>La Calidad se apoya en la Mejora Continua tanto de los procesos de congelados y almacenado en seco, como de la eficacia del Sistema de Gestión de la Calidad en el que prevenir las No Conformidades sea un aspecto fundamental.</li>
        <li>La Calidad nos dirige a prestar la máxima atención a la evolución tecnológica y a las posibles mejoras que las nuevas tecnologías pusieran a nuestra disposición, incluyendo un enfoque de sostenibilidad, para asegurar la rentabilidad en el tiempo.</li>
        <li>La comunicación entre CINA y sus clientes sea eficaz y asertiva.</li>
        <li>Nuestra operación siempre está enfocada en la Satisfacción de nuestros clientes para seguir manteniendo su confianza en CINA SRL como aliados estratégicos en sus negocios.</li>
        <li>La Calidad requiere de la colaboración de todos, por lo cual esta Política es difundida a todo el personal de la ORGANIZACIÓN para su conocimiento y comprensión.</li>
      </ul>
      
      <p style="text-align:right;font-style:italic;">La Gerencia.</p>
    </div>
  </div>
  </div>
</div>

<script src="https://accounts.google.com/gsi/client" async defer></script>
<script>
const GOOGLE_CLIENT_ID = '1098258632794-tj906j4kr33smkoano96d0mj62p9jvkq.apps.googleusercontent.com';
// Preservar el destino (hash) a través del flujo de login
const REDIRECT_HASH = <?= json_encode($redirect) ?>;

document.getElementById('loginForm').addEventListener('submit', function() {
  const btn = document.getElementById('btnLogin');
  btn.textContent = 'Ingresando...';
  btn.disabled = true;
});

document.getElementById('btnGoogle').addEventListener('click', function() {
  const btn = this;
  btn.textContent = 'Conectando con Google...';
  btn.disabled = true;

  const client = google.accounts.oauth2.initTokenClient({
    client_id: GOOGLE_CLIENT_ID,
    scope: 'email profile',
    callback: function(tokenResponse) {
      if (tokenResponse.error) {
        btn.innerHTML = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="width:18px;height:18px;flex-shrink:0"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg> Continuar con Google';
        btn.disabled = false;
        return;
      }
      fetch('https://www.googleapis.com/oauth2/v3/userinfo', {
        headers: { Authorization: 'Bearer ' + tokenResponse.access_token }
      })
      .then(r => r.json())
      .then(info => loginConGoogle(info.email, info.name))
      .catch(() => {
        const errUrl = 'login.php?error=google_error' + (REDIRECT_HASH ? '&redirect=' + encodeURIComponent(REDIRECT_HASH) : '');
        window.location.href = errUrl;
      });
    },
  });
  client.requestAccessToken();
});

function loginConGoogle(email, name) {
  fetch('api_2026ia.php?action=login_google', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, name })
  })
  .then(r => r.json())
  .then(data => {
    if (data.ok) {
      // Navegar a index con el hash destino preservado
      window.location.href = 'index.php' + (REDIRECT_HASH ? '#' + REDIRECT_HASH : '');
    } else {
      const errUrl = 'login.php?error=' + (data.error_code || 'google_email')
                   + (REDIRECT_HASH ? '&redirect=' + encodeURIComponent(REDIRECT_HASH) : '');
      window.location.href = errUrl;
    }
  })
  .catch(() => {
    const errUrl = 'login.php?error=google_error' + (REDIRECT_HASH ? '&redirect=' + encodeURIComponent(REDIRECT_HASH) : '');
    window.location.href = errUrl;
  });
}
</script>
</body>
</html>
