<?php
session_start();
require_once 'includes/config.php';

// Prevenir cacheo de la página para que el estado se actualice al presionar atrás
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

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

$error = "";
$success = "";
$ticket = null;
$comentarios = [];

// Obtener ID o número del ticket
$ticket_id = $_GET["id"] ?? "";

if (!$ticket_id) {
    header("Location: tickets.php");
    exit();
}

try {
    // Obtener ticket
    $stmt = $conexion->prepare("SELECT * FROM tickets WHERE id = ? OR ticket_number = ?");
    $stmt->execute([$ticket_id, $ticket_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ticket) {
        header("Location: tickets.php?error=not_found");
        exit();
    }
    
    // Si el ticket está en estado "sin abrir", cambiar a "en conocimiento" automáticamente
    if ($ticket["estado"] === "sin abrir") {
        $stmt = $conexion->prepare("UPDATE tickets SET estado = ?, fecha_ultima_modificacion = NOW() WHERE id = ?");
        $stmt->execute(["en conocimiento", $ticket["id"]]);
        $ticket["estado"] = "en conocimiento";
    }
    
    // Obtener comentarios con información de usuario que modificó
    $stmt = $conexion->prepare("
        SELECT c.*, u.username, 
               COALESCE(um.username, '') as usuario_modifico_nombre
        FROM comentarios_tickets c 
        JOIN users u ON c.usuario_id = u.id
        LEFT JOIN users um ON c.usuario_modificado_por = um.id
        WHERE c.ticket_id = ? 
        ORDER BY c.fecha_comentario ASC
    ");
    $stmt->execute([$ticket["id"]]);
    $comentarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Error al obtener el ticket: " . $e->getMessage();
}

// Actualizar estado
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["nueva_estado"])) {
    try {
        $stmt = $conexion->prepare("UPDATE tickets SET estado = ? WHERE id = ?");
        $stmt->execute([$_POST["nueva_estado"], $ticket["id"]]);
        $success = "Estado actualizado correctamente";
        $ticket["estado"] = $_POST["nueva_estado"];
    } catch (PDOException $e) {
        $error = "Error al actualizar estado: " . $e->getMessage();
    }
}

// Cambiar responsable
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["nuevo_responsable"])) {
    try {
        $nuevo_responsable = $_POST["nuevo_responsable"] === "" ? null : $_POST["nuevo_responsable"];
        $stmt = $conexion->prepare("UPDATE tickets SET responsable = ?, fecha_ultima_modificacion = NOW() WHERE id = ?");
        $stmt->execute([$nuevo_responsable, $ticket["id"]]);
        $success = "Responsable actualizado correctamente";
        $ticket["responsable"] = $nuevo_responsable;
        
        // Recargar responsable_nombre
        if ($nuevo_responsable) {
            $stmt = $conexion->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$nuevo_responsable]);
            $resp_user = $stmt->fetch(PDO::FETCH_ASSOC);
            $responsable_nombre = $resp_user["username"] ?? "No asignado";
        } else {
            $responsable_nombre = "";
        }
    } catch (PDOException $e) {
        $error = "Error al actualizar responsable: " . $e->getMessage();
    }
}

// Agregar comentario
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["nuevo_comentario"])) {
    $comentario = $_POST["nuevo_comentario"] ?? "";
    
    if (empty($comentario)) {
        $error = "El comentario no puede estar vacío";
    } else {
        try {
            $stmt = $conexion->prepare("INSERT INTO comentarios_tickets (ticket_id, usuario_id, comentario) VALUES (?, ?, ?)");
            $stmt->execute([$ticket["id"], $_SESSION["user_id"], $comentario]);
            $success = "Comentario agregado correctamente";
            $_POST["nuevo_comentario"] = "";
            
            // Recargar comentarios
            $stmt = $conexion->prepare("
                SELECT c.*, u.username, 
                       COALESCE(um.username, '') as usuario_modifico_nombre
                FROM comentarios_tickets c 
                JOIN users u ON c.usuario_id = u.id
                LEFT JOIN users um ON c.usuario_modificado_por = um.id
                WHERE c.ticket_id = ? 
                ORDER BY c.fecha_comentario ASC
            ");
            $stmt->execute([$ticket["id"]]);
            $comentarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = "Error al agregar comentario: " . $e->getMessage();
        }
    }
}

// Cerrar/Reabrir ticket
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["cerrar_ticket"])) {
    try {
        $nuevo_estado_cerrado = $_POST["cerrar_ticket"] === "cerrar" ? 1 : 0;
        
        if ($nuevo_estado_cerrado == 1) {
            // Cuando se cierra, actualizar estado a "ticket cerrado"
            $stmt = $conexion->prepare("UPDATE tickets SET es_cerrado = ?, estado = ? WHERE id = ?");
            $stmt->execute([1, 'ticket cerrado', $ticket["id"]]);
            $success = "Ticket cerrado correctamente";
            $ticket["es_cerrado"] = 1;
            $ticket["estado"] = 'ticket cerrado';
        } else {
            // Cuando se reabre, volver a "sin abrir"
            $stmt = $conexion->prepare("UPDATE tickets SET es_cerrado = ?, estado = ? WHERE id = ?");
            $stmt->execute([0, 'sin abrir', $ticket["id"]]);
            $success = "Ticket reabierto correctamente";
            $ticket["es_cerrado"] = 0;
            $ticket["estado"] = 'sin abrir';
        }
    } catch (PDOException $e) {
        $error = "Error al cerrar/reabrir ticket: " . $e->getMessage();
    }
}

// Cancelar ticket
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["cancelar_ticket"])) {
    $razon_cancelacion = $_POST["razon_cancelacion"] ?? "";
    
    if (empty($razon_cancelacion)) {
        $error = "Debes especificar un motivo para cancelar el ticket";
    } else {
        try {
            $stmt = $conexion->prepare("UPDATE tickets SET estado_cancelacion = ?, fecha_ultima_modificacion = NOW() WHERE id = ?");
            $stmt->execute([$razon_cancelacion, $ticket["id"]]);
            $success = "Ticket cancelado correctamente";
            $ticket["estado_cancelacion"] = $razon_cancelacion;
            
            // Agregar comentario de cancelación
            $comentario = "Ticket cancelado. Motivo: " . htmlspecialchars($razon_cancelacion);
            $stmt = $conexion->prepare("INSERT INTO comentarios_tickets (ticket_id, usuario_id, comentario) VALUES (?, ?, ?)");
            $stmt->execute([$ticket["id"], $_SESSION["user_id"], $comentario]);
        } catch (PDOException $e) {
            $error = "Error al cancelar ticket: " . $e->getMessage();
        }
    }
}

// Editar comentario
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["editar_comentario_id"])) {
    $comentario_id = $_POST["editar_comentario_id"];
    $nuevo_texto = $_POST["comentario_editado"] ?? "";
    
    if (empty($nuevo_texto)) {
        $error = "El comentario no puede estar vacío";
    } else {
        try {
            // Verificar que el usuario sea el autor o admin
            $stmt = $conexion->prepare("SELECT usuario_id FROM comentarios_tickets WHERE id = ?");
            $stmt->execute([$comentario_id]);
            $com_autor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($com_autor["usuario_id"] != $_SESSION["user_id"] && $_SESSION["role"] !== "admin") {
                $error = "No puedes editar comentarios de otros usuarios";
            } else {
                $stmt = $conexion->prepare("UPDATE comentarios_tickets SET comentario = ?, fecha_modificacion = NOW(), usuario_modificado_por = ? WHERE id = ?");
                $stmt->execute([$nuevo_texto, $_SESSION["user_id"], $comentario_id]);
                $success = "Comentario editado correctamente";
                
                // Recargar comentarios
                $stmt = $conexion->prepare("
                    SELECT c.*, u.username, 
                           COALESCE(um.username, '') as usuario_modifico_nombre
                    FROM comentarios_tickets c 
                    JOIN users u ON c.usuario_id = u.id
                    LEFT JOIN users um ON c.usuario_modificado_por = um.id
                    WHERE c.ticket_id = ? 
                    ORDER BY c.fecha_comentario ASC
                ");
                $stmt->execute([$ticket["id"]]);
                $comentarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            $error = "Error al editar comentario: " . $e->getMessage();
        }
    }
}

// Obtener datos del usuario propietario, responsable y creador
$propietario_nombre = "";
if ($ticket["propietario"]) {
    $stmt = $conexion->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$ticket["propietario"]]);
    $prop_user = $stmt->fetch(PDO::FETCH_ASSOC);
    $propietario_nombre = $prop_user["username"] ?? "No asignado";
}

$responsable_nombre = "";
if ($ticket["responsable"]) {
    $stmt = $conexion->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$ticket["responsable"]]);
    $resp_user = $stmt->fetch(PDO::FETCH_ASSOC);
    $responsable_nombre = $resp_user["username"] ?? "No asignado";
}

$stmt = $conexion->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$ticket["usuario_creador"]]);
$creator_user = $stmt->fetch(PDO::FETCH_ASSOC);
$creator_nombre = $creator_user["username"] ?? "Desconocido";

// Obtener lista de usuarios soporte TI y admin para cambiar propietario
try {
    $stmt = $conexion->query("SELECT id, username FROM users WHERE role IN ('tisupport', 'admin') ORDER BY username");
    $usuarios_soporte = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $usuarios_soporte = [];
}

$estados = ['sin abrir', 'en conocimiento', 'en proceso', 'ticket cerrado', 'pendiente de cierre'];
?>

<!DOCTYPE html>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Ticket <?php echo htmlspecialchars($ticket["ticket_number"]); ?></title>
    <style>
        .ticket-header { background-color: #f8f9fa; border-left: 5px solid #0d6efd; }
        .comentario { border-left: 3px solid #e9ecef; margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 4px; position: relative; }
        .comentario-autor { font-weight: bold; color: #0d6efd; }
        .comentario-fecha { font-size: 0.85em; color: #6c757d; }
        .comentario-modificado { font-size: 0.8em; color: #e74c3c; margin-top: 5px; }
        .btn-editar-com { font-size: 0.8em; padding: 0.25rem 0.5rem; }
        .comentario-edit-form { display: none; margin-top: 10px; }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                ✓ <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-12">
                <!-- Header del Ticket -->
                <div class="card mb-4 ticket-header">
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <h2><?php echo htmlspecialchars($ticket["ticket_number"]); ?></h2>
                                <h4><?php echo htmlspecialchars($ticket["titulo"]); ?></h4>
                                <div style="margin-top: 10px; padding: 10px; background-color: #e7f3ff; border-left: 4px solid #0d6efd; border-radius: 4px;">
                                    <strong style="color: #0d6efd;">Reportado por:</strong> 
                                    <span style="font-size: 1.1em; color: #0056b3;">
                                        <?php echo htmlspecialchars($ticket["nombre_solicitante"] ?? "N/A"); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="mb-2">
                                    <strong>Fecha Creación:</strong> <?php echo formatearFechaHora($ticket["fecha_creacion"]); ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Última Modificación:</strong> <?php echo formatearFechaHora($ticket["fecha_ultima_modificacion"]); ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Creado por:</strong> <?php echo htmlspecialchars($creator_nombre); ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Responsable:</strong> 
                                    <?php 
                                    if ($ticket["responsable"]) {
                                        echo '<span class="badge bg-success">' . htmlspecialchars($responsable_nombre) . '</span>';
                                    } else {
                                        echo '<span class="badge bg-secondary">Sin asignar</span>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <!-- Controles de Gestión -->
                        <div class="card border-light" style="background-color: #f9f9f9;">
                            <div class="card-body">
                                <h6 class="card-title mb-3"><strong>Gestión del Ticket</strong></h6>
                                
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label d-block"><strong>Estado:</strong></label>
                                        <form method="POST" style="display: inline;">
                                            <select name="nueva_estado" class="form-select form-select-sm" onchange="this.form.submit();">
                                                <?php foreach ($estados as $est): ?>
                                                    <option value="<?php echo htmlspecialchars($est); ?>" <?php echo $ticket["estado"] === $est ? "selected" : ""; ?>>
                                                        <?php echo ucfirst(htmlspecialchars($est)); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label class="form-label d-block"><strong>Responsable:</strong></label>
                                        <form method="POST" style="display: inline;">
                                            <select name="nuevo_responsable" class="form-select form-select-sm" onchange="this.form.submit();">
                                                <option value="">-- Sin asignar --</option>
                                                <?php foreach ($usuarios_soporte as $user): ?>
                                                    <option value="<?php echo htmlspecialchars($user["id"]); ?>" <?php echo (int)$ticket["responsable"] === (int)$user["id"] ? "selected" : ""; ?>>
                                                        <?php echo htmlspecialchars($user["username"]); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label class="form-label d-block"><strong>Propietario:</strong></label>
                                        <p class="form-control-plaintext"><span class="badge bg-info"><?php echo htmlspecialchars($propietario_nombre) ?: 'Sin asignar'; ?></span></p>
                                    </div>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <form method="POST" style="display: inline;">
                                            <?php if ($ticket["es_cerrado"] == 0): ?>
                                                <button type="submit" name="cerrar_ticket" value="cerrar" class="btn btn-danger" onclick="return confirm('¿Estás seguro de que deseas cerrar este ticket?');">
                                                    ✓ Cerrar Ticket
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" name="cerrar_ticket" value="reabrir" class="btn btn-warning">
                                                    ↺ Reabrir Ticket
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                        
                                        <!-- Botón de cancelación -->
                                        <?php if (empty($ticket["estado_cancelacion"])): ?>
                                            <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#modalCancelar" title="Cancelar este ticket">
                                                ✕ Cancelar Ticket
                                            </button>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Cancelado: <?php echo htmlspecialchars($ticket["estado_cancelacion"]); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Descripción del Ticket -->
                <div class="card mb-4">
                    <div class="card-header">
                        <strong>Descripción del Caso</strong>
                    </div>
                    <div class="card-body">
                        <p><?php echo nl2br(htmlspecialchars($ticket["descripcion"])); ?></p>
                        <small class="text-muted">Última modificación: <?php echo formatearFechaHora($ticket["fecha_ultima_modificacion"]); ?></small>
                    </div>
                </div>
                
                <!-- Comentarios -->
                <div class="card mb-4">
                    <div class="card-header">
                        <strong>Comentarios (<?php echo count($comentarios); ?>)</strong>
                    </div>
                    <div class="card-body">
                        <?php if (empty($comentarios)): ?>
                            <p class="text-muted">No hay comentarios aún</p>
                        <?php else: ?>
                            <?php foreach ($comentarios as $com): ?>
                                <div class="comentario" id="comentario-<?php echo $com['id']; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="comentario-autor"><?php echo htmlspecialchars($com["username"]); ?></div>
                                            <div class="comentario-fecha"><?php echo formatearFechaHora($com["fecha_comentario"]); ?></div>
                                            
                                            <?php if (!empty($com["fecha_modificacion"])): ?>
                                                <div class="comentario-modificado">
                                                    ✎ Editado por <?php echo htmlspecialchars($com["usuario_modifico_nombre"]); ?> el <?php echo formatearFechaHora($com["fecha_modificacion"]); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($com["usuario_id"] == $_SESSION["user_id"] || $_SESSION["role"] === "admin"): ?>
                                            <button class="btn btn-sm btn-outline-primary btn-editar-com" onclick="editarComentario(<?php echo $com['id']; ?>)">Editar</button>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mt-2" id="texto-comentario-<?php echo $com['id']; ?>">
                                        <?php echo nl2br(htmlspecialchars($com["comentario"])); ?>
                                    </div>
                                    
                                    <!-- Formulario edición -->
                                    <div class="comentario-edit-form" id="form-editar-<?php echo $com['id']; ?>">
                                        <form method="POST" action="">
                                            <textarea class="form-control mb-2" name="comentario_editado" rows="3"><?php echo htmlspecialchars($com["comentario"]); ?></textarea>
                                            <input type="hidden" name="editar_comentario_id" value="<?php echo $com['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-primary">Guardar</button>
                                            <button type="button" class="btn btn-sm btn-secondary" onclick="cancelarEditar(<?php echo $com['id']; ?>)">Cancelar</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <hr class="my-4">
                        
                        <!-- Agregar Comentario -->
                        <h5>Agregar Comentario</h5>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <textarea class="form-control" name="nuevo_comentario" rows="4" placeholder="Escribe el avance o comentario del ticket..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Agregar Comentario</button>
                        </form>
                    </div>
                </div>
                
                <div class="mb-4">
                    <a href="tickets.php" class="btn btn-secondary">Volver a Tickets</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function editarComentario(id) {
            document.getElementById('texto-comentario-' + id).style.display = 'none';
            document.getElementById('form-editar-' + id).style.display = 'block';
        }
        
        function cancelarEditar(id) {
            document.getElementById('texto-comentario-' + id).style.display = 'block';
            document.getElementById('form-editar-' + id).style.display = 'none';
        }
    </script>

    <!-- Modal de Cancelación -->
    <div class="modal fade" id="modalCancelar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cancelar Ticket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <p class="text-muted">¿Estás seguro de que deseas cancelar este ticket? Especifica un motivo:</p>
                        
                        <div class="mb-3">
                            <label for="razonCancelacion" class="form-label"><strong>Motivo de Cancelación <span class="text-danger">*</span></strong></label>
                            <select class="form-select" id="razonCancelacion" name="razon_cancelacion" required>
                                <option value="">-- Seleccionar motivo --</option>
                                <option value="Ticket Duplicado">Ticket Duplicado</option>
                                <option value="Ya Solucionado">Ya Solucionado</option>
                                <option value="Error en la Creación">Error en la Creación</option>
                                <option value="Información Incompleta">Información Incompleta</option>
                                <option value="Solicitado por Usuario">Solicitado por Usuario</option>
                                <option value="Otros">Otros</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="divOtrosCancelacion" style="display: none;">
                            <label for="especificarCancelacion" class="form-label">Especificar motivo:</label>
                            <input type="text" class="form-control" id="especificarCancelacion" name="razon_otros" placeholder="Describe el motivo de cancelación">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, Volver</button>
                        <button type="submit" name="cancelar_ticket" value="1" class="btn btn-danger" onclick="return confirm('¿Estás seguro? Esta acción no se puede deshacer fácilmente.');">Cancelar Ticket</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Mostrar input para "Otros" en cancelación
        document.getElementById('razonCancelacion').addEventListener('change', function() {
            document.getElementById('divOtrosCancelacion').style.display = this.value === 'Otros' ? 'block' : 'none';
        });
        
        // Validar que si se selecciona "Otros", el campo esté lleno
        document.querySelector('form').addEventListener('submit', function(e) {
            if (document.querySelector('[name="cancelar_ticket"]')) {
                const razon = document.getElementById('razonCancelacion').value;
                const especificar = document.getElementById('especificarCancelacion').value;
                
                if (razon === 'Otros' && !especificar.trim()) {
                    e.preventDefault();
                    alert('Debes especificar el motivo cuando seleccionas "Otros"');
                    return false;
                }
                
                if (razon === 'Otros' && especificar.trim()) {
                    document.querySelector('[name="razon_cancelacion"]').value = 'Otros: ' + especificar;
                }
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
