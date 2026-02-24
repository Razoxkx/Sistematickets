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
    header("Location: tickets.php");
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
            $usuario_modificador = $_SESSION["username"] ?? "Sistema";
            $stmt = $conexion->prepare("
                UPDATE activos 
                SET titulo = ?, descripcion = ?, propietario = ?, tipo = ?, fabricante = ?, modelo = ?, serie = ?, ubicacion = ?, fallas_activas = ?, usuario_modificado_por = ?, fecha_ultima_modificacion = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$titulo, $descripcion, $propietario, $tipo, $fabricante, $modelo, $serie, $ubicacion, $fallas_activas, $usuario_modificador, $activo_id]);

            // Redirigir a la lista de activos (orden RFK descendente, página 1) y marcar éxito
            header("Location: activos.php?orden=rfk&dir=DESC&pagina=1&success=editado");
            exit();
            
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
                                    <?php $selected_tipo = $_POST['tipo'] ?? $activo['tipo'] ?? ''; ?>
                                    <select name="tipo" id="tipo" class="form-select" required>
                                        <option value="" disabled <?php echo $selected_tipo === '' ? "selected" : ""; ?>>Seleccione un tipo</option>
                                        <option value="GENERICO" <?php echo ($selected_tipo === "GENERICO") ? "selected" : ""; ?>>GENERICO</option>
                                        <option value="ACCESS-POINT" <?php echo ($selected_tipo === "ACCESS-POINT") ? "selected" : ""; ?>>ACCESS-POINT</option>
                                        <option value="ADAPTADOR VIDEO" <?php echo ($selected_tipo === "ADAPTADOR VIDEO") ? "selected" : ""; ?>>ADAPTADOR VIDEO</option>
                                        <option value="BATERIA" <?php echo ($selected_tipo === "BATERIA") ? "selected" : ""; ?>>BATERIA</option>
                                        <option value="CABLE HDMI" <?php echo ($selected_tipo === "CABLE HDMI") ? "selected" : ""; ?>>CABLE HDMI</option>
                                        <option value="CABLE SEGURIDAD NOTEBOOK" <?php echo ($selected_tipo === "CABLE SEGURIDAD NOTEBOOK") ? "selected" : ""; ?>>CABLE SEGURIDAD NOTEBOOK</option>
                                        <option value="CAMARA" <?php echo ($selected_tipo === "CAMARA") ? "selected" : ""; ?>>CAMARA</option>
                                        <option value="CAMARA VIGILANCIA" <?php echo ($selected_tipo === "CAMARA VIGILANCIA") ? "selected" : ""; ?>>CAMARA VIGILANCIA</option>
                                        <option value="CARGADOR" <?php echo ($selected_tipo === "CARGADOR") ? "selected" : ""; ?>>CARGADOR</option>
                                        <option value="CENTRAL TELEFONICA" <?php echo ($selected_tipo === "CENTRAL TELEFONICA") ? "selected" : ""; ?>>CENTRAL TELEFONICA</option>
                                        <option value="COMPONENTE INTERNO" <?php echo ($selected_tipo === "COMPONENTE INTERNO") ? "selected" : ""; ?>>COMPONENTE INTERNO</option>
                                        <option value="DESKTOP" <?php echo ($selected_tipo === "DESKTOP") ? "selected" : ""; ?>>DESKTOP</option>
                                        <option value="DISK HDD" <?php echo ($selected_tipo === "DISK HDD") ? "selected" : ""; ?>>DISK HDD</option>
                                        <option value="DISK SSD" <?php echo ($selected_tipo === "DISK SSD") ? "selected" : ""; ?>>DISK SSD</option>
                                        <option value="DOCK" <?php echo ($selected_tipo === "DOCK") ? "selected" : ""; ?>>DOCK</option>
                                        <option value="ESCANER" <?php echo ($selected_tipo === "ESCANER") ? "selected" : ""; ?>>ESCANER</option>
                                        <option value="ETIQUETADORA" <?php echo ($selected_tipo === "ETIQUETADORA") ? "selected" : ""; ?>>ETIQUETADORA</option>
                                        <option value="FUENTE DE PODER" <?php echo ($selected_tipo === "FUENTE DE PODER") ? "selected" : ""; ?>>FUENTE DE PODER</option>
                                        <option value="HEADSET" <?php echo ($selected_tipo === "HEADSET") ? "selected" : ""; ?>>HEADSET</option>
                                        <option value="KVM" <?php echo ($selected_tipo === "KVM") ? "selected" : ""; ?>>KVM</option>
                                        <option value="MEDIACONVERT" <?php echo ($selected_tipo === "MEDIACONVERT") ? "selected" : ""; ?>>MEDIACONVERT</option>
                                        <option value="MODEM" <?php echo ($selected_tipo === "MODEM") ? "selected" : ""; ?>>MODEM</option>
                                        <option value="MONITOR" <?php echo ($selected_tipo === "MONITOR") ? "selected" : ""; ?>>MONITOR</option>
                                        <option value="MOUSE" <?php echo ($selected_tipo === "MOUSE") ? "selected" : ""; ?>>MOUSE</option>
                                        <option value="MUEBLE" <?php echo ($selected_tipo === "MUEBLE") ? "selected" : ""; ?>>MUEBLE</option>
                                        <option value="NAS" <?php echo ($selected_tipo === "NAS") ? "selected" : ""; ?>>NAS</option>
                                        <option value="NOTEBOOK" <?php echo ($selected_tipo === "NOTEBOOK") ? "selected" : ""; ?>>NOTEBOOK</option>
                                        <option value="NVR" <?php echo ($selected_tipo === "NVR") ? "selected" : ""; ?>>NVR</option>
                                        <option value="PARLANTES" <?php echo ($selected_tipo === "PARLANTES") ? "selected" : ""; ?>>PARLANTES</option>
                                        <option value="PATCH PANEL" <?php echo ($selected_tipo === "PATCH PANEL") ? "selected" : ""; ?>>PATCH PANEL</option>
                                        <option value="PDU" <?php echo ($selected_tipo === "PDU") ? "selected" : ""; ?>>PDU</option>
                                        <option value="PRINTER" <?php echo ($selected_tipo === "PRINTER") ? "selected" : ""; ?>>PRINTER</option>
                                        <option value="PROYECTOR" <?php echo ($selected_tipo === "PROYECTOR") ? "selected" : ""; ?>>PROYECTOR</option>
                                        <option value="PUNTO DE RED" <?php echo ($selected_tipo === "PUNTO DE RED") ? "selected" : ""; ?>>PUNTO DE RED</option>
                                        <option value="RACK" <?php echo ($selected_tipo === "RACK") ? "selected" : ""; ?>>RACK</option>
                                        <option value="RELOJ-CONTROL" <?php echo ($selected_tipo === "RELOJ-CONTROL") ? "selected" : ""; ?>>RELOJ-CONTROL</option>
                                        <option value="ROTULADORA" <?php echo ($selected_tipo === "ROTULADORA") ? "selected" : ""; ?>>ROTULADORA</option>
                                        <option value="ROUTER" <?php echo ($selected_tipo === "ROUTER") ? "selected" : ""; ?>>ROUTER</option>
                                        <option value="SERVIDOR" <?php echo ($selected_tipo === "SERVIDOR") ? "selected" : ""; ?>>SERVIDOR</option>
                                        <option value="SIM CARD" <?php echo ($selected_tipo === "SIM CARD") ? "selected" : ""; ?>>SIM CARD</option>
                                        <option value="SMART-LOCK" <?php echo ($selected_tipo === "SMART-LOCK") ? "selected" : ""; ?>>SMART-LOCK</option>
                                        <option value="SWITCH" <?php echo ($selected_tipo === "SWITCH") ? "selected" : ""; ?>>SWITCH</option>
                                        <option value="TABLET" <?php echo ($selected_tipo === "TABLET") ? "selected" : ""; ?>>TABLET</option>
                                        <option value="TARJETA DE IDENTIFICACION" <?php echo ($selected_tipo === "TARJETA DE IDENTIFICACION") ? "selected" : ""; ?>>TARJETA DE IDENTIFICACION</option>
                                        <option value="TECLADO" <?php echo ($selected_tipo === "TECLADO") ? "selected" : ""; ?>>TECLADO</option>
                                        <option value="TELEFONO" <?php echo ($selected_tipo === "TELEFONO") ? "selected" : ""; ?>>TELEFONO</option>
                                        <option value="TELEFONO-IP" <?php echo ($selected_tipo === "TELEFONO-IP") ? "selected" : ""; ?>>TELEFONO-IP</option>
                                        <option value="TV" <?php echo ($selected_tipo === "TV") ? "selected" : ""; ?>>TV</option>
                                        <option value="UPS" <?php echo ($selected_tipo === "UPS") ? "selected" : ""; ?>>UPS</option>
                                    </select>
                                    
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
