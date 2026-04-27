<?php
/**
 * ============================================================
 *  api_panol.php — API REST módulo Pañol
 *  Grupo CINA
 * ============================================================
 *  Subir a: /public_html/api_panol.php
 *  (mismo servidor que MySQL, mismo config.php)
 *
 *  Endpoints GET:
 *    ?action=items              Lista catálogo (?cat= ?q=)
 *    ?action=item&id=X          Detalle + movimientos + préstamos
 *    ?action=prestamos          Préstamos activos (?usuario_id=)
 *    ?action=solicitudes        Solicitudes (?estado=pendiente)
 *    ?action=historial          Movimientos (?item_id= ?limit=)
 *    ?action=kpis               Contadores del panel superior
 *
 *  Endpoints POST:
 *    ?action=nuevo_item         Alta de ítem en catálogo
 *    ?action=editar_item        Editar datos de un ítem
 *    ?action=solicitar          Operario solicita herramienta
 *    ?action=aprobar            Encargado aprueba y entrega
 *    ?action=rechazar           Encargado rechaza solicitud
 *    ?action=devolucion         Registrar devolución
 *    ?action=ajuste_stock       Ajuste manual de stock
 * ============================================================
 */

require_once dirname(__DIR__) . '/config.php';
if (!defined('SECRET_KEY')) define('SECRET_KEY', SESSION_SECRET);

header('Content-Type: application/json; charset=utf-8');

if (defined('ALLOWED_ORIGIN') && ALLOWED_ORIGIN !== '') {
    header('Access-Control-Allow-Origin: ' . ALLOWED_ORIGIN);
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Credentials: true');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
}

$action = $_GET['action'] ?? '';

session_start();

function requireSession(): void {
    if (empty($_SESSION['cina_usuario_id'])) {
        resp(401, ['error' => 'Sesión expirada. Recargá la página.']);
    }
}

function sessionUser(): string {
    return $_SESSION['cina_nombre'] ?? $_SESSION['cina_usuario'] ?? 'Sistema';
}

function sessionUserId(): int {
    return (int)($_SESSION['cina_usuario_id'] ?? 0);
}

function resp(int $code, array $body): never {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
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
    resp(500, ['error' => 'Error de conexión: ' . $e->getMessage()]);
}

// Heartbeat (igual que api_2026ia.php)
if (!empty($_SESSION['cina_usuario_id'])) {
    foreach (['UltimoLogin', 'ultimo_login'] as $col) {
        try {
            $pdo->prepare("UPDATE Usuarios SET $col = NOW() WHERE UsuarioID = ?")
                ->execute([$_SESSION['cina_usuario_id']]);
            break;
        } catch(Exception $e) { continue; }
    }
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

// Verifica si el usuario logueado tiene rol de encargado/admin.
// Ajustar RolIDs según tu tabla Roles.
// 1=admin, 2=supervisor, 5=encargado, 9=sala_maquinas, 10=sistemas
function isEncargado(PDO $pdo): bool {
    $stmt = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
    $stmt->execute([sessionUserId()]);
    return in_array((int)$stmt->fetchColumn(), [1, 2, 5, 9, 10]);
}

switch ($action) {

    case 'items':
        requireSession();
        $cat = $_GET['cat'] ?? '';
        $q   = $_GET['q']   ?? '';

        $where = ['i.activo = 1'];
        $params = [];

        if ($cat) { $where[] = 'i.categoria = ?'; $params[] = $cat; }
        if ($q) {
            $like     = "%$q%";
            $where[]  = '(i.nombre LIKE ? OR i.numero_item LIKE ? OR i.descripcion LIKE ?)';
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
            if      ($it['stock_actual'] <= 0)               $it['estado_stock'] = 'sin_stock';
            elseif  ($it['stock_actual'] < $it['stock_minimo']) $it['estado_stock'] = 'bajo';
            elseif  ($it['prestados'] > 0)                   $it['estado_stock'] = 'en_prestamo';
            else                                              $it['estado_stock'] = 'ok';
        }
        unset($it);
        resp(200, ['items' => $items, 'total' => count($items)]);
        break;

    case 'item':
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

    case 'prestamos':
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

    case 'solicitudes':
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

    case 'historial':
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

    case 'kpis':
        requireSession();
        resp(200, [
            'total_items'           => (int)$pdo->query("SELECT COUNT(*) FROM panol_items WHERE activo=1")->fetchColumn(),
            'prestamos_activos'      => (int)$pdo->query("SELECT COUNT(*) FROM panol_prestamos WHERE estado='activo'")->fetchColumn(),
            'stock_bajo'             => (int)$pdo->query("SELECT COUNT(*) FROM panol_items WHERE activo=1 AND stock_actual < stock_minimo AND stock_actual > 0")->fetchColumn(),
            'sin_stock'              => (int)$pdo->query("SELECT COUNT(*) FROM panol_items WHERE activo=1 AND stock_actual = 0")->fetchColumn(),
            'solicitudes_pendientes' => (int)$pdo->query("SELECT COUNT(*) FROM panol_solicitudes WHERE estado='pendiente'")->fetchColumn(),
            'prestamos_vencidos'     => (int)$pdo->query("SELECT COUNT(*) FROM panol_prestamos WHERE estado='activo' AND fecha_limite IS NOT NULL AND fecha_limite < NOW()")->fetchColumn(),
        ]);
        break;

    case 'nuevo_item':
        requireSession();
        if (!isEncargado($pdo)) resp(403, ['error' => 'Sin permisos.']);

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
                sessionUserId(), sessionUser()
            ]);
        }
        resp(201, ['ok' => true, 'id' => $newId, 'mensaje' => 'Ítem creado correctamente.']);
        break;

    case 'editar_item':
        requireSession();
        if (!isEncargado($pdo)) resp(403, ['error' => 'Sin permisos.']);
        $id = (int)($body['id'] ?? 0);
        if (!$id) resp(400, ['error' => 'Falta id']);
        $pdo->prepare("
            UPDATE panol_items
            SET nombre=?, categoria=?, descripcion=?, ubicacion=?,
                stock_minimo=?, unidad=?, updated_at=NOW()
            WHERE id=?
        ")->execute([
            trim($body['nombre']      ?? ''),
            $body['categoria']         ?? '',
            trim($body['descripcion'] ?? ''),
            trim($body['ubicacion']   ?? ''),
            (int)($body['stock_minimo'] ?? 0),
            trim($body['unidad'] ?? 'unidad'),
            $id
        ]);
        resp(200, ['ok' => true, 'mensaje' => 'Ítem actualizado.']);
        break;

    case 'solicitar':
        requireSession();
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
        ")->execute([$item_id, $cantidad, sessionUserId(), sessionUser(), $motivo]);

        resp(201, ['ok' => true, 'mensaje' => 'Solicitud enviada. El encargado la procesará pronto.']);
        break;

    case 'aprobar':
        requireSession();
        if (!isEncargado($pdo)) resp(403, ['error' => 'Sin permisos.']);

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
                $obs, sessionUserId(), sessionUser(), $solicitud_id
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

    case 'rechazar':
        requireSession();
        if (!isEncargado($pdo)) resp(403, ['error' => 'Sin permisos.']);

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

    case 'devolucion':
        requireSession();
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
            $it          = $itemRow->fetch();
            $antes       = $it['stock_actual'];
            $despues     = $antes + $reponer;

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
                $obs, sessionUserId(), sessionUser()
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

    case 'ajuste_stock':
        requireSession();
        if (!isEncargado($pdo)) resp(403, ['error' => 'Sin permisos.']);

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
            "Ajuste manual: $motivo", sessionUserId(), sessionUser()
        ]);

        resp(200, ['ok' => true, 'mensaje' => "Stock ajustado de $antes a $nuevo_stock."]);
        break;

    default:
        resp(404, ['error' => 'Acción no encontrada: ' . $action]);
}
