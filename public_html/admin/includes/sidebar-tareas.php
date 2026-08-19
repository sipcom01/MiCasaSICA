<?php $user = Auth::currentUser(); ?>
<aside class="sidebar collapsed" id="adminSidebar">
    <button class="sidebar-toggle" onclick="toggleSidebar()" aria-label="Alternar menú" title="Mostrar/ocultar menú">☰</button>
    <div class="sidebar-brand">
        <div class="brand-icon"><svg viewBox="0 0 36 44" width="30" height="37"><rect x="1.5" y="1.5" width="33" height="41" rx="2" fill="none" stroke="#50C8C6" stroke-width="2.5"/><rect x="8" y="24" width="7" height="14" fill="#FFFFFF"/><rect x="21" y="12" width="7" height="26" fill="#FFFFFF"/></svg></div>
        <div class="brand-text"><div class="brand-name">SICA</div><div class="brand-sub">Panel Admin</div></div>
    </div>
    <nav class="sidebar-nav">
        <a href="index.php" class="nav-item" data-label="Proyectos"><span class="nav-icon">📊</span><span class="nav-label">Proyectos</span></a>
        <a href="mis-tareas.php" class="nav-item active" data-label="Mis Tareas"><span class="nav-icon">✅</span><span class="nav-label">Mis Tareas</span></a>
        <a href="usuarios.php" class="nav-item" data-label="Usuarios"><span class="nav-icon">👥</span><span class="nav-label">Usuarios</span></a>
        <a href="logout.php" class="nav-item" data-label="Salir"><span class="nav-icon">🚪</span><span class="nav-label">Salir</span></a>
    </nav>
    <div class="user-info-wrap">
        <div class="user-avatar"><?= strtoupper(mb_substr($user['nombre'],0,1)) ?></div>
        <div><div class="user-name"><?= htmlspecialchars($user['nombre']) ?></div><a href="logout.php" class="user-logout">Cerrar sesión</a></div>
    </div>
    <div class="dark-switch-wrap">
        <label class="dark-switch"><input type="checkbox" id="darkSwitch" onchange="toggleDark()"><span class="slider"></span></label>
        <span class="dark-switch-label">Modo Noche</span>
    </div>
</aside>
<script>
function toggleSidebar(){var s=document.getElementById('adminSidebar');if(!s)return;s.classList.toggle('collapsed');localStorage.setItem('sica-sidebar',s.classList.contains('collapsed')?'1':'0')}
(function(){var s=document.getElementById('adminSidebar');if(!s)return;if(localStorage.getItem('sica-sidebar')==='0'){s.classList.remove('collapsed')}})();
function toggleDark(){var b=document.body;var c=document.getElementById("darkSwitch");b.classList.toggle("dark",c.checked);localStorage.setItem("sica-dark",c.checked?"1":"0")}
(function(){if(localStorage.getItem("sica-dark")==="1"){document.body.classList.add("dark");var c=document.getElementById("darkSwitch");if(c)c.checked=true}})();
</script>
