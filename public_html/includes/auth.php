<?php
/**
 * SICA - Funciones de Autenticación para Área Admin
 *
 * Clase Auth con métodos estáticos para gestionar el acceso al panel admin:
 *
 *   initSession()   — Configura e inicia la sesión PHP con cookies seguras
 *   login(u, p)     — Verifica credenciales contra la BD y crea la sesión
 *   isLoggedIn()    — Comprueba si hay una sesión activa y no expirada
 *   logout()        — Destruye la sesión y limpia la cookie
 *   requireLogin()  — Redirige al login si no hay sesión (úsalo al inicio de cada página admin)
 *   currentUser()   — Devuelve los datos del usuario logueado (id, nombre, rol)
 *
 * La sesión expira tras SESSION_LIFETIME segundos (24h por defecto).
 */

if (!defined('SICA_APP')) {
    die('Acceso no autorizado.');
}

class Auth {

    /**
     * Inicia la sesión PHP con configuración de seguridad:
     * - Cookie solo accesible vía HTTP (httponly = true)
     * - SameSite Lax para protección CSRF básica
     * - Secure solo si el sitio usa HTTPS
     * - La sesión caduca tras SESSION_LIFETIME segundos
     */
    public static function initSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path' => '/admin/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
        }
    }
    
    /**
     * Intenta autenticar a un usuario con las credenciales proporcionadas.
     * Busca el usuario en la BD, verifica el hash de la contraseña con password_verify(),
     * y si es correcto crea las variables de sesión (user_id, username, nombre, rol).
     * Regenera el ID de sesión para prevenir session fixation.
     *
     * @return bool true si el login fue exitoso, false si credenciales inválidas
     */
    public static function login($username, $password) {
        $db = Database::getInstance()->getPdo();
        // Buscar por username O por correo (para nuevos usuarios username = correo)
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE (username = :u OR correo = :u2) AND activo = 1");
        $stmt->execute(['u' => $username, 'u2' => $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            self::initSession();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nombre'] = $user['nombre'];
            $_SESSION['rol'] = $user['rol'] ?? 'colaborador';
            $_SESSION['logged_at'] = time();
            session_regenerate_id(true);
            return true;
        }
        return false;
    }
    
    /**
     * Verifica si el usuario tiene una sesión activa y no expirada.
     * Si la sesión expiró (más de SESSION_LIFETIME desde logged_at), hace logout automático.
     *
     * @return bool true si hay sesión válida
     */
    public static function isLoggedIn() {
        self::initSession();
        if (isset($_SESSION['user_id']) && isset($_SESSION['logged_at'])) {
            if (time() - $_SESSION['logged_at'] < SESSION_LIFETIME) {
                return true;
            }
            self::logout();
        }
        return false;
    }
    
    /**
     * Destruye completamente la sesión: limpia $_SESSION, elimina la cookie
     * de sesión del navegador y llama a session_destroy().
     */
    public static function logout() {
        self::initSession();
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
    
    /**
     * Middleware de protección: si el usuario no está logueado, redirige al login.
     * Se llama al inicio de cada página del panel admin.
     */
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: ' . ADMIN_URL . 'login.php');
            exit;
        }
    }
    
    /**
     * Devuelve un array con los datos del usuario actualmente logueado
     * (id, username, nombre, rol), o null si no hay sesión activa.
     *
     * @return array|null
     */
    public static function currentUser() {
        if (self::isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'nombre' => $_SESSION['nombre'],
                'rol' => $_SESSION['rol'] ?? 'colaborador'
            ];
        }
        return null;
    }
}
