<?php
/**
 * api_planificacion.php — Módulo Planificación Semanal
 * Grupo CINA 2026
 */

require_once dirname(__DIR__) . '/config.php';
if (!defined('SECRET_KEY')) define('SECRET_KEY', SESSION_SECRET);

header('Content-Type: application/json; charset=utf-8');
if (defined('ALLOWED_ORIGIN') && ALLOWED_ORIGIN !== '') {
    header('Access-Control-Allow-Origin: ' . ALLOWED_ORIGIN);
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

session_start();

function resp(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function requireSession(): void {
    if (empty($_SESSION['cina_usuario_id'])) resp(401, ['error' => 'No autenticado']);
}
function userId(): int { return (int)($_SESSION['cina_usuario_id'] ?? 0); }
function userRol(): int { return (int)($_SESSION['cina_rol_id'] ?? 0); }

// Roles que pueden gestionar planificación (operaciones)
function esOperaciones(): bool {
    return in_array(userRol(), [1,2,3,4,5,6,9,15,16,19,30,32]);
}

try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    resp(500, ['error' => 'Error de conexión: ' . $e->getMessage()]);
}

$action = $_GET['action'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// Franjas horarias
$FRANJAS = [
    0 => '00:00 – 03:00',
    1 => '03:00 – 06:00',
    2 => '06:00 – 09:00',
    3 => '09:00 – 12:00',
    4 => '12:00 – 15:00',
    5 => '15:00 – 18:00',
    6 => '18:00 – 21:00',
    7 => '21:00 – 24:00',
];

switch ($action) {

    // ══════════════════════════════════════════════════════════
    //  SEMANAS
    // ══════════════════════════════════════════════════════════

    // GET: semanas disponibles + crear faltantes automáticamente
    case 'semanas':
        requireSession();
        try {
            // Crear semanas faltantes para las próximas 8 semanas
            $lunes = new DateTime();
            $lunes->modify('this week monday');
            for ($i = 0; $i < 8; $i++) {
                $inicio  = $lunes->format('Y-m-d');
                $fin     = (clone $lunes)->modify('+6 days')->format('Y-m-d');
                // Cierre: viernes 10:00 de la semana ANTERIOR
                $viernes = (clone $lunes)->modify('-3 days')->format('Y-m-d') . ' 10:00:00';
                $estado  = $viernes < date('Y-m-d H:i:s') ? 'cerrada' : 'abierta';
                $pdo->prepare("
                    INSERT IGNORE INTO planificacion_semana (fecha_inicio, fecha_fin, fecha_cierre, estado)
                    VALUES (?,?,?,?)
                ")->execute([$inicio, $fin, $viernes, $estado]);
                $lunes->modify('+7 days');
            }

            // Actualizar estados automáticamente
            $pdo->query("
                UPDATE planificacion_semana SET estado = 'cerrada'
                WHERE fecha_cierre < NOW() AND estado = 'abierta'
            ");

            $semanas = $pdo->query("
                SELECT s.*,
                       COUNT(t.turno_id) AS total_turnos,
                       SUM(t.estado = 'pendiente') AS pendientes,
                       SUM(t.estado = 'aceptado')  AS aceptados,
                       SUM(t.estado = 'rechazado') AS rechazados,
                       SUM(t.estado = 'asignado')  AS asignados
                FROM planificacion_semana s
                LEFT JOIN planificacion_turno t ON t.semana_id = s.semana_id
                WHERE s.fecha_inicio >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                GROUP BY s.semana_id
                ORDER BY s.fecha_inicio ASC
                LIMIT 10
            ")->fetchAll();

            resp(200, ['semanas' => $semanas]);
        } catch (Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    // ══════════════════════════════════════════════════════════
    //  TIPOS DE VEHÍCULO Y ACOPLADO
    // ══════════════════════════════════════════════════════════

    case 'debug_tipos':
        requireSession();
        try {
            $cols = $pdo->query("
                SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME IN ('transporte_vehiculo_tipo','transporte_acoplado_tipo','carga_tratamiento')
                ORDER BY TABLE_NAME, ORDINAL_POSITION
            ")->fetchAll();
            $sample_v = $pdo->query("SELECT * FROM transporte_vehiculo_tipo LIMIT 3")->fetchAll();
            $sample_a = $pdo->query("SELECT * FROM transporte_acoplado_tipo LIMIT 3")->fetchAll();
            resp(200, ['cols'=>$cols,'vehiculo_sample'=>$sample_v,'acoplado_sample'=>$sample_a]);
        } catch(Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'tipos_transporte':
        requireSession();
        try {
            // ── Columna nombre: probar descripcion, denominacion, nombre en orden ──
            $colCandidates = ['descripcion','denominacion','nombre','tipo_nombre'];
            $colVeh = 'id'; $colAcop = 'id';

            // Detectar columna para vehiculo_tipo
            $cols = $pdo->query("
                SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transporte_vehiculo_tipo'
            ")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($colCandidates as $c) { if (in_array($c,$cols)) { $colVeh=$c; break; } }

            // Detectar columna para acoplado_tipo
            $cols2 = $pdo->query("
                SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transporte_acoplado_tipo'
            ")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($colCandidates as $c) { if (in_array($c,$cols2)) { $colAcop=$c; break; } }

            // Verificar si tiene columna activo
            $hasActivo  = in_array('activo',$cols);
            $hasActivo2 = in_array('activo',$cols2);

            $vehiculos = $pdo->query("
                SELECT vehiculo_tipo_id AS id, $colVeh AS nombre
                FROM transporte_vehiculo_tipo
                " . ($hasActivo ? "WHERE activo = 1" : "") . "
                ORDER BY $colVeh
            ")->fetchAll();

            $acoplados = $pdo->query("
                SELECT acoplado_tipo_id AS id, $colAcop AS nombre
                FROM transporte_acoplado_tipo
                " . ($hasActivo2 ? "WHERE activo = 1" : "") . "
                ORDER BY $colAcop
            ")->fetchAll();

            // ── carga_tratamiento ──
            try {
                $tratamientos = $pdo->query("
                    SELECT carga_tratamiento_id AS id, descripcion AS nombre
                    FROM carga_tratamiento
                    ORDER BY descripcion
                ")->fetchAll();
            } catch(Exception $e) { $tratamientos = []; }

            resp(200, [
                'vehiculos'    => $vehiculos,
                'acoplados'    => $acoplados,
                'tratamientos' => $tratamientos,
            ]);
        } catch (Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    // ══════════════════════════════════════════════════════════
    //  CLIENTES DEL USUARIO
    // ══════════════════════════════════════════════════════════

    case 'mis_clientes':
        requireSession();
        try {
            $uid = userId();
            // Verificar si el usuario tiene clientes asignados en usuario_cliente
            $tieneClientes = $pdo->prepare("SELECT COUNT(*) FROM usuario_cliente WHERE usuario_id=?");
            $tieneClientes->execute([$uid]);
            $esCliente = (int)$tieneClientes->fetchColumn() > 0;

            if (esOperaciones()) {
                // Operaciones ve todos los clientes
                $clientes = $pdo->query("
                    SELECT ClienteID AS id, ClienteNombre AS nombre, fiscal
                    FROM CLIENTES WHERE Activo = 1
                    ORDER BY ClienteNombre
                ")->fetchAll();
            } elseif ($esCliente) {
                // Usuario cliente: solo sus clientes asignados
                $stmt = $pdo->prepare("
                    SELECT C.ClienteID AS id, C.ClienteNombre AS nombre, C.fiscal
                    FROM CLIENTES C
                    JOIN usuario_cliente UC ON UC.cliente_id = C.ClienteID
                    WHERE UC.usuario_id = ? AND C.Activo = 1
                    ORDER BY C.ClienteNombre
                ");
                $stmt->execute([$uid]);
                $clientes = $stmt->fetchAll();
            } else {
                // Empleado sin clientes asignados: no accede al módulo cliente
                $clientes = [];
            }

            resp(200, [
                'clientes'    => $clientes,
                'es_cliente'  => $esCliente && !esOperaciones(),
                'es_operaciones' => esOperaciones(),
            ]);
        } catch (Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    // ══════════════════════════════════════════════════════════
    //  TURNOS — LISTA
    // ══════════════════════════════════════════════════════════

    case 'turnos_lista':
        requireSession();
        try {
            $semanaId  = intval($_GET['semana_id'] ?? 0);
            $clienteId = intval($_GET['cliente_id'] ?? 0);
            $uid = userId();

            $where = ['1=1'];
            $params = [];

            if ($semanaId) { $where[] = 't.semana_id = ?'; $params[] = $semanaId; }
            if ($clienteId) { $where[] = 't.cliente_id = ?'; $params[] = $clienteId; }

            // Si no es operaciones: filtrar solo sus clientes
            if (!esOperaciones()) {
                $where[] = 't.cliente_id IN (SELECT cliente_id FROM usuario_cliente WHERE usuario_id = ?)';
                $params[] = $uid;
            }

            $whereStr = implode(' AND ', $where);
            $stmt = $pdo->prepare("
                SELECT t.*,
                       C.ClienteNombre AS cliente_nombre,
                       C.fiscal        AS cliente_fiscal,
                       s.fecha_inicio  AS semana_inicio,
                       s.fecha_cierre  AS semana_cierre,
                       s.estado        AS semana_estado,
                       (SELECT COUNT(*) FROM planificacion_documento d WHERE d.turno_id = t.turno_id) AS cant_docs
                FROM planificacion_turno t
                JOIN planificacion_semana s ON s.semana_id = t.semana_id
                JOIN CLIENTES C ON C.ClienteID = t.cliente_id
                WHERE $whereStr
                ORDER BY t.fecha ASC, t.franja ASC, C.ClienteNombre ASC
            ");
            $stmt->execute($params);
            $turnos = $stmt->fetchAll();

            resp(200, ['turnos' => $turnos]);
        } catch (Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    // ══════════════════════════════════════════════════════════
    //  TURNOS — CREAR (cliente solicita)
    // ══════════════════════════════════════════════════════════

    case 'turno_crear':
        requireSession();
        try {
            $uid = userId();
            $semanaId  = intval($body['semana_id'] ?? 0);
            $clienteId = intval($body['cliente_id'] ?? 0);
            $fecha     = $body['fecha'] ?? '';
            $franja    = intval($body['franja'] ?? 0);
            $tipo      = in_array($body['tipo'] ?? '', ['N','F']) ? $body['tipo'] : 'N';
            $entrada   = intval($body['entrada'] ?? 1);
            $tipoTransporte = trim($body['tipo_transporte'] ?? '');
            $requiereAcond  = intval($body['requiere_acondicionamiento'] ?? 0);
            $notas     = trim($body['notas_cliente'] ?? '');
            $cantidad  = max(1, intval($body['cantidad'] ?? 1));

            if (!$semanaId || !$clienteId || !$fecha) resp(400, ['error' => 'Faltan datos obligatorios']);

            // Verificar que el cliente es accesible para el usuario
            if (!esOperaciones()) {
                $check = $pdo->prepare("SELECT 1 FROM usuario_cliente WHERE usuario_id=? AND cliente_id=?");
                $check->execute([$uid, $clienteId]);
                if (!$check->fetch()) resp(403, ['error' => 'Sin acceso a ese cliente']);
            }

            // Verificar semana abierta (fuera_programa si cerrada)
            $semana = $pdo->prepare("SELECT * FROM planificacion_semana WHERE semana_id=?");
            $semana->execute([$semanaId]);
            $sem = $semana->fetch();
            if (!$sem) resp(404, ['error' => 'Semana no encontrada']);
            $fueraProg = ($sem['estado'] === 'cerrada') ? 1 : 0;

            // Crear turno
            $ids = [];
            $tipoVehId  = intval($body['tipo_vehiculo_id'] ?? 0) ?: null;
            $tipoAcopId = intval($body['tipo_acoplado_id'] ?? 0) ?: null;
            $tratamId   = !empty($body['tratamiento_id']) ? trim($body['tratamiento_id']) : null;
            if (!$tratamId) resp(400, ['error' => 'El tratamiento es obligatorio']);

            $stmt = $pdo->prepare("
                INSERT INTO planificacion_turno
                  (semana_id, fecha, franja, tipo, entrada,
                   tipo_vehiculo_id, tipo_acoplado_id, tipo_transporte, tratamiento_id,
                   cliente_id, estado, fuera_programa,
                   notas_cliente, usuario_solicita_id)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            for ($i = 0; $i < $cantidad; $i++) {
                $stmt->execute([
                    $semanaId, $fecha, $franja, $tipo, $entrada,
                    $tipoVehId, $tipoAcopId, $tipoTransporte, $tratamId,
                    $clienteId, 'pendiente', $fueraProg,
                    $notas, $uid
                ]);
                $ids[] = (int)$pdo->lastInsertId();
            }

            $msg = $fueraProg
                ? "Turno agregado FUERA DE PROGRAMA (semana cerrada)"
                : "Turno solicitado correctamente";

            resp(201, ['ok' => true, 'ids' => $ids, 'fuera_programa' => $fueraProg, 'mensaje' => $msg]);
        } catch (Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    // ══════════════════════════════════════════════════════════
    //  TURNOS — GESTIONAR (operaciones acepta/rechaza/modifica)
    // ══════════════════════════════════════════════════════════

    case 'turno_gestionar':
        requireSession();
        if (!esOperaciones()) resp(403, ['error' => 'Sin permisos']);
        try {
            $turnoId         = intval($body['turno_id'] ?? 0);
            $estado          = $body['estado'] ?? null;   // aceptado|rechazado|asignado
            $franjaModif     = isset($body['franja_modificada']) ? intval($body['franja_modificada']) : null;
            $notasOp         = trim($body['notas_operaciones'] ?? '');
            $cargaNumero     = isset($body['carga_numero']) ? intval($body['carga_numero']) : null;

            if (!$turnoId) resp(400, ['error' => 'Falta turno_id']);

            $sets = ['usuario_gestiona_id = ?', 'updated_at = NOW()'];
            $params = [userId()];

            if ($estado && in_array($estado, ['pendiente','aceptado','rechazado','asignado'])) {
                $sets[] = 'estado = ?'; $params[] = $estado;
            }
            if ($franjaModif !== null) {
                $sets[] = 'franja_modificada = ?'; $params[] = $franjaModif;
            }
            if ($notasOp !== '') {
                $sets[] = 'notas_operaciones = ?'; $params[] = $notasOp;
            }
            if ($cargaNumero) {
                $sets[] = 'carga_numero = ?'; $params[] = $cargaNumero;
                $sets[] = "estado = 'asignado'";
            }

            $params[] = $turnoId;
            $pdo->prepare("UPDATE planificacion_turno SET " . implode(', ', $sets) . " WHERE turno_id = ?")->execute($params);

            resp(200, ['ok' => true, 'mensaje' => 'Turno actualizado']);
        } catch (Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    // ══════════════════════════════════════════════════════════
    //  TURNOS — ELIMINAR
    // ══════════════════════════════════════════════════════════

    case 'turno_eliminar':
        requireSession();
        try {
            $turnoId = intval($body['turno_id'] ?? 0);
            if (!$turnoId) resp(400, ['error' => 'Falta turno_id']);

            // Verificar que el turno pertenece al usuario (si no es operaciones)
            if (!esOperaciones()) {
                $check = $pdo->prepare("SELECT usuario_solicita_id, estado FROM planificacion_turno WHERE turno_id=?");
                $check->execute([$turnoId]);
                $t = $check->fetch();
                if (!$t) resp(404, ['error' => 'No encontrado']);
                if ($t['usuario_solicita_id'] != userId()) resp(403, ['error' => 'Sin permisos']);
                if (in_array($t['estado'], ['asignado'])) resp(400, ['error' => 'No se puede eliminar un turno asignado']);
            }

            $pdo->prepare("DELETE FROM planificacion_documento WHERE turno_id=?")->execute([$turnoId]);
            $pdo->prepare("DELETE FROM planificacion_turno WHERE turno_id=?")->execute([$turnoId]);

            resp(200, ['ok' => true, 'mensaje' => 'Turno eliminado']);
        } catch (Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    // ══════════════════════════════════════════════════════════
    //  DOCUMENTOS — SUBIR
    // ══════════════════════════════════════════════════════════

    case 'doc_subir':
        requireSession();
        try {
            $turnoId = intval($_POST['turno_id'] ?? 0);
            if (!$turnoId || !isset($_FILES['archivo'])) resp(400, ['error' => 'Faltan datos']);

            $file = $_FILES['archivo'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $tipo = in_array($ext, ['pdf']) ? 'pdf' : (in_array($ext, ['xls','xlsx']) ? 'excel' : 'otro');

            // Directorio de uploads
            $dir = dirname(__DIR__) . '/uploads/planificacion/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            $nombre = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
            $path   = 'uploads/planificacion/' . $nombre;

            if (!move_uploaded_file($file['tmp_name'], dirname(__DIR__) . '/' . $path)) {
                resp(500, ['error' => 'Error al guardar archivo']);
            }

            $pdo->prepare("
                INSERT INTO planificacion_documento (turno_id, nombre, tipo, path, tamaño, usuario_id)
                VALUES (?,?,?,?,?,?)
            ")->execute([$turnoId, $file['name'], $tipo, $path, $file['size'], userId()]);

            $docId = (int)$pdo->lastInsertId();
            resp(201, ['ok' => true, 'doc_id' => $docId, 'nombre' => $file['name'], 'tipo' => $tipo, 'path' => $path]);
        } catch (Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    // ══════════════════════════════════════════════════════════
    //  DOCUMENTOS — LISTA
    // ══════════════════════════════════════════════════════════

    case 'docs_turno':
        requireSession();
        try {
            $turnoId = intval($_GET['turno_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM planificacion_documento WHERE turno_id=? ORDER BY created_at DESC");
            $stmt->execute([$turnoId]);
            resp(200, ['docs' => $stmt->fetchAll()]);
        } catch (Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    // ══════════════════════════════════════════════════════════
    //  CARGAS DISPONIBLES PARA ASIGNAR (sin turno)
    // ══════════════════════════════════════════════════════════

    case 'cargas_sin_turno':
        requireSession();
        if (!esOperaciones()) resp(403, ['error' => 'Sin permisos']);
        try {
            $fecha = $_GET['fecha'] ?? date('Y-m-d');
            $stmt  = $pdo->prepare("
                SELECT TC.carga_numero, TC.tipo, TC.entrada, TC.estado,
                       TV.patente AS vehiculo, TA.patente AS acoplado
                FROM transporte_carga TC
                LEFT JOIN transporte_vehiculo TV ON TV.vehiculo_id = TC.vehiculo
                LEFT JOIN transporte_acoplado TA ON TA.acoplado_id = TC.acoplado
                WHERE DATE(TC.fecha_llegada_planeada) = ?
                  AND TC.carga_numero NOT IN (
                      SELECT carga_numero FROM planificacion_turno
                      WHERE carga_numero IS NOT NULL AND fecha = ?
                  )
                ORDER BY TC.carga_numero DESC
            ");
            $stmt->execute([$fecha, $fecha]);
            resp(200, ['cargas' => $stmt->fetchAll()]);
        } catch (Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    // ══════════════════════════════════════════════════════════
    //  RESUMEN SEMANAL (para vista de operaciones)
    // ══════════════════════════════════════════════════════════

    case 'resumen_semana':
        requireSession();
        try {
            $semanaId = intval($_GET['semana_id'] ?? 0);
            if (!$semanaId) resp(400, ['error' => 'Falta semana_id']);

            $stmt = $pdo->prepare("
                SELECT
                    t.fecha,
                    t.franja,
                    t.tipo,
                    t.entrada,
                    t.requiere_acondicionamiento,
                    t.estado,
                    t.fuera_programa,
                    t.carga_numero,
                    t.turno_id,
                    t.notas_cliente,
                    t.notas_operaciones,
                    t.franja_modificada,
                    C.ClienteNombre AS cliente_nombre,
                    C.ClienteID     AS cliente_id,
                    t.tipo_transporte,
                    (SELECT COUNT(*) FROM planificacion_documento d WHERE d.turno_id = t.turno_id) AS cant_docs
                FROM planificacion_turno t
                JOIN CLIENTES C ON C.ClienteID = t.cliente_id
                WHERE t.semana_id = ?
                ORDER BY t.fecha ASC, t.franja ASC
            ");
            $stmt->execute([$semanaId]);
            $turnos = $stmt->fetchAll();

            // Agrupar por fecha → franja
            $grid = [];
            foreach ($turnos as $t) {
                $grid[$t['fecha']][] = $t;
            }

            resp(200, ['turnos' => $turnos, 'grid' => $grid]);
        } catch (Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    // ══════════════════════════════════════════════════════════
    //  TURNO EDITAR (cliente — solo estado pendiente)
    // ══════════════════════════════════════════════════════════

    case 'turno_editar_cliente':
        requireSession();
        try {
            $uid     = userId();
            $turnoId = intval($body['turno_id'] ?? 0);
            if (!$turnoId) resp(400, ['error' => 'Falta turno_id']);

            // Verificar que pertenece al usuario y está pendiente
            $check = $pdo->prepare("
                SELECT usuario_solicita_id, estado FROM planificacion_turno WHERE turno_id=?
            ");
            $check->execute([$turnoId]);
            $t = $check->fetch();
            if (!$t) resp(404, ['error' => 'Turno no encontrado']);
            if (!esOperaciones() && $t['usuario_solicita_id'] != $uid)
                resp(403, ['error' => 'Sin permisos']);
            if (!esOperaciones() && $t['estado'] !== 'pendiente')
                resp(400, ['error' => 'Solo se pueden editar turnos en estado pendiente']);

            $pdo->prepare("
                UPDATE planificacion_turno SET
                    fecha                       = ?,
                    franja                      = ?,
                    entrada                     = ?,
                    tipo                        = ?,
                    tipo_vehiculo_id            = ?,
                    tipo_acoplado_id            = ?,
                    tipo_transporte             = ?,
                    tratamiento_id              = ?,
                    notas_cliente               = ?,
                    updated_at                  = NOW()
                WHERE turno_id = ?
            ")->execute([
                $body['fecha']    ?? null,
                intval($body['franja'] ?? 0),
                intval($body['entrada'] ?? 1),
                in_array($body['tipo']??'',['N','F']) ? $body['tipo'] : 'N',
                intval($body['tipo_vehiculo_id'] ?? 0) ?: null,
                intval($body['tipo_acoplado_id'] ?? 0) ?: null,
                trim($body['tipo_transporte'] ?? ''),
                !empty($body['tratamiento_id']) ? trim($body['tratamiento_id']) : null,
                trim($body['notas_cliente'] ?? ''),
                $turnoId,
            ]);

            resp(200, ['ok' => true, 'mensaje' => 'Turno actualizado correctamente']);
        } catch (Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    // ══════════════════════════════════════════════════════════
    //  TURNOS ASIGNADOS SIN CARGA (para módulo cargas)
    // ══════════════════════════════════════════════════════════

    case 'turnos_sin_carga':
        requireSession();
        try {
            $fecha = $_GET['fecha'] ?? null;
            $tipo  = $_GET['tipo']  ?? null; // N o F

            $where  = ["t.estado = 'aceptado'", "t.carga_numero IS NULL"];
            $params = [];
            if ($fecha) { $where[] = 't.fecha = ?'; $params[] = $fecha; }
            if ($tipo)  { $where[] = 't.tipo = ?';  $params[] = $tipo; }

            $stmt = $pdo->prepare("
                SELECT t.turno_id, t.fecha, t.franja, t.tipo, t.entrada,
                       t.tipo_transporte, t.requiere_acondicionamiento, t.fuera_programa,
                       t.notas_cliente,
                       C.ClienteNombre AS cliente_nombre,
                       C.ClienteID     AS cliente_id,
                       s.fecha_inicio  AS semana_inicio
                FROM planificacion_turno t
                JOIN CLIENTES C ON C.ClienteID = t.cliente_id
                JOIN planificacion_semana s ON s.semana_id = t.semana_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY t.fecha ASC, t.franja ASC
            ");
            $stmt->execute($params);
            resp(200, ['turnos' => $stmt->fetchAll()]);
        } catch (Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    // Asignar carga a turno
    case 'asignar_carga_turno':
        requireSession();
        try {
            $turnoId     = intval($body['turno_id'] ?? 0);
            $cargaNumero = intval($body['carga_numero'] ?? 0);
            if (!$turnoId || !$cargaNumero) resp(400, ['error' => 'Faltan datos']);

            // Obtener datos del turno
            $stmtTurno = $pdo->prepare("
                SELECT t.*,
                       TV.vehiculo_id, TV.patente AS vehiculo_patente,
                       TA.acoplado_id, TA.patente AS acoplado_patente,
                       CONCAT('Box ', TB.box_asignado_id) AS box_label,
                       TB.box_asignado_id
                FROM planificacion_turno t
                LEFT JOIN transporte_vehiculo_tipo TVT ON TVT.vehiculo_tipo_id = t.tipo_vehiculo_id
                LEFT JOIN transporte_vehiculo TV ON TV.vehiculo_tipo_id = t.tipo_vehiculo_id AND TV.activo = 1
                LEFT JOIN transporte_acoplado_tipo TAT ON TAT.acoplado_tipo_id = t.tipo_acoplado_id
                LEFT JOIN transporte_acoplado TA ON TA.acoplado_tipo_id = t.tipo_acoplado_id AND TA.activo = 1
                LEFT JOIN transporte_box TB ON TB.carga_numero = ? AND TB.estado IN ('A','U')
                WHERE t.turno_id = ?
                LIMIT 1
            ");
            $stmtTurno->execute([$cargaNumero, $turnoId]);
            $turno = $stmtTurno->fetch();
            if (!$turno) resp(404, ['error' => 'Turno no encontrado']);

            // Calcular hora de llegada planeada desde franja horaria
            // Franja 0=00:00, 1=03:00, 2=06:00, 3=09:00, 4=12:00, 5=15:00, 6=18:00, 7=21:00
            $horaInicio = str_pad((int)$turno['franja'] * 3, 2, '0', STR_PAD_LEFT) . ':00:00';

            // Desasignar cualquier turno anterior de esa carga
            $pdo->prepare("
                UPDATE planificacion_turno SET carga_numero = NULL, estado = 'aceptado'
                WHERE carga_numero = ? AND turno_id != ?
            ")->execute([$cargaNumero, $turnoId]);

            // Asignar turno → carga
            $pdo->prepare("
                UPDATE planificacion_turno
                SET carga_numero = ?, estado = 'asignado', updated_at = NOW()
                WHERE turno_id = ?
            ")->execute([$cargaNumero, $turnoId]);

            // Pre-poblar transporte_carga con datos del turno
            // Solo actualiza campos que estén vacíos/null para no pisar datos ya cargados
            $updateFields = [];
            $updateVals   = [];

            // tipo (N/F) — siempre actualizar desde turno
            if (!empty($turno['tipo'])) {
                $updateFields[] = 'tipo = ?';
                $updateVals[]   = $turno['tipo'];
            }

            // entrada (1=entrada, 0=salida)
            $updateFields[] = 'entrada = ?';
            $updateVals[]   = (int)$turno['entrada'];

            // fecha_llegada_planeada
            if (!empty($turno['fecha'])) {
                $updateFields[] = 'fecha_llegada_planeada = ?';
                $updateVals[]   = $turno['fecha'];
            }

            // hora_llegada_planeada — desde franja, solo si no tiene
            $checkHora = $pdo->prepare("
                SELECT hora_llegada_planeada FROM transporte_carga WHERE carga_numero = ?
            ");
            $checkHora->execute([$cargaNumero]);
            $horaActual = $checkHora->fetchColumn();
            if (empty($horaActual) || $horaActual === '00:00:00') {
                $updateFields[] = 'hora_llegada_planeada = ?';
                $updateVals[]   = $horaInicio;
            }

            // vehiculo — solo si está vacío en transporte_carga y hay uno disponible del tipo
            if ($turno['vehiculo_id']) {
                $checkVeh = $pdo->prepare("SELECT vehiculo FROM transporte_carga WHERE carga_numero=?");
                $checkVeh->execute([$cargaNumero]);
                $vehActual = $checkVeh->fetchColumn();
                if (!$vehActual) {
                    $updateFields[] = 'vehiculo = ?';
                    $updateVals[]   = $turno['vehiculo_id'];
                }
            }

            // acoplado — solo si está vacío y hay uno disponible del tipo
            if ($turno['acoplado_id']) {
                $checkAcop = $pdo->prepare("SELECT acoplado FROM transporte_carga WHERE carga_numero=?");
                $checkAcop->execute([$cargaNumero]);
                $acopActual = $checkAcop->fetchColumn();
                if (!$acopActual) {
                    $updateFields[] = 'acoplado = ?';
                    $updateVals[]   = $turno['acoplado_id'];
                }
            }

            // tratamiento — copiar del turno a las Cargas vinculadas
            if (!empty($turno['tratamiento_id'])) {
                try {
                    $pdo->prepare("
                        UPDATE Cargas SET tratamiento_id = ?
                        WHERE carga_numero = ? AND (tratamiento_id IS NULL OR tratamiento_id = '')
                    ")->execute([$turno['tratamiento_id'], $cargaNumero]);
                } catch(Exception $eTrat) { /* columna puede no existir aún */ }
            }

            if (!empty($updateFields)) {
                $updateVals[] = $cargaNumero;
                $pdo->prepare("
                    UPDATE transporte_carga SET " . implode(', ', $updateFields) . "
                    WHERE carga_numero = ?
                ")->execute($updateVals);
            }

            // Resumen de lo que se pre-pobló
            $poblado = [
                'tipo'                 => $turno['tipo'] ?? null,
                'entrada'              => (int)$turno['entrada'],
                'fecha_llegada_plan'   => $turno['fecha'] ?? null,
                'hora_llegada_plan'    => $horaInicio,
                'vehiculo_patente'     => $turno['vehiculo_patente'] ?? null,
                'acoplado_patente'     => $turno['acoplado_patente'] ?? null,
                'franja_label'         => ['00-03','03-06','06-09','09-12','12-15','15-18','18-21','21-24'][$turno['franja']] ?? null,
                'tratamiento_id'       => $turno['tratamiento_id'] ?? null,
            ];

            resp(200, [
                'ok'      => true,
                'mensaje' => 'Turno asignado y carga actualizada correctamente',
                'poblado' => $poblado,
            ]);
        } catch (Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    // Obtener turno vinculado a una carga específica
    case 'turno_por_carga':
        requireSession();
        try {
            $cn = intval($_GET['carga_numero'] ?? 0);
            if (!$cn) resp(400, ['error' => 'Falta carga_numero']);

            $stmt = $pdo->prepare("
                SELECT t.*,
                       C.ClienteNombre AS cliente_nombre,
                       s.fecha_inicio  AS semana_inicio,
                       s.fecha_fin     AS semana_fin,
                       TVT.descripcion AS vehiculo_tipo_nombre,
                       TAT.descripcion AS acoplado_tipo_nombre,
                       CT.descripcion  AS tratamiento_nombre
                FROM planificacion_turno t
                JOIN CLIENTES C          ON C.ClienteID           = t.cliente_id
                JOIN planificacion_semana s ON s.semana_id         = t.semana_id
                LEFT JOIN transporte_vehiculo_tipo TVT ON TVT.vehiculo_tipo_id = t.tipo_vehiculo_id
                LEFT JOIN transporte_acoplado_tipo TAT ON TAT.acoplado_tipo_id = t.tipo_acoplado_id
                LEFT JOIN carga_tratamiento CT         ON CT.carga_tratamiento_id = t.tratamiento_id
                WHERE t.carga_numero = ?
                LIMIT 1
            ");
            $stmt->execute([$cn]);
            $turno = $stmt->fetch();

            // Calcular franja label
            $franjas = ['00:00–03:00','03:00–06:00','06:00–09:00','09:00–12:00',
                        '12:00–15:00','15:00–18:00','18:00–21:00','21:00–24:00'];
            if ($turno) {
                $turno['franja_label'] = $franjas[$turno['franja']] ?? '';
                $turno['docs_count'] = (int)$pdo->prepare(
                    "SELECT COUNT(*) FROM planificacion_documento WHERE turno_id=?"
                )->execute([$turno['turno_id']]) ? $pdo->query(
                    "SELECT COUNT(*) FROM planificacion_documento WHERE turno_id={$turno['turno_id']}"
                )->fetchColumn() : 0;
            }

            resp(200, ['turno' => $turno ?: null]);
        } catch (Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;


    // ══════════════════════════════════════════════════════════
    //  SEMANA — EDITAR FECHA DE CIERRE
    // ══════════════════════════════════════════════════════════
    case 'semana_editar_cierre':
        requireSession();
        if (!esOperaciones()) resp(403, ['error' => 'Sin permisos']);
        try {
            $semanaId    = intval($body['semana_id']    ?? 0);
            $fechaCierre = trim($body['fecha_cierre']   ?? '');

            if (!$semanaId)    resp(400, ['error' => 'Falta semana_id']);
            if (!$fechaCierre) resp(400, ['error' => 'Falta fecha_cierre']);

            // Validar formato YYYY-MM-DD HH:MM:SS
            if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $fechaCierre)) {
                resp(400, ['error' => 'Formato de fecha inválido. Use YYYY-MM-DD HH:MM:SS']);
            }

            // Verificar que la semana existe
            $stmt = $pdo->prepare("SELECT semana_id FROM planificacion_semana WHERE semana_id = ?");
            $stmt->execute([$semanaId]);
            if (!$stmt->fetch()) resp(404, ['error' => 'Semana no encontrada']);

            // Calcular nuevo estado según la nueva fecha de cierre
            $nuevoEstado = ($fechaCierre < date('Y-m-d H:i:s')) ? 'cerrada' : 'abierta';

            $pdo->prepare("
                UPDATE planificacion_semana
                SET fecha_cierre = ?, estado = ?
                WHERE semana_id = ?
            ")->execute([$fechaCierre, $nuevoEstado, $semanaId]);

            resp(200, [
                'ok'           => true,
                'semana_id'    => $semanaId,
                'fecha_cierre' => $fechaCierre,
                'estado'       => $nuevoEstado,
            ]);
        } catch (Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    default:

    // ══════════════════════════════════════════════════════════════
    //  TRAZABILIDAD
    // ══════════════════════════════════════════════════════════════

    case 'trazabilidad_estiba':
        requireSession();
        $codigo = trim($_GET['codigo'] ?? '');
        if (!$codigo) resp(400, ['error' => 'Código requerido']);

        // El QR manda ?estibaid=NNNNNNN — ese número ES el EstibaID directamente
        $estibaId = (int)$codigo;
        if (!$estibaId) resp(400, ['error' => 'Código inválido']);

        try {
            // ── Detectar nombres de columnas dinámicamente ──────────
            // Posiciones: calle y fila
            $colCalle = 'NULL'; $colFila = 'NULL';
            try {
                $pcols = $pdo->query("SHOW COLUMNS FROM Posiciones")->fetchAll(PDO::FETCH_COLUMN);
                foreach (['PosicionCalle','calle','Calle','col','columna'] as $c) {
                    if (in_array($c, $pcols)) { $colCalle = "p.$c"; break; }
                }
                foreach (['PosicionFila','fila','Fila','row','fila_num'] as $c) {
                    if (in_array($c, $pcols)) { $colFila = "p.$c"; break; }
                }
            } catch (Exception $epc) {}

            // TiposCamara: nombre de la columna descriptiva
            $colTipoCam = 'TiposCamaraNombre';
            try {
                $tcols = $pdo->query("SHOW COLUMNS FROM TiposCamara")->fetchAll(PDO::FETCH_COLUMN);
                foreach (['TiposCamaraNombre','nombre','Nombre','descripcion','TipoNombre'] as $c) {
                    if (in_array($c, $tcols)) { $colTipoCam = $c; break; }
                }
            } catch (Exception $etc) {}

            $camNombre = "CASE
                WHEN LOWER(t.$colTipoCam) LIKE '%tunel%' OR LOWER(t.$colTipoCam) LIKE '%túnel%'
                    THEN CONCAT('Túnel ', cam.CamaraNumero)
                WHEN LOWER(t.$colTipoCam) LIKE '%antecamara%' OR LOWER(t.$colTipoCam) LIKE '%antecámara%'
                    THEN CONCAT('Antecámara ', cam.CamaraNumero)
                ELSE CONCAT('Cámara ', cam.CamaraNumero) END";

            // Detectar nombre de columna del producto en Cargas
            $colProducto = 'NULL';
            try {
                $cols = $pdo->query("SHOW COLUMNS FROM Cargas")->fetchAll(PDO::FETCH_COLUMN);
                foreach (['CargaNombre','nombre','descripcion','producto','NombreCarga'] as $c) {
                    if (in_array($c, $cols)) { $colProducto = "ca.$c"; break; }
                }
            } catch (Exception $ec) {}

            $stmtE = $pdo->prepare("
                SELECT e.EstibaID, e.NroEstiba, e.CargaID,
                       e.PosicionID, e.Nivel AS nivel_actual,
                       e.FechaAlta, e.FechaBaja,
                       $colProducto AS producto,
                       cl.ClienteID, cl.ClienteNombre AS cliente,
                       cl.siglas AS cliente_siglas, cl.color AS cliente_color
                FROM Estiba e
                LEFT JOIN Cargas ca   ON ca.CargaID   = e.CargaID
                LEFT JOIN CLIENTES cl ON cl.ClienteID = ca.ClienteID
                WHERE e.EstibaID = ?
                LIMIT 1
            ");
            $stmtE->execute([$estibaId]);
            $estiba = $stmtE->fetch();
            if (!$estiba) resp(404, ['error' => "EstibaID $estibaId no encontrada"]);

            $resultado = [
                'estiba_id'      => (int)$estiba['EstibaID'],
                'carga_id'       => (int)$estiba['CargaID'],
                'nro_estiba'     => (int)$estiba['NroEstiba'],
                'producto'       => $estiba['producto'],
                'cliente'        => $estiba['cliente'],
                'cliente_siglas' => $estiba['cliente_siglas'],
                'cliente_color'  => $estiba['cliente_color'],
                'fecha_alta'     => $estiba['FechaAlta'],
                'fecha_baja'     => $estiba['FechaBaja'],
                'posicion_sistema' => null,
                'ultima_posicion'  => null,
                'sin_posicion'     => false,
            ];

            // Posición actual
            if ($estiba['PosicionID']) {
                $stmtP = $pdo->prepare("
                    SELECT p.PosicionID,
                           $colCalle AS calle, $colFila AS fila,
                           p.CamaraID AS camara_id, ($camNombre) AS camara_nombre
                    FROM Posiciones p
                    JOIN Camaras cam     ON cam.CamaraID     = p.CamaraID
                    JOIN TiposCamara t   ON t.TiposCamaraID  = cam.TiposCamaraID
                    WHERE p.PosicionID = ? LIMIT 1
                ");
                $stmtP->execute([$estiba['PosicionID']]);
                $pos = $stmtP->fetch();
                if ($pos) {
                    $resultado['posicion_sistema'] = [
                        'posicion_id'   => (int)$pos['PosicionID'],
                        'camara_id'     => (int)$pos['camara_id'],
                        'camara_nombre' => $pos['camara_nombre'],
                        'calle'         => (int)$pos['calle'],
                        'fila'          => (int)$pos['fila'],
                        'nivel'         => (int)$estiba['nivel_actual'],
                    ];
                } else {
                    $resultado['sin_posicion'] = true;
                }
            } else {
                $resultado['sin_posicion'] = true;
            }

            // Última posición histórica si sin_posicion
            if ($resultado['sin_posicion']) {
                $stmtU = $pdo->prepare("
                    SELECT ep.posicion_id, ep.nivel, ep.fecha_posicion,
                           $colCalle AS calle, $colFila AS fila,
                           p.CamaraID AS camara_id, ($camNombre) AS camara_nombre
                    FROM estiba_posicion ep
                    JOIN Posiciones p  ON p.PosicionID  = ep.posicion_id
                    JOIN Camaras cam   ON cam.CamaraID  = p.CamaraID
                    JOIN TiposCamara t ON t.TiposCamaraID = cam.TiposCamaraID
                    WHERE ep.estiba_id = ?
                    ORDER BY ep.ubicacion DESC LIMIT 1
                ");
                $stmtU->execute([$estibaId]);
                $ult = $stmtU->fetch();
                if ($ult) {
                    $resultado['ultima_posicion'] = [
                        'camara_id'     => (int)$ult['camara_id'],
                        'camara_nombre' => $ult['camara_nombre'],
                        'calle'         => (int)$ult['calle'],
                        'fila'          => (int)$ult['fila'],
                        'nivel'         => (int)$ult['nivel'],
                        'fecha'         => $ult['fecha_posicion'],
                    ];
                }
            }

            resp(200, $resultado);
        } catch (Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    case 'trazabilidad_guardar':
        requireSession();
        $estibaId = (int)($body['estiba_id'] ?? 0);
        if (!$estibaId) resp(400, ['error' => 'Falta estiba_id']);
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS trazabilidad_control (
                id INT AUTO_INCREMENT PRIMARY KEY,
                estiba_id INT NOT NULL, codigo_escaneado VARCHAR(30) NOT NULL,
                usuario_id INT NOT NULL, fecha_control DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                sis_camara_id INT NULL, sis_camara_nombre VARCHAR(60) NULL,
                sis_calle SMALLINT NULL, sis_fila SMALLINT NULL, sis_nivel SMALLINT NULL,
                sin_posicion TINYINT(1) NOT NULL DEFAULT 0,
                ultima_pos_camara_id INT NULL, ultima_pos_camara VARCHAR(60) NULL,
                ultima_pos_calle SMALLINT NULL, ultima_pos_fila SMALLINT NULL, ultima_pos_nivel SMALLINT NULL,
                real_camara_id INT NULL, real_camara_nombre VARCHAR(60) NULL,
                real_calle SMALLINT NULL, real_fila SMALLINT NULL, real_nivel SMALLINT NULL,
                cumple TINYINT(1) NOT NULL DEFAULT 0,
                criticidad ENUM('ok','baja','media','alta') NOT NULL DEFAULT 'ok',
                motivos TEXT NULL, observacion TEXT NULL,
                INDEX idx_estiba(estiba_id), INDEX idx_usuario(usuario_id), INDEX idx_fecha(fecha_control)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $sis  = $body['posicion_sistema'] ?? [];
            $real = $body['posicion_real']    ?? [];
            $ultp = $body['ultima_posicion']  ?? [];
            $pdo->prepare("INSERT INTO trazabilidad_control
                (estiba_id,codigo_escaneado,usuario_id,
                 sis_camara_id,sis_camara_nombre,sis_calle,sis_fila,sis_nivel,
                 sin_posicion,ultima_pos_camara_id,ultima_pos_camara,
                 ultima_pos_calle,ultima_pos_fila,ultima_pos_nivel,
                 real_camara_id,real_camara_nombre,real_calle,real_fila,real_nivel,
                 cumple,criticidad,motivos,observacion)
                VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([
                $estibaId, $body['codigo'] ?? '', userId(),
                $sis['camara_id'] ?? null, $sis['camara_nombre'] ?? null,
                $sis['calle'] ?? null, $sis['fila'] ?? null, $sis['nivel'] ?? null,
                (int)($body['sin_posicion'] ?? 0),
                $ultp['camara_id'] ?? null, $ultp['camara_nombre'] ?? null,
                $ultp['calle'] ?? null, $ultp['fila'] ?? null, $ultp['nivel'] ?? null,
                $real['camara_id'] ?? null, $real['camara_nombre'] ?? null,
                $real['calle'] ?? null, $real['fila'] ?? null, $real['nivel'] ?? null,
                (int)($body['cumple'] ?? 0), $body['criticidad'] ?? 'ok',
                $body['motivos'] ?? null, $body['observacion'] ?? null,
            ]);
            resp(200, ['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
        } catch (Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    case 'trazabilidad_reporte':
        requireSession();
        try {
            $desde   = $_GET['desde']     ?? date('Y-m-d', strtotime('-7 days'));
            $hasta   = $_GET['hasta']     ?? date('Y-m-d');
            $critica = $_GET['criticidad'] ?? '';
            $where   = "WHERE tc.fecha_control BETWEEN ? AND ?";
            $params  = [$desde.' 00:00:00', $hasta.' 23:59:59'];
            if ($critica) { $where .= " AND tc.criticidad = ?"; $params[] = $critica; }
            $stmt = $pdo->prepare("
                SELECT tc.*,
                       u.UsuarioNombre AS usuario_login,
                       e.CargaID       AS carga_id,
                       e.NroEstiba     AS nro_estiba
                FROM trazabilidad_control tc
                JOIN Usuarios u ON u.UsuarioID = tc.usuario_id
                LEFT JOIN Estiba e ON e.EstibaID = tc.estiba_id
                $where ORDER BY tc.fecha_control DESC LIMIT 500
            ");
            $stmt->execute($params);
            $rows   = $stmt->fetchAll();
            $total  = count($rows);
            $cumple = count(array_filter($rows, fn($r) => $r['cumple']));
            $pCrit  = ['ok'=>0,'baja'=>0,'media'=>0,'alta'=>0];
            foreach ($rows as $r) { if (isset($pCrit[$r['criticidad']])) $pCrit[$r['criticidad']]++; }
            resp(200, ['registros' => $rows, 'resumen' => [
                'total' => $total, 'cumple' => $cumple, 'no_cumple' => $total - $cumple,
                'pct_ok' => $total > 0 ? round($cumple/$total*100,1) : 0,
                'por_criticidad' => $pCrit,
            ]]);
        } catch (Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

        resp(404, ['error' => 'Acción no encontrada: ' . $action]);
}
