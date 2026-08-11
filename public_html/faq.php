<?php
/**
 * SICA Construcciones - Página de Preguntas Frecuentes (FAQ)
 *
 * Presenta preguntas y respuestas categorizadas sobre:
 *   - El proceso de desarrollar un fraccionamiento
 *   - Trámites legales y permisos necesarios
 *   - Costos y financiamiento
 *   - Información sobre SICA como empresa
 *
 * Cada pregunta es expandible (acordeón) al hacer clic.
 * Incluye datos estructurados JSON-LD (schema.org FAQPage) para SEO.
 */
define('SICA_APP', true); require_once __DIR__ . '/includes/config.php'; ?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Preguntas frecuentes sobre cómo hacer un fraccionamiento, lotificar tu terreno, costos, permisos y más. SICA Construcciones te guía paso a paso.">
    <title>FAQ - Preguntas Frecuentes | Cómo Lotificar tu Terreno | SICA Construcciones</title>
    <link rel="stylesheet" href="assets/css/style.css?v=13">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 36 44%27%3E%3Crect x=%271.5%27 y=%271.5%27 width=%2733%27 height=%2741%27 rx=%272%27 fill=%27none%27 stroke=%27%2350C8C6%27 stroke-width=%272.5%27/%3E%3Crect x=%278%27 y=%2724%27 width=%277%27 height=%2714%27 fill=%27%23FFFFFF%27/%3E%3Crect x=%2721%27 y=%2712%27 width=%277%27 height=%2726%27 fill=%27%23FFFFFF%27/%3E%3C/svg%3E">
    <style>
        .faq-hero { background: #132236; padding: 6rem 2rem 3rem; text-align: center; color: #fff; }
        .faq-hero h1 { font-size: 2.5rem; margin-bottom: 0.5rem; }
        .faq-hero p { color: #94a3b8; font-size: 1.1rem; max-width: 600px; margin: 0 auto; }
        .faq-body { max-width: 900px; margin: 0 auto; padding: 3rem 2rem; }
        .faq-categories { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 2.5rem; justify-content: center; }
        .faq-cat { padding: 0.5rem 1.2rem; border-radius: 50px; font-size: 0.85rem; font-weight: 600; cursor: pointer; border: 1.5px solid #e2e8f0; background: #fff; color: #475569; transition: all 0.2s; }
        .faq-cat:hover, .faq-cat.active { background: #50C8C6; color: #132236; border-color: #50C8C6; }
        .faq-item { border-bottom: 1px solid #e2e8f0; padding: 1rem 0; }
        .faq-q { font-size: 1.1rem; font-weight: 700; color: #132236; cursor: pointer; display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; }
        .faq-q:hover { color: #50C8C6; }
        .faq-q .arrow { font-size: 1.2rem; transition: transform 0.3s; color: #50C8C6; }
        .faq-q.open .arrow { transform: rotate(180deg); }
        .faq-a { color: #475569; line-height: 1.8; font-size: 0.95rem; display: none; padding: 0.5rem 0 1rem; }
        .faq-a.show { display: block; }
        .faq-cta { background: #132236; color: #fff; text-align: center; padding: 3rem 2rem; margin-top: 2rem; border-radius: 16px; }
        .faq-cta h2 { color: #fff; margin-bottom: 1rem; }
        .faq-cta p { color: #cbd5e1; margin-bottom: 2rem; }
        .btn-primary { display:inline-block; padding:0.85rem 2rem; border-radius:8px; font-weight:700; text-decoration:none; background:#50C8C6; color:#132236; transition:all 0.3s; }
        .btn-primary:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(80,200,198,0.4); }
        @media (max-width:768px) { .faq-hero h1 { font-size:1.8rem; } .faq-q { font-size:1rem; } } @media (max-width:480px) { .faq-hero { padding:4rem 1rem 2rem; } .faq-hero h1 { font-size:1.4rem; } .faq-hero p { font-size:0.9rem; } .faq-body { padding:2rem 1rem; } .faq-cat { font-size:0.75rem; padding:0.4rem 0.8rem; } .faq-q { font-size:0.9rem; } .faq-cta { padding:2rem 1rem; } .faq-cta h2 { font-size:1.3rem; } }
    </style>
<script type="application/ld+json">{"@context": "https://schema.org", "@type": "FAQPage", "mainEntity": [{"@type": "Question", "name": "¿Cómo puedo hacer un fraccionamiento en mi terreno?", "acceptedAnswer": {"@type": "Answer", "text": "El proceso comienza con un estudio de factibilidad que evalúa la ubicación, el mercado, la normatividad y la viabilidad financiera. Posteriormente se elabora un proyecto ejecutivo, se tramitan los permisos municipales y estatales, se consigue el financiamiento, se ejecuta la obra de urbanización (agua, drenaje, electrificación, vialidades) y finalmente se entrega a una inmobiliaria para su comercialización. En SICA nos encargamos de todo el proceso, desde el terreno en breña hasta el desarrollo llave en mano."}}, {"@type": "Question", "name": "¿Cuánto tiempo toma desarrollar un fraccionamiento desde cero?", "acceptedAnswer": {"@type": "Answer", "text": "Típicamente toma entre 2 y 4 años desde los estudios iniciales hasta la entrega final. Las etapas incluyen: estudios y permisos (6-12 meses), urbanización (12-24 meses) y comercialización (en paralelo)."}}, {"@type": "Question", "name": "¿Qué tipo de terrenos son viables para un fraccionamiento?", "acceptedAnswer": {"@type": "Answer", "text": "Buscamos terrenos con ubicación estratégica, cercanos a zonas urbanas, con acceso a vialidades, factibilidad de servicios y sin riesgos legales. Consideramos predios desde 1 hectárea. Solo interés medio hacia arriba."}}, {"@type": "Question", "name": "¿Cuáles son los pasos para lotificar un terreno?", "acceptedAnswer": {"@type": "Answer", "text": "1) Estudio topográfico y de factibilidad. 2) Proyecto de lotificación y diseño urbano. 3) Trámite de permisos ante el municipio. 4) Escrituración del régimen de propiedad. 5) Obra de urbanización. 6) Entrega de lotes con servicios."}}, {"@type": "Question", "name": "¿Qué permisos necesito para lotificar mi terreno?", "acceptedAnswer": {"@type": "Answer", "text": "Dictamen de Uso de Suelo, Licencia de Urbanización, Manifestación de Impacto Ambiental, Factibilidad de Servicios (agua, CFE, drenaje), Alineamiento y Número Oficial, y Autorización del régimen de propiedad."}}, {"@type": "Question", "name": "¿Puedo lotificar un terreno ejidal?", "acceptedAnswer": {"@type": "Answer", "text": "Sí, pero requiere desincorporación del régimen ejidal mediante PROCEDE o equivalente para convertir a propiedad privada. Luego se sigue el proceso normal de lotificación."}}, {"@type": "Question", "name": "¿Cuánto cuesta desarrollar un fraccionamiento?", "acceptedAnswer": {"@type": "Answer", "text": "La urbanización básica cuesta entre $500 y $1,500 MXN por m². Un proyecto de 100 lotes requiere inversiones desde $15-30 millones de pesos. En SICA conseguimos el financiamiento."}}, {"@type": "Question", "name": "¿Tengo que pagar yo el desarrollo de mi terreno?", "acceptedAnswer": {"@type": "Answer", "text": "No necesariamente. SICA estructura el financiamiento con inversionistas y/o créditos bancarios. Tú aportas el terreno, nosotros el capital y la ejecución."}}, {"@type": "Question", "name": "¿Cuánto vale mi terreno después de desarrollarlo?", "acceptedAnswer": {"@type": "Answer", "text": "Un terreno urbanizado puede valer entre 3 y 10 veces más que uno en breña. Por ejemplo, $200 MXN/m² rústico puede valer $1,500-$2,500 MXN/m² urbanizado."}}, {"@type": "Question", "name": "¿SICA vende las casas o lotes al público?", "acceptedAnswer": {"@type": "Answer", "text": "No. SICA es una desarrolladora. Entregamos el fraccionamiento a inmobiliarias de renombre como Coldwell Banker para su comercialización."}}]}</script>
</head>
<body>

<nav class="navbar" id="navbar">
    <a href="/" class="logo"><img src="assets/img/Logo_Horizontal.png" alt="SICA Construcciones" class="logo-img"></a>
    <ul class="nav-links" id="navLinks">
        <li><a href="/#nosotros">Nosotros</a></li>
        <li><a href="/#modelo">Modelo</a></li>
        <li><a href="/#portafolio">Portafolio</a></li>
        <li><a href="/faq.php" class="active">FAQ</a></li>
        <li><a href="/#contacto">Contacto</a></li>
    </ul>
    <button class="nav-toggle" id="navToggle" aria-label="Menú">&#9776;</button>
</nav>

<section class="faq-hero">
    <h1>¿Tienes un terreno y no sabes cómo desarrollarlo?</h1>
    <p>Resolvemos todas tus dudas sobre cómo lotificar, hacer un fraccionamiento y convertir tu predio en un desarrollo residencial exitoso.</p>
</section>

<div class="faq-body">
    <div class="faq-categories">
        <button class="faq-cat active" onclick="filterFAQ('all', this)">Todas</button>
        <button class="faq-cat" onclick="filterFAQ('proceso', this)">El Proceso</button>
        <button class="faq-cat" onclick="filterFAQ('legal', this)">Legal y Permisos</button>
        <button class="faq-cat" onclick="filterFAQ('dinero', this)">Costos y Financiamiento</button>
        <button class="faq-cat" onclick="filterFAQ('sica', this)">Sobre SICA</button>
    </div>

    <?php
    $faqs = [
        ['cat'=>'proceso', 'q'=>'¿Cómo puedo hacer un fraccionamiento en mi terreno?', 'a'=>'El proceso comienza con un estudio de factibilidad que evalúa la ubicación, el mercado, la normatividad y la viabilidad financiera. Posteriormente se elabora un proyecto ejecutivo, se tramitan los permisos municipales y estatales, se consigue el financiamiento, se ejecuta la obra de urbanización (agua, drenaje, electrificación, vialidades) y finalmente se entrega a una inmobiliaria para su comercialización. En SICA nos encargamos de todo el proceso, desde el terreno en breña hasta el desarrollo llave en mano.'],
        ['cat'=>'proceso', 'q'=>'¿Cuánto tiempo toma desarrollar un fraccionamiento desde cero?', 'a'=>'El tiempo depende del tamaño y complejidad del proyecto, pero típicamente toma entre 2 y 4 años desde los estudios iniciales hasta la entrega final. Las etapas incluyen: estudios y permisos (6-12 meses), urbanización (12-24 meses) y comercialización (en paralelo). Un proyecto pequeño puede completarse en 18-24 meses.'],
        ['cat'=>'proceso', 'q'=>'¿Qué tipo de terrenos son viables para un fraccionamiento?', 'a'=>'Buscamos terrenos con ubicación estratégica, preferentemente cercanos a zonas urbanas consolidadas, con acceso a vialidades principales, factibilidad de servicios (agua, luz, drenaje) y sin riesgos legales o ambientales. La superficie mínima depende del proyecto, pero generalmente consideramos predios desde 1 hectárea. Solo desarrollamos proyectos de interés medio hacia arriba, no vivienda de interés social.'],
        ['cat'=>'proceso', 'q'=>'¿Cuáles son los pasos para lotificar un terreno?', 'a'=>'1) Estudio topográfico y de factibilidad. 2) Proyecto de lotificación y diseño urbano. 3) Trámite de permisos ante el municipio (uso de suelo, licencia de urbanización). 4) Escrituración del régimen de propiedad en condominio o subdivisión. 5) Obra de urbanización. 6) Entrega de lotes con servicios. Cada paso tiene requisitos específicos según el estado y municipio.'],
        
        ['cat'=>'legal', 'q'=>'¿Qué permisos necesito para lotificar mi terreno?', 'a'=>'Los principales permisos incluyen: Dictamen de Uso de Suelo (municipal), Licencia de Urbanización, Manifestación de Impacto Ambiental (si aplica), Factibilidad de Servicios (agua, CFE, drenaje), Alineamiento y Número Oficial, y Autorización del régimen de propiedad (condominio o subdivisión). Los requisitos varían por municipio y estado. En SICA nos encargamos de toda la gestoría.'],
        ['cat'=>'legal', 'q'=>'¿Puedo lotificar un terreno ejidal?', 'a'=>'Sí, pero el proceso es más complejo. Primero debe realizarse el procedimiento de desincorporación del régimen ejidal mediante el Programa de Certificación de Derechos Ejidales (PROCEDE) o su equivalente, para convertir el terreno a propiedad privada. Posteriormente se sigue el proceso normal de lotificación. Recomendamos asesoría legal especializada.'],
        ['cat'=>'legal', 'q'=>'¿Qué es el uso de suelo y por qué es importante?', 'a'=>'El uso de suelo es la clasificación que el municipio asigna a cada predio, determinando qué tipo de construcción se permite (habitacional, comercial, industrial, etc.). Para un fraccionamiento necesitas uso de suelo habitacional con la densidad adecuada. Sin este permiso, cualquier desarrollo es ilegal y puede ser clausurado. Es el primer trámite que debe realizarse.'],
        
        ['cat'=>'dinero', 'q'=>'¿Cuánto cuesta desarrollar un fraccionamiento?', 'a'=>'El costo varía según la ubicación, tamaño, nivel de urbanización y tipo de vivienda. Como referencia, la urbanización básica (agua, drenaje, electrificación, vialidades) puede costar entre $500 y $1,500 MXN por metro cuadrado de terreno. Un proyecto de 100 lotes puede requerir inversiones desde $15-30 millones de pesos. En SICA conseguimos el financiamiento para que tú no tengas que invertir de tu bolsillo.'],
        ['cat'=>'dinero', 'q'=>'¿Tengo que pagar yo el desarrollo de mi terreno?', 'a'=>'No necesariamente. En el modelo de SICA, nosotros estructuramos el financiamiento con inversionistas y/o créditos bancarios para cubrir los costos de desarrollo. Tú aportas el terreno y nosotros aportamos el capital, los estudios, los permisos y la ejecución. Las ganancias se comparten según lo acordado. Es una sociedad donde todos ganan.'],
        ['cat'=>'dinero', 'q'=>'¿Cuánto vale mi terreno después de desarrollarlo?', 'a'=>'Un terreno urbanizado puede valer entre 3 y 10 veces más que un terreno en breña. Por ejemplo, un predio rústico que vale $200 MXN/m² puede valer $1,500-$2,500 MXN/m² una vez urbanizado con todos los servicios. La plusvalía depende de la ubicación, el tipo de desarrollo y la demanda del mercado.'],
        ['cat'=>'dinero', 'q'=>'¿Qué porcentaje de ganancia me corresponde como dueño del terreno?', 'a'=>'Depende de la negociación y del valor del terreno. Típicamente, el dueño del terreno recibe entre el 20% y 40% de las utilidades del proyecto, o un pago fijo por la venta del terreno más un porcentaje. En SICA estructuramos cada proyecto de manera personalizada para que sea justo para todas las partes.'],
        
        ['cat'=>'sica', 'q'=>'¿SICA vende las casas o lotes al público?', 'a'=>'No. SICA es una desarrolladora, no una inmobiliaria comercial. Nosotros identificamos terrenos con potencial, hacemos estudios, conseguimos financiamiento, desarrollamos la urbanización completa y entregamos el fraccionamiento a inmobiliarias de renombre (como Coldwell Banker) para que ellas comercialicen los lotes o viviendas al público final.'],
        ['cat'=>'sica', 'q'=>'¿En qué estados opera SICA?', 'a'=>'Operamos en el centro del país, principalmente en el Estado de México y Michoacán. Tenemos proyectos activos en Atlacomulco, Maravatío y Uruapan. Si tu terreno está en otra ubicación, contáctanos y evaluamos la viabilidad.'],
        ['cat'=>'sica', 'q'=>'¿Cómo puedo proponer mi terreno para un desarrollo?', 'a'=>'Simplemente contáctanos a través del formulario en nuestra página, llámanos al 722 881 9163, o escríbenos a contacto@micasasica.com. Evaluamos tu terreno sin costo. Si el predio cumple con nuestros criterios de viabilidad, te presentamos una propuesta de desarrollo.'],
        ['cat'=>'sica', 'q'=>'¿Qué garantías tengo como dueño del terreno?', 'a'=>'Todos los acuerdos se formalizan mediante contratos legalmente vinculantes ante notario público. Tú mantienes la propiedad del terreno hasta que se concrete el desarrollo o la venta. Trabajamos con total transparencia: tienes acceso a todos los estudios, permisos y avances del proyecto.'],
        ['cat'=>'sica', 'q'=>'¿Qué diferencia a SICA de otras desarrolladoras?', 'a'=>'1) Hacemos todo el proceso: desde el estudio de factibilidad hasta la entrega llave en mano. 2) Conseguimos el financiamiento, tú no pones dinero. 3) Solo hacemos proyectos de interés medio hacia arriba, con calidad garantizada. 4) Trabajamos con aliados de primer nivel: Softek para estudios de mercado, Coldwell Banker para comercialización, y Avilés y Asociados para gestoría. 5) Operamos con total transparencia y contratos claros.'],
    ];
    
    $currentCat = '';
    foreach ($faqs as $i => $faq):
        if ($faq['cat'] !== $currentCat):
            $currentCat = $faq['cat'];
        endif;
    ?>
    <div class="faq-item" data-cat="<?= $faq['cat'] ?>">
        <div class="faq-q" onclick="toggleFAQ(this)"><span><?= $faq['q'] ?></span><span class="arrow">▾</span></div>
        <div class="faq-a"><?= $faq['a'] ?></div>
    </div>
    <?php endforeach; ?>

    <div class="faq-cta">
        <h2>¿Listo para desarrollar tu terreno?</h2>
        <p>Contáctanos hoy. Evaluamos tu predio sin costo y te decimos si es viable para un fraccionamiento.</p>
        <a href="tel:7228819163" class="btn-primary">📞 Llamar: 722 881 9163</a>
    </div>
</div>

<footer class="footer">
    <div class="container">
        <img src="assets/img/Logo_Horizontal.png" alt="SICA Construcciones" class="footer-logo-img">
        <p>&copy; <?= date('Y') ?> Soluciones Integrales en Construcción Atlacomulco S.A de C.V.</p>
    </div>
</footer>

<script>
function toggleFAQ(el) {
    el.classList.toggle('open');
    el.nextElementSibling.classList.toggle('show');
}
function filterFAQ(cat, btn) {
    document.querySelectorAll('.faq-cat').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.faq-item').forEach(item => {
        item.style.display = (cat === 'all' || item.dataset.cat === cat) ? '' : 'none';
    });
}
// Mobile nav toggle
document.getElementById('navToggle').addEventListener('click', () => {
    document.getElementById('navLinks').classList.toggle('open');
});
</script>
</body>
</html>
