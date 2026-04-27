<?php
/**
 * ══════════════════════════════════════════════════════════════════
 *  PARCHE 3B — Backend PHP: Gestión de Facturas
 *  Agregar estos cases al switch de api_2026ia.php
 * ══════════════════════════════════════════════════════════════════
 */

// ── SQL: ejecutar UNA SOLA VEZ para crear la tabla ───────────────
/*
CREATE TABLE IF NOT EXISTS facturas_emitidas (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    nro_factura         VARCHAR(20)    NOT NULL,
    fecha_factura       DATE           NOT NULL,
    cliente_tipo        ENUM('cliente','grupo') NOT NULL DEFAULT 'cliente',
    cliente_id          INT            NULL,
    grupo_id            INT            NULL,
    periodo_desde       DATE           NULL,
    periodo_hasta       DATE           NULL,
    monto_sin_iva       DECIMAL(14,2)  NULL,
    monto_final         DECIMAL(14,2)  NOT NULL,
    plazo_dias          INT            NULL,
    fecha_vencimiento   DATE           NULL,
    estado              ENUM('PENDIENTE','PAGADA','VENCIDA','ANULADA') NOT NULL DEFAULT 'PENDIENTE',
    url_factura         VARCHAR(500)   NULL,
    nro_oc              VARCHAR(100)   NULL,
    fecha_pago_estimada DATE           NULL,
    creado_por          INT            NULL,
    created_at          DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cliente   (cliente_tipo, cliente_id),
    INDEX idx_estado    (estado),
    INDEX idx_fecha     (fecha_factura)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
*/

// ═══════════════════════════════════════════════════════════════════
//  CASES A AGREGAR EN EL SWITCH DE api_2026ia.php
// ═══════════════════════════════════════════════════════════════════

    // ── GET: lista de facturas emitidas ──────────────────────────
    case 'gestion_facturas_lista':
        requireSession();
        try {
            // Si es cliente portal, filtrar por sus clientes
            $clienteIds = null;
            if (function_exists('getClienteIdsPermitidos')) {
                $clienteIds = getClienteIdsPermitidos(
                    $pdo,
                    $_SESSION['cina_usuario_id'],
                    $_SESSION['cina_rol_id'] ?? 0
                );
            }

            $where  = '1=1';
            $params = [];

            if ($clienteIds !== null) {
                if (empty($clienteIds)) {
                    resp(200, ['facturas' => []]);
                    break;
                }
                $placeholders = implode(',', array_fill(0, count($clienteIds), '?'));
                $where  .= " AND (f.cliente_tipo = 'cliente' AND f.cliente_id IN ($placeholders))";
                $params  = array_merge($params, $clienteIds);
            }

            $stmt = $pdo->prepare("
                SELECT
                    f.*,
                    CASE f.cliente_tipo
                        WHEN 'cliente' THEN c.ClienteNombre
                        WHEN 'grupo'   THEN g.Nombre
                    END AS cliente_nombre,
                    g.Nombre AS grupo_nombre
                FROM facturas_emitidas f
                LEFT JOIN CLIENTES c ON f.cliente_tipo = 'cliente' AND f.cliente_id = c.ClienteID
                LEFT JOIN CLIENTEGRUPO g ON (
                    (f.cliente_tipo = 'grupo' AND f.grupo_id = g.GrupoID)
                    OR (f.cliente_tipo = 'cliente' AND c.GrupoID = g.GrupoID)
                )
                WHERE $where
                ORDER BY f.fecha_factura DESC, f.id DESC
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            resp(200, ['facturas' => $rows]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── POST: crear factura ──────────────────────────────────────
    case 'gestion_factura_crear':
        requireSession();
        // Solo admins/roles internos pueden crear
        $stmtRolCheck = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
        $stmtRolCheck->execute([$_SESSION['cina_usuario_id']]);
        $rolActual = (int)$stmtRolCheck->fetchColumn();

        if (function_exists('getClienteIdsPermitidos')) {
            $esPortal = getClienteIdsPermitidos($pdo, $_SESSION['cina_usuario_id'], $rolActual);
            if ($esPortal !== null) resp(403, ['error' => 'Sin permisos para crear facturas']);
        }

        $nro      = trim($body['nro_factura']       ?? '');
        $fecha    = trim($body['fecha_factura']      ?? '');
        $cliTipo  = in_array($body['cliente_tipo']??'', ['cliente','grupo']) ? $body['cliente_tipo'] : 'cliente';
        $cliId    = (int)($body['cliente_id']        ?? 0);
        $mFinal   = (float)($body['monto_final']     ?? 0);

        if (!$nro)    resp(400, ['error' => 'Nº de factura obligatorio']);
        if (!$fecha)  resp(400, ['error' => 'Fecha de emisión obligatoria']);
        if (!$cliId)  resp(400, ['error' => 'Cliente obligatorio']);
        if (!$mFinal) resp(400, ['error' => 'Monto final obligatorio']);

        try {
            $pdo->prepare("
                INSERT INTO facturas_emitidas
                    (nro_factura, fecha_factura, cliente_tipo, cliente_id, grupo_id,
                     periodo_desde, periodo_hasta, monto_sin_iva, monto_final,
                     plazo_dias, fecha_vencimiento, estado, url_factura, creado_por)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $nro, $fecha, $cliTipo,
                $cliTipo === 'cliente' ? $cliId : null,
                $cliTipo === 'grupo'   ? $cliId : null,
                $body['periodo_desde']      ?: null,
                $body['periodo_hasta']      ?: null,
                isset($body['monto_sin_iva']) ? (float)$body['monto_sin_iva'] : null,
                $mFinal,
                isset($body['plazo_dias'])    ? (int)$body['plazo_dias'] : null,
                $body['fecha_vencimiento']  ?: null,
                $body['estado']             ?: 'PENDIENTE',
                $body['url_factura']        ?: null,
                $_SESSION['cina_usuario_id'],
            ]);
            resp(200, ['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── POST: editar factura ─────────────────────────────────────
    case 'gestion_factura_editar':
        requireSession();
        $stmtRolCheck = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
        $stmtRolCheck->execute([$_SESSION['cina_usuario_id']]);
        $rolActual = (int)$stmtRolCheck->fetchColumn();
        if (function_exists('getClienteIdsPermitidos')) {
            $esPortal = getClienteIdsPermitidos($pdo, $_SESSION['cina_usuario_id'], $rolActual);
            if ($esPortal !== null) resp(403, ['error' => 'Sin permisos']);
        }

        $id = (int)($body['id'] ?? 0);
        if (!$id) resp(400, ['error' => 'Falta id']);

        try {
            $pdo->prepare("
                UPDATE facturas_emitidas SET
                    nro_factura       = ?,
                    fecha_factura     = ?,
                    cliente_tipo      = ?,
                    cliente_id        = ?,
                    grupo_id          = ?,
                    periodo_desde     = ?,
                    periodo_hasta     = ?,
                    monto_sin_iva     = ?,
                    monto_final       = ?,
                    plazo_dias        = ?,
                    fecha_vencimiento = ?,
                    estado            = ?,
                    url_factura       = COALESCE(?, url_factura)
                WHERE id = ?
            ")->execute([
                $body['nro_factura']      ?? '',
                $body['fecha_factura']    ?? '',
                $body['cliente_tipo']     ?? 'cliente',
                ($body['cliente_tipo']??'') === 'cliente' ? (int)($body['cliente_id']??0) : null,
                ($body['cliente_tipo']??'') === 'grupo'   ? (int)($body['cliente_id']??0) : null,
                $body['periodo_desde']    ?: null,
                $body['periodo_hasta']    ?: null,
                isset($body['monto_sin_iva']) ? (float)$body['monto_sin_iva'] : null,
                (float)($body['monto_final'] ?? 0),
                isset($body['plazo_dias']) ? (int)$body['plazo_dias'] : null,
                $body['fecha_vencimiento'] ?: null,
                $body['estado']            ?: 'PENDIENTE',
                $body['url_factura']       ?: null,
                $id,
            ]);
            resp(200, ['ok' => true]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── POST: cliente carga su OC y fecha de pago estimada ───────
    case 'gestion_factura_cargar_oc':
        requireSession();
        $id      = (int)($body['id']                 ?? 0);
        $nroOC   = trim($body['nro_oc']              ?? '');
        $fechaPago = trim($body['fecha_pago_estimada'] ?? '');
        if (!$id) resp(400, ['error' => 'Falta id']);

        try {
            // Solo puede modificar facturas de sus clientes si es portal
            $stmtRolCheck = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
            $stmtRolCheck->execute([$_SESSION['cina_usuario_id']]);
            $rolActual = (int)$stmtRolCheck->fetchColumn();

            if (function_exists('getClienteIdsPermitidos')) {
                $idsPermitidos = getClienteIdsPermitidos($pdo, $_SESSION['cina_usuario_id'], $rolActual);
                if ($idsPermitidos !== null) {
                    // Verificar que la factura pertenece a uno de sus clientes
                    $stmtCheck = $pdo->prepare("SELECT cliente_id FROM facturas_emitidas WHERE id = ?");
                    $stmtCheck->execute([$id]);
                    $facClienteId = (int)$stmtCheck->fetchColumn();
                    if (!in_array($facClienteId, $idsPermitidos)) {
                        resp(403, ['error' => 'Sin acceso a esta factura']);
                    }
                }
            }

            $pdo->prepare("
                UPDATE facturas_emitidas
                SET nro_oc = ?, fecha_pago_estimada = ?
                WHERE id = ?
            ")->execute([$nroOC ?: null, $fechaPago ?: null, $id]);
            resp(200, ['ok' => true]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── POST multipart: subir PDF de factura ─────────────────────
    case 'gestion_factura_subir_pdf':
        requireSession();
        if (empty($_FILES['pdf'])) resp(400, ['error' => 'Sin archivo']);

        $file = $_FILES['pdf'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') resp(400, ['error' => 'Solo PDF']);
        if ($file['size'] > 15 * 1024 * 1024) resp(400, ['error' => 'Archivo demasiado grande (máx 15 MB)']);

        $dir = __DIR__ . '/uploads/facturas/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $nombre  = 'factura_' . time() . '_' . uniqid() . '.pdf';
        $destino = $dir . $nombre;
        $urlRel  = 'uploads/facturas/' . $nombre;

        if (!move_uploaded_file($file['tmp_name'], $destino)) {
            resp(500, ['error' => 'Error al guardar el archivo']);
        }
        resp(200, ['ok' => true, 'url' => $urlRel]);
        break;
