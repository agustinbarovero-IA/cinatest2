<?php
/**
 * ============================================================
 *  ENDPOINT: facturacion_resumen  (versión con soporte PICKING)
 *  Reemplaza el bloque anterior completo en api_2026ia.php
 *  Agregar ANTES del bloque "default:"
 * ============================================================
 *
 *  GET /api_2026ia.php?action=facturacion_resumen
 *       &cliente_id=123
 *       &desde=2026-01-01
 *       &hasta=2026-01-31
 * ============================================================
 */

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

        $fechaDesdeMenos1 = date('Y-m-d', strtotime('-1 day', $ts_desde));

        try {

            // ── Datos del cliente ────────────────────────────────────
            $stmtCli = $pdo->prepare("
                SELECT cl.ClienteID, cl.ClienteNombre, cl.NumeroReferencia,
                       cl.TipoMedicionTemperatura, cl.factura_picking, cl.factura_por
                FROM CLIENTES cl
                WHERE cl.ClienteID = ?
            ");
            $stmtCli->execute([$clienteId]);
            $cliente = $stmtCli->fetch();
            if (!$cliente) resp(404, ['error' => 'Cliente no encontrado']);

            $factPicking = (bool)($cliente['factura_picking'] ?? false);

            // ── Cotización vigente ───────────────────────────────────
            $stmtCot = $pdo->prepare("
                SELECT *
                FROM cliente_cotizacion
                WHERE ClienteID = ?
                  AND FechaVigencia <= ?
                ORDER BY FechaVigencia DESC, NroCotizacion DESC
                LIMIT 1
            ");
            $stmtCot->execute([$clienteId, $fechaHasta]);
            $cotizacion = $stmtCot->fetch();

            $precios = [
                'mant_congelado'      => $cotizacion['PrecioMantenimientoCongelado']  ?? null,
                'mant_enfriado'       => $cotizacion['PrecioMantenimientoEnfriado']   ?? null,
                'congelado'           => $cotizacion['PrecioCongelado']               ?? null,
                'mo_cargas'           => $cotizacion['PrecioManoObraCargas']          ?? null,
                'mo_descargas'        => $cotizacion['PrecioManoObraDescargas']       ?? null,
                // Precios picking (nuevas columnas — null si no existe aún la columna)
                'carga_picking'       => $cotizacion['precio_carga_picking']          ?? null,
                'descarga_picking'    => $cotizacion['precio_descarga_picking']       ?? null,
                // Swap intencional 1↔3
                'cong_det1'           => $cotizacion['PrecioCongeladoDetallado3']     ?? null,
                'cong_det2'           => $cotizacion['PrecioCongeladoDetallado2']     ?? null,
                'cong_det3'           => $cotizacion['PrecioCongeladoDetallado1']     ?? null,
                'cong_norm1'          => $cotizacion['PrecioCongeladoNormal2']        ?? null,
                'cong_norm2'          => $cotizacion['PrecioCongeladoNormal1']        ?? null,
            ];

            // ════════════════════════════════════════════════════════
            //  HELPER: clasificar estibas de una entrada/salida
            //  Devuelve ['pallet' => [...estibas], 'picking' => [...estibas]]
            //  Solo se llama cuando factura_picking = 1
            // ════════════════════════════════════════════════════════

            /**
             * Para ENTRADAS: la estiba se vincula a CargaID.
             * ProductoCargaEstiba tiene CargaID + ProductosID + EstibaID + Cantidad.
             * Comparamos Cantidad de la estiba con cajas_por_pallet del producto.
             */
            $clasificarEstibasEntrada = function(int $cargaId) use ($pdo): array {
                $stmt = $pdo->prepare("
                    SELECT
                        PCE.EstibaID,
                        PCE.Cantidad          AS bultos_estiba,
                        P.cajas_por_pallet    AS cajas_por_pallet,
                        PC.KilosUnidad        AS kilos_carga,
                        PC.Cantidad           AS bultos_carga
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
                    $cpp   = (int)$f['cajas_por_pallet'];
                    $bEst  = (int)$f['bultos_estiba'];
                    // Kilos proporcionales de esta estiba
                    $kEst  = $f['bultos_carga'] > 0
                             ? ($f['kilos_carga'] / $f['bultos_carga']) * $bEst
                             : 0;

                    if ($cpp > 0 && $bEst >= $cpp) {
                        // Pallet completo
                        $pallet['estibas']++;
                        $pallet['bultos'] += $bEst;
                        $pallet['kilos']  += $kEst;
                    } else {
                        // Picking
                        $picking['estibas']++;
                        $picking['bultos'] += $bEst;
                        $picking['kilos']  += $kEst;
                    }
                }
                return ['pallet' => $pallet, 'picking' => $picking];
            };

            /**
             * Para SALIDAS: la estiba se vincula via baja_salida_numero = PreSalidaID.
             * ProductoCargaSalida tiene PreSalidaID + EstibaID + CargaID + ProductosID + Cantidad.
             */
            $clasificarEstibasSalida = function(int $preSalidaId) use ($pdo): array {
                // NOTA: usamos PCE.Cantidad (cantidad ORIGINAL de la estiba al ingresar)
                // para clasificar pallet vs picking, no PCS.Cantidad (bultos retirados).
                // La regla es: si la estiba entró con la cantidad completa del pallet → tarifa normal.
                // PCS.Cantidad se usa solo para calcular kilos proporcionales a lo retirado.
                $stmt = $pdo->prepare("
                    SELECT
                        PCS.EstibaID,
                        PCE.Cantidad          AS bultos_estiba,
                        PCS.Cantidad          AS bultos_retirados,
                        P.cajas_por_pallet    AS cajas_por_pallet,
                        PC.KilosUnidad        AS kilos_carga,
                        PC.Cantidad           AS bultos_carga
                    FROM ProductoCargaSalida PCS
                    INNER JOIN ProductoCargaEstiba PCE
                           ON PCE.EstibaID   = PCS.EstibaID
                          AND PCE.CargaID    = PCS.CargaID
                          AND PCE.ProductosID = PCS.ProductosID
                    INNER JOIN ProductosCarga PC
                           ON PC.CargaID     = PCS.CargaID
                          AND PC.ProductosID  = PCS.ProductosID
                    INNER JOIN Productos P ON P.ProductosID = PCS.ProductosID
                    WHERE PCS.PreSalidaID = ?
                ");
                $stmt->execute([$preSalidaId]);
                $filas = $stmt->fetchAll();

                $pallet  = ['estibas' => 0, 'bultos' => 0, 'kilos' => 0.0];
                $picking = ['estibas' => 0, 'bultos' => 0, 'kilos' => 0.0];

                foreach ($filas as $f) {
                    $cpp      = (int)$f['cajas_por_pallet'];
                    $bEst     = (int)$f['bultos_estiba'];     // cantidad ORIGINAL al ingreso (para clasificar)
                    $bRet     = (int)$f['bultos_retirados'];  // cantidad retirada (para kilos y bultos)
                    // Kilos proporcionales a lo retirado en esta salida
                    $kEst = $f['bultos_carga'] > 0
                            ? ($f['kilos_carga'] / $f['bultos_carga']) * $bRet
                            : 0;

                    if ($cpp > 0 && $bEst >= $cpp) {
                        // La estiba originalmente era pallet completo → tarifa normal
                        $pallet['estibas']++;
                        $pallet['bultos'] += $bRet;
                        $pallet['kilos']  += $kEst;
                    } else {
                        // La estiba entró con menos bultos que el pallet → tarifa picking
                        $picking['estibas']++;
                        $picking['bultos'] += $bRet;
                        $picking['kilos']  += $kEst;
                    }
                }
                return ['pallet' => $pallet, 'picking' => $picking];
            };

            // ── 1. ENTRADAS (descargas) ──────────────────────────────
            $stmtEnt = $pdo->prepare("
                SELECT PC.CargaID,
                       C.NroRemito,
                       tc.fecha_movimiento,
                       SUM(PC.Cantidad)    AS Bultos,
                       SUM(PC.KilosUnidad) AS Kilos,
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
            $entradasRaw = $stmtEnt->fetchAll();

            // Si factura_picking: enriquecer cada entrada con datos pallet/picking
            $entradas = [];
            foreach ($entradasRaw as $ent) {
                $row = $ent;
                if ($factPicking) {
                    $row['picking_detalle'] = $clasificarEstibasEntrada((int)$ent['CargaID']);
                }
                $entradas[] = $row;
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
                          INNER JOIN Cargas C2 ON C2.CargaID  = PCS2.CargaID
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
            $salidasRaw = $stmtSal->fetchAll();

            $salidas = [];
            foreach ($salidasRaw as $sal) {
                $row = $sal;
                if ($factPicking) {
                    $row['picking_detalle'] = $clasificarEstibasSalida((int)$sal['PreSalidaID']);
                }
                $salidas[] = $row;
            }

            // ── 3. CONGELADO por temperatura ─────────────────────────
            $tipoMedicion = $cliente['TipoMedicionTemperatura'] ?? '';

            $qCongBase = "SELECT C.CargaID,
                SUM(PC.Cantidad) AS Cantidad,
                SUM(PC.KilosUnidad) AS Kilos,
                (SELECT COUNT(DISTINCT EP.EstibaID)
                   FROM Estiba EP WHERE EP.CargaID = C.CargaID) AS Posiciones,
                tc.fecha_movimiento,
                C.NroRemito,
                AVG((SELECT AVG(Temperatura)
                      FROM CargasControlTempMedicion CCTM
                     WHERE CCTM.ControlTemperaturaID = CCT.ControlTemperaturaID
                    )) AS Temperatura,
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
                $stmtC1 = $pdo->query($qCongBase . " GROUP BY C.CargaID HAVING Temperatura BETWEEN -12 AND -4 ORDER BY C.CargaID");
                $stmtC2 = $pdo->query($qCongBase . " GROUP BY C.CargaID HAVING Temperatura > -4 ORDER BY C.CargaID");
                $congelado = [
                    'tipo'    => 'simple',
                    'normal1' => $stmtC1->fetchAll(),
                    'normal2' => $stmtC2->fetchAll(),
                ];
            } else {
                $stmtD1 = $pdo->query($qCongBase . " GROUP BY C.CargaID HAVING Temperatura BETWEEN -15 AND -10 ORDER BY C.CargaID");
                $stmtD2 = $pdo->query($qCongBase . " GROUP BY C.CargaID HAVING Temperatura BETWEEN -9.9999999999999 AND -5 ORDER BY C.CargaID");
                $stmtD3 = $pdo->query($qCongBase . " GROUP BY C.CargaID HAVING Temperatura > -5 ORDER BY C.CargaID");
                $congelado = [
                    'tipo'     => 'detallado',
                    'detalle1' => $stmtD1->fetchAll(),
                    'detalle2' => $stmtD2->fetchAll(),
                    'detalle3' => $stmtD3->fetchAll(),
                ];
            }

            // ── 4. MANTENIMIENTO — Ingresos ──────────────────────────
            $stmtMantIn = $pdo->prepare("
                SELECT C.CargaID,
                       C.NroRemito,
                       tc.fecha_movimiento,
                       SUM(PC.Cantidad)    AS Cantidad,
                       SUM(PC.KilosUnidad) AS Kilos,
                       (SELECT COUNT(DISTINCT E.EstibaID)
                          FROM Estiba E
                         WHERE E.CargaID = C.CargaID) AS Posiciones,
                       DATEDIFF(?, tc.fecha_movimiento) AS Dias,
                       PC.Congelado,
                       C.numero_referencia
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
                SELECT S.PreSalidaID,
                       S.Remito,
                       tc.fecha_movimiento,
                       SUM(PCS.Cantidad) AS Cantidad,
                       SUM(PC.KilosUnidad / PC.Cantidad * PCS.Cantidad) AS Kilos,
                       (SELECT COUNT(DISTINCT E2.EstibaID)
                          FROM Estiba E2
                          INNER JOIN Cargas C2 ON C2.CargaID = E2.CargaID
                         WHERE C2.ClienteID = ?
                           AND E2.baja_salida_numero = S.PreSalidaID) AS Posiciones,
                       DATEDIFF(?, tc.fecha_movimiento) AS Dias,
                       PC.Congelado,
                       S.numero_referencia
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

            // ── 6. STOCK PREVIO ──────────────────────────────────────
            // Reglas de intervenidas en facturación:
            //   • Completa (estiba original quedó vacía) → cuenta posición + bultos + kilos
            //   • Parcial  (estiba original sigue con bultos) → solo bultos + kilos (la posición ya la aporta la original)
            $sqlPosiciones = function($congelado) use ($pdo, $clienteId, $fechaDesde) {
                $stmt = $pdo->prepare("
                    SELECT SUM(posiciones) AS posiciones FROM (
                      /* Estibas normales del cliente (incluye las originales intervenidas completas que quedan con Cantidad=0 en PCE — ya no se cuentan porque el JOIN con PCE requiere existencia de registros, y al vaciarse no aportan posición: la posición pasa a la intervenida, abajo) */
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
                        AND PCE.Cantidad > 0           -- sólo estibas con contenido = excluye las vaciadas por intervención completa
                        AND (
                            (TCout.fecha_movimiento IS NULL AND E.FechaBaja IS NULL)
                            OR TCout.fecha_movimiento >= ?
                        )
                      UNION ALL
                      /* Estibas INTERVENIDAS COMPLETAS (estiba original quedó con SUM(PCE.Cantidad)=0 → cuenta posición) */
                      SELECT COUNT(DISTINCT EI.EstibaID) AS posiciones
                      FROM Estiba_intervenidas EI
                      INNER JOIN Cargas C ON C.CargaID = EI.CargaID
                      INNER JOIN intervencion_estiba_producto IEP
                             ON IEP.estiba_id = EI.EstibaOriginalID
                            AND IEP.carga_id  = EI.CargaID
                            AND IEP.estado    = 'I'
                      INNER JOIN ProductosCarga PC ON PC.CargaID = IEP.carga_id AND PC.ProductosID = IEP.producto_id
                      INNER JOIN transporte_carga TCin ON C.carga_numero = TCin.carga_numero
                      WHERE C.ClienteID = ?
                        AND PC.Congelado = ?
                        AND C.Clasificada = 1
                        AND EI.FechaBaja IS NULL
                        AND TCin.fecha_movimiento < ?
                        AND COALESCE((SELECT SUM(PCE2.Cantidad) FROM ProductoCargaEstiba PCE2
                                      WHERE PCE2.CargaID = EI.CargaID AND PCE2.EstibaID = EI.EstibaOriginalID), 0) = 0
                    ) pos
                ");
                $stmt->execute([$clienteId, $congelado, $fechaDesde, $fechaDesde,
                                $clienteId, $congelado, $fechaDesde]);
                return (int)$stmt->fetchColumn();
            };

            $sqlStock = function($congelado) use ($pdo, $clienteId, $fechaDesde) {
                // NOTA: se agrega una 3ª rama UNION ALL que suma bultos y kilos de intervenciones activas
                // (completas y parciales). Para kilos se usa el ratio KilosUnidad/Cantidad de ProductosCarga.
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
                        UNION ALL
                        SELECT SUM(PCS.Cantidad) AS Cantidad,
                               SUM(PC.KilosUnidad / PC.Cantidad * PCS.Cantidad) AS Kilos
                        FROM ProductoCargaSalida PCS
                        INNER JOIN PreSalidas S ON S.PreSalidaID = PCS.PreSalidaID
                        INNER JOIN Cargas C ON C.CargaID = PCS.CargaID
                        INNER JOIN ProductosCarga PC ON PC.CargaID = PCS.CargaID AND PC.ProductosID = PCS.ProductosID
                        INNER JOIN transporte_carga tc ON tc.carga_numero = S.carga_numero
                        INNER JOIN transporte_carga tc1 ON tc1.carga_numero = C.carga_numero
                        WHERE C.ClienteID = ?
                          AND PC.Congelado = ?
                          AND S.clasificada = 1
                          AND tc.fecha_movimiento >= ?
                          AND tc1.fecha_movimiento < ?
                        UNION ALL
                        /* Estibas intervenidas vigentes (completas + parciales) — solo bultos y kilos */
                        SELECT SUM(IEP.cantidad_intervenido) AS Cantidad,
                               SUM(PC.KilosUnidad / PC.Cantidad * IEP.cantidad_intervenido) AS Kilos
                        FROM intervencion_estiba_producto IEP
                        INNER JOIN ProductosCarga PC ON PC.CargaID = IEP.carga_id AND PC.ProductosID = IEP.producto_id
                        INNER JOIN Cargas C ON C.CargaID = IEP.carga_id
                        INNER JOIN transporte_carga tc ON tc.carga_numero = C.carga_numero
                        WHERE IEP.estado = 'I'
                          AND IEP.cantidad_intervenido > 0
                          AND (IEP.fecha_liberacion IS NULL OR IEP.fecha_liberacion >= ?)
                          AND IEP.fecha_intervencion < ?
                          AND PC.Congelado = ?
                          AND C.ClienteID = ?
                          AND C.Clasificada = 1
                    ) productos
                ");
                $stmt->execute([
                    $congelado, $fechaDesde, $clienteId,
                    $clienteId, $congelado, $fechaDesde, $fechaDesde,
                    $fechaDesde, $fechaDesde, $congelado, $clienteId,
                ]);
                return $stmt->fetch();
            };

            $stockPrevioCongelado = $sqlStock(1);
            $stockPrevioEnfriado  = $sqlStock(0);
            $posPrevioCongelado   = $sqlPosiciones(1);
            $posPrevioEnfriado    = $sqlPosiciones(0);

            // ── Respuesta ────────────────────────────────────────────
            resp(200, [
                'cliente'         => $cliente,
                'precios'         => $precios,
                'dias'            => $dias,
                'fecha_desde'     => $fechaDesde,
                'fecha_hasta'     => $fechaHasta,
                'fact_picking'    => $factPicking,   // ← nuevo flag para el frontend

                'cant_entradas'   => $entradas,      // ahora incluye picking_detalle si aplica
                'cant_salidas'    => $salidas,        // idem

                'congelado'       => $congelado,

                'mant_ingresos'   => $mantIngresos,
                'mant_egresos'    => $mantEgresos,

                'stock_previo' => [
                    'congelado' => [
                        'cantidad'   => $stockPrevioCongelado['Cantidad']  ?? null,
                        'kilos'      => $stockPrevioCongelado['Kilos']     ?? null,
                        'posiciones' => $posPrevioCongelado,
                    ],
                    'enfriado' => [
                        'cantidad'   => $stockPrevioEnfriado['Cantidad']   ?? null,
                        'kilos'      => $stockPrevioEnfriado['Kilos']      ?? null,
                        'posiciones' => $posPrevioEnfriado,
                    ],
                ],
            ]);

        } catch (Exception $e) {
            resp(500, ['error' => 'Error al generar resumen: ' . $e->getMessage()]);
        }
        break;

// ── FIN ENDPOINT facturacion_resumen ─────────────────────────────────────────
