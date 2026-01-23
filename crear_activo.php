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
            // Obtener el último RFK y generar el siguiente
            $stmt = $conexion->query("SELECT rfk FROM activos ORDER BY CAST(SUBSTR(rfk, 3) AS UNSIGNED) DESC LIMIT 1");
            $ultimo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($ultimo) {
                // Extraer el número del RFK anterior y sumarle 1
                $numero_anterior = intval(substr($ultimo["rfk"], 2));
                $nuevo_numero = $numero_anterior + 1;
            } else {
                // Si no hay registros, comenzar en 7906000
                $nuevo_numero = 7906000;
            }
            
            // Generar nuevo RFK con formato AK + número
            $nuevo_rfk = "AK" . str_pad($nuevo_numero, 7, "0", STR_PAD_LEFT);
            
            // Insertar nuevo activo
            $stmt = $conexion->prepare("
                INSERT INTO activos (rfk, titulo, descripcion, propietario, tipo, fabricante, modelo, serie, ubicacion, fallas_activas)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nuevo_rfk, $titulo, $descripcion, $propietario, $tipo, $fabricante, $modelo, $serie, $ubicacion, $fallas_activas]);
            
            $success = "Activo creado exitosamente con RFK: <strong>$nuevo_rfk</strong>";
            
            // Limpiar formulario
            $_POST = [];
            
        } catch (PDOException $e) {
            $error = "Error al crear el activo: " . $e->getMessage();
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
    <title>Crear Activo</title>
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
                        <h2 class="card-title mb-4">Crear Nuevo Activo</h2>
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo $success; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="titulo" class="form-label">Título *</label>
                                    <input type="text" class="form-control" id="titulo" name="titulo" required value="<?php echo htmlspecialchars($_POST["titulo"] ?? ""); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="tipo" class="form-label">Tipo *</label>
                                    <input type="text" class="form-control" id="tipo" name="tipo" required value="<?php echo htmlspecialchars($_POST["tipo"] ?? ""); ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="descripcion" class="form-label">Descripción</label>
                                <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?php echo htmlspecialchars($_POST["descripcion"] ?? ""); ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="propietario" class="form-label">Propietario *</label>
                                    <input type="text" class="form-control" id="propietario" name="propietario" required value="<?php echo htmlspecialchars($_POST["propietario"] ?? ""); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="ubicacion" class="form-label">Ubicación</label>
                                    <input type="text" class="form-control" id="ubicacion" name="ubicacion" value="<?php echo htmlspecialchars($_POST["ubicacion"] ?? ""); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="fabricante" class="form-label">Fabricante</label>
                                    <input type="text" class="form-control" id="fabricante" name="fabricante" value="<?php echo htmlspecialchars($_POST["fabricante"] ?? ""); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="modelo" class="form-label">Modelo</label>
                                    <input type="text" class="form-control" id="modelo" name="modelo" value="<?php echo htmlspecialchars($_POST["modelo"] ?? ""); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="serie" class="form-label">Serie</label>
                                    <input type="text" class="form-control" id="serie" name="serie" value="<?php echo htmlspecialchars($_POST["serie"] ?? ""); ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="fallas_activas" class="form-label">Fallas Activas</label>
                                <textarea class="form-control" id="fallas_activas" name="fallas_activas" rows="3"><?php echo htmlspecialchars($_POST["fallas_activas"] ?? ""); ?></textarea>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="activos.php" class="btn btn-secondary">Cancelar</a>
                                <button type="submit" class="btn btn-primary">Crear Activo</button>
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
