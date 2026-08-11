<?php
/**
 * SICA Construcciones - Página de Detalle de Proyecto
 *
 * Muestra la información completa de un proyecto inmobiliario:
 *   - Hero con video (YouTube o MP4), nombre, ubicación, badge de estado
 *   - Plano general del fraccionamiento (imagen clickeable)
 *   - Descripción larga del desarrollo
 *   - Modelos de vivienda disponibles (tarjetas con imágenes)
 *   - Sidebar con datos del proyecto y checklist de servicios
 *   - CTA final invitando a desarrollar un terreno
 *   - Modal para ampliar imágenes al hacer clic
 *
 * Recibe el parámetro GET ?id= para cargar el proyecto desde la BD SQLite.
 * Si el proyecto no existe, redirige al home.
 */
define('SICA_APP', true);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// ─── CARGAR DATOS DEL PROYECTO DESDE LA BD ───────────────────
// Obtiene el proyecto por ID, sus archivos (planos/diseños) y servicios
$db = Database::getInstance();
$db->initTables();
$db->seedData();
$pdo = $db->getPdo();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT * FROM proyectos WHERE id = :id");
$stmt->execute(['id' => $id]);
$proyecto = $stmt->fetch();
if (!$proyecto) { header('Location: /'); exit; }

$archivos = $pdo->prepare("SELECT * FROM proyecto_archivos WHERE proyecto_id = :pid ORDER BY tipo, orden");
$archivos->execute(['pid' => $id]);
$archivos = $archivos->fetchAll();
$planos = array_values(array_filter($archivos, fn($a) => $a['tipo'] === 'plano'));
$disenos = array_values(array_filter($archivos, fn($a) => $a['tipo'] === 'diseno'));

$servicios = $pdo->prepare("SELECT * FROM proyecto_servicios WHERE proyecto_id = :pid ORDER BY orden");
$servicios->execute(['pid' => $id]);
$servicios = $servicios->fetchAll();

$statusLabels = ['en_construccion' => 'En Construcción', 'en_planeacion' => 'En Planeación', 'completado' => 'Completado'];
$statusColors = ['en_construccion' => '#3b82f6', 'en_planeacion' => '#f59e0b', 'completado' => '#22c55e'];
$logos = [
    'San Isidro' => 'Logo_Horizontal_San_isidro.png',
    'San Fernando Residencial' => 'Logo_San_fernando_Horizontal.png',
    'San Carlos Residencial' => 'Logo_Horizontal_San_Carlos.png',
];
$logo = $logos[$proyecto['nombre']] ?? null;
$statusLabel = $statusLabels[$proyecto['status']] ?? $proyecto['status'];
$statusColor = $statusColors[$proyecto['status']] ?? '#94a3b8';
?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($proyecto['nombre']) ?> | SICA Construcciones</title>
    <link rel="stylesheet" href="assets/css/style.css?v=10">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 36 44%27%3E%3Crect x=%271.5%27 y=%271.5%27 width=%2733%27 height=%2741%27 rx=%272%27 fill=%27none%27 stroke=%27%2350C8C6%27 stroke-width=%272.5%27/%3E%3Crect x=%278%27 y=%2724%27 width=%277%27 height=%2714%27 fill=%27%23FFFFFF%27/%3E%3Crect x=%2721%27 y=%2712%27 width=%277%27 height=%2726%27 fill=%27%23FFFFFF%27/%3E%3C/svg%3E">
    <style>
        :root { --navy: #132236; --teal: #50C8C6; --gray-100: #f1f5f9; --gray-200: #e2e8f0; --gray-400: #94a3b8; --gray-600: #475569; --gray-800: #1e293b; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; color: var(--gray-800); background: #fff; }
        
        /* ── HERO ── */
        .hero { background: var(--navy); padding: 5rem 2rem 3rem; }
        .hero-grid { max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: 1.6fr 1fr; gap: 2rem; align-items: center; }
        .hero-video-wrap { border-radius: 12px; overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.4); background: #000; }
        .hero-video-wrap video, .hero-video-wrap iframe { width: 100%; aspect-ratio: 16/9; display: block; border: 0; }
        .hero-video-placeholder { aspect-ratio: 16/9; background: linear-gradient(135deg,#1b3050,#132236); display: flex; align-items: center; justify-content: center; }
        .hero-info { color: #fff; }
        .hero-badge { display: inline-block; padding: 0.35rem 1rem; border-radius: 50px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 1rem; background: <?= $statusColor ?>; color: #fff; }
        .hero-info h1 { font-size: 2.2rem; font-weight: 800; margin-bottom: 0.5rem; line-height: 1.1; }
        .hero-info .loc { color: var(--teal); font-size: 1rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.4rem; }
        .hero-stats { display: flex; gap: 2rem; margin-top: 1.5rem; }
        .hero-stat .val { font-size: 1.5rem; font-weight: 800; color: var(--teal); }
        .hero-stat .lbl { font-size: 0.8rem; color: var(--gray-400); }
        
        /* ── MAIN LAYOUT ── */
        .main { max-width: 1200px; margin: 0 auto; padding: 3rem 2rem; display: grid; grid-template-columns: 1fr 340px; gap: 3rem; }
        .content h2 { font-size: 1.6rem; color: var(--navy); margin-bottom: 1.2rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--teal); display: inline-block; }
        .content p { color: var(--gray-600); line-height: 1.8; margin-bottom: 1.5rem; font-size: 1rem; }
        .content .section-block { margin-bottom: 3rem; }
        
        /* ── SIDEBAR ── */
        .sidebar-box { background: #fff; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 16px rgba(0,0,0,0.06); border: 1px solid var(--gray-200); margin-bottom: 1.5rem; }
        .sidebar-box h3 { font-size: 1rem; color: var(--navy); margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--gray-200); }
        .sb-row { display: flex; justify-content: space-between; padding: 0.4rem 0; font-size: 0.9rem; border-bottom: 1px solid #f8fafc; }
        .sb-row:last-child { border-bottom: 0; }
        .sb-row .key { color: var(--gray-400); }
        .sb-row .val { color: var(--gray-800); font-weight: 600; text-align: right; }
        .serv-item { display: flex; align-items: center; gap: 0.5rem; padding: 0.35rem 0; font-size: 0.85rem; border-bottom: 1px solid #f1f5f9; }
        .serv-item:last-child { border-bottom: 0; }
        .serv-check { color: #22c55e; font-weight: 700; font-size: 0.9rem; flex-shrink: 0; }
        
        /* ── PLANO ── */
        .plano-section { background:var(--gray-100); padding: 3rem 2rem; text-align: center; }
        .plano-section h2 { font-size: 1.6rem; color: var(--navy); margin-bottom: 1.5rem; }
        .plano-section img { max-width: 100%; max-height: 600px; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,0.1); cursor: pointer; transition: transform 0.3s; }
        .plano-section img:hover { transform: scale(1.02); }
        
        /* ── MODELOS ── */
        .modelos-section { padding: 3rem 2rem; background: #fff; }
        .modelos-wrap { max-width: 1200px; margin: 0 auto; }
        .modelos-wrap h2 { font-size: 1.6rem; color: var(--navy); margin-bottom: 1.5rem; text-align: center; }
        .modelos-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem; }
        .modelo-card { background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: 1px solid var(--gray-200); transition: all 0.3s; }
        .modelo-card:hover { transform: translateY(-6px); box-shadow: 0 12px 36px rgba(0,0,0,0.15); }
        .modelo-card img { width: 100%; height: 240px; object-fit: cover; display: block; }
        .modelo-card .card-body { padding: 1.5rem; }
        .modelo-card h3 { font-size: 1.2rem; color: var(--navy); margin-bottom: 0.5rem; }
        .modelo-card p { font-size: 0.9rem; color: var(--gray-600); line-height: 1.6; }
        
        /* ── IMAGE MODAL ── */
        .img-modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.9); z-index: 9999; align-items: center; justify-content: center; flex-direction: column; }
        .img-modal.active { display: flex; }
        .img-modal img { max-width: 90%; max-height: 75vh; border-radius: 8px; box-shadow: 0 20px 60px rgba(0,0,0,0.5); }
        .img-modal-close { position: absolute; top: 1.5rem; right: 2rem; color: #fff; font-size: 2.5rem; cursor: pointer; z-index: 1; }
        .img-modal-close:hover { color: #50C8C6; }
        .img-modal p { color: #fff; margin-top: 1rem; font-size: 1rem; }

        /* ── CTA ── */
        .cta { background: var(--navy); color: #fff; text-align: center; padding: 4rem 2rem; }
        .cta h2 { font-size: 1.8rem; margin-bottom: 1rem; color: #fff; }
        .cta p { color: #cbd5e1; margin-bottom: 2rem; max-width: 500px; margin-left: auto; margin-right: auto; }
        .btn { display: inline-block; padding: 0.85rem 2rem; border-radius: 8px; font-weight: 700; text-decoration: none; font-size: 0.95rem; transition: all 0.3s; }
        .btn-primary { background: var(--teal); color: var(--navy); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(80,200,198,0.4); }
        .btn-outline { background: transparent; color: #fff; border: 2px solid rgba(255,255,255,0.3); }
        .btn-outline:hover { border-color: var(--teal); color: var(--teal); }
        
        /* ── FOOTER ── */
        .footer { background:var(--navy); color:var(--gray-400); padding:2rem; text-align:center; border-top:2px solid var(--teal); }
        .footer img { height:50px; }
        .footer p { font-size:0.85rem; margin-top:0.5rem; }
        
        @media (max-width: 768px) {
            .hero-grid, .main { grid-template-columns: 1fr; }
            .hero-info h1 { font-size: 2rem; }
            .hero-stats { flex-wrap: wrap; }
            .modelos-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 480px) {
            .hero { padding: 4rem 1rem 2rem; }
            .hero-info h1 { font-size: 1.5rem; }
            .hero-stats { gap: 1rem; }
            .hero-stat .val { font-size: 1.2rem; }
            .main { padding: 2rem 1rem; }
            .plano-section { padding: 2rem 1rem; }
            .modelos-section { padding: 2rem 1rem; }
            .modelo-card img { height: 180px; }
            .cta { padding: 2.5rem 1rem; }
            .cta h2 { font-size: 1.3rem; }
            .sidebar-box { padding: 1rem; }
        }
    </style>
</head>
<body>

<nav class="navbar" id="navbar">
    <a href="/" class="logo">
        <img src="assets/img/Logo_Horizontal.png" alt="SICA Construcciones" class="logo-img">
    </a>
    <ul class="nav-links" id="navLinks">
        <li><a href="/#inicio">Inicio</a></li>
        <li><a href="/#nosotros">Nosotros</a></li>
        <li><a href="/#modelo">Modelo</a></li>
        <li><a href="/#portafolio">Portafolio</a></li>
        <li><a href="/#contacto">Contacto</a></li>
        <li><a href="admin/" class="btn btn-outline btn-sm" style="color:var(--teal);border-color:var(--teal);">Acceso Staff</a></li>
    </ul>
    <button class="nav-toggle" id="navToggle" aria-label="Menú">&#9776;</button>
</nav>

<!-- HERO -->
<section class="hero">
    <div class="hero-grid">
        <div class="hero-video-wrap">
            <?php if (!empty($proyecto['video_url'])): ?>
                <?php if (strpos($proyecto['video_url'], 'youtube.com') !== false || strpos($proyecto['video_url'], 'youtu.be') !== false): ?>
                    <?php 
                        $vid = '';
                        if (preg_match('/[?&]v=([^&]+)/', $proyecto['video_url'], $m)) $vid = $m[1];
                        elseif (preg_match('/youtu\.be\/([^?]+)/', $proyecto['video_url'], $m)) $vid = $m[1];
                    ?>
                    <iframe src="https://www.youtube.com/embed/<?= $vid ?>?autoplay=1&mute=1&loop=1&playlist=<?= $vid ?>&controls=0&modestbranding=1&rel=0&playsinline=1" allow="autoplay" allowfullscreen></iframe>
                <?php else: ?>
                    <video autoplay muted loop playsinline>
                        <source src="<?= htmlspecialchars($proyecto['video_url']) ?>" type="video/mp4">
                    </video>
                <?php endif; ?>
            <?php else: ?>
                <div class="hero-video-placeholder"></div>
            <?php endif; ?>
        </div>
        <div class="hero-info">
            <span class="hero-badge"><?= $statusLabel ?></span>
            <h1><?= htmlspecialchars($proyecto['nombre']) ?></h1>
            <div class="loc">📍 <?= htmlspecialchars($proyecto['ubicacion']) ?></div>
            <p style="color:#cbd5e1;line-height:1.7;font-size:1rem;"><?= htmlspecialchars($proyecto['descripcion']) ?></p>
            <div class="hero-stats">
                <div class="hero-stat"><div class="val"><?= date('Y', strtotime($proyecto['fecha_inicio'])) ?></div><div class="lbl">Inicio</div></div>
                <div class="hero-stat"><div class="val"><?= date('Y', strtotime($proyecto['fecha_fin'])) ?></div><div class="lbl">Entrega</div></div>
                <div class="hero-stat"><div class="val"><?= count($disenos) ?></div><div class="lbl">Modelos</div></div>
            </div>
        </div>
    </div>
</section>

<!-- PLANO GENERAL -->
<?php if (!empty($planos)): ?>
<section class="plano-section">
    <h2>📐 Plano General del Fraccionamiento</h2>
    <img src="<?= htmlspecialchars($planos[0]['archivo_url']) ?>" alt="<?= htmlspecialchars($planos[0]['titulo']) ?>" onclick="openModal('<?= htmlspecialchars($planos[0]['archivo_url']) ?>','<?= htmlspecialchars($planos[0]['titulo']) ?>')" style="cursor:pointer">
    <p style="margin-top:0.75rem;color:var(--gray-400);font-size:0.85rem;"><?= htmlspecialchars($planos[0]['titulo']) ?> — Click para ampliar</p>
</section>
<?php endif; ?>

<!-- MAIN CONTENT + SIDEBAR -->
<div class="main">
    <div class="content">
        <?php if ($proyecto['descripcion_larga']): ?>
        <div class="section-block">
            <h2>🏗️ Sobre el Desarrollo</h2>
            <p><?= nl2br(htmlspecialchars($proyecto['descripcion_larga'])) ?></p>
        </div>
        <?php endif; ?>

        <?php if (!empty($disenos)): ?>
        <div class="section-block">
            <h2>🏡 Modelos de Vivienda</h2>
            <div class="modelos-grid">
                <?php foreach ($disenos as $d): ?>
                <div class="modelo-card">
                    <img src="<?= htmlspecialchars($d['archivo_url']) ?>" alt="<?= htmlspecialchars($d['titulo']) ?>" loading="lazy" onclick="openModal('<?= htmlspecialchars($d['archivo_url']) ?>', '<?= htmlspecialchars($d['titulo']) ?>')" style="cursor:pointer;">
                    <div class="card-body">
                        <h3><?= htmlspecialchars($d['titulo']) ?></h3>
                        <p><?= !empty($d['descripcion']) ? htmlspecialchars($d['descripcion']) : 'Modelo de vivienda disponible en '.htmlspecialchars($proyecto['nombre']).'. Diseñado con los más altos estándares de calidad y confort para tu familia.' ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- SIDEBAR -->
    <aside>
        <div class="sidebar-box">
            <h3>📋 Datos del Proyecto</h3>
            <div class="sb-row"><span class="key">Estado</span><span class="val" style="color:<?= $statusColor ?>"><?= $statusLabel ?></span></div>
            <div class="sb-row"><span class="key">Inicio</span><span class="val"><?= date('d/m/Y', strtotime($proyecto['fecha_inicio'])) ?></span></div>
            <div class="sb-row"><span class="key">Entrega est.</span><span class="val"><?= date('d/m/Y', strtotime($proyecto['fecha_fin'])) ?></span></div>
            <div class="sb-row"><span class="key">Ubicación</span><span class="val"><?= htmlspecialchars($proyecto['ubicacion']) ?></span></div>
        </div>
        
        <div class="sidebar-box">
            <h3>✅ Servicios del proyecto</h3>
            <?php foreach ($servicios as $s): ?>
            <div class="serv-item">
                <span class="serv-check">✓</span>
                <span><?= htmlspecialchars($s['nombre']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </aside>
</div>

<!-- CTA -->
<section class="cta">
    <h2>¿Tienes un terreno con potencial de desarrollo?</h2>
    <p>En SICA convertimos predios en fraccionamientos residenciales completos. Si eres dueño de un terreno, evaluamos su viabilidad sin costo y estructuramos el proyecto.</p>
    <a href="/#contacto" class="btn btn-primary">Quiero desarrollar mi terreno</a>
</section>

<!-- MODAL IMAGEN -->
<div class="img-modal" id="imgModal" onclick="closeModal()">
    <span class="img-modal-close">&times;</span>
    <img id="imgModalImg" src="" alt="">
    <p id="imgModalCaption"></p>
</div>

<footer class="footer">
    <div class="container">
        <img src="assets/img/Logo_Horizontal.png" alt="SICA Construcciones" class="footer-logo-img">
        <p>&copy; <?= date('Y') ?> Soluciones Integrales en Construcción Atlacomulco S.A de C.V.</p>
    </div>
    <p>&copy; <?= date('Y') ?> Soluciones Integrales en Construcción Atlacomulco S.A de C.V.</p>
</footer>
<script>
function openModal(src, caption) {
    document.getElementById('imgModalImg').src = src;
    document.getElementById('imgModalCaption').textContent = caption;
    document.getElementById('imgModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeModal() {
    document.getElementById('imgModal').classList.remove('active');
    document.body.style.overflow = '';
}
</script>
</body>
</html>
