<?php
session_start();
require_once 'includes/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

// Verificar permisos
$permisos = ['tisupport', 'admin'];
if (!in_array($_SESSION["role"] ?? "viewer", $permisos)) {
    header("Location: dashboard.php");
    exit();
}

$procedimiento = null;
$menciones = [];
$historial = [];
$error = "";
$success = $_GET["success"] ?? "";

// Obtener ID del procedimiento
$procedimiento_id = $_GET["id"] ?? "";
if (empty($procedimiento_id)) {
    header("Location: procedimientos.php");
    exit();
}

try {
    // Obtener procedimiento
    $stmt = $conexion->prepare("
        SELECT p.*, u.username as autor_nombre
        FROM procedimientos p
        JOIN users u ON p.usuario_creador = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$procedimiento_id]);
    $procedimiento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$procedimiento) {
        $error = "Procedimiento no encontrado";
    } else {
        // Obtener menciones
        $stmt = $conexion->prepare("
            SELECT mp.*, t.ticket_number, c.comentario, c.fecha as fecha_comentario, u.username
            FROM menciones_procedimientos mp
            JOIN tickets t ON mp.ticket_id = t.id
            JOIN comentarios_tickets c ON mp.comentario_id = c.id
            JOIN users u ON c.usuario_id = u.id
            WHERE mp.procedimiento_id = ?
            ORDER BY mp.fecha_mencion DESC
        ");
        $stmt->execute([$procedimiento_id]);
        $menciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener historial
        $stmt = $conexion->prepare("
            SELECT h.*, u.username
            FROM historial_procedimientos h
            JOIN users u ON h.usuario_id = u.id
            WHERE h.procedimiento_id = ?
            ORDER BY h.fecha_cambio DESC
        ");
        $stmt->execute([$procedimiento_id]);
        $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="css/dark-mode.css" rel="stylesheet">
    <title><?php echo $procedimiento ? htmlspecialchars($procedimiento["titulo"]) : "Procedimiento"; ?></title>
    <style>
        body {
            background-color: #f8f9fa;
        }
        [data-bs-theme="dark"] body {
            background-color: #1a1d23;
        }
        
        .header-procedimiento {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        [data-bs-theme="dark"] .header-procedimiento {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .cuerpo-procedimiento {
            background: white;
            padding: 30px;
            border-radius: 8px;
            line-height: 1.8;
            font-size: 16px;
            max-width: 100%;
            margin: 0 0 30px 0;
            white-space: pre-line;
            word-wrap: break-word;
            overflow-x: auto;
            border-left: 5px solid #667eea;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: left;
        }
        
        [data-bs-theme="dark"] .cuerpo-procedimiento {
            background: #252a32;
            border-left-color: #667eea;
        }
        
        .info-meta {
            display: flex;
            gap: 40px;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-item strong {
            color: #667eea;
        }
        
        .mencion-card {
            border-left: 4px solid #17a2b8;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 4px;
            background: white;
        }
        
        [data-bs-theme="dark"] .mencion-card {
            background: #252a32;
        }
        
        .timeline-item {
            padding: 20px;
            border-left: 3px solid #667eea;
            margin-bottom: 20px;
            background: white;
            border-radius: 4px;
        }
        
        [data-bs-theme="dark"] .timeline-item {
            background: #252a32;
        }
        
        .btn-section {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .procedimiento-id {
            font-family: 'Courier New', monospace;
            background: rgba(255,255,255,0.2);
            padding: 8px 12px;
            border-radius: 4px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="container-fluid" style="padding: 30px 50px;">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php else: ?>
            <?php if (!empty($success)): ?>
                <div class="position-fixed top-0 start-50 translate-middle-x mt-3" style="z-index: 1080;">
                    <div id="successToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="2000">
                        <div class="d-flex">
                            <div class="toast-body">
                                <?php
                                    if ($success === 'creado') echo 'Procedimiento creado exitosamente';
                                    elseif ($success === 'cambios') echo 'Cambios realizados';
                                    else echo htmlspecialchars($success);
                                ?>
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Header -->
            <div class="header-procedimiento">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h1 class="mb-2"><?php echo htmlspecialchars($procedimiento["titulo"]); ?></h1>
                        <div class="procedimiento-id">
                            <i class="bi bi-key"></i> <?php echo htmlspecialchars($procedimiento["id_procedimiento"]); ?>
                        </div>
                    </div>
                    <span class="badge <?php echo $procedimiento["tipo_procedimiento"] === "técnico" ? "bg-info" : "bg-warning"; ?>" style="font-size: 1rem; padding: 10px 15px;">
                        <i class="bi <?php echo $procedimiento["tipo_procedimiento"] === "técnico" ? "bi-wrench" : "bi-clipboard-check"; ?>"></i>
                        <?php echo ucfirst($procedimiento["tipo_procedimiento"]); ?>
                    </span>
                </div>
            </div>
            
            <!-- Botones de acción -->
            <div class="btn-section">
                <a href="editar_procedimiento.php?id=<?php echo $procedimiento_id; ?>" class="btn btn-primary">
                    <i class="bi bi-pencil"></i> Editar
                </a>
                <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#modalHistorial">
                    <i class="bi bi-clock-history"></i> Actividad
                </button>
                <a href="procedimientos.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>
            
            <!-- Información Meta -->
            <div class="info-meta">
                <div class="info-item">
                    <i class="bi bi-person"></i>
                    <span><strong>Autor:</strong> <?php echo htmlspecialchars($procedimiento["autor_nombre"]); ?></span>
                </div>
                <div class="info-item">
                    <i class="bi bi-calendar"></i>
                    <span><strong>Creado:</strong> <?php echo date('d/m/Y H:i', strtotime($procedimiento["fecha_creacion"])); ?></span>
                </div>
                <div class="info-item">
                    <i class="bi bi-pencil"></i>
                    <span><strong>Modificado:</strong> <?php echo date('d/m/Y H:i', strtotime($procedimiento["fecha_ultima_modificacion"])); ?></span>
                </div>
            </div>
            
            <!-- Contenido -->
            <h3 class="mb-3"><i class="bi bi-file-text"></i> Contenido</h3>
            <div class="cuerpo-procedimiento">
                <?php 
                // Procesar el contenido para mejorar la alineación
                $contenido = htmlspecialchars($procedimiento["cuerpo"]);
                // Dividir por líneas y limpiar espacios en blanco
                $lineas = explode("\n", $contenido);
                $contenido_procesado = implode("\n", array_map('trim', $lineas));
                // Eliminar líneas vacías al inicio y final
                $contenido_procesado = trim($contenido_procesado);
                echo $contenido_procesado;
                ?>
            </div>
            
            <!-- Menciones -->
            <h3 class="mb-3"><i class="bi bi-link-45deg"></i> Menciones en Tickets (<?php echo count($menciones); ?>)</h3>
            <div class="card mb-4">
                <div class="card-body">
                    <?php if (empty($menciones)): ?>
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-info-circle"></i> Este procedimiento aún no ha sido mencionado en ningún ticket.
                        </div>
                    <?php else: ?>
                        <?php foreach ($menciones as $mencion): ?>
                            <div class="mencion-card">
                                <h6 class="mb-2">
                                    <a href="ver_ticket.php?id=<?php echo htmlspecialchars($mencion["ticket_id"]); ?>">
                                        <i class="bi bi-ticket-detailed"></i> <?php echo htmlspecialchars($mencion["ticket_number"]); ?>
                                    </a>
                                </h6>
                                <p class="mb-2 small text-muted">
                                    <strong><?php echo htmlspecialchars($mencion["username"]); ?></strong> - 
                                    <?php echo date('d/m/Y H:i', strtotime($mencion["fecha_comentario"])); ?>
                                </p>
                                <p class="mb-0" style="max-height: 100px; overflow: hidden;">
                                    <?php echo htmlspecialchars(substr($mencion["comentario"], 0, 200)); ?>
                                    <?php echo strlen($mencion["comentario"]) > 200 ? "..." : ""; ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal Historial -->
    <div class="modal fade" id="modalHistorial" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-clock-history"></i> Actividad del Procedimiento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($historial)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> No hay cambios registrados aún.
                        </div>
                    <?php else: ?>
                        <?php foreach ($historial as $cambio): ?>
                            <div class="timeline-item">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <strong><?php echo htmlspecialchars($cambio["username"]); ?></strong>
                                        <span class="text-muted ms-2">modificó <strong><?php echo htmlspecialchars($cambio["campo_modificado"]); ?></strong></span>
                                    </div>
                                    <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($cambio["fecha_cambio"])); ?></small>
                                </div>
                                
                                <?php if ($cambio["campo_modificado"] === "cuerpo"): ?>
                                    <p class="small text-muted mb-0">Se actualizó el contenido del procedimiento</p>
                                <?php else: ?>
                                    <div class="small">
                                        <span class="badge bg-danger">Antes: <?php echo htmlspecialchars(substr($cambio["valor_anterior"], 0, 30)); ?></span>
                                        <span class="badge bg-success ms-2">Después: <?php echo htmlspecialchars(substr($cambio["valor_nuevo"], 0, 30)); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toastEl = document.getElementById('successToast');
            if (toastEl) {
                const toast = new bootstrap.Toast(toastEl);
                toast.show();
            }
        });
    </script>
    <script src="includes/dark-mode.js"></script>
</body>
</html>
