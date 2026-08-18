<?php
/**
 * SICA Admin - Diagrama de Gantt del Proyecto (Nativo, sin librerías externas)
 */
define('SICA_APP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
Auth::requireLogin();
$user = Auth::currentUser();
$db = Database::getInstance()->getPdo();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
// Verificar acceso: admin total, otros solo proyectos asignados con permiso
if($user['rol'] !== 'admin'){
    $check = $db->prepare("SELECT permiso FROM usuario_proyectos WHERE usuario_id=:uid AND proyecto_id=:pid");
    $check->execute(['uid'=>$user['id'],'pid'=>$id]);
    $perm = $check->fetchColumn();
    if(!$perm){header('Location: index.php');exit;}
} else { $perm = 'editar'; }
$canEditGantt = ($perm === 'editar' || $perm === 'editar_gantt');
$canEditPresupuesto = ($perm === 'editar' || $perm === 'editar_presupuesto');
$proyecto = $db->prepare("SELECT * FROM proyectos WHERE id=:id"); $proyecto->execute(['id'=>$id]); $proyecto = $proyecto->fetch();
if(!$proyecto){header('Location: index.php');exit;}

if($_SERVER['REQUEST_METHOD']==='POST' && $canEditGantt && isset($_POST['save_fecha'])){
    $db->prepare("UPDATE presupuesto_partidas SET fecha_terminacion_real=:f WHERE id=:i")->execute(['f'=>$_POST['fecha_real']?:null,'i'=>(int)$_POST['partida_id']]);
}
// AJAX: guardar/quitar dependencia
if($_SERVER['REQUEST_METHOD']==='POST' && $canEditGantt && isset($_POST['dep_action'])){
    $pid = (int)$_POST['partida_id'];
    $did = (string)$_POST['dep_id'];
    $st = $db->prepare("SELECT dependencias FROM presupuesto_partidas WHERE id=:i");
    $st->execute(['i'=>$pid]);
    $cur = $st->fetchColumn();
    $deps = $cur ? explode(',', $cur) : [];
    if($_POST['dep_action'] === 'add' && !in_array($did, $deps)){
        $deps[] = $did;
    } elseif($_POST['dep_action'] === 'remove'){
        $deps = array_filter($deps, function($d) use($did){ return $d !== $did; });
    }
    $newDeps = implode(',', array_values($deps)) ?: null;
    // Si se agregó dependencia, ajustar fecha_inicio y fecha_fin de la tarea hija
    if($_POST['dep_action'] === 'add'){
        $st2 = $db->prepare("SELECT fecha_fin FROM presupuesto_partidas WHERE id=:i");
        $st2->execute(['i'=>(int)$did]);
        $predEnd = $st2->fetchColumn();
        if($predEnd){
            // Calcular días hábiles de desplazamiento para conservar duración
            $st3 = $db->prepare("SELECT fecha_inicio, fecha_fin FROM presupuesto_partidas WHERE id=:i");
            $st3->execute(['i'=>$pid]);
            $cur = $st3->fetch();
            $oldStart = $cur['fecha_inicio'] ?? $predEnd;
            // Contar días hábiles entre oldStart y predEnd
            $bizShift = 0; $d = strtotime($oldStart);
            $pend = strtotime($predEnd);
            while($d < $pend){ $w = (int)date('N',$d); if($w<6) $bizShift++; $d += 86400; }
            $db->prepare("UPDATE presupuesto_partidas SET dependencias=:d, fecha_inicio=:fi, fecha_fin=date(fecha_fin,'+'||:shift||' days') WHERE id=:i")
               ->execute(['d'=>$newDeps, 'fi'=>$predEnd, 'shift'=>$bizShift, 'i'=>$pid]);
        }
    } else {
        $db->prepare("UPDATE presupuesto_partidas SET dependencias=:d WHERE id=:i")
           ->execute(['d'=>$newDeps, 'i'=>$pid]);
    }
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true, 'dependencias'=>$newDeps]);
    exit;
}

$cats = $db->prepare("SELECT * FROM presupuesto_categorias WHERE proyecto_id=:pid ORDER BY orden");
$cats->execute(['pid'=>$id]); $categorias = $cats->fetchAll();
$ganttTasks = []; $ganttGroups = []; $totales = ['count'=>0,'prog'=>0,'comp'=>0];
$globalIdx = 0;
foreach($categorias as &$cat){
    $p = $db->prepare("SELECT * FROM presupuesto_partidas WHERE categoria_id=:cid ORDER BY orden");
    $p->execute(['cid'=>$cat['id']]); $cat['partidas'] = $p->fetchAll();
    $groupStart = $globalIdx;
    foreach($cat['partidas'] as &$pt){
        $totales['count']++; $totales['prog']+=$pt['progreso']; if($pt['progreso']>=100)$totales['comp']++;
        $start = $pt['fecha_inicio'] ?: date('Y-m-d');
        $today = date('Y-m-d');
        $end = $pt['fecha_fin'] ?: date('Y-m-d', strtotime('+7 days'));
        $cls = '';
        $isDelayed = ($end < $today && $pt['progreso'] < 100);
        if($pt['progreso']>=100) $cls = 'bar-green';
        $ganttTasks[] = ['id'=>(string)$pt['id'], 'name'=>$pt['procedimiento'], 'start'=>$start, 'end'=>$end, 'progress'=>(int)$pt['progreso']/100, 'custom_class'=>$cls, 'is_delayed'=>$isDelayed, 'responsable'=>$pt['responsable']??'', 'fecha_inicio'=>$start, 'fecha_fin'=>$end, 'dependencias'=>$pt['dependencias']??'', 'etapa'=>$cat['codigo'].' '.$cat['nombre']];
        $globalIdx++;
    }
    if(count($cat['partidas']) > 0){
        $ganttGroups[] = ['name'=>$cat['codigo'].' '.$cat['nombre'], 'taskIndex'=>$groupStart, 'count'=>count($cat['partidas'])];
    }
}
$totales['prog'] = $totales['count']>0 ? round($totales['prog']/$totales['count']) : 0;
?>
<!DOCTYPE html><html lang="es-MX"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?=htmlspecialchars($proyecto['nombre'])?> | Gantt</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 36 44'%3E%3Crect x='1.5' y='1.5' width='33' height='41' rx='2' fill='none' stroke='%2350C8C6' stroke-width='2.5'/%3E%3Crect x='8' y='24' width='7' height='14' fill='%23FFFFFF'/%3E%3Crect x='21' y='12' width='7' height='26' fill='%23FFFFFF'/%3E%3C/svg%3E">
<link rel="stylesheet" href="assets/css/admin.css?v=40">
<style>
/* --- CORRECCIÓN DE SCROLL GLOBAL --- */
body, html { 
    overflow-x: hidden; 
}
.admin-main { 
    min-width: 0; 
}

/* ═══ GANTT NATIVO ═══ */
.gantt-wrap {
    margin: 1rem 0; 
    background: #fff; 
    border-radius: 12px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06); 
    border: 1px solid #e2e8f0;
    position: relative;
    max-height: 75vh;
    overflow: auto;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    touch-action: pan-x pan-y;
}

/* --- FILA DE CABECERAS (sticky top) --- */
.gantt-header-row {
    display: flex; position: sticky; top: 0; z-index: 100; background: #fff;
    width: max-content; min-width: 100%;
}
.gantt-hleft {
    width: 350px; min-width: 350px; background: #132236; color: #fff; padding: 0 15px;
    font-size: 0.75rem; font-weight: 700; border-bottom: 2px solid #50C8C6;
    display: flex; align-items: center; height: 52px; box-sizing: border-box;
    position: sticky; left: 0; z-index: 102;
}
.gantt-hright {
    flex: 1; position: relative; height: 52px; border-bottom: 2px solid #e2e8f0;
}
.gantt-months { display: flex; height: 100%; position: absolute; left: 0; top: 0; }
.gantt-month { display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700; color: #1e293b; border-right: 1px solid #e2e8f0; height: 100%; box-sizing: border-box; white-space: nowrap; background: #f8fafc; }

/* --- FILA DEL CUERPO --- */
.gantt-body-row { display: flex; width: max-content; min-width: 100%; }
.gantt-bleft {
    width: 350px; min-width: 350px; position: sticky; left: 0; z-index: 90;
    background: #fff; border-right: 2px solid #cbd5e1; box-shadow: 3px 0 6px rgba(0,0,0,0.04);
}
.gantt-bright { flex: 1; position: relative; }
.gantt-grid-area { position: relative; }

/* --- FILAS DE TAREAS --- */
.gantt-task-row{display:flex;align-items:center;font-size:0.7rem;font-weight:500;color:#1e293b;border-bottom:1px solid #f1f5f9;padding:0 10px 0 15px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;cursor:pointer;transition:background 0.15s;background:#fff;box-sizing:border-box}
.gantt-task-row.delayed{color:#ef4444;font-weight:600}
.gantt-task-row.hover-active{background:#e8f0fe!important}
.gantt-task-row:nth-child(even){background:#fafbfc}
.gantt-task-row:nth-child(even).hover-active{background:#e8f0fe!important}
/* Grupo / Etapa header */
.gantt-group-row{display:flex;align-items:center;font-size:0.72rem;font-weight:700;color:#132236;background:#e2e8f0;border-bottom:2px solid #cbd5e1;padding:0 10px 0 12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;box-sizing:border-box;position:sticky;left:0;z-index:91}

/* --- GRID Y BARRAS --- */
.gantt-grid-row{position:absolute;left:0;right:0;border-bottom:1px solid #f1f5f9;box-sizing:border-box}
.gantt-grid-row:nth-child(even){background:#fafbfc}
.gantt-bar-wrap{position:absolute;display:flex;align-items:center;cursor:pointer;z-index:10}
.gantt-bar{position:absolute;border-radius:4px;background:#132236;height:22px;top:8px}
.gantt-bar-progress{position:absolute;border-radius:4px 0 0 4px;background:#50C8C6;height:22px;top:0;left:0}
.gantt-bar.done{background:#22c55e}
.gantt-bar.done .gantt-bar-progress{background:#16a34a}
.gantt-bar-delay{position:absolute;border-radius:3px;background:#ef4444;height:22px;top:8px;z-index:12}
/* Retraso heredado: borde naranja punteado */
.gantt-bar.inherited-delay{border:2px dashed #f59e0b!important;box-sizing:border-box}
/* --- LÍNEAS DE DEPENDENCIA ESTILO PROFESIONAL --- */
.gantt-dep-svg{position:absolute;top:0;left:0;pointer-events:none;z-index:15}
.gantt-dep-line{fill:none;stroke:#64748b;stroke-width:2px;stroke-linecap:round;stroke-linejoin:round;filter:url(#dep-shadow);pointer-events:stroke;transition:stroke 0.2s,stroke-width 0.2s}
.gantt-dep-line:hover{stroke:#3b82f6;stroke-width:2.5px}
.dep-delete-btn{cursor:pointer;pointer-events:auto;transform-box:fill-box;transform-origin:center;transition:transform 0.15s ease}
.dep-delete-btn:hover{transform:scale(1.25)}
.dep-btn-bg{fill:#ffffff;stroke:#cbd5e1;stroke-width:1.5px;filter:drop-shadow(0px 2px 4px rgba(0,0,0,0.12));transition:fill 0.15s,stroke 0.15s}
.dep-delete-btn:hover .dep-btn-bg{fill:#ef4444;stroke:#dc2626}
.dep-btn-text{font-size:11px;font-weight:800;font-family:system-ui,-apple-system,sans-serif;fill:#64748b;user-select:none;transition:fill 0.15s}
.dep-delete-btn:hover .dep-btn-text{fill:#ffffff}
/* Handle de conexión para drag-to-connect */
.gantt-connector{position:absolute;top:50%;transform:translateY(-50%);width:14px;height:14px;border-radius:50%;background:#50C8C6;border:2px solid #fff;cursor:crosshair;z-index:20;opacity:0;transition:opacity 0.15s}
.gantt-connector.conn-start{left:-7px;cursor:cell}
.gantt-connector.conn-end{right:-7px;cursor:crosshair}
.gantt-bar:hover .gantt-connector,.gantt-bar.dragging .gantt-connector{opacity:1}
.gantt-connector:hover{transform:translateY(-50%) scale(1.4);box-shadow:0 0 6px rgba(80,200,198,0.6)}
/* Línea de arrastre temporal */
.gantt-drag-line{position:absolute;top:0;left:0;pointer-events:none;z-index:15}
.gantt-drag-line line{stroke:#50C8C6;stroke-width:2;stroke-dasharray:6 3}

/* --- LÍNEA HOY --- */
.gantt-today{position:absolute;top:0;bottom:0;width:3px;background:#e6a23c;z-index:30;pointer-events:none}
.gantt-today-label{position:sticky;top:4px;left:0;background:#e6a23c;color:#fff;font-size:0.6rem;font-weight:700;padding:2px 6px;border-radius:3px;white-space:nowrap;z-index:31;pointer-events:none}

/* --- TOOLTIP --- */
.gantt-tooltip{display:none;position:fixed;z-index:999;background:#132236;color:#fff;padding:0.75rem 1rem;border-radius:8px;font-size:0.78rem;max-width:280px;box-shadow:0 4px 16px rgba(0,0,0,0.25);pointer-events:none;line-height:1.5}
.gantt-tooltip.visible{display:block}
.gantt-tooltip .tt-name{font-weight:700;font-size:0.85rem;margin-bottom:0.35rem;color:#50C8C6}
.gantt-tooltip .tt-row{display:flex;justify-content:space-between;gap:1rem}
.gantt-tooltip .tt-lbl{opacity:0.7}
.gantt-tooltip .tt-val{font-weight:600}
.gantt-tooltip .tt-delay{color:#ef4444;font-weight:700}

/* --- MÉTRICAS --- */
.sb{display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1rem}
.si{background:#132236;color:#fff;padding:0.6rem 1rem;border-radius:8px}
.si .b{font-size:1.3rem;font-weight:800}.si .l{font-size:0.7rem;opacity:0.8}
</style></head><body><div class="admin-layout">
<?php $activePage="index"; include __DIR__."/includes/sidebar.php"; ?>
<main class="admin-main"><a href="index.php" style="color:#64748b;font-size:0.9rem">← Dashboard</a>
<div class="admin-header"><h2>📅 <?=htmlspecialchars($proyecto['nombre'])?> — Diagrama de Gantt</h2></div>

<div class="gantt-wrap" id="ganttWrap">
    <div class="gantt-header-row" id="ganttHeaderRow">
        <div class="gantt-hleft">Tareas</div>
        <div class="gantt-hright" id="ganttHRight"><div class="gantt-months" id="ganttMonths"></div></div>
    </div>
    <div class="gantt-body-row" id="ganttBodyRow">
        <div class="gantt-bleft" id="ganttBLeft"></div>
        <div class="gantt-bright" id="ganttBRight">
            <div class="gantt-grid-area" id="ganttGrid"></div>
        </div>
    </div>
    <div id="gantt-tooltip" class="gantt-tooltip"></div>
</div>
</main></div>

<script>
var tasks = <?=json_encode($ganttTasks, JSON_UNESCAPED_UNICODE)?>;
var groups = <?=json_encode($ganttGroups, JSON_UNESCAPED_UNICODE)?>;
var canEditGantt = <?=json_encode($canEditGantt)?>;
if(tasks.length > 0){

    // ── 1. FECHAS BASE (timezone-safe, hora local) ──
    function parseLocal(d){ var p=d.split('-'); return new Date(p[0],p[1]-1,p[2]); }
    function isWeekend(d){ var w=d.getDay(); return w===0||w===6; }
    function addBizDays(d, n){
        var cur = new Date(d.getTime()), added = 0;
        while(added < n){ cur.setDate(cur.getDate()+1); if(!isWeekend(cur)) added++; }
        return cur;
    }
    function bizDaysBetween(d1, d2){
        var count = 0, cur = new Date(d1.getTime());
        while(cur < d2){ if(!isWeekend(cur)) count++; cur.setDate(cur.getDate()+1); }
        return count;
    }
    var minStart = new Date(Math.min(...tasks.map(function(t){ return parseLocal(t.start); })));
    var maxEnd   = new Date(Math.max(...tasks.map(function(t){ return parseLocal(t.end); })));
    var today    = new Date(); today.setHours(0,0,0,0);

    // Redondear minStart al día 1 de su mes para cabeceras limpias
    var chartStart = new Date(minStart.getFullYear(), minStart.getMonth(), 1);

    var MS_DAY = 86400000;
    var totalDays = Math.ceil((maxEnd - chartStart) / MS_DAY) + 1;

    var ROW_H = 38;       // altura de fila
    var DAY_W = 5;        // px por día (se recalcula en build)
    var zoomLevel = 1.0;  // zoom del trackpad

    // ── 2. REFERENCIAS DOM ──
    var wrap     = document.getElementById('ganttWrap');
    var hRight   = document.getElementById('ganttHRight');
    var monthsEl = document.getElementById('ganttMonths');
    var bLeft    = document.getElementById('ganttBLeft');
    var bRight   = document.getElementById('ganttBRight');
    var gridEl   = document.getElementById('ganttGrid');
    var tooltip  = document.getElementById('gantt-tooltip');

    var MONTHS = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

    // ── 3. CONSTRUIR GANTT ──
    function build(){
        // ── Layout plano con encabezados de etapa ──
        var flatRows = []; window._taskToRow = {};
        groups.forEach(function(g){
            flatRows.push({type:'group', name:g.name});
            for(var gi = g.taskIndex; gi < g.taskIndex + g.count; gi++){
                flatRows.push({type:'task', task:tasks[gi]});
                window._taskToRow[tasks[gi].id] = flatRows.length - 1;
            }
        });
        var totalRows = flatRows.length;

        // Calcular ancho disponible y px/día
        var availW = wrap.clientWidth - 370;
        DAY_W = Math.max(availW / totalDays, 6) * zoomLevel; // mínimo 6px/día para meses más anchos

        var fullW = totalDays * DAY_W;

        // --- Cabecera de meses ---
        monthsEl.innerHTML = '';
        monthsEl.style.width = fullW + 'px';
        hRight.style.minWidth = fullW + 'px';
        var cur = new Date(chartStart);
        while(cur <= maxEnd){
            var mKey = cur.getFullYear() + '-' + cur.getMonth();
            var daysInMonth = new Date(cur.getFullYear(), cur.getMonth()+1, 0).getDate();
            var mWidth = daysInMonth * DAY_W;
            var mDiv = document.createElement('div');
            mDiv.className = 'gantt-month';
            mDiv.style.width = mWidth + 'px';
            mDiv.style.minWidth = mWidth + 'px';
            mDiv.textContent = MONTHS[cur.getMonth()] + ' ' + cur.getFullYear();
            monthsEl.appendChild(mDiv);
            cur.setMonth(cur.getMonth() + 1);
        }

        // --- Panel izquierdo (etapas + tareas) ---
        bLeft.innerHTML = '';
        flatRows.forEach(function(r,i){
            if(r.type === 'group'){
                var gr = document.createElement('div');
                gr.className = 'gantt-group-row';
                gr.style.height = ROW_H + 'px';
                gr.textContent = r.name;
                bLeft.appendChild(gr);
            } else {
                let t = r.task;
                var tr = document.createElement('div');
                tr.className = 'gantt-task-row' + (t.is_delayed ? ' delayed' : '');
                tr.setAttribute('data-task-id', t.id);
                tr.style.height = ROW_H + 'px';
                tr.textContent = t.name;
                tr.addEventListener('click', function(){ showTooltip(t); });
                tr.addEventListener('mouseenter', function(){ hoverOn(t.id); });
                tr.addEventListener('mouseleave', function(){ hoverOff(t.id); });
                bLeft.appendChild(tr);
            }
        });

        // --- Área de grid + barras ---
        gridEl.innerHTML = '';
        gridEl.style.width = fullW + 'px';
        bRight.style.minWidth = fullW + 'px';
        gridEl.style.height = (totalRows * ROW_H) + 'px';

        // Cascada de fechas: si una tarea está atrasada, sus dependientes se recorren
        window._cascadeStart = {};
        window._cascadeEnd = {};
        tasks.forEach(function(t){
            window._cascadeStart[t.id] = parseLocal(t.start);
            window._cascadeEnd[t.id] = parseLocal(t.end);
        });
        var changed = true;
        while(changed){
            changed = false;
            tasks.forEach(function(t){
                var deps = (t.dependencias||'').split(',').filter(Boolean);
                deps.forEach(function(did){
                    var pred = tasks.find(function(dt){ return dt.id == did; });
                    if(!pred) return;
                    // effectiveEnd del padre: cascadedEnd (ya incluye sus propios atrasos)
                    var effEnd = pred.is_delayed ? new Date(Math.max(today.getTime(), window._cascadeEnd[pred.id].getTime())) : window._cascadeEnd[pred.id];
                    if(effEnd > window._cascadeStart[t.id]){
                        var bizShift = bizDaysBetween(window._cascadeStart[t.id], effEnd);
                        window._cascadeStart[t.id] = new Date(effEnd);
                        window._cascadeEnd[t.id] = addBizDays(window._cascadeEnd[t.id], bizShift);
                        changed = true;
                    }
                });
            });
        }

        // Extender rango si la cascada empujó fechas más allá
        var cascadedMax = maxEnd;
        tasks.forEach(function(t){
            if(window._cascadeEnd[t.id] > cascadedMax) cascadedMax = new Date(window._cascadeEnd[t.id]);
        });
        // Redondear al último día del mes para cubrir el mes completo
        var endOfMonth = new Date(cascadedMax.getFullYear(), cascadedMax.getMonth()+1, 0);
        var extDays = Math.ceil((endOfMonth - chartStart) / MS_DAY) + 1;
        if(extDays > totalDays) totalDays = extDays;

        // Recalcular ancho con el rango extendido y reconstruir meses
        DAY_W = Math.max((wrap.clientWidth - 370) / totalDays, 6) * zoomLevel;
        var fullW = totalDays * DAY_W;
        monthsEl.innerHTML = '';
        monthsEl.style.width = fullW + 'px';
        hRight.style.minWidth = fullW + 'px';
        gridEl.style.width = fullW + 'px';
        bRight.style.minWidth = fullW + 'px';
        var cur = new Date(chartStart);
        var endLimit = cascadedMax > maxEnd ? cascadedMax : maxEnd;
        while(cur <= endLimit){
            var daysInMonth = new Date(cur.getFullYear(), cur.getMonth()+1, 0).getDate();
            var mWidth = daysInMonth * DAY_W;
            var mDiv = document.createElement('div');
            mDiv.className = 'gantt-month';
            mDiv.style.width = mWidth + 'px';
            mDiv.style.minWidth = mWidth + 'px';
            mDiv.textContent = MONTHS[cur.getMonth()] + ' ' + cur.getFullYear();
            monthsEl.appendChild(mDiv);
            cur.setMonth(cur.getMonth() + 1);
        }

        // Filas de grupo en el grid
        flatRows.forEach(function(r,i){
            if(r.type === 'group'){
                var gr = document.createElement('div');
                gr.className = 'gantt-grid-row';
                gr.style.top = (i * ROW_H) + 'px';
                gr.style.height = ROW_H + 'px';
                gr.style.background = '#e2e8f0';
                gr.style.borderBottom = '2px solid #cbd5e1';
                gridEl.appendChild(gr);
            } else {
                let t = r.task;
                let top = i * ROW_H;
                // Fila del grid
                var gg = document.createElement('div');
                gg.className = 'gantt-grid-row';
                gg.style.top = top + 'px';
                gg.style.height = ROW_H + 'px';
                gg.style.background = i % 2 === 0 ? '#fff' : '#fafbfc';
                gridEl.appendChild(gg);

                // Barra wrapper (para hover sync)
                var bw = document.createElement('div');
                bw.className = 'gantt-bar-wrap';
                bw.setAttribute('data-task-id', t.id);
                bw.style.top = top + 'px';
                bw.style.height = ROW_H + 'px';
                bw.addEventListener('mouseenter', function(){ hoverOn(t.id); });
                bw.addEventListener('mouseleave', function(){ hoverOff(t.id); });
                bw.addEventListener('click', function(){ showTooltip(t); });
                gridEl.appendChild(bw);

                // Posición y ancho de la barra
                var tStart = window._cascadeStart[t.id];
                var tEnd = window._cascadeEnd[t.id];
                let left = ((tStart - chartStart) / MS_DAY) * DAY_W;
                let width = Math.max(((tEnd - tStart) / MS_DAY) * DAY_W, 4);

                // Barra principal
                var bar = document.createElement('div');
                bar.className = 'gantt-bar' + (t.custom_class === 'bar-green' ? ' done' : '');
                bar.style.left = left + 'px';
                bar.style.width = width + 'px';
                bw.appendChild(bar);

                // Progreso
                if(t.progress > 0){
                    var prog = document.createElement('div');
                    prog.className = 'gantt-bar-progress';
                    prog.style.width = (t.progress * 100) + '%';
                    bar.appendChild(prog);
                }

                // Handles de conexión (solo si puede editar Gantt)
                if(canEditGantt){
                var connStart = document.createElement('div');
                connStart.className = 'gantt-connector conn-start';
                connStart.title = 'Conectar desde otra tarea';
                connStart.addEventListener('mouseup', function(e){ endDragDep(e, t.id); });
                bar.appendChild(connStart);
                var connEnd = document.createElement('div');
                connEnd.className = 'gantt-connector conn-end';
                connEnd.title = 'Arrastrar para conectar';
                connEnd.addEventListener('mousedown', function(e){ startDragDep(e, t.id, left + width, top + ROW_H/2); });
                bar.appendChild(connEnd);
                }

                // Retraso inteligente
                if(t.is_delayed){
                    var deps = (t.dependencias||'').split(',').filter(Boolean);
                    var isRoot = true;
                    if(deps.length > 0){
                        isRoot = !deps.some(function(did){
                            var dep = tasks.find(function(dt){ return dt.id == did; });
                            return dep && dep.is_delayed;
                        });
                    }
                    if(isRoot){
                        var delayLeft = left + width;
                        var delayW = ((today - tEnd) / MS_DAY) * DAY_W;
                        if(delayW > 0){
                            var del = document.createElement('div');
                            del.className = 'gantt-bar-delay';
                            del.style.left = delayLeft + 'px';
                            del.style.top = (top + 8) + 'px';
                            del.style.width = Math.max(delayW, 4) + 'px';
                            gridEl.appendChild(del);
                        }
                    } else {
                        bar.classList.add('inherited-delay');
                    }
                }
            }
        });

        // --- Líneas de dependencia ---
        drawDependencies();

        // --- Línea HOY ---
        var todayLeft = ((today - chartStart) / MS_DAY) * DAY_W;
        if(todayLeft >= 0 && todayLeft <= fullW){
            var tl = document.createElement('div');
            tl.className = 'gantt-today';
            tl.id = 'ganttToday';
            tl.style.left = todayLeft + 'px';
            gridEl.appendChild(tl);

            var tlb = document.createElement('div');
            tlb.className = 'gantt-today-label';
            tlb.textContent = 'HOY';
            tlb.style.position = 'absolute';
            tlb.style.left = (todayLeft + 5) + 'px';
            tlb.style.top = '4px';
            gridEl.appendChild(tlb);
        }

    }

    // ── 4. LÍNEAS DE DEPENDENCIA ──
    function drawDependencies(){
        var count = 0;
        var svgNS = 'http://www.w3.org/2000/svg';
        var svg = document.createElementNS(svgNS, 'svg');
        svg.setAttribute('class', 'gantt-dep-svg');
        svg.setAttribute('width', gridEl.style.width);
        svg.setAttribute('height', gridEl.style.height);
        
        var defs = document.createElementNS(svgNS, 'defs');
        // Sombra suave
        var filter = document.createElementNS(svgNS, 'filter');
        filter.setAttribute('id', 'dep-shadow');
        filter.setAttribute('x', '-10%'); filter.setAttribute('y', '-10%');
        filter.setAttribute('width', '120%'); filter.setAttribute('height', '120%');
        filter.innerHTML = '<feDropShadow dx="0" dy="1" stdDeviation="1.5" flood-color="#0f172a" flood-opacity="0.15"/>';
        defs.appendChild(filter);
        // Flecha estilizada
        var marker = document.createElementNS(svgNS, 'marker');
        marker.setAttribute('id', 'arrowhead');
        marker.setAttribute('markerWidth', '7'); marker.setAttribute('markerHeight', '7');
        marker.setAttribute('refX', '6'); marker.setAttribute('refY', '3.5');
        marker.setAttribute('orient', 'auto');
        var arrP = document.createElementNS(svgNS, 'path');
        arrP.setAttribute('d', 'M 0 0 L 7 3.5 L 0 7 Z');
        arrP.setAttribute('fill', '#64748b');
        marker.appendChild(arrP);
        defs.appendChild(marker);
        svg.appendChild(defs);
        
        tasks.forEach(function(t){
            var deps = (t.dependencias||'').split(',').filter(Boolean);
            if(!deps.length) return;
            var tStart = window._cascadeStart[t.id];
            var targetLeft = ((tStart - chartStart) / MS_DAY) * DAY_W;
            var targetY = window._taskToRow[t.id] * ROW_H + (ROW_H/2);
            
            deps.forEach(function(did){
                var pred = tasks.find(function(dt){ return dt.id == did; });
                if(!pred) return;
                var origDur = parseLocal(pred.end) - parseLocal(pred.start);
                var visEnd = new Date(window._cascadeStart[pred.id].getTime() + origDur);
                var predLeft = ((visEnd - chartStart) / MS_DAY) * DAY_W;
                var predY = window._taskToRow[pred.id] * ROW_H + (ROW_H/2);
                
                var deltaX = targetLeft - predLeft;
                var cOffset = Math.max(18, Math.abs(deltaX) * 0.4);
                var cp1X = predLeft + cOffset, cp1Y = predY;
                var cp2X = targetLeft - cOffset, cp2Y = targetY;
                if(deltaX < 0){ cp1X = predLeft + 30; cp2X = targetLeft - 30; }
                
                var path = document.createElementNS(svgNS, 'path');
                path.setAttribute('d', 'M '+predLeft+','+predY+' C '+cp1X+','+cp1Y+' '+cp2X+','+cp2Y+' '+targetLeft+','+targetY);
                path.setAttribute('class', 'gantt-dep-line');
                path.setAttribute('marker-end', 'url(#arrowhead)');
                svg.appendChild(path);
                
                if(canEditGantt){
                var midX = (predLeft + targetLeft) / 2;
                var midY = (predY + targetY) / 2;
                var g = document.createElementNS(svgNS, 'g');
                g.setAttribute('class', 'dep-delete-btn');
                g.setAttribute('data-from', did);
                g.setAttribute('data-to', t.id);
                var bg = document.createElementNS(svgNS, 'circle');
                bg.setAttribute('cx', midX); bg.setAttribute('cy', midY);
                bg.setAttribute('r', '7'); bg.setAttribute('class', 'dep-btn-bg');
                g.appendChild(bg);
                var txt = document.createElementNS(svgNS, 'text');
                txt.setAttribute('x', midX); txt.setAttribute('y', midY + 3);
                txt.setAttribute('text-anchor', 'middle'); txt.setAttribute('class', 'dep-btn-text');
                txt.textContent = '×';
                g.appendChild(txt);
                g.addEventListener('click', function(e){
                    e.stopPropagation();
                    if(confirm('¿Eliminar esta dependencia?')){
                        saveDependency(this.getAttribute('data-from'), this.getAttribute('data-to'), 'remove');
                    }
                });
                svg.appendChild(g);
                count++;
                } // canEditGantt
            });
        });
        
        var oldSvg = gridEl.querySelector('.gantt-dep-svg');
        if(oldSvg) oldSvg.remove();
        gridEl.appendChild(svg);
    }

    // ── 4.5 DRAG-TO-CONNECT ──
    var dragInfo = null;
    function startDragDep(e, fromId, fromX, fromY){
        e.preventDefault(); e.stopPropagation();
        dragInfo = {fromId: fromId, fromX: fromX, fromY: fromY};
        // Crear línea temporal SVG
        var ns = 'http://www.w3.org/2000/svg';
        var svg = document.createElementNS(ns, 'svg');
        svg.setAttribute('class', 'gantt-drag-line');
        svg.setAttribute('width', gridEl.style.width);
        svg.setAttribute('height', gridEl.style.height);
        var line = document.createElementNS(ns, 'line');
        line.setAttribute('x1', fromX); line.setAttribute('y1', fromY);
        line.setAttribute('x2', fromX); line.setAttribute('y2', fromY);
        svg.appendChild(line);
        gridEl.appendChild(svg);
        document.addEventListener('mousemove', onDragMove);
        document.addEventListener('mouseup', onDragUp);
    }
    function onDragMove(e){
        if(!dragInfo) return;
        var svg = gridEl.querySelector('.gantt-drag-line');
        if(!svg) return;
        var rect = gridEl.getBoundingClientRect();
        var x = e.clientX - rect.left;
        var y = e.clientY - rect.top;
        var line = svg.querySelector('line');
        line.setAttribute('x2', x); line.setAttribute('y2', y);
    }
    function onDragUp(e){
        document.removeEventListener('mousemove', onDragMove);
        document.removeEventListener('mouseup', onDragUp);
        var svg = gridEl.querySelector('.gantt-drag-line');
        if(svg) svg.remove();
        if(!dragInfo) return;
        // Detectar sobre qué barra o punto izquierdo se soltó
        var target = e.target.closest('.bar-wrapper,.conn-start,.gantt-bar');
        if(!target){ dragInfo = null; return; }
        var toId = target.closest('.bar-wrapper') ? target.closest('.bar-wrapper').getAttribute('data-task-id') : target.parentElement.querySelector('.conn-start') ? target.closest('.gantt-bar').querySelector('.conn-start') : null;
        // Si fue sobre conn-start, obtener ID de la barra padre
        if(target.classList.contains('conn-start') || target.closest('.conn-start')){
            toId = target.closest('.gantt-bar').parentElement.getAttribute('data-task-id');
        } else if(target.classList.contains('gantt-bar')){
            toId = target.parentElement.getAttribute('data-task-id');
        }
        if(!toId || toId === dragInfo.fromId){ dragInfo = null; return; }
        saveDependency(dragInfo.fromId, toId, 'add');
        dragInfo = null;
    }
    // Llamado cuando se suelta sobre conn-start (recibir conexión)
    function endDragDep(e, toId){
        if(!dragInfo || dragInfo.fromId === toId) return;
        saveDependency(dragInfo.fromId, toId, 'add');
    }
    function saveDependency(fromId, toId, action){
        var fd = new FormData();
        fd.append('dep_action', action);
        fd.append('partida_id', toId);
        fd.append('dep_id', fromId);
        fetch(window.location.href, {method:'POST', body:fd})
        .then(function(r){ return r.json(); })
        .then(function(data){
            if(data.ok){
                console.log('Dep guardada:', fromId, '→', toId, 'deps:', data.dependencias);
                var t = tasks.find(function(x){ return x.id == toId; });
                if(t){
                    t.dependencias = data.dependencias || '';
                    if(action === 'add'){
                        var pred = tasks.find(function(x){ return x.id == fromId; });
                        if(pred){
                            var oldStart = parseLocal(t.start);
                            var newStart = parseLocal(pred.end);
                            // Días hábiles de diferencia
                            var bizShift = bizDaysBetween(oldStart, newStart);
                            t.start = pred.end;
                            if(bizShift > 0){
                                var newEnd = addBizDays(parseLocal(t.end), bizShift);
                                var pad = function(n){ return n<10?'0'+n:''+n; };
                                t.end = newEnd.getFullYear()+'-'+pad(newEnd.getMonth()+1)+'-'+pad(newEnd.getDate());
                            }
                        }
                    }
                }
                build();
            }
        }).catch(function(err){ console.error('Error guardando dependencia:', err); });
    }

    // ── 5. HOVER SYNC ──
    function hoverOn(taskId){
        document.querySelectorAll('.gantt-task-row[data-task-id="'+taskId+'"]').forEach(function(r){ r.classList.add('hover-active'); });
        document.querySelectorAll('.gantt-bar-wrap[data-task-id="'+taskId+'"] .gantt-bar').forEach(function(b){ b.style.filter='brightness(1.3)'; });
    }
    function hoverOff(taskId){
        document.querySelectorAll('.gantt-task-row[data-task-id="'+taskId+'"]').forEach(function(r){ r.classList.remove('hover-active'); });
        document.querySelectorAll('.gantt-bar-wrap[data-task-id="'+taskId+'"] .gantt-bar').forEach(function(b){ b.style.filter=''; });
    }

    // ── 6. TOOLTIP ──
    function showTooltip(task){
        if(!tooltip) return;
        var delayDays = task.is_delayed ? Math.floor((today - parseLocal(task.end)) / MS_DAY) : 0;
        var h = '<div class="tt-name">'+esc(task.name)+'</div>';
        h += '<div class="tt-row"><span class="tt-lbl">Encargado:</span><span class="tt-val">'+esc(task.responsable||'—')+'</span></div>';
        h += '<div class="tt-row"><span class="tt-lbl">Inicio:</span><span class="tt-val">'+(task.fecha_inicio||task.start)+'</span></div>';
        h += '<div class="tt-row"><span class="tt-lbl">Fin prog.:</span><span class="tt-val">'+(task.fecha_fin||task.end)+'</span></div>';
        h += '<div class="tt-row"><span class="tt-lbl">Avance:</span><span class="tt-val">'+Math.round(task.progress*100)+'%</span></div>';
        if(delayDays>0){ h += '<div class="tt-row"><span class="tt-lbl">Retraso:</span><span class="tt-delay">'+delayDays+' d&iacute;'+(delayDays!=1?'as':'a')+'</span></div>'; }
        tooltip.innerHTML = h;
        tooltip.classList.add('visible');
        // Posicionar tooltip cerca del mouse
        document.addEventListener('mousemove', moveTT);
    }
    function moveTT(e){
        tooltip.style.left = Math.min(e.clientX + 15, window.innerWidth - 300) + 'px';
        tooltip.style.top = Math.max(e.clientY - 40, 60) + 'px';
    }
    function hideTooltip(){
        tooltip.classList.remove('visible');
        document.removeEventListener('mousemove', moveTT);
    }
    function esc(s){ var d=document.createElement('div'); d.textContent=s; return d.innerHTML; }

    document.addEventListener('click', function(e){
        if(!e.target.closest('.gantt-bar-wrap') && !e.target.closest('.gantt-task-row') && !e.target.closest('#gantt-tooltip')){
            hideTooltip();
        }
    });

    // ── 7. INICIALIZAR + RESIZE ──
    build();
    var resizeTimer;
    window.addEventListener('resize', function(){
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(build, 200);
    });

    // Scroll inicial al primer mes con datos
    setTimeout(function(){
        var firstLeft = ((minStart - chartStart) / MS_DAY) * DAY_W;
        if(firstLeft > 30) wrap.scrollLeft = firstLeft - 20;
    }, 100);

    // ── 8. ZOOM CON TRACKPAD (PINCH-TO-ZOOM) ──
    var isZooming = false;
    wrap.addEventListener('wheel', function(e) {
        if (e.ctrlKey) {
            e.preventDefault();
            zoomLevel += e.deltaY * -0.01;
            zoomLevel = Math.max(0.3, Math.min(zoomLevel, 4.0));
            if (!isZooming) {
                isZooming = true;
                requestAnimationFrame(function() {
                    build();
                    isZooming = false;
                });
            }
        }
    }, { passive: false });

}
</script>
<script>function toggleDark(){var b=document.body;var c=document.getElementById("darkSwitch");b.classList.toggle("dark",c.checked);localStorage.setItem("sica-dark",c.checked?"1":"0")}(function(){if(localStorage.getItem("sica-dark")==="1"){document.body.classList.add("dark");var c=document.getElementById("darkSwitch");if(c)c.checked=true}})();</script></body></html>
