<?php
/**
 * ============================================================
 *  ENDPOINT: sync_temperaturas.php
 *  Recibe datos desde Google Apps Script y los carga en MySQL
 *  Grupo CINA
 * ============================================================
 */

require_once __DIR__ . '/sync_helper.php';

define('LOG_FILE', __DIR__ . '/sync_log.txt');

// ── Validar request ──────────────────────────────────────────
$data = sync_init();

$registros = $data['registros'] ?? [];
$archivo   = $data['archivo']   ?? 'desconocido';

if (empty($registros)) {
    sync_response(['insertados' => 0, 'ignorados' => 0, 'msg' => 'Sin registros']);
}

// ── Conectar MySQL ───────────────────────────────────────────
try {
    $pdo = sync_pdo();
} catch (PDOException $e) {
    http_response_code(500);
    sync_log(LOG_FILE, 'Error DB: ' . $e->getMessage());
    sync_response(['error' => 'Error de conexión a la base de datos']);
}

// ── Insertar registros ───────────────────────────────────────
$sql = "INSERT IGNORE INTO temperaturas
        (archivo, fecha, hora, camara, temperatura, pres_succ_30, pres_succ_40, pres_desc)
        VALUES
        (:archivo, :fecha, :hora, :camara, :temperatura, :pres_succ_30, :pres_succ_40, :pres_desc)";
$stmt = $pdo->prepare($sql);

$insertados = 0;
$ignorados  = 0;
$errores    = 0;

foreach ($registros as $reg) {
    if (empty($reg['fecha']) || empty($reg['hora']) || empty($reg['camara'])) {
        $errores++;
        continue;
    }

    try {
        $stmt->execute([
            ':archivo'      => substr($reg['archivo'] ?? $archivo, 0, 40),
            ':fecha'        => $reg['fecha'],
            ':hora'         => $reg['hora'],
            ':camara'       => substr($reg['camara'], 0, 40),
            ':temperatura'  => isset($reg['temperatura'])  && $reg['temperatura']  !== '' ? (float)$reg['temperatura']  : null,
            ':pres_succ_30' => isset($reg['pres_succ_30']) && $reg['pres_succ_30'] !== '' ? (float)$reg['pres_succ_30'] : null,
            ':pres_succ_40' => isset($reg['pres_succ_40']) && $reg['pres_succ_40'] !== '' ? (float)$reg['pres_succ_40'] : null,
            ':pres_desc'    => isset($reg['pres_desc'])    && $reg['pres_desc']    !== '' ? (float)$reg['pres_desc']    : null,
        ]);
        $stmt->rowCount() > 0 ? $insertados++ : $ignorados++;
    } catch (PDOException $e) {
        $errores++;
        sync_log(LOG_FILE, 'Error fila: ' . $e->getMessage());
    }
}

sync_log(LOG_FILE, sprintf(
    '[%s] archivo=%s | insertados=%d | ignorados=%d | errores=%d',
    date('Y-m-d H:i:s'), $archivo, $insertados, $ignorados, $errores
));

sync_response([
    'ok'         => true,
    'archivo'    => $archivo,
    'insertados' => $insertados,
    'ignorados'  => $ignorados,
    'errores'    => $errores,
    'total'      => count($registros),
]);
