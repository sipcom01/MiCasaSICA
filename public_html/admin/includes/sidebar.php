<?php
if(!isset($activePage)) $activePage = '';
$user = Auth::currentUser();
?>
<aside class="admin-sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <svg viewBox="0 0 36 44" xmlns="http://www.w3.org/2000/svg" width="30" height="37">
                <rect x="1.5" y="1.5" width="33" height="41" rx="2" fill="none" stroke="#50C8C6" stroke-width="2.5"/>
                <rect x="8" y="24" width="7" height="14" fill="#FFFFFF"/>
                <rect x="21" y="12" width="7" height="26" fill="#FFFFFF"/>
            </svg>
        </div><div><div class="brand-name">SICA</div><div class="brand-sub">Panel Admin</div></div>
    </div>
    <nav class="sidebar-nav">
        <a href="index.php" class="nav-item<?=$activePage==='index'?' active':''?>">📊 Proyectos</a>
        <?php if(isset($extraNav)) echo $extraNav."\n"; ?>
        <a href="mis-tareas.php" class="nav-item<?=$activePage==='tareas'?' active':''?>">✅ Mis Tareas</a>
        <a href="usuarios.php" class="nav-item<?=$activePage==='usuarios'?' active':''?>">👥 Usuarios</a>
        <a href="logout.php" class="nav-item">🚪 Salir</a>
    </nav>
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar"><?= strtoupper(mb_substr($user['nombre'],0,1)) ?></div>
            <div><div class="user-name"><?= htmlspecialchars($user['nombre']) ?></div><a href="logout.php" class="user-logout">Cerrar sesión</a></div>
        </div>
        <div class="dark-switch-wrap">
            <label class="dark-switch"><input type="checkbox" id="darkSwitch" onchange="toggleDark()"><span class="slider"></span></label>
            <span class="dark-switch-label">Modo Noche</span>
        </div>
    </div>
</aside>
<style>
.dark-switch-wrap{display:flex;align-items:center;gap:.5rem;padding:.5rem 1.5rem}
.dark-switch{position:relative;width:40px;height:22px;flex-shrink:0}
.dark-switch input{display:none}
.dark-switch .slider{position:absolute;top:0;left:0;right:0;bottom:0;background:#475569;border-radius:22px;cursor:pointer;transition:.3s}
.dark-switch .slider:before{content:"";position:absolute;height:16px;width:16px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s}
.dark-switch input:checked+.slider{background:#50C8C6}
.dark-switch input:checked+.slider:before{transform:translateX(18px)}
.dark-switch-label{font-size:.7rem;color:#94a3b8}
body.dark,.dark body{background:#0f172a!important}body.dark .admin-layout,body.dark .admin-main,body.dark main{background:#0f172a!important}body.dark{--bg:#0f172a;--white:#1e293b;--text:#e2e8f0;--text-muted:#94a3b8;--border:#334155}body.dark .card,body.dark .stat-card,body.dark .pg,body.dark .ub,body.dark .modal-box,body.dark .modal-content{background:#1e293b!important;border-color:#334155!important}body.dark table{background:#1e293b!important;color:#e2e8f0!important}body.dark th,body.dark .tbl th{background:#0f172a!important}body.dark td,body.dark .tbl td{border-color:#334155!important}body.dark input,body.dark select,body.dark textarea{background:#0f172a!important;color:#e2e8f0!important;border-color:#475569!important}body.dark .btn-s{background:#334155!important;color:#cbd5e1!important}body.dark .gantt-wrap{background:#1e293b!important}body.dark .gantt-task-row,body.dark .gantt-grid-row{background:#1e293b!important;color:#e2e8f0!important}body.dark .gantt-hleft,body.dark .gantt-month{background:#0f172a!important}body.dark .msg.ok{background:#064e3b!important;color:#6ee7b7!important}</style>
