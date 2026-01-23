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

// Capturar mensaje de éxito del query string
if (isset($_GET["success"])) {
    if ($_GET["success"] === "comentario_editado") {
        $success = "✅ Comentario editado correctamente";
    }
}

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
        ORDER BY c.fecha DESC
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
        $responsable_anterior = $ticket["responsable"];
        
        $stmt = $conexion->prepare("UPDATE tickets SET responsable = ?, fecha_ultima_modificacion = NOW() WHERE id = ?");
        $stmt->execute([$nuevo_responsable, $ticket["id"]]);
        $success = "Responsable actualizado correctamente";
        $ticket["responsable"] = $nuevo_responsable;
        
        // Obtener nombre del usuario actual (quien asigna)
        $stmt = $conexion->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$_SESSION["user_id"]]);
        $user_actual = $stmt->fetch(PDO::FETCH_ASSOC);
        $usuario_asigna = $user_actual["username"] ?? "Sistema";
        
        // Obtener nombre del nuevo responsable
        $responsable_nombre = "Sin asignar";
        if ($nuevo_responsable) {
            $stmt = $conexion->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$nuevo_responsable]);
            $resp_user = $stmt->fetch(PDO::FETCH_ASSOC);
            $responsable_nombre = $resp_user["username"] ?? "No asignado";
        }
        
        // Agregar comentario automático de asignación
        $mensaje_asignacion = "👤 " . htmlspecialchars($usuario_asigna) . " asignó este ticket a " . htmlspecialchars($responsable_nombre);
        $stmt = $conexion->prepare("INSERT INTO comentarios_tickets (ticket_id, usuario_id, comentario, tipo_comentario) VALUES (?, ?, ?, ?)");
        $stmt->execute([$ticket["id"], $_SESSION["user_id"], $mensaje_asignacion, 'asignacion']);
        
        // Recargar comentarios
        $stmt = $conexion->prepare("
            SELECT c.*, u.username, 
                   COALESCE(um.username, '') as usuario_modifico_nombre
            FROM comentarios_tickets c
            JOIN users u ON c.usuario_id = u.id
            LEFT JOIN users um ON c.usuario_modificado_por = um.id
            WHERE c.ticket_id = ?
            ORDER BY c.fecha DESC
        ");
        $stmt->execute([$ticket["id"]]);
        $comentarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                ORDER BY c.fecha DESC
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
            // Cuando se cierra, es obligatorio un comentario
            $comentario_cierre = trim($_POST["comentario_cierre"] ?? "");
            
            if (empty($comentario_cierre)) {
                $error = "Debes escribir un comentario antes de cerrar el ticket";
            } else {
                // Actualizar estado a "ticket cerrado"
                $stmt = $conexion->prepare("UPDATE tickets SET es_cerrado = ?, estado = ? WHERE id = ?");
                $stmt->execute([1, 'ticket cerrado', $ticket["id"]]);
                
                // Agregar comentario de cierre con tipo especial
                $stmt = $conexion->prepare("INSERT INTO comentarios_tickets (ticket_id, usuario_id, comentario, tipo_comentario) VALUES (?, ?, ?, ?)");
                $stmt->execute([$ticket["id"], $_SESSION["user_id"], $comentario_cierre, 'cierre']);
                
                $success = "Ticket cerrado correctamente";
                $ticket["es_cerrado"] = 1;
                $ticket["estado"] = 'ticket cerrado';
                
                // Recargar comentarios
                $stmt = $conexion->prepare("
                    SELECT c.*, u.username, 
                           COALESCE(um.username, '') as usuario_modifico_nombre
                    FROM comentarios_tickets c
                    JOIN users u ON c.usuario_id = u.id
                    LEFT JOIN users um ON c.usuario_modificado_por = um.id
                    WHERE c.ticket_id = ?
                    ORDER BY c.fecha DESC
                ");
                $stmt->execute([$ticket["id"]]);
                $comentarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
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

// Editar comentario
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["editar_comentario_id"])) {
    $comentario_id = $_POST["editar_comentario_id"];
    $nuevo_texto = $_POST["comentario_editado"] ?? "";
    
    if (empty($nuevo_texto)) {
        $error = "El comentario no puede estar vacío";
    } else {
        try {
            // Verificar que el usuario sea el autor (solo el autor puede editar su comentario)
            $stmt = $conexion->prepare("SELECT usuario_id FROM comentarios_tickets WHERE id = ?");
            $stmt->execute([$comentario_id]);
            $com_autor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($com_autor["usuario_id"] != $_SESSION["user_id"]) {
                $error = "Solo puedes editar tus propios comentarios";
            } else {
                $stmt = $conexion->prepare("UPDATE comentarios_tickets SET comentario = ?, fecha_modificacion = NOW(), usuario_modificado_por = ? WHERE id = ?");
                $stmt->execute([$nuevo_texto, $_SESSION["user_id"], $comentario_id]);
                
                // Recargar página para mostrar cambios
                header("Location: ver_ticket.php?id=" . $ticket["id"] . "&success=comentario_editado");
                exit();
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
<html lang="es" id="htmlRoot">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/dark-mode.css" rel="stylesheet">
    <title>Ticket <?php echo htmlspecialchars($ticket["ticket_number"]); ?></title>
    <style>
        .btn-editar-com { font-size: 0.8em; padding: 0.25rem 0.5rem; }
    </style>
    <script>
        // Aplicar tema al cargar la página ANTES de mostrar contenido
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
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="container mt-4">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle text-success"></i> <?php echo htmlspecialchars($success); ?>
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
                        <div class="card border-light card-gestion">
                            <div class="card-body">
                                <h6 class="card-title mb-3"><strong>Gestión del Ticket</strong></h6>
                                
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label d-block"><strong>Estado:</strong></label>
                                        <select name="nueva_estado" class="form-select form-select-sm select-tema" onchange="cambiarEstado(this.value);">
                                            <?php foreach ($estados as $est): ?>
                                                <option value="<?php echo htmlspecialchars($est); ?>" <?php echo $ticket["estado"] === $est ? "selected" : ""; ?>>
                                                    <?php echo ucfirst(htmlspecialchars($est)); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label class="form-label d-block"><strong>Responsable:</strong></label>
                                        <select name="nuevo_responsable" class="form-select form-select-sm select-tema" onchange="cambiarResponsable(this.value);">
                                            <option value="">-- Sin asignar --</option>
                                            <?php foreach ($usuarios_soporte as $user): ?>
                                                <option value="<?php echo htmlspecialchars($user["id"]); ?>" <?php echo (int)$ticket["responsable"] === (int)$user["id"] ? "selected" : ""; ?>>
                                                    <?php echo htmlspecialchars($user["username"]); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label class="form-label d-block"><strong>Propietario:</strong></label>
                                        <p class="form-control-plaintext"><span class="badge bg-info"><?php echo htmlspecialchars($propietario_nombre) ?: 'Sin asignar'; ?></span></p>
                                    </div>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <?php if ($ticket["es_cerrado"] == 0): ?>
                                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalCerrarTicket">
                                                <i class="bi bi-check-circle"></i> Cerrar Ticket
                                            </button>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;">
                                                <button type="submit" name="cerrar_ticket" value="reabrir" class="btn btn-warning">
                                                    ↺ Reabrir Ticket
                                                </button>
                                            </form>
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
                
                <!-- Agregar Comentario - Al inicio -->
                <div class="card mb-4">
                    <div class="card-header">
                        <strong>Agregar Comentario</strong>
                    </div>
                    <div class="card-body">
                        <form onsubmit="enviarComentarioAJAX(event);">
                            <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                            <div class="mb-3">
                                <textarea class="form-control" name="nuevo_comentario" rows="4" placeholder="Escribe el avance o comentario del ticket..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Agregar Comentario</button>
                        </form>
                    </div>
                </div>
                
                <!-- Comentarios -->
                <div class="card mb-4">
                    <div class="card-header">
                        <strong>Comentarios (<?php echo count($comentarios); ?>)</strong>
                    </div>
                    <div class="card-body" id="comentarios-contenedor">
                        <?php if (empty($comentarios)): ?>
                            <p class="text-muted">No hay comentarios aún</p>
                        <?php else: ?>
                            <?php foreach ($comentarios as $com): ?>
                                <div class="comentario <?php echo ($com['tipo_comentario'] === 'cierre') ? 'comentario-cierre' : (($com['tipo_comentario'] === 'asignacion') ? 'comentario-asignacion' : (($com['tipo_comentario'] === 'mencion') ? 'comentario-mencion' : '')); ?>" id="comentario-<?php echo $com['id']; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="comentario-autor"><?php echo htmlspecialchars($com["username"]); ?></div>
                                            <div class="comentario-fecha"><?php echo formatearFechaHora($com["fecha"]); ?></div>
                                            
                                            <?php if (!empty($com["fecha_modificacion"])): ?>
                                                <div class="comentario-modificado">
                                                    ⏱️ Última edición: <?php echo formatearFechaHora($com["fecha_modificacion"]); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($com["usuario_id"] == $_SESSION["user_id"] && $com["tipo_comentario"] !== 'asignacion' && $com["tipo_comentario"] !== 'cierre'): ?>
                                            <button class="btn btn-sm btn-outline-primary btn-editar-com" onclick="editarComentario(<?php echo $com['id']; ?>)">Editar</button>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-2" id="texto-comentario-<?php echo $com['id']; ?>">
                                        <?php echo nl2br(procesarMencionesTikets($com["comentario"])); ?>
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

    <!-- Modal de Cierre de Ticket -->
    <div class="modal fade" id="modalCerrarTicket" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-check-circle"></i> Cerrar Ticket</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <p class="text-muted"><strong>⚠️ Antes de cerrar, debes escribir un comentario explicando cómo se resolvió el ticket:</strong></p>
                        
                        <div class="mb-3">
                            <label for="comentarioCierre" class="form-label"><strong>Comentario de Cierre <span class="text-danger">*</span></strong></label>
                            <textarea class="form-control" id="comentarioCierre" name="comentario_cierre" rows="5" placeholder="Describe cómo se resolvió el problema, qué acciones se tomaron, etc." required></textarea>
                            <small class="text-muted">Este comentario será visible para todos y marcará el cierre del ticket</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="cerrar_ticket" value="cerrar" class="btn btn-danger">Cerrar Ticket</button>
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
        
        // AJAX para cambiar estado
        function cambiarEstado(nuevoEstado) {
            const ticketId = document.querySelector('input[name="ticket_id"]')?.value || new URLSearchParams(window.location.search).get('id');
            
            fetch('api_ticket.php?action=cambiar_estado', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    ticket_id: ticketId,
                    estado: nuevoEstado
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    console.log('Estado actualizado');
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }
        
        // AJAX para cambiar responsable
        function cambiarResponsable(nuevoResponsable) {
            const ticketId = document.querySelector('input[name="ticket_id"]')?.value || new URLSearchParams(window.location.search).get('id');
            
            fetch('api_ticket.php?action=cambiar_responsable', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    ticket_id: ticketId,
                    responsable: nuevoResponsable
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Actualizar responsable en pantalla
                    const propietarioEl = document.querySelector('[data-responsable]');
                    if (propietarioEl) {
                        propietarioEl.textContent = data.responsable_nombre;
                    }
                    // Agregar comentario automático arriba
                    agregarComentarioDOM(data.comentario);
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }
        
        // AJAX para agregar comentario
        function enviarComentarioAJAX(e) {
            e.preventDefault();
            
            const ticketId = document.querySelector('input[name="ticket_id"]')?.value || new URLSearchParams(window.location.search).get('id');
            const comentario = document.querySelector('textarea[name="nuevo_comentario"]').value;
            
            fetch('api_ticket.php?action=agregar_comentario', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    ticket_id: ticketId,
                    comentario: comentario
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.querySelector('textarea[name="nuevo_comentario"]').value = '';
                    agregarComentarioDOM(data.comentario);
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }
        
        // Agregar comentario al DOM sin recargar
        function agregarComentarioDOM(com) {
            const contenedor = document.querySelector('#comentarios-contenedor');
            if (!contenedor) return;
            
            const tipoClase = com.tipo_comentario === 'cierre' ? 'comentario-cierre' : (com.tipo_comentario === 'asignacion' ? 'comentario-asignacion' : (com.tipo_comentario === 'mencion' ? 'comentario-mencion' : ''));
            
            // Procesar menciones de tickets (#DCDXXXXXX) y activos (#AKXXXXXXX)
            let comentarioConMenciones = com.comentario
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/#(DCD\d{6})/gi, '<a href="ver_ticket.php?id=$1" class="ticket-mention">$1</a>')
                .replace(/#(AK\d{7})/gi, '<a href="ver_activo.php?id=$1" class="ticket-mention">$1</a>');
            
            const html = `
                <div class="comentario ${tipoClase}" id="comentario-${com.id}">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="comentario-autor">${com.username}</div>
                            <div class="comentario-fecha">${formatearFechaCliente(com.fecha)}</div>
                        </div>
                    </div>
                    <div class="mt-2" id="texto-comentario-${com.id}">
                        ${comentarioConMenciones}
                    </div>
                </div>
            `;
            
            // Insertar al inicio del contenedor
            contenedor.insertAdjacentHTML('afterbegin', html);
        }
        
        // Función para formatear fecha en cliente
        function formatearFechaCliente(fecha) {
            const date = new Date(fecha);
            const meses = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];
            const mes = meses[date.getMonth()];
            const dia = String(date.getDate()).padStart(2, '0');
            const año = date.getFullYear();
            const horas = String(date.getHours()).padStart(2, '0');
            const minutos = String(date.getMinutes()).padStart(2, '0');
            return `${mes} ${dia} ${año} - ${horas}:${minutos}`;
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
