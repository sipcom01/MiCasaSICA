<?php $user = Auth::currentUser(); ?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><svg viewBox="0 0 36 44" width="30" height="37"><rect x="1.5" y="1.5" width="33" height="41" rx="2" fill="none" stroke="#50C8C6" stroke-width="2.5"/><rect x="8" y="24" width="7" height="14" fill="#FFFFFF"/><rect x="21" y="12" width="7" height="26" fill="#FFFFFF"/></svg></div>
        <div><div class="brand-name">SICA</div><div class="brand-sub">Panel Admin</div></div>
    </div>
    <nav class="sidebar-nav">
        <a href="index.php" class="nav-item">📊 Proyectos</a>
        <a href="mis-tareas.php" class="nav-item active">✅ Mis Tareas</a>
        <a href="usuarios.php" class="nav-item">👥 Usuarios</a>
        <a href="logout.php" class="nav-item">🚪 Salir</a>
    </nav>
    <div class="user-info" style="padding:1rem 1.5rem;border-top:1px solid rgba(255,255,255,0.1);margin-top:auto">
        <div class="user-avatar"><?= strtoupper(mb_substr($user['nombre'],0,1)) ?></div>
        <div><div class="user-name"><?= htmlspecialchars($user['nombre']) ?></div><a href="logout.php" class="user-logout">Cerrar sesión</a></div>
    </div>
    <div class="dark-switch-wrap">
        <label class="dark-switch"><input type="checkbox" id="darkSwitch" onchange="toggleDark()"><span class="slider"></span></label>
        <span class="dark-switch-label">Modo Noche</span>
    </div>
</aside>
<script>
function toggleDark(){var b=document.body;var c=document.getElementById("darkSwitch");b.classList.toggle("dark",c.checked);localStorage.setItem("sica-dark",c.checked?"1":"0")}
(function(){if(localStorage.getItem("sica-dark")==="1"){document.body.classList.add("dark");var c=document.getElementById("darkSwitch");if(c)c.checked=true}})();
</script>
