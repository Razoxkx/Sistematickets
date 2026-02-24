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
$error = "";
$success = "";

// Obtener ID del procedimiento
$procedimiento_id = $_GET["id"] ?? "";
if (empty($procedimiento_id)) {
    header("Location: procedimientos.php");
    exit();
}

try {
    // Obtener procedimiento
    $stmt = $conexion->prepare("SELECT * FROM procedimientos WHERE id = ?");
    $stmt->execute([$procedimiento_id]);
    $procedimiento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$procedimiento) {
        $error = "Procedimiento no encontrado";
    }
    
    // Procesar actualización
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["actualizar_procedimiento"])) {
        $titulo = trim($_POST["titulo"] ?? "");
        $tipo = $_POST["tipo_procedimiento"] ?? "";
        $cuerpo = trim($_POST["cuerpo"] ?? "");
        
        if (empty($titulo) || empty($tipo) || empty($cuerpo)) {
            $error = "Todos los campos son obligatorios";
        } else {
            // Registrar cambios en historial
            if ($procedimiento["titulo"] !== $titulo) {
                $stmt_hist = $conexion->prepare("
                    INSERT INTO historial_procedimientos (procedimiento_id, campo_modificado, valor_anterior, valor_nuevo, usuario_id)
                    VALUES (?, 'titulo', ?, ?, ?)
                ");
                $stmt_hist->execute([$procedimiento_id, $procedimiento["titulo"], $titulo, $_SESSION["user_id"]]);
            }
            
            if ($procedimiento["tipo_procedimiento"] !== $tipo) {
                $stmt_hist = $conexion->prepare("
                    INSERT INTO historial_procedimientos (procedimiento_id, campo_modificado, valor_anterior, valor_nuevo, usuario_id)
                    VALUES (?, 'tipo_procedimiento', ?, ?, ?)
                ");
                $stmt_hist->execute([$procedimiento_id, $procedimiento["tipo_procedimiento"], $tipo, $_SESSION["user_id"]]);
            }
            
            if ($procedimiento["cuerpo"] !== $cuerpo) {
                $stmt_hist = $conexion->prepare("
                    INSERT INTO historial_procedimientos (procedimiento_id, campo_modificado, valor_anterior, valor_nuevo, usuario_id)
                    VALUES (?, 'cuerpo', ?, ?, ?)
                ");
                $stmt_hist->execute([$procedimiento_id, substr($procedimiento["cuerpo"], 0, 100), substr($cuerpo, 0, 100), $_SESSION["user_id"]]);
            }
            
            // Actualizar procedimiento
            $stmt = $conexion->prepare("
                UPDATE procedimientos 
                SET titulo = ?, tipo_procedimiento = ?, cuerpo = ?, fecha_ultima_modificacion = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$titulo, $tipo, $cuerpo, $procedimiento_id]);
            
            // Redirigir al procedimiento actualizado con mensaje
            header("Location: ver_procedimiento.php?id=" . $procedimiento_id . "&success=cambios");
            exit();
        }
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
    <title>Editar Procedimiento</title>
    <style>
        h1, h2, h3 {
            color: #8b9dff;
            font-weight: 700;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        h2 {
            font-size: 1.75rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-10">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php else: ?>
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1><i class="bi bi-pencil-square"></i> Editar Procedimiento</h1>
                        <a href="ver_procedimiento.php?id=<?php echo $procedimiento_id; ?>" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Volver
                        </a>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="titulo" class="form-label">
                                        <i class="bi bi-pencil"></i> Título <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" id="titulo" name="titulo" class="form-control" 
                                           value="<?php echo htmlspecialchars($procedimiento["titulo"]); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="tipo_procedimiento" class="form-label">
                                        <i class="bi bi-tag"></i> Tipo <span class="text-danger">*</span>
                                    </label>
                                    <select id="tipo_procedimiento" name="tipo_procedimiento" class="form-select" required>
                                        <option value="técnico" <?php echo $procedimiento["tipo_procedimiento"] === "técnico" ? "selected" : ""; ?>>Técnico</option>
                                        <option value="administrativo" <?php echo $procedimiento["tipo_procedimiento"] === "administrativo" ? "selected" : ""; ?>>Administrativo</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="cuerpo" class="form-label">
                                        <i class="bi bi-file-text"></i> Contenido <span class="text-danger">*</span>
                                    </label>
                                    <textarea id="cuerpo" name="cuerpo" class="form-control" rows="12" required><?php echo htmlspecialchars($procedimiento["cuerpo"]); ?></textarea>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" name="actualizar_procedimiento" class="btn btn-success">
                                        <i class="bi bi-check-circle"></i> Guardar Cambios
                                    </button>
                                    <a href="ver_procedimiento.php?id=<?php echo $procedimiento_id; ?>" class="btn btn-secondary">
                                        <i class="bi bi-x-circle"></i> Cancelar
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="includes/dark-mode.js"></script>
</body>
</html>
