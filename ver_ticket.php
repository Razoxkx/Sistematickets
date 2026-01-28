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
        $estado_anterior = $ticket["estado"];
        $estado_nuevo = $_POST["nueva_estado"];
        
        // Actualizar estado en tickets
        $stmt = $conexion->prepare("UPDATE tickets SET estado = ? WHERE id = ?");
        $stmt->execute([$estado_nuevo, $ticket["id"]]);
        
        // Registrar cambio en historial
        $stmt_historial = $conexion->prepare("
            INSERT INTO historial_estados_tickets (ticket_id, estado_anterior, estado_nuevo, usuario_id)
            VALUES (?, ?, ?, ?)
        ");
        $stmt_historial->execute([$ticket["id"], $estado_anterior, $estado_nuevo, $_SESSION["user_id"]]);
        
        $success = "Estado actualizado correctamente";
        $ticket["estado"] = $estado_nuevo;
    } catch (PDOException $e) {
        $error = "Error al actualizar estado: " . $e->getMessage();
    }
}

// Cambiar responsable
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["nuevo_responsable"])) {
    try {
        // Validación: prevenir cambio de responsable en tickets cerrados para no-admins
        if ($ticket["estado"] === "ticket cerrado" && $_SESSION["role"] !== "admin") {
            $error = "No puedes cambiar el responsable de un ticket cerrado. Solo administradores pueden hacerlo.";
        } else {
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
            $comentario_id = $conexion->lastInsertId();
            
            // Detectar menciones de procedimientos (formato: #DCD.T0000001)
            if (preg_match_all('/#(DCD\.T\d{7})/i', $comentario, $matches)) {
                foreach ($matches[1] as $id_procedimiento) {
                    // Obtener el ID interno del procedimiento
                    $stmt_proc = $conexion->prepare("SELECT id FROM procedimientos WHERE id_procedimiento = ?");
                    $stmt_proc->execute([$id_procedimiento]);
                    $proc = $stmt_proc->fetch(PDO::FETCH_ASSOC);
                    
                    if ($proc) {
                        // Registrar la mención
                        $stmt_mencion = $conexion->prepare("
                            INSERT INTO menciones_procedimientos (procedimiento_id, tipo_mencion, ticket_id, comentario_id)
                            VALUES (?, 'ticket_comentario', ?, ?)
                        ");
                        $stmt_mencion->execute([$proc["id"], $ticket["id"], $comentario_id]);
                    }
                }
            }
            
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
                $estado_anterior = $ticket["estado"];
                
                // Actualizar estado a "ticket cerrado"
                $stmt = $conexion->prepare("UPDATE tickets SET es_cerrado = ?, estado = ? WHERE id = ?");
                $stmt->execute([1, 'ticket cerrado', $ticket["id"]]);
                
                // Registrar cambio en historial
                $stmt_historial = $conexion->prepare("
                    INSERT INTO historial_estados_tickets (ticket_id, estado_anterior, estado_nuevo, usuario_id)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt_historial->execute([$ticket["id"], $estado_anterior, 'ticket cerrado', $_SESSION["user_id"]]);
                
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
            $estado_anterior = $ticket["estado"];
            
            $stmt = $conexion->prepare("UPDATE tickets SET es_cerrado = ?, estado = ? WHERE id = ?");
            $stmt->execute([0, 'sin abrir', $ticket["id"]]);
            
            // Registrar cambio en historial
            $stmt_historial = $conexion->prepare("
                INSERT INTO historial_estados_tickets (ticket_id, estado_anterior, estado_nuevo, usuario_id)
                VALUES (?, ?, ?, ?)
            ");
            $stmt_historial->execute([$ticket["id"], $estado_anterior, 'sin abrir', $_SESSION["user_id"]]);
            
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

// Editar descripción del ticket (solo admins)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["editar_descripcion_id"])) {
    // Validar que sea admin
    if ($_SESSION["role"] !== "admin") {
        $error = "Solo los administradores pueden editar la descripción";
    } else {
        $descripcion_nueva = $_POST["descripcion_editada"] ?? "";
        
        if (empty($descripcion_nueva)) {
            $error = "La descripción no puede estar vacía";
        } else {
            try {
                $stmt = $conexion->prepare("UPDATE tickets SET descripcion = ?, fecha_ultima_modificacion = NOW() WHERE id = ?");
                $stmt->execute([$descripcion_nueva, $ticket["id"]]);
                
                // Agregar comentario automático de edición
                $usuario_actual = htmlspecialchars($_SESSION["username"]);
                $mensaje_edicion = "📝 " . $usuario_actual . " editó la descripción del caso";
                $stmt = $conexion->prepare("INSERT INTO comentarios_tickets (ticket_id, usuario_id, comentario, tipo_comentario) VALUES (?, ?, ?, ?)");
                $stmt->execute([$ticket["id"], $_SESSION["user_id"], $mensaje_edicion, 'edicion']);
                
                $success = "Descripción actualizada correctamente";
                $ticket["descripcion"] = $descripcion_nueva;
                
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
                $error = "Error al editar descripción: " . $e->getMessage();
            }
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="css/dark-mode.css" rel="stylesheet">
    <title>Ticket <?php echo htmlspecialchars($ticket["ticket_number"]); ?></title>
    <style>
        body {
            background: linear-gradient(to bottom, #f8f9fa, #ffffff);
        }
        
        [data-bs-theme="dark"] body {
            background: linear-gradient(to bottom, #1a1a1a, #0d0d0d);
        }
        
        .ticket-header-gradient {
            background: white;
            color: #333;
            padding: 30px 50px;
            margin: 0;
            border-radius: 0;
        }
        
        [data-bs-theme="dark"] .ticket-header-gradient {
            background: #1e1e1e;
            color: #e0e0e0;
        }
        
        .ticket-header-gradient h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0;
            color: #667eea;
        }
        
        [data-bs-theme="dark"] .ticket-header-gradient h2 {
            color: #8b9dff;
        }
        
        .ticket-header-gradient h4 {
            font-size: 1.3rem;
            font-weight: 500;
            margin-bottom: 15px;
            opacity: 0.95;
        }
        
        [data-bs-theme="dark"] .ticket-header-gradient h4 {
            color: #e0e0e0;
        }
        
        [data-bs-theme="dark"] .ticket-header-gradient .col-lg-7 > div:nth-child(2) {
            background: rgba(139, 157, 255, 0.1) !important;
            border-left-color: #8b9dff !important;
        }
        
        [data-bs-theme="dark"] .ticket-header-gradient .col-lg-7 > div:nth-child(2) strong {
            color: #8b9dff !important;
        }
        
        [data-bs-theme="dark"] .ticket-header-gradient .col-lg-7 > div:nth-child(2) span {
            color: #e0e0e0 !important;
        }
        
        [data-bs-theme="dark"] .ticket-header-gradient .col-lg-7 > div:nth-child(3) {
            color: #e0e0e0;
        }
        
        [data-bs-theme="dark"] .ticket-header-gradient .col-lg-7 > div:nth-child(3) strong {
            color: #8b9dff;
        }
        
        [data-bs-theme="dark"] .ticket-header-gradient .col-lg-7 > div:nth-child(3) div {
            color: #b0b0b0;
        }
        
        .ticket-meta {
            padding: 20px 50px;
            background: white;
        }
        
        [data-bs-theme="dark"] .ticket-meta {
            background: #1e1e1e;
        }
        
        .ticket-info-section {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }
        
        .info-item {
            flex: 1;
            min-width: 200px;
        }
        
        .info-item strong {
            color: #667eea;
            font-weight: 600;
        }
        
        [data-bs-theme="dark"] .info-item strong {
            color: #8b9dff;
        }
        
        /* [data-bs-theme="dark"] .ticket-header-gradient > .row > .col-lg-5 {
            background: #262626;
            padding: 20px;
            border-radius: 8px;
        } */
        
        [data-bs-theme="dark"] .ticket-header-gradient > .row > .col-lg-5 h6 {
            color: #8b9dff;
        }
        
        [data-bs-theme="dark"] .ticket-header-gradient > .row > .col-lg-5 .form-select {
            background-color: #1a1a1a;
            border-color: #444;
            color: #e0e0e0;
        }
        
        [data-bs-theme="dark"] .ticket-header-gradient > .row > .col-lg-5 .btn-outline-secondary {
            color: #8b9dff;
            border-color: #444;
        }
        
        [data-bs-theme="dark"] .ticket-header-gradient > .row > .col-lg-5 .btn-outline-secondary:hover {
            background-color: #444;
            border-color: #555;
        }
        
        [data-bs-theme="dark"] .ticket-header-gradient > .row > .col-lg-5 > div {
            color: #e0e0e0;
        }
        
        [data-bs-theme="dark"] .ticket-header-gradient > .row > .col-lg-5 > div > div {
            background: #1a1a1a !important;
            border-color: #444 !important;
            color: #e0e0e0;
        }
        
        [data-bs-theme="dark"] .ticket-header-gradient > .row > .col-lg-5 .badge {
            background-color: #0d9b9e !important;
            color: white;
        }
        
        [data-bs-theme="dark"] .ticket-header-gradient > .row > .col-lg-5 .btn-danger {
            background-color: #c82333;
            border-color: #c82333;
        }
        
        [data-bs-theme="dark"] .ticket-header-gradient > .row > .col-lg-5 .btn-danger:hover {
            background-color: #a01e2a;
            border-color: #a01e2a;
        }
        
        [data-bs-theme="dark"] .ticket-header-gradient > .row > .col-lg-5 .btn-warning {
            background-color: #cc8800;
            border-color: #cc8800;
            color: white;
        }
        
        [data-bs-theme="dark"] .ticket-header-gradient > .row > .col-lg-5 .btn-warning:hover {
            background-color: #b37700;
            border-color: #b37700;
        }
        
        [data-bs-theme="dark"] .ticket-header-gradient h6 {
            color: #8b9dff;
        }
        
        [data-bs-theme="dark"] .ticket-header-gradient .form-select {
            background-color: #1a1a1a;
            border-color: #444;
            color: #e0e0e0;
        }
        
        #copyTicketBtn {
            background-color: rgba(102, 126, 234, 0.1) !important;
            border-color: #667eea !important;
            color: #667eea !important;
        }
        
        #copyTicketBtn:hover {
            background-color: rgba(102, 126, 234, 0.2) !important;
        }
        
        [data-bs-theme="dark"] #copyTicketBtn {
            background-color: rgba(139, 157, 255, 0.2) !important;
            border-color: #8b9dff !important;
            color: #8b9dff !important;
        }
        
        [data-bs-theme="dark"] #copyTicketBtn:hover {
            background-color: rgba(139, 157, 255, 0.3) !important;
        }
        
        /* Estilos Light Mode para Gestión */
        .gestion-box .form-select,
        .gestion-box .select-tema {
            background-color: white;
            border-color: #ddd;
            color: #333;
            border: 1px solid #ddd;
        }
        
        .gestion-box .form-label {
            color: #333;
            font-weight: 500;
        }
        
        /* Estilos Dark Mode para Gestión */
        [data-bs-theme="dark"] .gestion-box {
            background: #262626 !important;
            border-left-color: #8b9dff !important;
        }
        
        [data-bs-theme="dark"] .gestion-box h6 {
            color: #8b9dff;
        }
        
        [data-bs-theme="dark"] .gestion-box .form-label {
            color: #b0b0b0 !important;
        }
        
        [data-bs-theme="dark"] .gestion-box .form-label strong {
            color: #b0b0b0 !important;
        }
        
        [data-bs-theme="dark"] .gestion-box .form-select,
        [data-bs-theme="dark"] .gestion-box .select-tema {
            background-color: #333333 !important;
            border-color: #555 !important;
            color: #e0e0e0 !important;
            border: 1px solid #555 !important;
        }
        
        [data-bs-theme="dark"] .gestion-box .form-select:focus,
        [data-bs-theme="dark"] .gestion-box .select-tema:focus {
            background-color: #333333 !important;
            border-color: #8b9dff !important;
            color: #e0e0e0 !important;
            box-shadow: 0 0 0 0.25rem rgba(139, 157, 255, 0.25);
        }
        
        [data-bs-theme="dark"] .gestion-box .btn-outline-secondary {
            color: #8b9dff;
            border-color: #555;
            background-color: transparent;
        }
        
        [data-bs-theme="dark"] .gestion-box .btn-outline-secondary:hover {
            background-color: #333333;
            border-color: #8b9dff;
            color: #8b9dff;
        }
        
        [data-bs-theme="dark"] .propietario-box {
            background: #333333 !important;
            border-color: #555 !important;
        }
        
        [data-bs-theme="dark"] .gestion-box .badge.bg-info {
            background-color: #0d9b9e !important;
            color: white;
        }
        
        [data-bs-theme="dark"] .gestion-box .btn-danger {
            background-color: #c82333;
            border-color: #c82333;
        }
        
        [data-bs-theme="dark"] .gestion-box .btn-danger:hover {
            background-color: #a01e2a;
            border-color: #a01e2a;
        }
        
        [data-bs-theme="dark"] .gestion-box .btn-warning {
            background-color: #cc8800;
            border-color: #cc8800;
            color: white;
        }
        
        [data-bs-theme="dark"] .gestion-box .btn-warning:hover {
            background-color: #b37700;
            border-color: #b37700;
        }
        
        .ticket-content {
            padding: 30px 50px;
        }
        
        [data-bs-theme="dark"] .ticket-content {
            background: #0d0d0d;
        }
        
        /* Estilos para Descripción del Caso - Light Mode */
        .ticket-descripcion-case {
            padding: 30px 50px;
        }
        
        .ticket-descripcion-case h5 {
            color: #667eea;
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .descripcion-contenido {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.08) 0%, rgba(102, 126, 234, 0.04) 100%);
            border-left: 4px solid #667eea;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.12);
            margin-bottom: 30px;
        }
        
        .descripcion-contenido p {
            margin-bottom: 15px;
            line-height: 1.8;
            white-space: pre-wrap;
            color: #2c3e50;
            font-size: 1.05rem;
            font-weight: 500;
        }
        
        .texto-muted-case {
            color: #7f8c8d;
            font-size: 0.85rem;
        }
        
        /* Estilos para Descripción del Caso - Dark Mode */
        [data-bs-theme="dark"] .ticket-descripcion-case h5 {
            color: #8b9dff;
        }
        
        [data-bs-theme="dark"] .descripcion-contenido {
            background: linear-gradient(135deg, rgba(139, 157, 255, 0.15) 0%, rgba(139, 157, 255, 0.08) 100%);
            border-left-color: #8b9dff;
            box-shadow: 0 2px 8px rgba(139, 157, 255, 0.2);
        }
        
        [data-bs-theme="dark"] .descripcion-contenido p {
            color: #e0e0e0;
        }
        
        [data-bs-theme="dark"] .texto-muted-case {
            color: #a0a0a0;
        }
        
        [data-bs-theme="dark"] .ticket-descripcion-case h5 {
            color: #8b9dff;
        }
        
        /* Botón de editar descripción */
        .btn-outline-primary {
            color: #667eea;
            border-color: #667eea;
        }
        
        .btn-outline-primary:hover {
            background-color: #667eea;
            border-color: #667eea;
            color: white;
        }
        
        [data-bs-theme="dark"] .btn-outline-primary {
            color: #8b9dff;
            border-color: #8b9dff;
        }
        
        [data-bs-theme="dark"] .btn-outline-primary:hover {
            background-color: #8b9dff;
            border-color: #8b9dff;
            color: #1e1e1e;
        }
        
        .ticket-body-card {
            background: white;
            border-left: 5px solid #667eea;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        [data-bs-theme="dark"] .ticket-body-card {
            background: #1e1e1e;
            border-left-color: #8b9dff;
            color: #e0e0e0;
        }
        
        [data-bs-theme="dark"] .ticket-body-card h5 {
            color: #8b9dff;
        }
        
        .btn-editar-com { 
            font-size: 0.8em; 
            padding: 0.25rem 0.5rem; 
        }
        
        .comentario {
            background: white;
            border-left: 4px solid #e9ecef;
            padding: 15px 20px;
            margin-bottom: 15px;
            border-radius: 6px;
        }
        
        [data-bs-theme="dark"] .comentario {
            background: #262626;
            border-left-color: #444;
            color: #e0e0e0;
        }
        
        .comentario.comentario-cierre {
            border-left-color: #28a745;
            background: #f0f8f4;
        }
        
        [data-bs-theme="dark"] .comentario.comentario-cierre {
            background: rgba(40, 167, 69, 0.15);
            border-left-color: #51cf66;
        }
        
        .comentario.comentario-asignacion {
            border-left-color: #0d6efd;
            background: #f0f6ff;
        }
        
        [data-bs-theme="dark"] .comentario.comentario-asignacion {
            background: rgba(13, 110, 253, 0.15);
            border-left-color: #4dabf7;
        }
        
        .comentario.comentario-edicion {
            border-left-color: #6f42c1;
            background: #f8f5ff;
        }
        
        [data-bs-theme="dark"] .comentario.comentario-edicion {
            background: rgba(111, 66, 193, 0.15);
            border-left-color: #b197fc;
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
            border-color: #667eea;
            color: #e0e0e0;
        }
        
        [data-bs-theme="dark"] .text-muted {
            color: #999 !important;
        }
        
        /* Estilos para el botón copiar */
        #copyTicketBtn {
            background-color: rgba(255, 255, 255, 0.8);
            border: none;
        }
        
        #copyTicketBtn:hover {
            background-color: rgba(255, 255, 255, 1);
        }
        
        [data-bs-theme="dark"] #copyTicketBtn {
            background-color: rgba(139, 157, 255, 0.2);
            color: #e0e0e0;
            border: 1px solid rgba(139, 157, 255, 0.3);
        }
        
        [data-bs-theme="dark"] #copyTicketBtn:hover {
            background-color: rgba(139, 157, 255, 0.3);
            border-color: rgba(139, 157, 255, 0.5);
        }
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
    
    
    <div class="container-fluid p-0">
        <!-- Alerts al inicio -->
        <div style="padding: 20px 50px;">
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
        </div>
        
        <!-- Header y Gestión en una sola sección -->
        <div class="ticket-header-gradient" style="padding: 30px 50px;">
            <div class="row">
                <!-- Izquierda: Información del Ticket -->
                <div class="col-lg-7">
                    <div style="display: flex; align-items: center; gap: 0.8rem; margin-bottom: 15px;">
                        <h2 style="margin: 0; color: #667eea;"><?php echo htmlspecialchars($ticket["ticket_number"]); ?></h2>
                        <button class="btn btn-sm btn-outline-secondary" id="copyTicketBtn" onclick="copiarNumeroTicket('<?php echo htmlspecialchars($ticket["ticket_number"]); ?>')" 
                                style="padding: 0.4rem 0.5rem; opacity: 0.9; transition: all 0.2s;" 
                                title="Copiar número de ticket"
                                data-bs-toggle="tooltip" 
                                data-bs-title="Copiar número">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                    <h4 id="titulo-ticket" style="margin: 0; margin-bottom: 15px;"><?php echo htmlspecialchars($ticket["titulo"]); ?></h4>
                    <div style="background: #f0f6ff; padding: 10px 14px; border-radius: 6px; border-left: 4px solid #667eea; font-size: 0.95rem; margin-bottom: 15px;">
                        <strong style="color: #667eea;">📋 Reportado por:</strong> 
                        <span style="font-weight: 500; color: #0056b3;">
                            <?php echo htmlspecialchars($ticket["nombre_solicitante"] ?? "N/A"); ?>
                        </span>
                    </div>
                    <!-- Información Meta Compacta -->
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; font-size: 0.9rem;">
                        <div>
                            <strong style="color: #667eea;">Creado:</strong> 
                            <div style="margin-top: 4px; color: #555;"><?php echo formatearFechaHora($ticket["fecha_creacion"]); ?></div>
                        </div>
                        <div>
                            <strong style="color: #667eea;">Modificado:</strong> 
                            <div style="margin-top: 4px; color: #555;"><?php echo formatearFechaHora($ticket["fecha_ultima_modificacion"]); ?></div>
                        </div>
                        <div>
                            <strong style="color: #667eea;">Creado por:</strong> 
                            <div style="margin-top: 4px; color: #555;"><?php echo htmlspecialchars($creator_nombre); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Derecha: Botón Gestión Discreto -->
                <div class="col-lg-5" style="display: flex; align-items: flex-start; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalGestion" title="Gestionar ticket" style="padding: 0.4rem 0.8rem;">
                        <i class="bi bi-sliders"></i> Gestión
                    </button>
                    <?php if ($ticket["es_cerrado"] == 0): ?>
                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#modalCerrarTicket" title="Cerrar ticket">
                            <i class="bi bi-check-circle"></i> Cerrar
                        </button>
                    <?php else: ?>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="cerrar_ticket" value="reabrir" class="btn btn-sm btn-warning" title="Reabrir ticket">
                                ↺ Reabrir
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Descripción del Ticket -->
        <div class="ticket-descripcion-case">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h5 style="margin: 0;"><i class="bi bi-file-text"></i> <strong>Descripción del Caso</strong></h5>
                    <?php if ($_SESSION["role"] === "admin"): ?>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditarDescripcion" title="Editar descripción">
                            <i class="bi bi-pencil"></i> Editar
                        </button>
                    <?php endif; ?>
                </div>
                <div class="descripcion-contenido">
                    <p id="texto-descripcion"><?php echo htmlspecialchars($ticket["descripcion"]); ?></p>
                    <small class="texto-muted-case"><i class="bi bi-clock"></i> Última modificación: <?php echo formatearFechaHora($ticket["fecha_ultima_modificacion"]); ?></small>
                </div>
            </div>
            
            <!-- Agregar Comentario -->
            <div class="ticket-body-card">
                <h5 style="margin-bottom: 20px; color: #667eea;"><i class="bi bi-chat-left"></i> <strong>Agregar Comentario</strong></h5>
                <form onsubmit="enviarComentarioAJAX(event);">
                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                    <div class="mb-3">
                        <textarea class="form-control" name="nuevo_comentario" rows="4" placeholder="Escribe el avance o comentario del ticket..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Agregar Comentario</button>
                </form>
            </div>
            
            <!-- Comentarios -->
            <div class="ticket-body-card">
                <h5 style="margin-bottom: 20px; color: #667eea;"><i class="bi bi-chat-dots"></i> <strong>Comentarios (<?php echo count($comentarios); ?>)</strong></h5>
                <div id="comentarios-contenedor">
                    <?php if (empty($comentarios)): ?>
                        <p class="text-muted"><i class="bi bi-info-circle"></i> No hay comentarios aún</p>
                    <?php else: ?>
                        <?php foreach ($comentarios as $com): ?>
                            <div class="comentario <?php echo ($com['tipo_comentario'] === 'cierre') ? 'comentario-cierre' : (($com['tipo_comentario'] === 'asignacion') ? 'comentario-asignacion' : (($com['tipo_comentario'] === 'edicion') ? 'comentario-edicion' : (($com['tipo_comentario'] === 'mencion') ? 'comentario-mencion' : ''))); ?>" id="comentario-<?php echo $com['id']; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="comentario-autor"><strong><?php echo htmlspecialchars($com["username"]); ?></strong></div>
                                        <div class="comentario-fecha" style="font-size: 0.85rem; color: #6c757d;"><?php echo formatearFechaHora($com["fecha"]); ?></div>
                                        
                                        <?php if (!empty($com["fecha_modificacion"])): ?>
                                            <div class="comentario-modificado" style="font-size: 0.8rem; color: #6c757d; margin-top: 3px;">
                                                ⏱️ Editado: <?php echo formatearFechaHora($com["fecha_modificacion"]); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($com["usuario_id"] == $_SESSION["user_id"] && $com["tipo_comentario"] !== 'asignacion' && $com["tipo_comentario"] !== 'cierre'): ?>
                                        <button class="btn btn-sm btn-outline-primary btn-editar-com" onclick="editarComentario(<?php echo $com['id']; ?>)">Editar</button>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-2" id="texto-comentario-<?php echo $com['id']; ?>" style="line-height: 1.6;">
                                    <?php echo nl2br(procesarMencionesTikets($com["comentario"])); ?>
                                </div>
                                
                                <!-- Formulario edición -->
                                <div class="comentario-edit-form" id="form-editar-<?php echo $com['id']; ?>" style="display: none;">
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
                </div>
            </div>
            
            <!-- Botón de Retorno -->
            <div style="padding: 30px 50px;">
                <a href="tickets.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Volver a Tickets
                </a>
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

    <!-- Modal de Historial de Estados -->
    <div class="modal fade" id="modalHistorialEstados" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="bi bi-clock-history"></i> Historial de Cambios de Estado</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="contenedorHistorial" style="min-height: 200px;">
                        <div class="text-center text-muted">
                            <div class="spinner-border spinner-border-sm" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                            <p class="mt-2">Cargando historial...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Función para escapar HTML
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Función para obtener el color del estado (igual a tickets.php)
        function getEstadoColor(estado) {
            const colores = {
                'sin abrir': 'secondary',
                'en conocimiento': 'info',
                'en proceso': 'warning',
                'ticket cerrado': 'success',
                'pendiente de cierre': 'danger'
            };
            return colores[estado] || 'secondary';
        }
        
        // Cargar historial de estados
        document.getElementById('modalHistorialEstados')?.addEventListener('show.bs.modal', function() {
            const ticketId = document.querySelector('input[name="ticket_id"]')?.value || 
                            new URLSearchParams(window.location.search).get('id');
            
            if (!ticketId) {
                document.getElementById('contenedorHistorial').innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> Error: No se puede obtener el ID del ticket.</div>';
                return;
            }
            
            fetch('api_ticket.php?action=obtener_historial_estados&ticket_id=' + encodeURIComponent(ticketId))
                .then(r => r.json())
                .then(data => {
                    const contenedor = document.getElementById('contenedorHistorial');
                    
                    if (data.success && data.historial && data.historial.length > 0) {
                        let html = '<div class="timeline">';
                        
                        data.historial.forEach((cambio, index) => {
                            const estadoAnterior = cambio.estado_anterior || 'Inicial';
                            const estadoNuevo = cambio.estado_nuevo;
                            const usuario = cambio.usuario_nombre;
                            const fecha = new Date(cambio.fecha_cambio).toLocaleString('es-ES', {
                                day: '2-digit',
                                month: 'short',
                                year: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit'
                            });
                            
                            html += `
                                <div class="mb-3">
                                    <div class="d-flex gap-3">
                                        <div style="text-align: center; width: 40px;">
                                            <div style="width: 12px; height: 12px; border-radius: 50%; background-color: #0d6efd; margin-left: 14px;"></div>
                                            ${index < data.historial.length - 1 ? '<div style="width: 2px; height: 30px; background-color: #dee2e6; margin-left: 18px;"></div>' : ''}
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="card border-light">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <div>
                                                            <span class="badge bg-${getEstadoColor(estadoAnterior)}">${escapeHtml(estadoAnterior)}</span>
                                                            <i class="bi bi-arrow-right"></i>
                                                            <span class="badge bg-${getEstadoColor(estadoNuevo)}">${escapeHtml(estadoNuevo)}</span>
                                                        </div>
                                                        <small class="text-muted">${fecha}</small>
                                                    </div>
                                                    <p class="mb-0 small">
                                                        <strong>Por:</strong> <span class="text-primary">${escapeHtml(usuario)}</span>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        
                        html += '</div>';
                        contenedor.innerHTML = html;
                    } else {
                        contenedor.innerHTML = '<div class="alert alert-info"><i class="bi bi-info-circle"></i> No hay cambios de estado registrados.</div>';
                    }
                })
                .catch(error => {
                    console.error('Error al cargar historial:', error);
                    document.getElementById('contenedorHistorial').innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> Error al cargar el historial: ' + error.message + '</div>';
                });
        });
    </script>

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
        
        // AJAX para guardar descripción en tiempo real
        function guardarDescripcionAJAX(e) {
            e.preventDefault();
            
            const ticketId = new URLSearchParams(window.location.search).get('id') || document.querySelector('input[name="ticket_id"]')?.value;
            const descripcion = document.getElementById('descripcionEditada').value;
            const titulo = document.getElementById('tituloEditadoModal').value;
            const alertaDiv = document.getElementById('alertaDescripcion');
            const btnGuardar = document.getElementById('btnGuardarDescripcion');
            
            if (!descripcion.trim()) {
                alertaDiv.innerHTML = '<div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-circle"></i> La descripción no puede estar vacía</div>';
                alertaDiv.style.display = 'block';
                return;
            }
            
            if (!titulo.trim()) {
                alertaDiv.innerHTML = '<div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-circle"></i> El título no puede estar vacío</div>';
                alertaDiv.style.display = 'block';
                return;
            }
            
            // Mostrar estado de carga
            btnGuardar.disabled = true;
            const textoOriginal = btnGuardar.innerHTML;
            btnGuardar.innerHTML = '<i class="bi bi-hourglass-split"></i> Guardando...';
            
            fetch('api_ticket.php?action=editar_descripcion', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    ticket_id: ticketId,
                    descripcion: descripcion,
                    titulo: titulo
                })
            })
            .then(r => r.json())
            .then(data => {
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = textoOriginal;
                
                if (data.success) {
                    // Actualizar descripción en el DOM
                    const textoDescripcion = document.getElementById('texto-descripcion');
                    if (textoDescripcion) {
                        textoDescripcion.textContent = descripcion;
                    }
                    
                    // Actualizar título en el DOM
                    const tituloTicket = document.getElementById('titulo-ticket');
                    if (tituloTicket) {
                        tituloTicket.textContent = titulo;
                    }
                    
                    // Agregar comentario automático de edición al DOM
                    if (data.comentario) {
                        agregarComentarioDOM(data.comentario);
                    }
                    
                    // Cerrar modal inmediatamente
                    const modal = bootstrap.Modal.getInstance(document.getElementById('modalEditarDescripcion'));
                    if (modal) {
                        modal.hide();
                    }
                } else {
                    alertaDiv.innerHTML = '<div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-circle"></i> Error: ' + (data.error || 'No se pudo guardar') + '</div>';
                    alertaDiv.style.display = 'block';
                }
            })
            .catch(error => {
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = textoOriginal;
                alertaDiv.innerHTML = '<div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-circle"></i> Error: ' + error.message + '</div>';
                alertaDiv.style.display = 'block';
            });
        }
        
        // Agregar comentario al DOM sin recargar
        function agregarComentarioDOM(com) {
            const contenedor = document.querySelector('#comentarios-contenedor');
            if (!contenedor) return;
            
            const tipoClase = com.tipo_comentario === 'cierre' ? 'comentario-cierre' : (com.tipo_comentario === 'asignacion' ? 'comentario-asignacion' : (com.tipo_comentario === 'edicion' ? 'comentario-edicion' : (com.tipo_comentario === 'mencion' ? 'comentario-mencion' : '')));
            
            // Procesar menciones: tickets (#DCDXXXXXX), activos (#AKXXXXXXX) y usuarios (#usuario)
            let comentarioConMenciones = com.comentario
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                // Menciones de tickets
                .replace(/#(DCD\d{6})/gi, '<a href="ver_ticket.php?id=$1" class="ticket-mention" title="Ver ticket $1">#$1</a>')
                // Menciones de activos
                .replace(/#(AK\d{7})/gi, '<a href="ver_activo.php?id=$1" class="activo-mention" title="Ver activo $1">#$1</a>')
                // Menciones de usuarios (cualquier #usuario que no sea ticket o activo)
                .replace(/#([a-z0-9._-]+)/gi, function(match, usuario) {
                    // Evitar procesar tickets y activos que ya fueron procesados
                    if (/^(DCD\d{6}|AK\d{7})$/i.test(usuario)) {
                        return match;
                    }
                    return '<a href="perfil_usuario.php?username=' + encodeURIComponent(usuario) + '" class="usuario-mention" title="Ver perfil de ' + usuario + '">#' + usuario + '</a>';
                });
            
            // Determinar si mostrar botón de editar
            const userId = <?php echo $_SESSION["user_id"]; ?>;
            const puedeEditar = (com.usuario_id == userId && com.tipo_comentario !== 'asignacion' && com.tipo_comentario !== 'cierre');
            const botonEditar = puedeEditar ? `<button class="btn btn-sm btn-outline-primary btn-editar-com" onclick="editarComentario(${com.id})">Editar</button>` : '';
            
            const html = `
                <div class="comentario ${tipoClase}" id="comentario-${com.id}">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="comentario-autor"><strong>${com.username}</strong></div>
                            <div class="comentario-fecha" style="font-size: 0.85rem; color: #6c757d;">${formatearFechaCliente(com.fecha)}</div>
                        </div>
                        ${botonEditar}
                    </div>
                    <div class="mt-2" id="texto-comentario-${com.id}">
                        ${comentarioConMenciones}
                    </div>
                    <div class="comentario-edit-form" id="form-editar-${com.id}" style="display: none;">
                        <form method="POST" action="">
                            <textarea class="form-control mb-2" name="comentario_editado" rows="3">${com.comentario.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</textarea>
                            <input type="hidden" name="editar_comentario_id" value="${com.id}">
                            <button type="submit" class="btn btn-sm btn-primary">Guardar</button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="cancelarEditar(${com.id})">Cancelar</button>
                        </form>
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
        
        // Función para copiar número de ticket
        function copiarNumeroTicket(numeroTicket) {
            navigator.clipboard.writeText(numeroTicket).then(() => {
                const btn = document.getElementById('copyTicketBtn');
                const icon = btn.querySelector('i');
                const originalOpacity = btn.style.opacity;
                const originalIcon = icon.className;
                
                // Cambiar icono a checkmark
                icon.className = 'bi bi-check';
                btn.style.opacity = '1';
                btn.classList.add('text-success');
                
                // Cambiar tooltip
                const tooltip = bootstrap.Tooltip.getInstance(btn);
                if (tooltip) tooltip.dispose();
                btn.setAttribute('data-bs-title', '¡Copiado!');
                new bootstrap.Tooltip(btn);
                
                // Restaurar después de 2 segundos
                setTimeout(() => {
                    icon.className = originalIcon;
                    btn.style.opacity = originalOpacity;
                    btn.classList.remove('text-success');
                    
                    const tooltip = bootstrap.Tooltip.getInstance(btn);
                    if (tooltip) tooltip.dispose();
                    btn.setAttribute('data-bs-title', 'Copiar número');
                    new bootstrap.Tooltip(btn);
                }, 2000);
            }).catch(() => {
                alert('Error al copiar');
            });
        }
        
        // Inicializar tooltips
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>

    <!-- Modal Editar Descripción -->
    <div class="modal fade" id="modalEditarDescripcion" tabindex="-1" aria-labelledby="modalEditarDescripcionLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditarDescripcionLabel"><i class="bi bi-pencil-square"></i> Editar Descripción del Caso</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formEditarDescripcion" onsubmit="guardarDescripcionAJAX(event);">
                    <div class="modal-body">
                        <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                        <div class="mb-3">
                            <label for="tituloEditadoModal" class="form-label"><strong>Título del Ticket</strong></label>
                            <input type="text" class="form-control" id="tituloEditadoModal" name="titulo_editado_modal" placeholder="Edita el título del ticket..." required value="<?php echo htmlspecialchars($ticket["titulo"]); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="descripcionEditada" class="form-label"><strong>Descripción</strong></label>
                            <textarea class="form-control" id="descripcionEditada" name="descripcion_editada" rows="8" placeholder="Edita la descripción del caso..." required><?php echo htmlspecialchars($ticket["descripcion"]); ?></textarea>
                            <small class="text-muted">Esta descripción será visible para todos los usuarios</small>
                        </div>
                        <div id="alertaDescripcion" style="display: none;"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btnGuardarDescripcion">
                            <i class="bi bi-check"></i> Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Gestión -->
    <div class="modal fade" id="modalGestion" tabindex="-1" aria-labelledby="modalGestionLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalGestionLabel"><i class="bi bi-sliders"></i> Gestionar Ticket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formGestion">
                        <div class="mb-3">
                            <label for="modalEstado" class="form-label"><strong>Estado</strong></label>
                            <div class="d-flex gap-2">
                                <select id="modalEstado" name="nueva_estado" class="form-select">
                                    <?php foreach ($estados as $est): ?>
                                        <option value="<?php echo htmlspecialchars($est); ?>" <?php echo $ticket["estado"] === $est ? "selected" : ""; ?>>
                                            <?php echo ucfirst(htmlspecialchars($est)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalHistorialEstados" title="Ver historial">
                                    <i class="bi bi-clock-history"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="modalResponsable" class="form-label"><strong>Responsable</strong></label>
                            <?php 
                            $es_cerrado = $ticket["estado"] === "ticket cerrado";
                            $es_admin = $_SESSION["role"] === "admin";
                            $responsable_deshabilitado = $es_cerrado && !$es_admin;
                            ?>
                            <select id="modalResponsable" name="nuevo_responsable" class="form-select" <?php echo $responsable_deshabilitado ? "disabled" : ""; ?>>
                                <option value="">-- Sin asignar --</option>
                                <?php foreach ($usuarios_soporte as $user): ?>
                                    <option value="<?php echo htmlspecialchars($user["id"]); ?>" <?php echo (int)$ticket["responsable"] === (int)$user["id"] ? "selected" : ""; ?>>
                                        <?php echo htmlspecialchars($user["username"]); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="modalPropietario" class="form-label"><strong>Propietario</strong></label>
                            <div style="padding: 8px; background: #f8f9fa; border-radius: 4px; border: 1px solid #ddd;">
                                <span class="badge bg-info"><?php echo htmlspecialchars($propietario_nombre) ?: 'Sin asignar'; ?></span>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarGestion()">
                        <i class="bi bi-check"></i> Guardar Cambios
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function guardarGestion() {
            const estado = document.getElementById('modalEstado').value;
            const responsable = document.getElementById('modalResponsable').value;
            const ticketId = new URLSearchParams(window.location.search).get('id');
            
            // Cambiar estado si se modificó
            if (estado !== '<?php echo $ticket["estado"]; ?>') {
                fetch('api_ticket.php?action=cambiar_estado', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        ticket_id: ticketId,
                        estado: estado
                    })
                })
                .then(r => r.json())
                .catch(err => console.error(err));
            }
            
            // Cambiar responsable si se modificó
            if (responsable !== '<?php echo $ticket["responsable"] ?? ""; ?>') {
                fetch('api_ticket.php?action=cambiar_responsable', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        ticket_id: ticketId,
                        responsable: responsable
                    })
                })
                .then(r => r.json())
                .catch(err => console.error(err));
            }
            
            // Cerrar modal y recargar página
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalGestion'));
            modal.hide();
            
            // Recargar después de 500ms para que los cambios se guarden
            setTimeout(() => {
                location.reload();
            }, 500);
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
