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

$error = "";
$success = "";
$activo = null;

// Obtener ID del activo
$activo_id = $_GET["id"] ?? "";

if (!$activo_id) {
    header("Location: activos.php");
    exit();
}

try {
    $stmt = $conexion->prepare("SELECT * FROM activos WHERE id = ?");
    $stmt->execute([$activo_id]);
    $activo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$activo) {
        header("Location: activos.php?error=not_found");
        exit();
    }
} catch (PDOException $e) {
    $error = "Error al obtener el activo: " . $e->getMessage();
}

// Procesar actualización
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $titulo = $_POST["titulo"] ?? "";
    $descripcion = $_POST["descripcion"] ?? "";
    $propietario = $_POST["propietario"] ?? "";
    $tipo = $_POST["tipo"] ?? "";
    $fabricante = $_POST["fabricante"] ?? "";
    $modelo = $_POST["modelo"] ?? "";
    $serie = $_POST["serie"] ?? "";
    $ubicacion = $_POST["ubicacion"] ?? "";
    $fallas_activas = $_POST["fallas_activas"] ?? "";
    
    if (empty($titulo) || empty($propietario) || empty($tipo)) {
        $error = "El título, propietario y tipo son obligatorios";
    } else {
        try {
            $stmt = $conexion->prepare("
                UPDATE activos 
                SET titulo = ?, descripcion = ?, propietario = ?, tipo = ?, fabricante = ?, modelo = ?, serie = ?, ubicacion = ?, fallas_activas = ?
                WHERE id = ?
            ");
            $stmt->execute([$titulo, $descripcion, $propietario, $tipo, $fabricante, $modelo, $serie, $ubicacion, $fallas_activas, $activo_id]);
            
            $success = "Activo actualizado exitosamente";
            
            // Recargar datos
            $stmt = $conexion->prepare("SELECT * FROM activos WHERE id = ?");
            $stmt->execute([$activo_id]);
            $activo = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $error = "Error al actualizar el activo: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es" id="htmlRoot">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/dark-mode.css" rel="stylesheet">
    <title>Editar Activo</title>
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
                        <h2 class="card-title mb-4">Editar Activo: <?php echo htmlspecialchars($activo["rfk"]); ?></h2>
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($success); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="titulo" class="form-label">Título *</label>
                                    <input type="text" class="form-control" id="titulo" name="titulo" required value="<?php echo htmlspecialchars($activo["titulo"] ?? ""); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="tipo" class="form-label">Tipo *</label>
                                    <input type="text" class="form-control" id="tipo" name="tipo" required value="<?php echo htmlspecialchars($activo["tipo"] ?? ""); ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="descripcion" class="form-label">Descripción</label>
                                <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?php echo htmlspecialchars($activo["descripcion"] ?? ""); ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="propietario" class="form-label">Propietario *</label>
                                    <input type="text" class="form-control" id="propietario" name="propietario" required value="<?php echo htmlspecialchars($activo["propietario"] ?? ""); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="ubicacion" class="form-label">Ubicación</label>
                                    <input type="text" class="form-control" id="ubicacion" name="ubicacion" value="<?php echo htmlspecialchars($activo["ubicacion"] ?? ""); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="fabricante" class="form-label">Fabricante</label>
                                    <input type="text" class="form-control" id="fabricante" name="fabricante" value="<?php echo htmlspecialchars($activo["fabricante"] ?? ""); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="modelo" class="form-label">Modelo</label>
                                    <input type="text" class="form-control" id="modelo" name="modelo" value="<?php echo htmlspecialchars($activo["modelo"] ?? ""); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="serie" class="form-label">Serie</label>
                                    <input type="text" class="form-control" id="serie" name="serie" value="<?php echo htmlspecialchars($activo["serie"] ?? ""); ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="fallas_activas" class="form-label">Fallas Activas</label>
                                <textarea class="form-control" id="fallas_activas" name="fallas_activas" rows="3"><?php echo htmlspecialchars($activo["fallas_activas"] ?? ""); ?></textarea>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="ver_activo.php?id=<?php echo $activo["id"]; ?>" class="btn btn-secondary">Cancelar</a>
                                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
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
