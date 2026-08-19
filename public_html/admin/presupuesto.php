<?php
/**
 * SICA Admin - Presupuesto por Proyecto (con edición, borrado y carga de documentos)
 */
define('SICA_APP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
Auth::requireLogin();
$user = Auth::currentUser();
$db = Database::getInstance()->getPdo();

$proyecto_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
// Verificar acceso: admin total, otros solo proyectos asignados con permiso
if($proyecto_id && $user['rol'] !== 'admin'){
    $check = $db->prepare("SELECT permiso FROM usuario_proyectos WHERE usuario_id=:uid AND proyecto_id=:pid");
    $check->execute(['uid'=>$user['id'],'pid'=>$proyecto_id]);
    $perm = $check->fetchColumn();
    if(!$perm){header('Location: index.php');exit;}
} else { $perm = 'editar'; }
$canEditPresupuesto = ($perm === 'editar' || $perm === 'editar_presupuesto');
$proyecto = null;
if ($proyecto_id) {
    $stmt = $db->prepare("SELECT * FROM proyectos WHERE id=:id");
    $stmt->execute(['id'=>$proyecto_id]);
    $proyecto = $stmt->fetch();
    if(!$proyecto){header('Location: index.php');exit;}
}

// ═══ ACCIONES POST ═══
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEditPresupuesto) {
    // Editar partida
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        $deps = !empty($_POST['dependencias']) ? $_POST['dependencias'] : null;
        $db->prepare("UPDATE presupuesto_partidas SET procedimiento=:p, responsable=:r, costo_estimado=:c, progreso=:pr, fecha_inicio=:fi, fecha_fin=:ff, dependencias=:d WHERE id=:id")
           ->execute([
               'p'=>$_POST['procedimiento'], 'r'=>$_POST['responsable'],
               'c'=>(float)$_POST['costo'], 'pr'=>(int)$_POST['progreso'],
               'fi'=>$_POST['fecha_inicio']?:null, 'ff'=>$_POST['fecha_fin']?:null,
               'd'=>$deps, 'id'=>(int)$_POST['partida_id']
           ]);
        $msg = 'Partida actualizada.';
    }
    // Borrar partida
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $db->prepare("DELETE FROM presupuesto_partidas WHERE id=:id")->execute(['id'=>(int)$_POST['partida_id']]);
        $msg = 'Partida eliminada.';
    }
    // Upload documento y cerrar tarea
    if (isset($_FILES['documento']) && $_FILES['documento']['error'] === UPLOAD_ERR_OK) {
        $pid = (int)$_POST['partida_id'];
        $ext = pathinfo($_FILES['documento']['name'], PATHINFO_EXTENSION);
        $filename = 'doc_' . $pid . '_' . time() . '.' . $ext;
        $dest = __DIR__ . '/uploads/' . $filename;
        if (move_uploaded_file($_FILES['documento']['tmp_name'], $dest)) {
            $db->prepare("UPDATE presupuesto_partidas SET archivo_resultado=:a, completado=1, progreso=100, fecha_terminacion_real=DATE('now') WHERE id=:id")
               ->execute(['a'=>$filename, 'id'=>$pid]);
            $msg = 'Documento subido. Tarea completada.';
        }
    }
    // Agregar etapa
    if (isset($_POST['action']) && $_POST['action'] === 'add_etapa') {
        $max = $db->prepare("SELECT COALESCE(MAX(orden),0)+1 FROM presupuesto_categorias WHERE proyecto_id=:pid");
        $max->execute(['pid'=>$proyecto_id]);
        $next = $max->fetchColumn();
        $db->prepare("INSERT INTO presupuesto_categorias (proyecto_id, codigo, nombre, orden) VALUES (:pid, :cod, :nom, :ord)")
           ->execute(['pid'=>$proyecto_id, 'cod'=>$_POST['codigo'], 'nom'=>$_POST['nombre'], 'ord'=>$next]);
        $msg = 'Etapa agregada.';
    }
    // Agregar tarea
    if (isset($_POST['action']) && $_POST['action'] === 'add_tarea') {
        $cid = (int)$_POST['categoria_id'];
        $max = $db->prepare("SELECT COALESCE(MAX(orden),0)+1 FROM presupuesto_partidas WHERE categoria_id=:cid");
        $max->execute(['cid'=>$cid]);
        $next = $max->fetchColumn();
        $deps = !empty($_POST['dependencias']) ? $_POST['dependencias'] : null;
        $db->prepare("INSERT INTO presupuesto_partidas (categoria_id, procedimiento, responsable, costo_estimado, progreso, fecha_inicio, fecha_fin, dependencias, orden) VALUES (:cid, :proc, :resp, :costo, :prog, :fi, :ff, :d, :ord)")
           ->execute([
               'cid'=>$cid, 'proc'=>$_POST['procedimiento'], 'resp'=>$_POST['responsable']?:null,
               'costo'=>(float)$_POST['costo'], 'prog'=>(int)$_POST['progreso'],
               'fi'=>$_POST['fecha_inicio']?:null, 'ff'=>$_POST['fecha_fin']?:null,
               'd'=>$deps, 'ord'=>$next
           ]);
        $msg = 'Tarea agregada.';
    }
}

// ═══ DATOS ═══
$categorias = []; $totales = ['estimado'=>0, 'count'=>0, 'prog'=>0, 'completadas'=>0];
if ($proyecto_id) {
    $cats = $db->prepare("SELECT * FROM presupuesto_categorias WHERE proyecto_id=:pid ORDER BY orden");
    $cats->execute(['pid'=>$proyecto_id]); $categorias = $cats->fetchAll();
    $num = 1;
    foreach ($categorias as &$cat) {
        $p = $db->prepare("SELECT * FROM presupuesto_partidas WHERE categoria_id=:cid ORDER BY orden");
        $p->execute(['cid'=>$cat['id']]); $cat['partidas'] = $p->fetchAll();
        foreach ($cat['partidas'] as &$pt) {
            $pt['_num'] = $num++;
            $totales['estimado'] += $pt['costo_estimado'];
            $totales['count']++;
            $totales['prog'] += $pt['progreso'];
            if($pt['completado']) $totales['completadas']++;
        }
    }
    $totales['prog'] = $totales['count']>0 ? round($totales['prog']/$totales['count']) : 0;
}
function fmt($n){return '$ '.number_format($n,2);}
function barColor($p){return $p>=100?'#22c55e':($p>0?'#3b82f6':'#e2e8f0');}
?>
<!DOCTYPE html><html lang="es-MX"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?=$proyecto_id?htmlspecialchars($proyecto['nombre']).' | ':''?>Presupuesto | SICA</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 36 44'%3E%3Crect x='1.5' y='1.5' width='33' height='41' rx='2' fill='none' stroke='%2350C8C6' stroke-width='2.5'/%3E%3Crect x='8' y='24' width='7' height='14' fill='%23FFFFFF'/%3E%3Crect x='21' y='12' width='7' height='26' fill='%23FFFFFF'/%3E%3C/svg%3E">
<link rel="stylesheet" href="assets/css/admin.css?v=7"><style>
.tbl{width:100%;border-collapse:collapse;font-size:0.8rem;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,0.06)}
.tbl th{background:#132236;color:#fff;padding:0.5rem 0.4rem;font-weight:600;font-size:0.7rem;text-align:left;white-space:nowrap}
.tbl td{padding:0.4rem 0.4rem;border-bottom:1px solid #f1f5f9}
.tbl tr:hover td{background:#f8fafc}
.tbl .cr td{background:#e2e8f0;font-weight:700;color:#132236;border-bottom:2px solid #cbd5e1}
.tbl tr.done td{background:#f0fdf4}
.num{text-align:center;color:#94a3b8;font-size:0.7rem;width:30px}
.proc{font-weight:500;min-width:200px}.resp{color:#475569;font-size:0.75rem;min-width:120px}
.costo{text-align:right;font-weight:600;white-space:nowrap}
.pct{text-align:center;font-weight:600;font-size:0.75rem}
.bw{width:80px;height:7px;background:#e2e8f0;border-radius:4px;overflow:hidden;display:inline-block;vertical-align:middle}.bf{height:100%;border-radius:4px}
.act{display:flex;gap:4px;flex-wrap:nowrap}
.btn-xs{padding:3px 8px;font-size:0.65rem;border-radius:4px;border:1px solid #e2e8f0;background:#fff;cursor:pointer;white-space:nowrap}
.btn-xs:hover{background:#f1f5f9}
.btn-xs.danger{color:#ef4444;border-color:#ef4444}.btn-xs.danger:hover{background:#fef2f2}
.btn-xs.primary{color:#3b82f6;border-color:#3b82f6}.btn-xs.primary:hover{background:#eff6ff}
.btn-xs.success{color:#16a34a;border-color:#16a34a}.btn-xs.success:hover{background:#f0fdf4}
.sb{display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem}.si{background:#132236;color:#fff;padding:0.75rem 1.25rem;border-radius:8px}.si .b{font-size:1.4rem;font-weight:800}.si .l{font-size:0.75rem;opacity:0.8}
.tw{overflow-x:auto}
.msg{padding:0.6rem 1rem;border-radius:6px;margin-bottom:1rem;font-size:0.8rem}
.msg.show{display:block}
.msg.ok{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0}
/* Modal */
.modal-overlay{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center}
.modal-overlay.show{display:flex}
.modal-box{background:#fff;border-radius:12px;padding:1.5rem;width:500px;max-width:90vw;box-shadow:0 20px 60px rgba(0,0,0,0.3)}
.modal-box h3{margin:0 0 1rem 0;font-size:1rem}
.modal-box label{display:block;font-size:0.75rem;font-weight:600;margin:0.5rem 0 0.2rem;color:#475569}
.modal-box input,.modal-box select{width:100%;padding:0.5rem;border:1px solid #e2e8f0;border-radius:6px;font-size:0.8rem;box-sizing:border-box}
.modal-box .btn-row{display:flex;gap:0.5rem;justify-content:flex-end;margin-top:1rem}
</style></head><body><div class="admin-layout">
<?php $activePage="presupuesto"; include __DIR__."/includes/sidebar.php"; ?>
<main class="admin-main"><a href="index.php" style="color:#64748b;font-size:0.9rem">← Dashboard</a>
<div class="admin-header"><h2>💰 Presupuesto<?=$proyecto_id?' — '.htmlspecialchars($proyecto['nombre']):''?></h2></div>
<?php if(!$proyecto_id):?>
<div style="text-align:center;padding:3rem;color:#94a3b8">Selecciona un proyecto desde el <a href="index.php">Dashboard</a> para ver su presupuesto.</div>
<?php else:?>
<?php if($msg):?><div class="msg ok show"><?=htmlspecialchars($msg)?></div><?php endif?>
<div class="sb">
    <div class="si"><div class="b"><?=count($categorias)?></div><div class="l">Etapas</div></div>
    <div class="si"><div class="b"><?=$totales['count']?></div><div class="l">Tareas</div></div>
    <div class="si"><div class="b"><?=$totales['completadas']?></div><div class="l">Completadas</div></div>
    <div class="si"><div class="b"><?=fmt($totales['estimado'])?></div><div class="l">Costo Estimado</div></div>
    <div class="si" style="background:#22c55e"><div class="b"><?=$totales['prog']?>%</div><div class="l">Avance General</div></div>
</div>
<div style="margin-bottom:0.8rem"><?php if($canEditPresupuesto):?><button class="btn-xs primary" onclick="addEtapa()" style="font-size:0.75rem;padding:5px 14px">+ Agregar Etapa</button><?php endif?></div>
<div class="tw"><table class="tbl"><thead><tr><th>#</th><th>Tarea</th><th>Responsable</th><th>Costo</th><th>%</th><th>Avance</th><th>Acciones</th></tr></thead><tbody>
<?php foreach($categorias as $cat):?>
<tr class="cr"><td></td><td colspan="5"><?=htmlspecialchars($cat['codigo'].' '.$cat['nombre'])?></td><td><?php if($canEditPresupuesto):?><button class="btn-xs primary" onclick="addTarea(<?=$cat['id']?>)">+ Tarea</button><?php endif?></td></tr>
<?php foreach($cat['partidas'] as $pt): $c=barColor($pt['progreso']);?>
<tr class="<?=$pt['completado']?'done':''?>">
    <td class="num"><?=$pt['_num']?></td>
    <td class="proc"><?=htmlspecialchars($pt['procedimiento'])?></td>
    <td class="resp"><?=htmlspecialchars($pt['responsable']?:'—')?></td>
    <td class="costo"><?=fmt($pt['costo_estimado'])?></td>
    <td class="pct" style="color:<?=$c?>"><?=$pt['progreso']?>%</td>
    <td><div class="bw"><div class="bf" style="width:<?=$pt['progreso']?>%;background:<?=$c?>"></div></div></td>
    <td>
        <div class="act">
            <?php if($canEditPresupuesto):?>
            <button class="btn-xs primary" onclick="editPartida(<?=htmlspecialchars(json_encode($pt))?>)">✏️</button>
            <button class="btn-xs danger" onclick="deletePartida(<?=$pt['id']?>)">🗑️</button>
            <?php endif?>
            <?php if($pt['archivo_resultado']):?>
                <a href="uploads/<?=htmlspecialchars($pt['archivo_resultado'])?>" target="_blank" class="btn-xs success" title="Ver documento">✅</a>
            <?php elseif(!$pt['completado'] && $canEditPresupuesto):?>
                <button class="btn-xs success" onclick="uploadDoc(<?=$pt['id']?>)">📎</button>
            <?php elseif($pt['completado']):?>
                <span class="btn-xs success" style="cursor:default">✅</span>
            <?php endif?>
        </div>
    </td>
</tr>
<?php endforeach;endforeach;?>
</tbody></table></div>
<?php endif?>
</main></div>

<!-- ═══ MODAL EDITAR ═══ -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <h3>Editar Tarea</h3>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="partida_id" id="editId">
            <label>Procedimiento</label><input name="procedimiento" id="editProc" required>
            <label>Responsable</label><input name="responsable" id="editResp">
            <label>Costo Estimado</label><input type="number" step="0.01" name="costo" id="editCosto">
            <label>Progreso (%)</label><input type="number" min="0" max="100" name="progreso" id="editProg">
            <label>Fecha Inicio</label><input type="date" name="fecha_inicio" id="editFI" onchange="calcDias()">
            <label>Fecha Fin</label><input type="date" name="fecha_fin" id="editFF" onchange="calcDias()">
            <label>Días</label><input type="number" name="dias" id="editDias" readonly style="background:#f1f5f9;cursor:default">
            <label>Depende de (hereda atrasos)</label><select name="dependencias" id="editDeps"><option value="">— Ninguna —</option></select>
            <div class="btn-row">
                <button type="button" class="btn-xs" onclick="closeModal()">Cancelar</button>
                <button type="submit" class="btn-xs primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ MODAL UPLOAD ═══ -->
<div class="modal-overlay" id="uploadModal">
    <div class="modal-box">
        <h3>Subir Documento Resultado</h3>
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <input type="hidden" name="partida_id" id="uploadId">
            <label>Documento (PDF, Word, Excel, imagen)</label>
            <input type="file" name="documento" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" required>
            <p style="font-size:0.7rem;color:#94a3b8;margin:0.3rem 0">Al subir el documento, la tarea se marcará como completada al 100%.</p>
            <div class="btn-row">
                <button type="button" class="btn-xs" onclick="closeUpload()">Cancelar</button>
                <button type="submit" class="btn-xs primary">Subir y Completar</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ MODAL NUEVA ETAPA ═══ -->
<div class="modal-overlay" id="etapaModal">
    <div class="modal-box">
        <h3>Nueva Etapa</h3>
        <form method="POST" id="etapaForm">
            <input type="hidden" name="action" value="add_etapa">
            <label>Código (ej: 1, 2, 3...)</label><input name="codigo" required>
            <label>Nombre</label><input name="nombre" required>
            <div class="btn-row">
                <button type="button" class="btn-xs" onclick="closeEtapa()">Cancelar</button>
                <button type="submit" class="btn-xs primary">Crear Etapa</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ MODAL NUEVA TAREA ═══ -->
<div class="modal-overlay" id="tareaModal">
    <div class="modal-box">
        <h3>Nueva Tarea</h3>
        <form method="POST" id="tareaForm">
            <input type="hidden" name="action" value="add_tarea">
            <input type="hidden" name="categoria_id" id="addCatId">
            <label>Tarea</label><input name="procedimiento" required>
            <label>Responsable</label><input name="responsable">
            <label>Costo Estimado</label><input type="number" step="0.01" name="costo" value="0">
            <label>Progreso (%)</label><input type="number" min="0" max="100" name="progreso" value="0">
            <label>Fecha Inicio</label><input type="date" name="fecha_inicio">
            <label>Fecha Fin</label><input type="date" name="fecha_fin">
            <label>Depende de (hereda atrasos)</label><select name="dependencias" id="addDeps"><option value="">— Ninguna —</option></select>
            <div class="btn-row">
                <button type="button" class="btn-xs" onclick="closeTarea()">Cancelar</button>
                <button type="submit" class="btn-xs primary">Crear Tarea</button>
            </div>
        </form>
    </div>
</div>

<script>
// Todas las partidas del proyecto para el selector de dependencias
var allPartidas = <?php
$allParts = [];
if($proyecto_id){foreach($categorias as $cat){foreach($cat['partidas'] as $pt){$allParts[] = ['id'=>$pt['id'],'name'=>$pt['procedimiento']];}}}
echo json_encode($allParts);
?>;

function calcDias(){
    var fi = document.getElementById('editFI').value;
    var ff = document.getElementById('editFF').value;
    if(fi && ff){
        var d1 = new Date(fi + 'T00:00:00');
        var d2 = new Date(ff + 'T00:00:00');
        var diff = Math.round((d2 - d1) / (1000*60*60*24));
        document.getElementById('editDias').value = diff >= 0 ? diff : 0;
    } else {
        document.getElementById('editDias').value = '';
    }
}
function editPartida(pt){
    document.getElementById('editId').value = pt.id;
    document.getElementById('editProc').value = pt.procedimiento||'';
    document.getElementById('editResp').value = pt.responsable||'';
    document.getElementById('editCosto').value = pt.costo_estimado||0;
    document.getElementById('editProg').value = pt.progreso||0;
    document.getElementById('editFI').value = pt.fecha_inicio||'';
    document.getElementById('editFF').value = pt.fecha_fin||'';
    calcDias();
    // Llenar selector de dependencias (tarea precedente única)
    var sel = document.getElementById('editDeps');
    sel.innerHTML = '<option value="">— Ninguna —</option>';
    var depId = (pt.dependencias||'').split(',')[0]; // primer ID como precedente
    allPartidas.forEach(function(p){
        if(p.id == pt.id) return; // no depende de sí mismo
        var opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.name;
        if(depId == String(p.id)) opt.selected = true;
        sel.appendChild(opt);
    });
    document.getElementById('editModal').classList.add('show');
}
function closeModal(){ document.getElementById('editModal').classList.remove('show'); }
function deletePartida(id){
    if(!confirm('¿Eliminar esta partida?')) return;
    var f = document.createElement('form'); f.method='POST';
    f.innerHTML = '<input name="action" value="delete"><input name="partida_id" value="'+id+'">';
    document.body.appendChild(f); f.submit();
}
function uploadDoc(id){
    document.getElementById('uploadId').value = id;
    document.getElementById('uploadModal').classList.add('show');
}
function closeUpload(){ document.getElementById('uploadModal').classList.remove('show'); }
// Nueva Etapa
function addEtapa(){ document.getElementById('etapaModal').classList.add('show'); }
function closeEtapa(){ document.getElementById('etapaModal').classList.remove('show'); }
// Nueva Tarea
function addTarea(catId){
    document.getElementById('addCatId').value = catId;
    // Llenar dependencias
    var sel = document.getElementById('addDeps');
    sel.innerHTML = '<option value="">— Ninguna —</option>';
    allPartidas.forEach(function(p){
        var opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.name;
        sel.appendChild(opt);
    });
    document.getElementById('tareaModal').classList.add('show');
}
function closeTarea(){ document.getElementById('tareaModal').classList.remove('show'); }

document.getElementById('editModal').addEventListener('click',function(e){if(e.target===this)closeModal();});
document.getElementById('uploadModal').addEventListener('click',function(e){if(e.target===this)closeUpload();});
document.getElementById('etapaModal').addEventListener('click',function(e){if(e.target===this)closeEtapa();});
document.getElementById('tareaModal').addEventListener('click',function(e){if(e.target===this)closeTarea();});
</script>
<script>function toggleDark(){var b=document.body;var c=document.getElementById("darkSwitch");b.classList.toggle("dark",c.checked);localStorage.setItem("sica-dark",c.checked?"1":"0")}(function(){if(localStorage.getItem("sica-dark")==="1"){document.body.classList.add("dark");var c=document.getElementById("darkSwitch");if(c)c.checked=true}})();</script></body></html>
