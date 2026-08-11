<?php
/**
 * SICA Admin - Dashboard Principal
 *
 * Panel de control del área administrativa. Muestra:
 *   - Estadísticas generales (total proyectos, activos, fases, avance global)
 *   - Barra de progreso general de todos los proyectos
 *   - Tabla de proyectos con acciones: ver Gantt, editar, eliminar
 *   - Modal para crear/editar proyectos vía AJAX (api/proyectos.php)
 *
 * Requiere autenticación (Auth::requireLogin).
 * La gestión de proyectos usa la API REST en admin/api/proyectos.php
 * mediante fetch() desde el JS incluido en admin/assets/js/admin.js.
 */
define('SICA_APP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();
$user = Auth::currentUser();
$db = Database::getInstance()->getPdo();

// Filtro de acceso: admin/director ven todos, otros solo sus proyectos asignados
$isAdmin = ($user['rol'] === 'admin');
$proyectoFilter = '';
$filterParams = [];
if(!$isAdmin){
    $proyectoFilter = 'AND p.id IN (SELECT proyecto_id FROM usuario_proyectos WHERE usuario_id=:uid)';
    $filterParams['uid'] = $user['id'];
}

// ─── ESTADÍSTICAS PARA EL DASHBOARD ──────────────────────────
// Consultas rápidas de agregación para las tarjetas de estadísticas
$totalProyectos = $db->query("SELECT COUNT(*) FROM proyectos")->fetchColumn();
$proyectosActivos = $db->query("SELECT COUNT(*) FROM proyectos WHERE status != 'completado'")->fetchColumn();
$totalFases = $db->query("SELECT COUNT(*) FROM fases")->fetchColumn();
$fasesCompletadas = $db->query("SELECT COUNT(*) FROM fases WHERE progreso = 100")->fetchColumn();
$progresoGeneral = $totalFases > 0 ? round(($fasesCompletadas / $totalFases) * 100) : 0;

$stmt = $db->prepare("
    SELECT p.*, up.permiso as user_permiso,
           MIN(pp.fecha_inicio) as gantt_inicio, 
           MAX(pp.fecha_fin) as gantt_fin
    FROM proyectos p
    LEFT JOIN usuario_proyectos up ON up.proyecto_id = p.id AND up.usuario_id = :uid2
    LEFT JOIN presupuesto_categorias pc ON pc.proyecto_id = p.id
    LEFT JOIN presupuesto_partidas pp ON pp.categoria_id = pc.id
    WHERE 1=1 $proyectoFilter
    GROUP BY p.id
    ORDER BY p.created_at DESC
");
$stmt->execute(array_merge($filterParams, ['uid2'=>$user['id']]));
$proyectos = $stmt->fetchAll();

// Helper: devuelve [etiqueta, color] según el estado del proyecto para los badges
function statusBadge($s) {
    $map = [
        'en_construccion' => ['En Construcción', '#3b82f6'],
        'en_planeacion' => ['En Planeación', '#f59e0b'],
        'completado' => ['Completado', '#22c55e'],
    ];
    return $map[$s] ?? ['Desconocido', '#94a3b8'];
}
?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | SICA Admin</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 36 44%27%3E%3Crect x=%271.5%27 y=%271.5%27 width=%2733%27 height=%2741%27 rx=%272%27 fill=%27none%27 stroke=%27%2350C8C6%27 stroke-width=%272.5%27/%3E%3Crect x=%278%27 y=%2724%27 width=%277%27 height=%2714%27 fill=%27%23FFFFFF%27/%3E%3Crect x=%2721%27 y=%2712%27 width=%277%27 height=%2726%27 fill=%27%23FFFFFF%27/%3E%3C/svg%3E">
    <link rel="stylesheet" href="assets/css/admin.css?v=4">
</head>
<body>
<div class="admin-layout">
    <!-- Sidebar -->
    <?php $activePage="index"; include __DIR__."/includes/sidebar.php"; ?>

    <!-- Main Content -->
    <main class="admin-main">
        <header class="admin-header">
            <h2>Dashboard</h2>
            <span class="header-date"><?= date('d \d\e F, Y') ?></span>
        </header>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(59,130,246,0.15);color:#3b82f6;">🏗️</div>
                <div class="stat-info">
                    <div class="stat-value"><?= $totalProyectos ?></div>
                    <div class="stat-label">Proyectos Totales</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(34,197,94,0.15);color:#22c55e;">✅</div>
                <div class="stat-info">
                    <div class="stat-value"><?= $proyectosActivos ?></div>
                    <div class="stat-label">Proyectos Activos</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(245,158,11,0.15);color:#f59e0b;">📋</div>
                <div class="stat-info">
                    <div class="stat-value"><?= $totalFases ?></div>
                    <div class="stat-label">Fases Totales</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(200,164,92,0.15);color:#c8a45c;">📈</div>
                <div class="stat-info">
                    <div class="stat-value"><?= $progresoGeneral ?>%</div>
                    <div class="stat-label">Avance General</div>
                </div>
            </div>
        </div>

        <!-- Progress bar -->
        <div class="card" style="margin-bottom:2rem;">
            <h3 style="margin-bottom:1rem;">Progreso General de Proyectos</h3>
            <div style="background:#e2e8f0;border-radius:8px;height:24px;overflow:hidden;">
                <div style="background:linear-gradient(90deg,#22c55e,<?= $progresoGeneral > 60 ? '#3b82f6' : '#f59e0b' ?>);height:100%;width:<?= $progresoGeneral ?>%;border-radius:8px;transition:width 0.5s;display:flex;align-items:center;justify-content:flex-end;padding-right:12px;font-size:0.8rem;font-weight:700;color:#fff;min-width:40px;"><?= $progresoGeneral ?>%</div>
            </div>
        </div>

        <!-- Proyectos Table -->
        <div class="card" id="proyectos">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
                <h3>Proyectos</h3>
                <button class="btn btn-primary" onclick="openProjectModal()">+ Nuevo Proyecto</button>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Proyecto</th>
                            <th>Ubicación</th>
                            <th>Estado</th>
                            <th>Inicio</th>
                            <th>Fin Plan</th>
                            <th>Fin Real</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($proyectos as $p): 
                            $sb = statusBadge($p['status']);
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($p['nombre']) ?></strong></td>
                            <td><?= htmlspecialchars($p['ubicacion']) ?></td>
                            <td><span class="badge" style="background:<?= $sb[1] ?>20;color:<?= $sb[1] ?>;border:1px solid <?= $sb[1] ?>40;"><?= $sb[0] ?></span></td>
                            <td><?= $p['fecha_inicio'] ? date('d/m/Y', strtotime($p['fecha_inicio'])) : '-' ?></td>
                            <td><?= $p['fecha_fin'] ? date('d/m/Y', strtotime($p['fecha_fin'])) : '-' ?></td>
                            <td style="<?= $p['gantt_fin'] && $p['fecha_fin'] && $p['gantt_fin'] > $p['fecha_fin'] ? 'color:#ef4444;font-weight:700' : '' ?>">
                                <?= $p['gantt_fin'] ? date('d/m/Y', strtotime($p['gantt_fin'])) : '—' ?>
                            </td>
                            <td>
                                <div class="action-btns">
                                                <a href="proyecto.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline">📊 Gantt</a>
                                                <a href="presupuesto.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline">💰 Presupuesto</a>
                                                <a href="contenido.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline">📝 Contenido</a>
                                    <button class="btn btn-sm btn-outline" onclick="editProject(<?= $p['id'] ?>)">✏️</button>
                                    <button class="btn btn-sm btn-outline" style="color:#ef4444;border-color:#ef4444;" onclick="deleteProject(<?= $p['id'] ?>)">🗑️</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($proyectos)): ?>
                        <tr><td colspan="7" style="text-align:center;padding:3rem;color:#94a3b8;">No hay proyectos aún. Crea el primero.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Modal Crear/Editar Proyecto -->
<div class="modal" id="projectModal">
    <div class="modal-backdrop" onclick="closeProjectModal()"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Nuevo Proyecto</h3>
            <button class="modal-close" onclick="closeProjectModal()">&times;</button>
        </div>
        <form id="projectForm" onsubmit="return saveProject(event)">
            <input type="hidden" name="id" id="projectId">
            <div class="form-group">
                <label>Nombre del proyecto *</label>
                <input type="text" name="nombre" id="projectNombre" required>
            </div>
            <div class="form-group">
                <label>Ubicación *</label>
                <input type="text" name="ubicacion" id="projectUbicacion" required>
            </div>
            <div class="form-group">
                <label>Descripción</label>
                <textarea name="descripcion" id="projectDescripcion" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label>Logo del Proyecto</label>
                <div style="display:flex;align-items:center;gap:1rem;margin-bottom:0.5rem">
                    <img id="logoPreview" src="" style="max-height:60px;max-width:200px;display:none;border-radius:6px;border:1px solid #e2e8f0">
                </div>
                <input type="file" id="projectLogoFile" accept="image/*" onchange="uploadLogo()" style="margin-bottom:0.3rem">
                <input type="text" name="imagen_url" id="projectImagenUrl" placeholder="O pegar URL: assets/img/Logo_San_isidro.png">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Estado</label>
                    <select name="status" id="projectStatus">
                        <option value="en_planeacion">En Planeación</option>
                        <option value="en_construccion">En Construcción</option>
                        <option value="completado">Completado</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Fecha de inicio</label>
                    <input type="date" name="fecha_inicio" id="projectFechaInicio">
                </div>
                <div class="form-group">
                    <label>Fecha de fin</label>
                    <input type="date" name="fecha_fin" id="projectFechaFin">
                </div>
            </div>
            <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.5rem;">
                <button type="button" class="btn btn-outline" onclick="closeProjectModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/admin.js"></script>
<script>function toggleDark(){var b=document.body;var c=document.getElementById("darkSwitch");b.classList.toggle("dark",c.checked);localStorage.setItem("sica-dark",c.checked?"1":"0")}(function(){if(localStorage.getItem("sica-dark")==="1"){document.body.classList.add("dark");var c=document.getElementById("darkSwitch");if(c)c.checked=true}})();</script></body>
</html>
