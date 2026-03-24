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
    $tipo_dispositivo_id = isset($_POST["tipo_dispositivo_id"]) && !empty($_POST["tipo_dispositivo_id"]) ? (int)$_POST["tipo_dispositivo_id"] : null;
    
    if (empty($ip) || empty($nombre)) {
        $error = "La IP y el nombre son obligatorios";
    } else {
        try {
            $stmt = $conexion->prepare("
                INSERT INTO dispositivos_monitoreo (ip, nombre, descripcion, tipo_dispositivo_id, usuario_creador)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$ip, $nombre, $descripcion, $tipo_dispositivo_id, $_SESSION["user_id"]]);
            
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

// OBTENER TIPOS DE DISPOSITIVOS
$tipos_dispositivos = [];
try {
    $stmt = $conexion->prepare("SELECT id, nombre, color, icono FROM tipos_dispositivos ORDER BY nombre ASC");
    $stmt->execute();
    $tipos_dispositivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al obtener tipos de dispositivos: " . $e->getMessage();
}

// OBTENER DISPOSITIVOS CON JOIN
$filtro_tipo = isset($_GET['tipo']) && !empty($_GET['tipo']) ? (int)$_GET['tipo'] : null;
$filtro_estado = isset($_GET['estado']) && !empty($_GET['estado']) ? trim($_GET['estado']) : null;

try {
    $query = "
        SELECT d.*, u.username as usuario_nombre, t.nombre as tipo_nombre, t.color, t.icono
        FROM dispositivos_monitoreo d
        JOIN users u ON d.usuario_creador = u.id
        LEFT JOIN tipos_dispositivos t ON d.tipo_dispositivo_id = t.id
        WHERE d.activo = 1
    ";
    
    $params = [];
    if ($filtro_tipo) {
        $query .= " AND d.tipo_dispositivo_id = ?";
        $params[] = $filtro_tipo;
    }
    
    if ($filtro_estado) {
        $query .= " AND d.estado = ?";
        $params[] = $filtro_estado;
    }
    
    $query .= " ORDER BY d.fecha_creacion DESC";
    
    $stmt = $conexion->prepare($query);
    $stmt->execute($params);
    $dispositivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al obtener dispositivos: " . $e->getMessage();
}

// CALCULAR ESTADÍSTICAS POR TIPO
$estadisticas_tipo = [];
try {
    $stmt = $conexion->prepare("
        SELECT 
            COALESCE(t.id, 0) as tipo_id,
            COALESCE(t.nombre, 'Sin clasificar') as tipo_nombre,
            COALESCE(t.color, '#6c757d') as color,
            COALESCE(t.icono, 'bi-device-hdd') as icono,
            COUNT(d.id) as total,
            SUM(CASE WHEN d.estado = 'online' THEN 1 ELSE 0 END) as online_count,
            SUM(CASE WHEN d.estado = 'offline' THEN 1 ELSE 0 END) as offline_count
        FROM dispositivos_monitoreo d
        LEFT JOIN tipos_dispositivos t ON d.tipo_dispositivo_id = t.id
        WHERE d.activo = 1
        GROUP BY t.id, t.nombre, t.color, t.icono
        ORDER BY t.nombre ASC
    ");
    $stmt->execute();
    $estadisticas_tipo = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al obtener estadísticas: " . $e->getMessage();
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
                    <div style="display: flex; gap: 8px;">
                        <?php if ($_SESSION["role"] === "admin"): ?>
                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modalGestionarTipos" title="Gestionar tipos de dispositivos">
                            <i class="bi bi-tag"></i> Tipos
                        </button>
                        <?php endif; ?>
                        <button class="btn btn-secondary btn-sm" onclick="abrirFullscreen()" title="Pantalla completa para exposición">
                            <i class="bi bi-fullscreen"></i> Pantalla Completa
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botón Agregar + Filtro por Tipo (Dropdown) -->
        <div class="row mb-4">
            <div class="col-12">
                <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAgregar">
                        <i class="bi bi-plus-circle"></i> Agregar Dispositivo
                    </button>
                    
                    <?php if (!empty($tipos_dispositivos)): ?>
                    <select class="form-select" style="max-width: 250px;" onchange="if(this.value) location.href='monitoreo.php?tipo=' + this.value; else location.href='monitoreo.php';">
                        <option value="">
                            <i class="bi bi-list"></i> Todos los dispositivos
                        </option>
                        <?php foreach ($tipos_dispositivos as $tipo): ?>
                        <option value="<?php echo $tipo['id']; ?>" <?php echo $filtro_tipo == $tipo['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tipo['nombre']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                </div>
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

        <!-- Estadísticas por Tipo de Dispositivo -->
        <?php if (!empty($estadisticas_tipo)): ?>
        <div class="mb-4">
            <h5 style="color: #667eea; font-weight: 700; margin-bottom: 15px;">
                <i class="bi bi-bar-chart"></i> Estadísticas por Tipo de Dispositivo
            </h5>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px;">
                <?php foreach ($estadisticas_tipo as $stat): ?>
                <div style="background: #1e1e1e; border: 2px solid <?php echo $stat['color']; ?>; padding: 16px; border-radius: 10px; cursor: pointer;" onclick="location.href='monitoreo.php?tipo=<?php echo $stat['tipo_id']; ?>'">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                        <i class="bi <?php echo htmlspecialchars($stat['icono']); ?>" style="font-size: 1.5rem; color: <?php echo $stat['color']; ?>;"></i>
                        <div>
                            <div style="font-weight: 700; color: #e0e0e0;">
                                <?php echo htmlspecialchars($stat['tipo_nombre']); ?>
                            </div>
                            <div style="font-size: 0.85rem; color: #999;">
                                Total: <?php echo $stat['total']; ?>
                            </div>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px; margin-top: 10px;">
                        <div style="flex: 1; padding: 10px; background: #2a4a2a; border-radius: 6px; text-align: center; cursor: pointer; transition: all 0.2s;" onclick="event.stopPropagation(); location.href='monitoreo.php?tipo=<?php echo $stat['tipo_id']; ?>&estado=online'" onmouseover="this.style.background='#3a5a3a'" onmouseout="this.style.background='#2a4a2a'">
                            <div style="font-weight: 700; color: #28a745; font-size: 1.1rem;"><?php echo $stat['online_count']; ?></div>
                            <div style="font-size: 0.75rem; color: #28a745;">Online</div>
                        </div>
                        <div style="flex: 1; padding: 10px; background: #4a2a2a; border-radius: 6px; text-align: center; cursor: pointer; transition: all 0.2s;" onclick="event.stopPropagation(); location.href='monitoreo.php?tipo=<?php echo $stat['tipo_id']; ?>&estado=offline'" onmouseover="this.style.background='#5a3a3a'" onmouseout="this.style.background='#4a2a2a'">
                            <div style="font-weight: 700; color: #dc3545; font-size: 1.1rem;"><?php echo $stat['offline_count']; ?></div>
                            <div style="font-size: 0.75rem; color: #dc3545;">Offline</div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
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
                            <!-- Tipo Dispositivo Badge -->
                            <?php if ($dispositivo['tipo_nombre']): ?>
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; background: color-mix(in srgb, <?php echo htmlspecialchars($dispositivo['color']); ?> 15%, transparent); padding: 8px 12px; border-radius: 8px; border-left: 3px solid <?php echo htmlspecialchars($dispositivo['color']); ?>;">
                                <i class="bi <?php echo htmlspecialchars($dispositivo['icono']); ?>" style="font-size: 1.1rem; color: <?php echo htmlspecialchars($dispositivo['color']); ?>;"></i>
                                <span style="font-size: 0.85rem; font-weight: 600; color: <?php echo htmlspecialchars($dispositivo['color']); ?>;">
                                    <?php echo htmlspecialchars($dispositivo['tipo_nombre']); ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            
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
                            <label for="tipo_dispositivo_id" class="form-label">Tipo de Dispositivo</label>
                            <select class="form-select" id="tipo_dispositivo_id" name="tipo_dispositivo_id">
                                <option value="">-- Sin clasificar --</option>
                                <?php foreach ($tipos_dispositivos as $tipo): ?>
                                <option value="<?php echo $tipo['id']; ?>">
                                    <i class="bi <?php echo htmlspecialchars($tipo['icono']); ?>"></i>
                                    <?php echo htmlspecialchars($tipo['nombre']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
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
                            <label for="editarTipoDispositivo" class="form-label">Tipo de Dispositivo</label>
                            <select class="form-select" id="editarTipoDispositivo" name="tipo_dispositivo_id">
                                <option value="">-- Sin clasificar --</option>
                                <?php foreach ($tipos_dispositivos as $tipo): ?>
                                <option value="<?php echo $tipo['id']; ?>">
                                    <i class="bi <?php echo htmlspecialchars($tipo['icono']); ?>"></i>
                                    <?php echo htmlspecialchars($tipo['nombre']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
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

    <!-- Modal Gestionar Tipos de Dispositivos -->
    <div class="modal fade" id="modalGestionarTipos" tabindex="-1" size="lg">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-tag"></i> Gestionar Tipos de Dispositivos
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="max-height: 600px; overflow-y: auto;">
                    <!-- Botón Agregar -->
                    <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#modalAgregarTipo" onclick="limpiarFormularioTipo()">
                        <i class="bi bi-plus-circle"></i> Agregar Nuevo Tipo
                    </button>

                    <!-- Alertas -->
                    <div id="alerta-tipos" class="alert d-none" role="alert"></div>

                    <!-- Lista de Tipos -->
                    <div id="lista-tipos" style="display: grid; gap: 12px;">
                        <p class="text-muted"><i class="bi bi-hourglass-split"></i> Cargando...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Agregar/Editar Tipo -->
    <div class="modal fade" id="modalAgregarTipo" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle"></i> <span id="titulo-form-tipo">Agregar Tipo de Dispositivo</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formAgregarTipo">
                        <input type="hidden" id="tipoIdEditar" name="tipo_id" value="">
                        <div class="mb-3">
                            <label for="nombreTipo" class="form-label">Nombre *</label>
                            <input type="text" class="form-control" id="nombreTipo" name="nombre" placeholder="Ej: Switch, NVR, etc." required>
                        </div>
                        <div class="mb-3">
                            <label for="colorTipo" class="form-label">Color *</label>
                            <input type="color" class="form-control form-control-color" id="colorTipo" name="color" value="#007bff" required>
                        </div>
                        <div class="mb-3">
                            <label for="iconoTipo" class="form-label">Icono *</label>
                            <input type="text" class="form-control" id="iconoTipo" name="icono" placeholder="Ej: bi-wifi, bi-camera-video" required>
                            <small class="text-muted">Usa iconos de <a href="https://icons.getbootstrap.com/" target="_blank">Bootstrap Icons</a></small>
                        </div>
                        <div id="mensajeTipoError" class="alert alert-danger d-none" role="alert"></div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-check-circle"></i> Guardar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Eliminar Tipo -->
    <div class="modal fade" id="modalEliminarTipo" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle"></i> Eliminar Tipo de Dispositivo
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro que desea eliminar el tipo <strong id="nombreAEliminarTipo"></strong>?</p>
                    <p class="text-muted small">⚠️ Los dispositivos asociados quedarán sin clasificar.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="btnConfirmarEliminarTipo">Eliminar</button>
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
            document.getElementById('editarTipoDispositivo').value = dispositivo.tipo_dispositivo_id || '';
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
                    const tipo_dispositivo_id = document.getElementById('editarTipoDispositivo').value || null;
                    
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
                                ip,
                                tipo_dispositivo_id
                            })
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            modalEditar.hide();
                            
                            // Mostrar notificación y recargar la página después de 0.5 segundos
                            mostrarToastExito('✅ Dispositivo actualizado exitosamente');
                            
                            setTimeout(() => {
                                location.reload();
                            }, 500);
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

        // ========== GESTIONAR TIPOS DE DISPOSITIVOS ==========
        const modalGestionarTipos = new bootstrap.Modal(document.getElementById('modalGestionarTipos'));
        const modalAgregarTipo = new bootstrap.Modal(document.getElementById('modalAgregarTipo'));
        const modalEliminarTipo = new bootstrap.Modal(document.getElementById('modalEliminarTipo'));
        let tipoIdAEliminar = null;

        // Cargar tipos cuando se abre el modal
        document.getElementById('modalGestionarTipos').addEventListener('show.bs.modal', cargarTipos);

        async function cargarTipos() {
            const listaTipos = document.getElementById('lista-tipos');
            const alertaTipos = document.getElementById('alerta-tipos');
            alertaTipos.classList.add('d-none');

            try {
                const response = await fetch('api_obtener_tipos_dispositivos.php');
                const data = await response.json();

                if (data.success) {
                    if (data.tipos.length === 0) {
                        listaTipos.innerHTML = '<p class="text-muted">No hay tipos de dispositivos creados.</p>';
                    } else {
                        listaTipos.innerHTML = data.tipos.map(tipo => `
                            <div style="background: #1e1e1e; border: 2px solid ${tipo.color}; padding: 15px; border-radius: 10px; display: flex; align-items: center; gap: 15px; justify-content: space-between;">
                                <div style="display: flex; align-items: center; gap: 15px; flex: 1;">
                                    <i class="bi ${tipo.icono}" style="font-size: 2rem; color: ${tipo.color};"></i>
                                    <div>
                                        <div style="font-weight: 700; color: #e0e0e0; margin-bottom: 5px;">${tipo.nombre}</div>
                                        <div style="font-size: 0.85rem; color: #999;">
                                            <code>${tipo.color}</code> • <code>${tipo.icono}</code>
                                        </div>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <button type="button" class="btn btn-sm btn-warning" onclick="abrirEditarTipo(${JSON.stringify(tipo).replace(/"/g, '&quot;')})">
                                        <i class="bi bi-pencil"></i> Editar
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="abrirEliminarTipo(${tipo.id}, '${tipo.nombre}')">
                                        <i class="bi bi-trash"></i> Eliminar
                                    </button>
                                </div>
                            </div>
                        `).join('');
                    }
                } else {
                    alertaTipos.innerHTML = 'Error al cargar tipos: ' + (data.mensaje || '');
                    alertaTipos.classList.remove('d-none');
                    alertaTipos.classList.add('alert-danger');
                }
            } catch (error) {
                console.error('Error:', error);
                alertaTipos.innerHTML = 'Error al cargar tipos: ' + error.message;
                alertaTipos.classList.remove('d-none');
                alertaTipos.classList.add('alert-danger');
            }
        }

        function limpiarFormularioTipo() {
            document.getElementById('tipoIdEditar').value = '';
            document.getElementById('formAgregarTipo').reset();
            document.getElementById('colorTipo').value = '#007bff';
            document.getElementById('titulo-form-tipo').innerHTML = 'Agregar Tipo de Dispositivo';
            document.getElementById('mensajeTipoError').classList.add('d-none');
        }

        function abrirEditarTipo(tipo) {
            document.getElementById('tipoIdEditar').value = tipo.id;
            document.getElementById('nombreTipo').value = tipo.nombre;
            document.getElementById('colorTipo').value = tipo.color;
            document.getElementById('iconoTipo').value = tipo.icono;
            document.getElementById('titulo-form-tipo').innerHTML = 'Editar Tipo de Dispositivo';
            document.getElementById('mensajeTipoError').classList.add('d-none');

            // Cerrar el modal anterior y abrir el de agregar/editar
            modalGestionarTipos.hide();
            setTimeout(() => modalAgregarTipo.show(), 200);
        }

        document.getElementById('formAgregarTipo').addEventListener('submit', async function(e) {
            e.preventDefault();

            const tipoId = document.getElementById('tipoIdEditar').value;
            const nombre = document.getElementById('nombreTipo').value;
            const color = document.getElementById('colorTipo').value;
            const icono = document.getElementById('iconoTipo').value;

            const endpoint = tipoId ? 'api_editar_tipo_dispositivo.php' : 'api_crear_tipo_dispositivo.php';
            const body = tipoId 
                ? { tipo_id: tipoId, nombre, color, icono }
                : { nombre, color, icono };

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body)
                });

                const data = await response.json();

                if (data.success) {
                    modalAgregarTipo.hide();
                    cargarTipos();
                    setTimeout(() => modalGestionarTipos.show(), 200);
                    mostrarToastExito('✅ ' + (tipoId ? 'Tipo actualizado' : 'Tipo creado') + ' exitosamente');
                } else {
                    document.getElementById('mensajeTipoError').innerHTML = data.mensaje || 'Error';
                    document.getElementById('mensajeTipoError').classList.remove('d-none');
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('mensajeTipoError').innerHTML = 'Error al guardar tipo';
                document.getElementById('mensajeTipoError').classList.remove('d-none');
            }
        });

        function abrirEliminarTipo(tipoId, tipoNombre) {
            tipoIdAEliminar = tipoId;
            document.getElementById('nombreAEliminarTipo').textContent = tipoNombre;
            modalEliminarTipo.show();
        }

        document.getElementById('btnConfirmarEliminarTipo').addEventListener('click', async function() {
            try {
                const response = await fetch('api_eliminar_tipo_dispositivo.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ tipo_id: tipoIdAEliminar })
                });

                const data = await response.json();

                if (data.success) {
                    modalEliminarTipo.hide();
                    cargarTipos();
                    mostrarToastExito('✅ Tipo eliminado exitosamente');
                } else {
                    alert('Error: ' + (data.mensaje || ''));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error al eliminar tipo');
            }
        });
    </script>
</body>
</html>
