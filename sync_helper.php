<?php
/**
 * ============================================================
 *  sync_helper.php — Lógica compartida para endpoints de sync
 *  Grupo CINA — Corrección 2.6
 * ============================================================
 *  Ubicar en: /public_html/sync_helper.php
 *  Lo usan: sync_temperaturas.php, sync_lgh.php, sync_puertas.php
 * ============================================================
 */

// Cargar config central (credenciales, secrets)
$config_path = dirname(__DIR__) . '/config.php';
if (file_exists($config_path)) {
    require_once $config_path;
}
// Usar SYNC_SECRET del config.php, o fallback
if (!defined('SYNC_SECRET')) {
    define('SYNC_SECRET', '_cina_sistema_jcpc');
}

/**
 * Valida request POST, parsea JSON, verifica secret
 * Retorna el array de datos o termina con error
 */
function sync_init(): array {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    $body = file_get_contents('php://input');
    $data = json_decode($body, true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'JSON inválido']);
        exit;
    }

    if (($data['secret'] ?? '') !== SYNC_SECRET) {
        http_response_code(403);
        echo json_encode(['error' => 'No autorizado']);
        exit;
    }

    return $data;
}

/**
 * Crea conexión PDO con las credenciales del config.php
 */
function sync_pdo(): PDO {
    return new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

/**
 * Escribe en el log del sync
 */
function sync_log(string $logFile, string $msg): void {
    file_put_contents($logFile, $msg . "\n", FILE_APPEND);
}

/**
 * Responde JSON y termina
 */
function sync_response(array $data): never {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
