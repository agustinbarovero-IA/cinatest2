<?php
session_start();
if (empty($_SESSION['cina_usuario_id'])) {
    header('Location: login.php');
    exit;
}
$cinaSession = json_encode([
    'autenticado'  => true,
    'usuario_id'   => $_SESSION['cina_usuario_id'],
    'usuario'      => $_SESSION['cina_usuario'],
    'nombre'       => $_SESSION['cina_nombre'],
    'persona_id'   => $_SESSION['cina_persona_id'],
    'email'        => $_SESSION['cina_email']      ?? '',
    'auth_method'  => $_SESSION['cina_auth_method'] ?? 'password',
    'rol_id'       => (int)($_SESSION['cina_rol_id'] ?? 0),
], JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<script>
window.__cinaSession = <?= $cinaSession ?>;
</script>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sistema Grupo CINA</title>
  <link rel="stylesheet" href="style.css?v=20260407f" />
</head>
<body>
  <div class="app-shell">
    <header class="topbar">
      <div class="brand">
        <img src="img/cina_logo_principal.png" alt="CINA">
        <div class="brand-copy">
          <h1 id="screenTitle">BIENVENIDOS AL SISTEMA DE GRUPO CINA</h1>
          <p id="screenSubtitle">...</p>
        </div>
      </div>

      <div class="toolbar">
        <button id="backBtn">← Volver</button>
        <button id="homeBtn">Inicio</button>
        <button id="fullscreenBtn">Pantalla completa</button>
        <button id="userBtn" class="btn-user">Administrar usuario</button>
        <button id="logoutBtn" class="btn-logout" onclick="window.location.href='api_2026ia.php?action=logout'">⎋ Cerrar sesión</button>
      </div>
    </header>

    <section class="meta-panel">
      <div class="data-strip">
        <div class="data-box green">
          <div class="data-stack">
            <span class="data-number" id="vehiculos-planta">—</span>
            <span class="data-label">VEHICULOS</span>
          </div>
        </div>

        <div class="data-box green">
          <div class="data-stack">
            <span class="data-number" id="estibas-en-planta">—</span>
            <span class="data-label">ESTIBAS EN PLANTA</span>
          </div>
        </div>

        <div class="data-box qr" id="qrBox">
          <div class="data-content">
            <span class="data-qr-icon"></span>
            <span>SCAN QR</span>
          </div>
        </div>

        <div class="data-box orange" id="sinUbicacionBox" style="cursor:pointer">
          <div class="data-content">
            <span class="data-icon-round">!</span>
            <div class="data-stack">
              <span class="data-number">1050</span>
              <span class="data-label">SIN UBICACION</span>
            </div>
          </div>
        </div>

        <div class="data-box red" id="vencidasBox" style="cursor:pointer">
          <div class="data-content">
            <span class="data-icon-round">!</span>
            <div class="data-stack">
              <span class="data-number">189</span>
              <span class="data-label">VENCIDAS</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <main class="workspace">
      <div class="menu-wrap">
        <div class="menu-grid" id="menuGrid"></div>
      </div>
    </main>

    <footer class="footer-bar">Sistema interno Grupo CINA</footer>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
  <script src="cina_datepicker.js?v=20260422a"></script>
  <script src="app.js?v=20260423c"></script>
  <script src="facturacion.js?v=20260407f"></script>
  <script src="panol.js?v=20260422b"></script>
  <script src="planificacion.js?v=20260407f"></script>
  <script src="boxes.js?v=20260407f"></script>
  <script src="troneras.js?v=20260407f"></script>
  <script src="recepcion.js?v=20260407f"></script>
  <script src="encuestas_pr08.js?v=20260422a"></script>
  <script src="costeo.js?v=20260423a"></script>
  <script src="pr59_generadores.js?v=20260423a"></script>
</body>
</html>
