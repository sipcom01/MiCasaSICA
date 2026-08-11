<?php
/**
 * SICA - Conexión a Base de Datos SQLite
 *
 * Clase Database (Singleton):
 * - Gestiona la conexión PDO a la base de datos SQLite ubicada en DATA_PATH/sica.db
 * - Usa el patrón Singleton para garantizar una sola conexión en toda la app
 * - initTables(): crea todas las tablas si no existen (usuarios, proyectos, fases, etc.)
 * - seedData():   inserta datos iniciales (admin, proyectos demo) si las tablas están vacías
 *
 * Estructura de la BD:
 *   usuarios              → cuentas de acceso al panel admin
 *   proyectos             → desarrollos inmobiliarios (San Isidro, San Fernando, etc.)
 *   fases                 → etapas de cada proyecto para el diagrama de Gantt
 *   proyecto_archivos     → planos y diseños de cada proyecto
 *   proyecto_servicios    → checklist de servicios por proyecto
 *   presupuesto_categorias → categorías de presupuesto por proyecto
 *   presupuesto_partidas  → partidas individuales dentro de cada categoría
 */

if (!defined('SICA_APP')) {
    die('Acceso no autorizado.');
}

class Database {
    private static $instance = null; // Única instancia (patrón Singleton)
    private $pdo;                   // Conexión PDO subyacente

    /**
     * Constructor privado — solo se llama desde getInstance()
     * Configura PDO con SQLite, modo WAL para mejor concurrencia,
     * y activa claves foráneas para integridad referencial.
     */
    private function __construct() {
        try {
            $this->pdo = new PDO('sqlite:' . DB_PATH);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            // WAL (Write-Ahead Logging): permite lecturas concurrentes mientras se escribe
            $this->pdo->exec('PRAGMA journal_mode=WAL');
            // Habilita el soporte de FOREIGN KEY en SQLite (viene desactivado por defecto)
            $this->pdo->exec('PRAGMA foreign_keys=ON');
        } catch (PDOException $e) {
            die('Error de conexión a la base de datos.');
        }
    }

    /**
     * Obtiene la instancia única de Database (patrón Singleton).
     * La primera llamada crea la conexión; las siguientes devuelven la misma.
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Devuelve el objeto PDO para ejecutar consultas directamente.
     */
    public function getPdo() {
        return $this->pdo;
    }

    /**
     * Inicializa todas las tablas de la base de datos si no existen (CREATE TABLE IF NOT EXISTS).
     * También ejecuta migraciones ALTER TABLE para añadir columnas nuevas a tablas existentes.
     *
     * Tablas creadas:
     *   usuarios              — cuentas de acceso al panel admin
     *   proyectos             — desarrollos inmobiliarios
     *   proyecto_archivos     — planos y diseños asociados a cada proyecto
     *   fases                 — etapas del Gantt por proyecto
     *   presupuesto_categorias — agrupaciones de partidas presupuestarias
     *   presupuesto_partidas  — líneas de presupuesto individuales
     */
    public function initTables() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS usuarios (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                nombre TEXT NOT NULL,
                activo INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS proyectos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre TEXT NOT NULL,
                ubicacion TEXT NOT NULL,
                descripcion TEXT,
                descripcion_larga TEXT,
                video_url TEXT,
                imagen_url TEXT,
                lat REAL,
                lng REAL,
                status TEXT DEFAULT 'en_construccion',
                fecha_inicio DATE,
                fecha_fin DATE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS proyecto_archivos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                proyecto_id INTEGER NOT NULL,
                tipo TEXT NOT NULL DEFAULT 'plano',
                titulo TEXT NOT NULL,
                archivo_url TEXT NOT NULL,
                orden INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS fases (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                proyecto_id INTEGER NOT NULL,
                nombre TEXT NOT NULL,
                descripcion TEXT,
                fecha_inicio DATE,
                fecha_fin DATE,
                progreso INTEGER DEFAULT 0,
                dependencia_id INTEGER,
                color TEXT DEFAULT '#3b82f6',
                orden INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
                FOREIGN KEY (dependencia_id) REFERENCES fases(id) ON DELETE SET NULL
            );
        ");

        // ─── Migraciones: añadir columnas que no existían en versiones anteriores ───
        // Se usa try/catch porque ALTER TABLE falla si la columna ya existe en SQLite
        try { $this->pdo->exec("ALTER TABLE proyectos ADD COLUMN descripcion_larga TEXT"); } catch (\Exception $e) {}
        try { $this->pdo->exec("ALTER TABLE proyectos ADD COLUMN video_url TEXT"); } catch (\Exception $e) {}
        try { $this->pdo->exec("ALTER TABLE usuarios ADD COLUMN rol TEXT DEFAULT 'colaborador'"); } catch (\Exception $e) {}
        try { $this->pdo->exec("ALTER TABLE usuarios ADD COLUMN correo TEXT"); } catch (\Exception $e) {}
        try { $this->pdo->exec("ALTER TABLE usuarios ADD COLUMN telefono TEXT"); } catch (\Exception $e) {}
        try { $this->pdo->exec("ALTER TABLE presupuesto_partidas ADD COLUMN completado INTEGER DEFAULT 0"); } catch (\Exception $e) {}
        try { $this->pdo->exec("ALTER TABLE presupuesto_partidas ADD COLUMN archivo_resultado TEXT"); } catch (\Exception $e) {}
        try { $this->pdo->exec("ALTER TABLE presupuesto_partidas ADD COLUMN fecha_terminacion_real DATE"); } catch (\Exception $e) {}
        try { $this->pdo->exec("ALTER TABLE presupuesto_partidas ADD COLUMN dependencias TEXT"); } catch (\Exception $e) {}
        try { $this->pdo->exec("ALTER TABLE usuario_proyectos ADD COLUMN permiso TEXT DEFAULT 'editar'"); } catch (\Exception $e) {}
        try { $this->pdo->exec("ALTER TABLE proyecto_archivos ADD COLUMN descripcion TEXT"); } catch (\Exception $e) {}
        try { $this->pdo->exec("ALTER TABLE presupuesto_partidas ADD COLUMN presupuesto_tercero REAL DEFAULT 0"); } catch (\Exception $e) {}
        try { $this->pdo->exec("ALTER TABLE presupuesto_partidas ADD COLUMN nota_presupuesto TEXT"); } catch (\Exception $e) {}
        try { $this->pdo->exec("ALTER TABLE usuarios ADD COLUMN reset_token TEXT"); } catch (\Exception $e) {}
        try { $this->pdo->exec("ALTER TABLE usuarios ADD COLUMN reset_token_expires DATETIME"); } catch (\Exception $e) {}
        // Tablas de historial y chat
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS tarea_historial (id INTEGER PRIMARY KEY AUTOINCREMENT, partida_id INTEGER NOT NULL, usuario_id INTEGER NOT NULL, accion TEXT NOT NULL, detalle TEXT, fecha DATETIME DEFAULT CURRENT_TIMESTAMP)");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS tarea_chat (id INTEGER PRIMARY KEY AUTOINCREMENT, partida_id INTEGER NOT NULL, usuario_id INTEGER NOT NULL, mensaje TEXT NOT NULL, rol TEXT DEFAULT 'user', fecha DATETIME DEFAULT CURRENT_TIMESTAMP)");

        // ─── Tablas de presupuesto ──────────────────────────────────────
        // presupuesto_categorias: agrupa partidas (ej. "Urbanización", "Trámites")
        // presupuesto_partidas:   cada línea de gasto con costo estimado, real y progreso
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS presupuesto_categorias (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                proyecto_id INTEGER NOT NULL,
                codigo TEXT NOT NULL,
                nombre TEXT NOT NULL,
                descripcion TEXT,
                orden INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
            );
            CREATE TABLE IF NOT EXISTS presupuesto_partidas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                categoria_id INTEGER NOT NULL,
                procedimiento TEXT NOT NULL,
                responsable TEXT,
                costo_estimado REAL DEFAULT 0,
                costo_real REAL DEFAULT 0,
                progreso INTEGER DEFAULT 0,
                fecha_inicio DATE,
                fecha_fin DATE,
                tipo_costo TEXT DEFAULT 'interno',
                notas TEXT,
                orden INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (categoria_id) REFERENCES presupuesto_categorias(id) ON DELETE CASCADE
            );
        ");

        // ─── Tabla pivote: usuarios ↔ proyectos ─────────────────
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS usuario_proyectos (
                usuario_id INTEGER NOT NULL,
                proyecto_id INTEGER NOT NULL,
                PRIMARY KEY (usuario_id, proyecto_id),
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
                FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
            );
        ");
    }

    /**
     * Inserta datos iniciales (seed) si las tablas están vacías.
     * Solo se ejecuta la primera vez que se carga el sistema.
     *
     * Datos sembrados:
     *   1. Usuario admin por defecto (credenciales de config.php)
     *   2. Tres proyectos demo: San Isidro, San Fernando Residencial, San Carlos Residencial
     *   3. Fases de Gantt para cada proyecto con fechas y progreso simulados
     */
    public function seedData() {
        // ─── Usuario administrador inicial ───
        // Solo se crea si la tabla usuarios está vacía
        $stmt = $this->pdo->query("SELECT COUNT(*) as c FROM usuarios");
        if ($stmt->fetch()['c'] == 0) {
            $this->pdo->prepare("
                INSERT INTO usuarios (username, password_hash, nombre) 
                VALUES (:u, :p, :n)
            ")->execute([
                'u' => ADMIN_USERNAME,
                'p' => ADMIN_PASSWORD_HASH,
                'n' => 'Administrador SICA'
            ]);
        }

        // ─── Proyectos demo iniciales ───
        // Solo se insertan si no hay ningún proyecto en la BD
        $stmt = $this->pdo->query("SELECT COUNT(*) as c FROM proyectos");
        if ($stmt->fetch()['c'] == 0) {
            // Datos de los 3 proyectos que aparecen en el portafolio del sitio público
            $proyectos = [
                [
                    'nombre' => 'San Isidro',
                    'ubicacion' => 'Maravatío, Michoacán',
                    'descripcion' => 'Desarrollo residencial en el corazón de Maravatío. Un proyecto pensado para familias que buscan calidad de vida, con amplias áreas verdes, seguridad y accesibilidad. San Isidro ofrece lotes desde 120m² con todos los servicios.',
                    'descripcion_larga' => 'San Isidro es un desarrollo residencial ubicado en Maravatío, Michoacán, diseñado para ofrecer la mejor combinación de confort, seguridad y plusvalía. El proyecto contempla lotes desde 120m² completamente urbanizados con todos los servicios: agua potable, drenaje, electrificación subterránea, alumbrado público, guarniciones y banquetas. Adicionalmente, el fraccionamiento contará con áreas verdes, parque recreativo, acceso controlado y muro perimetral.',
                    'video_url' => '',
                    'status' => 'en_construccion',
                    'fecha_inicio' => '2025-03-01',
                    'fecha_fin' => '2027-06-30',
                    'lat' => 19.8929,
                    'lng' => -100.4441
                ],
                [
                    'nombre' => 'San Fernando Residencial',
                    'ubicacion' => 'Uruapan, Michoacán',
                    'descripcion' => 'Un concepto residencial de primer nivel en una de las ciudades más prósperas de Michoacán. San Fernando Residencial combina diseño contemporáneo con la belleza natural de la región, ofreciendo lotes residenciales desde 200m².',
                    'descripcion_larga' => 'San Fernando Residencial representa el concepto más innovador de vivienda en Uruapan. Con lotes desde 200m², este desarrollo ofrece una propuesta arquitectónica contemporánea que respeta e integra la belleza natural de la región. El proyecto incluye vialidades amplias con camellones arbolados, red de agua potable, drenaje sanitario y pluvial, electrificación subterránea, y un diseño urbano que prioriza al peatón. Contará con casa club, alberca, gimnasio y áreas deportivas.',
                    'video_url' => '',
                    'status' => 'en_planeacion',
                    'fecha_inicio' => '2025-09-01',
                    'fecha_fin' => '2028-03-31',
                    'lat' => 19.4108,
                    'lng' => -102.0533
                ],
                [
                    'nombre' => 'San Carlos Residencial',
                    'ubicacion' => 'Atlacomulco, Estado de México',
                    'descripcion' => 'Ubicado estratégicamente en Atlacomulco, San Carlos Residencial ofrece la combinación perfecta entre conectividad urbana y vida residencial. Un desarrollo planeado con los más altos estándares de construcción y diseño urbano.',
                    'descripcion_larga' => 'San Carlos Residencial se ubica en una de las zonas de mayor crecimiento del Estado de México. Este desarrollo está diseñado bajo los más altos estándares de calidad, con lotes de 160m² en adelante. Su ubicación estratégica ofrece excelente conectividad con Toluca, Querétaro y la CDMX. El proyecto incluye urbanización completa de primer nivel: pavimento hidráulico, electrificación y alumbrado LED, red hidrosanitaria, áreas verdes con sistema de riego automatizado, y una plaza central con locales comerciales.',
                    'video_url' => '',
                    'status' => 'en_planeacion',
                    'fecha_inicio' => '2024-06-01',
                    'fecha_fin' => '2026-12-31',
                    'lat' => 19.7991,
                    'lng' => -99.8744
                ]
            ];

            $stmt = $this->pdo->prepare("
                INSERT INTO proyectos (nombre, ubicacion, descripcion, descripcion_larga, video_url, status, fecha_inicio, fecha_fin, lat, lng)
                VALUES (:nombre, :ubicacion, :descripcion, :descripcion_larga, :video_url, :status, :fecha_inicio, :fecha_fin, :lat, :lng)
            ");

            foreach ($proyectos as $p) {
                $stmt->execute($p);
            }

            // ─── DATOS SEMILLA DE FASES (ETAPAS GANTT) ──────────────
            // San Carlos (id=3): 7 fases — el proyecto más avanzado con datos detallados
            // San Isidro (id=1):  6 fases — en construcción activa
            // San Fernando (id=2): 2 fases — en etapa temprana de planeación
            $fases = [
                ['proyecto_id' => 3, 'nombre' => 'Estudios de factibilidad', 'fecha_inicio' => '2024-06-01', 'fecha_fin' => '2024-07-15', 'progreso' => 100, 'color' => '#22c55e', 'orden' => 1],
                ['proyecto_id' => 3, 'nombre' => 'Trámites y permisos', 'fecha_inicio' => '2024-07-01', 'fecha_fin' => '2024-09-30', 'progreso' => 100, 'color' => '#22c55e', 'orden' => 2],
                ['proyecto_id' => 3, 'nombre' => 'Movimiento de tierras', 'fecha_inicio' => '2024-10-01', 'fecha_fin' => '2025-01-31', 'progreso' => 100, 'color' => '#22c55e', 'orden' => 3],
                ['proyecto_id' => 3, 'nombre' => 'Urbanización (redes)', 'fecha_inicio' => '2025-02-01', 'fecha_fin' => '2025-08-31', 'progreso' => 85, 'color' => '#3b82f6', 'orden' => 4],
                ['proyecto_id' => 3, 'nombre' => 'Construcción de vialidades', 'fecha_inicio' => '2025-06-01', 'fecha_fin' => '2025-12-31', 'progreso' => 60, 'color' => '#3b82f6', 'orden' => 5],
                ['proyecto_id' => 3, 'nombre' => 'Áreas verdes y amenidades', 'fecha_inicio' => '2025-10-01', 'fecha_fin' => '2026-04-30', 'progreso' => 30, 'color' => '#f59e0b', 'orden' => 6],
                ['proyecto_id' => 3, 'nombre' => 'Entrega de lotes', 'fecha_inicio' => '2026-05-01', 'fecha_fin' => '2026-12-31', 'progreso' => 0, 'color' => '#ef4444', 'orden' => 7],

                ['proyecto_id' => 1, 'nombre' => 'Estudios preliminares', 'fecha_inicio' => '2025-03-01', 'fecha_fin' => '2025-05-15', 'progreso' => 100, 'color' => '#22c55e', 'orden' => 1],
                ['proyecto_id' => 1, 'nombre' => 'Trámites municipales', 'fecha_inicio' => '2025-05-01', 'fecha_fin' => '2025-08-31', 'progreso' => 100, 'color' => '#22c55e', 'orden' => 2],
                ['proyecto_id' => 1, 'nombre' => 'Terracería y nivelación', 'fecha_inicio' => '2025-09-01', 'fecha_fin' => '2026-01-31', 'progreso' => 70, 'color' => '#3b82f6', 'orden' => 3],
                ['proyecto_id' => 1, 'nombre' => 'Redes de agua y drenaje', 'fecha_inicio' => '2026-02-01', 'fecha_fin' => '2026-08-31', 'progreso' => 20, 'color' => '#3b82f6', 'orden' => 4],
                ['proyecto_id' => 1, 'nombre' => 'Vialidades y guarniciones', 'fecha_inicio' => '2026-06-01', 'fecha_fin' => '2027-01-31', 'progreso' => 0, 'color' => '#f59e0b', 'orden' => 5],
                ['proyecto_id' => 1, 'nombre' => 'Entrega final', 'fecha_inicio' => '2027-02-01', 'fecha_fin' => '2027-06-30', 'progreso' => 0, 'color' => '#ef4444', 'orden' => 6],

                ['proyecto_id' => 2, 'nombre' => 'Análisis de mercado', 'fecha_inicio' => '2025-09-01', 'fecha_fin' => '2025-11-30', 'progreso' => 100, 'color' => '#22c55e', 'orden' => 1],
                ['proyecto_id' => 2, 'nombre' => 'Diseño conceptual', 'fecha_inicio' => '2025-12-01', 'fecha_fin' => '2026-04-30', 'progreso' => 65, 'color' => '#3b82f6', 'orden' => 2],
            ];

            $stmt = $this->pdo->prepare("
                INSERT INTO fases (proyecto_id, nombre, fecha_inicio, fecha_fin, progreso, color, orden)
                VALUES (:proyecto_id, :nombre, :fecha_inicio, :fecha_fin, :progreso, :color, :orden)
            ");

            foreach ($fases as $f) {
                $stmt->execute($f);
            }
        }
    }
}
