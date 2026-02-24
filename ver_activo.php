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
    header("Location: tickets.php");
    exit();
}

$error = "";
$activo = null;

// Obtener ID o número del activo
$activo_id = $_GET["id"] ?? "";

if (!$activo_id) {
    header("Location: activos.php");
    exit();
}

try {
    // Verificar si es un ID numérico o un RFK
    if (is_numeric($activo_id)) {
        $stmt = $conexion->prepare("SELECT * FROM activos WHERE id = ?");
        $stmt->execute([$activo_id]);
    } else {
        // Buscar por RFK (AK79XXXX)
        $stmt = $conexion->prepare("SELECT * FROM activos WHERE rfk = ?");
        $stmt->execute([$activo_id]);
    }
    
    $activo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$activo) {
        header("Location: activos.php?error=not_found");
        exit();
    }
    
    // Obtener tickets donde se menciona este activo
    $tickets_mencionados = [];
    if ($activo) {
        $stmt_tickets = $conexion->prepare("
            SELECT DISTINCT t.id, t.ticket_number, t.titulo, t.estado, t.es_cerrado, t.fecha_creacion
            FROM tickets t
            JOIN comentarios_tickets c ON t.id = c.ticket_id
            WHERE c.comentario LIKE ?
            ORDER BY t.fecha_creacion DESC
        ");
        $stmt_tickets->execute(['%' . $activo['rfk'] . '%']);
        $tickets_mencionados = $stmt_tickets->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = "Error al obtener el activo: " . $e->getMessage();
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
    <title>Ver Activo - <?php echo htmlspecialchars($activo["rfk"] ?? ""); ?></title>
    <style>
        h1, h2, h3 {
            color: #8b9dff;
            font-weight: 700;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
    <style>
        .activo-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
        }
        
        .info-section {
            margin-bottom: 2rem;
        }
        
        .info-section h5 {
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="container mt-5 mb-5" style="margin-left: auto; margin-right: auto; max-width: 1000px;">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($activo): ?>
        
            <!-- Encabezado del Activo -->
            <div class="activo-header">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <div style="font-size: 3rem;">
                            <i class="bi bi-box"></i>
                        </div>
                    </div>
                    <div class="col">
                        <h1><?php echo htmlspecialchars($activo["titulo"]); ?></h1>
                        <p class="mb-0">
                            <strong>Número de Activo:</strong> <?php echo htmlspecialchars($activo["rfk"]); ?>
                        </p>
                        <p class="mb-0">
                            <strong>RFK:</strong> <?php echo htmlspecialchars($activo["rfk"]); ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Información del Activo -->
            <div class="info-section">
                <h5><i class="bi bi-info-circle"></i> Información Básica</h5>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <strong>Tipo:</strong>
                            <p class="text-muted"><?php echo htmlspecialchars($activo["tipo"] ?? "No especificado"); ?></p>
                        </div>
                        <div class="mb-3">
                            <strong>Ubicación:</strong>
                            <p class="text-muted"><?php echo nl2br(procesarHashtagsContactos(htmlspecialchars($activo["ubicacion"] ?? "No especificada"))); ?></p>
                        </div>
                        <div class="mb-3">
                            <strong>Propietario:</strong>
                            <p class="text-muted"><?php echo htmlspecialchars($activo["propietario"] ?? "No asignado"); ?></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <strong>Fabricante:</strong>
                            <p class="text-muted"><?php echo htmlspecialchars($activo["fabricante"] ?? "No especificado"); ?></p>
                        </div>
                        <div class="mb-3">
                            <strong>Modelo:</strong>
                            <p class="text-muted"><?php echo htmlspecialchars($activo["modelo"] ?? "No especificado"); ?></p>
                        </div>
                        <div class="mb-3">
                            <strong>Número de Serie:</strong>
                            <p class="text-muted"><?php echo htmlspecialchars($activo["serie"] ?? "No especificado"); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Descripción -->
            <?php if (!empty($activo["descripcion"])): ?>
                <div class="info-section">
                    <h5><i class="bi bi-chat-left-text"></i> Descripción</h5>
                    <p><?php echo nl2br(procesarHashtagsContactos(htmlspecialchars($activo["descripcion"]))); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Fallas Activas -->
            <?php if (!empty($activo["fallas_activas"])): ?>
                <div class="info-section">
                    <h5><i class="bi bi-exclamation-triangle"></i> Fallas Activas</h5>
                    <div class="alert alert-warning">
                        <?php echo nl2br(htmlspecialchars($activo["fallas_activas"])); ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Información de Auditoría -->
            <div class="info-section">
                <h5><i class="bi bi-clock-history"></i> Información de Auditoría</h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="card bg-light">
                            <div class="card-body">
                                <strong>Creado por:</strong>
                                <p class="text-muted mb-0"><?php echo htmlspecialchars($activo["usuario_creado_por"] ?? "Sistema"); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card bg-light">
                            <div class="card-body">
                                <strong>Fecha Creación:</strong>
                                <p class="text-muted mb-0"><?php echo !empty($activo["fecha_creacion"]) ? formatearFechaHora($activo["fecha_creacion"]) : "-"; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card bg-light">
                            <div class="card-body">
                                <strong>Modificado por:</strong>
                                <p class="text-muted mb-0"><?php echo htmlspecialchars($activo["usuario_modificado_por"] ?? "-"); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card bg-light">
                            <div class="card-body">
                                <strong>Última Modificación:</strong>
                                <p class="text-muted mb-0"><?php echo !empty($activo["fecha_ultima_modificacion"]) ? formatearFechaHora($activo["fecha_ultima_modificacion"]) : "-"; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tickets Mencionados -->
            <div class="info-section">
                <h5><i class="bi bi-chat-left-dots"></i> Mencionado En Tickets</h5>
                <?php if (count($tickets_mencionados) > 0): ?>
                    <div class="row g-3">
                        <?php foreach ($tickets_mencionados as $ticket): ?>
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="mb-2">
                                            <a href="ver_ticket.php?id=<?php echo urlencode($ticket['ticket_number']); ?>" class="badge bg-primary text-decoration-none" style="font-size: 0.9rem;">
                                                <?php echo htmlspecialchars($ticket['ticket_number']); ?>
                                            </a>
                                        </div>
                                        <h6 class="card-title">
                                            <a href="ver_ticket.php?id=<?php echo urlencode($ticket['ticket_number']); ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars(substr($ticket['titulo'], 0, 60)); ?>
                                                <?php if (strlen($ticket['titulo']) > 60) echo '...'; ?>
                                            </a>
                                        </h6>
                                        <p class="text-muted small mb-2">
                                            <?php echo formatearFecha($ticket['fecha_creacion']); ?>
                                        </p>
                                        <p class="mb-0">
                                            <small>
                                                <?php
                                                $color = $ticket['es_cerrado'] ? 'secondary' : 'success';
                                                $estado = $ticket['es_cerrado'] ? 'Cerrado' : ucfirst($ticket['estado']);
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>">
                                                    <?php echo htmlspecialchars($estado); ?>
                                                </span>
                                            </small>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Este activo no ha sido mencionado en ningún ticket abierto.
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Botones de Acción -->
            <div class="d-flex gap-2 mt-4">
                <a href="activos.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
                <a href="editar_activo.php?id=<?php echo $activo["id"]; ?>" class="btn btn-warning">
                    <i class="bi bi-pencil"></i> Editar
                </a>
            </div>
        
        <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
