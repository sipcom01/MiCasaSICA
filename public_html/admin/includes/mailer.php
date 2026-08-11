<?php
/**
 * SICA Admin — Envío de Correos vía PHPMailer + SMTP Hostinger
 *
 * Funciones:
 *   enviarInvitacion($destinatario, $nombre, $token) — Email de bienvenida para nuevo usuario
 *   enviarResetPassword($destinatario, $nombre, $token) — Email para restablecer contraseña
 *
 * Requiere: PHPMailer instalado vía Composer (vendor/autoload.php)
 */

if (!defined('SICA_APP')) {
    die('Acceso no autorizado.');
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ─── CONFIGURACIÓN SMTP HOSTINGER ────────────────────────────────
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 465);
define('SMTP_USER', 'contacto@micasasica.com');
define('SMTP_PASS', '01J76e90@');
define('SMTP_FROM_EMAIL', 'contacto@micasasica.com');
define('SMTP_FROM_NAME', 'SICA Construcciones');

// URL base para los links en los correos
define('APP_BASE_URL', 'https://micasasica.com');

/**
 * Crea y configura una instancia base de PHPMailer con SMTP de Hostinger.
 * @return PHPMailer
 */
function crearMailer() {
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL (puerto 465)
    $mail->Port       = SMTP_PORT;
    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    return $mail;
}

/**
 * Devuelve el HTML base del template de correo corporativo.
 * Envuelve el $contenido dentro del layout con logo, colores y footer.
 */
function emailTemplate($titulo, $contenido) {
    $logoUrl = APP_BASE_URL . '/assets/img/Logo_Horizontal.png';
    return <<<HTML
<!DOCTYPE html>
<html lang="es-MX">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#132236;font-family:'Segoe UI',system-ui,-apple-system,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#132236;padding:40px 0">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,0.3)">
      <!-- Header con logo -->
      <tr><td align="center" style="background:#0f1b2d;padding:30px 20px;border-bottom:3px solid #50C8C6">
        <img src="{$logoUrl}" alt="SICA Construcciones" style="height:65px;width:auto;display:block">
      </td></tr>
      <!-- Contenido -->
      <tr><td style="padding:40px 36px">
        <h1 style="color:#132236;font-size:22px;font-weight:700;margin:0 0 12px 0">{$titulo}</h1>
        {$contenido}
      </td></tr>
      <!-- Footer -->
      <tr><td style="background:#f1f5f9;padding:24px 36px;border-top:1px solid #e2e8f0">
        <p style="color:#64748b;font-size:12px;margin:0 0 8px 0;text-align:center">
          SICA Construcciones &mdash; Soluciones Integrales en Construcción Atlacomulco S.A de C.V.
        </p>
        <p style="color:#94a3b8;font-size:11px;margin:0;text-align:center">
          Este es un correo automático. Por favor no respondas a este mensaje.<br>
          Si tienes dudas, contáctanos en <a href="mailto:contacto@micasasica.com" style="color:#50C8C6">contacto@micasasica.com</a>
        </p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}

/**
 * Envía email de invitación a un nuevo usuario para que establezca su contraseña.
 *
 * @param string $destinatario Correo del nuevo usuario
 * @param string $nombre       Nombre completo del usuario
 * @param string $token        Token único para el link de set-password
 * @return bool true si se envió correctamente
 * @throws Exception si falla el envío
 */
function enviarInvitacion($destinatario, $nombre, $token) {
    $link = APP_BASE_URL . '/admin/set-password.php?token=' . urlencode($token);
    $contenido = <<<HTML
<p style="color:#475569;font-size:15px;line-height:1.7;margin:0 0 24px 0">
  Hola <strong style="color:#132236">{$nombre}</strong>,<br><br>
  Tu cuenta ha sido creada en el panel de administración de <strong>SICA Construcciones</strong>.
  Para completar tu registro, necesitas establecer tu contraseña haciendo clic en el botón de abajo.
</p>
<div style="text-align:center;margin-bottom:24px">
  <a href="{$link}" style="display:inline-block;background:#50C8C6;color:#132236;padding:14px 40px;border-radius:8px;text-decoration:none;font-weight:700;font-size:15px">
    Establecer mi contraseña
  </a>
</div>
<p style="color:#64748b;font-size:13px;line-height:1.5;margin:0">
  O copia y pega este enlace en tu navegador:<br>
  <a href="{$link}" style="color:#50C8C6;word-break:break-all">{$link}</a>
</p>
<p style="color:#94a3b8;font-size:12px;line-height:1.5;margin:20px 0 0 0">
  Este enlace expirará en 24 horas. Si no lo usas en ese plazo, contacta a tu administrador para recibir una nueva invitación.
</p>
HTML;

    $mail = crearMailer();
    $mail->addAddress($destinatario, $nombre);
    $mail->Subject = 'Bienvenido a SICA — Establece tu contraseña';
    $mail->isHTML(true);
    $mail->Body    = emailTemplate('¡Bienvenido a SICA!', $contenido);
    $mail->AltBody = "Hola {$nombre},\n\nTu cuenta ha sido creada en SICA Construcciones. Para establecer tu contraseña, visita:\n{$link}\n\nEste enlace expira en 24 horas.\n";

    $mail->send();
    return true;
}

/**
 * Envía email para restablecer contraseña olvidada.
 *
 * @param string $destinatario Correo del usuario
 * @param string $nombre       Nombre del usuario
 * @param string $token        Token único para el link de set-password
 * @return bool true si se envió correctamente
 * @throws Exception si falla el envío
 */
function enviarResetPassword($destinatario, $nombre, $token) {
    $link = APP_BASE_URL . '/admin/set-password.php?token=' . urlencode($token);
    $contenido = <<<HTML
<p style="color:#475569;font-size:15px;line-height:1.7;margin:0 0 24px 0">
  Hola <strong style="color:#132236">{$nombre}</strong>,<br><br>
  Hemos recibido una solicitud para restablecer la contraseña de tu cuenta en <strong>SICA Construcciones</strong>.
  Haz clic en el botón de abajo para crear una nueva contraseña.
</p>
<div style="text-align:center;margin-bottom:24px">
  <a href="{$link}" style="display:inline-block;background:#50C8C6;color:#132236;padding:14px 40px;border-radius:8px;text-decoration:none;font-weight:700;font-size:15px">
    Restablecer contraseña
  </a>
</div>
<p style="color:#64748b;font-size:13px;line-height:1.5;margin:0">
  O copia y pega este enlace en tu navegador:<br>
  <a href="{$link}" style="color:#50C8C6;word-break:break-all">{$link}</a>
</p>
<p style="color:#94a3b8;font-size:12px;line-height:1.5;margin:20px 0 0 0">
  Este enlace expirará en 24 horas. Si no solicitaste este cambio, puedes ignorar este mensaje.
</p>
HTML;

    $mail = crearMailer();
    $mail->addAddress($destinatario, $nombre);
    $mail->Subject = 'Restablece tu contraseña — SICA';
    $mail->isHTML(true);
    $mail->Body    = emailTemplate('Restablecer contraseña', $contenido);
    $mail->AltBody = "Hola {$nombre},\n\nPara restablecer tu contraseña en SICA, visita:\n{$link}\n\nEste enlace expira en 24 horas. Si no solicitaste este cambio, ignora este mensaje.\n";

    $mail->send();
    return true;
}

/**
 * Genera un token criptográficamente seguro.
 * @param int $bytes Número de bytes aleatorios (default 32 → 64 caracteres hex)
 * @return string Token hexadecimal
 */
function generarToken($bytes = 32) {
    return bin2hex(random_bytes($bytes));
}
