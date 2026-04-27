<?php
/**
 * ============================================================
 *  ENDPOINT: sync_puertas.php
 *  Recibe datos desde Google Apps Script y los carga en MySQL
 *  Grupo CINA — Registro apertura/cierre de puertas de cámaras
 * ============================================================
 */

require_once __DIR__ . '/sync_helper.php';

define('LOG_FILE', __DIR__ . '/sync_puertas_log.txt');

// ── Validar request ──────────────────────────────────────────
$data = sync_init();

$registros = $data['registros'] ?? [];

if (empty($registros)) {
    sync_response(['ok' => true, 'insertados' => 0, 'ignorados' => 0, 'msg' => 'Sin registros']);
}

// ── Conectar MySQL ───────────────────────────────────────────
try {
    $pdo = sync_pdo();
} catch (PDOException $e) {
    http_response_code(500);
    sync_log(LOG_FILE, 'Error DB: ' . $e->getMessage());
    sync_response(['error' => 'Error de conexión']);
}

// ── Insertar registros de puertas ────────────────────────────
$sql = "INSERT IGNORE INTO camara_puerta_registro (fecha, hora, camara_id, estado)
        VALUES (:fecha, :hora, :camara_id, :estado)";

$stmt       = $pdo->prepare($sql);
$insertados = 0;
$ignorados  = 0;
$errores    = 0;

foreach ($registros as $reg) {
    if (empty($reg['fecha']) || empty($reg['hora']) || empty($reg['camara_id']) || empty($reg['estado'])) {
        $errores++;
        continue;
    }
    if (!in_array($reg['estado'], ['Abierta', 'Cerrada'])) {
        sync_log(LOG_FILE, 'Estado desconocido: ' . $reg['estado']);
        $errores++;
        continue;
    }
    try {
        $stmt->execute([
            ':fecha'     => $reg['fecha'],
            ':hora'      => $reg['hora'],
            ':camara_id' => (int)$reg['camara_id'],
            ':estado'    => $reg['estado'],
        ]);
        $stmt->rowCount() > 0 ? $insertados++ : $ignorados++;
    } catch (PDOException $e) {
        $errores++;
        sync_log(LOG_FILE, 'Error fila: ' . $e->getMessage());
    }
}

sync_log(LOG_FILE, sprintf(
    '[%s] insertados=%d | ignorados=%d | errores=%d | total=%d',
    date('Y-m-d H:i:s'), $insertados, $ignorados, $errores, count($registros)
));

sync_response([
    'ok'         => true,
    'insertados' => $insertados,
    'ignorados'  => $ignorados,
    'errores'    => $errores,
    'total'      => count($registros),
]);
