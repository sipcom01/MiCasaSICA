<?php
/**
 * SICA Admin — Establecer / Restablecer Contraseña
 *
 * Página pública (sin login) accesible vía link enviado por email.
 * Recibe ?token=XXX, valida contra la BD, y permite al usuario
 * definir su contraseña.
 *
 * Flujo:
 *   1. Usuario hace clic en link del email → llega a esta página con ?token=XXX
 *   2. Si el token es válido: formulario para nueva contraseña
 *   3. Al guardar: hashea, limpia token, redirige al login con mensaje
 *   4. Si token inválido/expirado: mensaje de error
 */
define('SICA_APP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$db = Database::getInstance()->getPdo();

$token   = trim($_GET['token'] ?? '');
$error   = '';   // Error de validación del formulario
$msg     = '';   // Mensaje de estado (éxito o error de token)
$step    = 'form'; // 'form' | 'success' | 'error'

// ─── VALIDAR TOKEN ───────────────────────────────────────────────
if (empty($token)) {
    $msg  = 'Enlace inválido. No se proporcionó un token de verificación.';
    $step = 'error';
} else {
    $stmt = $db->prepare("SELECT id, nombre, correo, reset_token_expires FROM usuarios WHERE reset_token = :t AND activo = 1");
    $stmt->execute(['t' => $token]);
    $user = $stmt->fetch();

    if (!$user) {
        $msg  = 'Este enlace no es válido o ya fue utilizado. Solicita uno nuevo desde la página de acceso.';
        $step = 'error';
    } elseif (strtotime($user['reset_token_expires']) < time()) {
        $msg  = 'Este enlace ha expirado (válido por 24 horas). Solicita uno nuevo desde la página de acceso.';
        $step = 'error';
    }
}

// ─── PROCESAR FORMULARIO DE CONTRASEÑA ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'form') {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // Validaciones
    if (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = 'La contraseña debe contener al menos un número.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'La contraseña debe contener al menos una letra mayúscula.';
    } elseif (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $error = 'La contraseña debe contener al menos un carácter especial.';
    } elseif ($password !== $confirm) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        // Guardar contraseña y limpiar token
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $db->prepare("UPDATE usuarios SET password_hash = :p, reset_token = NULL, reset_token_expires = NULL WHERE id = :id")
           ->execute(['p' => $hash, 'id' => $user['id']]);

        $step = 'success';
        $msg  = '¡Contraseña establecida correctamente! Ya puedes iniciar sesión.';
    }
}
?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $step === 'success' ? 'Contraseña Creada' : 'Establecer Contraseña' ?> | SICA Construcciones</title>
    <style>
        :root { --navy: #132236; --navy-light: #1b3050; --teal: #50C8C6; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, var(--navy) 0%, #1b3350 100%);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        .container {
            background: var(--navy);
            padding: 3rem; border-radius: 16px; width: 100%; max-width: 440px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
            border: 1px solid rgba(80,200,198,0.2);
        }
        .logo { text-align: center; margin-bottom: 2rem; }
        .logo img { height: 80px; width: auto; margin-bottom: 1rem; }
        .logo p { color: #94a3b8; font-size: 0.85rem; }

        h2 { color: #fff; font-size: 1.3rem; margin-bottom: 0.5rem; text-align: center; }
        .subtitle { color: #94a3b8; font-size: 0.85rem; text-align: center; margin-bottom: 1.5rem; }

        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; color: #cbd5e1; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.4rem; }
        .form-group input {
            width: 100%; padding: 0.8rem 1rem; background: rgba(255,255,255,0.05);
            border: 1.5px solid rgba(255,255,255,0.15); border-radius: 8px;
            color: #fff; font-size: 0.95rem; font-family: inherit;
            transition: border-color 0.3s;
        }
        .form-group input:focus { outline: none; border-color: var(--teal); }

        .requirements { color: #94a3b8; font-size: 0.78rem; margin-bottom: 1.5rem; line-height: 1.6; list-style: none; }
        .requirements li { margin: 0.3rem 0; transition: color 0.2s; }
        .requirements li .check {
            display: inline-block; width: 17px; height: 17px; line-height: 15px;
            border-radius: 50%; border: 1.5px solid #475569; color: transparent;
            text-align: center; font-size: 11px; font-weight: 700; margin-right: 0.5rem;
            transition: all 0.2s; vertical-align: middle;
        }
        .requirements li.done { color: #cbd5e1; }
        .requirements li.done .check { background: var(--teal); border-color: var(--teal); color: var(--navy); }

        .btn {
            width: 100%; padding: 0.85rem; background: var(--teal); color: var(--navy);
            border: none; border-radius: 8px; font-size: 1rem; font-weight: 700;
            cursor: pointer; transition: all 0.3s;
        }
        .btn:hover { background: #6FD9D7; }
        .btn-outline {
            display: block; text-align: center; padding: 0.75rem; margin-top: 1rem;
            background: transparent; color: #94a3b8; text-decoration: none;
            border: 1.5px solid rgba(255,255,255,0.15); border-radius: 8px;
            font-size: 0.9rem; transition: all 0.3s;
        }
        .btn-outline:hover { border-color: var(--teal); color: var(--teal); }

        .error-msg { background: rgba(239,68,68,0.15); color: #fca5a5; padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.85rem; margin-bottom: 1.25rem; border: 1px solid rgba(239,68,68,0.3); text-align: center; }
        .success-msg { background: rgba(80,200,198,0.12); color: #6FD9D7; padding: 1.25rem; border-radius: 8px; font-size: 0.95rem; text-align: center; margin-bottom: 1.25rem; border: 1px solid rgba(80,200,198,0.25); }
        .user-info { color: #cbd5e1; font-size: 0.9rem; text-align: center; margin-bottom: 1.5rem; }
        .user-info strong { color: var(--teal); }
    </style>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 36 44'%3E%3Crect x='1.5' y='1.5' width='33' height='41' rx='2' fill='none' stroke='%2350C8C6' stroke-width='2.5'/%3E%3Crect x='8' y='24' width='7' height='14' fill='%23FFFFFF'/%3E%3Crect x='21' y='12' width='7' height='26' fill='%23FFFFFF'/%3E%3C/svg%3E">
</head>
<body>
    <div class="container">
        <div class="logo">
            <img src="../assets/img/Logo_Horizontal.png" alt="SICA Construcciones">
            <p>Panel de Administración</p>
        </div>

        <?php if ($step === 'success'): ?>
            <div class="success-msg"><?= htmlspecialchars($msg) ?></div>
            <a href="login.php" class="btn" style="display:block;text-align:center;text-decoration:none">Iniciar Sesión</a>

        <?php elseif ($step === 'error'): ?>
            <div class="error-msg"><?= htmlspecialchars($msg) ?></div>
            <a href="login.php" class="btn" style="display:block;text-align:center;text-decoration:none">Ir a Iniciar Sesión</a>

        <?php else: ?>
            <h2>Establecer Contraseña</h2>
            <p class="user-info">
                <strong><?= htmlspecialchars($user['nombre']) ?></strong><br>
                <?= htmlspecialchars($user['correo'] ?? '') ?>
            </p>

            <?php if ($error): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="password">Nueva Contraseña</label>
                    <input type="password" id="password" name="password" placeholder="Mínimo 8 caracteres" required autofocus>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmar Contraseña</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Repite tu contraseña" required>
                </div>
                <ul class="requirements">
                    <li id="req-length"><span class="check">✓</span> Mínimo 8 caracteres</li>
                    <li id="req-upper"><span class="check">✓</span> Al menos 1 letra mayúscula</li>
                    <li id="req-number"><span class="check">✓</span> Al menos 1 número</li>
                    <li id="req-special"><span class="check">✓</span> Al menos 1 carácter especial</li>
                </ul>
                <button type="submit" class="btn">Guardar Contraseña</button>
            </form>
        <?php endif; ?>
    </div>
<script>
function setReq(id, ok) {
    var el = document.getElementById(id);
    if (el) { el.classList.toggle('done', ok); }
}
function checkPassword() {
    var p = document.getElementById('password').value;
    setReq('req-length',  p.length >= 8);
    setReq('req-upper',   /[A-Z]/.test(p));
    setReq('req-number',  /[0-9]/.test(p));
    setReq('req-special', /[^a-zA-Z0-9]/.test(p));
}
document.getElementById('password').addEventListener('input', checkPassword);
</script>
</body>
</html>
