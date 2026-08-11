<?php
/**
 * SICA API - Presupuesto (Partidas CRUD)
 *
 * Endpoint REST para gestionar partidas presupuestarias:
 *   GET  ?proyecto_id=  → obtener todas las categorías con sus partidas anidadas
 *   PUT                  → actualizar una partida (progreso, fechas, responsable, etc.)
 *
 * Las partidas pertenecen a categorías (presupuesto_categorias) que a su vez
 * pertenecen a un proyecto. El GET devuelve la estructura anidada completa.
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
                $cats = $db->prepare("SELECT * FROM presupuesto_categorias WHERE proyecto_id=:pid ORDER BY orden");
                $cats->execute(['pid' => $pid]);
                $categorias = $cats->fetchAll();
                foreach ($categorias as &$cat) {
                    $p = $db->prepare("SELECT * FROM presupuesto_partidas WHERE categoria_id=:cid ORDER BY orden");
                    $p->execute(['cid' => $cat['id']]);
                    $cat['partidas'] = $p->fetchAll();
                }
                echo json_encode($categorias);
            } else {
                echo json_encode(['error' => 'proyecto_id requerido']);
            }
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['id'])) { echo json_encode(['error'=>'ID requerido']); exit; }
            $sets = []; $params = ['id' => (int)$data['id']];
            foreach (['procedimiento','responsable','costo_estimado','costo_real','progreso','fecha_inicio','fecha_fin','tipo_costo','notas','orden'] as $f) {
                if (array_key_exists($f, $data)) { $sets[] = "$f = :$f"; $params[$f] = $data[$f]; }
            }
            if (empty($sets)) { echo json_encode(['success'=>true]); exit; }
            $db->prepare("UPDATE presupuesto_partidas SET ".implode(', ',$sets)." WHERE id=:id")->execute($params);
            echo json_encode(['success'=>true]);
            break;

        default:
            http_response_code(405); echo json_encode(['error'=>'Método no permitido']);
    }
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['error'=>$e->getMessage()]);
}
