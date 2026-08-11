<?php
/**
 * SICA Construcciones - Landing Page
 * Soluciones Integrales en Construcción Atlacomulco S.A de C.V.
 *
 * Esta es la página principal del sitio web público. Contiene:
 *   1. Navbar fijo con logo y navegación por anclas (#inicio, #nosotros, etc.)
 *   2. Hero con video de fondo y llamado a la acción
 *   3. Sección "Nosotros" con estadísticas de la empresa
 *   4. Modelo de negocio en 5 pasos
 *   5. Portafolio de proyectos (cargados desde la BD SQLite)
 *   6. Alianzas estratégicas (Coldwell Banker, Softec, Avilés)
 *   7. Formulario de contacto con mapa interactivo (Leaflet) y campos dinámicos
 *   8. Footer corporativo
 *
 * Funcionalidad JS incluida inline:
 *   - Efecto scroll en navbar
 *   - Menú móvil toggle
 *   - Smooth scroll y resaltado de sección activa
 *   - Campos dinámicos del formulario según tipo de interés (dueño, inversionista, etc.)
 *   - Mapa Leaflet con geocoding (Nominatim) y marcador arrastrable
 *   - Envío AJAX del formulario a api/contacto.php
 */
define('SICA_APP', true);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// Inicializar DB y obtener proyectos
$db = Database::getInstance();
$db->initTables();
$db->seedData();
$proyectos = $db->getPdo()->query("SELECT * FROM proyectos ORDER BY id")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="¿Tienes un terreno y quieres hacer un fraccionamiento? En SICA Construcciones te ayudamos a lotificar y desarrollar tu predio. Estudios, permisos, financiamiento y urbanización llave en mano. Contáctanos.">

    <meta property="og:title" content="SICA Construcciones | ¿Cómo Hacer un Fraccionamiento? Desarrolla tu Terreno">
    <meta property="og:description" content="¿Tienes un terreno? Te ayudamos a hacer un fraccionamiento. Estudios, permisos, financiamiento y urbanización llave en mano. Sin inversión de tu bolsillo.">
    <meta property="og:url" content="https://micasasica.com">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="es_MX">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="SICA Construcciones | Desarrolla tu Terreno">
    <meta name="twitter:description" content="Convertimos terrenos en fraccionamientos residenciales llave en mano.">
    <meta name="keywords" content="fraccionamiento, lotificar terreno, desarrollar terreno, como hacer un fraccionamiento, urbanización, desarrollo inmobiliario, SICA Construcciones, dueño de terreno, plusvalía">
    <title>SICA Construcciones | ¿Cómo Hacer un Fraccionamiento? Desarrolla tu Terreno</title>
    <link rel="stylesheet" href="assets/css/style.css?v=20">
    <style>
    .hero-video{position:relative;border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.4);background:#000;width:100%!important;height:0!important;padding-bottom:56.25%}
    .hero-video iframe,.hero-video video{position:absolute!important;top:0;left:0;width:100%!important;height:100%!important;border:0;object-fit:cover}
    </style>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 36 44%27%3E%3Crect x=%271.5%27 y=%271.5%27 width=%2733%27 height=%2741%27 rx=%272%27 fill=%27none%27 stroke=%27%2350C8C6%27 stroke-width=%272.5%27/%3E%3Crect x=%278%27 y=%2724%27 width=%277%27 height=%2714%27 fill=%27%23FFFFFF%27/%3E%3Crect x=%2721%27 y=%2712%27 width=%277%27 height=%2726%27 fill=%27%23FFFFFF%27/%3E%3C/svg%3E">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800;900&display=swap" rel="stylesheet">
<script type="application/ld+json">{"@context": "https://schema.org", "@type": "Organization", "name": "SICA Construcciones", "legalName": "Soluciones Integrales en Construcción Atlacomulco S.A de C.V.", "url": "https://micasasica.com", "telephone": "+527228819163", "email": "contacto@micasasica.com", "address": {"@type": "PostalAddress", "streetAddress": "Av. José María Velasco 20, Centro", "addressLocality": "Temascalcingo de José María Velasco", "addressRegion": "Méx.", "postalCode": "50400", "addressCountry": "MX"}, "description": "Desarrolladora de fraccionamientos residenciales. Convertimos terrenos en desarrollos llave en mano. Estudios, permisos, financiamiento y urbanización completa.", "sameAs": ["https://micasasica.com"], "areaServed": {"@type": "GeoCircle", "geoMidpoint": {"@type": "GeoCoordinates", "latitude": 19.7991, "longitude": -99.8744}, "geoRadius": "200000"}}</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar" id="navbar">
    <a href="#" class="logo">
        <img src="assets/img/Logo_Horizontal.png" alt="SICA Construcciones" class="logo-img">
    </a>
    <ul class="nav-links" id="navLinks">
        <li><a href="#inicio" class="active">Inicio</a></li>
        <li><a href="#nosotros">Nosotros</a></li>
        <li><a href="#modelo">Modelo</a></li>
        <li><a href="#portafolio">Portafolio</a></li>
        <li><a href="faq.php">FAQ</a></li>
        <li><a href="#contacto">Contacto</a></li>
        <li><a href="admin/" class="btn btn-outline btn-sm" style="color:var(--teal);border-color:var(--teal);">Acceso Staff</a></li>
    </ul>
    <button class="nav-toggle" id="navToggle" aria-label="Menú">&#9776;</button>
</nav>

<!-- HERO -->
<section class="hero" id="inicio">
    <div class="hero-container">
        <div class="hero-video">
            <video autoplay muted loop playsinline preload="auto" poster="assets/img/Fraccionameinto_muestra.png">
                <source src="assets/video/Clip_1.mp4" type="video/mp4">
            </video>
        </div>
        <div class="hero-content">
            <h1>Convertimos <span>terrenos</span> en desarrollos residenciales</h1>
            <p>Identificamos predios con alto potencial, realizamos estudios de mercado, proyecto ejecutivo, gestiones y financiamiento. Desarrollamos el fraccionamiento completo y lo entregamos a inmobiliarias de renombre para su comercialización.</p>
            <div class="hero-buttons">
                <a href="#modelo" class="btn btn-primary">Nuestro Modelo</a>
                <a href="#contacto" class="btn btn-outline">¿Tienes un Terreno?</a>
            </div>
        </div>
    </div>
</section>

<!-- NOSOTROS -->
<section class="section section-light" id="nosotros">
    <div class="container">
        <div class="section-title">
            <span class="overline">Quiénes Somos</span>
            <h2>Impulsando el desarrollo inmobiliario en el centro de México</h2>
            <div class="divider"></div>
        </div>
        <div class="about-grid">
            <div class="about-image">
                <img src="assets/img/Logo_Cuadrado.png" alt="SICA Construcciones" class="about-logo">
            </div>
            <div class="about-text">
                <h3>Hacemos realidad proyectos residenciales de interés medio y superior</h3>
                <p>Somos una desarrolladora especializada en identificar terrenos con alto potencial de desarrollo y convertirlos en fraccionamientos residenciales completos. <strong>No vendemos casas al público final:</strong> desarrollamos el proyecto de principio a fin y lo entregamos a inmobiliarias de renombre para su comercialización.</p>
                <p>Nuestro equipo multidisciplinario cubre todas las etapas: estudio de mercado, proyecto ejecutivo, gestoría de permisos, estructuración financiera, urbanización y construcción. Solo trabajamos en desarrollos de <strong>interés medio hacia arriba</strong>, garantizando calidad y plusvalía en cada proyecto.</p>
                <p>Operamos en el centro del país, la región con mayor dinamismo inmobiliario de México, donde hemos construido relaciones sólidas con autoridades, inversionistas y las principales inmobiliarias comerciales.</p>
                <div class="about-stats">
                    <div class="stat-item">
                        <div class="stat-number"><?= count($proyectos) ?></div>
                        <div class="stat-label">Desarrollos Activos</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">2</div>
                        <div class="stat-label">Estados</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">+500</div>
                        <div class="stat-label">Lotes Desarrollados</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- MODELO DE NEGOCIO -->
<section class="section section-dark" id="modelo">
    <div class="container">
        <div class="section-title">
            <span class="overline">Nuestro Modelo</span>
            <h2>Del terreno en breña al desarrollo llave en mano</h2>
            <div class="divider"></div>
        </div>
        <div class="process-grid">
            <div class="process-step">
                <div class="process-number">1</div>
                <div class="process-icon">🔍</div>
                <h3>Identificación del Terreno</h3>
                <p>Evaluamos predios con ubicación estratégica y potencial de desarrollo. Realizamos estudios de factibilidad técnica y legal.</p>
            </div>
            <div class="process-arrow">→</div>
            <div class="process-step">
                <div class="process-number">2</div>
                <div class="process-icon">📋</div>
                <h3>Estudios y Proyecto Ejecutivo</h3>
                <p>Estudio de mercado, diseño urbano, proyecto ejecutivo y gestoría de permisos ante autoridades municipales y estatales.</p>
            </div>
            <div class="process-arrow">→</div>
            <div class="process-step">
                <div class="process-number">3</div>
                <div class="process-icon">💰</div>
                <h3>Financiamiento</h3>
                <p>Estructuramos el capital necesario para el desarrollo. Conseguimos inversionistas y/o financiamiento bancario para ejecutar el proyecto.</p>
            </div>
            <div class="process-arrow">→</div>
            <div class="process-step">
                <div class="process-number">4</div>
                <div class="process-icon">🏗️</div>
                <h3>Desarrollo y Construcción</h3>
                <p>Ejecutamos la urbanización completa: terracerías, redes hidrosanitarias, electrificación, vialidades y áreas verdes.</p>
            </div>
            <div class="process-arrow">→</div>
            <div class="process-step">
                <div class="process-number">5</div>
                <div class="process-icon">🤝</div>
                <h3>Entrega a Inmobiliaria</h3>
                <p>Entregamos el fraccionamiento completamente urbanizado a una inmobiliaria de renombre para su comercialización al público.</p>
            </div>
        </div>
    </div>
</section>

<!-- PORTAFOLIO -->
<section class="section section-dark" id="portafolio">
    <div class="container">
        <div class="section-title">
            <span class="overline">Nuestro Portafolio</span>
            <h2>Proyectos que transforman terrenos en comunidad</h2>
            <div class="divider"></div>
        </div>
        <div class="projects-grid">
            <?php 
            $logos = [
                'San Isidro' => 'Logo_Horizontal_San_isidro.png',
                'San Fernando Residencial' => 'Logo_San_fernando_Horizontal.png',
                'San Carlos Residencial' => 'Logo_Horizontal_San_Carlos.png',
            ];
            foreach ($proyectos as $p):
                $logo = $logos[$p['nombre']] ?? null;
                $status = $p['status'];
                $badgeClass = $status === 'en_construccion' ? 'badge-building' : ($status === 'en_planeacion' ? 'badge-planning' : 'badge-done');
                $badgeText = $status === 'en_construccion' ? 'En Construcción' : ($status === 'en_planeacion' ? 'En Planeación' : 'Completado');
            ?>
            <div class="project-card">
                <div class="project-card-image">
                    <?php $img = !empty($p['imagen_url']) ? $p['imagen_url'] : ($logo ? 'assets/img/'.$logo : null); ?>
                    <?php if ($img): ?>
                    <div class="card-logo-container">
                        <a href="proyecto.php?id=<?= $p['id'] ?>">
                            <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($p['nombre']) ?>">
                        </a>
                    </div>
                    <?php endif; ?>
                    <span class="project-card-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
                </div>
                <div class="project-card-body">
                    <h3><?= htmlspecialchars($p['nombre']) ?></h3>
                    <div class="location">&#128205; <?= htmlspecialchars($p['ubicacion']) ?></div>
                    <p><?= htmlspecialchars($p['descripcion']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ALIANZAS -->
<section class="section section-light">
    <div class="container">
        <div class="section-title">
            <span class="overline">Alianzas Estratégicas</span>
            <h2>Trabajamos con los mejores para garantizar cada etapa</h2>
            <div class="divider"></div>
        </div>
        <div class="alianzas-grid">
            <div class="alianza-card">
                <div class="alianza-logo-container">
                    <a href="https://coldwellbanker.com.mx/" target="_blank" rel="noopener noreferrer">
                        <img src="assets/img/logo_coldwell.png" alt="Coldwell Banker" class="alianza-logo-img">
                    </a>
                </div>
                <p class="alianza-rol">Comercialización</p>
                <p><a href="https://coldwellbanker.com.mx/" target="_blank" rel="noopener noreferrer">Coldwell Banker</a>, una de las inmobiliarias más prestigiosas a nivel mundial, se encarga de la <strong>comercialización de nuestros desarrollos</strong>, asegurando que cada fraccionamiento llegue al comprador ideal con la mejor estrategia de venta.</p>
            </div>
            <div class="alianza-card">
                <div class="alianza-logo-container">
                    <a href="https://softec.com.mx/home/" target="_blank" rel="noopener noreferrer">
                        <img src="assets/img/logo-softec.png" alt="Softec" class="alianza-logo-img">
                    </a>
                </div>
                <p class="alianza-rol">Estudios Comerciales</p>
                <p><a href="https://softec.com.mx/home/" target="_blank" rel="noopener noreferrer">Softec</a> realiza los <strong>estudios de mercado y análisis comerciales</strong> que validan la viabilidad de cada proyecto. Su inteligencia de negocio nos permite tomar decisiones informadas sobre ubicación, segmento y dimensionamiento.</p>
            </div>
            <div class="alianza-card">
                <div class="alianza-logo-container">
                    <a href="https://avilesyasociados.com.mx/" target="_blank" rel="noopener noreferrer">
                        <img src="assets/img/Logo_Aviles.webp" alt="Avilés y Asociados" class="alianza-logo-img">
                    </a>
                </div>
                <p class="alianza-rol">Gestoría y Trámites</p>
                <p><a href="https://avilesyasociados.com.mx/" target="_blank" rel="noopener noreferrer">Avilés y Asociados</a> es nuestro aliado estratégico en <strong>gestoría de permisos, licencias y trámites</strong> ante autoridades municipales, estatales y federales, garantizando que cada proyecto cumpla con toda la normatividad vigente.</p>
            </div>
        </div>
    </div>
</section>

<!-- CONTACTO -->
<section class="section section-light" id="contacto">
    <div class="container">
        <div class="section-title">
            <span class="overline">Contáctanos</span>
            <h2>¿Tienes un terreno o buscas invertir en desarrollo?</h2>
            <div class="divider"></div>
        </div>
        <div class="contact-grid">
            <div class="contact-info">
                <h3>Hablemos de tu terreno o proyecto</h3>
                <p><strong>Si eres dueño de un terreno</strong> con potencial de desarrollo, evaluamos su viabilidad sin costo. Si el predio cumple con nuestros criterios, podemos estructurar el proyecto completo.</p>
                <p><strong>Si eres inversionista</strong> o representas una inmobiliaria, conoce nuestras oportunidades de desarrollo y alianzas comerciales.</p>
                <div class="contact-detail">
                    <div class="icon">&#128205;</div>
                    <div>
                        <h4>Oficina Corporativa</h4>
                        <span>Av. José María Velasco 20, Centro, 50400 Temascalcingo de José María Velasco, Méx.</span>
                    </div>
                </div>
                <div class="contact-detail">
                    <div class="icon">&#128231;</div>
                    <div>
                        <h4>Correo Electrónico</h4>
                        <span>contacto@micasasica.com</span>
                    </div>
                </div>
                <div class="contact-detail">
                    <div class="icon">&#128222;</div>
                    <div>
                        <h4>Teléfono</h4>
                        <span><a href="tel:7228819163" style="color:inherit;text-decoration:none;">722 881 9163</a></span>
                    </div>
                </div>
            </div>
            <form class="contact-form" id="contactForm" onsubmit="return handleContact(event)">
                <div class="form-group">
                    <label for="name">Nombre completo *</label>
                    <input type="text" id="name" name="name" placeholder="Tu nombre" required>
                </div>
                <div class="form-group">
                    <label for="email">Correo electrónico *</label>
                    <input type="email" id="email" name="email" placeholder="tu@correo.com" required>
                </div>
                <div class="form-group">
                    <label for="phone">Teléfono</label>
                    <input type="tel" id="phone" name="phone" placeholder="722 000 0000">
                </div>
                <div class="form-group">
                    <label for="interest">Me interesa como... *</label>
                    <select id="interest" name="interest" required onchange="showDynamicFields()">
                        <option value="">Selecciona una opción</option>
                        <option value="dueno_terreno">Dueño de terreno para desarrollar</option>
                        <option value="inversionista">Inversionista / Capitalista</option>
                        <option value="inmobiliaria">Inmobiliaria buscando alianza</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
                <!-- Campos dinámicos -->
                <div id="dynamicFields" style="display:none;"></div>
                <button type="submit" class="btn btn-primary" style="width:100%;">Enviar Información</button>
                <p id="formMsg" style="margin-top:1rem;text-align:center;display:none;"></p>
            </form>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer class="footer">
    <div class="container">
        <img src="assets/img/Logo_Horizontal.png" alt="SICA Construcciones" class="footer-logo-img">
        <p>&copy; <?= date('Y') ?> Soluciones Integrales en Construcción Atlacomulco S.A de C.V. Todos los derechos reservados.</p>
    </div>
</footer>

<script>
// ═══════════════════════════════════════════════════════════════
// JAVASCRIPT DE LA LANDING PAGE
// ═══════════════════════════════════════════════════════════════

// ─── 1. EFECTO SCROLL EN NAVBAR ──────────────────────────────
// Añade sombra cuando el usuario hace scroll más de 50px
window.addEventListener('scroll', () => {
    document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 50);
});

// ─── 2. MENÚ MÓVIL TOGGLE ────────────────────────────────────
// Abre/cierra el menú de navegación en dispositivos móviles
document.getElementById('navToggle').addEventListener('click', () => {
    document.getElementById('navLinks').classList.toggle('open');
});

// ─── 3. SMOOTH SCROLL + CIERRE DE MENÚ MÓVIL ─────────────────
// Al hacer clic en un enlace de ancla, cierra el menú móvil
// y hace scroll suave hasta la sección destino
document.querySelectorAll('.nav-links a[href^="#"]').forEach(link => {
    link.addEventListener('click', (e) => {
        document.getElementById('navLinks').classList.remove('open');
        const target = document.querySelector(link.getAttribute('href'));
        if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth' }); }
    });
});

// ─── 4. RESALTADO DE ENLACE ACTIVO SEGÚN SCROLL ──────────────
// Detecta qué sección es visible y marca el enlace correspondiente como activo
const sections = document.querySelectorAll('section[id]');
window.addEventListener('scroll', () => {
    let scrollY = window.pageYOffset;
    sections.forEach(section => {
        const sectionHeight = section.offsetHeight;
        const sectionTop = section.offsetTop - 100;
        const sectionId = section.getAttribute('id');
        const link = document.querySelector(`.nav-links a[href="#${sectionId}"]`);
        if (link && scrollY > sectionTop && scrollY <= sectionTop + sectionHeight) {
            link.classList.add('active');
        } else if (link) {
            link.classList.remove('active');
        }
    });
});

// ─── 5. CAMPOS DINÁMICOS DEL FORMULARIO ────────────────────────
// Muestra campos específicos según el tipo de interés seleccionado:
//   - dueño_terreno → superficie, ubicación con mapa Leaflet
//   - inversionista  → monto disponible
//   - inmobiliaria   → página web
function showDynamicFields() {
    var tipo = document.getElementById('interest').value;
    var container = document.getElementById('dynamicFields');
    var html = '';
    if (tipo === 'dueno_terreno') {
        html = '<div class="form-group"><label>Superficie del terreno (m²) *</label><input type="number" id="superficie" name="superficie" placeholder="Ej: 5000" min="100" required></div>'+
               '<div class="form-group"><label>Ubicación del terreno</label>'+
               '<div style="display:flex;gap:0.5rem;">'+
               '<input type="text" id="ubicacion" name="ubicacion" placeholder="Ej: Maravatío, Michoacán — o busca y selecciona en el mapa" style="flex:1;">'+
               '<button type="button" onclick="buscarUbicacion()" style="padding:0.65rem 1rem;background:#50C8C6;color:#132236;border:none;border-radius:8px;font-weight:700;cursor:pointer;">🔍</button>'+
               '</div></div>'+
               '<div id="mapContainer" style="width:100%;height:220px;border-radius:8px;margin-bottom:1rem;cursor:crosshair;background:#e2e8f0;position:relative;overflow:hidden;"></div>'+
               '<input type="hidden" id="latitud" name="latitud"><input type="hidden" id="longitud" name="longitud">';
        setTimeout(function() { initMap(); }, 100);
    } else if (tipo === 'inversionista') {
        html = '<div class="form-group"><label>Monto disponible para invertir (MXN) *</label><input type="number" id="monto" name="monto" placeholder="Ej: 5000000" min="100000" required></div>';
    } else if (tipo === 'inmobiliaria') {
        html = '<div class="form-group"><label>Página web de tu inmobiliaria</label><input type="text" id="web" name="web" placeholder="https://tuinmobiliaria.com"></div>';
    }
    container.innerHTML = html;
    container.style.display = tipo ? 'block' : 'none';
}

// ─── 6. ENVÍO AJAX DEL FORMULARIO DE CONTACTO ─────────────────
// Envía los datos del formulario a api/contacto.php vía fetch(),
// muestra mensaje de éxito/error, y resetea el formulario tras éxito.
function handleContact(e) {
    e.preventDefault();
    var form = document.getElementById('contactForm');
    var msgEl = document.getElementById('formMsg');
    var btn = form.querySelector('button[type=submit]');
    btn.disabled = true; btn.textContent = 'Enviando...';
    var formData = new FormData(form);
    fetch('api/contacto.php', { method: 'POST', body: formData })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        msgEl.style.display = 'block';
        if (res.success) {
            msgEl.style.color = '#22c55e';
            msgEl.textContent = res.message;
            form.reset();
            document.getElementById('dynamicFields').innerHTML = '';
            document.getElementById('dynamicFields').style.display = 'none';
        } else {
            msgEl.style.color = '#ef4444';
            msgEl.textContent = res.error || 'Error al enviar.';
        }
        btn.disabled = false; btn.textContent = 'Enviar Información';
        setTimeout(function() { msgEl.style.display = 'none'; }, 6000);
    })
    .catch(function() {
        msgEl.style.display = 'block';
        msgEl.style.color = '#ef4444';
        msgEl.textContent = 'Error de conexión. Intenta de nuevo.';
        btn.disabled = false; btn.textContent = 'Enviar Información';
    });
    return false;
}

var map = null, marker = null;  // Referencias al mapa y marcador de Leaflet
var isLeafletLoading = false;   // Candado para evitar carga duplicada del script CDN

// ─── 8. CARGA DINÁMICA DE LEAFLET ─────────────────────────────
// Inyecta el CSS y JS de Leaflet desde CDN solo cuando se necesita
// (cuando el usuario selecciona "Dueño de terreno" en el formulario).
// Usa un callback para ejecutar initMap() una vez que el script esté listo.
function loadLeaflet(cb) {
    if (typeof L !== 'undefined') { cb(); return; }
    if (isLeafletLoading) return; // Si ya se está intentando cargar, no hacer nada
    
    isLeafletLoading = true;

    // Inyectar CSS si no existe
    if (!document.querySelector('link[href*="leaflet.min.css"]')) {
        var css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css';
        document.head.appendChild(css);
    }

    // Inyectar JS usando CDNJS (Evita errores ORB)
    var s = document.createElement('script');
    s.src = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js';
    s.onload = function() {
        isLeafletLoading = false;
        cb();
    };
    s.onerror = function() {
        isLeafletLoading = false;
        console.error("Error al cargar el script de Leaflet.");
    };
    document.head.appendChild(s);
}

// 2. Inicialización del Mapa
// Inicializa el mapa Leaflet centrado en México (19.8, -99.9) con zoom 7
function initMap() {
    loadLeaflet(function() {
        var container = document.getElementById("mapContainer");
        if (!container) return;

        // Limpieza profunda si el mapa ya existía
        if (map) { 
            map.off(); // Apagar listeners
            map.remove(); // Destruir instancia
            map = null; 
        }

        container.innerHTML = ""; // Limpiar texto de "Cargando..."
        
        map = L.map("mapContainer").setView([19.7991, -99.8744], 7);
        
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            attribution: '© OpenStreetMap'
        }).addTo(map);
        
        marker = L.marker([19.7991, -99.8744], { draggable: true }).addTo(map);

        // Eventos del mapa
        map.on("click", function(e) { 
            marker.setLatLng(e.latlng); 
            updateCoords(e.latlng, true); 
        });
        
        marker.on("dragend", function() { 
            updateCoords(marker.getLatLng(), true); 
        });

        // Recalcular tamaño tras renderizar
        setTimeout(function() { if (map) map.invalidateSize(); }, 300);
    });
}

// 3. Actualizar Coordenadas
function updateCoords(ll, updateInput) {
    var inputLat = document.getElementById("latitud");
    var inputLng = document.getElementById("longitud");
    
    if (inputLat && inputLng) {
        inputLat.value = ll.lat.toFixed(6);
        inputLng.value = ll.lng.toFixed(6);
    }

    // Solo actualizar el input de texto si el usuario hizo clic (no si buscó por texto)
    if (updateInput) {
        var ubicacion = document.getElementById("ubicacion");
        // Si está vacío o tiene coordenadas previas, poner las nuevas. 
        // Si tiene un nombre de ciudad, no borrarlo.
        if (ubicacion && (!ubicacion.value || ubicacion.value.match(/^-?\d/))) {
            ubicacion.value = ll.lat.toFixed(4) + ", " + ll.lng.toFixed(4);
        }
    }
}

// 4. Buscador Geocoding Optimizado
function buscarUbicacion() {
    var u = document.getElementById("ubicacion");
    if (!u) return;
    
    var query = u.value.trim();
    if (!query) { alert("Escribe una ciudad o dirección para buscar."); return; }
    
    if (!map || !marker) { 
        alert("Espera un momento a que cargue el mapa."); 
        return; 
    }

    u.value = "Buscando..."; // Feedback visual

    fetch("https://nominatim.openstreetmap.org/search?format=json&q=" + encodeURIComponent(query), {
        headers: { "Accept-Language": "es" } // Prevenir bloqueos 403 de OSM
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.length === 0) { 
            u.value = query; // Restaurar texto original
            alert("No se encontró la ubicación. Intenta con otro término."); 
            return; 
        }
        
        var lat = parseFloat(data[0].lat);
        var lon = parseFloat(data[0].lon);
        
        map.setView([lat, lon], 14);
        marker.setLatLng([lat, lon]);
        updateCoords({lat: lat, lng: lon}, false);
        
        // Limpiar el nombre para que no sea un bloque de texto gigante
        var cleanName = data[0].display_name.split(",").slice(0, 3).join(",");
        u.value = cleanName;
    })
    .catch(function(e) { 
        console.error("Error Geocoding:", e);
        u.value = query; // Restaurar texto
        alert("Error de conexión al buscar la ubicación."); 
    });
}

// 5. Soporte para tecla Enter
document.addEventListener("keydown", function(e) {
    if (e.key === "Enter" && document.activeElement && document.activeElement.id === "ubicacion") {
        e.preventDefault();
        buscarUbicacion();
    }
});
</script>
</body>
</html>
