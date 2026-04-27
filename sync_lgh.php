<?php
/**
 * ============================================================
 *  ENDPOINT: sync_lgh.php
 *  Recibe datos desde Google Apps Script y los carga en MySQL
 *  Grupo CINA — Temperaturas SCADA (.lgh)
 * ============================================================
 */

require_once __DIR__ . '/sync_helper.php';

define('LOG_FILE', __DIR__ . '/sync_lgh_log.txt');

// ── Validar request ──────────────────────────────────────────
$data = sync_init();

$temperaturas = $data['temperaturas'] ?? [];
$compresores  = $data['compresores']  ?? [];

if (empty($temperaturas) && empty($compresores)) {
    sync_response(['msg' => 'Sin datos']);
}

// ── Conectar MySQL ───────────────────────────────────────────
try {
    $pdo = sync_pdo();
} catch (PDOException $e) {
    http_response_code(500);
    sync_log(LOG_FILE, 'Error DB: ' . $e->getMessage());
    sync_response(['error' => 'Error de conexión']);
}

// ── Insertar temperaturas ────────────────────────────────────
$sqlTemp = "INSERT IGNORE INTO temperaturas
                (archivo, fecha, hora, camara, temperatura, setpoint, estado,
                 pres_succ_30, pres_succ_40, pres_desc)
            VALUES
                (:archivo, :fecha, :hora, :camara, :temperatura, :setpoint, :estado,
                 :pres_succ_30, :pres_succ_40, :pres_desc)";

$stmtTemp       = $pdo->prepare($sqlTemp);
$tempInsertados = 0;
$tempIgnorados  = 0;

foreach ($temperaturas as $reg) {
    if (empty($reg['fecha']) || empty($reg['hora']) || empty($reg['camara'])) continue;
    try {
        $stmtTemp->execute([
            ':archivo'      => substr($reg['archivo'] ?? '', 0, 40),
            ':fecha'        => $reg['fecha'],
            ':hora'         => $reg['hora'],
            ':camara'       => substr($reg['camara'], 0, 40),
            ':temperatura'  => isset($reg['temperatura'])  && $reg['temperatura']  !== null ? (float)$reg['temperatura']  : null,
            ':setpoint'     => isset($reg['setpoint'])     && $reg['setpoint']     !== null ? (float)$reg['setpoint']     : null,
            ':estado'       => $reg['estado'] ?? null,
            ':pres_succ_30' => isset($reg['pres_succ_30']) && $reg['pres_succ_30'] !== null ? (float)$reg['pres_succ_30'] : null,
            ':pres_succ_40' => isset($reg['pres_succ_40']) && $reg['pres_succ_40'] !== null ? (float)$reg['pres_succ_40'] : null,
            ':pres_desc'    => isset($reg['pres_desc'])    && $reg['pres_desc']    !== null ? (float)$reg['pres_desc']    : null,
        ]);
        $stmtTemp->rowCount() > 0 ? $tempInsertados++ : $tempIgnorados++;
    } catch (PDOException $e) {
        sync_log(LOG_FILE, 'Error temp: ' . $e->getMessage());
    }
}

// ── Insertar compresores ─────────────────────────────────────
$sqlComp = "INSERT IGNORE INTO compresores (fecha, hora, compresor, estado)
            VALUES (:fecha, :hora, :compresor, :estado)";

$stmtComp       = $pdo->prepare($sqlComp);
$compInsertados = 0;
$compIgnorados  = 0;

foreach ($compresores as $reg) {
    if (empty($reg['fecha']) || empty($reg['hora']) || empty($reg['compresor'])) continue;
    try {
        $stmtComp->execute([
            ':fecha'     => $reg['fecha'],
            ':hora'      => $reg['hora'],
            ':compresor' => $reg['compresor'],
            ':estado'    => $reg['estado'],
        ]);
        $stmtComp->rowCount() > 0 ? $compInsertados++ : $compIgnorados++;
    } catch (PDOException $e) {
        sync_log(LOG_FILE, 'Error comp: ' . $e->getMessage());
    }
}

sync_log(LOG_FILE, sprintf(
    '[%s] temp=%d/%d | comp=%d/%d',
    date('Y-m-d H:i:s'),
    $tempInsertados, $tempIgnorados,
    $compInsertados, $compIgnorados
));

sync_response([
    'ok'               => true,
    'temp_insertadas'  => $tempInsertados,
    'temp_ignoradas'   => $tempIgnorados,
    'comp_insertados'  => $compInsertados,
    'comp_ignorados'   => $compIgnorados,
]);
