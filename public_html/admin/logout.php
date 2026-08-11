<?php
/**
 * SICA Admin - Cierre de Sesión (Logout)
 *
 * Destruye la sesión activa del usuario y redirige al formulario de login.
 * Se llama desde el enlace "Salir" del sidebar o desde el footer del panel.
 *
 * Flujo:
 *   1. Carga las dependencias (config, db, auth)
 *   2. Llama a Auth::logout() que destruye $_SESSION y la cookie
 *   3. Redirige a admin/login.php
 */
define('SICA_APP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::logout();
header('Location: ' . ADMIN_URL . 'login.php');
exit;
