<?php
/**
 * SICA API - CRUD de Servicios por Proyecto
 *
 * Endpoint REST para gestionar el checklist de servicios de cada proyecto:
 *   GET    ?proyecto_id=  → listar servicios de un proyecto
 *   POST                  → crear nuevo servicio (nombre, proyecto_id, completado)
 *   PUT                   → actualizar servicio (campos dinámicos: nombre, completado, orden)
 *   DELETE                → eliminar servicio
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
            $pid = isset($_GET['proyecto_id']) ? (int)$_GET['proyecto_id'] : 0;
            if ($pid) {
                $stmt = $db->prepare("SELECT * FROM proyecto_servicios WHERE proyecto_id = :pid ORDER BY orden");
                $stmt->execute(['pid' => $pid]);
            } else {
                $stmt = $db->query("SELECT * FROM proyecto_servicios ORDER BY proyecto_id, orden");
            }
            echo json_encode($stmt->fetchAll());
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['nombre']) || empty($data['proyecto_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Nombre y proyecto_id requeridos']);
                exit;
            }
            $max = $db->prepare("SELECT MAX(orden) FROM proyecto_servicios WHERE proyecto_id = :pid");
            $max->execute(['pid' => (int)$data['proyecto_id']]);
            $orden = ((int)$max->fetchColumn()) + 1;
            
            $stmt = $db->prepare("INSERT INTO proyecto_servicios (proyecto_id, nombre, completado, orden) VALUES (:pid, :nombre, :completado, :orden)");
            $stmt->execute([
                'pid' => (int)$data['proyecto_id'],
                'nombre' => trim($data['nombre']),
                'completado' => (int)($data['completado'] ?? 0),
                'orden' => $orden,
            ]);
            echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['id'])) {
                http_response_code(400); echo json_encode(['error' => 'ID requerido']); exit;
            }
            $sets = []; $params = ['id' => (int)$data['id']];
            foreach (['nombre', 'completado', 'orden'] as $f) {
                if (array_key_exists($f, $data)) { $sets[] = "$f = :$f"; $params[$f] = $data[$f]; }
            }
            if (empty($sets)) { echo json_encode(['success' => true]); exit; }
            $db->prepare("UPDATE proyecto_servicios SET " . implode(', ', $sets) . " WHERE id = :id")->execute($params);
            echo json_encode(['success' => true]);
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['id'])) { http_response_code(400); echo json_encode(['error' => 'ID requerido']); exit; }
            $db->prepare("DELETE FROM proyecto_servicios WHERE id = :id")->execute(['id' => (int)$data['id']]);
            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(405); echo json_encode(['error' => 'Método no permitido']);
    }
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['error' => $e->getMessage()]);
}
