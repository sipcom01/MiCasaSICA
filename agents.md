# SICA - Agente de Desarrollo y Despliegue

## Conexión al Host

```bash
# SSH
ssh -p 65002 u557645733@193.42.137.76

# SCP (subir archivo)
scp -P 65002 archivo.php u557645733@193.42.137.76:/home/u557645733/domains/micasasica.com/public_html/

# Subir carpeta completa
scp -P 65002 -r public_html/admin/* u557645733@193.42.137.76:/home/u557645733/domains/micasasica.com/public_html/admin/
```

## Rutas del Proyecto

| Entorno | Ruta |
|---------|------|
| **Local** | `/Users/macbook/Documents/GitHub/MiCasaSICA/public_html/` |
| **Host** | `/home/u557645733/domains/micasasica.com/public_html/` |
| **Web** | `https://micasasica.com/` |
| **Admin** | `https://micasasica.com/admin/` |
| **GitHub** | `https://github.com/sipcom01/MiCasaSICA` |

## Base de Datos

```bash
# Consultar DB remota
ssh -p 65002 u557645733@193.42.137.76 'sqlite3 domains/micasasica.com/data/sica.db "QUERY"'

# Migraciones ALTER TABLE
ssh -p 65002 u557645733@193.42.137.76 'sqlite3 domains/micasasica.com/data/sica.db "ALTER TABLE ..."'
```

## Estructura del Proyecto

```
public_html/
├── admin/
│   ├── api/                    # Endpoints REST
│   │   ├── chat-ia.php         # Proxy DeepSeek AI
│   │   ├── analizar-doc.php    # Análisis IA de documentos
│   │   ├── fases.php           # CRUD fases Gantt
│   │   ├── presupuesto.php     # API presupuesto
│   │   ├── proyectos.php       # CRUD proyectos
│   │   ├── servicios.php       # API servicios
│   │   └── upload-logo.php     # Upload logos
│   ├── includes/               # Componentes reutilizables
│   │   ├── sidebar.php         # Sidebar estándar (admin)
│   │   └── sidebar-tareas.php  # Sidebar para Mis Tareas
│   ├── assets/
│   │   ├── css/admin.css       # Estilos del panel
│   │   └── js/admin.js         # JS del panel
│   ├── index.php               # Dashboard proyectos
│   ├── proyecto.php            # Diagrama Gantt
│   ├── presupuesto.php         # Presupuesto por proyecto
│   ├── contenido.php           # Gestión contenido web
│   ├── usuarios.php            # CRUD usuarios
│   ├── mis-tareas.php          # Tareas asignadas + Chat IA
│   ├── login.php               # Login
│   └── logout.php              # Logout
├── assets/
│   ├── css/style.css           # Estilos sitio público
│   ├── video/Clip_1.mp4        # Video hero (6MB)
│   └── img/                    # Logos e imágenes
├── includes/
│   ├── config.php              # Configuración global
│   ├── db.php                  # Conexión SQLite + migraciones
│   └── auth.php                # Autenticación
├── index.php                   # Landing pública
├── proyecto.php                # Detalle proyecto público
├── faq.php                     # FAQ
├── .htaccess                   # Reglas Apache
├── robots.txt                  # SEO
├── sitemap.xml                 # Sitemap
└── llms.txt                    # LLM instructions
```

## Base de Datos - Tablas

| Tabla | Descripción |
|-------|-------------|
| `usuarios` | Cuentas de acceso (id, username, password_hash, nombre, correo, telefono, rol, activo) |
| `proyectos` | Desarrollos inmobiliarios (nombre, ubicacion, descripcion, video_url, imagen_url, status, fechas) |
| `presupuesto_categorias` | Etapas del presupuesto (codigo, nombre, proyecto_id) |
| `presupuesto_partidas` | Tareas individuales (procedimiento, responsable, costo, fechas, progreso, dependencias) |
| `proyecto_archivos` | Planos y diseños (tipo, titulo, descripcion, archivo_url) |
| `proyecto_servicios` | Checklist de servicios (nombre, completado) |
| `fases` | Etapas del diagrama Gantt |
| `usuario_proyectos` | Asignación usuarios↔proyectos (con permiso: ver/editar/editar_gantt/editar_presupuesto) |
| `tarea_historial` | Tracking de acciones en tareas (ajuste_fechas, presupuesto_tercero, documento_subido, completada) |
| `tarea_chat` | Conversaciones con IA por tarea |
| `contactos` | Formulario de contacto público |

## Comandos Útiles

### Desplegar un archivo al host
```bash
scp -P 65002 public_html/admin/archivo.php u557645733@193.42.137.76:/home/u557645733/domains/micasasica.com/public_html/admin/
```

### Desplegar todos los admin
```bash
scp -P 65002 public_html/admin/*.php u557645733@193.42.137.76:/home/u557645733/domains/micasasica.com/public_html/admin/
```

### Actualizar solo el sidebar
```bash
scp -P 65002 public_html/admin/includes/sidebar.php u557645733@193.42.137.76:/home/u557645733/domains/micasasica.com/public_html/admin/includes/
```

### Actualizar CSS (con cache bust)
```bash
# 1. Subir archivo
scp -P 65002 public_html/admin/assets/css/admin.css u557645733@193.42.137.76:/home/u557645733/domains/micasasica.com/public_html/admin/assets/css/
# 2. Cambiar ?v=X en los HTML para burlar CDN
```

### Ver logs del servidor
```bash
ssh -p 65002 u557645733@193.42.137.76 'tail -50 domains/micasasica.com/logs/error.log'
```

### Backup de la BD
```bash
ssh -p 65002 u557645733@193.42.137.76 'cp domains/micasasica.com/data/sica.db domains/micasasica.com/data/sica_backup_$(date +%Y%m%d).db'
```

## Git

```bash
git add -A
git commit -m "Descripción de cambios"
git push origin main
```

## Notas del CDN

El hosting usa un CDN que cachea archivos estáticos (CSS, JS, imágenes) por 30 días.
Para forzar recarga:
- CSS/JS: cambiar `?v=X` en el `<link>` o `<script>`
- Usar CSS inline en `<style>` para cambios críticos

## API Keys

- **DeepSeek AI**: Configurada en `admin/api/chat-ia.php` y `admin/api/analizar-doc.php`
- No exponer en GitHub (ya está en los archivos PHP del servidor)

## Permisos de Usuario

| Rol | Acceso |
|-----|--------|
| `admin` | Todo (todos los proyectos, gestión de usuarios) |
| `director` | Proyectos asignados, gestión de usuarios |
| Otros roles | Solo proyectos asignados con permiso específico |

Niveles de permiso por proyecto: `ver`, `editar`, `editar_gantt`, `editar_presupuesto`
