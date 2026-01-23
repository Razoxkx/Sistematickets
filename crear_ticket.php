<?php
session_start();
require_once 'includes/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

// Verificar permisos: solo tisupport y admin pueden crear tickets
$permisos = ['tisupport', 'admin'];
if (!in_array($_SESSION["role"] ?? "viewer", $permisos)) {
    header("Location: dashboard.php");
    exit();
}

$error = "";
$success = "";

// Obtener lista de usuarios soporte TI y admin para asignar
try {
    $stmt = $conexion->query("SELECT id, username FROM users WHERE role IN ('tisupport', 'admin') ORDER BY username");
    $usuarios_soporte = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $usuarios_soporte = [];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $titulo = $_POST["titulo"] ?? "";
    $descripcion = $_POST["descripcion"] ?? "";
    $nombre_reportante = $_POST["nombre_reportante"] ?? "";
    $responsable_asignado = $_POST["responsable_asignado"] ?? null;
    
    if (empty($titulo) || empty($descripcion) || empty($nombre_reportante) || empty($responsable_asignado)) {
        $error = "El título, descripción, nombre de quién reporta y responsable son obligatorios";
    } else {
        try {
            // Primero insertar con un número temporal
            $ticket_number_temp = "DCD" . uniqid();
            
            $stmt = $conexion->prepare("INSERT INTO tickets (ticket_number, titulo, descripcion, usuario_creador, nombre_solicitante, propietario, responsable) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$ticket_number_temp, $titulo, $descripcion, $_SESSION["user_id"], $nombre_reportante, $_SESSION["user_id"], $responsable_asignado]);
            
            // Obtener el ID del ticket insertado
            $ticket_id = $conexion->lastInsertId();
            
            // Generar número de ticket definitivo
            $ticket_number = "DCD" . str_pad($ticket_id, 6, "0", STR_PAD_LEFT);
            
            // Actualizar con el número de ticket definitivo
            $stmt = $conexion->prepare("UPDATE tickets SET ticket_number = ? WHERE id = ?");
            $stmt->execute([$ticket_number, $ticket_id]);
            
            // Agregar comentario inicial con el nombre del reportante
            $comentario_inicial = "Ticket reportado por: " . htmlspecialchars($nombre_reportante);
            $stmt = $conexion->prepare("INSERT INTO comentarios_tickets (ticket_id, usuario_id, comentario) VALUES (?, ?, ?)");
            $stmt->execute([$ticket_id, $_SESSION["user_id"], $comentario_inicial]);
            
            // Redirigir a tickets.php después de crear exitosamente
            header("Location: tickets.php?success=creado");
            exit();
            
        } catch (PDOException $e) {
            $error = "Error al crear el ticket: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/dark-mode.css" rel="stylesheet">
    <title>Crear Ticket</title>
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
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-10 offset-md-1">
                <div class="card shadow">
                    <div class="card-body">
                        <h2 class="card-title mb-4">Crear Nuevo Ticket</h2>
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle text-success"></i> <?php echo $success; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="nombre_reportante" class="form-label">Nombre de quién reporta el ticket</label>
                                <input type="text" class="form-control" id="nombre_reportante" name="nombre_reportante" required placeholder="Nombre de la persona que reporta" value="<?php echo htmlspecialchars($_POST["nombre_reportante"] ?? ""); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="titulo" class="form-label">Título del Ticket</label>
                                <input type="text" class="form-control" id="titulo" name="titulo" required placeholder="Resumen breve del problema" value="<?php echo htmlspecialchars($_POST["titulo"] ?? ""); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="descripcion" class="form-label">Descripción del Caso</label>
                                <textarea class="form-control" id="descripcion" name="descripcion" rows="10" required placeholder="Describe el caso con todos los detalles necesarios..."><?php echo htmlspecialchars($_POST["descripcion"] ?? ""); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="responsable_asignado" class="form-label">Asignar Responsable <span class="text-danger">*</span></label>
                                <select class="form-select" id="responsable_asignado" name="responsable_asignado" required>
                                    <option value="">-- Seleccionar responsable --</option>
                                    <?php foreach ($usuarios_soporte as $user): ?>
                                        <option value="<?php echo htmlspecialchars($user["id"]); ?>" <?php echo (isset($_POST["responsable_asignado"]) && $_POST["responsable_asignado"] == $user["id"]) ? "selected" : ""; ?>>
                                            <?php echo htmlspecialchars($user["username"]); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary" >Guardar Ticket</button>
                                <a href="tickets.php" class="btn btn-secondary">Volver a Tickets</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
