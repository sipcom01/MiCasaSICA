<?php
/**
 * SICA Construcciones - Configuración Principal
 * Soluciones Integrales en Construcción Atlacomulco S.A de C.V.
 *
 * Este archivo define todas las constantes y configuraciones globales del sistema:
 * - Rutas base del proyecto (BASE_PATH, DATA_PATH, DB_PATH)
 * - URLs públicas (BASE_URL, ADMIN_URL)
 * - Seguridad de sesiones (SESSION_LIFETIME, credenciales admin por defecto)
 * - Zona horaria (America/Mexico_City)
 * - Control de errores según entorno (development vs production)
 *
 * Es el primer archivo que se incluye en todas las páginas del sitio.
 */

// Evitar acceso directo al archivo — solo se puede cargar vía require/include desde otra página
if (!defined('SICA_APP')) {
    define('SICA_APP', true);
}

// Entorno de ejecución: 'development' muestra errores, 'production' los oculta
define('ENVIRONMENT', 'production');

// ─── RUTAS DEL SISTEMA ───────────────────────────────────────────
// BASE_PATH  = carpeta public_html (donde está este archivo)
// DATA_PATH  = carpeta data/ fuera de public_html (para la DB SQLite)
// DB_PATH    = archivo de base de datos SQLite
// ADMIN_PATH = carpeta del panel de administración
define('BASE_PATH', dirname(__DIR__));
define('DATA_PATH', dirname(BASE_PATH) . '/data');
define('DB_PATH', DATA_PATH . '/sica.db');
define('ADMIN_PATH', BASE_PATH . '/admin');
define('INCLUDES_PATH', BASE_PATH . '/includes');

// ─── URLs BASE ───────────────────────────────────────────────────
// Definidas para construir enlaces y redirecciones en todo el sitio
define('BASE_URL', '/');
define('ADMIN_URL', '/admin/');

// ─── SEGURIDAD DE SESIÓN ────────────────────────────────────────
// SESSION_LIFETIME: tiempo máximo de sesión activa (24 horas = 86400 segundos)
// ADMIN_USERNAME / ADMIN_PASSWORD_HASH: credenciales del usuario admin inicial
//   (solo se usa para crear el primer registro en la BD mediante seedData())
define('SESSION_LIFETIME', 86400);
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', password_hash('SICAadmin2024!', PASSWORD_BCRYPT));

// ─── ZONA HORARIA ───────────────────────────────────────────────
// Todas las fechas se manejan en horario de Ciudad de México (UTC-6)
date_default_timezone_set('America/Mexico_City');

// ─── MANEJO DE ERRORES SEGÚN ENTORNO ────────────────────────────
// Development: muestra todos los errores para depuración
// Production:  oculta errores al usuario final (seguridad)
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ─── INICIALIZACIÓN DEL DIRECTORIO DE DATOS ─────────────────────
// Crea la carpeta data/ con permisos restrictivos si no existe
// (ahí se almacena la base de datos SQLite)
if (!is_dir(DATA_PATH)) {
    mkdir(DATA_PATH, 0750, true);
}
