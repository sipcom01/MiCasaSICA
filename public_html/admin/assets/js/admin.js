/**
 * SICA Admin - Funciones JavaScript del Dashboard
 *
 * Maneja la interacción del modal de proyectos en el dashboard (admin/index.php):
 *
 *   openProjectModal()   — Abre el modal en modo "Nuevo Proyecto" (formulario vacío)
 *   closeProjectModal()  — Cierra el modal
 *   editProject(id)      — Carga datos del proyecto vía GET api/proyectos.php?id=
 *                          y abre el modal en modo edición con los campos rellenos
 *   saveProject(e)       — Envía el formulario vía fetch():
 *                          - POST api/proyectos.php si es nuevo
 *                          - PUT  api/proyectos.php si es edición
 *   deleteProject(id)    — Elimina proyecto con confirmación vía DELETE api/proyectos.php
 *
 * Todas las operaciones recargan la página (location.reload()) al completarse.
 */

// ===== PROYECTOS =====

/**
 * Abre el modal para crear un nuevo proyecto (formulario limpio)
 */
function openProjectModal() {
    document.getElementById('modalTitle').textContent = 'Nuevo Proyecto';
    document.getElementById('projectId').value = '';
    document.getElementById('projectForm').reset();
    document.getElementById('projectStatus').value = 'en_planeacion';
    document.getElementById('projectModal').classList.add('active');
}

/**
 * Cierra el modal de proyecto
 */
function closeProjectModal() {
    document.getElementById('projectModal').classList.remove('active');
}

/**
 * Carga los datos de un proyecto desde la API y abre el modal en modo edición
 */
function editProject(id) {
    fetch(`api/proyectos.php?id=${id}`)
        .then(r => r.json())
        .then(p => {
            document.getElementById('modalTitle').textContent = 'Editar Proyecto';
            document.getElementById('projectId').value = p.id;
            document.getElementById('projectNombre').value = p.nombre;
            document.getElementById('projectUbicacion').value = p.ubicacion;
            document.getElementById('projectDescripcion').value = p.descripcion || '';
            document.getElementById('projectStatus').value = p.status;
            document.getElementById('projectFechaInicio').value = p.fecha_inicio || '';
            document.getElementById('projectFechaFin').value = p.fecha_fin || '';
            document.getElementById('projectImagenUrl').value = p.imagen_url || '';
            // Preview logo
            var prev = document.getElementById('logoPreview');
            if(p.imagen_url){ prev.src = p.imagen_url; prev.style.display = 'block'; }
            else { prev.style.display = 'none'; }
            document.getElementById('projectModal').classList.add('active');
        });
}

/**
 * Envía el formulario de proyecto a la API (POST para crear, PUT para editar)
 */
function saveProject(e) {
    e.preventDefault();
    const form = document.getElementById('projectForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    const isEdit = data.id !== '';

    fetch('api/proyectos.php', {
        method: isEdit ? 'PUT' : 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            location.reload();
        } else {
            alert('Error: ' + (res.error || 'Error desconocido'));
        }
    });
}

/**
 * Elimina un proyecto con confirmación previa (DELETE a la API)
 */
function deleteProject(id) {
    if (!confirm('¿Estás seguro de eliminar este proyecto? También se eliminarán todas sus fases.')) return;
    fetch('api/proyectos.php', {
        method: 'DELETE',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ id: id })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) location.reload();
        else alert('Error al eliminar');
    });
}

/**
 * Sube el logo seleccionado y actualiza la URL y preview
 */
function uploadLogo(){
    var file = document.getElementById('projectLogoFile').files[0];
    if(!file) return;
    var fd = new FormData();
    fd.append('logo', file);
    fetch('api/upload-logo.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(function(data){
            if(data.success){
                document.getElementById('projectImagenUrl').value = data.path;
                var prev = document.getElementById('logoPreview');
                prev.src = data.path;
                prev.style.display = 'block';
            } else {
                alert('Error: ' + (data.error || 'No se pudo subir'));
            }
        });
}
