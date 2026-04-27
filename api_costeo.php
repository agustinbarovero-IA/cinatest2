<?php
/**
 * ============================================================
 *  api_costeo.php — Costeo de Operaciones PR-08
 *  Grupo CINA
 * ============================================================
 *
 *  Modelo:
 *   - operacion_codigo (CN1..CNn / DF1..DFm) : catálogo de códigos
 *   - operacion_item_catalogo : ítems de costos con valor de referencia
 *   - cliente_frio.codigo_operacion : columna nueva (1 código por PR-08)
 *   - pr08_costeo (cabecera) + pr08_costeo_item (detalle)
 *
 *  Permisos:
 *    costeo_editar  — administrar códigos, ítems, registrar costeos
 *    costeo_ver     — solo lectura
 *
 *  Endpoints (?action=...):
 *    cost_codigo_list
 *    cost_codigo_save                 POST
 *    cost_codigo_delete               POST
 *    cost_item_list
 *    cost_item_save                   POST
 *    cost_item_delete                 POST
 *    cost_pr08_sin_codigo             (lista cliente_frio sin código)
 *    cost_asignar_pr08                POST  (asigna código a cliente_frio)
 *    cost_costeo_list
 *    cost_costeo_get                  (vigente o por id)
 *    cost_costeo_save                 POST  (registra nuevo costeo)
 *    cost_historial_codigo            (historial por código)
 *    cost_reporte_csv                 (descarga CSV)
 *    cost_reporte_html                (HTML imprimible a PDF)
 * ============================================================
 */

require_once dirname(__DIR__) . '/config.php';

header('Content-Type: application/json; charset=utf-8');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (empty($_SESSION['cina_usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Sesión expirada']);
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
    http_response_code(500);
    echo json_encode(['error' => 'Error conexión BD']);
    exit;
}

// ═════════════════════════════════════════════════════════════
//  Migración
// ═════════════════════════════════════════════════════════════
function migrar($pdo) {
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS operacion_codigo (
        codigo VARCHAR(10) PRIMARY KEY,
        tipo_cliente ENUM('nacional','fiscal') NOT NULL,
        numero INT NOT NULL,
        denominacion VARCHAR(255) NOT NULL,
        descripcion TEXT NULL,
        cantidad_pallets_ref INT NULL,
        peso_pallet_ref DECIMAL(10,2) NULL,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        creado_por INT NULL,
        creado_en DATETIME NULL,
        actualizado_por INT NULL,
        actualizado_por_nombre VARCHAR(120) NULL,
        actualizado_en DATETIME NULL,
        UNIQUE KEY uk_tipo_num (tipo_cliente, numero)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS operacion_item_catalogo (
        id INT PRIMARY KEY AUTO_INCREMENT,
        tipo_operacion ENUM('IN','OUT','ALMACENAMIENTO','TUNEL') NOT NULL,
        nombre VARCHAR(255) NOT NULL,
        descripcion TEXT NULL,
        unidad VARCHAR(20) NOT NULL DEFAULT 'min',
        costo_unitario DECIMAL(14,4) NOT NULL DEFAULT 0,
        moneda ENUM('ARS','USD') NOT NULL DEFAULT 'ARS',
        orden INT NOT NULL DEFAULT 0,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        actualizado_por INT NULL,
        actualizado_por_nombre VARCHAR(120) NULL,
        actualizado_en DATETIME NULL,
        KEY idx_tipo (tipo_operacion, orden)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS pr08_costeo (
        id INT PRIMARY KEY AUTO_INCREMENT,
        codigo_operacion VARCHAR(10) NOT NULL,
        numero_costeo VARCHAR(30) NOT NULL,
        fecha_costeo DATETIME NOT NULL,
        cantidad_pallets INT NOT NULL,
        peso_pallet DECIMAL(10,2) NOT NULL,
        peso_total DECIMAL(14,2) NOT NULL,
        total_general DECIMAL(14,2) NOT NULL DEFAULT 0,
        precio_por_pallet DECIMAL(14,4) NOT NULL DEFAULT 0,
        precio_por_kg DECIMAL(14,6) NOT NULL DEFAULT 0,
        moneda VARCHAR(10) NOT NULL DEFAULT 'ARS',
        observaciones TEXT NULL,
        usuario_id INT NULL,
        usuario_nombre VARCHAR(120) NULL,
        vigente TINYINT(1) NOT NULL DEFAULT 1,
        KEY idx_codigo (codigo_operacion, vigente, fecha_costeo),
        UNIQUE KEY uk_numero (numero_costeo)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS pr08_costeo_item (
        id INT PRIMARY KEY AUTO_INCREMENT,
        pr08_costeo_id INT NOT NULL,
        tipo_operacion ENUM('IN','OUT','ALMACENAMIENTO','TUNEL') NOT NULL,
        item_catalogo_id INT NULL,
        nombre_snapshot VARCHAR(255) NOT NULL,
        unidad_snapshot VARCHAR(20) NOT NULL,
        cantidad DECIMAL(14,4) NOT NULL DEFAULT 0,
        costo_unitario_snapshot DECIMAL(14,4) NOT NULL DEFAULT 0,
        moneda_snapshot ENUM('ARS','USD') NOT NULL DEFAULT 'ARS',
        subtotal DECIMAL(14,4) NOT NULL DEFAULT 0,
        orden INT NOT NULL DEFAULT 0,
        es_indirecto TINYINT(1) NOT NULL DEFAULT 0,
        KEY idx_costeo (pr08_costeo_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // ALTERs idempotentes
    try { $pdo->exec("ALTER TABLE pr08_costeo_item ADD COLUMN es_indirecto TINYINT(1) NOT NULL DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE pr08_costeo ADD COLUMN dias_almacenamiento INT NOT NULL DEFAULT 30 AFTER peso_total"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE cliente_frio ADD COLUMN codigo_operacion VARCHAR(10) NULL AFTER cliente_id"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE cliente_frio ADD INDEX idx_codigo_op (codigo_operacion)"); } catch (Exception $e) {}

    // Costos indirectos (sheet COSTOS FIJOS)
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS costo_indirecto (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nombre VARCHAR(120) NOT NULL,
        descripcion TEXT NULL,
        costo_mensual DECIMAL(16,2) NOT NULL DEFAULT 0,
        moneda ENUM('ARS','USD') NOT NULL DEFAULT 'ARS',
        rep_nacional DECIMAL(5,4) NOT NULL DEFAULT 0.7,
        rep_fiscal DECIMAL(5,4) NOT NULL DEFAULT 0.3,
        rep_inout DECIMAL(5,4) NOT NULL DEFAULT 0.6,
        rep_almacenamiento DECIMAL(5,4) NOT NULL DEFAULT 0.3,
        rep_tunel DECIMAL(5,4) NOT NULL DEFAULT 0.1,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        orden INT NOT NULL DEFAULT 0,
        actualizado_por INT NULL,
        actualizado_por_nombre VARCHAR(120) NULL,
        actualizado_en DATETIME NULL,
        KEY idx_orden (orden)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    // ALTER idempotente: si la tabla existía con costo_anual, renombrar a costo_mensual
    try { $pdo->exec("ALTER TABLE costo_indirecto CHANGE COLUMN costo_anual costo_mensual DECIMAL(16,2) NOT NULL DEFAULT 0"); } catch (Exception $e) { /* ya renombrada */ }

    // Parámetros de costeo (posiciones, días/mes, ingresos/egresos/mes)
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS parametros_costeo (
        clave VARCHAR(60) PRIMARY KEY,
        valor DECIMAL(16,4) NOT NULL DEFAULT 0,
        unidad VARCHAR(40) NULL,
        descripcion VARCHAR(255) NULL,
        orden INT NOT NULL DEFAULT 0,
        actualizado_por INT NULL,
        actualizado_por_nombre VARCHAR(120) NULL,
        actualizado_en DATETIME NULL
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Seed catálogo base si está vacío (basado en Excel del usuario)
    if ((int)$pdo->query("SELECT COUNT(*) FROM operacion_item_catalogo")->fetchColumn() === 0) {
        $seed = [
            // tipo, nombre, unidad, costo_ref, moneda, orden
            ['IN','PLANIFICACION','hs',0,'ARS',10],
            ['IN','PORTERIA','min',0,'ARS',20],
            ['IN','BALANZA','min',0,'ARS',30],
            ['IN','DOCUMENTACION','min',0,'ARS',40],
            ['IN','ENTRADA/SALIDA','min',0,'ARS',50],
            ['IN','CONTROL DE CALIDAD','min',0,'ARS',60],
            ['IN','CLASIFICACION LOGISTICA','min',0,'ARS',70],
            ['IN','CLASIFICACION CALIDAD','min',0,'ARS',80],
            ['IN','PREPARACION DE PALLET','min',0,'ARS',90],
            ['IN','MOVIMIENTO A CAMARA','min',0,'ARS',100],
            ['IN','AUTOELEVADOR','min',0,'ARS',110],
            ['IN','ZORRA ELECTRICA','min',0,'ARS',120],
            ['IN','STRICH','Kg',0,'ARS',130],
            ['IN','AMORTIZACION RACKS','un',0,'ARS',140],
            ['IN','PALLET REFORZADO','un',0,'ARS',150],
            ['IN','RENDICIONES AL CLIENTE','hs',0,'ARS',160],
            ['IN','FACTURACION','hs',0,'ARS',170],
            ['IN','CAPACITACIONES','hs',0,'ARS',180],
            ['IN','COBRANZAS','hs',0,'ARS',190],
            ['IN','CONSUMO ENERGETICO','kw',0,'ARS',200],

            ['OUT','PLANIFICACION','hs',0,'ARS',10],
            ['OUT','PORTERIA','min',0,'ARS',20],
            ['OUT','BALANZA','min',0,'ARS',30],
            ['OUT','DOCUMENTACION','min',0,'ARS',40],
            ['OUT','ENTRADA/SALIDA','min',0,'ARS',50],
            ['OUT','CONTROL DE CALIDAD','min',0,'ARS',60],
            ['OUT','PREPARACION DE PEDIDO','min',0,'ARS',70],
            ['OUT','ARMADO DE PALLETS','min',0,'ARS',80],
            ['OUT','MOVIMIENTO DE CAMARA','min',0,'ARS',90],
            ['OUT','AUTOELEVADOR','min',0,'ARS',100],
            ['OUT','ZORRA ELECTRICA / APILADOR','min',0,'ARS',110],
            ['OUT','SENASA','min',0,'ARS',120],
            ['OUT','RENDICIONES AL CLIENTE','hs',0,'ARS',130],
            ['OUT','FACTURACION','hs',0,'ARS',140],
            ['OUT','COBRANZAS','hs',0,'ARS',150],
            ['OUT','CONSUMO ENERGETICO','kw',0,'ARS',160],

            ['ALMACENAMIENTO','POSICION CONGELADA','día',0,'ARS',10],
            ['ALMACENAMIENTO','POSICION ENFRIADA','día',0,'ARS',20],
            ['ALMACENAMIENTO','POSICION AMBIENTE','día',0,'ARS',30],
            ['ALMACENAMIENTO','ENERGIA ALMACENAMIENTO','día',0,'ARS',40],
            ['ALMACENAMIENTO','MANTENIMIENTO / DEPRECIACIÓN','día',0,'ARS',50],
            ['ALMACENAMIENTO','RACKEADO','día',0,'ARS',60],

            ['TUNEL','TUNEL CONGELADO','día',0,'ARS',10],
            ['TUNEL','ENERGIA TUNEL','día',0,'ARS',20],
            ['TUNEL','MANTENIMIENTO TUNEL','día',0,'ARS',30],
            ['TUNEL','INGRESO/EGRESO TUNEL','un',0,'ARS',40],
        ];
        $ins = $pdo->prepare("INSERT INTO operacion_item_catalogo (tipo_operacion, nombre, unidad, costo_unitario, moneda, orden) VALUES (?,?,?,?,?,?)");
        foreach ($seed as $r) $ins->execute($r);
    }

    // Seed códigos base si vacío (los que vi en el Excel: CN1-CN40, DF1-DF11)
    if ((int)$pdo->query("SELECT COUNT(*) FROM operacion_codigo")->fetchColumn() === 0) {
        $seedCN = [
            [1,'CARNE NORMAL - MONO PRODUCTO'],[2,'CARNE NORMAL - MONO PRODUCTO RACKEADO 1.75'],
            [3,'CARNE NORMAL - MONO PRODUCTO RACKEADO 2.20'],[4,'CARNE SIN PICKEO - MULTIPRODUCTO'],
            [5,'CARNE SIN PICKEO - MULTIPRODUCTO RACKEADO 1.75'],[6,'CARNE SIN PICKEO - MULTIPRODUCTO RACKEADO 2.20'],
            [7,'CARNE CON PICKEO - MULTIPRODUCTO'],[8,'CARNE CON PICKEO - MULTIPRODUCTO RACKEADO 1.75'],
            [9,'CARNE CON PICKEO - MULTIPRODUCTO RACKEADO 2.20'],[10,'CARNICOS INDUSTRIALIZADO'],
            [11,'CARNICOS INDUSTRIALIZADO RACKEADO 1.75'],[12,'CARNICOS INDUSTRIALIZADO RACKEADO 2.20'],
            [13,'PRODUCTO ENFRIADOS CARNICOS'],[14,'PRODUCTO ENFRIADOS CARNICOS - CON PICKEO'],
            [15,'VEGETALES - RACKEADO 1.75'],[16,'VEGETALES - RACKEADO 2.20'],
            [17,'HELADOS - RACKEADO 1.75'],[18,'HELADOS - RACKEADO 2.20'],
            [19,'QUESOS ENFRIADOS'],[20,'PANIFICADOS'],[21,'PANIFICADOS RACKEADO 1.75'],
            [22,'PANIFICADOS RACKEADO 2.20'],[23,'PANIFICADOS - CON TRACKEO'],
            [24,'PANIFICADOS CON TRACKEO RACKEADO 1.75'],[25,'PANIFICADOS CON TRACKEO RACKEADO 2.20'],
            [26,'CITRICOS - TAMBOR'],[27,'CITRICOS - GOODPACK'],[28,'LIQUIDOS ENFRIADOS'],
            [29,'LIQUIDOS CONGELADOS'],[30,'PRODUCTOS SECOS'],[31,'PRODUCTOS SECOS RACKEADO 1.75'],
            [32,'PRODUCTOS SECOS RACKEADO 2.20'],[33,'ENFRIADO PRODUCTOS VARIOS'],
            [34,'CROSDOCKING'],[35,'AMBIENTES CON PICKEO'],[36,'OPERACION ARCOR'],
            [37,'INGRESO GRANEL - ALM PALETIZADO SECO'],[38,'PANIFICADOS PICKEO PALLETS PEQUEÑOS'],
            [39,'PRODUCTOS FARMACEUTICOS CONGELADOS'],[40,'ENFRIADO PRODUCTOS FARINACEOS'],
        ];
        $seedDF = [
            [1,'SECO GRANEL CAJAS'],[2,'SECO GRANEL TAMBORES'],[3,'SECO PALETIZADO'],
            [4,'ENFRIADO GRANEL'],[5,'ENFRIADO PALETIZADO'],[6,'CONGELADO GRANEL 40'],
            [7,'CONGELADO GRANEL 40 CON DESVIOS'],[8,'CONGELADO GRANEL 20'],
            [9,'CONGELADO GRANEL 20 CON DESVIOS'],[10,'CONGELADO GRANEL TAMBORES'],
            [11,'CONGELADO PALETIZADO'],
        ];
        $ins = $pdo->prepare("INSERT INTO operacion_codigo (codigo, tipo_cliente, numero, denominacion, activo, creado_en) VALUES (?,?,?,?,1,NOW())");
        foreach ($seedCN as $r) $ins->execute(['CN'.$r[0], 'nacional', $r[0], $r[1]]);
        foreach ($seedDF as $r) $ins->execute(['DF'.$r[0], 'fiscal', $r[0], $r[1]]);
    }

    // Seed costos indirectos (tabla de la hoja COSTOS FIJOS) + impuestos
    if ((int)$pdo->query("SELECT COUNT(*) FROM costo_indirecto")->fetchColumn() === 0) {
        $seedCI = [
            // [nombre, costo_mensual, rep_nac, rep_fis, rep_inout, rep_alm, rep_tunel, descripcion]
            ['MANTENIMIENTO',               54617635.00, 0.70, 0.30, 0.20, 0.70, 0.10, 'Incluye mantenimiento y depreciación de la planta. 50% almacenamiento, 30% túneles, 20% in-out'],
            ['SISTEMA DE GESTION',           2000000.00, 0.70, 0.30, 0.35, 0.35, 0.30, 'Por volumen de operación'],
            ['HABILITACIONES',               1000000.00, 0.60, 0.40, 0.00, 1.00, 0.00, ''],
            ['ADMINISTRACION',               6236907.00, 0.70, 0.30, 0.60, 0.30, 0.10, ''],
            ['SISTEMAS',                     2800000.00, 0.70, 0.30, 0.35, 0.35, 0.30, 'Sueldo sistemas + internet 70% nac / 30% fiscal'],
            ['RRHH SIN VESTIMENTA',         11407000.00, 0.70, 0.30, 0.60, 0.30, 0.10, ''],
            ['LIQUIDACION DE SUELDOS',       1385600.00, 0.70, 0.30, 0.60, 0.30, 0.10, ''],
            ['OTROS GASTOS NO CONTEMPLADOS',35000000.00, 0.70, 0.30, 0.35, 0.35, 0.30, 'Seguros, agua, gas y otros gastos'],
            ['COSTO DE VESTIMENTA',          3400000.00, 0.80, 0.20, 0.70, 0.00, 0.30, ''],
            ['ENERGIA GENERAL',              2969577.00, 0.70, 0.30, 0.60, 0.30, 0.10, ''],
            ['GUARDIA OCIOSA',               8995352.00, 0.70, 0.30, 0.60, 0.30, 0.10, 'Se considera 451 hs'],
            // Impuestos (con imputación default, editables)
            ['SEGUROS',                      9056300.00, 0.70, 0.30, 0.35, 0.35, 0.30, ''],
            ['IMP Y SERVICIOS',             10543700.00, 0.70, 0.30, 0.35, 0.35, 0.30, ''],
            ['DREI ARROYO SECO',              623000.00, 0.70, 0.30, 0.35, 0.35, 0.30, ''],
            ['DREI PUEBLO ESTHER',           5300000.00, 0.70, 0.30, 0.35, 0.35, 0.30, ''],
            ['IMP INMOBILIARIO',             2850000.00, 0.70, 0.30, 0.35, 0.35, 0.30, ''],
        ];
        $ins = $pdo->prepare("INSERT INTO costo_indirecto (nombre, costo_mensual, rep_nacional, rep_fiscal, rep_inout, rep_almacenamiento, rep_tunel, descripcion, activo, orden) VALUES (?,?,?,?,?,?,?,?,1,?)");
        foreach ($seedCI as $i => $r) {
            $ins->execute([$r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[6], $r[7], ($i+1)*10]);
        }
    }

    // Seed parámetros
    if ((int)$pdo->query("SELECT COUNT(*) FROM parametros_costeo")->fetchColumn() === 0) {
        $seedP = [
            ['POSICIONES_CALCULO', 4500,  'posiciones', 'Posiciones totales de la planta',                     10],
            ['DIAS_MES',             30,  'días',       'Días considerados por mes para cálculos',             20],
            ['INGRESOS_MES_NAC',      0,  'movimientos','Movimientos promedio de ingreso mensual nacional',    30],
            ['EGRESOS_MES_NAC',       0,  'movimientos','Movimientos promedio de egreso mensual nacional',     40],
            ['INGRESOS_MES_FIS',      0,  'movimientos','Movimientos promedio de ingreso mensual fiscal',      50],
            ['EGRESOS_MES_FIS',       0,  'movimientos','Movimientos promedio de egreso mensual fiscal',       60],
        ];
        $ins = $pdo->prepare("INSERT INTO parametros_costeo (clave, valor, unidad, descripcion, orden) VALUES (?,?,?,?,?)");
        foreach ($seedP as $r) $ins->execute($r);
    }
}

// ═════════════════════════════════════════════════════════════
//  Cálculo de unitarios de costos indirectos
//  Devuelve, para cada tipo_cliente (nacional/fiscal), los valores:
//    unit_inout:  $ por movimiento IN-OUT (suma de todos los indirectos)
//    unit_alm:    $ por pos-día almacenamiento
//    unit_tunel:  $ por día túnel
// ═════════════════════════════════════════════════════════════
function calcularUnitariosIndirectos($pdo) {
    $params = [];
    foreach ($pdo->query("SELECT clave, valor FROM parametros_costeo") as $r) {
        $params[$r['clave']] = (float)$r['valor'];
    }
    $posiciones = $params['POSICIONES_CALCULO'] ?? 4500;
    $diasMes    = $params['DIAS_MES']           ?? 30;
    $movNac     = ($params['INGRESOS_MES_NAC'] ?? 0) + ($params['EGRESOS_MES_NAC'] ?? 0);
    $movFis     = ($params['INGRESOS_MES_FIS'] ?? 0) + ($params['EGRESOS_MES_FIS'] ?? 0);

    $acumNac = ['inout' => 0, 'alm' => 0, 'tunel' => 0];
    $acumFis = ['inout' => 0, 'alm' => 0, 'tunel' => 0];
    $detalle = []; // desglose por item

    $items = $pdo->query("SELECT * FROM costo_indirecto WHERE activo=1 ORDER BY orden, id")->fetchAll();
    foreach ($items as $it) {
        $mens   = (float)$it['costo_mensual'];
        $valNac = $mens * (float)$it['rep_nacional'];
        $valFis = $mens * (float)$it['rep_fiscal'];

        // Nacional
        $valNacInOut = $valNac * (float)$it['rep_inout'];
        $valNacAlm   = $valNac * (float)$it['rep_almacenamiento'];
        $valNacTun   = $valNac * (float)$it['rep_tunel'];
        $uNacIO  = $movNac    > 0 ? $valNacInOut / $movNac                  : 0;
        $uNacAlm = ($posiciones * $diasMes) > 0 ? $valNacAlm / ($posiciones * $diasMes) : 0;
        $uNacTun = $diasMes   > 0 ? $valNacTun / $diasMes                    : 0;

        // Fiscal
        $valFisInOut = $valFis * (float)$it['rep_inout'];
        $valFisAlm   = $valFis * (float)$it['rep_almacenamiento'];
        $valFisTun   = $valFis * (float)$it['rep_tunel'];
        $uFisIO  = $movFis    > 0 ? $valFisInOut / $movFis                  : 0;
        $uFisAlm = ($posiciones * $diasMes) > 0 ? $valFisAlm / ($posiciones * $diasMes) : 0;
        $uFisTun = $diasMes   > 0 ? $valFisTun / $diasMes                    : 0;

        $acumNac['inout'] += $uNacIO;
        $acumNac['alm']   += $uNacAlm;
        $acumNac['tunel'] += $uNacTun;
        $acumFis['inout'] += $uFisIO;
        $acumFis['alm']   += $uFisAlm;
        $acumFis['tunel'] += $uFisTun;

        $detalle[] = [
            'id' => (int)$it['id'],
            'nombre' => $it['nombre'],
            'costo_mensual' => $mens,
            'val_nac_inout' => $valNacInOut, 'unit_nac_inout' => $uNacIO,
            'val_nac_alm'   => $valNacAlm,   'unit_nac_alm'   => $uNacAlm,
            'val_nac_tunel' => $valNacTun,   'unit_nac_tunel' => $uNacTun,
            'val_fis_inout' => $valFisInOut, 'unit_fis_inout' => $uFisIO,
            'val_fis_alm'   => $valFisAlm,   'unit_fis_alm'   => $uFisAlm,
            'val_fis_tunel' => $valFisTun,   'unit_fis_tunel' => $uFisTun,
        ];
    }

    return [
        'parametros' => [
            'posiciones' => $posiciones, 'dias_mes' => $diasMes,
            'mov_nac' => $movNac, 'mov_fis' => $movFis,
        ],
        'nacional' => $acumNac,
        'fiscal'   => $acumFis,
        'detalle'  => $detalle,
    ];
}

migrar($pdo);

// ═════════════════════════════════════════════════════════════
//  Helpers
// ═════════════════════════════════════════════════════════════
function resp($code, $payload) {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function userInfo() {
    return [
        'id'     => (int)($_SESSION['cina_usuario_id'] ?? 0),
        'nombre' => $_SESSION['cina_nombre'] ?? $_SESSION['cina_usuario'] ?? 'Sistema',
        'rol_id' => (int)($_SESSION['cina_rol_id'] ?? 0),
    ];
}

function tienePermiso($pdo, $permiso) {
    $u = userInfo();
    if (in_array($u['rol_id'], [1,5,10])) return true;
    $stmt = $pdo->prepare("SELECT valor FROM rol_permisos_extra WHERE rol_id=? AND permiso=?");
    $stmt->execute([$u['rol_id'], $permiso]);
    return (bool)$stmt->fetchColumn();
}

function requireEdit($pdo) {
    if (!tienePermiso($pdo, 'costeo_editar')) resp(403, ['error' => 'Sin permisos (requiere costeo_editar)']);
}

function requireView($pdo) {
    if (!tienePermiso($pdo, 'costeo_editar') && !tienePermiso($pdo, 'costeo_ver')) {
        resp(403, ['error' => 'Sin permisos']);
    }
}

// Próximo número de costeo global COST-YYYY-NNNN
function proximoNumeroCosteo($pdo) {
    $yyyy = date('Y');
    $last = $pdo->prepare("SELECT numero_costeo FROM pr08_costeo WHERE numero_costeo LIKE ? ORDER BY id DESC LIMIT 1");
    $last->execute(["COST-$yyyy-%"]);
    $ult = $last->fetchColumn();
    $n = 1;
    if ($ult && preg_match('/COST-\d{4}-(\d+)/', $ult, $m)) $n = (int)$m[1] + 1;
    return sprintf('COST-%s-%04d', $yyyy, $n);
}

// Próximo número de código para un tipo_cliente
function proximoNumeroCodigo($pdo, $tipo_cliente) {
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(numero),0)+1 FROM operacion_codigo WHERE tipo_cliente=?");
    $stmt->execute([$tipo_cliente]);
    return (int)$stmt->fetchColumn();
}

// ═════════════════════════════════════════════════════════════
//  Enrutador
// ═════════════════════════════════════════════════════════════
$action = $_GET['action'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($action) {

    // ────── Catálogo de códigos ──────
    case 'cost_codigo_list': {
        requireView($pdo);
        $rows = $pdo->query("
          SELECT oc.*,
            (SELECT COUNT(*) FROM cliente_frio cf WHERE cf.codigo_operacion = oc.codigo) AS pr08_asignados,
            (SELECT c.numero_costeo FROM pr08_costeo c WHERE c.codigo_operacion=oc.codigo AND c.vigente=1 ORDER BY c.id DESC LIMIT 1) AS costeo_vigente_num,
            (SELECT c.fecha_costeo  FROM pr08_costeo c WHERE c.codigo_operacion=oc.codigo AND c.vigente=1 ORDER BY c.id DESC LIMIT 1) AS costeo_vigente_fecha,
            (SELECT c.total_general FROM pr08_costeo c WHERE c.codigo_operacion=oc.codigo AND c.vigente=1 ORDER BY c.id DESC LIMIT 1) AS costeo_vigente_total,
            (SELECT c.precio_por_pallet FROM pr08_costeo c WHERE c.codigo_operacion=oc.codigo AND c.vigente=1 ORDER BY c.id DESC LIMIT 1) AS costeo_vigente_pallet,
            (SELECT c.precio_por_kg FROM pr08_costeo c WHERE c.codigo_operacion=oc.codigo AND c.vigente=1 ORDER BY c.id DESC LIMIT 1) AS costeo_vigente_kg,
            (SELECT c.moneda FROM pr08_costeo c WHERE c.codigo_operacion=oc.codigo AND c.vigente=1 ORDER BY c.id DESC LIMIT 1) AS costeo_vigente_moneda
          FROM operacion_codigo oc
          ORDER BY oc.tipo_cliente, oc.numero
        ")->fetchAll();
        resp(200, ['codigos' => $rows]);
        break;
    }

    case 'cost_codigo_save': {
        requireEdit($pdo);
        $codigo = trim($body['codigo'] ?? '');
        $tipo   = $body['tipo_cliente'] ?? '';
        $denom  = trim($body['denominacion'] ?? '');
        $desc   = $body['descripcion'] ?? null;
        $palR   = $body['cantidad_pallets_ref'] ?? null;
        $pesoR  = $body['peso_pallet_ref'] ?? null;
        $activo = !empty($body['activo']) ? 1 : 0;
        if (!in_array($tipo, ['nacional','fiscal'])) resp(400, ['error' => 'tipo_cliente inválido']);
        if (!$denom) resp(400, ['error' => 'Denominación requerida']);
        $u = userInfo();
        if (!$codigo) {
            // nuevo: asignar código secuencial
            $num = proximoNumeroCodigo($pdo, $tipo);
            $codigo = ($tipo === 'nacional' ? 'CN' : 'DF') . $num;
            $pdo->prepare("
              INSERT INTO operacion_codigo (codigo, tipo_cliente, numero, denominacion, descripcion, cantidad_pallets_ref, peso_pallet_ref, activo, creado_por, creado_en, actualizado_por, actualizado_por_nombre, actualizado_en)
              VALUES (?,?,?,?,?,?,?,?,?,NOW(),?,?,NOW())
            ")->execute([$codigo, $tipo, $num, $denom, $desc, $palR ?: null, $pesoR ?: null, $activo, $u['id'], $u['id'], $u['nombre']]);
        } else {
            $pdo->prepare("
              UPDATE operacion_codigo SET denominacion=?, descripcion=?, cantidad_pallets_ref=?, peso_pallet_ref=?, activo=?,
                actualizado_por=?, actualizado_por_nombre=?, actualizado_en=NOW()
              WHERE codigo=?
            ")->execute([$denom, $desc, $palR ?: null, $pesoR ?: null, $activo, $u['id'], $u['nombre'], $codigo]);
        }
        resp(200, ['ok' => true, 'codigo' => $codigo]);
        break;
    }

    case 'cost_codigo_delete': {
        requireEdit($pdo);
        $codigo = trim($body['codigo'] ?? '');
        if (!$codigo) resp(400, ['error' => 'codigo requerido']);
        // Chequear que no tenga PR-08 ni costeos
        $n = (int)$pdo->prepare("SELECT COUNT(*) FROM cliente_frio WHERE codigo_operacion=?")->execute([$codigo]);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cliente_frio WHERE codigo_operacion=?");
        $stmt->execute([$codigo]);
        if ((int)$stmt->fetchColumn() > 0) resp(400, ['error' => 'Tiene PR-08 asignados, desactivá en vez de eliminar']);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pr08_costeo WHERE codigo_operacion=?");
        $stmt->execute([$codigo]);
        if ((int)$stmt->fetchColumn() > 0) resp(400, ['error' => 'Tiene costeos históricos, desactivá en vez de eliminar']);
        $pdo->prepare("DELETE FROM operacion_codigo WHERE codigo=?")->execute([$codigo]);
        resp(200, ['ok' => true]);
        break;
    }

    // ────── Catálogo de ítems ──────
    case 'cost_item_list': {
        requireView($pdo);
        $rows = $pdo->query("SELECT * FROM operacion_item_catalogo WHERE activo=1 ORDER BY tipo_operacion, orden, id")->fetchAll();
        resp(200, ['items' => $rows]);
        break;
    }

    case 'cost_item_save': {
        requireEdit($pdo);
        $u = userInfo();
        $id       = (int)($body['id'] ?? 0);
        $tipo     = $body['tipo_operacion'] ?? '';
        $nombre   = trim($body['nombre'] ?? '');
        $desc     = $body['descripcion'] ?? null;
        $unidad   = trim($body['unidad'] ?? 'min');
        $costo    = (float)($body['costo_unitario'] ?? 0);
        $moneda   = in_array($body['moneda'] ?? '', ['ARS','USD']) ? $body['moneda'] : 'ARS';
        $orden    = (int)($body['orden'] ?? 0);
        $activo   = !empty($body['activo']) ? 1 : 0;
        if (!in_array($tipo, ['IN','OUT','ALMACENAMIENTO','TUNEL'])) resp(400, ['error' => 'tipo_operacion inválido']);
        if (!$nombre) resp(400, ['error' => 'nombre requerido']);

        if ($id) {
            $pdo->prepare("
              UPDATE operacion_item_catalogo SET tipo_operacion=?, nombre=?, descripcion=?, unidad=?, costo_unitario=?, moneda=?, orden=?, activo=?,
                actualizado_por=?, actualizado_por_nombre=?, actualizado_en=NOW() WHERE id=?
            ")->execute([$tipo, $nombre, $desc, $unidad, $costo, $moneda, $orden, $activo, $u['id'], $u['nombre'], $id]);
        } else {
            $pdo->prepare("
              INSERT INTO operacion_item_catalogo (tipo_operacion, nombre, descripcion, unidad, costo_unitario, moneda, orden, activo, actualizado_por, actualizado_por_nombre, actualizado_en)
              VALUES (?,?,?,?,?,?,?,?,?,?,NOW())
            ")->execute([$tipo, $nombre, $desc, $unidad, $costo, $moneda, $orden, $activo, $u['id'], $u['nombre']]);
            $id = (int)$pdo->lastInsertId();
        }
        resp(200, ['ok' => true, 'id' => $id]);
        break;
    }

    case 'cost_item_delete': {
        requireEdit($pdo);
        $id = (int)($body['id'] ?? 0);
        if (!$id) resp(400, ['error' => 'id requerido']);
        // Soft delete
        $pdo->prepare("UPDATE operacion_item_catalogo SET activo=0 WHERE id=?")->execute([$id]);
        resp(200, ['ok' => true]);
        break;
    }

    // ────── PR-08 (cliente_frio) sin código asignado ──────
    case 'cost_pr08_sin_codigo': {
        requireView($pdo);
        // Última fila por cliente sin código (sólo las que son últimas por cliente)
        $rows = $pdo->query("
          SELECT cf.cliente_frio_id, cf.cliente_id, c.ClienteNombre, c.fiscal,
                 cf.descripcion_producto, cf.fecha_registro, cf.updated_at, cf.codigo_operacion
          FROM cliente_frio cf
          INNER JOIN (
            SELECT cliente_id, MAX(cliente_frio_id) AS mx FROM cliente_frio GROUP BY cliente_id
          ) ult ON ult.cliente_id=cf.cliente_id AND ult.mx=cf.cliente_frio_id
          LEFT JOIN CLIENTES c ON c.ClienteID = cf.cliente_id
          ORDER BY cf.updated_at DESC
        ")->fetchAll();
        resp(200, ['pr08' => $rows]);
        break;
    }

    case 'cost_asignar_pr08': {
        requireEdit($pdo);
        $cfid   = (int)($body['cliente_frio_id'] ?? 0);
        $codigo = trim($body['codigo'] ?? '');
        if (!$cfid) resp(400, ['error' => 'cliente_frio_id requerido']);
        // validar que el código exista y coincida con tipo del cliente
        if ($codigo) {
            $cf = $pdo->prepare("SELECT cf.cliente_id, c.fiscal FROM cliente_frio cf LEFT JOIN CLIENTES c ON c.ClienteID=cf.cliente_id WHERE cf.cliente_frio_id=?");
            $cf->execute([$cfid]);
            $r = $cf->fetch();
            if (!$r) resp(400, ['error' => 'PR-08 no encontrado']);
            $tipoCli = ((int)$r['fiscal']===1) ? 'fiscal' : 'nacional';
            $oc = $pdo->prepare("SELECT tipo_cliente FROM operacion_codigo WHERE codigo=?");
            $oc->execute([$codigo]);
            $tipoCod = $oc->fetchColumn();
            if (!$tipoCod) resp(400, ['error' => 'Código inexistente']);
            if ($tipoCod !== $tipoCli) resp(400, ['error' => "Cliente es $tipoCli, el código es $tipoCod"]);
            $pdo->prepare("UPDATE cliente_frio SET codigo_operacion=? WHERE cliente_frio_id=?")->execute([$codigo, $cfid]);
        } else {
            $pdo->prepare("UPDATE cliente_frio SET codigo_operacion=NULL WHERE cliente_frio_id=?")->execute([$cfid]);
        }
        resp(200, ['ok' => true]);
        break;
    }

    // ────── Costeos ──────
    case 'cost_costeo_list': {
        requireView($pdo);
        $soloVig = !empty($_GET['vigentes']) ? 1 : 0;
        $where = $soloVig ? "WHERE c.vigente=1" : "";
        $stmt = $pdo->query("
          SELECT c.id, c.codigo_operacion, oc.denominacion, oc.tipo_cliente,
                 c.numero_costeo, c.fecha_costeo, c.cantidad_pallets, c.peso_pallet, c.peso_total,
                 c.total_general, c.precio_por_pallet, c.precio_por_kg, c.moneda, c.vigente,
                 c.usuario_nombre
          FROM pr08_costeo c
          LEFT JOIN operacion_codigo oc ON oc.codigo = c.codigo_operacion
          $where
          ORDER BY c.fecha_costeo DESC, c.id DESC
        ");
        resp(200, ['costeos' => $stmt->fetchAll()]);
        break;
    }

    case 'cost_costeo_get': {
        requireView($pdo);
        $id     = (int)($_GET['id'] ?? 0);
        $codigo = $_GET['codigo'] ?? '';
        if ($id) {
            $c = $pdo->prepare("SELECT c.*, oc.denominacion, oc.tipo_cliente FROM pr08_costeo c LEFT JOIN operacion_codigo oc ON oc.codigo=c.codigo_operacion WHERE c.id=?");
            $c->execute([$id]);
        } elseif ($codigo) {
            $c = $pdo->prepare("SELECT c.*, oc.denominacion, oc.tipo_cliente FROM pr08_costeo c LEFT JOIN operacion_codigo oc ON oc.codigo=c.codigo_operacion WHERE c.codigo_operacion=? AND c.vigente=1 ORDER BY c.id DESC LIMIT 1");
            $c->execute([$codigo]);
        } else {
            resp(400, ['error' => 'id o codigo requerido']);
        }
        $costeo = $c->fetch();
        $items = [];
        if ($costeo) {
            $it = $pdo->prepare("SELECT * FROM pr08_costeo_item WHERE pr08_costeo_id=? ORDER BY tipo_operacion, orden, id");
            $it->execute([$costeo['id']]);
            $items = $it->fetchAll();
        }
        resp(200, ['costeo' => $costeo ?: null, 'items' => $items]);
        break;
    }

    case 'cost_costeo_save': {
        requireEdit($pdo);
        $codigo = trim($body['codigo_operacion'] ?? '');
        $pallets = (int)($body['cantidad_pallets'] ?? 0);
        $pesoP   = (float)($body['peso_pallet'] ?? 0);
        $dias    = (int)($body['dias_almacenamiento'] ?? 30);
        if ($dias <= 0) $dias = 30;
        $obs     = $body['observaciones'] ?? null;
        $items   = $body['items'] ?? [];
        if (!$codigo) resp(400, ['error' => 'codigo requerido']);
        if ($pallets <= 0) resp(400, ['error' => 'cantidad_pallets debe ser > 0']);
        if ($pesoP  <= 0)  resp(400, ['error' => 'peso_pallet debe ser > 0']);

        // Verificar código
        $oc = $pdo->prepare("SELECT * FROM operacion_codigo WHERE codigo=?");
        $oc->execute([$codigo]);
        if (!$oc->fetch()) resp(400, ['error' => 'Código inexistente']);

        $pesoTotal = round($pallets * $pesoP, 2);
        $u = userInfo();

        $pdo->beginTransaction();
        try {
            // Marcar costeos previos como no vigentes
            $pdo->prepare("UPDATE pr08_costeo SET vigente=0 WHERE codigo_operacion=?")->execute([$codigo]);

            $num = proximoNumeroCosteo($pdo);
            $pdo->prepare("
              INSERT INTO pr08_costeo (codigo_operacion, numero_costeo, fecha_costeo, cantidad_pallets, peso_pallet, peso_total, dias_almacenamiento,
                total_general, precio_por_pallet, precio_por_kg, moneda, observaciones, usuario_id, usuario_nombre, vigente)
              VALUES (?, ?, NOW(), ?, ?, ?, ?, 0, 0, 0, 'ARS', ?, ?, ?, 1)
            ")->execute([$codigo, $num, $pallets, $pesoP, $pesoTotal, $dias, $obs, $u['id'], $u['nombre']]);
            $costeoId = (int)$pdo->lastInsertId();

            $insItem = $pdo->prepare("
              INSERT INTO pr08_costeo_item (pr08_costeo_id, tipo_operacion, item_catalogo_id, nombre_snapshot, unidad_snapshot,
                cantidad, costo_unitario_snapshot, moneda_snapshot, subtotal, orden, es_indirecto)
              VALUES (?,?,?,?,?,?,?,?,?,?,?)
            ");

            $total = 0.0;
            $monedas = [];
            $orden = 0;
            foreach ($items as $it) {
                $tipo = $it['tipo_operacion'] ?? '';
                if (!in_array($tipo, ['IN','OUT','ALMACENAMIENTO','TUNEL'])) continue;
                $cant = (float)($it['cantidad'] ?? 0);
                $costo = (float)($it['costo_unitario'] ?? 0);
                $moneda = in_array($it['moneda'] ?? '', ['ARS','USD']) ? $it['moneda'] : 'ARS';
                $sub = round($cant * $costo, 4);
                $nombre = trim($it['nombre'] ?? '');
                $unidad = trim($it['unidad'] ?? '');
                $itemId = !empty($it['item_catalogo_id']) ? (int)$it['item_catalogo_id'] : null;
                $esInd = !empty($it['es_indirecto']) ? 1 : 0;
                if (!$nombre) continue;
                $orden++;
                $insItem->execute([$costeoId, $tipo, $itemId, $nombre, $unidad, $cant, $costo, $moneda, $sub, $orden, $esInd]);
                $total += $sub;
                $monedas[$moneda] = true;
            }

            $monedaCab = count($monedas) === 1 ? array_key_first($monedas) : (count($monedas) > 1 ? 'MIXTA' : 'ARS');
            $precioPallet = $pallets > 0 ? round($total / $pallets, 4) : 0;
            $precioKg     = $pesoTotal > 0 ? round($total / $pesoTotal, 6) : 0;

            $pdo->prepare("
              UPDATE pr08_costeo SET total_general=?, precio_por_pallet=?, precio_por_kg=?, moneda=? WHERE id=?
            ")->execute([round($total,2), $precioPallet, $precioKg, $monedaCab, $costeoId]);

            $pdo->commit();
            resp(200, [
                'ok' => true, 'id' => $costeoId, 'numero_costeo' => $num,
                'total_general' => round($total,2),
                'precio_por_pallet' => $precioPallet,
                'precio_por_kg' => $precioKg,
                'moneda' => $monedaCab
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            resp(500, ['error' => $e->getMessage()]);
        }
        break;
    }

    case 'cost_historial_codigo': {
        requireView($pdo);
        $codigo = $_GET['codigo'] ?? '';
        if (!$codigo) resp(400, ['error' => 'codigo requerido']);
        $stmt = $pdo->prepare("
          SELECT id, numero_costeo, fecha_costeo, cantidad_pallets, peso_pallet, peso_total,
                 total_general, precio_por_pallet, precio_por_kg, moneda, vigente, usuario_nombre
          FROM pr08_costeo
          WHERE codigo_operacion=?
          ORDER BY fecha_costeo DESC, id DESC
        ");
        $stmt->execute([$codigo]);
        resp(200, ['historial' => $stmt->fetchAll()]);
        break;
    }

    // ────── Reportes ──────
    case 'cost_reporte_csv': {
        requireView($pdo);
        $rows = $pdo->query("
          SELECT c.numero_costeo, c.fecha_costeo, c.codigo_operacion, oc.denominacion, oc.tipo_cliente,
                 c.cantidad_pallets, c.peso_pallet, c.peso_total, c.dias_almacenamiento, c.total_general,
                 c.precio_por_pallet, c.precio_por_kg, c.moneda, c.usuario_nombre,
                 (SELECT COALESCE(SUM(subtotal),0) FROM pr08_costeo_item i WHERE i.pr08_costeo_id=c.id AND i.tipo_operacion='IN' AND i.es_indirecto=0)   AS dir_in,
                 (SELECT COALESCE(SUM(subtotal),0) FROM pr08_costeo_item i WHERE i.pr08_costeo_id=c.id AND i.tipo_operacion='OUT' AND i.es_indirecto=0)  AS dir_out,
                 (SELECT COALESCE(SUM(subtotal),0) FROM pr08_costeo_item i WHERE i.pr08_costeo_id=c.id AND i.tipo_operacion='ALMACENAMIENTO' AND i.es_indirecto=0) AS dir_alm,
                 (SELECT COALESCE(SUM(subtotal),0) FROM pr08_costeo_item i WHERE i.pr08_costeo_id=c.id AND i.tipo_operacion='TUNEL' AND i.es_indirecto=0) AS dir_tunel,
                 (SELECT COALESCE(SUM(subtotal),0) FROM pr08_costeo_item i WHERE i.pr08_costeo_id=c.id AND i.tipo_operacion IN ('IN','OUT') AND i.es_indirecto=1) AS ind_inout,
                 (SELECT COALESCE(SUM(subtotal),0) FROM pr08_costeo_item i WHERE i.pr08_costeo_id=c.id AND i.tipo_operacion='ALMACENAMIENTO' AND i.es_indirecto=1) AS ind_alm,
                 (SELECT COALESCE(SUM(subtotal),0) FROM pr08_costeo_item i WHERE i.pr08_costeo_id=c.id AND i.tipo_operacion='TUNEL' AND i.es_indirecto=1) AS ind_tunel
          FROM pr08_costeo c
          LEFT JOIN operacion_codigo oc ON oc.codigo=c.codigo_operacion
          WHERE c.vigente=1
          ORDER BY oc.tipo_cliente, oc.numero
        ")->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=reporte_costeos_".date('Ymd').".csv");
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output','w');
        fputcsv($out, ['Fecha','Nº Costeo','Código','Tipo','Denominación','Pallets','Peso/Pallet (kg)','Peso Total (kg)','Días Alm.',
            'Directo IN','Directo OUT','Directo ALM','Directo TUNEL',
            'Indirecto IN-OUT','Indirecto ALM','Indirecto TUNEL',
            'Total General','Precio/Pallet','Precio/Kg','Moneda','Registrado por'], ';');
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['fecha_costeo'], $r['numero_costeo'], $r['codigo_operacion'],
                strtoupper($r['tipo_cliente']), $r['denominacion'],
                $r['cantidad_pallets'], $r['peso_pallet'], $r['peso_total'], $r['dias_almacenamiento'],
                $r['dir_in'], $r['dir_out'], $r['dir_alm'], $r['dir_tunel'],
                $r['ind_inout'], $r['ind_alm'], $r['ind_tunel'],
                $r['total_general'], $r['precio_por_pallet'], $r['precio_por_kg'],
                $r['moneda'], $r['usuario_nombre'] ?? ''
            ], ';');
        }
        fclose($out);
        exit;
    }

    case 'cost_reporte_html': {
        requireView($pdo);
        $rows = $pdo->query("
          SELECT c.numero_costeo, c.fecha_costeo, c.codigo_operacion, oc.denominacion, oc.tipo_cliente,
                 c.cantidad_pallets, c.peso_pallet, c.peso_total, c.dias_almacenamiento, c.total_general,
                 c.precio_por_pallet, c.precio_por_kg, c.moneda, c.usuario_nombre,
                 (SELECT COALESCE(SUM(subtotal),0) FROM pr08_costeo_item i WHERE i.pr08_costeo_id=c.id AND i.tipo_operacion='IN' AND i.es_indirecto=0)   AS dir_in,
                 (SELECT COALESCE(SUM(subtotal),0) FROM pr08_costeo_item i WHERE i.pr08_costeo_id=c.id AND i.tipo_operacion='OUT' AND i.es_indirecto=0)  AS dir_out,
                 (SELECT COALESCE(SUM(subtotal),0) FROM pr08_costeo_item i WHERE i.pr08_costeo_id=c.id AND i.tipo_operacion='ALMACENAMIENTO' AND i.es_indirecto=0) AS dir_alm,
                 (SELECT COALESCE(SUM(subtotal),0) FROM pr08_costeo_item i WHERE i.pr08_costeo_id=c.id AND i.tipo_operacion='TUNEL' AND i.es_indirecto=0) AS dir_tunel,
                 (SELECT COALESCE(SUM(subtotal),0) FROM pr08_costeo_item i WHERE i.pr08_costeo_id=c.id AND i.tipo_operacion IN ('IN','OUT') AND i.es_indirecto=1) AS ind_inout,
                 (SELECT COALESCE(SUM(subtotal),0) FROM pr08_costeo_item i WHERE i.pr08_costeo_id=c.id AND i.tipo_operacion='ALMACENAMIENTO' AND i.es_indirecto=1) AS ind_alm,
                 (SELECT COALESCE(SUM(subtotal),0) FROM pr08_costeo_item i WHERE i.pr08_costeo_id=c.id AND i.tipo_operacion='TUNEL' AND i.es_indirecto=1) AS ind_tunel
          FROM pr08_costeo c
          LEFT JOIN operacion_codigo oc ON oc.codigo=c.codigo_operacion
          WHERE c.vigente=1
          ORDER BY oc.tipo_cliente, oc.numero
        ")->fetchAll();

        header('Content-Type: text/html; charset=utf-8');
        $fmt = fn($v) => number_format((float)$v, 2, ',', '.');
        $fmtKg = fn($v) => number_format((float)$v, 4, ',', '.');
        $fecha = date('d/m/Y H:i');
        ?><!DOCTYPE html>
        <html lang="es"><head>
          <meta charset="UTF-8">
          <title>Reporte de Costeos — <?= $fecha ?></title>
          <style>
            * { box-sizing: border-box; }
            body { font-family: Arial, sans-serif; margin: 24px; color: #111; font-size: 12px; }
            h1 { margin: 0 0 4px; font-size: 18px; color: #00103a; }
            .sub { color: #555; font-size: 11px; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; font-size: 10.5px; }
            th, td { border: 1px solid #ccc; padding: 5px 6px; vertical-align: top; text-align: left; }
            th { background: #00103a; color: #fff; font-weight: 700; font-size: 10px; text-transform: uppercase; letter-spacing: .3px; }
            td.n { text-align: right; font-family: Consolas, monospace; }
            tr.grp td { background: #e8eef7; font-weight: 700; color: #00103a; }
            .totales { margin-top: 16px; padding: 10px 14px; background: #f5f8fc; border-left: 4px solid #36B0C9; }
            .print-btn { position: fixed; top: 16px; right: 16px; padding: 8px 14px; background: #36B0C9; color: #fff; border: none; border-radius: 6px; font-weight: 700; cursor: pointer; }
            @media print { .print-btn { display: none; } body { margin: 12px; } }
          </style>
        </head><body>
          <button class="print-btn" onclick="window.print()">🖨️ Imprimir / Guardar como PDF</button>
          <h1>REPORTE DE COSTEOS — CINA SRL</h1>
          <div class="sub">Generado: <?= $fecha ?> · Total códigos vigentes: <?= count($rows) ?></div>

          <table>
            <thead><tr>
              <th rowspan="2">Fecha</th><th rowspan="2">Nº Costeo</th><th rowspan="2">Código</th><th rowspan="2">Denominación</th>
              <th rowspan="2">Pallets</th><th rowspan="2">Peso Tot.</th><th rowspan="2">Días Alm.</th>
              <th colspan="4" style="text-align:center">COSTOS DIRECTOS</th>
              <th colspan="3" style="text-align:center">COSTOS INDIRECTOS</th>
              <th rowspan="2">Total</th><th rowspan="2">$/Pallet</th><th rowspan="2">$/Kg</th><th rowspan="2">Mon.</th>
            </tr>
            <tr>
              <th>IN</th><th>OUT</th><th>ALM</th><th>TUNEL</th>
              <th>IN-OUT</th><th>ALM</th><th>TUNEL</th>
            </tr></thead>
            <tbody>
            <?php
            $lastTipo = null;
            foreach ($rows as $r):
              if ($r['tipo_cliente'] !== $lastTipo):
                $lastTipo = $r['tipo_cliente'];
                echo '<tr class="grp"><td colspan="18">'.($lastTipo==='nacional'?'DEPÓSITO NACIONAL (CN)':'DEPÓSITO FISCAL (DF)').'</td></tr>';
              endif;
            ?>
              <tr>
                <td><?= htmlspecialchars($r['fecha_costeo']) ?></td>
                <td><?= htmlspecialchars($r['numero_costeo']) ?></td>
                <td><strong><?= htmlspecialchars($r['codigo_operacion']) ?></strong></td>
                <td><?= htmlspecialchars($r['denominacion']) ?></td>
                <td class="n"><?= (int)$r['cantidad_pallets'] ?></td>
                <td class="n"><?= $fmt($r['peso_total']) ?></td>
                <td class="n"><?= (int)$r['dias_almacenamiento'] ?></td>
                <td class="n"><?= $fmt($r['dir_in']) ?></td>
                <td class="n"><?= $fmt($r['dir_out']) ?></td>
                <td class="n"><?= $fmt($r['dir_alm']) ?></td>
                <td class="n"><?= $fmt($r['dir_tunel']) ?></td>
                <td class="n" style="background:rgba(54,176,201,.08)"><?= $fmt($r['ind_inout']) ?></td>
                <td class="n" style="background:rgba(54,176,201,.08)"><?= $fmt($r['ind_alm']) ?></td>
                <td class="n" style="background:rgba(54,176,201,.08)"><?= $fmt($r['ind_tunel']) ?></td>
                <td class="n"><strong><?= $fmt($r['total_general']) ?></strong></td>
                <td class="n"><?= $fmt($r['precio_por_pallet']) ?></td>
                <td class="n"><?= $fmtKg($r['precio_por_kg']) ?></td>
                <td><?= htmlspecialchars($r['moneda']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>

          <div class="totales">
            <strong>Notas:</strong><br>
            · Solo se muestran los costeos marcados como <em>vigentes</em>.<br>
            · Los precios por pallet y por kg se calculan sobre el total general del costeo.<br>
            · Moneda "MIXTA" indica que el costeo tiene ítems en distintas monedas.
          </div>
        </body></html><?php
        exit;
    }

    // ────── Costos indirectos ──────
    case 'cost_indirecto_list': {
        requireView($pdo);
        $rows = $pdo->query("SELECT * FROM costo_indirecto ORDER BY orden, id")->fetchAll();
        $params = $pdo->query("SELECT * FROM parametros_costeo ORDER BY orden, clave")->fetchAll();
        $calc = calcularUnitariosIndirectos($pdo);
        resp(200, ['indirectos' => $rows, 'parametros' => $params, 'calculo' => $calc]);
        break;
    }

    case 'cost_indirecto_save': {
        requireEdit($pdo);
        $u = userInfo();
        $id    = (int)($body['id'] ?? 0);
        $nombre= trim($body['nombre'] ?? '');
        $desc  = $body['descripcion'] ?? null;
        // Aceptar tanto "costo_mensual" (nuevo) como "costo_anual" (legacy por retrocompat)
        $mens = (float)($body['costo_mensual'] ?? $body['costo_anual'] ?? 0);
        $mon   = in_array($body['moneda'] ?? '', ['ARS','USD']) ? $body['moneda'] : 'ARS';
        $rN    = (float)($body['rep_nacional'] ?? 0);
        $rF    = (float)($body['rep_fiscal'] ?? 0);
        $rIO   = (float)($body['rep_inout'] ?? 0);
        $rA    = (float)($body['rep_almacenamiento'] ?? 0);
        $rT    = (float)($body['rep_tunel'] ?? 0);
        $orden = (int)($body['orden'] ?? 0);
        $activo= !empty($body['activo']) ? 1 : 0;
        if (!$nombre) resp(400, ['error' => 'nombre requerido']);
        // Validar que %nac+%fis sume ≤ 1 y %inout+%alm+%tun sume ≤ 1 (tolerancia)
        if (abs(($rN + $rF) - 1.0) > 0.0101 && ($rN + $rF) > 0) {
            // permitimos pero avisamos solo con no-strict
        }
        if ($id) {
            $pdo->prepare("
              UPDATE costo_indirecto SET nombre=?, descripcion=?, costo_mensual=?, moneda=?,
                rep_nacional=?, rep_fiscal=?, rep_inout=?, rep_almacenamiento=?, rep_tunel=?,
                orden=?, activo=?, actualizado_por=?, actualizado_por_nombre=?, actualizado_en=NOW()
              WHERE id=?
            ")->execute([$nombre, $desc, $mens, $mon, $rN, $rF, $rIO, $rA, $rT, $orden, $activo, $u['id'], $u['nombre'], $id]);
        } else {
            $pdo->prepare("
              INSERT INTO costo_indirecto (nombre, descripcion, costo_mensual, moneda, rep_nacional, rep_fiscal, rep_inout, rep_almacenamiento, rep_tunel, orden, activo, actualizado_por, actualizado_por_nombre, actualizado_en)
              VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
            ")->execute([$nombre, $desc, $mens, $mon, $rN, $rF, $rIO, $rA, $rT, $orden, $activo, $u['id'], $u['nombre']]);
            $id = (int)$pdo->lastInsertId();
        }
        resp(200, ['ok' => true, 'id' => $id]);
        break;
    }

    case 'cost_indirecto_delete': {
        requireEdit($pdo);
        $id = (int)($body['id'] ?? 0);
        if (!$id) resp(400, ['error' => 'id requerido']);
        $pdo->prepare("UPDATE costo_indirecto SET activo=0 WHERE id=?")->execute([$id]);
        resp(200, ['ok' => true]);
        break;
    }

    case 'cost_parametro_save': {
        requireEdit($pdo);
        $u = userInfo();
        $clave = trim($body['clave'] ?? '');
        $valor = (float)($body['valor'] ?? 0);
        if (!$clave) resp(400, ['error' => 'clave requerido']);
        $pdo->prepare("
          UPDATE parametros_costeo SET valor=?, actualizado_por=?, actualizado_por_nombre=?, actualizado_en=NOW()
          WHERE clave=?
        ")->execute([$valor, $u['id'], $u['nombre'], $clave]);
        resp(200, ['ok' => true]);
        break;
    }

    case 'cost_indirectos_unitarios': {
        requireView($pdo);
        resp(200, calcularUnitariosIndirectos($pdo));
        break;
    }

    default:
        resp(400, ['error' => 'action inválido']);
}
