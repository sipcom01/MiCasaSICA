<?php
define('SICA_APP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
Auth::requireLogin();
$user = Auth::currentUser();
$db = Database::getInstance()->getPdo();
$uid = $user['id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid = isset($_POST['partida_id']) ? (int)$_POST['partida_id'] : 0;
    if (isset($_POST['action']) && $_POST['action'] === 'edit_fechas') {
        $fi = $_POST['fecha_inicio'] ?: null; $ff = $_POST['fecha_fin'] ?: null;
        $db->prepare("UPDATE presupuesto_partidas SET fecha_inicio=:fi, fecha_fin=:ff WHERE id=:id")->execute(['fi'=>$fi,'ff'=>$ff,'id'=>$pid]);
        $db->prepare("INSERT INTO tarea_historial (partida_id, usuario_id, accion, detalle) VALUES (:p,:u,:a,:d)")->execute(['p'=>$pid,'u'=>$uid,'a'=>'ajuste_fechas','d'=>"Inicio: $fi → Fin: $ff"]);
        $msg = 'Fechas actualizadas.';
    }
    if (isset($_POST['action']) && $_POST['action'] === 'edit_presupuesto') {
        $monto = (float)$_POST['presupuesto_tercero']; $nota = $_POST['nota_presupuesto'] ?? '';
        $db->prepare("UPDATE presupuesto_partidas SET presupuesto_tercero=:m, nota_presupuesto=:n, costo_real=costo_estimado+:m WHERE id=:id")->execute(['m'=>$monto,'n'=>$nota,'id'=>$pid]);
        $db->prepare("INSERT INTO tarea_historial (partida_id, usuario_id, accion, detalle) VALUES (:p,:u,:a,:d)")->execute(['p'=>$pid,'u'=>$uid,'a'=>'presupuesto_tercero','d'=>"Monto: \$$monto. $nota"]);
        $msg = 'Presupuesto registrado.';
    }
    if (isset($_FILES['documento']) && $_FILES['documento']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['documento']['name'], PATHINFO_EXTENSION);
        $filename = 'doc_' . $pid . '_' . time() . '.' . $ext;
        $dest = __DIR__ . '/uploads/' . $filename;
        if (move_uploaded_file($_FILES['documento']['tmp_name'], $dest)) {
            $completar = isset($_POST['completar']) && $_POST['completar'] === '1';
            if ($completar) {
                $db->prepare("UPDATE presupuesto_partidas SET archivo_resultado=:a, completado=1, progreso=100, fecha_terminacion_real=DATE('now') WHERE id=:id")->execute(['a'=>$filename,'id'=>$pid]);
                $db->prepare("INSERT INTO tarea_historial (partida_id, usuario_id, accion, detalle) VALUES (:p,:u,:a,:d)")->execute(['p'=>$pid,'u'=>$uid,'a'=>'completada','d'=>"Doc: $filename"]);
                $msg = 'Documento subido. Tarea completada al 100%.';
            } else {
                $db->prepare("UPDATE presupuesto_partidas SET archivo_resultado=:a WHERE id=:id")->execute(['a'=>$filename,'id'=>$pid]);
                $db->prepare("INSERT INTO tarea_historial (partida_id, usuario_id, accion, detalle) VALUES (:p,:u,:a,:d)")->execute(['p'=>$pid,'u'=>$uid,'a'=>'documento_subido','d'=>"Archivo: $filename"]);
                $msg = 'Documento subido.';
            }
        }
    }
    // Finalizar sin cargar entregable: el responsable solicita el cierre con una descripción
    if (isset($_POST['action']) && $_POST['action'] === 'solicitar_cierre') {
        $pid = (int)$_POST['partida_id'];
        $desc = trim($_POST['cierre_descripcion'] ?? '');
        if ($desc !== '') {
            $db->prepare("UPDATE presupuesto_partidas SET cierre_solicitado=1, cierre_solicitado_por=:u, cierre_fecha_solicitud=datetime('now'), cierre_descripcion=:d, cierre_estado='pendiente' WHERE id=:id")
               ->execute(['u'=>$uid, 'd'=>$desc, 'id'=>$pid]);
            $db->prepare("INSERT INTO tarea_historial (partida_id, usuario_id, accion, detalle) VALUES (:p,:u,:a,:d)")
               ->execute(['p'=>$pid,'u'=>$uid,'a'=>'solicitud_cierre','d'=>$desc]);
            $msg = 'Solicitud de cierre enviada. Queda pendiente de validación por el líder del proyecto.';
        }
    }
    // Validación del cierre sin entregable (solo líder, director o admin)
    if (isset($_POST['action']) && $_POST['action'] === 'validar_cierre') {
        if (in_array($user['rol'], ['lider','director','admin'])) {
            $pid = (int)$_POST['partida_id'];
            $decision = $_POST['decision'] ?? '';
            if ($decision === 'aprobar') {
                $db->prepare("UPDATE presupuesto_partidas SET completado=1, progreso=100, fecha_terminacion_real=DATE('now'), cierre_estado='aprobado', cierre_validado_por=:u, cierre_fecha_validacion=datetime('now') WHERE id=:id")
                   ->execute(['u'=>$uid,'id'=>$pid]);
                $db->prepare("INSERT INTO tarea_historial (partida_id, usuario_id, accion, detalle) VALUES (:p,:u,:a,:d)")
                   ->execute(['p'=>$pid,'u'=>$uid,'a'=>'cierre_aprobado','d'=>'Cierre sin entregable validado por el líder']);
                $msg = 'Tarea cerrada correctamente.';
            } elseif ($decision === 'rechazar') {
                $db->prepare("UPDATE presupuesto_partidas SET cierre_estado='rechazado', cierre_solicitado=0, cierre_validado_por=:u, cierre_fecha_validacion=datetime('now') WHERE id=:id")
                   ->execute(['u'=>$uid,'id'=>$pid]);
                $db->prepare("INSERT INTO tarea_historial (partida_id, usuario_id, accion, detalle) VALUES (:p,:u,:a,:d)")
                   ->execute(['p'=>$pid,'u'=>$uid,'a'=>'cierre_rechazado','d'=>'Cierre sin entregable rechazado']);
                $msg = 'Solicitud de cierre rechazada.';
            }
        }
    }
}

$tareas = $db->prepare("SELECT pp.*, pc.nombre as etapa, pj.nombre as proyecto, pj.id as proyecto_id FROM presupuesto_partidas pp JOIN presupuesto_categorias pc ON pp.categoria_id=pc.id JOIN proyectos pj ON pc.proyecto_id=pj.id WHERE pp.responsable=:n AND pp.completado=0 ORDER BY pp.fecha_fin ASC");
$tareas->execute(['n'=>$user['nombre']]); $tareas = $tareas->fetchAll();

$tareasCompletadas = $db->prepare("SELECT pp.*, pc.nombre as etapa, pj.nombre as proyecto FROM presupuesto_partidas pp JOIN presupuesto_categorias pc ON pp.categoria_id=pc.id JOIN proyectos pj ON pc.proyecto_id=pj.id WHERE pp.responsable=:n AND pp.completado=1 ORDER BY pp.fecha_terminacion_real DESC LIMIT 10");
$tareasCompletadas->execute(['n'=>$user['nombre']]); $tareasCompletadas = $tareasCompletadas->fetchAll();

// Solicitudes de cierre sin entregable pendientes de validar (visible para líder, director y admin)
$validaciones = [];
if (in_array($user['rol'], ['lider','director','admin'])) {
    $vq = $db->prepare("SELECT pp.*, pc.nombre as etapa, pj.nombre as proyecto, pj.id as proyecto_id, us.nombre as solicitante FROM presupuesto_partidas pp JOIN presupuesto_categorias pc ON pp.categoria_id=pc.id JOIN proyectos pj ON pc.proyecto_id=pj.id LEFT JOIN usuarios us ON us.id=pp.cierre_solicitado_por WHERE pp.cierre_estado='pendiente' ORDER BY pp.cierre_fecha_solicitud ASC");
    $vq->execute(); $validaciones = $vq->fetchAll();
}
?><!DOCTYPE html><html lang="es-MX"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Mis Tareas | SICA</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 36 44'%3E%3Crect x='1.5' y='1.5' width='33' height='41' rx='2' fill='none' stroke='%2350C8C6' stroke-width='2.5'/%3E%3Crect x='8' y='24' width='7' height='14' fill='%23FFFFFF'/%3E%3Crect x='21' y='12' width='7' height='26' fill='%23FFFFFF'/%3E%3C/svg%3E">
<style>
*{box-sizing:border-box;margin:0;padding:0}body,html{height:100%;overflow:hidden;font-family:system-ui,-apple-system,sans-serif;background:#f8fafc}
.layout{display:flex;height:100vh}.sidebar{width:260px;background:#132236;color:#fff;flex-shrink:0;display:flex;flex-direction:column}
.sidebar-brand{display:flex;align-items:center;gap:.75rem;padding:1.5rem;border-bottom:1px solid rgba(255,255,255,.1)}
.brand-name{font-weight:700;font-size:1rem}.brand-sub{font-size:.75rem;color:#94a3b8}
.sidebar-nav{flex:1;padding:1rem .75rem}
.nav-item{display:flex;align-items:center;gap:.75rem;padding:.7rem 1rem;border-radius:8px;color:#cbd5e1;text-decoration:none;font-size:.9rem;margin-bottom:.25rem}
.nav-item:hover{background:rgba(255,255,255,.08);color:#fff}.nav-item.active{background:#50C8C6;color:#132236;font-weight:600}
.main{flex:1;display:flex;overflow:hidden}.panel{overflow-y:auto;padding:1.5rem}
.panel-tasks{flex:1.2;border-right:1px solid #e2e8f0;background:#f8fafc}
.panel-chat{flex:1;display:flex;flex-direction:column;background:#fff}
h2{font-size:1.2rem;color:#132236;margin:0 0 1rem 0}.msg{padding:.6rem 1rem;border-radius:8px;margin-bottom:1rem;font-size:.85rem;background:#dcfce7;color:#166534}
.task-card{background:#fff;border-radius:10px;padding:1rem;margin-bottom:.75rem;box-shadow:0 1px 3px rgba(0,0,0,.06);border-left:3px solid #3b82f6}
.task-card.delayed{border-left-color:#ef4444}.task-card.done{border-left-color:#22c55e;opacity:.7}
.tc-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.5rem}
.tc-name{font-weight:600;font-size:.9rem;color:#1e293b}.tc-meta{font-size:.75rem;color:#64748b;margin-top:.25rem}
.tc-actions{display:flex;flex-direction:column;gap:.4rem;margin-top:.6rem}
.tc-actions .btn{width:100%;text-align:center}
.tc-dates{display:grid;grid-template-columns:1fr 1fr;gap:.4rem;margin-top:.4rem}
.tc-date{background:#f8fafc;border-radius:6px;padding:.4rem .5rem}
.tc-date label{display:block;font-size:.6rem;color:#94a3b8;text-transform:uppercase;margin-bottom:.1rem}
.tc-date span{font-size:.75rem;font-weight:600;color:#1e293b}
.tc-date-full{grid-column:1/-1}
.tc-badge{font-size:.65rem;padding:.15rem .5rem;border-radius:10px;font-weight:600;white-space:nowrap}
.badge-pend{background:#fef3c7;color:#92400e}.badge-ok{background:#dcfce7;color:#166534}.badge-late{background:#fee2e2;color:#991b1b}
.section-title{font-size:.85rem;font-weight:600;color:#94a3b8;margin:1.5rem 0 .75rem 0;text-transform:uppercase;letter-spacing:.5px}
.btn{padding:.4rem .8rem;border-radius:6px;border:1px solid #e2e8f0;background:#fff;cursor:pointer;font-size:.75rem;white-space:nowrap;text-decoration:none;display:inline-block}
.btn:hover{background:#f1f5f9}.btn-p{background:#50C8C6;color:#132236;border:none;font-weight:600}.btn-p:hover{background:#3db8b6}.btn-d{color:#ef4444;border-color:#ef4444}
.chat-messages{flex:1;overflow-y:auto;padding:1rem;display:flex;flex-direction:column;gap:.75rem}
.chat-msg{max-width:85%;padding:.75rem 1rem;border-radius:12px;font-size:.85rem;line-height:1.5;word-break:break-word}
.chat-msg.user{align-self:flex-end;background:#50C8C6;color:#132236}
.chat-msg.ai{align-self:flex-start;background:#f1f5f9;color:#1e293b}
.chat-input-wrap{display:flex;gap:.5rem;padding:1rem;border-top:1px solid #e2e8f0;background:#fff}
.chat-input-wrap textarea{flex:1;padding:.6rem .8rem;border:1px solid #e2e8f0;border-radius:8px;resize:none;font-size:.85rem;font-family:inherit;rows:2}
.chat-input-wrap textarea:focus{outline:none;border-color:#50C8C6}
.chat-typing{font-size:.75rem;color:#94a3b8;padding:.25rem 1rem;display:none}.chat-typing.show{display:block}
.modal{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center}
.modal.show{display:flex}.modal-box{background:#fff;border-radius:12px;padding:1.5rem;width:500px;max-width:90vw;max-height:85vh;overflow-y:auto}
.modal-box h3{margin:0 0 1rem 0;font-size:1rem;color:#132236}
.modal-box label{display:block;font-size:.8rem;font-weight:600;color:#475569;margin-bottom:.25rem;margin-top:.75rem}
.modal-box input,.modal-box select,.modal-box textarea{width:100%;padding:.5rem;border:1px solid #e2e8f0;border-radius:6px;font-size:.85rem;box-sizing:border-box}
.modal-box textarea{resize:vertical;min-height:80px;font-family:inherit}
.btn-row{display:flex;gap:.5rem;justify-content:flex-end;margin-top:1.25rem}
.info-box{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:.75rem;margin:.5rem 0;font-size:.8rem;color:#166534}
.warn-box{background:#fef3c7;border:1px solid #fde68a;border-radius:8px;padding:.75rem;margin:.5rem 0;font-size:.8rem;color:#92400e}
.dark-switch-wrap{display:flex;align-items:center;gap:.5rem;padding:.5rem 1.5rem}
.dark-switch{position:relative;width:40px;height:22px;flex-shrink:0}.dark-switch input{display:none}
.dark-switch .slider{position:absolute;top:0;left:0;right:0;bottom:0;background:#475569;border-radius:22px;cursor:pointer;transition:.3s}
.dark-switch .slider:before{content:"";position:absolute;height:16px;width:16px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s}
.dark-switch input:checked+.slider{background:#50C8C6}.dark-switch input:checked+.slider:before{transform:translateX(18px)}
.dark-switch-label{font-size:.7rem;color:#94a3b8}
.user-info-wrap{display:flex;align-items:center;gap:.75rem;padding:1rem 1.5rem;border-top:1px solid rgba(255,255,255,.1);margin-top:auto}
.user-avatar{width:36px;height:36px;border-radius:50%;background:#50C8C6;color:#132236;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;flex-shrink:0}
.user-name{font-size:.85rem;font-weight:500}.user-logout{font-size:.7rem;color:#94a3b8;text-decoration:none}
body.dark{background:#0f172a}.dark .panel-tasks{background:#0f172a}.dark .panel-chat{background:#1e293b}
.dark .task-card{background:#1e293b}.dark .tc-name{color:#e2e8f0}.dark .tc-meta{color:#94a3b8}
.dark .tc-date{background:#0f172a}.dark .tc-date span{color:#e2e8f0}
.dark .btn{background:#334155;border-color:#475569;color:#e2e8f0}.dark .btn:hover{background:#475569}
.dark .chat-messages{background:#0f172a}.dark .chat-msg.ai{background:#1e293b;color:#e2e8f0}
.dark .chat-input-wrap{background:#1e293b;border-color:#334155}
.dark .chat-input-wrap textarea{background:#0f172a;color:#e2e8f0;border-color:#475569}
.dark h2{color:#e2e8f0}.dark .section-title{color:#64748b}.dark .modal-box{background:#1e293b}.dark .chat-header{border-color:#334155}.dark .chat-close{color:#94a3b8}
.dark .modal-box h3{color:#e2e8f0}.dark .modal-box label{color:#94a3b8}
.dark .modal-box input,.dark .modal-box select,.dark .modal-box textarea{background:#0f172a;color:#e2e8f0;border-color:#475569}
.nav-item{position:relative}
.nav-icon{font-size:1.15rem;flex-shrink:0;width:24px;text-align:center}
.tasks-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem}
.assistant-btn{display:none}
.chat-header{display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #e2e8f0}
.chat-close{display:none;background:none;border:none;font-size:1.3rem;cursor:pointer;color:#64748b;padding:.5rem 1rem;line-height:1}
@media(max-width:768px){.sidebar{width:68px}.sidebar .brand-text,.sidebar .nav-label,.sidebar .user-name,.sidebar .user-logout,.sidebar .dark-switch-label{display:none}.sidebar .sidebar-brand{justify-content:center;padding:1.25rem 0}.sidebar .nav-item{justify-content:center;padding:.75rem 0;gap:0}.sidebar .sidebar-nav{padding:1rem .5rem}.sidebar .user-info-wrap{justify-content:center;padding:1rem .5rem}.sidebar .dark-switch-wrap{justify-content:center;padding:.5rem 0}.sidebar .nav-item:hover::after{content:attr(data-label);position:absolute;left:calc(100% + 8px);top:50%;transform:translateY(-50%);background:#1b3050;color:#fff;padding:.4rem .8rem;border-radius:6px;font-size:.8rem;white-space:nowrap;z-index:1001;box-shadow:0 4px 14px rgba(0,0,0,.4);border:1px solid rgba(255,255,255,.12)}.panel-tasks,.panel-chat{flex:1}.main{flex-direction:column}.assistant-btn{display:block}.chat-close{display:block}.panel-chat{display:none}.panel-chat.open{display:flex;position:fixed;top:0;left:0;right:0;bottom:0;z-index:900}}
</style></head><body>
<div class="layout">
<aside class="sidebar">
<div class="sidebar-brand"><div class="brand-icon"><svg viewBox="0 0 36 44" width="30" height="37"><rect x="1.5" y="1.5" width="33" height="41" rx="2" fill="none" stroke="#50C8C6" stroke-width="2.5"/><rect x="8" y="24" width="7" height="14" fill="#FFFFFF"/><rect x="21" y="12" width="7" height="26" fill="#FFFFFF"/></svg></div><div class="brand-text"><div class="brand-name">SICA</div><div class="brand-sub">Panel Admin</div></div></div>
<nav class="sidebar-nav">
<a href="index.php" class="nav-item" data-label="Proyectos"><span class="nav-icon">📊</span><span class="nav-label">Proyectos</span></a>
<a href="mis-tareas.php" class="nav-item active" data-label="Mis Tareas"><span class="nav-icon">✅</span><span class="nav-label">Mis Tareas</span></a>
<a href="usuarios.php" class="nav-item" data-label="Usuarios"><span class="nav-icon">👥</span><span class="nav-label">Usuarios</span></a>
<a href="logout.php" class="nav-item" data-label="Salir"><span class="nav-icon">🚪</span><span class="nav-label">Salir</span></a>
</nav>
<div class="user-info-wrap"><div class="user-avatar"><?=strtoupper(mb_substr($user['nombre'],0,1))?></div><div><div class="user-name"><?=htmlspecialchars($user['nombre'])?></div><a href="logout.php" class="user-logout">Cerrar sesión</a></div></div>
<div class="dark-switch-wrap"><label class="dark-switch"><input type="checkbox" id="darkSwitch" onchange="toggleDark()"><span class="slider"></span></label><span class="dark-switch-label">Modo Noche</span></div>
</aside>
<div class="main">
<div class="panel panel-tasks">
<div class="tasks-header">
<h2>✅ Mis Tareas</h2>
<button class="btn btn-p assistant-btn" onclick="openAssistant()">🤖 Asistente SICA</button>
</div>
<?php if($msg):?><div class="msg"><?=htmlspecialchars($msg)?></div><?php endif?>
<?php if(empty($tareas)&&empty($tareasCompletadas)):?><p style="color:#94a3b8;text-align:center;padding:2rem">No tienes tareas asignadas.</p><?php endif?>
<?php foreach($tareas as $t):$hoy=date('Y-m-d');$atrasada=$t['fecha_fin']&&$t['fecha_fin']<$hoy;?>
<div class="task-card <?=$atrasada?'delayed':''?>">
<div class="tc-header"><div><div class="tc-name"><?=htmlspecialchars($t['procedimiento'])?></div><div class="tc-meta">📍 <?=htmlspecialchars($t['proyecto'])?> · <?=htmlspecialchars($t['etapa'])?></div></div><span class="tc-badge <?=$atrasada?'badge-late':'badge-pend'?>"><?=$atrasada?'Atrasada':'Pendiente'?></span></div>
<div class="tc-dates">
<div class="tc-date tc-date-full"><label>Inicio</label><span><?=$t['fecha_inicio']?date('d/m/Y',strtotime($t['fecha_inicio'])):'—'?></span></div>
<div class="tc-date"><label>Fin</label><span><?=$t['fecha_fin']?date('d/m/Y',strtotime($t['fecha_fin'])):'—'?></span></div>
<div class="tc-date"><label>Progreso</label><span><?=(int)$t['progreso']?>%</span></div>
</div>
<div class="tc-actions">
<button class="btn" onclick="openGanttModal(<?=htmlspecialchars(json_encode($t))?>)">📅 Gantt</button>
<button class="btn" onclick="openPresupuestoModal(<?=htmlspecialchars(json_encode($t))?>)">💰 Presupuesto</button>
<?php if ($t['cierre_estado'] === 'pendiente'): ?>
<span class="tc-badge badge-pend">⏳ Cierre solicitado — pendiente de validación</span>
<?php else: ?>
<button class="btn" onclick="openCierreModal(<?=$t['id']?>,'<?=htmlspecialchars($t['procedimiento'],ENT_QUOTES)?>')">✅ Finalizar sin cargar</button>
<button class="btn btn-p" onclick="openUploadModal(<?=$t['id']?>,'<?=htmlspecialchars($t['procedimiento'],ENT_QUOTES)?>')">📎 Subir y Completar</button>
<?php endif; ?>
<?php if ($t['cierre_estado'] === 'rechazado'): ?>
<span class="tc-badge badge-late">Cierre rechazado — puedes reintentar</span>
<?php endif; ?>
</div></div>
<?php endforeach?>
<?php if(!empty($validaciones)):?>
<div class="section-title">🛡️ Validar cierres sin entregable</div>
<?php foreach($validaciones as $v):?>
<div class="task-card" style="border-left-color:#f59e0b">
<div class="tc-header"><div>
<div class="tc-name"><?=htmlspecialchars($v['procedimiento'])?></div>
<div class="tc-meta">📍 <?=htmlspecialchars($v['proyecto'])?> · <?=htmlspecialchars($v['etapa'])?></div>
<div class="tc-meta">👤 Solicitó: <?=htmlspecialchars($v['solicitante']??'—')?> · <?=htmlspecialchars($v['cierre_fecha_solicitud']??'')?></div>
<div class="tc-meta" style="white-space:normal;line-height:1.5">📝 <?=nl2br(htmlspecialchars($v['cierre_descripcion']??''))?></div>
</div></div>
<div class="tc-actions">
<form method="POST" style="display:inline"><input type="hidden" name="action" value="validar_cierre"><input type="hidden" name="partida_id" value="<?=$v['id']?>"><input type="hidden" name="decision" value="aprobar"><button type="submit" class="btn btn-p">✔ Aprobar y cerrar</button></form>
<form method="POST" style="display:inline"><input type="hidden" name="action" value="validar_cierre"><input type="hidden" name="partida_id" value="<?=$v['id']?>"><input type="hidden" name="decision" value="rechazar"><button type="submit" class="btn btn-d">✖ Rechazar</button></form>
</div>
</div>
<?php endforeach;endif?>
<?php if(!empty($tareasCompletadas)):?><div class="section-title">✅ Completadas</div>
<?php foreach($tareasCompletadas as $t):?><div class="task-card done"><div class="tc-header"><div><div class="tc-name"><?=htmlspecialchars($t['procedimiento'])?></div><div class="tc-meta">📍 <?=htmlspecialchars($t['proyecto'])?> · Terminado: <?=$t['fecha_terminacion_real']?date('d/m/Y',strtotime($t['fecha_terminacion_real'])):'Sí'?></div></div><span class="tc-badge badge-ok">OK</span></div></div>
<?php endforeach;endif?></div>
<div class="panel panel-chat" id="chatPanel">
<div class="chat-header">
<h2 style="padding:.5rem 1rem;margin:0">🤖 Asistente SICA</h2>
<button class="chat-close" onclick="closeAssistant()" aria-label="Cerrar">✕</button>
</div>
<div class="chat-messages" id="chatMessages"><div class="chat-msg ai">¡Hola! Soy el asistente IA de SICA. Puedo ayudarte con consultas legales, técnicas y financieras sobre desarrollo inmobiliario. ¿En qué te ayudo?</div></div>
<div class="chat-typing" id="chatTyping">🤖 Pensando...</div>
<div class="chat-input-wrap"><textarea id="chatInput" rows="2" placeholder="Escribe tu consulta..." onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMessage()}"></textarea><button class="btn btn-p" onclick="sendMessage()" style="align-self:flex-end">Enviar</button></div>
</div></div></div>

<!-- MODAL GANTT -->
<div class="modal" id="ganttModal" onclick="if(event.target===this)this.classList.remove('show')"><div class="modal-box" onclick="event.stopPropagation()">
<h3>📅 Ajustar Fechas</h3><form method="POST"><input type="hidden" name="action" value="edit_fechas"><input type="hidden" name="partida_id" id="ganttPid">
<div class="info-box" id="ganttInfo"></div>
<label>Fecha Inicio</label><input type="date" name="fecha_inicio" id="ganttFI">
<label>Fecha Fin</label><input type="date" name="fecha_fin" id="ganttFF">
<div class="btn-row"><button type="button" class="btn" onclick="document.getElementById('ganttModal').classList.remove('show')">Cancelar</button><button type="submit" class="btn btn-p">💾 Guardar</button></div></form></div></div>

<!-- MODAL PRESUPUESTO -->
<div class="modal" id="presupuestoModal" onclick="if(event.target===this)this.classList.remove('show')"><div class="modal-box" onclick="event.stopPropagation()">
<h3>💰 Presupuesto de Tercero</h3><form method="POST"><input type="hidden" name="action" value="edit_presupuesto"><input type="hidden" name="partida_id" id="presPid">
<div class="info-box" id="presInfo"></div>
<label>Monto del tercero ($)</label><input type="number" step="0.01" name="presupuesto_tercero" id="presMonto" placeholder="0.00">
<label>Nota / Concepto</label><textarea name="nota_presupuesto" id="presNota" placeholder="Ej: Cotización de Ingeniería Civil..."></textarea>
<p style="font-size:.75rem;color:#64748b;margin-top:.25rem">Se suma al costo real del presupuesto general.</p>
<div class="btn-row"><button type="button" class="btn" onclick="document.getElementById('presupuestoModal').classList.remove('show')">Cancelar</button><button type="submit" class="btn btn-p">💾 Registrar</button></div></form></div></div>

<!-- MODAL SUBIR + IA -->
<div class="modal" id="uploadModal" onclick="if(event.target===this)this.classList.remove('show')"><div class="modal-box" onclick="event.stopPropagation()">
<h3>📎 Subir y Analizar</h3><form method="POST" enctype="multipart/form-data" onsubmit="return confirm('¿Confirmas esta acción?')"><input type="hidden" name="partida_id" id="uploadPid"><input type="hidden" name="completar" id="uploadCompletar" value="0">
<div class="info-box" id="uploadInfo"></div>
<label>Documento</label><input type="file" name="documento" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" required id="uploadFile">
<div id="analysisResult" style="display:none"></div>
<div class="warn-box" id="analysisPending" style="display:none">⏳ Analizando con IA...</div>
<div class="btn-row"><button type="button" class="btn" onclick="document.getElementById('uploadModal').classList.remove('show')">Cancelar</button><button type="button" class="btn" onclick="analyzeDocument()">🤖 Analizar con IA</button><button type="submit" class="btn btn-p" id="btnCompletar" disabled>📎 Subir</button></div></form></div></div>

<!-- MODAL FINALIZAR SIN CARGAR -->
<div class="modal" id="cierreModal" onclick="if(event.target===this)this.classList.remove('show')"><div class="modal-box" onclick="event.stopPropagation()">
<h3>✅ Finalizar sin cargar</h3>
<form method="POST" onsubmit="return confirm('¿Enviar solicitud de cierre? El líder del proyecto deberá validarla para que la tarea quede cerrada.')">
<input type="hidden" name="action" value="solicitar_cierre"><input type="hidden" name="partida_id" id="cierrePid">
<div class="info-box" id="cierreInfo"></div>
<label>Descripción del motivo de cierre *</label>
<textarea name="cierre_descripcion" id="cierreDesc" required placeholder="Ej: Trámite concluido sin documento entregable, se obtuvo visto bueno..."></textarea>
<div class="btn-row"><button type="button" class="btn" onclick="document.getElementById('cierreModal').classList.remove('show')">Cancelar</button><button type="submit" class="btn btn-p">Enviar solicitud</button></div>
</form>
</div></div>

<script>
function toggleDark(){var b=document.body;var c=document.getElementById("darkSwitch");b.classList.toggle("dark",c.checked);localStorage.setItem("sica-dark",c.checked?"1":"0")}
(function(){if(localStorage.getItem("sica-dark")==="1"){document.body.classList.add("dark");var c=document.getElementById("darkSwitch");if(c)c.checked=true}})();

function openAssistant(){var p=document.getElementById('chatPanel');if(p)p.classList.add('open')}
function closeAssistant(){var p=document.getElementById('chatPanel');if(p)p.classList.remove('open')}

function openGanttModal(t){document.getElementById('ganttPid').value=t.id;document.getElementById('ganttFI').value=t.fecha_inicio||'';document.getElementById('ganttFF').value=t.fecha_fin||'';document.getElementById('ganttInfo').innerHTML='<strong>'+esc(t.procedimiento)+'</strong><br>📍 '+esc(t.proyecto)+'<br>📅 Actual: '+(t.fecha_inicio||'?')+' → '+(t.fecha_fin||'?');document.getElementById('ganttModal').classList.add('show')}
function openPresupuestoModal(t){document.getElementById('presPid').value=t.id;document.getElementById('presMonto').value=t.presupuesto_tercero||0;document.getElementById('presNota').value=t.nota_presupuesto||'';document.getElementById('presInfo').innerHTML='<strong>'+esc(t.procedimiento)+'</strong><br>📍 '+esc(t.proyecto)+'<br>💰 Estimado: $'+(t.costo_estimado||0)+' | Tercero: $'+(t.presupuesto_tercero||0);document.getElementById('presupuestoModal').classList.add('show')}
function openUploadModal(id,nombre){document.getElementById('uploadPid').value=id;document.getElementById('uploadInfo').innerHTML='<strong>'+esc(nombre)+'</strong>';document.getElementById('uploadCompletar').value='0';document.getElementById('btnCompletar').disabled=true;document.getElementById('btnCompletar').textContent='📎 Subir';document.getElementById('analysisResult').style.display='none';document.getElementById('analysisPending').style.display='none';document.getElementById('uploadFile').value='';document.getElementById('uploadModal').classList.add('show')}
function openCierreModal(id,nombre){document.getElementById('cierrePid').value=id;document.getElementById('cierreInfo').innerHTML='<strong>'+esc(nombre)+'</strong>';document.getElementById('cierreDesc').value='';document.getElementById('cierreModal').classList.add('show')}

function analyzeDocument(){
var file=document.getElementById('uploadFile').files[0];if(!file){alert('Selecciona un archivo');return}
document.getElementById('analysisPending').style.display='block';
var fd=new FormData();fd.append('file',file);fd.append('query','Analiza este documento para desarrollo inmobiliario en México. ¿Está alineado con el proyecto? ¿Tiene áreas de mejora? Si es aceptable di APROBADO.');
fetch('api/analizar-doc.php',{method:'POST',body:fd}).then(r=>r.json()).then(function(data){
document.getElementById('analysisPending').style.display='none';var res=document.getElementById('analysisResult');res.style.display='block';
if(data.success){var ok=data.reply.toUpperCase().indexOf('APROBADO')!==-1;res.innerHTML='<div class="'+(ok?'info-box':'warn-box')+'"><strong>🤖 Análisis:</strong><br>'+data.reply.replace(/\n/g,'<br>')+'</div>';
document.getElementById('btnCompletar').disabled=false;document.getElementById('uploadCompletar').value=ok?'1':'0';document.getElementById('btnCompletar').textContent=ok?'✅ Aprobado - Cerrar Tarea':'⚠️ Subir (requiere mejoras)';}
else{res.innerHTML='<div class="warn-box">❌ '+data.error+'</div>';document.getElementById('btnCompletar').disabled=false;document.getElementById('btnCompletar').textContent='📎 Subir sin análisis'}
}).catch(function(){document.getElementById('analysisPending').style.display='none'})}

var chatMessages=[];var isWaiting=false;
function sendMessage(){var input=document.getElementById('chatInput');var text=input.value.trim();if(!text||isWaiting)return;input.value='';addMessage('user',text);chatMessages.push({role:'user',content:text});isWaiting=true;document.getElementById('chatTyping').classList.add('show');
fetch('api/chat-ia.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({messages:chatMessages})}).then(r=>r.json()).then(function(data){document.getElementById('chatTyping').classList.remove('show');isWaiting=false;if(data.success){addMessage('ai',data.reply);chatMessages.push({role:'assistant',content:data.reply})}else addMessage('ai','❌ Error')}).catch(function(){document.getElementById('chatTyping').classList.remove('show');isWaiting=false;addMessage('ai','❌ Error de conexión')})}
function addMessage(role,text){var div=document.createElement('div');div.className='chat-msg '+role;div.innerHTML=text.replace(/\n/g,'<br>');document.getElementById('chatMessages').appendChild(div);document.getElementById('chatMessages').scrollTop=document.getElementById('chatMessages').scrollHeight}
function esc(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML}
</script></body></html>
