<?php
/**
 * ══════════════════════════════════════════════════════════════════
 *  PARCHE — MÓDULO TRONERAS
 *  Pegar estos cases en el switch de api_2026ia.php
 *  ANTES del default:
 *
 *  Endpoints nuevos:
 *    GET  ?action=troneras_disponibles   → lista para asignar
 *    GET  ?action=troneras_lista         → CRUD index
 *    POST ?action=tronera_asignar        → asignar a una carga
 *    POST ?action=tronera_desasignar     → cancelar asignación
 *    POST ?action=tronera_liberar        → liberar (operación terminada)
 *    POST ?action=tronera_guardar        → crear / editar tronera
 *    POST ?action=tronera_eliminar       → baja lógica (estado I)
 *
 *  Tablas involucradas:
 *    tronera           — catálogo de troneras físicas
 *    transporte_tronera — asignaciones (estados A/U/L/C)
 *    transporte_box    — se verifica que el box esté asignado antes
 *                        de poder asignar una tronera
 * ══════════════════════════════════════════════════════════════════
 */

// ══════════════════════════════════════════════════════════════
//  GET troneras_disponibles
//  Devuelve troneras activas sin asignación activa (A/U)
//  GET ?action=troneras_disponibles&tipo_planta=N
// ══════════════════════════════════════════════════════════════
case 'troneras_disponibles':
    requireSession();
    $tipo_planta = $_GET['tipo_planta'] ?? 'N';
    if (!in_array($tipo_planta, ['N', 'F'])) $tipo_planta = 'N';

    try {
        $stmt = $pdo->prepare("
            SELECT t.id, t.numero, t.anden,
                   CASE t.anden
                       WHEN 'N' THEN 'Norte'
                       WHEN 'S' THEN 'Sur'
                       WHEN 'F' THEN 'Frío'
                       WHEN 'E' THEN 'Seco'
                       ELSE t.anden
                   END AS anden_texto
            FROM tronera t
            WHERE t.estado = 'A'
              AND t.tipo = ?
              AND NOT EXISTS (
                  SELECT 1 FROM transporte_tronera tt
                  WHERE tt.estado IN ('A', 'U')
                    AND tt.tronera_asignada_id = t.id
              )
            ORDER BY t.numero ASC
        ");
        $stmt->execute([$tipo_planta]);
        resp(200, $stmt->fetchAll());
    } catch (Exception $e) {
        resp(500, ['error' => $e->getMessage()]);
    }
    break;

// ══════════════════════════════════════════════════════════════
//  GET troneras_lista
//  CRUD index con filtros opcionales: planta, estado
//  GET ?action=troneras_lista&planta=N&estado=A
// ══════════════════════════════════════════════════════════════
case 'troneras_lista':
    requireSession();
    $planta = $_GET['planta'] ?? '';
    $estado = $_GET['estado'] ?? '';

    try {
        $where  = ['1=1'];
        $params = [];

        if ($planta !== '') {
            $where[]  = 't.tipo = ?';
            $params[] = $planta;
        }
        if ($estado !== '') {
            $where[]  = 't.estado = ?';
            $params[] = $estado;
        }

        $stmt = $pdo->prepare("
            SELECT
                t.id, t.numero, t.tipo, t.anden,
                t.fila, t.columna, t.grado,
                t.estado, t.comentarios,
                CASE t.tipo
                    WHEN 'N' THEN 'Nacional'
                    WHEN 'F' THEN 'Fiscal'
                    ELSE t.tipo
                END AS tipo_texto,
                CASE t.anden
                    WHEN 'N' THEN 'Norte'
                    WHEN 'S' THEN 'Sur'
                    WHEN 'F' THEN 'Frío'
                    WHEN 'E' THEN 'Seco'
                    ELSE t.anden
                END AS anden_texto,
                CASE t.estado
                    WHEN 'A' THEN 'Activa'
                    WHEN 'M' THEN 'Mantenimiento'
                    WHEN 'I' THEN 'Inactiva'
                    ELSE t.estado
                END AS estado_texto,
                -- Asignación activa si existe
                tt.id            AS tt_id,
                tt.estado        AS tt_estado,
                tt.carga_numero  AS tt_carga_numero,
                tt.fecha_asignacion,
                tt.fecha_inicio_uso
            FROM tronera t
            LEFT JOIN transporte_tronera tt
                ON tt.tronera_asignada_id = t.id
               AND tt.estado IN ('A', 'U')
            WHERE " . implode(' AND ', $where) . "
            ORDER BY t.tipo ASC, t.numero ASC
        ");
        $stmt->execute($params);
        resp(200, $stmt->fetchAll());
    } catch (Exception $e) {
        resp(500, ['error' => $e->getMessage()]);
    }
    break;

// ══════════════════════════════════════════════════════════════
//  POST tronera_asignar
//  Asigna una tronera a una carga. Requisito previo: el box de
//  esa carga ya debe estar asignado (estado A o U).
//
//  Body: { carga_numero, tronera_id }
// ══════════════════════════════════════════════════════════════
case 'tronera_asignar':
    requireSession();
    $carga_numero = (int)($body['carga_numero'] ?? 0);
    $tronera_id   = (int)($body['tronera_id']   ?? 0);

    if (!$carga_numero) resp(400, ['error' => 'Falta carga_numero']);
    if (!$tronera_id)   resp(400, ['error' => 'Falta tronera_id']);

    try {
        $pdo->beginTransaction();

        // 1. Validar tronera activa
        $stmtT = $pdo->prepare("SELECT id, numero, estado, tipo FROM tronera WHERE id = ? LIMIT 1");
        $stmtT->execute([$tronera_id]);
        $tronera = $stmtT->fetch();
        if (!$tronera) {
            $pdo->rollBack();
            resp(404, ['error' => 'Tronera no encontrada']);
        }
        if ($tronera['estado'] !== 'A') {
            $pdo->rollBack();
            resp(400, ['error' => 'La tronera no está activa (estado: ' . $tronera['estado'] . ')']);
        }

        // 2. Re-check anti race-condition dentro de la transacción
        $stmtOcup = $pdo->prepare("
            SELECT COUNT(*) FROM transporte_tronera
            WHERE tronera_asignada_id = ? AND estado IN ('A','U')
        ");
        $stmtOcup->execute([$tronera_id]);
        if ((int)$stmtOcup->fetchColumn() > 0) {
            $pdo->rollBack();
            resp(409, ['error' => 'La tronera ya fue asignada por otro operador']);
        }

        // 3. Verificar que el box de esta carga ya esté asignado
        $stmtBox = $pdo->prepare("
            SELECT estado FROM transporte_box
            WHERE carga_numero = ?
            ORDER BY id DESC LIMIT 1
        ");
        $stmtBox->execute([$carga_numero]);
        $tbEstado = $stmtBox->fetchColumn();
        if ($tbEstado === false) {
            $pdo->rollBack();
            resp(400, ['error' => 'No se encontró un registro de box para esta carga']);
        }
        if ($tbEstado === 'P') {
            $pdo->rollBack();
            resp(400, ['error' => 'El box del transporte aún no fue asignado. Asignar box primero.']);
        }

        // 4. Verificar estado del TransporteTronera existente (si hay)
        $stmtTT = $pdo->prepare("
            SELECT id, estado FROM transporte_tronera
            WHERE carga_numero = ?
            ORDER BY id DESC LIMIT 1
        ");
        $stmtTT->execute([$carga_numero]);
        $ttExistente = $stmtTT->fetch();

        if ($ttExistente) {
            if ($ttExistente['estado'] === 'U') {
                $pdo->rollBack();
                resp(400, ['error' => 'Esta carga ya tiene una tronera en uso']);
            }
            if ($ttExistente['estado'] === 'A') {
                $pdo->rollBack();
                resp(400, ['error' => 'Esta carga ya tiene una tronera asignada']);
            }
        }

        // 5. Buscar o crear transporte_tronera
        if ($ttExistente && in_array($ttExistente['estado'], ['L', 'C'])) {
            // Reasignar — crear nuevo registro en lugar de reutilizar el cerrado
            $ttId = null;
        } else {
            $ttId = null;
        }

        // Siempre crear un nuevo registro (más limpio para auditoría)
        $pdo->prepare("
            INSERT INTO transporte_tronera
                (carga_numero, tronera_asignada_id, estado,
                 fecha_asignacion, usuario_id, created_at, updated_at)
            VALUES (?, ?, 'A', NOW(), ?, NOW(), NOW())
        ")->execute([$carga_numero, $tronera_id, $_SESSION['cina_usuario_id']]);
        $nuevoTTId = (int)$pdo->lastInsertId();

        // 6. Obtener datos del conductor para WhatsApp
        $conductor = null;
        $boxNumero = null;
        try {
            $stmtCond = $pdo->prepare("
                SELECT
                    tc.conductor,
                    tcond.nombre,
                    tcond.apellido,
                    tcond.numero_celular,
                    tb.box_asignado_id,
                    b.numero AS box_numero
                FROM transporte_carga tc
                LEFT JOIN transporte_conductor tcond ON tcond.conductor_id = tc.conductor
                LEFT JOIN transporte_box tb
                    ON tb.carga_numero = tc.carga_numero
                   AND tb.estado IN ('A','U')
                LEFT JOIN box b ON b.id = tb.box_asignado_id
                WHERE tc.carga_numero = ?
                LIMIT 1
            ");
            $stmtCond->execute([$carga_numero]);
            $conductor = $stmtCond->fetch();
            $boxNumero = $conductor['box_numero'] ?? null;
        } catch (Exception $eCond) {
            // No interrumpir si falla la consulta del conductor
        }

        // 7. Asignar responsable: el usuario que asigna la tronera pasa a ser responsable
        $userId = (int)$_SESSION['cina_usuario_id'];
        $stmtPers = $pdo->prepare("SELECT persona_id FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
        $stmtPers->execute([$userId]);
        $personaId = $stmtPers->fetchColumn();
        if ($personaId) {
            // Buscar o crear CargasResponsable
            $stmtResp = $pdo->prepare("SELECT ResponsableID FROM CargasResponsable WHERE persona_id = ? LIMIT 1");
            $stmtResp->execute([$personaId]);
            $respId = $stmtResp->fetchColumn();
            if (!$respId) {
                $pdo->prepare("INSERT INTO CargasResponsable (persona_id, Activo, is_responsable_cargas) VALUES (?, 1, 1)")->execute([$personaId]);
                $respId = (int)$pdo->lastInsertId();
            }
            $pdo->prepare("UPDATE transporte_carga SET responsable_carga = ? WHERE carga_numero = ?")->execute([$respId, $carga_numero]);
        }

        $pdo->commit();

        // 8. Enviar WhatsApp si hay conductor (sin bloquear el response)
        // Si CommunicationClass existe en el contexto, usarla:
        // CommunicationClass::sendWhatsapp('aviso_tronera_3', $cel, [...]);

        resp(200, [
            'ok'                   => true,
            'transporte_tronera_id'=> $nuevoTTId,
            'tronera_id'           => $tronera_id,
            'tronera_numero'       => $tronera['numero'],
            'carga_numero'         => $carga_numero,
            'box_numero'           => $boxNumero,
            'mensaje'              => 'Tronera ' . $tronera['numero'] . ' asignada a carga ' . $carga_numero,
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        resp(500, ['error' => $e->getMessage()]);
    }
    break;

// ══════════════════════════════════════════════════════════════
//  POST tronera_desasignar
//  Cancela una asignación activa (A → C).
//  Body: { carga_numero }
// ══════════════════════════════════════════════════════════════
case 'tronera_desasignar':
    requireSession();
    $carga_numero = (int)($body['carga_numero'] ?? 0);
    if (!$carga_numero) resp(400, ['error' => 'Falta carga_numero']);

    try {
        $pdo->beginTransaction();

        $stmtTT = $pdo->prepare("
            SELECT id FROM transporte_tronera
            WHERE carga_numero = ? AND estado IN ('A','U')
            ORDER BY id DESC LIMIT 1
        ");
        $stmtTT->execute([$carga_numero]);
        $ttId = $stmtTT->fetchColumn();

        if (!$ttId) {
            $pdo->rollBack();
            resp(404, ['error' => 'No se encontró una asignación activa para esta carga']);
        }

        $pdo->prepare("
            UPDATE transporte_tronera
            SET estado     = 'C',
                usuario_id = ?,
                updated_at = NOW()
            WHERE id = ?
        ")->execute([$_SESSION['cina_usuario_id'], $ttId]);

        $pdo->commit();

        // WhatsApp de cancelación: CommunicationClass::sendWhatsapp('aviso_cancelacion_tronera', ...);

        resp(200, ['ok' => true, 'mensaje' => 'Asignación cancelada correctamente']);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        resp(500, ['error' => $e->getMessage()]);
    }
    break;

// ══════════════════════════════════════════════════════════════
//  POST tronera_liberar
//  Marca la operación como finalizada (U/A → L).
//  Body: { carga_numero } o { transporte_tronera_id }
// ══════════════════════════════════════════════════════════════
case 'tronera_liberar':
    requireSession();
    $carga_numero = (int)($body['carga_numero']          ?? 0);
    $ttId_directo = (int)($body['transporte_tronera_id'] ?? 0);

    if (!$carga_numero && !$ttId_directo) {
        resp(400, ['error' => 'Falta carga_numero o transporte_tronera_id']);
    }

    try {
        $pdo->beginTransaction();

        if ($ttId_directo) {
            $stmtTT = $pdo->prepare("SELECT id FROM transporte_tronera WHERE id = ? AND estado IN ('A','U') LIMIT 1");
            $stmtTT->execute([$ttId_directo]);
            $ttId = $stmtTT->fetchColumn();
        } else {
            $stmtTT = $pdo->prepare("
                SELECT id FROM transporte_tronera
                WHERE carga_numero = ? AND estado IN ('A','U')
                ORDER BY id DESC LIMIT 1
            ");
            $stmtTT->execute([$carga_numero]);
            $ttId = $stmtTT->fetchColumn();
        }

        if (!$ttId) {
            $pdo->rollBack();
            resp(404, ['error' => 'No se encontró una asignación activa para liberar']);
        }

        $pdo->prepare("
            UPDATE transporte_tronera
            SET estado          = 'L',
                fecha_fin_uso   = NOW(),
                usuario_id      = ?,
                updated_at      = NOW()
            WHERE id = ?
        ")->execute([$_SESSION['cina_usuario_id'], $ttId]);

        $pdo->commit();
        resp(200, ['ok' => true, 'mensaje' => 'Tronera liberada correctamente']);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        resp(500, ['error' => $e->getMessage()]);
    }
    break;

// ══════════════════════════════════════════════════════════════
//  POST tronera_guardar
//  Crea o edita una tronera (CRUD edit).
//  Body: { id?, numero, tipo, anden, fila, columna, grado, estado, comentarios }
// ══════════════════════════════════════════════════════════════
case 'tronera_guardar':
    requireSession();

    // Validar permisos mínimos (roles 1, 2, 5, 10)
    $stmtRolTr = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
    $stmtRolTr->execute([$_SESSION['cina_usuario_id']]);
    if (!in_array((int)$stmtRolTr->fetchColumn(), [1, 2, 5, 10])) {
        resp(403, ['error' => 'Sin permisos para modificar troneras']);
    }

    $id        = (int)($body['id']         ?? 0);
    $numero    = trim($body['numero']      ?? '');
    $tipo      = trim($body['tipo']        ?? '');
    $anden     = trim($body['anden']       ?? '');
    $fila      = (int)($body['fila']       ?? 0);
    $columna   = (int)($body['columna']    ?? 0);
    $grado     = (int)($body['grado']      ?? 0);
    $estado    = trim($body['estado']      ?? 'A');
    $comentarios = trim($body['comentarios'] ?? '');

    if (!$numero)                          resp(400, ['error' => 'El número es obligatorio']);
    if (!in_array($tipo, ['N', 'F']))      resp(400, ['error' => 'Tipo inválido (N/F)']);
    if (!in_array($anden, ['N','S','F','E'])) resp(400, ['error' => 'Andén inválido (N/S/F/E)']);
    if (!in_array($estado, ['A','M','I'])) resp(400, ['error' => 'Estado inválido (A/M/I)']);

    try {
        if ($id) {
            // Editar
            $pdo->prepare("
                UPDATE tronera SET
                    numero      = ?,
                    tipo        = ?,
                    anden       = ?,
                    fila        = ?,
                    columna     = ?,
                    grado       = ?,
                    estado      = ?,
                    comentarios = ?
                WHERE id = ?
            ")->execute([$numero, $tipo, $anden, $fila, $columna, $grado, $estado, $comentarios ?: null, $id]);
            resp(200, ['ok' => true, 'id' => $id, 'modo' => 'editado']);
        } else {
            // Crear
            $pdo->prepare("
                INSERT INTO tronera
                    (numero, tipo, anden, fila, columna, grado, estado, comentarios)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$numero, $tipo, $anden, $fila, $columna, $grado, $estado, $comentarios ?: null]);
            resp(201, ['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'modo' => 'creado']);
        }
    } catch (Exception $e) {
        resp(500, ['error' => $e->getMessage()]);
    }
    break;

// ══════════════════════════════════════════════════════════════
//  POST tronera_eliminar
//  Baja lógica: estado → I (Inactiva). No se puede si hay
//  asignación activa.
//  Body: { id }
// ══════════════════════════════════════════════════════════════
case 'tronera_eliminar':
    requireSession();

    $stmtRolTr = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
    $stmtRolTr->execute([$_SESSION['cina_usuario_id']]);
    if (!in_array((int)$stmtRolTr->fetchColumn(), [1, 2, 5, 10])) {
        resp(403, ['error' => 'Sin permisos']);
    }

    $id = (int)($body['id'] ?? 0);
    if (!$id) resp(400, ['error' => 'Falta id']);

    try {
        // Verificar que no tenga asignación activa
        $stmtChk = $pdo->prepare("
            SELECT COUNT(*) FROM transporte_tronera
            WHERE tronera_asignada_id = ? AND estado IN ('A','U')
        ");
        $stmtChk->execute([$id]);
        if ((int)$stmtChk->fetchColumn() > 0) {
            resp(409, ['error' => 'No se puede desactivar: la tronera tiene una asignación activa']);
        }

        $pdo->prepare("UPDATE tronera SET estado = 'I' WHERE id = ?")->execute([$id]);
        resp(200, ['ok' => true]);
    } catch (Exception $e) {
        resp(500, ['error' => $e->getMessage()]);
    }
    break;

// ══════════════════════════════════════════════════════════════
//  GET tronera_historial
//  Historial de asignaciones de una tronera o carga.
//  GET ?action=tronera_historial&tronera_id=N
//  GET ?action=tronera_historial&carga_numero=N
// ══════════════════════════════════════════════════════════════
case 'tronera_historial':
    requireSession();
    $tronera_id   = (int)($_GET['tronera_id']   ?? 0);
    $carga_numero = (int)($_GET['carga_numero'] ?? 0);
    $limite       = min((int)($_GET['limite']   ?? 50), 200);

    if (!$tronera_id && !$carga_numero) {
        resp(400, ['error' => 'Falta tronera_id o carga_numero']);
    }

    try {
        $where  = ['1=1'];
        $params = [];
        if ($tronera_id) {
            $where[]  = 'tt.tronera_asignada_id = ?';
            $params[] = $tronera_id;
        }
        if ($carga_numero) {
            $where[]  = 'tt.carga_numero = ?';
            $params[] = $carga_numero;
        }

        $stmt = $pdo->prepare("
            SELECT
                tt.id, tt.carga_numero, tt.tronera_asignada_id,
                tt.estado,
                CASE tt.estado
                    WHEN 'A' THEN 'Asignado'
                    WHEN 'U' THEN 'En uso'
                    WHEN 'L' THEN 'Liberado'
                    WHEN 'C' THEN 'Cancelado'
                    ELSE tt.estado
                END AS estado_texto,
                tt.fecha_asignacion,
                tt.fecha_inicio_uso,
                tt.fecha_fin_uso,
                TIMESTAMPDIFF(MINUTE, tt.fecha_inicio_uso, COALESCE(tt.fecha_fin_uso, NOW())) AS duracion_min,
                t.numero AS tronera_numero,
                t.anden,
                u.UsuarioNombre AS usuario_nombre
            FROM transporte_tronera tt
            LEFT JOIN tronera t ON t.id = tt.tronera_asignada_id
            LEFT JOIN Usuarios u ON u.UsuarioID = tt.usuario_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY tt.id DESC
            LIMIT $limite
        ");
        $stmt->execute($params);
        resp(200, $stmt->fetchAll());
    } catch (Exception $e) {
        resp(500, ['error' => $e->getMessage()]);
    }
    break;
