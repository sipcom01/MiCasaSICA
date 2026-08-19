<?php
/**
 * SICA Admin - Gestión de Usuarios
 *
 * CRUD de usuarios del panel administrativo. Solo accesible para roles admin/director.
 *
 * Funcionalidades:
 *   - Formulario para crear nuevos usuarios (nombre, correo, contraseña, rol)
 *   - Listado de usuarios registrados con badge activo/inactivo
 *   - Modal para editar nombre, rol y cambiar contraseña
 *   - Botón para activar/desactivar usuarios (toggle lógico, no elimina)
 *
 * Roles disponibles: Director General, Líder de Proyecto, Gestor de Trámites,
 * Arquitecto, Ingeniero, Abogado, Finanzas, Colaborador.
 */
define('SICA_APP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';
Auth::requireLogin();
$user = Auth::currentUser();
$db = Database::getInstance()->getPdo();

// Solo admin y director pueden gestionar usuarios
if(!in_array($user['rol'],['admin','director'])){header('Location: index.php');exit;}

// Inicializar mensaje de feedback
$feedback = '';

// ─── PROCESAR FORMULARIOS POST ────────────────────────────────
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(isset($_POST['add_user'])){
        // Nuevo usuario: username = correo, contraseña temporal aleatoria
        // Se envía email de invitación para que establezca su contraseña
        $correo = trim($_POST['correo'] ?? '');
        if(empty($correo)){ $feedback = '<div class="msg msg-e">El correo electrónico es obligatorio.</div>'; }
        else {
            try {
                $token = generarToken();
                $tokenExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                $hashTemp = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
                $db->prepare("INSERT INTO usuarios (username, password_hash, nombre, correo, telefono, rol, reset_token, reset_token_expires) VALUES (:u,:p,:n,:c,:t,:r,:tok,:texp)")
                   ->execute([
                       'u'=>$correo, 'p'=>$hashTemp, 'n'=>$_POST['nombre'],
                       'c'=>$correo, 't'=>$_POST['telefono']?:null, 'r'=>$_POST['rol'],
                       'tok'=>$token, 'texp'=>$tokenExpires
                   ]);
                require_once __DIR__ . '/../vendor/autoload.php';
                enviarInvitacion($correo, $_POST['nombre'], $token);
                $feedback = '<div class="msg msg-s">Usuario creado. Se ha enviado invitación a <strong>'.htmlspecialchars($correo).'</strong> para establecer contraseña.</div>';
            } catch (\Exception $e) {
                if(strpos($e->getMessage(),'UNIQUE')!==false){
                    $feedback = '<div class="msg msg-e">Ya existe un usuario con ese correo electrónico.</div>';
                } else {
                    $feedback = '<div class="msg msg-w">Usuario creado pero el envío de invitación falló: '.htmlspecialchars($e->getMessage()).'</div>';
                }
            }
        }
    } elseif(isset($_POST['edit_user'])){
        // Editar usuario (nombre, correo, teléfono, rol — sin contraseña)
        $data=['id'=>(int)$_POST['id'],'nombre'=>$_POST['nombre'],'correo'=>$_POST['correo']?:null,'telefono'=>$_POST['telefono']?:null,'rol'=>$_POST['rol']];
        $set = "nombre=:nombre, correo=:correo, telefono=:telefono, rol=:rol";
        $db->prepare("UPDATE usuarios SET $set WHERE id=:id")->execute($data);
        $feedback = '<div class="msg msg-s">Usuario actualizado.</div>';
    } elseif(isset($_POST['reenviar_invitacion'])){
        // Reenviar email de invitación
        $uid = (int)$_POST['id'];
        $uu = $db->prepare("SELECT nombre, correo FROM usuarios WHERE id=:id"); $uu->execute(['id'=>$uid]); $dest = $uu->fetch();
        if($dest && !empty($dest['correo'])){
            try {
                $token = generarToken();
                $tokenExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                $db->prepare("UPDATE usuarios SET reset_token=:tok, reset_token_expires=:texp WHERE id=:id")
                   ->execute(['tok'=>$token,'texp'=>$tokenExpires,'id'=>$uid]);
                require_once __DIR__ . '/../vendor/autoload.php';
                enviarInvitacion($dest['correo'], $dest['nombre'], $token);
                $feedback = '<div class="msg msg-s">Invitación reenviada a <strong>'.htmlspecialchars($dest['correo']).'</strong>.</div>';
            } catch (\Exception $e) {
                $feedback = '<div class="msg msg-e">Error al reenviar: '.htmlspecialchars($e->getMessage()).'</div>';
            }
        }
    } elseif(isset($_POST['toggle_active'])){
        // Activar/desactivar usuario (toggle booleano)
        $u = $db->prepare("SELECT activo FROM usuarios WHERE id=:id"); $u->execute(['id'=>(int)$_POST['id']]); $uu = $u->fetch();
        $db->prepare("UPDATE usuarios SET activo=:a WHERE id=:id")->execute(['a'=>$uu['activo']?0:1,'id'=>(int)$_POST['id']]);
    } elseif(isset($_POST['delete_user'])){
        // Eliminar usuario permanentemente
        $db->prepare("DELETE FROM usuarios WHERE id=:id")->execute(['id'=>(int)$_POST['id']]);
    } elseif(isset($_POST['assign_projects'])){
        // Asignar proyectos a un usuario con nivel de permiso
        $uid = (int)$_POST['usuario_id'];
        $db->prepare("DELETE FROM usuario_proyectos WHERE usuario_id=:uid")->execute(['uid'=>$uid]);
        if(!empty($_POST['proyectos'])){
            $ins = $db->prepare("INSERT INTO usuario_proyectos (usuario_id, proyecto_id, permiso) VALUES (:uid,:pid,:perm)");
            foreach($_POST['proyectos'] as $pid){
                $perm = isset($_POST['permiso_'.$pid]) ? $_POST['permiso_'.$pid] : 'editar';
                $ins->execute(['uid'=>$uid,'pid'=>(int)$pid,'perm'=>$perm]);
            }
        }
    }
}

$usuarios = $db->query("SELECT * FROM usuarios ORDER BY id")->fetchAll();
$proyectos = $db->query("SELECT id, nombre FROM proyectos ORDER BY nombre")->fetchAll();
// Cargar asignaciones actuales por usuario
$asignaciones = [];
$ass = $db->query("SELECT usuario_id, proyecto_id, permiso FROM usuario_proyectos")->fetchAll();
foreach($ass as $a){ $asignaciones[$a['usuario_id']][$a['proyecto_id']] = $a['permiso']; }
$roles = ['director'=>'Director General','lider'=>'Líder de Proyecto','gestor'=>'Gestor de Trámites','arquitecto'=>'Arquitecto','ingeniero'=>'Ingeniero','abogado'=>'Abogado','finanzas'=>'Finanzas','colaborador'=>'Colaborador'];
?>
<!DOCTYPE html><html lang="es-MX"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Usuarios | SICA</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 36 44'%3E%3Crect x='1.5' y='1.5' width='33' height='41' rx='2' fill='none' stroke='%2350C8C6' stroke-width='2.5'/%3E%3Crect x='8' y='24' width='7' height='14' fill='%23FFFFFF'/%3E%3Crect x='21' y='12' width='7' height='26' fill='%23FFFFFF'/%3E%3C/svg%3E">
<link rel="stylesheet" href="assets/css/admin.css?v=7"><style>
.pg{background:#fff;border-radius:12px;padding:1.5rem;box-shadow:0 1px 4px rgba(0,0,0,0.06);margin-bottom:1.5rem}
.pg h3{font-size:1rem;margin-bottom:1rem;color:#132236}
.fg{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:0.75rem;margin-bottom:1rem}
.fg label{font-size:0.8rem;color:#64748b;display:block;margin-bottom:0.2rem}
.fg input,.fg select{padding:0.5rem 0.75rem;border:1px solid #e2e8f0;border-radius:8px;font-size:0.85rem;width:100%;box-sizing:border-box}
.ub{display:flex;align-items:center;justify-content:space-between;padding:0.75rem 1rem;border-bottom:1px solid #f1f5f9}
.ub:last-child{border:none}.ub .un{font-weight:600;color:#1e293b}.ub .ur{font-size:0.8rem;font-weight:600}.ub .ue{color:#64748b;font-size:0.8rem}
.btns{display:flex;gap:0.5rem}.btn{padding:0.5rem 1rem;border:none;border-radius:8px;cursor:pointer;font-weight:600;font-size:0.8rem}
.btn-p{background:#50C8C6;color:#132236}.btn-d{background:#ef4444;color:#fff}.btn-s{background:#e2e8f0;color:#475569}
.badge{display:inline-block;padding:0.2rem 0.6rem;border-radius:20px;font-size:0.7rem;font-weight:700}
.bg-g{background:#dcfce7;color:#166534}.bg-r{background:#fee2e2;color:#991b1b}.bg-b{background:#dbeafe;color:#1e40af}
.modal{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:100;align-items:center;justify-content:center}
.modal.show{display:flex}.modal-box{background:#fff;border-radius:12px;padding:2rem;width:90%;max-width:500px}
.msg{padding:0.75rem 1rem;border-radius:8px;font-size:0.85rem;margin-bottom:1rem}
.msg-s{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}
.msg-e{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
.msg-w{background:#fef3c7;color:#92400e;border:1px solid #fde68a}
</style></head><body><div class="admin-layout">
<!-- Sidebar admin -->
<?php $activePage="usuarios"; include __DIR__."/includes/sidebar.php"; ?>
<!-- Contenido principal -->
<main class="admin-main"><a href="index.php" style="color:#64748b;font-size:0.9rem">← Proyectos</a><div class="admin-header"><h2>👥 Gestión de Usuarios</h2></div>
<?php if($feedback) echo $feedback; ?>

<!-- Formulario para crear nuevo usuario -->
<div class="pg">
<h3>➕ Nuevo Usuario</h3>
<form method="POST">
<input type="hidden" name="add_user" value="1">
<div class="fg">
<div><label>Nombre completo</label><input type="text" name="nombre" required></div>
<div><label>Correo electrónico *</label><input type="email" name="correo" required placeholder="usuario@micasasica.com"></div>
<div><label>Teléfono</label><input type="text" name="telefono"></div>
<div><label>Rol</label><select name="rol" required><?php foreach($roles as $k=>$v):?><option value="<?=$k?>"><?=$v?></option><?php endforeach?></select></div>
</div>
<p style="font-size:0.8rem;color:#64748b;margin-bottom:1rem">Se enviará un correo de invitación para que el usuario establezca su contraseña.</p>
<button type="submit" class="btn btn-p">➕ Crear Usuario</button>
</form>
</div>

<!-- Listado de usuarios existentes -->
<div class="pg">
<h3>👤 Usuarios Registrados (<?=count($usuarios)?>)</h3>
<div class="user-grid">
<?php foreach($usuarios as $u):?>
<div class="user-card">
    <div class="uc-head">
        <div class="uc-avatar"><?=strtoupper(mb_substr($u['nombre'],0,1))?></div>
        <div>
            <div class="uc-name"><?=htmlspecialchars($u['nombre'])?></div>
            <span class="badge <?=$u['activo']?'bg-g':'bg-r'?>"><?=$u['activo']?'Activo':'Inactivo'?></span>
        </div>
    </div>
    <div class="uc-meta">
        <span>👤 <?=htmlspecialchars($roles[$u['rol']]??$u['rol'])?></span>
        <span>✉️ <?=htmlspecialchars($u['correo']?:$u['username'])?></span>
        <?php if($u['telefono']):?><span>📞 <?=htmlspecialchars($u['telefono'])?></span><?php endif?>
    </div>
    <div class="uc-actions">
        <button class="btn btn-s" onclick="editUser(<?=$u['id']?>,'<?=htmlspecialchars($u['nombre'],ENT_QUOTES)?>','<?=htmlspecialchars($u['correo']??'',ENT_QUOTES)?>','<?=htmlspecialchars($u['telefono']??'',ENT_QUOTES)?>','<?=$u['rol']?>')" title="Editar">✏️</button>
        <form method="POST" style="display:inline"><input type="hidden" name="toggle_active" value="1"><input type="hidden" name="id" value="<?=$u['id']?>"><button type="submit" class="btn <?=$u['activo']?'btn-d':'btn-p'?>" title="<?=$u['activo']?'Desactivar':'Activar'?>"><?=$u['activo']?'⏸️':'▶️'?></button></form>
        <button class="btn btn-p" onclick="assignProjects(<?=$u['id']?>,'<?=htmlspecialchars($u['nombre'],ENT_QUOTES)?>')" title="Asignar Proyectos">📋</button>
        <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar permanentemente a <?=htmlspecialchars($u['nombre'],ENT_QUOTES)?>?')"><input type="hidden" name="delete_user" value="1"><input type="hidden" name="id" value="<?=$u['id']?>"><button type="submit" class="btn btn-d" title="Eliminar">🗑️</button></form>
    </div>
</div>
<?php endforeach?>
</div>
</div>
</main></div>

<!-- Modal para editar usuario -->
<div class="modal" id="editModal" onclick="if(event.target===this)this.classList.remove('show')"><div class="modal-box" onclick="event.stopPropagation()">
<h3>✏️ Editar Usuario</h3>
<form method="POST">
<input type="hidden" name="edit_user" value="1"><input type="hidden" name="id" id="editId">
<div class="fg">
<div><label>Nombre completo</label><input type="text" name="nombre" id="editNombre" required></div>
<div><label>Correo electrónico</label><input type="email" name="correo" id="editCorreo"></div>
<div><label>Teléfono</label><input type="text" name="telefono" id="editTelefono"></div>
<div><label>Rol</label><select name="rol" id="editRol"><?php foreach($roles as $k=>$v):?><option value="<?=$k?>"><?=$v?></option><?php endforeach?></select></div>
</div>
<div style="display:flex;gap:0.5rem;margin-bottom:0.75rem"><button type="submit" class="btn btn-p">💾 Guardar</button><button type="button" class="btn btn-s" onclick="document.getElementById('editModal').classList.remove('show')">Cancelar</button></div>
</form>
<form method="POST" style="border-top:1px solid #e2e8f0;padding-top:0.75rem">
<input type="hidden" name="reenviar_invitacion" value="1"><input type="hidden" name="id" id="reenviarId">
<p style="font-size:0.8rem;color:#64748b;margin-bottom:0.5rem">¿El usuario no recibió la invitación? Reenvía el correo para establecer contraseña.</p>
<button type="submit" class="btn btn-s" style="width:100%">📧 Reenviar Invitación</button>
</form>
</div></div>

<!-- Modal para asignar proyectos -->
<div class="modal" id="projectsModal" onclick="if(event.target===this)this.classList.remove('show')"><div class="modal-box" onclick="event.stopPropagation()">
<h3 id="projectsModalTitle">📋 Asignar Proyectos</h3>
<form method="POST">
<input type="hidden" name="assign_projects" value="1"><input type="hidden" name="usuario_id" id="assignUserId">
<div style="max-height:350px;overflow-y:auto;margin-bottom:1rem">
<?php foreach($proyectos as $pr):?>
<div style="display:flex;align-items:center;gap:0.5rem;padding:0.4rem 0;font-size:0.85rem;border-bottom:1px solid #f1f5f9">
    <input type="checkbox" name="proyectos[]" value="<?=$pr['id']?>" class="proj-check" onchange="togglePerm(this)" style="width:auto">
    <span style="flex:1"><?=htmlspecialchars($pr['nombre'])?></span>
    <select name="permiso_<?=$pr['id']?>" class="proj-perm" style="width:180px;padding:0.3rem;border:1px solid #e2e8f0;border-radius:6px;font-size:0.8rem" disabled>
        <option value="ver">👁 Solo ver</option>
        <option value="editar">✏️ Editar todo</option>
        <option value="editar_gantt">📅 Editar Gantt</option>
        <option value="editar_presupuesto">💰 Editar Presupuesto</option>
    </select>
</div>
<?php endforeach?>
<?php if(empty($proyectos)):?><p style="color:#94a3b8;text-align:center">No hay proyectos creados.</p><?php endif?>
</div>
<div style="display:flex;gap:0.5rem"><button type="submit" class="btn btn-p">💾 Guardar</button><button type="button" class="btn btn-s" onclick="document.getElementById('projectsModal').classList.remove('show')">Cancelar</button></div>
</form>
</div></div>

<script>
var asignaciones = <?=json_encode($asignaciones)?>;
function togglePerm(cb){
    var row = cb.parentElement;
    var sel = row.querySelector('.proj-perm');
    sel.disabled = !cb.checked;
}
function assignProjects(uid, nombre){
    document.getElementById('assignUserId').value = uid;
    document.getElementById('projectsModalTitle').textContent = '📋 Proyectos de ' + nombre;
    var userPerms = asignaciones[uid] || {};
    document.querySelectorAll('.proj-check').forEach(function(cb){
        var pid = parseInt(cb.value);
        var perm = userPerms[pid] || 'editar';
        cb.checked = !!userPerms[pid];
        var sel = cb.parentElement.querySelector('.proj-perm');
        sel.value = perm;
        sel.disabled = !cb.checked;
    });
    document.getElementById('projectsModal').classList.add('show');
}
// Rellena el modal de edición
function editUser(id, nombre, correo, telefono, rol){
    document.getElementById('editId').value=id;
    document.getElementById('reenviarId').value=id;
    document.getElementById('editNombre').value=nombre;
    document.getElementById('editCorreo').value=correo||'';
    document.getElementById('editTelefono').value=telefono||'';
    document.getElementById('editRol').value=rol;
    document.getElementById('editModal').classList.add('show');
}
</script>
<script>function toggleDark(){var b=document.body;var c=document.getElementById("darkSwitch");b.classList.toggle("dark",c.checked);localStorage.setItem("sica-dark",c.checked?"1":"0")}(function(){if(localStorage.getItem("sica-dark")==="1"){document.body.classList.add("dark");var c=document.getElementById("darkSwitch");if(c)c.checked=true}})();</script></body></html>
