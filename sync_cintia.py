"""
sync_cintia_mysql_v4.py
Lee Cintia.mdb y sincroniza con MariaDB remota
Tablas destino: persona, persona_empleado2
Base: ljadglob_sistemacina_2026ia

Cambios v4:
- Al finalizar la sync, marca como activo=0 a toda persona
  que esté en MySQL pero NO esté en Cintia.mdb
- Solo afecta personas que tienen registro en persona_empleado2
  (es decir, empleados del sistema, no otros tipos de persona)
"""

import subprocess
import pandas as pd
import mysql.connector
import logging
import re
from datetime import datetime
from io import StringIO

# ─────────────────────────────────────────────────
# CONFIGURACIÓN
# ─────────────────────────────────────────────────
MDB_PATH = r"C:\Archivos de Programas\CintiaFull\Cintia.mdb"

MYSQL = {
    "host":     "tu-host.com",
    "port":     3306,
    "database": "ljadglob_sistemacina_2026ia",
    "user":     "ljadglob_usuario",
    "password": "tu_password",
    "charset":  "utf8mb4",
}

LOG_FILE = r"C:\scripts\logs\sync_cintia_mysql.log"

# ─────────────────────────────────────────────────
# MAPEO SECTORES CINTIA → departamento_id MySQL
#
#  CodSector | Sector Cintia   | departamento_id MySQL
#  ──────────|─────────────────|──────────────────────
#     1      | SIN SECTOR      | NULL
#     2      | MANTENIMIENTO   | 3
#     3      | OPERACIONES     | 11
#     4      | ADMINISTRACION  | 4
#     5      | GRANJA          | 8
#     6      | DEPOSITO FISCAL | 25
#     7      | LIMPIEZA        | 12
#     8      | CALIDAD         | 1
# ─────────────────────────────────────────────────
SECTOR_MAP = {
    1: None,  # SIN SECTOR      → NULL
    2: 3,     # MANTENIMIENTO   → 3
    3: 11,    # OPERACIONES     → 11
    4: 4,     # ADMINISTRACION  → 4
    5: 8,     # GRANJA          → 8
    6: 25,    # DEPOSITO FISCAL → 25
    7: 12,    # LIMPIEZA        → 12
    8: 1,     # CALIDAD         → 1
}

DEFAULTS = {
    "tipo_documento_id": 1,   # DNI
    "nacionalidad_id":   1,   # Argentina
    "localidad_id":      1,   # Sin datos en Cintia — ajustar al ID correcto
    "pais_id":           1,   # Argentina
    "empresa_id":        1,   # Siempre 1
}

# ─────────────────────────────────────────────────
# LOGGING
# ─────────────────────────────────────────────────
logging.basicConfig(
    filename=LOG_FILE,
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S"
)
log = logging.getLogger()


# ─────────────────────────────────────────────────
# HELPERS
# ─────────────────────────────────────────────────

def leer_mdb(tabla: str) -> pd.DataFrame:
    result = subprocess.run(
        ["mdb-export", MDB_PATH, tabla],
        capture_output=True, text=True
    )
    if result.returncode != 0:
        raise Exception(f"Error leyendo {tabla}: {result.stderr}")
    return pd.read_csv(StringIO(result.stdout), low_memory=False)


def conectar_mysql():
    return mysql.connector.connect(**MYSQL)


def separar_nombre(nombre_completo: str):
    partes = str(nombre_completo).strip().split()
    if not partes:
        return "SIN APELLIDO", "SIN NOMBRE"
    apellido = partes[0].capitalize()
    nombre   = " ".join(p.capitalize() for p in partes[1:]) if len(partes) > 1 else "SIN NOMBRE"
    return apellido, nombre


def limpiar_cuit(nro_doc: str) -> str:
    return re.sub(r"[^0-9]", "", str(nro_doc))


def parsear_fecha(fecha_str):
    if not fecha_str or str(fecha_str).strip() in ("", "nan", "None", " "):
        return None
    for fmt in ("%m/%d/%y %H:%M:%S", "%m/%d/%Y %H:%M:%S", "%Y-%m-%d"):
        try:
            return datetime.strptime(str(fecha_str).strip(), fmt).strftime("%Y-%m-%d")
        except ValueError:
            continue
    return None


def mapear_sexo(cod) -> str:
    try:
        return "F" if int(float(str(cod))) == 1 else "M"
    except Exception:
        return "M"


def mapear_estado_civil(cod) -> str:
    mapa = {0: "S", 1: "C", 2: "D", 3: "V", 4: "U"}
    try:
        return mapa.get(int(float(str(cod))), "S")
    except Exception:
        return "S"


def mapear_activo(estado) -> int:
    """Cintia: Estado=0 activo, Estado=1 inactivo."""
    try:
        return 0 if int(float(str(estado))) == 1 else 1
    except Exception:
        return 1


def mapear_departamento(cod_sector):
    try:
        return SECTOR_MAP.get(int(float(str(cod_sector))), None)
    except Exception:
        return None


# ─────────────────────────────────────────────────
# SYNC PERSONA
# ─────────────────────────────────────────────────

def sync_persona(cursor, row: dict) -> int:
    cuit_raw    = str(row.get("NroDoc", "")).strip().strip('"')
    cuit_limpio = limpiar_cuit(cuit_raw)
    cuit_fmt    = cuit_raw if "-" in cuit_raw else None
    apellido, nombre = separar_nombre(row.get("Nombre", ""))
    now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    cursor.execute(
        "SELECT persona_id FROM persona WHERE numero_documento = %s",
        (cuit_limpio,)
    )
    existente = cursor.fetchone()

    datos = {
        "nombre":            nombre,
        "apellido":          apellido,
        "tipo_documento_id": DEFAULTS["tipo_documento_id"],
        "numero_documento":  cuit_limpio,
        "cuit":              cuit_fmt,
        "cuil":              None,
        "fecha_nacimiento":  parsear_fecha(row.get("FechaNaci")) or "1900-01-01",
        "nacionalidad_id":   DEFAULTS["nacionalidad_id"],
        "sexo":              mapear_sexo(row.get("CodGénero", 0)),
        "estado_civil":      mapear_estado_civil(row.get("CodEstadoCivil", 0)),
        "cantidad_hijos":    int(float(str(row.get("CargasFliares", 0) or 0))),
        "direccion":         "SIN DATOS",
        "localidad_id":      DEFAULTS["localidad_id"],
        "pais_id":           DEFAULTS["pais_id"],
        "codigo_postal":     "",
        "telefono_fijo":     None,
        "telefono_movil":    None,
        "email1":            f"{cuit_limpio}@pendiente.com",
        "email2":            None,
        "art_vencimiento":   None,
        "usuario_id":        None,
        "activo":            mapear_activo(row.get("Estado", 0)),
        "updated_at":        now,
    }

    if existente:
        persona_id = existente[0]
        sets = ", ".join(f"`{k}` = %s" for k in datos)
        cursor.execute(
            f"UPDATE persona SET {sets} WHERE persona_id = %s",
            list(datos.values()) + [persona_id]
        )
        return persona_id
    else:
        datos["created_at"] = now
        cols = ", ".join(f"`{k}`" for k in datos)
        ph   = ", ".join(["%s"] * len(datos))
        cursor.execute(
            f"INSERT INTO persona ({cols}) VALUES ({ph})",
            list(datos.values())
        )
        return cursor.lastrowid


# ─────────────────────────────────────────────────
# SYNC PERSONA_EMPLEADO2
# ─────────────────────────────────────────────────

def sync_empleado2(cursor, persona_id: int, row: dict):
    cursor.execute(
        "SELECT empleado_id FROM persona_empleado2 WHERE persona_id = %s",
        (persona_id,)
    )
    existente = cursor.fetchone()

    legajo = 0
    try:
        legajo = int(str(row.get("NroLegajo", "0")).strip().lstrip("0") or "0")
    except Exception:
        pass

    datos = {
        "persona_id":                       persona_id,
        "empresa_id":                       DEFAULTS["empresa_id"],
        "legajo":                           legajo,
        "departamento_id":                  mapear_departamento(row.get("CodSector", 1)),
        "fecha_ingreso":                    parsear_fecha(row.get("FechaAlta")),
        "fecha_examen_ingreso":             None,
        "visado":                           0,
        "patologia":                        None,
        "actividades_no_realizables":       None,
        "monotributista":                   0,
        "reponsable_inscripto":             0,
        "exento":                           0,
        "seguro_accidentes_personales_id":  None,
        "fecha_alta_afip":                  parsear_fecha(row.get("FechaAlta")),
        "fecha_ultima_modificacion_afip":   None,
        "fecha_baja_afip":                  parsear_fecha(row.get("FechaBaja")),
        "obra_social_id":                   None,
        "cantidad_adherentes":              0,
        "fecha_ultimo_cambio_obra_social":  None,
        "seguro_vida_obligatorio":          None,
        "cantidad_beneficiarios":           None,
        "tarjeta_ingreso_entregadada":      0,
        "numero_tarjeta":                   str(row.get("NroTarjeta", "")).strip() or None,
        "nota_entrega_de_tarjeta":          0,
        "firmo_reglamento_interno":         0,
        "induccion_ingresante":             None,
        "induccion_calidad":                None,
        "induccion_SYSO":                   None,
        "induccion_ingresante_fecha":       None,
        "induccion_calidad_fecha":          None,
        "induccion_SYSO_fecha":             None,
    }

    if existente:
        empleado_id = existente[0]
        sets = ", ".join(f"`{k}` = %s" for k in datos)
        cursor.execute(
            f"UPDATE persona_empleado2 SET {sets} WHERE empleado_id = %s",
            list(datos.values()) + [empleado_id]
        )
    else:
        cols = ", ".join(f"`{k}`" for k in datos)
        ph   = ", ".join(["%s"] * len(datos))
        cursor.execute(
            f"INSERT INTO persona_empleado2 ({cols}) VALUES ({ph})",
            list(datos.values())
        )


# ─────────────────────────────────────────────────
# MARCAR INACTIVOS — personas en MySQL no en Cintia
# ─────────────────────────────────────────────────

def marcar_inactivos(cursor, cuits_cintia: set) -> int:
    """
    Busca todos los persona_id que tienen registro en persona_empleado2
    (son empleados del sistema) pero cuyo numero_documento NO está
    en el set de CUITs de Cintia. Los marca como activo=0.

    Retorna la cantidad de personas marcadas como inactivas.
    """
    # Traer todos los empleados de MySQL (solo los que tienen persona_empleado2)
    cursor.execute("""
        SELECT p.persona_id, p.numero_documento, p.activo,
               CONCAT(p.apellido, ' ', p.nombre) AS nombre_completo
        FROM persona p
        INNER JOIN persona_empleado2 pe2 ON pe2.persona_id = p.persona_id
        WHERE p.activo = 1
    """)
    empleados_mysql = cursor.fetchall()

    inactivados = 0
    now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    for persona_id, numero_doc, activo, nombre in empleados_mysql:
        doc_limpio = limpiar_cuit(str(numero_doc))
        if doc_limpio not in cuits_cintia:
            cursor.execute(
                "UPDATE persona SET activo = 0, updated_at = %s WHERE persona_id = %s",
                (now, persona_id)
            )
            inactivados += 1
            log.info(f"  INACTIVADO: {nombre} | doc: {doc_limpio} | persona_id: {persona_id}")

    return inactivados


# ─────────────────────────────────────────────────
# MAIN
# ─────────────────────────────────────────────────

def main():
    log.info("=" * 55)
    log.info("Inicio sync Cintia → MySQL v4")

    # Leer Cintia
    try:
        df = leer_mdb("Legajos")
        log.info(f"Legajos leídos de Cintia: {len(df)}")
    except Exception as e:
        log.error(f"No se pudo leer Cintia.mdb: {e}")
        return

    # Armar set de CUITs presentes en Cintia (para la baja de inactivos)
    cuits_cintia = set()
    for _, row in df.iterrows():
        nro = str(row.get("NroDoc", "")).strip()
        limpio = limpiar_cuit(nro)
        if limpio:
            cuits_cintia.add(limpio)
    log.info(f"CUITs únicos en Cintia: {len(cuits_cintia)}")

    # Conectar MySQL
    try:
        conn   = conectar_mysql()
        cursor = conn.cursor()
        log.info("Conexión MySQL OK")
    except Exception as e:
        log.error(f"No se pudo conectar a MySQL: {e}")
        return

    insertados   = 0
    actualizados = 0
    errores      = 0

    # ── Paso 1: Sincronizar activos de Cintia ──
    log.info("── Paso 1: Sincronizando empleados de Cintia ──")
    for _, row in df.iterrows():
        row_dict = row.to_dict()
        nombre  = str(row_dict.get("Nombre", "")).strip()
        nro_doc = str(row_dict.get("NroDoc", "")).strip()

        if not nombre or not nro_doc or nro_doc in ("", "nan"):
            continue

        try:
            cuit_limpio = limpiar_cuit(nro_doc)
            cursor.execute(
                "SELECT persona_id FROM persona WHERE numero_documento = %s",
                (cuit_limpio,)
            )
            era_nuevo = cursor.fetchone() is None

            persona_id = sync_persona(cursor, row_dict)
            sync_empleado2(cursor, persona_id, row_dict)
            conn.commit()

            depto     = mapear_departamento(row_dict.get("CodSector", 1))
            depto_str = str(depto) if depto is not None else "NULL"

            if era_nuevo:
                insertados += 1
                log.info(f"  INSERT: {nombre} | doc: {cuit_limpio} | depto_id: {depto_str}")
            else:
                actualizados += 1
                log.info(f"  UPDATE: {nombre} | doc: {cuit_limpio} | depto_id: {depto_str}")

        except Exception as e:
            conn.rollback()
            errores += 1
            log.error(f"  ERROR en '{nombre}': {e}")

    # ── Paso 2: Marcar inactivos ──
    log.info("── Paso 2: Marcando inactivos (no están en Cintia) ──")
    try:
        inactivados = marcar_inactivos(cursor, cuits_cintia)
        conn.commit()
        log.info(f"  Personas marcadas inactivas: {inactivados}")
    except Exception as e:
        conn.rollback()
        log.error(f"  ERROR al marcar inactivos: {e}")
        inactivados = 0

    cursor.close()
    conn.close()

    # ── Resumen ──
    log.info(f"Resultado: {insertados} insertados | {actualizados} actualizados | {inactivados} inactivados | {errores} errores")
    log.info("Sync finalizado")

    print(f"\n{'='*40}")
    print(f"  Insertados:   {insertados}")
    print(f"  Actualizados: {actualizados}")
    print(f"  Inactivados:  {inactivados}")
    print(f"  Errores:      {errores}")
    print(f"{'='*40}")


if __name__ == "__main__":
    main()