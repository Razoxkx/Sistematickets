<?php
session_start();
require_once 'includes/config.php';

// Prevenir cacheo
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Verificar sesión
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

// Verificar que sea admin
if ($_SESSION["role"] !== 'admin') {
    header("Location: tickets.php");
    exit();
}

$database_name = obtenerEnv('DB_NAME', 'jupiter');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración del Sistema - Sistemátickets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/dark-mode.css">
    <style>
        .config-card {
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        .config-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2rem;
            border-radius: 8px 8px 0 0;
            color: white;
        }
        .config-body {
            padding: 2rem;
        }
        .backup-section {
            background-color: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 4px solid #dc3545;
            color: #333;
        }
        .backup-section.dark {
            background-color: #1a1f2e;
            border-left-color: #f56565;
            color: #e2e8f0;
        }
        .backup-section.dark h5 {
            color: #f5f7fa;
        }
        .backup-section.dark p {
            color: #cbd5e0;
        }
        .btn-backup {
            background-color: #dc3545;
            border-color: #dc3545;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-backup:hover {
            background-color: #c82333;
            border-color: #bd2130;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }
        .spinner-border {
            display: none;
        }
        .backup-info {
            font-size: 0.9rem;
            margin-top: 1rem;
            padding: 1rem;
            background-color: #e7d4f5;
            border-radius: 4px;
            color: #333;
        }
        .backup-info.dark {
            background-color: #2d1d3d;
            color: #e2c9f0;
        }
        .backup-info.dark strong {
            color: #f5d9ff;
        }
        .backup-info.dark ul {
            color: #d0a6e8;
        }
        .backup-list-item {
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            background-color: #fff;
            transition: all 0.2s ease;
        }
        .backup-list-item:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .backup-list-item.dark {
            background-color: #2d3748;
            border-color: #4a5568;
            color: #e2e8f0;
        }
        .backup-list-item.dark strong {
            color: #f5f7fa;
        }
        .backup-list-item.dark .text-muted {
            color: #a0aec0 !important;
        }
        .backup-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .programacion-container {
            background-color: #e8f4f8;
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 4px solid #0dcaf0;
            color: #333;
        }
        .programacion-container.dark {
            background-color: #1a2835;
            border-left-color: #0dcaf0;
            color: #e2e8f0;
        }
        .programacion-container.dark h5 {
            color: #f5f7fa;
        }
        .programacion-container.dark label {
            color: #cbd5e0;
        }
        .programacion-container.dark .form-label {
            color: #cbd5e0;
        }
        .programacion-container.dark .form-check-label {
            color: #cbd5e0;
        }
        .programacion-container.dark .form-select,
        .programacion-container.dark .form-control,
        .programacion-container.dark input[type="time"] {
            background-color: #2d3748;
            color: #e2e8f0;
            border-color: #4a5568;
        }
        .programacion-container.dark .form-select:focus,
        .programacion-container.dark .form-control:focus,
        .programacion-container.dark input[type="time"]:focus {
            background-color: #2d3748;
            color: #e2e8f0;
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }
        .programacion-container.dark .form-select option {
            background-color: #2d3748;
            color: #e2e8f0;
        }
        .programacion-container.dark .form-select option:checked {
            background-color: #667eea;
            color: #e2e8f0;
        }
        .programacion-container.dark .alert {
            background-color: #2d3748;
            color: #e2e8f0;
            border-color: #4a5568;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="container-fluid py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Encabezado -->
                <div class="mb-4">
                    <h1 class="h2 mb-1">⚙️ Configuración del Sistema</h1>
                    <p class="text-muted">Administración y mantenimiento de la base de datos</p>
                </div>
                
                <!-- Tarjeta de Backup con Tabs -->
                <div class="card config-card">
                    <div class="config-header">
                        <h4 class="mb-0">💾 Gestión de Backups</h4>
                    </div>
                    <div class="config-body">
                        <!-- Tabs -->
                        <ul class="nav nav-tabs mb-4" id="backupTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="tab-descargar" data-bs-toggle="tab" data-bs-target="#content-descargar" type="button" role="tab">
                                    📥 Descargar
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tab-listar" data-bs-toggle="tab" data-bs-target="#content-listar" type="button" role="tab">
                                    📋 Listar
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tab-restaurar" data-bs-toggle="tab" data-bs-target="#content-restaurar" type="button" role="tab">
                                    ↩️ Restaurar
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tab-programar" data-bs-toggle="tab" data-bs-target="#content-programar" type="button" role="tab">
                                    ⏰ Programar
                                </button>
                            </li>
                        </ul>
                        
                        <!-- Tab Content -->
                        <div class="tab-content" id="backupTabsContent">
                            <!-- TAB 1: DESCARGAR -->
                            <div class="tab-pane fade show active" id="content-descargar" role="tabpanel">
                                <div class="backup-section" id="backup-section">
                                    <h5 class="mb-3">Descargar Backup de la Base de Datos</h5>
                                    <p class="mb-3 text-muted">
                                        Descarga una copia completa de la base de datos <strong><?php echo htmlspecialchars($database_name); ?></strong> 
                                        en formato SQL para guardar localmente.
                                    </p>
                                    
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <button 
                                                class="btn btn-backup btn-lg w-100" 
                                                id="btnDescargarBackup"
                                                type="button"
                                            >
                                                <span id="btnText">📥 Descargar Ahora</span>
                                                <span class="spinner-border spinner-border-sm ms-2" id="spinner" role="status" aria-hidden="true"></span>
                                            </button>
                                        </div>
                                        <div class="col-md-6">
                                            <small class="text-muted d-block mt-2 mt-md-0">
                                                <strong>Última descarga:</strong> <span id="lastBackup">Sin información</span>
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <div class="backup-info" id="backup-info">
                                        <strong>ℹ️ Información:</strong>
                                        <ul class="mb-0 mt-2 ps-3">
                                            <li>El archivo incluye toda la estructura e datos de la BD</li>
                                            <li>Formato: SQL compatible con MySQL/MariaDB</li>
                                            <li>Incluye: Tablas, índices, claves foráneas y datos</li>
                                            <li>Se descarga directamente a tu computadora</li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <!-- Alerta de éxito -->
                                <div class="alert alert-success d-none mt-3" id="successAlert" role="alert">
                                    ✅ Backup descargado exitosamente
                                </div>
                                
                                <!-- Alerta de error -->
                                <div class="alert alert-danger d-none mt-3" id="errorAlert" role="alert">
                                    <strong>❌ Error:</strong> <span id="errorMessage"></span>
                                </div>
                            </div>
                            
                            <!-- TAB 2: LISTAR -->
                            <div class="tab-pane fade" id="content-listar" role="tabpanel">
                                <h5 class="mb-3">Backups Disponibles</h5>
                                <div id="backupsList" class="mb-3">
                                    <div class="text-center py-4">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Cargando...</span>
                                        </div>
                                    </div>
                                </div>
                                <button class="btn btn-outline-primary w-100" onclick="cargarBackups()">🔄 Actualizar</button>
                            </div>
                            
                            <!-- TAB 3: RESTAURAR -->
                            <div class="tab-pane fade" id="content-restaurar" role="tabpanel">
                                <h5 class="mb-3">Restaurar Backup</h5>
                                <div class="alert alert-warning">
                                    <strong>⚠️ Advertencia:</strong> Restaurar un backup sobrescribirá todos los datos actuales de la base de datos. Esta acción no se puede deshacer.
                                </div>
                                <div id="backupsRestaurar" class="mb-3">
                                    <div class="text-center py-4">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Cargando...</span>
                                        </div>
                                    </div>
                                </div>
                                <div id="restaurarMsg" class="alert d-none" role="alert"></div>
                            </div>
                            
                            <!-- TAB 4: PROGRAMAR -->
                            <div class="tab-pane fade" id="content-programar" role="tabpanel">
                                <h5 class="mb-3">Programar Backups Automáticos</h5>
                                <div class="programacion-container" id="programacion-container">
                                    <form id="formProgramacion">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="frecuencia" class="form-label">Frecuencia</label>
                                                <select class="form-select" id="frecuencia" name="frecuencia" required>
                                                    <option value="manual">Manual (solo al hacer clic)</option>
                                                    <option value="diario">Diario</option>
                                                    <option value="semanal">Semanal</option>
                                                    <option value="mensual">Mensual</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="horario" class="form-label">Horario</label>
                                                <input type="time" class="form-control" id="horario" name="hora_backup" value="02:00">
                                            </div>
                                        </div>
                                        
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="activarBackup" name="activo" value="1">
                                            <label class="form-check-label" for="activarBackup">
                                                Activar backups automáticos
                                            </label>
                                        </div>
                                        
                                        <div class="d-grid gap-2 d-md-flex">
                                            <button type="submit" class="btn btn-primary">💾 Guardar Configuración</button>
                                            <button type="button" class="btn btn-success" onclick="ejecutarBackupAhora()">⚡ Ejecutar Ahora</button>
                                        </div>
                                    </form>
                                    
                                    <div id="programacionMsg" class="alert d-none mt-3" role="alert"></div>
                                    <div id="estadoBackup" class="alert alert-info mt-3">
                                        <strong>Estado:</strong> <span id="estadoTexto">Cargando...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Información del Sistema -->
                <div class="card config-card">
                    <div class="config-header">
                        <h4 class="mb-0">ℹ️ Información del Sistema</h4>
                    </div>
                    <div class="config-body">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold">Base de Datos:</td>
                                <td><?php echo htmlspecialchars($database_name); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Versión de PHP:</td>
                                <td><?php echo phpversion(); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Versión de MySQL:</td>
                                <td id="mysqlVersion">Cargando...</td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Usuario Actual:</td>
                                <td><?php echo htmlspecialchars($_SESSION['username']); ?> (<?php echo ucfirst($_SESSION['role']); ?>)</td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Fecha del Sistema:</td>
                                <td><?php echo date('d/m/Y H:i:s'); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Aplicar dark mode si está activado
        function inicializarDarkMode() {
            const darkModeEnabled = localStorage.getItem('darkMode') === 'true';
            if (darkModeEnabled) {
                document.body.classList.add('dark-mode');
                applyDarkModeToBackup();
            }
        }
        
        // Inicializar en cuanto sea posible
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', inicializarDarkMode);
        } else {
            inicializarDarkMode();
        }
        
        // Event listener para cambios de dark mode
        document.addEventListener('darkModeToggled', function() {
            applyDarkModeToBackup();
        });
        
        function applyDarkModeToBackup() {
            const isDarkMode = document.body.classList.contains('dark-mode');
            const backupSection = document.getElementById('backup-section');
            const backupInfo = document.getElementById('backup-info');
            const programacion = document.getElementById('programacion-container');
            const backupsList = document.getElementById('backupsList');
            const backupsRestaurar = document.getElementById('backupsRestaurar');
            
            if (backupSection) {
                if (isDarkMode) backupSection.classList.add('dark');
                else backupSection.classList.remove('dark');
            }
            
            if (backupInfo) {
                if (isDarkMode) backupInfo.classList.add('dark');
                else backupInfo.classList.remove('dark');
            }
            
            if (programacion) {
                if (isDarkMode) programacion.classList.add('dark');
                else programacion.classList.remove('dark');
            }
            
            if (backupsList) {
                const items = backupsList.querySelectorAll('.backup-list-item');
                items.forEach(item => {
                    if (isDarkMode) item.classList.add('dark');
                    else item.classList.remove('dark');
                });
            }
            
            if (backupsRestaurar) {
                const items = backupsRestaurar.querySelectorAll('.backup-list-item');
                items.forEach(item => {
                    if (isDarkMode) item.classList.add('dark');
                    else item.classList.remove('dark');
                });
            }
        }
        
        // Función para descargar backup
        document.getElementById('btnDescargarBackup')?.addEventListener('click', async function() {
            const btn = this;
            const spinner = document.getElementById('spinner');
            const btnText = document.getElementById('btnText');
            const successAlert = document.getElementById('successAlert');
            const errorAlert = document.getElementById('errorAlert');
            
            btn.disabled = true;
            spinner.style.display = 'inline-block';
            btnText.textContent = 'Generando backup...';
            successAlert.classList.add('d-none');
            errorAlert.classList.add('d-none');
            
            try {
                const formData = new FormData();
                formData.append('accion', 'descargar');
                
                const response = await fetch('backup_database.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    const error = await response.json().catch(() => ({}));
                    throw new Error(error.error || 'Error desconocido');
                }
                
                // Descargar el archivo
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `<?php echo $database_name; ?>_${new Date().toISOString().slice(0,10)}.sql`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                a.remove();
                
                // Mostrar éxito
                successAlert.classList.remove('d-none');
                btnText.textContent = '✅ Backup descargado';
                document.getElementById('lastBackup').textContent = new Date().toLocaleString('es-ES');
                
            } catch (error) {
                console.error('Error:', error);
                errorAlert.classList.remove('d-none');
                document.getElementById('errorMessage').textContent = error.message;
            } finally {
                btn.disabled = false;
                spinner.style.display = 'none';
                setTimeout(() => {
                    btnText.textContent = '📥 Descargar Ahora';
                }, 2000);
            }
        });
        
        // Cargar backups cuando se hace clic en el tab
        document.getElementById('tab-listar')?.addEventListener('click', cargarBackups);
        document.getElementById('tab-restaurar')?.addEventListener('click', cargarBackupsRestaurar);
        document.getElementById('tab-programar')?.addEventListener('click', cargarConfigProgramacion);
        
        async function cargarBackups() {
            const container = document.getElementById('backupsList');
            container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
            
            try {
                const response = await fetch('api_backups.php?accion=listar');
                if (!response.ok) throw new Error('Error al cargar backups');
                
                const data = await response.json();
                let html = '';
                
                if (data.backups_sistema.length === 0) {
                    html = '<div class="alert alert-info">No hay backups disponibles aún.</div>';
                } else {
                    data.backups_sistema.forEach(backup => {
                        const fecha = new Date(backup.fecha * 1000);
                        const tamanoBD = formatearTamano(backup.tamano);
                        
                        html += `
                            <div class="backup-list-item" id="backup-${backup.nombre}">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <strong>${backup.nombre}</strong><br>
                                        <small class="text-muted">
                                            📅 ${fecha.toLocaleString('es-ES')} | 💾 ${tamanoBD}
                                        </small>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="backup-actions">
                                            <button class="btn btn-sm btn-primary" onclick="descargarBackup('${backup.nombre}')">
                                                📥 Descargar
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="eliminarBackup('${backup.nombre}')">
                                                🗑️ Eliminar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                }
                
                container.innerHTML = html;
                applyDarkModeToBackup(); // Aplicar dark mode a elementos recién cargados
            } catch (error) {
                container.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
            }
        }
        
        async function cargarBackupsRestaurar() {
            const container = document.getElementById('backupsRestaurar');
            container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
            
            try {
                const response = await fetch('api_backups.php?accion=listar');
                if (!response.ok) throw new Error('Error al cargar backups');
                
                const data = await response.json();
                let html = '';
                
                if (data.backups_sistema.length === 0) {
                    html = '<div class="alert alert-info">No hay backups disponibles para restaurar.</div>';
                } else {
                    data.backups_sistema.forEach(backup => {
                        const fecha = new Date(backup.fecha * 1000);
                        const tamanoBD = formatearTamano(backup.tamano);
                        
                        html += `
                            <div class="backup-list-item">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <strong>${backup.nombre}</strong><br>
                                        <small class="text-muted">
                                            📅 ${fecha.toLocaleString('es-ES')} | 💾 ${tamanoBD}
                                        </small>
                                    </div>
                                    <div class="col-md-4">
                                        <button class="btn btn-sm btn-warning w-100" onclick="confirmarRestaurar('${backup.nombre}')">
                                            ↩️ Restaurar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                }
                
                container.innerHTML = html;
                applyDarkModeToBackup(); // Aplicar dark mode a elementos recién cargados
            } catch (error) {
                container.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
            }
        }
        
        async function cargarConfigProgramacion() {
            try {
                const response = await fetch('api_backup_programado.php?accion=obtener');
                if (!response.ok) throw new Error('Error al cargar configuración');
                
                const data = await response.json();
                const config = data.config;
                
                document.getElementById('frecuencia').value = config.frecuencia || 'manual';
                document.getElementById('horario').value = config.hora_backup || '02:00';
                document.getElementById('activarBackup').checked = config.activo === 1;
                
                let estadoTxt = 'Inactivo';
                if (config.activo === 1) {
                    estadoTxt = `Activo (${config.frecuencia}) - Próxima: ${config.proxima_programacion || 'No programada'}`;
                }
                if (config.ultimo_backup) {
                    estadoTxt += ` | Último: ${new Date(config.ultimo_backup).toLocaleString('es-ES')}`;
                }
                
                document.getElementById('estadoTexto').textContent = estadoTxt;
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('estadoTexto').textContent = 'Error al cargar configuración';
            }
        }
        
        function descargarBackup(archivo) {
            const link = document.createElement('a');
            link.href = `api_backups.php?accion=descargar&archivo=${encodeURIComponent(archivo)}`;
            link.download = archivo;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        async function eliminarBackup(archivo) {
            if (!confirm(`¿Estás seguro de que deseas eliminar ${archivo}?`)) return;
            
            try {
                const formData = new FormData();
                formData.append('accion', 'eliminar');
                formData.append('archivo', archivo);
                
                const response = await fetch('api_backups.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) throw new Error('Error al eliminar');
                
                const data = await response.json();
                alert(data.mensaje);
                cargarBackups();
            } catch (error) {
                alert(`Error: ${error.message}`);
            }
        }
        
        function confirmarRestaurar(archivo) {
            if (!confirm(`⚠️ ADVERTENCIA: Esto sobrescribirá TODOS los datos actuales.\\n\\n¿Deseas restaurar ${archivo}?`)) return;
            
            restaurarBackup(archivo);
        }
        
        async function restaurarBackup(archivo) {
            const btn = event?.target;
            if (btn) btn.disabled = true;
            
            try {
                const formData = new FormData();
                formData.append('accion', 'restaurar');
                formData.append('archivo', archivo);
                
                const response = await fetch('api_backups.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) throw new Error('Error al restaurar');
                
                const data = await response.json();
                const msg = document.getElementById('restaurarMsg');
                msg.classList.remove('d-none', 'alert-danger');
                msg.classList.add('alert-success');
                msg.innerHTML = `<strong>✅ Éxito:</strong> ${data.mensaje}`;
                
                setTimeout(() => location.reload(), 2000);
            } catch (error) {
                const msg = document.getElementById('restaurarMsg');
                msg.classList.remove('d-none', 'alert-success');
                msg.classList.add('alert-danger');
                msg.innerHTML = `<strong>❌ Error:</strong> ${error.message}`;
            } finally {
                if (btn) btn.disabled = false;
            }
        }
        
        async function ejecutarBackupAhora() {
            if (!confirm('¿Ejecutar backup inmediatamente?')) return;
            
            try {
                const formData = new FormData();
                formData.append('accion', 'ejecutar_ahora');
                
                const response = await fetch('api_backup_programado.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) throw new Error('Error al ejecutar');
                
                const data = await response.json();
                const msg = document.getElementById('programacionMsg');
                msg.classList.remove('d-none', 'alert-danger');
                msg.classList.add('alert-success');
                msg.innerHTML = `<strong>✅ Éxito:</strong> ${data.mensaje}<br><em>${data.archivo}</em>`;
            } catch (error) {
                const msg = document.getElementById('programacionMsg');
                msg.classList.remove('d-none', 'alert-success');
                msg.classList.add('alert-danger');
                msg.innerHTML = `<strong>❌ Error:</strong> ${error.message}`;
            }
        }
        
        // Manejar form de programación
        document.getElementById('formProgramacion')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const frecuencia = document.getElementById('frecuencia').value;
            const hora_backup = document.getElementById('horario').value;
            const activo = document.getElementById('activarBackup').checked ? 1 : 0;
            
            try {
                const formData = new FormData();
                formData.append('accion', 'actualizar');
                formData.append('frecuencia', frecuencia);
                formData.append('hora_backup', hora_backup);
                formData.append('activo', activo);
                
                const response = await fetch('api_backup_programado.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) throw new Error('Error al guardar');
                
                const data = await response.json();
                const msg = document.getElementById('programacionMsg');
                msg.classList.remove('d-none', 'alert-danger');
                msg.classList.add('alert-success');
                msg.innerHTML = `<strong>✅ Éxito:</strong> ${data.mensaje}`;
                
                setTimeout(() => cargarConfigProgramacion(), 1000);
            } catch (error) {
                const msg = document.getElementById('programacionMsg');
                msg.classList.remove('d-none', 'alert-success');
                msg.classList.add('alert-danger');
                msg.innerHTML = `<strong>❌ Error:</strong> ${error.message}`;
            }
        });
        
        function formatearTamano(bytes) {
            const unidades = ['B', 'KB', 'MB', 'GB'];
            let size = bytes;
            let unitIndex = 0;
            
            while (size >= 1024 && unitIndex < unidades.length - 1) {
                size /= 1024;
                unitIndex++;
            }
            
            return size.toFixed(2) + ' ' + unidades[unitIndex];
        }
        
        // Obtener versión de MySQL
        document.addEventListener('DOMContentLoaded', async function() {
            try {
                const response = await fetch('api_debug_ping.php');
                if (response.ok) {
                    const data = await response.json();
                    document.getElementById('mysqlVersion').textContent = data.mysql_version || 'No disponible';
                }
            } catch (e) {
                document.getElementById('mysqlVersion').textContent = 'No disponible';
            }
        });
    </script>
</body>
</html>
