<?php
session_start();
require_once 'includes/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

// Verificar si es admin o soporte TI
$permisos = ['admin', 'tisupport'];
if (!in_array($_SESSION["role"] ?? "viewer", $permisos)) {
    header("Location: tickets.php");
    exit();
}

$error = "";
$success = "";
$dispositivos = [];

// CREAR DISPOSITIVO
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["accion_crear"])) {
    $ip = trim($_POST["ip"] ?? "");
    $nombre = trim($_POST["nombre"] ?? "");
    $descripcion = trim($_POST["descripcion"] ?? "");
    
    if (empty($ip) || empty($nombre)) {
        $error = "La IP y el nombre son obligatorios";
    } else {
        try {
            $stmt = $conexion->prepare("
                INSERT INTO dispositivos_monitoreo (ip, nombre, descripcion, usuario_creador)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$ip, $nombre, $descripcion, $_SESSION["user_id"]]);
            
            // Ir a verificar el estado
            header("Location: monitoreo.php?success=creado");
            exit();
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                $error = "La IP $ip ya está siendo monitoreada";
            } else {
                $error = "Error al crear dispositivo: " . $e->getMessage();
            }
        }
    }
}

// ELIMINAR DISPOSITIVO
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["accion_eliminar"])) {
    $dispositivo_id = $_POST["dispositivo_id"] ?? "";
    
    if (!empty($dispositivo_id)) {
        try {
            $stmt = $conexion->prepare("DELETE FROM dispositivos_monitoreo WHERE id = ?");
            $stmt->execute([$dispositivo_id]);
            
            header("Location: monitoreo.php?success=eliminado");
            exit();
        } catch (PDOException $e) {
            $error = "Error al eliminar dispositivo: " . $e->getMessage();
        }
    }
}

// OBTENER DISPOSITIVOS
try {
    $stmt = $conexion->prepare("
        SELECT d.*, u.username as usuario_nombre
        FROM dispositivos_monitoreo d
        JOIN users u ON d.usuario_creador = u.id
        WHERE d.activo = 1
        ORDER BY d.fecha_creacion DESC
    ");
    $stmt->execute();
    $dispositivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al obtener dispositivos: " . $e->getMessage();
}

// Detectar modo fullscreen
$fullscreen = isset($_GET['fullscreen']) && $_GET['fullscreen'] === '1';


// Capturar mensaje de éxito
if (isset($_GET["success"])) {
    if ($_GET["success"] === "creado") {
        $success = "✅ Dispositivo agregado exitosamente";
    } elseif ($_GET["success"] === "editado") {
        $success = "✅ Dispositivo actualizado exitosamente";
    } elseif ($_GET["success"] === "eliminado") {
        $success = "✅ Dispositivo eliminado exitosamente";
    }
}
?>

<!DOCTYPE html>
<html lang="es" id="htmlRoot">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="css/dark-mode.css" rel="stylesheet">
    <title>Monitoreo de Dispositivos</title>
    <style>
        body {
            background: linear-gradient(to bottom, #f8f9fa, #ffffff);
        }
        
        [data-bs-theme="dark"] body {
            background: linear-gradient(to bottom, #1a1a1a, #0d0d0d);
        }

        .container {
            margin-top: 20px;
            padding-left: 20px;
            padding-right: 20px;
        }

        h1, h2, h3 {
            color: #8b9dff;
            font-weight: 700;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        h1 {
            font-size: 2rem;
        }

        .dispositivo-card {
            border-radius: 12px;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .dispositivo-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        [data-bs-theme="dark"] .dispositivo-card {
            background: #1e1e1e;
            border-color: #444;
        }

        .estado-badge {
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .estado-online {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .estado-offline {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .estado-desconocido {
            background-color: #e2e3e5;
            color: #383d41;
            border: 1px solid #d6d8db;
        }

        [data-bs-theme="dark"] .estado-online {
            background-color: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }

        [data-bs-theme="dark"] .estado-offline {
            background-color: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }

        [data-bs-theme="dark"] .estado-desconocido {
            background-color: rgba(108, 117, 125, 0.2);
            color: #adb5bd;
        }

        .pulse-online {
            animation: pulse-online 2s infinite;
        }

        @keyframes pulse-online {
            0% {
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(40, 167, 69, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0);
            }
        }

        .ip-display {
            font-family: 'Courier New', monospace;
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        .botones-acciones {
            display: flex;
            gap: 8px;
            margin-top: 15px;
        }

        .botones-acciones .btn {
            flex: 1;
        }

        [data-bs-theme="dark"] .form-control,
        [data-bs-theme="dark"] .form-select {
            background-color: #2a2a2a;
            border-color: #444;
            color: #e0e0e0;
        }

        [data-bs-theme="dark"] .form-control:focus,
        [data-bs-theme="dark"] .form-select:focus {
            background-color: #2a2a2a;
            border-color: #8b9dff;
            color: #e0e0e0;
        }

        .card-header {
            border-bottom: 1px solid #e9ecef;
            padding: 15px;
        }

        [data-bs-theme="dark"] .card-header {
            border-bottom-color: #444;
            background-color: #2a2a2a;
        }

        .spinner-border-custom {
            width: 1.5rem;
            height: 1.5rem;
        }

        .row {
            margin-right: 0 !important;
            margin-left: 0 !important;
        }

        .col-lg-4 {
            padding-right: 10px;
            padding-left: 0;
        }

        .col-md-6 {
            padding-right: 10px;
            padding-left: 0;
        }

        @media (max-width: 768px) {
            .col-lg-4, .col-md-6 {
                width: 100%;
                padding-right: 5px;
            }
        }

        /* Modo Fullscreen */
        body.fullscreen-mode {
            background: #000;
            margin: 0;
            padding: 0;
        }

        body.fullscreen-mode .sidebar {
            display: none;
        }

        body.fullscreen-mode .container {
            max-width: 100%;
            margin: 0;
            padding: 30px;
        }

        body.fullscreen-mode .row {
            margin-bottom: 0;
        }

        body.fullscreen-mode h1,
        body.fullscreen-mode .alert,
        body.fullscreen-mode button.btn-primary {
            display: none;
        }

        body.fullscreen-mode #containerDispositivos {
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 25px;
        }

        body.fullscreen-mode .dispositivo-card {
            box-shadow: 0 8px 25px rgba(0,0,0,0.5);
        }

        body.fullscreen-mode .card-body {
            padding: 25px !important;
        }

        .fullscreen-exit-button {
            display: none;
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 0.9rem;
            cursor: pointer;
            backdrop-filter: blur(10px);
        }

        body.fullscreen-mode .fullscreen-exit-button {
            display: block;
        }

        body.fullscreen-mode .fullscreen-exit-button:hover {
            background: rgba(255, 255, 255, 0.3);
        }
    </style>
    <script>
        (function() {
            const darkMode = localStorage.getItem('darkMode');
            if (darkMode === 'enabled') {
                document.documentElement.setAttribute('data-bs-theme', 'dark');
            } else {
                document.documentElement.removeAttribute('data-bs-theme');
            }
        })();
    </script>
</head>
<body 

<?php echo $fullscreen ? 'class="fullscreen-mode"' : ''; ?>>
    <?php if (!$fullscreen): ?>
    <?php include 'includes/sidebar.php'; ?>
    <?php endif; ?>
    
    <button class="fullscreen-exit-button" onclick="salirFullscreen()">ESC para salir</button>
    
    <div class="container mt-5">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h1><i class="bi bi-wifi"></i> Monitoreo de Dispositivos</h1>
                    <button class="btn btn-secondary btn-sm" onclick="abrirFullscreen()" title="Pantalla completa para exposición">
                        <i class="bi bi-fullscreen"></i> Pantalla Completa
                    </button>
                </div>
            </div>
        </div>

        <!-- Botón Agregar -->
        <div class="row mb-4">
            <div class="col-12">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAgregar">
                    <i class="bi bi-plus-circle"></i> Agregar Dispositivo
                </button>
            </div>
        </div>

        <!-- Alertas -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Estadísticas Dashboard -->
        <?php if (!empty($dispositivos)): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 25px;">
            <?php
            $total = count($dispositivos);
            $online_count = count(array_filter($dispositivos, fn($d) => $d['estado'] === 'online'));
            $offline_count = $total - $online_count;
            ?>
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <div style="font-size: 2rem; font-weight: 800; margin-bottom: 5px;"><?php echo $total; ?></div>
                <div style="font-size: 0.9rem; opacity: 0.9;">TOTAL</div>
            </div>
            <div style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <div style="font-size: 2rem; font-weight: 800; margin-bottom: 5px;"><?php echo $online_count; ?></div>
                <div style="font-size: 0.9rem; opacity: 0.9;">ONLINE</div>
            </div>
            <div style="background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%); color: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <div style="font-size: 2rem; font-weight: 800; margin-bottom: 5px;"><?php echo $offline_count; ?></div>
                <div style="font-size: 0.9rem; opacity: 0.9;">OFFLINE</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Contenido -->
        <?php if (empty($dispositivos)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> No hay dispositivos siendo monitoreados.
                <button class="btn btn-sm btn-info ms-2" data-bs-toggle="modal" data-bs-target="#modalAgregar">
                    Agregar el primero
                </button>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px;" id="containerDispositivos">
                <?php foreach ($dispositivos as $dispositivo): ?>
                    <div class="card dispositivo-card h-100" id="device-<?php echo $dispositivo["id"]; ?>" style="border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border-radius: 12px; cursor: pointer;" onclick="abrirEditarDispositivo(<?php echo htmlspecialchars(json_encode($dispositivo), ENT_QUOTES, 'UTF-8'); ?>)">
                        <!-- Body -->
                        <div class="card-body" style="padding: 18px;">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                                <div style="flex: 1;">
                                    <h5 class="card-title mb-1" style="font-size: 1rem; font-weight: 700;"><?php echo htmlspecialchars($dispositivo["nombre"]); ?></h5>
                                    <small class="text-muted" style="font-size: 0.8rem;"><?php echo htmlspecialchars($dispositivo["ip"]); ?></small>
                                </div>
                                <div id="status-<?php echo $dispositivo["id"]; ?>" data-estado="<?php echo $estado; ?>" style="min-width: 80px; text-align: right;">
                                    <?php
                                    $estado = $dispositivo["estado"];
                                    if ($estado === "online") {
                                        echo '<span style="display: inline-flex; align-items: center; gap: 4px; background: rgba(40, 167, 69, 0.15); color: #28a745; padding: 4px 8px; border-radius: 6px; font-size: 0.8rem; font-weight: 600;"><span style="width: 6px; height: 6px; background: #28a745; border-radius: 50%; animation: pulse 2s infinite;"></span>Online</span>';
                                    } elseif ($estado === "offline") {
                                        echo '<span style="display: inline-flex; align-items: center; gap: 4px; background: rgba(220, 53, 69, 0.15); color: #dc3545; padding: 4px 8px; border-radius: 6px; font-size: 0.8rem; font-weight: 600;"><span style="width: 6px; height: 6px; background: #dc3545; border-radius: 50%;"></span>Offline</span>';
                                    } else {
                                        echo '<span style="display: inline-flex; align-items: center; gap: 4px; background: rgba(108, 117, 125, 0.15); color: #666; padding: 4px 8px; border-radius: 6px; font-size: 0.8rem; font-weight: 600;"><span class="spinner-border spinner-border-sm" style="width: 6px; height: 6px;"></span>...</span>';
                                    }
                                    ?>
                                </div>
                            </div>

                            <!-- Métricas -->
                            <div style="border-top: 1px solid #e9ecef; padding-top: 12px; margin-bottom: 12px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <span style="font-size: 0.85rem; color: #666;"><i class="bi bi-hourglass-split"></i> Latencia</span>
                                    <span style="font-size: 0.85rem; font-weight: 600; color: #667eea; font-family: 'Courier New', monospace;" id="latencia-<?php echo $dispositivo["id"]; ?>">N/A</span>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="font-size: 0.85rem; color: #666;"><i class="bi bi-clock"></i> Verificado</span>
                                    <span style="font-size: 0.85rem; font-weight: 600; color: #666;" id="fecha-<?php echo $dispositivo["id"]; ?>">
                                        <?php 
                                        if ($dispositivo["fecha_ultima_verificacion"]) {
                                            $fecha = new DateTime($dispositivo["fecha_ultima_verificacion"]);
                                            echo $fecha->format('d/m/Y H:i');
                                        } else {
                                            echo "Nunca";
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Botones de acción -->
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="btn btn-sm" style="flex: 1; background: rgba(102, 126, 234, 0.15); color: #667eea; border: none; font-size: 0.8rem; font-weight: 600; padding: 6px;" onclick="event.stopPropagation(); verificarAhora(<?php echo $dispositivo['id']; ?>)">
                                    <i class="bi bi-arrow-clockwise"></i> Verificar
                                </button>
                                <button type="button" class="btn btn-sm" style="flex: 1; background: rgba(220, 53, 69, 0.15); color: #dc3545; border: none; font-size: 0.8rem; font-weight: 600; padding: 6px;" onclick="event.stopPropagation(); confirmarEliminar(<?php echo $dispositivo['id']; ?>)">
                                    <i class="bi bi-trash"></i> Eliminar
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Agregar Dispositivo -->
    <div class="modal fade" id="modalAgregar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle"></i> Agregar Dispositivo
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="ip" class="form-label">Dirección IP *</label>
                            <input type="text" class="form-control" id="ip" name="ip" placeholder="192.168.1.1" required>
                            <small class="text-muted">Ejemplo: 192.168.1.100</small>
                        </div>
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre del Dispositivo *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" placeholder="Router Principal" required>
                        </div>
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción (Opcional)</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3" placeholder="Gateway principal de la red..."></textarea>
                        </div>
                        <input type="hidden" name="accion_crear" value="1">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Agregar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Editar Dispositivo -->
    <div class="modal fade" id="modalEditar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil-square"></i> Editar Dispositivo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="mensajeEditarError" class="alert alert-danger d-none" role="alert"></div>
                    <form id="formEditarDispositivo">
                        <input type="hidden" id="editarDispositivoId" name="dispositivo_id">
                        <div class="mb-3">
                            <label for="editarIP" class="form-label">Dirección IP *</label>
                            <input type="text" class="form-control" id="editarIP" name="ip" placeholder="192.168.1.1" required>
                            <small class="text-muted">Ejemplo: 192.168.1.100</small>
                        </div>
                        <div class="mb-3">
                            <label for="editarNombre" class="form-label">Nombre del Dispositivo *</label>
                            <input type="text" class="form-control" id="editarNombre" name="nombre" placeholder="Router Principal" required>
                        </div>
                        <div class="mb-3">
                            <label for="editarDescripcion" class="form-label">Descripción (Opcional)</label>
                            <textarea class="form-control" id="editarDescripcion" name="descripcion" rows="3" placeholder="Gateway principal de la red..."></textarea>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-warning" id="btnActualizarDispositivo">
                                <i class="bi bi-check-circle"></i> Actualizar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Confirmación Eliminación -->
    <div class="modal fade" id="modalEliminar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle"></i> Confirmar Eliminación
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro que desea eliminar este dispositivo del monitoreo?</p>
                    <p class="text-muted small">Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="dispositivo_id" id="deviceIdEliminar">
                        <input type="hidden" name="accion_eliminar" value="1">
                        <button type="submit" class="btn btn-danger">Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); }
            70% { box-shadow: 0 0 0 8px rgba(40, 167, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
        }
    </style>
    <script>
        const modalEliminar = new bootstrap.Modal(document.getElementById('modalEliminar'));
        const modalEditar = new bootstrap.Modal(document.getElementById('modalEditar'));
        let autoRefreshInterval = null;

        function abrirEditarDispositivo(dispositivo) {
            document.getElementById('editarDispositivoId').value = dispositivo.id;
            document.getElementById('editarIP').value = dispositivo.ip;
            document.getElementById('editarNombre').value = dispositivo.nombre;
            document.getElementById('editarDescripcion').value = dispositivo.descripcion || '';
            document.getElementById('mensajeEditarError').classList.add('d-none');
            modalEditar.show();
        }

        function confirmarEliminar(deviceId) {
            document.getElementById('deviceIdEliminar').value = deviceId;
            modalEliminar.show();
        }

        function formatearLatencia(latencia) {
            if (latencia === null || latencia === undefined) return 'N/A';
            if (latencia < 50) {
                return `<span style="color: #28a745;">${latencia.toFixed(1)}ms</span>`;
            } else if (latencia < 200) {
                return `<span style="color: #ffc107;">${latencia.toFixed(1)}ms</span>`;
            } else {
                return `<span style="color: #dc3545;">${latencia.toFixed(1)}ms</span>`;
            }
        }

        function verificarAhora(deviceId) {
            const statusEl = document.getElementById('status-' + deviceId);
            const latenciaEl = document.getElementById('latencia-' + deviceId);
            const fechaEl = document.getElementById('fecha-' + deviceId);
            
            // Guardar estado anterior
            const estadoAnterior = statusEl.dataset.estado;
            const htmlAnterior = statusEl.innerHTML;
            
            // No mostrar estado verificando para auto-actualizaciones (cada 60s)
            // Solo mostrar spinner si es click manual del usuario
            const esAutoActualizacion = statusEl.dataset.autoActualizando === 'true';
            if (!esAutoActualizacion) {
                statusEl.innerHTML = '<span style="display: inline-flex; align-items: center; gap: 4px; background: rgba(108, 117, 125, 0.15); color: #666; padding: 4px 8px; border-radius: 6px; font-size: 0.8rem; font-weight: 600;"><span class="spinner-border spinner-border-sm" style="width: 6px; height: 6px;"></span>...</span>';
            }
            
            // Marcar timestamp de verificación
            const ahora = Date.now();
            statusEl.dataset.ultimaVerificacion = ahora.toString();
            
            // Llamar API
            fetch('api_verificar_ip.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    dispositivo_id: deviceId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Actualizar estado
                    const estado = data.estado;
                    let badgeHtml = '';
                    
                    if (estado === 'online') {
                        badgeHtml = '<span style="display: inline-flex; align-items: center; gap: 4px; background: rgba(40, 167, 69, 0.15); color: #28a745; padding: 4px 8px; border-radius: 6px; font-size: 0.8rem; font-weight: 600;"><span style="width: 6px; height: 6px; background: #28a745; border-radius: 50%; animation: pulse 2s infinite;"></span>Online</span>';
                    } else if (estado === 'offline') {
                        badgeHtml = '<span style="display: inline-flex; align-items: center; gap: 4px; background: rgba(220, 53, 69, 0.15); color: #dc3545; padding: 4px 8px; border-radius: 6px; font-size: 0.8rem; font-weight: 600;"><span style="width: 6px; height: 6px; background: #dc3545; border-radius: 50%;"></span>Offline</span>';
                    }
                    
                    // Solo actualizar si el estado cambió, pero siempre restaurar del spinner
                    if (estado !== estadoAnterior) {
                        statusEl.innerHTML = badgeHtml;
                        statusEl.dataset.estado = estado;
                    } else if (!esAutoActualizacion) {
                        // Si es click manual y el estado no cambió, restaurar el HTML
                        statusEl.innerHTML = htmlAnterior;
                    }
                    
                    latenciaEl.innerHTML = formatearLatencia(data.latencia);
                    if (data.fecha_verificacion) {
                        fechaEl.textContent = data.fecha_verificacion;
                    }
                } else {
                    statusEl.innerHTML = '<span style="display: inline-flex; align-items: center; gap: 4px; background: rgba(220, 53, 69, 0.15); color: #dc3545; padding: 4px 8px; border-radius: 6px; font-size: 0.8rem; font-weight: 600;"><i class="bi bi-exclamation-triangle-fill"></i>Error</span>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                statusEl.innerHTML = '<span style="display: inline-flex; align-items: center; gap: 4px; background: rgba(220, 53, 69, 0.15); color: #dc3545; padding: 4px 8px; border-radius: 6px; font-size: 0.8rem; font-weight: 600;"><i class="bi bi-x-circle-fill"></i>Error</span>';
            });
        }

        // Auto-verificar todos los dispositivos cada 60 segundos
        // OPTIMIZACIÓN: Solo verifica si cambió el estado, no cada vez
        function verificarTodosAutomaticamente() {
            const statusElements = document.querySelectorAll('[id^="status-"]');
            let dispositivosAVerificar = 0;
            
            statusElements.forEach(el => {
                el.dataset.autoActualizando = 'true';
                const deviceId = el.id.replace('status-', '');
                verificarAhora(deviceId);
                dispositivosAVerificar++;
                el.dataset.autoActualizando = 'false';
            });
            
            console.log(`[Monitoreo] Auto-verificación completada: ${dispositivosAVerificar} dispositivos`);
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Manejar formulario de editar dispositivo
            const formEditar = document.getElementById('formEditarDispositivo');
            if (formEditar) {
                formEditar.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const dispositivo_id = document.getElementById('editarDispositivoId').value;
                    const nombre = document.getElementById('editarNombre').value;
                    const descripcion = document.getElementById('editarDescripcion').value;
                    const ip = document.getElementById('editarIP').value;
                    
                    const btnActualizar = document.getElementById('btnActualizarDispositivo');
                    const estadoOriginal = btnActualizar.innerHTML;
                    btnActualizar.disabled = true;
                    btnActualizar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Actualizando...';
                    
                    try {
                        const response = await fetch('api_editar_dispositivo.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                dispositivo_id,
                                nombre,
                                descripcion,
                                ip
                            })
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            // Actualizar la tarjeta en el DOM sin recargar
                            const card = document.querySelector(`[id="device-${dispositivo_id}"]`);
                            if (card) {
                                const nombreEl = card.querySelector('.card-title');
                                const ipEl = card.querySelector('small.text-muted');
                                
                                if (nombreEl) nombreEl.textContent = nombre;
                                if (ipEl) ipEl.textContent = ip;
                            }
                            
                            modalEditar.hide();
                            
                            // Mostrar notificación con toast
                            mostrarToastExito('✅ Dispositivo actualizado exitosamente');
                            
                            btnActualizar.disabled = false;
                            btnActualizar.innerHTML = estadoOriginal;
                        } else {
                            document.getElementById('mensajeEditarError').textContent = data.mensaje || 'Error al actualizar dispositivo';
                            document.getElementById('mensajeEditarError').classList.remove('d-none');
                            btnActualizar.disabled = false;
                            btnActualizar.innerHTML = estadoOriginal;
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        document.getElementById('mensajeEditarError').textContent = 'Error al actualizar dispositivo';
                        document.getElementById('mensajeEditarError').classList.remove('d-none');
                        btnActualizar.disabled = false;
                        btnActualizar.innerHTML = estadoOriginal;
                    }
                });
            }
            
            // OPTIMIZACIÓN: Verificar solo dispositivos con estado antiguo (más de 2 minutos)
            // y después comenzar ciclo automático cada 60 segundos (no 30)
            verificarDispositivosAniguos();
            
            // Auto-actualizar cada 60 segundos en lugar de 30 (reduce carga 50%)
            autoRefreshInterval = setInterval(verificarTodosAutomaticamente, 60000);
        });

        // OPTIMIZACIÓN: Nueva función para verificar solo dispositivos con estado viejo
        function verificarDispositivosAniguos() {
            const statusElements = document.querySelectorAll('[id^="status-"]');
            const ahora = Date.now();
            
            statusElements.forEach(el => {
                const deviceId = el.id.replace('status-', '');
                const fechaEl = document.getElementById('fecha-' + deviceId);
                
                // Si el estado es "Nunca" o tiene más de 2 minutos, verificar
                if (fechaEl.textContent === 'Nunca' || (el.dataset.ultimaVerificacion && 
                    (ahora - parseInt(el.dataset.ultimaVerificacion)) > 120000)) {
                    el.dataset.autoActualizando = 'true';
                    verificarAhora(deviceId);
                    el.dataset.ultimaVerificacion = ahora.toString();
                    el.dataset.autoActualizando = 'false';
                }
            });
        }

        function mostrarToastExito(mensaje) {
            // Crear elemento toast
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #d4edda;
                color: #155724;
                padding: 15px 20px;
                border-radius: 8px;
                border: 1px solid #c3e6cb;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 9999;
                animation: slideIn 0.3s ease-out;
                font-weight: 500;
                font-size: 0.95rem;
            `;
            toast.textContent = mensaje;
            
            document.body.appendChild(toast);
            
            // Auto-remover después de 3 segundos
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Agregar estilos para animaciones del toast
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            @keyframes slideOut {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);

        // Limpiar intervalo al cerrar la página
        window.addEventListener('beforeunload', () => {
            if (autoRefreshInterval) clearInterval(autoRefreshInterval);
        });

        // Fullscreen functions
        function abrirFullscreen() {
            window.location.href = 'monitoreo.php?fullscreen=1';
        }

        function salirFullscreen() {
            window.location.href = 'monitoreo.php';
        }

        // Escuchar tecla ESC para salir del fullscreen
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && document.body.classList.contains('fullscreen-mode')) {
                salirFullscreen();
            }
        });

        // Si está en fullscreen, maximizar cuerpo
        document.addEventListener('DOMContentLoaded', function() {
            if (document.body.classList.contains('fullscreen-mode')) {
                // Intentar modo fullscreen de navegador si es posible
                if (document.documentElement.requestFullscreen) {
                    document.documentElement.requestFullscreen().catch(() => {
                        // Si no se permite, solo continuar con el modo fullscreen CSS
                    });
                }
            }
        });
    </script>
</body>
</html>
