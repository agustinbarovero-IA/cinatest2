<?php
/**
 * api_usuarios_endpoints.php — Extensión de api_2026ia.php
 * NUEVOS ACTIONS:
 *   rol_permisos          GET  ?action=rol_permisos&rol_id=N
 *   rol_guardar_permisos  POST ?action=rol_guardar_permisos  { RolID, modulos[], modulos_editar[], extra{} }
 *   usuario_clientes      GET  ?action=usuario_clientes&usuario_id=N
 *   clientes_lista        (extensión) acepta &cliente_ids=1,2,3 para filtrar
 *
 * Agregar estas funciones al switch principal de api_2026ia.php:
 *   case 'rol_permisos':          echo json_encode(api_rol_permisos($pdo)); break;
 *   case 'rol_guardar_permisos':  echo json_encode(api_rol_guardar_permisos($pdo)); break;
 *   case 'usuario_clientes':      echo json_encode(api_usuario_clientes($pdo)); break;
 *
 * Y en el case 'clientes_lista', agregar el filtro de cliente_ids (ver abajo).
 */

// ══════════════════════════════════════════════════════════════
//  GET rol_permisos — Devuelve módulos/editar/extra de un rol
// ══════════════════════════════════════════════════════════════
function api_rol_permisos($pdo) {
    $rol_id = intval($_GET['rol_id'] ?? 0);
    if (!$rol_id) return ['error' => 'rol_id requerido'];

    $modulos        = [];
    $modulos_editar = [];

    // Leer permisos de módulos
    $stmt = $pdo->prepare("
        SELECT modulo, puede_ver, puede_editar
        FROM rol_permisos
        WHERE rol_id = ?
    ");
    $stmt->execute([$rol_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ($row['puede_ver'])    $modulos[]        = $row['modulo'];
        if ($row['puede_editar']) $modulos_editar[] = $row['modulo'];
    }

    // Leer permisos extra
    $extra = [];
    $stmtE = $pdo->prepare("SELECT permiso, valor FROM rol_permisos_extra WHERE rol_id = ?");
    $stmtE->execute([$rol_id]);
    foreach ($stmtE->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $extra[$row['permiso']] = (bool) $row['valor'];
    }

    return [
        'rol_id'         => $rol_id,
        'modulos'        => $modulos,
        'modulos_editar' => $modulos_editar,
        'extra'          => $extra,
    ];
}

// ══════════════════════════════════════════════════════════════
//  POST rol_guardar_permisos — Guarda permisos de un rol
// ══════════════════════════════════════════════════════════════
function api_rol_guardar_permisos($pdo) {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) return ['error' => 'Body inválido'];

    $rol_id         = intval($body['RolID']          ?? 0);
    $modulos        = $body['modulos']               ?? [];
    $modulos_editar = $body['modulos_editar']        ?? [];
    $extra          = $body['extra']                 ?? [];

    if (!$rol_id) return ['error' => 'RolID requerido'];

    // Todos los módulos conocidos (unión de ver + editar)
    $todos = array_unique(array_merge($modulos, $modulos_editar));

    // Borrar permisos anteriores y reinsertar
    $pdo->prepare("DELETE FROM rol_permisos WHERE rol_id = ?")->execute([$rol_id]);

    if (!empty($todos)) {
        $stmt = $pdo->prepare("
            INSERT INTO rol_permisos (rol_id, modulo, puede_ver, puede_editar)
            VALUES (?, ?, ?, ?)
        ");
        foreach ($todos as $mod) {
            $ver   = in_array($mod, $modulos)        ? 1 : 0;
            $edit  = in_array($mod, $modulos_editar) ? 1 : 0;
            $stmt->execute([$rol_id, $mod, $ver, $edit]);
        }
    }

    // Guardar permisos extra (upsert)
    if (!empty($extra)) {
        $stmtE = $pdo->prepare("
            INSERT INTO rol_permisos_extra (rol_id, permiso, valor)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE valor = VALUES(valor)
        ");
        foreach ($extra as $permiso => $valor) {
            $stmtE->execute([$rol_id, $permiso, $valor ? 1 : 0]);
        }
    }

    return ['ok' => true, 'rol_id' => $rol_id];
}

// ══════════════════════════════════════════════════════════════
//  GET usuario_clientes — Devuelve cliente_ids del usuario
// ══════════════════════════════════════════════════════════════
function api_usuario_clientes($pdo) {
    // Usar usuario_id de sesión si no se pasa por GET (más seguro)
    session_start_if_needed(); // asumiendo que la sesión ya está iniciada
    $usuario_id = intval($_GET['usuario_id'] ?? $_SESSION['cina_usuario_id'] ?? 0);
    if (!$usuario_id) return ['error' => 'usuario_id requerido'];

    $stmt = $pdo->prepare("
        SELECT cliente_id
        FROM usuario_cliente
        WHERE usuario_id = ?
        ORDER BY cliente_id
    ");
    $stmt->execute([$usuario_id]);
    $ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'cliente_id');

    return [
        'usuario_id'  => $usuario_id,
        'cliente_ids' => array_map('intval', $ids),
        'total'       => count($ids),
    ];
}

// ══════════════════════════════════════════════════════════════
//  MODIFICACIÓN en el case 'clientes_lista'
//  Agregar este bloque ANTES de ejecutar la query principal:
// ══════════════════════════════════════════════════════════════
/*
    // Filtro por cliente_ids (acceso portal cliente)
    $cliente_ids_raw = $_GET['cliente_ids'] ?? '';
    $cliente_ids_filter = '';
    if ($cliente_ids_raw !== '') {
        $ids = array_filter(array_map('intval', explode(',', $cliente_ids_raw)));
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $cliente_ids_filter = " AND c.ClienteID IN ($placeholders)";
            // Agregar $ids al array de parámetros de la query
        }
    }

    // También filtrar por usuario_cliente si la sesión tiene rol cliente_portal:
    // (Verificar en tabla rol_permisos_extra si el rol del usuario tiene cliente_portal=1)
    // Esto se puede hacer en PHP consultando la DB con $_SESSION['cina_rol_id']
*/

// ══════════════════════════════════════════════════════════════
//  FILTRADO AUTOMÁTICO EN BACKEND (más seguro que frontend)
//  Agregar esta función y llamarla en clientes_lista, facturacion, etc.
// ══════════════════════════════════════════════════════════════
function getClienteIdsPermitidos($pdo, $usuario_id, $rol_id) {
    // Verificar si el rol tiene cliente_portal activo
    $stmt = $pdo->prepare("
        SELECT valor FROM rol_permisos_extra
        WHERE rol_id = ? AND permiso = 'cliente_portal'
    ");
    $stmt->execute([$rol_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || !$row['valor']) {
        return null; // Sin restricción — acceso total
    }

    // Retornar solo los clientes asociados a este usuario
    $stmt2 = $pdo->prepare("
        SELECT cliente_id FROM usuario_cliente WHERE usuario_id = ?
    ");
    $stmt2->execute([$usuario_id]);
    $ids = array_column($stmt2->fetchAll(PDO::FETCH_ASSOC), 'cliente_id');
    return array_map('intval', $ids);
}

/*
 * USO en clientes_lista, facturacion_resumen_endpoint.php, etc.:
 *
 *   $clienteIds = getClienteIdsPermitidos($pdo, $_SESSION['cina_usuario_id'], $_SESSION['cina_rol_id']);
 *   if ($clienteIds !== null) {
 *       // Agregar filtro WHERE c.ClienteID IN (...)
 *       if (empty($clienteIds)) {
 *           // El usuario no tiene clientes asignados — retornar vacío
 *           echo json_encode(['clientes' => [], 'total' => 0, 'total_paginas' => 0]);
 *           exit;
 *       }
 *       $placeholders = implode(',', array_fill(0, count($clienteIds), '?'));
 *       // WHERE c.ClienteID IN ($placeholders) AND ...
 *   }
 */
