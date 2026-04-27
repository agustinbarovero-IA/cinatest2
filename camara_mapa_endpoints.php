<?php
/* ═══════════════════════════════════════════════════════════════════════════
   NUEVOS ENDPOINTS — Agregar en api_2026ia.php dentro del switch de actions
   ═══════════════════════════════════════════════════════════════════════════ */

/* ─────────────────────────────────────────────────────────────────────────
   ENDPOINT: camara_mapa
   Devuelve la estructura de pilas (calle+fila) con sus estibas agrupadas
   por nivel/altura.

   GET ?action=camara_mapa&camara_id=X

   Respuesta:
   {
     camara_id: N,
     calles: N,
     filas: N,
     total_posiciones: N,
     total_ocupadas: N,
     pilas: {
       "1_1": { alturas: 3, estibas: [...] },
       "1_2": { alturas: 1, estibas: [...] },
       ...
     }
   }
   ───────────────────────────────────────────────────────────────────────── */

    case 'camara_mapa':
        $camara_id = (int)($_GET['camara_id'] ?? 0);
        if (!$camara_id) resp(400, ['error' => 'Falta camara_id']);

        $resultado = ['camara_id' => $camara_id];

        // ── Estructura de la cámara ──────────────────────────────────────
        try {
            $stmt = $pdo->prepare("
                SELECT CamaraCalles, CamaraFilas, CamaraNiveles
                FROM Camaras WHERE CamaraID = ?
            ");
            $stmt->execute([$camara_id]);
            $estructura = $stmt->fetch();
            $resultado['calles']  = (int)($estructura['CamaraCalles']  ?? 0);
            $resultado['filas']   = (int)($estructura['CamaraFilas']   ?? 0);
            $resultado['niveles'] = (int)($estructura['CamaraNiveles'] ?? 1);
        } catch(Exception $e) {
            $resultado['calles'] = $resultado['filas'] = $resultado['niveles'] = 0;
        }

        // ── Estibas activas con posición ─────────────────────────────────
        $pilas              = [];
        $total_posiciones   = 0;
        $total_ocupadas     = 0;

        try {
            // Traer todas las posiciones de la cámara
            $stmtPos = $pdo->prepare("
                SELECT p.PosicionID, p.PosicionCalle, p.PosicionFila, p.PosicionNivel
                FROM Posiciones p
                WHERE p.CamaraID = ?
                ORDER BY p.PosicionCalle, p.PosicionFila, p.PosicionNivel
            ");
            $stmtPos->execute([$camara_id]);
            $posiciones = $stmtPos->fetchAll();
            $total_posiciones = count($posiciones);

            // Traer estibas activas con detalle
            $stmtEst = $pdo->prepare("
                SELECT
                    e.EstibaID                      AS estiba_id,
                    e.PosicionID                    AS posicion_id,
                    p.PosicionCalle                 AS calle,
                    p.PosicionFila                  AS fila,
                    p.PosicionNivel                 AS nivel,
                    ca.CargaNombre                  AS producto,
                    e.EstibaCantidad                AS cantidad,
                    e.EstibaPeso                    AS peso,
                    e.FechaAlta                     AS fecha_ingreso,
                    ca.CargaVencimiento             AS vencimiento,
                    cl.siglas                       AS cliente_siglas,
                    cl.color                        AS cliente_color,
                    cl.ClienteNombre                AS cliente_nombre
                FROM Estiba e
                JOIN Posiciones p  ON p.PosicionID  = e.PosicionID
                LEFT JOIN Cargas   ca ON ca.CargaID  = e.CargaID
                LEFT JOIN CLIENTES cl ON cl.ClienteID = ca.ClienteID
                WHERE p.CamaraID = ?
                  AND e.FechaBaja IS NULL
                ORDER BY p.PosicionCalle, p.PosicionFila, p.PosicionNivel
            ");
            $stmtEst->execute([$camara_id]);
            $estibas = $stmtEst->fetchAll();
            $total_ocupadas = count(array_unique(array_column($estibas, 'posicion_id')));

            // Agrupar estibas por pila (calle_fila)
            foreach ($estibas as $est) {
                $key = $est['calle'] . '_' . $est['fila'];
                if (!isset($pilas[$key])) {
                    $pilas[$key] = ['alturas' => 0, 'estibas' => []];
                }
                $pilas[$key]['estibas'][] = [
                    'estiba_id'      => (int)$est['estiba_id'],
                    'nivel'          => (int)$est['nivel'],
                    'producto'       => $est['producto'],
                    'cantidad'       => $est['cantidad'] ? (int)$est['cantidad'] : null,
                    'peso'           => $est['peso']     ? (float)$est['peso']   : null,
                    'fecha_ingreso'  => $est['fecha_ingreso'],
                    'vencimiento'    => $est['vencimiento'],
                    'cliente_siglas' => $est['cliente_siglas'],
                    'cliente_color'  => $est['cliente_color'],
                    'cliente_nombre' => $est['cliente_nombre'],
                ];
                // El número de alturas = nivel más alto ocupado en esa pila
                if ((int)$est['nivel'] > $pilas[$key]['alturas']) {
                    $pilas[$key]['alturas'] = (int)$est['nivel'];
                }
            }

        } catch(Exception $e) {
            $resultado['error_pilas'] = $e->getMessage();
        }

        $resultado['pilas']            = $pilas;
        $resultado['total_posiciones'] = $total_posiciones;
        $resultado['total_ocupadas']   = $total_ocupadas;
        resp(200, $resultado);
        break;

/* ─────────────────────────────────────────────────────────────────────────
   ENDPOINT: buscar_estiba
   Busca una estiba por ID o producto en TODAS las cámaras activas.

   GET ?action=buscar_estiba&q=TEXTO

   Respuesta: array de resultados con camara_id, camara_nombre, calle, fila,
   nivel, estiba_id, producto, cliente_siglas
   ───────────────────────────────────────────────────────────────────────── */

    case 'buscar_estiba':
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) resp(400, ['error' => 'Query muy corta']);

        $esNumero = is_numeric($q);
        $like     = '%' . $q . '%';

        try {
            if ($esNumero) {
                // Buscar por EstibaID exacto o por CargaNombre
                $stmt = $pdo->prepare("
                    SELECT
                        e.EstibaID                   AS estiba_id,
                        p.PosicionCalle              AS calle,
                        p.PosicionFila               AS fila,
                        p.PosicionNivel              AS nivel,
                        cam.CamaraID                 AS camara_id,
                        CASE
                          WHEN LOWER(t.TiposCamaraNombre) LIKE '%tunel%'
                            OR LOWER(t.TiposCamaraNombre) LIKE '%túnel%'
                            THEN CONCAT('Túnel ', cam.CamaraNumero)
                          WHEN LOWER(t.TiposCamaraNombre) LIKE '%antecamara%'
                            OR LOWER(t.TiposCamaraNombre) LIKE '%antecámara%'
                            THEN CONCAT('Antecámara ', cam.CamaraNumero)
                          ELSE CONCAT('Cámara ', cam.CamaraNumero)
                        END                          AS camara_nombre,
                        ca.CargaNombre               AS producto,
                        cl.siglas                    AS cliente_siglas,
                        cl.color                     AS cliente_color
                    FROM Estiba e
                    JOIN Posiciones p    ON p.PosicionID  = e.PosicionID
                    JOIN Camaras cam     ON cam.CamaraID  = p.CamaraID
                    JOIN TiposCamara t   ON t.TiposCamaraID = cam.TiposCamaraID
                    LEFT JOIN Cargas ca  ON ca.CargaID    = e.CargaID
                    LEFT JOIN CLIENTES cl ON cl.ClienteID = ca.ClienteID
                    WHERE e.FechaBaja IS NULL
                      AND cam.activo = 1
                      AND (
                        e.EstibaID = ?
                        OR ca.CargaNombre LIKE ?
                      )
                    ORDER BY e.EstibaID DESC
                    LIMIT 30
                ");
                $stmt->execute([(int)$q, $like]);
            } else {
                // Solo buscar por producto o cliente
                $stmt = $pdo->prepare("
                    SELECT
                        e.EstibaID                   AS estiba_id,
                        p.PosicionCalle              AS calle,
                        p.PosicionFila               AS fila,
                        p.PosicionNivel              AS nivel,
                        cam.CamaraID                 AS camara_id,
                        CASE
                          WHEN LOWER(t.TiposCamaraNombre) LIKE '%tunel%'
                            OR LOWER(t.TiposCamaraNombre) LIKE '%túnel%'
                            THEN CONCAT('Túnel ', cam.CamaraNumero)
                          WHEN LOWER(t.TiposCamaraNombre) LIKE '%antecamara%'
                            OR LOWER(t.TiposCamaraNombre) LIKE '%antecámara%'
                            THEN CONCAT('Antecámara ', cam.CamaraNumero)
                          ELSE CONCAT('Cámara ', cam.CamaraNumero)
                        END                          AS camara_nombre,
                        ca.CargaNombre               AS producto,
                        cl.siglas                    AS cliente_siglas,
                        cl.color                     AS cliente_color
                    FROM Estiba e
                    JOIN Posiciones p    ON p.PosicionID  = e.PosicionID
                    JOIN Camaras cam     ON cam.CamaraID  = p.CamaraID
                    JOIN TiposCamara t   ON t.TiposCamaraID = cam.TiposCamaraID
                    LEFT JOIN Cargas ca  ON ca.CargaID    = e.CargaID
                    LEFT JOIN CLIENTES cl ON cl.ClienteID = ca.ClienteID
                    WHERE e.FechaBaja IS NULL
                      AND cam.activo = 1
                      AND (
                        ca.CargaNombre LIKE ?
                        OR cl.siglas LIKE ?
                        OR cl.ClienteNombre LIKE ?
                      )
                    ORDER BY e.EstibaID DESC
                    LIMIT 30
                ");
                $stmt->execute([$like, $like, $like]);
            }

            $rows = $stmt->fetchAll();
            resp(200, array_map(fn($r) => [
                'estiba_id'      => (int)$r['estiba_id'],
                'camara_id'      => (int)$r['camara_id'],
                'camara_nombre'  => $r['camara_nombre'],
                'calle'          => (int)$r['calle'],
                'fila'           => (int)$r['fila'],
                'nivel'          => (int)$r['nivel'],
                'producto'       => $r['producto'],
                'cliente_siglas' => $r['cliente_siglas'],
                'cliente_color'  => $r['cliente_color'],
            ], $rows));

        } catch(Exception $e) {
            resp(500, ['error' => $e->getMessage()]);
        }
        break;
