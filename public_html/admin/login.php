<?php
/**
 * SICA Admin - Página de Login (Acceso Staff)
 *
 * Formulario de inicio de sesión para el personal autorizado.
 *
 * Funcionamiento:
 *   - Si ya hay sesión activa, redirige directamente al dashboard
 *   - Si se envía el formulario (POST), verifica credenciales contra la BD
 *   - Usa password_verify() para comparar contraseñas de forma segura
 *   - Muestra mensaje de error si las credenciales son inválidas
 *
 * Incluye toggle para mostrar/ocultar la contraseña (ícono de ojo).
 */
define('SICA_APP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

$db = Database::getInstance();
$db->initTables();
$db->seedData();

// Si ya está logueado, redirigir al dashboard
if (Auth::isLoggedIn()) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$error = '';
$forgotMsg = '';
$forgotSent = false;
$showForgot = isset($_GET['forgot']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['forgot_password'])) {
        // Flujo "Olvidé mi contraseña"
        $email = trim($_POST['forgot_email'] ?? '');
        if (empty($email)) {
            $forgotMsg = 'Por favor ingresa tu correo electrónico.';
            $showForgot = true;
        } else {
            $pdo = $db->getPdo();
            $stmt = $pdo->prepare("SELECT id, nombre, correo FROM usuarios WHERE (correo = :e OR username = :e2) AND activo = 1");
            $stmt->execute(['e' => $email, 'e2' => $email]);
            $u = $stmt->fetch();
            if ($u && !empty($u['correo'])) {
                try {
                    $token = generarToken();
                    $tokenExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                    $pdo->prepare("UPDATE usuarios SET reset_token=:tok, reset_token_expires=:texp WHERE id=:id")
                       ->execute(['tok'=>$token, 'texp'=>$tokenExpires, 'id'=>$u['id']]);
                    require_once __DIR__ . '/../vendor/autoload.php';
                    enviarResetPassword($u['correo'], $u['nombre'], $token);
                } catch (\Exception $e) {
                    // Si el envío falla, no revelamos el error al usuario
                }
            }
            // Siempre mostramos éxito para no revelar si el correo existe
            $forgotSent = true;
            $forgotMsg = 'Si tu correo está registrado, recibirás un enlace para restablecer tu contraseña.';
        }
    } else {
        // Login normal
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'Por favor completa todos los campos.';
        } elseif (Auth::login($username, $password)) {
            header('Location: ' . ADMIN_URL);
            exit;
        } else {
            $error = 'Usuario o contraseña incorrectos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Staff | SICA Construcciones</title>
    <style>
        :root { --navy: #132236; --navy-light: #1b3050; --teal: #50C8C6; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, var(--navy) 0%, #1b3350 100%);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        .login-container {
            background: var(--navy);
            padding: 3rem; border-radius: 16px; width: 100%; max-width: 420px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
            border: 1px solid rgba(80,200,198,0.2);
        }
        .login-logo { text-align: center; margin-bottom: 2rem; }
        .login-logo-img { height: 125px; width: auto; margin-bottom: 1rem; }
        .login-logo p { color: #94a3b8; font-size: 0.85rem; }
        
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; color: #cbd5e1; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.4rem; }
        .form-group input {
            width: 100%; padding: 0.8rem 1rem; background: rgba(255,255,255,0.05);
            border: 1.5px solid rgba(255,255,255,0.15); border-radius: 8px;
            color: #fff; font-size: 0.95rem; font-family: inherit;
            transition: border-color 0.3s;
        }
        .form-group input:focus { outline: none; border-color: var(--teal); }
        .password-wrap { position: relative; }
        .password-wrap input { padding-right: 2.5rem; }
        .toggle-password { position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 1.1rem; padding: 0; line-height: 1; }
        .toggle-password:hover { color: #50C8C6; }
        
        .btn-login {
            width: 100%; padding: 0.85rem; background: var(--teal); color: var(--navy);
            border: none; border-radius: 8px; font-size: 1rem; font-weight: 700;
            cursor: pointer; transition: all 0.3s; margin-top: 0.5rem;
        }
        .btn-login:hover { background: #6FD9D7; }
        
        .error-msg { background: rgba(239,68,68,0.15); color: #fca5a5; padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.85rem; margin-bottom: 1.5rem; border: 1px solid rgba(239,68,68,0.3); }
        .success-msg { background: rgba(80,200,198,0.12); color: #6FD9D7; padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.85rem; margin-bottom: 1.5rem; border: 1px solid rgba(80,200,198,0.25); text-align: center; }
        .back-link { display: block; text-align: center; color: #94a3b8; font-size: 0.85rem; margin-top: 1.5rem; text-decoration: none; }
        .back-link:hover { color: var(--teal); }
        .forgot-link { display: block; text-align: center; color: #94a3b8; font-size: 0.8rem; margin-top: 1rem; cursor: pointer; background: none; border: none; width: 100%; font-family: inherit; }
        .forgot-link:hover { color: var(--teal); text-decoration: underline; }
        #forgotForm { display: <?= $showForgot ? 'block' : 'none' ?>; }
        #loginForm { display: <?= $showForgot ? 'none' : 'block' ?>; }
    </style>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 36 44%27%3E%3Crect x=%271.5%27 y=%271.5%27 width=%2733%27 height=%2741%27 rx=%272%27 fill=%27none%27 stroke=%27%2350C8C6%27 stroke-width=%272.5%27/%3E%3Crect x=%278%27 y=%2724%27 width=%277%27 height=%2714%27 fill=%27%23FFFFFF%27/%3E%3Crect x=%2721%27 y=%2712%27 width=%277%27 height=%2726%27 fill=%27%23FFFFFF%27/%3E%3C/svg%3E">
</head>
<body>
    <div class="login-container">
        <div class="login-logo">
            <img src="../assets/img/Logo_Horizontal.png" alt="SICA Construcciones" class="login-logo-img">
            <p>Acceso exclusivo para personal autorizado</p>
        </div>
        
        <?php if ($error): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($forgotMsg): ?>
        <div class="<?= $forgotSent ? 'success-msg' : 'error-msg' ?>"><?= htmlspecialchars($forgotMsg) ?></div>
        <?php endif; ?>
        
        <!-- Login Form -->
        <div id="loginForm">
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Correo Institucional</label>
                <input type="text" id="username" name="username" placeholder="Correo de SICA" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <div class="password-wrap">
                    <input type="password" id="password" name="password" placeholder="Tu contraseña" required>
                    <button type="button" class="toggle-password" onclick="togglePassword()" aria-label="Mostrar contraseña"><svg id="eyeIcon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
                </div>
            </div>
            <button type="submit" class="btn-login">Iniciar Sesión</button>
        </form>
        <button class="forgot-link" onclick="showForgot()">¿Olvidaste tu contraseña?</button>
        </div>

        <!-- Forgot Password Form -->
        <div id="forgotForm">
        <form method="POST" action="">
            <input type="hidden" name="forgot_password" value="1">
            <p style="color:#cbd5e1;font-size:0.9rem;text-align:center;margin-bottom:1.5rem">Ingresa tu correo institucional y te enviaremos un enlace para restablecer tu contraseña.</p>
            <div class="form-group">
                <label for="forgot_email">Correo electrónico</label>
                <input type="email" id="forgot_email" name="forgot_email" placeholder="tu@micasasica.com" required autofocus>
            </div>
            <button type="submit" class="btn-login">Enviar Enlace</button>
        </form>
        <button class="forgot-link" onclick="showLogin()">← Volver al inicio de sesión</button>
        </div>
        
        <a href="/" class="back-link">← Volver al sitio principal</a>
    </div>
<script>
function togglePassword() {
    var pwd = document.getElementById('password');
    var icon = document.getElementById('eyeIcon');
    if (pwd.type === 'password') { 
        pwd.type = 'text'; 
        icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
    } else { 
        pwd.type = 'password'; 
        icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    }
}
function showForgot() {
    document.getElementById('loginForm').style.display = 'none';
    document.getElementById('forgotForm').style.display = 'block';
    document.getElementById('forgot_email').focus();
}
function showLogin() {
    document.getElementById('forgotForm').style.display = 'none';
    document.getElementById('loginForm').style.display = 'block';
    document.getElementById('username').focus();
}
</script>
</body>
</html>
