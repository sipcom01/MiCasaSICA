<?php
/**
 * SICA - Endpoint de Formulario de Contacto
 *
 * Recibe los datos del formulario de la landing page vía POST (fetch AJAX),
 * los sanitiza, valida y guarda en la tabla `contactos` de la BD SQLite.
 *
 * Flujo:
 *   1. Solo acepta POST (responde 405 para otros métodos)
 *   2. Sanitiza todas las entradas con htmlspecialchars()
 *   3. Valida campos obligatorios: nombre, email, tipo de interés
 *   4. Valida campos específicos según tipo:
 *      - dueño_terreno → requiere superficie y opcionalmente ubicación con coordenadas
 *      - inversionista  → requiere monto disponible
 *      - inmobiliaria   → opcionalmente URL del sitio web
 *   5. Inserta en la BD y responde JSON {success: true/false, message/error}
 *
 * No requiere autenticación (es público).
 */
define('SICA_APP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Sanitización
function clean($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

$nombre   = clean($_POST['name'] ?? '');
$email    = clean($_POST['email'] ?? '');
$telefono = clean($_POST['phone'] ?? '');
$tipo     = clean($_POST['interest'] ?? '');

// Validaciones básicas
if (empty($nombre) || mb_strlen($nombre) < 3) {
    echo json_encode(['success' => false, 'error' => 'Nombre inválido.']);
    exit;
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Correo electrónico inválido.']);
    exit;
}
if (empty($tipo)) {
    echo json_encode(['success' => false, 'error' => 'Selecciona un tipo de interés.']);
    exit;
}

$superficie   = isset($_POST['superficie']) ? (float)$_POST['superficie'] : null;
$ubicacion    = isset($_POST['ubicacion']) ? clean($_POST['ubicacion']) : null;
$latitud      = isset($_POST['latitud']) ? (float)$_POST['latitud'] : null;
$longitud     = isset($_POST['longitud']) ? (float)$_POST['longitud'] : null;
$monto        = isset($_POST['monto']) ? (float)$_POST['monto'] : null;
$web          = isset($_POST['web']) ? clean($_POST['web']) : null;

// Validar campos según tipo
if ($tipo === 'dueno_terreno' && empty($superficie)) {
    echo json_encode(['success' => false, 'error' => 'Indica la superficie del terreno.']);
    exit;
}
if ($tipo === 'inversionista' && empty($monto)) {
    echo json_encode(['success' => false, 'error' => 'Indica el monto disponible para invertir.']);
    exit;
}

// Validar web URL
if ($tipo === 'inmobiliaria' && !empty($web)) {
    if (!preg_match('/^https?:\/\//', $web)) {
        $web = 'https://' . $web;
    }
    if (!filter_var($web, FILTER_VALIDATE_URL)) {
        $web = null;
    }
}

try {
    $db = Database::getInstance();
    $db->initTables();
    $pdo = $db->getPdo();

    $stmt = $pdo->prepare("INSERT INTO contactos (nombre, email, telefono, tipo, superficie, ubicacion, latitud, longitud, monto_inversion, web_inmobiliaria)
        VALUES (:n, :e, :t, :tp, :s, :u, :la, :lo, :m, :w)");
    
    $stmt->execute([
        'n'  => $nombre,
        'e'  => $email,
        't'  => $telefono,
        'tp' => $tipo,
        's'  => $superficie,
        'u'  => $ubicacion,
        'la' => $latitud,
        'lo' => $longitud,
        'm'  => $monto,
        'w'  => $web,
    ]);

    echo json_encode(['success' => true, 'message' => '¡Información enviada! Nos pondremos en contacto contigo pronto.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al guardar. Intenta de nuevo.']);
}
