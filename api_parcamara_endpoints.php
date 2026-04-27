<?php
/*
 * ============================================================
 *  FRAGMENTO PARA PEGAR EN api_2026ia.php
 *  Agregar los tres case siguientes ANTES del `default:` al
 *  final del switch($action).
 * ============================================================
 *
 *  Endpoints nuevos:
 *    GET  ?action=parcamara_camaras          → cámaras 1,2,7 con estado
 *    POST ?action=parcamara_solicitar        → el usuario solicita parada
 *    GET  ?action=parcamara_lista            → lista para el panel PC
 *    POST ?action=parcamara_responder        → RolID=9 informa resultado
 * ============================================================
 */

    // ── GET: cámaras tipo 1,2,7 con estado de temperatura ──────
    case 'parcamara_camaras':
        requireSession();
        try {
            $stmt = $pdo->query("
                SELECT
                    c.CamaraID,
                    c.CamaraNumero,
                    c.TiposCamaraID,
                    tmp.estado  AS temp_estado,
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

            // Mapear color de botón según estado de temperatura
            foreach ($camaras as &$cam) {
                $estado = strtolower(trim($cam['temp_estado'] ?? ''));
                if ($estado === 'sin inyección' || $estado === 'sin inyeccion') {
                    $cam['btn_color'] = 'red';       // Parada
                } elseif (strpos($estado, 'deshelo') !== false || strpos($estado, 'desheló') !== false || strpos($estado, 'descongelado') !== false) {
                    $cam['btn_color'] = 'yellow';    // En deshielo
                } else {
                    $cam['btn_color'] = 'green';     // En marcha / cualquier otro
                }
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

        // Verificar que la cámara sea de TiposCamaraID 1 (TUNEL), 2 (Camara) o 7 (CAMARA DF)
        $stmtChk = $pdo->prepare("SELECT TiposCamaraID FROM Camaras WHERE CamaraID = ? AND activo = 1");
        $stmtChk->execute([$camaraId]);
        $tipoId = (int)$stmtChk->fetchColumn();
        if (!in_array($tipoId, [1, 2, 7])) {
            resp(403, ['error' => 'Cámara no habilitada para solicitud de parada']);
        }

        $usuarioId     = $_SESSION['cina_usuario_id'];
        $usuarioNombre = $_SESSION['cina_nombre'] ?? $_SESSION['cina_usuario'] ?? 'Usuario';

        try {
            $pdo->prepare("
                INSERT INTO sdm_solicitud_parcamara
                    (camara_id, camara_numero, estado_temp,
                     usuario_id, usuario_nombre, fecha_solicitud, estado_solicitud, activo)
                VALUES (?, ?, ?, ?, ?, NOW(), 'Pendiente', 1)
            ")->execute([$camaraId, $camaraNumero, $estadoTemp ?: 'Sin información', $usuarioId, $usuarioNombre]);

            resp(200, ['ok' => true, 'solicitud_id' => $pdo->lastInsertId()]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── GET: lista de solicitudes para el panel de PC ───────────
    // Disponible para todos; los botones de respuesta solo para RolID=9
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
                    s.usuario_nombre          AS solicitante,
                    s.fecha_solicitud,
                    s.estado_solicitud,
                    s.usuario_responde_nombre AS respondio,
                    s.fecha_respuesta,
                    s.observaciones,
                    -- Estado actual de temperatura de la cámara
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

            // Rol del usuario actual — para indicar si puede responder
            $stmtRol = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
            $stmtRol->execute([$_SESSION['cina_usuario_id']]);
            $rolId = (int)$stmtRol->fetchColumn();

            resp(200, [
                'solicitudes' => $rows,
                'puede_responder' => ($rolId === 9),
                'rol_id' => $rolId,
            ]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── POST: RolID=9 informa resultado de la parada ────────────
    case 'parcamara_responder':
        requireSession();

        // Verificar que el usuario que responde tenga RolID = 9
        $stmtRolChk = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
        $stmtRolChk->execute([$_SESSION['cina_usuario_id']]);
        $rolUsuario = (int)$stmtRolChk->fetchColumn();
        if ($rolUsuario !== 9) {
            resp(403, ['error' => 'Sin permiso. Solo usuarios con RolID=9 pueden informar resultado.']);
        }

        $solicitudId   = (int)($body['solicitud_id']    ?? 0);
        $estadoRespuesta = trim($body['estado_solicitud'] ?? '');
        $observaciones   = trim($body['observaciones']   ?? '');

        $estadosValidos = ['Parada Informada', 'No Parada Informada'];
        if (!$solicitudId || !in_array($estadoRespuesta, $estadosValidos)) {
            resp(400, ['error' => 'Datos inválidos. estado_solicitud debe ser: ' . implode(' | ', $estadosValidos)]);
        }

        // Obtener la solicitud para notificar al solicitante
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
        if ($solicitud['estado_solicitud'] !== 'Pendiente') {
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
                'ok'               => true,
                'solicitud_id'     => $solicitudId,
                'estado'           => $estadoRespuesta,
                'camara_numero'    => $solicitud['camara_numero'],
                'solicitante'      => $solicitud['usuario_nombre'],
                'solicitante_id'   => $solicitud['usuario_id'],
            ]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;
