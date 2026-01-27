<?php
session_start();
require_once 'includes/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

// Verificar permisos (solo tisupport y admin)
$permisos = ['tisupport', 'admin'];
if (!in_array($_SESSION["role"] ?? "viewer", $permisos)) {
    header("Location: dashboard.php");
    exit();
}

$error = "";
$success = "";

// Crear procedimiento
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["crear_procedimiento"])) {
    $titulo = trim($_POST["titulo"] ?? "");
    $tipo = $_POST["tipo_procedimiento"] ?? "";
    $cuerpo = trim($_POST["cuerpo"] ?? "");
    
    if (empty($titulo) || empty($tipo) || empty($cuerpo)) {
        $error = "Todos los campos son obligatorios";
    } elseif (!in_array($tipo, ['técnico', 'administrativo'])) {
        $error = "Tipo de procedimiento inválido";
    } else {
        try {
            // Generar ID temporal
            $id_temp = "DCD.T" . uniqid();
            
            // Insertar procedimiento
            $stmt = $conexion->prepare("
                INSERT INTO procedimientos (id_procedimiento, titulo, tipo_procedimiento, cuerpo, usuario_creador)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$id_temp, $titulo, $tipo, $cuerpo, $_SESSION["user_id"]]);
            
            // Obtener el ID generado
            $procedimiento_id = $conexion->lastInsertId();
            
            // Actualizar con el ID correcto: DCD.T0000000
            $id_procedimiento = "DCD.T" . str_pad($procedimiento_id, 7, "0", STR_PAD_LEFT);
            $stmt = $conexion->prepare("UPDATE procedimientos SET id_procedimiento = ? WHERE id = ?");
            $stmt->execute([$id_procedimiento, $procedimiento_id]);
            
            $success = "Procedimiento creado correctamente: $id_procedimiento";
            
            // Redirigir a ver el procedimiento
            header("Location: ver_procedimiento.php?id=$procedimiento_id&success=creado");
            exit();
        } catch (PDOException $e) {
            $error = "Error al crear procedimiento: " . $e->getMessage();
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="css/dark-mode.css" rel="stylesheet">
    <title>Crear Procedimiento</title>
    <style>
        body {
            background: linear-gradient(to bottom, #f8f9fa, #ffffff);
        }
        
        [data-bs-theme="dark"] body {
            background: linear-gradient(to bottom, #1a1a1a, #0d0d0d);
        }
        
        [data-bs-theme="dark"] .card {
            background: #1e1e1e;
            border-color: #444;
        }
        
        [data-bs-theme="dark"] .card-body {
            color: #e0e0e0;
        }
        
        [data-bs-theme="dark"] .form-label {
            color: #e0e0e0;
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
        
        [data-bs-theme="dark"] .input-group-text {
            background-color: #2a2a2a;
            border-color: #444;
            color: #e0e0e0;
        }
        
        [data-bs-theme="dark"] .text-muted {
            color: #999 !important;
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
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-10">
                <h1><i class="bi bi-file-earmark-text"></i> Crear Nuevo Procedimiento</h1>
                <p class="text-muted">Documenta procedimientos técnicos o administrativos del sistema</p>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="titulo" class="form-label">
                                    <i class="bi bi-pencil"></i> Título del Procedimiento <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="titulo" name="titulo" class="form-control" 
                                       placeholder="Ej: Procedimiento de Reinstalación de SO" 
                                       value="<?php echo htmlspecialchars($_POST["titulo"] ?? ""); ?>" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="tipo_procedimiento" class="form-label">
                                            <i class="bi bi-tag"></i> Tipo de Procedimiento <span class="text-danger">*</span>
                                        </label>
                                        <select id="tipo_procedimiento" name="tipo_procedimiento" class="form-select" required>
                                            <option value="">Seleccionar tipo...</option>
                                            <option value="técnico" <?php echo ($_POST["tipo_procedimiento"] ?? "") === "técnico" ? "selected" : ""; ?>>
                                                <i class="bi bi-wrench"></i> Técnico
                                            </option>
                                            <option value="administrativo" <?php echo ($_POST["tipo_procedimiento"] ?? "") === "administrativo" ? "selected" : ""; ?>>
                                                <i class="bi bi-clipboard-check"></i> Administrativo
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <i class="bi bi-key"></i> ID del Procedimiento
                                        </label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" value="DCD.T0000000" disabled>
                                            <span class="input-group-text"><small class="text-muted">Automático</small></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="cuerpo" class="form-label">
                                    <i class="bi bi-file-text"></i> Contenido del Procedimiento <span class="text-danger">*</span>
                                </label>
                                <textarea id="cuerpo" name="cuerpo" class="form-control" rows="12" 
                                          placeholder="Describe el procedimiento de forma detallada. Puedes incluir pasos, notas, advertencias, etc."
                                          required><?php echo htmlspecialchars($_POST["cuerpo"] ?? ""); ?></textarea>
                                <small class="text-muted">Máximo 65535 caracteres</small>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="bi bi-person"></i> Autor
                                    </label>
                                    <div class="input-group mb-3">
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION["username"]); ?>" disabled>
                                        <span class="input-group-text"><small class="text-muted">Automático</small></span>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="bi bi-calendar"></i> Fecha de Creación
                                    </label>
                                    <div class="input-group mb-3">
                                        <input type="text" class="form-control" value="<?php echo date('d/m/Y H:i'); ?>" disabled>
                                        <span class="input-group-text"><small class="text-muted">Automática</small></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" name="crear_procedimiento" class="btn btn-success">
                                    <i class="bi bi-check-circle"></i> Crear Procedimiento
                                </button>
                                <a href="procedimientos.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Volver
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="includes/dark-mode.js"></script>
</body>
</html>
