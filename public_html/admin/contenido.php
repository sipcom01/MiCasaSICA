<?php
/**
 * SICA Admin - Gestión de Contenido del Proyecto
 *
 * Permite editar el contenido que se muestra en la página pública del proyecto:
 *   - Pestaña "Descripción & Video": editar descripción larga y URL del video
 *   - Pestaña "Planos": gestionar imágenes de planos del fraccionamiento
 *   - Pestaña "Diseños": gestionar imágenes de modelos de vivienda
 *   - Pestaña "Servicios": checklist de servicios con barra de progreso
 *
 * Recibe ?id= del proyecto vía GET.
 * Las acciones (guardar, agregar, eliminar) se procesan por POST.
 */
define('SICA_APP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();
$user = Auth::currentUser();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$db = Database::getInstance()->getPdo();

// Verificar acceso: admin total, otros solo proyectos asignados
if($user['rol'] !== 'admin'){
    $check = $db->prepare("SELECT permiso FROM usuario_proyectos WHERE usuario_id=:uid AND proyecto_id=:pid");
    $check->execute(['uid'=>$user['id'],'pid'=>$id]);
    $perm = $check->fetchColumn();
    if(!$perm){header('Location: index.php');exit;}
} else { $perm = 'editar'; }
$canEdit = ($perm === 'editar' || $perm === 'editar_presupuesto');

$proyecto = $db->prepare("SELECT * FROM proyectos WHERE id = :id");
$proyecto->execute(['id' => $id]);
$proyecto = $proyecto->fetch();
if (!$proyecto) { header('Location: index.php'); exit; }

// Guardar cambios
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    if (isset($_POST['save_contenido'])) {
        $video_url = $_POST['video_url'] ?? '';
        // Si se subió un archivo de video, guardarlo localmente
        if (!empty($_FILES['video_file']['name']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION);
            $filename = 'video_' . $id . '_' . time() . '.' . $ext;
            $dest = __DIR__ . '/uploads/' . $filename;
            if (move_uploaded_file($_FILES['video_file']['tmp_name'], $dest)) {
                $video_url = 'admin/uploads/' . $filename;
            }
        }
        $db->prepare("UPDATE proyectos SET descripcion_larga=:dl, video_url=:vu WHERE id=:id")->execute([
            'dl' => $_POST['descripcion_larga'] ?? '',
            'vu' => $video_url,
            'id' => $id
        ]);
        $msg = 'Contenido guardado.';
    }
    if (isset($_POST['add_archivo'])) {
        $archivo_url = $_POST['archivo_url'] ?? '';
        // Si se subió un archivo de imagen, guardarlo localmente
        if (!empty($_FILES['archivo_file']['name']) && $_FILES['archivo_file']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['archivo_file']['name'], PATHINFO_EXTENSION);
            $filename = 'img_' . $id . '_' . time() . '.' . $ext;
            $dest = __DIR__ . '/uploads/' . $filename;
            if (move_uploaded_file($_FILES['archivo_file']['tmp_name'], $dest)) {
                $archivo_url = 'admin/uploads/' . $filename;
            }
        }
        $max = $db->prepare("SELECT MAX(orden) FROM proyecto_archivos WHERE proyecto_id=:pid AND tipo=:t");
        $max->execute(['pid' => $id, 't' => $_POST['tipo']]);
        $orden = ((int)$max->fetchColumn()) + 1;
        $db->prepare("INSERT INTO proyecto_archivos (proyecto_id, tipo, titulo, descripcion, archivo_url, orden) VALUES (:pid,:t,:tit,:desc,:url,:ord)")->execute([
            'pid' => $id, 't' => $_POST['tipo'], 'tit' => $_POST['titulo'], 'desc' => $_POST['descripcion'] ?? '', 'url' => $archivo_url, 'ord' => $orden
        ]);
        $msg = 'Archivo agregado.';
    }
    if (isset($_POST['edit_archivo'])) {
        $aid = (int)$_POST['archivo_id'];
        $db->prepare("UPDATE proyecto_archivos SET titulo=:tit, descripcion=:desc WHERE id=:id")->execute([
            'tit' => $_POST['titulo'], 'desc' => $_POST['descripcion'] ?? '', 'id' => $aid
        ]);
        $msg = 'Archivo actualizado.';
    }
    if (isset($_POST['delete_archivo'])) {
        $db->prepare("DELETE FROM proyecto_archivos WHERE id=:id")->execute(['id' => (int)$_POST['delete_archivo']]);
        $msg = 'Archivo eliminado.';
    }
    if (isset($_POST['save_servicio'])) {
        $db->prepare("UPDATE proyecto_servicios SET completado=:c WHERE id=:id")->execute([
            'c' => (int)$_POST['completado'], 'id' => (int)$_POST['servicio_id']
        ]);
        $msg = 'Progreso actualizado.';
    }
    if (isset($_POST['add_servicio'])) {
        $max = $db->prepare("SELECT MAX(orden) FROM proyecto_servicios WHERE proyecto_id=:pid");
        $max->execute(['pid' => $id]);
        $orden = ((int)$max->fetchColumn()) + 1;
        $db->prepare("INSERT INTO proyecto_servicios (proyecto_id, nombre, completado, orden) VALUES (:pid,:n,:c,:o)")->execute([
            'pid' => $id, 'n' => $_POST['servicio_nombre'], 'c' => (int)$_POST['servicio_completado'], 'o' => $orden
        ]);
        $msg = 'Servicio agregado.';
    }
    if (isset($_POST['delete_servicio'])) {
        $db->prepare("DELETE FROM proyecto_servicios WHERE id=:id")->execute(['id' => (int)$_POST['delete_servicio']]);
        $msg = 'Servicio eliminado.';
    }
    // Refrescar proyecto
    $proyecto = $db->prepare("SELECT * FROM proyectos WHERE id = :id");
    $proyecto->execute(['id' => $id]);
    $proyecto = $proyecto->fetch();
}

$archivos = $db->prepare("SELECT * FROM proyecto_archivos WHERE proyecto_id=:pid ORDER BY tipo, orden");
$archivos->execute(['pid' => $id]);
$archivos = $archivos->fetchAll();
$planos = array_values(array_filter($archivos, fn($a) => $a['tipo'] === 'plano'));
$disenos = array_values(array_filter($archivos, fn($a) => $a['tipo'] === 'diseno'));

$servicios = $db->prepare("SELECT * FROM proyecto_servicios WHERE proyecto_id=:pid ORDER BY orden");
$servicios->execute(['pid' => $id]);
$servicios = $servicios->fetchAll();
?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($proyecto['nombre']) ?> - Contenido | SICA Admin</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 36 44%27%3E%3Crect x=%271.5%27 y=%271.5%27 width=%2733%27 height=%2741%27 rx=%272%27 fill=%27none%27 stroke=%27%2350C8C6%27 stroke-width=%272.5%27/%3E%3Crect x=%278%27 y=%2724%27 width=%277%27 height=%2714%27 fill=%27%23FFFFFF%27/%3E%3Crect x=%2721%27 y=%2712%27 width=%277%27 height=%2726%27 fill=%27%23FFFFFF%27/%3E%3C/svg%3E">
    <link rel="stylesheet" href="assets/css/admin.css?v=4">
    <style>
        .tab-nav { display: flex; gap: 0.5rem; margin-bottom: 2rem; border-bottom: 2px solid #e2e8f0; }
        .tab-nav a { padding: 0.75rem 1.5rem; text-decoration: none; color: #64748b; font-weight: 600; border-bottom: 3px solid transparent; margin-bottom: -2px; }
        .tab-nav a.active, .tab-nav a:hover { color: #132236; border-bottom-color: #50C8C6; }
        .card { margin-bottom: 2rem; }
        .servicio-row { display: flex; align-items: center; gap: 1rem; padding: 0.6rem 0; border-bottom: 1px solid #e2e8f0; }
        .servicio-row:last-child { border-bottom: 0; }
        .servicio-row .nombre { flex: 1; font-weight: 500; }
        .servicio-row input[type=range] { width: 150px; }
        .servicio-row .pct { width: 40px; text-align: right; font-weight: 700; font-size: 0.85rem; }
        .progress-bar { height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; margin-top: 4px; }
        .progress-fill { height: 100%; border-radius: 3px; transition: width 0.3s; }
        .archivo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; }
        .archivo-item { background: #f8fafc; border-radius: 8px; padding: 1rem; text-align: center; }
        .archivo-item img { width: 100%; height: 120px; object-fit: cover; border-radius: 4px; margin-bottom: 0.5rem; }
        .archivo-item .titulo { font-size: 0.85rem; font-weight: 600; }
        .archivo-item .actions { margin-top: 0.5rem; }
        /* Modal */
        .modal{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center}
        .modal.show{display:flex}.modal-box{background:#fff;border-radius:12px;padding:2rem;width:90%;max-width:500px}
    </style>
</head>
<body>
<div class="admin-layout">
    <?php $activePage="contenido"; $extraNav='<a href="proyecto.php?id='.$id.'" class="nav-item">📅 Gantt</a> <a href="contenido.php?id='.$id.'" class="nav-item active">📝 Contenido</a>'; include __DIR__."/includes/sidebar.php"; ?>

    <main class="admin-main">
        <a href="index.php" style="color:#64748b;font-size:0.9rem;">← Dashboard</a>
        <div class="admin-header"><h2><?= htmlspecialchars($proyecto['nombre']) ?> — Gestión de Contenido</h2></div>
        
        <?php if ($msg): ?><div class="card" style="background:#dcfce7;color:#166534;margin-bottom:1rem;">✅ <?= $msg ?></div><?php endif; ?>

        <div class="tab-nav">
            <a href="#descripcion" class="active" onclick="showTab(event,'tab-desc')">📄 Descripción & Video</a>
            <a href="#planos" onclick="showTab(event,'tab-planos')">📐 Planos (<?= count($planos) ?>)</a>
            <a href="#disenos" onclick="showTab(event,'tab-disenos')">🏡 Diseños (<?= count($disenos) ?>)</a>
            <a href="#servicios" onclick="showTab(event,'tab-servicios')">✅ Servicios (<?= count($servicios) ?>)</a>
            <a href="/proyecto.php?id=<?= $id ?>" target="_blank" style="margin-left:auto;">👁️ Vista Pública</a>
        </div>

        <!-- TAB: DESCRIPCIÓN & VIDEO -->
        <div id="tab-desc" class="tab-content">
            <div class="card">
                <h3 style="margin-bottom:1rem;">Descripción Larga y Video</h3>
                <form method="POST" enctype="multipart/form-data">
                    <?php if($canEdit):?>
                    <div class="form-group"><label>Descripción Larga (página pública)</label>
                        <textarea name="descripcion_larga" rows="10" style="width:100%;"><?= htmlspecialchars($proyecto['descripcion_larga'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group"><label>URL del Video (YouTube)</label>
                        <input type="text" name="video_url" value="<?= htmlspecialchars($proyecto['video_url'] ?? '') ?>" placeholder="https://youtube.com/watch?v=... (dejar vacío si subes archivo)">
                    </div>
                    <div class="form-group"><label>O subir archivo de video (MP4)</label>
                        <input type="file" name="video_file" accept="video/mp4,video/webm,video/ogg">
                        <?php if(!empty($proyecto['video_url']) && strpos($proyecto['video_url'],'youtube.com')===false && strpos($proyecto['video_url'],'youtu.be')===false):?>
                        <p style="font-size:0.75rem;color:#64748b;margin-top:0.3rem;">Video actual: <?=htmlspecialchars($proyecto['video_url'])?></p>
                        <?php endif?>
                    </div>
                    <button type="submit" name="save_contenido" class="btn btn-primary">Guardar Cambios</button>
                    <?php else:?>
                    <p><?= nl2br(htmlspecialchars($proyecto['descripcion_larga'] ?? 'Sin descripción.')) ?></p>
                    <?php if($proyecto['video_url']):?><p>🎬 Video: <?=htmlspecialchars($proyecto['video_url'])?></p><?php endif?>
                    <?php endif?>
                </form>
            </div>
        </div>

        <!-- TAB: PLANOS -->
        <div id="tab-planos" class="tab-content" style="display:none;">
            <div class="card">
                <h3 style="margin-bottom:1rem;">Planos y Layout del Fraccionamiento</h3>
                <div class="archivo-grid">
                    <?php foreach ($planos as $a): ?>
                    <div class="archivo-item">
                        <img src="<?= htmlspecialchars($a['archivo_url']) ?>" alt="<?= htmlspecialchars($a['titulo']) ?>" onerror="this.style.display='none'">
                        <div class="titulo"><?= htmlspecialchars($a['titulo']) ?></div>
                        <?php if(!empty($a['descripcion'])):?><div style="font-size:0.75rem;color:#64748b;margin-top:0.25rem;"><?= htmlspecialchars($a['descripcion']) ?></div><?php endif?>
                        <div class="actions">
                            <?php if($canEdit):?>
                            <button class="btn btn-sm btn-outline" onclick="editArchivo(<?=$a['id']?>,'<?=htmlspecialchars($a['titulo'],ENT_QUOTES)?>','<?=htmlspecialchars($a['descripcion']??'',ENT_QUOTES)?>')">✏️</button>
                            <form method="POST" style="display:inline;"><button type="submit" name="delete_archivo" value="<?= $a['id'] ?>" class="btn btn-sm" style="color:#ef4444;border-color:#ef4444;">🗑️</button></form>
                            <?php endif?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if($canEdit):?>
                <form method="POST" enctype="multipart/form-data" style="margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid #e2e8f0;">
                    <h4>+ Agregar Plano</h4>
                    <input type="hidden" name="tipo" value="plano">
                    <div class="form-row">
                        <div class="form-group"><label>Título</label><input type="text" name="titulo" required></div>
                        <div class="form-group"><label>Subir imagen</label><input type="file" name="archivo_file" accept="image/*"></div>
                    </div>
                    <div class="form-group"><label>Descripción</label><input type="text" name="descripcion" placeholder="Ej: Plano de lotificación general"></div>
                    <div class="form-group"><label>O pegar URL de la imagen</label><input type="text" name="archivo_url" placeholder="assets/img/planos/mi-plano.jpg"></div>
                    <button type="submit" name="add_archivo" class="btn btn-primary">Agregar</button>
                </form>
                <?php endif?>
            </div>
        </div>

        <!-- TAB: DISEÑOS -->
        <div id="tab-disenos" class="tab-content" style="display:none;">
            <div class="card">
                <h3 style="margin-bottom:1rem;">Diseños de Vivienda</h3>
                <div class="archivo-grid">
                    <?php foreach ($disenos as $a): ?>
                    <div class="archivo-item">
                        <img src="<?= htmlspecialchars($a['archivo_url']) ?>" alt="<?= htmlspecialchars($a['titulo']) ?>" onerror="this.style.display='none'">
                        <div class="titulo"><?= htmlspecialchars($a['titulo']) ?></div>
                        <?php if(!empty($a['descripcion'])):?><div style="font-size:0.75rem;color:#64748b;margin-top:0.25rem;"><?= htmlspecialchars($a['descripcion']) ?></div><?php endif?>
                        <div class="actions">
                            <?php if($canEdit):?>
                            <button class="btn btn-sm btn-outline" onclick="editArchivo(<?=$a['id']?>,'<?=htmlspecialchars($a['titulo'],ENT_QUOTES)?>','<?=htmlspecialchars($a['descripcion']??'',ENT_QUOTES)?>')">✏️</button>
                            <form method="POST" style="display:inline;"><button type="submit" name="delete_archivo" value="<?= $a['id'] ?>" class="btn btn-sm" style="color:#ef4444;border-color:#ef4444;">🗑️</button></form>
                            <?php endif?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if($canEdit):?>
                <form method="POST" enctype="multipart/form-data" style="margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid #e2e8f0;">
                    <h4>+ Agregar Diseño</h4>
                    <input type="hidden" name="tipo" value="diseno">
                    <div class="form-row">
                        <div class="form-group"><label>Título</label><input type="text" name="titulo" required></div>
                        <div class="form-group"><label>Subir imagen</label><input type="file" name="archivo_file" accept="image/*"></div>
                    </div>
                    <div class="form-group"><label>Descripción</label><input type="text" name="descripcion" placeholder="Ej: Modelo Campestre 220m² con 3 recámaras"></div>
                    <div class="form-group"><label>O pegar URL de la imagen</label><input type="text" name="archivo_url" placeholder="assets/img/disenos/mi-diseno.jpg"></div>
                    <button type="submit" name="add_archivo" class="btn btn-primary">Agregar</button>
                </form>
                <?php endif?>
            </div>
        </div>

        <!-- TAB: SERVICIOS -->
        <div id="tab-servicios" class="tab-content" style="display:none;">
            <div class="card">
                <h3 style="margin-bottom:1rem;">Seguimiento de Servicios</h3>
                <?php foreach ($servicios as $s): 
                    $pct = (int)$s['completado'];
                    $color = $pct >= 100 ? '#22c55e' : ($pct >= 50 ? '#3b82f6' : ($pct > 0 ? '#f59e0b' : '#e2e8f0'));
                ?>
                <div class="servicio-row">
                    <div class="nombre"><?= htmlspecialchars($s['nombre']) ?></div>
                    <div style="display:flex;align-items:center;gap:0.5rem;">
                        <span class="pct"><?= $pct ?>%</span>
                        <div style="width:80px;"><div class="progress-bar"><div class="progress-fill" style="width:<?= $pct ?>%;background:<?= $color ?>;"></div></div></div>
                        <?php if($canEdit):?>
                        <form method="POST" style="display:flex;align-items:center;gap:0.5rem;margin:0;">
                            <input type="hidden" name="servicio_id" value="<?= $s['id'] ?>">
                            <input type="range" name="completado" min="0" max="100" value="<?= $pct ?>" onchange="this.nextElementSibling.textContent=this.value+'%'" style="width:100px;">
                            <button type="submit" name="save_servicio" class="btn btn-sm btn-primary">✓</button>
                        </form>
                        <form method="POST" style="display:inline;"><button type="submit" name="delete_servicio" value="<?= $s['id'] ?>" class="btn btn-sm" style="color:#ef4444;border-color:#ef4444;">🗑️</button></form>
                        <?php endif?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if($canEdit):?>
                <form method="POST" style="margin-top:1rem;padding-top:1rem;border-top:1px solid #e2e8f0;display:flex;gap:0.75rem;align-items:flex-end;">
                    <div class="form-group" style="flex:1;margin:0;"><input type="text" name="servicio_nombre" placeholder="Nombre del servicio" required></div>
                    <div class="form-group" style="width:100px;margin:0;"><input type="number" name="servicio_completado" min="0" max="100" value="0" style="width:80px;"></div>
                    <button type="submit" name="add_servicio" class="btn btn-primary">+ Agregar</button>
                </form>
                <?php endif?>
            </div>
        </div>
    </main>
</div>

<!-- Modal editar archivo -->
<div class="modal" id="archivoModal" onclick="if(event.target===this)this.classList.remove('show')"><div class="modal-box" onclick="event.stopPropagation()">
<h3>✏️ Editar Información</h3>
<form method="POST">
<input type="hidden" name="edit_archivo" value="1"><input type="hidden" name="archivo_id" id="editArchivoId">
<div class="form-group"><label>Título</label><input type="text" name="titulo" id="editArchivoTitulo" required></div>
<div class="form-group"><label>Descripción</label><input type="text" name="descripcion" id="editArchivoDesc"></div>
<div style="display:flex;gap:0.5rem;margin-top:1rem"><button type="submit" class="btn btn-primary">💾 Guardar</button><button type="button" class="btn btn-s" onclick="document.getElementById('archivoModal').classList.remove('show')">Cancelar</button></div>
</form>
</div></div>

<script>
function showTab(e, id) {
    e.preventDefault();
    document.querySelectorAll('.tab-content').forEach(t => t.style.display = 'none');
    document.querySelectorAll('.tab-nav a').forEach(a => a.classList.remove('active'));
    document.getElementById(id).style.display = 'block';
    e.target.classList.add('active');
}
function editArchivo(id, titulo, descripcion){
    document.getElementById('editArchivoId').value = id;
    document.getElementById('editArchivoTitulo').value = titulo;
    document.getElementById('editArchivoDesc').value = descripcion||'';
    document.getElementById('archivoModal').classList.add('show');
}
</script>
<script>function toggleDark(){var b=document.body;var c=document.getElementById("darkSwitch");b.classList.toggle("dark",c.checked);localStorage.setItem("sica-dark",c.checked?"1":"0")}(function(){if(localStorage.getItem("sica-dark")==="1"){document.body.classList.add("dark");var c=document.getElementById("darkSwitch");if(c)c.checked=true}})();</script></body>
</html>
