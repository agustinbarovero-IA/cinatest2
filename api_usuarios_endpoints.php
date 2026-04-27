<?php
/*
 * ============================================================
 *  FRAGMENTO PARA PEGAR EN api_2026ia.php
 *  Agregar los case siguientes ANTES del `default:` en el switch($action).
 * ============================================================
 *
 *  Endpoints:
 *    GET  ?action=usuarios_lista            → lista de usuarios con foto_url
 *    GET  ?action=roles_lista               → roles con cantidad de usuarios
 *    GET  ?action=usuario_foto_actual       → foto del usuario en sesión
 *    POST ?action=usuario_crear             → crea nuevo usuario
 *    POST ?action=usuario_editar            → edita usuario existente
 *    POST ?action=usuario_cambiar_clave     → cambia contraseña
 *    POST ?action=usuario_actualizar_foto   → actualiza foto_url (reset o URL directa)
 *    POST ?action=usuario_subir_foto        → sube archivo de foto (multipart)
 *    POST ?action=rol_crear                 → crea nuevo rol
 *    POST ?action=rol_editar                → edita rol existente
 *    GET  ?action=rol_permisos&rol_id=N     → permisos actuales de un rol
 *    POST ?action=rol_guardar_permisos      → guarda permisos de módulos de un rol
 *
 *  Tabla nueva necesaria (ejecutar una vez):
 *    CREATE TABLE IF NOT EXISTS rol_permisos (
 *      id       INT AUTO_INCREMENT PRIMARY KEY,
 *      rol_id   INT NOT NULL,
 *      modulo   VARCHAR(60) NOT NULL,
 *      activo   TINYINT(1) NOT NULL DEFAULT 1,
 *      UNIQUE KEY uk_rol_modulo (rol_id, modulo)
 *    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 *
 *  Columna nueva en Usuarios (si no existe):
 *    ALTER TABLE Usuarios ADD COLUMN foto_url VARCHAR(255) NULL AFTER persona_id;
 *
 *  Directorio para fotos de perfil (crear en servidor):
 *    uploads/perfil/  (con permisos de escritura)
 * ============================================================
 */

    // ────────────────────────────────────────────────────────────
    //  ROLES QUE PUEDEN GESTIONAR USUARIOS
    // ────────────────────────────────────────────────────────────
    // Helper local (no sobreescribe requireSession global)
    $rolesAdmin = [1, 5, 10];  // ajustar según el sistema

    // ── GET: lista completa de usuarios ─────────────────────────
    case 'usuarios_lista':
        requireSession();
        $stmtRolCheck = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
        $stmtRolCheck->execute([$_SESSION['cina_usuario_id']]);
        $rolActual = (int)$stmtRolCheck->fetchColumn();
        if (!in_array($rolActual, [1, 5, 10])) {
            resp(403, ['error' => 'Sin permisos']);
        }

        $rows = $pdo->query("
            SELECT
                u.UsuarioID,
                u.UsuarioNombre,
                u.RolID,
                u.activo,
                u.UltimoLogin,
                -- foto_url: si la columna existe la traemos, si no devolvemos NULL
                COALESCE(u.foto_url, NULL) AS foto_url,
                COALESCE(
                    CONCAT(TRIM(p.nombre), ' ', TRIM(p.apellido)),
                    u.UsuarioNombre
                ) AS nombre_completo,
                p.email1 AS email
            FROM Usuarios u
            LEFT JOIN persona p ON p.persona_id = u.persona_id
            ORDER BY u.activo DESC, u.RolID, u.UsuarioNombre
        ")->fetchAll();
        resp(200, $rows);
        break;

    // ── GET: lista de roles con cantidad de usuarios ─────────────
    case 'roles_lista':
        requireSession();
        try {
            $rows = $pdo->query("
                SELECT
                    r.RolID,
                    r.RolNombre,
                    COALESCE(r.funcion, r.Funcion, '') AS funcion,
                    COUNT(u.UsuarioID) AS cantidad_usuarios
                FROM Roles r
                LEFT JOIN Usuarios u ON u.RolID = r.RolID AND u.activo = 1
                GROUP BY r.RolID, r.RolNombre
                ORDER BY r.RolNombre
            ")->fetchAll();
            resp(200, $rows);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── GET: foto del usuario en sesión ─────────────────────────
    case 'usuario_foto_actual':
        requireSession();
        try {
            $stmt = $pdo->prepare("SELECT foto_url FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
            $stmt->execute([$_SESSION['cina_usuario_id']]);
            $fotoUrl = $stmt->fetchColumn();
            resp(200, ['foto_url' => $fotoUrl ?: null]);
        } catch (Exception $e) {
            // Si la columna no existe todavía
            resp(200, ['foto_url' => null]);
        }
        break;

    // ── POST: crear usuario ──────────────────────────────────────
    case 'usuario_crear':
        requireSession();
        $stmtRolCheck = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
        $stmtRolCheck->execute([$_SESSION['cina_usuario_id']]);
        $rolActual = (int)$stmtRolCheck->fetchColumn();
        if (!in_array($rolActual, [1, 5, 10])) resp(403, ['error' => 'Sin permisos']);

        $usuarioNombre   = trim($body['UsuarioNombre']    ?? '');
        $nombreCompleto  = trim($body['nombre_completo']  ?? '');
        $email           = trim($body['email']            ?? '');
        $rolId           = (int)($body['RolID']           ?? 0);
        $activo          = (int)($body['activo']          ?? 1);
        $password        = $body['password']              ?? '';

        if (!$usuarioNombre) resp(400, ['error' => 'El nombre de usuario es obligatorio']);
        if (!$password)      resp(400, ['error' => 'La contraseña es obligatoria']);

        // Verificar que no exista
        $chk = $pdo->prepare("SELECT UsuarioID FROM Usuarios WHERE UsuarioNombre = ? LIMIT 1");
        $chk->execute([$usuarioNombre]);
        if ($chk->fetchColumn()) resp(409, ['error' => 'El usuario ya existe']);

        try {
            $pdo->beginTransaction();

            // ── PASO 1: Crear registro en persona (requerido por FK_personas) ──
            $partes   = array_filter(explode(' ', $nombreCompleto, 2));
            $nombre   = trim($partes[0] ?? $usuarioNombre);
            $apellido = trim($partes[1] ?? '');

            $pdo->prepare("
                INSERT INTO persona (nombre, apellido, email1)
                VALUES (?, ?, ?)
            ")->execute([$nombre, $apellido, $email ?: null]);

            $personaId = (int)$pdo->lastInsertId();

            // ── PASO 2: Crear usuario con persona_id ──
            $pdo->prepare("
                INSERT INTO Usuarios (UsuarioNombre, UsuarioClave, RolID, activo, persona_id, ultimo_login)
                VALUES (?, SHA1(?), ?, ?, ?, NOW())
            ")->execute([$usuarioNombre, $password, $rolId ?: null, $activo, $personaId]);

            $newId = (int)$pdo->lastInsertId();
            $pdo->commit();
            resp(200, ['ok' => true, 'id' => $newId, 'persona_id' => $personaId]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── POST: editar usuario ─────────────────────────────────────
    case 'usuario_editar':
        requireSession();
        $stmtRolCheck = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
        $stmtRolCheck->execute([$_SESSION['cina_usuario_id']]);
        $rolActual = (int)$stmtRolCheck->fetchColumn();
        if (!in_array($rolActual, [1, 5, 10])) resp(403, ['error' => 'Sin permisos']);

        $usuarioId    = (int)($body['UsuarioID']       ?? 0);
        $rolId        = (int)($body['RolID']           ?? 0);
        $activo       = (int)($body['activo']          ?? 1);
        $email        = trim($body['email']            ?? '');

        if (!$usuarioId) resp(400, ['error' => 'Falta UsuarioID']);

        try {
            // Actualizar rol y activo en Usuarios
            $pdo->prepare("
                UPDATE Usuarios SET RolID = ?, activo = ? WHERE UsuarioID = ?
            ")->execute([$rolId ?: null, $activo, $usuarioId]);

            // Actualizar email en persona si existe
            if ($email) {
                $stmtPer = $pdo->prepare("SELECT persona_id FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
                $stmtPer->execute([$usuarioId]);
                $personaId = $stmtPer->fetchColumn();
                if ($personaId) {
                    $pdo->prepare("UPDATE persona SET email1 = ? WHERE persona_id = ?")
                        ->execute([$email, $personaId]);
                }
            }
            resp(200, ['ok' => true]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── POST: cambiar contraseña ─────────────────────────────────
    case 'usuario_cambiar_clave':
        requireSession();
        // Puede cambiarse a sí mismo O si es admin
        $stmtRolCheck = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
        $stmtRolCheck->execute([$_SESSION['cina_usuario_id']]);
        $rolActual = (int)$stmtRolCheck->fetchColumn();

        $usuarioId = (int)($body['UsuarioID'] ?? 0);
        $password  = $body['password'] ?? '';

        $esPropioUsuario = ($usuarioId === (int)$_SESSION['cina_usuario_id']);
        $esAdmin         = in_array($rolActual, [1, 5, 10]);

        if (!$esPropioUsuario && !$esAdmin) resp(403, ['error' => 'Sin permisos']);
        if (!$usuarioId)  resp(400, ['error' => 'Falta UsuarioID']);
        if (!$password)   resp(400, ['error' => 'La contraseña no puede estar vacía']);

        try {
            $pdo->prepare("UPDATE Usuarios SET UsuarioClave = SHA1(?) WHERE UsuarioID = ?")
                ->execute([$password, $usuarioId]);
            resp(200, ['ok' => true]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── POST: actualizar foto_url (reset o URL directa) ──────────
    case 'usuario_actualizar_foto':
        requireSession();
        $stmtRolCheck = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
        $stmtRolCheck->execute([$_SESSION['cina_usuario_id']]);
        $rolActual = (int)$stmtRolCheck->fetchColumn();
        if (!in_array($rolActual, [1, 5, 10])) resp(403, ['error' => 'Sin permisos para modificar fotos']);

        $usuarioId = (int)($body['UsuarioID'] ?? 0);
        $fotoUrl   = $body['foto_url'] ?? null; // null = restablecer default

        if (!$usuarioId) resp(400, ['error' => 'Falta UsuarioID']);

        try {
            // Agregar columna si no existe (migración automática segura)
            try {
                $pdo->exec("ALTER TABLE Usuarios ADD COLUMN foto_url VARCHAR(255) NULL");
            } catch (Exception $ex) { /* ya existe */ }

            $pdo->prepare("UPDATE Usuarios SET foto_url = ? WHERE UsuarioID = ?")
                ->execute([$fotoUrl, $usuarioId]);
            resp(200, ['ok' => true, 'foto_url' => $fotoUrl]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── POST multipart: subir foto de perfil ─────────────────────
    case 'usuario_subir_foto':
        requireSession();
        $stmtRolCheck = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
        $stmtRolCheck->execute([$_SESSION['cina_usuario_id']]);
        $rolActual = (int)$stmtRolCheck->fetchColumn();
        if (!in_array($rolActual, [1, 5, 10])) resp(403, ['error' => 'Sin permisos para subir fotos']);

        $usuarioId = (int)($_POST['usuario_id'] ?? 0);
        if (!$usuarioId)          resp(400, ['error' => 'Falta usuario_id']);
        if (empty($_FILES['foto'])) resp(400, ['error' => 'Sin archivo']);

        $file    = $_FILES['foto'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($ext, $allowed))          resp(400, ['error' => 'Tipo de imagen no permitido']);
        if ($file['size'] > 5 * 1024 * 1024)   resp(400, ['error' => 'Imagen demasiado grande (máx 5 MB)']);

        $dir = __DIR__ . '/uploads/perfil/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Borrar foto anterior si existe
        try {
            $stmtFotoAnt = $pdo->prepare("SELECT foto_url FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
            $stmtFotoAnt->execute([$usuarioId]);
            $fotoAnt = $stmtFotoAnt->fetchColumn();
            if ($fotoAnt && strpos($fotoAnt, 'uploads/perfil/') !== false) {
                $pathAnt = __DIR__ . '/' . $fotoAnt;
                if (file_exists($pathAnt)) @unlink($pathAnt);
            }
        } catch (Exception $ex) { /* ignorar */ }

        $nombre   = 'perfil_' . $usuarioId . '_' . time() . '.' . $ext;
        $destino  = $dir . $nombre;
        $urlRelat = 'uploads/perfil/' . $nombre;

        if (!move_uploaded_file($file['tmp_name'], $destino)) {
            resp(500, ['error' => 'Error al guardar la imagen']);
        }

        try {
            // Agregar columna si no existe
            try {
                $pdo->exec("ALTER TABLE Usuarios ADD COLUMN foto_url VARCHAR(255) NULL");
            } catch (Exception $ex) { /* ya existe */ }

            $pdo->prepare("UPDATE Usuarios SET foto_url = ? WHERE UsuarioID = ?")
                ->execute([$urlRelat, $usuarioId]);
            resp(200, ['ok' => true, 'foto_url' => $urlRelat]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── POST: crear rol ──────────────────────────────────────────
    case 'rol_crear':
        requireSession();
        $stmtRolCheck = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
        $stmtRolCheck->execute([$_SESSION['cina_usuario_id']]);
        $rolActual = (int)$stmtRolCheck->fetchColumn();
        if (!in_array($rolActual, [1, 5, 10])) resp(403, ['error' => 'Sin permisos']);

        $rolNombre = trim($body['RolNombre'] ?? '');
        $funcion   = trim($body['funcion']   ?? '');
        if (!$rolNombre) resp(400, ['error' => 'El nombre del rol es obligatorio']);

        try {
            $pdo->prepare("INSERT INTO Roles (RolNombre, funcion) VALUES (?, ?)")
                ->execute([$rolNombre, $funcion ?: null]);
            resp(200, ['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── POST: editar rol ─────────────────────────────────────────
    case 'rol_editar':
        requireSession();
        $stmtRolCheck = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
        $stmtRolCheck->execute([$_SESSION['cina_usuario_id']]);
        $rolActual = (int)$stmtRolCheck->fetchColumn();
        if (!in_array($rolActual, [1, 5, 10])) resp(403, ['error' => 'Sin permisos']);

        $rolId     = (int)($body['RolID']    ?? 0);
        $rolNombre = trim($body['RolNombre'] ?? '');
        $funcion   = trim($body['funcion']   ?? '');
        if (!$rolId)     resp(400, ['error' => 'Falta RolID']);
        if (!$rolNombre) resp(400, ['error' => 'El nombre del rol es obligatorio']);

        try {
            $pdo->prepare("UPDATE Roles SET RolNombre = ?, funcion = ? WHERE RolID = ?")
                ->execute([$rolNombre, $funcion ?: null, $rolId]);
            resp(200, ['ok' => true]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── GET: permisos actuales de un rol ─────────────────────────
    case 'rol_permisos':
        requireSession();
        $rolId = (int)($_GET['rol_id'] ?? 0);
        if (!$rolId) resp(400, ['error' => 'Falta rol_id']);

        try {
            $modulos        = [];
            $modulos_editar = [];

            $stmt = $pdo->prepare("SELECT modulo, puede_ver, puede_editar FROM rol_permisos WHERE rol_id = ?");
            $stmt->execute([$rolId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ($row['puede_ver'])    $modulos[]        = $row['modulo'];
                if ($row['puede_editar']) $modulos_editar[] = $row['modulo'];
            }

            // Permisos extra (pañol, compras, cliente_portal, etc.)
            $extra = [];
            $stmtE = $pdo->prepare("SELECT permiso, valor FROM rol_permisos_extra WHERE rol_id = ?");
            $stmtE->execute([$rolId]);
            foreach ($stmtE->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $extra[$row['permiso']] = (bool)$row['valor'];
            }

            resp(200, [
                'rol_id'         => $rolId,
                'modulos'        => $modulos,
                'modulos_editar' => $modulos_editar,
                'extra'          => $extra,
            ]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── POST: guardar permisos de módulos de un rol ──────────────
    case 'rol_guardar_permisos':
        requireSession();
        $stmtRolCheck = $pdo->prepare("SELECT RolID FROM Usuarios WHERE UsuarioID = ? LIMIT 1");
        $stmtRolCheck->execute([$_SESSION['cina_usuario_id']]);
        $rolActual = (int)$stmtRolCheck->fetchColumn();
        if (!in_array($rolActual, [1, 5, 10])) resp(403, ['error' => 'Sin permisos']);

        $rolId         = (int)($body['RolID']          ?? 0);
        $modulos       = $body['modulos']               ?? [];
        $modulos_editar = $body['modulos_editar']       ?? [];
        $extra          = $body['extra']                ?? [];
        if (!$rolId) resp(400, ['error' => 'Falta RolID']);

        // Sanitizar nombres de módulo
        $sanitize = fn($arr) => array_values(array_filter(array_map(
            fn($m) => preg_replace('/[^a-z0-9_]/', '', strtolower(trim($m))),
            (array)$arr
        )));
        $modulos        = $sanitize($modulos);
        $modulos_editar = $sanitize($modulos_editar);
        $todos          = array_unique(array_merge($modulos, $modulos_editar));

        try {
            $pdo->beginTransaction();

            // Borrar permisos anteriores del rol
            $pdo->prepare("DELETE FROM rol_permisos WHERE rol_id = ?")->execute([$rolId]);

            // Insertar con puede_ver / puede_editar
            if (!empty($todos)) {
                $stmtIns = $pdo->prepare("
                    INSERT INTO rol_permisos (rol_id, modulo, activo, puede_ver, puede_editar)
                    VALUES (?, ?, 1, ?, ?)
                ");
                foreach ($todos as $m) {
                    $ver  = in_array($m, $modulos)        ? 1 : 0;
                    $edit = in_array($m, $modulos_editar) ? 1 : 0;
                    $stmtIns->execute([$rolId, $m, $ver, $edit]);
                }
            }

            // Guardar permisos extra (upsert)
            if (!empty($extra) && is_array($extra)) {
                $stmtE = $pdo->prepare("
                    INSERT INTO rol_permisos_extra (rol_id, permiso, valor)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE valor = VALUES(valor)
                ");
                foreach ($extra as $permiso => $valor) {
                    $permiso = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($permiso)));
                    if ($permiso) $stmtE->execute([$rolId, $permiso, $valor ? 1 : 0]);
                }
            }

            $pdo->commit();
            resp(200, ['ok' => true, 'rol_id' => $rolId, 'modulos_guardados' => count($todos)]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── GET: grupos con sus clientes (para el modal de usuario) ──
    case 'grupos_clientes_lista':
        requireSession();
        try {
            // Grupos activos
            $grupos = $pdo->query("
                SELECT GrupoID, Nombre
                FROM CLIENTEGRUPO
                WHERE Activo = 1
                ORDER BY Nombre
            ")->fetchAll();

            // Todos los clientes activos (con y sin grupo)
            $rows = $pdo->query("
                SELECT ClienteID, ClienteNombre, GrupoID
                FROM CLIENTES
                WHERE Activo = 1
                ORDER BY ClienteNombre
            ")->fetchAll();

            // Indexar por GrupoID
            $clientesPorGrupo = [];
            $clientesSinGrupo = [];
            foreach ($rows as $c) {
                if ($c['GrupoID']) {
                    $clientesPorGrupo[$c['GrupoID']][] = $c;
                } else {
                    $clientesSinGrupo[] = $c;
                }
            }

            // Agregar pseudo-grupo para clientes sin grupo
            if (!empty($clientesSinGrupo)) {
                $grupos[] = ['GrupoID' => 0, 'Nombre' => 'Clientes sin grupo'];
                $clientesPorGrupo[0] = $clientesSinGrupo;
            }

            resp(200, [
                'grupos'            => $grupos,
                'clientes_por_grupo'=> $clientesPorGrupo,
            ]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── GET: clientes asignados a un usuario ─────────────────────
    case 'usuario_clientes':
        requireSession();
        try {
            // Acepta usuario_id por GET (admin consultando otro usuario)
            // o usa la sesión actual si no se pasa
            $uid = isset($_GET['usuario_id']) ? intval($_GET['usuario_id']) : (int)$_SESSION['cina_usuario_id'];
            if (!$uid) resp(400, ['error' => 'usuario_id requerido']);

            $stmt = $pdo->prepare("
                SELECT cliente_id FROM usuario_cliente WHERE usuario_id = ? ORDER BY cliente_id
            ");
            $stmt->execute([$uid]);
            $ids = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'cliente_id'));

            resp(200, [
                'usuario_id'  => $uid,
                'cliente_ids' => $ids,
                'total'       => count($ids),
            ]);
        } catch (Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;

    // ── POST: guardar clientes asignados a un usuario ─────────────
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

        // Sanitizar: solo enteros positivos
        $clienteIds = array_values(array_unique(array_filter(array_map('intval', $clienteIds))));

        try {
            $pdo->beginTransaction();

            // Borrar asignaciones anteriores
            $pdo->prepare("DELETE FROM usuario_cliente WHERE usuario_id = ?")->execute([$uid]);

            // Insertar nuevas
            if (!empty($clienteIds)) {
                $stmtIns = $pdo->prepare("INSERT IGNORE INTO usuario_cliente (usuario_id, cliente_id) VALUES (?, ?)");
                foreach ($clienteIds as $cid) {
                    $stmtIns->execute([$uid, $cid]);
                }
            }

            $pdo->commit();
            resp(200, ['ok' => true, 'usuario_id' => $uid, 'clientes_guardados' => count($clienteIds)]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            resp(500, ['error' => $e->getMessage()]);
        }
        break;
