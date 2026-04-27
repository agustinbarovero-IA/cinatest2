<?php
/**
 * ============================================================
 *  api_encuestas_pr08.php — Encuesta de Satisfacción + PR-08/B
 *  Grupo CINA
 * ============================================================
 *  1) Encuesta de satisfacción anual (PR-105)
 *     Tablas: encuesta_config, encuesta_pregunta, encuesta_respuesta
 *     Config desde COMERCIAL Y PLANIFICACION → ENCUESTA SATISFACCION
 *
 *  2) PR-08 (nacional) y PR-08B (fiscal)
 *     Las respuestas se almacenan en la tabla EXISTENTE `cliente_frio`
 *     (histórico con +480 registros desde 2015).
 *     Config desde CALIDAD → PR 08 CONDICIONES DE CONTRATACION
 *     Tablas adicionales:
 *       pr08_plantilla      — config por tipo (nacional/fiscal)
 *       pr08_postergacion   — "recordar más tarde" por cliente/tipo
 *
 *  Permisos especiales (rol_permisos_extra):
 *     encuestas_editar
 *     pr08_editar
 *     cliente_portal
 * ============================================================
 */

require_once dirname(__DIR__) . '/config.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (empty($_SESSION['cina_usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Sesión expirada. Recargá la página.']);
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
//  WHITELIST de columnas escribibles en cliente_frio
//  (EXACTAMENTE las columnas existentes de cliente_frio que
//   puede modificar el cliente desde el formulario)
// ═════════════════════════════════════════════════════════════
function columnasValidasClienteFrio(): array {
    return [
        // Despachante
        'despachante_nombre','despachante_cuit','despachante_telefono',
        'despachante_domicilio','despachante_email',
        // Tratamiento/servicio
        'tratamiento_producto','servicio_logistico','servicio_otra',
        'almacenamiento_carga',
        // Producto
        'tipo_producto','tipo_producto_otros','descripcion_producto',
        // Temperaturas
        'temp_entrada_desde','temp_entrada_hasta',
        'temp_almacenamiento_desde','temp_almacenamiento_hasta',
        'temp_salida_desde','temp_salida_hasta',
        'temp_producto','temp_entrada_producto',
        // HACCP
        'accion_correctiva',
        'haccp_recepcion','haccp_recepcion_desc',
        'haccp_almacenamiento','haccp_almacenamiento_desc',
        'haccp_acondicionamiento','haccp_acondicionamiento_desc',
        'haccp_despacho','haccp_despacho_desc',
        // Carga
        'caracteristicas_carga','instrucciones_carga',
        'cantidad_producto','cantidad_muestras','tipo_toma_temperatura',
        'vida_util',
        'tipo_carga','esp_carga',
        // Transporte
        'tipo_transporte',
        'camion_i','camion_j','camion_k','camion_l','camion_m',
        // Almacenamiento requerido
        'tipo_almacenamiento','cantidad_apilar','peso_maximo','peso_minimo',
        // Frecuencia
        'frecuencia_carga','tiempo_almacenamiento','fecha_est_comienzo',
        // Trazabilidad (no la edita el cliente, pero se llena server-side)
        // 'trato_cliente','fecha_registro','usuario_id','created_at','updated_at'
    ];
}

// Plantilla por defecto de PR-08 (secciones y campos) — mapeados 1:1 a cliente_frio
// tipo: text|textarea|number|decimal|date|radio|checkbox|checkbox_single
function plantillaBasePr08(): array {
    $tempCargas = ['Bolsas','Pallets','Cajas','Chapas','Bobinas','Fardos','Maquinarias','Automóviles','Tambores','Insumos','Alimentos','Otros'];
    $servicios  = ['Desconsolidación de cargas de importación','Recepción de cargas para exportar','Almacenamiento provisorio y acondicionamiento','Retiro de la carga con destinación provisoria o definitiva','Consolidación de cargas de exportación'];
    $transporte = ['Contenedor Reefer 20','Contenedor Reefer 40','Contenedor Dry 20','Contenedor Dry 40','Camión Int. carga Seca','Camión Int. carga Refrigerada'];

    return [
        'secciones' => [
            [
                'titulo' => 'Despachante Autorizado (si aplica)',
                'campos' => [
                    ['col'=>'despachante_nombre','label'=>'Nombre y Apellido','tipo'=>'text','obligatorio'=>0],
                    ['col'=>'despachante_cuit','label'=>'CUIT','tipo'=>'text','obligatorio'=>0],
                    ['col'=>'despachante_telefono','label'=>'Teléfono','tipo'=>'text','obligatorio'=>0],
                    ['col'=>'despachante_email','label'=>'E-mail','tipo'=>'text','obligatorio'=>0],
                    ['col'=>'despachante_domicilio','label'=>'Domicilio','tipo'=>'text','obligatorio'=>0],
                ],
            ],
            [
                'titulo' => 'Servicio Logístico Demandado',
                'campos' => [
                    ['col'=>'servicio_logistico','label'=>'Servicios requeridos','tipo'=>'checkbox','opciones'=>$servicios,'obligatorio'=>0,'coding'=>'letters'],
                    ['col'=>'servicio_otra','label'=>'Otro servicio (especificar)','tipo'=>'text','obligatorio'=>0],
                ],
            ],
            [
                'titulo' => 'Tipo de Producto',
                'campos' => [
                    ['col'=>'almacenamiento_carga','label'=>'Almacenamiento de la carga','tipo'=>'radio','opciones'=>['Ambiente / Seco','Refrigerado','Congelado'],'obligatorio'=>1,'coding'=>'index1'],
                    ['col'=>'tipo_producto','label'=>'Tipo de producto','tipo'=>'radio','opciones'=>['Producto alimenticio','Producto no alimenticio','Otros'],'obligatorio'=>1,'coding'=>'index1'],
                    ['col'=>'tipo_producto_otros','label'=>'Otros (especificar)','tipo'=>'text','obligatorio'=>0],
                    ['col'=>'descripcion_producto','label'=>'Descripción del producto','tipo'=>'textarea','obligatorio'=>1],
                    ['col'=>'cantidad_producto','label'=>'Cantidad de producto (toneladas / m³ / unidades)','tipo'=>'text','obligatorio'=>0],
                    ['col'=>'vida_util','label'=>'Vida útil','tipo'=>'text','obligatorio'=>0],
                ],
            ],
            [
                'titulo' => 'Temperaturas (°C)',
                'campos' => [
                    ['col'=>'temp_entrada_desde','label'=>'Temperatura entrada (desde)','tipo'=>'decimal','obligatorio'=>0],
                    ['col'=>'temp_entrada_hasta','label'=>'Temperatura entrada (hasta)','tipo'=>'decimal','obligatorio'=>0],
                    ['col'=>'temp_almacenamiento_desde','label'=>'Temperatura almacenamiento (desde)','tipo'=>'decimal','obligatorio'=>0],
                    ['col'=>'temp_almacenamiento_hasta','label'=>'Temperatura almacenamiento (hasta)','tipo'=>'decimal','obligatorio'=>0],
                    ['col'=>'temp_salida_desde','label'=>'Temperatura salida (desde)','tipo'=>'decimal','obligatorio'=>0],
                    ['col'=>'temp_salida_hasta','label'=>'Temperatura salida (hasta)','tipo'=>'decimal','obligatorio'=>0],
                    ['col'=>'temp_producto','label'=>'Temperatura del producto (observación libre)','tipo'=>'text','obligatorio'=>0],
                    ['col'=>'temp_entrada_producto','label'=>'Temperatura de entrada (obs.)','tipo'=>'text','obligatorio'=>0],
                ],
            ],
            [
                'titulo' => 'HACCP / Puntos de control',
                'campos' => [
                    ['col'=>'haccp_recepcion','label'=>'¿Aplica HACCP en recepción?','tipo'=>'checkbox_single','obligatorio'=>0],
                    ['col'=>'haccp_recepcion_desc','label'=>'Descripción HACCP recepción','tipo'=>'text','obligatorio'=>0],
                    ['col'=>'haccp_almacenamiento','label'=>'¿Aplica HACCP en almacenamiento?','tipo'=>'checkbox_single','obligatorio'=>0],
                    ['col'=>'haccp_almacenamiento_desc','label'=>'Descripción HACCP almacenamiento','tipo'=>'text','obligatorio'=>0],
                    ['col'=>'haccp_acondicionamiento','label'=>'¿Aplica HACCP en acondicionamiento?','tipo'=>'checkbox_single','obligatorio'=>0],
                    ['col'=>'haccp_acondicionamiento_desc','label'=>'Descripción HACCP acondicionamiento','tipo'=>'text','obligatorio'=>0],
                    ['col'=>'haccp_despacho','label'=>'¿Aplica HACCP en despacho?','tipo'=>'checkbox_single','obligatorio'=>0],
                    ['col'=>'haccp_despacho_desc','label'=>'Descripción HACCP despacho','tipo'=>'text','obligatorio'=>0],
                    ['col'=>'accion_correctiva','label'=>'Acción correctiva (código interno)','tipo'=>'text','obligatorio'=>0],
                ],
            ],
            [
                'titulo' => 'Características de la Carga',
                'campos' => [
                    ['col'=>'caracteristicas_carga','label'=>'Característica','tipo'=>'radio','opciones'=>['Frágil','Perecedera','Peligrosa','General'],'obligatorio'=>0,'coding'=>'index1'],
                    ['col'=>'instrucciones_carga','label'=>'Instrucciones para manipuleo','tipo'=>'textarea','obligatorio'=>0],
                    ['col'=>'tipo_carga','label'=>'Tipo de carga','tipo'=>'checkbox','opciones'=>$tempCargas,'obligatorio'=>1,'coding'=>'letters'],
                    ['col'=>'esp_carga','label'=>'Especificaciones adicionales de carga','tipo'=>'text','obligatorio'=>0],
                ],
            ],
            [
                'titulo' => 'Muestreo',
                'campos' => [
                    ['col'=>'cantidad_muestras','label'=>'Cantidad de muestras (sugerido 3)','tipo'=>'number','obligatorio'=>0],
                    ['col'=>'tipo_toma_temperatura','label'=>'Tipo de toma de temperatura','tipo'=>'text','obligatorio'=>0],
                ],
            ],
            [
                'titulo' => 'Transporte',
                'campos' => [
                    ['col'=>'tipo_transporte','label'=>'Tipo de transporte','tipo'=>'checkbox','opciones'=>$transporte,'obligatorio'=>0,'coding'=>'letters'],
                    ['col'=>'camion_i','label'=>'Cantidad: Reefer 20','tipo'=>'number','obligatorio'=>0],
                    ['col'=>'camion_j','label'=>'Cantidad: Reefer 40','tipo'=>'number','obligatorio'=>0],
                    ['col'=>'camion_k','label'=>'Cantidad: Dry 20','tipo'=>'number','obligatorio'=>0],
                    ['col'=>'camion_l','label'=>'Cantidad: Dry 40','tipo'=>'number','obligatorio'=>0],
                    ['col'=>'camion_m','label'=>'Cantidad: Camión Internacional','tipo'=>'number','obligatorio'=>0],
                ],
            ],
            [
                'titulo' => 'Almacenamiento Requerido',
                'campos' => [
                    ['col'=>'tipo_almacenamiento','label'=>'Tipo de almacenamiento','tipo'=>'checkbox','opciones'=>['Pallet','Rack','Bins','Piso','Otros'],'obligatorio'=>0,'coding'=>'letters'],
                    ['col'=>'cantidad_apilar','label'=>'Cantidad máxima para apilar','tipo'=>'text','obligatorio'=>0],
                    ['col'=>'peso_maximo','label'=>'Peso máximo por unidad','tipo'=>'text','obligatorio'=>0],
                    ['col'=>'peso_minimo','label'=>'Peso mínimo por unidad','tipo'=>'text','obligatorio'=>0],
                ],
            ],
            [
                'titulo' => 'Frecuencia',
                'campos' => [
                    ['col'=>'frecuencia_carga','label'=>'Frecuencia de recepción / despacho','tipo'=>'radio','opciones'=>['Diaria','Semanal','Mensual','Semestral','Anual','Otra'],'obligatorio'=>1,'coding'=>'index1'],
                    ['col'=>'tiempo_almacenamiento','label'=>'Temporada / tiempo de almacenamiento','tipo'=>'text','obligatorio'=>0],
                    ['col'=>'fecha_est_comienzo','label'=>'Fecha estimativa de comienzo del servicio','tipo'=>'date','obligatorio'=>0],
                ],
            ],
        ],
    ];
}

// ═════════════════════════════════════════════════════════════
//  Codificación de valores (frontend ↔ cliente_frio)
//  Algunos campos guardan:
//   - 'letters'  → concatenación de letras (A..Z) ej "BCE"
//   - 'index1'   → 1-based del índice de opción seleccionada (radio)
// ═════════════════════════════════════════════════════════════
function encodeCampo(array $campo, $valor) {
    $coding = $campo['coding'] ?? null;
    $tipo = $campo['tipo'] ?? 'text';

    if ($valor === null) return null;

    if ($tipo === 'checkbox' && $coding === 'letters') {
        // valor = array de strings (opciones seleccionadas)
        $opciones = $campo['opciones'] ?? [];
        if (!is_array($valor)) return '';
        $out = '';
        foreach ($valor as $sel) {
            $idx = array_search($sel, $opciones, true);
            if ($idx !== false) $out .= chr(ord('A') + $idx); // A, B, C...
        }
        return $out;
    }

    if ($tipo === 'radio' && $coding === 'index1') {
        $opciones = $campo['opciones'] ?? [];
        $idx = array_search($valor, $opciones, true);
        return $idx === false ? null : (string)($idx + 1);
    }

    if ($tipo === 'checkbox_single') {
        return !empty($valor) ? 1 : 0;
    }

    if ($tipo === 'number' || $tipo === 'decimal') {
        if ($valor === '' || $valor === null) return null;
        return $tipo === 'number' ? (int)$valor : (float)$valor;
    }

    if ($tipo === 'date') {
        return ($valor === '' || $valor === null) ? null : $valor;
    }

    // text/textarea/otro: string limpio
    return is_array($valor) ? implode(', ', $valor) : (string)$valor;
}

// Decodifica un valor de cliente_frio al formato del formulario (para pre-cargar)
function decodeCampo(array $campo, $valor) {
    if ($valor === null) return null;
    $coding = $campo['coding'] ?? null;
    $tipo = $campo['tipo'] ?? 'text';

    if ($tipo === 'checkbox' && $coding === 'letters') {
        $opciones = $campo['opciones'] ?? [];
        $out = [];
        foreach (str_split((string)$valor) as $ch) {
            $idx = ord($ch) - ord('A');
            if ($idx >= 0 && $idx < count($opciones)) $out[] = $opciones[$idx];
        }
        return $out;
    }

    if ($tipo === 'radio' && $coding === 'index1') {
        $opciones = $campo['opciones'] ?? [];
        $idx = ((int)$valor) - 1;
        return ($idx >= 0 && $idx < count($opciones)) ? $opciones[$idx] : null;
    }

    if ($tipo === 'checkbox_single') {
        return ((int)$valor === 1);
    }

    return $valor;
}

// ═════════════════════════════════════════════════════════════
//  Migraciones idempotentes
// ═════════════════════════════════════════════════════════════
function migrar($pdo) {
    // Encuesta (sin cambios)
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS encuesta_config (
        id INT PRIMARY KEY,
        activa TINYINT(1) NOT NULL DEFAULT 0,
        anio_vigente SMALLINT NOT NULL,
        fecha_vencimiento DATE NULL,
        titulo VARCHAR(255) DEFAULT 'Encuesta de Satisfacción del Cliente',
        descripcion TEXT,
        actualizado_por INT NULL,
        actualizado_por_nombre VARCHAR(120) NULL,
        actualizado_en DATETIME NULL
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS encuesta_pregunta (
        id INT PRIMARY KEY AUTO_INCREMENT,
        orden INT NOT NULL DEFAULT 0,
        texto VARCHAR(500) NOT NULL,
        ayuda VARCHAR(500) NULL,
        tipo ENUM('radio','checkbox','texto','textarea','escala') NOT NULL DEFAULT 'radio',
        opciones TEXT NULL,
        obligatoria TINYINT(1) NOT NULL DEFAULT 1,
        permite_otros TINYINT(1) NOT NULL DEFAULT 0,
        activa TINYINT(1) NOT NULL DEFAULT 1
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    // ALTERs idempotentes para instalaciones previas
    try { $pdo->exec("ALTER TABLE encuesta_pregunta ADD COLUMN ayuda VARCHAR(500) NULL AFTER texto"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE encuesta_pregunta ADD COLUMN permite_otros TINYINT(1) NOT NULL DEFAULT 0 AFTER obligatoria"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE encuesta_pregunta MODIFY tipo ENUM('radio','checkbox','texto','textarea','escala') NOT NULL DEFAULT 'radio'"); } catch (Exception $e) {}
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS encuesta_respuesta (
        id INT PRIMARY KEY AUTO_INCREMENT,
        cliente_id MEDIUMINT NOT NULL,
        anio SMALLINT NOT NULL,
        usuario_id INT NULL,
        usuario_nombre VARCHAR(120) NULL,
        respuestas LONGTEXT NULL,
        fecha_respuesta DATETIME NULL,
        recordar_hasta DATETIME NULL,
        UNIQUE KEY uk_cli_anio (cliente_id, anio)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    foreach ([
        "ALTER TABLE encuesta_respuesta MODIFY fecha_respuesta DATETIME NULL",
        "ALTER TABLE encuesta_respuesta MODIFY respuestas LONGTEXT NULL",
    ] as $sql) { try { $pdo->exec($sql); } catch (Exception $e) {} }

    // PR-08: plantilla + postergación. Las respuestas van a `cliente_frio` (existente).
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS pr08_plantilla (
        id INT PRIMARY KEY AUTO_INCREMENT,
        tipo ENUM('nacional','fiscal') NOT NULL UNIQUE,
        activa TINYINT(1) NOT NULL DEFAULT 0,
        fecha_vencimiento DATE NULL,
        fecha_lanzamiento DATETIME NULL,
        titulo VARCHAR(255) NULL,
        campos LONGTEXT NULL,
        version INT NOT NULL DEFAULT 1,
        actualizado_por INT NULL,
        actualizado_por_nombre VARCHAR(120) NULL,
        actualizado_en DATETIME NULL
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    // ALTER idempotente por si existía la versión anterior sin fecha_lanzamiento
    try { $pdo->exec("ALTER TABLE pr08_plantilla ADD COLUMN fecha_lanzamiento DATETIME NULL AFTER fecha_vencimiento"); } catch (Exception $e) {}

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS pr08_postergacion (
        cliente_id MEDIUMINT NOT NULL,
        tipo ENUM('nacional','fiscal') NOT NULL,
        recordar_hasta DATETIME NOT NULL,
        PRIMARY KEY (cliente_id, tipo)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Limpieza: tabla anterior pr08_respuesta (si se creó, estaba vacía)
    try { $pdo->exec("DROP TABLE IF EXISTS pr08_respuesta"); } catch (Exception $e) {}

    // Seed de preguntas de encuesta (solo si está vacía)
    if ((int)$pdo->query("SELECT COUNT(*) FROM encuesta_pregunta")->fetchColumn() === 0) {
        sembrarPreguntasDefault($pdo);
    }

    if ((int)$pdo->query("SELECT COUNT(*) FROM encuesta_config")->fetchColumn() === 0) {
        $pdo->prepare("INSERT INTO encuesta_config (id, activa, anio_vigente, titulo, descripcion) VALUES (1, 0, ?, ?, ?)")
            ->execute([(int)date('Y'), 'Encuesta de Satisfacción del Cliente', 'Usted puede ayudarnos a mejorar nuestros servicios, le pedimos unos minutos de su tiempo para calificarnos. Gracias.']);
    }

    // Seed de plantillas PR-08
    if ((int)$pdo->query("SELECT COUNT(*) FROM pr08_plantilla")->fetchColumn() === 0) {
        $base = plantillaBasePr08();
        $stmt = $pdo->prepare("INSERT INTO pr08_plantilla (tipo, activa, titulo, campos, version) VALUES (?, 0, ?, ?, 1)");
        $stmt->execute(['nacional', 'PR-08 · Condiciones Generales de Contratación — Depósito Nacional', json_encode($base, JSON_UNESCAPED_UNICODE)]);
        $stmt->execute(['fiscal',   'PR-08B · Condiciones Generales de Contratación — Depósito Fiscal',   json_encode($base, JSON_UNESCAPED_UNICODE)]);
    }
}

// ═════════════════════════════════════════════════════════════
//  Preguntas por defecto de la encuesta (PDF "Encuesta de satisfacción" abril 2026)
// ═════════════════════════════════════════════════════════════
function preguntasEncuestaDefault(): array {
    // [texto, ayuda, tipo, opciones[], obligatoria, permite_otros]
    return [
        ['¿Cuál es el servicio que más utiliza?', null,
            'checkbox', ['Depósito Fiscal','Almacenamiento','Túnel','Crossdocking','Picking / Distribución'], 1, 1],

        ['¿Cómo calificaría la calidad del servicio de almacenamiento ofrecido?',
            'Escala de 1 a 10, considerando que 1 es muy poco satisfactorio y 10 muy satisfactorio',
            'escala', [], 1, 0],

        ['Respecto de la atención de los reclamos (tiempos de respuesta, validez y soporte de las respuestas), usted se encuentra:', null,
            'checkbox', ['Muy satisfecho','Satisfecho','Medianamente insatisfecho','Insatisfecho','Muy insatisfecho'], 1, 0],

        ['¿Cómo evaluaría la entrada y salida de sus productos almacenados?', null,
            'radio', ['Muy satisfactorio','Satisfactorio','Medianamente satisfactorio','Insatisfactorio','Muy insatisfactorio'], 1, 0],

        ['De haber experimentado algún inconveniente con los servicios de almacenamiento, ¿cómo se resolvió?', null,
            'radio', ['De manera rápida y eficaz','De manera lenta pero eficaz','De manera rápida pero poco eficaz','No se resolvió como esperaba'], 1, 1],

        ['De haber experimentado algún inconveniente, el mismo fue de:', null,
            'checkbox', ['Carga/Descarga','Temperatura','Estado de la mercadería','Comunicación','No tuve inconvenientes'], 1, 1],

        ['¿Recomendaría los servicios de CINA a otras empresas? ¿Por qué?', null,
            'textarea', [], 1, 0],

        ['De existir, seleccione los puntos donde considera que debemos mejorar',
            'Dejar sin marcar si no hay puntos a destacar',
            'checkbox', ['Tiempos de respuesta de mensajes','Canales de comunicación','Respuesta ante reclamos','Cargas/Descargas','Disponibilidad de turnos','Almacenaje','Informes enviados'], 0, 1],

        ['Nos sería de mucho agrado que pueda escribirnos algún comentario que nos ayude a seguir mejorando día a día', null,
            'textarea', [], 0, 0],
    ];
}

function sembrarPreguntasDefault(PDO $pdo): void {
    $stmt = $pdo->prepare("INSERT INTO encuesta_pregunta (orden, texto, ayuda, tipo, opciones, obligatoria, permite_otros, activa) VALUES (?,?,?,?,?,?,?,1)");
    foreach (preguntasEncuestaDefault() as $i => $p) {
        $stmt->execute([$i+1, $p[0], $p[1], $p[2], json_encode($p[3], JSON_UNESCAPED_UNICODE), $p[4], $p[5]]);
    }
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

function tienePermisoExtra($pdo, $permiso) {
    $u = userInfo();
    if (in_array($u['rol_id'], [1,5,10])) return true;
    $stmt = $pdo->prepare("SELECT valor FROM rol_permisos_extra WHERE rol_id=? AND permiso=?");
    $stmt->execute([$u['rol_id'], $permiso]);
    return (bool)$stmt->fetchColumn();
}

function clientesDelUsuario($pdo) {
    $u = userInfo();
    $stmt = $pdo->prepare("SELECT cliente_id FROM usuario_cliente WHERE usuario_id=?");
    $stmt->execute([$u['id']]);
    return array_map('intval', array_column($stmt->fetchAll(), 'cliente_id'));
}

function infoCliente($pdo, $cliente_id) {
    $stmt = $pdo->prepare("SELECT ClienteID, ClienteNombre, ClienteDireccion, ClienteLocalidad, ClienteProvincia, pais, codigo_postal, ClienteTel, email, ClienteContacto, ClienteCUIT, nro_iibb, fiscal FROM CLIENTES WHERE ClienteID=?");
    $stmt->execute([$cliente_id]);
    return $stmt->fetch();
}

function requireEditor($pdo, $permiso) {
    if (!tienePermisoExtra($pdo, $permiso)) {
        resp(403, ['error' => 'Sin permisos para esta acción']);
    }
}

// Última entrada de cliente_frio para un cliente
function ultimaClienteFrio($pdo, $cliente_id) {
    $stmt = $pdo->prepare("
      SELECT * FROM cliente_frio
      WHERE cliente_id = ?
      ORDER BY COALESCE(updated_at, created_at, fecha_registro) DESC, cliente_frio_id DESC
      LIMIT 1
    ");
    $stmt->execute([$cliente_id]);
    return $stmt->fetch();
}

// Timestamp efectivo de una fila cliente_frio (el mayor de updated_at/created_at/fecha_registro)
function tsCf(array $cf): ?string {
    $opts = array_filter([$cf['updated_at'] ?? null, $cf['created_at'] ?? null, $cf['fecha_registro'] ?? null]);
    if (!$opts) return null;
    sort($opts);
    return end($opts);
}

// ═════════════════════════════════════════════════════════════
//  Enrutador
// ═════════════════════════════════════════════════════════════
$action = $_GET['action'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($action) {

    // ─────────────────────────── ENCUESTA ────────────────────
    case 'encuesta_config_get':
        $cfg = $pdo->query("SELECT * FROM encuesta_config WHERE id=1")->fetch();
        resp(200, ['config' => $cfg ?: null]);
        break;

    case 'encuesta_config_save':
        requireEditor($pdo, 'encuestas_editar');
        $u = userInfo();
        $activa  = !empty($body['activa']) ? 1 : 0;
        $anio    = (int)($body['anio_vigente'] ?? date('Y'));
        $venc    = $body['fecha_vencimiento'] ?? null;
        $titulo  = trim($body['titulo'] ?? 'Encuesta de Satisfacción del Cliente');
        $desc    = trim($body['descripcion'] ?? '');
        $pdo->prepare("
            INSERT INTO encuesta_config (id, activa, anio_vigente, fecha_vencimiento, titulo, descripcion, actualizado_por, actualizado_por_nombre, actualizado_en)
            VALUES (1, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE activa=VALUES(activa), anio_vigente=VALUES(anio_vigente),
              fecha_vencimiento=VALUES(fecha_vencimiento), titulo=VALUES(titulo),
              descripcion=VALUES(descripcion), actualizado_por=VALUES(actualizado_por),
              actualizado_por_nombre=VALUES(actualizado_por_nombre), actualizado_en=NOW()
        ")->execute([$activa, $anio, $venc ?: null, $titulo, $desc, $u['id'], $u['nombre']]);
        resp(200, ['ok' => true]);
        break;

    case 'encuesta_preguntas_get':
        $preg = $pdo->query("SELECT id, orden, texto, ayuda, tipo, opciones, obligatoria, permite_otros, activa FROM encuesta_pregunta WHERE activa=1 ORDER BY orden, id")->fetchAll();
        foreach ($preg as &$p) { $p['opciones'] = json_decode($p['opciones'] ?? '[]', true) ?: []; }
        resp(200, ['preguntas' => $preg]);
        break;

    case 'encuesta_preguntas_save':
        requireEditor($pdo, 'encuestas_editar');
        $preguntas = $body['preguntas'] ?? [];
        $pdo->beginTransaction();
        try {
            $pdo->exec("UPDATE encuesta_pregunta SET activa=0");
            $upd = $pdo->prepare("UPDATE encuesta_pregunta SET orden=?, texto=?, ayuda=?, tipo=?, opciones=?, obligatoria=?, permite_otros=?, activa=1 WHERE id=?");
            $ins = $pdo->prepare("INSERT INTO encuesta_pregunta (orden, texto, ayuda, tipo, opciones, obligatoria, permite_otros, activa) VALUES (?,?,?,?,?,?,?,1)");
            foreach ($preguntas as $i => $p) {
                $id    = isset($p['id']) && $p['id'] ? (int)$p['id'] : null;
                $orden = (int)($p['orden'] ?? $i+1);
                $texto = trim($p['texto'] ?? '');
                $ayuda = trim($p['ayuda'] ?? '') ?: null;
                $tipo  = in_array($p['tipo'] ?? '', ['radio','checkbox','texto','textarea','escala']) ? $p['tipo'] : 'radio';
                $op    = json_encode($p['opciones'] ?? [], JSON_UNESCAPED_UNICODE);
                $oblig = !empty($p['obligatoria']) ? 1 : 0;
                $otros = !empty($p['permite_otros']) ? 1 : 0;
                if ($texto === '') continue;
                if ($id) $upd->execute([$orden, $texto, $ayuda, $tipo, $op, $oblig, $otros, $id]);
                else     $ins->execute([$orden, $texto, $ayuda, $tipo, $op, $oblig, $otros]);
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            resp(500, ['error' => $e->getMessage()]);
        }
        resp(200, ['ok' => true]);
        break;

    case 'encuesta_restaurar_default':
        requireEditor($pdo, 'encuestas_editar');
        $pdo->beginTransaction();
        try {
            // Desactivar todas las actuales (preserva respuestas históricas)
            $pdo->exec("UPDATE encuesta_pregunta SET activa=0");
            sembrarPreguntasDefault($pdo);
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            resp(500, ['error' => $e->getMessage()]);
        }
        resp(200, ['ok' => true, 'cantidad' => count(preguntasEncuestaDefault())]);
        break;

    case 'encuesta_estado_cliente':
        $cfg = $pdo->query("SELECT * FROM encuesta_config WHERE id=1")->fetch();
        if (!$cfg || !$cfg['activa']) resp(200, ['estado' => 'no_activa']);
        $cids = clientesDelUsuario($pdo);
        if (!$cids) resp(200, ['estado' => 'no_activa']);
        $cid = (int)$cids[0];
        $anio = (int)$cfg['anio_vigente'];
        $st = $pdo->prepare("SELECT * FROM encuesta_respuesta WHERE cliente_id=? AND anio=?");
        $st->execute([$cid, $anio]);
        $r = $st->fetch();
        if ($r && !empty($r['respuestas'])) resp(200, ['estado' => 'respondida', 'fecha' => $r['fecha_respuesta']]);
        if ($r && $r['recordar_hasta'] && strtotime($r['recordar_hasta']) > time()) {
            resp(200, ['estado' => 'pospuesta', 'hasta' => $r['recordar_hasta']]);
        }
        resp(200, ['estado' => 'pendiente', 'config' => $cfg, 'cliente_id' => $cid]);
        break;

    case 'encuesta_responder':
        $cids = clientesDelUsuario($pdo);
        if (!$cids) resp(403, ['error' => 'Usuario sin cliente asociado']);
        $cid = (int)$cids[0];
        $cfg = $pdo->query("SELECT * FROM encuesta_config WHERE id=1")->fetch();
        if (!$cfg || !$cfg['activa']) resp(400, ['error' => 'Encuesta no activa']);
        $anio = (int)$cfg['anio_vigente'];
        $preg = $pdo->query("SELECT id, texto, tipo, obligatoria FROM encuesta_pregunta WHERE activa=1")->fetchAll();
        $respUsr = $body['respuestas'] ?? [];
        foreach ($preg as $p) {
            $v = $respUsr[$p['id']] ?? null;
            // Validar escala (1-10) cuando tiene valor
            if ($p['tipo'] === 'escala' && $v !== null && $v !== '') {
                $n = (int)$v;
                if ($n < 1 || $n > 10) {
                    resp(400, ['error' => 'Valor inválido en escala: ' . $p['texto']]);
                }
            }
            if ((int)$p['obligatoria'] === 1) {
                if ($v === null || $v === '' || (is_array($v) && !count($v))) {
                    resp(400, ['error' => 'Falta responder: ' . $p['texto']]);
                }
            }
        }
        $u = userInfo();
        $pdo->prepare("
          INSERT INTO encuesta_respuesta (cliente_id, anio, usuario_id, usuario_nombre, respuestas, fecha_respuesta, recordar_hasta)
          VALUES (?,?,?,?,?,NOW(),NULL)
          ON DUPLICATE KEY UPDATE usuario_id=VALUES(usuario_id), usuario_nombre=VALUES(usuario_nombre),
            respuestas=VALUES(respuestas), fecha_respuesta=NOW(), recordar_hasta=NULL
        ")->execute([$cid, $anio, $u['id'], $u['nombre'], json_encode($respUsr, JSON_UNESCAPED_UNICODE)]);
        resp(200, ['ok' => true]);
        break;

    case 'encuesta_posponer':
        $cids = clientesDelUsuario($pdo);
        if (!$cids) resp(403, ['error' => 'Usuario sin cliente asociado']);
        $cid = (int)$cids[0];
        $cfg = $pdo->query("SELECT * FROM encuesta_config WHERE id=1")->fetch();
        if (!$cfg || !$cfg['activa']) resp(400, ['error' => 'Encuesta no activa']);
        $anio = (int)$cfg['anio_vigente'];
        $hasta = $cfg['fecha_vencimiento'] ? ($cfg['fecha_vencimiento'] . ' 23:59:59') : date('Y-m-d H:i:s', time() + 86400);
        $pdo->prepare("
          INSERT INTO encuesta_respuesta (cliente_id, anio, respuestas, fecha_respuesta, recordar_hasta)
          VALUES (?,?, NULL, NULL, ?)
          ON DUPLICATE KEY UPDATE recordar_hasta=VALUES(recordar_hasta)
        ")->execute([$cid, $anio, $hasta]);
        resp(200, ['ok' => true, 'recordar_hasta' => $hasta]);
        break;

    case 'encuesta_resultados':
        requireEditor($pdo, 'encuestas_editar');
        $anio = (int)($_GET['anio'] ?? date('Y'));
        $stmt = $pdo->prepare("
          SELECT r.id, r.cliente_id, c.ClienteNombre, r.anio, r.usuario_nombre,
                 r.respuestas, r.fecha_respuesta
          FROM encuesta_respuesta r
          LEFT JOIN CLIENTES c ON c.ClienteID = r.cliente_id
          WHERE r.anio=? AND r.respuestas IS NOT NULL AND r.respuestas <> ''
          ORDER BY r.fecha_respuesta DESC
        ");
        $stmt->execute([$anio]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) { $r['respuestas'] = json_decode($r['respuestas'] ?? '{}', true) ?: []; }
        $preg = $pdo->query("SELECT id, orden, texto, ayuda, tipo, opciones, permite_otros FROM encuesta_pregunta ORDER BY orden, id")->fetchAll();
        foreach ($preg as &$p) { $p['opciones'] = json_decode($p['opciones'] ?? '[]', true) ?: []; }
        resp(200, ['resultados' => $rows, 'preguntas' => $preg, 'anio' => $anio]);
        break;

    case 'encuesta_resultados_excel':
        requireEditor($pdo, 'encuestas_editar');
        $anio = (int)($_GET['anio'] ?? date('Y'));
        $preg = $pdo->query("SELECT id, texto FROM encuesta_pregunta WHERE activa=1 ORDER BY orden, id")->fetchAll();
        $stmt = $pdo->prepare("
          SELECT r.cliente_id, c.ClienteNombre, r.anio, r.usuario_nombre, r.respuestas, r.fecha_respuesta
          FROM encuesta_respuesta r
          LEFT JOIN CLIENTES c ON c.ClienteID = r.cliente_id
          WHERE r.anio=? AND r.respuestas IS NOT NULL AND r.respuestas <> ''
          ORDER BY r.fecha_respuesta
        ");
        $stmt->execute([$anio]);
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=encuesta_{$anio}.csv");
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        $headers = ['ClienteID','Cliente','Año','Respondido por','Fecha'];
        foreach ($preg as $p) $headers[] = $p['texto'];
        fputcsv($out, $headers, ';');
        foreach ($stmt as $r) {
            $resp = json_decode($r['respuestas'] ?? '{}', true) ?: [];
            $row = [$r['cliente_id'], $r['ClienteNombre'], $r['anio'], $r['usuario_nombre'], $r['fecha_respuesta']];
            foreach ($preg as $p) {
                $v = $resp[$p['id']] ?? '';
                if (is_array($v)) $v = implode(' | ', $v);
                $row[] = $v;
            }
            fputcsv($out, $row, ';');
        }
        fclose($out);
        exit;

    // ─────────────────────────── PR-08 ────────────────────────
    case 'pr08_plantilla_get':
        $tipo = $_GET['tipo'] ?? 'nacional';
        if (!in_array($tipo, ['nacional','fiscal'])) resp(400, ['error' => 'tipo inválido']);
        $stmt = $pdo->prepare("SELECT * FROM pr08_plantilla WHERE tipo=?");
        $stmt->execute([$tipo]);
        $p = $stmt->fetch();
        if ($p) $p['campos'] = json_decode($p['campos'] ?? '{}', true) ?: (object)[];
        resp(200, ['plantilla' => $p ?: null, 'columnas_validas' => columnasValidasClienteFrio()]);
        break;

    case 'pr08_plantilla_save':
        requireEditor($pdo, 'pr08_editar');
        $tipo   = $body['tipo'] ?? '';
        if (!in_array($tipo, ['nacional','fiscal'])) resp(400, ['error' => 'tipo inválido']);
        $activa = !empty($body['activa']) ? 1 : 0;
        $venc   = $body['fecha_vencimiento'] ?? null;
        $titulo = trim($body['titulo'] ?? '');
        $campos = $body['campos'] ?? [];

        // Validar que todas las keys de campo sean columnas válidas de cliente_frio
        $validas = array_flip(columnasValidasClienteFrio());
        if (isset($campos['secciones'])) {
            foreach ($campos['secciones'] as $sec) {
                foreach (($sec['campos'] ?? []) as $c) {
                    if (!isset($validas[$c['col'] ?? ''])) {
                        resp(400, ['error' => 'Columna inválida en plantilla: '.($c['col'] ?? '(vacía)')]);
                    }
                }
            }
        }

        $u = userInfo();
        $prev = $pdo->prepare("SELECT version, campos, activa FROM pr08_plantilla WHERE tipo=?");
        $prev->execute([$tipo]);
        $r = $prev->fetch();
        $version = $r ? (int)$r['version'] : 1;
        $camposJson = json_encode($campos, JSON_UNESCAPED_UNICODE);
        if ($r && $r['campos'] !== $camposJson) $version++;

        // fecha_lanzamiento: se setea al ACTIVAR (o reactivar) — marca el "desde cuándo vale"
        $relanzar = ($activa && (!$r || !$r['activa']));

        $pdo->prepare("
          INSERT INTO pr08_plantilla (tipo, activa, fecha_vencimiento, fecha_lanzamiento, titulo, campos, version, actualizado_por, actualizado_por_nombre, actualizado_en)
          VALUES (?, ?, ?, " . ($relanzar ? "NOW()" : "NULL") . ", ?, ?, ?, ?, ?, NOW())
          ON DUPLICATE KEY UPDATE activa=VALUES(activa),
            fecha_vencimiento=VALUES(fecha_vencimiento),
            " . ($relanzar ? "fecha_lanzamiento=NOW()," : "") . "
            titulo=VALUES(titulo), campos=VALUES(campos), version=VALUES(version),
            actualizado_por=VALUES(actualizado_por), actualizado_por_nombre=VALUES(actualizado_por_nombre),
            actualizado_en=NOW()
        ")->execute([$tipo, $activa, $venc ?: null, $titulo, $camposJson, $version, $u['id'], $u['nombre']]);
        resp(200, ['ok' => true, 'version' => $version]);
        break;

    case 'pr08_estado_cliente':
        $cids = clientesDelUsuario($pdo);
        if (!$cids) resp(200, ['estado' => 'no_activa']);
        $cid = (int)$cids[0];
        $cli = infoCliente($pdo, $cid);
        if (!$cli) resp(200, ['estado' => 'no_activa']);
        $tipo = ((int)$cli['fiscal'] === 1) ? 'fiscal' : 'nacional';
        $pl = $pdo->prepare("SELECT * FROM pr08_plantilla WHERE tipo=?");
        $pl->execute([$tipo]);
        $plantilla = $pl->fetch();
        if (!$plantilla || !$plantilla['activa']) resp(200, ['estado' => 'no_activa']);

        // Verificar postergación vigente
        $po = $pdo->prepare("SELECT recordar_hasta FROM pr08_postergacion WHERE cliente_id=? AND tipo=?");
        $po->execute([$cid, $tipo]);
        $rp = $po->fetch();
        if ($rp && strtotime($rp['recordar_hasta']) > time()) {
            resp(200, ['estado' => 'pospuesta', 'hasta' => $rp['recordar_hasta']]);
        }

        // Buscar último cliente_frio del cliente. Si es posterior a fecha_lanzamiento → completa.
        $ultima = ultimaClienteFrio($pdo, $cid);
        if ($ultima && $plantilla['fecha_lanzamiento']) {
            $ts = tsCf($ultima);
            if ($ts && strtotime($ts) >= strtotime($plantilla['fecha_lanzamiento'])) {
                resp(200, ['estado' => 'completa', 'fecha' => $ts]);
            }
        }

        $plantilla['campos'] = json_decode($plantilla['campos'] ?? '{}', true) ?: [];

        // Prellenar con la última respuesta (decodificada) si existe
        $respuesta_previa = null;
        if ($ultima) {
            $respuesta_previa = [];
            foreach (($plantilla['campos']['secciones'] ?? []) as $sec) {
                foreach (($sec['campos'] ?? []) as $c) {
                    $val = $ultima[$c['col']] ?? null;
                    $respuesta_previa[$c['col']] = decodeCampo($c, $val);
                }
            }
        }

        resp(200, [
            'estado'           => 'pendiente',
            'plantilla'        => $plantilla,
            'tipo'             => $tipo,
            'cliente'          => $cli,
            'respuesta_previa' => $respuesta_previa,
        ]);
        break;

    case 'pr08_responder':
        $cids = clientesDelUsuario($pdo);
        if (!$cids) resp(403, ['error' => 'Usuario sin cliente asociado']);
        $cid = (int)$cids[0];
        $cli = infoCliente($pdo, $cid);
        if (!$cli) resp(400, ['error' => 'Cliente no encontrado']);
        $tipo = ((int)$cli['fiscal'] === 1) ? 'fiscal' : 'nacional';
        $pl = $pdo->prepare("SELECT * FROM pr08_plantilla WHERE tipo=?");
        $pl->execute([$tipo]);
        $plantilla = $pl->fetch();
        if (!$plantilla || !$plantilla['activa']) resp(400, ['error' => 'PR-08 no activo']);
        $config = json_decode($plantilla['campos'] ?? '{}', true) ?: [];
        $datos = $body['datos'] ?? [];

        // Validar obligatorios + construir map de valores codificados a insertar
        $validas = array_flip(columnasValidasClienteFrio());
        $insertCols = [];
        $insertVals = [];

        foreach (($config['secciones'] ?? []) as $sec) {
            foreach (($sec['campos'] ?? []) as $c) {
                $col = $c['col'] ?? '';
                if (!isset($validas[$col])) continue;
                $raw = $datos[$col] ?? null;
                if ((int)($c['obligatorio'] ?? 0) === 1) {
                    if ($raw === null || $raw === '' || (is_array($raw) && !count($raw))) {
                        resp(400, ['error' => 'Falta completar: ' . $c['label']]);
                    }
                }
                $encoded = encodeCampo($c, $raw);
                if ($encoded !== null) {
                    $insertCols[$col] = $encoded;
                }
            }
        }

        // INSERT nueva fila (histórico) en cliente_frio
        $u = userInfo();
        $insertCols['cliente_id']     = $cid;
        $insertCols['trato_cliente']  = $u['nombre'];
        $insertCols['fecha_registro'] = date('Y-m-d');
        $insertCols['usuario_id']     = $u['id'];
        $insertCols['created_at']     = date('Y-m-d H:i:s');
        $insertCols['updated_at']     = date('Y-m-d H:i:s');

        $cols = array_keys($insertCols);
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $colsQuoted = implode(',', array_map(fn($c)=>"`$c`", $cols));
        $sql = "INSERT INTO cliente_frio ($colsQuoted) VALUES ($placeholders)";
        $pdo->prepare($sql)->execute(array_values($insertCols));

        // Borrar postergación si existía
        $pdo->prepare("DELETE FROM pr08_postergacion WHERE cliente_id=? AND tipo=?")->execute([$cid, $tipo]);

        resp(200, ['ok' => true, 'cliente_frio_id' => (int)$pdo->lastInsertId()]);
        break;

    case 'pr08_posponer':
        $cids = clientesDelUsuario($pdo);
        if (!$cids) resp(403, ['error' => 'Usuario sin cliente asociado']);
        $cid = (int)$cids[0];
        $cli = infoCliente($pdo, $cid);
        if (!$cli) resp(400, ['error' => 'Cliente no encontrado']);
        $tipo = ((int)$cli['fiscal'] === 1) ? 'fiscal' : 'nacional';
        $pl = $pdo->prepare("SELECT fecha_vencimiento FROM pr08_plantilla WHERE tipo=?");
        $pl->execute([$tipo]);
        $plant = $pl->fetch();
        $hasta = ($plant && $plant['fecha_vencimiento']) ? ($plant['fecha_vencimiento'] . ' 23:59:59') : date('Y-m-d H:i:s', time() + 86400);
        $pdo->prepare("
          INSERT INTO pr08_postergacion (cliente_id, tipo, recordar_hasta) VALUES (?,?,?)
          ON DUPLICATE KEY UPDATE recordar_hasta=VALUES(recordar_hasta)
        ")->execute([$cid, $tipo, $hasta]);
        resp(200, ['ok' => true, 'recordar_hasta' => $hasta]);
        break;

    case 'pr08_respuestas_lista':
        requireEditor($pdo, 'pr08_editar');
        $filtroTipo = $_GET['tipo'] ?? '';
        $where = "1=1";
        if (in_array($filtroTipo, ['nacional','fiscal'])) {
            $where .= ($filtroTipo === 'fiscal') ? " AND COALESCE(c.fiscal,0)=1" : " AND COALESCE(c.fiscal,0)=0";
        }
        // Última fila por cliente
        $stmt = $pdo->prepare("
          SELECT cf.cliente_frio_id, cf.cliente_id, c.ClienteNombre, c.fiscal,
                 cf.descripcion_producto, cf.trato_cliente, cf.fecha_registro,
                 cf.created_at, cf.updated_at, u.NombreCompleto AS usuario_nombre
          FROM cliente_frio cf
          INNER JOIN (
            SELECT cliente_id, MAX(cliente_frio_id) AS max_id
            FROM cliente_frio GROUP BY cliente_id
          ) ult ON ult.cliente_id = cf.cliente_id AND ult.max_id = cf.cliente_frio_id
          LEFT JOIN CLIENTES c ON c.ClienteID = cf.cliente_id
          LEFT JOIN Usuarios u ON u.UsuarioID = cf.usuario_id
          WHERE $where
          ORDER BY cf.updated_at DESC, cf.created_at DESC, cf.cliente_frio_id DESC
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['tipo'] = ((int)$r['fiscal'] === 1) ? 'fiscal' : 'nacional';
        }
        resp(200, ['respuestas' => $rows]);
        break;

    case 'pr08_respuesta_get':
        requireEditor($pdo, 'pr08_editar');
        $cid = (int)($_GET['cliente_id'] ?? 0);
        if (!$cid) resp(400, ['error' => 'Parámetros inválidos']);
        $ultima = ultimaClienteFrio($pdo, $cid);
        $cli = infoCliente($pdo, $cid);
        $tipo = ($cli && (int)$cli['fiscal'] === 1) ? 'fiscal' : 'nacional';
        $pl = $pdo->prepare("SELECT * FROM pr08_plantilla WHERE tipo=?");
        $pl->execute([$tipo]);
        $p = $pl->fetch();
        if ($p) $p['campos'] = json_decode($p['campos'] ?? '{}', true) ?: [];

        // Decodificar valores para visualización
        $decoded = null;
        if ($ultima && $p) {
            $decoded = [];
            foreach (($p['campos']['secciones'] ?? []) as $sec) {
                foreach (($sec['campos'] ?? []) as $c) {
                    $decoded[$c['col']] = decodeCampo($c, $ultima[$c['col']] ?? null);
                }
            }
        }
        resp(200, [
            'cliente_frio' => $ultima ?: null,
            'datos'        => $decoded,
            'plantilla'    => $p ?: null,
            'cliente'      => $cli,
            'tipo'         => $tipo,
        ]);
        break;

    case 'pr08_respuestas_excel':
        requireEditor($pdo, 'pr08_editar');
        $filtroTipo = $_GET['tipo'] ?? 'nacional';
        if (!in_array($filtroTipo, ['nacional','fiscal'])) resp(400, ['error' => 'tipo inválido']);
        $pl = $pdo->prepare("SELECT campos FROM pr08_plantilla WHERE tipo=?");
        $pl->execute([$filtroTipo]);
        $p = $pl->fetch();
        $cfg = $p ? (json_decode($p['campos'] ?? '{}', true) ?: []) : [];
        // Aplanar campos
        $campos = [];
        foreach (($cfg['secciones'] ?? []) as $sec) {
            foreach (($sec['campos'] ?? []) as $c) $campos[] = $c;
        }

        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=pr08_{$filtroTipo}.csv");
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        $headers = ['ClienteID','Cliente','Tipo','Editado por','Fecha registro','Actualización'];
        foreach ($campos as $c) $headers[] = $c['label'];
        fputcsv($out, $headers, ';');

        $whereTipo = ($filtroTipo === 'fiscal') ? " AND COALESCE(c.fiscal,0)=1" : " AND COALESCE(c.fiscal,0)=0";
        $stmt = $pdo->prepare("
          SELECT cf.*, c.ClienteNombre, c.fiscal, u.NombreCompleto AS usuario_nombre
          FROM cliente_frio cf
          INNER JOIN (
            SELECT cliente_id, MAX(cliente_frio_id) AS max_id
            FROM cliente_frio GROUP BY cliente_id
          ) ult ON ult.cliente_id = cf.cliente_id AND ult.max_id = cf.cliente_frio_id
          LEFT JOIN CLIENTES c ON c.ClienteID = cf.cliente_id
          LEFT JOIN Usuarios u ON u.UsuarioID = cf.usuario_id
          WHERE 1=1 $whereTipo
          ORDER BY cf.updated_at DESC
        ");
        $stmt->execute();
        foreach ($stmt as $r) {
            $row = [
                $r['cliente_id'], $r['ClienteNombre'],
                ((int)$r['fiscal']===1?'FISCAL':'NACIONAL'),
                $r['usuario_nombre'] ?: $r['trato_cliente'],
                $r['fecha_registro'], $r['updated_at'] ?: $r['created_at']
            ];
            foreach ($campos as $c) {
                $v = decodeCampo($c, $r[$c['col']] ?? null);
                if (is_array($v)) $v = implode(' | ', $v);
                if (is_bool($v)) $v = $v ? 'SÍ' : 'NO';
                $row[] = $v;
            }
            fputcsv($out, $row, ';');
        }
        fclose($out);
        exit;

    default:
        resp(400, ['error' => 'action inválido']);
}
