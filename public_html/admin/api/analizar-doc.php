<?php
/**
 * SICA - Análisis de documentos con IA (DeepSeek)
 * Recibe un archivo y lo analiza para determinar si está alineado con el proyecto
 */
define('SICA_APP', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Archivo requerido']);
    exit;
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'Error al subir archivo']);
    exit;
}

// Leer contenido del archivo para análisis
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$content = '';

if (in_array($ext, ['txt','csv','md'])) {
    $content = mb_substr(file_get_contents($file['tmp_name']), 0, 8000);
} elseif (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
    $content = "[Imagen: {$file['name']}] " . ($_POST['query'] ?? 'Analiza esta imagen para un proyecto inmobiliario.');
} elseif ($ext === 'pdf') {
    $content = "[PDF: {$file['name']}] " . ($_POST['query'] ?? 'Analiza este PDF para un proyecto inmobiliario.');
} else {
    $content = "[Documento: {$file['name']} ({$ext})] " . ($_POST['query'] ?? 'Analiza este documento.');
}

$query = $_POST['query'] ?? 'Analiza este documento.';

$ch = curl_init('https://api.deepseek.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer sk-ad4cd3c3a7ee4a04983bfc4e7a20d097',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'model' => 'deepseek-chat',
        'messages' => [
            ['role' => 'system', 'content' => "Eres un revisor de calidad de SICA Construcciones, desarrolladora inmobiliaria mexicana. Analizas documentos y determinas si están alineados con los requisitos del proyecto. Si el documento es aceptable, tu respuesta DEBE incluir la palabra 'APROBADO' en mayúsculas. Si necesita mejoras, explica qué falta. Sé conciso y profesional."],
            ['role' => 'user', 'content' => "$query\n\nContenido del documento:\n$content\n\nEvalúa si este documento cumple con los estándares requeridos. Si es aceptable, di 'APROBADO'. Si no, explica las mejoras necesarias."]
        ],
        'temperature' => 0.5,
        'max_tokens' => 1024,
    ]),
    CURLOPT_TIMEOUT => 45,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo json_encode(['error' => 'Error al conectar con IA', 'details' => $response]);
    exit;
}

$result = json_decode($response, true);
echo json_encode([
    'success' => true,
    'reply' => $result['choices'][0]['message']['content'] ?? 'Sin respuesta del análisis.',
]);
