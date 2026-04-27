<?php
/**
 * ============================================================
 *  facturacion_excel.php
 *  Genera el Excel de facturación para un cliente y período.
 *  Subir a: /public_html/facturacion_excel.php
 *
 *  GET /facturacion_excel.php
 *       ?cliente_id=123
 *       &desde=2026-01-01
 *       &hasta=2026-01-31
 * ============================================================
 */

session_start();
if (empty($_SESSION['cina_usuario_id'])) {
    http_response_code(401);
    exit('No autorizado');
}

// ── PhpSpreadsheet ───────────────────────────────────────────
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// ── Config / DB ───────────────────────────────────────────────
require_once dirname(__DIR__) . '/config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_NUM]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('Error de conexión: ' . $e->getMessage());
}

// ── Parámetros ────────────────────────────────────────────────
$clienteId  = (int)($_GET['cliente_id'] ?? 0);
$fechaDesde = $_GET['desde'] ?? date('Y-m-01');
$fechaHasta = $_GET['hasta'] ?? date('Y-m-d');

if (!$clienteId) { http_response_code(400); exit('Falta cliente_id'); }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDesde)) $fechaDesde = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHasta)) $fechaHasta = date('Y-m-d');

$ts_desde = strtotime($fechaDesde);
$ts_hasta = strtotime($fechaHasta);
$dias = (int)(($ts_hasta - $ts_desde) / 86400) + 1;

// Fechas para mostrar en el Excel
$fechadesde = date('d/m/Y', $ts_desde);
$fechahasta = date('d/m/Y', $ts_hasta);

// ── Helper fecha ──────────────────────────────────────────────
function ymdToDmy($val) {
    if (!$val) return '';
    $d = explode(' ', $val)[0];
    $p = explode('-', $d);
    if (count($p) !== 3) return $val;
    return $p[2] . '/' . $p[1] . '/' . $p[0];
}

// ── Datos del cliente ─────────────────────────────────────────
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$stmtCli = $pdo->prepare("
    SELECT ClienteID, ClienteNombre, TipoMedicionTemperatura, factura_picking, factura_por
    FROM CLIENTES WHERE ClienteID = ?
");
$stmtCli->execute([$clienteId]);
$cliente = $stmtCli->fetch();
if (!$cliente) { http_response_code(404); exit('Cliente no encontrado'); }

$tipoMedicion = (!empty($cliente['TipoMedicionTemperatura'])) ? $cliente['TipoMedicionTemperatura'] : 'S';

// factura_por: P=Posición | B=Bultos | K=Kilos
// Fuente 1: columna en BD (requiere ALTER TABLE migracion_factura_por.sql)
// Fuente 2: GET param ?fp=K como override manual temporal
$_fpBD  = $cliente['factura_por'] ?? null;
$_fpGet = $_GET['fp'] ?? null;
$_fpVal = (!empty($_fpGet) && in_array($_fpGet, ['P','B','K'])) ? $_fpGet : $_fpBD;
$facturaPor = (in_array($_fpVal, ['P','B','K'])) ? $_fpVal : 'P';

// ── Cotización vigente ────────────────────────────────────────
$stmtCot = $pdo->prepare("
    SELECT * FROM cliente_cotizacion
    WHERE cliente_id = ? AND fecha_vigencia <= ?
    ORDER BY fecha_vigencia DESC, nro_cotizacion DESC
    LIMIT 1
");
$stmtCot->execute([$clienteId, $fechaHasta]);
$cot = $stmtCot->fetch() ?: [];

$preciomantenimientocongelado = (float)($cot['precio_mantenimiento_congelado'] ?? 0);
$preciomantenimientoenfriado  = (float)($cot['precio_mantenimiento_enfriado']  ?? 0);
$preciocongelado              = (float)($cot['precio_congelado']               ?? 0);
$preciomanodeobracargas       = (float)($cot['precio_mano_obra_cargas']        ?? 0);
$preciomanodeobradescargas    = (float)($cot['precio_mano_obra_descargas']     ?? 0);
// Precios picking
$preciocargapicking           = (float)($cot['precio_carga_picking']           ?? 0);
$preciodescargapicking        = (float)($cot['precio_descarga_picking']        ?? 0);
// Swap 1↔3 (igual que actions_class.php)
$preciocongeladodetallado1    = (float)($cot['precio_congelado_detallado_3']    ?? 0);
$preciocongeladodetallado2    = (float)($cot['precio_congelado_detallado_2']    ?? 0);
$preciocongeladodetallado3    = (float)($cot['precio_congelado_detallado_1']    ?? 0);
$preciocongeladonormal1       = (float)($cot['precio_congelado_normal_2']       ?? 0);
$preciocongeladonormal2       = (float)($cot['precio_congelado_normal_1']       ?? 0);

$factPicking = (bool)($cliente['factura_picking'] ?? false);

// numero_referencia_texto
$stmtNR = $pdo->prepare("SELECT NumeroReferenciaTexto FROM CLIENTES WHERE ClienteID = ?");
try { $stmtNR->execute([$clienteId]); $nr = $stmtNR->fetchColumn(); } catch(Exception $e) { $nr = ''; }
$numero_referencia_texto = $nr ?: 'Ref.';

// ── Helpers picking: clasificar estibas por CargaID / PreSalidaID ──
// cajas_por_pallet = 0 → no clasifica, precio $0
// Devuelve ['pallet' => [bultos,kilos,estibas], 'picking' => [bultos,kilos,estibas]]
$clasificarEstibasEntrada = function(int $cargaId) use ($pdo): array {
    $stmt = $pdo->prepare("
        SELECT PCE.Cantidad AS bultos_estiba, P.cajas_por_pallet,
               PC.KilosUnidad AS kilos_carga, PC.Cantidad AS bultos_carga
        FROM ProductoCargaEstiba PCE
        INNER JOIN ProductosCarga PC ON PC.CargaID=PCE.CargaID AND PC.ProductosID=PCE.ProductosID
        INNER JOIN Productos P ON P.ProductosID=PCE.ProductosID
        WHERE PCE.CargaID = ?
    ");
    $stmt->execute([$cargaId]);
    $pallet  = [0, 0.0, 0];  // [bultos, kilos, estibas]
    $picking = [0, 0.0, 0];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $cpp  = (int)$f['cajas_por_pallet'];
        $bEst = (int)$f['bultos_estiba'];
        $kEst = $f['bultos_carga'] > 0 ? ($f['kilos_carga'] / $f['bultos_carga']) * $bEst : 0;
        if ($cpp === 0) continue;
        if ($bEst >= $cpp) { $pallet[0]+=$bEst; $pallet[1]+=$kEst; $pallet[2]++; }
        else               { $picking[0]+=$bEst; $picking[1]+=$kEst; $picking[2]++; }
    }
    return ['pallet' => $pallet, 'picking' => $picking];
};

$clasificarEstibasSalida = function(int $preSalidaId) use ($pdo): array {
    $stmt = $pdo->prepare("
        SELECT PCS.Cantidad AS bultos_estiba, P.cajas_por_pallet,
               PC.KilosUnidad AS kilos_carga, PC.Cantidad AS bultos_carga
        FROM ProductoCargaSalida PCS
        INNER JOIN ProductosCarga PC ON PC.CargaID=PCS.CargaID AND PC.ProductosID=PCS.ProductosID
        INNER JOIN Productos P ON P.ProductosID=PCS.ProductosID
        WHERE PCS.PreSalidaID = ?
    ");
    $stmt->execute([$preSalidaId]);
    $pallet  = [0, 0.0, 0];
    $picking = [0, 0.0, 0];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $cpp  = (int)$f['cajas_por_pallet'];
        $bEst = (int)$f['bultos_estiba'];
        $kEst = $f['bultos_carga'] > 0 ? ($f['kilos_carga'] / $f['bultos_carga']) * $bEst : 0;
        if ($cpp === 0) continue;
        if ($bEst >= $cpp) { $pallet[0]+=$bEst; $pallet[1]+=$kEst; $pallet[2]++; }
        else               { $picking[0]+=$bEst; $picking[1]+=$kEst; $picking[2]++; }
    }
    return ['pallet' => $pallet, 'picking' => $picking];
};

// ── Queries (FETCH_NUM para compatibilidad con facturarSuccess) ─
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_NUM);

// ENTRADAS
$pdo->prepare("SET @cli = $clienteId")->execute();
$stmtEnt = $pdo->prepare("
    SELECT PC.CargaID, C.NroRemito, tc.fecha_movimiento,
           SUM(Cantidad) AS Bultos, SUM(KilosUnidad) AS Kilos,
           (SELECT COUNT(DISTINCT PCE.EstibaID) FROM ProductoCargaEstiba PCE WHERE PCE.CargaID = PC.CargaID) AS Posiciones,
           C.numero_referencia
    FROM Cargas C
    INNER JOIN ProductosCarga PC ON C.CargaID = PC.CargaID
    INNER JOIN transporte_carga tc ON tc.carga_numero = C.carga_numero
    WHERE tc.fecha_movimiento >= ? AND tc.fecha_movimiento <= CONCAT(?, ' 23:59')
      AND C.ClienteID = ? AND C.Clasificada = 1
    GROUP BY PC.CargaID ORDER BY PC.CargaID
");
$stmtEnt->execute([$fechaDesde, $fechaHasta, $clienteId]);
$cant_entradas = $stmtEnt->fetchAll();

// SALIDAS
$stmtSal = $pdo->prepare("
    SELECT PCS.PreSalidaID, C.Remito, C.numero_referencia, tc.fecha_movimiento,
           SUM(PCS.Cantidad) AS Bultos,
           SUM(PCS.Cantidad * (SELECT KilosUnidad/Cantidad FROM ProductosCarga WHERE CargaID=PCS.CargaID AND ProductosID=PCS.ProductosID)) AS Kilos,
           (SELECT COUNT(DISTINCT E2.EstibaID) FROM ProductoCargaSalida PCS2
            INNER JOIN Estiba E2 ON E2.EstibaID=PCS2.EstibaID
            INNER JOIN Cargas C2 ON C2.CargaID=PCS2.CargaID
            WHERE PCS2.PreSalidaID=PCS.PreSalidaID AND E2.baja_salida_numero=PCS.PreSalidaID AND C2.ClienteID=?) AS Posiciones
    FROM PreSalidas C
    INNER JOIN ProductoCargaSalida PCS ON C.PreSalidaID=PCS.PreSalidaID
    INNER JOIN Cargas Ca ON Ca.CargaID=PCS.CargaID
    INNER JOIN transporte_carga tc ON tc.carga_numero=C.carga_numero
    WHERE tc.fecha_movimiento >= ? AND tc.fecha_movimiento <= CONCAT(?, ' 23:59')
      AND Ca.ClienteID = ? AND C.clasificada = 1
    GROUP BY PCS.PreSalidaID ORDER BY PCS.PreSalidaID
");
$stmtSal->execute([$clienteId, $fechaDesde, $fechaHasta, $clienteId]);
$cant_salidas = $stmtSal->fetchAll();

// CONGELADO base query
$qBase = "SELECT C.CargaID, SUM(PC.Cantidad), SUM(PC.KilosUnidad),
           (SELECT COUNT(DISTINCT EP.EstibaID) FROM Estiba EP WHERE EP.CargaID=C.CargaID) AS Posiciones,
           tc.fecha_movimiento, C.NroRemito,
           (SELECT AVG(Temperatura) FROM CargasControlTempMedicion CCTM WHERE CCTM.ControlTemperaturaID=CCT.ControlTemperaturaID) AS Temperatura,
           C.numero_referencia
          FROM Cargas C
          INNER JOIN ProductosCarga PC ON C.CargaID=PC.CargaID
          INNER JOIN CargasControlTemp CCT ON CCT.EntradaID=C.CargaID
          INNER JOIN Productos ON PC.ProductosID=Productos.ProductosID
          INNER JOIN transporte_carga tc ON tc.carga_numero=C.carga_numero
          WHERE tc.fecha_movimiento >= '$fechaDesde'
            AND PC.Congelado=1
            AND tc.fecha_movimiento <= '$fechaHasta 23:59'
            AND C.ClienteID=$clienteId
            AND CCT.NroControl=1";

if ($tipoMedicion == 'S') {
    $rsCongeladoNormal2 = $pdo->query($qBase . " GROUP BY C.CargaID HAVING Temperatura > -4 ORDER BY C.CargaID")->fetchAll();
    $rsCongeladoNormal1 = $pdo->query($qBase . " GROUP BY C.CargaID HAVING Temperatura BETWEEN -12 AND -4 ORDER BY C.CargaID")->fetchAll();
    $rsCongeladoDetalle1 = $rsCongeladoDetalle2 = $rsCongeladoDetalle3 = [];
} else {
    $rsCongeladoDetalle3 = $pdo->query($qBase . " GROUP BY C.CargaID HAVING Temperatura > -5 ORDER BY C.CargaID")->fetchAll();
    $rsCongeladoDetalle2 = $pdo->query($qBase . " GROUP BY C.CargaID HAVING Temperatura BETWEEN -9.9999999999999 AND -5 ORDER BY C.CargaID")->fetchAll();
    $rsCongeladoDetalle1 = $pdo->query($qBase . " GROUP BY C.CargaID HAVING Temperatura BETWEEN -15 AND -10 ORDER BY C.CargaID")->fetchAll();
    $rsCongeladoNormal1 = $rsCongeladoNormal2 = [];
}

// MANTENIMIENTO INGRESOS
$stmtMIn = $pdo->prepare("
    SELECT C.CargaID, C.NroRemito, tc.fecha_movimiento,
           SUM(PC.Cantidad), SUM(PC.KilosUnidad),
           (SELECT COUNT(DISTINCT E.EstibaID) FROM Estiba E WHERE E.CargaID=C.CargaID) AS Posiciones,
           DATEDIFF(?, tc.fecha_movimiento) AS Dias,
           PC.Congelado, C.numero_referencia
    FROM ProductosCarga PC
    INNER JOIN Cargas C ON C.CargaID=PC.CargaID
    INNER JOIN Productos P ON P.ProductosID=PC.ProductosID
    INNER JOIN transporte_carga tc ON tc.carga_numero=C.carga_numero
    WHERE tc.fecha_movimiento <= CONCAT(?, ' 23:59') AND tc.fecha_movimiento >= ?
      AND C.ClienteID=? AND C.Clasificada=1
    GROUP BY C.CargaID, PC.Congelado ORDER BY C.CargaID
");
$stmtMIn->execute([$fechaHasta, $fechaHasta, $fechaDesde, $clienteId]);
$rsMantenimientoCongeladoIngresos = $stmtMIn->fetchAll();

// MANTENIMIENTO EGRESOS
$stmtMEg = $pdo->prepare("
    SELECT S.PreSalidaID, S.Remito, tc.fecha_movimiento,
           SUM(PCS.Cantidad),
           SUM(PC.KilosUnidad/PC.Cantidad*PCS.Cantidad),
           (SELECT COUNT(DISTINCT E2.EstibaID) FROM Estiba E2 INNER JOIN Cargas C2 ON C2.CargaID=E2.CargaID
            WHERE C2.ClienteID=? AND E2.baja_salida_numero=S.PreSalidaID) AS Posiciones,
           DATEDIFF(?, tc.fecha_movimiento) AS Dias,
           PC.Congelado, S.numero_referencia
    FROM ProductoCargaSalida PCS
    INNER JOIN ProductosCarga PC ON PC.CargaID=PCS.CargaID AND PC.ProductosID=PCS.ProductosID
    INNER JOIN PreSalidas S ON S.PreSalidaID=PCS.PreSalidaID
    INNER JOIN Productos P ON P.ProductosID=PCS.ProductosID
    INNER JOIN Cargas C ON C.CargaID=PCS.CargaID
    INNER JOIN Estiba E ON E.EstibaID=PCS.EstibaID
    INNER JOIN transporte_carga tc ON tc.carga_numero=S.carga_numero
    WHERE tc.fecha_movimiento <= CONCAT(?, ' 23:59') AND tc.fecha_movimiento >= ?
      AND C.ClienteID=? AND C.Clasificada=1 AND S.clasificada=1
    GROUP BY S.PreSalidaID, PC.Congelado ORDER BY S.PreSalidaID
");
$stmtMEg->execute([$clienteId, $fechaHasta, $fechaHasta, $fechaDesde, $clienteId]);
$rsMantenimientoCongeladoEgresos = $stmtMEg->fetchAll();

// STOCK PREVIO
$fnStock = function($congelado) use ($pdo, $clienteId, $fechaDesde) {
    $stmt = $pdo->prepare("
        SELECT SUM(p.cant) AS Cantidad, SUM(p.kilos) AS Kilos FROM (
            SELECT SUM(PCE.Cantidad) AS cant, SUM(PC.KilosUnidad/PC.Cantidad*PCE.Cantidad) AS kilos
            FROM ProductoCargaEstiba PCE
            INNER JOIN ProductosCarga PC ON PC.CargaID=PCE.CargaID AND PC.ProductosID=PCE.ProductosID
            INNER JOIN Cargas C ON C.CargaID=PC.CargaID
            INNER JOIN transporte_carga tc ON tc.carga_numero=C.carga_numero
            WHERE PCE.Cantidad>0 AND PC.Congelado=? AND tc.fecha_movimiento<? AND C.ClienteID=? AND C.Clasificada=1
            UNION ALL
            SELECT SUM(PCS.Cantidad) AS cant, SUM(PC.KilosUnidad/PC.Cantidad*PCS.Cantidad) AS kilos
            FROM ProductoCargaSalida PCS
            INNER JOIN PreSalidas S ON S.PreSalidaID=PCS.PreSalidaID
            INNER JOIN Cargas C ON C.CargaID=PCS.CargaID
            INNER JOIN ProductosCarga PC ON PC.CargaID=PCS.CargaID AND PC.ProductosID=PCS.ProductosID
            INNER JOIN transporte_carga tc ON tc.carga_numero=S.carga_numero
            INNER JOIN transporte_carga tc1 ON tc1.carga_numero=C.carga_numero
            WHERE C.ClienteID=? AND PC.Congelado=? AND S.clasificada=1 AND tc.fecha_movimiento>=? AND tc1.fecha_movimiento<?
        ) p
    ");
    $stmt->execute([$congelado, $fechaDesde, $clienteId, $clienteId, $congelado, $fechaDesde, $fechaDesde]);
    return $stmt->fetch(PDO::FETCH_NUM) ?: [null, null];
};
$fnPosiciones = function($congelado) use ($pdo, $clienteId, $fechaDesde) {
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT E.EstibaID)
        FROM Estiba E
        INNER JOIN Cargas C ON C.CargaID=E.CargaID
        INNER JOIN ProductosCarga PC ON C.CargaID=PC.CargaID
        INNER JOIN transporte_carga TCin ON C.carga_numero=TCin.carga_numero
        INNER JOIN ProductoCargaEstiba PCE ON PC.CargaID=PCE.CargaID AND PC.ProductosID=PCE.ProductosID AND PCE.EstibaID=E.EstibaID
        LEFT JOIN PreSalidas S ON S.PreSalidaID=E.baja_salida_numero
        LEFT JOIN transporte_carga TCout ON S.carga_numero=TCout.carga_numero
        WHERE C.ClienteID=? AND PC.Congelado=? AND C.Clasificada=1
          AND (S.clasificada IS NULL OR S.clasificada=1)
          AND TCin.fecha_movimiento < ?
          AND ((TCout.fecha_movimiento IS NULL AND E.FechaBaja IS NULL) OR TCout.fecha_movimiento>=?)
    ");
    $stmt->execute([$clienteId, $congelado, $fechaDesde, $fechaDesde]);
    return (int)$stmt->fetchColumn();
};

$stockCong  = $fnStock(1);
$stockEnfr  = $fnStock(0);
$posCong    = $fnPosiciones(1);
$posEnfr    = $fnPosiciones(0);

// ════════════════════════════════════════════════════════════
//  GENERAR EXCEL — igual estructura que facturarSuccess.php
// ════════════════════════════════════════════════════════════
$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setCreator("CINA")->setTitle("Facturación")
    ->setSubject("Facturación")->setCategory("Facturación");

// ── Estilos ───────────────────────────────────────────────────
$arrStandard = [
    'borders' => ['bottom' => ['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'444444']],
                  'top'    => ['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'444444']],
                  'right'  => ['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'444444']]],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_RIGHT],
    'font'      => ['size'=>10,'name'=>'Arial'],
];
$arrBold = array_merge($arrStandard, ['font'=>['size'=>10,'name'=>'Arial','bold'=>true]]);
$arrTitulos = [
    'fill'      => ['fillType'=>Fill::FILL_SOLID,'color'=>['rgb'=>'cccccc']],
    'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'444444']]],
    'font'      => ['bold'=>true,'name'=>'Arial','size'=>10],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
];
$arrTotal = [
    'fill'    => ['fillType'=>Fill::FILL_SOLID,'color'=>['argb'=>'FFCCFFCC']],
    'borders' => ['bottom'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'444444']],
                  'top'   =>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'444444']],
                  'right' =>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'444444']]],
    'font'    => ['bold'=>true,'name'=>'Arial'],
];
$fmtCurrency = "#,##0.00";
$fmtNumber   = "#,##0.00";

// ════════════════════════════════════════════════════════════
//  HOJA 1: Cargas - Descargas
// ════════════════════════════════════════════════════════════
$spreadsheet->setActiveSheetIndex(0);
$sheet = $spreadsheet->getActiveSheet()->setTitle('Cargas - Descargas');
$sheet = $spreadsheet->getActiveSheet();
$fila = 1;
$sheet->setCellValue('A'.$fila, $cliente['ClienteNombre']);
$sheet->getStyle('A'.$fila)->getFont()->setBold(true)->setSize(16);
$fila += 2;
$sheet->setCellValue('A'.$fila, 'Período: ' . $fechadesde . ' - ' . $fechahasta);
$fila += 2;

// Columna de multiplicación según factura_por:
// P = Posición → columna H | B = Bultos → columna F | K = Kilos → columna G
$colMO = 'H'; // Cargas/Descargas: P=Posiciones(H) | B=Bultos(F) | K=Kilos(G)
if ($facturaPor === 'B') $colMO = 'F';
if ($facturaPor === 'K') $colMO = 'G';

// Congelado: E=Bultos, F=Kilos, G=Posiciones
$colCong = 'G'; // default Posiciones
if ($facturaPor === 'B') $colCong = 'E';
if ($facturaPor === 'K') $colCong = 'F';

// Almacenamiento: E=Bultos, F=Kilos, G=Posiciones (multiplica también por H=Días)
$colAlm = 'G'; // default Posiciones
if ($facturaPor === 'B') $colAlm = 'E';
if ($facturaPor === 'K') $colAlm = 'F';

// DESCARGAS
$sheet->setCellValue('A'.$fila, 'Descargas');
$sheet->getStyle('A'.$fila)->getFont()->setBold(true)->setSize(14);
$fila++;
$sheet->getStyle('A'.$fila.':J'.$fila)->applyFromArray($arrTitulos);
$sheet->setCellValue('A'.$fila, 'Entrada');
$sheet->setCellValue('C'.$fila, 'Remito');
$sheet->setCellValue('D'.$fila, $numero_referencia_texto);
$sheet->setCellValue('E'.$fila, 'Fecha Llegada');
$sheet->setCellValue('F'.$fila, 'Bultos');
$sheet->setCellValue('G'.$fila, 'Kilos');
$sheet->setCellValue('H'.$fila, 'Posiciones');
$sheet->setCellValue('I'.$fila, 'Monto Unitario');
$sheet->setCellValue('J'.$fila, 'Monto Total');
if ($factPicking) $sheet->setCellValue('B'.$fila, 'Tipo');
$filaInicial = $fila + 1;

foreach ($cant_entradas as $row) {
    if (!$factPicking) {
        // Modo normal: una fila
        $fila++;
        $sheet->getStyle('A'.$fila.':J'.$fila)->applyFromArray($arrStandard);
        $sheet->setCellValue('A'.$fila, $row[0]);
        $sheet->setCellValue('C'.$fila, $row[1]);
        $sheet->setCellValue('D'.$fila, $row[6]);
        $sheet->setCellValue('E'.$fila, ymdToDmy($row[2]));
        $sheet->setCellValue('F'.$fila, $row[3]);
        $sheet->setCellValue('G'.$fila, $row[4]);
        $sheet->setCellValue('H'.$fila, $row[5]);
        $sheet->setCellValue('I'.$fila, number_format($preciomanodeobradescargas, 2, '.', ''));
        $sheet->setCellValue('J'.$fila, '=I'.$fila.'*'.$colMO.$fila);
    } else {
        // Modo picking: dos filas (pallet completo + picking)
        $det = $clasificarEstibasEntrada((int)$row[0]);

        // Fila pallet completo (precio normal)
        $fila++;
        $sheet->getStyle('A'.$fila.':J'.$fila)->applyFromArray($arrStandard);
        $sheet->setCellValue('A'.$fila, $row[0]);
        $sheet->setCellValue('B'.$fila, 'Pallet completo');
        $sheet->setCellValue('C'.$fila, $row[1]);
        $sheet->setCellValue('D'.$fila, $row[6]);
        $sheet->setCellValue('E'.$fila, ymdToDmy($row[2]));
        $sheet->setCellValue('F'.$fila, $det['pallet'][0]);
        $sheet->setCellValue('G'.$fila, number_format($det['pallet'][1], 2, '.', ''));
        $sheet->setCellValue('H'.$fila, $det['pallet'][2]);
        $sheet->setCellValue('I'.$fila, number_format($preciomanodeobradescargas, 2, '.', ''));
        $sheet->setCellValue('J'.$fila, '=I'.$fila.'*'.$colMO.$fila);

        // Fila picking (precio picking)
        $fila++;
        $arrPickingStyle = array_merge($arrStandard, ['fill'=>['fillType'=>Fill::FILL_SOLID,'color'=>['rgb'=>'FFF8EE']]]);
        $sheet->getStyle('A'.$fila.':J'.$fila)->applyFromArray($arrPickingStyle);
        $sheet->setCellValue('A'.$fila, $row[0]);
        $sheet->setCellValue('B'.$fila, 'Picking');
        $sheet->setCellValue('C'.$fila, $row[1]);
        $sheet->setCellValue('D'.$fila, $row[6]);
        $sheet->setCellValue('E'.$fila, ymdToDmy($row[2]));
        $sheet->setCellValue('F'.$fila, $det['picking'][0]);
        $sheet->setCellValue('G'.$fila, number_format($det['picking'][1], 2, '.', ''));
        $sheet->setCellValue('H'.$fila, $det['picking'][2]);
        $sheet->setCellValue('I'.$fila, number_format($preciodescargapicking, 2, '.', ''));
        $sheet->setCellValue('J'.$fila, '=I'.$fila.'*'.$colMO.$fila);
    }
}
$fila++;
$sheet->getStyle('E'.$fila.':J'.$fila)->applyFromArray($arrBold);
if ($filaInicial > $fila-1) $filaInicial = $fila-1;
$sheet->setCellValue('E'.$fila, 'SUBTOTAL:');
$sheet->setCellValue('F'.$fila, '=SUM(F'.$filaInicial.':F'.($fila-1).')');
$sheet->setCellValue('G'.$fila, '=SUM(G'.$filaInicial.':G'.($fila-1).')');
$sheet->setCellValue('H'.$fila, '=SUM(H'.$filaInicial.':H'.($fila-1).')');
$sheet->setCellValue('J'.$fila, '=SUM(J'.$filaInicial.':J'.($fila-1).')');
$sheet->getStyle('I7:J'.$fila)->getNumberFormat()->setFormatCode($fmtCurrency);
$sheet->getStyle('G7:G'.$fila)->getNumberFormat()->setFormatCode($fmtNumber);
$filaTotalDescargas = $fila;

// CARGAS
$fila += 2;
$sheet->setCellValue('A'.$fila, 'Cargas');
$sheet->getStyle('A'.$fila)->getFont()->setBold(true)->setSize(14);
$fila++;
$sheet->getStyle('A'.$fila.':J'.$fila)->applyFromArray($arrTitulos);
$sheet->setCellValue('A'.$fila, 'Salida');
$sheet->setCellValue('C'.$fila, 'Remito');
$sheet->setCellValue('D'.$fila, $numero_referencia_texto);
$sheet->setCellValue('E'.$fila, 'Fecha Salida');
$sheet->setCellValue('F'.$fila, 'Bultos');
$sheet->setCellValue('G'.$fila, 'Kilos');
$sheet->setCellValue('H'.$fila, 'Posiciones');
$sheet->setCellValue('I'.$fila, 'Monto Unitario');
$sheet->setCellValue('J'.$fila, 'Monto Total');
if ($factPicking) $sheet->setCellValue('B'.$fila, 'Tipo');
$filaInicial = $fila + 1;
$filaInicialCargas = $filaInicial;

foreach ($cant_salidas as $row) {
    if (!$factPicking) {
        // Modo normal: una fila
        $fila++;
        $sheet->getStyle('A'.$fila.':J'.$fila)->applyFromArray($arrStandard);
        $sheet->setCellValue('A'.$fila, $row[0]);
        $sheet->setCellValue('C'.$fila, $row[1]);
        $sheet->setCellValue('D'.$fila, $row[2]);
        $sheet->setCellValue('E'.$fila, ymdToDmy($row[3]));
        $sheet->setCellValue('F'.$fila, $row[4]);
        $sheet->setCellValue('G'.$fila, $row[5]);
        $sheet->setCellValue('H'.$fila, $row[6]);
        $sheet->setCellValue('I'.$fila, number_format($preciomanodeobracargas, 2, '.', ''));
        $sheet->setCellValue('J'.$fila, '=I'.$fila.'*'.$colMO.$fila);
    } else {
        // Modo picking: dos filas
        $det = $clasificarEstibasSalida((int)$row[0]);

        // Fila pallet completo
        $fila++;
        $sheet->getStyle('A'.$fila.':J'.$fila)->applyFromArray($arrStandard);
        $sheet->setCellValue('A'.$fila, $row[0]);
        $sheet->setCellValue('B'.$fila, 'Pallet completo');
        $sheet->setCellValue('C'.$fila, $row[1]);
        $sheet->setCellValue('D'.$fila, $row[2]);
        $sheet->setCellValue('E'.$fila, ymdToDmy($row[3]));
        $sheet->setCellValue('F'.$fila, $det['pallet'][0]);
        $sheet->setCellValue('G'.$fila, number_format($det['pallet'][1], 2, '.', ''));
        $sheet->setCellValue('H'.$fila, $det['pallet'][2]);
        $sheet->setCellValue('I'.$fila, number_format($preciomanodeobracargas, 2, '.', ''));
        $sheet->setCellValue('J'.$fila, '=I'.$fila.'*'.$colMO.$fila);

        // Fila picking
        $fila++;
        $arrPickingStyle = array_merge($arrStandard, ['fill'=>['fillType'=>Fill::FILL_SOLID,'color'=>['rgb'=>'FFF8EE']]]);
        $sheet->getStyle('A'.$fila.':J'.$fila)->applyFromArray($arrPickingStyle);
        $sheet->setCellValue('A'.$fila, $row[0]);
        $sheet->setCellValue('B'.$fila, 'Picking');
        $sheet->setCellValue('C'.$fila, $row[1]);
        $sheet->setCellValue('D'.$fila, $row[2]);
        $sheet->setCellValue('E'.$fila, ymdToDmy($row[3]));
        $sheet->setCellValue('F'.$fila, $det['picking'][0]);
        $sheet->setCellValue('G'.$fila, number_format($det['picking'][1], 2, '.', ''));
        $sheet->setCellValue('H'.$fila, $det['picking'][2]);
        $sheet->setCellValue('I'.$fila, number_format($preciocargapicking, 2, '.', ''));
        $sheet->setCellValue('J'.$fila, '=I'.$fila.'*'.$colMO.$fila);
    }
}
$fila++;
$sheet->getStyle('E'.$fila.':J'.$fila)->applyFromArray($arrBold);
if ($filaInicial > $fila-1) $filaInicial = $fila-1;
$sheet->setCellValue('E'.$fila, 'SUBTOTAL:');
$sheet->setCellValue('F'.$fila, '=SUM(F'.$filaInicial.':F'.($fila-1).')');
$sheet->setCellValue('G'.$fila, '=SUM(G'.$filaInicial.':G'.($fila-1).')');
$sheet->setCellValue('H'.$fila, '=SUM(H'.$filaInicial.':H'.($fila-1).')');
$sheet->setCellValue('J'.$fila, '=SUM(J'.$filaInicial.':J'.($fila-1).')');
$fila += 2;
$sheet->getStyle('E'.$fila.':J'.$fila)->applyFromArray($arrBold);
$sheet->setCellValue('E'.$fila, 'TOTAL:');
$sheet->setCellValue('F'.$fila, '=F'.($filaInicialCargas-4).'+F'.($fila-2));
$sheet->setCellValue('G'.$fila, '=G'.($filaInicialCargas-4).'+G'.($fila-2));
$sheet->setCellValue('H'.$fila, '=H'.($filaInicialCargas-4).'+H'.($fila-2));
$sheet->setCellValue('J'.$fila, '=J'.($filaInicialCargas-4).'+J'.($fila-2));
$filaTotalCargas = $fila;
$sheet->getStyle('I'.($filaTotalDescargas+1).':J'.$fila)->getNumberFormat()->setFormatCode($fmtCurrency);
foreach (['C'=>15,'D'=>18,'E'=>15,'F'=>15,'G'=>15,'H'=>15,'I'=>15,'J'=>15,'L'=>200] as $col=>$w)
    $sheet->getColumnDimension($col)->setWidth($w);

// ════════════════════════════════════════════════════════════
//  HOJA 2: Congelado
// ════════════════════════════════════════════════════════════
$spreadsheet->createSheet(); $spreadsheet->setActiveSheetIndex(1);
$sheet = $spreadsheet->getActiveSheet()->setTitle('Congelado');
$sheet = $spreadsheet->getActiveSheet();
$fila = 1;
$sheet->setCellValue('A'.$fila, $cliente['ClienteNombre']);
$sheet->getStyle('A'.$fila)->getFont()->setBold(true)->setSize(16);
$fila += 2;
$sheet->setCellValue('A'.$fila, 'Período: ' . $fechadesde . ' - ' . $fechahasta);
$fila++;

$thCong = ['A'=>'Entrada','B'=>'Remito','C'=>$numero_referencia_texto,'D'=>'Fecha Llegada',
           'E'=>'Bultos','F'=>'Kilos','G'=>'Posiciones','H'=>'Temperatura','I'=>'Monto Unitario','J'=>'Monto Total'];

$writeCongSection = function($titulo, $rows, $precioExpr) use ($sheet, &$fila, $arrTitulos, $arrStandard, $arrBold, $thCong, $fmtCurrency, $fmtNumber, $colCong) {
    $fila += 2;
    $sheet->setCellValue('A'.$fila, $titulo);
    $sheet->getStyle('A'.$fila)->getFont()->setBold(true)->setSize(14);
    $fila++;
    $sheet->getStyle('A'.$fila.':J'.$fila)->applyFromArray($arrTitulos);
    foreach ($thCong as $col=>$val) $sheet->setCellValue($col.$fila, $val);
    $filaInicial = $fila+1;
    foreach ($rows as $row) {
        $fila++;
        $sheet->getStyle('A'.$fila.':J'.$fila)->applyFromArray($arrStandard);
        $sheet->setCellValue('A'.$fila, $row[0]);
        $sheet->setCellValue('B'.$fila, $row[5] ?? '');
        $sheet->setCellValue('C'.$fila, $row[7] ?? '');
        $sheet->setCellValue('D'.$fila, ymdToDmy($row[4] ?? ''));
        $sheet->setCellValue('E'.$fila, $row[1]);
        $sheet->setCellValue('F'.$fila, $row[2]);
        $sheet->setCellValue('G'.$fila, $row[3]);
        $sheet->setCellValue('H'.$fila, $row[6] ?? '');
        $sheet->setCellValue('I'.$fila, $precioExpr);
        $sheet->setCellValue('J'.$fila, '=I'.$fila.'*'.$colCong.$fila);
        $sheet->getStyle('J'.$fila)->getNumberFormat()->setFormatCode($fmtCurrency);
    }
    $fila++;
    $sheet->getStyle('D'.$fila.':J'.$fila)->applyFromArray($arrBold);
    if ($filaInicial > $fila-1) $filaInicial = $fila-1;
    $sheet->setCellValue('D'.$fila, 'TOTAL:');
    $sheet->setCellValue('E'.$fila, '=SUM(E'.$filaInicial.':E'.($fila-1).')');
    $sheet->setCellValue('F'.$fila, '=SUM(F'.$filaInicial.':F'.($fila-1).')');
    $sheet->setCellValue('G'.$fila, '=SUM(G'.$filaInicial.':G'.($fila-1).')');
    $sheet->setCellValue('J'.$fila, '=SUM(J'.$filaInicial.':J'.($fila-1).')');
    return $fila;
};

if ($tipoMedicion == 'S') {
    $sheet->getStyle('I6:J500')->getNumberFormat()->setFormatCode($fmtCurrency);
    $sheet->getStyle('E6:F500')->getNumberFormat()->setFormatCode($fmtNumber);
    $p2 = $preciocongeladonormal2 . '-(2*' . $preciomantenimientocongelado . ')';
    $p1 = $preciocongeladonormal1 . '-(2*' . $preciomantenimientocongelado . ')';
    $filaTotalCongeladoNormal2 = $writeCongSection('Congelado Normal (mayor -4°)', $rsCongeladoNormal2, '='.$p2);
    $filaTotalCongeladoNormal1 = $writeCongSection('Congelado Normal (-12° a -4°)', $rsCongeladoNormal1, '='.$p1);
} else {
    $sheet->getStyle('I6:J500')->getNumberFormat()->setFormatCode($fmtCurrency);
    $sheet->getStyle('E6:F500')->getNumberFormat()->setFormatCode($fmtNumber);
    $p3 = $preciocongeladodetallado3 . '-(2*' . $preciomantenimientocongelado . ')';
    $p2 = $preciocongeladodetallado2 . '-(2*' . $preciomantenimientocongelado . ')';
    $p1 = $preciocongeladodetallado1 . '-(2*' . $preciomantenimientocongelado . ')';
    $filaTotalCongeladoDetalle3 = $writeCongSection('Congelado (mayor -5°)',    $rsCongeladoDetalle3, '='.$p3);
    $filaTotalCongeladoDetalle2 = $writeCongSection('Congelado (-10° a -5°)',   $rsCongeladoDetalle2, '='.$p2);
    $filaTotalCongeladoDetalle1 = $writeCongSection('Congelado (-15° a -10°)',  $rsCongeladoDetalle1, '='.$p1);
}
foreach (['C'=>15,'D'=>18,'E'=>15,'F'=>15,'G'=>15,'H'=>15,'I'=>15,'J'=>15,'L'=>200] as $col=>$w)
    $sheet->getColumnDimension($col)->setWidth($w);

// ════════════════════════════════════════════════════════════
//  HOJA 3: Almacenamiento
// ════════════════════════════════════════════════════════════
$spreadsheet->createSheet(); $spreadsheet->setActiveSheetIndex(2);
$sheet = $spreadsheet->getActiveSheet()->setTitle('Almacenamiento');
$sheet = $spreadsheet->getActiveSheet();
$fila = 1;
$sheet->setCellValue('A'.$fila, $cliente['ClienteNombre']);
$sheet->mergeCells('A'.$fila.':J'.$fila);
$sheet->getStyle('A'.$fila)->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('I6:J500')->getNumberFormat()->setFormatCode($fmtCurrency);
$sheet->getStyle('E5:F500')->getNumberFormat()->setFormatCode($fmtNumber);
$fila += 2;
$sheet->setCellValue('A'.$fila, 'Período: ' . $fechadesde . ' - ' . $fechahasta);
$sheet->mergeCells('A'.$fila.':E'.$fila);
$fila++;

$filaSaldo = $fila; // fallback

// Stock previo congelado
if ($stockCong[0] !== null) {
    $fila++;
    $sheet->getStyle('A'.$fila.':J'.$fila)->applyFromArray($arrTitulos);
    $sheet->mergeCells('A'.$fila.':D'.$fila);
    foreach (['E'=>'Bultos','F'=>'Kilos','G'=>'Posiciones','H'=>'Días','I'=>'Monto Unitario','J'=>'Monto Total'] as $c=>$v)
        $sheet->setCellValue($c.$fila, $v);
    $fila++;
    $sheet->getStyle('A'.$fila.':J'.$fila)->applyFromArray($arrBold);
    $sheet->mergeCells('A'.$fila.':C'.$fila);
    $sheet->setCellValue('A'.$fila, 'Stock Inicial CONGELADO:');
    $sheet->setCellValue('E'.$fila, number_format((float)$stockCong[0], 0, '.', ''));
    $sheet->setCellValue('F'.$fila, number_format((float)$stockCong[1], 2, '.', ''));
    $sheet->setCellValue('G'.$fila, $posCong);
    $sheet->setCellValue('H'.$fila, $dias);
    $sheet->setCellValue('I'.$fila, $preciomantenimientocongelado);
    $sheet->setCellValue('J'.$fila, '=I'.$fila.'*'.$colAlm.$fila.'*H'.$fila);
    $filaSaldo = $fila;
}

// Stock previo enfriado
if ($stockEnfr[0] !== null) {
    $fila++;
    $sheet->getStyle('A'.$fila.':J'.$fila)->applyFromArray($arrBold);
    $sheet->mergeCells('A'.$fila.':C'.$fila);
    $sheet->setCellValue('A'.$fila, 'Stock Inicial ENFRIADO:');
    $sheet->setCellValue('E'.$fila, number_format((float)$stockEnfr[0], 0, '.', ''));
    $sheet->setCellValue('F'.$fila, number_format((float)$stockEnfr[1], 2, '.', ''));
    $sheet->setCellValue('G'.$fila, $posEnfr);
    $sheet->setCellValue('H'.$fila, $dias);
    $sheet->setCellValue('I'.$fila, $preciomantenimientoenfriado);
    $sheet->setCellValue('J'.$fila, '=I'.$fila.'*'.$colAlm.$fila.'*H'.$fila);
}

// Ingresos
$fila += 2;
$sheet->setCellValue('A'.$fila, 'Ingresos en el período');
$sheet->getStyle('A'.$fila)->getFont()->setBold(true)->setSize(14);
$sheet->mergeCells('A'.$fila.':J'.$fila);
$fila++;
$sheet->getStyle('A'.$fila.':J'.$fila)->applyFromArray($arrTitulos);
foreach (['A'=>'Entrada','B'=>'Remito','C'=>$numero_referencia_texto,'D'=>'Fecha Llegada',
          'E'=>'Bultos','F'=>'Kilos','G'=>'Posiciones','H'=>'Días','I'=>'Monto Unitario','J'=>'Monto Total'] as $c=>$v)
    $sheet->setCellValue($c.$fila, $v);
$filaInicial = $fila+1;
foreach ($rsMantenimientoCongeladoIngresos as $row) {
    $fila++;
    $sheet->getStyle('A'.$fila.':J'.$fila)->applyFromArray($arrStandard);
    $sheet->getStyle('J'.$fila)->getNumberFormat()->setFormatCode($fmtCurrency);
    $precioMant = ($row[7] == '1') ? $preciomantenimientocongelado : $preciomantenimientoenfriado;
    $sheet->setCellValue('A'.$fila, $row[0]);
    $sheet->setCellValue('B'.$fila, $row[1]);
    $sheet->setCellValue('C'.$fila, $row[8]);
    $sheet->setCellValue('D'.$fila, ymdToDmy($row[2]));
    $sheet->setCellValue('E'.$fila, number_format((float)$row[3], 2, '.', ''));
    $sheet->setCellValue('F'.$fila, number_format((float)$row[4], 2, '.', ''));
    $sheet->setCellValue('G'.$fila, number_format((float)$row[5], 2, '.', ''));
    $sheet->setCellValue('H'.$fila, $row[6]);
    $sheet->setCellValue('I'.$fila, $precioMant);
    $sheet->setCellValue('J'.$fila, '=I'.$fila.'*'.$colAlm.$fila.'*H'.$fila);
}
$fila++;
$sheet->getStyle('D'.$fila.':J'.$fila)->applyFromArray($arrBold);
if ($filaInicial > $fila-1) $filaInicial = $fila-1;
$sheet->setCellValue('D'.$fila, 'SUBTOTAL:');
foreach (['E','F','G','J'] as $c)
    $sheet->setCellValue($c.$fila, '=SUM('.$c.$filaInicial.':'.$c.($fila-1).')');
$filaIngresos = $fila;

// Egresos
$fila += 2;
$sheet->setCellValue('A'.$fila, 'Egresos en el período');
$sheet->getStyle('A'.$fila)->getFont()->setBold(true)->setSize(14);
$sheet->mergeCells('A'.$fila.':J'.$fila);
$fila++;
$sheet->getStyle('A'.$fila.':J'.$fila)->applyFromArray($arrTitulos);
foreach (['A'=>'Salida','B'=>'Remito','C'=>$numero_referencia_texto,'D'=>'Fecha Salida',
          'E'=>'Bultos','F'=>'Kilos','G'=>'Posiciones','H'=>'Días','I'=>'Monto Unitario','J'=>'Monto Total'] as $c=>$v)
    $sheet->setCellValue($c.$fila, $v);
$filaInicial = $fila+1;
foreach ($rsMantenimientoCongeladoEgresos as $row) {
    $fila++;
    $sheet->getStyle('A'.$fila.':J'.$fila)->applyFromArray($arrStandard);
    $precioMant = ($row[7] == '1') ? -$preciomantenimientocongelado : -$preciomantenimientoenfriado;
    $sheet->setCellValue('A'.$fila, $row[0]);
    $sheet->setCellValue('B'.$fila, $row[1]);
    $sheet->setCellValue('C'.$fila, $row[8]);
    $sheet->setCellValue('D'.$fila, ymdToDmy($row[2]));
    $sheet->setCellValue('E'.$fila, number_format((float)$row[3], 2, '.', ''));
    $sheet->setCellValue('F'.$fila, number_format((float)$row[4], 2, '.', ''));
    $sheet->setCellValue('G'.$fila, number_format((float)$row[5], 2, '.', ''));
    $sheet->setCellValue('H'.$fila, $row[6]);
    $sheet->setCellValue('I'.$fila, $precioMant);
    $sheet->setCellValue('J'.$fila, '=I'.$fila.'*'.$colAlm.$fila.'*H'.$fila);
}
$fila++;
$sheet->getStyle('D'.$fila.':J'.$fila)->applyFromArray($arrBold);
if ($filaInicial > $fila-1) $filaInicial = $fila-1;
$sheet->setCellValue('D'.$fila, 'SUBTOTAL:');
foreach (['E','F','G','J'] as $c)
    $sheet->setCellValue($c.$fila, '=SUM('.$c.$filaInicial.':'.$c.($fila-1).')');

// TOTAL general almacenamiento
$fila += 2;
$sheet->getStyle('D'.$fila.':J'.$fila)->applyFromArray($arrBold);
$sheet->setCellValue('D'.$fila, 'TOTAL:');
$sheet->setCellValue('J'.$fila, '=J'.$filaSaldo.'+J'.($filaSaldo+1).'+J'.$filaIngresos.'+J'.($fila-2));

// Saldo final (bultos/kilos/posiciones)
$fila += 2;
$sheet->getStyle('A'.$fila.':G'.$fila)->applyFromArray($arrTitulos);
$sheet->setCellValue('E'.$fila, 'Bultos'); $sheet->setCellValue('F'.$fila, 'Kilos'); $sheet->setCellValue('G'.$fila, 'Posiciones');
$fila++;
$sheet->getStyle('A'.$fila.':G'.$fila)->applyFromArray($arrBold);
$sheet->setCellValue('D'.$fila, 'SALDO FINAL:');
$sheet->setCellValue('E'.$fila, '=E'.$filaSaldo.'+E'.($filaSaldo+1).'+E'.$filaIngresos.'-E'.($fila-5));
$sheet->setCellValue('F'.$fila, '=F'.$filaSaldo.'+F'.($filaSaldo+1).'+F'.$filaIngresos.'-F'.($fila-5));
$sheet->setCellValue('G'.$fila, '=G'.$filaSaldo.'+G'.($filaSaldo+1).'+G'.$filaIngresos.'-G'.($fila-5));

// Tabla resumen final (igual a facturarSuccess)
$fila += 2;
$sheet->getStyle('G'.$fila.':I'.($fila+5))->applyFromArray($arrTitulos);
$sheet->getStyle('J'.$fila.':J'.($fila+4))->applyFromArray($arrStandard);
$sheet->mergeCells('H'.$fila.':I'.$fila); $sheet->setCellValue('H'.$fila, 'ALMACENAMIENTO');
$sheet->setCellValue('J'.$fila, '=J'.($fila-5));
$fila++;
$sheet->mergeCells('H'.$fila.':I'.$fila); $sheet->setCellValue('H'.$fila, 'CARGA / DESCARGA');
$sheet->setCellValue('J'.$fila, "='Cargas - Descargas'!J".$filaTotalCargas);
$fila++;
$sheet->mergeCells('H'.$fila.':I'.$fila); $sheet->setCellValue('H'.$fila, 'SERV. CONGELADO');
if ($tipoMedicion == 'S') {
    $sheet->setCellValue('J'.$fila, '=Congelado!J'.$filaTotalCongeladoNormal1.'+Congelado!J'.$filaTotalCongeladoNormal2);
} else {
    $sheet->setCellValue('J'.$fila, '=Congelado!J'.$filaTotalCongeladoDetalle1.'+Congelado!J'.$filaTotalCongeladoDetalle2.'+Congelado!J'.$filaTotalCongeladoDetalle3);
}
$fila++;
$sheet->mergeCells('H'.$fila.':I'.$fila); $sheet->setCellValue('H'.$fila, 'SUBTOTAL');
$sheet->setCellValue('J'.$fila, '=J'.($fila-3).'+J'.($fila-2).'+J'.($fila-1));
$fila++;
$sheet->mergeCells('H'.$fila.':I'.$fila); $sheet->setCellValue('H'.$fila, 'IVA 21%');
$sheet->setCellValue('J'.$fila, '=J'.($fila-1).'*0.21');
$fila++;
$sheet->getStyle('J'.$fila)->applyFromArray($arrBold);
$sheet->mergeCells('H'.$fila.':I'.$fila); $sheet->setCellValue('H'.$fila, 'TOTAL');
$sheet->setCellValue('J'.$fila, '=J'.($fila-2).'+J'.($fila-1));
$sheet->getStyle('J6:J1000')->getNumberFormat()->setFormatCode($fmtCurrency);
foreach (['D'=>18,'E'=>15,'F'=>15,'G'=>15,'H'=>18,'I'=>15,'J'=>15,'L'=>200] as $col=>$w)
    $sheet->getColumnDimension($col)->setWidth($w);

// ── Activar hoja 1 y descargar ────────────────────────────────
$spreadsheet->setActiveSheetIndex(0);
$nombre = 'Factura_Frio_' . preg_replace('/[^A-Za-z0-9_]/', '_', $cliente['ClienteNombre']) . '.xlsx';

header('Pragma: public');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nombre . '"');
header('Content-Transfer-Encoding: binary');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
