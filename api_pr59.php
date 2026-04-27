<?php
/**
 * ============================================================
 *  api_pr59.php — PR 59: Registro Control y Puesta en Marcha
 *  de Generadores (775 KVA y 343 KVA)
 *  Grupo CINA
 * ============================================================
 *  Tabla: pr59_registro (auto-creada al primer request)
 *
 *  Endpoints (todos con ?action=...):
 *    pr59_lista        GET   — historial con paginación y filtro opcional por generador
 *    pr59_detalle      GET   — un registro puntual
 *    pr59_guardar      POST  — crea un registro
 *    pr59_eliminar     POST  — soft delete (solo rol admin)
 * ============================================================
 */

require_once dirname(__DIR__) . '/config.php';

header('Content-Type: application/json; charset=utf-8');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (empty($_SESSION['cina_usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Sesión expirada. Recargá la página.']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error conexión BD']);
    exit;
}

// ═════════════════════════════════════════════════════════════
//  Migración idempotente
// ═════════════════════════════════════════════════════════════
$pdo->exec("
  CREATE TABLE IF NOT EXISTS pr59_registro (
    id INT PRIMARY KEY AUTO_INCREMENT,
    generador ENUM('775 KVA','343 KVA') NOT NULL,
    fecha DATE NOT NULL,
    /* Controles ANTES de la puesta en marcha — Estado B (Bien) / M (Mal) */
    nivel_aceite           CHAR(1) NULL,
    nivel_aceite_obs       VARCHAR(255) NULL,
    nivel_agua_radiador    CHAR(1) NULL,
    nivel_agua_obs         VARCHAR(255) NULL,
    nivel_electrolito      CHAR(1) NULL,
    nivel_electrolito_obs  VARCHAR(255) NULL,
    cargador_baterias      CHAR(1) NULL,
    cargador_baterias_obs  VARCHAR(255) NULL,
    nivel_gasoil           CHAR(1) NULL,
    nivel_gasoil_obs       VARCHAR(255) NULL,
    horas_trabajo          VARCHAR(40) NULL,
    horas_trabajo_obs      VARCHAR(255) NULL,
    proximo_service        VARCHAR(60) NULL,
    proximo_service_obs    VARCHAR(255) NULL,
    /* Puesta en marcha + 10 minutos */
    presion_aceite         CHAR(1) NULL,
    presion_aceite_obs     VARCHAR(255) NULL,
    temp_refrigerante      CHAR(1) NULL,
    temp_refrigerante_obs  VARCHAR(255) NULL,
    perdida_fluidos        CHAR(1) NULL,
    perdida_fluidos_obs    VARCHAR(255) NULL,
    regulacion_voltaje     CHAR(1) NULL,
    regulacion_voltaje_obs VARCHAR(255) NULL,
    carga_baterias         CHAR(1) NULL,
    carga_baterias_obs     VARCHAR(255) NULL,
    /* Cierre */
    otras_fallas           TEXT NULL,
    observaciones          TEXT NULL,
    realizo_nombre         VARCHAR(120) NULL,
    firma                  VARCHAR(120) NULL,
    usuario_id             INT NULL,
    usuario_nombre         VARCHAR(120) NULL,
    created_at             DATETIME NOT NULL,
    activo                 TINYINT(1) NOT NULL DEFAULT 1,
    KEY idx_gen_fecha (generador, fecha),
    KEY idx_activo (activo)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ═════════════════════════════════════════════════════════════
//  Helpers
// ═════════════════════════════════════════════════════════════
function resp($code, $payload) {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
function userInfo() {
    return [
        'id'     => (int)($_SESSION['cina_usuario_id'] ?? 0),
        'nombre' => $_SESSION['cina_nombre'] ?? $_SESSION['cina_usuario'] ?? 'Sistema',
        'rol_id' => (int)($_SESSION['cina_rol_id'] ?? 0),
    ];
}
function esB($v) { $v = strtoupper(trim((string)$v)); return in_array($v, ['B','M']) ? $v : null; }

// Lista de campos editables (para INSERT / validación)
function camposPR59(): array {
    return [
        // Antes de puesta en marcha
        'nivel_aceite','nivel_aceite_obs',
        'nivel_agua_radiador','nivel_agua_obs',
        'nivel_electrolito','nivel_electrolito_obs',
        'cargador_baterias','cargador_baterias_obs',
        'nivel_gasoil','nivel_gasoil_obs',
        'horas_trabajo','horas_trabajo_obs',
        'proximo_service','proximo_service_obs',
        // Puesta en marcha
        'presion_aceite','presion_aceite_obs',
        'temp_refrigerante','temp_refrigerante_obs',
        'perdida_fluidos','perdida_fluidos_obs',
        'regulacion_voltaje','regulacion_voltaje_obs',
        'carga_baterias','carga_baterias_obs',
        // Cierre
        'otras_fallas','observaciones','realizo_nombre','firma',
    ];
}
// Cuáles se normalizan a B/M
function camposBM(): array {
    return ['nivel_aceite','nivel_agua_radiador','nivel_electrolito','cargador_baterias','nivel_gasoil',
            'presion_aceite','temp_refrigerante','perdida_fluidos','regulacion_voltaje','carga_baterias'];
}

// ═════════════════════════════════════════════════════════════
//  Enrutador
// ═════════════════════════════════════════════════════════════
$action = $_GET['action'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($action) {

    case 'pr59_lista': {
        $generador = trim($_GET['generador'] ?? '');
        $desde     = trim($_GET['desde'] ?? '');
        $hasta     = trim($_GET['hasta'] ?? '');
        $limite    = min((int)($_GET['limite'] ?? 100), 500);
        $where = "activo = 1";
        $params = [];
        if (in_array($generador, ['775 KVA','343 KVA'])) { $where .= " AND generador = ?"; $params[] = $generador; }
        if ($desde) { $where .= " AND fecha >= ?"; $params[] = $desde; }
        if ($hasta) { $where .= " AND fecha <= ?"; $params[] = $hasta; }
        $sql = "SELECT id, generador, fecha, realizo_nombre, usuario_nombre, created_at,
                       otras_fallas, observaciones
                FROM pr59_registro
                WHERE $where
                ORDER BY fecha DESC, id DESC
                LIMIT $limite";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        resp(200, ['registros' => $stmt->fetchAll()]);
        break;
    }

    case 'pr59_detalle': {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) resp(400, ['error' => 'id requerido']);
        $st = $pdo->prepare("SELECT * FROM pr59_registro WHERE id=? AND activo=1");
        $st->execute([$id]);
        $reg = $st->fetch();
        if (!$reg) resp(404, ['error' => 'No encontrado']);
        resp(200, ['registro' => $reg]);
        break;
    }

    case 'pr59_guardar': {
        $u = userInfo();
        $generador = trim($body['generador'] ?? '');
        $fecha     = trim($body['fecha'] ?? date('Y-m-d'));
        if (!in_array($generador, ['775 KVA','343 KVA'])) resp(400, ['error' => 'generador inválido (775 KVA | 343 KVA)']);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) resp(400, ['error' => 'fecha inválida (YYYY-MM-DD)']);

        $cols = ['generador', 'fecha'];
        $vals = [$generador, $fecha];

        $bmSet = array_flip(camposBM());
        foreach (camposPR59() as $campo) {
            $v = $body[$campo] ?? null;
            if ($v === '') $v = null;
            if (isset($bmSet[$campo])) $v = esB($v); // normaliza a B/M o null
            $cols[] = $campo;
            $vals[] = $v;
        }
        $cols[] = 'usuario_id';       $vals[] = $u['id'];
        $cols[] = 'usuario_nombre';   $vals[] = $u['nombre'];
        $cols[] = 'created_at';       $vals[] = date('Y-m-d H:i:s');

        $ph = implode(',', array_fill(0, count($cols), '?'));
        $colsQ = implode(',', array_map(fn($c)=>"`$c`", $cols));
        $pdo->prepare("INSERT INTO pr59_registro ($colsQ) VALUES ($ph)")->execute($vals);
        resp(200, ['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
        break;
    }

    case 'pr59_eliminar': {
        // Soft delete, solo admins
        $u = userInfo();
        if (!in_array($u['rol_id'], [1,5,10])) resp(403, ['error' => 'Sin permisos']);
        $id = (int)($body['id'] ?? 0);
        if (!$id) resp(400, ['error' => 'id requerido']);
        $pdo->prepare("UPDATE pr59_registro SET activo=0 WHERE id=?")->execute([$id]);
        resp(200, ['ok' => true]);
        break;
    }

    default:
        resp(400, ['error' => 'action inválido']);
}
