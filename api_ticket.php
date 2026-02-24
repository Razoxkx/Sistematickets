<?php
session_start();
require_once 'includes/config.php';

header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["error" => "No autorizado"]);
    exit();
}

// Verificar permisos
$permisos = ['tisupport', 'admin'];
if (!in_array($_SESSION["role"] ?? "viewer", $permisos)) {
    http_response_code(403);
    echo json_encode(["error" => "Sin permisos"]);
    exit();
}

$action = $_GET["action"] ?? "";
$ticket_id = $_POST["ticket_id"] ?? "";

if (empty($action)) {
    http_response_code(400);
    echo json_encode(["error" => "Acción requerida"]);
    exit();
}

try {
    // AGREGAR COMENTARIO
    if ($action === "agregar_comentario") {
        $comentario = $_POST["comentario"] ?? "";
        
        error_log("Iniciando agregar_comentario: ticket_id=$ticket_id, comentario=" . substr($comentario, 0, 50));
        
        if (empty($comentario)) {
            echo json_encode(["error" => "El comentario no puede estar vacío"]);
            exit();
        }
        
        // Insertar comentario original en el ticket actual (sin tipo_comentario)
        $stmt = $conexion->prepare("INSERT INTO comentarios_tickets (ticket_id, usuario_id, comentario, tipo_comentario) VALUES (?, ?, ?, NULL)");
        $stmt->execute([$ticket_id, $_SESSION["user_id"], $comentario]);
        
        // Guardar el ID del comentario inmediatamente
        $comentario_id = $conexion->lastInsertId();
        error_log("Comentario insertado con ID: " . $comentario_id);
        
        if (!$comentario_id) {
            error_log("ERROR: No se obtuvo ID del comentario");
            echo json_encode(["error" => "Error al insertar el comentario"]);
            exit();
        }
        
        // Detectar menciones de tickets en el comentario
        if (preg_match_all('/#(DCD\d{6})/i', $comentario, $matches)) {
            $usuario_menciona = '';
            $stmt = $conexion->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$_SESSION["user_id"]]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $usuario_menciona = $user["username"] ?? "Sistema";
            
            // Obtener número del ticket actual
            $stmt = $conexion->prepare("SELECT ticket_number FROM tickets WHERE id = ?");
            $stmt->execute([$ticket_id]);
            $ticket_actual = $stmt->fetch(PDO::FETCH_ASSOC);
            $ticket_number = $ticket_actual["ticket_number"] ?? "DCD000000";
            
            foreach ($matches[1] as $ticket_mencionado) {
                // No crear comentario automático si se menciona el mismo ticket
                if ($ticket_mencionado === $ticket_number) {
                    continue;
                }
                
                // Verificar que el ticket mencionado existe
                $stmt = $conexion->prepare("SELECT id FROM tickets WHERE ticket_number = ?");
                $stmt->execute([$ticket_mencionado]);
                $ticket_dest = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($ticket_dest) {
                    // Agregar comentario automático SOLO en el ticket mencionado
                    // Incluir referencia del ticket actual + comentario completo
                    $msg_menciona = "🔗 Ticket mencionado en #" . $ticket_number . " por " . $usuario_menciona . "\n\n" . $comentario;
                    $stmt = $conexion->prepare("INSERT INTO comentarios_tickets (ticket_id, usuario_id, comentario, tipo_comentario) VALUES (?, ?, ?, 'mencion')");
                    $stmt->execute([$ticket_dest["id"], $_SESSION["user_id"], $msg_menciona]);
                }
            }
        }
        
        // Detectar menciones de procedimientos en el comentario (#DCD.T0000001)
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
                    $stmt_mencion->execute([$proc["id"], $ticket_id, $comentario_id]);
                }
            }
        }
        
        // Obtener el comentario que se acaba de crear usando el ID guardado
        $stmt = $conexion->prepare("
            SELECT c.*, u.username, 
                   COALESCE(um.username, '') as usuario_modifico_nombre
            FROM comentarios_tickets c
            JOIN users u ON c.usuario_id = u.id
            LEFT JOIN users um ON c.usuario_modificado_por = um.id
            WHERE c.id = ?
        ");
        $stmt->execute([$comentario_id]);
        $comentario_nuevo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Debugar si el comentario no se recuperó
        if (!$comentario_nuevo) {
            error_log("ERROR: Comentario no encontrado con ID: " . $comentario_id);
            echo json_encode(["error" => "Error al recuperar el comentario creado"]);
            exit();
        }
        
        error_log("Comentario creado exitosamente: " . json_encode($comentario_nuevo));
        
        // Cambio automático de estado: si está en "en conocimiento", pasar a "en proceso"
        $stmt = $conexion->prepare("SELECT estado FROM tickets WHERE id = ?");
        $stmt->execute([$ticket_id]);
        $ticket_actual = $stmt->fetch(PDO::FETCH_ASSOC);
        $estado_actual = $ticket_actual['estado'] ?? null;
        
        if ($estado_actual === "en conocimiento") {
            $stmt_update = $conexion->prepare("UPDATE tickets SET estado = ?, fecha_ultima_modificacion = NOW() WHERE id = ?");
            $stmt_update->execute(["en proceso", $ticket_id]);
            
            // Registrar cambio automático en historial
            $stmt_historial = $conexion->prepare("
                INSERT INTO historial_estados_tickets (ticket_id, estado_anterior, estado_nuevo, usuario_id)
                VALUES (?, ?, ?, ?)
            ");
            $stmt_historial->execute([$ticket_id, "en conocimiento", "en proceso", $_SESSION["user_id"]]);
        }
        
        if ($comentario_nuevo) {
            echo json_encode(["success" => true, "comentario" => $comentario_nuevo]);
        } else {
            echo json_encode(["error" => "No se pudo recuperar el comentario creado"]);
        }
    }
    
    // CAMBIAR ESTADO
    elseif ($action === "cambiar_estado") {
        $nuevo_estado = $_POST["estado"] ?? "";
        
        if (empty($nuevo_estado)) {
            echo json_encode(["error" => "Estado requerido"]);
            exit();
        }
        
        // Obtener estado anterior
        $stmt = $conexion->prepare("SELECT estado FROM tickets WHERE id = ?");
        $stmt->execute([$ticket_id]);
        $ticket_actual = $stmt->fetch(PDO::FETCH_ASSOC);
        $estado_anterior = $ticket_actual['estado'] ?? null;
        
        // Validación: prevenir cambio manual a "sin abrir" (excepto si ya está en ese estado)
        if ($nuevo_estado === "sin abrir" && $estado_anterior !== "sin abrir") {
            echo json_encode(["error" => "No se puede cambiar un ticket a estado 'sin abrir' manualmente."]);
            exit();
        }
        
        // Actualizar estado
        $stmt = $conexion->prepare("UPDATE tickets SET estado = ?, fecha_ultima_modificacion = NOW() WHERE id = ?");
        $stmt->execute([$nuevo_estado, $ticket_id]);
        
        // Registrar en historial
        $stmt_historial = $conexion->prepare("
            INSERT INTO historial_estados_tickets (ticket_id, estado_anterior, estado_nuevo, usuario_id)
            VALUES (?, ?, ?, ?)
        ");
        $stmt_historial->execute([$ticket_id, $estado_anterior, $nuevo_estado, $_SESSION["user_id"]]);
        
        echo json_encode(["success" => true, "estado" => $nuevo_estado]);
    }
    
    // CAMBIAR RESPONSABLE
    elseif ($action === "cambiar_responsable") {
        $nuevo_responsable = $_POST["responsable"] === "" ? null : $_POST["responsable"];
        
        $stmt = $conexion->prepare("UPDATE tickets SET responsable = ?, fecha_ultima_modificacion = NOW() WHERE id = ?");
        $stmt->execute([$nuevo_responsable, $ticket_id]);
        
        // Obtener nombre del nuevo responsable
        $responsable_nombre = "Sin asignar";
        if ($nuevo_responsable) {
            $stmt = $conexion->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$nuevo_responsable]);
            $resp_user = $stmt->fetch(PDO::FETCH_ASSOC);
            $responsable_nombre = $resp_user["username"] ?? "No asignado";
        }
        
        // Obtener nombre del usuario actual
        $stmt = $conexion->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$_SESSION["user_id"]]);
        $user_actual = $stmt->fetch(PDO::FETCH_ASSOC);
        $usuario_asigna = $user_actual["username"] ?? "Sistema";
        
        // Agregar comentario automático
        $mensaje_asignacion = "👤 " . $usuario_asigna . " asignó este ticket a " . $responsable_nombre;
        $stmt = $conexion->prepare("INSERT INTO comentarios_tickets (ticket_id, usuario_id, comentario, tipo_comentario) VALUES (?, ?, ?, ?)");
        $stmt->execute([$ticket_id, $_SESSION["user_id"], $mensaje_asignacion, 'asignacion']);
        
        // Obtener el comentario que se acaba de crear
        $stmt = $conexion->prepare("
            SELECT c.*, u.username, 
                   COALESCE(um.username, '') as usuario_modifico_nombre
            FROM comentarios_tickets c
            JOIN users u ON c.usuario_id = u.id
            LEFT JOIN users um ON c.usuario_modificado_por = um.id
            WHERE c.id = LAST_INSERT_ID()
        ");
        $stmt->execute();
        $comentario_nuevo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode(["success" => true, "responsable_nombre" => $responsable_nombre, "comentario" => $comentario_nuevo]);
    }
    
    // EDITAR DESCRIPCIÓN
    elseif ($action === "editar_descripcion") {
        // Validar que sea admin
        if ($_SESSION["role"] !== "admin") {
            http_response_code(403);
            echo json_encode(["error" => "Solo administradores pueden editar la descripción"]);
            exit();
        }
        
        $descripcion_nueva = $_POST["descripcion"] ?? "";
        $titulo_nuevo = $_POST["titulo"] ?? "";
        
        if (empty($descripcion_nueva)) {
            echo json_encode(["error" => "La descripción no puede estar vacía"]);
            exit();
        }
        
        if (empty($titulo_nuevo)) {
            echo json_encode(["error" => "El título no puede estar vacío"]);
            exit();
        }
        
        // Actualizar descripción y título
        $stmt = $conexion->prepare("UPDATE tickets SET descripcion = ?, titulo = ?, fecha_ultima_modificacion = NOW() WHERE id = ?");
        $stmt->execute([$descripcion_nueva, $titulo_nuevo, $ticket_id]);
        
        // Obtener nombre del usuario actual
        $stmt = $conexion->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$_SESSION["user_id"]]);
        $user_actual = $stmt->fetch(PDO::FETCH_ASSOC);
        $usuario_actual = $user_actual["username"] ?? "Sistema";
        
        // Agregar comentario automático de edición
        $mensaje_edicion = "📝 " . $usuario_actual . " editó la descripción del caso";
        $stmt = $conexion->prepare("INSERT INTO comentarios_tickets (ticket_id, usuario_id, comentario, tipo_comentario) VALUES (?, ?, ?, ?)");
        $stmt->execute([$ticket_id, $_SESSION["user_id"], $mensaje_edicion, 'edicion']);
        
        // Obtener el comentario que se acaba de crear
        $stmt = $conexion->prepare("
            SELECT c.*, u.username, 
                   COALESCE(um.username, '') as usuario_modifico_nombre
            FROM comentarios_tickets c
            JOIN users u ON c.usuario_id = u.id
            LEFT JOIN users um ON c.usuario_modificado_por = um.id
            WHERE c.id = LAST_INSERT_ID()
        ");
        $stmt->execute();
        $comentario_nuevo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode(["success" => true, "descripcion" => $descripcion_nueva, "titulo" => $titulo_nuevo, "comentario" => $comentario_nuevo]);
    }
    
    // OBTENER TICKETS POR ACTIVO
    else if ($action === "obtener_tickets_por_activo") {
        $rfk = $_GET["rfk"] ?? "";
        
        if (empty($rfk)) {
            echo json_encode(["error" => "RFK requerido", "success" => false]);
            exit();
        }
        
        $stmt = $conexion->prepare("
            SELECT DISTINCT t.id, t.ticket_number, t.titulo, t.estado, t.es_cerrado
            FROM tickets t
            JOIN comentarios_tickets c ON t.id = c.ticket_id
            WHERE c.comentario LIKE ?
            ORDER BY t.id DESC
        ");
        $stmt->execute(['%' . $rfk . '%']);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(["success" => true, "tickets" => $tickets]);
    }
    
    // OBTENER HISTORIAL DE ESTADOS
    else if ($action === "obtener_historial_estados") {
        $ticket_id = $_GET["ticket_id"] ?? "";
        
        if (empty($ticket_id)) {
            http_response_code(400);
            echo json_encode(["error" => "ticket_id requerido", "success" => false]);
            exit();
        }
        
        try {
            // Si es un número, buscar por ID; si no, buscar por ticket_number
            if (is_numeric($ticket_id)) {
                $stmt = $conexion->prepare("SELECT id FROM tickets WHERE id = ?");
                $stmt->execute([$ticket_id]);
            } else {
                $stmt = $conexion->prepare("SELECT id FROM tickets WHERE ticket_number = ?");
                $stmt->execute([$ticket_id]);
            }
            
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ticket) {
                http_response_code(404);
                echo json_encode(["error" => "Ticket no encontrado", "success" => false]);
                exit();
            }
            
            $stmt = $conexion->prepare("
                SELECT h.id, h.ticket_id, h.estado_anterior, h.estado_nuevo, h.fecha_cambio, u.username as usuario_nombre
                FROM historial_estados_tickets h
                JOIN users u ON h.usuario_id = u.id
                WHERE h.ticket_id = ?
                ORDER BY h.fecha_cambio DESC
            ");
            $stmt->execute([$ticket['id']]);
            $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(["success" => true, "historial" => $historial]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["error" => "Error en la base de datos: " . $e->getMessage(), "success" => false]);
        }
    }
    
    else {
        http_response_code(400);
        echo json_encode(["error" => "Acción no válida"]);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    error_log("PDOException en api_ticket.php: " . $e->getMessage());
    echo json_encode(["error" => $e->getMessage(), "success" => false]);
}
?>
