<?php
/**
 * SICA - Proxy para DeepSeek AI Chat
 * Mantiene el API key seguro en el servidor
 */
define('SICA_APP', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$messages = $input['messages'] ?? [];
$systemPrompt = $input['system'] ?? 'Eres un asistente para SICA Construcciones, una desarrolladora inmobiliaria mexicana. Ayudas con temas legales, técnicos, financieros y administrativos relacionados con fraccionamientos, desarrollo inmobiliario, trámites, permisos, construcción, y gestión de proyectos. Responde en español mexicano, de forma profesional y útil.';

if (empty($messages)) {
    http_response_code(400);
    echo json_encode(['error' => 'Mensajes requeridos']);
    exit;
}

// Preparar mensajes para DeepSeek
$apiMessages = [['role' => 'system', 'content' => $systemPrompt]];
foreach ($messages as $m) {
    $apiMessages[] = ['role' => $m['role'], 'content' => $m['content']];
}

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
        'messages' => $apiMessages,
        'temperature' => 0.7,
        'max_tokens' => 2048,
    ]),
    CURLOPT_TIMEOUT => 60,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al comunicarse con la IA', 'details' => $response]);
    exit;
}

$result = json_decode($response, true);
echo json_encode([
    'success' => true,
    'reply' => $result['choices'][0]['message']['content'] ?? 'Sin respuesta',
]);
