<?php
/**
 * SICA API - CRUD de Fases (Etapas del Gantt)
 *
 * Endpoint REST para gestionar las fases/etapas de cada proyecto:
 *   GET    ?id=           → obtener una fase específica
 *   GET    ?proyecto_id=  → listar fases de un proyecto
 *   GET    (sin params)   → listar todas las fases (con nombre del proyecto)
 *   POST                  → crear nueva fase (JSON en body)
 *   PUT                   → actualizar fase (campos dinámicos)
 *   DELETE                → eliminar fase (libera dependencias primero)
 *
 * Las fases tienen: nombre, fechas, progreso, color, orden,
 * y opcionalmente una dependencia_id que vincula con otra fase.
 *
 * Requiere autenticación (Auth::requireLogin).
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
                $stmt = $db->prepare("SELECT * FROM fases WHERE id = :id");
                $stmt->execute(['id' => (int)$_GET['id']]);
                $row = $stmt->fetch();
                if (!$row) { http_response_code(404); echo json_encode(['error' => 'No encontrada']); exit; }
                echo json_encode($row);
            } elseif (isset($_GET['proyecto_id'])) {
                $stmt = $db->prepare("SELECT * FROM fases WHERE proyecto_id = :pid ORDER BY orden");
                $stmt->execute(['pid' => (int)$_GET['proyecto_id']]);
                echo json_encode($stmt->fetchAll());
            } else {
                $rows = $db->query("SELECT f.*, p.nombre as proyecto_nombre FROM fases f JOIN proyectos p ON f.proyecto_id = p.id ORDER BY f.proyecto_id, f.orden")->fetchAll();
                echo json_encode($rows);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['nombre']) || empty($data['fecha_inicio']) || empty($data['fecha_fin']) || empty($data['proyecto_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Nombre, fechas y proyecto son requeridos']);
                exit;
            }
            
            // Obtener el siguiente orden
            $max = $db->prepare("SELECT MAX(orden) FROM fases WHERE proyecto_id = :pid");
            $max->execute(['pid' => (int)$data['proyecto_id']]);
            $orden = ((int)$max->fetchColumn()) + 1;
            
            $stmt = $db->prepare("
                INSERT INTO fases (proyecto_id, nombre, descripcion, fecha_inicio, fecha_fin, progreso, dependencia_id, color, orden)
                VALUES (:proyecto_id, :nombre, :descripcion, :fecha_inicio, :fecha_fin, :progreso, :dependencia_id, :color, :orden)
            ");
            $stmt->execute([
                'proyecto_id' => (int)$data['proyecto_id'],
                'nombre' => trim($data['nombre']),
                'descripcion' => trim($data['descripcion'] ?? ''),
                'fecha_inicio' => $data['fecha_inicio'],
                'fecha_fin' => $data['fecha_fin'],
                'progreso' => (int)($data['progreso'] ?? 0),
                'dependencia_id' => $data['dependencia_id'] ? (int)$data['dependencia_id'] : null,
                'color' => $data['color'] ?? '#3b82f6',
                'orden' => $orden,
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
            
            // Construir UPDATE dinámico (solo campos presentes)
            $allowed = ['nombre', 'descripcion', 'fecha_inicio', 'fecha_fin', 'progreso', 'dependencia_id', 'color', 'orden'];
            $sets = [];
            $params = ['id' => (int)$data['id']];
            
            foreach ($allowed as $field) {
                if (array_key_exists($field, $data)) {
                    $sets[] = "$field = :$field";
                    $params[$field] = $field === 'dependencia_id' 
                        ? ($data[$field] ? (int)$data[$field] : null)
                        : $data[$field];
                }
            }
            
            if (empty($sets)) {
                echo json_encode(['success' => true, 'message' => 'Sin cambios']);
                exit;
            }
            
            $sql = "UPDATE fases SET " . implode(', ', $sets) . " WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true]);
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'ID requerido']);
                exit;
            }
            // Liberar dependencias
            $db->prepare("UPDATE fases SET dependencia_id = NULL WHERE dependencia_id = :id")->execute(['id' => (int)$data['id']]);
            $db->prepare("DELETE FROM fases WHERE id = :id")->execute(['id' => (int)$data['id']]);
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
