<?php
/**
 * ============================================================
 *  config.php — Configuración segura de claves API
 *  Grupo CINA — Ambiente: sistemacina.ljadglobal.com.ar
 * ============================================================
 *
 *  IMPORTANTE: Este archivo va FUERA de public_html
 *  Ruta en este servidor: /home/ljadglob/config.php
 *
 *  MIGRACIÓN A VARIABLES DE ENTORNO:
 *  Configurar en .htaccess (fuera de public_html) o panel hosting:
 *    SetEnv CINA_ANTHROPIC_KEY sk-ant-NUEVA-KEY-AQUI
 *    SetEnv CINA_DB_PASS nueva_password
 *    SetEnv CINA_IA_SECRET secreto_largo_32chars
 *    SetEnv CINA_SYNC_SECRET otro_secreto_largo
 *
 *  Una vez configuradas, eliminá los valores fallback.
 * ============================================================
 */

// ── API Keys ─────────────────────────────────────────────────
define('ANTHROPIC_API_KEY', getenv('CINA_ANTHROPIC_KEY') ?: 'REEMPLAZAR_CON_NUEVA_KEY');

// ── Base de datos ─────────────────────────────────────────────
define('DB_HOST', getenv('CINA_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('CINA_DB_NAME') ?: 'ljadglob_sistemacina_2026ia');
define('DB_USER', getenv('CINA_DB_USER') ?: 'agustinb');
define('DB_PASS', getenv('CINA_DB_PASS') ?: 'REEMPLAZAR_CON_NUEVA_PASSWORD');

// ── Seguridad ─────────────────────────────────────────────────
define('IA_SECRET',      getenv('CINA_IA_SECRET')   ?: 'cina_ia_2026');
define('SYNC_SECRET',    getenv('CINA_SYNC_SECRET')  ?: '_cina_sistema_jcpc');
define('SESSION_SECRET', getenv('CINA_SESSION_SECRET') ?: 'cina_secret_2026');

// ── Modelo Claude ─────────────────────────────────────────────
define('CLAUDE_MODEL',      'claude-haiku-4-5-20251001');
define('CLAUDE_MAX_TOKENS', 1500);

// ── Rate Limiting IA ──────────────────────────────────────────
define('IA_RATE_LIMIT_PER_MINUTE', 10);
define('IA_RATE_LIMIT_PER_HOUR',   100);

// ── CORS ──────────────────────────────────────────────────────
define('ALLOWED_ORIGIN', getenv('CINA_ALLOWED_ORIGIN') ?: '');
