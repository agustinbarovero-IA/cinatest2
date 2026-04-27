<?php
// API_VERSION: 2026-04-02-v3
/**
 * ============================================================
 *  api_equipos.php — API REST para gestión de equipos
 *  Grupo CINA
 * ============================================================
 *  Subir a: /public_html/api_equipos.php
 *  (mismo servidor que MySQL)
 *
 *  Endpoints:
 *  POST /api_equipos.php?action=cambio_estado
 *  POST /api_equipos.php?action=checklist
 *  POST /api_equipos.php?action=falla
 *  GET  /api_equipos.php?action=estado&interno=1520
 *  GET  /api_equipos.php?action=historial&interno=1520
 * ============================================================
 */

// ── CONFIGURACIÓN ────────────────────────────────────────────
require_once dirname(__DIR__) . '/config.php';
if (!defined('SECRET_KEY')) define('SECRET_KEY', SESSION_SECRET);
// Función helper para filtrar por clientes permitidos (portal cliente)
if (file_exists(__DIR__ . '/api_permisos_extension.php')) {
    require_once __DIR__ . '/api_permisos_extension.php';
}
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');

// ── CORS restringido ────────────────────────────────────────
if (defined('ALLOWED_ORIGIN') && ALLOWED_ORIGIN !== '') {
    header('Access-Control-Allow-Origin: ' . ALLOWED_ORIGIN);
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Credentials: true');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
}

$action = $_GET['action'] ?? '';

// ── Iniciar sesión para endpoints que la necesitan ──────────
// Los endpoints de login/logout manejan session_start internamente
$noSessionActions = ['login_session', 'login_google'];
if (!in_array($action, $noSessionActions)) {
    session_start();
}

// ── Helper: verificar sesión PHP activa ─────────────────────
function requireSession(): void {
    if (empty($_SESSION['cina_usuario_id'])) {
        resp(401, ['error' => 'Sesión expirada. Recargá la página.']);
    }
}

// ── Helper: obtener usuario de sesión ───────────────────────
function sessionUser(): string {
    return $_SESSION['cina_nombre'] ?? $_SESSION['cina_usuario'] ?? 'Sistema';
}

// ── Conexión ─────────────────────────────────────────────────
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

// ── Heartbeat: actualizar UltimoLogin en cada request autenticado ──
if (!empty($_SESSION['cina_usuario_id'])) {
    foreach (['UltimoLogin', 'ultimo_login'] as $col) {
        try {
            $pdo->prepare("UPDATE Usuarios SET $col = NOW() WHERE UsuarioID = ?")
                ->execute([$_SESSION['cina_usuario_id']]);
            break; // si funcionó, no probar más
        } catch(Exception $e) { continue; }
    }
}

// ── Leer body JSON (para todos los endpoints POST) ───────────
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ── Enrutador ────────────────────────────────────────────────
switch ($action) {

    // ── GET: estado actual de un equipo ──────────────────────
    case 'estado':
        $interno = $_GET['interno'] ?? '';
        if (!$interno) resp(400, ['error' => 'Falta parámetro interno']);

        $stmt = $pdo->prepare("SELECT id, interno, denominacion, estado_actual, horometro_actual FROM equipos WHERE interno = ?");
        $stmt->execute([$interno]);
        $eq = $stmt->fetch();
        if (!$eq) resp(404, ['error' => 'Equipo no encontrado']);
        resp(200, $eq);
        break;

    // ── GET: historial de estados de un equipo ───────────────
    case 'historial':
        $interno = $_GET['interno'] ?? '';
        if (!$interno) resp(400, ['error' => 'Falta parámetro interno']);

        $stmt = $pdo->prepare("
            SELECT es.estado_anterior, es.estado_nuevo, es.fecha_hora,
                   es.usuario, es.horometro, es.motivo,
                   ROUND(es.minutos_en_estado_anterior/60, 1) AS horas_previas
            FROM equipo_estados es
            JOIN equipos e ON e.id = es.equipo_id
            WHERE e.interno = ?
            ORDER BY es.fecha_hora DESC
            LIMIT 50
        ");
        $stmt->execute([$interno]);
        resp(200, $stmt->fetchAll());
        break;

    // ── POST: registrar cambio de estado ─────────────────────
    case 'cambio_estado':
        $data = json_input();
        auth($data);

        $interno     = $data['interno']     ?? null;
        $estadoNuevo = $data['estado_nuevo'] ?? null;
        $usuario     = $data['usuario']     ?? 'Sistema';
        $horometro   = (float)($data['horometro'] ?? 0);
        $motivo      = $data['motivo']      ?? null;

        if (!$interno || !$estadoNuevo) resp(400, ['error' => 'Faltan campos requeridos']);

        $estadosValidos = ['Cargando', 'En marcha', 'Falla', 'Dado de baja'];
        if (!in_array($estadoNuevo, $estadosValidos))
            resp(400, ['error' => 'Estado inválido: ' . $estadoNuevo]);

        // Buscar equipo por número interno
        $stmt = $pdo->prepare("SELECT id, estado_actual, horometro_actual FROM equipos WHERE interno = ?");
        $stmt->execute([$interno]);
        $equipo = $stmt->fetch();
        if (!$equipo) resp(404, ['error' => 'Equipo no encontrado: ' . $interno]);

        $equipoId       = $equipo['id'];
        $estadoAnterior = $equipo['estado_actual'];

        // Calcular minutos en estado anterior
        $minutosEnEstado = null;
        try {
            $stmtUlt = $pdo->prepare("SELECT fecha_hora FROM equipo_estados WHERE equipo_id = ? ORDER BY id DESC LIMIT 1");
            $stmtUlt->execute([$equipoId]);
            $ultimoCambio = $stmtUlt->fetchColumn();
            if ($ultimoCambio) {
                $minutosEnEstado = round((time() - strtotime($ultimoCambio)) / 60);
            }
        } catch(Exception $e) {}

        $pdo->beginTransaction();
        try {
            // Log en equipo_estados
            $pdo->prepare("
                INSERT INTO equipo_estados (equipo_id, estado, horometro, usuario, observacion, fecha_hora)
                VALUES (?, ?, ?, ?, ?, NOW())
            ")->execute([$equipoId, $estadoNuevo, $horometro ?: null, $usuario,
                $motivo ? "Desde: $estadoAnterior | $motivo" : "Desde: $estadoAnterior"]);
            $estadoId = $pdo->lastInsertId();

            // Actualizar estado actual del equipo
            $pdo->prepare("UPDATE equipos SET estado_actual=?, horometro_actual=?, updated_at=NOW() WHERE id=?")
                ->execute([$estadoNuevo, $horometro ?: $equipo['horometro_actual'], $equipoId]);

            // Si es Falla → crear registro en equipo_fallas
            if ($estadoNuevo === 'Falla' && $motivo) {
                $pdo->prepare("
                    INSERT INTO equipo_fallas (equipo_id, descripcion, gravedad, usuario, fecha_hora, resuelta)
                    VALUES (?, ?, 'media', ?, NOW(), 0)
                ")->execute([$equipoId, $motivo, $usuario]);
            }

            $pdo->commit();
            resp(200, [
                'ok'              => true,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo'    => $estadoNuevo,
                'horometro'       => $horometro,
                'minutos_previos' => $minutosEnEstado,
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            resp(500, ['error' => 'Error al guardar: ' . $e->getMessage()]);
        }
        break;

    // ── POST: registrar checklist de inicio ──────────────────
    case 'checklist':
        $data = json_input();
        auth($data);

        $required = ['interno', 'usuario', 'horometro_inicio', 'items'];
        foreach ($required as $f) {
            if (!isset($data[$f])) resp(400, ['error' => "Falta campo: $f"]);
        }

        $stmt = $pdo->prepare("SELECT id FROM equipos WHERE interno = ?");
        $stmt->execute([$data['interno']]);
        $equipoId = $stmt->fetchColumn();
        if (!$equipoId) resp(404, ['error' => 'Equipo no encontrado']);

        // Buscar el último estado_id (el cambio a En marcha recién registrado)
        $stmt = $pdo->prepare("
            SELECT id FROM equipo_estados
            WHERE equipo_id = ? AND estado_nuevo = 'En marcha'
            ORDER BY fecha_hora DESC LIMIT 1
        ");
        $stmt->execute([$equipoId]);
        $estadoId = $stmt->fetchColumn() ?: 0;

        $items   = $data['items'];   // array de 18 valores: 1, 0 o null
        $hayFalla = in_array(0, $items);
        $resultado = $hayFalla
            ? (array_sum($items) >= 15 ? 'Aprobado con observaciones' : 'Rechazado')
            : 'Aprobado';

        $stmt = $pdo->prepare("
            INSERT INTO equipo_checklist
                (equipo_id, estado_id, usuario, fecha_hora, horometro_inicio, resultado, observaciones,
                 item_01_extinguidores, item_02_espejos, item_03_cinturon, item_04_luz_delantera,
                 item_05_bocina, item_06_luz_trasera, item_07_alarma_retroceso, item_08_luz_electrob,
                 item_09_frenos, item_10_direccion, item_11_mastil_vertical, item_12_mastil_angulo,
                 item_13_apertura_unas, item_14_desplaz_lateral, item_15_cadena,
                 item_16_mangueras, item_17_asiento, item_18_jaula)
            VALUES (?,?,?,NOW(),?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([
            $equipoId, $estadoId, $data['usuario'], (int)$data['horometro_inicio'],
            $resultado, $data['observaciones'] ?? null,
            $items[0]??null, $items[1]??null, $items[2]??null,  $items[3]??null,
            $items[4]??null, $items[5]??null, $items[6]??null,  $items[7]??null,
            $items[8]??null, $items[9]??null, $items[10]??null, $items[11]??null,
            $items[12]??null,$items[13]??null,$items[14]??null, $items[15]??null,
            $items[16]??null,$items[17]??null,
        ]);

        resp(200, ['ok' => true, 'resultado' => $resultado, 'checklist_id' => $pdo->lastInsertId()]);
        break;

    // ── GET: lista de cámaras activas desde MySQL ───────────
    case 'camaras':
        // Trae TODAS las cámaras activas con su última temperatura disponible
        // La columna 'sector' (1=Verde, 2=Azul, 3=Blanco) ya existe en la tabla Camaras
        $stmt = $pdo->query("
            SELECT
                c.CamaraID,
                c.CamaraNumero,
                c.CamaraCalles,
                c.CamaraFilas,
                c.CamaraNiveles,
                c.ReservaCliente,
                c.activo,
                c.TiposCamaraID,
                c.sector,
                t.TiposCamaraNombre AS tipo_nombre,
                t.iniciales,
                tmp.temperatura AS temp_actual,
                tmp.setpoint    AS temp_setpoint,
                tmp.estado      AS temp_estado,
                tmp.fecha AS temp_fecha,
                tmp.hora AS temp_hora
            FROM Camaras c
            LEFT JOIN TiposCamara t ON t.TiposCamaraID = c.TiposCamaraID
            LEFT JOIN (
                SELECT m.camara_id, t2.temperatura, t2.setpoint, t2.estado, t2.fecha, t2.hora
                FROM temperatura_camara_map m
                INNER JOIN temperaturas t2 ON t2.camara = m.nombre_csv
                INNER JOIN (
                    SELECT camara, MAX(CONCAT(fecha,' ',hora)) AS ultima
                    FROM temperaturas
                    WHERE temperatura IS NOT NULL
                    GROUP BY camara
                ) u ON u.camara = t2.camara AND CONCAT(t2.fecha,' ',t2.hora) = u.ultima
                WHERE m.camara_id IS NOT NULL
            ) tmp ON tmp.camara_id = c.CamaraID
            WHERE c.activo = 1
            ORDER BY c.TiposCamaraID ASC, c.CamaraNumero ASC
        ");
        $camaras = $stmt->fetchAll();

        foreach ($camaras as &$cam) {
            $num  = $cam['CamaraNumero'];
            $tipo = strtolower($cam['tipo_nombre'] ?? '');

            // Tipos excluidos del dashboard: 7,10,13,14,20,21
            $excluidos = [7, 10, 13, 14, 20, 21];
            $tipoId    = (int)$cam['TiposCamaraID'];

            if (str_contains($tipo, 'tunel') || str_contains($tipo, 'túnel')) {
                $cam['nombre_display'] = 'TÚNEL ' . $num;
                $cam['es_dashboard']   = true;
            } elseif (str_contains($tipo, 'antecamara') || str_contains($tipo, 'antecámara')) {
                $cam['nombre_display'] = 'ANTECÁMARA ' . $num;
                $cam['es_dashboard']   = true;
            } elseif ($tipo === 'anden norte') {
                $cam['nombre_display'] = 'ANDEN NORTE ' . $num;
                $cam['es_dashboard']   = true;
            } elseif ($tipo === 'anden sur') {
                $cam['nombre_display'] = 'ANDEN SUR ' . $num;
                $cam['es_dashboard']   = true;
            } elseif (str_contains($tipo, 'pasillo')) {
                $cam['nombre_display'] = 'PASILLO ' . $num;
                $cam['es_dashboard']   = false;
            } elseif (str_contains($tipo, 'ext')) {
                $cam['nombre_display'] = 'EXTERIOR ' . $num;
                $cam['es_dashboard']   = false;
            } elseif (str_contains($tipo, 'camion') || str_contains($tipo, 'camión')) {
                $cam['nombre_display'] = 'CAMIÓN ' . $num;
                $cam['es_dashboard']   = false;
            } elseif (str_contains($tipo, 'dsf') || str_contains($tipo, 'dsn')) {
                $cam['nombre_display'] = strtoupper($cam['iniciales']) . ' ' . $num;
                $cam['es_dashboard']   = false;
            } elseif (str_contains($tipo, 'reefer')) {
                $cam['nombre_display'] = 'REEFER ' . $num;
                $cam['es_dashboard']   = false;
            } else {
                $cam['nombre_display'] = 'CÁMARA ' . $num;
                $cam['es_dashboard']   = !in_array($tipoId, $excluidos);
            }
        }
        unset($cam);
        resp(200, $camaras);
        break;

    // ── POST: guardar registro PR-6 Contraste ───────────────
    case 'pr6_guardar':
        $data = json_input();
        auth($data);

        $fecha      = $data['fecha']      ?? '';
        $hora       = $data['hora']       ?? '';
        $maquinista = $data['maquinista'] ?? ''; // nombre del perfil
        $detalles   = $data['detalles']   ?? [];
        $turno      = 2; // fijo por ahora

        if (!$fecha || !$hora || !$maquinista || empty($detalles)) {
            resp(400, ['error' => 'Faltan campos obligatorios']);
        }

        // Buscar persona_id desde tabla Usuarios usando UsuarioNombre
        // El nombre de usuario en la app tiene formato "Nombre Apellido" (perfilData.nombre)
        // Lo mapeamos al UsuarioNombre en formato "nombre.apellido"
        $personaId = null;

        // Intento 1: buscar por UsuarioNombre directo (si se pasa el username)
        $st = $pdo->prepare("SELECT persona_id FROM Usuarios WHERE UsuarioNombre = ? AND activo = 1 LIMIT 1");
        $st->execute([$maquinista]);
        $personaId = $st->fetchColumn() ?: null;

        // Intento 2: convertir "Nombre Apellido" → "nombre.apellido"
        if (!$personaId) {
            $partes       = preg_split('/\s+/', trim($maquinista), 2);
            $nombre_lower = strtolower($partes[0] ?? '');
            $apell_lower  = strtolower($partes[1] ?? '');
            $usuario_fmt  = $nombre_lower . '.' . str_replace(' ', '.', $apell_lower);
            $st = $pdo->prepare("SELECT persona_id FROM Usuarios WHERE UsuarioNombre = ? AND activo = 1 LIMIT 1");
            $st->execute([$usuario_fmt]);
            $personaId = $st->fetchColumn() ?: null;
        }

        // Intento 3: LIKE por primera parte del nombre
        if (!$personaId) {
            $primer_nombre = strtolower(explode(' ', trim($maquinista))[0]);
            $st = $pdo->prepare("SELECT persona_id FROM Usuarios WHERE UsuarioNombre LIKE ? AND activo = 1 LIMIT 1");
            $st->execute([$primer_nombre . '.%']);
            $personaId = $st->fetchColumn() ?: null;
        }

        // Fallback: usar 1 si no se encontró, y loguear
        if (!$personaId) {
            error_log("PR6: persona_id no encontrado para: $maquinista");
            $personaId = 1;
        }

        $pdo->beginTransaction();
        try {
            // Insertar cabecera
            $stmt = $pdo->prepare("
                INSERT INTO sdm_reg_contraste
                    (fecha, hora, maquinista, turno, activo, created_by, created_at, updated_by, updated_at)
                VALUES (?, ?, ?, ?, 1, ?, NOW(), ?, NOW())
            ");
            $stmt->execute([$fecha, $hora, $personaId, $turno, $personaId, $personaId]);
            $registroId = $pdo->lastInsertId();

            // Insertar detalles
            $stmtDet = $pdo->prepare("
                INSERT INTO sdm_reg_contraste_detalle
                    (sdm_reg_contraste_id, camara,
                     temp_patron_n1, temp_patron_n2, temp_plc, temp_puerta, temp_alcohol,
                     dif_temp_plc, dif_temp_puerta, dif_temp_alcohol, dif_temp_max,
                     observaciones, activo, created_by, created_at, updated_by, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), ?, NOW())
            ");

            $insertados = 0;
            foreach ($detalles as $d) {
                $camaraNum = (int)($d['camara_num'] ?? 0);
                if (!$camaraNum) continue;

                $ptr1    = isset($d['ptr1'])     && $d['ptr1']     !== '' ? (float)$d['ptr1']     : null;
                $ptr2    = isset($d['ptr2'])     && $d['ptr2']     !== '' ? (float)$d['ptr2']     : null;
                $plc     = isset($d['plc'])      && $d['plc']      !== '' ? (float)$d['plc']      : null;
                $puerta  = isset($d['puerta'])   && $d['puerta']   !== '' ? (float)$d['puerta']   : null;
                $alcohol = isset($d['alcohol'])  && $d['alcohol']  !== '' ? (float)$d['alcohol']  : null;
                $obs     = $d['obs'] ?? null;

                // Calcular diferencias respecto al promedio de patrones
                $prom    = ($ptr1 !== null && $ptr2 !== null) ? ($ptr1 + $ptr2) / 2 : ($ptr1 ?? $ptr2);
                $difPlc  = ($plc     !== null && $prom !== null) ? round($plc     - $prom, 2) : null;
                $difPue  = ($puerta  !== null && $prom !== null) ? round($puerta  - $prom, 2) : null;
                $difAlc  = ($alcohol !== null && $prom !== null) ? round($alcohol - $prom, 2) : null;

                // dif_temp_max = diferencia absoluta mayor
                $difs    = array_filter([$difPlc, $difPue, $difAlc], fn($v) => $v !== null);
                $difMax  = $difs ? round(max(array_map('abs', $difs)), 2) : null;

                $stmtDet->execute([
                    $registroId, $camaraNum,
                    $ptr1, $ptr2, $plc, $puerta, $alcohol,
                    $difPlc, $difPue, $difAlc, $difMax,
                    $obs, $personaId, $personaId
                ]);
                $insertados++;
            }

            $pdo->commit();
            resp(200, [
                'ok'          => true,
                'registro_id' => $registroId,
                'maquinista_id' => $personaId,
                'detalles'    => $insertados,
            ]);
        } catch(Exception $e) {
            $pdo->rollBack();
            resp(500, ['error' => 'Error al guardar: ' . $e->getMessage()]);
        }
        break;

    // ── GET: historial PR-6 desde la base ───────────────────
    case 'pr6_historial':
        $limit = min((int)($_GET['limit'] ?? 20), 100);
        $rows  = $pdo->query("
            SELECT
                r.sdm_reg_contraste_id AS id,
                r.fecha,
                r.hora,
                COALESCE(
                    CONCAT(TRIM(p.nombre), ' ', TRIM(p.apellido)),
                    p.nombre,
                    'Desconocido'
                ) AS maquinista_nombre,
                COUNT(d.sdm_reg_contraste_detalle_id) AS camaras_registradas
            FROM sdm_reg_contraste r
            LEFT JOIN persona p ON p.persona_id = r.maquinista
            LEFT JOIN sdm_reg_contraste_detalle d ON d.sdm_reg_contraste_id = r.sdm_reg_contraste_id AND d.activo = 1
            WHERE r.activo = 1
            GROUP BY r.sdm_reg_contraste_id, r.fecha, r.hora, p.nombre, p.apellido
            ORDER BY r.fecha DESC, r.hora DESC
            LIMIT $limit
        ")->fetchAll();
        resp(200, $rows);
        break;

    // ── GET: detalle de un registro PR-6 ────────────────────
    case 'pr6_detalle':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) resp(400, ['error' => 'Falta ID']);

        $cab = $pdo->prepare("
            SELECT r.*, COALESCE(CONCAT(p.nombre,' ',p.apellido), p.nombre, 'Desconocido') AS maquinista_nombre
            FROM sdm_reg_contraste r
            LEFT JOIN persona p ON p.persona_id = r.maquinista
            WHERE r.sdm_reg_contraste_id = ?
        ");
        $cab->execute([$id]);
        $cabecera = $cab->fetch();
        if (!$cabecera) resp(404, ['error' => 'Registro no encontrado']);

        $det = $pdo->prepare("
            SELECT d.*, c.CamaraNumero,
                   CONCAT('CÁMARA ', c.CamaraNumero) AS camara_nombre
            FROM sdm_reg_contraste_detalle d
            LEFT JOIN Camaras c ON c.CamaraNumero = d.camara
            WHERE d.sdm_reg_contraste_id = ? AND d.activo = 1
            ORDER BY d.camara
        ");
        $det->execute([$id]);
        $detalles = $det->fetchAll();

        resp(200, ['cabecera' => $cabecera, 'detalles' => $detalles]);
        break;

    // ── GET: última fecha de registro por cámara (para el tile) ──
    case 'pr6_estado_camaras':
        $rows = $pdo->query("
            SELECT
                d.camara AS camara_numero,
                MAX(r.fecha) AS ultima_fecha,
                DATEDIFF(CURDATE(), MAX(r.fecha)) AS dias_desde_ultimo
            FROM sdm_reg_contraste_detalle d
            JOIN sdm_reg_contraste r ON r.sdm_reg_contraste_id = d.sdm_reg_contraste_id
            WHERE r.activo = 1 AND d.activo = 1
            GROUP BY d.camara
            ORDER BY d.camara
        ")->fetchAll();
        resp(200, $rows);
        break;

    // ── POST: guardar registro PR-6 Contraste ──────────────
    case 'pr6_guardar':
        $fecha      = $body['fecha']      ?? '';
        $hora       = $body['hora']       ?? '';
        $maquinista = $body['maquinista'] ?? '';
        $detalles   = $body['detalles']   ?? [];

        if (!$fecha || !$hora || !$maquinista || empty($detalles)) {
            resp(400, ['error' => 'Faltan datos obligatorios']);
        }

        // Buscar persona_id por nombre completo
        $stmt = $pdo->prepare("
            SELECT persona_id FROM persona
            WHERE CONCAT(TRIM(nombre), ' ', TRIM(apellido)) = ?
            AND activo = 1
            LIMIT 1
        ");
        $stmt->execute([trim($maquinista)]);
        $personaId = $stmt->fetchColumn();

        // Si no encontró por nombre completo, buscar por apellido
        if (!$personaId) {
            $partes    = explode(' ', trim($maquinista));
            $apellido  = end($partes);
            $stmt = $pdo->prepare("SELECT persona_id FROM persona WHERE apellido LIKE ? AND activo=1 LIMIT 1");
            $stmt->execute(["%$apellido%"]);
            $personaId = $stmt->fetchColumn();
        }

        // Fallback: usar ID 1 si no se encontró (log de advertencia)
        if (!$personaId) {
            error_log("PR6: persona no encontrada para '$maquinista', usando fallback ID=1");
            $personaId = 1;
        }

        // Calcular turno en base a la hora (1=mañana, 2=tarde, 3=noche)
        $hh    = (int)substr($hora, 0, 2);
        $turno = $hh >= 6 && $hh < 14 ? 1 : ($hh >= 14 && $hh < 22 ? 2 : 3);

        $pdo->beginTransaction();
        try {
            // Insertar cabecera en sdm_reg_contraste
            $stmt = $pdo->prepare("
                INSERT INTO sdm_reg_contraste
                    (fecha, hora, maquinista, turno, activo, created_by, created_at, updated_by, updated_at)
                VALUES (?, ?, ?, ?, 1, ?, NOW(), ?, NOW())
            ");
            $stmt->execute([$fecha, $hora, $personaId, $turno, $personaId, $personaId]);
            $registroId = $pdo->lastInsertId();

            // Insertar detalles por cámara
            $stmtDet = $pdo->prepare("
                INSERT INTO sdm_reg_contraste_detalle
                    (sdm_reg_contraste_id, camara,
                     temp_patron_n1, temp_patron_n2, temp_plc, temp_puerta, temp_alcohol,
                     dif_temp_plc, dif_temp_puerta, dif_temp_alcohol, dif_temp_max,
                     observaciones, activo, created_by, created_at, updated_by, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), ?, NOW())
            ");

            foreach ($detalles as $d) {
                $camaraNum = (int)($d['camara_num'] ?? 0);
                if (!$camaraNum) continue;

                $ptr1   = isset($d['ptr1'])   && $d['ptr1']   !== '' ? (float)$d['ptr1']   : null;
                $ptr2   = isset($d['ptr2'])   && $d['ptr2']   !== '' ? (float)$d['ptr2']   : null;
                $plc    = isset($d['plc'])    && $d['plc']    !== '' ? (float)$d['plc']    : null;
                $puerta = isset($d['puerta']) && $d['puerta'] !== '' ? (float)$d['puerta'] : null;
                $alc    = isset($d['alcohol'])&& $d['alcohol']!== '' ? (float)$d['alcohol']: null;
                $obs    = $d['obs'] ?? null;

                // Calcular diferencias: dif = valor_medicion - promedio_patrones
                $promPat = ($ptr1 !== null && $ptr2 !== null) ? ($ptr1 + $ptr2) / 2 : ($ptr1 ?? $ptr2);
                $difPlc    = ($plc    !== null && $promPat !== null) ? round($plc    - $promPat, 2) : null;
                $difPuerta = ($puerta !== null && $promPat !== null) ? round($puerta - $promPat, 2) : null;
                $difAlc    = ($alc    !== null && $promPat !== null) ? round($alc    - $promPat, 2) : null;

                // dif_max = mayor diferencia absoluta
                $difs   = array_filter([$difPlc, $difPuerta, $difAlc], fn($v) => $v !== null);
                $difMax = $difs ? round(max(array_map('abs', $difs)), 2) : null;
                if ($difMax !== null) {
                    // Conservar el signo del mayor absoluto
                    foreach ($difs as $v) {
                        if (abs($v) == $difMax) { $difMax = $v; break; }
                    }
                }

                $stmtDet->execute([
                    $registroId, $camaraNum,
                    $ptr1, $ptr2, $plc, $puerta, $alc,
                    $difPlc, $difPuerta, $difAlc, $difMax,
                    $obs ?: null,
                    $personaId, $personaId
                ]);
            }

            $pdo->commit();
            resp(200, ['ok' => true, 'registro_id' => $registroId, 'turno' => $turno]);

        } catch (Exception $e) {
            $pdo->rollBack();
            resp(500, ['error' => 'Error al guardar PR-6: ' . $e->getMessage()]);
        }
        break;

    // ── GET: historial PR-6 desde DB ────────────────────────
    case 'pr6_historial':
        $limite = min((int)($_GET['limite'] ?? 20), 50);
        $rows = $pdo->query("
            SELECT
                r.sdm_reg_contraste_id AS id,
                r.fecha,
                r.hora,
                r.turno,
                CONCAT(TRIM(p.nombre), ' ', TRIM(p.apellido)) AS maquinista,
                COUNT(d.sdm_reg_contraste_detalle_id) AS total_camaras
            FROM sdm_reg_contraste r
            LEFT JOIN persona p ON p.persona_id = r.maquinista
            LEFT JOIN sdm_reg_contraste_detalle d
                ON d.sdm_reg_contraste_id = r.sdm_reg_contraste_id AND d.activo = 1
            WHERE r.activo = 1
            GROUP BY r.sdm_reg_contraste_id
            ORDER BY r.fecha DESC, r.hora DESC
            LIMIT $limite
        ")->fetchAll();

        // Para cada registro traer los detalles
        $stmtDet = $pdo->prepare("
            SELECT d.camara, c.nombre_display,
                   d.temp_patron_n1, d.temp_patron_n2, d.temp_plc,
                   d.temp_puerta, d.temp_alcohol,
                   d.dif_temp_plc, d.dif_temp_puerta, d.dif_temp_alcohol, d.dif_temp_max,
                   d.observaciones
            FROM sdm_reg_contraste_detalle d
            LEFT JOIN (
                SELECT CamaraNumero,
                       CASE
                         WHEN t.tipo_lower LIKE '%tunel%' THEN CONCAT('TÚNEL ', c2.CamaraNumero)
                         ELSE CONCAT('CÁMARA ', c2.CamaraNumero)
                       END AS nombre_display
                FROM Camaras c2
                LEFT JOIN (SELECT TiposCamaraID, LOWER(COALESCE(TipoNombre,Nombre,Descripcion,nombre,'')) AS tipo_lower FROM TiposCamara) t
                    ON t.TiposCamaraID = c2.TiposCamaraID
            ) c ON c.CamaraNumero = d.camara
            WHERE d.sdm_reg_contraste_id = ? AND d.activo = 1
            ORDER BY d.camara
        ");

        foreach ($rows as &$row) {
            $stmtDet->execute([$row['id']]);
            $row['detalles'] = $stmtDet->fetchAll();
        }
        unset($row);

        resp(200, $rows);
        break;

    // ── POST: guardar registro PR-22 Limpieza de Tanques ────
    case 'pr22_guardar':
        $fecha      = $body['fecha']      ?? '';
        $hora       = $body['hora']       ?? '';
        $maquinista = $body['maquinista'] ?? '';
        $norte      = !empty($body['tanque_norte']) ? 1 : 0;
        $sur        = !empty($body['tanque_sur'])   ? 1 : 0;
        $preNorte   = trim($body['precinto_norte'] ?? '');
        $preSur     = trim($body['precinto_sur']   ?? '');
        $comentarios= trim($body['comentarios']    ?? '');

        if (!$fecha || !$hora || !$maquinista) {
            resp(400, ['error' => 'Faltan datos obligatorios']);
        }

        // Buscar persona_id igual que en PR-6
        $stmt = $pdo->prepare("
            SELECT persona_id FROM persona
            WHERE CONCAT(TRIM(nombre), ' ', TRIM(apellido)) = ? AND activo = 1 LIMIT 1
        ");
        $stmt->execute([trim($maquinista)]);
        $personaId = $stmt->fetchColumn();
        if (!$personaId) {
            $partes = explode(' ', trim($maquinista));
            $stmt = $pdo->prepare("SELECT persona_id FROM persona WHERE apellido LIKE ? AND activo=1 LIMIT 1");
            $stmt->execute(['%' . end($partes) . '%']);
            $personaId = $stmt->fetchColumn() ?: 1;
        }

        // Concatenar precintos en un solo campo de texto
        $presintos = [];
        if ($norte && $preNorte) $presintos[] = "Tanque Norte: $preNorte";
        if ($sur   && $preSur  ) $presintos[] = "Tanque Sur: $preSur";
        if ($comentarios)        $presintos[] = "Obs: $comentarios";
        $presintoTexto = implode("
", $presintos) ?: null;

        try {
            $stmt = $pdo->prepare("
                INSERT INTO sdm_reg_limpieza_tanque
                    (fecha, hora, maquinista, tanque_norte, tanque_sur, presinto,
                     activo, created_by, created_at, updated_by, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW(), ?, NOW())
            ");
            $stmt->execute([$fecha, $hora, $personaId, $norte, $sur,
                            $presintoTexto, $personaId, $personaId]);
            resp(200, ['ok' => true, 'registro_id' => $pdo->lastInsertId()]);
        } catch (Exception $e) {
            resp(500, ['error' => 'Error al guardar PR-22: ' . $e->getMessage()]);
        }
        break;

    // ── GET: historial PR-22 desde DB ────────────────────────
    case 'pr22_historial':
        $limite = min((int)($_GET['limite'] ?? 20), 50);
        $rows = $pdo->query("
            SELECT
                t.sdm_reg_limpieza_tanque_id AS id,
                t.fecha, t.hora,
                COALESCE(
                    CONCAT(TRIM(p.nombre), ' ', TRIM(p.apellido)),
                    p.nombre,
                    'Desconocido'
                ) AS maquinista,
                t.tanque_norte,
                t.tanque_sur,
                t.presinto
            FROM sdm_reg_limpieza_tanque t
            LEFT JOIN persona p ON p.persona_id = t.maquinista
            WHERE t.activo = 1
            ORDER BY t.fecha DESC, t.hora DESC
            LIMIT $limite
        ")->fetchAll();
        resp(200, $rows);
        break;

    // ── POST: guardar registro PR-9 Semanal ─────────────────
    case 'pr9_guardar':
        $fecha       = $body['fecha']       ?? '';
        $hora        = $body['hora']        ?? '';
        $maquinista  = $body['maquinista']  ?? '';
        $personaId   = $body['persona_id']  ?? null;
        $camaras     = $body['camaras']     ?? [];

        if (!$fecha || !$hora || empty($camaras)) {
            resp(400, ['error' => 'Faltan datos obligatorios']);
        }

        // Resolver maquinista_id: preferir persona_id enviado desde sesión
        $maqId = null;
        if ($personaId) {
            $maqId = (int)$personaId;
        } else {
            // Fallback: buscar por nombre de usuario
            $stmt2 = $pdo->prepare("SELECT persona_id FROM Usuarios WHERE UsuarioNombre=? AND activo=1 LIMIT 1");
            $stmt2->execute([trim($maquinista)]);
            $maqId = (int)($stmt2->fetchColumn() ?: 1);
        }

        // Migración segura: verificar columna por columna via INFORMATION_SCHEMA
        // (compatible con MySQL 5.7+ y MariaDB 10.1+)
        $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
        $colsExistentes = $pdo->query("
            SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'sdm_egc_semanal_detalle'
        ")->fetchAll(PDO::FETCH_COLUMN);
        $colsExistentes = array_map('strtolower', $colsExistentes);

        $colsNuevas = [
            'equipos_json'          => "ALTER TABLE sdm_egc_semanal_detalle ADD COLUMN equipos_json MEDIUMTEXT NULL",
            'apertura'              => "ALTER TABLE sdm_egc_semanal_detalle ADD COLUMN apertura VARCHAR(20) NULL",
            'presencia_hielo'       => "ALTER TABLE sdm_egc_semanal_detalle ADD COLUMN presencia_hielo VARCHAR(5) NULL",
            'mantenimiento_urgente' => "ALTER TABLE sdm_egc_semanal_detalle ADD COLUMN mantenimiento_urgente VARCHAR(5) NULL",
        ];
        foreach ($colsNuevas as $col => $sql) {
            if (!in_array($col, $colsExistentes)) {
                try { $pdo->exec($sql); } catch(Exception $e) { /* ignora */ }
            }
        }

        // Re-verificar qué columnas existen ahora para armar el INSERT dinámicamente
        $colsExistentes = $pdo->query("
            SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'sdm_egc_semanal_detalle'
        ")->fetchAll(PDO::FETCH_COLUMN);
        $colsExistentes = array_map('strtolower', $colsExistentes);

        $tieneEqJson  = in_array('equipos_json', $colsExistentes);
        $tieneAp      = in_array('apertura', $colsExistentes);
        $tieneHielo   = in_array('presencia_hielo', $colsExistentes);
        $tieneUrgente = in_array('mantenimiento_urgente', $colsExistentes);

        try {
            $pdo->beginTransaction();
            $stmtCab = $pdo->prepare("
                INSERT INTO sdm_egc_semanal (fecha, hora, maquinista_id, activo, created_by, created_at, updated_by, updated_at)
                VALUES (?, ?, ?, 1, ?, NOW(), ?, NOW())
            ");
            $stmtCab->execute([$fecha, $hora, $maqId, $maqId, $maqId]);
            $newId = $pdo->lastInsertId();

            foreach ($camaras as $cam) {
                // Enriquecer JSON de equipos con nombres
                $equipos = $cam['equipos'] ?? [];
                if (!empty($equipos)) {
                    $ids = array_filter(array_map(fn($e) => (int)($e['equipo_id'] ?? 0), $equipos));
                    if ($ids) {
                        $placeholders = implode(',', array_fill(0, count($ids), '?'));
                        $stmtEq = $pdo->prepare("SELECT id, nombre, codigo_interno FROM mant_equipos WHERE id IN ($placeholders)");
                        $stmtEq->execute(array_values($ids));
                        $nombresMap = [];
                        foreach ($stmtEq->fetchAll() as $eq) {
                            $nombresMap[(int)$eq['id']] = ['nombre' => $eq['nombre'], 'codigo' => $eq['codigo_interno']];
                        }
                        foreach ($equipos as &$eq) {
                            $eId = (int)($eq['equipo_id'] ?? 0);
                            $eq['nombre'] = $nombresMap[$eId]['nombre'] ?? '';
                            $eq['codigo'] = $nombresMap[$eId]['codigo'] ?? '';
                        }
                        unset($eq);
                    }
                }

                // Construir INSERT dinámicamente según columnas disponibles
                $cols   = ['sdm_egc_semanal_id','camara_id','temperatura','estado_puerta',
                           'estado_higienico','estado_serpentina','estado_bandejas',
                           'orden_mercaderia','luminarias','observaciones',
                           'activo','created_by','created_at','updated_by','updated_at'];
                $vals   = [$newId, (int)$cam['camara_id'], 0, 0, 0, 0, 0, 0, 0,
                           !empty($cam['observaciones']) ? trim($cam['observaciones']) : null,
                           1, $maqId];
                $pholds = ['?','?','0','0','0','0','0','0','0','?','1','?'];

                if ($tieneEqJson) {
                    $cols[]   = 'equipos_json';
                    $pholds[] = '?';
                    $vals[]   = !empty($equipos) ? json_encode($equipos, JSON_UNESCAPED_UNICODE) : null;
                }
                if ($tieneAp) {
                    $cols[]   = 'apertura';
                    $pholds[] = '?';
                    $vals[]   = !empty($cam['apertura']) ? trim($cam['apertura']) : null;
                }
                if ($tieneHielo) {
                    $cols[]   = 'presencia_hielo';
                    $pholds[] = '?';
                    $vals[]   = !empty($cam['presencia_hielo']) ? trim($cam['presencia_hielo']) : null;
                }
                if ($tieneUrgente) {
                    $cols[]   = 'mantenimiento_urgente';
                    $pholds[] = '?';
                    $vals[]   = !empty($cam['mantenimiento_urgente']) ? trim($cam['mantenimiento_urgente']) : null;
                }

                // Agregar timestamps al final
                $cols[]   = 'created_at'; $pholds[] = 'NOW()';
                $cols[]   = 'updated_by'; $pholds[] = '?'; $vals[] = $maqId;
                $cols[]   = 'updated_at'; $pholds[] = 'NOW()';

                // Quitar duplicados de created_at (ya estaba en la lista base)
                // Reconstruir limpio sin duplicados
                $insertCols = ['sdm_egc_semanal_id','camara_id','temperatura','estado_puerta',
                               'estado_higienico','estado_serpentina','estado_bandejas',
                               'orden_mercaderia','luminarias','observaciones','activo',
                               'created_by','created_at','updated_by','updated_at'];
                $insertPh   = ['?','?','0','0','0','0','0','0','0','?','1','?','NOW()','?','NOW()'];
                $insertVals = [
                    $newId,
                    (int)$cam['camara_id'],
                    !empty($cam['observaciones']) ? trim($cam['observaciones']) : null,
                    $maqId,
                    $maqId,
                ];
                if ($tieneEqJson) {
                    $insertCols[] = 'equipos_json'; $insertPh[] = '?';
                    $insertVals[] = !empty($equipos) ? json_encode($equipos, JSON_UNESCAPED_UNICODE) : null;
                }
                if ($tieneAp) {
                    $insertCols[] = 'apertura'; $insertPh[] = '?';
                    $insertVals[] = !empty($cam['apertura']) ? trim($cam['apertura']) : null;
                }
                if ($tieneHielo) {
                    $insertCols[] = 'presencia_hielo'; $insertPh[] = '?';
                    $insertVals[] = !empty($cam['presencia_hielo']) ? trim($cam['presencia_hielo']) : null;
                }
                if ($tieneUrgente) {
                    $insertCols[] = 'mantenimiento_urgente'; $insertPh[] = '?';
                    $insertVals[] = !empty($cam['mantenimiento_urgente']) ? trim($cam['mantenimiento_urgente']) : null;
                }

                $sql = "INSERT INTO sdm_egc_semanal_detalle (" . implode(',', $insertCols) . ") VALUES (" . implode(',', $insertPh) . ")";
                $pdo->prepare($sql)->execute($insertVals);
            }
            $pdo->commit();
            resp(200, ['ok' => true, 'id' => $newId]);
        } catch (Exception $e) {
            $pdo->rollBack();
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── GET: último registro PR-9 (para tile del menú) ──────────
    case 'pr9_ultimo':
        try {
            $row = $pdo->query("
                SELECT s.sdm_egc_semanal_id AS id, s.fecha, s.hora,
                       COALESCE(CONCAT(TRIM(p.apellido),', ',TRIM(p.nombre)), p.nombre, 'Desconocido') AS maquinista
                FROM sdm_egc_semanal s
                LEFT JOIN persona p ON p.persona_id = s.maquinista_id
                WHERE s.activo = 1
                ORDER BY s.fecha DESC, s.hora DESC
                LIMIT 1
            ")->fetch();
            if (!$row) { resp(200, ['fecha'=>null,'hora'=>null,'maquinista'=>null]); }
            // Obtener cámaras del registro
            $cams = $pdo->prepare("
                SELECT COUNT(*) AS total,
                       GROUP_CONCAT(
                           COALESCE(c.nombre_display, CONCAT('C',c.CamaraNumero))
                           ORDER BY c.CamaraNumero ASC SEPARATOR ', '
                       ) AS nombres
                FROM sdm_egc_semanal_detalle d
                LEFT JOIN Camaras c ON c.CamaraID = d.camara_id
                WHERE d.sdm_egc_semanal_id = ? AND d.activo = 1
            ");
            $cams->execute([$row['id']]);
            $camData = $cams->fetch();
            $row['camaras_count']   = (int)($camData['total'] ?? 0);
            $row['camaras_nombres'] = $camData['nombres'] ?? '';
            resp(200, $row);
        } catch(Exception $e) { resp(200, ['fecha'=>null,'hora'=>null,'maquinista'=>null,'error_detail'=>$e->getMessage()]); }
        break;

    // ── GET: historial PR-9 Semanal ─────────────────────────
    case 'pr9_historial':
        $limite = min((int)($_GET['limite'] ?? 20), 100);
        try {
            $rows = $pdo->query("
                SELECT
                    s.sdm_egc_semanal_id AS id,
                    s.fecha, s.hora,
                    COALESCE(
                        CONCAT(TRIM(p.apellido), ', ', TRIM(p.nombre)),
                        p.nombre,
                        'Desconocido'
                    ) AS maquinista,
                    COUNT(d.sdm_egc_semanal_detalle_id) AS camaras_registradas
                FROM sdm_egc_semanal s
                LEFT JOIN persona p ON p.persona_id = s.maquinista_id
                LEFT JOIN sdm_egc_semanal_detalle d ON d.sdm_egc_semanal_id = s.sdm_egc_semanal_id AND d.activo = 1
                WHERE s.activo = 1
                GROUP BY s.sdm_egc_semanal_id, s.fecha, s.hora, p.nombre, p.apellido
                ORDER BY s.fecha DESC, s.hora DESC
                LIMIT $limite
            ")->fetchAll();
            resp(200, $rows);
        } catch(Exception $e) { resp(200, []); }
        break;

    // ── GET: detalle de un registro PR-9 ────────────────────
    case 'pr9_detalle':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) resp(400, ['error' => 'Falta ID']);
        try {
            $cab = $pdo->prepare("
                SELECT s.*, COALESCE(CONCAT(TRIM(p.apellido),', ',TRIM(p.nombre)), p.nombre, 'Desconocido') AS maquinista_nombre
                FROM sdm_egc_semanal s
                LEFT JOIN persona p ON p.persona_id = s.maquinista_id
                WHERE s.sdm_egc_semanal_id = ?
            ");
            $cab->execute([$id]);
            $cabecera = $cab->fetch();
            if (!$cabecera) resp(404, ['error' => 'Registro no encontrado']);
            $det = $pdo->prepare("
                SELECT d.*,
                       COALESCE(c.nombre_display, CONCAT('CÁMARA ', c.CamaraNumero)) AS camara_nombre
                FROM sdm_egc_semanal_detalle d
                LEFT JOIN Camaras c ON c.CamaraID = d.camara_id
                WHERE d.sdm_egc_semanal_id = ? AND d.activo = 1
                ORDER BY d.camara_id
            ");
            $det->execute([$id]);
            $detalles = $det->fetchAll();
            resp(200, ['cabecera' => $cabecera, 'detalles' => $detalles]);
        } catch(Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    // ── GET: lista equipos mantenimiento ────────────────────
    case 'mant_equipos_lista':
        $rows = $pdo->query("
            SELECT e.*, 
                CASE WHEN e.camara_id IS NOT NULL
                     THEN CONCAT('CÁMARA ', c.CamaraNumero)
                     WHEN e.espacio_comun_id IS NOT NULL
                     THEN ec.nombre
                     ELSE 'Sin asignar' END AS sector,
                d.nombre AS departamento
            FROM mant_equipos e
            LEFT JOIN Camaras c ON c.CamaraID = e.camara_id
            LEFT JOIN mant_espacios_comunes ec ON ec.id = e.espacio_comun_id
            LEFT JOIN departamento d ON d.departamento_id = e.responsable_depto_id
            WHERE e.activo = 1
            ORDER BY sector, e.categoria, e.nombre
        ")->fetchAll();
        resp(200, $rows);
        break;

    // ── GET: ficha completa de un equipo ─────────────────────
    case 'mant_equipo_ficha':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) resp(400, ['error' => 'ID requerido']);
        $stmt = $pdo->prepare("
            SELECT e.*,
                CASE WHEN e.camara_id IS NOT NULL
                     THEN CONCAT('CÁMARA ', c.CamaraNumero)
                     WHEN e.espacio_comun_id IS NOT NULL
                     THEN ec.nombre
                     ELSE 'Sin asignar' END AS sector,
                d.nombre AS departamento
            FROM mant_equipos e
            LEFT JOIN Camaras c ON c.CamaraID = e.camara_id
            LEFT JOIN mant_espacios_comunes ec ON ec.id = e.espacio_comun_id
            LEFT JOIN departamento d ON d.departamento_id = e.responsable_depto_id
            WHERE e.id = ?
        ");
        $stmt->execute([$id]);
        $eq = $stmt->fetch();
        if (!$eq) resp(404, ['error' => 'Equipo no encontrado']);
        resp(200, $eq);
        break;

    // ── GET: historial de estados de un equipo ───────────────
    case 'mant_historial':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) resp(400, ['error' => 'ID requerido']);
        $stmt = $pdo->prepare("
            SELECT * FROM mant_equipo_estados
            WHERE equipo_id = ?
            ORDER BY fecha_hora DESC LIMIT 50
        ");
        $stmt->execute([$id]);
        resp(200, $stmt->fetchAll());
        break;

    // ── POST: cambio de estado equipo mantenimiento ──────────
    case 'mant_upload_imagen':
        $equipoId = (int)($_POST['equipo_id'] ?? 0);
        if (!$equipoId) resp(400, ['error' => 'Falta equipo_id']);
        if (empty($_FILES['imagen'])) resp(400, ['error' => 'Sin archivo']);

        $file    = $_FILES['imagen'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (!in_array($ext, $allowed)) resp(400, ['error' => 'Tipo no permitido']);
        if ($file['size'] > 10 * 1024 * 1024) resp(400, ['error' => 'Archivo demasiado grande (máx 10MB)']);

        $dir = __DIR__ . '/uploads/equipos/' . $equipoId . '/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $nombre   = date('Ymd_His') . '_' . preg_replace('/[^a-zA-Z0-9_.]/', '_', basename($file['name']));
        $destino  = $dir . $nombre;
        $urlRelat = 'uploads/equipos/' . $equipoId . '/' . $nombre;

        if (!move_uploaded_file($file['tmp_name'], $destino)) {
            resp(500, ['error' => 'Error al guardar el archivo']);
        }

        // Registrar en DB si existe la tabla
        try {
            $pdo->prepare("
                INSERT INTO mant_equipo_imagenes (equipo_id, ruta, nombre_original, created_by, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ")->execute([$equipoId, $urlRelat, $file['name'], 1]);
        } catch(Exception $e) {
            // Si la tabla no existe, igual devolver ok con la URL
        }

        resp(200, ['ok' => true, 'url' => $urlRelat, 'nombre' => $nombre]);
        break;

    case 'mant_cambio_estado':
        $equipoId    = (int)($body['equipo_id'] ?? 0);
        $estadoNuevo = $body['estado_nuevo'] ?? '';
        $motivo      = $body['motivo']       ?? null;
        $usuario     = $body['usuario']      ?? 'Sistema';
        if (!$equipoId || !$estadoNuevo) resp(400, ['error' => 'Faltan datos']);

        $stmt = $pdo->prepare("SELECT estado FROM mant_equipos WHERE id = ?");
        $stmt->execute([$equipoId]);
        $estadoAnt = $stmt->fetchColumn();

        // Calcular minutos en estado anterior
        $stmtUlt = $pdo->prepare("SELECT fecha_hora FROM mant_equipo_estados WHERE equipo_id = ? ORDER BY fecha_hora DESC LIMIT 1");
        $stmtUlt->execute([$equipoId]);
        $ultFecha = $stmtUlt->fetchColumn();
        $minutos  = $ultFecha ? round((time() - strtotime($ultFecha)) / 60) : null;

        $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT INTO mant_equipo_estados (equipo_id,estado_anterior,estado_nuevo,fecha_hora,usuario,motivo,minutos_en_estado_anterior) VALUES(?,?,?,NOW(),?,?,?)")
                ->execute([$equipoId, $estadoAnt, $estadoNuevo, $usuario, $motivo, $minutos]);
            $pdo->prepare("UPDATE mant_equipos SET estado=?, updated_at=NOW() WHERE id=?")
                ->execute([$estadoNuevo, $equipoId]);
            $pdo->commit();
            resp(200, ['ok' => true]);
        } catch(Exception $e) {
            $pdo->rollBack();
            resp(500, ['error' => $e->getMessage()]);
        }
        break;


    // ── POST: editar equipo de mantenimiento ─────────────────
    case 'mant_editar_equipo':
        $id = (int)($body['id'] ?? 0);
        if (!$id) resp(400, ['error' => 'Falta id']);

        $campos_permitidos = [
            'marca', 'modelo', 'numero_serie', 'criticidad',
            'anio_fabricacion', 'anio_instalacion',
            'proveedor', 'empresa_instaladora',
            'fecha_compra', 'valor_compra',
            'requiere_periodico', 'periodicidad_dias',
            'ultimo_mantenimiento', 'manual_tecnico_url',
        ];

        $set_parts = [];
        $params    = [];

        foreach ($campos_permitidos as $campo) {
            if (array_key_exists($campo, $body)) {
                $val = $body[$campo];
                // Convertir vacíos a NULL
                if ($val === '' || $val === null) {
                    $set_parts[] = "`$campo` = NULL";
                } else {
                    $set_parts[] = "`$campo` = ?";
                    $params[]    = $val;
                }
            }
        }

        // Calcular proximo_mantenimiento si hay ultimo + periodicidad
        $ult   = $body['ultimo_mantenimiento'] ?? null;
        $per   = isset($body['periodicidad_dias']) && $body['periodicidad_dias'] !== '' ? (int)$body['periodicidad_dias'] : null;
        $req   = isset($body['requiere_periodico']) ? (int)$body['requiere_periodico'] : null;
        if ($ult && $per && $req) {
            $set_parts[] = "`proximo_mantenimiento` = DATE_ADD(?, INTERVAL ? DAY)";
            $params[]    = $ult;
            $params[]    = $per;
        } elseif ($req === 0) {
            $set_parts[] = "`proximo_mantenimiento` = NULL";
        }

        // updated_by
        if (!empty($body['usuario'])) {
            $stmtU = $pdo->prepare("SELECT UsuarioID FROM Usuarios WHERE UsuarioNombre = ? AND activo = 1 LIMIT 1");
            $stmtU->execute([trim($body['usuario'])]);
            $uid = $stmtU->fetchColumn();
            if ($uid) {
                $set_parts[] = "`updated_by` = ?";
                $params[]    = (int)$uid;
            }
        }

        if (empty($set_parts)) resp(400, ['error' => 'Sin campos para actualizar']);

        $params[] = $id;
        $sql = "UPDATE `mant_equipos` SET " . implode(', ', $set_parts) . ", `updated_at` = NOW() WHERE `id` = ?";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            resp(200, ['ok' => true, 'rows' => $stmt->rowCount()]);
        } catch(Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── GET: espacios comunes ─────────────────────────────────
    case 'mant_espacios':
        $rows = $pdo->query("SELECT * FROM mant_espacios_comunes WHERE activo=1 ORDER BY nombre")->fetchAll();
        resp(200, $rows);
        break;

    // ── POST: nuevo espacio común ─────────────────────────────
    case 'mant_espacio_nuevo':
        $nombre = trim($body['nombre'] ?? '');
        $tipo   = $body['tipo'] ?? 'interior';
        if (!$nombre) resp(400, ['error' => 'Nombre requerido']);
        $stmt = $pdo->prepare("INSERT INTO mant_espacios_comunes (nombre, tipo) VALUES (?, ?)");
        $stmt->execute([$nombre, $tipo]);
        resp(200, ['ok' => true, 'id' => $pdo->lastInsertId()]);
        break;

    // ── POST: nuevo equipo mantenimiento ─────────────────────
    case 'mant_equipo_nuevo':
        $req = ['codigo_interno','nombre','categoria','fecha_alta'];
        foreach ($req as $f) {
            if (empty($body[$f])) resp(400, ['error' => "Falta campo: $f"]);
        }

        // Fix created_by: buscar por UsuarioNombre
        $createdBy = 1;
        if (!empty($body['created_by_usuario'])) {
            $stmt = $pdo->prepare("SELECT UsuarioID FROM Usuarios WHERE UsuarioNombre=? AND activo=1 LIMIT 1");
            $stmt->execute([trim($body['created_by_usuario'])]);
            $createdBy = $stmt->fetchColumn() ?: 1;
        } elseif (!empty($body['created_by_nombre'])) {
            $stmt = $pdo->prepare("SELECT persona_id FROM persona WHERE CONCAT(TRIM(nombre),' ',TRIM(apellido))=? AND activo=1 LIMIT 1");
            $stmt->execute([trim($body['created_by_nombre'])]);
            $createdBy = $stmt->fetchColumn() ?: 1;
        }

        // Calcular próximo mantenimiento
        $proxMant = null;
        if (!empty($body['requiere_periodico']) && !empty($body['ultimo_mantenimiento']) && !empty($body['periodicidad_dias'])) {
            $proxMant = date('Y-m-d', strtotime($body['ultimo_mantenimiento'] . ' + ' . (int)$body['periodicidad_dias'] . ' days'));
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO mant_equipos (
                    codigo_interno, nombre, categoria, subcategoria,
                    marca, modelo, numero_serie, anio_fabricacion, anio_instalacion,
                    proveedor, empresa_instaladora, valor_compra,
                    fecha_alta, fecha_compra,
                    camara_id, espacio_comun_id,
                    estado, criticidad, requiere_periodico,
                    periodicidad_dias, ultimo_mantenimiento, proximo_mantenimiento,
                    activo, created_by, created_at, updated_by, updated_at
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,NOW(),?,NOW())
            ");
            $stmt->execute([
                $body['codigo_interno'],   $body['nombre'],
                $body['categoria'],        $body['subcategoria']??null,
                $body['marca']??null,      $body['modelo']??null,
                $body['numero_serie']??null,
                !empty($body['anio_fabricacion']) ? (int)$body['anio_fabricacion'] : null,
                !empty($body['anio_instalacion'])  ? (int)$body['anio_instalacion']  : null,
                $body['proveedor']??null,  $body['empresa_instaladora']??null,
                !empty($body['valor_compra']) ? (float)$body['valor_compra'] : null,
                $body['fecha_alta'],       $body['fecha_compra']??null,
                !empty($body['camara_id'])      ? (int)$body['camara_id']      : null,
                !empty($body['espacio_comun_id']) ? (int)$body['espacio_comun_id'] : null,
                $body['estado']??'Operativo', $body['criticidad']??'media',
                !empty($body['requiere_periodico']) ? 1 : 0,
                !empty($body['periodicidad_dias']) ? (int)$body['periodicidad_dias'] : null,
                $body['ultimo_mantenimiento']??null,
                $proxMant,
                $createdBy, $createdBy
            ]);
            $newId = $pdo->lastInsertId();

            // Registrar estado inicial
            $pdo->prepare("INSERT INTO mant_equipo_estados (equipo_id,estado_nuevo,fecha_hora,usuario) VALUES(?,?,NOW(),?)")
                ->execute([$newId, $body['estado']??'Operativo', $body['created_by_nombre']??'Sistema']);

            resp(200, ['ok' => true, 'id' => $newId]);
        } catch(Exception $e) {
            resp(500, ['error' => $e->getMessage(), 'debug_code' => $e->getCode()]);
        }
        break;

    // ── GET: lista de maquinistas desde tabla persona ──────────
    case 'maquinistas':
        // Devuelve persona_id + apellido + nombre para que el formulario
        // PR-24 (y otros) pueda usar persona_id directamente como maquinista_id
        $rows = $pdo->query("
            SELECT
                p.persona_id,
                TRIM(p.apellido) AS apellido,
                TRIM(p.nombre)   AS nombre
            FROM persona p
            JOIN sdm_maquinista sm ON sm.persona_id = p.persona_id
            WHERE p.activo = 1 AND sm.estado = 1
            ORDER BY p.apellido, p.nombre
        ")->fetchAll();
        resp(200, $rows);
        break;

    // ── GET: temperatura actual por cámara (última lectura del día) ──
    case 'temperaturas_actuales':
        $rows = $pdo->query("
            SELECT
                m.camara_id,
                t.camara AS nombre_csv,
                t.temperatura,
                t.fecha,
                t.hora,
                CONCAT(t.fecha, ' ', t.hora) AS fecha_hora
            FROM temperaturas t
            INNER JOIN temperatura_camara_map m ON m.nombre_csv = t.camara
            INNER JOIN (
                SELECT camara, MAX(CONCAT(fecha, ' ', hora)) AS ultima
                FROM temperaturas
                WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)
                AND temperatura IS NOT NULL
                GROUP BY camara
            ) ult ON ult.camara = t.camara
                  AND CONCAT(t.fecha, ' ', t.hora) = ult.ultima
            WHERE m.camara_id IS NOT NULL
            ORDER BY m.camara_id
        ")->fetchAll();

        // También traer presiones más recientes
        try {
            $presiones = $pdo->query("
                SELECT temperatura, pres_succ_30, pres_succ_40, pres_desc, fecha, hora
                FROM temperaturas
                WHERE camara = 'PRESIONES'
                AND fecha >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)
                ORDER BY fecha DESC, hora DESC
                LIMIT 1
            ")->fetch();
        } catch(Exception $e) { $presiones = null; }

        resp(200, ['temperaturas' => $rows, 'presiones' => $presiones]);
        break;

    // ── GET: lista equipos industriales ─────────────────────────
    // ── GET: uso de equipos por mes ─────────────────────────
    // ── GET: horas de uso de troneras por mes y por unidad ─────
    case 'uso_troneras':
        try {
            // Por mes: suma de minutos (fecha_inicio_uso → fecha_fin_uso)
            $rows = $pdo->query("
                SELECT
                    DATE_FORMAT(fecha_fin_uso, '%b %y')  AS mes_label,
                    DATE_FORMAT(fecha_fin_uso, '%Y-%m')  AS mes_key,
                    tronera_asignada_id                  AS unidad_id,
                    COUNT(*)                             AS operaciones,
                    SUM(TIMESTAMPDIFF(MINUTE, fecha_inicio_uso, fecha_fin_uso)) AS minutos
                FROM transporte_tronera
                WHERE fecha_inicio_uso IS NOT NULL
                  AND fecha_fin_uso   IS NOT NULL
                  AND fecha_fin_uso >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY mes_key, mes_label, tronera_asignada_id
                ORDER BY mes_key ASC, tronera_asignada_id ASC
            ")->fetchAll();

            $porMes = [];
            $unidades = [];
            foreach ($rows as $r) {
                $mk  = $r['mes_key'];
                $ml  = $r['mes_label'];
                $uid = 'Tronera ' . $r['unidad_id'];
                if (!isset($porMes[$mk])) $porMes[$mk] = ['mes_key'=>$mk,'mes_label'=>$ml,'total_horas'=>0,'detalle'=>[]];
                $horas = round($r['minutos'] / 60, 1);
                $porMes[$mk]['detalle'][$uid] = ['horas'=>$horas,'operaciones'=>(int)$r['operaciones']];
                $porMes[$mk]['total_horas'] += $horas;
                $unidades[$uid] = true;
            }
            $unidadesList = array_keys($unidades);
            sort($unidadesList);
            $result = [];
            foreach ($porMes as $m) {
                $m['detalle'] = array_values(array_map(fn($u) => [
                    'id' => $u,
                    'horas' => $m['detalle'][$u]['horas'] ?? 0,
                    'operaciones' => $m['detalle'][$u]['operaciones'] ?? 0,
                ], $unidadesList));
                $result[] = $m;
            }
            resp(200, ['meses' => $result, 'unidades' => $unidadesList]);
        } catch(Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    // ── GET: horas de uso de boxes por mes y por unidad ─────────
    case 'uso_boxes':
        try {
            $rows = $pdo->query("
                SELECT
                    DATE_FORMAT(fecha_fin_uso, '%b %y')  AS mes_label,
                    DATE_FORMAT(fecha_fin_uso, '%Y-%m')  AS mes_key,
                    box_asignado_id                      AS unidad_id,
                    COUNT(*)                             AS operaciones,
                    SUM(TIMESTAMPDIFF(MINUTE, fecha_llegada, fecha_fin_uso)) AS minutos
                FROM transporte_box
                WHERE fecha_fin_uso IS NOT NULL
                  AND fecha_llegada IS NOT NULL
                  AND fecha_fin_uso >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY mes_key, mes_label, box_asignado_id
                ORDER BY mes_key ASC, box_asignado_id ASC
            ")->fetchAll();

            $porMes = [];
            $unidades = [];
            foreach ($rows as $r) {
                $mk  = $r['mes_key'];
                $ml  = $r['mes_label'];
                $uid = 'Box ' . $r['unidad_id'];
                if (!isset($porMes[$mk])) $porMes[$mk] = ['mes_key'=>$mk,'mes_label'=>$ml,'total_horas'=>0,'detalle'=>[]];
                $horas = round($r['minutos'] / 60, 1);
                $porMes[$mk]['detalle'][$uid] = ['horas'=>$horas,'operaciones'=>(int)$r['operaciones']];
                $porMes[$mk]['total_horas'] += $horas;
                $unidades[$uid] = true;
            }
            $unidadesList = array_keys($unidades);
            usort($unidadesList, fn($a,$b) => (int)substr($a,4) - (int)substr($b,4));
            $result = [];
            foreach ($porMes as $m) {
                $m['detalle'] = array_values(array_map(fn($u) => [
                    'id' => $u,
                    'horas' => $m['detalle'][$u]['horas'] ?? 0,
                    'operaciones' => $m['detalle'][$u]['operaciones'] ?? 0,
                ], $unidadesList));
                $result[] = $m;
            }
            resp(200, ['meses' => $result, 'unidades' => $unidadesList]);
        } catch(Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    // ── GET: horas de equipos filtrados por tipo ─────────────────
    // ?tipo=silcar|camion|limpieza
    case 'uso_equipos_tipo':
        // Calcula horas por estado usando diferencia entre registros consecutivos
        // Compatible con tabla equipo_estados que solo tiene columna 'estado'
        $tipoFiltro = strtolower(trim($_GET['tipo'] ?? ''));
        $condTipo = match($tipoFiltro) {
            'silcar'   => "AND LOWER(e.tipo) NOT LIKE '%camion%' AND LOWER(e.tipo) NOT LIKE '%robot%' AND LOWER(e.tipo) NOT LIKE '%limpie%'",
            'camion'   => "AND (LOWER(e.tipo) LIKE '%camion%' OR LOWER(e.tipo) LIKE '%cami%')",
            'limpieza' => "AND (LOWER(e.tipo) LIKE '%robot%' OR LOWER(e.tipo) LIKE '%limpie%')",
            default    => "",
        };
        try {
            // Traer todos los registros de los últimos 13 meses (1 extra para calcular duración del último estado)
            $rows = $pdo->query("
                SELECT es.equipo_id, es.estado, es.fecha_hora,
                       e.interno, e.denominacion, e.tipo
                FROM equipo_estados es
                JOIN equipos e ON e.id = es.equipo_id
                WHERE es.fecha_hora >= DATE_SUB(NOW(), INTERVAL 13 MONTH)
                  AND e.activo = 1
                  $condTipo
                ORDER BY es.equipo_id ASC, es.fecha_hora ASC
            ")->fetchAll();

            // Calcular duración de cada estado por diferencia entre registros consecutivos
            $porEquipo = [];
            foreach ($rows as $r) {
                $porEquipo[$r['equipo_id']][] = $r;
            }

            $porMes = [];
            foreach ($porEquipo as $eqId => $regs) {
                $n = count($regs);
                for ($i = 0; $i < $n; $i++) {
                    $reg     = $regs[$i];
                    $estado  = $reg['estado'];
                    if (!in_array($estado, ['En marcha','Falla','Cargando'])) continue;

                    $tsIni = strtotime($reg['fecha_hora']);
                    $tsFin = ($i + 1 < $n) ? strtotime($regs[$i+1]['fecha_hora']) : time();

                    $minutos = max(0, ($tsFin - $tsIni) / 60);
                    // Solo contar si el inicio está dentro de los 12 meses
                    if (strtotime($reg['fecha_hora']) < strtotime('-12 months')) continue;

                    $mk  = date('Y-m', $tsIni);
                    $ml  = date('M y', $tsIni);
                    $int = $reg['interno'];

                    if (!isset($porMes[$mk])) $porMes[$mk] = ['mes_key'=>$mk,'mes_label'=>$ml,'equipos'=>[]];
                    if (!isset($porMes[$mk]['equipos'][$int])) {
                        $porMes[$mk]['equipos'][$int] = [
                            'interno'=>$int,'denominacion'=>$reg['denominacion'],
                            'tipo'=>$reg['tipo'],'en_marcha'=>0,'falla'=>0,'cargando'=>0
                        ];
                    }
                    $horas = round($minutos / 60, 2);
                    if ($estado === 'En marcha') $porMes[$mk]['equipos'][$int]['en_marcha'] = round($porMes[$mk]['equipos'][$int]['en_marcha'] + $horas, 2);
                    elseif ($estado === 'Falla')    $porMes[$mk]['equipos'][$int]['falla']    = round($porMes[$mk]['equipos'][$int]['falla']    + $horas, 2);
                    elseif ($estado === 'Cargando') $porMes[$mk]['equipos'][$int]['cargando'] = round($porMes[$mk]['equipos'][$int]['cargando'] + $horas, 2);
                }
            }

            ksort($porMes);
            $result = [];
            foreach ($porMes as $m) {
                $eqs = array_values($m['equipos']);
                $result[] = [
                    'mes_key'        => $m['mes_key'],
                    'mes_label'      => $m['mes_label'],
                    'equipos'        => $eqs,
                    'total_marcha'   => round(array_sum(array_column($eqs,'en_marcha')), 2),
                    'total_falla'    => round(array_sum(array_column($eqs,'falla')), 2),
                    'total_cargando' => round(array_sum(array_column($eqs,'cargando')), 2),
                ];
            }
            resp(200, $result);
        } catch(Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    case 'uso_equipos':
        // Igual que uso_equipos_tipo pero sin filtro de tipo
        try {
            $rows = $pdo->query("
                SELECT es.equipo_id, es.estado, es.fecha_hora,
                       e.interno, e.denominacion, e.tipo
                FROM equipo_estados es
                JOIN equipos e ON e.id = es.equipo_id
                WHERE es.fecha_hora >= DATE_SUB(NOW(), INTERVAL 13 MONTH)
                  AND e.activo = 1
                ORDER BY es.equipo_id ASC, es.fecha_hora ASC
            ")->fetchAll();

            $porEquipo = [];
            foreach ($rows as $r) {
                $porEquipo[$r['equipo_id']][] = $r;
            }

            $porMes = [];
            foreach ($porEquipo as $eqId => $regs) {
                $n = count($regs);
                for ($i = 0; $i < $n; $i++) {
                    $reg    = $regs[$i];
                    $estado = $reg['estado'];
                    if (!in_array($estado, ['En marcha','Falla','Cargando'])) continue;

                    $tsIni = strtotime($reg['fecha_hora']);
                    $tsFin = ($i + 1 < $n) ? strtotime($regs[$i+1]['fecha_hora']) : time();
                    $minutos = max(0, ($tsFin - $tsIni) / 60);
                    if (strtotime($reg['fecha_hora']) < strtotime('-12 months')) continue;

                    $mk      = date('Y-m', $tsIni);
                    $ml      = date('M y', $tsIni);
                    $interno = $reg['interno'];

                    if (!isset($porMes[$mk])) $porMes[$mk] = ['mes_key'=>$mk,'mes_label'=>$ml,'equipos'=>[]];
                    if (!isset($porMes[$mk]['equipos'][$interno])) {
                        $porMes[$mk]['equipos'][$interno] = [
                            'interno'=>$interno,'denominacion'=>$reg['denominacion'],
                            'tipo'=>$reg['tipo'],'en_marcha'=>0,'falla'=>0,'cargando'=>0
                        ];
                    }
                    $horas = round($minutos / 60, 2);
                    if ($estado === 'En marcha') $porMes[$mk]['equipos'][$interno]['en_marcha'] = round($porMes[$mk]['equipos'][$interno]['en_marcha'] + $horas, 2);
                    elseif ($estado === 'Falla')    $porMes[$mk]['equipos'][$interno]['falla']    = round($porMes[$mk]['equipos'][$interno]['falla']    + $horas, 2);
                    elseif ($estado === 'Cargando') $porMes[$mk]['equipos'][$interno]['cargando'] = round($porMes[$mk]['equipos'][$interno]['cargando'] + $horas, 2);
                }
            }

            ksort($porMes);
            $result = [];
            foreach ($porMes as $mk => $m) {
                $eqs = array_values($m['equipos']);
                $result[] = [
                    'mes_key'        => $mk,
                    'mes_label'      => $m['mes_label'],
                    'equipos'        => $eqs,
                    'total_marcha'   => round(array_sum(array_column($eqs,'en_marcha')), 2),
                    'total_falla'    => round(array_sum(array_column($eqs,'falla')), 2),
                    'total_cargando' => round(array_sum(array_column($eqs,'cargando')), 2),
                ];
            }
            resp(200, $result);
        } catch(Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    case 'siguiente_codigo':
        $prefijo = strtoupper(trim($_GET['prefijo'] ?? 'EQ'));
        if (!preg_match('/^[A-Z]{2,5}$/', $prefijo)) resp(400, ['error' => 'Prefijo inválido']);
        try {
            $stmt = $pdo->prepare("
                SELECT codigo_interno FROM mant_equipos
                WHERE codigo_interno LIKE ? AND activo = 1
                ORDER BY CAST(SUBSTRING_INDEX(codigo_interno, '-', -1) AS UNSIGNED) DESC
                LIMIT 1
            ");
            $stmt->execute([$prefijo . '-%']);
            $ultimo = $stmt->fetchColumn();
            if ($ultimo) {
                preg_match('/-(\d+)$/', $ultimo, $m2);
                $num = isset($m2[1]) ? (int)$m2[1] + 1 : 1;
            } else {
                $num = 1;
            }
            resp(200, ['codigo' => $prefijo . '-' . str_pad($num, 6, '0', STR_PAD_LEFT)]);
        } catch(Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    case 'equipos_lista':
        $rows = $pdo->query("
            SELECT e.*,
                   COUNT(f.id) AS fallas_abiertas,
                   ult.usuario AS ultimo_usuario,
                   ult.fecha_hora AS ultimo_cambio
            FROM equipos e
            LEFT JOIN equipo_fallas f ON f.equipo_id = e.id AND f.resuelta = 0
            LEFT JOIN (
                SELECT equipo_id, usuario, fecha_hora
                FROM equipo_estados
                WHERE id IN (
                    SELECT MAX(id) FROM equipo_estados GROUP BY equipo_id
                )
            ) ult ON ult.equipo_id = e.id
            WHERE e.activo = 1
            GROUP BY e.id
            ORDER BY e.tipo, e.interno
        ")->fetchAll();
        resp(200, $rows);
        break;

    // ── GET: historial temperatura de una cámara ────────────────
    case 'temp_historial':
        $camaraId = (int)($_GET['camara_id'] ?? 0);
        if (!$camaraId) resp(400, ['error' => 'camara_id requerido']);

        // Buscar nombre CSV de esta cámara
        $stmtMap = $pdo->prepare("SELECT nombre_csv FROM temperatura_camara_map WHERE camara_id = ? AND activo = 1 LIMIT 1");
        $stmtMap->execute([$camaraId]);
        $nombreCsv = $stmtMap->fetchColumn();
        if (!$nombreCsv) resp(200, ['registros' => [], 'camara_id' => $camaraId]);

        // Calcular rango de fechas
        if (!empty($_GET['desde']) && !empty($_GET['hasta'])) {
            $desde = $_GET['desde'];
            $hasta = $_GET['hasta'];
        } else {
            $dias  = max(0, (int)($_GET['dias'] ?? 0));
            $hasta = date('Y-m-d');
            $desde = $dias === 0
                ? date('Y-m-d')
                : date('Y-m-d', strtotime("-{$dias} days"));
        }

        // Para períodos largos agrupamos en PHP para no devolver miles de filas
        $diffDias = (strtotime($hasta) - strtotime($desde)) / 86400;

        if ($diffDias <= 2) {
            // Todas las lecturas individuales
            $sql = "SELECT fecha, hora, temperatura
                    FROM temperaturas
                    WHERE camara = ? AND fecha BETWEEN ? AND ?
                    AND temperatura IS NOT NULL
                    ORDER BY fecha ASC, hora ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombreCsv, $desde, $hasta]);
        } else {
            // Lecturas individuales igualmente — agrupación la hace el JS
            $sql = "SELECT fecha, hora, temperatura
                    FROM temperaturas
                    WHERE camara = ? AND fecha BETWEEN ? AND ?
                    AND temperatura IS NOT NULL
                    ORDER BY fecha ASC, hora ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombreCsv, $desde, $hasta]);
        }

        $rows = $stmt->fetchAll();
        resp(200, [
            'registros'  => $rows,
            'camara_id'  => $camaraId,
            'nombre_csv' => $nombreCsv,
            'desde'      => $desde,
            'hasta'      => $hasta,
            'total'      => count($rows),
        ]);
        break;

    // ── GET: estibas activas por cámara + total ─────────────────
    case 'estibas_activas':
        $rows = $pdo->query("
            SELECT c.CamaraID, COUNT(e.EstibaId) AS estibas_activas
            FROM Estiba e
            JOIN Posiciones p ON p.PosicionId = e.PosicionId
            JOIN Camaras c ON c.CamaraID = p.CamaraId
            WHERE e.FechaBaja IS NULL
            GROUP BY c.CamaraID
        ")->fetchAll();

        $total = $pdo->query("
            SELECT COUNT(*) FROM Estiba WHERE FechaBaja IS NULL
        ")->fetchColumn();

        // Convertir a mapa camara_id => cantidad
        $mapa = [];
        foreach ($rows as $r) {
            $mapa[(int)$r['CamaraID']] = (int)$r['estibas_activas'];
        }

        resp(200, ['por_camara' => $mapa, 'total' => (int)$total]);
        break;

    // ── POST: login usuario/contraseña → crea sesión PHP ────────
    case 'login_session':
        // Este endpoint acepta POST de formulario o JSON
        $data = $_POST ?: (json_decode(file_get_contents('php://input'), true) ?? []);
        $usuario  = trim($data['usuario']  ?? '');
        $password = trim($data['password'] ?? '');

        if (!$usuario || !$password) {
            header('Location: login.php?error=invalid'); exit;
        }

        // Buscar en tabla Usuarios
        // Verificar credenciales + datos de persona en un solo JOIN
        $stmt = $pdo->prepare("
            SELECT u.UsuarioID, u.UsuarioNombre,
                   u.persona_id, u.RolID,
                   CONCAT(TRIM(p.nombre), ' ', TRIM(p.apellido)) AS nombre_completo,
                   p.email1, p.email2
            FROM Usuarios u
            LEFT JOIN persona p ON p.persona_id = u.persona_id
            WHERE u.UsuarioNombre = ? AND u.UsuarioClave = SHA1(?) AND u.activo = 1
            LIMIT 1
        ");
        $stmt->execute([$usuario, $password]);
        $user = $stmt->fetch();

        if (!$user) {
            header('Location: login.php?error=invalid'); exit;
        }

        session_start();
        $_SESSION['cina_usuario_id']  = $user['UsuarioID'];
        $_SESSION['cina_usuario']     = $user['UsuarioNombre'];
        $_SESSION['cina_nombre']      = trim($user['nombre_completo']) ?: $user['UsuarioNombre'];
        $_SESSION['cina_persona_id']  = $user['persona_id'];
        $_SESSION['cina_email']       = $user['email1'] ?: '';
        $_SESSION['cina_auth_method'] = 'password';
        $_SESSION['cina_rol_id']      = (int)($user['RolID'] ?? 0);

        // Actualizar último login
        try {
            $pdo->prepare("UPDATE Usuarios SET UltimoLogin = NOW() WHERE UsuarioID = ?")
                ->execute([$user['UsuarioID']]);
        } catch(Exception $e) {}

        header('Location: index.php'); exit;
        break;

    // ── POST: login Google OAuth → valida email2 en persona ──────
    case 'login_google':
        $data  = json_decode(file_get_contents('php://input'), true) ?? [];
        $email = strtolower(trim($data['email'] ?? ''));
        $name  = trim($data['name'] ?? '');

        if (!$email) resp(400, ['ok' => false, 'error_code' => 'google_error']);

        // Buscar persona por email2
        $stmt = $pdo->prepare("
            SELECT p.persona_id, p.nombre, p.apellido,
                   u.UsuarioID, u.UsuarioNombre, u.activo
            FROM persona p
            LEFT JOIN Usuarios u ON u.persona_id = p.persona_id
            WHERE LOWER(TRIM(p.email2)) = ? AND p.activo = 1
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            resp(200, ['ok' => false, 'error_code' => 'google_email']);
        }

        if (($user['activo'] ?? 1) == 0) {
            resp(200, ['ok' => false, 'error_code' => 'inactive']);
        }

        session_start();
        // Obtener RolID para el login Google
        $stmtRolG = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
        $stmtRolG->execute([$user['UsuarioID']]);
        $rolIdGoogle = (int)$stmtRolG->fetchColumn();

        $_SESSION['cina_usuario_id']  = $user['UsuarioID'];
        $_SESSION['cina_usuario']     = $user['UsuarioNombre'];
        $_SESSION['cina_nombre']      = trim($user['nombre'] . ' ' . $user['apellido']);
        $_SESSION['cina_persona_id']  = $user['persona_id'];
        $_SESSION['cina_email']       = $email;
        $_SESSION['cina_auth_method'] = 'google';
        $_SESSION['cina_rol_id']      = $rolIdGoogle;

        // Actualizar último login
        if ($user['UsuarioID']) {
            $pdo->prepare("UPDATE Usuarios SET UltimoLogin = NOW() WHERE UsuarioID = ?")
                ->execute([$user['UsuarioID']]);
        }

        resp(200, ['ok' => true, 'nombre' => $_SESSION['cina_nombre']]);
        break;

    // ── GET: verificar sesión activa y obtener datos del usuario ─
    case 'verificar_sesion':
        if (empty($_SESSION['cina_usuario_id'])) {
            resp(200, ['autenticado' => false]);
        }

        // Actualizar UltimoLogin para detección de usuarios online
        try {
            $pdo->prepare("UPDATE Usuarios SET UltimoLogin = NOW() WHERE UsuarioID = ?")
                ->execute([$_SESSION['cina_usuario_id']]);
        } catch(Exception $e) {}

        // Traer datos completos de la persona
        $stmt = $pdo->prepare("
            SELECT p.persona_id, p.nombre, p.apellido, p.email1, p.email2,
                   p.telefono_movil, p.cuil, p.numero_documento,
                   p.fecha_nacimiento, p.direccion,
                   u.UsuarioNombre,
                   d.nombre AS departamento
            FROM persona p
            LEFT JOIN Usuarios u ON u.persona_id = p.persona_id
            LEFT JOIN departamento d ON d.responsable_departamento = p.persona_id
            WHERE p.persona_id = ?
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['cina_persona_id']]);
        $perfil = $stmt->fetch();

        // Obtener RolID del usuario
        $stmtRol = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
        $stmtRol->execute([$_SESSION['cina_usuario_id']]);
        $rolID = (int)$stmtRol->fetchColumn();

        // Obtener RolNombre (LEFT JOIN separado para no romper si falla)
        $rolNombre = '';
        try {
            if ($rolID) {
                $stmtRolN = $pdo->prepare("SELECT RolNombre FROM Roles WHERE RolID = ? LIMIT 1");
                $stmtRolN->execute([$rolID]);
                $rolNombre = $stmtRolN->fetchColumn() ?: '';
            }
        } catch (Exception $e) { $rolNombre = ''; }

        resp(200, [
            'autenticado'  => true,
            'usuario_id'   => $_SESSION['cina_usuario_id'],
            'usuario'      => $_SESSION['cina_usuario'],
            'nombre'       => $_SESSION['cina_nombre'],
            'persona_id'   => $_SESSION['cina_persona_id'],
            'email'        => $_SESSION['cina_email'],
            'auth_method'  => $_SESSION['cina_auth_method'],
            'rol_id'       => $rolID,
            'rol_nombre'   => $rolNombre,
            'perfil'       => $perfil ?: [],
        ]);
        break;

    // ── GET: cerrar sesión ────────────────────────────────────────
    case 'logout':
        session_destroy();
        header('Location: login.php'); exit;
        break;

    // ── GET: personas activas del sistema (para selector en pañol) ──
    case 'personas_activas':
        requireSession();
        $stmt = $pdo->prepare("
            SELECT TRIM(CONCAT(p.nombre, ' ', p.apellido)) AS nombre_completo,
                   u.UsuarioNombre AS usuario
            FROM Usuarios u
            JOIN persona p ON p.persona_id = u.persona_id
            WHERE u.activo = 1
            ORDER BY p.nombre, p.apellido
        ");
        $stmt->execute();
        $personas = $stmt->fetchAll();
        resp(200, ['personas' => $personas]);
        break;

    // ════════════════════════════════════════════════════════════
    //  MÓDULO CARGAS — Lista, Ver, Crear, Editar
    // ════════════════════════════════════════════════════════════

    // ── GET: diagnóstico columnas transporte_carga ─────────────
    case 'cargas_debug':
        requireSession();
        try {
            $cols = $pdo->query("
                SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME IN ('transporte_carga','transporte_vehiculo','transporte_acoplado',
                                     'transporte_box','transporte_tronera','CargasResponsable',
                                     'transporte_conductor','Personas','personas')
                ORDER BY TABLE_NAME, ORDINAL_POSITION
            ")->fetchAll();

            $tables = [];
            foreach ($cols as $c) {
                $tn = $c['TABLE_NAME'] ?? $c['table_name'] ?? '';
                $tables[$tn][] = $c;
            }

            // También intentar un SELECT básico para ver si hay datos hoy
            $sample = $pdo->query("
                SELECT carga_numero, fecha_llegada FROM transporte_carga
                ORDER BY carga_numero DESC LIMIT 5
            ")->fetchAll();

            resp(200, ['tables' => $tables, 'sample' => $sample]);
        } catch(Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── GET: lista de cargas por fecha ──────────────────────────
    // Separación Nacional/Fiscal: via Clientes.fiscal (0=Nacional, 1=Fiscal)
    // transporte_carga no tiene columna fiscal — la distinción viene de Cargas/PreSalidas
    // Box y Tronera están en tablas propias (transporte_box, transporte_tronera) vinculadas por carga_numero
    // Columnas reales de transporte_carga: carga_numero, vehiculo, acoplado, entrada (bool),
    //   fecha_movimiento, hora_movimiento_inicio, hora_movimiento_fin,
    //   fecha_llegada, fecha_salida, tipo, responsable_carga (texto), precinto, contenedor
    case 'api_version':
        resp(200, ['version'=>'2026-04-02-v3', 'timestamp'=>date('Y-m-d H:i:s'), 'file'=>__FILE__]);
        break;

    case 'cargas_lista':
        // Schema confirmado de transporte_carga:
        //   carga_numero (PK auto), tipo (char N/F/null), entrada (tinyint 1/0), estado (char P/E/D/C/R/A/Z)
        //   responsable_carga (smallint FK), vehiculo (mediumint FK), acoplado (mediumint FK), conductor (mediumint FK)
        //   fecha_llegada_planeada (date), hora_llegada_planeada (time), fecha_llegada (datetime nullable)
        //   ticket_bascula_entrada, fecha_movimiento (date), hora_movimiento_inicio/fin (time)
        //   fecha_salida (datetime), ticket_bascula_salida, tara, peso_bruto, contenedor, precinto, precinto_msa
        // transporte_box:    carga_numero, estado, box_asignado_id  (NO tiene columna 'nombre')
        // transporte_tronera: carga_numero, estado, tronera_asignada_id (NO tiene columna 'nombre')
        requireSession();
        $fecha  = $_GET['fecha'] ?? date('Y-m-d');
        $fiscal = intval($_GET['fiscal'] ?? 0);
        // tipo = 'N' → Nacional | 'F' → Fiscal | NULL → ambos
        // Filtro portal cliente (vía cliente_ids GET o autodetección)
        $cargasPortalIds = null;
        $clienteIdsParam = $_GET['cliente_ids'] ?? '';
        if ($clienteIdsParam) {
            $cargasPortalIds = array_map('intval', explode(',', $clienteIdsParam));
        } elseif (function_exists('getClienteIdsPermitidos')) {
            $cargasPortalIds = getClienteIdsPermitidos($pdo, $_SESSION['cina_usuario_id'], $_SESSION['cina_rol_id'] ?? 0);
        }

        try {
            $tipoFiscal = $fiscal ? 'F' : 'N';
            $extraWhere = '';
            $extraParams = [];
            if ($cargasPortalIds !== null && !empty($cargasPortalIds)) {
                $phCli = implode(',', array_fill(0, count($cargasPortalIds), '?'));
                $extraWhere = " AND (TC.carga_numero IN (SELECT carga_numero FROM Cargas WHERE ClienteID IN ($phCli)) OR TC.carga_numero IN (SELECT carga_numero FROM PreSalidas WHERE ClienteID IN ($phCli)))";
                $extraParams = array_merge($cargasPortalIds, $cargasPortalIds);
            } elseif ($cargasPortalIds !== null && empty($cargasPortalIds)) {
                resp(200, ['cargas' => []]);
            }

            $stmt = $pdo->prepare("
                SELECT
                    TC.carga_numero                AS id,
                    TC.carga_numero,
                    TC.tipo                        AS planta,
                    TC.fecha_llegada_planeada      AS plan_llegada,
                    TC.hora_llegada_planeada,
                    TC.fecha_llegada               AS llegada,
                    TC.fecha_movimiento,
                    TC.hora_movimiento_inicio,
                    TC.hora_movimiento_fin,
                    TC.fecha_salida                AS salida,
                    TC.responsable_carga           AS responsable_id,
                    CONCAT(P.apellido,' ',P.nombre) AS responsable,
                    TC.ticket_bascula_entrada,
                    TC.ticket_bascula_salida,
                    TC.precinto,
                    TC.precinto_msa,
                    TC.contenedor,
                    TC.tara,
                    TC.peso_bruto,
                    TV.patente                     AS vehiculo,
                    TV.vehiculo_id,
                    TA.patente                     AS acoplado,
                    TA.acoplado_id,
                    TC.entrada,
                    TC.estado                      AS estado_raw,
                    PT.turno_id,
                    PT.franja                      AS turno_franja,
                    PT.fuera_programa              AS turno_fuera_programa,
                    PT.cliente_id                  AS turno_cliente_id,
                    (SELECT COUNT(*) FROM planificacion_documento PD WHERE PD.turno_id = PT.turno_id) AS turno_docs,
                    (CASE TC.estado
                        WHEN 'E' THEN 'En Planta'
                        WHEN 'P' THEN 'Planificado'
                        WHEN 'D' THEN 'Demorado'
                        WHEN 'C' THEN 'Cargando'
                        WHEN 'F' THEN 'Finalizado'
                        WHEN 'R' THEN 'Retirado'
                        WHEN 'A' THEN 'Cancelado'
                        WHEN 'Z' THEN 'Cerrado'
                        ELSE 'Planificado'
                    END)                           AS estado
                FROM transporte_carga TC
                LEFT JOIN transporte_vehiculo TV   ON TV.vehiculo_id  = TC.vehiculo
                LEFT JOIN transporte_acoplado TA   ON TA.acoplado_id  = TC.acoplado
                LEFT JOIN CargasResponsable CR ON CR.ResponsableID = TC.responsable_carga
                LEFT JOIN persona          P  ON P.persona_id    = CR.persona_id
                LEFT JOIN planificacion_turno PT ON PT.carga_numero = TC.carga_numero AND PT.estado = 'asignado'
                WHERE (TC.tipo IS NULL OR TC.tipo = ?)
                  AND (
                      DATE(TC.fecha_llegada) = ?
                      OR (TC.fecha_llegada IS NULL AND TC.fecha_llegada_planeada = ?)
                      OR TC.estado IN ('E','D','C','F')
                  )
                  $extraWhere
                ORDER BY FIELD(TC.estado,'E','D','C','F','P','R','A','Z'), COALESCE(TC.fecha_llegada, TC.fecha_llegada_planeada) DESC, TC.carga_numero DESC
            ");
            $stmt->execute(array_merge([$tipoFiscal, $fecha, $fecha], $extraParams));
            $rows = $stmt->fetchAll();

            // Box: transporte_box tiene box_asignado_id, no 'nombre' — mostrar el ID
            $stmtBox  = $pdo->prepare("
                SELECT CONCAT('Box ', b.numero)
                FROM transporte_box tb
                INNER JOIN box b ON b.id = tb.box_asignado_id
                WHERE tb.carga_numero = ? AND tb.estado IN ('A','U')
                ORDER BY tb.id DESC LIMIT 1
            ");
            $stmtTron = $pdo->prepare("
                SELECT CONCAT('T', t.numero)
                FROM transporte_tronera tt
                INNER JOIN tronera t ON t.id = tt.tronera_asignada_id
                WHERE tt.carga_numero = ? AND tt.estado IN ('A','U')
                ORDER BY tt.id DESC LIMIT 1
            ");
            $stmtVinc = $pdo->prepare("
                SELECT C.CargaID AS numero, CL.ClienteNombre AS cliente
                FROM Cargas C
                LEFT JOIN CLIENTES CL ON CL.ClienteID = C.ClienteID
                WHERE C.carga_numero = ?
                UNION ALL
                SELECT PS.PreSalidaID, CL.ClienteNombre
                FROM PreSalidas PS
                LEFT JOIN CLIENTES CL ON CL.ClienteID = PS.ClienteID
                WHERE PS.carga_numero = ?
            ");

            foreach ($rows as &$row) {
                $cn = (int)$row['carga_numero'];

                $stmtBox->execute([$cn]);
                $stmtTron->execute([$cn]);
                $row['box']    = $stmtBox->fetchColumn()  ?: null;
                $row['tronera']= $stmtTron->fetchColumn() ?: null;

                $stmtVinc->execute([$cn, $cn]);
                $vinc = $stmtVinc->fetchAll();

                $row['tipo']     = ((int)($row['entrada'] ?? 1)) ? 'Entrada' : 'Salida';
                $row['cargas']   = array_map(fn($x) => $x['numero'], $vinc);
                $clientesArr = array_values(array_unique(array_filter(
                    array_map(fn($x) => $x['cliente'], $vinc)
                )));

                // Si no hay clientes por operaciones, buscar desde el turno
                if (empty($clientesArr) && !empty($row['turno_id'])) {
                    $stmtTC = $pdo->prepare("SELECT CL.ClienteNombre FROM planificacion_turno PT JOIN CLIENTES CL ON CL.ClienteID = PT.cliente_id WHERE PT.turno_id = ? LIMIT 1");
                    $stmtTC->execute([(int)$row['turno_id']]);
                    $clTurno = $stmtTC->fetchColumn();
                    if ($clTurno) $clientesArr[] = $clTurno;
                }
                $row['clientes'] = $clientesArr;
            }
            unset($row);

            resp(200, ['cargas' => $rows]);

        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage(), 'cargas' => []]);
        }
        break;

    // ── GET: detalle de una carga (transporte_carga + datos) ───
    case 'cargas_ver':
        requireSession();
        $id = intval($_GET['id'] ?? 0);
        if (!$id) resp(400, ['error' => 'Falta id']);

        try {
            $stmt = $pdo->prepare("
                SELECT TC.*, TC.carga_numero AS id,
                    TV.patente                     AS vehiculo_patente,
                    TV.vehiculo_id,
                    TA.patente                     AS acoplado_patente,
                    TA.acoplado_id,
                    CONCAT(P.apellido,' ',P.nombre) AS responsable_nombre,
                    (CASE TC.estado
                        WHEN 'E' THEN 'En Planta'
                        WHEN 'P' THEN 'Planificado'
                        WHEN 'D' THEN 'Demorado'
                        WHEN 'C' THEN 'Cargando'
                        WHEN 'F' THEN 'Finalizado'
                        WHEN 'R' THEN 'Retirado'
                        WHEN 'A' THEN 'Cancelado'
                        WHEN 'Z' THEN 'Cerrado'
                        ELSE 'Planificado'
                    END) AS estado_texto
                FROM transporte_carga TC
                LEFT JOIN transporte_vehiculo TV  ON TV.vehiculo_id  = TC.vehiculo
                LEFT JOIN transporte_acoplado TA  ON TA.acoplado_id  = TC.acoplado
                LEFT JOIN CargasResponsable CR ON CR.ResponsableID = TC.responsable_carga
                LEFT JOIN persona          P  ON P.persona_id    = CR.persona_id
                WHERE TC.carga_numero = ?
            ");
            $stmt->execute([$id]);
            $carga = $stmt->fetch();
            if (!$carga) resp(404, ['error' => 'Carga no encontrada']);

            // Normalizar columnas para el frontend
            $carga['id'] = $carga['carga_numero'];  // Asegurar que id existe

            // ── IDs para los <select> del formulario ──────────────────────
            $carga['vehiculo_id']    = $carga['vehiculo']          ?? null;  // FK int → transporte_vehiculo
            $carga['acoplado_id']    = $carga['acoplado']          ?? null;  // FK int → transporte_acoplado
            $carga['conductor_id']   = $carga['conductor']         ?? null;  // FK int → transporte_conductor
            $carga['responsable_id'] = $carga['responsable_carga'] ?? null;  // FK int → CargasResponsable

            // ── Textos para la vista de detalle ───────────────────────────
            $carga['vehiculo']    = $carga['vehiculo_patente']  ?? null;
            $carga['acoplado']    = $carga['acoplado_patente']  ?? null;
            $carga['responsable'] = $carga['responsable_nombre'] ?? null;
            $carga['estado_raw']  = $carga['estado']            ?? null;  // P/E/D/C/F/R/A/Z
            $carga['estado']      = $carga['estado_texto']      ?? $carga['estado'] ?? null;
            $carga['plan_llegada']= $carga['fecha_llegada_planeada'] ?? null;
            $carga['llegada']     = $carga['fecha_llegada']     ?? null;
            $carga['salida']      = $carga['fecha_salida']      ?? null;
            $carga['movimiento']  = $carga['hora_movimiento_inicio'] ?? null;

            // ── Nombre del conductor ──────────────────────────────────────
            $condId = $carga['conductor'] ?? null;  // TC.conductor es el FK int
            $carga['conductor_id'] = $condId;
            $carga['conductor'] = null;
            if ($condId) {
                $sc = $pdo->prepare("SELECT CONCAT(apellido,' ',nombre) FROM transporte_conductor WHERE conductor_id=? LIMIT 1");
                $sc->execute([$condId]);
                $carga['conductor'] = $sc->fetchColumn() ?: 'ID:'.$condId;
            }

            // ── Acoplado: confirmar que se lee el nombre ──────────────────
            // (ya viene de acoplado_patente via JOIN)

            // ── Empresa de transporte ─────────────────────────────────────
            $carga['empresa_transporte'] = null;
            $vehIdEmp = $carga['vehiculo_id'] ?? null;
            if ($vehIdEmp) {
                $se = $pdo->prepare("SELECT TE.razon_social FROM transporte_vehiculo TV JOIN transporte_empresa TE ON TE.transporte_id = TV.transporte_id WHERE TV.vehiculo_id = ? LIMIT 1");
                $se->execute([$vehIdEmp]);
                $carga['empresa_transporte'] = $se->fetchColumn() ?: null;
            }

            // ── Hora movimiento: el campo es TIME (HH:MM:SS), truncar a HH:MM ─
            $carga['hora_movimiento_inicio'] = $carga['hora_movimiento_inicio']
                ? substr($carga['hora_movimiento_inicio'], 0, 5) : null;
            $carga['hora_movimiento_fin']    = $carga['hora_movimiento_fin']
                ? substr($carga['hora_movimiento_fin'],    0, 5) : null;

            // ── Box y tronera con número real ─────────────────────────────
            $bx = $pdo->prepare("SELECT CONCAT('Box ', b.numero) FROM transporte_box tb JOIN box b ON b.id=tb.box_asignado_id WHERE tb.carga_numero=? AND tb.estado IN ('A','U') ORDER BY tb.id DESC LIMIT 1");
            $bx->execute([$id]); $carga['box'] = $bx->fetchColumn() ?: null;
            $tr = $pdo->prepare("SELECT CONCAT('T', t.numero) FROM transporte_tronera tt JOIN tronera t ON t.id=tt.tronera_asignada_id WHERE tt.carga_numero=? AND tt.estado IN ('A','U') ORDER BY tt.id DESC LIMIT 1");
            $tr->execute([$id]); $carga['tronera'] = $tr->fetchColumn() ?: null;

            // Tipo y fiscal
            $scE = $pdo->prepare("
                SELECT C.CargaID, CL.fiscal
                FROM Cargas C JOIN CLIENTES CL ON CL.ClienteID = C.ClienteID
                WHERE C.carga_numero = ? LIMIT 1
            ");
            $scE->execute([$id]);
            $cargaRow = $scE->fetch();
            $carga['tipo']   = $cargaRow ? 'Entrada' : 'Salida';
            $carga['fiscal'] = $cargaRow ? (int)$cargaRow['fiscal'] : 0;

            // Cargas vinculadas con clientes
            $sc = $pdo->prepare("
                SELECT C.CargaID AS numero, 'Entrada' AS tipo_mov,
                       CL.ClienteNombre AS cliente, C.NroRemito AS remito
                FROM Cargas C LEFT JOIN CLIENTES CL ON CL.ClienteID = C.ClienteID
                WHERE C.carga_numero = ?
                UNION
                SELECT PS.PreSalidaID, 'Salida',
                       CL.ClienteNombre, PS.Remito
                FROM PreSalidas PS
                LEFT JOIN CLIENTES CL ON CL.ClienteID = PS.ClienteID
                WHERE PS.carga_numero = ?
            ");
            $sc->execute([$id, $id]);
            $carga['cargas'] = $sc->fetchAll();

            // ── Turno vinculado ────────────────────────────────────────
            $carga['turno'] = null;
            try {
                $st = $pdo->prepare("SELECT turno_id, franja, fecha, tipo, entrada, estado, cliente_id, notas_cliente FROM planificacion_turno WHERE carga_numero = ? AND estado = 'asignado' LIMIT 1");
                $st->execute([$id]);
                $turno = $st->fetch();
                if ($turno) {
                    $franjas = ['00-03','03-06','06-09','09-12','12-15','15-18','18-21','21-24'];
                    $turno['franja_label'] = $franjas[(int)$turno['franja']] ?? '';
                    $stCl = $pdo->prepare("SELECT ClienteNombre FROM CLIENTES WHERE ClienteID = ? LIMIT 1");
                    $stCl->execute([$turno['cliente_id']]);
                    $turno['cliente_nombre'] = $stCl->fetchColumn() ?: '';
                    $carga['turno'] = $turno;
                }
            } catch(Exception $e) {}

            // Insumos (tabla puede no existir aún)
            try {
                $si = $pdo->prepare("
                    SELECT CI.CargaInsumoId AS id, TI.Nombre AS nombre,
                           CI.Cantidad AS cantidad, CI.Observaciones AS observacion
                    FROM carga_insumo CI
                    JOIN tipo_insumo TI ON TI.TipoInsumoId = CI.TipoInsumoId
                    WHERE CI.carga_numero = ?
                ");
                $si->execute([$id]);
                $carga['insumos'] = $si->fetchAll();
            } catch(Exception $e) { $carga['insumos'] = []; }

            resp(200, ['carga' => $carga]);
        } catch(Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── GET: catálogos para el formulario ──────────────────────
    case 'cargas_catalogos':
        requireSession();
        try {
            // Responsables de carga
            try {
                $responsables = $pdo->query("
                    SELECT CR.ResponsableID AS id,
                           CONCAT(P.apellido,' ',P.nombre) AS nombre
                    FROM CargasResponsable CR
                    JOIN persona P ON P.persona_id = CR.persona_id
                    WHERE CR.Activo = 1
                    ORDER BY P.apellido, P.nombre
                ")->fetchAll();
            } catch(Exception $e) { $responsables = []; }

            // Vehículos — intentar nombre de tabla minúscula luego Propel
            try {
                $vehiculos = $pdo->query("SELECT vehiculo_id AS id, patente FROM transporte_vehiculo WHERE activo=1 ORDER BY patente")->fetchAll();
            } catch(Exception $e) {
                try { $vehiculos = $pdo->query("SELECT VehiculoId AS id, Patente AS patente FROM TransporteVehiculo WHERE Activo=1 ORDER BY Patente")->fetchAll(); }
                catch(Exception $e2) { $vehiculos = []; }
            }

            // Acoplados
            try {
                $acoplados = $pdo->query("SELECT acoplado_id AS id, patente FROM transporte_acoplado WHERE activo=1 ORDER BY patente")->fetchAll();
            } catch(Exception $e) {
                try { $acoplados = $pdo->query("SELECT AcopladoId AS id, Patente AS patente FROM TransporteAcoplado WHERE Activo=1 ORDER BY Patente")->fetchAll(); }
                catch(Exception $e2) { $acoplados = []; }
            }

            // Conductores
            try {
                $conductores = $pdo->query("SELECT conductor_id AS id, CONCAT(apellido,' ',nombre) AS nombre FROM transporte_conductor WHERE activo=1 ORDER BY apellido")->fetchAll();
            } catch(Exception $e) {
                try { $conductores = $pdo->query("SELECT ConductorId AS id, CONCAT(COALESCE(Nombre,''),' ',COALESCE(Apellido,'')) AS nombre FROM TransporteConductor WHERE Activo=1 ORDER BY Nombre")->fetchAll(); }
                catch(Exception $e2) { $conductores = []; }
            }

            // Insumos (tabla puede no existir)
            try { $insumos = $pdo->query("SELECT TipoInsumoId AS id, Nombre AS nombre FROM tipo_insumo WHERE Activo=1 ORDER BY Nombre")->fetchAll(); }
            catch(Exception $e) { $insumos = []; }

            resp(200, compact('responsables','vehiculos','acoplados','conductores','insumos'));
        } catch(Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── POST: crear nueva carga ─────────────────────────────────
    case 'cargas_crear':
        requireSession();
        $b = $body;
        // Generar próximo número de carga
        $ultimo = (int)$pdo->query("SELECT MAX(carga_numero) FROM transporte_carga")->fetchColumn();
        $nuevo  = $ultimo + 1;

        $pdo->prepare("
            INSERT INTO transporte_carga
              (carga_numero, tipo, entrada, vehiculo, acoplado, responsable_carga,
               fecha_llegada_planeada, hora_llegada_planeada, fecha_llegada, fecha_movimiento,
               hora_movimiento_inicio, hora_movimiento_fin,
               fecha_salida, ticket_bascula_entrada, ticket_bascula_salida,
               tara, peso_bruto, contenedor, precinto, precinto_msa,
               estado, usuario_id, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'P',?,NOW(),NOW())
        ")->execute([
            $nuevo,
            ($b['fiscal'] ?? 0) ? 'F' : 'N',
            (int)($b['entrada'] ?? 1),
            $b['vehiculo_id']   ?: null,
            $b['acoplado_id']   ?: null,
            intval($b['responsable_id'] ?? $b['responsable'] ?? 0),
            $b['plan_llegada']  ?: null,
            $b['hora_llegada_planeada'] ?: null,
            $b['llegada']       ?: null,
            $b['fecha_movimiento'] ?: null,
            $b['hora_movimiento_inicio'] ?: null,
            $b['hora_movimiento_fin']    ?: null,
            $b['salida']        ?: null,
            $b['ticket_bascula_entrada'] ?: null,
            $b['ticket_bascula_salida']  ?: null,
            $b['tara']          ?: null,
            $b['peso_bruto']    ?: null,
            $b['contenedor']    ?: null,
            $b['precinto']      ?: null,
            $b['precinto_msa']  ?: null,
            (int)($_SESSION['cina_usuario_id'] ?? 0),
        ]);

        // Insumos
        if (!empty($b['insumos'])) {
            $stmtIns = $pdo->prepare("
                INSERT INTO carga_insumo (carga_numero, TipoInsumoId, Cantidad, Observaciones)
                VALUES (?,?,?,?)
                ON DUPLICATE KEY UPDATE Cantidad=VALUES(Cantidad), Observaciones=VALUES(Observaciones)
            ");
            foreach ($b['insumos'] as $ins) {
                if (intval($ins['cantidad']) > 0) {
                    $stmtIns->execute([$nuevo, $ins['id'], $ins['cantidad'], $ins['observacion']??'']);
                }
            }
        }

        resp(201, ['ok' => true, 'id' => $nuevo, 'mensaje' => 'Carga N° '.$nuevo.' creada correctamente.']);
        break;

    // ── POST: editar carga existente ────────────────────────────
    case 'cargas_editar':
        requireSession();
        $b  = $body;
        $id = intval($b['id'] ?? 0);
        if (!$id) resp(400, ['error' => 'Falta id']);

        // Resolver responsable: frontend envía responsable_id
        $responsableVal = intval($b['responsable_id'] ?? $b['responsable'] ?? 0);

        // Validar que no se cambie responsable después de iniciar operación
        $stEstado = $pdo->prepare("SELECT estado, responsable_carga FROM transporte_carga WHERE carga_numero=?");
        $stEstado->execute([$id]);
        $cargaActual = $stEstado->fetch();
        if ($cargaActual && in_array($cargaActual['estado'], ['C','F','R','Z'])) {
            $responsableVal = intval($cargaActual['responsable_carga']);
        }

        $pdo->prepare("
            UPDATE transporte_carga SET
              vehiculo                 = ?,
              acoplado                 = ?,
              responsable_carga        = ?,
              fecha_llegada_planeada   = ?,
              fecha_llegada            = ?,
              fecha_movimiento         = ?,
              hora_movimiento_inicio   = ?,
              hora_movimiento_fin      = ?,
              fecha_salida             = ?,
              ticket_bascula_entrada   = ?,
              ticket_bascula_salida    = ?,
              tara                     = ?,
              peso_bruto               = ?,
              contenedor               = ?,
              precinto                 = ?,
              precinto_msa             = ?,
              updated_at               = NOW()
            WHERE carga_numero = ?
        ")->execute([
            $b['vehiculo_id']   ?: null,
            $b['acoplado_id']   ?: null,
            $responsableVal ?: 0,
            $b['plan_llegada']  ?: null,
            $b['llegada']       ?: null,
            $b['fecha_movimiento'] ?: null,
            $b['hora_movimiento_inicio'] ?: null,
            $b['hora_movimiento_fin']    ?: null,
            $b['salida']        ?: null,
            $b['ticket_bascula_entrada'] ?: null,
            $b['ticket_bascula_salida']  ?: null,
            $b['tara']          ?: null,
            $b['peso_bruto']    ?: null,
            $b['contenedor']    ?: null,
            $b['precinto']      ?: null,
            $b['precinto_msa']  ?: null,
            $id,
        ]);

        // Insumos: reemplazar
        $pdo->prepare("DELETE FROM carga_insumo WHERE carga_numero = ?")->execute([$id]);
        if (!empty($b['insumos'])) {
            $stmtIns = $pdo->prepare("
                INSERT INTO carga_insumo (carga_numero, TipoInsumoId, Cantidad, Observaciones)
                VALUES (?,?,?,?)
            ");
            foreach ($b['insumos'] as $ins) {
                if (intval($ins['cantidad']) > 0) {
                    $stmtIns->execute([$id, $ins['id'], $ins['cantidad'], $ins['observacion']??'']);
                }
            }
        }

        resp(200, ['ok' => true, 'mensaje' => 'Carga N° '.$id.' actualizada correctamente.']);
        break;

    case 'turno_detalle':
        requireSession();
        $turnoId = intval($_GET['turno_id'] ?? 0);
        if (!$turnoId) resp(400, ['error' => 'Falta turno_id']);
        try {
            $stmt = $pdo->prepare("
                SELECT PT.turno_id, PT.fecha, PT.franja, PT.tipo, PT.entrada,
                       PT.estado, PT.fuera_programa, PT.carga_numero,
                       PT.notas_cliente, PT.notas_operaciones,
                       PT.tipo_transporte, PT.tratamiento_id,
                       CL.ClienteNombre AS cliente_nombre,
                       TV.patente AS veh_patente,
                       TA.patente AS acop_patente
                FROM planificacion_turno PT
                LEFT JOIN CLIENTES CL ON CL.ClienteID = PT.cliente_id
                LEFT JOIN transporte_carga TC ON TC.carga_numero = PT.carga_numero
                LEFT JOIN transporte_vehiculo TV ON TV.vehiculo_id = TC.vehiculo
                LEFT JOIN transporte_acoplado TA ON TA.acoplado_id = TC.acoplado
                WHERE PT.turno_id = ?
            ");
            $stmt->execute([$turnoId]);
            $t = $stmt->fetch();
            if (!$t) resp(404, ['error' => 'Turno no encontrado']);
            // Labels de franja
            $franjas = ['00:00-03:00','03:00-06:00','06:00-09:00','09:00-12:00','12:00-15:00','15:00-18:00','18:00-21:00','21:00-24:00'];
            $t['franja_label'] = $franjas[(int)$t['franja']] ?? 'Franja '.$t['franja'];
            if ($t['fecha']) $t['fecha'] = (new DateTime($t['fecha']))->format('d/m/Y');
            resp(200, ['ok'=>true, 'turno'=>$t]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    case 'turno_documentos':
        requireSession();
        $turnoId = intval($_GET['turno_id'] ?? 0);
        if (!$turnoId) resp(400, ['error'=>'Falta turno_id']);
        try {
            $stmt = $pdo->prepare("SELECT doc_id AS id, nombre, tipo, path, created_at FROM planificacion_documento WHERE turno_id = ? ORDER BY created_at DESC");
            $stmt->execute([$turnoId]);
            resp(200, ['ok'=>true, 'documentos'=>$stmt->fetchAll()]);
        } catch (Exception $e) {
            resp(500, ['error'=>$e->getMessage()]);
        }
        break;

    case 'turno_reprogramar':
        requireSession();
        $body = json_input();
        $turnoId = intval($body['turno_id'] ?? 0);
        $fecha   = $body['fecha'] ?? '';
        $franja  = intval($body['franja'] ?? 0);
        if (!$turnoId || !$fecha) resp(400, ['error'=>'Falta turno_id o fecha']);
        try {
            $pdo->prepare("UPDATE planificacion_turno SET fecha = ?, franja = ?, franja_modificada = ? WHERE turno_id = ?")
                ->execute([$fecha, $franja, $franja, $turnoId]);
            resp(200, ['ok'=>true]);
        } catch (Exception $e) {
            resp(500, ['error'=>$e->getMessage()]);
        }
        break;

    case 'turno_desasignar_carga':
        requireSession();
        $body = json_input();
        $turnoId = intval($body['turno_id'] ?? 0);
        $cargaNumero = intval($body['carga_numero'] ?? 0);
        if (!$turnoId) resp(400, ['error'=>'Falta turno_id']);
        try {
            $pdo->prepare("UPDATE planificacion_turno SET estado = 'aceptado', carga_numero = NULL WHERE turno_id = ?")->execute([$turnoId]);
            resp(200, ['ok'=>true]);
        } catch (Exception $e) {
            resp(500, ['error'=>$e->getMessage()]);
        }
        break;

    case 'cargas_pesada':
        requireSession();
        $b  = $body;
        $id = intval($b['id'] ?? 0);
        if (!$id) resp(400, ['error' => 'Falta id']);

        try {
            $sets = [];
            $params = [];
            if (isset($b['ticket_bascula_entrada'])) { $sets[] = 'ticket_bascula_entrada = ?'; $params[] = $b['ticket_bascula_entrada']; }
            if (isset($b['tara']))                    { $sets[] = 'tara = ?'; $params[] = intval($b['tara']); }
            if (isset($b['peso_bruto']))              { $sets[] = 'peso_bruto = ?'; $params[] = intval($b['peso_bruto']); }
            if (isset($b['ticket_bascula_salida']))   { $sets[] = 'ticket_bascula_salida = ?'; $params[] = $b['ticket_bascula_salida']; }
            if (empty($sets)) resp(400, ['error' => 'Sin datos de pesada']);

            $sets[] = 'updated_at = NOW()';
            $params[] = $id;
            $sql = "UPDATE transporte_carga SET " . implode(', ', $sets) . " WHERE carga_numero = ?";
            $pdo->prepare($sql)->execute($params);
            resp(200, ['ok' => true]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── GET: estibas activas + última fecha por cámara específica ─
    case 'tuneles_header':
        // CamaraID: 2=Tunel2, 108=Tunel9, 109=Tunel10
        $camIds = [2, 108, 109];
        $result = [];

        foreach ($camIds as $cid) {
            // Contar estibas activas
            $stmtC = $pdo->prepare("
                SELECT COUNT(e.EstibaId) AS total
                FROM Estiba e
                JOIN Posiciones p ON p.PosicionId = e.PosicionId
                WHERE p.CamaraId = ? AND e.FechaBaja IS NULL
            ");
            $stmtC->execute([$cid]);
            $total = (int)$stmtC->fetchColumn();

            // Fecha más reciente en que se colocó una estiba en esa cámara
            $stmtF = $pdo->prepare("
                SELECT MAX(ep.FechaPosicion)
                FROM EstibaPosicion ep
                JOIN Posiciones p ON p.PosicionId = ep.PosicionId
                JOIN Estiba e ON e.EstibaId = ep.EstibaId
                WHERE p.CamaraId = ? AND e.FechaBaja IS NULL
            ");
            $stmtF->execute([$cid]);
            $fechaRaw = $stmtF->fetchColumn();

            // Formatear fecha para mostrar
            $fechaDisplay = '—';
            if ($fechaRaw) {
                $dt = new DateTime($fechaRaw);
                $fechaDisplay = $dt->format('d/m/Y H:i');
            }

            $result[$cid] = [
                'estibas' => $total,
                'fecha'   => $fechaDisplay,
            ];
        }

        // Total general estibas en planta
        $totalGeneral = (int)$pdo->query("
            SELECT COUNT(*) FROM Estiba WHERE FechaBaja IS NULL
        ")->fetchColumn();

        resp(200, [
            'tuneles'      => $result,
            'total_planta' => $totalGeneral,
        ]);
        break;

    // ── GET: todos los tiles del header en UNA sola llamada ────────
    case 'header_tiles':
        $out = [];

        // 1. Estibas activas por cámara en UNA query (usando índice FechaBaja)
        try {
            $rows = $pdo->query("
                SELECT p.CamaraId, COUNT(e.EstibaId) AS total
                FROM Estiba e
                JOIN Posiciones p ON p.PosicionId = e.PosicionId
                WHERE e.FechaBaja IS NULL
                GROUP BY p.CamaraId
            ")->fetchAll();
            $estibas_por_camara = [];
            $total_planta = 0;
            foreach ($rows as $r) {
                $estibas_por_camara[(int)$r['CamaraId']] = (int)$r['total'];
                $total_planta += (int)$r['total'];
            }
        } catch(Exception $e) {
            $estibas_por_camara = [];
            $total_planta = 0;
        }
        $out['estibas_por_camara'] = $estibas_por_camara;
        $out['total_planta']       = $total_planta;

        // 2. Fechas de los túneles en UNA sola query con CASE
        $tunelIds = [2, 108, 109];
        try {
            $stmt = $pdo->prepare("
                SELECT p.CamaraId,
                       MAX(ep.FechaPosicion) AS ultima_fecha
                FROM EstibaPosicion ep
                JOIN Posiciones p ON p.PosicionId = ep.PosicionId
                WHERE p.CamaraId IN (2, 108, 109)
                GROUP BY p.CamaraId
            ");
            $stmt->execute();
            $fechasPorCamara = [];
            foreach ($stmt->fetchAll() as $r) {
                $fechasPorCamara[(int)$r['CamaraId']] = $r['ultima_fecha'];
            }
        } catch(Exception $e) {
            $fechasPorCamara = [];
        }

        $mapaKeys = [2 => 'tunel2', 108 => 'tunel9', 109 => 'tunel10'];
        foreach ($mapaKeys as $cid => $key) {
            $out[$key . '_estibas'] = $estibas_por_camara[$cid] ?? 0;
            $fechaRaw = $fechasPorCamara[$cid] ?? null;
            $out[$key . '_fecha']   = $fechaRaw ? (new DateTime($fechaRaw))->format('d/m/Y H:i') : '—';
        }

        // 3. Vehículos en planta
        $boxes_activos = 0;
        $troneras_uso  = 0;
        try { $boxes_activos = (int)$pdo->query("SELECT COUNT(*) FROM transporte_box WHERE estado IN ('A','U')")->fetchColumn(); } catch(Exception $e) {}
        try { $troneras_uso  = (int)$pdo->query("SELECT COUNT(*) FROM transporte_tronera WHERE estado IN ('A','U')")->fetchColumn(); } catch(Exception $e) {}
        $out['vehiculos_planta'] = $boxes_activos + $troneras_uso;

        // Sin ubicación
        try {
            $out['sin_ubicacion'] = (int)$pdo->query("
                SELECT COUNT(*) FROM Estiba e
                WHERE e.PosicionID IS NULL AND e.FechaBaja IS NULL
            ")->fetchColumn();
        } catch(Exception $e) { $out['sin_ubicacion'] = 0; }

        // Estibas vencidas
        try {
            $out['estibas_vencidas'] = (int)$pdo->query("
                SELECT COUNT(DISTINCT pce.EstibaID)
                FROM ProductoCargaEstiba pce
                JOIN Estiba e ON e.EstibaID = pce.EstibaID
                WHERE pce.FechaVencimiento IS NOT NULL
                  AND pce.FechaVencimiento < CURDATE()
                  AND pce.Cantidad > 0
                  AND e.FechaBaja IS NULL
            ")->fetchColumn();
        } catch(Exception $e) { $out['estibas_vencidas'] = 0; }

        // Sin posición por cámara
        try {
            $rows2 = $pdo->query("
                SELECT p.CamaraID, COUNT(DISTINCT e.EstibaID) AS total
                FROM Estiba e
                JOIN (
                    SELECT ep.estiba_id, ep.posicion_id
                    FROM estiba_posicion ep
                    INNER JOIN (
                        SELECT estiba_id, MAX(ubicacion) AS max_ubic
                        FROM estiba_posicion
                        GROUP BY estiba_id
                    ) ult ON ult.estiba_id = ep.estiba_id
                           AND ult.max_ubic = ep.ubicacion
                ) ep_ult ON ep_ult.estiba_id = e.EstibaID
                JOIN Posiciones p ON p.PosicionID = ep_ult.posicion_id
                WHERE e.FechaBaja IS NULL
                  AND e.PosicionID IS NULL
                GROUP BY p.CamaraID
            ")->fetchAll();
            $sp = [];
            foreach ($rows2 as $r) { $sp[(int)$r['CamaraID']] = (int)$r['total']; }
            $out['sin_posicion_por_camara'] = $sp;
        } catch(Exception $e) { $out['sin_posicion_por_camara'] = []; }

        // Clientes con mercadería activa por cámara (siglas + color + si está fuera de sus cámaras asignadas)
        // Cadena: Estiba → Posiciones → Camaras, y Estiba → Cargas → CLIENTES
        try {
            $rowsCli = $pdo->query("
                SELECT
                    p.CamaraID,
                    ca.ClienteID,
                    cl.siglas,
                    cl.color,
                    cl.ClienteNombre,
                    -- 1 si el cliente tiene esta cámara asignada, 0 si no
                    CASE WHEN cc.camara_id IS NOT NULL THEN 1 ELSE 0 END AS camara_asignada
                FROM Estiba e
                JOIN Posiciones p   ON p.PosicionID = e.PosicionID
                JOIN Cargas ca      ON ca.CargaID   = e.CargaID
                JOIN CLIENTES cl    ON cl.ClienteID  = ca.ClienteID
                LEFT JOIN cliente_camara cc
                    ON cc.cliente_id = ca.ClienteID AND cc.camara_id = p.CamaraID
                WHERE e.FechaBaja IS NULL
                  AND e.PosicionID IS NOT NULL
                GROUP BY p.CamaraID, ca.ClienteID
                ORDER BY p.CamaraID, cl.ClienteNombre
            ")->fetchAll();

            // Agrupar por cámara: { camara_id: [ {ClienteID, siglas, color, camara_asignada}, ... ] }
            $clientesPorCamara = [];
            foreach ($rowsCli as $r) {
                $cid = (int)$r['CamaraID'];
                if (!isset($clientesPorCamara[$cid])) $clientesPorCamara[$cid] = [];
                $clientesPorCamara[$cid][] = [
                    'cliente_id'      => (int)$r['ClienteID'],
                    'siglas'          => $r['siglas']         ?: substr($r['ClienteNombre'], 0, 4),
                    'color'           => $r['color']          ?: null,
                    'nombre'          => $r['ClienteNombre'],
                    'camara_asignada' => (int)$r['camara_asignada'] === 1,
                ];
            }
            $out['clientes_por_camara'] = $clientesPorCamara;
        } catch(Exception $e) { $out['clientes_por_camara'] = []; }

        // Estado actual de cada puerta (último registro de camara_puerta_registro por cámara)
        try {
            $rowsPuertas = $pdo->query("
                SELECT r.camara_id, r.estado
                FROM camara_puerta_registro r
                INNER JOIN (
                    SELECT camara_id, MAX(CONCAT(fecha,' ',hora)) AS ultima
                    FROM camara_puerta_registro
                    GROUP BY camara_id
                ) u ON u.camara_id = r.camara_id
                   AND CONCAT(r.fecha,' ',r.hora) = u.ultima
            ")->fetchAll();
            $puertas = [];
            foreach ($rowsPuertas as $r) {
                $puertas[(int)$r['camara_id']] = $r['estado']; // 'Abierta' | 'Cerrada'
            }
            $out['puertas_estado'] = $puertas;
        } catch(Exception $e) { $out['puertas_estado'] = []; }

        resp(200, $out);
        break;

    // ══════════════════════════════════════════════════════════
    //  MÓDULO CLIENTES — Comercial y Planificación
    // ══════════════════════════════════════════════════════════

    // ── GET: listado paginado de clientes ─────────────────────
    case 'clientes_lista':
        $buscar    = '%' . trim($_GET['buscar'] ?? '') . '%';
        $fiscal    = (int)($_GET['fiscal']    ?? 0);
        $pagina    = max(1, (int)($_GET['pagina']   ?? 1));
        $porPagina = (int)($_GET['por_pagina'] ?? 20);
        $offset    = ($pagina - 1) * $porPagina;

        // Total para paginación
        $stmtTotal = $pdo->prepare("
            SELECT COUNT(*) FROM CLIENTES
            WHERE ClienteNombre LIKE ? AND fiscal = ?
        ");
        $stmtTotal->execute([$buscar, $fiscal]);
        $total     = (int)$stmtTotal->fetchColumn();
        $totalPags = max(1, (int)ceil($total / $porPagina));

        // Listado con conteos de PR-08 y cotizaciones
        // ORDER BY: primero activos, luego inactivos, dentro de cada grupo por nombre
        $stmt = $pdo->prepare("
            SELECT
                c.ClienteID, c.ClienteNombre, c.ClienteCUIT,
                c.ClienteLocalidad, c.ClienteContacto,
                c.ClienteTel, c.Activo, c.fiscal,
                c.factura_por, c.TipoMedicionTemperatura,
                (SELECT COUNT(*) FROM cliente_frio       cf WHERE cf.cliente_id = c.ClienteID) AS cant_pr08,
                (SELECT COUNT(*) FROM cliente_cotizacion cc WHERE cc.cliente_id = c.ClienteID) AS cant_cotizaciones
            FROM CLIENTES c
            WHERE c.ClienteNombre LIKE :buscar AND c.fiscal = :fiscal
            ORDER BY c.Activo DESC, c.ClienteNombre ASC
            LIMIT :lim OFFSET :off
        ");
        $stmt->bindValue(':buscar', $buscar,    PDO::PARAM_STR);
        $stmt->bindValue(':fiscal', $fiscal,    PDO::PARAM_INT);
        $stmt->bindValue(':lim',    $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':off',    $offset,    PDO::PARAM_INT);
        $stmt->execute();
        resp(200, [
            'clientes'      => $stmt->fetchAll(),
            'total'         => $total,
            'total_paginas' => $totalPags,
            'pagina_actual' => $pagina,
        ]);
        break;

    // ── GET: ficha completa de un cliente ─────────────────────
    case 'clientes_ficha':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) resp(400, ['error' => 'Falta id']);

        $stmt = $pdo->prepare("
            SELECT c.*, g.Nombre AS grupo_nombre
            FROM CLIENTES c
            LEFT JOIN CLIENTEGRUPO g ON g.GrupoID = c.GrupoID
            WHERE c.ClienteID = ?
        ");
        $stmt->execute([$id]);
        $cliente = $stmt->fetch();
        if (!$cliente) resp(404, ['error' => 'Cliente no encontrado']);

        // PR-08
        $stmtPR = $pdo->prepare("
            SELECT cliente_frio_id, descripcion_producto,
                   DATE_FORMAT(fecha_registro,'%d/%m/%Y') AS fecha_registro,
                   temp_almacenamiento_desde, temp_almacenamiento_hasta,
                   tratamiento_producto, updated_at
            FROM cliente_frio
            WHERE cliente_id = ?
            ORDER BY cliente_frio_id DESC
            LIMIT 10
        ");
        $stmtPR->execute([$id]);
        $pr08 = $stmtPR->fetchAll();

        // Cotizaciones
        $stmtCO = $pdo->prepare("
            SELECT cliente_cotizacion_id, nro_cotizacion,
                   DATE_FORMAT(fecha_vigencia,'%d/%m/%Y') AS fecha_vigencia,
                   precio_congelado, precio_mantenimiento_congelado,
                   precio_mano_obra_cargas, precio_mano_obra_descargas,
                   updated_at
            FROM cliente_cotizacion
            WHERE cliente_id = ?
            ORDER BY cliente_cotizacion_id DESC
            LIMIT 10
        ");
        $stmtCO->execute([$id]);
        $cotizaciones = $stmtCO->fetchAll();

        // Cámaras habilitadas
        try {
            $stmtCAM = $pdo->prepare("
                SELECT cc.camara_id
                FROM cliente_camara cc
                WHERE cc.cliente_id = ?
            ");
            $stmtCAM->execute([$id]);
            $camarasHab = array_column($stmtCAM->fetchAll(), 'camara_id');
        } catch(Exception $e) { $camarasHab = []; }

        resp(200, [
            'cliente'        => $cliente,
            'pr08'           => $pr08,
            'cotizaciones'   => $cotizaciones,
            'camaras_hab'    => $camarasHab,
        ]);
        break;

    // ── POST: crear nuevo cliente ─────────────────────────────
    case 'clientes_nuevo':
        requireSession();
        $data = $body;

        $nombre = trim($data['ClienteNombre'] ?? '');
        if (!$nombre) resp(400, ['error' => 'Razón social requerida']);

        $siglas = trim($data['siglas'] ?? '') ?: null;
        $color  = trim($data['color']  ?? '') ?: null;
        if ($siglas) {
            $chk = $pdo->prepare("SELECT ClienteID FROM CLIENTES WHERE siglas = ?");
            $chk->execute([$siglas]);
            if ($chk->fetch()) resp(409, ['error' => 'Las siglas ya están en uso por otro cliente']);
        }
        if ($color) {
            $chk = $pdo->prepare("SELECT ClienteID FROM CLIENTES WHERE color = ?");
            $chk->execute([$color]);
            if ($chk->fetch()) resp(409, ['error' => 'El color ya está en uso por otro cliente']);
        }

        $tipoMedicion = in_array($data['TipoMedicionTemperatura'] ?? '', ['S','D']) ? $data['TipoMedicionTemperatura'] : 'S';
        $facturaPor   = in_array($data['factura_por'] ?? '', ['P','B','K']) ? $data['factura_por'] : 'P';

        $stmt = $pdo->prepare("
            INSERT INTO CLIENTES
                (ClienteNombre, ClienteCUIT, ClienteTel, email, ClienteContacto,
                 ClienteDireccion, ClienteLocalidad, ClienteProvincia,
                 codigo_postal, nro_iibb, tipo_cliente, factura_picking,
                 Activo, fiscal, ClienteFechaAlta, TipoMedicionTemperatura,
                 factura_por, email_alertas, whatsapp, color, siglas)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,?,?,?,?,?)
        ");
        $stmt->execute([
            $nombre,
            $data['ClienteCUIT']      ?? '',
            $data['ClienteTel']       ?? '',
            $data['email']            ?? '',
            $data['ClienteContacto']  ?? '',
            $data['ClienteDireccion'] ?? '',
            $data['ClienteLocalidad'] ?? '',
            $data['ClienteProvincia'] ?? '',
            $data['codigo_postal']    ?? '',
            $data['nro_iibb']         ?? '',
            $data['tipo_cliente']     ?? 'N',
            (int)($data['factura_picking'] ?? 0),
            (int)($data['Activo']     ?? 1),
            (int)($data['fiscal']     ?? 0),
            $tipoMedicion,
            $facturaPor,
            $data['email_alertas']    ?: null,
            $data['whatsapp']         ?: null,
            $color,
            $siglas,
        ]);
        $newId = (int)$pdo->lastInsertId();

        // Guardar cámaras habilitadas
        $camaras = $data['camaras_hab'] ?? [];
        if ($newId && is_array($camaras) && count($camaras)) {
            $stmtCC = $pdo->prepare("INSERT IGNORE INTO cliente_camara (cliente_id, camara_id) VALUES (?,?)");
            foreach ($camaras as $cid) { $stmtCC->execute([$newId, (int)$cid]); }
        }
        resp(200, ['ok' => true, 'id' => $newId]);
        break;

    // ── POST: editar cliente existente ────────────────────────
    case 'clientes_editar':
        requireSession();
        $data = $body;

        $id     = (int)($data['ClienteID'] ?? 0);
        $nombre = trim($data['ClienteNombre'] ?? '');
        if (!$id)     resp(400, ['error' => 'Falta ClienteID']);
        if (!$nombre) resp(400, ['error' => 'Razón social requerida']);

        $siglas = trim($data['siglas'] ?? '') ?: null;
        $color  = trim($data['color']  ?? '') ?: null;
        if ($siglas) {
            $chk = $pdo->prepare("SELECT ClienteID FROM CLIENTES WHERE siglas = ? AND ClienteID != ?");
            $chk->execute([$siglas, $id]);
            if ($chk->fetch()) resp(409, ['error' => 'Las siglas ya están en uso por otro cliente']);
        }
        if ($color) {
            $chk = $pdo->prepare("SELECT ClienteID FROM CLIENTES WHERE color = ? AND ClienteID != ?");
            $chk->execute([$color, $id]);
            if ($chk->fetch()) resp(409, ['error' => 'El color ya está en uso por otro cliente']);
        }

        $tipoMedicion = in_array($data['TipoMedicionTemperatura'] ?? '', ['S','D']) ? $data['TipoMedicionTemperatura'] : 'S';
        $facturaPor   = in_array($data['factura_por'] ?? '', ['P','B','K']) ? $data['factura_por'] : 'P';

        $stmt = $pdo->prepare("
            UPDATE CLIENTES SET
                ClienteNombre           = ?,
                ClienteCUIT             = ?,
                ClienteTel              = ?,
                email                   = ?,
                ClienteContacto         = ?,
                ClienteDireccion        = ?,
                ClienteLocalidad        = ?,
                ClienteProvincia        = ?,
                codigo_postal           = ?,
                nro_iibb                = ?,
                tipo_cliente            = ?,
                factura_picking         = ?,
                Activo                  = ?,
                TipoMedicionTemperatura = ?,
                factura_por             = ?,
                email_alertas           = ?,
                whatsapp                = ?,
                color                   = ?,
                siglas                  = ?
            WHERE ClienteID = ?
        ");
        $stmt->execute([
            $nombre,
            $data['ClienteCUIT']      ?? '',
            $data['ClienteTel']       ?? '',
            $data['email']            ?? '',
            $data['ClienteContacto']  ?? '',
            $data['ClienteDireccion'] ?? '',
            $data['ClienteLocalidad'] ?? '',
            $data['ClienteProvincia'] ?? '',
            $data['codigo_postal']    ?? '',
            $data['nro_iibb']         ?? '',
            $data['tipo_cliente']     ?? 'N',
            (int)($data['factura_picking'] ?? 0),
            (int)($data['Activo']     ?? 1),
            $tipoMedicion,
            $facturaPor,
            $data['email_alertas']    ?: null,
            $data['whatsapp']         ?: null,
            $color,
            $siglas,
            $id,
        ]);

        // Sincronizar cámaras: borrar las actuales y reinsertar
        $camaras = $data['camaras_hab'] ?? [];
        try {
            $pdo->prepare("DELETE FROM cliente_camara WHERE cliente_id = ?")->execute([$id]);
            if (is_array($camaras) && count($camaras)) {
                $stmtCC = $pdo->prepare("INSERT IGNORE INTO cliente_camara (cliente_id, camara_id) VALUES (?,?)");
                foreach ($camaras as $cid) { $stmtCC->execute([$id, (int)$cid]); }
            }
        } catch(Exception $e) {}

        resp(200, ['ok' => true]);
        break;

    // ── GET: historial de tiempo por estado de puerta por cámara y día ──
    case 'historial_puertas':

    // ══════════════════════════════════════════════════════════
    //  DETALLE COMPLETO DE CÁMARA (ocupación + puerta + estado)
    // ══════════════════════════════════════════════════════════
    case 'camara_detalle':
        $camara_id = (int)($_GET['camara_id'] ?? 0);
        // Soporta desde/hasta para filtro compartido de temperatura+puerta+ventilador
        // Mantiene compatibilidad con el parámetro ?fecha= anterior
        $desde = $_GET['desde'] ?? ($_GET['fecha'] ?? date('Y-m-d'));
        $hasta = $_GET['hasta'] ?? $desde;
        $fecha = $desde; // compatibilidad con código existente que usa $fecha
        if (!$camara_id) resp(400, ['error' => 'Falta camara_id']);

        $resultado = ['camara_id' => $camara_id, 'fecha' => $fecha, 'desde' => $desde, 'hasta' => $hasta];

        // ── 1. Estructura: calles, filas, niveles ───────────────
        try {
            $stmt = $pdo->prepare("SELECT CamaraCalles, CamaraFilas, CamaraNiveles FROM Camaras WHERE CamaraID = ?");
            $stmt->execute([$camara_id]);
            $estructura = $stmt->fetch();
            $resultado['calles']  = (int)($estructura['CamaraCalles'] ?? 0);
            $resultado['filas']   = (int)($estructura['CamaraFilas'] ?? 0);
            $resultado['niveles'] = (int)($estructura['CamaraNiveles'] ?? 0);
        } catch(Exception $e) {
            $resultado['calles'] = $resultado['filas'] = $resultado['niveles'] = 0;
        }

        // ── 2. Posiciones ocupadas (con cliente) ────────────────
        try {
            $stmt = $pdo->prepare("
                SELECT p.PosicionID, p.PosicionCalle, p.PosicionFila, p.PosicionNivel,
                       CASE WHEN e.EstibaID IS NOT NULL THEN 1 ELSE 0 END AS ocupada,
                       cl.siglas AS cliente_siglas,
                       cl.color AS cliente_color,
                       cl.ClienteNombre AS cliente_nombre
                FROM Posiciones p
                LEFT JOIN Estiba e ON e.PosicionID = p.PosicionID AND e.FechaBaja IS NULL
                LEFT JOIN Cargas ca ON ca.CargaID = e.CargaID
                LEFT JOIN CLIENTES cl ON cl.ClienteID = ca.ClienteID
                WHERE p.CamaraID = ?
                ORDER BY p.PosicionCalle, p.PosicionFila, p.PosicionNivel
            ");
            $stmt->execute([$camara_id]);
            $posiciones = $stmt->fetchAll();
            $resultado['posiciones'] = array_map(fn($p) => [
                'calle'   => (int)$p['PosicionCalle'],
                'fila'    => (int)$p['PosicionFila'],
                'nivel'   => (int)$p['PosicionNivel'],
                'ocupada' => (int)$p['ocupada'],
                'cliente' => $p['cliente_siglas'] ?: null,
                'color'   => $p['cliente_color'] ?: null,
            ], $posiciones);
            $resultado['total_posiciones'] = count($posiciones);
            $resultado['total_ocupadas']   = count(array_filter($posiciones, fn($p) => (int)$p['ocupada']));
        } catch(Exception $e) {
            $resultado['posiciones'] = [];
            $resultado['total_posiciones'] = 0;
            $resultado['total_ocupadas'] = 0;
            $resultado['error_posiciones'] = $e->getMessage();
        }

        // ── 3. Timeline de puerta del período ──────────────────
        try {
            $stmt = $pdo->prepare("
                SELECT fecha, hora, estado
                FROM camara_puerta_registro
                WHERE camara_id = ? AND fecha BETWEEN ? AND ?
                ORDER BY fecha ASC, hora ASC
            ");
            $stmt->execute([$camara_id, $desde, $hasta]);
            $resultado['puerta_eventos'] = $stmt->fetchAll();
        } catch(Exception $e) {
            $resultado['puerta_eventos'] = [];
        }

        // ── 4. Estado actual del equipo de frío ─────────────────
        try {
            $stmt = $pdo->prepare("
                SELECT temperatura, setpoint, estado, hora
                FROM temperaturas t
                JOIN temperatura_camara_map m ON m.nombre_csv = t.camara
                WHERE m.camara_id = ? AND t.fecha BETWEEN ? AND ? AND t.temperatura IS NOT NULL
                ORDER BY t.fecha DESC, t.hora DESC LIMIT 1
            ");
            $stmt->execute([$camara_id, $desde, $hasta]);
            $tempActual = $stmt->fetch();
            $resultado['temp_actual']  = $tempActual ? (float)$tempActual['temperatura'] : null;
            $resultado['setpoint']     = $tempActual ? (float)$tempActual['setpoint'] : null;
            $resultado['estado_frio']  = $tempActual['estado'] ?? null;
            $resultado['temp_hora']    = $tempActual['hora'] ?? null;
        } catch(Exception $e) {
            $resultado['temp_actual'] = null;
            $resultado['estado_frio'] = null;
        }

        // ── 5. Historial de estados del equipo de frío (timeline ventilador) ──
        try {
            $stmt = $pdo->prepare("
                SELECT t.fecha, t.hora, t.estado
                FROM temperaturas t
                JOIN temperatura_camara_map m ON m.nombre_csv = t.camara
                WHERE m.camara_id = ? AND t.fecha BETWEEN ? AND ? AND t.estado IS NOT NULL
                ORDER BY t.fecha ASC, t.hora ASC
            ");
            $stmt->execute([$camara_id, $desde, $hasta]);
            $resultado['estado_historial'] = $stmt->fetchAll();
        } catch(Exception $e) {
            $resultado['estado_historial'] = [];
        }

        resp(200, $resultado);
        break;

    // ══════════════════════════════════════════════════════════
        $fecha = $_GET['fecha'] ?? date('Y-m-d');

        // Traer todos los registros del día ordenados por cámara y hora
        $stmt = $pdo->prepare("
            SELECT r.camara_id, r.fecha, r.hora, r.estado,
                   c.CamaraNumero,
                   CASE
                     WHEN LOWER(t.TiposCamaraNombre) LIKE '%tunel%'
                       OR LOWER(t.TiposCamaraNombre) LIKE '%túnel%'     THEN CONCAT('Túnel ', c.CamaraNumero)
                     WHEN LOWER(t.TiposCamaraNombre) LIKE '%antecamara%'
                       OR LOWER(t.TiposCamaraNombre) LIKE '%antecámara%' THEN CONCAT('Antecámara ', c.CamaraNumero)
                     ELSE CONCAT('Cámara ', c.CamaraNumero)
                   END AS camara_nombre
            FROM camara_puerta_registro r
            JOIN Camaras c       ON c.CamaraID       = r.camara_id
            JOIN TiposCamara t   ON t.TiposCamaraID  = c.TiposCamaraID
            WHERE r.fecha = ?
            ORDER BY r.camara_id, r.hora
        ");
        $stmt->execute([$fecha]);
        $registros = $stmt->fetchAll();

        if (empty($registros)) {
            resp(200, ['fecha' => $fecha, 'camaras' => []]);
        }

        // Calcular tiempo en cada estado por cámara
        // Agrupa los registros por cámara y calcula duración entre cambios de estado
        $porCamara = [];
        foreach ($registros as $r) {
            $porCamara[$r['camara_id']]['nombre'] = $r['camara_nombre'];
            $porCamara[$r['camara_id']]['eventos'][] = [
                'hora'   => $r['hora'],
                'estado' => $r['estado'],
            ];
        }

        $resultado = [];
        foreach ($porCamara as $camaraId => $data) {
            $eventos    = $data['eventos'];
            $segsAbierta = 0;
            $segsCerrada = 0;
            $cantAperturas = 0;

            for ($i = 0; $i < count($eventos) - 1; $i++) {
                $tIni = strtotime('2000-01-01 ' . $eventos[$i]['hora']);
                $tFin = strtotime('2000-01-01 ' . $eventos[$i + 1]['hora']);
                $dur  = max(0, $tFin - $tIni);

                if ($eventos[$i]['estado'] === 'Abierta') {
                    $segsAbierta += $dur;
                    $cantAperturas++;
                } else {
                    $segsCerrada += $dur;
                }
            }

            $resultado[] = [
                'camara_id'       => $camaraId,
                'camara_nombre'   => $data['nombre'],
                'cant_aperturas'  => $cantAperturas,
                'segs_abierta'    => $segsAbierta,
                'segs_cerrada'    => $segsCerrada,
                'tiempo_abierta'  => gmdate('H:i:s', $segsAbierta),
                'tiempo_cerrada'  => gmdate('H:i:s', $segsCerrada),
                'ultimo_estado'   => end($eventos)['estado'],
                'total_eventos'   => count($eventos),
            ];
        }

        // Ordenar por nombre de cámara
        usort($resultado, fn($a, $b) => strcmp($a['camara_nombre'], $b['camara_nombre']));

        resp(200, ['fecha' => $fecha, 'camaras' => $resultado]);
        break;

    // ══════════════════════════════════════════════════════════
    //  INDICADOR: ESTIBAS INGRESADAS / EGRESADAS / MODIFICADAS
    // ══════════════════════════════════════════════════════════
    case 'estibas_ing_egr':
        $modo   = $_GET['modo']  ?? 'mes';
        $meses  = (int)($_GET['meses'] ?? 12);
        $dias   = (int)($_GET['dias']  ?? 30);

        // ── Helper interno: modificadas via movimiento (fallback robusto) ──
        // Una estiba "modificada" es aquella que tuvo un movimiento registrado
        // en el período pero NO se dio de baja en ese mismo período.
        // Usamos la tabla `movimiento` que registra todos los movimientos de stock.
        $fnModificadas = function(string $agrupacion, string $filtroFecha, PDO $pdo, int $param, bool $esDia): array {
            // Intentar primero con ProductoCargaEstiba_audit
            try {
                $campo  = $esDia ? "DATE(a.created_at)" : "DATE_FORMAT(a.created_at,'%Y-%m')";
                $stmt = $pdo->prepare("
                    SELECT $campo AS periodo, COUNT(DISTINCT a.EstibaId) AS total
                    FROM ProductoCargaEstiba_audit a
                    JOIN Estiba e ON e.EstibaID = a.EstibaId
                    WHERE a.created_at >= $filtroFecha
                      AND (e.FechaBaja IS NULL
                           OR $campo != " . ($esDia ? "DATE(e.FechaBaja)" : "DATE_FORMAT(e.FechaBaja,'%Y-%m')") . ")
                    GROUP BY periodo ORDER BY periodo
                ");
                $stmt->bindValue(':p', $param, PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll();
                if (!empty($rows)) {
                    $res = [];
                    foreach ($rows as $r) $res[$r['periodo']] = (int)$r['total'];
                    return $res;
                }
            } catch(Exception $e) {}

            // Fallback: tabla movimiento (EstibaId, FechaMovimiento o created_at)
            try {
                $campo  = $esDia ? "DATE(m.FechaMovimiento)" : "DATE_FORMAT(m.FechaMovimiento,'%Y-%m')";
                $stmt = $pdo->prepare("
                    SELECT $campo AS periodo, COUNT(DISTINCT m.EstibaId) AS total
                    FROM movimiento m
                    JOIN Estiba e ON e.EstibaID = m.EstibaId
                    WHERE m.FechaMovimiento >= $filtroFecha
                      AND (e.FechaBaja IS NULL
                           OR $campo != " . ($esDia ? "DATE(e.FechaBaja)" : "DATE_FORMAT(e.FechaBaja,'%Y-%m')") . ")
                    GROUP BY periodo ORDER BY periodo
                ");
                $stmt->bindValue(':p', $param, PDO::PARAM_INT);
                $stmt->execute();
                $res = [];
                foreach ($stmt->fetchAll() as $r) $res[$r['periodo']] = (int)$r['total'];
                return $res;
            } catch(Exception $e2) {}

            return [];
        };

        // ── Helper: kg por período ─────────────────────────────────────────
        // Suma de peso bruto (o neto) de las estibas creadas/dadas de baja en el período
        // Busca en Cargas el campo de kilos (PesoBruto, PesoNeto, Peso, kilos, kg, etc.)
        $fnKg = function(string $campoFecha, string $filtroFecha, PDO $pdo, int $param, bool $esDia): array {
            $agr = $esDia ? "DATE(e.$campoFecha)" : "DATE_FORMAT(e.$campoFecha,'%Y-%m')";
            // Intentar con columnas comunes de peso en Cargas
            $columnasPeso = ['PesoBruto', 'PesoNeto', 'Peso', 'kilos', 'kg', 'peso_bruto', 'peso_neto'];
            foreach ($columnasPeso as $col) {
                try {
                    $stmt = $pdo->prepare("
                        SELECT $agr AS periodo, COALESCE(SUM(ca.$col), 0) AS total_kg
                        FROM Estiba e
                        JOIN Cargas ca ON ca.CargaID = e.CargaID
                        WHERE e.$campoFecha >= $filtroFecha
                          AND e.$campoFecha IS NOT NULL
                        GROUP BY periodo ORDER BY periodo
                    ");
                    $stmt->bindValue(':p', $param, PDO::PARAM_INT);
                    $stmt->execute();
                    $rows = $stmt->fetchAll();
                    $res = [];
                    foreach ($rows as $r) $res[$r['periodo']] = round((float)$r['total_kg']);
                    return $res;
                } catch(Exception $e) { continue; }
            }
            return [];
        };

        if ($modo === 'mes') {
            $filtro = 'DATE_SUB(LAST_DAY(NOW()), INTERVAL :p MONTH)';

            // Ingresadas (cantidad y kg)
            $stmtIn = $pdo->prepare("
                SELECT DATE_FORMAT(FechaAlta,'%Y-%m') AS periodo, COUNT(*) AS total
                FROM Estiba
                WHERE FechaAlta >= DATE_SUB(LAST_DAY(NOW()), INTERVAL :p MONTH)
                  AND FechaAlta IS NOT NULL
                GROUP BY periodo ORDER BY periodo
            ");
            $stmtIn->bindValue(':p', $meses, PDO::PARAM_INT); $stmtIn->execute();
            $ingresadas = [];
            foreach ($stmtIn->fetchAll() as $r) $ingresadas[$r['periodo']] = (int)$r['total'];

            // Egresadas (cantidad y kg)
            $stmtOut = $pdo->prepare("
                SELECT DATE_FORMAT(FechaBaja,'%Y-%m') AS periodo, COUNT(*) AS total
                FROM Estiba
                WHERE FechaBaja >= DATE_SUB(LAST_DAY(NOW()), INTERVAL :p MONTH)
                  AND FechaBaja IS NOT NULL
                GROUP BY periodo ORDER BY periodo
            ");
            $stmtOut->bindValue(':p', $meses, PDO::PARAM_INT); $stmtOut->execute();
            $egresadas = [];
            foreach ($stmtOut->fetchAll() as $r) $egresadas[$r['periodo']] = (int)$r['total'];

            $modificadas = $fnModificadas('', $filtro, $pdo, $meses, false);
            $kgIn        = $fnKg('FechaAlta',  $filtro, $pdo, $meses, false);
            $kgOut       = $fnKg('FechaBaja',  $filtro, $pdo, $meses, false);

            // Construir períodos
            $periodos = [];
            $base = new DateTime('first day of this month');
            for ($i = $meses - 1; $i >= 0; $i--) {
                $d = clone $base; $d->modify("-$i month");
                $periodos[] = $d->format('Y-m');
            }

            $resultado = [];
            foreach ($periodos as $p) {
                $in  = $ingresadas[$p]  ?? 0;
                $out = $egresadas[$p]   ?? 0;
                $mod = $modificadas[$p] ?? 0;
                $dt  = DateTime::createFromFormat('Y-m', $p);
                $resultado[] = [
                    'periodo'     => $p,
                    'label'       => $dt ? mb_strtolower(strftime('%b %y', $dt->getTimestamp())) : $p,
                    'ingresadas'  => $in,
                    'egresadas'   => $out,
                    'modificadas' => $mod,
                    'total'       => $in + $out,
                    'kg_in'       => $kgIn[$p]  ?? 0,
                    'kg_out'      => $kgOut[$p] ?? 0,
                ];
            }

        } else {
            $filtro = 'DATE_SUB(CURDATE(), INTERVAL :p DAY)';

            $stmtIn = $pdo->prepare("
                SELECT DATE(FechaAlta) AS periodo, COUNT(*) AS total
                FROM Estiba
                WHERE FechaAlta >= DATE_SUB(CURDATE(), INTERVAL :p DAY)
                  AND FechaAlta IS NOT NULL
                GROUP BY periodo ORDER BY periodo
            ");
            $stmtIn->bindValue(':p', $dias, PDO::PARAM_INT); $stmtIn->execute();
            $ingresadas = [];
            foreach ($stmtIn->fetchAll() as $r) $ingresadas[$r['periodo']] = (int)$r['total'];

            $stmtOut = $pdo->prepare("
                SELECT DATE(FechaBaja) AS periodo, COUNT(*) AS total
                FROM Estiba
                WHERE FechaBaja >= DATE_SUB(CURDATE(), INTERVAL :p DAY)
                  AND FechaBaja IS NOT NULL
                GROUP BY periodo ORDER BY periodo
            ");
            $stmtOut->bindValue(':p', $dias, PDO::PARAM_INT); $stmtOut->execute();
            $egresadas = [];
            foreach ($stmtOut->fetchAll() as $r) $egresadas[$r['periodo']] = (int)$r['total'];

            $modificadas = $fnModificadas('', $filtro, $pdo, $dias, true);
            $kgIn        = $fnKg('FechaAlta', $filtro, $pdo, $dias, true);
            $kgOut       = $fnKg('FechaBaja', $filtro, $pdo, $dias, true);

            $periodos = [];
            for ($i = $dias - 1; $i >= 0; $i--) {
                $d = new DateTime(); $d->modify("-$i day");
                $periodos[] = $d->format('Y-m-d');
            }

            $resultado = [];
            foreach ($periodos as $p) {
                $in  = $ingresadas[$p]  ?? 0;
                $out = $egresadas[$p]   ?? 0;
                $mod = $modificadas[$p] ?? 0;
                $dt  = new DateTime($p);
                $resultado[] = [
                    'periodo'     => $p,
                    'label'       => $dt->format('d/m'),
                    'ingresadas'  => $in,
                    'egresadas'   => $out,
                    'modificadas' => $mod,
                    'total'       => $in + $out,
                    'kg_in'       => $kgIn[$p]  ?? 0,
                    'kg_out'      => $kgOut[$p] ?? 0,
                ];
            }
        }

        // ── Mes actual para el tile del menú ──────────────────────────────
        $mesActual = date('Y-m');
        $tileIn = $tileOut = 0;
        foreach ($resultado as $r) {
            if (str_starts_with($r['periodo'], $mesActual)) {
                $tileIn  = $r['ingresadas'];
                $tileOut = $r['egresadas'];
                break;
            }
        }

        resp(200, [
            'modo'      => $modo,
            'datos'     => $resultado,
            'tile_in'   => $tileIn,
            'tile_out'  => $tileOut,
            'mes_actual'=> $mesActual,
        ]);
        break;

    // ══════════════════════════════════════════════════════════
    //  INDICADOR: MOVIMIENTOS — TIEMPOS PROMEDIO POR TIPO VEHÍCULO
    // ══════════════════════════════════════════════════════════
    case 'movimientos_tiempos':
        $meses    = (int)($_GET['meses']    ?? 12);
        $tipoCarga= $_GET['tipo_carga'] ?? 'todos'; // 'E','I','todos'

        // Filtro de tipo de carga
        $filtroTipo = '';
        $paramsTipo = [];
        if ($tipoCarga !== 'todos' && $tipoCarga !== '') {
            $filtroTipo = "AND tc.tipo = :tipo_carga";
            $paramsTipo[':tipo_carga'] = $tipoCarga;
        }

        // Definición de combinaciones de vehículo
        // Cada una tiene: id, condición SQL para WHERE
        $combis = [
            'todos'    => ['label'=>'Todos',         'vt'=>null, 'at'=>null],
            'cont'     => ['label'=>'Contenedores',  'vt'=>7,    'at'=>2],   // Tractor + Porta Contenedor
            'semi'     => ['label'=>'Semirem. Ref.', 'vt'=>7,    'at'=>4],   // Tractor + Semi Carga Refrigerada
            'semiSeca' => ['label'=>'Semirem. Seco', 'vt'=>7,    'at'=>3],   // Tractor + Semi Carga Seca
            'balan'    => ['label'=>'Balancines',    'vt'=>1,    'at'=>null], // Balancín
            'chasis'   => ['label'=>'Chasis',        'vt'=>3,    'at'=>null], // Chasis
        ];

        // Construir períodos (últimos N meses)
        $periodos = [];
        $base = new DateTime('first day of this month');
        for ($i = $meses - 1; $i >= 0; $i--) {
            $d = clone $base; $d->modify("-$i month");
            $periodos[] = $d->format('Y-m');
        }
        $fechaDesde = (clone $base)->modify("-" . ($meses-1) . " month")->format('Y-m-01');

        // Tipos de carga disponibles
        try {
            $tiposDisp = $pdo->query("
                SELECT DISTINCT tipo FROM transporte_carga
                WHERE tipo IS NOT NULL AND tipo != ''
                ORDER BY tipo
            ")->fetchAll(PDO::FETCH_COLUMN);
        } catch(Exception $e) { $tiposDisp = []; }

        // Consulta base: promedio minutos por período, tipo_entrada, filtro vehículo
        $resultado = [];
        foreach ($combis as $vid => $cfg) {
            // Construir condición de vehículo
            $condVeh = '';
            $paramsVeh = [];
            if ($cfg['vt'] !== null) {
                $condVeh .= " AND tv.vehiculo_tipo_id = :vt_$vid";
                $paramsVeh[":vt_$vid"] = $cfg['vt'];
            }
            if ($cfg['at'] !== null) {
                $condVeh .= " AND ta.acoplado_tipo_id = :at_$vid";
                $paramsVeh[":at_$vid"] = $cfg['at'];
            }

            try {
                $sql = "
                    SELECT
                        DATE_FORMAT(tc.fecha_movimiento, '%Y-%m') AS periodo,
                        tc.entrada,
                        ROUND(AVG(
                            TIMESTAMPDIFF(MINUTE,
                                CONCAT(tc.fecha_movimiento,' ',tc.hora_movimiento_inicio),
                                CONCAT(tc.fecha_movimiento,' ',tc.hora_movimiento_fin)
                            )
                        ), 1) AS minutos_prom,
                        COUNT(*) AS cantidad
                    FROM transporte_carga tc
                    LEFT JOIN transporte_vehiculo tv ON tv.vehiculo_id = tc.vehiculo
                    LEFT JOIN transporte_acoplado ta ON ta.acoplado_id = tc.acoplado
                    WHERE tc.fecha_movimiento >= :fecha_desde
                      AND tc.hora_movimiento_inicio IS NOT NULL
                      AND tc.hora_movimiento_fin IS NOT NULL
                      AND tc.hora_movimiento_inicio != '00:00:00'
                      AND tc.hora_movimiento_fin   != '00:00:00'
                      AND tc.hora_movimiento_fin   > tc.hora_movimiento_inicio
                      $filtroTipo
                      $condVeh
                    GROUP BY periodo, tc.entrada
                    ORDER BY periodo, tc.entrada
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':fecha_desde', $fechaDesde);
                foreach ($paramsTipo as $k => $v) $stmt->bindValue($k, $v);
                foreach ($paramsVeh as $k => $v)  $stmt->bindValue($k, (int)$v, PDO::PARAM_INT);
                $stmt->execute();

                // Indexar por período y entrada/salida
                $porPeriodo = [];
                foreach ($stmt->fetchAll() as $r) {
                    $p = $r['periodo'];
                    $key = $r['entrada'] ? 'entrada' : 'salida';
                    $porPeriodo[$p][$key] = [
                        'min'      => (float)$r['minutos_prom'],
                        'horas'    => round((float)$r['minutos_prom'] / 60, 1),
                        'cantidad' => (int)$r['cantidad'],
                    ];
                }

                // Construir array de meses
                $mesesArr = [];
                foreach ($periodos as $p) {
                    $dt = DateTime::createFromFormat('Y-m', $p);
                    $ent = $porPeriodo[$p]['entrada'] ?? ['min'=>0,'horas'=>0,'cantidad'=>0];
                    $sal = $porPeriodo[$p]['salida']  ?? ['min'=>0,'horas'=>0,'cantidad'=>0];
                    $promMin = ($ent['min'] + $sal['min']) > 0
                        ? round(($ent['min'] + $sal['min']) / ($ent['min']>0 && $sal['min']>0 ? 2 : 1), 1)
                        : 0;
                    $mesesArr[] = [
                        'periodo'        => $p,
                        'label'          => $dt ? mb_strtolower(strftime('%b %y', $dt->getTimestamp())) : $p,
                        'entrada_horas'  => $ent['horas'],
                        'salida_horas'   => $sal['horas'],
                        'prom_horas'     => round($promMin / 60, 1),
                        'entrada_cant'   => $ent['cantidad'],
                        'salida_cant'    => $sal['cantidad'],
                    ];
                }

                $resultado[$vid] = [
                    'label' => $cfg['label'],
                    'meses' => $mesesArr,
                ];

            } catch(Exception $e) {
                $resultado[$vid] = ['label' => $cfg['label'], 'meses' => [], 'error' => $e->getMessage()];
            }
        }

        resp(200, [
            'vehiculos'   => array_keys($combis),
            'labels'      => array_column($combis, 'label', null),
            'datos'       => $resultado,
            'tipos_carga' => $tiposDisp,
            'meses'       => $periodos,
        ]);
        break;

    // ══════════════════════════════════════════════════════════
    //  INDICADOR: RRHH — usuarios online + movimientos por usuario
    // ══════════════════════════════════════════════════════════
    case 'rrhh_estadisticas':
        $meses   = (int)($_GET['meses']  ?? 12);
        $rol_id  = (int)($_GET['rol_id'] ?? 0);  // 0 = todos

        // ── 1. Usuarios online (activos en los últimos 15 minutos) ──
        $online = 0;
        $onlineDebug = '';
        // Intentar con diferentes nombres de columna
        foreach (['UltimoLogin', 'ultimo_login', 'last_login', 'last_activity'] as $col) {
            try {
                $online = (int)$pdo->query("
                    SELECT COUNT(*) FROM Usuarios
                    WHERE activo = 1
                      AND $col >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                ")->fetchColumn();
                $onlineDebug = "col=$col ok=$online";
                break;
            } catch(Exception $e) {
                $onlineDebug = "col=$col err=" . $e->getMessage();
                continue;
            }
        }

        // ── 2. Roles disponibles ───────────────────────────────────
        $roles = [];
        try {
            // Intentar tabla Roles
            $roles = $pdo->query("
                SELECT r.RolID AS rol_id, r.RolNombre AS nombre,
                       COUNT(u.UsuarioID) AS cantidad_usuarios
                FROM Roles r
                LEFT JOIN Usuarios u ON u.RolID = r.RolID AND u.activo = 1
                GROUP BY r.RolID, r.RolNombre
                ORDER BY r.RolNombre
            ")->fetchAll();
        } catch(Exception $e) {
            try {
                // Fallback: agrupar por perfil si no hay tabla Roles
                $roles = $pdo->query("
                    SELECT perfil AS rol_id, perfil AS nombre,
                           COUNT(*) AS cantidad_usuarios
                    FROM Usuarios WHERE activo = 1
                    GROUP BY perfil ORDER BY perfil
                ")->fetchAll();
            } catch(Exception $e2) { $roles = []; }
        }

        // ── 3. Lista de usuarios con su rol ───────────────────────
        $condRol = $rol_id > 0 ? "AND u.RolID = :rol_id" : "";
        $usuarios = [];

        // Intento 1: query completa con persona + roles
        try {
            $stmtU = $pdo->prepare("
                SELECT u.UsuarioID, u.UsuarioNombre,
                       COALESCE(CONCAT(TRIM(p.nombre),' ',TRIM(p.apellido)), u.UsuarioNombre) AS nombre_completo,
                       COALESCE(r.RolNombre, 'Sin rol') AS rol_nombre,
                       COALESCE(u.RolID, 0) AS rol_id,
                       u.UltimoLogin AS ultimo_login
                FROM Usuarios u
                LEFT JOIN persona p ON p.persona_id = u.persona_id
                LEFT JOIN Roles r   ON r.RolID = u.RolID
                WHERE u.activo = 1
                $condRol
                ORDER BY rol_nombre, u.UsuarioNombre
            ");
            if ($rol_id > 0) $stmtU->bindValue(':rol_id', $rol_id, PDO::PARAM_INT);
            $stmtU->execute();
            $usuarios = $stmtU->fetchAll();
        } catch(Exception $e) {
            // Intento 2: query simplificada sin persona
            try {
                $stmtU = $pdo->prepare("
                    SELECT u.UsuarioID, u.UsuarioNombre,
                           u.UsuarioNombre AS nombre_completo,
                           COALESCE(r.RolNombre, 'Sin rol') AS rol_nombre,
                           COALESCE(u.RolID, 0) AS rol_id,
                           NULL AS ultimo_login
                    FROM Usuarios u
                    LEFT JOIN Roles r ON r.RolID = u.RolID
                    WHERE u.activo = 1
                    $condRol
                    ORDER BY u.UsuarioNombre
                ");
                if ($rol_id > 0) $stmtU->bindValue(':rol_id', $rol_id, PDO::PARAM_INT);
                $stmtU->execute();
                $usuarios = $stmtU->fetchAll();
            } catch(Exception $e2) {
                // Intento 3: query mínima
                try {
                    $usuarios = $pdo->query("
                        SELECT UsuarioID, UsuarioNombre,
                               UsuarioNombre AS nombre_completo,
                               'Sin rol' AS rol_nombre,
                               0 AS rol_id,
                               NULL AS ultimo_login
                        FROM Usuarios WHERE activo = 1
                        ORDER BY UsuarioNombre
                    ")->fetchAll();
                } catch(Exception $e3) { $usuarios = []; }
            }
        }

        // ── 4. Movimientos por usuario por mes ────────────────────
        // Fuente 1: estiba_posicion (cuando fecha_posicion no es NULL)
        // Fuente 2: movimiento (tabla de movimientos con usuario_id)
        // Fuente 3: Estiba.updated_at o FechaAlta como fallback
        $fechaDesde = (new DateTime('first day of this month'))
            ->modify('-' . ($meses - 1) . ' month')
            ->format('Y-m-01');

        $condRolMov = $rol_id > 0 ? "AND u.RolID = :rol_id_mov" : "";
        $movRows    = [];

        // Fuente 1: estiba_posicion + estiba_intervenida_posicion con fecha_posicion NOT NULL
        try {
            $cnt = (int)$pdo->query("
                SELECT COUNT(*) FROM estiba_posicion
                WHERE fecha_posicion IS NOT NULL
                  AND usuario_id IS NOT NULL
                LIMIT 1
            ")->fetchColumn();

            if ($cnt > 0) {
                $stmtMov = $pdo->prepare("
                    SELECT u.UsuarioID, u.UsuarioNombre, sub.periodo, SUM(sub.movimientos) AS movimientos
                    FROM (
                        SELECT ep.usuario_id,
                               DATE_FORMAT(ep.fecha_posicion, '%Y-%m') AS periodo,
                               COUNT(*) AS movimientos
                        FROM estiba_posicion ep
                        WHERE ep.fecha_posicion >= :fecha_desde1
                          AND ep.usuario_id IS NOT NULL
                        GROUP BY ep.usuario_id, periodo
                        UNION ALL
                        SELECT eip.usuario_id,
                               DATE_FORMAT(eip.fecha_posicion, '%Y-%m') AS periodo,
                               COUNT(*) AS movimientos
                        FROM estiba_intervenida_posicion eip
                        WHERE eip.fecha_posicion >= :fecha_desde2
                          AND eip.usuario_id IS NOT NULL
                        GROUP BY eip.usuario_id, periodo
                    ) sub
                    JOIN Usuarios u ON u.UsuarioID = sub.usuario_id
                    WHERE u.activo = 1 $condRolMov
                    GROUP BY u.UsuarioID, u.UsuarioNombre, sub.periodo
                    ORDER BY sub.periodo, u.UsuarioNombre
                ");
                $stmtMov->bindValue(':fecha_desde1', $fechaDesde);
                $stmtMov->bindValue(':fecha_desde2', $fechaDesde);
                if ($rol_id > 0) $stmtMov->bindValue(':rol_id_mov', $rol_id, PDO::PARAM_INT);
                $stmtMov->execute();
                $movRows = $stmtMov->fetchAll();
            }
        } catch(Exception $e) { $movRows = []; }

        // Fuente 2: tabla movimiento (FK con estiba_posicion)
        if (empty($movRows)) {
            try {
                $stmtMov = $pdo->prepare("
                    SELECT u.UsuarioID, u.UsuarioNombre,
                           DATE_FORMAT(m.fecha_movimiento, '%Y-%m') AS periodo,
                           COUNT(*) AS movimientos
                    FROM movimiento m
                    JOIN Usuarios u ON u.UsuarioID = m.usuario_id
                    WHERE m.fecha_movimiento >= :fecha_desde
                      AND u.activo = 1
                      $condRolMov
                    GROUP BY u.UsuarioID, u.UsuarioNombre, periodo
                    ORDER BY periodo, u.UsuarioNombre
                ");
                $stmtMov->bindValue(':fecha_desde', $fechaDesde);
                if ($rol_id > 0) $stmtMov->bindValue(':rol_id_mov', $rol_id, PDO::PARAM_INT);
                $stmtMov->execute();
                $movRows = $stmtMov->fetchAll();
            } catch(Exception $e) { $movRows = []; }
        }

        // Fuente 3: Estiba por FechaAlta (no filtra por usuario pero da volumen)
        if (empty($movRows)) {
            try {
                // Intentar con updated_at si existe
                $stmtMov = $pdo->prepare("
                    SELECT u.UsuarioID, u.UsuarioNombre,
                           DATE_FORMAT(e.FechaAlta, '%Y-%m') AS periodo,
                           COUNT(*) AS movimientos
                    FROM Estiba e
                    JOIN Usuarios u ON u.UsuarioID = e.UsuarioID
                    WHERE e.FechaAlta >= :fecha_desde
                      AND u.activo = 1
                      $condRolMov
                    GROUP BY u.UsuarioID, u.UsuarioNombre, periodo
                    ORDER BY periodo, u.UsuarioNombre
                ");
                $stmtMov->bindValue(':fecha_desde', $fechaDesde);
                if ($rol_id > 0) $stmtMov->bindValue(':rol_id_mov', $rol_id, PDO::PARAM_INT);
                $stmtMov->execute();
                $movRows = $stmtMov->fetchAll();
            } catch(Exception $e) { $movRows = []; }
        }

        // Construir períodos
        $periodos = [];
        $base = new DateTime('first day of this month');
        for ($i = $meses - 1; $i >= 0; $i--) {
            $d = clone $base; $d->modify("-$i month");
            $periodos[] = $d->format('Y-m');
        }

        // Indexar movimientos: [usuario_id][periodo] = count
        $movIndex = [];
        foreach ($movRows as $r) {
            $movIndex[$r['UsuarioID']][$r['periodo']] = (int)$r['movimientos'];
        }

        // Totales por período (para el gráfico general)
        $totalPorPeriodo = [];
        foreach ($periodos as $p) {
            $dt = DateTime::createFromFormat('Y-m', $p);
            $sum = 0;
            foreach ($movIndex as $uid => $mmap) $sum += $mmap[$p] ?? 0;
            $totalPorPeriodo[] = [
                'periodo' => $p,
                'label'   => $dt ? mb_strtolower(strftime('%b %y', $dt->getTimestamp())) : $p,
                'total'   => $sum,
            ];
        }

        // Agregar movimientos a cada usuario
        $usuariosConMov = [];

        // Si la lista de usuarios está vacía pero hay movimientos, derivar usuarios de ahí
        if (empty($usuarios) && !empty($movRows)) {
            $userMap = [];
            foreach ($movRows as $r) {
                $uid = (int)$r['UsuarioID'];
                if (!isset($userMap[$uid])) {
                    $userMap[$uid] = [
                        'UsuarioID'        => $uid,
                        'UsuarioNombre'    => $r['UsuarioNombre'],
                        'nombre_completo'  => $r['UsuarioNombre'],
                        'rol_nombre'       => 'Sin rol',
                        'rol_id'           => 0,
                        'ultimo_login'     => null,
                    ];
                }
            }
            $usuarios = array_values($userMap);
        }

        foreach ($usuarios as $u) {
            $uid   = (int)$u['UsuarioID'];
            $total = array_sum($movIndex[$uid] ?? []);
            $porMes = [];
            foreach ($periodos as $p) {
                $porMes[] = $movIndex[$uid][$p] ?? 0;
            }
            $usuariosConMov[] = [
                'usuario_id'    => $uid,
                'usuario'       => $u['UsuarioNombre'],
                'nombre'        => $u['nombre_completo'],
                'rol_id'        => (int)$u['rol_id'],
                'rol_nombre'    => $u['rol_nombre'],
                'ultimo_login'  => $u['ultimo_login'],
                'total_mov'     => $total,
                'por_mes'       => $porMes,
            ];
        }

        // Ordenar por total de movimientos desc
        usort($usuariosConMov, fn($a,$b) => $b['total_mov'] - $a['total_mov']);

        resp(200, [
            'online'           => $online,
            'roles'            => $roles,
            'usuarios'         => $usuariosConMov,
            'periodos'         => $totalPorPeriodo,
            'meses_labels'     => array_column($totalPorPeriodo, 'label'),
            'debug_mov_count'  => count($movRows),
            'debug_online'     => $onlineDebug,
        ]);
        break;

    // ══════════════════════════════════════════════════════════
    //  INDICADOR: ESTIBAS CONGELADAS — TÚNELES 2, 9, 10
    // ══════════════════════════════════════════════════════════
    case 'tuneles_congeladas':
        $meses = (int)($_GET['meses'] ?? 12);

        // IDs de los túneles
        $tunel2  = 2;
        $tunel9  = 108;
        $tunel10 = 109;

        $fechaDesde = (new DateTime('first day of this month'))
            ->modify('-' . ($meses - 1) . ' month')
            ->format('Y-m-01');

        // Períodos
        $periodos = [];
        $base = new DateTime('first day of this month');
        for ($i = $meses - 1; $i >= 0; $i--) {
            $d = clone $base; $d->modify("-$i month");
            $periodos[] = $d->format('Y-m');
        }

        // Query: estibas distintas que pasaron por cada túnel en cada mes
        // Una estiba "pasó" si tuvo al menos 1 registro en estiba_posicion
        // cuya posicion_id pertenezca a ese túnel
        $queryTuneles = "
            SELECT
                DATE_FORMAT(ep.fecha_posicion, '%Y-%m') AS periodo,
                p.CamaraID,
                COUNT(DISTINCT ep.estiba_id)             AS estibas
            FROM estiba_posicion ep
            JOIN Posiciones p ON p.PosicionID = ep.posicion_id
            WHERE ep.fecha_posicion >= :fecha_desde
              AND ep.fecha_posicion IS NOT NULL
              AND p.CamaraID IN (:t2, :t9, :t10)
            GROUP BY periodo, p.CamaraID
            ORDER BY periodo, p.CamaraID
        ";

        $rows = [];
        try {
            $stmt = $pdo->prepare($queryTuneles);
            $stmt->bindValue(':fecha_desde', $fechaDesde);
            $stmt->bindValue(':t2',  $tunel2,  PDO::PARAM_INT);
            $stmt->bindValue(':t9',  $tunel9,  PDO::PARAM_INT);
            $stmt->bindValue(':t10', $tunel10, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();
        } catch(Exception $e) {
            resp(200, ['error' => $e->getMessage(), 'datos' => []]);
        }

        // Indexar por [periodo][camara_id]
        $idx = [];
        foreach ($rows as $r) {
            $idx[$r['periodo']][(int)$r['CamaraID']] = (int)$r['estibas'];
        }

        // Construir resultado
        $resultado = [];
        foreach ($periodos as $p) {
            $dt = DateTime::createFromFormat('Y-m', $p);
            $t2  = $idx[$p][$tunel2]  ?? 0;
            $t9  = $idx[$p][$tunel9]  ?? 0;
            $t10 = $idx[$p][$tunel10] ?? 0;
            $resultado[] = [
                'periodo' => $p,
                'label'   => $dt ? mb_strtolower(strftime('%b %y', $dt->getTimestamp())) : $p,
                'tunel2'  => $t2,
                'tunel9'  => $t9,
                'tunel10' => $t10,
                'total'   => $t2 + $t9 + $t10,
            ];
        }

        // Mes actual (último período)
        $mesActual = end($resultado);

        resp(200, [
            'datos'      => $resultado,
            'mes_actual' => $mesActual,
        ]);
        break;

    // ══════════════════════════════════════════════════════════
    //  INDICADOR: CLIENTES QUE OPERARON
    // ══════════════════════════════════════════════════════════
    case 'clientes_operaron':
        $periodo = $_GET['periodo'] ?? 'mes_actual'; // 'mes_actual' | 'mes_anterior'

        if ($periodo === 'mes_anterior') {
            $fechaDesde   = date('Y-m-01', strtotime('first day of last month'));
            $fechaHasta   = date('Y-m-t',  strtotime('last day of last month'));
            $labelPeriodo = date('M Y', strtotime('last month'));
        } else {
            $fechaDesde   = date('Y-m-01');
            $fechaHasta   = date('Y-m-d');
            $labelPeriodo = date('M Y');
        }

        // ── 1. Clientes con estibas CREADAS en el período ─────────────
        try {
            $rowsCreadas = $pdo->prepare("
                SELECT DISTINCT
                    cl.ClienteID,
                    cl.ClienteNombre,
                    cl.siglas,
                    cl.color,
                    COUNT(DISTINCT e.EstibaID) AS estibas_creadas
                FROM Estiba e
                JOIN ProductoCargaEstiba pce ON pce.EstibaID = e.EstibaID
                JOIN Cargas              ca  ON ca.CargaID   = pce.CargaID
                JOIN CLIENTES            cl  ON cl.ClienteID = ca.ClienteID
                WHERE e.FechaAlta BETWEEN :desde AND :hasta
                  AND pce.Cantidad > 0
                GROUP BY cl.ClienteID, cl.ClienteNombre, cl.siglas, cl.color
                ORDER BY cl.ClienteNombre
            ");
            $rowsCreadas->bindValue(':desde', $fechaDesde);
            $rowsCreadas->bindValue(':hasta', $fechaHasta);
            $rowsCreadas->execute();
            $creadas = $rowsCreadas->fetchAll();
        } catch(Exception $e) { $creadas = []; }

        // ── 2. Clientes con estibas ELIMINADAS en el período ──────────
        try {
            $rowsEliminadas = $pdo->prepare("
                SELECT DISTINCT
                    cl.ClienteID,
                    cl.ClienteNombre,
                    cl.siglas,
                    cl.color,
                    COUNT(DISTINCT e.EstibaID) AS estibas_eliminadas
                FROM Estiba e
                JOIN ProductoCargaEstiba pce ON pce.EstibaID = e.EstibaID
                JOIN Cargas              ca  ON ca.CargaID   = pce.CargaID
                JOIN CLIENTES            cl  ON cl.ClienteID = ca.ClienteID
                WHERE e.FechaBaja BETWEEN :desde AND :hasta
                GROUP BY cl.ClienteID, cl.ClienteNombre, cl.siglas, cl.color
                ORDER BY cl.ClienteNombre
            ");
            $rowsEliminadas->bindValue(':desde', $fechaDesde);
            $rowsEliminadas->bindValue(':hasta', $fechaHasta);
            $rowsEliminadas->execute();
            $eliminadas = $rowsEliminadas->fetchAll();
        } catch(Exception $e) { $eliminadas = []; }

        // ── 3. Clientes con mercadería VIGENTE DURANTE el período ─────
        //  Estibas activas en algún momento del rango:
        //  FechaAlta <= hasta  Y  (FechaBaja IS NULL  OR  FechaBaja >= desde)
        try {
            $rowsVigentes = $pdo->prepare("
                SELECT DISTINCT
                    cl.ClienteID,
                    cl.ClienteNombre,
                    cl.siglas,
                    cl.color,
                    COUNT(DISTINCT e.EstibaID) AS estibas_en_stock
                FROM Estiba e
                JOIN ProductoCargaEstiba pce ON pce.EstibaID = e.EstibaID
                JOIN Cargas              ca  ON ca.CargaID   = pce.CargaID
                JOIN CLIENTES            cl  ON cl.ClienteID = ca.ClienteID
                WHERE e.FechaAlta <= :hasta
                  AND (e.FechaBaja IS NULL OR e.FechaBaja >= :desde)
                  AND pce.Cantidad > 0
                GROUP BY cl.ClienteID, cl.ClienteNombre, cl.siglas, cl.color
                ORDER BY cl.ClienteNombre
            ");
            $rowsVigentes->bindValue(':desde', $fechaDesde);
            $rowsVigentes->bindValue(':hasta', $fechaHasta);
            $rowsVigentes->execute();
            $vigentes = $rowsVigentes->fetchAll();
        } catch(Exception $e) { $vigentes = []; }

        // ── Unificar por ClienteID ─────────────────────────────────
        $mapa = [];

        foreach ($creadas as $r) {
            $id = (int)$r['ClienteID'];
            $mapa[$id] = [
                'cliente_id'         => $id,
                'nombre'             => $r['ClienteNombre'],
                'siglas'             => $r['siglas'] ?: '',
                'color'              => $r['color']  ?: null,
                'estibas_creadas'    => (int)$r['estibas_creadas'],
                'estibas_eliminadas' => 0,
                'estibas_en_stock'   => 0,
            ];
        }
        foreach ($eliminadas as $r) {
            $id = (int)$r['ClienteID'];
            if (!isset($mapa[$id])) $mapa[$id] = [
                'cliente_id'         => $id,
                'nombre'             => $r['ClienteNombre'],
                'siglas'             => $r['siglas'] ?: '',
                'color'              => $r['color']  ?: null,
                'estibas_creadas'    => 0,
                'estibas_eliminadas' => 0,
                'estibas_en_stock'   => 0,
            ];
            $mapa[$id]['estibas_eliminadas'] = (int)$r['estibas_eliminadas'];
        }
        foreach ($vigentes as $r) {
            $id = (int)$r['ClienteID'];
            if (!isset($mapa[$id])) $mapa[$id] = [
                'cliente_id'         => $id,
                'nombre'             => $r['ClienteNombre'],
                'siglas'             => $r['siglas'] ?: '',
                'color'              => $r['color']  ?: null,
                'estibas_creadas'    => 0,
                'estibas_eliminadas' => 0,
                'estibas_en_stock'   => 0,
            ];
            $mapa[$id]['estibas_en_stock'] = (int)$r['estibas_en_stock'];
        }

        $lista = array_values($mapa);
        usort($lista, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));

        $totalCreadas    = array_sum(array_column($lista, 'estibas_creadas'));
        $totalEliminadas = array_sum(array_column($lista, 'estibas_eliminadas'));
        $totalStock      = array_sum(array_column($lista, 'estibas_en_stock'));

        resp(200, [
            'periodo'          => $periodo,
            'label_periodo'    => $labelPeriodo,
            'fecha_desde'      => $fechaDesde,
            'fecha_hasta'      => $fechaHasta,
            'clientes'         => $lista,
            'total_clientes'   => count($lista),
            'total_creadas'    => $totalCreadas,
            'total_eliminadas' => $totalEliminadas,
            'total_stock'      => $totalStock,
        ]);
        break;

    // ══════════════════════════════════════════════════════════
    //  CLIENTES QUE OPERARON — POR RANGO DE FECHAS (para facturación)
    // ══════════════════════════════════════════════════════════
    case 'clientes_operaron_rango':
        requireSession();
        $fechaDesde = $_GET['desde'] ?? date('Y-m-01');
        $fechaHasta = $_GET['hasta'] ?? date('Y-m-d');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDesde)) $fechaDesde = date('Y-m-01');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHasta)) $fechaHasta = date('Y-m-d');

        $errores_debug = [];

        // ── 1. Clientes con estibas CREADAS en el rango ───────────────
        try {
            $stmt = $pdo->prepare("
                SELECT DISTINCT cl.ClienteID, cl.ClienteNombre, cl.siglas, cl.color,
                       cl.factura_por, cl.TipoMedicionTemperatura,
                       COUNT(DISTINCT e.EstibaID) AS estibas_creadas
                FROM Estiba e
                JOIN ProductoCargaEstiba pce ON pce.EstibaID = e.EstibaID
                JOIN Cargas   ca ON ca.CargaID   = pce.CargaID
                JOIN CLIENTES cl ON cl.ClienteID = ca.ClienteID
                WHERE e.FechaAlta BETWEEN :desde AND :hasta
                  AND pce.Cantidad > 0
                GROUP BY cl.ClienteID, cl.ClienteNombre, cl.siglas, cl.color,
                         cl.factura_por, cl.TipoMedicionTemperatura
            ");
            $stmt->execute([':desde' => $fechaDesde, ':hasta' => $fechaHasta]);
            $creadas = $stmt->fetchAll();
        } catch(Exception $e) {
            $creadas = [];
            $errores_debug[] = 'creadas: '.$e->getMessage();
            error_log('[clientes_operaron_rango] creadas: '.$e->getMessage());
        }

        // ── 2. Clientes con estibas ELIMINADAS en el rango ────────────
        try {
            $stmt = $pdo->prepare("
                SELECT DISTINCT cl.ClienteID, cl.ClienteNombre, cl.siglas, cl.color,
                       cl.factura_por, cl.TipoMedicionTemperatura,
                       COUNT(DISTINCT e.EstibaID) AS estibas_eliminadas
                FROM Estiba e
                JOIN ProductoCargaEstiba pce ON pce.EstibaID = e.EstibaID
                JOIN Cargas   ca ON ca.CargaID   = pce.CargaID
                JOIN CLIENTES cl ON cl.ClienteID = ca.ClienteID
                WHERE e.FechaBaja BETWEEN :desde AND :hasta
                GROUP BY cl.ClienteID, cl.ClienteNombre, cl.siglas, cl.color,
                         cl.factura_por, cl.TipoMedicionTemperatura
            ");
            $stmt->execute([':desde' => $fechaDesde, ':hasta' => $fechaHasta]);
            $eliminadas = $stmt->fetchAll();
        } catch(Exception $e) {
            $eliminadas = [];
            $errores_debug[] = 'eliminadas: '.$e->getMessage();
            error_log('[clientes_operaron_rango] eliminadas: '.$e->getMessage());
        }

        // ── 3. Clientes con mercadería VIGENTE DURANTE el rango ───────
        //  FechaAlta <= hasta  Y  (FechaBaja IS NULL  OR  FechaBaja >= desde)
        try {
            $stmt = $pdo->prepare("
                SELECT DISTINCT cl.ClienteID, cl.ClienteNombre, cl.siglas, cl.color,
                       cl.factura_por, cl.TipoMedicionTemperatura,
                       COUNT(DISTINCT e.EstibaID) AS estibas_vigentes
                FROM Estiba e
                JOIN ProductoCargaEstiba pce ON pce.EstibaID = e.EstibaID
                JOIN Cargas   ca ON ca.CargaID   = pce.CargaID
                JOIN CLIENTES cl ON cl.ClienteID = ca.ClienteID
                WHERE e.FechaAlta <= :hasta
                  AND (e.FechaBaja IS NULL OR e.FechaBaja >= :desde)
                  AND pce.Cantidad > 0
                GROUP BY cl.ClienteID, cl.ClienteNombre, cl.siglas, cl.color,
                         cl.factura_por, cl.TipoMedicionTemperatura
            ");
            $stmt->execute([':desde' => $fechaDesde, ':hasta' => $fechaHasta]);
            $vigentes = $stmt->fetchAll();
        } catch(Exception $e) {
            $vigentes = [];
            $errores_debug[] = 'vigentes: '.$e->getMessage();
            error_log('[clientes_operaron_rango] vigentes: '.$e->getMessage());
        }

        // ── 4. Clientes con PreSalidas en el rango (cubre el caso en que
        //      hay movimientos pero aún no generaron Estiba) ────────────
        try {
            $stmt = $pdo->prepare("
                SELECT DISTINCT cl.ClienteID, cl.ClienteNombre, cl.siglas, cl.color,
                       cl.factura_por, cl.TipoMedicionTemperatura,
                       COUNT(DISTINCT ps.PreSalidaID) AS presalidas_rango
                FROM PreSalidas ps
                JOIN CLIENTES cl ON cl.ClienteID = ps.ClienteID
                WHERE DATE(ps.created_at) BETWEEN :desde AND :hasta
                GROUP BY cl.ClienteID, cl.ClienteNombre, cl.siglas, cl.color,
                         cl.factura_por, cl.TipoMedicionTemperatura
            ");
            $stmt->execute([':desde' => $fechaDesde, ':hasta' => $fechaHasta]);
            $presalidas_rango = $stmt->fetchAll();
        } catch(Exception $e) {
            $presalidas_rango = [];
            $errores_debug[] = 'presalidas: '.$e->getMessage();
            error_log('[clientes_operaron_rango] presalidas: '.$e->getMessage());
        }

        // ── Unificar ──────────────────────────────────────────────────
        $mapa = [];

        foreach ($creadas as $r) {
            $id = (int)$r['ClienteID'];
            $mapa[$id] = [
                'cliente_id'         => $id,
                'nombre'             => $r['ClienteNombre'],
                'siglas'             => $r['siglas']  ?: '',
                'color'              => $r['color']   ?: null,
                'factura_por'        => $r['factura_por'] ?: 'P',
                'tipo_medicion'      => $r['TipoMedicionTemperatura'] ?: 'S',
                'estibas_creadas'    => (int)$r['estibas_creadas'],
                'estibas_eliminadas' => 0,
                'estibas_vigentes'   => 0,
            ];
        }
        foreach ($eliminadas as $r) {
            $id = (int)$r['ClienteID'];
            if (!isset($mapa[$id])) $mapa[$id] = [
                'cliente_id'         => $id,
                'nombre'             => $r['ClienteNombre'],
                'siglas'             => $r['siglas']  ?: '',
                'color'              => $r['color']   ?: null,
                'factura_por'        => $r['factura_por'] ?: 'P',
                'tipo_medicion'      => $r['TipoMedicionTemperatura'] ?: 'S',
                'estibas_creadas'    => 0,
                'estibas_eliminadas' => 0,
                'estibas_vigentes'   => 0,
            ];
            $mapa[$id]['estibas_eliminadas'] = (int)$r['estibas_eliminadas'];
        }
        foreach ($vigentes as $r) {
            $id = (int)$r['ClienteID'];
            if (!isset($mapa[$id])) $mapa[$id] = [
                'cliente_id'         => $id,
                'nombre'             => $r['ClienteNombre'],
                'siglas'             => $r['siglas']  ?: '',
                'color'              => $r['color']   ?: null,
                'factura_por'        => $r['factura_por'] ?: 'P',
                'tipo_medicion'      => $r['TipoMedicionTemperatura'] ?: 'S',
                'estibas_creadas'    => 0,
                'estibas_eliminadas' => 0,
                'estibas_vigentes'   => 0,
            ];
            $mapa[$id]['estibas_vigentes'] = (int)$r['estibas_vigentes'];
        }
        foreach ($presalidas_rango as $r) {
            $id = (int)$r['ClienteID'];
            if (!isset($mapa[$id])) $mapa[$id] = [
                'cliente_id'         => $id,
                'nombre'             => $r['ClienteNombre'],
                'siglas'             => $r['siglas']  ?: '',
                'color'              => $r['color']   ?: null,
                'factura_por'        => $r['factura_por'] ?: 'P',
                'tipo_medicion'      => $r['TipoMedicionTemperatura'] ?: 'S',
                'estibas_creadas'    => 0,
                'estibas_eliminadas' => 0,
                'estibas_vigentes'   => 0,
            ];
            $mapa[$id]['presalidas_rango'] = (int)$r['presalidas_rango'];
        }

        $lista = array_values($mapa);
        usort($lista, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));

        $respuesta = [
            'clientes'       => $lista,
            'total_clientes' => count($lista),
            'fecha_desde'    => $fechaDesde,
            'fecha_hasta'    => $fechaHasta,
        ];
        if (!empty($errores_debug)) $respuesta['errores_debug'] = $errores_debug;
        resp(200, $respuesta);
        break;

    // ══════════════════════════════════════════════════════════
    //  MAPA DE PILAS — estructura + estibas desde estiba_posicion
    // ══════════════════════════════════════════════════════════
    case 'camara_mapa':
        $camara_id = (int)($_GET['camara_id'] ?? 0);
        if (!$camara_id) resp(400, ['error' => 'Falta camara_id']);
        $resultado = ['camara_id' => $camara_id];
        try {
            $stmt = $pdo->prepare("SELECT CamaraCalles, CamaraFilas, CamaraNiveles FROM Camaras WHERE CamaraID = ?");
            $stmt->execute([$camara_id]);
            $estructura = $stmt->fetch();
            $resultado['calles']  = (int)($estructura['CamaraCalles']  ?? 0);
            $resultado['filas']   = (int)($estructura['CamaraFilas']   ?? 0);
            $resultado['niveles'] = (int)($estructura['CamaraNiveles'] ?? 1);
        } catch(Exception $e) {
            $resultado['calles'] = $resultado['filas'] = $resultado['niveles'] = 0;
        }
        $pilas = []; $total_posiciones = 0; $total_ocupadas = 0;
        try {
            $stmtPos = $pdo->prepare("SELECT COUNT(*) FROM Posiciones WHERE CamaraID = ?");
            $stmtPos->execute([$camara_id]);
            $total_posiciones = (int)$stmtPos->fetchColumn();
        } catch(Exception $e) {}
        try {
            // Usa estiba_posicion con MAX(ubicacion) = posición actual de cada estiba
            $stmtEst = $pdo->prepare("
                SELECT ep.estiba_id, ep.nivel,
                       p.PosicionCalle AS calle, p.PosicionFila AS fila,
                       ca.CargaNombre  AS producto,
                       e.EstibaCantidad AS cantidad, e.EstibaPeso AS peso,
                       e.FechaAlta     AS fecha_ingreso,
                       ca.CargaVencimiento AS vencimiento,
                       cl.siglas       AS cliente_siglas,
                       cl.color        AS cliente_color,
                       cl.ClienteNombre AS cliente_nombre
                FROM estiba_posicion ep
                INNER JOIN (
                    SELECT estiba_id, MAX(ubicacion) AS max_ubic
                    FROM estiba_posicion GROUP BY estiba_id
                ) ult ON ult.estiba_id = ep.estiba_id AND ult.max_ubic = ep.ubicacion
                JOIN Posiciones p    ON p.PosicionID  = ep.posicion_id
                JOIN Estiba e        ON e.EstibaID     = ep.estiba_id
                LEFT JOIN Cargas ca  ON ca.CargaID     = e.CargaID
                LEFT JOIN CLIENTES cl ON cl.ClienteID  = ca.ClienteID
                WHERE p.CamaraID = ? AND e.FechaBaja IS NULL
                ORDER BY p.PosicionCalle, p.PosicionFila, ep.nivel
            ");
            $stmtEst->execute([$camara_id]);
            $estibas = $stmtEst->fetchAll();
            $posOcupadas = [];
            foreach ($estibas as $est) {
                $key = $est['calle'] . '_' . $est['fila'];
                if (!isset($pilas[$key])) $pilas[$key] = ['alturas' => 0, 'estibas' => []];
                $pilas[$key]['estibas'][] = [
                    'estiba_id'      => (int)$est['estiba_id'],
                    'nivel'          => (int)$est['nivel'],
                    'producto'       => $est['producto'],
                    'cantidad'       => $est['cantidad'] !== null ? (int)$est['cantidad'] : null,
                    'peso'           => $est['peso']     !== null ? (float)$est['peso']   : null,
                    'fecha_ingreso'  => $est['fecha_ingreso'],
                    'vencimiento'    => $est['vencimiento'],
                    'cliente_siglas' => $est['cliente_siglas'],
                    'cliente_color'  => $est['cliente_color'],
                    'cliente_nombre' => $est['cliente_nombre'],
                ];
                if ((int)$est['nivel'] > $pilas[$key]['alturas'])
                    $pilas[$key]['alturas'] = (int)$est['nivel'];
                $posOcupadas[$key] = true;
            }
            $total_ocupadas = count($posOcupadas);
        } catch(Exception $e) { $resultado['error_pilas'] = $e->getMessage(); }

        // Incluir estibas intervenidas con posición asignada
        try {
            $stmtInt = $pdo->prepare("
                SELECT eip.estiba_id, eip.nivel,
                       p.PosicionCalle AS calle, p.PosicionFila AS fila,
                       'INTERVENIDA' AS producto,
                       NULL AS cantidad, NULL AS peso,
                       ei.FechaAlta AS fecha_ingreso,
                       NULL AS vencimiento,
                       cl.siglas AS cliente_siglas,
                       cl.color AS cliente_color,
                       cl.ClienteNombre AS cliente_nombre
                FROM estiba_intervenida_posicion eip
                INNER JOIN (
                    SELECT estiba_id, MAX(ubicacion) AS max_ubic
                    FROM estiba_intervenida_posicion GROUP BY estiba_id
                ) ult ON ult.estiba_id = eip.estiba_id AND ult.max_ubic = eip.ubicacion
                JOIN Posiciones p ON p.PosicionID = eip.posicion_id
                JOIN Estiba_intervenidas ei ON ei.EstibaID = eip.estiba_id
                LEFT JOIN Cargas ca ON ca.CargaID = ei.CargaID
                LEFT JOIN CLIENTES cl ON cl.ClienteID = ca.ClienteID
                WHERE p.CamaraID = ? AND (ei.FechaBaja IS NULL AND ei.estado = 1)
                ORDER BY p.PosicionCalle, p.PosicionFila, eip.nivel
            ");
            $stmtInt->execute([$camara_id]);
            foreach ($stmtInt->fetchAll() as $est) {
                $key = $est['calle'] . '_' . $est['fila'];
                if (!isset($pilas[$key])) $pilas[$key] = ['alturas' => 0, 'estibas' => []];
                $pilas[$key]['estibas'][] = [
                    'estiba_id'      => (int)$est['estiba_id'],
                    'nivel'          => (int)$est['nivel'],
                    'producto'       => $est['producto'],
                    'cantidad'       => null,
                    'peso'           => null,
                    'fecha_ingreso'  => $est['fecha_ingreso'],
                    'vencimiento'    => null,
                    'cliente_siglas' => $est['cliente_siglas'],
                    'cliente_color'  => $est['cliente_color'],
                    'cliente_nombre' => $est['cliente_nombre'],
                    'intervenida'    => true,
                ];
                if ((int)$est['nivel'] > $pilas[$key]['alturas'])
                    $pilas[$key]['alturas'] = (int)$est['nivel'];
                $posOcupadas[$key] = true;
            }
            $total_ocupadas = count($posOcupadas);
        } catch(Exception $e) { /* tabla puede no existir aún */ }

        $resultado['pilas'] = $pilas;
        $resultado['total_posiciones'] = $total_posiciones;
        $resultado['total_ocupadas']   = $total_ocupadas;
        resp(200, $resultado);
        break;

    // ══════════════════════════════════════════════════════════
    //  BUSCAR ESTIBA — en todas las cámaras, usa estiba_posicion
    // ══════════════════════════════════════════════════════════
    case 'buscar_estiba':
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) resp(400, ['error' => 'Query muy corta']);
        $esNumero = is_numeric($q);
        $like     = '%' . $q . '%';
        try {
            $camNombre = "CASE
                WHEN LOWER(t.TiposCamaraNombre) LIKE '%tunel%' OR LOWER(t.TiposCamaraNombre) LIKE '%túnel%'
                    THEN CONCAT('Túnel ', cam.CamaraNumero)
                WHEN LOWER(t.TiposCamaraNombre) LIKE '%antecamara%' OR LOWER(t.TiposCamaraNombre) LIKE '%antecámara%'
                    THEN CONCAT('Antecámara ', cam.CamaraNumero)
                ELSE CONCAT('Cámara ', cam.CamaraNumero) END";
            $baseFrom = "FROM estiba_posicion ep
                INNER JOIN (SELECT estiba_id, MAX(ubicacion) AS max_ubic FROM estiba_posicion GROUP BY estiba_id) ult
                    ON ult.estiba_id = ep.estiba_id AND ult.max_ubic = ep.ubicacion
                JOIN Posiciones p    ON p.PosicionID   = ep.posicion_id
                JOIN Camaras cam     ON cam.CamaraID   = p.CamaraID
                JOIN TiposCamara t   ON t.TiposCamaraID = cam.TiposCamaraID
                JOIN Estiba e        ON e.EstibaID      = ep.estiba_id
                LEFT JOIN Cargas ca  ON ca.CargaID      = e.CargaID
                LEFT JOIN CLIENTES cl ON cl.ClienteID   = ca.ClienteID";
            if ($esNumero) {
                $stmt = $pdo->prepare("SELECT ep.estiba_id, p.PosicionCalle AS calle, p.PosicionFila AS fila,
                    ep.nivel, cam.CamaraID AS camara_id, ($camNombre) AS camara_nombre,
                    ca.CargaNombre AS producto, cl.siglas AS cliente_siglas, cl.color AS cliente_color
                    $baseFrom WHERE e.FechaBaja IS NULL AND cam.activo = 1 AND (ep.estiba_id = ? OR ca.CargaNombre LIKE ?)
                    ORDER BY ep.estiba_id DESC LIMIT 30");
                $stmt->execute([(int)$q, $like]);
            } else {
                $stmt = $pdo->prepare("SELECT ep.estiba_id, p.PosicionCalle AS calle, p.PosicionFila AS fila,
                    ep.nivel, cam.CamaraID AS camara_id, ($camNombre) AS camara_nombre,
                    ca.CargaNombre AS producto, cl.siglas AS cliente_siglas, cl.color AS cliente_color
                    $baseFrom WHERE e.FechaBaja IS NULL AND cam.activo = 1
                    AND (ca.CargaNombre LIKE ? OR cl.siglas LIKE ? OR cl.ClienteNombre LIKE ?)
                    ORDER BY ep.estiba_id DESC LIMIT 30");
                $stmt->execute([$like, $like, $like]);
            }
            $rows = $stmt->fetchAll();
            resp(200, array_map(fn($r) => [
                'estiba_id'     => (int)$r['estiba_id'],
                'camara_id'     => (int)$r['camara_id'],
                'camara_nombre' => $r['camara_nombre'],
                'calle'         => (int)$r['calle'],
                'fila'          => (int)$r['fila'],
                'nivel'         => (int)$r['nivel'],
                'producto'      => $r['producto'],
                'cliente_siglas'=> $r['cliente_siglas'],
                'cliente_color' => $r['cliente_color'],
            ], $rows));
        } catch(Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    // ══════════════════════════════════════════════════════════
    //  RESUMEN EQUIPOS MANTENIMIENTO — para tile dashboard
    // ══════════════════════════════════════════════════════════
    case 'mant_equipos_resumen':
        try {
            $rows = $pdo->query("SELECT estado, COUNT(*) AS total FROM mant_equipos WHERE activo=1 GROUP BY estado ORDER BY estado")->fetchAll();
            $porEstado = []; $totalEq = 0;
            foreach ($rows as $r) { $porEstado[$r['estado']] = (int)$r['total']; $totalEq += (int)$r['total']; }
            resp(200, ['total' => $totalEq, 'por_estado' => $porEstado]);
        } catch(Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    // ══════════════════════════════════════════════════════════
    //  OCUPACIÓN TOTAL — para tile Dashboard Logística Nacional
    // ══════════════════════════════════════════════════════════
    case 'ocupacion_total':
        try {
            $activas = (int)$pdo->query("
                SELECT COUNT(DISTINCT ep.estiba_id)
                FROM estiba_posicion ep
                INNER JOIN (SELECT estiba_id, MAX(ubicacion) AS max_ubic FROM estiba_posicion GROUP BY estiba_id) ult
                    ON ult.estiba_id = ep.estiba_id AND ult.max_ubic = ep.ubicacion
                JOIN Estiba e ON e.EstibaID = ep.estiba_id WHERE e.FechaBaja IS NULL
            ")->fetchColumn();
            $totalPos = (int)$pdo->query("
                SELECT COUNT(*) FROM Posiciones p JOIN Camaras c ON c.CamaraID = p.CamaraID WHERE c.activo = 1
            ")->fetchColumn();
            $pct = $totalPos > 0 ? round($activas / $totalPos * 100, 1) : 0;
            resp(200, ['estibas_activas' => $activas, 'total_posiciones' => $totalPos, 'pct_ocupacion' => $pct]);
        } catch(Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    // ── GET: cámaras tipo 1,2,7 con estado de temperatura ──────
    case 'parcamara_camaras':
        requireSession();
        try {
            // ── Cámaras con temperatura ──────────────────────────────
            $stmt = $pdo->query("
                SELECT
                    c.CamaraID,
                    c.CamaraNumero,
                    c.TiposCamaraID,
                    tmp.estado      AS temp_estado,
                    tmp.temperatura AS temp_actual,
                    tmp.setpoint    AS temp_setpoint,
                    tmp.fecha       AS temp_fecha,
                    tmp.hora        AS temp_hora
                FROM Camaras c
                LEFT JOIN (
                    SELECT m.camara_id, t2.estado, t2.temperatura, t2.setpoint, t2.fecha, t2.hora
                    FROM temperatura_camara_map m
                    INNER JOIN temperaturas t2 ON t2.camara = m.nombre_csv
                    INNER JOIN (
                        SELECT camara, MAX(CONCAT(fecha,' ',hora)) AS ultima
                        FROM temperaturas
                        WHERE temperatura IS NOT NULL
                        GROUP BY camara
                    ) u ON u.camara = t2.camara AND CONCAT(t2.fecha,' ',t2.hora) = u.ultima
                    WHERE m.camara_id IS NOT NULL
                ) tmp ON tmp.camara_id = c.CamaraID
                WHERE c.activo = 1
                  AND c.TiposCamaraID IN (1, 2, 7)
                ORDER BY c.TiposCamaraID ASC, c.CamaraNumero ASC
            ");
            $camaras = $stmt->fetchAll();

            // ── Estado de puertas (último registro por cámara) ───────
            $puertas = [];
            try {
                $rowsPuertas = $pdo->query("
                    SELECT r.camara_id, r.estado
                    FROM camara_puerta_registro r
                    INNER JOIN (
                        SELECT camara_id, MAX(CONCAT(fecha,' ',hora)) AS ultima
                        FROM camara_puerta_registro
                        GROUP BY camara_id
                    ) u ON u.camara_id = r.camara_id
                       AND CONCAT(r.fecha,' ',r.hora) = u.ultima
                ")->fetchAll();
                foreach ($rowsPuertas as $p) {
                    $puertas[(int)$p['camara_id']] = strtolower(trim($p['estado']));
                }
            } catch(Exception $e) {}

            // ── Solicitudes activas por cámara (Pendiente o Recibida) ────
            $solicEstados = [];
            try {
                $rowsSol = $pdo->query("
                    SELECT camara_id, estado_solicitud
                    FROM sdm_solicitud_parcamara
                    WHERE estado_solicitud IN ('Pendiente','Recibida') AND activo = 1
                    ORDER BY
                        CASE estado_solicitud
                            WHEN 'Pendiente' THEN 1
                            WHEN 'Recibida'  THEN 2
                        END ASC
                ")->fetchAll();
                // Si hay varias por cámara, queda la de mayor prioridad (Pendiente gana sobre Recibida)
                foreach ($rowsSol as $r) {
                    $cid = (int)$r['camara_id'];
                    if (!isset($solicEstados[$cid])) {
                        $solicEstados[$cid] = $r['estado_solicitud'];
                    }
                }
            } catch(Exception $e) {}

            // ── Color + campos adicionales ───────────────────────────
            foreach ($camaras as &$cam) {
                $cid    = (int)$cam['CamaraID'];
                $estado = strtolower(trim($cam['temp_estado'] ?? ''));

                if ($estado === 'sin inyección' || $estado === 'sin inyeccion' || $estado === 'no inyectando') {
                    $cam['btn_color'] = 'gray';
                } elseif (strpos($estado, 'deshelo') !== false || strpos($estado, 'desheló') !== false || strpos($estado, 'descongelado') !== false) {
                    $cam['btn_color'] = 'yellow';
                } else {
                    $cam['btn_color'] = 'green';
                }

                $cam['puerta_estado']          = $puertas[$cid] ?? null;
                $cam['solicitudes_pendientes'] = isset($solicEstados[$cid]) ? 1 : 0;
                $cam['solicitud_estado']       = $solicEstados[$cid] ?? null; // 'Pendiente' | 'Recibida' | null
            }
            unset($cam);

            resp(200, $camaras);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── POST: usuario solicita parada de cámara ─────────────────
    case 'parcamara_solicitar':
        requireSession();
        $camaraId     = (int)($body['camara_id']     ?? 0);
        $camaraNumero = (int)($body['camara_numero'] ?? 0);
        $estadoTemp   = trim($body['estado_temp']    ?? '');

        if (!$camaraId || !$camaraNumero) {
            resp(400, ['error' => 'Falta camara_id o camara_numero']);
        }

        $stmtChk = $pdo->prepare("SELECT TiposCamaraID FROM Camaras WHERE CamaraID = ? AND activo = 1");
        $stmtChk->execute([$camaraId]);
        $tipoId = (int)$stmtChk->fetchColumn();
        if (!in_array($tipoId, [1, 2, 7])) {
            resp(403, ['error' => 'Cámara no habilitada para solicitud de parada']);
        }

        $usuarioId     = $_SESSION['cina_usuario_id'];
        $usuarioNombre = $_SESSION['cina_nombre'] ?? $_SESSION['cina_usuario'] ?? 'Usuario';

        try {
            $tempValor    = isset($body['temp_valor'])    ? (float)$body['temp_valor']    : null;
            $puertaEstado = trim($body['puerta_estado'] ?? '');

            $pdo->prepare("
                INSERT INTO sdm_solicitud_parcamara
                    (camara_id, camara_numero, estado_temp, temp_valor_solicitud, puerta_estado_solicitud,
                     usuario_id, usuario_nombre, fecha_solicitud, estado_solicitud, activo)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'Pendiente', 1)
            ")->execute([$camaraId, $camaraNumero, $estadoTemp ?: 'Sin información',
                         $tempValor ?: null, $puertaEstado ?: null,
                         $usuarioId, $usuarioNombre]);

            resp(200, ['ok' => true, 'solicitud_id' => $pdo->lastInsertId()]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── GET: lista de solicitudes para el panel de PC ───────────
    case 'parcamara_lista':
        requireSession();
        $limite = min((int)($_GET['limite'] ?? 50), 200);
        $soloP  = ($_GET['solo_pendientes'] ?? '0') === '1';
        $where  = $soloP ? "WHERE s.estado_solicitud = 'Pendiente' AND s.activo = 1" : "WHERE s.activo = 1";

        try {
            $rows = $pdo->query("
                SELECT
                    s.id,
                    s.camara_numero,
                    s.estado_temp,
                    s.temp_valor_solicitud,
                    s.puerta_estado_solicitud,
                    s.usuario_nombre          AS solicitante,
                    s.fecha_solicitud,
                    s.estado_solicitud,
                    s.usuario_responde_nombre AS respondio,
                    s.fecha_respuesta,
                    s.observaciones,
                    s.va_a_parar,
                    tmp.estado  AS temp_estado_actual,
                    tmp.temperatura AS temp_actual
                FROM sdm_solicitud_parcamara s
                LEFT JOIN Camaras c ON c.CamaraID = s.camara_id
                LEFT JOIN (
                    SELECT m.camara_id, t2.estado, t2.temperatura
                    FROM temperatura_camara_map m
                    INNER JOIN temperaturas t2 ON t2.camara = m.nombre_csv
                    INNER JOIN (
                        SELECT camara, MAX(CONCAT(fecha,' ',hora)) AS ultima
                        FROM temperaturas
                        WHERE temperatura IS NOT NULL
                        GROUP BY camara
                    ) u ON u.camara = t2.camara AND CONCAT(t2.fecha,' ',t2.hora) = u.ultima
                    WHERE m.camara_id IS NOT NULL
                ) tmp ON tmp.camara_id = s.camara_id
                $where
                ORDER BY s.fecha_solicitud DESC
                LIMIT $limite
            ")->fetchAll();

            $stmtRol = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
            $stmtRol->execute([$_SESSION['cina_usuario_id']]);
            $rolId = (int)$stmtRol->fetchColumn();

            resp(200, [
                'solicitudes'     => $rows,
                'puede_responder' => in_array($rolId, [1, 9]),
                'rol_id'          => $rolId,
            ]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── POST: sala de máquinas marca solicitud como "Recibida" ───
    // Solo RolID 1 o 9. Cambia Pendiente → Recibida (estado naranja).
    case 'parcamara_recibir':
        requireSession();

        $stmtRolChk = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
        $stmtRolChk->execute([$_SESSION['cina_usuario_id']]);
        $rolUsuario = (int)$stmtRolChk->fetchColumn();
        if (!in_array($rolUsuario, [1, 9])) {
            resp(403, ['error' => 'Sin permiso']);
        }

        $solicitudId = (int)($body['solicitud_id'] ?? 0);
        if (!$solicitudId) resp(400, ['error' => 'Falta solicitud_id']);

        $stmtChk = $pdo->prepare("SELECT estado_solicitud FROM sdm_solicitud_parcamara WHERE id = ? AND activo = 1 LIMIT 1");
        $stmtChk->execute([$solicitudId]);
        $estadoActual = $stmtChk->fetchColumn();
        if (!$estadoActual) resp(404, ['error' => 'Solicitud no encontrada']);
        if ($estadoActual !== 'Pendiente') resp(409, ['error' => 'La solicitud no está Pendiente']);

        $respondeNombre = $_SESSION['cina_nombre'] ?? $_SESSION['cina_usuario'] ?? 'Operador';
        $respondeId     = $_SESSION['cina_usuario_id'];
        $vaAParar       = isset($body['va_a_parar']) ? (int)$body['va_a_parar'] : null;

        try {
            $pdo->prepare("
                UPDATE sdm_solicitud_parcamara SET
                    estado_solicitud        = 'Recibida',
                    usuario_responde_id     = ?,
                    usuario_responde_nombre = ?,
                    va_a_parar              = ?,
                    fecha_respuesta         = NOW()
                WHERE id = ?
            ")->execute([$respondeId, $respondeNombre, $vaAParar, $solicitudId]);
            resp(200, ['ok' => true, 'solicitud_id' => $solicitudId, 'estado' => 'Recibida', 'va_a_parar' => $vaAParar]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── POST: RolID=9 informa resultado de la parada ────────────
    case 'parcamara_responder':
        requireSession();

        $stmtRolChk = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
        $stmtRolChk->execute([$_SESSION['cina_usuario_id']]);
        $rolUsuario = (int)$stmtRolChk->fetchColumn();
        if (!in_array($rolUsuario, [1, 9])) {
            resp(403, ['error' => 'Sin permiso. Solo usuarios con RolID=1 o 9 pueden informar resultado.']);
        }

        $solicitudId     = (int)($body['solicitud_id']     ?? 0);
        $estadoRespuesta = trim($body['estado_solicitud']  ?? '');
        $observaciones   = trim($body['observaciones']     ?? '');

        $estadosValidos = ['Parada Informada', 'No Parada Informada'];
        if (!$solicitudId || !in_array($estadoRespuesta, $estadosValidos)) {
            resp(400, ['error' => 'Datos inválidos. estado_solicitud debe ser: ' . implode(' | ', $estadosValidos)]);
        }

        $stmtSol = $pdo->prepare("
            SELECT s.*, u.UsuarioNombre AS usuario_login
            FROM sdm_solicitud_parcamara s
            LEFT JOIN Usuarios u ON u.UsuarioID = s.usuario_id
            WHERE s.id = ? AND s.activo = 1 LIMIT 1
        ");
        $stmtSol->execute([$solicitudId]);
        $solicitud = $stmtSol->fetch();
        if (!$solicitud) {
            resp(404, ['error' => 'Solicitud no encontrada']);
        }
        if ($solicitud['estado_solicitud'] !== 'Pendiente' && $solicitud['estado_solicitud'] !== 'Recibida') {
            resp(409, ['error' => 'La solicitud ya fue respondida']);
        }

        $respondeNombre = $_SESSION['cina_nombre'] ?? $_SESSION['cina_usuario'] ?? 'Operador';
        $respondeId     = $_SESSION['cina_usuario_id'];

        try {
            $pdo->prepare("
                UPDATE sdm_solicitud_parcamara SET
                    estado_solicitud        = ?,
                    usuario_responde_id     = ?,
                    usuario_responde_nombre = ?,
                    fecha_respuesta         = NOW(),
                    observaciones           = ?
                WHERE id = ?
            ")->execute([$estadoRespuesta, $respondeId, $respondeNombre, $observaciones ?: null, $solicitudId]);

            resp(200, [
                'ok'             => true,
                'solicitud_id'   => $solicitudId,
                'estado'         => $estadoRespuesta,
                'camara_numero'  => $solicitud['camara_numero'],
                'solicitante'    => $solicitud['usuario_nombre'],
                'solicitante_id' => $solicitud['usuario_id'],
            ]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;


    // ══════════════════════════════════════════════════════════════════════
    //  MÓDULO GASOIL
    // ══════════════════════════════════════════════════════════════════════

    // GET: Datos iniciales del formulario (departamentos, usuarios, equipos)
    case 'gasoil_form_data':
        requireSession();
        try {
            // Departamentos activos (empresa_id=1 = CINA principal, más los activos generales)
            $deptos = $pdo->query("
                SELECT departamento_id AS id, nombre
                FROM departamento
                WHERE activo = 1
                ORDER BY nombre ASC
            ")->fetchAll();

            // Usuarios activos internos (excluye clientes externos)
            $usuarios = $pdo->query("
                SELECT u.UsuarioID AS id,
                       TRIM(COALESCE(
                           CONCAT(p.nombre, ' ', p.apellido),
                           u.UsuarioNombre
                       )) AS nombre_completo,
                       u.UsuarioNombre AS usuario
                FROM Usuarios u
                LEFT JOIN persona p ON p.persona_id = u.persona_id
                WHERE u.activo = 1
                  AND u.es_cliente = 0
                ORDER BY nombre_completo ASC
            ")->fetchAll();

            // Equipos activos (filtra por gasoil si la columna existe)
            try {
                $equipos = $pdo->query("
                    SELECT id, denominacion, tipo, interno
                    FROM equipos
                    WHERE activo = 1
                      AND (usa_gasoil = 1 OR LOWER(COALESCE(combustible,'')) LIKE '%gasoil%' OR LOWER(COALESCE(combustible,'')) LIKE '%diesel%')
                    ORDER BY tipo, denominacion ASC
                ")->fetchAll();
            } catch (Exception $eq) {
                // Si la columna no existe aún, devolver todos los equipos activos
                $equipos = $pdo->query("
                    SELECT id, denominacion, tipo, interno
                    FROM equipos
                    WHERE activo = 1
                    ORDER BY tipo, denominacion ASC
                ")->fetchAll();
            }

            resp(200, [
                'departamentos' => $deptos,
                'usuarios'      => $usuarios,
                'equipos'       => $equipos,
            ]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // GET: Stock actual + movimientos
    case 'gasoil_stock':
        requireSession();
        $limite = min((int)($_GET['limite'] ?? 100), 500);
        try {
            // Stock = suma de ingresos - suma de egresos
            $stockRow = $pdo->query("
                SELECT
                    COALESCE(SUM(CASE WHEN tipo_movimiento = 'Ingreso' THEN cantidad ELSE 0 END), 0) AS total_ingresos,
                    COALESCE(SUM(CASE WHEN tipo_movimiento = 'Egreso'  THEN cantidad ELSE 0 END), 0) AS total_egresos
                FROM gasoil_movimientos
                WHERE activo = 1
            ")->fetch();

            $stock = (float)$stockRow['total_ingresos'] - (float)$stockRow['total_egresos'];

            // Últimos movimientos
            $movs = $pdo->query("
                SELECT
                    m.id,
                    m.tipo_movimiento,
                    m.cantidad,
                    m.unidad,
                    m.fecha_hora,
                    m.destino_tipo,
                    m.destino_nombre,
                    m.equipo_id,
                    e.denominacion AS equipo_nombre,
                    m.solicitante_nombre,
                    m.autorizo_nombre,
                    m.operador_nombre,
                    m.observaciones,
                    m.remito_oc
                FROM gasoil_movimientos m
                LEFT JOIN equipos e ON e.id = m.equipo_id
                WHERE m.activo = 1
                ORDER BY m.fecha_hora DESC
                LIMIT $limite
            ")->fetchAll();

            resp(200, [
                'stock'          => $stock,
                'total_ingresos' => (float)$stockRow['total_ingresos'],
                'total_egresos'  => (float)$stockRow['total_egresos'],
                'movimientos'    => $movs,
                'alerta_stock'   => $stock < 1000,
            ]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // POST: Registrar movimiento de gasoil
    case 'gasoil_registrar':
        requireSession();
        $tipoMov       = trim($body['tipo_movimiento']   ?? '');
        $cantidad      = (float)($body['cantidad']        ?? 0);
        $unidad        = trim($body['unidad']             ?? 'litros');
        $destinoTipo   = trim($body['destino_tipo']       ?? '');   // 'interno'|'externo'
        $destinoNombre = trim($body['destino_nombre']     ?? '');
        $equipoId      = (int)($body['equipo_id']         ?? 0) ?: null;
        $solicitanteNombre = trim($body['solicitante_nombre'] ?? '');
        $autorizoNombre    = trim($body['autorizo_nombre']    ?? '');
        $observaciones     = trim($body['observaciones']      ?? '');
        $remitoOc          = trim($body['remito_oc']          ?? '');

        if (!in_array($tipoMov, ['Ingreso', 'Egreso'])) resp(400, ['error' => 'tipo_movimiento inválido']);
        if ($cantidad <= 0) resp(400, ['error' => 'Cantidad debe ser mayor a 0']);

        $operadorNombre = $_SESSION['cina_nombre'] ?? $_SESSION['cina_usuario'] ?? 'Sistema';
        $operadorId     = $_SESSION['cina_usuario_id'];

        try {
            $pdo->prepare("
                INSERT INTO gasoil_movimientos
                    (tipo_movimiento, cantidad, unidad, fecha_hora,
                     destino_tipo, destino_nombre, equipo_id,
                     solicitante_nombre, autorizo_nombre,
                     operador_id, operador_nombre, observaciones, remito_oc, activo)
                VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ")->execute([
                $tipoMov, $cantidad, $unidad,
                $destinoTipo ?: null, $destinoNombre ?: null,
                $equipoId,
                $solicitanteNombre ?: null, $autorizoNombre ?: null,
                $operadorId, $operadorNombre,
                $observaciones ?: null, $remitoOc ?: null,
            ]);

            $nuevoId = $pdo->lastInsertId();

            // Recalcular stock para devolver actualizado
            $stockRow = $pdo->query("
                SELECT
                    COALESCE(SUM(CASE WHEN tipo_movimiento = 'Ingreso' THEN cantidad ELSE 0 END), 0) AS ti,
                    COALESCE(SUM(CASE WHEN tipo_movimiento = 'Egreso'  THEN cantidad ELSE 0 END), 0) AS te
                FROM gasoil_movimientos WHERE activo = 1
            ")->fetch();
            $stockActual = (float)$stockRow['ti'] - (float)$stockRow['te'];

            resp(200, [
                'ok'           => true,
                'id'           => $nuevoId,
                'stock_actual' => $stockActual,
                'alerta_stock' => $stockActual < 1000,
            ]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;


    // ══════════════════════════════════════════════════════════════════════
    //  DASHBOARD EQUIPAMIENTO — Editar y crear equipos
    // ══════════════════════════════════════════════════════════════════════

    case 'equipos_editar':
        requireSession();
        $id = (int)($body['id'] ?? 0);
        if (!$id) resp(400, ['error' => 'Falta id']);

        $campos = ['denominacion','tipo','marca','modelo','estado_actual',
                   'planta','interno','anio','horometro_actual','combustible',
                   'usa_gasoil','icono','activo'];
        $set = []; $params = [];
        foreach ($campos as $c) {
            if (array_key_exists($c, $body)) {
                $v = $body[$c];
                if ($v === '' || $v === null) { $set[] = "`$c` = NULL"; }
                else { $set[] = "`$c` = ?"; $params[] = $v; }
            }
        }
        if (empty($set)) resp(400, ['error' => 'Sin campos para actualizar']);
        $params[] = $id;
        try {
            $pdo->prepare("UPDATE equipos SET " . implode(', ', $set) . " WHERE id = ?")->execute($params);
            resp(200, ['ok' => true, 'id' => $id]);
        } catch(Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    case 'equipos_nuevo':
        requireSession();
        $denom  = trim($body['denominacion'] ?? '');
        $tipo   = trim($body['tipo']         ?? '');
        if (!$denom) resp(400, ['error' => 'Falta denominación']);
        try {
            $pdo->prepare("
                INSERT INTO equipos
                    (denominacion, tipo, marca, modelo, estado_actual, planta,
                     interno, anio, horometro_actual, combustible, usa_gasoil, icono, activo)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1)
            ")->execute([
                $denom,
                $tipo ?: null,
                trim($body['marca']  ?? '') ?: null,
                trim($body['modelo'] ?? '') ?: null,
                trim($body['estado_actual']    ?? 'Cargando'),
                trim($body['planta']           ?? '') ?: null,
                isset($body['interno']) ? (int)$body['interno'] : null,
                isset($body['anio'])    ? (int)$body['anio']    : null,
                isset($body['horometro_actual']) ? (float)$body['horometro_actual'] : 0,
                trim($body['combustible'] ?? '') ?: null,
                isset($body['usa_gasoil']) ? (int)$body['usa_gasoil'] : 0,
                trim($body['icono']  ?? '') ?: null,
            ]);
            resp(200, ['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
        } catch(Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;


    // ══════════════════════════════════════════════════════════════════════
    //  PR-100 DESCONGELADO — Historial de periodos en deshelo por cámara
    // ══════════════════════════════════════════════════════════════════════
    //  GET ?action=pr100_deshelo_historial
    //      &camara_id=X          (opcional, si no se envía devuelve todas)
    //      &desde=YYYY-MM-DD     (opcional, default: últimos 90 días)
    //      &hasta=YYYY-MM-DD     (opcional, default: hoy)
    //
    //  Devuelve los PERÍODOS continuos donde estado LIKE '%deshel%' o
    //  '%descongelad%', agrupando lecturas consecutivas en bloques.
    // ══════════════════════════════════════════════════════════════════════
    case 'pr100_deshelo_historial':
        requireSession();

        $camaraId = isset($_GET['camara_id']) && $_GET['camara_id'] !== ''
            ? (int)$_GET['camara_id'] : null;

        $hasta = !empty($_GET['hasta']) ? $_GET['hasta'] : date('Y-m-d');
        $desde = !empty($_GET['desde']) ? $_GET['desde']
               : date('Y-m-d', strtotime('-90 days'));

        try {
            // Traer todas las lecturas con estado deshelo en el rango
            $where = "WHERE t.fecha BETWEEN ? AND ?
                        AND (
                            t.estado LIKE '%eshel%'
                            OR t.estado LIKE '%escongelad%'
                        )
                        AND m.camara_id IS NOT NULL";
            $params = [$desde, $hasta];

            if ($camaraId) {
                $where .= " AND m.camara_id = ?";
                $params[] = $camaraId;
            }

            $stmt = $pdo->prepare("
                SELECT
                    m.camara_id,
                    c.CamaraNumero,
                    CASE
                      WHEN LOWER(tc.TiposCamaraNombre) LIKE '%tunel%'
                        OR LOWER(tc.TiposCamaraNombre) LIKE '%túnel%'
                           THEN CONCAT('Túnel ', c.CamaraNumero)
                      WHEN LOWER(tc.TiposCamaraNombre) LIKE '%antecamara%'
                        OR LOWER(tc.TiposCamaraNombre) LIKE '%antecámara%'
                           THEN CONCAT('Antecámara ', c.CamaraNumero)
                      ELSE CONCAT('Cámara ', c.CamaraNumero)
                    END AS camara_nombre,
                    t.fecha,
                    t.hora,
                    t.estado,
                    t.temperatura
                FROM temperaturas t
                JOIN temperatura_camara_map m ON m.nombre_csv = t.camara
                JOIN Camaras c     ON c.CamaraID      = m.camara_id
                JOIN TiposCamara tc ON tc.TiposCamaraID = c.TiposCamaraID
                $where
                ORDER BY m.camara_id ASC, t.fecha ASC, t.hora ASC
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            // Agrupar en períodos continuos (gap > 2 horas = nuevo período)
            $periodos = [];
            $current  = null;
            $GAP_SEG  = 7200; // 2 horas = nuevo período

            foreach ($rows as $r) {
                $ts = strtotime($r['fecha'] . ' ' . $r['hora']);
                if (
                    $current === null
                    || $current['camara_id'] !== $r['camara_id']
                    || ($ts - $current['_ultimo_ts']) > $GAP_SEG
                ) {
                    // Cerrar período anterior
                    if ($current !== null) {
                        unset($current['_ultimo_ts']);
                        $periodos[] = $current;
                    }
                    // Nuevo período
                    $current = [
                        'camara_id'    => $r['camara_id'],
                        'camara_nombre'=> $r['camara_nombre'],
                        'camara_numero'=> $r['CamaraNumero'],
                        'fecha_inicio' => $r['fecha'],
                        'hora_inicio'  => $r['hora'],
                        'fecha_fin'    => $r['fecha'],
                        'hora_fin'     => $r['hora'],
                        'estado'       => $r['estado'],
                        'temp_min'     => $r['temperatura'] !== null ? (float)$r['temperatura'] : null,
                        'temp_max'     => $r['temperatura'] !== null ? (float)$r['temperatura'] : null,
                        'lecturas'     => 1,
                        '_ultimo_ts'   => $ts,
                    ];
                } else {
                    // Extender período actual
                    $current['fecha_fin']  = $r['fecha'];
                    $current['hora_fin']   = $r['hora'];
                    $current['_ultimo_ts'] = $ts;
                    $current['lecturas']++;
                    if ($r['temperatura'] !== null) {
                        $temp = (float)$r['temperatura'];
                        if ($current['temp_min'] === null || $temp < $current['temp_min']) $current['temp_min'] = $temp;
                        if ($current['temp_max'] === null || $temp > $current['temp_max']) $current['temp_max'] = $temp;
                    }
                }
            }
            if ($current !== null) {
                unset($current['_ultimo_ts']);
                $periodos[] = $current;
            }

            // Ordenar por fecha descendente
            usort($periodos, fn($a, $b) =>
                strcmp($b['fecha_inicio'].$b['hora_inicio'], $a['fecha_inicio'].$a['hora_inicio'])
            );

            // Calcular duración en minutos de cada período
            foreach ($periodos as &$p) {
                $tsI = strtotime($p['fecha_inicio'] . ' ' . $p['hora_inicio']);
                $tsF = strtotime($p['fecha_fin']    . ' ' . $p['hora_fin']);
                $p['duracion_min'] = (int)round(($tsF - $tsI) / 60);
            }
            unset($p);

            resp(200, [
                'periodos' => $periodos,
                'total'    => count($periodos),
                'desde'    => $desde,
                'hasta'    => $hasta,
            ]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;


    // ══════════════════════════════════════════════════════════════════════
    //  PR-100 — Detalle de deshelo + presiones por cámara y período
    //  GET ?action=pr100_cam_detalle&camara_id=X&desde=YYYY-MM-DD&hasta=YYYY-MM-DD
    // ══════════════════════════════════════════════════════════════════════
    case 'pr100_cam_detalle':
        requireSession();
        $camaraId = (int)($_GET['camara_id'] ?? 0);
        if (!$camaraId) resp(400, ['error' => 'Falta camara_id']);

        $hasta = !empty($_GET['hasta']) ? $_GET['hasta'] : date('Y-m-d');
        $desde = !empty($_GET['desde']) ? $_GET['desde']
               : date('Y-m-d', strtotime('-30 days'));

        try {
            // 1. Nombre CSV de la cámara
            $stmtMap = $pdo->prepare("
                SELECT nombre_csv FROM temperatura_camara_map
                WHERE camara_id = ? AND activo = 1 LIMIT 1
            ");
            $stmtMap->execute([$camaraId]);
            $nombreCsv = $stmtMap->fetchColumn();
            if (!$nombreCsv) resp(200, ['estados' => [], 'presiones' => []]);

            // 2. Lecturas de estado de la cámara (temperatura + estado)
            $stmtE = $pdo->prepare("
                SELECT fecha, hora, temperatura, estado
                FROM temperaturas
                WHERE camara = ?
                  AND fecha BETWEEN ? AND ?
                  AND estado IS NOT NULL
                ORDER BY fecha ASC, hora ASC
            ");
            $stmtE->execute([$nombreCsv, $desde, $hasta]);
            $estados = $stmtE->fetchAll();

            // 3. Presiones del sistema (camara='PRESIONES') en el mismo rango
            $stmtP = $pdo->prepare("
                SELECT fecha, hora, pres_succ_30, pres_succ_40, pres_desc
                FROM temperaturas
                WHERE camara = 'PRESIONES'
                  AND fecha BETWEEN ? AND ?
                  AND (pres_succ_30 IS NOT NULL OR pres_succ_40 IS NOT NULL OR pres_desc IS NOT NULL)
                ORDER BY fecha ASC, hora ASC
            ");
            $stmtP->execute([$desde, $hasta]);
            $presiones = $stmtP->fetchAll();

            resp(200, [
                'camara_id'  => $camaraId,
                'nombre_csv' => $nombreCsv,
                'desde'      => $desde,
                'hasta'      => $hasta,
                'estados'    => $estados,
                'presiones'  => $presiones,
            ]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;


// ── GET: lista de puntos de control activos ──────────────────
case 'pr24_puntos':
    requireSession();
    try {
        $stmt = $pdo->query("
            SELECT id, nombre
            FROM sdm_punto_control
            WHERE activo = 1
            ORDER BY nombre ASC
        ");
        resp(200, $stmt->fetchAll());
    } catch (Exception $e) {
        resp(500, ['error' => $e->getMessage()]);
    }
    break;

// ── GET: lista de registros ──────────────────────────────────
case 'pr24_lista':
    requireSession();
    $fecha  = $_GET['fecha']  ?? '';
    $buscar = $_GET['buscar'] ?? '';

    $where  = "WHERE d.activo = 1";
    $params = [];

    if ($fecha) {
        $where .= " AND d.fecha = ?";
        $params[] = $fecha;
    }

    if ($buscar) {
        $like = "%{$buscar}%";
        $where .= " AND (
            CONCAT(p.apellido, ', ', p.nombre) LIKE ?
            OR d.descripcion  LIKE ?
            OR d.observaciones LIKE ?
        )";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                d.sdm_egc_diario_id,
                d.fecha,
                d.hora,
                d.maquinista_id,
                CONCAT(TRIM(p.apellido), ', ', TRIM(p.nombre)) AS maquinista_nombre,
                d.punto_control,
                pc.nombre  AS punto_nombre,
                d.descripcion,
                d.observaciones,
                d.mant_correctivo,
                d.mant_preventivo,
                d.mant_predictivo
            FROM sdm_egc_diario d
            -- Para registros hasta 23/03/2026 el maquinista_id apunta a sdm_maquinista;
            -- desde 24/03/2026 apunta directamente a persona.persona_id.
            LEFT JOIN sdm_maquinista sm
                   ON d.fecha <= '2026-03-23'
                  AND sm.maquinista_id = d.maquinista_id
            LEFT JOIN persona p
                   ON p.persona_id = COALESCE(sm.persona_id, d.maquinista_id)
            LEFT JOIN sdm_punto_control pc ON pc.id = d.punto_control
            {$where}
            ORDER BY d.fecha DESC, d.hora DESC
            LIMIT 200
        ");
        $stmt->execute($params);
        resp(200, $stmt->fetchAll());
    } catch (Exception $e) {
        resp(500, ['error' => $e->getMessage()]);
    }
    break;

// ── GET: registro individual ─────────────────────────────────
case 'pr24_get':
    requireSession();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) resp(400, ['error' => 'Falta id']);

    try {
        $stmt = $pdo->prepare("
            SELECT
                d.*,
                CONCAT(TRIM(p.apellido), ', ', TRIM(p.nombre)) AS maquinista_nombre,
                pc.nombre AS punto_nombre
            FROM sdm_egc_diario d
            LEFT JOIN sdm_maquinista sm
                   ON d.fecha <= '2026-03-23'
                  AND sm.maquinista_id = d.maquinista_id
            LEFT JOIN persona p
                   ON p.persona_id = COALESCE(sm.persona_id, d.maquinista_id)
            LEFT JOIN sdm_punto_control pc ON pc.id = d.punto_control
            WHERE d.sdm_egc_diario_id = ? AND d.activo = 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) resp(404, ['error' => 'Registro no encontrado']);
        resp(200, $row);
    } catch (Exception $e) {
        resp(500, ['error' => $e->getMessage()]);
    }
    break;

// ── POST: crear o actualizar registro ────────────────────────
case 'pr24_guardar':
    requireSession();
    $uid = (int)$_SESSION['cina_usuario_id'];

    $id            = isset($body['id']) ? (int)$body['id'] : 0;
    $maquinista_id = (int)($body['maquinista_id'] ?? 0);
    $punto_control = isset($body['punto_control']) && $body['punto_control'] !== ''
                        ? (int)$body['punto_control'] : null;
    $fecha         = $body['fecha']          ?? '';
    $hora          = $body['hora']           ?? '';
    $descripcion   = trim($body['descripcion']   ?? '');
    $observaciones = trim($body['observaciones'] ?? '');
    $correctivo    = (int)($body['mant_correctivo']  ?? 0);
    $preventivo    = (int)($body['mant_preventivo']  ?? 0);
    $predictivo    = (int)($body['mant_predictivo']  ?? 0);

    if (!$maquinista_id) resp(400, ['error' => 'Maquinista requerido']);
    if (!$fecha)         resp(400, ['error' => 'Fecha requerida']);
    if (!$hora)          resp(400, ['error' => 'Hora requerida']);
    if (!$descripcion)   resp(400, ['error' => 'Descripción requerida']);

    try {
        if ($id) {
            // UPDATE
            $pdo->prepare("
                UPDATE sdm_egc_diario SET
                    maquinista_id   = ?,
                    punto_control   = ?,
                    fecha           = ?,
                    hora            = ?,
                    descripcion     = ?,
                    observaciones   = ?,
                    mant_correctivo = ?,
                    mant_preventivo = ?,
                    mant_predictivo = ?,
                    updated_by      = ?,
                    updated_at      = NOW()
                WHERE sdm_egc_diario_id = ? AND activo = 1
            ")->execute([
                $maquinista_id, $punto_control, $fecha, $hora,
                $descripcion, $observaciones,
                $correctivo, $preventivo, $predictivo,
                $uid, $id
            ]);
            resp(200, ['ok' => true, 'id' => $id, 'action' => 'updated']);
        } else {
            // INSERT
            $pdo->prepare("
                INSERT INTO sdm_egc_diario
                    (fecha, hora, maquinista_id, descripcion, observaciones,
                     mant_correctivo, mant_preventivo, mant_predictivo,
                     punto_control, activo, created_by, created_at, updated_by, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), ?, NOW())
            ")->execute([
                $fecha, $hora, $maquinista_id, $descripcion, $observaciones,
                $correctivo, $preventivo, $predictivo, $punto_control,
                $uid, $uid
            ]);
            $newId = (int)$pdo->lastInsertId();
            resp(200, ['ok' => true, 'id' => $newId, 'action' => 'created']);
        }
    } catch (Exception $e) {
        resp(500, ['error' => $e->getMessage()]);
    }
    break;

// ── POST: eliminar (soft delete) ─────────────────────────────
case 'pr24_eliminar':
    requireSession();
    $uid = (int)$_SESSION['cina_usuario_id'];
    $id  = (int)($body['id'] ?? 0);
    if (!$id) resp(400, ['error' => 'Falta id']);

    try {
        $pdo->prepare("
            UPDATE sdm_egc_diario
            SET activo = 0, updated_by = ?, updated_at = NOW()
            WHERE sdm_egc_diario_id = ?
        ")->execute([$uid, $id]);
        resp(200, ['ok' => true]);
    } catch (Exception $e) {
        resp(500, ['error' => $e->getMessage()]);
    }
    break;


    // ══════════════════════════════════════════════════════════════════
    //  PR-30 — CONTROL SALA DE MÁQUINAS
    //  Insertar en api_2026ia.php ANTES del default:
    // ══════════════════════════════════════════════════════════════════

    // ── GET: grilla del día (turnos + controles) ─────────────────
    // ?action=pr30_grilla&fecha=YYYY-MM-DD
    case 'pr30_grilla':
        requireSession();
        $fecha = $_GET['fecha'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $fecha = date('Y-m-d');

        // Horarios fijos de cada turno/control
        $grilla_def = [
            ['turno'=>'MAÑANA', 'control_num'=>1, 'hora_prevista'=>'06:30:00'],
            ['turno'=>'MAÑANA', 'control_num'=>2, 'hora_prevista'=>'10:00:00'],
            ['turno'=>'MAÑANA', 'control_num'=>3, 'hora_prevista'=>'13:30:00'],
            ['turno'=>'TARDE',  'control_num'=>1, 'hora_prevista'=>'14:30:00'],
            ['turno'=>'TARDE',  'control_num'=>2, 'hora_prevista'=>'18:00:00'],
            ['turno'=>'TARDE',  'control_num'=>3, 'hora_prevista'=>'21:30:00'],
            ['turno'=>'NOCHE',  'control_num'=>1, 'hora_prevista'=>'22:30:00'],
            ['turno'=>'NOCHE',  'control_num'=>2, 'hora_prevista'=>'02:00:00'],
            ['turno'=>'NOCHE',  'control_num'=>3, 'hora_prevista'=>'05:30:00'],
        ];

        try {
            // Registros ya cargados para esta fecha
            $stmt = $pdo->prepare("
                SELECT
                    c.id, c.turno, c.control_num, c.hora_prevista,
                    c.hora_real, c.verificado, c.observaciones,
                    CONCAT(TRIM(p.apellido), ', ', TRIM(p.nombre)) AS maquinista_nombre,
                    c.maquinista_id
                FROM sdm_pr30_control c
                LEFT JOIN persona p ON p.persona_id = c.maquinista_id
                WHERE c.fecha = ? AND c.activo = 1
                ORDER BY FIELD(c.turno,'MAÑANA','TARDE','NOCHE'), c.control_num
            ");
            $stmt->execute([$fecha]);
            $registros = $stmt->fetchAll();

            // Indexar por turno+num
            $map = [];
            foreach ($registros as $r) {
                $map[$r['turno'] . '_' . $r['control_num']] = $r;
            }

            // Armar respuesta combinando definición con registros
            $rows = [];
            foreach ($grilla_def as $def) {
                $key = $def['turno'] . '_' . $def['control_num'];
                $reg = $map[$key] ?? null;
                $rows[] = [
                    'id'               => $reg['id'] ?? null,
                    'turno'            => $def['turno'],
                    'control_num'      => $def['control_num'],
                    'hora_prevista'    => substr($def['hora_prevista'], 0, 5),
                    'hora_real'        => $reg ? substr($reg['hora_real'] ?? '', 0, 5) : null,
                    'maquinista_id'    => $reg['maquinista_id'] ?? null,
                    'maquinista_nombre'=> $reg['maquinista_nombre'] ?? null,
                    'verificado'       => $reg ? (int)$reg['verificado'] : 0,
                    'observaciones'    => $reg['observaciones'] ?? null,
                    'tiene_registro'   => $reg !== null,
                ];
            }
            resp(200, $rows);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── GET: datos para formulario de nuevo control ──────────────
    // ?action=pr30_form_datos
    case 'pr30_form_datos':
        requireSession();
        try {
            // 1. Cámaras activas con setpoint y temperatura actual
            $camaras = $pdo->query("
                SELECT
                    c.CamaraID,
                    c.CamaraNumero,
                    c.TiposCamaraID,
                    CASE
                        WHEN tt.TiposCamaraNombre LIKE '%tunel%'
                          OR tt.TiposCamaraNombre LIKE '%túnel%'
                            THEN CONCAT('TÚNEL ', c.CamaraNumero)
                        WHEN tt.TiposCamaraNombre LIKE '%antecamara%'
                          OR tt.TiposCamaraNombre LIKE '%antecámara%'
                            THEN CONCAT('ANTECÁMARA ', c.CamaraNumero)
                        ELSE CONCAT('CÁMARA ', c.CamaraNumero)
                    END AS nombre_display,
                    tmp.setpoint    AS setpoint,
                    tmp.temperatura AS temp_actual,
                    tmp.estado      AS temp_estado
                FROM Camaras c
                LEFT JOIN TiposCamara tt ON tt.TiposCamaraID = c.TiposCamaraID
                LEFT JOIN (
                    SELECT m.camara_id, t2.temperatura, t2.setpoint, t2.estado
                    FROM temperatura_camara_map m
                    INNER JOIN temperaturas t2 ON t2.camara = m.nombre_csv
                    INNER JOIN (
                        SELECT camara, MAX(CONCAT(fecha,' ',hora)) AS ultima
                        FROM temperaturas
                        WHERE temperatura IS NOT NULL
                        GROUP BY camara
                    ) u ON u.camara = t2.camara
                       AND CONCAT(t2.fecha,' ',t2.hora) = u.ultima
                    WHERE m.camara_id IS NOT NULL
                ) tmp ON tmp.camara_id = c.CamaraID
                WHERE c.activo = 1
                  AND c.TiposCamaraID IN (1, 2, 7)
                ORDER BY c.TiposCamaraID ASC, c.CamaraNumero ASC
            ")->fetchAll();

            // 2. Última presión del sistema
            $presion = null;
            try {
                $presion = $pdo->query("
                    SELECT pres_succ_30, pres_succ_40, pres_desc, fecha, hora
                    FROM temperaturas
                    WHERE camara = 'PRESIONES'
                      AND (pres_succ_30 IS NOT NULL OR pres_succ_40 IS NOT NULL)
                    ORDER BY fecha DESC, hora DESC
                    LIMIT 1
                ")->fetch();
            } catch(Exception $e) {}

            resp(200, [
                'camaras' => $camaras,
                'presion' => $presion ?: null,
            ]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── GET: detalle completo de un control guardado ─────────────
    // ?action=pr30_detalle&id=N
    case 'pr30_detalle':
        requireSession();
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) resp(400, ['error' => 'Falta id']);
        try {
            // Cabecera
            $stmt = $pdo->prepare("
                SELECT c.*,
                       CONCAT(TRIM(p.apellido),', ',TRIM(p.nombre)) AS maquinista_nombre
                FROM sdm_pr30_control c
                LEFT JOIN persona p ON p.persona_id = c.maquinista_id
                WHERE c.id = ? AND c.activo = 1
            ");
            $stmt->execute([$id]);
            $cabecera = $stmt->fetch();
            if (!$cabecera) resp(404, ['error' => 'Control no encontrado']);

            // Cámaras
            $stmtC = $pdo->prepare("
                SELECT
                    dc.camara_id, dc.temperatura, dc.observaciones,
                    CASE
                        WHEN tt.TiposCamaraNombre LIKE '%tunel%'
                          OR tt.TiposCamaraNombre LIKE '%túnel%'
                            THEN CONCAT('TÚNEL ', cam.CamaraNumero)
                        WHEN tt.TiposCamaraNombre LIKE '%antecamara%'
                          OR tt.TiposCamaraNombre LIKE '%antecámara%'
                            THEN CONCAT('ANTECÁMARA ', cam.CamaraNumero)
                        ELSE CONCAT('CÁMARA ', cam.CamaraNumero)
                    END AS nombre_display,
                    tmp.setpoint, tmp.temperatura AS setpoint_actual
                FROM sdm_pr30_camara dc
                JOIN Camaras cam ON cam.CamaraID = dc.camara_id
                LEFT JOIN TiposCamara tt ON tt.TiposCamaraID = cam.TiposCamaraID
                LEFT JOIN (
                    SELECT m.camara_id, t2.setpoint, t2.temperatura
                    FROM temperatura_camara_map m
                    INNER JOIN temperaturas t2 ON t2.camara = m.nombre_csv
                    INNER JOIN (
                        SELECT camara, MAX(CONCAT(fecha,' ',hora)) AS ultima
                        FROM temperaturas WHERE temperatura IS NOT NULL GROUP BY camara
                    ) u ON u.camara = t2.camara AND CONCAT(t2.fecha,' ',t2.hora) = u.ultima
                ) tmp ON tmp.camara_id = dc.camara_id
                WHERE dc.control_id = ?
                ORDER BY cam.TiposCamaraID, cam.CamaraNumero
            ");
            $stmtC->execute([$id]);
            $camaras = $stmtC->fetchAll();

            // Presiones
            $stmtP = $pdo->prepare("
                SELECT sistema, aspiracion, descarga
                FROM sdm_pr30_presion
                WHERE control_id = ?
                ORDER BY sistema
            ");
            $stmtP->execute([$id]);
            $presiones = $stmtP->fetchAll();

            // Condensadores
            $stmtD = $pdo->prepare("
                SELECT numero, bomba1, bomba2, vent_grupo1, vent_grupo2
                FROM sdm_pr30_condensador
                WHERE control_id = ?
                ORDER BY numero
            ");
            $stmtD->execute([$id]);
            $condensadores = $stmtD->fetchAll();

            resp(200, [
                'cabecera'     => $cabecera,
                'camaras'      => $camaras,
                'presiones'    => $presiones,
                'condensadores'=> $condensadores,
            ]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── POST: guardar control ────────────────────────────────────
    case 'pr30_guardar':
        requireSession();
        $uid = (int)$_SESSION['cina_usuario_id'];

        $fecha        = $body['fecha']        ?? date('Y-m-d');
        $turno        = $body['turno']        ?? '';
        $control_num  = (int)($body['control_num'] ?? 0);
        $hora_real    = $body['hora_real']    ?? null;
        $maquinistaId = (int)($body['maquinista_id'] ?? 0);
        $verificado   = (int)($body['verificado']   ?? 0);
        $observaciones= trim($body['observaciones'] ?? '');
        $camaras      = $body['camaras']      ?? [];  // [{camara_id, temperatura, observaciones}]
        $presiones    = $body['presiones']    ?? [];  // [{sistema, aspiracion, descarga}]
        $condensadores= $body['condensadores']?? [];  // [{numero, bomba1, bomba2, vent_grupo1, vent_grupo2}]

        if (!$fecha || !$turno || !$control_num)
            resp(400, ['error' => 'Faltan campos requeridos (fecha, turno, control_num)']);
        if (!in_array($turno, ['MAÑANA','TARDE','NOCHE']))
            resp(400, ['error' => 'Turno inválido']);

        // Hora prevista fija según turno/control
        $horasPrevistas = [
            'MAÑANA_1'=>'06:30','MAÑANA_2'=>'10:00','MAÑANA_3'=>'13:30',
            'TARDE_1' =>'14:30','TARDE_2' =>'18:00','TARDE_3' =>'21:30',
            'NOCHE_1' =>'22:30','NOCHE_2' =>'02:00','NOCHE_3' =>'05:30',
        ];
        $horaPrevista = $horasPrevistas[$turno . '_' . $control_num] ?? '00:00';

        try {
            $pdo->beginTransaction();

            // UPSERT cabecera
            $stmtCheck = $pdo->prepare("
                SELECT id FROM sdm_pr30_control
                WHERE fecha=? AND turno=? AND control_num=? AND activo=1
            ");
            $stmtCheck->execute([$fecha, $turno, $control_num]);
            $existeId = $stmtCheck->fetchColumn();

            if ($existeId) {
                $pdo->prepare("
                    UPDATE sdm_pr30_control SET
                        hora_real=?, maquinista_id=?, verificado=?,
                        observaciones=?, updated_by=?, updated_at=NOW()
                    WHERE id=?
                ")->execute([$hora_real, $maquinistaId ?: null, $verificado,
                             $observaciones ?: null, $uid, $existeId]);
                $controlId = $existeId;
            } else {
                $pdo->prepare("
                    INSERT INTO sdm_pr30_control
                        (fecha, turno, control_num, hora_prevista, hora_real,
                         maquinista_id, verificado, observaciones, activo,
                         created_by, created_at, updated_by, updated_at)
                    VALUES (?,?,?,?,?,?,?,?,1,?,NOW(),?,NOW())
                ")->execute([$fecha, $turno, $control_num, $horaPrevista, $hora_real,
                             $maquinistaId ?: null, $verificado, $observaciones ?: null,
                             $uid, $uid]);
                $controlId = (int)$pdo->lastInsertId();
            }

            // Cámaras — REPLACE
            if (!empty($camaras)) {
                $pdo->prepare("DELETE FROM sdm_pr30_camara WHERE control_id=?")->execute([$controlId]);
                $stmtCam = $pdo->prepare("
                    INSERT INTO sdm_pr30_camara (control_id, camara_id, temperatura, observaciones)
                    VALUES (?,?,?,?)
                ");
                foreach ($camaras as $cam) {
                    if (empty($cam['camara_id'])) continue;
                    $temp = isset($cam['temperatura']) && $cam['temperatura'] !== '' ? (float)$cam['temperatura'] : null;
                    $stmtCam->execute([
                        $controlId, (int)$cam['camara_id'], $temp,
                        trim($cam['observaciones'] ?? '') ?: null
                    ]);
                }
            }

            // Presiones — REPLACE
            if (!empty($presiones)) {
                $pdo->prepare("DELETE FROM sdm_pr30_presion WHERE control_id=?")->execute([$controlId]);
                $stmtPr = $pdo->prepare("
                    INSERT INTO sdm_pr30_presion (control_id, sistema, aspiracion, descarga)
                    VALUES (?,?,?,?)
                ");
                foreach ($presiones as $pr) {
                    if (empty($pr['sistema'])) continue;
                    $asp  = isset($pr['aspiracion']) && $pr['aspiracion'] !== '' ? (float)$pr['aspiracion'] : null;
                    $desc = isset($pr['descarga'])   && $pr['descarga']   !== '' ? (float)$pr['descarga']   : null;
                    $stmtPr->execute([$controlId, $pr['sistema'], $asp, $desc]);
                }
            }

            // Condensadores — REPLACE
            if (!empty($condensadores)) {
                $pdo->prepare("DELETE FROM sdm_pr30_condensador WHERE control_id=?")->execute([$controlId]);
                $stmtCd = $pdo->prepare("
                    INSERT INTO sdm_pr30_condensador
                        (control_id, numero, bomba1, bomba2, vent_grupo1, vent_grupo2)
                    VALUES (?,?,?,?,?,?)
                ");
                foreach ($condensadores as $cd) {
                    if (empty($cd['numero'])) continue;
                    $stmtCd->execute([
                        $controlId, (int)$cd['numero'],
                        (int)($cd['bomba1'] ?? 0), (int)($cd['bomba2'] ?? 0),
                        (int)($cd['vent_grupo1'] ?? 0), (int)($cd['vent_grupo2'] ?? 0)
                    ]);
                }
            }

            $pdo->commit();
            resp(200, ['ok' => true, 'id' => $controlId]);
        } catch (Exception $e) {
            $pdo->rollBack();
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ══════════════════════════════════════════════════════════
    //  FACTURACIÓN — Resumen completo por cliente y período
    //  GET /api_2026ia.php?action=facturacion_resumen
    //       &cliente_id=123
    //       &desde=2026-01-01
    //       &hasta=2026-01-31
    // ══════════════════════════════════════════════════════════
    case 'facturacion_resumen':
        requireSession();

        $clienteId  = (int)($_GET['cliente_id'] ?? 0);
        $fechaDesde = $_GET['desde'] ?? date('Y-m-01');
        $fechaHasta = $_GET['hasta'] ?? date('Y-m-d');

        if (!$clienteId) resp(400, ['error' => 'Falta cliente_id']);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDesde)) $fechaDesde = date('Y-m-01');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHasta)) $fechaHasta = date('Y-m-d');

        $ts_desde = strtotime($fechaDesde);
        $ts_hasta = strtotime($fechaHasta);
        $dias = (int)(($ts_hasta - $ts_desde) / 86400) + 1;
        if ($dias < 1) resp(400, ['error' => 'Rango de fechas inválido']);

        try {

            // ── Datos del cliente ────────────────────────────────────
            $stmtCli = $pdo->prepare("
                SELECT ClienteID, ClienteNombre,
                       TipoMedicionTemperatura, factura_picking, factura_por
                FROM CLIENTES
                WHERE ClienteID = ?
            ");
            $stmtCli->execute([$clienteId]);
            $cliente = $stmtCli->fetch();
            if (!$cliente) resp(404, ['error' => 'Cliente no encontrado']);

            $factPicking = (bool)($cliente['factura_picking'] ?? false);

            // ── Cotización vigente ────────────────────────────────────
            $stmtCot = $pdo->prepare("
                SELECT *
                FROM cliente_cotizacion
                WHERE cliente_id = ?
                  AND fecha_vigencia <= ?
                ORDER BY fecha_vigencia DESC, nro_cotizacion DESC
                LIMIT 1
            ");
            $stmtCot->execute([$clienteId, $fechaHasta]);
            $cotizacion = $stmtCot->fetch() ?: [];

            $precios = [
                'mant_congelado'   => $cotizacion['precio_mantenimiento_congelado'] ?? null,
                'mant_enfriado'    => $cotizacion['precio_mantenimiento_enfriado']  ?? null,
                'congelado'        => $cotizacion['precio_congelado']               ?? null,
                'mo_cargas'        => $cotizacion['precio_mano_obra_cargas']        ?? null,
                'mo_descargas'     => $cotizacion['precio_mano_obra_descargas']     ?? null,
                'carga_picking'    => $cotizacion['precio_carga_picking']           ?? null,
                'descarga_picking' => $cotizacion['precio_descarga_picking']        ?? null,
                // Swap intencional 1<->3 (igual que actions_class.php)
                'cong_det1'        => $cotizacion['precio_congelado_detallado_3']   ?? null,
                'cong_det2'        => $cotizacion['precio_congelado_detallado_2']   ?? null,
                'cong_det3'        => $cotizacion['precio_congelado_detallado_1']   ?? null,
                'cong_norm1'       => $cotizacion['precio_congelado_normal_2']      ?? null,
                'cong_norm2'       => $cotizacion['precio_congelado_normal_1']      ?? null,
            ];

            // ── Helper: clasificar estibas de una ENTRADA ────────────
            // cajas_por_pallet = 0 -> sin clasificar (no cobra nada)
            $clasificarEstibasEntrada = function(int $cargaId) use ($pdo): array {
                $stmt = $pdo->prepare("
                    SELECT PCE.EstibaID,
                           PCE.Cantidad       AS bultos_estiba,
                           P.cajas_por_pallet AS cajas_por_pallet,
                           PC.KilosUnidad     AS kilos_carga,
                           PC.Cantidad        AS bultos_carga
                    FROM ProductoCargaEstiba PCE
                    INNER JOIN ProductosCarga PC
                           ON PC.CargaID    = PCE.CargaID
                          AND PC.ProductosID = PCE.ProductosID
                    INNER JOIN Productos P ON P.ProductosID = PCE.ProductosID
                    WHERE PCE.CargaID = ?
                ");
                $stmt->execute([$cargaId]);
                $filas = $stmt->fetchAll();
                $pallet  = ['estibas' => 0, 'bultos' => 0, 'kilos' => 0.0];
                $picking = ['estibas' => 0, 'bultos' => 0, 'kilos' => 0.0];
                foreach ($filas as $f) {
                    $cpp  = (int)$f['cajas_por_pallet'];
                    $bEst = (int)$f['bultos_estiba'];
                    $kEst = $f['bultos_carga'] > 0 ? ($f['kilos_carga'] / $f['bultos_carga']) * $bEst : 0;
                    if ($cpp === 0) { continue; }
                    elseif ($bEst >= $cpp) { $pallet['estibas']++; $pallet['bultos'] += $bEst; $pallet['kilos'] += $kEst; }
                    else                   { $picking['estibas']++; $picking['bultos'] += $bEst; $picking['kilos'] += $kEst; }
                }
                return ['pallet' => $pallet, 'picking' => $picking];
            };

            // ── Helper: clasificar estibas de una SALIDA ─────────────
            $clasificarEstibasSalida = function(int $preSalidaId) use ($pdo): array {
                $stmt = $pdo->prepare("
                    SELECT PCS.EstibaID,
                           PCS.Cantidad       AS bultos_estiba,
                           P.cajas_por_pallet AS cajas_por_pallet,
                           PC.KilosUnidad     AS kilos_carga,
                           PC.Cantidad        AS bultos_carga
                    FROM ProductoCargaSalida PCS
                    INNER JOIN ProductosCarga PC
                           ON PC.CargaID    = PCS.CargaID
                          AND PC.ProductosID = PCS.ProductosID
                    INNER JOIN Productos P ON P.ProductosID = PCS.ProductosID
                    WHERE PCS.PreSalidaID = ?
                ");
                $stmt->execute([$preSalidaId]);
                $filas = $stmt->fetchAll();
                $pallet  = ['estibas' => 0, 'bultos' => 0, 'kilos' => 0.0];
                $picking = ['estibas' => 0, 'bultos' => 0, 'kilos' => 0.0];
                foreach ($filas as $f) {
                    $cpp  = (int)$f['cajas_por_pallet'];
                    $bEst = (int)$f['bultos_estiba'];
                    $kEst = $f['bultos_carga'] > 0 ? ($f['kilos_carga'] / $f['bultos_carga']) * $bEst : 0;
                    if ($cpp === 0) { continue; }
                    elseif ($bEst >= $cpp) { $pallet['estibas']++; $pallet['bultos'] += $bEst; $pallet['kilos'] += $kEst; }
                    else                   { $picking['estibas']++; $picking['bultos'] += $bEst; $picking['kilos'] += $kEst; }
                }
                return ['pallet' => $pallet, 'picking' => $picking];
            };

            // ── 1. ENTRADAS (descargas) ──────────────────────────────
            $stmtEnt = $pdo->prepare("
                SELECT PC.CargaID,
                       C.NroRemito,
                       tc.fecha_movimiento,
                       SUM(Cantidad)    AS Bultos,
                       SUM(KilosUnidad) AS Kilos,
                       (SELECT COUNT(DISTINCT PCE.EstibaID)
                          FROM ProductoCargaEstiba PCE
                         WHERE PCE.CargaID = PC.CargaID) AS Posiciones,
                       C.numero_referencia
                FROM Cargas C
                INNER JOIN ProductosCarga PC ON C.CargaID = PC.CargaID
                INNER JOIN transporte_carga tc ON tc.carga_numero = C.carga_numero
                WHERE tc.fecha_movimiento >= ?
                  AND tc.fecha_movimiento <= CONCAT(?, ' 23:59')
                  AND C.ClienteID = ?
                  AND C.Clasificada = 1
                GROUP BY PC.CargaID
                ORDER BY PC.CargaID
            ");
            $stmtEnt->execute([$fechaDesde, $fechaHasta, $clienteId]);
            $entradas = [];
            foreach ($stmtEnt->fetchAll() as $ent) {
                if ($factPicking) $ent['picking_detalle'] = $clasificarEstibasEntrada((int)$ent['CargaID']);
                $entradas[] = $ent;
            }

            // ── 2. SALIDAS (cargas) ──────────────────────────────────
            $stmtSal = $pdo->prepare("
                SELECT PCS.PreSalidaID,
                       C.Remito,
                       C.numero_referencia,
                       tc.fecha_movimiento,
                       SUM(PCS.Cantidad) AS Bultos,
                       SUM(PCS.Cantidad * (
                           SELECT KilosUnidad / Cantidad
                             FROM ProductosCarga
                            WHERE CargaID    = PCS.CargaID
                              AND ProductosID = PCS.ProductosID
                       )) AS Kilos,
                       (SELECT COUNT(DISTINCT E2.EstibaID)
                          FROM ProductoCargaSalida PCS2
                          INNER JOIN Estiba E2 ON E2.EstibaID = PCS2.EstibaID
                          INNER JOIN Cargas C2  ON C2.CargaID = PCS2.CargaID
                         WHERE PCS2.PreSalidaID = PCS.PreSalidaID
                           AND E2.baja_salida_numero = PCS.PreSalidaID
                           AND C2.ClienteID = ?
                       ) AS Posiciones
                FROM PreSalidas C
                INNER JOIN ProductoCargaSalida PCS ON C.PreSalidaID = PCS.PreSalidaID
                INNER JOIN Cargas Ca ON Ca.CargaID = PCS.CargaID
                INNER JOIN transporte_carga tc ON tc.carga_numero = C.carga_numero
                WHERE tc.fecha_movimiento >= ?
                  AND tc.fecha_movimiento <= CONCAT(?, ' 23:59')
                  AND Ca.ClienteID = ?
                  AND C.clasificada = 1
                GROUP BY PCS.PreSalidaID
                ORDER BY PCS.PreSalidaID
            ");
            $stmtSal->execute([$clienteId, $fechaDesde, $fechaHasta, $clienteId]);
            $salidas = [];
            foreach ($stmtSal->fetchAll() as $sal) {
                if ($factPicking) $sal['picking_detalle'] = $clasificarEstibasSalida((int)$sal['PreSalidaID']);
                $salidas[] = $sal;
            }

            // ── 3. CONGELADO por rangos de temperatura ───────────────
            $tipoMedicion = $cliente['TipoMedicionTemperatura'] ?? '';
            $qBase = "SELECT C.CargaID, SUM(PC.Cantidad) AS Cantidad, SUM(PC.KilosUnidad) AS Kilos,
                        (SELECT COUNT(DISTINCT EP.EstibaID)
                           FROM Estiba EP WHERE EP.CargaID = C.CargaID) AS Posiciones,
                        tc.fecha_movimiento, C.NroRemito,
                        (SELECT AVG(Temperatura)
                           FROM CargasControlTempMedicion CCTM
                          WHERE CCTM.ControlTemperaturaID = CCT.ControlTemperaturaID
                        ) AS Temperatura,
                        C.numero_referencia
                 FROM Cargas C
                 INNER JOIN ProductosCarga PC ON C.CargaID = PC.CargaID
                 INNER JOIN CargasControlTemp CCT ON CCT.EntradaID = C.CargaID
                 INNER JOIN Productos ON PC.ProductosID = Productos.ProductosID
                 INNER JOIN transporte_carga tc ON tc.carga_numero = C.carga_numero
                 WHERE tc.fecha_movimiento >= '$fechaDesde'
                   AND PC.Congelado = 1
                   AND tc.fecha_movimiento <= '$fechaHasta 23:59'
                   AND C.ClienteID = $clienteId
                   AND CCT.NroControl = 1";

            $congelado = [];
            if ($tipoMedicion === 'S') {
                $congelado = [
                    'tipo'    => 'simple',
                    'normal1' => $pdo->query($qBase . " GROUP BY C.CargaID HAVING Temperatura BETWEEN -12 AND -4 ORDER BY C.CargaID")->fetchAll(),
                    'normal2' => $pdo->query($qBase . " GROUP BY C.CargaID HAVING Temperatura > -4 ORDER BY C.CargaID")->fetchAll(),
                ];
            } else {
                $congelado = [
                    'tipo'     => 'detallado',
                    'detalle1' => $pdo->query($qBase . " GROUP BY C.CargaID HAVING Temperatura BETWEEN -15 AND -10 ORDER BY C.CargaID")->fetchAll(),
                    'detalle2' => $pdo->query($qBase . " GROUP BY C.CargaID HAVING Temperatura BETWEEN -9.9999999999999 AND -5 ORDER BY C.CargaID")->fetchAll(),
                    'detalle3' => $pdo->query($qBase . " GROUP BY C.CargaID HAVING Temperatura > -5 ORDER BY C.CargaID")->fetchAll(),
                ];
            }

            // ── 4. MANTENIMIENTO — Ingresos ──────────────────────────
            $stmtMantIn = $pdo->prepare("
                SELECT C.CargaID, C.NroRemito, tc.fecha_movimiento,
                       SUM(PC.Cantidad)    AS Cantidad,
                       SUM(PC.KilosUnidad) AS Kilos,
                       (SELECT COUNT(DISTINCT E.EstibaID)
                          FROM Estiba E WHERE E.CargaID = C.CargaID) AS Posiciones,
                       DATEDIFF(?, tc.fecha_movimiento) AS Dias,
                       PC.Congelado, C.numero_referencia
                FROM ProductosCarga PC
                INNER JOIN Cargas C ON C.CargaID = PC.CargaID
                INNER JOIN Productos P ON P.ProductosID = PC.ProductosID
                INNER JOIN transporte_carga tc ON tc.carga_numero = C.carga_numero
                WHERE tc.fecha_movimiento <= CONCAT(?, ' 23:59')
                  AND tc.fecha_movimiento >= ?
                  AND C.ClienteID = ?
                  AND C.Clasificada = 1
                GROUP BY C.CargaID, PC.Congelado
                ORDER BY C.CargaID
            ");
            $stmtMantIn->execute([$fechaHasta, $fechaHasta, $fechaDesde, $clienteId]);
            $mantIngresos = $stmtMantIn->fetchAll();

            // ── 5. MANTENIMIENTO — Egresos ───────────────────────────
            $stmtMantEg = $pdo->prepare("
                SELECT S.PreSalidaID, S.Remito, tc.fecha_movimiento,
                       SUM(PCS.Cantidad) AS Cantidad,
                       SUM(PC.KilosUnidad / PC.Cantidad * PCS.Cantidad) AS Kilos,
                       (SELECT COUNT(DISTINCT E2.EstibaID)
                          FROM Estiba E2
                          INNER JOIN Cargas C2 ON C2.CargaID = E2.CargaID
                         WHERE C2.ClienteID = ?
                           AND E2.baja_salida_numero = S.PreSalidaID) AS Posiciones,
                       DATEDIFF(?, tc.fecha_movimiento) AS Dias,
                       PC.Congelado, S.numero_referencia
                FROM ProductoCargaSalida PCS
                INNER JOIN ProductosCarga PC ON PC.CargaID = PCS.CargaID AND PC.ProductosID = PCS.ProductosID
                INNER JOIN PreSalidas S ON S.PreSalidaID = PCS.PreSalidaID
                INNER JOIN Productos P ON P.ProductosID = PCS.ProductosID
                INNER JOIN Cargas C ON C.CargaID = PCS.CargaID
                INNER JOIN Estiba E ON E.EstibaID = PCS.EstibaID
                INNER JOIN transporte_carga tc ON tc.carga_numero = S.carga_numero
                WHERE tc.fecha_movimiento <= CONCAT(?, ' 23:59')
                  AND tc.fecha_movimiento >= ?
                  AND C.ClienteID = ?
                  AND C.Clasificada = 1
                  AND S.clasificada = 1
                GROUP BY S.PreSalidaID, PC.Congelado
                ORDER BY S.PreSalidaID
            ");
            $stmtMantEg->execute([$clienteId, $fechaHasta, $fechaHasta, $fechaDesde, $clienteId]);
            $mantEgresos = $stmtMantEg->fetchAll();

            // ── 6. STOCK PREVIO al inicio del período ────────────────
            $fnPosiciones = function(int $congelado) use ($pdo, $clienteId, $fechaDesde): int {
                $stmt = $pdo->prepare("
                    SELECT COUNT(DISTINCT E.EstibaID) AS posiciones
                    FROM Estiba E
                    INNER JOIN Cargas C ON C.CargaID = E.CargaID
                    INNER JOIN ProductosCarga PC ON C.CargaID = PC.CargaID
                    INNER JOIN transporte_carga TCin ON C.carga_numero = TCin.carga_numero
                    INNER JOIN ProductoCargaEstiba PCE
                           ON PC.CargaID = PCE.CargaID
                          AND PC.ProductosID = PCE.ProductosID
                          AND PCE.EstibaID = E.EstibaID
                    LEFT JOIN PreSalidas S ON S.PreSalidaID = E.baja_salida_numero
                    LEFT JOIN transporte_carga TCout ON S.carga_numero = TCout.carga_numero
                    WHERE C.ClienteID = ?
                      AND PC.Congelado = ?
                      AND C.Clasificada = 1
                      AND (S.clasificada IS NULL OR S.clasificada = 1)
                      AND TCin.fecha_movimiento < ?
                      AND (
                            (TCout.fecha_movimiento IS NULL AND E.FechaBaja IS NULL)
                            OR TCout.fecha_movimiento >= ?
                          )
                ");
                $stmt->execute([$clienteId, $congelado, $fechaDesde, $fechaDesde]);
                return (int)$stmt->fetchColumn();
            };

            $fnStock = function(int $congelado) use ($pdo, $clienteId, $fechaDesde): array {
                $stmt = $pdo->prepare("
                    SELECT SUM(Cantidad) AS Cantidad, SUM(Kilos) AS Kilos FROM (
                        SELECT SUM(PCE.Cantidad) AS Cantidad,
                               SUM(PC.KilosUnidad / PC.Cantidad * PCE.Cantidad) AS Kilos
                        FROM ProductoCargaEstiba PCE
                        INNER JOIN ProductosCarga PC ON PC.CargaID = PCE.CargaID AND PC.ProductosID = PCE.ProductosID
                        INNER JOIN Cargas C ON C.CargaID = PC.CargaID
                        INNER JOIN transporte_carga tc ON tc.carga_numero = C.carga_numero
                        WHERE PCE.Cantidad > 0
                          AND PC.Congelado = ?
                          AND tc.fecha_movimiento < ?
                          AND C.ClienteID = ?
                          AND C.Clasificada = 1
                        UNION
                        SELECT SUM(PCS.Cantidad) AS Cantidad,
                               SUM(PC.KilosUnidad / PC.Cantidad * PCS.Cantidad) AS Kilos
                        FROM ProductoCargaSalida PCS
                        INNER JOIN PreSalidas S ON S.PreSalidaID = PCS.PreSalidaID
                        INNER JOIN Cargas C ON C.CargaID = PCS.CargaID
                        INNER JOIN ProductosCarga PC ON PC.CargaID = PCS.CargaID AND PC.ProductosID = PCS.ProductosID
                        INNER JOIN transporte_carga tc  ON tc.carga_numero = S.carga_numero
                        INNER JOIN transporte_carga tc1 ON tc1.carga_numero = C.carga_numero
                        WHERE C.ClienteID = ?
                          AND PC.Congelado = ?
                          AND S.clasificada = 1
                          AND tc.fecha_movimiento  >= ?
                          AND tc1.fecha_movimiento <  ?
                    ) productos
                ");
                $stmt->execute([$congelado, $fechaDesde, $clienteId,
                                $clienteId, $congelado, $fechaDesde, $fechaDesde]);
                $row = $stmt->fetch();
                return ['cantidad' => $row['Cantidad'] ?? null, 'kilos' => $row['Kilos'] ?? null];
            };

            $stockCong = $fnStock(1);
            $stockEnfr = $fnStock(0);

            // ── Productos sin cajas_por_pallet que operaron en el período ──
            $productosSinCpp = [];
            if ($factPicking) {
                $stmtSinCpp = $pdo->prepare("
                    SELECT DISTINCT P.ProductosID, P.CodigoProducto, P.Descripcion1,
                                    P.Marca, P.cajas_por_pallet
                    FROM Productos P
                    INNER JOIN ProductosCarga PC ON PC.ProductosID = P.ProductosID
                    INNER JOIN Cargas C ON C.CargaID = PC.CargaID
                    INNER JOIN transporte_carga tc ON tc.carga_numero = C.carga_numero
                    WHERE C.ClienteID = ?
                      AND C.Clasificada = 1
                      AND tc.fecha_movimiento >= ?
                      AND tc.fecha_movimiento <= CONCAT(?, ' 23:59')
                      AND (P.cajas_por_pallet = 0 OR P.cajas_por_pallet IS NULL)
                    ORDER BY P.Descripcion1
                ");
                $stmtSinCpp->execute([$clienteId, $fechaDesde, $fechaHasta]);
                $productosSinCpp = $stmtSinCpp->fetchAll();
            }

            resp(200, [
                'cliente'           => $cliente,
                'precios'           => $precios,
                'dias'              => $dias,
                'fecha_desde'       => $fechaDesde,
                'fecha_hasta'       => $fechaHasta,
                'fact_picking'      => $factPicking,
                'cant_entradas'     => $entradas,
                'cant_salidas'      => $salidas,
                'congelado'         => $congelado,
                'mant_ingresos'     => $mantIngresos,
                'mant_egresos'      => $mantEgresos,
                'productos_sin_cpp' => $productosSinCpp,
                'stock_previo'      => [
                    'congelado' => array_merge($stockCong, ['posiciones' => $fnPosiciones(1)]),
                    'enfriado'  => array_merge($stockEnfr, ['posiciones' => $fnPosiciones(0)]),
                ],
            ]);

        } catch (Exception $e) {
            resp(500, ['error' => 'Error al generar resumen: ' . $e->getMessage()]);
        }
        break;

    // ── GET: todos los campos de una cotización ────────────────
    case 'cotizacion_detalle':
        requireSession();
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) resp(400, ['error' => 'Falta id']);
        $stmt = $pdo->prepare("
            SELECT cc.*,
                   DATE_FORMAT(cc.fecha_vigencia, '%Y-%m-%d') AS fecha_vigencia_iso
            FROM cliente_cotizacion cc
            WHERE cc.cliente_cotizacion_id = ?
        ");
        $stmt->execute([$id]);
        $cot = $stmt->fetch();
        if (!$cot) resp(404, ['error' => 'Cotización no encontrada']);
        resp(200, $cot);
        break;

    // ── POST: crear o editar cotización ───────────────────────
    case 'cotizacion_guardar':
        requireSession();
        $data = $body;
        $clienteId = (int)($data['cliente_id'] ?? 0);
        if (!$clienteId) resp(400, ['error' => 'Falta cliente_id']);
        $fechaVig = trim($data['fecha_vigencia'] ?? '');
        if (!$fechaVig || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaVig))
            resp(400, ['error' => 'Fecha de vigencia inválida (formato YYYY-MM-DD)']);
        $camposCot = [
            'precio_congelado'               => (float)($data['precio_congelado']               ?? 0),
            'precio_mantenimiento_congelado'  => (float)($data['precio_mantenimiento_congelado'] ?? 0),
            'precio_mantenimiento_enfriado'   => (float)($data['precio_mantenimiento_enfriado']  ?? 0),
            'precio_mano_obra_cargas'         => (float)($data['precio_mano_obra_cargas']        ?? 0),
            'precio_mano_obra_descargas'      => (float)($data['precio_mano_obra_descargas']     ?? 0),
            'precio_carga_picking'            => (float)($data['precio_carga_picking']           ?? 0),
            'precio_descarga_picking'         => (float)($data['precio_descarga_picking']        ?? 0),
            'precio_congelado_detallado_1'    => (float)($data['precio_congelado_detallado_1']   ?? 0),
            'precio_congelado_detallado_2'    => (float)($data['precio_congelado_detallado_2']   ?? 0),
            'precio_congelado_detallado_3'    => (float)($data['precio_congelado_detallado_3']   ?? 0),
            'precio_congelado_normal_1'       => (float)($data['precio_congelado_normal_1']      ?? 0),
            'precio_congelado_normal_2'       => (float)($data['precio_congelado_normal_2']      ?? 0),
        ];
        $usuarioId = $_SESSION['cina_usuario_id'];
        $cotId     = (int)($data['cliente_cotizacion_id'] ?? 0);
        if ($cotId) {
            $chk = $pdo->prepare("SELECT cliente_id FROM cliente_cotizacion WHERE cliente_cotizacion_id = ?");
            $chk->execute([$cotId]);
            $row = $chk->fetch();
            if (!$row) resp(404, ['error' => 'Cotización no encontrada']);
            if ((int)$row['cliente_id'] !== $clienteId)
                resp(403, ['error' => 'La cotización no pertenece al cliente indicado']);
            $pdo->prepare("
                UPDATE cliente_cotizacion SET
                    fecha_vigencia                 = ?,
                    precio_congelado               = ?,
                    precio_mantenimiento_congelado = ?,
                    precio_mantenimiento_enfriado  = ?,
                    precio_mano_obra_cargas        = ?,
                    precio_mano_obra_descargas     = ?,
                    precio_carga_picking           = ?,
                    precio_descarga_picking        = ?,
                    precio_congelado_detallado_1   = ?,
                    precio_congelado_detallado_2   = ?,
                    precio_congelado_detallado_3   = ?,
                    precio_congelado_normal_1      = ?,
                    precio_congelado_normal_2      = ?,
                    usuario_id                     = ?,
                    updated_at                     = NOW()
                WHERE cliente_cotizacion_id = ?
            ")->execute([
                $fechaVig,
                $camposCot['precio_congelado'],               $camposCot['precio_mantenimiento_congelado'],
                $camposCot['precio_mantenimiento_enfriado'],  $camposCot['precio_mano_obra_cargas'],
                $camposCot['precio_mano_obra_descargas'],     $camposCot['precio_carga_picking'],
                $camposCot['precio_descarga_picking'],        $camposCot['precio_congelado_detallado_1'],
                $camposCot['precio_congelado_detallado_2'],   $camposCot['precio_congelado_detallado_3'],
                $camposCot['precio_congelado_normal_1'],      $camposCot['precio_congelado_normal_2'],
                $usuarioId, $cotId,
            ]);
            resp(200, ['ok' => true, 'id' => $cotId, 'modo' => 'editado']);
        } else {
            $stmtNro = $pdo->prepare("SELECT COALESCE(MAX(nro_cotizacion),0)+1 FROM cliente_cotizacion WHERE cliente_id = ?");
            $stmtNro->execute([$clienteId]);
            $nroCot = (int)$stmtNro->fetchColumn();
            $pdo->prepare("
                INSERT INTO cliente_cotizacion
                    (cliente_id, nro_cotizacion, fecha_vigencia,
                     precio_congelado, precio_mantenimiento_congelado, precio_mantenimiento_enfriado,
                     precio_mano_obra_cargas, precio_mano_obra_descargas,
                     precio_carga_picking, precio_descarga_picking,
                     precio_congelado_detallado_1, precio_congelado_detallado_2, precio_congelado_detallado_3,
                     precio_congelado_normal_1, precio_congelado_normal_2,
                     usuario_id, created_at, updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())
            ")->execute([
                $clienteId, $nroCot, $fechaVig,
                $camposCot['precio_congelado'],               $camposCot['precio_mantenimiento_congelado'],
                $camposCot['precio_mantenimiento_enfriado'],  $camposCot['precio_mano_obra_cargas'],
                $camposCot['precio_mano_obra_descargas'],     $camposCot['precio_carga_picking'],
                $camposCot['precio_descarga_picking'],        $camposCot['precio_congelado_detallado_1'],
                $camposCot['precio_congelado_detallado_2'],   $camposCot['precio_congelado_detallado_3'],
                $camposCot['precio_congelado_normal_1'],      $camposCot['precio_congelado_normal_2'],
                $usuarioId,
            ]);
            resp(200, ['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'nro' => $nroCot, 'modo' => 'creado']);
        }
        break;

    // ══════════════════════════════════════════════════════════
    //  PRODUCTOS — Lista, ficha, crear, editar
    // ══════════════════════════════════════════════════════════
    case 'productos_lista':
        requireSession();
        $buscar      = trim($_GET['buscar'] ?? '');
        $pagina      = max(1, (int)($_GET['pagina']    ?? 1));
        $porPag      = min(100, max(10, (int)($_GET['por_pagina'] ?? 50)));
        $offset      = ($pagina - 1) * $porPag;
        $soloActivos = (int)($_GET['activos'] ?? 1);
        $where       = $soloActivos ? 'WHERE P.Activo = 1' : 'WHERE 1=1';
        $params      = [];
        if ($buscar) {
            $where  .= ' AND (P.Descripcion1 LIKE ? OR P.CodigoProducto LIKE ? OR P.Marca LIKE ?)';
            $params  = ["%$buscar%", "%$buscar%", "%$buscar%"];
        }
        try {
            $total = $pdo->prepare("SELECT COUNT(*) FROM Productos P $where");
            $total->execute($params);
            $totalRows = (int)$total->fetchColumn();

            // LIMIT/OFFSET como enteros literales (ya validados con cast+clamp arriba)
            // evita el problema de PDO emulado que cita los números y rompe el LIMIT.
            $stmt = $pdo->prepare("
                SELECT P.ProductosID, P.CodigoProducto, P.Descripcion1, P.Descripcion2,
                       P.Marca, P.peso_estandar, P.cajas_por_pallet, P.Activo,
                       TP.TiposProductoNombre AS tipo_nombre
                FROM Productos P
                LEFT JOIN TiposProducto TP ON TP.TiposProductoID = P.TiposProductoID
                $where
                ORDER BY P.Descripcion1 ASC
                LIMIT " . (int)$porPag . " OFFSET " . (int)$offset . "
            ");
            $stmt->execute($params);
            resp(200, [
                'productos'  => $stmt->fetchAll(),
                'total'      => $totalRows,
                'pagina'     => $pagina,
                'por_pagina' => $porPag,
                'paginas'    => $totalRows > 0 ? (int)ceil($totalRows / $porPag) : 0,
            ]);
        } catch (Exception $e) {
            error_log('[productos_lista] '.$e->getMessage());
            resp(500, ['error' => 'Error al listar productos: '.$e->getMessage()]);
        }
        break;

    case 'productos_ficha':
        requireSession();
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) resp(400, ['error' => 'Falta id']);
        $stmt = $pdo->prepare("
            SELECT P.*, TP.TiposProductoNombre AS tipo_nombre
            FROM Productos P
            LEFT JOIN TiposProducto TP ON TP.TiposProductoID = P.TiposProductoID
            WHERE P.ProductosID = ?
        ");
        $stmt->execute([$id]);
        $prod = $stmt->fetch();
        if (!$prod) resp(404, ['error' => 'Producto no encontrado']);

        // Clientes asignados a este producto
        $stCl = $pdo->prepare("SELECT PC.ClienteID, CL.ClienteNombre FROM ProductosCliente PC INNER JOIN CLIENTES CL ON CL.ClienteID = PC.ClienteID WHERE PC.ProductosID = ? ORDER BY CL.ClienteNombre");
        $stCl->execute([$id]);
        $prod['clientes_asignados'] = $stCl->fetchAll();

        // Todos los clientes activos para el selector
        $prod['clientes_disponibles'] = $pdo->query("SELECT ClienteID, ClienteNombre FROM CLIENTES WHERE Activo = 1 ORDER BY ClienteNombre")->fetchAll();

        resp(200, $prod);
        break;

    case 'producto_asignar_cliente':
        requireSession();
        $b = json_input();
        $prodId = intval($b['ProductosID'] ?? 0);
        $clienteId = intval($b['ClienteID'] ?? 0);
        if (!$prodId || !$clienteId) resp(400, ['error'=>'Faltan datos']);
        try {
            $ex = $pdo->prepare("SELECT COUNT(*) FROM ProductosCliente WHERE ProductosID=? AND ClienteID=?");
            $ex->execute([$prodId, $clienteId]);
            if ((int)$ex->fetchColumn() > 0) resp(400, ['error'=>'El cliente ya está asignado a este producto']);
            $pdo->prepare("INSERT INTO ProductosCliente (ProductosID, ClienteID) VALUES (?,?)")->execute([$prodId, $clienteId]);
            resp(201, ['ok'=>true]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'producto_desasignar_cliente':
        requireSession();
        $b = json_input();
        $prodId = intval($b['ProductosID'] ?? 0);
        $clienteId = intval($b['ClienteID'] ?? 0);
        if (!$prodId || !$clienteId) resp(400, ['error'=>'Faltan datos']);
        try {
            $pdo->prepare("DELETE FROM ProductosCliente WHERE ProductosID=? AND ClienteID=?")->execute([$prodId, $clienteId]);
            resp(200, ['ok'=>true]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'productos_tipos':
        requireSession();
        $stmt = $pdo->query("SELECT TiposProductoID, TiposProductoNombre FROM TiposProducto ORDER BY TiposProductoNombre");
        resp(200, $stmt->fetchAll());
        break;

    case 'productos_editar':
        requireSession();
        $data = $body;
        $id   = (int)($data['ProductosID'] ?? 0);
        if (!$id) resp(400, ['error' => 'Falta ProductosID']);
        $desc1 = trim($data['Descripcion1'] ?? '');
        if (!$desc1) resp(400, ['error' => 'La descripción es requerida']);
        $pdo->prepare("
            UPDATE Productos SET
                CodigoProducto      = ?,
                Descripcion1        = ?,
                Descripcion2        = ?,
                Marca               = ?,
                peso_estandar       = ?,
                cajas_por_pallet    = ?,
                dias_vida_estandar  = ?,
                dias_vida_comercial = ?,
                Activo              = ?,
                FechaModificacion   = NOW(),
                UsuarioID           = ?
            WHERE ProductosID = ?
        ")->execute([
            trim($data['CodigoProducto'] ?? ''),
            $desc1,
            trim($data['Descripcion2']   ?? ''),
            trim($data['Marca']          ?? ''),
            (float)($data['peso_estandar']      ?? 0),
            (int)($data['cajas_por_pallet']     ?? 0),
            (int)($data['dias_vida_estandar']   ?? 0),
            (int)($data['dias_vida_comercial']  ?? 0),
            (int)($data['Activo']               ?? 1),
            $_SESSION['cina_usuario_id'],
            $id,
        ]);
        resp(200, ['ok' => true]);
        break;

    case 'productos_crear':
        requireSession();
        $data  = $body;
        $desc1 = trim($data['Descripcion1'] ?? '');
        if (!$desc1) resp(400, ['error' => 'La descripción 1 es requerida']);
        $pdo->prepare("
            INSERT INTO Productos
                (CodigoProducto, Descripcion1, Descripcion2, Marca,
                 peso_estandar, cajas_por_pallet, Activo,
                 FechaCreacion, FechaModificacion, UsuarioID)
            VALUES (?,?,?,?,?,?,?,NOW(),NOW(),?)
        ")->execute([
            trim($data['CodigoProducto'] ?? ''),
            $desc1,
            trim($data['Descripcion2']   ?? ''),
            trim($data['Marca']          ?? ''),
            (float)($data['peso_estandar']   ?? 0),
            (int)($data['cajas_por_pallet']  ?? 0),
            1,
            $_SESSION['cina_usuario_id'],
        ]);
        $newId = (int)$pdo->lastInsertId();
        resp(201, ['ok' => true, 'ProductosID' => $newId]);
        break;

    // ════════════════════════════════════════════════════════════
    //  MÓDULO: ADMINISTRACIÓN DE USUARIOS Y ROLES
    // ════════════════════════════════════════════════════════════

    // ── GET: lista completa de usuarios ─────────────────────────
    // ── GET: diagnóstico de la tabla Usuarios (solo admin) ──────
    case 'usuarios_debug':
        requireSession();
        $db = $pdo->query("SELECT DATABASE()")->fetchColumn();
        $cols = $pdo->query("
            SELECT COLUMN_NAME, DATA_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA='$db' AND TABLE_NAME='Usuarios'
            ORDER BY ORDINAL_POSITION
        ")->fetchAll();
        $count = $pdo->query("SELECT COUNT(*) FROM Usuarios")->fetchColumn();
        $stmtRolCk2 = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
        $stmtRolCk2->execute([$_SESSION['cina_usuario_id']]);
        $myRol = $stmtRolCk2->fetchColumn();
        resp(200, [
            'database'       => $db,
            'total_usuarios' => (int)$count,
            'mi_usuario_id'  => $_SESSION['cina_usuario_id'],
            'mi_rol_id'      => $myRol,
            'columnas'       => $cols,
        ]);
        break;

    // ── GET: lista completa de usuarios ─────────────────────────
    case 'usuarios_lista':
        requireSession();
        $stmtRolCk = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
        $stmtRolCk->execute([$_SESSION['cina_usuario_id']]);
        $rolActualUser = (int)$stmtRolCk->fetchColumn();
        if (!in_array($rolActualUser, [1,2,3,5,10])) {
            resp(403, ['error' => "Sin permisos. RolID=$rolActualUser"]);
        }
        // Columnas confirmadas: UsuarioID, persona_id, RolID, UsuarioNombre,
        // activo, ultimo_login, cantidad_login, es_cliente
        $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
        $tieneFoto = (bool)$pdo->query("
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA='$dbName' AND TABLE_NAME='Usuarios' AND COLUMN_NAME='foto_url'
        ")->fetchColumn();
        $colFoto = $tieneFoto ? 'u.foto_url' : 'NULL';
        try {
            $rows = $pdo->query("
                SELECT
                    u.UsuarioID,
                    u.UsuarioNombre,
                    u.RolID,
                    u.activo,
                    u.ultimo_login   AS UltimoLogin,
                    u.cantidad_login,
                    $colFoto         AS foto_url,
                    COALESCE(
                        NULLIF(TRIM(CONCAT(COALESCE(p.nombre,''), ' ', COALESCE(p.apellido,''))), ''),
                        u.UsuarioNombre
                    ) AS nombre_completo,
                    p.email1         AS email,
                    p.telefono_movil,
                    pe.legajo,
                    pe.fecha_ingreso,
                    u.proveedor_id
                FROM Usuarios u
                LEFT JOIN persona p
                    ON p.persona_id = u.persona_id
                LEFT JOIN persona_empleado pe
                    ON pe.persona_id = u.persona_id
                ORDER BY u.activo DESC, u.RolID ASC, u.UsuarioNombre ASC
            ")->fetchAll();
            resp(200, $rows);
        } catch (Exception $e) {
            // Fallback mínimo sin joins
            try {
                $rows = $pdo->query("
                    SELECT UsuarioID, UsuarioNombre, RolID, activo,
                           ultimo_login AS UltimoLogin, cantidad_login,
                           NULL AS foto_url, UsuarioNombre AS nombre_completo,
                           NULL AS email, NULL AS telefono_movil,
                           NULL AS legajo, NULL AS fecha_ingreso
                    FROM Usuarios
                    ORDER BY activo DESC, UsuarioNombre ASC
                ")->fetchAll();
                resp(200, $rows);
            } catch (Exception $e2) {
                resp(500, ['error' => $e->getMessage(), 'fallback' => $e2->getMessage()]);
            }
        }
        break;

    // ── GET: lista de roles con cantidad de usuarios ─────────────
    case 'roles_lista':
        requireSession();
        try {
            // Detectar nombre real de la columna función (puede ser 'funcion' o 'Funcion')
            $dbName2   = $pdo->query("SELECT DATABASE()")->fetchColumn();
            $colsFuncion = $pdo->query("
                SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA='$dbName2' AND TABLE_NAME='Roles'
                  AND LOWER(COLUMN_NAME) IN ('funcion','función','function','default_action')
                LIMIT 1
            ")->fetchColumn();
            $colFuncion = $colsFuncion ? "r.`$colsFuncion`" : "NULL";

            $rows = $pdo->query("
                SELECT r.RolID, r.RolNombre,
                       COALESCE($colFuncion, '') AS funcion,
                       COUNT(u.UsuarioID) AS cantidad_usuarios
                FROM Roles r
                LEFT JOIN Usuarios u ON u.RolID = r.RolID AND u.activo = 1
                GROUP BY r.RolID, r.RolNombre
                ORDER BY r.RolNombre
            ")->fetchAll();
            resp(200, $rows);
        } catch (Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    // ── GET: foto del usuario en sesión ─────────────────────────
    case 'usuario_foto_actual':
        requireSession();
        try {
            $dbName3 = $pdo->query("SELECT DATABASE()")->fetchColumn();
            $existe  = (bool)$pdo->query("
                SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA='$dbName3' AND TABLE_NAME='Usuarios' AND COLUMN_NAME='foto_url'
            ")->fetchColumn();
            if (!$existe) { resp(200, ['foto_url' => null]); }
            $stmt = $pdo->prepare("SELECT foto_url FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
            $stmt->execute([$_SESSION['cina_usuario_id']]);
            resp(200, ['foto_url' => $stmt->fetchColumn() ?: null]);
        } catch (Exception $e) { resp(200, ['foto_url' => null]); }
        break;

    // ── POST: crear usuario ──────────────────────────────────────
    case 'usuario_crear':
        requireSession();
        $stmtRolCk = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
        $stmtRolCk->execute([$_SESSION['cina_usuario_id']]);
        if (!in_array((int)$stmtRolCk->fetchColumn(), [1,5,10])) resp(403, ['error'=>'Sin permisos']);
        $uNombre  = trim($body['UsuarioNombre'] ?? '');
        $password = $body['password'] ?? '';
        $rolId    = (int)($body['RolID'] ?? 0);
        $activo   = (int)($body['activo'] ?? 1);
        if (!$uNombre)  resp(400, ['error'=>'El nombre de usuario es obligatorio']);
        if (!$password) resp(400, ['error'=>'La contraseña es obligatoria']);
        $chk = $pdo->prepare("SELECT UsuarioID FROM Usuarios WHERE UsuarioNombre = ? LIMIT 1");
        $chk->execute([$uNombre]);
        if ($chk->fetchColumn()) resp(409, ['error'=>'El usuario ya existe']);
        try {
            // Permitir persona_id NULL para usuarios externos (proveedores, clientes)
            try { $pdo->exec("ALTER TABLE Usuarios MODIFY COLUMN persona_id SMALLINT(5) UNSIGNED NULL DEFAULT NULL"); } catch(Exception $e) {}
            $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
            $pdo->prepare("INSERT INTO Usuarios (UsuarioNombre, UsuarioClave, RolID, activo, persona_id, ultimo_login) VALUES (?, SHA1(?), ?, ?, NULL, NOW())")
                ->execute([$uNombre, $password, $rolId ?: null, $activo]);
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            resp(200, ['ok'=>true, 'id'=>(int)$pdo->lastInsertId()]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    // ── POST: editar usuario ─────────────────────────────────────
    case 'usuario_editar':
        requireSession();
        $stmtRolCk = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
        $stmtRolCk->execute([$_SESSION['cina_usuario_id']]);
        if (!in_array((int)$stmtRolCk->fetchColumn(), [1,5,10])) resp(403, ['error'=>'Sin permisos']);
        $usuarioId = (int)($body['UsuarioID'] ?? 0);
        $rolId     = (int)($body['RolID']     ?? 0);
        $activo    = (int)($body['activo']    ?? 1);
        $email     = trim($body['email']      ?? '');
        if (!$usuarioId) resp(400, ['error'=>'Falta UsuarioID']);
        try {
            $pdo->prepare("UPDATE Usuarios SET RolID=?, activo=? WHERE UsuarioID=?")
                ->execute([$rolId ?: null, $activo, $usuarioId]);
            if ($email) {
                $stmtPer = $pdo->prepare("SELECT persona_id FROM Usuarios WHERE UsuarioID=? LIMIT 1");
                $stmtPer->execute([$usuarioId]);
                $pId = $stmtPer->fetchColumn();
                if ($pId) $pdo->prepare("UPDATE persona SET email1=? WHERE persona_id=?")->execute([$email, $pId]);
            }
            resp(200, ['ok'=>true]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    // ── POST: cambiar contraseña ─────────────────────────────────
    case 'usuario_cambiar_clave':
        requireSession();
        $stmtRolCk = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
        $stmtRolCk->execute([$_SESSION['cina_usuario_id']]);
        $rolActual  = (int)$stmtRolCk->fetchColumn();
        $usuarioId  = (int)($body['UsuarioID'] ?? 0);
        $password   = $body['password'] ?? '';
        $esPropioU  = ($usuarioId === (int)$_SESSION['cina_usuario_id']);
        $esAdmin    = in_array($rolActual, [1,5,10]);
        if (!$esPropioU && !$esAdmin) resp(403, ['error'=>'Sin permisos']);
        if (!$usuarioId)  resp(400, ['error'=>'Falta UsuarioID']);
        if (!$password)   resp(400, ['error'=>'La contraseña no puede estar vacía']);
        try {
            $pdo->prepare("UPDATE Usuarios SET UsuarioClave=SHA1(?) WHERE UsuarioID=?")
                ->execute([$password, $usuarioId]);
            resp(200, ['ok'=>true]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    // ── POST: actualizar foto_url (reset o URL directa) ──────────
    case 'usuario_actualizar_foto':
        requireSession();
        $stmtRolCk = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
        $stmtRolCk->execute([$_SESSION['cina_usuario_id']]);
        if (!in_array((int)$stmtRolCk->fetchColumn(), [1,5,10])) resp(403, ['error'=>'Sin permisos para modificar fotos']);
        $usuarioId = (int)($body['UsuarioID'] ?? 0);
        $fotoUrl   = array_key_exists('foto_url', $body) ? $body['foto_url'] : null;
        if (!$usuarioId) resp(400, ['error'=>'Falta UsuarioID']);
        try {
            try { $pdo->exec("ALTER TABLE Usuarios ADD COLUMN foto_url VARCHAR(255) NULL"); } catch(Exception $ex){}
            $pdo->prepare("UPDATE Usuarios SET foto_url=? WHERE UsuarioID=?")->execute([$fotoUrl, $usuarioId]);
            resp(200, ['ok'=>true, 'foto_url'=>$fotoUrl]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    // ── POST multipart: subir foto de perfil ─────────────────────
    case 'usuario_subir_foto':
        requireSession();
        $stmtRolCk = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
        $stmtRolCk->execute([$_SESSION['cina_usuario_id']]);
        if (!in_array((int)$stmtRolCk->fetchColumn(), [1,5,10])) resp(403, ['error'=>'Sin permisos para subir fotos']);
        $usuarioId = (int)($_POST['usuario_id'] ?? 0);
        if (!$usuarioId)           resp(400, ['error'=>'Falta usuario_id']);
        if (empty($_FILES['foto'])) resp(400, ['error'=>'Sin archivo']);
        $file    = $_FILES['foto'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (!in_array($ext, $allowed))        resp(400, ['error'=>'Tipo de imagen no permitido']);
        if ($file['size'] > 5*1024*1024)      resp(400, ['error'=>'Imagen demasiado grande (máx 5 MB)']);
        $dir = __DIR__ . '/uploads/perfil/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        // Borrar foto anterior
        try {
            $stmtFA = $pdo->prepare("SELECT foto_url FROM Usuarios WHERE UsuarioID=? LIMIT 1");
            $stmtFA->execute([$usuarioId]);
            $fA = $stmtFA->fetchColumn();
            if ($fA && strpos($fA,'uploads/perfil/')!==false && file_exists(__DIR__.'/'.$fA)) @unlink(__DIR__.'/'.$fA);
        } catch(Exception $ex){}
        $nombre   = 'perfil_' . $usuarioId . '_' . time() . '.' . $ext;
        $destino  = $dir . $nombre;
        $urlRelat = 'uploads/perfil/' . $nombre;
        if (!move_uploaded_file($file['tmp_name'], $destino)) resp(500, ['error'=>'Error al guardar la imagen']);
        try {
            try { $pdo->exec("ALTER TABLE Usuarios ADD COLUMN foto_url VARCHAR(255) NULL"); } catch(Exception $ex){}
            $pdo->prepare("UPDATE Usuarios SET foto_url=? WHERE UsuarioID=?")->execute([$urlRelat, $usuarioId]);
            resp(200, ['ok'=>true, 'foto_url'=>$urlRelat]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    // ── POST: crear rol ──────────────────────────────────────────
    case 'rol_crear':
        requireSession();
        $stmtRolCk = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
        $stmtRolCk->execute([$_SESSION['cina_usuario_id']]);
        if (!in_array((int)$stmtRolCk->fetchColumn(), [1,5,10])) resp(403, ['error'=>'Sin permisos']);
        $rolNombre = trim($body['RolNombre'] ?? '');
        $funcion   = trim($body['funcion']   ?? '');
        if (!$rolNombre) resp(400, ['error'=>'El nombre del rol es obligatorio']);
        try {
            // Verificar si existe columna funcion en Roles
            $dbNR = $pdo->query("SELECT DATABASE()")->fetchColumn();
            $colFR = $pdo->query("
                SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA='$dbNR' AND TABLE_NAME='Roles' AND LOWER(COLUMN_NAME)='funcion' LIMIT 1
            ")->fetchColumn();
            if ($colFR) {
                $pdo->prepare("INSERT INTO Roles (RolNombre, `$colFR`) VALUES (?,?)")->execute([$rolNombre, $funcion?:null]);
            } else {
                $pdo->prepare("INSERT INTO Roles (RolNombre) VALUES (?)")->execute([$rolNombre]);
            }
            resp(200, ['ok'=>true, 'id'=>(int)$pdo->lastInsertId()]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    // ── POST: editar rol ─────────────────────────────────────────
    case 'rol_editar':
        requireSession();
        $stmtRolCk = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
        $stmtRolCk->execute([$_SESSION['cina_usuario_id']]);
        if (!in_array((int)$stmtRolCk->fetchColumn(), [1,5,10])) resp(403, ['error'=>'Sin permisos']);
        $rolId     = (int)($body['RolID']    ?? 0);
        $rolNombre = trim($body['RolNombre'] ?? '');
        $funcion   = trim($body['funcion']   ?? '');
        if (!$rolId || !$rolNombre) resp(400, ['error'=>'Faltan datos']);
        try {
            $dbNR2 = $pdo->query("SELECT DATABASE()")->fetchColumn();
            $colFR2 = $pdo->query("
                SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA='$dbNR2' AND TABLE_NAME='Roles' AND LOWER(COLUMN_NAME)='funcion' LIMIT 1
            ")->fetchColumn();
            if ($colFR2) {
                $pdo->prepare("UPDATE Roles SET RolNombre=?, `$colFR2`=? WHERE RolID=?")->execute([$rolNombre, $funcion?:null, $rolId]);
            } else {
                $pdo->prepare("UPDATE Roles SET RolNombre=? WHERE RolID=?")->execute([$rolNombre, $rolId]);
            }
            resp(200, ['ok'=>true]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    // ── GET: permisos actuales de un rol ─────────────────────────
    case 'rol_permisos':
        requireSession();
        $rolId = (int)($_GET['rol_id'] ?? 0);
        if (!$rolId) resp(400, ['error'=>'Falta rol_id']);
        try {
            // Asegurar columnas puede_ver y puede_editar existen
            try {
                $pdo->exec("ALTER TABLE rol_permisos ADD COLUMN puede_ver TINYINT(1) NOT NULL DEFAULT 1");
            } catch (Exception $ex) {}
            try {
                $pdo->exec("ALTER TABLE rol_permisos ADD COLUMN puede_editar TINYINT(1) NOT NULL DEFAULT 0");
            } catch (Exception $ex) {}

            $modulos = []; $modulos_editar = [];
            $stmt = $pdo->prepare("SELECT modulo, puede_ver, puede_editar FROM rol_permisos WHERE rol_id=?");
            $stmt->execute([$rolId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ($row['puede_ver'])    $modulos[]        = $row['modulo'];
                if ($row['puede_editar']) $modulos_editar[] = $row['modulo'];
            }

            // Permisos extra
            $extra = [];
            try {
                $stmtE = $pdo->prepare("SELECT permiso, valor FROM rol_permisos_extra WHERE rol_id=?");
                $stmtE->execute([$rolId]);
                foreach ($stmtE->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $extra[$row['permiso']] = (bool)$row['valor'];
                }
            } catch (Exception $ex) {}

            resp(200, [
                'rol_id'         => $rolId,
                'modulos'        => $modulos,
                'modulos_editar' => $modulos_editar,
                'extra'          => $extra,
            ]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    // ── POST: guardar permisos de módulos de un rol ──────────────
    case 'rol_guardar_permisos':
        requireSession();
        $stmtRolCk = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
        $stmtRolCk->execute([$_SESSION['cina_usuario_id']]);
        if (!in_array((int)$stmtRolCk->fetchColumn(), [1,5,10])) resp(403, ['error'=>'Sin permisos']);
        $rolId          = (int)($body['RolID']           ?? 0);
        $modulos        = $body['modulos']               ?? [];
        $modulos_editar = $body['modulos_editar']        ?? [];
        $extra          = $body['extra']                 ?? [];
        if (!$rolId) resp(400, ['error'=>'Falta RolID']);

        $sanitize = function($arr) {
            return array_values(array_unique(array_filter(array_map(function($m){
                return preg_replace('/[^a-z0-9_]/','',strtolower(trim($m)));
            }, is_array($arr) ? $arr : []))));
        };
        $modulos        = $sanitize($modulos);
        $modulos_editar = $sanitize($modulos_editar);
        $todos          = array_unique(array_merge($modulos, $modulos_editar));

        try {
            // Asegurar tabla con columnas correctas
            $pdo->exec("CREATE TABLE IF NOT EXISTS rol_permisos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                rol_id INT NOT NULL, modulo VARCHAR(60) NOT NULL,
                activo TINYINT(1) NOT NULL DEFAULT 1,
                puede_ver TINYINT(1) NOT NULL DEFAULT 1,
                puede_editar TINYINT(1) NOT NULL DEFAULT 0,
                UNIQUE KEY uk_rol_modulo (rol_id, modulo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            try { $pdo->exec("ALTER TABLE rol_permisos ADD COLUMN puede_ver TINYINT(1) NOT NULL DEFAULT 1"); } catch(Exception $ex){}
            try { $pdo->exec("ALTER TABLE rol_permisos ADD COLUMN puede_editar TINYINT(1) NOT NULL DEFAULT 0"); } catch(Exception $ex){}

            // Asegurar tabla extra
            $pdo->exec("CREATE TABLE IF NOT EXISTS rol_permisos_extra (
                id INT AUTO_INCREMENT PRIMARY KEY,
                rol_id INT NOT NULL, permiso VARCHAR(60) NOT NULL, valor TINYINT(1) NOT NULL DEFAULT 0,
                UNIQUE KEY uk_rol_permiso (rol_id, permiso)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->beginTransaction();

            // Guardar módulos con puede_ver / puede_editar
            $pdo->prepare("DELETE FROM rol_permisos WHERE rol_id=?")->execute([$rolId]);
            if (!empty($todos)) {
                $stmtIns = $pdo->prepare("INSERT INTO rol_permisos (rol_id,modulo,activo,puede_ver,puede_editar) VALUES (?,?,1,?,?)");
                foreach ($todos as $m) {
                    if (!$m) continue;
                    $ver  = in_array($m, $modulos)        ? 1 : 0;
                    $edit = in_array($m, $modulos_editar) ? 1 : 0;
                    $stmtIns->execute([$rolId, $m, $ver, $edit]);
                }
            }

            // Guardar permisos extra (pañol, compras, cliente_portal, etc.)
            if (!empty($extra) && is_array($extra)) {
                $stmtE = $pdo->prepare("INSERT INTO rol_permisos_extra (rol_id,permiso,valor)
                    VALUES (?,?,?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)");
                foreach ($extra as $permiso => $valor) {
                    $permiso = preg_replace('/[^a-z0-9_]/','',strtolower(trim($permiso)));
                    if ($permiso) $stmtE->execute([$rolId, $permiso, $valor ? 1 : 0]);
                }
            }

            $pdo->commit();
            resp(200, ['ok'=>true, 'rol_id'=>$rolId, 'modulos_guardados'=>count($todos), 'extra_guardados'=>count($extra)]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            resp(500, ['error'=>$e->getMessage()]);
        }
        break;

    // ── Grupos y clientes para asignación a usuarios ────────────
    case 'grupos_clientes_lista':
        requireSession();
        try {
            $grupos = $pdo->query("
                SELECT GrupoID, Nombre FROM CLIENTEGRUPO WHERE Activo = 1 ORDER BY Nombre
            ")->fetchAll();

            $rows = $pdo->query("
                SELECT ClienteID, ClienteNombre, GrupoID FROM CLIENTES WHERE Activo = 1 ORDER BY ClienteNombre
            ")->fetchAll();

            $clientesPorGrupo = [];
            $clientesSinGrupo = [];
            foreach ($rows as $c) {
                if ($c['GrupoID']) {
                    $clientesPorGrupo[$c['GrupoID']][] = $c;
                } else {
                    $clientesSinGrupo[] = $c;
                }
            }
            if (!empty($clientesSinGrupo)) {
                $grupos[] = ['GrupoID' => 0, 'Nombre' => 'Clientes sin grupo'];
                $clientesPorGrupo[0] = $clientesSinGrupo;
            }

            resp(200, ['grupos' => $grupos, 'clientes_por_grupo' => $clientesPorGrupo]);
        } catch (Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    case 'usuario_clientes':
        requireSession();
        try {
            $uid = isset($_GET['usuario_id']) ? intval($_GET['usuario_id']) : (int)$_SESSION['cina_usuario_id'];
            if (!$uid) resp(400, ['error' => 'usuario_id requerido']);

            // Asegurar tabla
            $pdo->exec("CREATE TABLE IF NOT EXISTS usuario_cliente (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT UNSIGNED NOT NULL,
                cliente_id INT UNSIGNED NOT NULL,
                UNIQUE KEY uk_uc (usuario_id, cliente_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $stmt = $pdo->prepare("SELECT cliente_id FROM usuario_cliente WHERE usuario_id = ? ORDER BY cliente_id");
            $stmt->execute([$uid]);
            $ids = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'cliente_id'));

            resp(200, ['usuario_id' => $uid, 'cliente_ids' => $ids, 'total' => count($ids)]);
        } catch (Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    case 'usuario_guardar_clientes':
        requireSession();
        $stmtRolCheck = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
        $stmtRolCheck->execute([$_SESSION['cina_usuario_id']]);
        $rolActual = (int)$stmtRolCheck->fetchColumn();
        if (!in_array($rolActual, [1, 5, 10])) resp(403, ['error' => 'Sin permisos']);

        $uid        = (int)($body['usuario_id']  ?? 0);
        $clienteIds = $body['cliente_ids']        ?? [];
        if (!$uid) resp(400, ['error' => 'usuario_id requerido']);
        if (!is_array($clienteIds)) resp(400, ['error' => 'cliente_ids debe ser un array']);

        $clienteIds = array_values(array_unique(array_filter(array_map('intval', $clienteIds))));

        try {
            // Asegurar tabla
            $pdo->exec("CREATE TABLE IF NOT EXISTS usuario_cliente (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT UNSIGNED NOT NULL,
                cliente_id INT UNSIGNED NOT NULL,
                UNIQUE KEY uk_uc (usuario_id, cliente_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM usuario_cliente WHERE usuario_id = ?")->execute([$uid]);
            if (!empty($clienteIds)) {
                $stmtIns = $pdo->prepare("INSERT IGNORE INTO usuario_cliente (usuario_id, cliente_id) VALUES (?, ?)");
                foreach ($clienteIds as $cid) { $stmtIns->execute([$uid, $cid]); }
            }
            $pdo->commit();
            resp(200, ['ok' => true, 'usuario_id' => $uid, 'clientes_guardados' => count($clienteIds)]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    case 'usuario_guardar_proveedor':
        requireSession();
        $b = json_input();
        $uid = intval($b['usuario_id'] ?? 0);
        $provId = intval($b['proveedor_id'] ?? 0);
        if (!$uid) resp(400, ['error'=>'Falta usuario_id']);
        try {
            // Asegurar columna proveedor_id en Usuarios
            try { $pdo->exec("ALTER TABLE Usuarios ADD COLUMN proveedor_id SMALLINT UNSIGNED DEFAULT NULL"); } catch(Exception $e) {}
            $pdo->prepare("UPDATE Usuarios SET proveedor_id=? WHERE UsuarioID=?")->execute([$provId?:null, $uid]);
            resp(200, ['ok'=>true]);
        } catch(Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    // También necesitamos devolver proveedor_id al obtener info de usuario
    case 'usuario_proveedor':
        requireSession();
        $uid = intval($_GET['usuario_id'] ?? $_SESSION['cina_usuario_id']);
        try {
            try { $pdo->exec("ALTER TABLE Usuarios ADD COLUMN proveedor_id SMALLINT UNSIGNED DEFAULT NULL"); } catch(Exception $e) {}
            $st = $pdo->prepare("SELECT proveedor_id FROM Usuarios WHERE UsuarioID=?");
            $st->execute([$uid]);
            resp(200, ['ok'=>true, 'proveedor_id'=>$st->fetchColumn()?:null]);
        } catch(Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    // ── Gestión de facturas emitidas ─────────────────────────────
    // ═══════════════════════════════════════════════════════════════
    //  MÓDULO RRHH: Organigrama + Evaluaciones de Desempeño
    // ═══════════════════════════════════════════════════════════════

    case 'rrhh_organigrama':
        requireSession();
        try {
            // Detectar tabla de empleados
            $dbN = $pdo->query("SELECT DATABASE()")->fetchColumn();
            $tieneE2 = (bool)$pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='$dbN' AND TABLE_NAME='persona_empleado2'")->fetchColumn();
            $tablaEmp = $tieneE2 ? 'persona_empleado2' : 'persona_empleado';

            // Departamentos con responsable
            $deptos = $pdo->query("
                SELECT d.departamento_id, d.nombre, d.responsable_departamento,
                       CONCAT(pr.apellido,' ',pr.nombre) AS responsable_nombre
                FROM departamento d
                LEFT JOIN persona pr ON pr.persona_id = d.responsable_departamento
                WHERE d.activo = 1
                ORDER BY d.nombre
            ")->fetchAll(PDO::FETCH_ASSOC);

            // Personas activas con departamento
            $personas = $pdo->query("
                SELECT p.persona_id, p.nombre, p.apellido, p.activo, p.numero_documento,
                       pe.legajo, pe.departamento_id, pe.fecha_ingreso,
                       d.nombre AS departamento_nombre,
                       d.responsable_departamento,
                       u.UsuarioNombre AS usuario
                FROM persona p
                LEFT JOIN $tablaEmp pe ON pe.persona_id = p.persona_id
                LEFT JOIN departamento d ON d.departamento_id = pe.departamento_id
                LEFT JOIN Usuarios u ON u.persona_id = p.persona_id
                WHERE p.activo > 0
                ORDER BY d.nombre, p.apellido, p.nombre
            ")->fetchAll(PDO::FETCH_ASSOC);

            resp(200, ['ok'=>true, 'departamentos'=>$deptos, 'personas'=>$personas]);
        } catch(Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'rrhh_evaluacion_guardar':
        requireSession();
        $b = json_input();
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS evaluacion_desempeno (
                id INT AUTO_INCREMENT PRIMARY KEY,
                persona_id SMALLINT UNSIGNED NOT NULL,
                evaluador_id SMALLINT UNSIGNED DEFAULT NULL,
                tipo ENUM('auto','jefe') NOT NULL,
                periodo VARCHAR(20),
                puntaje TINYINT UNSIGNED,
                observaciones TEXT,
                created_at DATETIME DEFAULT NOW(),
                INDEX idx_persona (persona_id), INDEX idx_periodo (periodo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->prepare("INSERT INTO evaluacion_desempeno (persona_id, evaluador_id, tipo, periodo, puntaje, observaciones) VALUES (?,?,?,?,?,?)")
                ->execute([
                    intval($b['persona_id']??0),
                    intval($b['evaluador_id']??0)?:null,
                    $b['tipo']??'auto',
                    $b['periodo']??date('Y'),
                    intval($b['puntaje']??0),
                    trim($b['observaciones']??'')
                ]);
            resp(200, ['ok'=>true, 'id'=>(int)$pdo->lastInsertId()]);
        } catch(Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'rrhh_evaluaciones_lista':
        requireSession();
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS evaluacion_desempeno (
                id INT AUTO_INCREMENT PRIMARY KEY, persona_id SMALLINT UNSIGNED NOT NULL,
                evaluador_id SMALLINT UNSIGNED DEFAULT NULL, tipo ENUM('auto','jefe') NOT NULL,
                periodo VARCHAR(20), puntaje TINYINT UNSIGNED, observaciones TEXT,
                created_at DATETIME DEFAULT NOW(), INDEX idx_persona (persona_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $personaId = intval($_GET['persona_id']??0);
            $periodo = $_GET['periodo'] ?? '';
            $where = '1=1'; $params = [];
            if ($personaId) { $where .= ' AND e.persona_id=?'; $params[] = $personaId; }
            if ($periodo) { $where .= ' AND e.periodo=?'; $params[] = $periodo; }

            $st = $pdo->prepare("
                SELECT e.*, CONCAT(p.apellido,' ',p.nombre) AS persona_nombre,
                       CONCAT(ev.apellido,' ',ev.nombre) AS evaluador_nombre,
                       pe.legajo, d.nombre AS departamento
                FROM evaluacion_desempeno e
                LEFT JOIN persona p ON p.persona_id=e.persona_id
                LEFT JOIN persona ev ON ev.persona_id=e.evaluador_id
                LEFT JOIN persona_empleado pe ON pe.persona_id=e.persona_id
                LEFT JOIN departamento d ON d.departamento_id=pe.departamento_id
                WHERE $where ORDER BY e.created_at DESC LIMIT 200
            ");
            $st->execute($params);
            resp(200, ['ok'=>true, 'evaluaciones'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
        } catch(Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'rrhh_mi_evaluacion':
        requireSession();
        try {
            // Obtener persona_id del usuario logueado
            $stU = $pdo->prepare("SELECT persona_id FROM Usuarios WHERE UsuarioID=?");
            $stU->execute([$_SESSION['cina_usuario_id']]);
            $miPersonaId = (int)$stU->fetchColumn();
            if (!$miPersonaId) resp(200, ['ok'=>true, 'persona'=>null]);

            $dbN2 = $pdo->query("SELECT DATABASE()")->fetchColumn();
            $tieneE2b = (bool)$pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='$dbN2' AND TABLE_NAME='persona_empleado2'")->fetchColumn();
            $tablaE = $tieneE2b ? 'persona_empleado2' : 'persona_empleado';

            $st = $pdo->prepare("
                SELECT p.persona_id, p.nombre, p.apellido, p.numero_documento, p.fecha_nacimiento,
                       pe.legajo, pe.fecha_ingreso, pe.departamento_id,
                       d.nombre AS departamento, d.responsable_departamento,
                       CONCAT(jr.apellido,' ',jr.nombre) AS jefe_nombre
                FROM persona p
                LEFT JOIN $tablaE pe ON pe.persona_id=p.persona_id
                LEFT JOIN departamento d ON d.departamento_id=pe.departamento_id
                LEFT JOIN persona jr ON jr.persona_id=d.responsable_departamento
                WHERE p.persona_id=?
            ");
            $st->execute([$miPersonaId]);
            $persona = $st->fetch(PDO::FETCH_ASSOC);

            // Subordinados (si es jefe de algún departamento)
            $subordinados = [];
            if ($miPersonaId) {
                $stSub = $pdo->prepare("
                    SELECT p.persona_id, p.nombre, p.apellido, pe.legajo, pe.fecha_ingreso,
                           d.nombre AS departamento
                    FROM persona p
                    INNER JOIN $tablaE pe ON pe.persona_id=p.persona_id
                    INNER JOIN departamento d ON d.departamento_id=pe.departamento_id AND d.responsable_departamento=?
                    WHERE p.activo=1 AND p.persona_id!=?
                    ORDER BY p.apellido
                ");
                $stSub->execute([$miPersonaId, $miPersonaId]);
                $subordinados = $stSub->fetchAll(PDO::FETCH_ASSOC);
            }

            resp(200, ['ok'=>true, 'persona'=>$persona, 'subordinados'=>$subordinados, 'persona_id'=>$miPersonaId]);
        } catch(Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    // ═══════════════════════════════════════════════════════════════
    //  CAMPUS VIRTUAL
    // ═══════════════════════════════════════════════════════════════
    case 'campus_cursos':
    case 'campus_curso_detalle':
    case 'campus_curso_crear':
    case 'campus_curso_subir_video':
    case 'campus_curso_subir_pdf':
    case 'campus_completar':
    case 'campus_reporte':
        requireSession();
        $pdo->exec("CREATE TABLE IF NOT EXISTS campus_curso (
            id INT AUTO_INCREMENT PRIMARY KEY, titulo VARCHAR(200) NOT NULL, descripcion TEXT,
            video_url VARCHAR(500), video_archivo VARCHAR(500), material_pdf VARCHAR(500),
            rol_ids VARCHAR(200), activo TINYINT(1) DEFAULT 1, orden INT DEFAULT 0,
            created_by INT, created_at DATETIME DEFAULT NOW()
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        try { $pdo->exec("ALTER TABLE campus_curso ADD COLUMN material_pdf VARCHAR(500)"); } catch(Exception $e) {}
        $pdo->exec("CREATE TABLE IF NOT EXISTS campus_pregunta (
            id INT AUTO_INCREMENT PRIMARY KEY, curso_id INT NOT NULL, pregunta TEXT NOT NULL,
            opciones TEXT, respuesta_correcta TINYINT DEFAULT 0, orden INT DEFAULT 0,
            INDEX idx_curso (curso_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS campus_completado (
            id INT AUTO_INCREMENT PRIMARY KEY, curso_id INT NOT NULL, persona_id SMALLINT UNSIGNED NOT NULL,
            puntaje TINYINT DEFAULT 0, aprobado TINYINT(1) DEFAULT 0,
            completed_at DATETIME DEFAULT NOW(),
            INDEX idx_curso (curso_id), INDEX idx_persona (persona_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if ($action === 'campus_cursos') {
            $rolId = intval($_GET['rol_id'] ?? $_SESSION['cina_rol_id'] ?? 0);
            try {
                $rows = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM campus_pregunta WHERE curso_id=c.id) AS total_preguntas FROM campus_curso c WHERE c.activo=1 ORDER BY c.orden, c.id")->fetchAll(PDO::FETCH_ASSOC);
                // Filtrar por rol si no es admin
                if ($rolId && !in_array($rolId, [1,5,10])) {
                    $rows = array_values(array_filter($rows, function($c) use ($rolId) {
                        $rids = $c['rol_ids'] ? array_map('intval', explode(',', $c['rol_ids'])) : [];
                        return empty($rids) || in_array($rolId, $rids);
                    }));
                }
                // Agregar estado de completado para el usuario actual
                $stU = $pdo->prepare("SELECT persona_id FROM Usuarios WHERE UsuarioID=?");
                $stU->execute([$_SESSION['cina_usuario_id']]); $miPid = (int)$stU->fetchColumn();
                $stComp = $pdo->prepare("SELECT puntaje, aprobado, completed_at FROM campus_completado WHERE curso_id=? AND persona_id=? ORDER BY id DESC LIMIT 1");
                foreach ($rows as &$r) {
                    $stComp->execute([$r['id'], $miPid]); $comp = $stComp->fetch(PDO::FETCH_ASSOC);
                    $r['completado'] = $comp ? true : false;
                    $r['mi_puntaje'] = $comp['puntaje'] ?? null;
                    $r['mi_aprobado'] = $comp['aprobado'] ?? null;
                }
                unset($r);
                resp(200, ['ok'=>true, 'cursos'=>$rows]);
            } catch(Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        }
        elseif ($action === 'campus_curso_detalle') {
            $id = intval($_GET['id']??0); if (!$id) resp(400, ['error'=>'Falta id']);
            try {
                $st = $pdo->prepare("SELECT * FROM campus_curso WHERE id=?"); $st->execute([$id]);
                $curso = $st->fetch(PDO::FETCH_ASSOC); if (!$curso) resp(404, ['error'=>'Curso no encontrado']);
                $sp = $pdo->prepare("SELECT * FROM campus_pregunta WHERE curso_id=? ORDER BY orden, id"); $sp->execute([$id]);
                $curso['preguntas'] = $sp->fetchAll(PDO::FETCH_ASSOC);
                foreach ($curso['preguntas'] as &$p) { $p['opciones'] = json_decode($p['opciones'], true) ?: []; }
                unset($p);
                resp(200, ['ok'=>true, 'curso'=>$curso]);
            } catch(Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        }
        elseif ($action === 'campus_curso_crear') {
            $b = json_input();
            try {
                $pdo->beginTransaction();
                $pdo->prepare("INSERT INTO campus_curso (titulo, descripcion, video_url, video_archivo, rol_ids, activo, orden, created_by) VALUES (?,?,?,?,?,1,?,?)")
                    ->execute([trim($b['titulo']??''), trim($b['descripcion']??''), trim($b['video_url']??''), trim($b['video_archivo']??''),
                               is_array($b['rol_ids']??null) ? implode(',', $b['rol_ids']) : ($b['rol_ids']??''),
                               intval($b['orden']??0), (int)$_SESSION['cina_usuario_id']]);
                $cursoId = (int)$pdo->lastInsertId();
                $stP = $pdo->prepare("INSERT INTO campus_pregunta (curso_id, pregunta, opciones, respuesta_correcta, orden) VALUES (?,?,?,?,?)");
                foreach (($b['preguntas']??[]) as $i => $preg) {
                    $stP->execute([$cursoId, $preg['pregunta']??'', json_encode($preg['opciones']??[]), intval($preg['respuesta_correcta']??0), $i]);
                }
                $pdo->commit();
                resp(200, ['ok'=>true, 'id'=>$cursoId]);
            } catch(Exception $e) { if($pdo->inTransaction())$pdo->rollBack(); resp(500, ['error'=>$e->getMessage()]); }
        }
        elseif ($action === 'campus_curso_subir_video') {
            $id = intval($_POST['curso_id']??0); if (!$id) resp(400, ['error'=>'Falta curso_id']);
            if (empty($_FILES['video'])) resp(400, ['error'=>'No se recibió archivo']);
            $file = $_FILES['video'];
            if ($file['size'] > 500*1024*1024) resp(400, ['error'=>'Archivo mayor a 500 MB']);
            try {
                $dir = __DIR__ . '/uploads/campus'; if (!is_dir($dir)) mkdir($dir, 0755, true);
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'mp4');
                $nombre = 'curso_'.$id.'_'.time().'.'.$ext;
                if (!move_uploaded_file($file['tmp_name'], $dir.'/'.$nombre)) resp(500, ['error'=>'Error al guardar']);
                $url = '/uploads/campus/'.$nombre;
                $pdo->prepare("UPDATE campus_curso SET video_archivo=? WHERE id=?")->execute([$url, $id]);
                resp(200, ['ok'=>true, 'url'=>$url]);
            } catch(Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        }
        elseif ($action === 'campus_curso_subir_pdf') {
            $id = intval($_POST['curso_id']??0); if (!$id) resp(400, ['error'=>'Falta curso_id']);
            if (empty($_FILES['pdf'])) resp(400, ['error'=>'No se recibió archivo']);
            $file = $_FILES['pdf'];
            if ($file['size'] > 50*1024*1024) resp(400, ['error'=>'Archivo mayor a 50 MB']);
            try {
                $dir = __DIR__ . '/uploads/campus'; if (!is_dir($dir)) mkdir($dir, 0755, true);
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'pdf');
                $nombre = 'material_'.$id.'_'.time().'.'.$ext;
                if (!move_uploaded_file($file['tmp_name'], $dir.'/'.$nombre)) resp(500, ['error'=>'Error al guardar']);
                $url = '/uploads/campus/'.$nombre;
                $pdo->prepare("UPDATE campus_curso SET material_pdf=? WHERE id=?")->execute([$url, $id]);
                resp(200, ['ok'=>true, 'url'=>$url]);
            } catch(Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        }
        elseif ($action === 'campus_completar') {
            $b = json_input();
            $cursoId = intval($b['curso_id']??0);
            $respuestas = $b['respuestas']??[];
            try {
                $stU = $pdo->prepare("SELECT persona_id FROM Usuarios WHERE UsuarioID=?");
                $stU->execute([$_SESSION['cina_usuario_id']]); $pid = (int)$stU->fetchColumn();
                // Obtener preguntas
                $st = $pdo->prepare("SELECT id, respuesta_correcta FROM campus_pregunta WHERE curso_id=?"); $st->execute([$cursoId]);
                $pregs = $st->fetchAll(PDO::FETCH_ASSOC);
                $correctas = 0;
                foreach ($pregs as $p) {
                    $resp = $respuestas[$p['id']] ?? -1;
                    if ((int)$resp === (int)$p['respuesta_correcta']) $correctas++;
                }
                $total = count($pregs); $puntaje = $total > 0 ? round($correctas/$total*10) : 10;
                $aprobado = $puntaje >= 6 ? 1 : 0;
                $pdo->prepare("INSERT INTO campus_completado (curso_id, persona_id, puntaje, aprobado) VALUES (?,?,?,?)")
                    ->execute([$cursoId, $pid, $puntaje, $aprobado]);
                resp(200, ['ok'=>true, 'puntaje'=>$puntaje, 'aprobado'=>$aprobado, 'correctas'=>$correctas, 'total'=>$total]);
            } catch(Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        }
        elseif ($action === 'campus_reporte') {
            try {
                $rows = $pdo->query("
                    SELECT cc.id AS curso_id, cc.titulo, comp.persona_id, comp.puntaje, comp.aprobado, comp.completed_at,
                           CONCAT(p.apellido,' ',p.nombre) AS persona_nombre, pe.legajo
                    FROM campus_completado comp
                    INNER JOIN campus_curso cc ON cc.id=comp.curso_id
                    LEFT JOIN persona p ON p.persona_id=comp.persona_id
                    LEFT JOIN persona_empleado pe ON pe.persona_id=comp.persona_id
                    ORDER BY comp.completed_at DESC LIMIT 500
                ")->fetchAll(PDO::FETCH_ASSOC);
                resp(200, ['ok'=>true, 'completados'=>$rows]);
            } catch(Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        }
        else { resp(400, ['error'=>'Acción campus no reconocida']); }
        break;

    // ═══════════════════════════════════════════════════════════════
    //  PR-44: Registro de No Conformidades
    // ═══════════════════════════════════════════════════════════════
    case 'pr44_lista':
        requireSession();
        try {
            $where = '1=1'; $params = [];
            if (!empty($_GET['estado'])) { $where .= ' AND r.estado=?'; $params[] = $_GET['estado']; }
            if (!empty($_GET['departamento_id'])) { $where .= ' AND r.departamento_id=?'; $params[] = intval($_GET['departamento_id']); }
            if (!empty($_GET['tipo'])) { $where .= ' AND r.tipo_registro_id=?'; $params[] = intval($_GET['tipo']); }
            $st = $pdo->prepare("
                SELECT r.*, CONCAT(p.apellido,' ',p.nombre) AS persona_nombre,
                       d.nombre AS departamento_nombre, rt.nombre AS tipo_nombre,
                       ro.descripcion AS origen_nombre
                FROM registro r
                LEFT JOIN persona p ON p.persona_id=r.persona_id
                LEFT JOIN departamento d ON d.departamento_id=r.departamento_id
                LEFT JOIN registro_tipo rt ON rt.registro_tipo_id=r.tipo_registro_id
                LEFT JOIN registro_origen ro ON ro.origen_id=r.origen_id
                WHERE $where ORDER BY r.registro_id DESC LIMIT 200
            ");
            $st->execute($params);
            // Catalogos
            $tipos = $pdo->query("SELECT registro_tipo_id AS id, nombre FROM registro_tipo WHERE activo=1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
            $origenes = $pdo->query("SELECT origen_id AS id, descripcion AS nombre FROM registro_origen WHERE activo=1 ORDER BY descripcion")->fetchAll(PDO::FETCH_ASSOC);
            $deptos = $pdo->query("SELECT departamento_id AS id, nombre FROM departamento WHERE activo=1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
            resp(200, ['ok'=>true, 'registros'=>$st->fetchAll(PDO::FETCH_ASSOC), 'tipos'=>$tipos, 'origenes'=>$origenes, 'departamentos'=>$deptos]);
        } catch(Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'pr44_crear':
    case 'pr44_editar':
        requireSession();
        $b = json_input();
        try {
            if ($action === 'pr44_crear') {
                $maxNum = (int)$pdo->query("SELECT COALESCE(MAX(numero),0) FROM registro")->fetchColumn() + 1;
                $pdo->prepare("INSERT INTO registro (numero, fecha_registro, departamento_id, persona_id, tipo_registro_id, origen_id, titulo, descripcion, causa_inmediata, accion_inmediata, accion_inmediata_fecha, accion_correctiva, accion_correctiva_fecha, estado, usuario_id, created_at, updated_at) VALUES (?,CURDATE(),?,?,?,?,?,?,?,?,?,?,?,'abierto',?,NOW(),NOW())")
                    ->execute([$maxNum, intval($b['departamento_id']??0), intval($b['persona_id']??0), intval($b['tipo_registro_id']??0), intval($b['origen_id']??0), trim($b['titulo']??''), trim($b['descripcion']??''), trim($b['causa_inmediata']??''), trim($b['accion_inmediata']??''), $b['accion_inmediata_fecha']??null, trim($b['accion_correctiva']??''), $b['accion_correctiva_fecha']??null, (int)$_SESSION['cina_usuario_id']]);
                resp(200, ['ok'=>true, 'id'=>(int)$pdo->lastInsertId(), 'numero'=>$maxNum]);
            } else {
                $id = intval($b['id']??0); if (!$id) resp(400, ['error'=>'Falta id']);
                $pdo->prepare("UPDATE registro SET departamento_id=?, persona_id=?, tipo_registro_id=?, origen_id=?, titulo=?, descripcion=?, causa_inmediata=?, accion_inmediata=?, accion_inmediata_fecha=?, accion_correctiva=?, accion_correctiva_fecha=?, estado=?, evaluacion_metodo=?, evaluacion=?, evaluacion_fecha=?, updated_at=NOW() WHERE registro_id=?")
                    ->execute([intval($b['departamento_id']??0), intval($b['persona_id']??0), intval($b['tipo_registro_id']??0), intval($b['origen_id']??0), trim($b['titulo']??''), trim($b['descripcion']??''), trim($b['causa_inmediata']??''), trim($b['accion_inmediata']??''), $b['accion_inmediata_fecha']??null, trim($b['accion_correctiva']??''), $b['accion_correctiva_fecha']??null, $b['estado']??'abierto', trim($b['evaluacion_metodo']??''), trim($b['evaluacion']??''), $b['evaluacion_fecha']??null, $id]);
                resp(200, ['ok'=>true]);
            }
        } catch(Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'pr44_ver':
        requireSession();
        $id = intval($_GET['id']??0); if (!$id) resp(400, ['error'=>'Falta id']);
        try {
            $st = $pdo->prepare("SELECT r.*, CONCAT(p.apellido,' ',p.nombre) AS persona_nombre, d.nombre AS departamento_nombre, rt.nombre AS tipo_nombre, ro.descripcion AS origen_nombre FROM registro r LEFT JOIN persona p ON p.persona_id=r.persona_id LEFT JOIN departamento d ON d.departamento_id=r.departamento_id LEFT JOIN registro_tipo rt ON rt.registro_tipo_id=r.tipo_registro_id LEFT JOIN registro_origen ro ON ro.origen_id=r.origen_id WHERE r.registro_id=?");
            $st->execute([$id]); $reg = $st->fetch(PDO::FETCH_ASSOC);
            if (!$reg) resp(404, ['error'=>'Registro no encontrado']);
            resp(200, ['ok'=>true, 'registro'=>$reg]);
        } catch(Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    // ═══════════════════════════════════════════════════════════════
    //  SGC: Gestor de Documentos
    // ═══════════════════════════════════════════════════════════════
    case 'sgc_documentos_lista':
        requireSession();
        try {
            $rows = $pdo->query("
                SELECT d.documento_id, d.codigo, d.nombre, d.fecha_alta, d.fecha_baja,
                       (SELECT MAX(dr.revision) FROM documento_revision dr WHERE dr.documento_id=d.documento_id) AS ultima_revision,
                       (SELECT dr.fecha_creacion FROM documento_revision dr WHERE dr.documento_id=d.documento_id ORDER BY dr.revision DESC LIMIT 1) AS fecha_ultima_revision,
                       (SELECT dr.descripcion FROM documento_revision dr WHERE dr.documento_id=d.documento_id ORDER BY dr.revision DESC LIMIT 1) AS desc_ultima_revision
                FROM documento d
                WHERE d.fecha_baja IS NULL
                ORDER BY d.codigo, d.nombre
            ")->fetchAll(PDO::FETCH_ASSOC);
            resp(200, ['ok'=>true, 'documentos'=>$rows]);
        } catch(Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'sgc_documento_revisiones':
        requireSession();
        $docId = intval($_GET['documento_id']??0); if (!$docId) resp(400, ['error'=>'Falta documento_id']);
        try {
            $doc = $pdo->prepare("SELECT * FROM documento WHERE documento_id=?"); $doc->execute([$docId]);
            $docData = $doc->fetch(PDO::FETCH_ASSOC);
            $revs = $pdo->prepare("SELECT * FROM documento_revision WHERE documento_id=? ORDER BY revision DESC"); $revs->execute([$docId]);
            resp(200, ['ok'=>true, 'documento'=>$docData, 'revisiones'=>$revs->fetchAll(PDO::FETCH_ASSOC)]);
        } catch(Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'sgc_documento_crear':
        requireSession();
        $b = json_input();
        try {
            $pdo->prepare("INSERT INTO documento (codigo, nombre, fecha_alta, manual_id) VALUES (?,?,CURDATE(),1)")
                ->execute([trim($b['codigo']??''), trim($b['nombre']??'')]);
            resp(200, ['ok'=>true, 'id'=>(int)$pdo->lastInsertId()]);
        } catch(Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'sgc_revision_crear':
        requireSession();
        $docId = intval($_POST['documento_id']??0); if (!$docId) resp(400, ['error'=>'Falta documento_id']);
        try {
            $maxRev = (int)$pdo->prepare("SELECT COALESCE(MAX(revision),0) FROM documento_revision WHERE documento_id=?")->execute([$docId]);
            $stMax = $pdo->prepare("SELECT COALESCE(MAX(revision),0) FROM documento_revision WHERE documento_id=?");
            $stMax->execute([$docId]); $maxRev = (int)$stMax->fetchColumn() + 1;
            $archivo = null;
            if (!empty($_FILES['archivo'])) {
                $dir = __DIR__ . '/uploads/sgc'; if (!is_dir($dir)) mkdir($dir, 0755, true);
                $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
                $nombre = 'doc_'.$docId.'_rev'.$maxRev.'_'.time().'.'.$ext;
                move_uploaded_file($_FILES['archivo']['tmp_name'], $dir.'/'.$nombre);
                $archivo = '/uploads/sgc/'.$nombre;
            }
            $desc = $_POST['descripcion'] ?? '';
            $pdo->prepare("INSERT INTO documento_revision (documento_id, revision, fecha_creacion, archivo, descripcion) VALUES (?,?,NOW(),?,?)")
                ->execute([$docId, $maxRev, $archivo, $desc]);
            resp(200, ['ok'=>true, 'revision'=>$maxRev]);
        } catch(Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    //  MÓDULO INVENTARIO FÍSICO
    // ═══════════════════════════════════════════════════════════════

    case 'inventario_init_tables':
    case 'inventario_crear':
    case 'inventario_lista':
    case 'inventario_detalle':
    case 'inventario_escanear':
    case 'inventario_agregar_extra':
    case 'inventario_cerrar':
    case 'inventario_aprobar_ajuste':
        requireSession();
        // Auto-crear tablas
        $pdo->exec("CREATE TABLE IF NOT EXISTS inventario_sesion (
            id INT AUTO_INCREMENT PRIMARY KEY, cliente_id INT NOT NULL, fecha DATE NOT NULL,
            estado ENUM('abierto','cerrado','aprobado') DEFAULT 'abierto',
            creado_por INT, cerrado_por INT, aprobado_por INT,
            created_at DATETIME DEFAULT NOW(), closed_at DATETIME NULL, approved_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS inventario_item (
            id INT AUTO_INCREMENT PRIMARY KEY, sesion_id INT NOT NULL,
            CargaID INT, ProductosID INT, FechaEV SMALLINT DEFAULT 0, EstibaID DOUBLE,
            NroEstiba VARCHAR(20), producto_nombre VARCHAR(200), codigo_producto VARCHAR(50),
            cantidad_esperada INT DEFAULT 0, cantidad_contada INT DEFAULT NULL,
            es_extra TINYINT(1) DEFAULT 0, es_intervenida TINYINT(1) DEFAULT 0,
            motivo_intervencion VARCHAR(200),
            observaciones TEXT, ubicacion VARCHAR(100),
            escaneado_por INT, escaneado_at DATETIME NULL,
            INDEX idx_sesion (sesion_id), INDEX idx_estiba (EstibaID)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        try { $pdo->exec("ALTER TABLE inventario_item ADD COLUMN es_intervenida TINYINT(1) DEFAULT 0"); } catch(Exception $e) {}
        try { $pdo->exec("ALTER TABLE inventario_item ADD COLUMN motivo_intervencion VARCHAR(200)"); } catch(Exception $e) {}

        if ($action === 'inventario_crear') {
            $b = json_input();
            $clienteId = intval($b['cliente_id'] ?? 0);
            if (!$clienteId) resp(400, ['error'=>'Falta cliente_id']);
            try {
                $pdo->beginTransaction();
                $pdo->prepare("INSERT INTO inventario_sesion (cliente_id, fecha, creado_por, created_at) VALUES (?,CURDATE(),?,NOW())")
                    ->execute([$clienteId, (int)$_SESSION['cina_usuario_id']]);
                $sesionId = (int)$pdo->lastInsertId();

                // Copiar stock del cliente
                $stStock = $pdo->prepare("
                    SELECT PCE.CargaID, PCE.ProductosID, PCE.FechaEV, PCE.EstibaID, PCE.Cantidad,
                           P.CodigoProducto, P.Descripcion1 AS producto,
                           E.NroEstiba,
                           CONCAT(COALESCE(TCA.iniciales,CONCAT('C',CAM.CamaraNumero)),' ',COALESCE(POS.Calle,''),'-',COALESCE(POS.Fila,''),'.',COALESCE(E.Nivel,'')) AS ubicacion
                    FROM ProductoCargaEstiba PCE
                    INNER JOIN Cargas Car ON Car.CargaID=PCE.CargaID
                    INNER JOIN Productos P ON P.ProductosID=PCE.ProductosID
                    INNER JOIN Estiba E ON E.EstibaID=PCE.EstibaID
                    LEFT JOIN Posiciones POS ON POS.PosicionID=E.PosicionID
                    LEFT JOIN Camaras CAM ON CAM.CamaraID=POS.CamaraID
                    LEFT JOIN TiposCamara TCA ON TCA.TiposCamaraID=CAM.TiposCamaraID
                    WHERE PCE.Cantidad > 0 AND Car.Clasificada=1 AND E.FechaBaja IS NULL AND Car.ClienteID=?
                    ORDER BY P.Descripcion1, E.NroEstiba
                ");
                $stStock->execute([$clienteId]);
                $items = $stStock->fetchAll();

                $stIns = $pdo->prepare("INSERT INTO inventario_item (sesion_id, CargaID, ProductosID, FechaEV, EstibaID, NroEstiba, producto_nombre, codigo_producto, cantidad_esperada, ubicacion, es_intervenida, motivo_intervencion) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
                foreach ($items as $it) {
                    $stIns->execute([$sesionId, $it['CargaID'], $it['ProductosID'], $it['FechaEV'], $it['EstibaID'], $it['NroEstiba'], $it['producto'], $it['CodigoProducto'], (int)$it['Cantidad'], $it['ubicacion'], 0, null]);
                }

                // Copiar estibas intervenidas del cliente
                $stInt = $pdo->prepare("
                    SELECT EI.CargaID, EI.EstibaID, EI.NroEstiba,
                           IEP.producto_id AS ProductosID, IEP.cantidad_intervenido AS Cantidad,
                           P.CodigoProducto, P.Descripcion1 AS producto,
                           IM.descripcion AS motivo,
                           CONCAT(COALESCE(TCA.iniciales,CONCAT('C',CAM.CamaraNumero)),' ',COALESCE(POS.Calle,''),'-',COALESCE(POS.Fila,''),'.',COALESCE(EI.Nivel,'')) AS ubicacion
                    FROM Estiba_intervenidas EI
                    INNER JOIN Cargas Car ON Car.CargaID=EI.CargaID
                    LEFT JOIN intervencion_estiba_producto IEP ON IEP.estiba_id=EI.EstibaID AND IEP.carga_id=EI.CargaID AND IEP.estado='I'
                    LEFT JOIN Productos P ON P.ProductosID=IEP.producto_id
                    LEFT JOIN intervencion_motivo IM ON IM.motivo_intervencion_id=EI.intervencion_motivo
                    LEFT JOIN Posiciones POS ON POS.PosicionID=EI.PosicionID
                    LEFT JOIN Camaras CAM ON CAM.CamaraID=POS.CamaraID
                    LEFT JOIN TiposCamara TCA ON TCA.TiposCamaraID=CAM.TiposCamaraID
                    WHERE EI.FechaBaja IS NULL AND Car.ClienteID=?
                    ORDER BY P.Descripcion1, EI.NroEstiba
                ");
                $stInt->execute([$clienteId]);
                $itemsInt = $stInt->fetchAll();
                $countInt = 0;
                foreach ($itemsInt as $it) {
                    $stIns->execute([$sesionId, $it['CargaID'], $it['ProductosID'], 0, $it['EstibaID'], $it['NroEstiba'], $it['producto']??'Sin producto', $it['CodigoProducto']??'', (int)($it['Cantidad']??0), $it['ubicacion'], 1, $it['motivo']]);
                    $countInt++;
                }

                $pdo->commit();
                resp(200, ['ok'=>true, 'sesion_id'=>$sesionId, 'items_copiados'=>count($items), 'intervenidas_copiadas'=>$countInt]);
            } catch(Exception $e) { if($pdo->inTransaction())$pdo->rollBack(); resp(500, ['error'=>$e->getMessage()]); }
        }

        elseif ($action === 'inventario_lista') {
            try {
                $stmt = $pdo->query("
                    SELECT s.*, CL.ClienteNombre AS cliente,
                        (SELECT COUNT(*) FROM inventario_item WHERE sesion_id=s.id) AS total_items,
                        (SELECT COUNT(*) FROM inventario_item WHERE sesion_id=s.id AND cantidad_contada IS NOT NULL) AS items_contados
                    FROM inventario_sesion s
                    LEFT JOIN CLIENTES CL ON CL.ClienteID=s.cliente_id
                    ORDER BY s.id DESC LIMIT 50
                ");
                resp(200, ['ok'=>true, 'sesiones'=>$stmt->fetchAll()]);
            } catch(Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        }

        elseif ($action === 'inventario_detalle') {
            $sesionId = intval($_GET['sesion_id'] ?? 0);
            if (!$sesionId) resp(400, ['error'=>'Falta sesion_id']);
            try {
                $stS = $pdo->prepare("SELECT s.*, CL.ClienteNombre AS cliente FROM inventario_sesion s LEFT JOIN CLIENTES CL ON CL.ClienteID=s.cliente_id WHERE s.id=?");
                $stS->execute([$sesionId]); $sesion = $stS->fetch();
                if (!$sesion) resp(404, ['error'=>'Sesión no encontrada']);

                $stI = $pdo->prepare("SELECT * FROM inventario_item WHERE sesion_id=? ORDER BY producto_nombre, NroEstiba");
                $stI->execute([$sesionId]);
                $items = $stI->fetchAll();

                // Calcular resumen
                $contados = 0; $diferencias = 0; $sobrantes = 0; $faltantes = 0;
                foreach ($items as $it) {
                    if ($it['cantidad_contada'] !== null) {
                        $contados++;
                        $diff = (int)$it['cantidad_contada'] - (int)$it['cantidad_esperada'];
                        if ($diff !== 0) $diferencias++;
                        if ($diff > 0) $sobrantes += $diff;
                        if ($diff < 0) $faltantes += abs($diff);
                    }
                }
                resp(200, ['ok'=>true, 'sesion'=>$sesion, 'items'=>$items,
                    'resumen'=>['total'=>count($items), 'contados'=>$contados, 'diferencias'=>$diferencias, 'sobrantes'=>$sobrantes, 'faltantes'=>$faltantes]]);
            } catch(Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        }

        elseif ($action === 'inventario_escanear') {
            $b = json_input();
            $sesionId = intval($b['sesion_id'] ?? 0);
            $estibaId = $b['EstibaID'] ?? null;
            $cantContada = intval($b['cantidad_contada'] ?? 0);
            $obs = trim($b['observaciones'] ?? '');
            $soloChequeo = !empty($b['solo_chequeo']);   // si es true, no registra el conteo (usado para preview)
            if (!$sesionId || $estibaId === null) resp(400, ['error'=>'Faltan datos']);
            try {
                // Buscar item por EstibaID en esta sesión (traer CargaID, NroEstiba, codigo para UI)
                $st = $pdo->prepare("SELECT id, cantidad_esperada, producto_nombre, codigo_producto, CargaID, NroEstiba, ubicacion FROM inventario_item WHERE sesion_id=? AND EstibaID=? LIMIT 1");
                $st->execute([$sesionId, $estibaId]);
                $item = $st->fetch();
                if (!$item) {
                    resp(200, ['ok'=>false, 'no_encontrado'=>true, 'EstibaID'=>$estibaId, 'mensaje'=>'Estiba no encontrada en este inventario']);
                }
                if (!$soloChequeo) {
                    $pdo->prepare("UPDATE inventario_item SET cantidad_contada=?, observaciones=?, escaneado_por=?, escaneado_at=NOW() WHERE id=?")
                        ->execute([$cantContada, $obs, (int)$_SESSION['cina_usuario_id'], $item['id']]);
                }
                $diff = $cantContada - (int)$item['cantidad_esperada'];
                resp(200, [
                    'ok'        => true,
                    'item_id'   => $item['id'],
                    'producto'  => $item['producto_nombre'],
                    'codigo'    => $item['codigo_producto'],
                    'CargaID'   => $item['CargaID'],
                    'NroEstiba' => $item['NroEstiba'],
                    'ubicacion' => $item['ubicacion'],
                    'dni'       => ($item['CargaID'] ? $item['CargaID'].'-' : '') . ($item['NroEstiba'] ?? ''),
                    'esperada'  => (int)$item['cantidad_esperada'],
                    'contada'   => $cantContada,
                    'diferencia'=> $diff,
                    'registrado'=> !$soloChequeo,
                ]);
            } catch(Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        }

        elseif ($action === 'inventario_agregar_extra') {
            $b = json_input();
            $sesionId = intval($b['sesion_id'] ?? 0);
            $estibaId = $b['EstibaID'] ?? null;
            $cantidad = intval($b['cantidad'] ?? 0);
            $producto = trim($b['producto'] ?? '');
            $nroEstiba = trim($b['NroEstiba'] ?? '');
            if (!$sesionId || !$cantidad) resp(400, ['error'=>'Faltan datos']);
            try {
                $pdo->prepare("INSERT INTO inventario_item (sesion_id, EstibaID, NroEstiba, producto_nombre, cantidad_esperada, cantidad_contada, es_extra, escaneado_por, escaneado_at) VALUES (?,?,?,?,0,?,1,?,NOW())")
                    ->execute([$sesionId, $estibaId, $nroEstiba, $producto, $cantidad, (int)$_SESSION['cina_usuario_id']]);
                resp(200, ['ok'=>true, 'item_id'=>(int)$pdo->lastInsertId()]);
            } catch(Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        }

        elseif ($action === 'inventario_cerrar') {
            $b = json_input();
            $sesionId = intval($b['sesion_id'] ?? 0);
            if (!$sesionId) resp(400, ['error'=>'Falta sesion_id']);
            try {
                $pdo->prepare("UPDATE inventario_sesion SET estado='cerrado', cerrado_por=?, closed_at=NOW() WHERE id=? AND estado='abierto'")
                    ->execute([(int)$_SESSION['cina_usuario_id'], $sesionId]);
                resp(200, ['ok'=>true, 'mensaje'=>'Inventario cerrado']);
            } catch(Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        }

        elseif ($action === 'inventario_aprobar_ajuste') {
            // Solo RolID=1 puede aprobar
            $stRol = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID=?");
            $stRol->execute([$_SESSION['cina_usuario_id']]);
            if ((int)$stRol->fetchColumn() !== 1) resp(403, ['error'=>'Solo administradores pueden aprobar ajustes']);

            $b = json_input();
            $sesionId = intval($b['sesion_id'] ?? 0);
            if (!$sesionId) resp(400, ['error'=>'Falta sesion_id']);
            try {
                $pdo->beginTransaction();
                $stItems = $pdo->prepare("SELECT * FROM inventario_item WHERE sesion_id=? AND cantidad_contada IS NOT NULL");
                $stItems->execute([$sesionId]);
                $items = $stItems->fetchAll();

                $ajustados = 0;
                foreach ($items as $it) {
                    $esperada = (int)$it['cantidad_esperada'];
                    $contada  = (int)$it['cantidad_contada'];
                    $diff = $contada - $esperada;
                    if ($diff === 0) continue;

                    if ($it['es_extra']) {
                        // Sobrante extra: no ajustar automáticamente (requiere entrada manual posterior)
                        $ajustados++;
                        continue;
                    }

                    if ($diff < 0) {
                        // Faltante: reducir stock
                        $pdo->prepare("UPDATE ProductoCargaEstiba SET Cantidad=? WHERE CargaID=? AND ProductosID=? AND FechaEV=? AND EstibaID=?")
                            ->execute([$contada, $it['CargaID'], $it['ProductosID'], $it['FechaEV'], $it['EstibaID']]);
                        // Si queda en 0, dar de baja estiba
                        if ($contada <= 0) {
                            $pdo->prepare("UPDATE Estiba SET FechaBaja=NOW() WHERE EstibaID=? AND FechaBaja IS NULL")
                                ->execute([$it['EstibaID']]);
                        }
                    } else {
                        // Sobrante: agregar stock
                        $pdo->prepare("UPDATE ProductoCargaEstiba SET Cantidad=? WHERE CargaID=? AND ProductosID=? AND FechaEV=? AND EstibaID=?")
                            ->execute([$contada, $it['CargaID'], $it['ProductosID'], $it['FechaEV'], $it['EstibaID']]);
                    }
                    $ajustados++;
                }

                $pdo->prepare("UPDATE inventario_sesion SET estado='aprobado', aprobado_por=?, approved_at=NOW() WHERE id=?")
                    ->execute([(int)$_SESSION['cina_usuario_id'], $sesionId]);
                $pdo->commit();
                resp(200, ['ok'=>true, 'ajustados'=>$ajustados, 'mensaje'=>"Inventario aprobado. $ajustados ajustes aplicados."]);
            } catch(Exception $e) { if($pdo->inTransaction())$pdo->rollBack(); resp(500, ['error'=>$e->getMessage()]); }
        }

        else { resp(400, ['error'=>'Acción de inventario no reconocida']); }
        break;

    // ── Gestión de facturas emitidas ─────────────────────────────
    case 'gestion_facturas_lista':
        requireSession();
        try {
            // Auto-crear tabla si no existe
            $pdo->exec("CREATE TABLE IF NOT EXISTS facturas_emitidas (
                id INT AUTO_INCREMENT PRIMARY KEY, nro_factura VARCHAR(20) NOT NULL,
                fecha_factura DATE NOT NULL, cliente_tipo ENUM('cliente','grupo') NOT NULL DEFAULT 'cliente',
                cliente_id INT NULL, grupo_id INT NULL, periodo_desde DATE NULL, periodo_hasta DATE NULL,
                monto_sin_iva DECIMAL(14,2) NULL, monto_final DECIMAL(14,2) NOT NULL,
                plazo_dias INT NULL, fecha_vencimiento DATE NULL,
                estado ENUM('PENDIENTE','PAGADA','VENCIDA','ANULADA') NOT NULL DEFAULT 'PENDIENTE',
                url_factura VARCHAR(500) NULL, nro_oc VARCHAR(100) NULL,
                fecha_pago_estimada DATE NULL, creado_por INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_cliente (cliente_tipo, cliente_id), INDEX idx_estado (estado)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Filtrar por clientes permitidos si es portal
            $clienteIds = null;
            if (function_exists('getClienteIdsPermitidos')) {
                $clienteIds = getClienteIdsPermitidos($pdo, $_SESSION['cina_usuario_id'], $_SESSION['cina_rol_id'] ?? 0);
            }
            $where = '1=1'; $params = [];
            if ($clienteIds !== null) {
                if (empty($clienteIds)) { resp(200, ['facturas' => []]); break; }
                $ph = implode(',', array_fill(0, count($clienteIds), '?'));
                $where .= " AND ((f.cliente_tipo='cliente' AND f.cliente_id IN ($ph)) OR (f.cliente_tipo='grupo' AND f.grupo_id IN (SELECT DISTINCT GrupoID FROM CLIENTES WHERE ClienteID IN ($ph))))";
                $params = array_merge($params, $clienteIds, $clienteIds);
            }
            $stmt = $pdo->prepare("
                SELECT f.*, CASE f.cliente_tipo WHEN 'cliente' THEN c.ClienteNombre WHEN 'grupo' THEN g.Nombre END AS cliente_nombre, g.Nombre AS grupo_nombre
                FROM facturas_emitidas f
                LEFT JOIN CLIENTES c ON f.cliente_tipo='cliente' AND f.cliente_id=c.ClienteID
                LEFT JOIN CLIENTEGRUPO g ON (f.cliente_tipo='grupo' AND f.grupo_id=g.GrupoID) OR (f.cliente_tipo='cliente' AND c.GrupoID=g.GrupoID)
                WHERE $where ORDER BY f.fecha_factura DESC, f.id DESC
            ");
            $stmt->execute($params);
            $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Cargar adjuntos por factura
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS factura_adjuntos (
                    adjunto_id INT AUTO_INCREMENT PRIMARY KEY, factura_id INT NOT NULL,
                    nombre_original VARCHAR(255), ruta VARCHAR(500) NOT NULL,
                    tipo VARCHAR(50), tamano INT DEFAULT 0, subido_por INT,
                    created_at DATETIME DEFAULT NOW(), INDEX idx_factura (factura_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                $stAdj = $pdo->prepare("SELECT adjunto_id, nombre_original, ruta, tipo, tamano FROM factura_adjuntos WHERE factura_id=? ORDER BY created_at");
                foreach ($facturas as &$f) {
                    $stAdj->execute([$f['id']]);
                    $f['adjuntos'] = $stAdj->fetchAll(PDO::FETCH_ASSOC);
                }
                unset($f);
            } catch(Exception $e2) {
                // Si la tabla no existe aún, seguir sin adjuntos
                foreach ($facturas as &$f) { $f['adjuntos'] = []; }
                unset($f);
            }

            resp(200, ['facturas' => $facturas]);
        } catch (Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    case 'gestion_factura_crear':
    case 'gestion_factura_editar':
        requireSession();
        $b = json_input();
        try {
            $nro       = trim($b['nro_factura'] ?? '');
            $fecha     = $b['fecha_factura'] ?? '';
            $cliTipo   = $b['cliente_tipo'] ?? 'cliente';
            $cliId     = intval($b['cliente_id'] ?? 0);
            $grupoId   = intval($b['grupo_id'] ?? 0);
            $desde     = $b['periodo_desde'] ?: null;
            $hasta     = $b['periodo_hasta'] ?: null;
            $sinIva    = $b['monto_sin_iva'] !== '' ? floatval($b['monto_sin_iva']) : null;
            $final     = floatval($b['monto_final'] ?? 0);
            $plazo     = intval($b['plazo_dias'] ?? 0) ?: null;
            $venc      = $b['fecha_vencimiento'] ?: null;
            $estado    = $b['estado'] ?? 'PENDIENTE';
            if (!$nro || !$fecha) resp(400, ['error'=>'N° factura y fecha son obligatorios']);

            if ($action === 'gestion_factura_crear') {
                $pdo->prepare("INSERT INTO facturas_emitidas (nro_factura, fecha_factura, cliente_tipo, cliente_id, grupo_id, periodo_desde, periodo_hasta, monto_sin_iva, monto_final, plazo_dias, fecha_vencimiento, estado, creado_por) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$nro, $fecha, $cliTipo, $cliId?:null, $grupoId?:null, $desde, $hasta, $sinIva, $final, $plazo, $venc, $estado, (int)$_SESSION['cina_usuario_id']]);
                resp(200, ['ok'=>true, 'id'=>(int)$pdo->lastInsertId(), 'mensaje'=>'Factura creada']);
            } else {
                $id = intval($b['id'] ?? 0);
                if (!$id) resp(400, ['error'=>'Falta id']);
                $pdo->prepare("UPDATE facturas_emitidas SET nro_factura=?, fecha_factura=?, cliente_tipo=?, cliente_id=?, grupo_id=?, periodo_desde=?, periodo_hasta=?, monto_sin_iva=?, monto_final=?, plazo_dias=?, fecha_vencimiento=?, estado=?, updated_at=NOW() WHERE id=?")
                    ->execute([$nro, $fecha, $cliTipo, $cliId?:null, $grupoId?:null, $desde, $hasta, $sinIva, $final, $plazo, $venc, $estado, $id]);
                resp(200, ['ok'=>true, 'mensaje'=>'Factura actualizada']);
            }
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'gestion_factura_subir_pdf':
        requireSession();
        $id = intval($_POST['id'] ?? 0);
        if (!$id) resp(400, ['error'=>'Falta id']);
        if (empty($_FILES['pdf'])) resp(400, ['error'=>'No se recibió archivo']);
        // Auto-crear tabla de adjuntos
        $pdo->exec("CREATE TABLE IF NOT EXISTS factura_adjuntos (
            adjunto_id INT AUTO_INCREMENT PRIMARY KEY,
            factura_id INT NOT NULL,
            nombre_original VARCHAR(255),
            ruta VARCHAR(500) NOT NULL,
            tipo VARCHAR(50),
            tamano INT DEFAULT 0,
            subido_por INT,
            created_at DATETIME DEFAULT NOW(),
            INDEX idx_factura (factura_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $file = $_FILES['pdf'];
        if ($file['size'] > 15 * 1024 * 1024) resp(400, ['error'=>'Archivo mayor a 15 MB']);
        try {
            $dir = __DIR__ . '/uploads/facturas';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'pdf');
            $nombre = 'factura_' . $id . '_' . time() . '_' . rand(100,999) . '.' . $ext;
            $ruta = $dir . '/' . $nombre;
            if (!move_uploaded_file($file['tmp_name'], $ruta)) resp(500, ['error'=>'Error al guardar archivo']);
            $url = '/uploads/facturas/' . $nombre;

            // Guardar en tabla de adjuntos
            $pdo->prepare("INSERT INTO factura_adjuntos (factura_id, nombre_original, ruta, tipo, tamano, subido_por) VALUES (?,?,?,?,?,?)")
                ->execute([$id, $file['name'], $url, $ext, $file['size'], (int)$_SESSION['cina_usuario_id']]);

            // También mantener url_factura con el último archivo para compatibilidad
            $pdo->prepare("UPDATE facturas_emitidas SET url_factura=?, updated_at=NOW() WHERE id=?")->execute([$url, $id]);
            resp(200, ['ok'=>true, 'url'=>$url, 'nombre'=>$file['name']]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'gestion_factura_adjuntos':
        requireSession();
        $id = intval($_GET['id'] ?? 0);
        if (!$id) resp(400, ['error'=>'Falta id']);
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS factura_adjuntos (
                adjunto_id INT AUTO_INCREMENT PRIMARY KEY, factura_id INT NOT NULL,
                nombre_original VARCHAR(255), ruta VARCHAR(500) NOT NULL,
                tipo VARCHAR(50), tamano INT DEFAULT 0, subido_por INT,
                created_at DATETIME DEFAULT NOW(), INDEX idx_factura (factura_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $st = $pdo->prepare("SELECT * FROM factura_adjuntos WHERE factura_id=? ORDER BY created_at DESC");
            $st->execute([$id]);
            resp(200, ['ok'=>true, 'adjuntos'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'gestion_factura_eliminar_pdf':
        requireSession();
        $b = json_input();
        $adjuntoId = intval($b['adjunto_id'] ?? 0);
        $facturaId = intval($b['id'] ?? 0);
        try {
            if ($adjuntoId) {
                // Eliminar adjunto específico
                $st = $pdo->prepare("SELECT ruta, factura_id FROM factura_adjuntos WHERE adjunto_id=?");
                $st->execute([$adjuntoId]); $adj = $st->fetch();
                if ($adj) {
                    $rutaArchivo = __DIR__ . $adj['ruta'];
                    if (file_exists($rutaArchivo)) @unlink($rutaArchivo);
                    $pdo->prepare("DELETE FROM factura_adjuntos WHERE adjunto_id=?")->execute([$adjuntoId]);
                    // Actualizar url_factura al último adjunto restante
                    $stLast = $pdo->prepare("SELECT ruta FROM factura_adjuntos WHERE factura_id=? ORDER BY created_at DESC LIMIT 1");
                    $stLast->execute([$adj['factura_id']]);
                    $lastUrl = $stLast->fetchColumn() ?: null;
                    $pdo->prepare("UPDATE facturas_emitidas SET url_factura=?, updated_at=NOW() WHERE id=?")->execute([$lastUrl, $adj['factura_id']]);
                }
            } elseif ($facturaId) {
                // Eliminar todos los adjuntos de una factura (legacy)
                $st = $pdo->prepare("SELECT ruta FROM factura_adjuntos WHERE factura_id=?");
                $st->execute([$facturaId]);
                while ($row = $st->fetch()) {
                    $rutaArchivo = __DIR__ . $row['ruta'];
                    if (file_exists($rutaArchivo)) @unlink($rutaArchivo);
                }
                $pdo->prepare("DELETE FROM factura_adjuntos WHERE factura_id=?")->execute([$facturaId]);
                $pdo->prepare("UPDATE facturas_emitidas SET url_factura=NULL, updated_at=NOW() WHERE id=?")->execute([$facturaId]);
            }
            resp(200, ['ok'=>true, 'mensaje'=>'Archivo eliminado']);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'gestion_factura_cargar_oc':
        requireSession();
        $b = json_input();
        $id = intval($b['id'] ?? 0);
        if (!$id) resp(400, ['error'=>'Falta id']);
        try {
            $nroOc = trim($b['nro_oc'] ?? '');
            $fechaPago = $b['fecha_pago_estimada'] ?: null;
            $pdo->prepare("UPDATE facturas_emitidas SET nro_oc=?, fecha_pago_estimada=?, updated_at=NOW() WHERE id=?")
                ->execute([$nroOc, $fechaPago, $id]);
            resp(200, ['ok'=>true, 'mensaje'=>'OC actualizada']);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    // ═══════════════════════════════════════════════════════════════
    //  ÓRDENES DE COMPRA — Persistencia en DB
    // ═══════════════════════════════════════════════════════════════
    case 'compras_empresas':
        requireSession();
        try {
            $rows = $pdo->query("
                SELECT empresa_compra_id AS id, razon_social AS nombre, cuit,
                       CONCAT(direccion,' - ',localidad) AS contacto
                FROM empresa_compra
                ORDER BY razon_social
            ")->fetchAll(PDO::FETCH_ASSOC);
            $deptos = $pdo->query("
                SELECT departamento_id AS id, nombre
                FROM departamento
                WHERE activo = 1
                ORDER BY nombre
            ")->fetchAll(PDO::FETCH_ASSOC);
            $proveedores = $pdo->query("
                SELECT proveedor_id AS id, razon_social AS nombre, cuit
                FROM proveedor
                ORDER BY razon_social
            ")->fetchAll(PDO::FETCH_ASSOC);
            resp(200, ['ok'=>true, 'empresas'=>$rows, 'departamentos'=>$deptos, 'proveedores'=>$proveedores]);
        } catch(Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'compras_init':
    case 'compras_guardar':
    case 'compras_lista':
    case 'compras_ver':
    case 'compras_cambiar_estado':
        requireSession();
        // Auto-crear/migrar tabla
        $pdo->exec("CREATE TABLE IF NOT EXISTS ordenes_compra (
            id INT AUTO_INCREMENT PRIMARY KEY,
            numero VARCHAR(30) NOT NULL,
            fecha DATE NOT NULL,
            proveedor VARCHAR(200),
            proveedor_cuit VARCHAR(20),
            proveedor_contacto VARCHAR(200),
            observaciones TEXT,
            estado VARCHAR(20) DEFAULT 'PENDIENTE',
            monto_total DECIMAL(14,2) DEFAULT 0,
            autorizado_por INT, fecha_autorizacion DATETIME,
            creado_por INT, usuario_id INT,
            created_at DATETIME DEFAULT NOW(), updated_at DATETIME DEFAULT NOW()
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Migrar columna estado de ENUM a VARCHAR si es necesario
        try { $pdo->exec("ALTER TABLE ordenes_compra MODIFY COLUMN estado VARCHAR(20) DEFAULT 'PENDIENTE'"); } catch(Exception $e) {}
        // Agregar columna creado_por_nombre para saber quién creó
        try { $pdo->exec("ALTER TABLE ordenes_compra ADD COLUMN creado_por_nombre VARCHAR(100)"); } catch(Exception $e) {}
        $pdo->exec("CREATE TABLE IF NOT EXISTS ordenes_compra_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            orden_id INT NOT NULL,
            descripcion VARCHAR(300),
            cantidad DECIMAL(10,2) DEFAULT 1,
            precio_unitario DECIMAL(14,2) DEFAULT 0,
            subtotal DECIMAL(14,2) DEFAULT 0,
            INDEX idx_orden (orden_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if ($action === 'compras_guardar') {
            $b = json_input();
            $id = intval($b['id'] ?? 0);
            try {
                $pdo->beginTransaction();
                if ($id) {
                    $pdo->prepare("UPDATE ordenes_compra SET numero=?,fecha=?,proveedor=?,proveedor_cuit=?,proveedor_contacto=?,observaciones=?,estado=?,monto_total=?,usuario_id=?,updated_at=NOW() WHERE id=?")
                        ->execute([$b['numero']??'',$b['fecha']??date('Y-m-d'),$b['proveedor']??'',$b['proveedor_cuit']??'',$b['proveedor_contacto']??'',$b['observaciones']??'',$b['estado']??'borrador',floatval($b['monto_total']??0),(int)$_SESSION['cina_usuario_id'],$id]);
                } else {
                    // Obtener nombre del usuario creador
                    $stNom = $pdo->prepare("SELECT UsuarioNombre FROM Usuarios WHERE UsuarioID=?");
                    $stNom->execute([(int)$_SESSION['cina_usuario_id']]);
                    $nomCreador = $stNom->fetchColumn() ?: '';
                    $pdo->prepare("INSERT INTO ordenes_compra (numero,fecha,proveedor,proveedor_cuit,proveedor_contacto,observaciones,estado,monto_total,creado_por,usuario_id,creado_por_nombre) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                        ->execute([$b['numero']??'',$b['fecha']??date('Y-m-d'),$b['proveedor']??'',$b['proveedor_cuit']??'',$b['proveedor_contacto']??'',$b['observaciones']??'',$b['estado']??'PENDIENTE',floatval($b['monto_total']??0),(int)$_SESSION['cina_usuario_id'],(int)$_SESSION['cina_usuario_id'],$nomCreador]);
                    $id = (int)$pdo->lastInsertId();
                }
                // Items
                $pdo->prepare("DELETE FROM ordenes_compra_items WHERE orden_id=?")->execute([$id]);
                $stItem = $pdo->prepare("INSERT INTO ordenes_compra_items (orden_id,descripcion,cantidad,precio_unitario,subtotal) VALUES (?,?,?,?,?)");
                foreach (($b['items']??[]) as $it) {
                    $cant = floatval($it['cantidad']??1); $pu = floatval($it['precio_unitario']??0);
                    $stItem->execute([$id, $it['descripcion']??'', $cant, $pu, $cant*$pu]);
                }
                $pdo->commit();
                resp(200, ['ok'=>true, 'id'=>$id]);
            } catch(Exception $e) { if($pdo->inTransaction())$pdo->rollBack(); resp(500, ['error'=>$e->getMessage()]); }
        }
        elseif ($action === 'compras_lista') {
            try {
                $rows = $pdo->query("SELECT oc.*, U.UsuarioNombre AS creador FROM ordenes_compra oc LEFT JOIN Usuarios U ON U.UsuarioID=oc.creado_por ORDER BY oc.id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
                resp(200, ['ok'=>true, 'ordenes'=>$rows]);
            } catch(Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        }
        elseif ($action === 'compras_ver') {
            $id = intval($_GET['id']??0);
            if (!$id) resp(400, ['error'=>'Falta id']);
            try {
                $st = $pdo->prepare("SELECT * FROM ordenes_compra WHERE id=?"); $st->execute([$id]); $oc = $st->fetch(PDO::FETCH_ASSOC);
                if (!$oc) resp(404, ['error'=>'OC no encontrada']);
                $si = $pdo->prepare("SELECT * FROM ordenes_compra_items WHERE orden_id=? ORDER BY id"); $si->execute([$id]);
                $oc['items'] = $si->fetchAll(PDO::FETCH_ASSOC);
                resp(200, ['ok'=>true, 'orden'=>$oc]);
            } catch(Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        }
        elseif ($action === 'compras_cambiar_estado') {
            $b = json_input();
            $id = intval($b['id']??0); $estado = $b['estado']??'';
            if (!$id||!$estado) resp(400, ['error'=>'Faltan datos']);
            try {
                $sets = "estado=?, updated_at=NOW()"; $params = [$estado];
                if ($estado === 'autorizada') { $sets .= ", autorizado_por=?, fecha_autorizacion=NOW()"; $params[] = (int)$_SESSION['cina_usuario_id']; }
                $params[] = $id;
                $pdo->prepare("UPDATE ordenes_compra SET $sets WHERE id=?")->execute($params);
                resp(200, ['ok'=>true]);
            } catch(Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        }
        else { resp(200, ['ok'=>true]); }
        break;

    // ── GET: lista de personas (RRHH) con datos laborales ───────
    case 'personal_lista':
        requireSession();
        try {
            $dbNP = $pdo->query("SELECT DATABASE()")->fetchColumn();
            $tieneEmpleado = (bool)$pdo->query("
                SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA='$dbNP' AND TABLE_NAME='persona_empleado'
            ")->fetchColumn();
            // Detectar si existe persona_empleado2 (versión extendida con inducciones)
            $tieneEmpleado2 = (bool)$pdo->query("
                SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA='$dbNP' AND TABLE_NAME='persona_empleado2'
            ")->fetchColumn();

            if ($tieneEmpleado2) {
                $rows = $pdo->query("
                    SELECT
                        p.persona_id, p.nombre, p.apellido,
                        p.tipo_documento_id, p.numero_documento,
                        p.cuit, p.cuil,
                        p.fecha_nacimiento, p.nacionalidad_id,
                        p.sexo, p.estado_civil, p.cantidad_hijos,
                        p.direccion, p.localidad_id, p.pais_id, p.codigo_postal,
                        p.telefono_fijo, p.telefono_movil,
                        p.email1, p.email2,
                        p.art_vencimiento, p.activo,
                        pe.legajo, pe.departamento_id,
                        pe.fecha_ingreso, pe.fecha_examen_ingreso,
                        pe.visado, pe.patologia, pe.actividades_no_realizables,
                        pe.monotributista, pe.reponsable_inscripto, pe.exento,
                        pe.fecha_alta_afip, pe.fecha_baja_afip,
                        pe.tarjeta_ingreso_entregadada, pe.numero_tarjeta,
                        pe.nota_entrega_de_tarjeta, pe.firmo_reglamento_interno,
                        pe.induccion_ingresante, pe.induccion_calidad, pe.induccion_SYSO,
                        pe.induccion_ingresante_fecha, pe.induccion_calidad_fecha, pe.induccion_SYSO_fecha,
                        u.UsuarioNombre AS usuario_nombre,
                        u.RolID         AS usuario_rol_id,
                        u.UsuarioID     AS usuario_id
                    FROM persona p
                    LEFT JOIN persona_empleado2 pe ON pe.persona_id = p.persona_id
                    LEFT JOIN Usuarios u ON u.persona_id = p.persona_id AND u.activo = 1
                    ORDER BY p.activo DESC, pe.legajo ASC, p.apellido ASC
                ")->fetchAll();
            } elseif ($tieneEmpleado) {
                $rows = $pdo->query("
                    SELECT
                        p.persona_id, p.nombre, p.apellido,
                        p.tipo_documento_id, p.numero_documento,
                        p.cuit, p.cuil,
                        p.fecha_nacimiento, p.nacionalidad_id,
                        p.sexo, p.estado_civil, p.cantidad_hijos,
                        p.direccion, p.localidad_id, p.pais_id, p.codigo_postal,
                        p.telefono_fijo, p.telefono_movil,
                        p.email1, p.email2,
                        p.art_vencimiento, p.activo,
                        pe.legajo, pe.departamento_id,
                        pe.fecha_ingreso, pe.fecha_examen_ingreso,
                        pe.visado, pe.patologia, pe.actividades_no_realizables,
                        pe.monotributista, pe.reponsable_inscripto, pe.exento,
                        pe.fecha_alta_afip, pe.fecha_baja_afip,
                        pe.tarjeta_ingreso_entregadada, pe.numero_tarjeta,
                        pe.nota_entrega_de_tarjeta, pe.firmo_reglamento_interno,
                        NULL AS induccion_ingresante,
                        NULL AS induccion_calidad,
                        NULL AS induccion_SYSO,
                        u.UsuarioNombre AS usuario_nombre,
                        u.RolID         AS usuario_rol_id,
                        u.UsuarioID     AS usuario_id
                    FROM persona p
                    LEFT JOIN persona_empleado pe ON pe.persona_id = p.persona_id
                    LEFT JOIN Usuarios u ON u.persona_id = p.persona_id AND u.activo = 1
                    ORDER BY p.activo DESC, pe.legajo ASC, p.apellido ASC
                ")->fetchAll();
            } else {
                $rows = $pdo->query("
                    SELECT
                        p.persona_id, p.nombre, p.apellido,
                        p.numero_documento, p.cuit, p.cuil,
                        p.fecha_nacimiento, p.sexo, p.estado_civil, p.cantidad_hijos,
                        p.direccion, p.codigo_postal,
                        p.telefono_fijo, p.telefono_movil,
                        p.email1, p.email2,
                        p.art_vencimiento, p.activo,
                        NULL AS legajo, NULL AS departamento_id,
                        NULL AS fecha_ingreso, NULL AS fecha_examen_ingreso,
                        NULL AS visado, NULL AS patologia, NULL AS actividades_no_realizables,
                        NULL AS monotributista, NULL AS reponsable_inscripto, NULL AS exento,
                        NULL AS fecha_alta_afip, NULL AS fecha_baja_afip,
                        NULL AS tarjeta_ingreso_entregadada, NULL AS numero_tarjeta,
                        NULL AS nota_entrega_de_tarjeta, NULL AS firmo_reglamento_interno,
                        NULL AS induccion_ingresante, NULL AS induccion_calidad, NULL AS induccion_SYSO,
                        u.UsuarioNombre AS usuario_nombre,
                        u.RolID         AS usuario_rol_id,
                        u.UsuarioID     AS usuario_id
                    FROM persona p
                    LEFT JOIN Usuarios u ON u.persona_id = p.persona_id AND u.activo = 1
                    ORDER BY p.activo DESC, p.apellido ASC
                ")->fetchAll();
            }
            resp(200, $rows);
        } catch (Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    // ── POST: editar datos de persona + opcionalmente crear usuario ──
    case 'persona_editar':
        requireSession();
        $stmtRolCk = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
        $stmtRolCk->execute([$_SESSION['cina_usuario_id']]);
        if (!in_array((int)$stmtRolCk->fetchColumn(), [1,2,3,5,10])) resp(403, ['error'=>'Sin permisos']);

        $pid = (int)($body['persona_id'] ?? 0);
        if (!$pid) resp(400, ['error'=>'Falta persona_id']);

        $pdo->beginTransaction();
        try {
            // ── Actualizar tabla persona — todos los campos ────────
            $pdo->prepare("
                UPDATE persona SET
                    nombre            = ?,
                    apellido          = ?,
                    numero_documento  = ?,
                    cuil              = ?,
                    cuit              = ?,
                    fecha_nacimiento  = ?,
                    sexo              = ?,
                    estado_civil      = ?,
                    cantidad_hijos    = ?,
                    direccion         = ?,
                    codigo_postal     = ?,
                    telefono_fijo     = ?,
                    telefono_movil    = ?,
                    email1            = ?,
                    email2            = ?,
                    art_vencimiento   = ?,
                    activo            = ?,
                    updated_at        = NOW()
                WHERE persona_id = ?
            ")->execute([
                trim($body['nombre']           ?? ''),
                trim($body['apellido']         ?? ''),
                trim($body['numero_documento'] ?? ''),
                trim($body['cuil']             ?? '') ?: null,
                trim($body['cuit']             ?? '') ?: null,
                $body['fecha_nacimiento']       ?? null ?: null,
                trim($body['sexo']             ?? 'M'),
                trim($body['estado_civil']     ?? 'S'),
                (int)($body['cantidad_hijos']  ?? 0),
                trim($body['direccion']        ?? ''),
                trim($body['codigo_postal']    ?? ''),
                trim($body['telefono_fijo']    ?? '') ?: null,
                trim($body['telefono_movil']   ?? '') ?: null,
                trim($body['email1']           ?? '') ?: null,
                trim($body['email2']           ?? '') ?: null,
                $body['art_vencimiento']        ?? null ?: null,
                (int)($body['activo']          ?? 1),
                $pid,
            ]);

            // ── Actualizar persona_empleado / persona_empleado2 ───
            $legajo   = isset($body['legajo'])       && $body['legajo']       !== '' ? (int)$body['legajo']  : null;
            $fIngreso = $body['fecha_ingreso']        ?? null ?: null;
            $fExamen  = $body['fecha_examen_ingreso'] ?? null ?: null;
            $fAltaAF  = $body['fecha_alta_afip']      ?? null ?: null;
            $fBajaAF  = $body['fecha_baja_afip']      ?? null ?: null;
            $tarjeta  = trim($body['numero_tarjeta']  ?? '');
            $patol    = trim($body['patologia']       ?? '');
            $activ    = trim($body['actividades_no_realizables'] ?? '');
            $mono     = (int)($body['monotributista']          ?? 0);
            $resp_i   = (int)($body['reponsable_inscripto']    ?? 0);
            $exento   = (int)($body['exento']                  ?? 0);
            $tarj_ent = (int)($body['tarjeta_ingreso_entregadada'] ?? 0);
            $reglam   = (int)($body['firmo_reglamento_interno'] ?? 0);

            $dbNE = $pdo->query("SELECT DATABASE()")->fetchColumn();

            // Detectar qué tabla de empleado usar
            $tablaEmp = null;
            foreach (['persona_empleado2', 'persona_empleado'] as $t) {
                $ex = (bool)$pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                    WHERE TABLE_SCHEMA='$dbNE' AND TABLE_NAME='$t'")->fetchColumn();
                if ($ex) { $tablaEmp = $t; break; }
            }

            if ($tablaEmp) {
                $stmtChkPE = $pdo->prepare("SELECT COUNT(*) FROM `$tablaEmp` WHERE persona_id = ?");
                $stmtChkPE->execute([$pid]);
                $existePE = (bool)$stmtChkPE->fetchColumn();

                // Campos extra de persona_empleado2
                $indIng  = isset($body['induccion_ingresante']) ? (int)$body['induccion_ingresante'] : null;
                $indCal  = isset($body['induccion_calidad'])    ? (int)$body['induccion_calidad']    : null;
                $indSYSO = isset($body['induccion_SYSO'])       ? (int)$body['induccion_SYSO']       : null;
                $tieneInd = ($tablaEmp === 'persona_empleado2') && ($indIng !== null);

                // Departamento
                $deptoId = isset($body['departamento_id']) ? (intval($body['departamento_id']) ?: 0) : null;
                $esResponsableDepto = (int)($body['es_responsable_depto'] ?? 0);

                if ($existePE) {
                    $setPE = "
                        legajo = ?, departamento_id = ?, fecha_ingreso = ?, fecha_examen_ingreso = ?,
                        fecha_alta_afip = ?, fecha_baja_afip = ?,
                        patologia = ?, actividades_no_realizables = ?,
                        monotributista = ?, reponsable_inscripto = ?, exento = ?,
                        tarjeta_ingreso_entregadada = ?, numero_tarjeta = ?,
                        firmo_reglamento_interno = ?";
                    $paramsPE = [$legajo ?? 0, $deptoId ?? 0, $fIngreso, $fExamen, $fAltaAF, $fBajaAF,
                                 $patol, $activ, $mono, $resp_i, $exento,
                                 $tarj_ent, $tarjeta, $reglam];
                    if ($tieneInd) {
                        $setPE .= ", induccion_ingresante=?, induccion_calidad=?, induccion_SYSO=?";
                        $paramsPE[] = $indIng; $paramsPE[] = $indCal; $paramsPE[] = $indSYSO;
                    }
                    $paramsPE[] = $pid;
                    $pdo->prepare("UPDATE `$tablaEmp` SET $setPE WHERE persona_id = ?")
                        ->execute($paramsPE);
                } else {
                    // INSERT mínimo según tabla
                    if ($tablaEmp === 'persona_empleado2') {
                        $pdo->prepare("
                            INSERT INTO persona_empleado2
                                (persona_id, empresa_id, legajo, departamento_id,
                                 fecha_ingreso, fecha_examen_ingreso, visado,
                                 patologia, actividades_no_realizables,
                                 monotributista, reponsable_inscripto, exento,
                                 fecha_alta_afip, fecha_baja_afip,
                                 tarjeta_ingreso_entregadada, numero_tarjeta,
                                 nota_entrega_de_tarjeta, firmo_reglamento_interno,
                                 induccion_ingresante, induccion_calidad, induccion_SYSO)
                            VALUES (?,1,?,1,?,?,0,?,?,?,?,?,?,?,?,?,0,?,?,?,?)
                        ")->execute([$pid, $legajo??0, $fIngreso, $fExamen, $patol, $activ,
                                     $mono, $resp_i, $exento, $fAltaAF, $fBajaAF,
                                     $tarj_ent, $tarjeta, $reglam, $indIng, $indCal, $indSYSO]);
                    } else {
                        $pdo->prepare("
                            INSERT INTO persona_empleado
                                (persona_id, empresa_id, legajo, departamento_id,
                                 fecha_ingreso, fecha_examen_ingreso, visado,
                                 patologia, actividades_no_realizables,
                                 monotributista, reponsable_inscripto, exento,
                                 fecha_alta_afip, fecha_baja_afip,
                                 tarjeta_ingreso_entregadada, numero_tarjeta,
                                 nota_entrega_de_tarjeta, firmo_reglamento_interno)
                            VALUES (?,1,?,1,?,?,0,?,?,?,?,?,?,?,?,0,?)
                        ")->execute([$pid, $legajo??0, $fIngreso, $fExamen, $patol, $activ,
                                     $mono, $resp_i, $exento, $fAltaAF, $fBajaAF,
                                     $tarj_ent, $tarjeta, $reglam]);
                    }
                }
            }

            // ── Crear/actualizar usuario del sistema si se pidió ──
            if (!empty($body['crear_usuario'])) {
                $uNombre  = trim($body['usuario_nombre'] ?? '');
                $password = $body['password'] ?? '';
                $rolId    = (int)($body['rol_id'] ?? 0);
                if (!$uNombre || !$password) {
                    $pdo->rollBack();
                    resp(400, ['error' => 'Faltan nombre de usuario o contraseña']);
                }
                $stmtExU = $pdo->prepare("SELECT UsuarioID FROM Usuarios WHERE persona_id = ? LIMIT 1");
                $stmtExU->execute([$pid]);
                $uidExist = $stmtExU->fetchColumn();
                if ($uidExist) {
                    $pdo->prepare("UPDATE Usuarios SET UsuarioNombre=?, UsuarioClave=SHA1(?), RolID=? WHERE UsuarioID=?")
                        ->execute([$uNombre, $password, $rolId ?: null, $uidExist]);
                } else {
                    $stmtNom = $pdo->prepare("SELECT UsuarioID FROM Usuarios WHERE UsuarioNombre=? LIMIT 1");
                    $stmtNom->execute([$uNombre]);
                    if ($stmtNom->fetchColumn()) {
                        $pdo->rollBack();
                        resp(409, ['error' => "El usuario \"$uNombre\" ya existe"]);
                    }
                    $pdo->prepare("
                        INSERT INTO Usuarios (UsuarioNombre, UsuarioClave, RolID, persona_id, activo, ultimo_login)
                        VALUES (?, SHA1(?), ?, ?, 1, NOW())
                    ")->execute([$uNombre, $password, $rolId ?: null, $pid]);
                }
            }

            // Actualizar responsable del departamento si corresponde
            if (isset($body['departamento_id']) && !empty($body['es_responsable_depto'])) {
                $deptoIdResp = intval($body['departamento_id']);
                if ($deptoIdResp) {
                    $pdo->prepare("UPDATE departamento SET responsable_departamento=? WHERE departamento_id=?")
                        ->execute([$pid, $deptoIdResp]);
                }
            }

            $pdo->commit();
            resp(200, ['ok' => true, 'persona_id' => $pid]);

        } catch (Exception $e) {
            $pdo->rollBack();
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── POST: desvincular usuario de una persona ─────────────────
    case 'persona_desvincular_usuario':
        requireSession();
        $stmtRolCk = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
        $stmtRolCk->execute([$_SESSION['cina_usuario_id']]);
        if (!in_array((int)$stmtRolCk->fetchColumn(), [1,2,3,5,10])) resp(403, ['error'=>'Sin permisos']);

        $pid = (int)($body['persona_id'] ?? 0);
        if (!$pid) resp(400, ['error' => 'Falta persona_id']);

        try {
            // Pone persona_id = NULL en el usuario (no lo elimina)
            $pdo->prepare("UPDATE Usuarios SET persona_id = NULL WHERE persona_id = ?")
                ->execute([$pid]);
            resp(200, ['ok' => true]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── POST: actualizar sector de una cámara (1=Verde, 2=Azul, 3=Blanco) ──
    case 'camara_sector_update':
        requireSession();
        $stmtRolCk = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
        $stmtRolCk->execute([$_SESSION['cina_usuario_id']]);
        if (!in_array((int)$stmtRolCk->fetchColumn(), [1,5,10])) resp(403, ['error'=>'Sin permisos']);
        $camaraId = (int)($body['camara_id'] ?? 0);
        $sector   = (int)($body['sector']    ?? 1);
        if (!$camaraId || !in_array($sector, [1,2,3])) resp(400, ['error'=>'Datos inválidos']);
        try {
            $pdo->prepare("UPDATE Camaras SET sector = ? WHERE CamaraID = ?")
                ->execute([$sector, $camaraId]);
            resp(200, ['ok' => true, 'camara_id' => $camaraId, 'sector' => $sector]);
        } catch (Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    // ── GET: lista sectores de todas las cámaras activas ────────
    case 'camaras_sectores':
        requireSession();
        try {
            $rows = $pdo->query("
                SELECT c.CamaraID, c.CamaraNumero, c.TiposCamaraID, c.activo,
                       c.sector,
                       t.TiposCamaraNombre AS tipo_nombre
                FROM Camaras c
                LEFT JOIN TiposCamara t ON t.TiposCamaraID = c.TiposCamaraID
                ORDER BY c.activo DESC, c.TiposCamaraID, c.CamaraNumero
            ")->fetchAll();
            resp(200, $rows);
        } catch (Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    // ══════════════════════════════════════════════════════════
    //  MÓDULO PAÑOL
    // ══════════════════════════════════════════════════════════

    case 'panol_items':
        requireSession();
        $cat = $_GET['cat'] ?? '';
        $q   = $_GET['q']   ?? '';
        $where = ['i.activo = 1'];
        $params = [];
        if ($cat) { $where[] = 'i.categoria = ?'; $params[] = $cat; }
        if ($q) {
            $like    = "%$q%";
            $where[] = '(i.nombre LIKE ? OR i.numero_item LIKE ? OR i.descripcion LIKE ?)';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        $stmt = $pdo->prepare("
            SELECT i.id, i.numero_item, i.nombre, i.categoria, i.descripcion,
                   i.ubicacion, i.stock_actual, i.stock_minimo, i.unidad, i.activo,
                   COALESCE(p.prestados,0) AS prestados,
                   i.created_at, i.updated_at
            FROM panol_items i
            LEFT JOIN (
                SELECT item_id, COUNT(*) AS prestados
                FROM panol_prestamos WHERE estado='activo'
                GROUP BY item_id
            ) p ON p.item_id = i.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY i.categoria, i.nombre
        ");
        $stmt->execute($params);
        $items = $stmt->fetchAll();
        foreach ($items as &$it) {
            $it['stock_disponible'] = max(0, $it['stock_actual'] - $it['prestados']);
            if      ($it['stock_actual'] <= 0)                $it['estado_stock'] = 'sin_stock';
            elseif  ($it['stock_actual'] < $it['stock_minimo']) $it['estado_stock'] = 'bajo';
            elseif  ($it['prestados'] > 0)                    $it['estado_stock'] = 'en_prestamo';
            else                                               $it['estado_stock'] = 'ok';
        }
        unset($it);
        resp(200, ['items' => $items, 'total' => count($items)]);
        break;

    case 'panol_item':
        requireSession();
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) resp(400, ['error' => 'Falta id']);
        $stmt = $pdo->prepare("SELECT * FROM panol_items WHERE id=? AND activo=1");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if (!$item) resp(404, ['error' => 'Ítem no encontrado']);
        $stmt2 = $pdo->prepare("
            SELECT tipo, cantidad, stock_antes, stock_despues,
                   motivo, observaciones, created_at, usuario_nombre
            FROM panol_movimientos WHERE item_id=?
            ORDER BY created_at DESC LIMIT 10
        ");
        $stmt2->execute([$id]);
        $item['movimientos'] = $stmt2->fetchAll();
        $stmt3 = $pdo->prepare("
            SELECT id, usuario_nombre, cantidad, fecha_entrega, fecha_limite,
                   DATEDIFF(NOW(), fecha_entrega) AS dias_prestado
            FROM panol_prestamos WHERE item_id=? AND estado='activo'
            ORDER BY fecha_entrega DESC
        ");
        $stmt3->execute([$id]);
        $item['prestamos_activos'] = $stmt3->fetchAll();
        $item['prestados'] = array_sum(array_column($item['prestamos_activos'], 'cantidad'));
        resp(200, $item);
        break;

    case 'panol_prestamos':
        requireSession();
        $uid    = (int)($_GET['usuario_id'] ?? 0);
        $where  = ["p.estado='activo'"];
        $params = [];
        if ($uid) { $where[] = 'p.usuario_id=?'; $params[] = $uid; }
        $stmt = $pdo->prepare("
            SELECT p.id, p.item_id, i.numero_item, i.nombre AS item_nombre,
                   i.categoria, p.cantidad, p.usuario_id, p.usuario_nombre,
                   p.fecha_entrega, p.fecha_limite, p.encargado_nombre, p.observaciones,
                   DATEDIFF(NOW(), p.fecha_entrega) AS dias_prestado,
                   CASE WHEN p.fecha_limite IS NOT NULL AND p.fecha_limite < NOW() THEN 1 ELSE 0 END AS vencido
            FROM panol_prestamos p
            JOIN panol_items i ON i.id = p.item_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY p.fecha_entrega DESC
        ");
        $stmt->execute($params);
        resp(200, ['prestamos' => $stmt->fetchAll()]);
        break;

    case 'panol_solicitudes':
        requireSession();
        $estado = $_GET['estado'] ?? 'pendiente';
        $stmt = $pdo->prepare("
            SELECT s.id, s.item_id, i.numero_item, i.nombre AS item_nombre,
                   i.stock_actual, s.cantidad_solicitada,
                   s.usuario_id, s.usuario_nombre, s.motivo,
                   s.estado, s.created_at, s.encargado_nombre, s.observaciones_encargado
            FROM panol_solicitudes s
            JOIN panol_items i ON i.id = s.item_id
            WHERE s.estado=?
            ORDER BY s.created_at ASC
        ");
        $stmt->execute([$estado]);
        resp(200, ['solicitudes' => $stmt->fetchAll()]);
        break;

    case 'panol_historial':
        requireSession();
        $item_id = (int)($_GET['item_id'] ?? 0);
        $limit   = min((int)($_GET['limit'] ?? 80), 200);
        $where   = ['1=1'];
        $params  = [];
        if ($item_id) { $where[] = 'm.item_id=?'; $params[] = $item_id; }
        $stmt = $pdo->prepare("
            SELECT m.id, m.item_id, i.numero_item, i.nombre AS item_nombre,
                   m.tipo, m.cantidad, m.stock_antes, m.stock_despues,
                   m.motivo, m.observaciones, m.usuario_nombre,
                   m.referencia_solicitud_id, m.created_at
            FROM panol_movimientos m
            JOIN panol_items i ON i.id = m.item_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY m.created_at DESC
            LIMIT $limit
        ");
        $stmt->execute($params);
        resp(200, ['movimientos' => $stmt->fetchAll()]);
        break;

    case 'panol_kpis':
        requireSession();
        resp(200, [
            'total_items'            => (int)$pdo->query("SELECT COUNT(*) FROM panol_items WHERE activo=1")->fetchColumn(),
            'prestamos_activos'      => (int)$pdo->query("SELECT COUNT(*) FROM panol_prestamos WHERE estado='activo'")->fetchColumn(),
            'stock_bajo'             => (int)$pdo->query("SELECT COUNT(*) FROM panol_items WHERE activo=1 AND stock_actual < stock_minimo AND stock_actual > 0")->fetchColumn(),
            'sin_stock'              => (int)$pdo->query("SELECT COUNT(*) FROM panol_items WHERE activo=1 AND stock_actual = 0")->fetchColumn(),
            'solicitudes_pendientes' => (int)$pdo->query("SELECT COUNT(*) FROM panol_solicitudes WHERE estado='pendiente'")->fetchColumn(),
            'prestamos_vencidos'     => (int)$pdo->query("SELECT COUNT(*) FROM panol_prestamos WHERE estado='activo' AND fecha_limite IS NOT NULL AND fecha_limite < NOW()")->fetchColumn(),
        ]);
        break;

    case 'panol_nuevo_item':
        requireSession();
        if (!panolIsEncargado($pdo)) resp(403, ['error' => 'Sin permisos.']);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        foreach (['numero_item','nombre','categoria','stock_actual','stock_minimo'] as $f) {
            if (!isset($body[$f]) || $body[$f] === '') resp(400, ['error' => "Campo requerido: $f"]);
        }
        $chk = $pdo->prepare("SELECT id FROM panol_items WHERE numero_item=?");
        $chk->execute([trim($body['numero_item'])]);
        if ($chk->fetch()) resp(409, ['error' => 'El número de ítem ya existe.']);
        $pdo->prepare("
            INSERT INTO panol_items
                (numero_item, nombre, categoria, descripcion, ubicacion,
                 stock_actual, stock_minimo, unidad, activo, created_by)
            VALUES (?,?,?,?,?,?,?,?,1,?)
        ")->execute([
            trim($body['numero_item']),
            trim($body['nombre']),
            $body['categoria'],
            trim($body['descripcion'] ?? ''),
            trim($body['ubicacion']   ?? ''),
            (int)$body['stock_actual'],
            (int)$body['stock_minimo'],
            trim($body['unidad'] ?? 'unidad'),
            sessionUser()
        ]);
        $newId = (int)$pdo->lastInsertId();
        if ((int)$body['stock_actual'] > 0) {
            $pdo->prepare("
                INSERT INTO panol_movimientos
                    (item_id, tipo, cantidad, stock_antes, stock_despues,
                     motivo, usuario_id, usuario_nombre)
                VALUES (?,'ingreso',?,0,?,'Stock inicial al crear ítem',?,?)
            ")->execute([
                $newId, (int)$body['stock_actual'], (int)$body['stock_actual'],
                panolSessionUserId(), sessionUser()
            ]);
        }
        resp(201, ['ok' => true, 'id' => $newId, 'mensaje' => 'Ítem creado correctamente.']);
        break;

    case 'panol_editar_item':
        requireSession();
        if (!panolIsEncargado($pdo)) resp(403, ['error' => 'Sin permisos.']);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int)($body['id'] ?? 0);
        if (!$id) resp(400, ['error' => 'Falta id']);
        $pdo->prepare("
            UPDATE panol_items
            SET nombre=?, categoria=?, descripcion=?, ubicacion=?,
                stock_minimo=?, unidad=?, updated_at=NOW()
            WHERE id=?
        ")->execute([
            trim($body['nombre']       ?? ''),
            $body['categoria']          ?? '',
            trim($body['descripcion']  ?? ''),
            trim($body['ubicacion']    ?? ''),
            (int)($body['stock_minimo'] ?? 0),
            trim($body['unidad'] ?? 'unidad'),
            $id
        ]);
        resp(200, ['ok' => true, 'mensaje' => 'Ítem actualizado.']);
        break;

    case 'panol_solicitar':
        requireSession();
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $item_id  = (int)($body['item_id']  ?? 0);
        $cantidad = (int)($body['cantidad'] ?? 0);
        $motivo   = trim($body['motivo']    ?? '');
        if (!$item_id || $cantidad < 1) resp(400, ['error' => 'Datos incompletos.']);
        if (!$motivo)                   resp(400, ['error' => 'El motivo es obligatorio.']);
        $stmt = $pdo->prepare("SELECT id, stock_actual FROM panol_items WHERE id=? AND activo=1");
        $stmt->execute([$item_id]);
        $it = $stmt->fetch();
        if (!$it) resp(404, ['error' => 'Ítem no encontrado.']);
        if ($it['stock_actual'] < $cantidad) {
            resp(400, ['error' => "Stock insuficiente. Disponible: {$it['stock_actual']}"]);
        }
        $pdo->prepare("
            INSERT INTO panol_solicitudes
                (item_id, cantidad_solicitada, usuario_id, usuario_nombre, motivo, estado)
            VALUES (?,?,?,?,?,'pendiente')
        ")->execute([$item_id, $cantidad, panolSessionUserId(), sessionUser(), $motivo]);
        resp(201, ['ok' => true, 'mensaje' => 'Solicitud enviada. El encargado la procesará pronto.']);
        break;

    case 'panol_aprobar':
        requireSession();
        if (!panolIsEncargado($pdo)) resp(403, ['error' => 'Sin permisos.']);
        $body         = json_decode(file_get_contents('php://input'), true) ?? [];
        $solicitud_id = (int)($body['solicitud_id'] ?? 0);
        $obs          = trim($body['observaciones'] ?? '');
        $dias_limite  = (int)($body['dias_limite']  ?? 0);
        if (!$solicitud_id) resp(400, ['error' => 'Falta solicitud_id']);
        $stmt = $pdo->prepare("SELECT * FROM panol_solicitudes WHERE id=? AND estado='pendiente'");
        $stmt->execute([$solicitud_id]);
        $sol = $stmt->fetch();
        if (!$sol) resp(404, ['error' => 'Solicitud no encontrada o ya procesada.']);
        $itemRow = $pdo->prepare("SELECT stock_actual FROM panol_items WHERE id=? FOR UPDATE");
        $itemRow->execute([$sol['item_id']]);
        $it = $itemRow->fetch();
        if (!$it || $it['stock_actual'] < $sol['cantidad_solicitada']) {
            resp(400, ['error' => 'Stock insuficiente al momento de aprobar.']);
        }
        $pdo->beginTransaction();
        try {
            $antes   = $it['stock_actual'];
            $despues = $antes - $sol['cantidad_solicitada'];
            $pdo->prepare("UPDATE panol_items SET stock_actual=?, updated_at=NOW() WHERE id=?")
                ->execute([$despues, $sol['item_id']]);
            $fechaLimite = $dias_limite > 0
                ? date('Y-m-d H:i:s', strtotime("+$dias_limite days")) : null;
            $pdo->prepare("
                INSERT INTO panol_prestamos
                    (item_id, solicitud_id, usuario_id, usuario_nombre,
                     cantidad, fecha_entrega, fecha_limite, encargado_nombre,
                     observaciones, estado)
                VALUES (?,?,?,?,?,NOW(),?,?,?,'activo')
            ")->execute([
                $sol['item_id'], $solicitud_id,
                $sol['usuario_id'], $sol['usuario_nombre'],
                $sol['cantidad_solicitada'],
                $fechaLimite, sessionUser(), $obs
            ]);
            $pdo->prepare("
                INSERT INTO panol_movimientos
                    (item_id, tipo, cantidad, stock_antes, stock_despues,
                     motivo, observaciones, usuario_id, usuario_nombre,
                     referencia_solicitud_id)
                VALUES (?,'egreso',?,?,?,?,?,?,?,?)
            ")->execute([
                $sol['item_id'], $sol['cantidad_solicitada'], $antes, $despues,
                "Entrega a {$sol['usuario_nombre']}: {$sol['motivo']}",
                $obs, panolSessionUserId(), sessionUser(), $solicitud_id
            ]);
            $pdo->prepare("
                UPDATE panol_solicitudes
                SET estado='aprobada', encargado_nombre=?,
                    observaciones_encargado=?, updated_at=NOW()
                WHERE id=?
            ")->execute([sessionUser(), $obs, $solicitud_id]);
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            resp(500, ['error' => 'Error al procesar: ' . $e->getMessage()]);
        }
        resp(200, ['ok' => true, 'mensaje' => 'Solicitud aprobada y entrega registrada.']);
        break;

    case 'panol_rechazar':
        requireSession();
        if (!panolIsEncargado($pdo)) resp(403, ['error' => 'Sin permisos.']);
        $body           = json_decode(file_get_contents('php://input'), true) ?? [];
        $solicitud_id   = (int)($body['solicitud_id']  ?? 0);
        $motivo_rechazo = trim($body['motivo_rechazo'] ?? '');
        if (!$solicitud_id)   resp(400, ['error' => 'Falta solicitud_id']);
        if (!$motivo_rechazo) resp(400, ['error' => 'El motivo de rechazo es obligatorio.']);
        $stmt = $pdo->prepare("SELECT id FROM panol_solicitudes WHERE id=? AND estado='pendiente'");
        $stmt->execute([$solicitud_id]);
        if (!$stmt->fetch()) resp(404, ['error' => 'Solicitud no encontrada o ya procesada.']);
        $pdo->prepare("
            UPDATE panol_solicitudes
            SET estado='rechazada', encargado_nombre=?,
                observaciones_encargado=?, updated_at=NOW()
            WHERE id=?
        ")->execute([sessionUser(), $motivo_rechazo, $solicitud_id]);
        resp(200, ['ok' => true, 'mensaje' => 'Solicitud rechazada.']);
        break;

    case 'panol_devolucion':
        requireSession();
        $body         = json_decode(file_get_contents('php://input'), true) ?? [];
        $prestamo_id  = (int)($body['prestamo_id']      ?? 0);
        $estado_devol = $body['estado_devolucion'] ?? 'bueno';
        $obs          = trim($body['observaciones']  ?? '');
        if (!$prestamo_id) resp(400, ['error' => 'Falta prestamo_id']);
        $stmt = $pdo->prepare("SELECT * FROM panol_prestamos WHERE id=? AND estado='activo'");
        $stmt->execute([$prestamo_id]);
        $prest = $stmt->fetch();
        if (!$prest) resp(404, ['error' => 'Préstamo no encontrado o ya devuelto.']);
        $pdo->beginTransaction();
        try {
            $reponer = ($estado_devol !== 'perdido') ? $prest['cantidad'] : 0;
            $itemRow = $pdo->prepare("SELECT stock_actual FROM panol_items WHERE id=? FOR UPDATE");
            $itemRow->execute([$prest['item_id']]);
            $it      = $itemRow->fetch();
            $antes   = $it['stock_actual'];
            $despues = $antes + $reponer;
            if ($reponer > 0) {
                $pdo->prepare("UPDATE panol_items SET stock_actual=?, updated_at=NOW() WHERE id=?")
                    ->execute([$despues, $prest['item_id']]);
            }
            $pdo->prepare("
                INSERT INTO panol_movimientos
                    (item_id, tipo, cantidad, stock_antes, stock_despues,
                     motivo, observaciones, usuario_id, usuario_nombre)
                VALUES (?,'devolucion',?,?,?,?,?,?,?)
            ")->execute([
                $prest['item_id'], $prest['cantidad'], $antes, $despues,
                "Devolución de {$prest['usuario_nombre']} — estado: $estado_devol",
                $obs, panolSessionUserId(), sessionUser()
            ]);
            $pdo->prepare("
                UPDATE panol_prestamos
                SET estado='devuelto', fecha_devolucion=NOW(),
                    estado_devolucion=?, observaciones_devolucion=?, updated_at=NOW()
                WHERE id=?
            ")->execute([$estado_devol, $obs, $prestamo_id]);
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            resp(500, ['error' => 'Error al procesar: ' . $e->getMessage()]);
        }
        resp(200, ['ok' => true, 'mensaje' => 'Devolución registrada correctamente.']);
        break;

    case 'panol_ajuste_stock':
        requireSession();
        if (!panolIsEncargado($pdo)) resp(403, ['error' => 'Sin permisos.']);
        $body        = json_decode(file_get_contents('php://input'), true) ?? [];
        $item_id     = (int)($body['item_id']     ?? 0);
        $nuevo_stock = isset($body['nuevo_stock']) ? (int)$body['nuevo_stock'] : -1;
        $motivo      = trim($body['motivo']        ?? '');
        if (!$item_id || $nuevo_stock < 0) resp(400, ['error' => 'Datos incompletos.']);
        if (!$motivo)                       resp(400, ['error' => 'El motivo es obligatorio.']);
        $itemRow = $pdo->prepare("SELECT stock_actual FROM panol_items WHERE id=? FOR UPDATE");
        $itemRow->execute([$item_id]);
        $it = $itemRow->fetch();
        if (!$it) resp(404, ['error' => 'Ítem no encontrado.']);
        $antes     = $it['stock_actual'];
        $diferencia = $nuevo_stock - $antes;
        $tipo_mov  = $diferencia >= 0 ? 'ajuste_ingreso' : 'ajuste_egreso';
        $pdo->prepare("UPDATE panol_items SET stock_actual=?, updated_at=NOW() WHERE id=?")
            ->execute([$nuevo_stock, $item_id]);
        $pdo->prepare("
            INSERT INTO panol_movimientos
                (item_id, tipo, cantidad, stock_antes, stock_despues,
                 motivo, usuario_id, usuario_nombre)
            VALUES (?,?,?,?,?,?,?,?)
        ")->execute([
            $item_id, $tipo_mov, abs($diferencia), $antes, $nuevo_stock,
            "Ajuste manual: $motivo", panolSessionUserId(), sessionUser()
        ]);
        resp(200, ['ok' => true, 'mensaje' => "Stock ajustado de $antes a $nuevo_stock."]);
        break;
// ══════════════════════════════════════════════════════════════════
//  PARCHE — RECEPCIÓN DE TRANSPORTES
//  Pegar ANTES de:  default: resp(404, [...]);
// ══════════════════════════════════════════════════════════════════

case 'rec_verificar_patente':
    $patente = strtoupper(str_replace(' ', '', trim($body['patente'] ?? '')));
    $tipo    = $body['tipo'] ?? 'vehiculo';
    if (!$patente) resp(400, ['error' => 'Ingresá la patente.']);
    try {
        if ($tipo === 'vehiculo') {
            $stmt = $pdo->prepare("SELECT vehiculo_id, patente FROM transporte_vehiculo WHERE UPPER(REPLACE(patente,' ','')) = ? LIMIT 1");
        } else {
            $stmt = $pdo->prepare("SELECT acoplado_id AS vehiculo_id, patente FROM transporte_acoplado WHERE UPPER(REPLACE(patente,' ','')) = ? LIMIT 1");
        }
        $stmt->execute([$patente]);
        $row = $stmt->fetch();
        if (!$row) resp(404, ['error' => "La patente <strong>$patente</strong> no está registrada."]);
        resp(200, ['ok' => true, 'patente' => $row['patente']]);
    } catch (Exception $e) { resp(500, ['error' => $e->getMessage()]); }
    break;

case 'rec_verificar_conductor':
    $tipoDoc = (int)($body['tipo_doc'] ?? 0);
    $nroDoc  = trim($body['nro_doc']   ?? '');
    if (!$tipoDoc || !$nroDoc) resp(400, ['error' => 'Datos de documento incompletos.']);
    try {
        $stmt = $pdo->prepare("
            SELECT conductor_id, nombre, apellido, numero_celular
            FROM transporte_conductor
            WHERE tipo_documento_id = ? AND documento_numero = ?
            LIMIT 1
        ");
        $stmt->execute([$tipoDoc, $nroDoc]);
        $row = $stmt->fetch();
        if (!$row) resp(404, ['error' => 'Conductor no registrado. Consultá en portería.']);
        resp(200, [
            'ok'           => true,
            'conductor_id' => $row['conductor_id'],
            'nombre'       => trim($row['apellido'] . ' ' . $row['nombre']),
            'celular'      => $row['numero_celular'] ?? '',
        ]);
    } catch (Exception $e) { resp(500, ['error' => $e->getMessage()]); }
    break;

case 'rec_actualizar_celular':
    $conductorId = (int)($body['conductor_id'] ?? 0);
    $celular     = trim($body['celular']       ?? '');
    if (!$conductorId || !$celular) resp(400, ['error' => 'Datos incompletos.']);
    try {
        $pdo->prepare("UPDATE transporte_conductor SET numero_celular = ? WHERE conductor_id = ?")->execute([$celular, $conductorId]);
        resp(200, ['ok' => true]);
    } catch (Exception $e) { resp(500, ['error' => $e->getMessage()]); }
    break;

case 'rec_registrar_ingreso':
    $patente     = strtoupper(str_replace(' ', '', trim($body['patente']          ?? '')));
    $patenteAcop = strtoupper(str_replace(' ', '', trim($body['patente_acoplado'] ?? '')));
    $conductorId = (int)($body['conductor_id'] ?? 0);
    $planta      = in_array($body['planta'] ?? '', ['N','F']) ? $body['planta'] : 'N';
    $entrada     = isset($body['entrada']) ? (int)$body['entrada'] : 1;
    if (!$patente || !$conductorId) resp(400, ['error' => 'Datos incompletos.']);
    try {
        $pdo->beginTransaction();
        // Obtener vehiculo_id
        $stmtV = $pdo->prepare("SELECT vehiculo_id FROM transporte_vehiculo WHERE UPPER(REPLACE(patente,' ','')) = ? LIMIT 1");
        $stmtV->execute([$patente]);
        $vehiculoId = $stmtV->fetchColumn();
        if (!$vehiculoId) { $pdo->rollBack(); resp(404, ['error' => 'Vehículo no encontrado.']); }
        // Obtener acoplado_id (opcional)
        $acopladoId = null;
        if ($patenteAcop) {
            $stmtA = $pdo->prepare("SELECT acoplado_id FROM transporte_acoplado WHERE UPPER(REPLACE(patente,' ','')) = ? LIMIT 1");
            $stmtA->execute([$patenteAcop]);
            $acopladoId = $stmtA->fetchColumn() ?: null;
        }
        // Verificar si ya está en planta
        $stmtChk = $pdo->prepare("
            SELECT COUNT(*) FROM transporte_box tb
            JOIN transporte_carga tc ON tc.carga_numero = tb.carga_numero
            WHERE tb.estado IN ('A','U','P') AND tc.vehiculo = ? AND tc.tipo = ? AND tc.entrada = ?
        ");
        $stmtChk->execute([$vehiculoId, $planta, $entrada]);
        if ((int)$stmtChk->fetchColumn() > 0) { $pdo->rollBack(); resp(200, ['estado' => 'ya_ingresado']); }
        // Buscar carga planificada hoy ±1 día
        $stmtCarga = $pdo->prepare("
            SELECT carga_numero FROM transporte_carga
            WHERE vehiculo = ? AND estado = 'P' AND tipo = ? AND entrada = ?
              AND fecha_llegada_planeada >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
              AND fecha_llegada_planeada <  DATE_ADD(CURDATE(), INTERVAL 2 DAY)
            ORDER BY fecha_llegada_planeada ASC LIMIT 1
        ");
        $stmtCarga->execute([$vehiculoId, $planta, $entrada]);
        $cargaNumero = $stmtCarga->fetchColumn();
        $usuId = $_SESSION['cina_usuario_id'] ?? 0;
        if ($cargaNumero) {
            $pdo->prepare("
                UPDATE transporte_carga SET estado='E', conductor=?, acoplado=?,
                fecha_llegada=NOW(), usuario_id=?, updated_at=NOW()
                WHERE carga_numero=?
            ")->execute([$conductorId, $acopladoId, $usuId, $cargaNumero]);
        } else {
            $pdo->prepare("
                INSERT INTO transporte_carga
                    (vehiculo, acoplado, conductor, tipo, entrada, estado,
                     fecha_llegada, fecha_llegada_planeada, responsable_carga, usuario_id, created_at, updated_at)
                VALUES (?,?,?,?,?,'E',NOW(),CURDATE(),1,?,NOW(),NOW())
            ")->execute([$vehiculoId, $acopladoId, $conductorId, $planta, $entrada, $usuId]);
            $cargaNumero = (int)$pdo->lastInsertId();
        }
        // Buscar box libre
        $stmtBox = $pdo->prepare("
            SELECT id, numero FROM box
            WHERE activo=1 AND planta=? AND tipo='B'
              AND NOT EXISTS (SELECT 1 FROM transporte_box tb WHERE tb.estado IN ('A','U') AND tb.box_asignado_id=box.id)
            ORDER BY numero ASC LIMIT 1
        ");
        $stmtBox->execute([$planta]);
        $boxLibre = $stmtBox->fetch();
        if ($boxLibre) {
            $pdo->prepare("
                INSERT INTO transporte_box (carga_numero, box_asignado_id, estado, fecha_llegada, fecha_asignacion, prioridad, usuario_id)
                VALUES (?,?,'A',NOW(),NOW(),5,?)
            ")->execute([$cargaNumero, $boxLibre['id'], $usuId]);
            $pdo->commit();
            resp(200, ['ok'=>true, 'estado'=>'asignado', 'box_numero'=>$boxLibre['numero'], 'carga_numero'=>$cargaNumero]);
        } else {
            $pdo->prepare("
                INSERT INTO transporte_box (carga_numero, estado, prioridad, fecha_llegada, usuario_id)
                VALUES (?,'P',5,NOW(),?)
            ")->execute([$cargaNumero, $usuId]);
            $pdo->commit();
            resp(200, ['ok'=>true, 'estado'=>'en_espera', 'carga_numero'=>$cargaNumero]);
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        resp(500, ['error' => $e->getMessage()]);
    }
    break;
    
    // ═══════════════════════════════════════════════════════════════
    //  MAPA DE BOXES Y TRONERAS
    // ═══════════════════════════════════════════════════════════════

    case 'box_estado_general':
        requireSession();
        $planta = $_GET['planta'] ?? 'N';
        try {
            // Boxes con su estado actual
            $stmt = $pdo->prepare("
                SELECT b.id, b.numero, b.planta, b.tipo, b.fila, b.columna, b.grado, b.activo,
                       tb.estado AS box_estado, tb.carga_numero,
                       TC.entrada,
                       TV.patente
                FROM box b
                LEFT JOIN transporte_box tb ON tb.box_asignado_id = b.id AND tb.estado IN ('A','U')
                LEFT JOIN transporte_carga TC ON TC.carga_numero = tb.carga_numero
                LEFT JOIN transporte_vehiculo TV ON TV.vehiculo_id = TC.vehiculo
                WHERE b.planta = ? AND b.activo = 1
                ORDER BY b.numero
            ");
            $stmt->execute([$planta]);
            $boxes = $stmt->fetchAll();

            // Normalizar campos para el frontend
            foreach ($boxes as &$bx) {
                $bx['estado'] = $bx['carga_numero'] ? ($bx['entrada'] ? 'entrada' : 'salida') : 'libre';
                $bx['entrada'] = $bx['entrada'] ?? null;
                $bx['patente'] = $bx['patente'] ?? '';
            }
            unset($bx);

            // Troneras con su estado actual
            $stmt2 = $pdo->prepare("
                SELECT t.id, t.numero, t.tipo, t.fila, t.columna, t.grado, t.anden, t.estado AS tronera_estado,
                       tt.carga_numero,
                       TC.entrada,
                       TV.patente
                FROM tronera t
                LEFT JOIN transporte_tronera tt ON tt.tronera_asignada_id = t.id AND tt.estado IN ('A','U')
                LEFT JOIN transporte_carga TC ON TC.carga_numero = tt.carga_numero
                LEFT JOIN transporte_vehiculo TV ON TV.vehiculo_id = TC.vehiculo
                WHERE t.estado != 'I'
                ORDER BY t.numero
            ");
            $stmt2->execute();
            $troneras = $stmt2->fetchAll();

            foreach ($troneras as &$tr) {
                $tr['estado'] = $tr['carga_numero'] ? ($tr['entrada'] ? 'entrada' : 'salida') : 'L';
                $tr['entrada'] = $tr['entrada'] ?? null;
                $tr['patente'] = $tr['patente'] ?? '';
            }
            unset($tr);

            resp(200, ['ok'=>true, 'boxes'=>$boxes, 'troneras'=>$troneras]);
        } catch (Exception $e) {
            resp(500, ['error'=>$e->getMessage()]);
        }
        break;

    case 'boxes_disponibles':
        requireSession();
        $planta = $_GET['planta'] ?? 'N';
        try {
            $stmt = $pdo->prepare("
                SELECT b.id, b.numero, b.tipo
                FROM box b
                WHERE b.planta = ? AND b.activo = 1
                  AND b.id NOT IN (
                    SELECT tb.box_asignado_id FROM transporte_box tb
                    WHERE tb.estado IN ('A','U') AND tb.box_asignado_id IS NOT NULL
                  )
                ORDER BY b.numero
            ");
            $stmt->execute([$planta]);
            resp(200, ['ok'=>true, 'boxes'=>$stmt->fetchAll()]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'box_asignar':
        requireSession();
        $b = json_input();
        $cargaNumero = intval($b['carga_numero'] ?? 0);
        $boxId = intval($b['box_id'] ?? 0);
        if (!$cargaNumero || !$boxId) resp(400, ['error'=>'Faltan datos']);
        try {
            $pdo->beginTransaction();
            // Liberar box anterior si tiene uno
            $pdo->prepare("UPDATE transporte_box SET estado='L', fecha_fin_uso=NOW(), usuario_id=? WHERE carga_numero=? AND estado IN ('A','U')")
                ->execute([(int)$_SESSION['cina_usuario_id'], $cargaNumero]);
            // Asignar nuevo box
            $pdo->prepare("INSERT INTO transporte_box (carga_numero, estado, box_asignado_id, fecha_asignacion, fecha_llegada, usuario_id, created_at, updated_at) VALUES (?,'A',?,NOW(),NOW(),?,NOW(),NOW())")
                ->execute([$cargaNumero, $boxId, (int)$_SESSION['cina_usuario_id']]);
            // Actualizar estado de la carga a En Planta si estaba Planificado
            $pdo->prepare("UPDATE transporte_carga SET estado='E', updated_at=NOW() WHERE carga_numero=? AND estado='P'")
                ->execute([$cargaNumero]);
            $pdo->commit();
            $boxNum = $pdo->prepare("SELECT numero FROM box WHERE id=?"); $boxNum->execute([$boxId]);
            resp(200, ['ok'=>true, 'mensaje'=>'Box '.$boxNum->fetchColumn().' asignado a carga #'.$cargaNumero]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            resp(500, ['error'=>$e->getMessage()]);
        }
        break;

    case 'box_liberar':
        requireSession();
        $b = json_input();
        $boxNumero = intval($b['box_numero'] ?? 0);
        $cargaNumero = intval($b['carga_numero'] ?? 0);
        if (!$boxNumero && !$cargaNumero) resp(400, ['error'=>'Falta box_numero o carga_numero']);
        try {
            $pdo->beginTransaction();
            if ($boxNumero) {
                // Buscar el box por número y liberar
                $boxRow = $pdo->prepare("SELECT b.id, tb.carga_numero FROM box b INNER JOIN transporte_box tb ON tb.box_asignado_id=b.id AND tb.estado IN ('A','U') WHERE b.numero=? ORDER BY tb.id DESC LIMIT 1");
                $boxRow->execute([$boxNumero]); $bx = $boxRow->fetch();
                if (!$bx) { $pdo->rollBack(); resp(404, ['error'=>'Box no encontrado o ya liberado']); }
                $cargaNumero = (int)$bx['carga_numero'];
                $pdo->prepare("UPDATE transporte_box SET estado='L', fecha_fin_uso=NOW(), usuario_id=?, updated_at=NOW() WHERE box_asignado_id=? AND estado IN ('A','U')")
                    ->execute([(int)$_SESSION['cina_usuario_id'], $bx['id']]);
            } else {
                $pdo->prepare("UPDATE transporte_box SET estado='L', fecha_fin_uso=NOW(), usuario_id=?, updated_at=NOW() WHERE carga_numero=? AND estado IN ('A','U')")
                    ->execute([(int)$_SESSION['cina_usuario_id'], $cargaNumero]);
            }
            // También liberar tronera si tiene
            $pdo->prepare("UPDATE transporte_tronera SET estado='L', fecha_fin_uso=NOW(), usuario_id=?, updated_at=NOW() WHERE carga_numero=? AND estado IN ('A','U')")
                ->execute([(int)$_SESSION['cina_usuario_id'], $cargaNumero]);
            // Marcar carga como Retirado (salió de planta pero NO cerrada)
            if ($cargaNumero) {
                $pdo->prepare("UPDATE transporte_carga SET estado='R', fecha_salida=NOW(), updated_at=NOW() WHERE carga_numero=? AND estado IN ('E','C','D','F')")
                    ->execute([$cargaNumero]);
            }
            $pdo->commit();

            // Buscar qué box se liberó para reasignar al próximo en espera
            $nextMsg = '';
            $liberadoBoxId = null;
            if ($cargaNumero) {
                // Buscar el box que acabamos de liberar
                $bxLib = $pdo->prepare("SELECT tb.box_asignado_id FROM transporte_box tb WHERE tb.carga_numero=? AND tb.estado='L' ORDER BY tb.id DESC LIMIT 1");
                $bxLib->execute([$cargaNumero]); $liberadoBoxId = $bxLib->fetchColumn();
            }
            if ($liberadoBoxId) {
                $next = $pdo->prepare("SELECT tb.id, tb.carga_numero FROM transporte_box tb WHERE tb.estado='P' AND tb.box_asignado_id IS NULL ORDER BY tb.prioridad ASC, tb.fecha_llegada ASC LIMIT 1");
                $next->execute(); $nxt = $next->fetch();
                if ($nxt) {
                    $pdo->prepare("UPDATE transporte_box SET estado='A', box_asignado_id=?, fecha_asignacion=NOW(), usuario_id=?, updated_at=NOW() WHERE id=?")
                        ->execute([$liberadoBoxId, (int)$_SESSION['cina_usuario_id'], $nxt['id']]);
                    $pdo->prepare("UPDATE transporte_carga SET estado='E', updated_at=NOW() WHERE carga_numero=? AND estado='P'")
                        ->execute([$nxt['carga_numero']]);
                    $nextMsg = ' → Reasignado a carga #'.$nxt['carga_numero'];
                }
            }
            resp(200, ['ok'=>true, 'mensaje'=>'Box liberado. Camión marcado como Retirado.'.$nextMsg]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            resp(500, ['error'=>$e->getMessage()]);
        }
        break;

    case 'tronera_volver_a_box':
        // Libera la tronera y permite reasignar a un box
        requireSession();
        $b = json_input();
        $cargaNumero = intval($b['carga_numero'] ?? 0);
        if (!$cargaNumero) resp(400, ['error'=>'Falta carga_numero']);
        try {
            // Liberar tronera
            $pdo->prepare("UPDATE transporte_tronera SET estado='L', fecha_fin_uso=NOW(), usuario_id=?, updated_at=NOW() WHERE carga_numero=? AND estado IN ('A','U')")
                ->execute([(int)$_SESSION['cina_usuario_id'], $cargaNumero]);
            // Cambiar estado de carga a En Planta (vuelve al box)
            $pdo->prepare("UPDATE transporte_carga SET estado='E', updated_at=NOW() WHERE carga_numero=? AND estado IN ('C','R')")
                ->execute([$cargaNumero]);
            resp(200, ['ok'=>true, 'mensaje'=>'Tronera liberada. Carga vuelve a estado En Planta.']);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    // ═══════════════════════════════════════════════════════════════
    //  MÓDULO TRONERAS — Endpoints completos
    // ═══════════════════════════════════════════════════════════════

    case 'troneras_disponibles':
        requireSession();
        $tipo_planta = $_GET['tipo_planta'] ?? 'N';
        if (!in_array($tipo_planta, ['N', 'F'])) $tipo_planta = 'N';
        try {
            $stmt = $pdo->prepare("SELECT t.id, t.numero, t.anden, CASE t.anden WHEN 'N' THEN 'Norte' WHEN 'S' THEN 'Sur' WHEN 'F' THEN 'Frío' WHEN 'E' THEN 'Seco' ELSE t.anden END AS anden_texto FROM tronera t WHERE t.estado = 'A' AND t.tipo = ? AND NOT EXISTS (SELECT 1 FROM transporte_tronera tt WHERE tt.estado IN ('A','U') AND tt.tronera_asignada_id = t.id) ORDER BY t.numero ASC");
            $stmt->execute([$tipo_planta]);
            resp(200, $stmt->fetchAll());
        } catch (Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    case 'troneras_lista':
        requireSession();
        $planta = $_GET['planta'] ?? ''; $estado = $_GET['estado'] ?? '';
        try {
            $where = ['1=1']; $params = [];
            if ($planta !== '') { $where[] = 't.tipo = ?'; $params[] = $planta; }
            if ($estado !== '') { $where[] = 't.estado = ?'; $params[] = $estado; }
            $stmt = $pdo->prepare("SELECT t.*, tt.id AS tt_id, tt.estado AS tt_estado, tt.carga_numero AS tt_carga_numero, tt.fecha_asignacion, tt.fecha_inicio_uso FROM tronera t LEFT JOIN transporte_tronera tt ON tt.tronera_asignada_id = t.id AND tt.estado IN ('A','U') WHERE " . implode(' AND ', $where) . " ORDER BY t.tipo ASC, t.numero ASC");
            $stmt->execute($params);
            resp(200, $stmt->fetchAll());
        } catch (Exception $e) { resp(500, ['error' => $e->getMessage()]); }
        break;

    case 'usuarios_responsables':
        // GET: usuarios con RolID 1,4,6,7 para asignar como responsable
        requireSession();
        try {
            $stmt = $pdo->query("
                SELECT U.UsuarioID, CONCAT(P.apellido, ', ', P.nombre) AS nombre, U.UsuarioNombre
                FROM Usuarios U
                INNER JOIN persona P ON P.persona_id = U.persona_id
                WHERE U.activo = 1 AND U.RolID IN (1,4,6,7)
                ORDER BY P.apellido, P.nombre
            ");
            resp(200, ['ok'=>true, 'usuarios'=>$stmt->fetchAll()]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'tronera_asignar':
        requireSession();
        $b = json_input();
        $carga_numero = (int)($b['carga_numero'] ?? 0); $tronera_id = (int)($b['tronera_id'] ?? 0);
        if (!$carga_numero) resp(400, ['error'=>'Falta carga_numero']);
        if (!$tronera_id) resp(400, ['error'=>'Falta tronera_id']);
        try {
            $pdo->beginTransaction();
            $stmtT = $pdo->prepare("SELECT id, numero, estado, tipo FROM tronera WHERE id=? LIMIT 1"); $stmtT->execute([$tronera_id]); $tronera = $stmtT->fetch();
            if (!$tronera) { $pdo->rollBack(); resp(404, ['error'=>'Tronera no encontrada']); }
            if ($tronera['estado'] !== 'A') { $pdo->rollBack(); resp(400, ['error'=>'Tronera no activa']); }
            $stmtOcup = $pdo->prepare("SELECT COUNT(*) FROM transporte_tronera WHERE tronera_asignada_id=? AND estado IN ('A','U')"); $stmtOcup->execute([$tronera_id]);
            if ((int)$stmtOcup->fetchColumn() > 0) { $pdo->rollBack(); resp(409, ['error'=>'Tronera ya asignada']); }
            $stmtTT = $pdo->prepare("SELECT id, estado FROM transporte_tronera WHERE carga_numero=? ORDER BY id DESC LIMIT 1"); $stmtTT->execute([$carga_numero]); $ttEx = $stmtTT->fetch();
            if ($ttEx && in_array($ttEx['estado'], ['A','U'])) { $pdo->rollBack(); resp(400, ['error'=>'Carga ya tiene tronera']); }
            $pdo->prepare("INSERT INTO transporte_tronera (carga_numero, tronera_asignada_id, estado, fecha_asignacion, usuario_id, created_at, updated_at) VALUES (?,?,'A',NOW(),?,NOW(),NOW())")->execute([$carga_numero, $tronera_id, $_SESSION['cina_usuario_id']]);
            $nuevoTTId = (int)$pdo->lastInsertId();
            // Asignar responsable — puede venir como responsable_id (ResponsableID directo) o responsable_usuario_id (UsuarioID)
            $respDirecto = intval($b['responsable_id'] ?? 0);
            if ($respDirecto) {
                // Usar ResponsableID directamente (viene del catálogo de cargas)
                $pdo->prepare("UPDATE transporte_carga SET responsable_carga=? WHERE carga_numero=?")->execute([$respDirecto, $carga_numero]);
            } else {
                // Fallback: buscar por UsuarioID → persona_id → CargasResponsable
                $respUsuarioId = intval($b['responsable_usuario_id'] ?? 0) ?: (int)$_SESSION['cina_usuario_id'];
                $stmtPers = $pdo->prepare("SELECT persona_id FROM Usuarios WHERE UsuarioID=? LIMIT 1"); $stmtPers->execute([$respUsuarioId]); $personaId = $stmtPers->fetchColumn();
                if ($personaId) {
                    $stmtResp = $pdo->prepare("SELECT ResponsableID FROM CargasResponsable WHERE persona_id=? LIMIT 1"); $stmtResp->execute([$personaId]); $respId = $stmtResp->fetchColumn();
                    if (!$respId) { $pdo->prepare("INSERT INTO CargasResponsable (persona_id, Activo, is_responsable_cargas) VALUES (?,1,1)")->execute([$personaId]); $respId = (int)$pdo->lastInsertId(); }
                    $pdo->prepare("UPDATE transporte_carga SET responsable_carga=? WHERE carga_numero=?")->execute([$respId, $carga_numero]);
                }
            }
            $pdo->commit();
            resp(200, ['ok'=>true, 'transporte_tronera_id'=>$nuevoTTId, 'tronera_numero'=>$tronera['numero'], 'carga_numero'=>$carga_numero, 'mensaje'=>'Tronera '.$tronera['numero'].' asignada']);
        } catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'tronera_desasignar':
        requireSession();
        $b = json_input();
        $cn = (int)($b['carga_numero'] ?? 0); if (!$cn) resp(400, ['error'=>'Falta carga_numero']);
        try {
            $pdo->prepare("UPDATE transporte_tronera SET estado='C', usuario_id=?, updated_at=NOW() WHERE carga_numero=? AND estado IN ('A','U')")->execute([$_SESSION['cina_usuario_id'], $cn]);
            resp(200, ['ok'=>true, 'mensaje'=>'Asignación cancelada']);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'tronera_liberar':
        requireSession();
        $b = json_input();
        $cn = (int)($b['carga_numero'] ?? 0); $ttDirect = (int)($b['transporte_tronera_id'] ?? 0);
        if (!$cn && !$ttDirect) resp(400, ['error'=>'Falta carga_numero o transporte_tronera_id']);
        try {
            if ($ttDirect) { $pdo->prepare("UPDATE transporte_tronera SET estado='L', fecha_fin_uso=NOW(), usuario_id=?, updated_at=NOW() WHERE id=? AND estado IN ('A','U')")->execute([$_SESSION['cina_usuario_id'], $ttDirect]); }
            else { $pdo->prepare("UPDATE transporte_tronera SET estado='L', fecha_fin_uso=NOW(), usuario_id=?, updated_at=NOW() WHERE carga_numero=? AND estado IN ('A','U')")->execute([$_SESSION['cina_usuario_id'], $cn]); }
            resp(200, ['ok'=>true, 'mensaje'=>'Tronera liberada']);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'tronera_guardar':
        requireSession();
        $b = json_input();
        $id = (int)($b['id']??0); $numero = trim($b['numero']??''); $tipo = trim($b['tipo']??''); $anden = trim($b['anden']??'');
        if (!$numero) resp(400, ['error'=>'Número obligatorio']);
        try {
            if ($id) { $pdo->prepare("UPDATE tronera SET numero=?, tipo=?, anden=?, fila=?, columna=?, grado=?, estado=?, comentarios=? WHERE id=?")->execute([$numero,$tipo,$anden,(int)($b['fila']??0),(int)($b['columna']??0),(int)($b['grado']??0),trim($b['estado']??'A'),trim($b['comentarios']??''),$id]); resp(200, ['ok'=>true]); }
            else { $pdo->prepare("INSERT INTO tronera (numero,tipo,anden,fila,columna,grado,estado,comentarios,usuario_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())")->execute([$numero,$tipo,$anden,(int)($b['fila']??0),(int)($b['columna']??0),(int)($b['grado']??0),trim($b['estado']??'A'),trim($b['comentarios']??''),$_SESSION['cina_usuario_id']]); resp(201, ['ok'=>true, 'id'=>(int)$pdo->lastInsertId()]); }
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'tronera_historial':
        requireSession();
        $tId = (int)($_GET['tronera_id']??0); $cn = (int)($_GET['carga_numero']??0);
        if (!$tId && !$cn) resp(400, ['error'=>'Falta tronera_id o carga_numero']);
        try {
            $where = ['1=1']; $params = [];
            if ($tId) { $where[] = 'tt.tronera_asignada_id=?'; $params[] = $tId; }
            if ($cn) { $where[] = 'tt.carga_numero=?'; $params[] = $cn; }
            $stmt = $pdo->prepare("SELECT tt.*, t.numero AS tronera_numero, u.UsuarioNombre FROM transporte_tronera tt LEFT JOIN tronera t ON t.id=tt.tronera_asignada_id LEFT JOIN Usuarios u ON u.UsuarioID=tt.usuario_id WHERE ".implode(' AND ',$where)." ORDER BY tt.id DESC LIMIT 50");
            $stmt->execute($params);
            resp(200, $stmt->fetchAll());
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    // ── Iniciar / Finalizar operación de carga ────────────────────
    case 'carga_iniciar_operacion':
        requireSession();
        $b = json_input();
        $cn = intval($b['carga_numero'] ?? 0);
        if (!$cn) resp(400, ['error'=>'Falta carga_numero']);
        try {
            // Cambiar estado a Cargando, registrar usuario e inicio
            $pdo->prepare("UPDATE transporte_carga SET estado='C', hora_movimiento_inicio=CURTIME(), fecha_movimiento=CURDATE(), updated_at=NOW() WHERE carga_numero=? AND estado IN ('E','D')")
                ->execute([$cn]);
            // Liberar box
            $pdo->prepare("UPDATE transporte_box SET estado='L', fecha_fin_uso=NOW(), usuario_id=?, updated_at=NOW() WHERE carga_numero=? AND estado IN ('A','U')")
                ->execute([(int)$_SESSION['cina_usuario_id'], $cn]);
            // Cambiar tronera a En Uso
            $pdo->prepare("UPDATE transporte_tronera SET estado='U', fecha_inicio_uso=NOW(), usuario_id=?, updated_at=NOW() WHERE carga_numero=? AND estado='A'")
                ->execute([(int)$_SESSION['cina_usuario_id'], $cn]);
            resp(200, ['ok'=>true, 'mensaje'=>'Operación iniciada. Box liberado. Tronera en uso.']);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'carga_finalizar_operacion':
        requireSession();
        $b = json_input();
        $cn = intval($b['carga_numero'] ?? 0);
        $force = !empty($b['force']);
        if (!$cn) resp(400, ['error'=>'Falta carga_numero']);
        try {
            // Verificar escaneo de pre-salidas vinculadas (si las hay)
            if (!$force) {
                $psList = $pdo->prepare("SELECT PreSalidaID FROM PreSalidas WHERE carga_numero=? AND clasificada=0");
                $psList->execute([$cn]);
                $presalidaIds = $psList->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($presalidaIds)) {
                    $ph = implode(',', array_fill(0, count($presalidaIds), '?'));
                    $totalSt = $pdo->prepare("SELECT COUNT(*) FROM ProductoCargaSalida WHERE PreSalidaID IN ($ph)");
                    $totalSt->execute($presalidaIds);
                    $total = (int)$totalSt->fetchColumn();
                    if ($total > 0) {
                        $verifSt = $pdo->prepare("SELECT COUNT(*) FROM SalidaVerificacionScan WHERE carga_numero=? AND estado='V'");
                        $verifSt->execute([$cn]);
                        $verif = (int)$verifSt->fetchColumn();
                        if ($verif < $total) {
                            resp(200, ['ok'=>false, 'requiere_confirmacion'=>true,
                                'mensaje'=>"Verificación incompleta: $verif de $total items escaneados",
                                'progreso'=>['total'=>$total, 'verificados'=>$verif, 'porcentaje'=>round($verif/$total*100)]]);
                        }
                    }
                }
            }
            // Registrar hora fin y cambiar estado a Finalizado (F) — vehículo aún en planta
            $pdo->prepare("UPDATE transporte_carga SET estado='F', hora_movimiento_fin=CURTIME(), updated_at=NOW() WHERE carga_numero=? AND estado='C'")
                ->execute([$cn]);
            // Liberar tronera
            $pdo->prepare("UPDATE transporte_tronera SET estado='L', fecha_fin_uso=NOW(), usuario_id=?, updated_at=NOW() WHERE carga_numero=? AND estado='U'")
                ->execute([(int)$_SESSION['cina_usuario_id'], $cn]);
            resp(200, ['ok'=>true, 'mensaje'=>'Operación finalizada. Tronera liberada.']);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'carga_liberar_vehiculo':
        requireSession();
        $b = json_input();
        $cn = intval($b['carga_numero'] ?? 0);
        if (!$cn) resp(400, ['error'=>'Falta carga_numero']);
        try {
            // Marcar como Retirado y registrar fecha/hora de salida
            $pdo->prepare("UPDATE transporte_carga SET estado='R', fecha_salida=NOW(), updated_at=NOW() WHERE carga_numero=? AND estado='F'")
                ->execute([$cn]);
            resp(200, ['ok'=>true, 'mensaje'=>'Vehículo liberado. Salida registrada.']);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    // ═══════════════════════════════════════════════════════════════
    //  DETALLE ENTRADA: ver, productos, estibas, CRUD, clasificar
    // ═══════════════════════════════════════════════════════════════

    case 'entrada_ver':
        requireSession();
        $id = intval($_GET['id'] ?? -1);
        if ($id < 0) resp(400, ['error'=>'Falta parametro id en URL']);
        try {
            // Datos de la carga
            $st = $pdo->prepare("SELECT C.*, CL.ClienteNombre AS cliente, CT.descripcion AS tratamiento_nombre FROM Cargas C LEFT JOIN CLIENTES CL ON CL.ClienteID=C.ClienteID LEFT JOIN carga_tratamiento CT ON CT.carga_tratamiento_id=C.tratamiento_id WHERE C.CargaID=?");
            $st->execute([$id]); $reg = $st->fetch();
            if (!$reg) resp(404, ['error'=>'Entrada no encontrada']);

            // Catálogo de tratamientos
            try { $tratamientos = $pdo->query("SELECT carga_tratamiento_id AS id, descripcion FROM carga_tratamiento ORDER BY descripcion")->fetchAll(); } catch(Exception $e) { $tratamientos = []; }
            $reg['tratamientos_catalogo'] = $tratamientos;

            // Productos de la carga
            $sp = $pdo->prepare("
                SELECT PC.CargaID, PC.ProductosID, PC.Cantidad, PC.KilosUnidad, PC.kilos_bruto, PC.Congelado, PC.CertificadoID,
                       P.CodigoProducto AS codigo, P.Descripcion1 AS producto, P.Marca,
                       (SELECT COUNT(*) FROM ProductoCargaEstiba PCE WHERE PCE.CargaID=PC.CargaID AND PCE.ProductosID=PC.ProductosID) AS estibado
                FROM ProductosCarga PC
                INNER JOIN Productos P ON P.ProductosID=PC.ProductosID
                WHERE PC.CargaID=?
                ORDER BY P.Descripcion1
            ");
            $sp->execute([$id]); $productos = $sp->fetchAll();

            // Estibas con productos estibados
            $se = $pdo->prepare("
                SELECT E.EstibaID, E.NroEstiba, E.TiposEstibaID, TE.TipoEstibaNombre AS tipo_estiba,
                       E.PosicionID, E.Nivel, E.Abierta, E.FechaAlta, E.FechaBaja, E.estado AS estiba_estado,
                       CAM.CamaraNumero, POS.Calle, POS.Fila,
                       COALESCE(TCA.iniciales,'') AS camara_iniciales
                FROM Estiba E
                LEFT JOIN TiposEstiba TE ON TE.TiposEstibaID=E.TiposEstibaID
                LEFT JOIN Posiciones POS ON POS.PosicionID=E.PosicionID
                LEFT JOIN Camaras CAM ON CAM.CamaraID=POS.CamaraID
                LEFT JOIN TiposCamara TCA ON TCA.TiposCamaraID=CAM.TiposCamaraID
                WHERE E.CargaID=?
                  AND (E.FechaBaja IS NULL OR ? = 1)
                ORDER BY E.NroEstiba
            ");
            $esClasificada = (int)($reg['Clasificada'] ?? 0);
            $se->execute([$id, $esClasificada]); $estibas = $se->fetchAll();

            // Productos estibados por estiba (incluir con cantidad 0 para entradas viejas)
            // KilosUnidad se calcula desde PC.KilosUnidad/PC.Cantidad (total_kilos_carga / total_bultos_carga = kg por unidad).
            // Si no hay dato en ProductosCarga, fallback a P.peso_estandar del catálogo.
            $spe = $pdo->prepare("
                SELECT PCE.EstibaID, PCE.ProductosID, PCE.CargaID, PCE.FechaEV, PCE.Cantidad,
                       PCE.NroLote, PCE.FechaElaboracion, PCE.FechaVencimiento, PCE.FechaCongelado,
                       PCE.FechaFaena, PCE.NroTropa, PCE.nro_tambor,
                       P.CodigoProducto AS codigo, P.Descripcion1 AS producto,
                       COALESCE(
                         NULLIF(PC.KilosUnidad, 0) / NULLIF(PC.Cantidad, 0),
                         NULLIF(P.peso_estandar, 0),
                         0
                       ) AS KilosUnidad
                FROM ProductoCargaEstiba PCE
                INNER JOIN Productos P ON P.ProductosID=PCE.ProductosID
                LEFT JOIN ProductosCarga PC ON PC.CargaID=PCE.CargaID AND PC.ProductosID=PCE.ProductosID
                WHERE PCE.CargaID=?
                ORDER BY PCE.EstibaID, P.Descripcion1
            ");
            $spe->execute([$id]); $prodEstibados = $spe->fetchAll();

            // Agrupar prodEstibados por estiba
            $estibaProd = [];
            foreach ($prodEstibados as $pe) {
                $eid = $pe['EstibaID'];
                if (!isset($estibaProd[$eid])) $estibaProd[$eid] = [];
                if ($pe['FechaElaboracion']) $pe['FechaElaboracion'] = (new DateTime($pe['FechaElaboracion']))->format('d/m/Y');
                if ($pe['FechaVencimiento']) $pe['FechaVencimiento'] = (new DateTime($pe['FechaVencimiento']))->format('d/m/Y');
                if ($pe['FechaCongelado'])   $pe['FechaCongelado']   = (new DateTime($pe['FechaCongelado']))->format('d/m/Y');
                $estibaProd[$eid][] = $pe;
            }
            foreach ($estibas as &$est) {
                $est['productos'] = $estibaProd[$est['EstibaID']] ?? [];
                if ($est['PosicionID']) {
                    $camLabel = $est['camara_iniciales'] ?: "C{$est['CamaraNumero']}";
                    $est['ubicacion'] = "{$camLabel} {$est['Calle']}-{$est['Fila']}.{$est['Nivel']}";
                } else {
                    $est['ubicacion'] = null;
                }
            }
            unset($est);

            // Contar salidas vinculadas (para saber si se puede desclasificar)
            $ss = $pdo->prepare("SELECT COUNT(*) FROM ProductoCargaSalida WHERE CargaID=?");
            $ss->execute([$id]);
            $tieneSalidas = (int)$ss->fetchColumn() > 0;

            // Estibas intervenidas
            $estibasIntervenidas = [];
            try {
                $sei = $pdo->prepare("SELECT EI.*, IM.descripcion AS motivo_desc FROM Estiba_intervenidas EI LEFT JOIN intervencion_motivo IM ON IM.motivo_intervencion_id=EI.intervencion_motivo WHERE EI.CargaID=? AND EI.FechaBaja IS NULL ORDER BY EI.NroEstiba");
                $sei->execute([$id]);
                $estibasIntervenidas = $sei->fetchAll();
            } catch(Exception $e2) {}

            // Datos de transporte (estado, movimiento, tronera)
            $tcData = null;
            if ($reg['carga_numero']) {
                $stTC = $pdo->prepare("SELECT TC.estado AS tc_estado, TC.fecha_movimiento, TC.hora_movimiento_inicio, TC.hora_movimiento_fin FROM transporte_carga TC WHERE TC.carga_numero=?");
                $stTC->execute([(int)$reg['carga_numero']]);
                $tcData = $stTC->fetch();
                // Tronera asignada
                $stTR = $pdo->prepare("SELECT CONCAT('T', t.numero) FROM transporte_tronera tt JOIN tronera t ON t.id=tt.tronera_asignada_id WHERE tt.carga_numero=? AND tt.estado IN ('A','U') ORDER BY tt.id DESC LIMIT 1");
                $stTR->execute([(int)$reg['carga_numero']]);
                $reg['tronera'] = $stTR->fetchColumn() ?: null;
            }
            if ($tcData) {
                $reg['tc_estado'] = $tcData['tc_estado'];
                $reg['hora_movimiento_inicio'] = $tcData['hora_movimiento_inicio'];
                $reg['hora_movimiento_fin'] = $tcData['hora_movimiento_fin'];
                $reg['fecha_movimiento'] = $tcData['fecha_movimiento'];
            }

            // Tratamiento original del turno vinculado (para mostrar si cambió)
            $reg['tratamiento_turno_id'] = null;
            $reg['tratamiento_turno_nombre'] = null;
            if ($reg['carga_numero']) {
                try {
                    $stTT = $pdo->prepare("SELECT PT.tratamiento_id, CT.descripcion AS tratamiento_nombre FROM planificacion_turno PT LEFT JOIN carga_tratamiento CT ON CT.carga_tratamiento_id=PT.tratamiento_id WHERE PT.carga_numero=? AND PT.estado='asignado' LIMIT 1");
                    $stTT->execute([(int)$reg['carga_numero']]);
                    $turnoTrat = $stTT->fetch();
                    if ($turnoTrat) {
                        $reg['tratamiento_turno_id'] = $turnoTrat['tratamiento_id'];
                        $reg['tratamiento_turno_nombre'] = $turnoTrat['tratamiento_nombre'];
                    }
                } catch(Exception $eTT) {}
            }

            resp(200, ['ok'=>true, 'registro'=>$reg, 'productos'=>$productos, 'estibas'=>$estibas, 'estibas_intervenidas'=>$estibasIntervenidas, 'tiene_salidas'=>$tieneSalidas]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'entrada_catalogos':
        requireSession();
        $clienteId = intval($_GET['cliente_id'] ?? 0);
        try {
            // Auto-seed TiposEstiba si está vacía
            $countTipos = (int)$pdo->query("SELECT COUNT(*) FROM TiposEstiba")->fetchColumn();
            if ($countTipos === 0) {
                $pdo->exec("INSERT INTO TiposEstiba (TiposEstibaID, TipoEstibaNombre) VALUES
                    (1,'Pallet'),(2,'Bin'),(3,'Rack'),(4,'Granel'),(5,'Caja'),(6,'Bolsa'),(7,'Tambor'),(8,'Contenedor')");
            }
            // Productos del cliente (o todos si no se filtra)
            $prodWhere = $clienteId ? "WHERE P.Activo=1 AND EXISTS (SELECT 1 FROM ProductosCliente PCl WHERE PCl.ProductosID=P.ProductosID AND PCl.ClienteID=$clienteId)" : "WHERE P.Activo=1";
            $prods = $pdo->query("SELECT ProductosID AS id, CodigoProducto AS codigo, Descripcion1 AS nombre, peso_estandar FROM Productos P $prodWhere ORDER BY Descripcion1")->fetchAll();
            $tipos = $pdo->query("SELECT TiposEstibaID AS id, TipoEstibaNombre AS nombre FROM TiposEstiba ORDER BY TipoEstibaNombre")->fetchAll();
            $camaras = $pdo->query("SELECT C.CamaraID AS id, C.CamaraNumero AS numero, C.CamaraCalles AS calles, C.CamaraFilas AS filas, C.CamaraNiveles AS niveles, COALESCE(TC.iniciales,'') AS iniciales, COALESCE(TC.TiposCamaraNombre,'') AS tipo_nombre FROM Camaras C LEFT JOIN TiposCamara TC ON TC.TiposCamaraID=C.TiposCamaraID WHERE C.activo=1 ORDER BY C.CamaraNumero")->fetchAll();
            resp(200, ['ok'=>true, 'productos'=>$prods, 'tipos_estiba'=>$tipos, 'camaras'=>$camaras]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'entrada_agregar_producto':
        requireSession();
        $b = json_input();
        $cargaId = intval($b['CargaID'] ?? 0);
        $prodId  = intval($b['ProductosID'] ?? 0);
        if (!isset($b['CargaID']) || !isset($b['ProductosID'])) resp(400, ['error'=>'Faltan datos']);
        try {
            // Verificar no clasificada
            $chk = $pdo->prepare("SELECT Clasificada FROM Cargas WHERE CargaID=?"); $chk->execute([$cargaId]);
            if ((int)$chk->fetchColumn()) resp(400, ['error'=>'No se puede modificar una entrada clasificada']);
            // Verificar duplicado
            $dup = $pdo->prepare("SELECT COUNT(*) FROM ProductosCarga WHERE CargaID=? AND ProductosID=?");
            $dup->execute([$cargaId, $prodId]);
            if ((int)$dup->fetchColumn() > 0) resp(400, ['error'=>'El producto ya existe en esta entrada']);

            $pdo->prepare("INSERT INTO ProductosCarga (CargaID, ProductosID, Cantidad, KilosUnidad, Congelado) VALUES (?,?,?,?,?)")
                ->execute([$cargaId, $prodId, intval($b['Cantidad']??0), floatval($b['KilosUnidad']??0), intval($b['Congelado']??0)]);
            resp(201, ['ok'=>true]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'entrada_eliminar_producto':
        requireSession();
        $b = json_input();
        $cargaId = intval($b['CargaID'] ?? 0); $prodId = intval($b['ProductosID'] ?? 0);
        if (!isset($b['CargaID']) || !isset($b['ProductosID'])) resp(400, ['error'=>'Faltan datos']);
        try {
            $chk = $pdo->prepare("SELECT Clasificada FROM Cargas WHERE CargaID=?"); $chk->execute([$cargaId]);
            if ((int)$chk->fetchColumn()) resp(400, ['error'=>'No se puede modificar una entrada clasificada']);
            $est = $pdo->prepare("SELECT COUNT(*) FROM ProductoCargaEstiba WHERE CargaID=? AND ProductosID=?"); $est->execute([$cargaId,$prodId]);
            if ((int)$est->fetchColumn() > 0) resp(400, ['error'=>'El producto tiene estibas asignadas. Elimine primero el estibado.']);
            $pdo->prepare("DELETE FROM ProductosCarga WHERE CargaID=? AND ProductosID=?")->execute([$cargaId, $prodId]);
            resp(200, ['ok'=>true]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'entrada_importar_xml_twins':
        requireSession();
        if (!isset($_FILES['xml_file'])) resp(400, ['error'=>'No se recibió archivo XML']);
        $cargaId = intval($_POST['CargaID'] ?? 0);
        if (!$cargaId) resp(400, ['error'=>'Falta CargaID']);
        try {
            // Verificar no clasificada
            $chk = $pdo->prepare("SELECT Clasificada, ClienteID FROM Cargas WHERE CargaID=?"); $chk->execute([$cargaId]);
            $carga = $chk->fetch();
            if (!$carga) resp(404, ['error'=>'Entrada no encontrada']);
            if ((int)$carga['Clasificada']) resp(400, ['error'=>'No se puede modificar una entrada clasificada']);

            $xml = simplexml_load_file($_FILES['xml_file']['tmp_name']);
            if ($xml === false) resp(400, ['error'=>'El archivo no es un XML válido']);

            $items = $xml->GENERAL;
            if (!$items || count($items) === 0) resp(400, ['error'=>'El XML no contiene registros <GENERAL>']);

            // Agrupar items por PALLET
            $pallets = [];
            $sinPallet = 0;
            foreach ($items as $item) {
                $palletNum = trim((string)($item->PALLET ?? ''));
                if ($palletNum === '') { $sinPallet++; continue; }
                if (!isset($pallets[$palletNum])) $pallets[$palletNum] = [];
                $pallets[$palletNum][] = $item;
            }

            if (count($pallets) === 0) {
                resp(400, ['error'=>"No se encontraron items con número de PALLET asignado. $sinPallet items sin pallet fueron ignorados."]);
            }

            $pdo->beginTransaction();

            // Obtener último NroEstiba de esta carga
            $mx = $pdo->prepare("SELECT COALESCE(MAX(NroEstiba),0) FROM Estiba WHERE CargaID=?"); $mx->execute([$cargaId]);
            $ultimoNroEstiba = (int)$mx->fetchColumn();

            // TiposEstibaID=1 = Pallet
            $tipoEstibaPallet = 1;
            $insEstiba = $pdo->prepare("INSERT INTO Estiba (CargaID, NroEstiba, TiposEstibaID, Clasificada, Abierta, FechaAlta, estado) VALUES (?,?,?,1,0,NOW(),1)");
            $insPCE = $pdo->prepare("INSERT INTO ProductoCargaEstiba (CargaID, ProductosID, FechaEV, EstibaID, estiba_inicial_id, Cantidad, FechaElaboracion, FechaVencimiento, FechaCongelado, FechaFaena, NroTropa, NroLote) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");

            $estibasCreadas = [];
            $productosAgregados = [];
            $totalItems = 0;

            foreach ($pallets as $palletNum => $palletItems) {
                $ultimoNroEstiba++;
                $insEstiba->execute([$cargaId, $ultimoNroEstiba, $tipoEstibaPallet]);
                $estibaId = $pdo->lastInsertId();
                $estibasCreadas[] = ['EstibaID'=>$estibaId, 'NroEstiba'=>$ultimoNroEstiba, 'PalletXML'=>$palletNum];

                // Agrupar items del pallet por producto+fechas (misma combinación = sumar cantidad)
                $grupos = [];
                foreach ($palletItems as $it) {
                    $codProd = trim((string)$it->PRODUCTO);
                    $fProd = trim((string)($it->FECHAPRODUCCION ?? ''));
                    $fVenc = trim((string)($it->FECHAVENCIMIENTO ?? ''));
                    $fCong = trim((string)($it->FECHACONGELADO ?? ''));
                    $fFaena = trim((string)($it->FECHAFAENA ?? ''));
                    $tropa = trim((string)($it->TROPA ?? ''));
                    $lote = trim((string)($it->LOTE ?? ''));
                    $peso = floatval((string)($it->PESONETO ?? 0));

                    $key = "$codProd|$fProd|$fVenc|$fCong|$fFaena|$tropa|$lote";
                    if (!isset($grupos[$key])) {
                        $grupos[$key] = [
                            'codigo'=>$codProd, 'cantidad'=>0, 'peso_total'=>0,
                            'fProd'=>$fProd, 'fVenc'=>$fVenc, 'fCong'=>$fCong, 'fFaena'=>$fFaena,
                            'tropa'=>$tropa, 'lote'=>$lote
                        ];
                    }
                    $grupos[$key]['cantidad']++;
                    $grupos[$key]['peso_total'] += $peso;
                    $totalItems++;
                }

                // Insertar cada grupo en ProductoCargaEstiba
                $fechaEVSeq = 0;
                foreach ($grupos as $g) {
                    $fechaEVSeq++;
                    // Buscar ProductosID por CodigoProducto
                    $stP = $pdo->prepare("SELECT ProductosID FROM Productos WHERE CodigoProducto=? LIMIT 1");
                    $stP->execute([$g['codigo']]);
                    $prodId = (int)$stP->fetchColumn();
                    if (!$prodId) {
                        $pdo->rollBack();
                        resp(400, ['error'=>"Producto con código '{$g['codigo']}' no encontrado en la base de datos"]);
                    }

                    // Formatear fechas YYYYMMDD → YYYY-MM-DD
                    $fmtDate = function($d) {
                        if (!$d || $d === '0' || strlen($d) < 8) return null;
                        return substr($d,0,4).'-'.substr($d,4,2).'-'.substr($d,6,2);
                    };

                    $insPCE->execute([
                        $cargaId, $prodId, $fechaEVSeq, $estibaId, $estibaId,
                        $g['cantidad'],
                        $fmtDate($g['fProd']),
                        $fmtDate($g['fVenc']),
                        $fmtDate($g['fCong']),
                        $fmtDate($g['fFaena']),
                        $g['tropa'] ?: null,
                        $g['lote'] ?: null
                    ]);

                    // Acumular para ProductosCarga
                    if (!isset($productosAgregados[$prodId])) {
                        $productosAgregados[$prodId] = ['cantidad'=>0, 'kilos'=>0, 'kilos_bruto'=>0];
                    }
                    $productosAgregados[$prodId]['cantidad'] += $g['cantidad'];
                    $productosAgregados[$prodId]['kilos'] += $g['peso_total'];
                }
            }

            // Insertar/actualizar ProductosCarga (resumen por producto)
            foreach ($productosAgregados as $prodId => $totales) {
                $exPC = $pdo->prepare("SELECT COUNT(*) FROM ProductosCarga WHERE CargaID=? AND ProductosID=?");
                $exPC->execute([$cargaId, $prodId]);
                if ((int)$exPC->fetchColumn() > 0) {
                    $pdo->prepare("UPDATE ProductosCarga SET Cantidad=Cantidad+?, KilosUnidad=KilosUnidad+? WHERE CargaID=? AND ProductosID=?")
                        ->execute([$totales['cantidad'], $totales['kilos'], $cargaId, $prodId]);
                } else {
                    $pdo->prepare("INSERT INTO ProductosCarga (CargaID, ProductosID, Cantidad, KilosUnidad, Congelado) VALUES (?,?,?,?,0)")
                        ->execute([$cargaId, $prodId, $totales['cantidad'], $totales['kilos']]);
                }
            }

            $pdo->commit();
            resp(201, [
                'ok'=>true,
                'estibas_creadas'=>count($estibasCreadas),
                'items_procesados'=>$totalItems,
                'items_sin_pallet'=>$sinPallet,
                'estibas'=>$estibasCreadas,
                'productos'=>count($productosAgregados)
            ]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            resp(500, ['error'=>$e->getMessage()]);
        }
        break;

    case 'entrada_crear_estibas':
        requireSession();
        $b = json_input();
        $cargaId = intval($b['CargaID'] ?? 0); $tipoId = intval($b['TiposEstibaID'] ?? 0); $cant = intval($b['cantidad'] ?? 1);
        if (!isset($b['CargaID']) || !$tipoId || $cant < 1) resp(400, ['error'=>'Faltan datos']);
        try {
            $chk = $pdo->prepare("SELECT Clasificada FROM Cargas WHERE CargaID=?"); $chk->execute([$cargaId]);
            if ((int)$chk->fetchColumn()) resp(400, ['error'=>'No se puede modificar una entrada clasificada']);
            $mx = $pdo->prepare("SELECT COALESCE(MAX(NroEstiba),0) FROM Estiba WHERE CargaID=?"); $mx->execute([$cargaId]);
            $ultimo = (int)$mx->fetchColumn();
            $ins = $pdo->prepare("INSERT INTO Estiba (CargaID, NroEstiba, TiposEstibaID, Clasificada, Abierta, FechaAlta, estado) VALUES (?,?,?,1,0,NOW(),1)");
            $creadas = [];
            for ($i = 1; $i <= $cant; $i++) {
                $ins->execute([$cargaId, $ultimo + $i, $tipoId]);
                $creadas[] = ['EstibaID'=>$pdo->lastInsertId(), 'NroEstiba'=>$ultimo + $i];
            }
            resp(201, ['ok'=>true, 'creadas'=>count($creadas), 'estibas'=>$creadas, 'CargaID'=>$cargaId]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'estiba_datos':
        requireSession();
        $estibaId = $_GET['estiba_id'] ?? 0;
        if (!$estibaId) resp(400, ['error'=>'Falta estiba_id']);
        try {
            $se = $pdo->prepare("
                SELECT E.EstibaID, E.CargaID, E.NroEstiba, E.TiposEstibaID, E.PosicionID, E.Nivel,
                       TE.TipoEstibaNombre AS tipo_estiba,
                       CL.ClienteNombre AS cliente, CL.ClienteID,
                       C.Clasificada, C.es_fiscal,
                       POS.Calle, POS.Fila,
                       CAM.CamaraID, CAM.CamaraNumero,
                       COALESCE(TCA.iniciales,'') AS camara_iniciales,
                       COALESCE(TCA.TiposCamaraNombre,'') AS camara_tipo
                FROM Estiba E
                LEFT JOIN TiposEstiba TE ON TE.TiposEstibaID=E.TiposEstibaID
                INNER JOIN Cargas C ON C.CargaID=E.CargaID
                LEFT JOIN CLIENTES CL ON CL.ClienteID=C.ClienteID
                LEFT JOIN Posiciones POS ON POS.PosicionID=E.PosicionID
                LEFT JOIN Camaras CAM ON CAM.CamaraID=POS.CamaraID
                LEFT JOIN TiposCamara TCA ON TCA.TiposCamaraID=CAM.TiposCamaraID
                WHERE E.EstibaID=?
            ");
            $se->execute([$estibaId]); $estiba = $se->fetch();
            if (!$estiba) resp(404, ['error'=>'Estiba no encontrada']);

            // Productos cargados en esta estiba
            $sp = $pdo->prepare("
                SELECT PCE.ProductosID, PCE.FechaEV, PCE.Cantidad, PCE.NroLote,
                       PCE.FechaElaboracion, PCE.FechaVencimiento, PCE.FechaCongelado,
                       PCE.FechaFaena, PCE.NroTropa, PCE.nro_tambor,
                       P.CodigoProducto AS codigo, P.Descripcion1 AS producto, P.Marca,
                       CASE WHEN PCE.Cantidad = 0 THEN 1 ELSE 0 END AS intervenido
                FROM ProductoCargaEstiba PCE
                INNER JOIN Productos P ON P.ProductosID=PCE.ProductosID
                WHERE PCE.EstibaID=?
                ORDER BY PCE.Cantidad DESC, P.Descripcion1
            ");
            $sp->execute([$estibaId]); $productos = $sp->fetchAll();

            // Rango de vencimiento
            $vencMin = null; $vencMax = null;
            foreach ($productos as &$p) {
                if ($p['FechaVencimiento']) {
                    if (!$vencMin || $p['FechaVencimiento'] < $vencMin) $vencMin = $p['FechaVencimiento'];
                    if (!$vencMax || $p['FechaVencimiento'] > $vencMax) $vencMax = $p['FechaVencimiento'];
                    $p['FechaVencimiento'] = (new DateTime($p['FechaVencimiento']))->format('d/m/Y');
                }
                if ($p['FechaElaboracion']) $p['FechaElaboracion'] = (new DateTime($p['FechaElaboracion']))->format('d/m/Y');
                if ($p['FechaCongelado'])   $p['FechaCongelado']   = (new DateTime($p['FechaCongelado']))->format('d/m/Y');
                if ($p['FechaFaena'])       $p['FechaFaena']       = (new DateTime($p['FechaFaena']))->format('d/m/Y');
            }
            unset($p);

            // Catálogo de productos activos del cliente
            $clienteId = $estiba['ClienteID'];
            if ($clienteId) {
                $prods = $pdo->prepare("
                    SELECT P.ProductosID AS id, P.CodigoProducto AS codigo, P.Descripcion1 AS nombre,
                           P.peso_estandar, P.dias_vida_estandar, P.RequiereFechaElaboracion, P.RequiereFechaVencimiento,
                           P.RequiereFechaCongelacion, P.RequiereFechaFaena, P.RequiereNroTropa, P.RequiereNroLote
                    FROM Productos P
                    INNER JOIN ProductosCliente PC ON PC.ProductosID = P.ProductosID AND PC.ClienteID = ?
                    WHERE P.Activo=1
                    ORDER BY P.Descripcion1
                ");
                $prods->execute([$clienteId]);
            } else {
                $prods = $pdo->prepare("SELECT P.ProductosID AS id, P.CodigoProducto AS codigo, P.Descripcion1 AS nombre, P.peso_estandar FROM Productos P WHERE P.Activo=1 ORDER BY P.Descripcion1");
                $prods->execute();
            }

            resp(200, [
                'ok'=>true, 'estiba'=>$estiba, 'productos'=>$productos,
                'catalogo_productos'=>$prods->fetchAll(),
                'rango_vencimiento'=>['min'=>$vencMin ? (new DateTime($vencMin))->format('d/m/Y') : null, 'max'=>$vencMax ? (new DateTime($vencMax))->format('d/m/Y') : null]
            ]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'estiba_intervenida_datos':
        requireSession();
        $estibaId = $_GET['estiba_id'] ?? 0;
        if (!$estibaId) resp(400, ['error'=>'Falta estiba_id']);
        try {
            $se = $pdo->prepare("
                SELECT EI.EstibaID, EI.CargaID, EI.NroEstiba, EI.TiposEstibaID, EI.PosicionID, EI.Nivel,
                       EI.intervencion_motivo, EI.causa, EI.EstibaOriginalID,
                       TE.TipoEstibaNombre AS tipo_estiba,
                       CL.ClienteNombre AS cliente, CL.ClienteID,
                       C.Clasificada, C.es_fiscal,
                       IM.descripcion AS motivo_desc,
                       POS.Calle, POS.Fila,
                       CAM.CamaraID, CAM.CamaraNumero,
                       COALESCE(TCA.iniciales,'') AS camara_iniciales
                FROM Estiba_intervenidas EI
                LEFT JOIN TiposEstiba TE ON TE.TiposEstibaID=EI.TiposEstibaID
                INNER JOIN Cargas C ON C.CargaID=EI.CargaID
                LEFT JOIN CLIENTES CL ON CL.ClienteID=C.ClienteID
                LEFT JOIN intervencion_motivo IM ON IM.motivo_intervencion_id=EI.intervencion_motivo
                LEFT JOIN Posiciones POS ON POS.PosicionID=EI.PosicionID
                LEFT JOIN Camaras CAM ON CAM.CamaraID=POS.CamaraID
                LEFT JOIN TiposCamara TCA ON TCA.TiposCamaraID=CAM.TiposCamaraID
                WHERE EI.EstibaID=? AND EI.FechaBaja IS NULL
            ");
            $se->execute([$estibaId]); $estiba = $se->fetch();
            if (!$estiba) resp(404, ['error'=>'Estiba intervenida no encontrada']);
            $estiba['intervenida'] = true;

            // Productos intervenidos desde intervencion_estiba_producto
            $productos = [];
            try {
                $sp = $pdo->prepare("
                    SELECT IEP.producto_id AS ProductosID, IEP.cantidad_intervenido AS Cantidad,
                           P.CodigoProducto AS codigo, P.Descripcion1 AS producto,
                           PCE.NroLote, PCE.FechaElaboracion, PCE.FechaVencimiento, PCE.FechaCongelado, PCE.NroTropa
                    FROM intervencion_estiba_producto IEP
                    INNER JOIN Productos P ON P.ProductosID = IEP.producto_id
                    LEFT JOIN ProductoCargaEstiba PCE ON PCE.CargaID = IEP.carga_id AND PCE.ProductosID = IEP.producto_id AND PCE.EstibaID = IEP.estiba_id
                    WHERE IEP.estiba_id = ? AND IEP.carga_id = ? AND IEP.estado = 'I'
                    ORDER BY P.Descripcion1
                ");
                $sp->execute([$estiba['EstibaOriginalID'], $estiba['CargaID']]);
                $productos = $sp->fetchAll();
                foreach ($productos as &$pp) {
                    if (!empty($pp['FechaVencimiento'])) $pp['FechaVencimiento'] = (new DateTime($pp['FechaVencimiento']))->format('d/m/Y');
                    if (!empty($pp['FechaElaboracion'])) $pp['FechaElaboracion'] = (new DateTime($pp['FechaElaboracion']))->format('d/m/Y');
                    if (!empty($pp['FechaCongelado']))   $pp['FechaCongelado']   = (new DateTime($pp['FechaCongelado']))->format('d/m/Y');
                }
                unset($pp);
            } catch(Exception $e2) {}

            resp(200, ['ok'=>true, 'estiba'=>$estiba, 'productos'=>$productos]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'estiba_cargar_producto':
        requireSession();
        $b = json_input();
        $estibaId = $b['EstibaID'] ?? 0;
        $cargaId = intval($b['CargaID'] ?? 0);
        $prodId = intval($b['ProductosID'] ?? 0);
        $cant = intval($b['Cantidad'] ?? 0);
        if (!$estibaId || !isset($b['CargaID']) || !isset($b['ProductosID']) || !$cant) resp(400, ['error'=>'Faltan datos obligatorios']);
        try {
            // Verificar no clasificada
            $chk = $pdo->prepare("SELECT Clasificada FROM Cargas WHERE CargaID=?"); $chk->execute([$cargaId]);
            if ((int)$chk->fetchColumn()) resp(400, ['error'=>'Entrada clasificada, no se puede modificar']);

            $fechaEV = intval($b['FechaEV'] ?? 0);
            if (!$fechaEV) {
                // FechaEV es SMALLINT: generar secuencial por CargaID+ProductosID+EstibaID
                $maxFEV = $pdo->prepare("SELECT COALESCE(MAX(FechaEV),0) FROM ProductoCargaEstiba WHERE CargaID=? AND ProductosID=? AND EstibaID=?");
                $maxFEV->execute([$cargaId, $prodId, $estibaId]);
                $fechaEV = ((int)$maxFEV->fetchColumn()) + 1;
            }

            // PRIMERO: asegurar que existe ProductosCarga (FK requerida)
            $exPC = $pdo->prepare("SELECT COUNT(*) FROM ProductosCarga WHERE CargaID=? AND ProductosID=?");
            $exPC->execute([$cargaId, $prodId]);
            if ((int)$exPC->fetchColumn() === 0) {
                $pesoEst = $pdo->prepare("SELECT COALESCE(peso_estandar,0) FROM Productos WHERE ProductosID=?");
                $pesoEst->execute([$prodId]);
                $peso = $pesoEst->fetchColumn() ?: 0;
                $pdo->prepare("INSERT INTO ProductosCarga (CargaID, ProductosID, Cantidad, KilosUnidad) VALUES (?,?,0,?)")
                    ->execute([$cargaId, $prodId, $peso]);
            }

            // DESPUÉS: insertar o actualizar ProductoCargaEstiba
            $ex = $pdo->prepare("SELECT COUNT(*) FROM ProductoCargaEstiba WHERE CargaID=? AND ProductosID=? AND FechaEV=? AND EstibaID=?");
            $ex->execute([$cargaId, $prodId, $fechaEV, $estibaId]);

            if ((int)$ex->fetchColumn() > 0) {
                $pdo->prepare("UPDATE ProductoCargaEstiba SET Cantidad=?, NroLote=?, FechaElaboracion=?, FechaVencimiento=?, FechaCongelado=?, FechaFaena=?, NroTropa=?, nro_tambor=? WHERE CargaID=? AND ProductosID=? AND FechaEV=? AND EstibaID=?")
                    ->execute([$cant, $b['NroLote']??null, $b['FechaElaboracion']??null, $b['FechaVencimiento']??null, $b['FechaCongelado']??null, $b['FechaFaena']??null, $b['NroTropa']??null, $b['nro_tambor']??null, $cargaId, $prodId, $fechaEV, $estibaId]);
            } else {
                $pdo->prepare("INSERT INTO ProductoCargaEstiba (CargaID, ProductosID, FechaEV, EstibaID, estiba_inicial_id, Cantidad, NroLote, FechaElaboracion, FechaVencimiento, FechaCongelado, FechaFaena, NroTropa, nro_tambor) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$cargaId, $prodId, $fechaEV, $estibaId, $estibaId, $cant, $b['NroLote']??null, $b['FechaElaboracion']??null, $b['FechaVencimiento']??null, $b['FechaCongelado']??null, $b['FechaFaena']??null, $b['NroTropa']??null, $b['nro_tambor']??null]);
            }

            // Sync totales en ProductosCarga
            $pdo->prepare("
                UPDATE ProductosCarga SET Cantidad = (
                    SELECT COALESCE(SUM(PCE.Cantidad),0) FROM ProductoCargaEstiba PCE WHERE PCE.CargaID=? AND PCE.ProductosID=?
                ) WHERE CargaID=? AND ProductosID=?
            ")->execute([$cargaId, $prodId, $cargaId, $prodId]);

            resp(200, ['ok'=>true]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'estiba_eliminar_producto':
        requireSession();
        $b = json_input();
        $cargaId = intval($b['CargaID']??0); $prodId = intval($b['ProductosID']??0);
        $fechaEV = intval($b['FechaEV']??0); $estibaId = $b['EstibaID']??0;
        if (!isset($b['CargaID']) || !isset($b['ProductosID']) || !$estibaId) resp(400, ['error'=>'Faltan datos']);
        try {
            $pdo->prepare("DELETE FROM ProductoCargaEstiba WHERE CargaID=? AND ProductosID=? AND FechaEV=? AND EstibaID=?")
                ->execute([$cargaId, $prodId, $fechaEV, $estibaId]);

            // Re-sync ProductosCarga
            $rem = $pdo->prepare("SELECT SUM(Cantidad) FROM ProductoCargaEstiba WHERE CargaID=? AND ProductosID=?");
            $rem->execute([$cargaId, $prodId]); $total = (int)$rem->fetchColumn();
            if ($total > 0) {
                $pdo->prepare("UPDATE ProductosCarga SET Cantidad=? WHERE CargaID=? AND ProductosID=?")->execute([$total, $cargaId, $prodId]);
            } else {
                $pdo->prepare("DELETE FROM ProductosCarga WHERE CargaID=? AND ProductosID=?")->execute([$cargaId, $prodId]);
            }
            resp(200, ['ok'=>true]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'entrada_eliminar_estiba':
        requireSession();
        $b = json_input();
        $estibaId = $b['EstibaID'] ?? 0;
        if (!$estibaId) resp(400, ['error'=>'Falta EstibaID']);
        try {
            $pe = $pdo->prepare("SELECT COUNT(*) FROM ProductoCargaEstiba WHERE EstibaID=? AND Cantidad>0"); $pe->execute([$estibaId]);
            if ((int)$pe->fetchColumn() > 0) resp(400, ['error'=>'La estiba tiene productos. Elimine primero los productos estibados.']);
            $pdo->prepare("DELETE FROM Estiba WHERE EstibaID=?")->execute([$estibaId]);
            resp(200, ['ok'=>true]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'entrada_buscar_posicion':
        requireSession();
        $camId = intval($_GET['camara_id']??0); $calle = intval($_GET['calle']??0); $fila = intval($_GET['fila']??0);
        try {
            $st = $pdo->prepare("SELECT PosicionID FROM Posiciones WHERE CamaraID=? AND Calle=? AND Fila=? AND Habilitado=1 LIMIT 1");
            $st->execute([$camId, $calle, $fila]); $posId = $st->fetchColumn();
            resp(200, ['ok'=>true, 'posicion_id'=>$posId?:null]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'entrada_ubicar_estiba':
        requireSession();
        $b = json_input();
        $estibaId = $b['EstibaID'] ?? 0; $posId = intval($b['PosicionID'] ?? 0); $nivel = intval($b['Nivel'] ?? 1);
        $intervenida = !empty($b['intervenida']);
        $userId = (int)($_SESSION['cina_usuario_id'] ?? 0);
        if (!$estibaId || !$posId) resp(400, ['error'=>'Faltan datos (EstibaID='.json_encode($estibaId).', PosicionID='.$posId.', intervenida='.($intervenida?'true':'false').')']);
        try {
            // Asegurar tabla historial (fuera de transacción — DDL causa commit implícito)
            if ($intervenida) {
                try { $pdo->exec("CREATE TABLE IF NOT EXISTS estiba_intervenida_posicion (
                    estiba_id DOUBLE NOT NULL, ubicacion SMALLINT(5) UNSIGNED NOT NULL,
                    posicion_id INT(10) UNSIGNED NOT NULL, nivel SMALLINT(5) UNSIGNED NOT NULL,
                    fecha_posicion DATETIME DEFAULT NULL, usuario_id INT(10) UNSIGNED DEFAULT NULL,
                    PRIMARY KEY (estiba_id, ubicacion)
                ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci"); } catch(Exception $e2) {}
            }

            $pdo->beginTransaction();

            if ($intervenida) {
                // Obtener siguiente ubicacion secuencial
                $stMax = $pdo->prepare("SELECT COALESCE(MAX(ubicacion),0)+1 FROM estiba_intervenida_posicion WHERE estiba_id=?");
                $stMax->execute([$estibaId]);
                $nextUbic = (int)$stMax->fetchColumn();

                // Insertar historial de posición
                $pdo->prepare("INSERT INTO estiba_intervenida_posicion (estiba_id, ubicacion, posicion_id, nivel, fecha_posicion, usuario_id) VALUES (?,?,?,?,NOW(),?)")
                    ->execute([$estibaId, $nextUbic, $posId, $nivel, $userId]);

                // Actualizar posición actual
                $pdo->prepare("UPDATE Estiba_intervenidas SET PosicionID=?, Nivel=? WHERE EstibaID=?")->execute([$posId, $nivel, $estibaId]);
            } else {
                // Obtener siguiente ubicacion secuencial
                $stMax = $pdo->prepare("SELECT COALESCE(MAX(ubicacion),0)+1 FROM estiba_posicion WHERE estiba_id=?");
                $stMax->execute([$estibaId]);
                $nextUbic = (int)$stMax->fetchColumn();

                // Insertar historial de posición
                $pdo->prepare("INSERT INTO estiba_posicion (estiba_id, ubicacion, posicion_id, nivel, fecha_posicion, usuario_id) VALUES (?,?,?,?,NOW(),?)")
                    ->execute([$estibaId, $nextUbic, $posId, $nivel, $userId]);

                // Actualizar posición actual
                $pdo->prepare("UPDATE Estiba SET PosicionID=?, Nivel=? WHERE EstibaID=?")->execute([$posId, $nivel, $estibaId]);
            }

            $pdo->commit();
            resp(200, ['ok'=>true]);
        } catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'entrada_estibar':
        requireSession();
        $b = json_input();
        $cargaId = intval($b['CargaID']??0); $prodId = intval($b['ProductosID']??0);
        $estibaId = $b['EstibaID']??0; $cant = intval($b['Cantidad']??0); $fechaEV = intval($b['FechaEV']??0);
        if (!isset($b['CargaID']) || !isset($b['ProductosID']) || !$estibaId || !$cant) resp(400, ['error'=>'Faltan datos']);
        if (!$fechaEV) {
                // FechaEV es SMALLINT: generar secuencial por CargaID+ProductosID+EstibaID
                $maxFEV = $pdo->prepare("SELECT COALESCE(MAX(FechaEV),0) FROM ProductoCargaEstiba WHERE CargaID=? AND ProductosID=? AND EstibaID=?");
                $maxFEV->execute([$cargaId, $prodId, $estibaId]);
                $fechaEV = ((int)$maxFEV->fetchColumn()) + 1;
            } // Generar FechaEV si no viene
        try {
            $chk = $pdo->prepare("SELECT Clasificada FROM Cargas WHERE CargaID=?"); $chk->execute([$cargaId]);
            if ((int)$chk->fetchColumn()) resp(400, ['error'=>'No se puede modificar una entrada clasificada']);
            // Verificar si ya existe
            $ex = $pdo->prepare("SELECT COUNT(*) FROM ProductoCargaEstiba WHERE CargaID=? AND ProductosID=? AND FechaEV=? AND EstibaID=?");
            $ex->execute([$cargaId, $prodId, $fechaEV, $estibaId]);
            if ((int)$ex->fetchColumn() > 0) {
                $pdo->prepare("UPDATE ProductoCargaEstiba SET Cantidad=?, NroLote=?, FechaElaboracion=?, FechaVencimiento=?, FechaCongelado=?, FechaFaena=?, NroTropa=?, nro_tambor=? WHERE CargaID=? AND ProductosID=? AND FechaEV=? AND EstibaID=?")
                    ->execute([$cant, $b['NroLote']??null, $b['FechaElaboracion']??null, $b['FechaVencimiento']??null, $b['FechaCongelado']??null, $b['FechaFaena']??null, $b['NroTropa']??null, $b['nro_tambor']??null, $cargaId, $prodId, $fechaEV, $estibaId]);
            } else {
                $pdo->prepare("INSERT INTO ProductoCargaEstiba (CargaID, ProductosID, FechaEV, EstibaID, estiba_inicial_id, Cantidad, NroLote, FechaElaboracion, FechaVencimiento, FechaCongelado, FechaFaena, NroTropa, nro_tambor) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$cargaId, $prodId, $fechaEV, $estibaId, $estibaId, $cant, $b['NroLote']??null, $b['FechaElaboracion']??null, $b['FechaVencimiento']??null, $b['FechaCongelado']??null, $b['FechaFaena']??null, $b['NroTropa']??null, $b['nro_tambor']??null]);
            }
            resp(200, ['ok'=>true]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'entrada_eliminar_estibado':
        requireSession();
        $b = json_input();
        try {
            $pdo->prepare("DELETE FROM ProductoCargaEstiba WHERE CargaID=? AND ProductosID=? AND FechaEV=? AND EstibaID=?")
                ->execute([intval($b['CargaID']??0), intval($b['ProductosID']??0), intval($b['FechaEV']??0), $b['EstibaID']??0]);
            resp(200, ['ok'=>true]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    // ═══════════════════════════════════════════════════════════════
    //  ESTIBAS INTERVENIDAS
    // ═══════════════════════════════════════════════════════════════

    case 'estiba_intervenir':
        requireSession();
        $b = json_input();
        $cargaId = intval($b['CargaID'] ?? 0);
        $estibaId = $b['EstibaID'] ?? 0;
        $modo = $b['modo'] ?? 'completa'; // completa | parcial | carga_completa
        $cantidad = intval($b['cantidad'] ?? 0);
        $motivoId = intval($b['motivo_intervencion'] ?? 0);
        $causa = $b['causa'] ?? '';

        try {
            // Auto-crear tabla si no existe
            $pdo->exec("CREATE TABLE IF NOT EXISTS Estiba_intervenidas (
                EstibaID DOUBLE NOT NULL AUTO_INCREMENT,
                CargaID INT(10) UNSIGNED NOT NULL,
                EstibaOriginalID DOUBLE NOT NULL,
                NroEstiba INT(10) UNSIGNED,
                TiposEstibaID SMALLINT(5) UNSIGNED NOT NULL DEFAULT 1,
                PosicionID INT(10) UNSIGNED DEFAULT NULL,
                Nivel SMALLINT(5) UNSIGNED DEFAULT NULL,
                FechaAlta DATETIME DEFAULT NOW(),
                FechaBaja DATETIME DEFAULT NULL,
                estado TINYINT(3) UNSIGNED DEFAULT 1,
                intervencion_motivo TINYINT UNSIGNED,
                causa TEXT,
                usuario_id INT UNSIGNED,
                PRIMARY KEY (EstibaID)
            ) ENGINE=InnoDB");

            $pdo->beginTransaction();

            if ($modo === 'carga_completa') {
                // Intervenir todas las estibas de la carga
                $pdo->prepare("UPDATE Cargas SET estado_carga = 2, updated_at = NOW(), usuario_id = ? WHERE CargaID = ?")
                    ->execute([(int)$_SESSION['cina_usuario_id'], $cargaId]);
                $pdo->commit();
                resp(200, ['ok'=>true, 'mensaje'=>'Carga completa en cuarentena']);
            }

            // Obtener estiba original
            $stE = $pdo->prepare("SELECT * FROM Estiba WHERE EstibaID = ?");
            $stE->execute([$estibaId]); $estibaOrig = $stE->fetch();
            if (!$estibaOrig) { $pdo->rollBack(); resp(404, ['error'=>'Estiba no encontrada']); }

            // Obtener NroEstiba para la intervenida
            $maxNro = $pdo->prepare("SELECT COALESCE(MAX(NroEstiba),0) FROM Estiba_intervenidas WHERE CargaID = ?");
            $maxNro->execute([$cargaId]); $nroInt = (int)$maxNro->fetchColumn() + 1;

            // Crear estiba intervenida
            $pdo->prepare("INSERT INTO Estiba_intervenidas (CargaID, EstibaOriginalID, NroEstiba, TiposEstibaID, PosicionID, Nivel, intervencion_motivo, causa, usuario_id) VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$cargaId, $estibaId, $nroInt, $estibaOrig['TiposEstibaID'], $estibaOrig['PosicionID'], $estibaOrig['Nivel'], $motivoId, $causa, (int)$_SESSION['cina_usuario_id']]);
            $newEstibaIntId = $pdo->lastInsertId();

            if ($modo === 'completa') {
                // Mover TODOS los productos de la estiba original a la intervenida
                // Los productos quedan referenciados en intervencion_estiba_producto
                $prods = $pdo->prepare("SELECT * FROM ProductoCargaEstiba WHERE EstibaID = ? AND CargaID = ? AND Cantidad > 0");
                $prods->execute([$estibaId, $cargaId]);
                foreach ($prods->fetchAll() as $p) {
                    // Registrar intervención
                    $pdo->prepare("INSERT INTO intervencion_estiba_producto (carga_id, producto_id, fecha_ev, estiba_id, estado, cantidad_intervenido, causa, motivo_intervencion, fecha_intervencion, usuario_intervencion) VALUES (?,?,?,?,?,?,?,?,CURDATE(),?)")
                        ->execute([$cargaId, $p['ProductosID'], $p['FechaEV'], $estibaId, 'I', $p['Cantidad'], $causa, $motivoId, (int)$_SESSION['cina_usuario_id']]);
                    // Vaciar estiba original
                    $pdo->prepare("UPDATE ProductoCargaEstiba SET Cantidad = 0 WHERE CargaID=? AND ProductosID=? AND FechaEV=? AND EstibaID=?")
                        ->execute([$cargaId, $p['ProductosID'], $p['FechaEV'], $estibaId]);
                }
            } elseif ($modo === 'parcial') {
                // Solo una parte — necesitaría saber qué producto, pero por ahora toma todos parcialmente
                // Registrar en intervencion_estiba_producto con la cantidad parcial
                $prods = $pdo->prepare("SELECT * FROM ProductoCargaEstiba WHERE EstibaID = ? AND CargaID = ? AND Cantidad > 0 LIMIT 1");
                $prods->execute([$estibaId, $cargaId]); $p = $prods->fetch();
                if ($p) {
                    $cantInt = min($cantidad, (int)$p['Cantidad']);
                    $pdo->prepare("INSERT INTO intervencion_estiba_producto (carga_id, producto_id, fecha_ev, estiba_id, estado, cantidad_intervenido, causa, motivo_intervencion, fecha_intervencion, usuario_intervencion) VALUES (?,?,?,?,?,?,?,?,CURDATE(),?)")
                        ->execute([$cargaId, $p['ProductosID'], $p['FechaEV'], $estibaId, 'I', $cantInt, $causa, $motivoId, (int)$_SESSION['cina_usuario_id']]);
                    // Reducir cantidad en estiba original
                    $pdo->prepare("UPDATE ProductoCargaEstiba SET Cantidad = Cantidad - ? WHERE CargaID=? AND ProductosID=? AND FechaEV=? AND EstibaID=?")
                        ->execute([$cantInt, $cargaId, $p['ProductosID'], $p['FechaEV'], $estibaId]);
                }
            }

            $pdo->commit();
            resp(200, ['ok'=>true, 'estiba_intervenida'=>['EstibaID'=>$newEstibaIntId, 'NroEstiba'=>$nroInt]]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            resp(500, ['error'=>$e->getMessage()]);
        }
        break;

    case 'estiba_liberar_intervenida':
        requireSession();
        $b = json_input();
        $eiId = $b['EstibaIntervenidaID'] ?? 0;
        if (!$eiId) resp(400, ['error'=>'Falta EstibaIntervenidaID']);
        try {
            $pdo->beginTransaction();
            // Obtener la estiba intervenida
            $st = $pdo->prepare("SELECT * FROM Estiba_intervenidas WHERE EstibaID = ?");
            $st->execute([$eiId]); $ei = $st->fetch();
            if (!$ei) { $pdo->rollBack(); resp(404, ['error'=>'Estiba intervenida no encontrada']); }

            $estibaOrigId = $ei['EstibaOriginalID'];
            $cargaId = $ei['CargaID'];

            // Liberar todas las intervenciones asociadas a esta estiba original
            $pdo->prepare("UPDATE intervencion_estiba_producto SET estado='L', motivo_liberacion=1, fecha_liberacion=CURDATE(), comentarios_liberacion='Liberada desde estiba intervenida', usuario_liberacion=? WHERE carga_id=? AND estiba_id=? AND estado='I'")
                ->execute([(int)$_SESSION['cina_usuario_id'], $cargaId, $estibaOrigId]);

            // Devolver cantidades a la estiba original
            $intervs = $pdo->prepare("SELECT producto_id, fecha_ev, cantidad_intervenido FROM intervencion_estiba_producto WHERE carga_id=? AND estiba_id=? AND estado='L' AND fecha_liberacion=CURDATE()");
            $intervs->execute([$cargaId, $estibaOrigId]);
            foreach ($intervs->fetchAll() as $iv) {
                $pdo->prepare("UPDATE ProductoCargaEstiba SET Cantidad = Cantidad + ? WHERE CargaID=? AND ProductosID=? AND FechaEV=? AND EstibaID=?")
                    ->execute([$iv['cantidad_intervenido'], $cargaId, $iv['producto_id'], $iv['fecha_ev'], $estibaOrigId]);
            }

            // Marcar estiba intervenida como dada de baja
            $pdo->prepare("UPDATE Estiba_intervenidas SET FechaBaja=NOW(), estado=0 WHERE EstibaID=?")->execute([$eiId]);

            $pdo->commit();
            resp(200, ['ok'=>true, 'mensaje'=>'Productos devueltos a estiba original']);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            resp(500, ['error'=>$e->getMessage()]);
        }
        break;

    // ═══════════════════════════════════════════════════════════════
    //  TEMPERATURAS POR ESTIBA + FOTOS + PR-05 DIGITAL
    // ═══════════════════════════════════════════════════════════════

    case 'entrada_temperaturas':
        // GET: listar mediciones de temperatura de una entrada
        requireSession();
        $cargaId = $_GET['carga_id'] ?? null;
        if ($cargaId === null) resp(400, ['error'=>'Falta carga_id']);
        $cargaId = intval($cargaId);
        try {
            $stmt = $pdo->prepare("
                SELECT CCT.ControlTemperaturaID, CCT.FechaMedicion, CCT.NroTermometro, CCT.Tratamiento, CCT.NroControl,
                       CCM.MuestraID, CCM.ProductosID, CCM.Temperatura, CCM.Observaciones, CCM.CamaraID,
                       P.Descripcion1 AS producto, E.NroEstiba,
                       PCE.EstibaID
                FROM CargasControlTemp CCT
                LEFT JOIN CargasControlTempMedicion CCM ON CCM.ControlTemperaturaID = CCT.ControlTemperaturaID
                LEFT JOIN Productos P ON P.ProductosID = CCM.ProductosID
                LEFT JOIN ProductoCargaEstiba PCE ON PCE.CargaID = ? AND PCE.ProductosID = CCM.ProductosID AND PCE.Cantidad > 0
                LEFT JOIN Estiba E ON E.EstibaID = PCE.EstibaID
                WHERE CCT.EntradaID = ?
                ORDER BY CCT.NroControl, CCM.MuestraID
            ");
            $stmt->execute([$cargaId, $cargaId]);
            resp(200, ['ok'=>true, 'mediciones'=>$stmt->fetchAll()]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'entrada_registrar_temperatura':
        // POST: registrar medición de temperatura
        requireSession();
        $b = json_input();
        $cargaId = intval($b['carga_id'] ?? 0);
        $mediciones = $b['mediciones'] ?? []; // [{estiba_id, temperatura1, temperatura2, producto_id}]
        $nroTermometro = $b['nro_termometro'] ?? '';
        $tratamiento = $b['tratamiento'] ?? 'C';
        if (!$cargaId && !isset($b['carga_id'])) resp(400, ['error'=>'Falta carga_id']);
        $cargaId = intval($b['carga_id']);

        try {
            // Obtener próximo NroControl
            $maxCtrl = $pdo->prepare("SELECT COALESCE(MAX(NroControl),0) FROM CargasControlTemp WHERE EntradaID=?");
            $maxCtrl->execute([$cargaId]);
            $nroControl = (int)$maxCtrl->fetchColumn() + 1;

            // Crear registro de control
            $pdo->prepare("INSERT INTO CargasControlTemp (EntradaID, FechaMedicion, NroTermometro, Tratamiento, NroControl) VALUES (?,NOW(),?,?,?)")
                ->execute([$cargaId, $nroTermometro, $tratamiento, $nroControl]);
            $ctrlId = (int)$pdo->lastInsertId();

            // Insertar mediciones individuales
            // Obtener CamaraID default (primera activa)
            $defaultCamara = (int)$pdo->query("SELECT CamaraID FROM Camaras WHERE activo=1 ORDER BY CamaraID LIMIT 1")->fetchColumn() ?: 1;
            $ins = $pdo->prepare("INSERT INTO CargasControlTempMedicion (ControlTemperaturaID, MuestraID, ProductosID, CamaraID, Temperatura, Observaciones) VALUES (?,?,?,?,?,?)");
            $muestraId = 1;
            // Helper: buscar ProductosID de una estiba
            $stmtProdEstiba = $pdo->prepare("SELECT ProductosID FROM ProductoCargaEstiba WHERE EstibaID=? AND CargaID=? AND Cantidad>0 LIMIT 1");

            foreach ($mediciones as $m) {
                $prodId = intval($m['producto_id'] ?? 0);
                // Si no viene producto, buscarlo de la estiba
                if (!$prodId && !empty($m['estiba_id'])) {
                    $stmtProdEstiba->execute([$m['estiba_id'], $cargaId]);
                    $prodId = (int)$stmtProdEstiba->fetchColumn() ?: 0;
                }
                if (!$prodId) continue; // Sin producto, no registrar

                // Temperatura 1 (facturación)
                if (isset($m['temperatura1']) && $m['temperatura1'] !== '') {
                    $ins->execute([$ctrlId, $muestraId++, $prodId, $defaultCamara, floatval($m['temperatura1']), 'Facturación']);
                }
                // Temperatura 2 (formularios)
                if (isset($m['temperatura2']) && $m['temperatura2'] !== '') {
                    $ins->execute([$ctrlId, $muestraId++, $prodId, $defaultCamara, floatval($m['temperatura2']), 'Formulario']);
                }
            }

            // Actualizar estado de frío en Cargas
            try { $pdo->exec("ALTER TABLE Cargas ADD COLUMN tratamiento_id CHAR(1) DEFAULT NULL"); } catch(Exception $e2) {}
            $avgTemp = $pdo->prepare("SELECT AVG(Temperatura) FROM CargasControlTempMedicion WHERE ControlTemperaturaID=?");
            $avgTemp->execute([$ctrlId]);
            $promedio = (float)$avgTemp->fetchColumn();
            $estadoFrio = ($promedio <= -15) ? 3 : 2; // 3=OK, 2=completando frío
            $pdo->prepare("UPDATE Cargas SET estado=?, updated_at=NOW() WHERE CargaID=?")->execute([$estadoFrio, $cargaId]);

            resp(201, ['ok'=>true, 'control_id'=>$ctrlId, 'nro_control'=>$nroControl, 'promedio'=>round($promedio,2), 'estado_frio'=>$estadoFrio]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'estiba_fotos':
        // GET: listar fotos de una estiba
        requireSession();
        $estibaId = $_GET['estiba_id'] ?? 0;
        if (!$estibaId) resp(400, ['error'=>'Falta estiba_id']);
        try {
            $stmt = $pdo->prepare("
                SELECT EF.estiba_foto_id, F.foto_id, F.s3_key, F.mime_type, F.created_at
                FROM estiba_foto EF
                INNER JOIN foto F ON F.foto_id = EF.foto_id
                WHERE EF.estiba_id = ?
                ORDER BY F.created_at DESC
            ");
            $stmt->execute([$estibaId]);
            $fotos = $stmt->fetchAll();
            // Construir URLs
            foreach ($fotos as &$f) {
                $f['url'] = '/uploads/estibas/' . $f['s3_key'];
            }
            unset($f);
            resp(200, ['ok'=>true, 'fotos'=>$fotos]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'estiba_subir_foto':
        // POST: subir foto para una estiba
        requireSession();
        $estibaId = $_POST['estiba_id'] ?? 0;
        if (!$estibaId) resp(400, ['error'=>'Falta estiba_id']);
        if (empty($_FILES['foto'])) resp(400, ['error'=>'No se recibió archivo']);
        $file = $_FILES['foto'];
        $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
        if (!in_array($file['type'], $allowed)) resp(400, ['error'=>'Formato no permitido (jpg/png/gif/webp)']);
        if ($file['size'] > 10 * 1024 * 1024) resp(400, ['error'=>'Archivo muy grande (máx 10MB)']);

        try {
            $dir = __DIR__ . '/uploads/estibas/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
            $filename = 'estiba_' . $estibaId . '_' . time() . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) resp(500, ['error'=>'Error al guardar archivo']);

            // Registrar en tabla foto
            $pdo->prepare("INSERT INTO foto (s3_key, mime_type, size, usuario_id, created_at, updated_at) VALUES (?,?,?,?,NOW(),NOW())")
                ->execute([$filename, $file['type'], $file['size'], (int)$_SESSION['cina_usuario_id']]);
            $fotoId = (int)$pdo->lastInsertId();

            // Vincular a estiba
            $pdo->prepare("INSERT INTO estiba_foto (foto_id, estiba_id) VALUES (?,?)")->execute([$fotoId, $estibaId]);

            resp(201, ['ok'=>true, 'foto_id'=>$fotoId, 'url'=>'/uploads/estibas/'.$filename]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'entrada_pr05':
        // GET: obtener datos de PR-05 para una entrada
        requireSession();
        $cargaId = $_GET['carga_id'] ?? null;
        if ($cargaId === null) resp(400, ['error'=>'Falta carga_id']);
        $cargaId = intval($cargaId);
        try {
            // Datos de la carga con transporte
            $st = $pdo->prepare("
                SELECT C.CargaID, C.NroRemito, C.carga_numero, CL.ClienteNombre AS cliente,
                       CL.ClienteDireccion AS direccion, CL.ClienteLocalidad AS localidad, CL.ClienteCUIT AS cuit,
                       TC.fecha_movimiento, TC.hora_movimiento_inicio, TC.hora_movimiento_fin,
                       TV.patente AS vehiculo_patente, TA.patente AS acoplado_patente,
                       TE.razon_social AS transporte_nombre,
                       CONCAT(TCond.apellido,' ',TCond.nombre) AS conductor_nombre, TCond.documento_numero AS conductor_doc,
                       TV.habilitacion_senasa
                FROM Cargas C
                LEFT JOIN CLIENTES CL ON CL.ClienteID = C.ClienteID
                LEFT JOIN transporte_carga TC ON TC.carga_numero = C.carga_numero
                LEFT JOIN transporte_vehiculo TV ON TV.vehiculo_id = TC.vehiculo
                LEFT JOIN transporte_acoplado TA ON TA.acoplado_id = TC.acoplado
                LEFT JOIN transporte_conductor TCond ON TCond.conductor_id = TC.conductor
                LEFT JOIN transporte_empresa TE ON TE.transporte_id = TV.transporte_id
                WHERE C.CargaID = ?
            ");
            $st->execute([$cargaId]); $datos = $st->fetch();

            // PR-05 control transporte
            $pr05 = null;
            $st2 = $pdo->prepare("SELECT * FROM CargasControlTransporte WHERE EntradaID = ? LIMIT 1");
            $st2->execute([$cargaId]); $pr05 = $st2->fetch();

            // Certificados
            $certs = $pdo->prepare("SELECT CONCAT(COALESCE(estab_procedencia,''),'-',COALESCE(letra,''),'-',LPAD(COALESCE(numero,''),6,'0')) AS cert FROM Certificados WHERE CargaID=? AND activo=1");
            $certs->execute([$cargaId]);

            // Productos con estibas agrupados
            $prods = $pdo->prepare("
                SELECT P.CodigoProducto AS codigo, P.Descripcion1 AS producto,
                       COUNT(DISTINCT PCE.EstibaID) AS estibas, SUM(PCE.Cantidad) AS bultos,
                       ROUND(SUM(PCE.Cantidad * COALESCE(P.peso_estandar,0)),2) AS kilos
                FROM ProductoCargaEstiba PCE
                INNER JOIN Productos P ON P.ProductosID = PCE.ProductosID
                WHERE PCE.CargaID = ? AND PCE.Cantidad > 0
                GROUP BY P.ProductosID, P.CodigoProducto, P.Descripcion1
                ORDER BY P.Descripcion1
            ");
            $prods->execute([$cargaId]);

            // Insumos (PR-98)
            $insumos = [];
            try {
                $si = $pdo->prepare("SELECT CI.cantidad, TE.TipoEstibaNombre AS nombre, CI.observaciones FROM carga_insumo CI JOIN TiposEstiba TE ON TE.TiposEstibaID=CI.tipo_estiba_id WHERE CI.carga_numero=?");
                $cn = $datos['carga_numero'] ?? 0;
                if ($cn) { $si->execute([$cn]); $insumos = $si->fetchAll(); }
            } catch(Exception $e) {}

            // Temperaturas: 3 valores más altos de FACT (Facturación) para PR-05
            $tempForm = [];
            try {
                $stTemp = $pdo->prepare("
                    SELECT M.Temperatura FROM CargasControlTempMedicion M
                    INNER JOIN CargasControlTemp CT ON CT.ControlTemperaturaID = M.ControlTemperaturaID
                    WHERE CT.EntradaID = ? AND M.Observaciones = 'Facturación'
                    ORDER BY M.Temperatura DESC LIMIT 3
                ");
                $stTemp->execute([$cargaId]);
                $tempForm = array_column($stTemp->fetchAll(), 'Temperatura');
            } catch(Exception $e3) {}

            resp(200, [
                'ok'=>true,
                'datos'=>$datos,
                'pr05'=>$pr05,
                'certificados'=>$certs->fetchAll(PDO::FETCH_COLUMN),
                'productos'=>$prods->fetchAll(),
                'insumos'=>$insumos,
                'temperaturas_formulario'=>$tempForm,
            ]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'entrada_guardar_pr05':
        // POST: guardar datos de PR-05
        requireSession();
        $b = json_input();
        $cargaId = intval($b['carga_id'] ?? 0);
        if (!$cargaId && !isset($b['carga_id'])) resp(400, ['error'=>'Falta carga_id']);
        $cargaId = intval($b['carga_id']);
        try {
            // Verificar si existe
            $ex = $pdo->prepare("SELECT ControlCalidadID FROM CargasControlTransporte WHERE EntradaID=? LIMIT 1");
            $ex->execute([$cargaId]); $existente = $ex->fetchColumn();

            $campos = [
                'Temperatura1'=>floatval($b['Temperatura1']??0), 'Temperatura2'=>floatval($b['Temperatura2']??0), 'Temperatura3'=>floatval($b['Temperatura3']??0),
                'NroTermometro'=>$b['NroTermometro']??'', 'CondicionTransporte'=>$b['CondicionTransporte']??'B',
                'Organoleptico'=>$b['Organoleptico']??'N', 'EstadoEnvases'=>$b['EstadoEnvases']??'B',
                'EstadoTarimas'=>$b['EstadoTarimas']??'B', 'EstadoSanitario'=>$b['EstadoSanitario']??'B',
                'EstadoParedesPisoTecho'=>$b['EstadoParedesPisoTecho']??'B', 'EquipoFrio'=>$b['EquipoFrio']??'F',
                'setpoint_equipo_frio'=>$b['setpoint_equipo_frio']??'F', 'Observaciones'=>$b['Observaciones']??'',
                'TipoCarga'=>$b['TipoCarga']??'', 'CantidadMercaderiaMover'=>$b['CantidadMercaderiaMover']??'A',
            ];

            if ($existente) {
                $sets = implode(', ', array_map(fn($k)=>"$k=?", array_keys($campos)));
                $pdo->prepare("UPDATE CargasControlTransporte SET $sets WHERE ControlCalidadID=?")->execute([...array_values($campos), $existente]);
            } else {
                $campos['EntradaID'] = $cargaId;
                $cols = implode(',', array_keys($campos));
                $phs = implode(',', array_fill(0, count($campos), '?'));
                $pdo->prepare("INSERT INTO CargasControlTransporte ($cols) VALUES ($phs)")->execute(array_values($campos));
            }
            resp(200, ['ok'=>true]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'entrada_cambiar_tratamiento':
        requireSession();
        $b = json_input();
        $cargaId = $b['CargaID'] ?? null;
        $tratNuevo = $b['tratamiento_id'] ?? '';
        if ($cargaId === null || !$tratNuevo) resp(400, ['error'=>'Faltan datos']);
        $cargaId = intval($cargaId);
        try {
            // Asegurar que la columna existe
            try { $pdo->exec("ALTER TABLE Cargas ADD COLUMN tratamiento_id CHAR(1) DEFAULT NULL"); } catch(Exception $e) {}

            // Obtener tratamiento actual
            $st = $pdo->prepare("SELECT tratamiento_id FROM Cargas WHERE CargaID=?"); $st->execute([$cargaId]);
            $tratAnterior = $st->fetchColumn() ?: null;

            // Actualizar
            $pdo->prepare("UPDATE Cargas SET tratamiento_id=?, updated_at=NOW(), usuario_id=? WHERE CargaID=?")
                ->execute([$tratNuevo, (int)$_SESSION['cina_usuario_id'], $cargaId]);

            // Log de cambio
            try { $pdo->exec("CREATE TABLE IF NOT EXISTS carga_tratamiento_log (id INT AUTO_INCREMENT PRIMARY KEY, carga_id INT UNSIGNED, tratamiento_anterior CHAR(1), tratamiento_nuevo CHAR(1), usuario_id INT UNSIGNED, fecha DATETIME DEFAULT NOW())"); } catch(Exception $e) {}
            if ($tratAnterior !== $tratNuevo) {
                $pdo->prepare("INSERT INTO carga_tratamiento_log (carga_id, tratamiento_anterior, tratamiento_nuevo, usuario_id) VALUES (?,?,?,?)")
                    ->execute([$cargaId, $tratAnterior, $tratNuevo, (int)$_SESSION['cina_usuario_id']]);
            }
            resp(200, ['ok'=>true, 'anterior'=>$tratAnterior, 'nuevo'=>$tratNuevo]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'entrada_validar':
        // Paso intermedio: confirma que los datos de la pre-entrada están correctos
        // No clasifica, solo marca como "validada" actualizando timestamp
        requireSession();
        $b = json_input();
        $cargaId = $b['CargaID'] ?? null;
        if ($cargaId === null) resp(400, ['error'=>'Falta CargaID']);
        $cargaId = intval($cargaId);
        try {
            // Verificar que tiene al menos una estiba con productos
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM ProductoCargaEstiba WHERE CargaID=? AND Cantidad > 0");
            $cnt->execute([$cargaId]);
            if ((int)$cnt->fetchColumn() === 0) resp(400, ['error'=>'Debe tener al menos una estiba con productos cargados']);

            // Actualizar timestamp de validación
            $pdo->prepare("UPDATE Cargas SET updated_at = NOW(), usuario_id = ? WHERE CargaID = ?")
                ->execute([(int)$_SESSION['cina_usuario_id'], $cargaId]);

            resp(200, ['ok'=>true, 'mensaje'=>'Ingreso validado. Puede proceder a clasificar como Entrada.']);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'entrada_clasificar':
        requireSession();
        $b = json_input();
        $cargaId = $b['CargaID'] ?? null;
        if ($cargaId === null) resp(400, ['error'=>'Falta CargaID']);
        $cargaId = intval($cargaId);
        try {
            $st = $pdo->prepare("SELECT * FROM Cargas WHERE CargaID=?"); $st->execute([$cargaId]);
            $carga = $st->fetch();
            if (!$carga) resp(404, ['error'=>'Entrada no encontrada']);
            if ((int)$carga['Clasificada']) resp(400, ['error'=>'Ya está clasificada']);

            $errores = [];
            // 1. Remito
            if (empty(trim($carga['NroRemito']??''))) $errores[] = 'Falta Nro de Remito';
            // 2. Transporte — solo verificar tiempos de operación, NO bloquear por estado
            if ($carga['carga_numero']) {
                $tc = $pdo->prepare("SELECT estado, fecha_movimiento, hora_movimiento_inicio, hora_movimiento_fin FROM transporte_carga WHERE carga_numero=?");
                $tc->execute([$carga['carga_numero']]); $tr = $tc->fetch();
                if ($tr) {
                    if (!$tr['fecha_movimiento']) $errores[] = 'Transporte sin fecha de movimiento';
                    if (!$tr['hora_movimiento_inicio']) $errores[] = 'Transporte sin hora de inicio';
                    if (!$tr['hora_movimiento_fin']) $errores[] = 'Transporte sin hora de fin';
                    // Ya no se bloquea por estado R/Z — se puede clasificar con transporte en cualquier estado
                }
            }
            // 3. Estibas con ubicación
            $sinUbic = $pdo->prepare("SELECT COUNT(*) FROM Estiba WHERE CargaID=? AND FechaBaja IS NULL AND PosicionID IS NULL");
            $sinUbic->execute([$cargaId]);
            if ((int)$sinUbic->fetchColumn() > 0) $errores[] = 'Hay estibas sin ubicación asignada';
            // 4. Estibas vacías
            $vacias = $pdo->prepare("SELECT COUNT(*) FROM Estiba E WHERE E.CargaID=? AND E.FechaBaja IS NULL AND NOT EXISTS (SELECT 1 FROM ProductoCargaEstiba PCE WHERE PCE.EstibaID=E.EstibaID AND PCE.Cantidad>0)");
            $vacias->execute([$cargaId]);
            if ((int)$vacias->fetchColumn() > 0) $errores[] = 'Hay estibas sin productos asignados';

            if (!empty($errores)) resp(400, ['error'=>implode('. ', $errores)]);

            // Clasificar
            $pdo->prepare("UPDATE Cargas SET Clasificada=1, FechaClasificacion=NOW(), usuario_id=?, updated_at=NOW() WHERE CargaID=?")
                ->execute([(int)$_SESSION['cina_usuario_id'], $cargaId]);
            resp(200, ['ok'=>true, 'mensaje'=>'Entrada clasificada correctamente']);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'entrada_desclasificar':
        requireSession();
        $b = json_input();
        $cargaId = $b['CargaID'] ?? null;
        if ($cargaId === null) resp(400, ['error'=>'Falta CargaID']);
        $cargaId = intval($cargaId);
        try {
            $ss = $pdo->prepare("SELECT COUNT(*) FROM ProductoCargaSalida WHERE CargaID=?"); $ss->execute([$cargaId]);
            if ((int)$ss->fetchColumn() > 0) resp(400, ['error'=>'No se puede desclasificar: hay salidas vinculadas']);
            $pdo->prepare("UPDATE Cargas SET Clasificada=0, FechaClasificacion=NULL, usuario_id=?, updated_at=NOW() WHERE CargaID=?")
                ->execute([(int)$_SESSION['cina_usuario_id'], $cargaId]);
            resp(200, ['ok'=>true]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    // ═══════════════════════════════════════════════════════════════
    //  DETALLE SALIDA: ver, stock, seleccionar, confirmar
    // ═══════════════════════════════════════════════════════════════

    case 'salida_ver':
        requireSession();
        $id = intval($_GET['id'] ?? 0);
        if (!$id) resp(400, ['error'=>'Falta id']);
        try {
            $st = $pdo->prepare("SELECT PS.*, COALESCE(CG.Nombre, CL.ClienteNombre) AS cliente, CL.ClienteID, D.DestinoNombre AS destino FROM PreSalidas PS LEFT JOIN CLIENTES CL ON CL.ClienteID=PS.ClienteID LEFT JOIN CLIENTEGRUPO CG ON CG.GrupoID=PS.GrupoID LEFT JOIN Destinos D ON D.DestinoID=PS.DestinoID WHERE PS.PreSalidaID=?");
            $st->execute([$id]); $reg = $st->fetch();
            if (!$reg) resp(404, ['error'=>'Salida no encontrada']);

            // Productos seleccionados para la salida
            $sp = $pdo->prepare("
                SELECT PCS.PreSalidaID, PCS.SubSalidaID, PCS.EstibaID, PCS.FechaEV, PCS.ProductosID, PCS.CargaID, PCS.Cantidad,
                       PCS.EtiquetaProvisoriaID,
                       P.CodigoProducto AS codigo, P.Descripcion1 AS producto,
                       E.NroEstiba,
                       PCE.Cantidad AS stock_disponible,
                       EP.codigo_qr AS etiqueta_qr
                FROM ProductoCargaSalida PCS
                INNER JOIN Productos P ON P.ProductosID=PCS.ProductosID
                LEFT JOIN Estiba E ON E.EstibaID=PCS.EstibaID
                LEFT JOIN ProductoCargaEstiba PCE ON PCE.CargaID=PCS.CargaID AND PCE.ProductosID=PCS.ProductosID AND PCE.FechaEV=PCS.FechaEV AND PCE.EstibaID=PCS.EstibaID
                LEFT JOIN EtiquetaProvisoria EP ON EP.EtiquetaProvisoriaID=PCS.EtiquetaProvisoriaID
                WHERE PCS.PreSalidaID=?
                ORDER BY PCS.SubSalidaID, P.Descripcion1
            ");
            $sp->execute([$id]); $seleccionados = $sp->fetchAll();

            // Datos de transporte (estado, movimiento, tronera) para botones de operación
            $reg['tc_estado'] = null;
            $reg['hora_movimiento_inicio'] = null;
            $reg['hora_movimiento_fin'] = null;
            $reg['fecha_movimiento'] = null;
            $reg['tronera'] = null;
            if ($reg['carga_numero']) {
                $stTC = $pdo->prepare("SELECT TC.estado AS tc_estado, TC.fecha_movimiento, TC.hora_movimiento_inicio, TC.hora_movimiento_fin FROM transporte_carga TC WHERE TC.carga_numero=?");
                $stTC->execute([(int)$reg['carga_numero']]);
                $tcData = $stTC->fetch();
                if ($tcData) {
                    $reg['tc_estado'] = $tcData['tc_estado'];
                    $reg['hora_movimiento_inicio'] = $tcData['hora_movimiento_inicio'];
                    $reg['hora_movimiento_fin'] = $tcData['hora_movimiento_fin'];
                    $reg['fecha_movimiento'] = $tcData['fecha_movimiento'];
                }
                $stTR = $pdo->prepare("SELECT CONCAT('T', t.numero) FROM transporte_tronera tt JOIN tronera t ON t.id=tt.tronera_asignada_id WHERE tt.carga_numero=? AND tt.estado IN ('A','U') ORDER BY tt.id DESC LIMIT 1");
                $stTR->execute([(int)$reg['carga_numero']]);
                $reg['tronera'] = $stTR->fetchColumn() ?: null;
            }

            resp(200, ['ok'=>true, 'registro'=>$reg, 'seleccionados'=>$seleccionados]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'salida_stock_cliente':
        requireSession();
        $clienteId    = intval($_GET['cliente_id'] ?? 0);
        $grupoId      = intval($_GET['grupo_id'] ?? 0);
        $busqueda     = $_GET['q'] ?? '';
        $productoId   = intval($_GET['producto_id'] ?? 0);
        $fechaLimite  = $_GET['fecha_limite'] ?? '';  // fecha límite de elaboración (YYYY-MM-DD)
        try {
            $where = "PCE.Cantidad > 0 AND Car.Clasificada = 1 AND E.FechaBaja IS NULL";
            $params = [];

            // Filtro por cliente (opcional si viene producto_id)
            if ($clienteId) { $where .= " AND Car.ClienteID = ?"; $params[] = $clienteId; }
            elseif ($grupoId) { $where .= " AND Car.ClienteID IN (SELECT ClienteID FROM CLIENTES WHERE GrupoID=?)"; $params[] = $grupoId; }

            // Filtro por producto específico
            if ($productoId) { $where .= " AND PCE.ProductosID = ?"; $params[] = $productoId; }

            // Filtro por fecha límite de elaboración (producción anterior a esa fecha)
            if ($fechaLimite) { $where .= " AND PCE.FechaElaboracion <= ?"; $params[] = $fechaLimite; }

            // Búsqueda por texto
            if ($busqueda) { $where .= " AND (P.Descripcion1 LIKE ? OR P.CodigoProducto LIKE ?)"; $params[] = "%$busqueda%"; $params[] = "%$busqueda%"; }

            $stmt = $pdo->prepare("
                SELECT PCE.CargaID, PCE.ProductosID, PCE.FechaEV, PCE.EstibaID,
                       PCE.Cantidad, PCE.NroLote, PCE.FechaElaboracion, PCE.FechaVencimiento,
                       PCE.FechaCongelado, PCE.FechaFaena,
                       P.CodigoProducto AS codigo, P.Descripcion1 AS producto,
                       E.NroEstiba, E.EstibaID AS estiba_id_raw,
                       Car.CargaID AS entrada_id,
                       CL.ClienteNombre AS cliente_stock,
                       CONCAT(COALESCE(TCA.iniciales,CONCAT('C',CAM.CamaraNumero)),' ',POS.Calle,'-',POS.Fila,'.',E.Nivel) AS ubicacion
                FROM ProductoCargaEstiba PCE
                INNER JOIN Cargas Car ON Car.CargaID=PCE.CargaID
                INNER JOIN Productos P ON P.ProductosID=PCE.ProductosID
                INNER JOIN Estiba E ON E.EstibaID=PCE.EstibaID
                LEFT JOIN CLIENTES CL ON CL.ClienteID=Car.ClienteID
                LEFT JOIN Posiciones POS ON POS.PosicionID=E.PosicionID
                LEFT JOIN Camaras CAM ON CAM.CamaraID=POS.CamaraID
                WHERE $where
                ORDER BY P.Descripcion1, PCE.FechaElaboracion, PCE.FechaVencimiento
                LIMIT 500
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            // Calcular cantidad afectada en pre-salidas pendientes (no clasificadas)
            // para mostrar el "disponible real" descontando lo ya comprometido
            $presalidaIdActual = intval($_GET['presalida_id'] ?? 0);
            $stmtAfectado = $pdo->prepare("
                SELECT COALESCE(SUM(PCS.Cantidad), 0)
                FROM ProductoCargaSalida PCS
                INNER JOIN PreSalidas PS ON PS.PreSalidaID = PCS.PreSalidaID
                WHERE PCS.CargaID = ? AND PCS.ProductosID = ? AND PCS.FechaEV = ? AND PCS.EstibaID = ?
                  AND PS.clasificada = 0
                  AND PCS.PreSalidaID != ?
            ");

            foreach ($rows as &$r) {
                $r['FechaElaboracion_raw'] = $r['FechaElaboracion'];
                $r['FechaVencimiento_raw'] = $r['FechaVencimiento'];
                if ($r['FechaElaboracion']) $r['FechaElaboracion'] = (new DateTime($r['FechaElaboracion']))->format('d/m/Y');
                if ($r['FechaVencimiento']) $r['FechaVencimiento'] = (new DateTime($r['FechaVencimiento']))->format('d/m/Y');
                if ($r['FechaCongelado']) $r['FechaCongelado'] = (new DateTime($r['FechaCongelado']))->format('d/m/Y');

                // Cantidad comprometida en otras pre-salidas pendientes
                $stmtAfectado->execute([$r['CargaID'], $r['ProductosID'], $r['FechaEV'], $r['EstibaID'], $presalidaIdActual]);
                $afectado = (int)$stmtAfectado->fetchColumn();
                $r['cantidad_afectada'] = $afectado;
                $r['disponible_real'] = max(0, (int)$r['Cantidad'] - $afectado);
            }
            unset($r);
            resp(200, ['ok'=>true, 'stock'=>$rows]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    // ── Catálogo de productos para dropdown ───────────────────────
    case 'salida_productos_catalogo':
        requireSession();
        $clienteId = intval($_GET['cliente_id'] ?? 0);
        $grupoId   = intval($_GET['grupo_id'] ?? 0);
        try {
            // Traer todos los productos con stock disponible (sin filtro por cliente obligatorio)
            $where = "PCE.Cantidad > 0 AND Car.Clasificada = 1 AND E.FechaBaja IS NULL";
            $params = [];
            // Filtro por cliente es opcional — si no viene, trae todos
            if ($clienteId) { $where .= " AND Car.ClienteID = ?"; $params[] = $clienteId; }
            elseif ($grupoId) { $where .= " AND Car.ClienteID IN (SELECT ClienteID FROM CLIENTES WHERE GrupoID=?)"; $params[] = $grupoId; }

            $stmt = $pdo->prepare("
                SELECT P.ProductosID, P.CodigoProducto AS codigo, P.Descripcion1 AS producto,
                       SUM(PCE.Cantidad) AS stock_total
                FROM ProductoCargaEstiba PCE
                INNER JOIN Cargas Car ON Car.CargaID=PCE.CargaID
                INNER JOIN Productos P ON P.ProductosID=PCE.ProductosID
                INNER JOIN Estiba E ON E.EstibaID=PCE.EstibaID
                WHERE $where
                GROUP BY P.ProductosID, P.CodigoProducto, P.Descripcion1
                ORDER BY P.Descripcion1
            ");
            $stmt->execute($params);
            resp(200, ['ok'=>true, 'productos'=>$stmt->fetchAll()]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'salida_seleccionar':
        requireSession();
        $b = json_input();
        $presalidaId = intval($b['PreSalidaID'] ?? 0);
        $items = $b['items'] ?? [];
        if (!$presalidaId) resp(400, ['error'=>'Falta PreSalidaID']);
        try {
            // Bloquear si vehículo fue liberado
            $stPS = $pdo->prepare("SELECT carga_numero FROM PreSalidas WHERE PreSalidaID=?"); $stPS->execute([$presalidaId]);
            $cnPS = $stPS->fetchColumn();
            if ($cnPS) {
                $stTC = $pdo->prepare("SELECT estado FROM transporte_carga WHERE carga_numero=?"); $stTC->execute([$cnPS]);
                $tcEst = $stTC->fetchColumn();
                if (in_array($tcEst, ['R','Z'])) resp(400, ['error'=>'No se puede modificar: el vehículo ya fue liberado']);
            }
            $pdo->beginTransaction();

            // Borrar selecciones anteriores PRIMERO (así la validación es contra estado limpio)
            $pdo->prepare("DELETE FROM ProductoCargaSalida WHERE PreSalidaID=?")->execute([$presalidaId]);

            // Validar disponible real (stock - afectado por OTRAS pre-salidas pendientes)
            $stValDisp = $pdo->prepare("SELECT PCE.Cantidad FROM ProductoCargaEstiba PCE WHERE PCE.CargaID=? AND PCE.ProductosID=? AND PCE.FechaEV=? AND PCE.EstibaID=?");
            $stValAfect = $pdo->prepare("
                SELECT COALESCE(SUM(PCS.Cantidad),0) FROM ProductoCargaSalida PCS
                INNER JOIN PreSalidas PS ON PS.PreSalidaID=PCS.PreSalidaID
                WHERE PCS.CargaID=? AND PCS.ProductosID=? AND PCS.FechaEV=? AND PCS.EstibaID=?
                  AND PS.clasificada=0
            ");
            $erroresVal = [];
            foreach ($items as $it) {
                $cant = intval($it['Cantidad'] ?? 0);
                if ($cant <= 0) continue;
                $stValDisp->execute([$it['CargaID'], $it['ProductosID'], intval($it['FechaEV']??0), $it['EstibaID']]);
                $stockTotal = (int)($stValDisp->fetchColumn() ?: 0);
                $stValAfect->execute([$it['CargaID'], $it['ProductosID'], intval($it['FechaEV']??0), $it['EstibaID']]);
                $afectado = (int)$stValAfect->fetchColumn();
                $dispReal = $stockTotal - $afectado;
                if ($cant > $dispReal) {
                    $stPN2 = $pdo->prepare("SELECT Descripcion1 FROM Productos WHERE ProductosID=? LIMIT 1");
                    $stPN2->execute([$it['ProductosID']]);
                    $nomP = $stPN2->fetchColumn() ?: "ID {$it['ProductosID']}";
                    $erroresVal[] = "«{$nomP}» estiba #{$it['EstibaID']}: pedido {$cant}, disponible real {$dispReal}";
                }
            }
            if (!empty($erroresVal)) { $pdo->rollBack(); resp(400, ['error'=>'Stock insuficiente: '.implode('; ', $erroresVal)]); }
            // Insertar nuevas
            $ins = $pdo->prepare("INSERT INTO ProductoCargaSalida (PreSalidaID, SubSalidaID, EstibaID, FechaEV, ProductosID, CargaID, Cantidad) VALUES (?,?,?,?,?,?,?)");
            foreach ($items as $it) {
                $cant = intval($it['Cantidad'] ?? 0);
                if ($cant <= 0) continue;
                $ins->execute([$presalidaId, intval($it['SubSalidaID']??1), $it['EstibaID'], intval($it['FechaEV']??0), intval($it['ProductosID']??0), intval($it['CargaID']??0), $cant]);
            }
            $pdo->prepare("UPDATE PreSalidas SET usuario_id=?, updated_at=NOW() WHERE PreSalidaID=?")->execute([(int)$_SESSION['cina_usuario_id'], $presalidaId]);
            $pdo->commit();
            resp(200, ['ok'=>true]);
        } catch (Exception $e) { if($pdo->inTransaction())$pdo->rollBack(); resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'salida_confirmar':
        requireSession();
        $b = json_input();
        $presalidaId = intval($b['PreSalidaID'] ?? 0);
        if (!$presalidaId) resp(400, ['error'=>'Falta PreSalidaID']);
        try {
            $pdo->beginTransaction();

            $ps = $pdo->prepare("SELECT * FROM PreSalidas WHERE PreSalidaID=?"); $ps->execute([$presalidaId]);
            $presalida = $ps->fetch();
            if (!$presalida) { $pdo->rollBack(); resp(404, ['error'=>'Pre-salida no encontrada']); }
            if ((int)$presalida['clasificada']) { $pdo->rollBack(); resp(400, ['error'=>'Ya está confirmada']); }

            $errores = [];
            if (empty(trim($presalida['Remito']??''))) $errores[] = 'Falta Nro de Remito';

            // Obtener productos seleccionados
            $pcs = $pdo->prepare("SELECT * FROM ProductoCargaSalida WHERE PreSalidaID=?"); $pcs->execute([$presalidaId]);
            $items = $pcs->fetchAll();
            if (empty($items)) $errores[] = 'No hay productos seleccionados';

            // Validar cantidades disponibles (stock real en ProductoCargaEstiba)
            foreach ($items as $it) {
                $st = $pdo->prepare("SELECT Cantidad FROM ProductoCargaEstiba WHERE CargaID=? AND ProductosID=? AND FechaEV=? AND EstibaID=?");
                $st->execute([$it['CargaID'], $it['ProductosID'], $it['FechaEV'], $it['EstibaID']]);
                $disp = (int)($st->fetchColumn() ?: 0);
                if ($disp < (int)$it['Cantidad']) {
                    // Obtener nombre del producto para mensaje legible
                    $stPN = $pdo->prepare("SELECT Descripcion1 FROM Productos WHERE ProductosID=? LIMIT 1");
                    $stPN->execute([$it['ProductosID']]);
                    $prodNom = $stPN->fetchColumn() ?: "ID {$it['ProductosID']}";
                    $errores[] = "Stock insuficiente para «{$prodNom}» en estiba #{$it['EstibaID']} (pedido: {$it['Cantidad']}, disponible: {$disp})";
                }
            }

            if (!empty($errores)) { $pdo->rollBack(); resp(400, ['error'=>implode('. ', $errores)]); }

            // Reducir stock
            foreach ($items as $it) {
                $pdo->prepare("UPDATE ProductoCargaEstiba SET Cantidad = Cantidad - ? WHERE CargaID=? AND ProductosID=? AND FechaEV=? AND EstibaID=?")
                    ->execute([(int)$it['Cantidad'], $it['CargaID'], $it['ProductosID'], $it['FechaEV'], $it['EstibaID']]);

                // Desactivar estiba si queda en 0
                $rem = $pdo->prepare("SELECT SUM(Cantidad) FROM ProductoCargaEstiba WHERE EstibaID=?"); $rem->execute([$it['EstibaID']]);
                if ((int)$rem->fetchColumn() <= 0) {
                    $pdo->prepare("UPDATE Estiba SET FechaBaja=NOW(), baja_salida_numero=? WHERE EstibaID=?")->execute([$presalidaId, $it['EstibaID']]);
                }
                // Marcar carga sin stock si corresponde
                $remC = $pdo->prepare("SELECT SUM(PCE.Cantidad) FROM ProductoCargaEstiba PCE WHERE PCE.CargaID=?"); $remC->execute([$it['CargaID']]);
                if ((int)$remC->fetchColumn() <= 0) {
                    $pdo->prepare("UPDATE Cargas SET Stock=0, FechaSinStock=CURDATE(), usuario_id=? WHERE CargaID=?")->execute([(int)$_SESSION['cina_usuario_id'], $it['CargaID']]);
                }
            }

            // Confirmar pre-salida
            $pdo->prepare("UPDATE PreSalidas SET clasificada=1, fecha_clasificada=NOW(), usuario_id=?, updated_at=NOW() WHERE PreSalidaID=?")
                ->execute([(int)$_SESSION['cina_usuario_id'], $presalidaId]);

            $pdo->commit();
            resp(200, ['ok'=>true, 'mensaje'=>'Salida confirmada. Stock actualizado.']);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            resp(500, ['error'=>$e->getMessage()]);
        }
        break;

    // ── Confirmar Pre-Salida (bloquea selección, genera etiquetas provisorias para parciales) ──
    case 'salida_confirmar_presalida':
        requireSession();
        $b = json_input();
        $presalidaId = intval($b['PreSalidaID'] ?? 0);
        if (!$presalidaId) resp(400, ['error'=>'Falta PreSalidaID']);
        try {
            $pdo->beginTransaction();
            $ps = $pdo->prepare("SELECT * FROM PreSalidas WHERE PreSalidaID=?"); $ps->execute([$presalidaId]);
            $presalida = $ps->fetch();
            if (!$presalida) { $pdo->rollBack(); resp(404, ['error'=>'Pre-salida no encontrada']); }
            if ((int)$presalida['clasificada']) { $pdo->rollBack(); resp(400, ['error'=>'Ya está confirmada como salida']); }

            // Obtener productos seleccionados
            $pcs = $pdo->prepare("SELECT PCS.*, PCE.Cantidad AS stock_disponible FROM ProductoCargaSalida PCS LEFT JOIN ProductoCargaEstiba PCE ON PCE.CargaID=PCS.CargaID AND PCE.ProductosID=PCS.ProductosID AND PCE.FechaEV=PCS.FechaEV AND PCE.EstibaID=PCS.EstibaID WHERE PCS.PreSalidaID=?");
            $pcs->execute([$presalidaId]);
            $items = $pcs->fetchAll();
            if (empty($items)) { $pdo->rollBack(); resp(400, ['error'=>'No hay productos seleccionados']); }

            // Crear tabla EtiquetaProvisoria si no existe
            $pdo->exec("CREATE TABLE IF NOT EXISTS `EtiquetaProvisoria` (
                `EtiquetaProvisoriaID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `PreSalidaID` int(10) UNSIGNED NOT NULL,
                `EstibaOrigenID` double NOT NULL,
                `CargaID` int(10) UNSIGNED NOT NULL,
                `ProductosID` int(10) UNSIGNED NOT NULL,
                `FechaEV` smallint(6) NOT NULL DEFAULT 0,
                `Cantidad` int(10) UNSIGNED NOT NULL,
                `codigo_qr` varchar(64) NOT NULL DEFAULT '',
                `estado` char(1) NOT NULL DEFAULT 'A',
                `usuario_id` int(10) UNSIGNED DEFAULT NULL,
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`EtiquetaProvisoriaID`),
                KEY `idx_presalida` (`PreSalidaID`),
                KEY `idx_codigo_qr` (`codigo_qr`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci");

            $etiquetasGeneradas = [];
            $insEP = $pdo->prepare("INSERT INTO EtiquetaProvisoria (PreSalidaID, EstibaOrigenID, CargaID, ProductosID, FechaEV, Cantidad, usuario_id) VALUES (?,?,?,?,?,?,?)");
            $updQR = $pdo->prepare("UPDATE EtiquetaProvisoria SET codigo_qr=? WHERE EtiquetaProvisoriaID=?");
            $updPCS = $pdo->prepare("UPDATE ProductoCargaSalida SET EtiquetaProvisoriaID=? WHERE PreSalidaID=? AND EstibaID=? AND ProductosID=? AND CargaID=? AND FechaEV=?");
            $getProd = $pdo->prepare("SELECT Descripcion1 FROM Productos WHERE ProductosID=?");
            $getEst = $pdo->prepare("SELECT NroEstiba FROM Estiba WHERE EstibaID=?");

            foreach ($items as $it) {
                $esParcial = $it['stock_disponible'] && (int)$it['Cantidad'] < (int)$it['stock_disponible'];
                // Solo generar etiqueta provisoria para items PARCIALES que no tengan una ya
                if ($esParcial && !$it['EtiquetaProvisoriaID']) {
                    $insEP->execute([$presalidaId, $it['EstibaID'], $it['CargaID'], $it['ProductosID'], $it['FechaEV'], (int)$it['Cantidad'], (int)$_SESSION['cina_usuario_id']]);
                    $epId = (int)$pdo->lastInsertId();
                    $codigoQR = 'EP-' . $epId;
                    $updQR->execute([$codigoQR, $epId]);
                    $updPCS->execute([$epId, $presalidaId, $it['EstibaID'], $it['ProductosID'], $it['CargaID'], $it['FechaEV']]);

                    $getProd->execute([$it['ProductosID']]); $prodNombre = $getProd->fetchColumn();
                    $getEst->execute([$it['EstibaID']]); $nroEst = $getEst->fetchColumn();
                    $etiquetasGeneradas[] = [
                        'EtiquetaProvisoriaID'=>$epId, 'codigo_qr'=>$codigoQR,
                        'PreSalidaID'=>$presalidaId, 'EstibaOrigenID'=>$it['EstibaID'],
                        'NroEstiba'=>$nroEst, 'CargaID'=>$it['CargaID'],
                        'producto'=>$prodNombre, 'Cantidad'=>(int)$it['Cantidad']
                    ];
                }
            }

            // Marcar como confirmada
            $pdo->prepare("UPDATE PreSalidas SET confirmada=1, updated_at=NOW(), usuario_id=? WHERE PreSalidaID=?")
                ->execute([(int)$_SESSION['cina_usuario_id'], $presalidaId]);

            $pdo->commit();
            resp(200, ['ok'=>true, 'mensaje'=>'Pre-salida confirmada.', 'etiquetas'=>$etiquetasGeneradas, 'total_etiquetas'=>count($etiquetasGeneradas)]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            resp(500, ['error'=>$e->getMessage()]);
        }
        break;

    // ── Reemplazo de estiba (misma producto, distinta estiba) ─────
    case 'salida_reemplazar_estiba':
        requireSession();
        $b = json_input();
        $presalidaId = intval($b['PreSalidaID'] ?? 0);
        $estibaOriginal = $b['EstibaID_original'] ?? 0;
        $productosId = intval($b['ProductosID'] ?? 0);
        $fechaEV = intval($b['FechaEV'] ?? 0);
        $cargaIdOrig = intval($b['CargaID'] ?? 0);
        $itemsNuevos = $b['items_nuevos'] ?? [];
        if (!$presalidaId || !$estibaOriginal || !$productosId || empty($itemsNuevos))
            resp(400, ['error'=>'Faltan campos requeridos']);
        try {
            $pdo->beginTransaction();
            // Obtener item original
            $orig = $pdo->prepare("SELECT * FROM ProductoCargaSalida WHERE PreSalidaID=? AND EstibaID=? AND ProductosID=? AND CargaID=? AND FechaEV=?");
            $orig->execute([$presalidaId, $estibaOriginal, $productosId, $cargaIdOrig, $fechaEV]);
            $itemOrig = $orig->fetch();
            if (!$itemOrig) { $pdo->rollBack(); resp(404, ['error'=>'Item original no encontrado']); }
            $cantidadOriginal = (int)$itemOrig['Cantidad'];

            // Validar que la suma de los nuevos == cantidad original
            $sumaNew = 0;
            foreach ($itemsNuevos as $ni) { $sumaNew += intval($ni['Cantidad'] ?? 0); }
            if ($sumaNew !== $cantidadOriginal) { $pdo->rollBack(); resp(400, ['error'=>"La suma de cantidades ($sumaNew) debe ser igual a la original ($cantidadOriginal)"]); }

            // Validar que cada nuevo item sea del mismo producto y tenga stock
            foreach ($itemsNuevos as $ni) {
                $stk = $pdo->prepare("SELECT PCE.Cantidad, PCE.CargaID FROM ProductoCargaEstiba PCE INNER JOIN Cargas Car ON Car.CargaID=PCE.CargaID WHERE PCE.EstibaID=? AND PCE.ProductosID=? AND PCE.Cantidad >= ? AND Car.Clasificada=1");
                $stk->execute([$ni['EstibaID'], $productosId, intval($ni['Cantidad'])]);
                if (!$stk->fetch()) { $pdo->rollBack(); resp(400, ['error'=>'Estiba '.$ni['EstibaID'].' no tiene stock suficiente del mismo producto']); }
            }

            // Cancelar etiqueta provisoria del original si existe
            if ($itemOrig['EtiquetaProvisoriaID']) {
                $pdo->prepare("UPDATE EtiquetaProvisoria SET estado='X' WHERE EtiquetaProvisoriaID=?")->execute([$itemOrig['EtiquetaProvisoriaID']]);
            }

            // Borrar item original
            $pdo->prepare("DELETE FROM ProductoCargaSalida WHERE PreSalidaID=? AND EstibaID=? AND ProductosID=? AND CargaID=? AND FechaEV=?")
                ->execute([$presalidaId, $estibaOriginal, $productosId, $cargaIdOrig, $fechaEV]);

            // Insertar nuevos items
            $ins = $pdo->prepare("INSERT INTO ProductoCargaSalida (PreSalidaID, SubSalidaID, EstibaID, FechaEV, ProductosID, CargaID, Cantidad) VALUES (?,?,?,?,?,?,?)");
            foreach ($itemsNuevos as $ni) {
                // Obtener CargaID y FechaEV de la estiba nueva
                $pceInfo = $pdo->prepare("SELECT CargaID, FechaEV FROM ProductoCargaEstiba WHERE EstibaID=? AND ProductosID=? AND Cantidad > 0 LIMIT 1");
                $pceInfo->execute([$ni['EstibaID'], $productosId]);
                $info = $pceInfo->fetch();
                $ins->execute([$presalidaId, (int)($itemOrig['SubSalidaID'] ?? 1), $ni['EstibaID'], (int)($info['FechaEV'] ?? 0), $productosId, (int)($info['CargaID'] ?? $cargaIdOrig), intval($ni['Cantidad'])]);
            }

            $pdo->commit();
            resp(200, ['ok'=>true, 'mensaje'=>'Estiba reemplazada correctamente']);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            resp(500, ['error'=>$e->getMessage()]);
        }
        break;

    // ── Estibas alternativas (mismo producto, con stock) ──────────
    case 'salida_estibas_alternativas':
        requireSession();
        $productoId = intval($_GET['producto_id'] ?? 0);
        $estibaExcluir = $_GET['estiba_excluir'] ?? '';
        if (!$productoId) resp(400, ['error'=>'Falta producto_id']);
        try {
            $where = "PCE.Cantidad > 0 AND PCE.ProductosID = ? AND Car.Clasificada = 1 AND E.FechaBaja IS NULL";
            $params = [$productoId];
            if ($estibaExcluir) { $where .= " AND PCE.EstibaID != ?"; $params[] = $estibaExcluir; }

            $stmt = $pdo->prepare("
                SELECT PCE.EstibaID, PCE.CargaID, PCE.FechaEV, PCE.Cantidad,
                       PCE.FechaElaboracion, PCE.FechaVencimiento, PCE.NroLote,
                       E.NroEstiba,
                       CONCAT(COALESCE(TCA.iniciales,CONCAT('C',CAM.CamaraNumero)),' ',POS.Calle,'-',POS.Fila,'.',E.Nivel) AS ubicacion
                FROM ProductoCargaEstiba PCE
                INNER JOIN Cargas Car ON Car.CargaID=PCE.CargaID
                INNER JOIN Estiba E ON E.EstibaID=PCE.EstibaID
                LEFT JOIN Posiciones POS ON POS.PosicionID=E.PosicionID
                LEFT JOIN Camaras CAM ON CAM.CamaraID=POS.CamaraID
                WHERE $where
                ORDER BY PCE.FechaElaboracion, E.NroEstiba
                LIMIT 100
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) {
                if ($r['FechaElaboracion']) $r['FechaElaboracion'] = (new DateTime($r['FechaElaboracion']))->format('d/m/Y');
                if ($r['FechaVencimiento']) $r['FechaVencimiento'] = (new DateTime($r['FechaVencimiento']))->format('d/m/Y');
            }
            unset($r);
            resp(200, ['ok'=>true, 'estibas'=>$rows]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    // ── Etiqueta Provisoria: generar ──────────────────────────────
    case 'salida_generar_etiqueta_provisoria':
        requireSession();
        $b = json_input();
        $presalidaId = intval($b['PreSalidaID'] ?? 0);
        $estibaId    = $b['EstibaID'] ?? 0;
        $cargaId     = intval($b['CargaID'] ?? 0);
        $productosId = intval($b['ProductosID'] ?? 0);
        $fechaEV     = intval($b['FechaEV'] ?? 0);
        $cantidad    = intval($b['Cantidad'] ?? 0);
        if (!$presalidaId || !$estibaId || !$cargaId || !$productosId || $cantidad <= 0)
            resp(400, ['error'=>'Faltan campos requeridos']);
        try {
            // Verificar que existe en ProductoCargaSalida
            $check = $pdo->prepare("SELECT Cantidad FROM ProductoCargaSalida WHERE PreSalidaID=? AND EstibaID=? AND ProductosID=? AND CargaID=? AND FechaEV=?");
            $check->execute([$presalidaId, $estibaId, $productosId, $cargaId, $fechaEV]);
            $pcsQty = $check->fetchColumn();
            if ($pcsQty === false) resp(404, ['error'=>'Item no encontrado en la selección']);

            // Crear tabla si no existe
            $pdo->exec("CREATE TABLE IF NOT EXISTS `EtiquetaProvisoria` (
                `EtiquetaProvisoriaID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `PreSalidaID` int(10) UNSIGNED NOT NULL,
                `EstibaOrigenID` double NOT NULL,
                `CargaID` int(10) UNSIGNED NOT NULL,
                `ProductosID` int(10) UNSIGNED NOT NULL,
                `FechaEV` smallint(6) NOT NULL DEFAULT 0,
                `Cantidad` int(10) UNSIGNED NOT NULL,
                `codigo_qr` varchar(64) NOT NULL DEFAULT '',
                `estado` char(1) NOT NULL DEFAULT 'A',
                `usuario_id` int(10) UNSIGNED DEFAULT NULL,
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`EtiquetaProvisoriaID`),
                KEY `idx_presalida` (`PreSalidaID`),
                KEY `idx_codigo_qr` (`codigo_qr`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci");

            $pdo->beginTransaction();
            $ins = $pdo->prepare("INSERT INTO EtiquetaProvisoria (PreSalidaID, EstibaOrigenID, CargaID, ProductosID, FechaEV, Cantidad, usuario_id) VALUES (?,?,?,?,?,?,?)");
            $ins->execute([$presalidaId, $estibaId, $cargaId, $productosId, $fechaEV, $cantidad, (int)$_SESSION['cina_usuario_id']]);
            $epId = (int)$pdo->lastInsertId();
            $codigoQR = 'EP-' . $epId;
            $pdo->prepare("UPDATE EtiquetaProvisoria SET codigo_qr=? WHERE EtiquetaProvisoriaID=?")->execute([$codigoQR, $epId]);

            // Vincular en ProductoCargaSalida
            $pdo->prepare("UPDATE ProductoCargaSalida SET EtiquetaProvisoriaID=? WHERE PreSalidaID=? AND EstibaID=? AND ProductosID=? AND CargaID=? AND FechaEV=?")
                ->execute([$epId, $presalidaId, $estibaId, $productosId, $cargaId, $fechaEV]);

            $pdo->commit();

            // Obtener datos para la etiqueta
            $prod = $pdo->prepare("SELECT Descripcion1 FROM Productos WHERE ProductosID=?"); $prod->execute([$productosId]);
            $nroEst = $pdo->prepare("SELECT NroEstiba FROM Estiba WHERE EstibaID=?"); $nroEst->execute([$estibaId]);

            resp(200, ['ok'=>true, 'etiqueta'=>[
                'EtiquetaProvisoriaID'=>$epId,
                'codigo_qr'=>$codigoQR,
                'PreSalidaID'=>$presalidaId,
                'EstibaOrigenID'=>$estibaId,
                'NroEstiba'=>$nroEst->fetchColumn(),
                'CargaID'=>$cargaId,
                'producto'=>$prod->fetchColumn(),
                'Cantidad'=>$cantidad
            ]]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            resp(500, ['error'=>$e->getMessage()]);
        }
        break;

    // ── Etiqueta Provisoria: listar por pre-salida ────────────────
    case 'salida_etiquetas_provisorias':
        requireSession();
        $presalidaId = intval($_GET['presalida_id'] ?? 0);
        if (!$presalidaId) resp(400, ['error'=>'Falta presalida_id']);
        try {
            $st = $pdo->prepare("
                SELECT EP.*, P.Descripcion1 AS producto, P.CodigoProducto AS codigo, E.NroEstiba
                FROM EtiquetaProvisoria EP
                INNER JOIN Productos P ON P.ProductosID=EP.ProductosID
                LEFT JOIN Estiba E ON E.EstibaID=EP.EstibaOrigenID
                WHERE EP.PreSalidaID=? AND EP.estado='A'
                ORDER BY EP.created_at
            ");
            $st->execute([$presalidaId]);
            resp(200, ['ok'=>true, 'etiquetas'=>$st->fetchAll()]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    // ── Verificación de carga: escanear QR ────────────────────────
    case 'salida_verificacion_scan':
        requireSession();
        $b = json_input();
        $cargaNumero  = intval($b['carga_numero'] ?? 0);
        $presalidaDir = intval($b['presalida_id'] ?? 0); // alternativa: verificar por presalida directa
        $codigoQR     = trim($b['codigo_qr'] ?? '');
        if (!$codigoQR || (!$cargaNumero && !$presalidaDir)) resp(400, ['error'=>'Faltan carga_numero/presalida_id o codigo_qr']);
        try {
            // Obtener pre-salidas: por carga_numero o por presalida_id directo
            if ($presalidaDir) {
                $presalidaIds = [$presalidaDir];
                // Si no viene carga_numero, obtenerlo de la presalida
                if (!$cargaNumero) {
                    $cn = $pdo->prepare("SELECT carga_numero FROM PreSalidas WHERE PreSalidaID=?"); $cn->execute([$presalidaDir]);
                    $cargaNumero = (int)($cn->fetchColumn() ?: 0);
                }
            } else {
                $psList = $pdo->prepare("SELECT PreSalidaID FROM PreSalidas WHERE carga_numero=? AND clasificada=0");
                $psList->execute([$cargaNumero]);
                $presalidaIds = $psList->fetchAll(PDO::FETCH_COLUMN);
                if (empty($presalidaIds)) resp(404, ['error'=>'No hay pre-salidas vinculadas a esta carga']);
            }

            $placeholders = implode(',', array_fill(0, count($presalidaIds), '?'));

            // Parsear código QR
            $esProvisoria = false;
            $epId = null;
            $estibaId = null;

            if (preg_match('/^EP-(\d+)$/i', $codigoQR, $m)) {
                $esProvisoria = true;
                $epId = (int)$m[1];
                // Buscar la etiqueta provisoria
                $epSt = $pdo->prepare("SELECT * FROM EtiquetaProvisoria WHERE EtiquetaProvisoriaID=? AND estado='A'");
                $epSt->execute([$epId]);
                $ep = $epSt->fetch();
                if (!$ep) resp(404, ['error'=>'Etiqueta provisoria no encontrada o ya utilizada']);
                if (!in_array((int)$ep['PreSalidaID'], $presalidaIds))
                    resp(400, ['error'=>'Esta etiqueta provisoria no pertenece a las pre-salidas de esta carga']);

                // Buscar el item correspondiente en ProductoCargaSalida
                $pcsItem = $pdo->prepare("SELECT * FROM ProductoCargaSalida WHERE PreSalidaID=? AND EtiquetaProvisoriaID=?");
                $pcsItem->execute([$ep['PreSalidaID'], $epId]);
                $item = $pcsItem->fetch();
                if (!$item) resp(404, ['error'=>'Item no encontrado en la selección de la pre-salida']);

                // Verificar que no se escaneó antes
                $dup = $pdo->prepare("SELECT scan_id FROM SalidaVerificacionScan WHERE carga_numero=? AND EtiquetaProvisoriaID=?");
                $dup->execute([$cargaNumero, $epId]);
                if ($dup->fetch()) resp(400, ['error'=>'Esta etiqueta provisoria ya fue escaneada']);

                // Registrar scan
                $pdo->prepare("INSERT INTO SalidaVerificacionScan (carga_numero, PreSalidaID, EstibaID, EtiquetaProvisoriaID, ProductosID, CargaID_origen, FechaEV, cantidad_esperada, cantidad_verificada, usuario_id) VALUES (?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$cargaNumero, $ep['PreSalidaID'], $ep['EstibaOrigenID'], $epId, $ep['ProductosID'], $ep['CargaID'], $ep['FechaEV'], (int)$item['Cantidad'], (int)$item['Cantidad'], (int)$_SESSION['cina_usuario_id']]);

            } else {
                // Parsear como estiba normal
                if (preg_match('/#estiba\/(\d+)/', $codigoQR, $m)) $estibaId = $m[1];
                elseif (preg_match('/[?&]estibaid=(\d+)/i', $codigoQR, $m)) $estibaId = $m[1];
                elseif (preg_match('/^\d+(\.\d+)?$/', $codigoQR)) $estibaId = $codigoQR;
                else resp(400, ['error'=>'Código QR no reconocido']);

                // Buscar en ProductoCargaSalida de las pre-salidas vinculadas
                $pcsItem = $pdo->prepare("SELECT * FROM ProductoCargaSalida WHERE PreSalidaID IN ($placeholders) AND EstibaID=? AND EtiquetaProvisoriaID IS NULL ORDER BY PreSalidaID LIMIT 1");
                $pcsParams = array_merge($presalidaIds, [$estibaId]);
                $pcsItem->execute($pcsParams);
                $item = $pcsItem->fetch();
                if (!$item) resp(400, ['error'=>'Esta estiba no está en el pedido de las pre-salidas vinculadas']);

                // Verificar duplicado
                $dup = $pdo->prepare("SELECT scan_id FROM SalidaVerificacionScan WHERE carga_numero=? AND EstibaID=? AND PreSalidaID=? AND ProductosID=? AND FechaEV=?");
                $dup->execute([$cargaNumero, $estibaId, $item['PreSalidaID'], $item['ProductosID'], $item['FechaEV']]);
                if ($dup->fetch()) resp(400, ['error'=>'Esta estiba ya fue escaneada para este item']);

                // Registrar scan
                $pdo->prepare("INSERT INTO SalidaVerificacionScan (carga_numero, PreSalidaID, EstibaID, ProductosID, CargaID_origen, FechaEV, cantidad_esperada, cantidad_verificada, usuario_id) VALUES (?,?,?,?,?,?,?,?,?)")
                    ->execute([$cargaNumero, $item['PreSalidaID'], $estibaId, $item['ProductosID'], $item['CargaID'], $item['FechaEV'], (int)$item['Cantidad'], (int)$item['Cantidad'], (int)$_SESSION['cina_usuario_id']]);
            }

            // Calcular progreso
            $totalItems = $pdo->prepare("SELECT COUNT(*) FROM ProductoCargaSalida WHERE PreSalidaID IN ($placeholders)");
            $totalItems->execute($presalidaIds);
            $total = (int)$totalItems->fetchColumn();

            $verified = $pdo->prepare("SELECT COUNT(*) FROM SalidaVerificacionScan WHERE carga_numero=? AND estado='V'");
            $verified->execute([$cargaNumero]);
            $verif = (int)$verified->fetchColumn();

            resp(200, ['ok'=>true, 'mensaje'=>$esProvisoria ? 'Etiqueta provisoria verificada' : 'Estiba verificada',
                'progreso'=>['total'=>$total, 'verificados'=>$verif, 'porcentaje'=>$total > 0 ? round($verif/$total*100) : 0]]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    // ── Verificación de carga: estado completo ────────────────────
    case 'salida_verificacion_estado':
        requireSession();
        $cargaNumero  = intval($_GET['carga_numero'] ?? 0);
        $presalidaDir = intval($_GET['presalida_id'] ?? 0);
        if (!$cargaNumero && !$presalidaDir) resp(400, ['error'=>'Falta carga_numero o presalida_id']);
        try {
            if ($presalidaDir) {
                $presalidaIds = [$presalidaDir];
                if (!$cargaNumero) {
                    $cn = $pdo->prepare("SELECT carga_numero FROM PreSalidas WHERE PreSalidaID=?"); $cn->execute([$presalidaDir]);
                    $cargaNumero = (int)($cn->fetchColumn() ?: 0);
                }
            } else {
                $psList = $pdo->prepare("SELECT PreSalidaID FROM PreSalidas WHERE carga_numero=? AND clasificada=0");
                $psList->execute([$cargaNumero]);
                $presalidaIds = $psList->fetchAll(PDO::FETCH_COLUMN);
            }
            if (empty($presalidaIds)) resp(200, ['ok'=>true, 'items'=>[], 'progreso'=>['total'=>0, 'verificados'=>0, 'porcentaje'=>100], 'sin_presalidas'=>true]);

            $placeholders = implode(',', array_fill(0, count($presalidaIds), '?'));

            // Items esperados
            $st = $pdo->prepare("
                SELECT PCS.PreSalidaID, PCS.EstibaID, PCS.ProductosID, PCS.CargaID, PCS.FechaEV, PCS.Cantidad,
                       PCS.EtiquetaProvisoriaID,
                       P.Descripcion1 AS producto, P.CodigoProducto AS codigo,
                       E.NroEstiba,
                       EP.codigo_qr AS etiqueta_qr
                FROM ProductoCargaSalida PCS
                INNER JOIN Productos P ON P.ProductosID=PCS.ProductosID
                LEFT JOIN Estiba E ON E.EstibaID=PCS.EstibaID
                LEFT JOIN EtiquetaProvisoria EP ON EP.EtiquetaProvisoriaID=PCS.EtiquetaProvisoriaID
                WHERE PCS.PreSalidaID IN ($placeholders)
                ORDER BY PCS.PreSalidaID, P.Descripcion1
            ");
            $st->execute($presalidaIds);
            $items = $st->fetchAll();

            // Scans existentes (buscar por presalida_id o carga_numero)
            $scans = $pdo->prepare("SELECT * FROM SalidaVerificacionScan WHERE PreSalidaID IN ($placeholders) AND estado='V'");
            $scans->execute($presalidaIds);
            $scanRows = $scans->fetchAll();

            // Crear mapa de scans
            $scanMap = [];
            foreach ($scanRows as $s) {
                if ($s['EtiquetaProvisoriaID']) {
                    $scanMap['ep-'.$s['EtiquetaProvisoriaID']] = $s;
                } else {
                    $key = $s['PreSalidaID'].'-'.$s['EstibaID'].'-'.$s['ProductosID'].'-'.$s['FechaEV'];
                    $scanMap[$key] = $s;
                }
            }

            // Marcar cada item
            $total = count($items);
            $verificados = 0;
            foreach ($items as &$it) {
                if ($it['EtiquetaProvisoriaID']) {
                    $it['verificado'] = isset($scanMap['ep-'.$it['EtiquetaProvisoriaID']]);
                } else {
                    $key = $it['PreSalidaID'].'-'.$it['EstibaID'].'-'.$it['ProductosID'].'-'.$it['FechaEV'];
                    $it['verificado'] = isset($scanMap[$key]);
                }
                if ($it['verificado']) $verificados++;
            }
            unset($it);

            resp(200, ['ok'=>true, 'items'=>$items,
                'progreso'=>['total'=>$total, 'verificados'=>$verificados, 'porcentaje'=>$total > 0 ? round($verificados/$total*100) : 0]]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    // ── Verificación de carga: resetear ───────────────────────────
    case 'salida_verificacion_reset':
        requireSession();
        $b = json_input();
        $cargaNumero = intval($b['carga_numero'] ?? 0);
        if (!$cargaNumero) resp(400, ['error'=>'Falta carga_numero']);
        try {
            $pdo->prepare("DELETE FROM SalidaVerificacionScan WHERE carga_numero=?")->execute([$cargaNumero]);
            resp(200, ['ok'=>true, 'mensaje'=>'Verificación reseteada']);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    // ═══════════════════════════════════════════════════════════════
    //  MÓDULO LOGÍSTICA: PRE-ENTRADAS / ENTRADAS / PRE-SALIDAS / SALIDAS
    // ═══════════════════════════════════════════════════════════════

    case 'logmod_lista':
        requireSession();
        $tipo   = $_GET['tipo']   ?? 'preEntradas'; // preEntradas|entradas|preSalidas|salidas
        $fiscal = intval($_GET['fiscal'] ?? 0);
        $page   = max(1, intval($_GET['page'] ?? 1));
        $limit  = 50;
        $offset = ($page - 1) * $limit;

        // Filtros opcionales
        $clienteId   = $_GET['cliente']   ?? '';
        $numero      = $_GET['numero']    ?? '';
        $busqueda    = $_GET['busqueda']  ?? '';

        $esEntrada = in_array($tipo, ['preEntradas', 'entradas']);
        $esPre     = in_array($tipo, ['preEntradas', 'preSalidas']);

        // Filtro portal cliente
        $portalClienteIds = null;
        if (function_exists('getClienteIdsPermitidos')) {
            $portalClienteIds = getClienteIdsPermitidos($pdo, $_SESSION['cina_usuario_id'], $_SESSION['cina_rol_id'] ?? 0);
        }

        try {
            if ($esEntrada) {
                // ── PRE-ENTRADAS y ENTRADAS (tabla Cargas) ──────────────────
                $clasificada = $esPre ? 0 : 1;

                $where = "C.es_fiscal = ? AND C.clasificada = ?";
                $params = [$fiscal, $clasificada];

                // Portal cliente: solo sus clientes
                if ($portalClienteIds !== null) {
                    if (empty($portalClienteIds)) { resp(200, ['ok'=>true,'tipo'=>$tipo,'fiscal'=>$fiscal,'registros'=>[],'total'=>0,'page'=>$page,'pages'=>0]); }
                    $phCli = implode(',', array_fill(0, count($portalClienteIds), '?'));
                    $where .= " AND C.ClienteID IN ($phCli)";
                    $params = array_merge($params, $portalClienteIds);
                }

                if ($clienteId) {
                    $where .= " AND C.ClienteID = ?";
                    $params[] = (int)$clienteId;
                }
                if ($numero) {
                    $where .= " AND C.CargaID LIKE ?";
                    $params[] = "%$numero%";
                }
                if ($busqueda) {
                    $where .= " AND (C.NroRemito LIKE ? OR C.numero_referencia LIKE ? OR C.permiso_embarque LIKE ?)";
                    $params[] = "%$busqueda%";
                    $params[] = "%$busqueda%";
                    $params[] = "%$busqueda%";
                }

                // Count
                $stmtCnt = $pdo->prepare("SELECT COUNT(*) FROM Cargas C WHERE $where");
                $stmtCnt->execute($params);
                $total = (int)$stmtCnt->fetchColumn();

                $stmt = $pdo->prepare("
                    SELECT
                        C.CargaID           AS numero,
                        C.carga_numero      AS carga,
                        C.NroRemito         AS remito,
                        C.numero_referencia AS referencia,
                        C.permiso_embarque,
                        C.es_fiscal,
                        C.clasificada,
                        C.TemperaturaControlada,
                        C.devolucion,
                        C.estado_carga,
                        C.Observaciones,
                        C.updated_at,
                        CL.ClienteNombre    AS cliente,
                        CL.ClienteID,
                        TC.fecha_movimiento,
                        TC.estado           AS tc_estado,
                        TC.hora_movimiento_inicio,
                        TC.hora_movimiento_fin,
                        U.UsuarioNombre AS modificadoPor,
                        C.updated_at        AS modificadoFecha
                    FROM Cargas C
                    LEFT JOIN CLIENTES CL          ON CL.ClienteID  = C.ClienteID
                    LEFT JOIN transporte_carga TC   ON TC.carga_numero = C.carga_numero
                    LEFT JOIN Usuarios U            ON U.UsuarioID  = C.usuario_id
                    WHERE $where
                    ORDER BY C.CargaID DESC
                    LIMIT $limit OFFSET $offset
                ");
                $stmt->execute($params);
                $rows = $stmt->fetchAll();

                // Certificados para cada entrada
                $stmtCert = $pdo->prepare("
                    SELECT CONCAT(COALESCE(estab_procedencia,''), '-', COALESCE(letra,''), '-', LPAD(COALESCE(numero,''),6,'0')) AS cert
                    FROM Certificados
                    WHERE CargaID = ? AND activo = 1
                ");

                // Lookup tronera asignada por carga_numero
                $stmtTron = $pdo->prepare("SELECT CONCAT('T', t.numero) FROM transporte_tronera tt JOIN tronera t ON t.id=tt.tronera_asignada_id WHERE tt.carga_numero=? AND tt.estado IN ('A','U') ORDER BY tt.id DESC LIMIT 1");

                foreach ($rows as &$row) {
                    $stmtCert->execute([(int)$row['numero']]);
                    $certs = $stmtCert->fetchAll(PDO::FETCH_COLUMN);
                    $row['certificados'] = $certs ?: [];

                    $row['calidad'] = ((int)$row['TemperaturaControlada']) ? 'Completo' : 'Pendiente';
                    $row['destino'] = ''; // entradas no tienen destino directo

                    // Estado de transporte y tronera (para botón Iniciar/Finalizar en pre-entradas)
                    $row['tc_estado'] = $row['tc_estado'] ?? null;
                    $row['hora_inicio'] = $row['hora_movimiento_inicio'] ?? null;
                    $row['hora_fin'] = $row['hora_movimiento_fin'] ?? null;
                    if ($row['carga']) {
                        $stmtTron->execute([(int)$row['carga']]);
                        $row['tronera'] = $stmtTron->fetchColumn() ?: null;
                    } else {
                        $row['tronera'] = null;
                    }

                    // Formatear fecha
                    if ($row['modificadoFecha']) {
                        $dt = new DateTime($row['modificadoFecha']);
                        $row['modificadoFecha'] = $dt->format('d/m/Y H:i:s');
                    }
                }
                unset($row);

            } else {
                // ── PRE-SALIDAS y SALIDAS (tabla PreSalidas) ────────────────
                $clasificada = $esPre ? 0 : 1;

                $where = "PS.es_fiscal = ? AND PS.clasificada = ?";
                $params = [$fiscal, $clasificada];

                // Portal cliente: solo sus clientes
                if ($portalClienteIds !== null) {
                    if (empty($portalClienteIds)) { resp(200, ['ok'=>true,'tipo'=>$tipo,'fiscal'=>$fiscal,'registros'=>[],'total'=>0,'page'=>$page,'pages'=>0]); }
                    $phCli2 = implode(',', array_fill(0, count($portalClienteIds), '?'));
                    $where .= " AND PS.ClienteID IN ($phCli2)";
                    $params = array_merge($params, $portalClienteIds);
                }

                if ($clienteId) {
                    $where .= " AND (PS.ClienteID = ? OR PS.GrupoID IN (SELECT GrupoID FROM CLIENTES WHERE ClienteID = ?))";
                    $params[] = (int)$clienteId;
                    $params[] = (int)$clienteId;
                }
                if ($numero) {
                    $where .= " AND PS.PreSalidaID LIKE ?";
                    $params[] = "%$numero%";
                }
                if ($busqueda) {
                    $where .= " AND (PS.Remito LIKE ? OR PS.numero_referencia LIKE ? OR PS.ContratoNro LIKE ? OR PS.permiso_embarque LIKE ?)";
                    $params[] = "%$busqueda%";
                    $params[] = "%$busqueda%";
                    $params[] = "%$busqueda%";
                    $params[] = "%$busqueda%";
                }

                // Count
                $stmtCnt = $pdo->prepare("SELECT COUNT(*) FROM PreSalidas PS WHERE $where");
                $stmtCnt->execute($params);
                $total = (int)$stmtCnt->fetchColumn();

                $stmt = $pdo->prepare("
                    SELECT
                        PS.PreSalidaID       AS numero,
                        PS.carga_numero      AS carga,
                        PS.Remito            AS remito,
                        PS.numero_referencia AS referencia,
                        PS.ContratoNro   AS contrato,
                        PS.permiso_embarque,
                        PS.es_fiscal,
                        PS.clasificada,
                        PS.TemperaturaControlada,
                        PS.updated_at,
                        CL.ClienteNombre     AS cliente,
                        CL.ClienteID,
                        COALESCE(CG.Nombre, CL.ClienteNombre) AS clienteGrupo,
                        D.DestinoNombre      AS destino,
                        U.UsuarioNombre AS modificadoPor,
                        PS.updated_at        AS modificadoFecha,
                        TC.estado            AS tc_estado,
                        TC.hora_movimiento_inicio,
                        TC.hora_movimiento_fin
                    FROM PreSalidas PS
                    LEFT JOIN CLIENTES CL           ON CL.ClienteID  = PS.ClienteID
                    LEFT JOIN CLIENTEGRUPO CG       ON CG.GrupoID    = PS.GrupoID
                    LEFT JOIN Destinos D            ON D.DestinoID   = PS.DestinoID
                    LEFT JOIN Usuarios U            ON U.UsuarioID   = PS.usuario_id
                    LEFT JOIN transporte_carga TC   ON TC.carga_numero = PS.carga_numero
                    WHERE $where
                    ORDER BY PS.PreSalidaID DESC
                    LIMIT $limit OFFSET $offset
                ");
                $stmt->execute($params);
                $rows = $stmt->fetchAll();

                // Certificados internos para cada salida
                $stmtCert = $pdo->prepare("
                    SELECT CONCAT(COALESCE(estab_procedencia,''), '-', COALESCE(letra,''), '-', LPAD(COALESCE(numero,''),6,'0')) AS cert
                    FROM certificado_interno
                    WHERE salida_id = ? AND (anulado IS NULL OR anulado = 0)
                ");

                // Lookup tronera asignada por carga_numero (para pre-salidas)
                $stmtTronPS = $pdo->prepare("SELECT CONCAT('T', t.numero) FROM transporte_tronera tt JOIN tronera t ON t.id=tt.tronera_asignada_id WHERE tt.carga_numero=? AND tt.estado IN ('A','U') ORDER BY tt.id DESC LIMIT 1");

                foreach ($rows as &$row) {
                    $stmtCert->execute([(int)$row['numero']]);
                    $certs = $stmtCert->fetchAll(PDO::FETCH_COLUMN);
                    $row['certificados'] = $certs ?: [];

                    $row['calidad'] = ((int)$row['TemperaturaControlada']) ? 'Completo' : 'Pendiente';

                    // Estado de transporte y tronera (para botones Iniciar/Finalizar)
                    $row['tc_estado'] = $row['tc_estado'] ?? null;
                    $row['hora_inicio'] = $row['hora_movimiento_inicio'] ?? null;
                    $row['hora_fin'] = $row['hora_movimiento_fin'] ?? null;
                    if ($row['carga']) {
                        $stmtTronPS->execute([(int)$row['carga']]);
                        $row['tronera'] = $stmtTronPS->fetchColumn() ?: null;
                    } else {
                        $row['tronera'] = null;
                    }

                    if ($row['modificadoFecha']) {
                        $dt = new DateTime($row['modificadoFecha']);
                        $row['modificadoFecha'] = $dt->format('d/m/Y H:i:s');
                    }
                }
                unset($row);
            }

            resp(200, [
                'ok'      => true,
                'tipo'    => $tipo,
                'fiscal'  => $fiscal,
                'registros' => $rows,
                'total'   => $total,
                'page'    => $page,
                'pages'   => ceil($total / $limit),
            ]);

        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    case 'logmod_clientes':
        // Catálogo de clientes para los filtros de los módulos logísticos
        requireSession();
        $fiscal = intval($_GET['fiscal'] ?? 0);
        try {
            $where = "fiscal = ? AND Activo = 1";
            $params = [$fiscal];

            // Si es portal cliente, filtrar solo sus clientes
            if (function_exists('getClienteIdsPermitidos')) {
                $cIds = getClienteIdsPermitidos($pdo, $_SESSION['cina_usuario_id'], $_SESSION['cina_rol_id'] ?? 0);
                if ($cIds !== null) {
                    if (empty($cIds)) { resp(200, ['ok'=>true, 'clientes'=>[]]); break; }
                    $ph = implode(',', array_fill(0, count($cIds), '?'));
                    $where .= " AND ClienteID IN ($ph)";
                    $params = array_merge($params, $cIds);
                }
            }

            $stmt = $pdo->prepare("
                SELECT ClienteID AS id, ClienteNombre AS nombre
                FROM CLIENTES
                WHERE $where
                ORDER BY ClienteNombre
            ");
            $stmt->execute($params);
            resp(200, ['ok' => true, 'clientes' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ═══════════════════════════════════════════════════════════════
    //  MÓDULO CALIDAD: Control de Entradas/Salidas, Intervenciones,
    //  Vencimientos, Estadísticas
    // ═══════════════════════════════════════════════════════════════

    case 'calidad_catalogos':
        requireSession();
        try {
            $motivos = $pdo->query("SELECT motivo_intervencion_id AS id, descripcion FROM intervencion_motivo WHERE activo = 1 ORDER BY descripcion")->fetchAll();
            $motivosLib = $pdo->query("SELECT motivo_liberacion_id AS id, descripcion FROM intervencion_motivo_liberacion WHERE activo = 1 ORDER BY descripcion")->fetchAll();
            $clientes = $pdo->query("SELECT ClienteID AS id, ClienteNombre AS nombre FROM CLIENTES WHERE activo = 1 ORDER BY ClienteNombre")->fetchAll();
            resp(200, [
                'ok' => true,
                'motivos_intervencion' => $motivos,
                'motivos_liberacion'   => $motivosLib,
                'clientes'             => $clientes,
            ]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    case 'calidad_entradas':
    case 'calidad_salidas':
        requireSession();
        $esEntrada  = ($action === 'calidad_entradas');
        $clienteId  = $_GET['cliente_id'] ?? '';
        $desde      = $_GET['desde'] ?? '';
        $hasta      = $_GET['hasta'] ?? '';
        $verificado = $_GET['verificado'] ?? ''; // ''=todos, '1'=verificado, '0'=pendiente
        $page       = max(1, intval($_GET['page'] ?? 1));
        $limit      = 50;
        $offset     = ($page - 1) * $limit;

        // Filtro portal cliente
        $calPortalIds = null;
        if (function_exists('getClienteIdsPermitidos')) {
            $calPortalIds = getClienteIdsPermitidos($pdo, $_SESSION['cina_usuario_id'], $_SESSION['cina_rol_id'] ?? 0);
        }

        try {
            if ($esEntrada) {
                $where = "1=1";
                $params = [];
                // Portal cliente
                if ($calPortalIds !== null) {
                    if (empty($calPortalIds)) { resp(200, ['ok'=>true,'registros'=>[],'total'=>0,'page'=>$page,'pages'=>0]); break; }
                    $phC = implode(',', array_fill(0, count($calPortalIds), '?'));
                    $where .= " AND C.ClienteID IN ($phC)";
                    $params = array_merge($params, $calPortalIds);
                }
                if ($clienteId) { $where .= " AND C.ClienteID = ?"; $params[] = (int)$clienteId; }
                if ($desde)     { $where .= " AND TC.fecha_movimiento >= ?"; $params[] = $desde; }
                if ($hasta)     { $where .= " AND TC.fecha_movimiento <= ?"; $params[] = $hasta; }
                if ($verificado !== '') { $where .= " AND C.TemperaturaControlada = ?"; $params[] = (int)$verificado; }

                $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM Cargas C LEFT JOIN transporte_carga TC ON TC.carga_numero = C.carga_numero WHERE $where");
                $cntStmt->execute($params);
                $total = (int)$cntStmt->fetchColumn();

                $stmt = $pdo->prepare("
                    SELECT
                        C.CargaID AS id, C.carga_numero AS carga, CL.ClienteNombre AS cliente,
                        C.NroRemito AS remito, C.es_fiscal,
                        C.TemperaturaControlada AS verificado,
                        C.estado_carga,
                        TC.fecha_movimiento,
                        (SELECT COUNT(*) FROM CargasControlTransporte WHERE EntradaID = C.CargaID) AS tiene_pr05,
                        (SELECT COUNT(*) FROM CargasControlTemp WHERE EntradaID = C.CargaID) AS controles_temp,
                        (SELECT COUNT(*) FROM intervencion_estiba_producto WHERE carga_id = C.CargaID AND estado = 'I') AS intervenciones_abiertas,
                        (SELECT COUNT(*) FROM intervencion_estiba_producto WHERE carga_id = C.CargaID) AS intervenciones_total,
                        U.UsuarioNombre AS modificadoPor,
                        C.updated_at AS modificadoFecha
                    FROM Cargas C
                    LEFT JOIN CLIENTES CL ON CL.ClienteID = C.ClienteID
                    LEFT JOIN transporte_carga TC ON TC.carga_numero = C.carga_numero
                    LEFT JOIN Usuarios U ON U.UsuarioID = C.usuario_id
                    WHERE $where
                    ORDER BY C.CargaID DESC
                    LIMIT $limit OFFSET $offset
                ");
                $stmt->execute($params);
            } else {
                $where = "PS.clasificada = 1";
                $params = [];
                // Portal cliente
                if ($calPortalIds !== null) {
                    if (empty($calPortalIds)) { resp(200, ['ok'=>true,'registros'=>[],'total'=>0,'page'=>$page,'pages'=>0]); break; }
                    $phC2 = implode(',', array_fill(0, count($calPortalIds), '?'));
                    $where .= " AND PS.ClienteID IN ($phC2)";
                    $params = array_merge($params, $calPortalIds);
                }
                if ($clienteId) { $where .= " AND PS.ClienteID = ?"; $params[] = (int)$clienteId; }
                if ($desde)     { $where .= " AND TC.fecha_movimiento >= ?"; $params[] = $desde; }
                if ($hasta)     { $where .= " AND TC.fecha_movimiento <= ?"; $params[] = $hasta; }
                if ($verificado !== '') { $where .= " AND PS.TemperaturaControlada = ?"; $params[] = (int)$verificado; }

                $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM PreSalidas PS LEFT JOIN transporte_carga TC ON TC.carga_numero = PS.carga_numero WHERE $where");
                $cntStmt->execute($params);
                $total = (int)$cntStmt->fetchColumn();

                $stmt = $pdo->prepare("
                    SELECT
                        PS.PreSalidaID AS id, PS.carga_numero AS carga,
                        COALESCE(CG.Nombre, CL.ClienteNombre) AS cliente,
                        PS.Remito AS remito, PS.es_fiscal,
                        PS.TemperaturaControlada AS verificado,
                        TC.fecha_movimiento,
                        (SELECT COUNT(*) FROM CargasControlTransporte WHERE SalidaID = PS.PreSalidaID) AS tiene_pr05,
                        (SELECT COUNT(*) FROM CargasControlTemp WHERE SalidaID = PS.PreSalidaID) AS controles_temp,
                        0 AS intervenciones_abiertas,
                        0 AS intervenciones_total,
                        U.UsuarioNombre AS modificadoPor,
                        PS.updated_at AS modificadoFecha
                    FROM PreSalidas PS
                    LEFT JOIN CLIENTES CL ON CL.ClienteID = PS.ClienteID
                    LEFT JOIN CLIENTEGRUPO CG ON CG.GrupoID = PS.GrupoID
                    LEFT JOIN transporte_carga TC ON TC.carga_numero = PS.carga_numero
                    LEFT JOIN Usuarios U ON U.UsuarioID = PS.usuario_id
                    WHERE $where
                    ORDER BY PS.PreSalidaID DESC
                    LIMIT $limit OFFSET $offset
                ");
                $stmt->execute($params);
            }

            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) {
                $r['verificado']     = (int)$r['verificado'];
                $r['tiene_pr05']     = (int)$r['tiene_pr05'] > 0;
                $r['controles_temp'] = (int)$r['controles_temp'];
                if ($r['modificadoFecha']) {
                    $dt = new DateTime($r['modificadoFecha']);
                    $r['modificadoFecha'] = $dt->format('d/m/Y H:i');
                }
            }
            unset($r);

            resp(200, ['ok' => true, 'registros' => $rows, 'total' => $total, 'page' => $page, 'pages' => ceil($total / $limit)]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    case 'calidad_ver':
        requireSession();
        $id   = intval($_GET['id'] ?? 0);
        $tipo = $_GET['tipo'] ?? 'entrada'; // entrada|salida
        if (!$id) resp(400, ['error' => 'Falta id']);

        try {
            // Registro base
            if ($tipo === 'entrada') {
                $stmt = $pdo->prepare("
                    SELECT C.CargaID AS id, C.carga_numero AS carga, CL.ClienteNombre AS cliente,
                           C.NroRemito AS remito, C.numero_referencia AS referencia, C.es_fiscal,
                           C.clasificada, C.TemperaturaControlada AS verificado, C.estado_carga,
                           C.estado AS estado_frio,
                           C.devolucion, C.Observaciones, C.updated_at
                    FROM Cargas C LEFT JOIN CLIENTES CL ON CL.ClienteID = C.ClienteID
                    WHERE C.CargaID = ?
                ");
                $stmt->execute([$id]);
                $fkCol = 'EntradaID';
            } else {
                $stmt = $pdo->prepare("
                    SELECT PS.PreSalidaID AS id, PS.carga_numero AS carga,
                           COALESCE(CG.Nombre, CL.ClienteNombre) AS cliente,
                           PS.Remito AS remito, PS.numero_referencia AS referencia, PS.es_fiscal,
                           PS.clasificada, PS.TemperaturaControlada AS verificado,
                           PS.ContratoNro AS contrato, PS.updated_at
                    FROM PreSalidas PS
                    LEFT JOIN CLIENTES CL ON CL.ClienteID = PS.ClienteID
                    LEFT JOIN CLIENTEGRUPO CG ON CG.GrupoID = PS.GrupoID
                    WHERE PS.PreSalidaID = ?
                ");
                $stmt->execute([$id]);
                $fkCol = 'SalidaID';
            }
            $registro = $stmt->fetch();
            if (!$registro) resp(404, ['error' => 'Registro no encontrado']);

            // Productos en estibas (solo para entradas)
            $productos = [];
            if ($tipo === 'entrada') {
                $sp = $pdo->prepare("
                    SELECT PCE.CargaID, PCE.ProductosID, PCE.FechaEV, PCE.EstibaID,
                           PCE.Cantidad, PCE.NroLote AS lote, PCE.FechaElaboracion, PCE.FechaVencimiento,
                           PCE.FechaCongelado, PCE.FechaFaena, PCE.NroTropa, PCE.nro_tambor,
                           P.CodigoProducto AS codigo, P.Descripcion1 AS producto,
                           E.EstibaID AS estiba_id
                    FROM ProductoCargaEstiba PCE
                    INNER JOIN Productos P ON P.ProductosID = PCE.ProductosID
                    INNER JOIN Estiba E ON E.EstibaID = PCE.EstibaID
                    WHERE PCE.CargaID = ? AND PCE.Cantidad > 0
                    ORDER BY E.EstibaID, P.Descripcion1
                ");
                $sp->execute([$id]);
                $productos = $sp->fetchAll();
                foreach ($productos as &$p) {
                    if ($p['FechaElaboracion']) $p['FechaElaboracion'] = (new DateTime($p['FechaElaboracion']))->format('d/m/Y');
                    if ($p['FechaVencimiento']) $p['FechaVencimiento'] = (new DateTime($p['FechaVencimiento']))->format('d/m/Y');
                    if ($p['FechaCongelado'])   $p['FechaCongelado']   = (new DateTime($p['FechaCongelado']))->format('d/m/Y');
                    if ($p['FechaFaena'])       $p['FechaFaena']       = (new DateTime($p['FechaFaena']))->format('d/m/Y');
                }
                unset($p);
            }

            // PR-05 Control Transporte
            $sp2 = $pdo->prepare("SELECT * FROM CargasControlTransporte WHERE $fkCol = ? LIMIT 1");
            $sp2->execute([$id]);
            $pr05 = $sp2->fetch() ?: null;

            // Controles de temperatura
            $sp3 = $pdo->prepare("
                SELECT CCT.ControlTemperaturaID, CCT.FechaMedicion, CCT.NroTermometro,
                       CCT.Tratamiento, CCT.NroControl,
                       CCM.MuestraID, CCM.Temperatura, CCM.Observaciones AS obs_med,
                       P.Descripcion1 AS producto_med
                FROM CargasControlTemp CCT
                LEFT JOIN CargasControlTempMedicion CCM ON CCM.ControlTemperaturaID = CCT.ControlTemperaturaID
                LEFT JOIN Productos P ON P.ProductosID = CCM.ProductosID
                WHERE CCT.$fkCol = ?
                ORDER BY CCT.NroControl, CCM.MuestraID
            ");
            $sp3->execute([$id]);
            $tempRows = $sp3->fetchAll();
            // Agrupar por control
            $temperaturas = [];
            foreach ($tempRows as $tr) {
                $ctrlId = $tr['ControlTemperaturaID'];
                if (!isset($temperaturas[$ctrlId])) {
                    $temperaturas[$ctrlId] = [
                        'id' => $ctrlId, 'fecha' => $tr['FechaMedicion'],
                        'termometro' => $tr['NroTermometro'], 'tratamiento' => $tr['Tratamiento'],
                        'nroControl' => $tr['NroControl'], 'mediciones' => []
                    ];
                }
                if ($tr['MuestraID'] !== null) {
                    $temperaturas[$ctrlId]['mediciones'][] = [
                        'muestra' => $tr['MuestraID'], 'temperatura' => $tr['Temperatura'],
                        'producto' => $tr['producto_med'], 'obs' => $tr['obs_med']
                    ];
                }
            }
            $temperaturas = array_values($temperaturas);

            // Intervenciones
            $sp4 = $pdo->prepare("
                SELECT iep.intervencion_id, iep.estiba_id, iep.estado,
                       iep.cantidad_intervenido, iep.causa,
                       iep.fecha_intervencion, iep.fecha_liberacion,
                       iep.comentarios_liberacion, iep.producto_id, iep.fecha_ev,
                       im.descripcion AS motivo_desc,
                       iml.descripcion AS liberacion_desc,
                       P.Descripcion1 AS producto,
                       UI.UsuarioNombre AS usuario_intervencion,
                       UL.UsuarioNombre AS usuario_liberacion
                FROM intervencion_estiba_producto iep
                LEFT JOIN intervencion_motivo im ON im.motivo_intervencion_id = iep.motivo_intervencion
                LEFT JOIN intervencion_motivo_liberacion iml ON iml.motivo_liberacion_id = iep.motivo_liberacion
                LEFT JOIN Productos P ON P.ProductosID = iep.producto_id
                LEFT JOIN Usuarios UI ON UI.UsuarioID = iep.usuario_intervencion
                LEFT JOIN Usuarios UL ON UL.UsuarioID = iep.usuario_liberacion
                WHERE iep.carga_id = ?
                ORDER BY iep.fecha_intervencion DESC
            ");
            $sp4->execute([$id]);
            $intervenciones = $sp4->fetchAll();
            foreach ($intervenciones as &$iv) {
                if ($iv['fecha_intervencion']) $iv['fecha_intervencion'] = (new DateTime($iv['fecha_intervencion']))->format('d/m/Y');
                if ($iv['fecha_liberacion'])   $iv['fecha_liberacion']   = (new DateTime($iv['fecha_liberacion']))->format('d/m/Y');
            }
            unset($iv);

            resp(200, [
                'ok'             => true,
                'registro'       => $registro,
                'productos'      => $productos,
                'pr05'           => $pr05,
                'temperaturas'   => $temperaturas,
                'intervenciones' => $intervenciones,
            ]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    case 'calidad_intervenciones':
        requireSession();
        $clienteId = $_GET['cliente_id'] ?? '';
        $estado    = $_GET['estado'] ?? '';  // I|L
        $cargaId   = $_GET['carga_id'] ?? '';
        $page      = max(1, intval($_GET['page'] ?? 1));
        $limit     = 50;
        $offset    = ($page - 1) * $limit;

        try {
            $where  = "1=1";
            $params = [];
            if ($estado)    { $where .= " AND iep.estado = ?"; $params[] = $estado; }
            if ($clienteId) { $where .= " AND C.ClienteID = ?"; $params[] = (int)$clienteId; }
            if ($cargaId)   { $where .= " AND iep.carga_id = ?"; $params[] = (int)$cargaId; }

            $cntStmt = $pdo->prepare("
                SELECT COUNT(*) FROM intervencion_estiba_producto iep
                INNER JOIN Cargas C ON C.CargaID = iep.carga_id
                WHERE $where
            ");
            $cntStmt->execute($params);
            $total = (int)$cntStmt->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT iep.intervencion_id, iep.carga_id, iep.estiba_id,
                       iep.estado, iep.cantidad_intervenido, iep.causa,
                       iep.fecha_intervencion, iep.fecha_liberacion,
                       iep.comentarios_liberacion, iep.motivo_intervencion AS motivo_id,
                       im.descripcion AS motivo_desc,
                       iml.descripcion AS liberacion_desc,
                       CL.ClienteNombre AS cliente, C.NroRemito AS remito,
                       P.Descripcion1 AS producto
                FROM intervencion_estiba_producto iep
                INNER JOIN Cargas C ON C.CargaID = iep.carga_id
                INNER JOIN CLIENTES CL ON CL.ClienteID = C.ClienteID
                INNER JOIN Productos P ON P.ProductosID = iep.producto_id
                LEFT JOIN intervencion_motivo im ON im.motivo_intervencion_id = iep.motivo_intervencion
                LEFT JOIN intervencion_motivo_liberacion iml ON iml.motivo_liberacion_id = iep.motivo_liberacion
                WHERE $where
                ORDER BY iep.fecha_intervencion DESC
                LIMIT $limit OFFSET $offset
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) {
                if ($r['fecha_intervencion']) $r['fecha_intervencion'] = (new DateTime($r['fecha_intervencion']))->format('d/m/Y');
                if ($r['fecha_liberacion'])   $r['fecha_liberacion']   = (new DateTime($r['fecha_liberacion']))->format('d/m/Y');
            }
            unset($r);

            resp(200, ['ok' => true, 'registros' => $rows, 'total' => $total, 'page' => $page, 'pages' => ceil($total / $limit)]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    case 'calidad_vencimientos':
        requireSession();
        $clienteId = $_GET['cliente_id'] ?? '';
        $dias      = intval($_GET['dias'] ?? 90);
        $page      = max(1, intval($_GET['page'] ?? 1));
        $limit     = 50;
        $offset    = ($page - 1) * $limit;

        try {
            $where  = "PCE.Cantidad > 0 AND PCE.FechaVencimiento IS NOT NULL AND PCE.FechaVencimiento <= DATE_ADD(CURDATE(), INTERVAL ? DAY)";
            $params = [$dias];
            if ($clienteId) { $where .= " AND C.ClienteID = ?"; $params[] = (int)$clienteId; }

            $paramsCnt = $params;
            $cntStmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM ProductoCargaEstiba PCE
                INNER JOIN Cargas C ON C.CargaID = PCE.CargaID
                WHERE $where
            ");
            $cntStmt->execute($paramsCnt);
            $total = (int)$cntStmt->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT P.Descripcion1 AS producto, P.CodigoProducto AS codigo,
                       PCE.FechaVencimiento, DATEDIFF(PCE.FechaVencimiento, CURDATE()) AS dias_para_vencer,
                       PCE.Cantidad, PCE.NroLote AS lote, PCE.EstibaID AS estiba_id,
                       PCE.CargaID AS carga_id,
                       CL.ClienteNombre AS cliente
                FROM ProductoCargaEstiba PCE
                INNER JOIN Productos P ON P.ProductosID = PCE.ProductosID
                INNER JOIN Cargas C ON C.CargaID = PCE.CargaID
                INNER JOIN CLIENTES CL ON CL.ClienteID = C.ClienteID
                WHERE $where
                ORDER BY PCE.FechaVencimiento ASC
                LIMIT $limit OFFSET $offset
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) {
                if ($r['FechaVencimiento']) $r['FechaVencimiento'] = (new DateTime($r['FechaVencimiento']))->format('d/m/Y');
                $r['dias_para_vencer'] = (int)$r['dias_para_vencer'];
            }
            unset($r);

            resp(200, ['ok' => true, 'registros' => $rows, 'total' => $total, 'page' => $page, 'pages' => ceil($total / $limit)]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    case 'calidad_estadisticas':
        requireSession();
        try {
            $intervenciones = $pdo->query("
                SELECT im.descripcion, COUNT(*) AS cantidad
                FROM intervencion_estiba_producto iep
                INNER JOIN intervencion_motivo im ON iep.motivo_intervencion = im.motivo_intervencion_id
                WHERE iep.motivo_intervencion IS NOT NULL
                GROUP BY iep.motivo_intervencion, im.descripcion
                ORDER BY cantidad DESC
            ")->fetchAll();
            $totalInt = array_sum(array_column($intervenciones, 'cantidad'));

            $liberaciones = $pdo->query("
                SELECT iml.descripcion, COUNT(*) AS cantidad
                FROM intervencion_estiba_producto iep
                INNER JOIN intervencion_motivo_liberacion iml ON iep.motivo_liberacion = iml.motivo_liberacion_id
                WHERE iep.motivo_liberacion IS NOT NULL
                GROUP BY iep.motivo_liberacion, iml.descripcion
                ORDER BY cantidad DESC
            ")->fetchAll();
            $totalLib = array_sum(array_column($liberaciones, 'cantidad'));

            foreach ($intervenciones as &$r) {
                $r['porcentaje'] = $totalInt > 0 ? round($r['cantidad'] / $totalInt * 100, 1) : 0;
            }
            foreach ($liberaciones as &$r) {
                $r['porcentaje'] = $totalLib > 0 ? round($r['cantidad'] / $totalLib * 100, 1) : 0;
            }
            unset($r);

            resp(200, [
                'ok' => true,
                'intervenciones' => $intervenciones, 'total_intervenciones' => $totalInt,
                'liberaciones'   => $liberaciones,   'total_liberaciones'   => $totalLib,
            ]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    case 'calidad_intervenir':
        requireSession();
        $body = json_input();
        $required = ['carga_id','producto_id','fecha_ev','estiba_id','cantidad_intervenido','causa','motivo_intervencion'];
        foreach ($required as $f) { if (!isset($body[$f])) resp(400, ['error' => "Falta campo: $f"]); }
        try {
            $stmt = $pdo->prepare("
                INSERT INTO intervencion_estiba_producto
                    (carga_id, producto_id, fecha_ev, estiba_id, estado, cantidad_intervenido,
                     causa, motivo_intervencion, fecha_intervencion, usuario_intervencion)
                VALUES (?, ?, ?, ?, 'I', ?, ?, ?, CURDATE(), ?)
            ");
            $stmt->execute([
                (int)$body['carga_id'], (int)$body['producto_id'], (int)$body['fecha_ev'],
                $body['estiba_id'], (int)$body['cantidad_intervenido'],
                $body['causa'], (int)$body['motivo_intervencion'],
                (int)$_SESSION['cina_usuario_id']
            ]);
            resp(201, ['ok' => true, 'intervencion_id' => (int)$pdo->lastInsertId()]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    case 'calidad_liberar':
        requireSession();
        $body = json_input();
        if (empty($body['intervencion_id'])) resp(400, ['error' => 'Falta intervencion_id']);
        try {
            $stmt = $pdo->prepare("
                UPDATE intervencion_estiba_producto
                SET estado = 'L', motivo_liberacion = ?, fecha_liberacion = CURDATE(),
                    comentarios_liberacion = ?, usuario_liberacion = ?
                WHERE intervencion_id = ? AND estado = 'I'
            ");
            $stmt->execute([
                (int)($body['motivo_liberacion'] ?? 0),
                $body['comentarios_liberacion'] ?? '',
                (int)$_SESSION['cina_usuario_id'],
                (int)$body['intervencion_id']
            ]);
            if ($stmt->rowCount() === 0) resp(404, ['error' => 'Intervención no encontrada o ya liberada']);
            resp(200, ['ok' => true]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    case 'calidad_verificar':
        requireSession();
        $body = json_input();
        $id       = intval($body['id'] ?? 0);
        $tipo     = $body['tipo'] ?? 'entrada';
        $verificar= (int)($body['verificar'] ?? 1);
        if (!$id) resp(400, ['error' => 'Falta id']);

        try {
            if ($tipo === 'entrada' && $verificar) {
                // Validar reglas antes de verificar
                $chk = $pdo->prepare("SELECT estado, estado_carga FROM Cargas WHERE CargaID = ?");
                $chk->execute([$id]);
                $crgChk = $chk->fetch();
                if ($crgChk) {
                    // Verificar que exista PR-05
                    $pr05 = $pdo->prepare("SELECT COUNT(*) FROM CargasControlTransporte WHERE EntradaID = ?");
                    $pr05->execute([$id]);
                    if ((int)$pr05->fetchColumn() === 0) resp(400, ['error'=>'Falta completar el control de transporte (PR-05)']);

                    // Verificar que exista al menos 1 control de temperatura
                    $temps = $pdo->prepare("SELECT COUNT(*) FROM CargasControlTemp WHERE EntradaID = ?");
                    $temps->execute([$id]);
                    if ((int)$temps->fetchColumn() === 0) resp(400, ['error'=>'Falta registrar al menos un control de temperatura']);

                    // Si tiene productos congelados: estado de frío debe ser 3 (OK)
                    $frozen = $pdo->prepare("SELECT COUNT(*) FROM ProductosCarga WHERE CargaID = ? AND Congelado = 1");
                    $frozen->execute([$id]);
                    if ((int)$frozen->fetchColumn() > 0 && (int)($crgChk['estado'] ?? 0) !== 3) {
                        resp(400, ['error'=>'La entrada tiene productos congelados y aún está completando frío (temperatura no alcanzó -15°C)']);
                    }

                    // Si está en cuarentena, advertir
                    if ((int)$crgChk['estado_carga'] === 2) {
                        resp(400, ['error'=>'La entrada está en cuarentena. Debe liberarse antes de verificar calidad.']);
                    }
                }
            }

            if ($tipo === 'entrada') {
                $stmt = $pdo->prepare("UPDATE Cargas SET TemperaturaControlada = ?, TemperaturaControladaFecha = NOW(), temperatura_control_usuario = ?, usuario_id = ?, updated_at = NOW() WHERE CargaID = ?");
                $stmt->execute([$verificar, (int)$_SESSION['cina_usuario_id'], (int)$_SESSION['cina_usuario_id'], $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE PreSalidas SET TemperaturaControlada = ?, TemperaturaControladaFecha = NOW(), temperatura_control_usuario = ?, usuario_id = ?, updated_at = NOW() WHERE PreSalidaID = ?");
                $stmt->execute([$verificar, (int)$_SESSION['cina_usuario_id'], (int)$_SESSION['cina_usuario_id'], $id]);
            }
            resp(200, ['ok' => true]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    case 'calidad_cambiar_estado_carga':
        // Cambiar estado sanitario: 1=Liberada, 2=Cuarentena
        requireSession();
        $body = json_input();
        $id = intval($body['id'] ?? 0);
        $estadoNuevo = intval($body['estado_carga'] ?? 0);
        if (!$id || !in_array($estadoNuevo, [1, 2])) resp(400, ['error'=>'Datos inválidos']);
        try {
            $pdo->prepare("UPDATE Cargas SET estado_carga = ?, usuario_id = ?, updated_at = NOW() WHERE CargaID = ?")
                ->execute([$estadoNuevo, (int)$_SESSION['cina_usuario_id'], $id]);
            resp(200, ['ok'=>true, 'estado_carga'=>$estadoNuevo]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    // ═══════════════════════════════════════════════════════════════
    //  MÓDULO TRANSPORTE: CRUD Aseguradoras, Empresas, Conductores,
    //  Vehículos, Acoplados
    // ═══════════════════════════════════════════════════════════════

    case 'transporte_lista':
        requireSession();
        $entidad = $_GET['entidad'] ?? ''; // aseguradoras|empresas|conductores|vehiculos|acoplados
        $soloActivos = intval($_GET['activos'] ?? 1);
        $busqueda = $_GET['q'] ?? '';

        $tablas = [
            'aseguradoras' => ['tabla'=>'transporte_aseguradora','pk'=>'aseguradora_id',
                'cols'=>'aseguradora_id AS id, nombre, cuit, domicilio, localidad, telefono, email, activo'],
            'empresas' => ['tabla'=>'transporte_empresa','pk'=>'transporte_id',
                'cols'=>'transporte_id AS id, razon_social AS nombre, cuit, domicilio, localidad, telefono, email, activo'],
            'conductores' => ['tabla'=>'transporte_conductor','pk'=>'conductor_id',
                'cols'=>"conductor_id AS id, CONCAT(apellido,' ',nombre) AS nombre, documento_numero AS documento, numero_celular AS celular, carnet_conducir AS carnet, carnet_vencimiento, activo"],
            'vehiculos' => ['tabla'=>'transporte_vehiculo','pk'=>'vehiculo_id',
                'cols'=>'vehiculo_id AS id, patente, habilitacion_senasa, habilitacion_vencimiento, vtv_vencimiento, aseguradora_vencimiento, activo'],
            'acoplados' => ['tabla'=>'transporte_acoplado','pk'=>'acoplado_id',
                'cols'=>'acoplado_id AS id, patente, habilitacion_senasa, habilitacion_vencimiento, vtv_vencimiento, aseguradora_vencimiento, activo'],
        ];
        if (!isset($tablas[$entidad])) resp(400, ['error'=>'Entidad inválida: '.$entidad]);
        $t = $tablas[$entidad];

        try {
            $where = $soloActivos ? "WHERE activo = 1" : "WHERE 1=1";
            $params = [];
            if ($busqueda) {
                if ($entidad === 'conductores') {
                    $where .= " AND (nombre LIKE ? OR apellido LIKE ? OR documento_numero LIKE ?)";
                    $params = array_merge($params, ["%$busqueda%","%$busqueda%","%$busqueda%"]);
                } elseif ($entidad === 'vehiculos' || $entidad === 'acoplados') {
                    $where .= " AND patente LIKE ?";
                    $params[] = "%$busqueda%";
                } else {
                    $colBusq = $entidad === 'empresas' ? 'razon_social' : 'nombre';
                    $where .= " AND ($colBusq LIKE ? OR cuit LIKE ?)";
                    $params = array_merge($params, ["%$busqueda%","%$busqueda%"]);
                }
            }
            $stmt = $pdo->prepare("SELECT {$t['cols']} FROM {$t['tabla']} $where ORDER BY 2");
            $stmt->execute($params);
            resp(200, ['ok'=>true, 'registros'=>$stmt->fetchAll()]);
        } catch (Exception $e) {
            resp(500, ['error'=>$e->getMessage()]);
        }
        break;

    case 'transporte_ver':
        requireSession();
        $entidad = $_GET['entidad'] ?? '';
        $id = intval($_GET['id'] ?? 0);
        if (!$id) resp(400, ['error'=>'Falta id']);

        $tablas = [
            'aseguradoras'=>['tabla'=>'transporte_aseguradora','pk'=>'aseguradora_id'],
            'empresas'=>['tabla'=>'transporte_empresa','pk'=>'transporte_id'],
            'conductores'=>['tabla'=>'transporte_conductor','pk'=>'conductor_id'],
            'vehiculos'=>['tabla'=>'transporte_vehiculo','pk'=>'vehiculo_id'],
            'acoplados'=>['tabla'=>'transporte_acoplado','pk'=>'acoplado_id'],
        ];
        if (!isset($tablas[$entidad])) resp(400, ['error'=>'Entidad inválida']);
        $t = $tablas[$entidad];

        try {
            $stmt = $pdo->prepare("SELECT * FROM {$t['tabla']} WHERE {$t['pk']} = ?");
            $stmt->execute([$id]);
            $reg = $stmt->fetch();
            if (!$reg) resp(404, ['error'=>'No encontrado']);
            resp(200, ['ok'=>true, 'registro'=>$reg]);
        } catch (Exception $e) {
            resp(500, ['error'=>$e->getMessage()]);
        }
        break;

    case 'transporte_guardar':
        requireSession();
        $body = json_input();
        $entidad = $body['entidad'] ?? '';
        $campos = $body['campos'] ?? [];
        $id = intval($body['id'] ?? 0);

        $tablas = [
            'aseguradoras'=>['tabla'=>'transporte_aseguradora','pk'=>'aseguradora_id',
                'editables'=>['nombre','cuit','domicilio','localidad','telefono','email','activo']],
            'empresas'=>['tabla'=>'transporte_empresa','pk'=>'transporte_id',
                'editables'=>['razon_social','cuit','domicilio','localidad','codigo_postal','telefono','email','activo']],
            'conductores'=>['tabla'=>'transporte_conductor','pk'=>'conductor_id',
                'editables'=>['nombre','apellido','documento_numero','fecha_nacimiento','carnet_conducir','carnet_vencimiento','numero_celular','activo']],
            'vehiculos'=>['tabla'=>'transporte_vehiculo','pk'=>'vehiculo_id',
                'editables'=>['patente','patente_vencimiento','vtv_vencimiento','ruta_vencimiento','aseguradora_id','aseguradora_numero','aseguradora_vencimiento','habilitacion_senasa','habilitacion_clase','habilitacion_vencimiento','transporte_id','activo']],
            'acoplados'=>['tabla'=>'transporte_acoplado','pk'=>'acoplado_id',
                'editables'=>['patente','patente_vencimiento','vtv_vencimiento','ruta_vencimiento','aseguradora_id','aseguradora_numero','aseguradora_vencimiento','habilitacion_senasa','habilitacion_clase','habilitacion_vencimiento','transporte_id','activo']],
        ];
        if (!isset($tablas[$entidad])) resp(400, ['error'=>'Entidad inválida']);
        $t = $tablas[$entidad];

        // Filtrar solo campos editables
        $data = [];
        foreach ($t['editables'] as $col) {
            if (array_key_exists($col, $campos)) $data[$col] = $campos[$col];
        }
        if (empty($data)) resp(400, ['error'=>'Sin campos para guardar']);

        try {
            if ($id > 0) {
                // UPDATE
                $sets = implode(', ', array_map(fn($c) => "$c = ?", array_keys($data)));
                $vals = array_values($data);
                $vals[] = $id;
                $stmt = $pdo->prepare("UPDATE {$t['tabla']} SET $sets, updated_at = NOW(), usuario_id = ? WHERE {$t['pk']} = ?");
                $allVals = array_values($data);
                $allVals[] = (int)$_SESSION['cina_usuario_id'];
                $allVals[] = $id;
                $setsParts = [];
                foreach (array_keys($data) as $col) $setsParts[] = "$col = ?";
                $setsStr = implode(', ', $setsParts) . ', updated_at = NOW(), usuario_id = ?';
                $stmt = $pdo->prepare("UPDATE {$t['tabla']} SET $setsStr WHERE {$t['pk']} = ?");
                $stmt->execute($allVals);
                resp(200, ['ok'=>true, 'id'=>$id]);
            } else {
                // INSERT
                $data['usuario_id'] = (int)$_SESSION['cina_usuario_id'];
                $data['activo'] = $data['activo'] ?? 1;
                $data['created_at'] = date('Y-m-d H:i:s');
                $data['updated_at'] = date('Y-m-d H:i:s');
                $cols = implode(', ', array_keys($data));
                $placeholders = implode(', ', array_fill(0, count($data), '?'));
                $stmt = $pdo->prepare("INSERT INTO {$t['tabla']} ($cols) VALUES ($placeholders)");
                $stmt->execute(array_values($data));
                resp(201, ['ok'=>true, 'id'=>(int)$pdo->lastInsertId()]);
            }
        } catch (Exception $e) {
            resp(500, ['error'=>$e->getMessage()]);
        }
        break;

    // ═══════════════════════════════════════════════════════════════
    //  MÓDULO REPORTES: Stock
    // ═══════════════════════════════════════════════════════════════

    case 'reporte_stock':
        requireSession();
        $clienteId = $_GET['cliente_id'] ?? '';

        try {
            $where = "PCE.Cantidad > 0 AND Car.Clasificada = 1";
            $params = [];
            if ($clienteId) {
                $where .= " AND Car.ClienteID = ?";
                $params[] = (int)$clienteId;
            }

            $stmt = $pdo->prepare("
                SELECT
                    CL.ClienteNombre AS cliente,
                    CL.ClienteID,
                    SUM(PCE.Cantidad) AS bultos,
                    ROUND(SUM(PCE.Cantidad * COALESCE(PC.KilosUnidad,0)),0) AS kilos,
                    COUNT(DISTINCT PCE.EstibaID) AS posiciones
                FROM ProductoCargaEstiba PCE
                INNER JOIN Cargas Car ON Car.CargaID = PCE.CargaID
                INNER JOIN CLIENTES CL ON CL.ClienteID = Car.ClienteID
                INNER JOIN ProductosCarga PC ON PC.CargaID = PCE.CargaID AND PC.ProductosID = PCE.ProductosID
                WHERE $where
                GROUP BY CL.ClienteID, CL.ClienteNombre
                ORDER BY CL.ClienteNombre
            ");
            $stmt->execute($params);
            $resumen = $stmt->fetchAll();

            // Totales
            $totBultos = 0; $totKilos = 0; $totPos = 0;
            foreach ($resumen as &$r) {
                $r['bultos'] = (int)$r['bultos'];
                $r['kilos'] = (int)$r['kilos'];
                $r['posiciones'] = (int)$r['posiciones'];
                $totBultos += $r['bultos'];
                $totKilos += $r['kilos'];
                $totPos += $r['posiciones'];
            }
            unset($r);

            resp(200, [
                'ok'=>true,
                'registros'=>$resumen,
                'totales'=>['bultos'=>$totBultos,'kilos'=>$totKilos,'posiciones'=>$totPos],
            ]);
        } catch (Exception $e) {
            resp(500, ['error'=>$e->getMessage()]);
        }
        break;

    case 'reporte_stock_detalle':
        requireSession();
        $clienteId = intval($_GET['cliente_id'] ?? 0);
        if (!$clienteId) resp(400, ['error'=>'Falta cliente_id']);

        try {
            $stmt = $pdo->prepare("
                SELECT
                    P.CodigoProducto AS codigo,
                    P.Descripcion1 AS producto,
                    SUM(PCE.Cantidad) AS bultos,
                    ROUND(SUM(PCE.Cantidad * COALESCE(PC.KilosUnidad,0)),0) AS kilos,
                    COUNT(DISTINCT PCE.EstibaID) AS posiciones,
                    MIN(PCE.FechaElaboracion) AS fecha_elab_min,
                    MAX(PCE.FechaVencimiento) AS fecha_venc_max
                FROM ProductoCargaEstiba PCE
                INNER JOIN Cargas Car ON Car.CargaID = PCE.CargaID
                INNER JOIN ProductosCarga PC ON PC.CargaID = PCE.CargaID AND PC.ProductosID = PCE.ProductosID
                INNER JOIN Productos P ON P.ProductosID = PCE.ProductosID
                WHERE PCE.Cantidad > 0 AND Car.Clasificada = 1 AND Car.ClienteID = ?
                GROUP BY P.ProductosID, P.CodigoProducto, P.Descripcion1
                ORDER BY P.Descripcion1
            ");
            $stmt->execute([$clienteId]);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) {
                $r['bultos'] = (int)$r['bultos'];
                $r['kilos'] = (int)$r['kilos'];
                $r['posiciones'] = (int)$r['posiciones'];
                if ($r['fecha_elab_min']) $r['fecha_elab_min'] = (new DateTime($r['fecha_elab_min']))->format('d/m/Y');
                if ($r['fecha_venc_max']) $r['fecha_venc_max'] = (new DateTime($r['fecha_venc_max']))->format('d/m/Y');
            }
            unset($r);
            resp(200, ['ok'=>true, 'registros'=>$rows]);
        } catch (Exception $e) {
            resp(500, ['error'=>$e->getMessage()]);
        }
        break;

    case 'reporte_stock_completo':
        // Stock detallado con filtros, usado para preview y descarga
        requireSession();
        $clienteId  = $_GET['cliente_id'] ?? '';
        $producto   = $_GET['producto'] ?? '';
        $lote       = $_GET['lote'] ?? '';
        $vencDesde  = $_GET['venc_desde'] ?? '';
        $vencHasta  = $_GET['venc_hasta'] ?? '';

        try {
            $where = "PCE.Cantidad > 0 AND Car.Clasificada = 1";
            $params = [];
            if ($clienteId)  { $where .= " AND Car.ClienteID = ?"; $params[] = (int)$clienteId; }
            if ($producto)   { $where .= " AND (P.Descripcion1 LIKE ? OR P.CodigoProducto LIKE ?)"; $params[] = "%$producto%"; $params[] = "%$producto%"; }
            if ($lote)       { $where .= " AND PCE.NroLote LIKE ?"; $params[] = "%$lote%"; }
            if ($vencDesde)  { $where .= " AND PCE.FechaVencimiento >= ?"; $params[] = $vencDesde; }
            if ($vencHasta)  { $where .= " AND PCE.FechaVencimiento <= ?"; $params[] = $vencHasta; }

            $stmt = $pdo->prepare("
                SELECT
                    CL.ClienteNombre AS cliente,
                    P.CodigoProducto AS codigo,
                    P.Descripcion1 AS producto,
                    PCE.Cantidad AS bultos,
                    ROUND(PCE.Cantidad * COALESCE(PC.KilosUnidad,0),0) AS kilos,
                    PCE.NroLote AS lote,
                    PCE.EstibaID AS estiba,
                    PCE.CargaID AS entrada,
                    PCE.FechaElaboracion AS fecha_elaboracion,
                    PCE.FechaVencimiento AS fecha_vencimiento,
                    PCE.FechaCongelado AS fecha_congelado,
                    PCE.NroTropa AS tropa,
                    PCE.nro_tambor AS tambor,
                    Car.NroRemito AS remito
                FROM ProductoCargaEstiba PCE
                INNER JOIN Cargas Car ON Car.CargaID = PCE.CargaID
                INNER JOIN CLIENTES CL ON CL.ClienteID = Car.ClienteID
                INNER JOIN ProductosCarga PC ON PC.CargaID = PCE.CargaID AND PC.ProductosID = PCE.ProductosID
                INNER JOIN Productos P ON P.ProductosID = PCE.ProductosID
                WHERE $where
                ORDER BY CL.ClienteNombre, P.Descripcion1, PCE.FechaVencimiento
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            $totBultos = 0; $totKilos = 0;
            foreach ($rows as &$r) {
                $r['bultos'] = (int)$r['bultos'];
                $r['kilos'] = (int)$r['kilos'];
                $totBultos += $r['bultos'];
                $totKilos += $r['kilos'];
                if ($r['fecha_elaboracion']) $r['fecha_elaboracion'] = (new DateTime($r['fecha_elaboracion']))->format('d/m/Y');
                if ($r['fecha_vencimiento']) $r['fecha_vencimiento'] = (new DateTime($r['fecha_vencimiento']))->format('d/m/Y');
                if ($r['fecha_congelado'])   $r['fecha_congelado']   = (new DateTime($r['fecha_congelado']))->format('d/m/Y');
            }
            unset($r);

            resp(200, ['ok'=>true, 'registros'=>$rows, 'totales'=>['bultos'=>$totBultos,'kilos'=>$totKilos,'filas'=>count($rows)]]);
        } catch (Exception $e) {
            resp(500, ['error'=>$e->getMessage()]);
        }
        break;

    // ── Crear Pre-Entrada (Cargas con clasificada=0) ────────────
    case 'logmod_crear_preentrada':
        requireSession();
        $body = json_input();
        $clienteId = intval($body['ClienteID'] ?? 0);
        $fiscal    = intval($body['es_fiscal'] ?? 0);
        if (!$clienteId) resp(400, ['error'=>'Falta ClienteID']);

        try {
            // CargaID no es auto_increment, generar MAX+1
            $maxId = (int)$pdo->query("SELECT COALESCE(MAX(CargaID),0) FROM Cargas")->fetchColumn();
            $newId = $maxId + 1;

            $stmt = $pdo->prepare("
                INSERT INTO Cargas (CargaID, ClienteID, NroRemito, numero_referencia, permiso_embarque,
                    Observaciones, es_fiscal, devolucion, estado_carga, Clasificada,
                    TemperaturaControlada, Stock, usuario_id, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 1, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $newId,
                $clienteId,
                $body['NroRemito'] ?? '',
                $body['numero_referencia'] ?? '',
                $fiscal ? ($body['permiso_embarque'] ?? '') : '',
                $body['Observaciones'] ?? '',
                $fiscal,
                intval($body['devolucion'] ?? 0),
                intval($body['estado_carga'] ?? 1),
                (int)$_SESSION['cina_usuario_id']
            ]);
            resp(201, ['ok'=>true, 'id'=>$newId]);
        } catch (Exception $e) {
            resp(500, ['error'=>$e->getMessage()]);
        }
        break;

    // ── Crear Pre-Salida (PreSalidas con clasificada=0) ─────────
    case 'logmod_crear_presalida':
        requireSession();
        $body = json_input();
        $clienteId = intval($body['ClienteID'] ?? 0);
        $destinoId = intval($body['DestinoID'] ?? 0);
        $fiscal    = intval($body['es_fiscal'] ?? 0);
        if (!$clienteId) resp(400, ['error'=>'Falta ClienteID']);
        if (!$destinoId) resp(400, ['error'=>'Falta DestinoID — seleccioná un destino válido']);

        // Verificar que el DestinoID exista en la tabla Destinos (evita violación FK silenciosa)
        try {
            $chk = $pdo->prepare("SELECT 1 FROM Destinos WHERE DestinoID = ? LIMIT 1");
            $chk->execute([$destinoId]);
            if (!$chk->fetchColumn()) {
                resp(400, ['error'=>'DestinoID inexistente ('.$destinoId.')']);
            }
        } catch (Exception $e) {
            resp(500, ['error'=>'Error validando destino: '.$e->getMessage()]);
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO PreSalidas (ClienteID, GrupoID, DestinoID, Remito, ContratoNro,
                    numero_referencia, permiso_embarque, Observaciones, es_fiscal,
                    contramarca, termografo, faja_seguridad, fotos,
                    clasificada, TemperaturaControlada, usuario_id, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $clienteId,
                intval($body['GrupoID'] ?? 0) ?: null,
                $destinoId,
                $body['Remito'] ?? '',
                $body['ContratoNro'] ?? '',
                $body['numero_referencia'] ?? '',
                $fiscal ? ($body['permiso_embarque'] ?? '') : '',
                $body['Observaciones'] ?? '',
                $fiscal,
                intval($body['contramarca'] ?? 0),
                intval($body['termografo'] ?? 0),
                intval($body['faja_seguridad'] ?? 0),
                intval($body['fotos'] ?? 0),
                (int)$_SESSION['cina_usuario_id']
            ]);
            $newId = (int)$pdo->lastInsertId();
            resp(201, ['ok'=>true, 'id'=>$newId]);
        } catch (Exception $e) {
            resp(500, ['error'=>$e->getMessage()]);
        }
        break;

    // ── Catálogos para formularios de pre-entrada/pre-salida ────
    case 'logmod_vincular_preentrada':
        requireSession();
        $body = json_input();
        $id = array_key_exists('id', $body) ? intval($body['id']) : null;
        $cargaNumero = intval($body['carga_numero'] ?? 0);
        if ($id === null) resp(400, ['error'=>'Falta id']);
        try {
            // Asegurar columna tratamiento_id
            try { $pdo->exec("ALTER TABLE Cargas ADD COLUMN tratamiento_id CHAR(1) DEFAULT NULL"); } catch(Exception $e2) {}

            // Si carga_numero es 0, desvincular (poner NULL)
            $valorCarga = $cargaNumero > 0 ? $cargaNumero : null;
            $pdo->prepare("UPDATE Cargas SET carga_numero = ?, updated_at = NOW(), usuario_id = ? WHERE CargaID = ?")
                ->execute([$valorCarga, (int)$_SESSION['cina_usuario_id'], $id]);

            // Copiar tratamiento del turno vinculado a esta carga
            if ($cargaNumero > 0) {
                $stTurno = $pdo->prepare("SELECT tratamiento_id FROM planificacion_turno WHERE carga_numero = ? AND estado = 'asignado' LIMIT 1");
                $stTurno->execute([$cargaNumero]);
                $tratTurno = $stTurno->fetchColumn();
                if ($tratTurno) {
                    $pdo->prepare("UPDATE Cargas SET tratamiento_id = ? WHERE CargaID = ? AND (tratamiento_id IS NULL OR tratamiento_id = '')")
                        ->execute([$tratTurno, $id]);
                }
            }

            resp(200, ['ok'=>true]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'logmod_vincular_presalida':
        requireSession();
        $body = json_input();
        $id = intval($body['id'] ?? 0);
        $cargaNumero = intval($body['carga_numero'] ?? 0);
        if (!$id) resp(400, ['error'=>'Falta id']);
        try {
            $valorCarga = $cargaNumero > 0 ? $cargaNumero : null;
            $pdo->prepare("UPDATE PreSalidas SET carga_numero = ?, updated_at = NOW(), usuario_id = ? WHERE PreSalidaID = ?")
                ->execute([$valorCarga, (int)$_SESSION['cina_usuario_id'], $id]);
            resp(200, ['ok'=>true]);
        } catch (Exception $e) { resp(500, ['error'=>$e->getMessage()]); }
        break;

    case 'logmod_catalogos':
        requireSession();
        $fiscal = intval($_GET['fiscal'] ?? 0);
        try {
            $clientes = $pdo->prepare("SELECT ClienteID AS id, ClienteNombre AS nombre FROM CLIENTES WHERE Activo = 1 AND fiscal = ? ORDER BY ClienteNombre");
            $clientes->execute([$fiscal]);
            $destinos = $pdo->query("SELECT DestinoID AS id, DestinoNombre AS nombre FROM Destinos WHERE Activo = 1 ORDER BY DestinoNombre")->fetchAll();
            resp(200, ['ok'=>true, 'clientes'=>$clientes->fetchAll(), 'destinos'=>$destinos]);
        } catch (Exception $e) {
            resp(500, ['error'=>$e->getMessage()]);
        }
        break;

    // ═══════════════════════════════════════════════════════════════
    //  MÓDULO SENASA: Certificados
    // ═══════════════════════════════════════════════════════════════

    case 'certificados_lista':
        requireSession();
        $estado = $_GET['estado'] ?? '';     // A|C
        $tipo   = $_GET['tipo'] ?? '';       // 0=normal,1=reemplazo,2=redestino,3=intervenido
        $busqueda = $_GET['q'] ?? '';
        $page   = max(1, intval($_GET['page'] ?? 1));
        $limit  = 50;
        $offset = ($page - 1) * $limit;

        try {
            $where = "1=1";
            $params = [];
            if ($estado) { $where .= " AND C.estado = ?"; $params[] = $estado; }
            if ($tipo === '1') { $where .= " AND C.CertificadoID_2 IS NOT NULL AND C.activo = 0"; }
            elseif ($tipo === '2') { $where .= " AND C.redestino = 1"; }
            elseif ($tipo === '3') { $where .= " AND C.intervenido = 1"; }
            elseif ($tipo === '0') { $where .= " AND (C.CertificadoID_2 IS NULL OR C.CertificadoID_2 = 0) AND C.redestino = 0 AND C.activo = 1"; }
            if ($busqueda) {
                $where .= " AND (C.CertificadoNro LIKE ? OR C.numero LIKE ? OR CAST(C.CertificadoID AS CHAR) LIKE ?)";
                $params[] = "%$busqueda%"; $params[] = "%$busqueda%"; $params[] = "%$busqueda%";
            }

            $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM Certificados C WHERE $where");
            $cntStmt->execute($params);
            $total = (int)$cntStmt->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT C.CertificadoID AS id,
                       CONCAT(COALESCE(C.estab_procedencia,''),'-',COALESCE(C.letra,''),'-',LPAD(COALESCE(C.numero,''),6,'0')) AS certificado_nro,
                       C.estado, C.fecha_emision, C.fecha_cerrado,
                       C.intervenido, C.redestino, C.activo,
                       C.temperatura,
                       TC.TipoCertificadoNombre AS tipo_cert,
                       E.nombre AS establecimiento,
                       CL.ClienteNombre AS cliente,
                       C.updated_at
                FROM Certificados C
                LEFT JOIN TiposCertificados TC ON TC.TipoCertificadoID = C.TipoCertificadoID
                LEFT JOIN establecimiento E ON E.establecimiento_id = C.estab_procedencia
                LEFT JOIN Cargas Car ON Car.CargaID = C.CargaID
                LEFT JOIN CLIENTES CL ON CL.ClienteID = Car.ClienteID
                WHERE $where
                ORDER BY C.CertificadoID DESC
                LIMIT $limit OFFSET $offset
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            foreach ($rows as &$r) {
                if ($r['fecha_emision']) $r['fecha_emision'] = (new DateTime($r['fecha_emision']))->format('d/m/Y');
                if ($r['fecha_cerrado']) $r['fecha_cerrado'] = (new DateTime($r['fecha_cerrado']))->format('d/m/Y H:i');
            }
            unset($r);

            resp(200, ['ok'=>true, 'registros'=>$rows, 'total'=>$total, 'page'=>$page, 'pages'=>ceil($total/$limit)]);
        } catch (Exception $e) {
            resp(500, ['error'=>$e->getMessage()]);
        }
        break;

    case 'certificados_ver':
        requireSession();
        $id = intval($_GET['id'] ?? 0);
        if (!$id) resp(400, ['error'=>'Falta id']);

        try {
            $stmt = $pdo->prepare("
                SELECT C.*,
                       TC.TipoCertificadoNombre AS tipo_cert,
                       E.nombre AS establecimiento,
                       CL.ClienteNombre AS cliente
                FROM Certificados C
                LEFT JOIN TiposCertificados TC ON TC.TipoCertificadoID = C.TipoCertificadoID
                LEFT JOIN establecimiento E ON E.establecimiento_id = C.estab_procedencia
                LEFT JOIN Cargas Car ON Car.CargaID = C.CargaID
                LEFT JOIN CLIENTES CL ON CL.ClienteID = Car.ClienteID
                WHERE C.CertificadoID = ?
            ");
            $stmt->execute([$id]);
            $cert = $stmt->fetch();
            if (!$cert) resp(404, ['error'=>'Certificado no encontrado']);

            // Destinos
            $sd = $pdo->prepare("SELECT D.DestinoNombre FROM CertificadosDestinos CD INNER JOIN Destinos D ON D.DestinoID = CD.DestinoID WHERE CD.CertificadoID = ?");
            $sd->execute([$id]);
            $cert['destinos'] = $sd->fetchAll(PDO::FETCH_COLUMN);

            // Intervenciones
            $si = $pdo->prepare("
                SELECT CI.IntervencionID, I.IntervencionNombre, CI.IntervencionFecha,
                       CI.IntervencionObservacion, CI.ActaNro, CI.ActaFecha,
                       CI.ActaResolucion, CI.ActaObservacion
                FROM CertificadosIntervenciones CI
                LEFT JOIN Intervenciones I ON I.IntervencionID = CI.IntervencionID
                WHERE CI.CertificadoID = ?
                ORDER BY CI.IntervencionFecha DESC
            ");
            $si->execute([$id]);
            $cert['intervenciones'] = $si->fetchAll();

            resp(200, ['ok'=>true, 'certificado'=>$cert]);
        } catch (Exception $e) {
            resp(500, ['error'=>$e->getMessage()]);
        }
        break;

    case 'certificados_estado':
        requireSession();
        $body = json_input();
        $id = intval($body['id'] ?? 0);
        $estado = $body['estado'] ?? ''; // A|C
        if (!$id || !in_array($estado, ['A','C'])) resp(400, ['error'=>'Datos inválidos']);

        try {
            if ($estado === 'C') {
                $stmt = $pdo->prepare("UPDATE Certificados SET estado='C', fecha_cerrado=NOW(), usuario_id=?, updated_at=NOW() WHERE CertificadoID=?");
            } else {
                $stmt = $pdo->prepare("UPDATE Certificados SET estado='A', fecha_cerrado=NULL, usuario_id=?, updated_at=NOW() WHERE CertificadoID=?");
            }
            $stmt->execute([(int)$_SESSION['cina_usuario_id'], $id]);
            resp(200, ['ok'=>true]);
        } catch (Exception $e) {
            resp(500, ['error'=>$e->getMessage()]);
        }
        break;

    default:
        resp(404, ['error' => 'Acción no encontrada: ' . $action]);
}

// ── Helpers ──────────────────────────────────────────────────
function json_input(): array {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    if (!is_array($data)) resp(400, ['error' => 'JSON inválido']);
    return $data;
}

function auth(array $data): void {
    // Primero verificar sesión PHP (desde el frontend)
    if (!empty($_SESSION['cina_usuario_id'])) {
        return; // sesión válida
    }
    // Fallback: verificar secret (para scripts de backend/sync)
    if (($data['secret'] ?? '') === SECRET_KEY) {
        return;
    }
    resp(401, ['error' => 'No autorizado. Sesión expirada.']);
}

function resp(int $code, array $body): never {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Helpers pañol (usan la sesión estándar de CINA) ──────────
function panolSessionUserId(): int {
    return (int)($_SESSION['cina_usuario_id'] ?? 0);
}

// Roles con permisos de encargado: 1=admin, 2=supervisor, 5=encargado, 9=sala_maquinas, 10=sistemas
function panolIsEncargado(PDO $pdo): bool {
    $stmt = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
    $stmt->execute([panolSessionUserId()]);
    return in_array((int)$stmt->fetchColumn(), [1, 2, 5, 9, 10, 32]); // 32 = Pañolero
}
