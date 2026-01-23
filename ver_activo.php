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
?>

<!DOCTYPE html>
<html lang="es" id="htmlRoot">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/dark-mode.css" rel="stylesheet">
    <title>Ver Activo - <?php echo htmlspecialchars($activo["rfk"] ?? ""); ?></title>
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
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Activo: <?php echo htmlspecialchars($activo["rfk"]); ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5>Información Básica</h5>
                                <dl class="row">
                                    <dt class="col-sm-4">RFK:</dt>
                                    <dd class="col-sm-8"><strong><?php echo htmlspecialchars($activo["rfk"]); ?></strong></dd>
                                    
                                    <dt class="col-sm-4">Título:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($activo["titulo"]); ?></dd>
                                    
                                    <dt class="col-sm-4">Tipo:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($activo["tipo"]); ?></dd>
                                    
                                    <dt class="col-sm-4">Propietario:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($activo["propietario"]); ?></dd>
                                    
                                    <dt class="col-sm-4">Ubicación:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($activo["ubicacion"]); ?></dd>
                                </dl>
                            </div>
                            
                            <div class="col-md-6">
                                <h5>Especificaciones</h5>
                                <dl class="row">
                                    <dt class="col-sm-4">Fabricante:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($activo["fabricante"]); ?></dd>
                                    
                                    <dt class="col-sm-4">Modelo:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($activo["modelo"]); ?></dd>
                                    
                                    <dt class="col-sm-4">Serie:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($activo["serie"]); ?></dd>
                                </dl>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h5>Descripción</h5>
                                <p><?php echo nl2br(htmlspecialchars($activo["descripcion"])); ?></p>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <h5>Fallas Activas</h5>
                                <p><?php echo nl2br(htmlspecialchars($activo["fallas_activas"])); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-footer">
                        <a href="activos.php" class="btn btn-secondary">Volver</a>
                        <a href="editar_activo.php?id=<?php echo $activo["id"]; ?>" class="btn btn-warning">Editar</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
