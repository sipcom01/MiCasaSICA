<?php
define('SICA_APP', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['logo'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Archivo requerido']);
    exit;
}

$file = $_FILES['logo'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Error al subir']);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg','jpeg','png','gif','webp','svg'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Formato no permitido']);
    exit;
}

$destDir = __DIR__ . '/../uploads/';
if (!is_dir($destDir)) mkdir($destDir, 0755, true);

$filename = 'logo_' . time() . '.' . $ext;
$dest = $destDir . $filename;

if (move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['success' => true, 'path' => 'admin/uploads/' . $filename]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Error al guardar']);
}
