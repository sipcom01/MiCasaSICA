<?php
/**
 * SICA API - CRUD de Proyectos
 *
 * Endpoint REST para gestionar proyectos inmobiliarios:
 *   GET    ?id=     → obtener un proyecto específico
 *   GET    (sin id) → listar todos los proyectos
 *   POST            → crear nuevo proyecto (JSON en body)
 *   PUT             → actualizar proyecto existente (JSON en body)
 *   DELETE          → eliminar proyecto y sus fases asociadas
 *
 * Requiere autenticación (Auth::requireLogin).
 * Responde siempre en JSON con Content-Type application/json.
 */
define('SICA_APP', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requireLogin();

$db = Database::getInstance()->getPdo();
$method = $_SERVER['REQUEST_METHOD'];

header('Content-Type: application/json; charset=utf-8');

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $stmt = $db->prepare("SELECT * FROM proyectos WHERE id = :id");
                $stmt->execute(['id' => (int)$_GET['id']]);
                $row = $stmt->fetch();
                if (!$row) { http_response_code(404); echo json_encode(['error' => 'No encontrado']); exit; }
                echo json_encode($row);
            } else {
                $rows = $db->query("SELECT * FROM proyectos ORDER BY created_at DESC")->fetchAll();
                echo json_encode($rows);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['nombre']) || empty($data['ubicacion'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Nombre y ubicación son requeridos']);
                exit;
            }
            $stmt = $db->prepare("
                INSERT INTO proyectos (nombre, ubicacion, descripcion, descripcion_larga, video_url, imagen_url, status, fecha_inicio, fecha_fin)
                VALUES (:nombre, :ubicacion, :descripcion, :descripcion_larga, :video_url, :imagen_url, :status, :fecha_inicio, :fecha_fin)
            ");
            $stmt->execute([
                'nombre' => trim($data['nombre']),
                'ubicacion' => trim($data['ubicacion']),
                'descripcion' => trim($data['descripcion'] ?? ''),
                'descripcion_larga' => trim($data['descripcion_larga'] ?? ''),
                'video_url' => trim($data['video_url'] ?? ''),
                'imagen_url' => trim($data['imagen_url'] ?? ''),
                'status' => $data['status'] ?? 'en_planeacion',
                'fecha_inicio' => $data['fecha_inicio'] ?: null,
                'fecha_fin' => $data['fecha_fin'] ?: null,
            ]);
            echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'ID requerido']);
                exit;
            }
            $stmt = $db->prepare("
                UPDATE proyectos SET 
                    nombre = :nombre,
                    ubicacion = :ubicacion,
                    descripcion = :descripcion,
                    descripcion_larga = :descripcion_larga,
                    video_url = :video_url,
                    imagen_url = :imagen_url,
                    status = :status,
                    fecha_inicio = :fecha_inicio,
                    fecha_fin = :fecha_fin
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => (int)$data['id'],
                'nombre' => trim($data['nombre'] ?? ''),
                'ubicacion' => trim($data['ubicacion'] ?? ''),
                'descripcion' => trim($data['descripcion'] ?? ''),
                'descripcion_larga' => trim($data['descripcion_larga'] ?? ''),
                'video_url' => trim($data['video_url'] ?? ''),
                'imagen_url' => trim($data['imagen_url'] ?? ''),
                'status' => $data['status'] ?? 'en_construccion',
                'fecha_inicio' => $data['fecha_inicio'] ?: null,
                'fecha_fin' => $data['fecha_fin'] ?: null,
            ]);
            echo json_encode(['success' => true]);
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'ID requerido']);
                exit;
            }
            // Eliminar fases primero (CASCADE debería hacerlo, pero por si acaso)
            $db->prepare("DELETE FROM fases WHERE proyecto_id = :pid")->execute(['pid' => (int)$data['id']]);
            $db->prepare("DELETE FROM proyectos WHERE id = :id")->execute(['id' => (int)$data['id']]);
            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
