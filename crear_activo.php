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
            
            // Insertar nuevo activo con información de auditoría
            $usuario_creador = $_SESSION["username"] ?? "Sistema";
            $stmt = $conexion->prepare("
                INSERT INTO activos (rfk, titulo, usuario_creado_por, fecha_creacion, descripcion, propietario, tipo, fabricante, modelo, serie, ubicacion, fallas_activas)
                VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nuevo_rfk, $titulo, $usuario_creador, $descripcion, $propietario, $tipo, $fabricante, $modelo, $serie, $ubicacion, $fallas_activas]);
            
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
                                    <select name="tipo" id="tipo" class="form-select" required>
                                        <option value="" disabled <?php echo empty($_POST["tipo"]) ? "selected" : ""; ?>>Seleccione un tipo</option>
                                        <option value="GENERICO" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "GENERICO") ? "selected" : ""; ?>>GENERICO</option>
                                        <option value="ACCESS-POINT" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "ACCESS-POINT") ? "selected" : ""; ?>>ACCESS-POINT</option>
                                        <option value="ADAPTADOR VIDEO" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "ADAPTADOR VIDEO") ? "selected" : ""; ?>>ADAPTADOR VIDEO</option>
                                        <option value="BATERIA" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "BATERIA") ? "selected" : ""; ?>>BATERIA</option>
                                        <option value="CABLE HDMI" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "CABLE HDMI") ? "selected" : ""; ?>>CABLE HDMI</option>
                                        <option value="CABLE SEGURIDAD NOTEBOOK" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "CABLE SEGURIDAD NOTEBOOK") ? "selected" : ""; ?>>CABLE SEGURIDAD NOTEBOOK</option>
                                        <option value="CAMARA" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "CAMARA") ? "selected" : ""; ?>>CAMARA</option>
                                        <option value="CAMARA VIGILANCIA" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "CAMARA VIGILANCIA") ? "selected" : ""; ?>>CAMARA VIGILANCIA</option>
                                        <option value="CARGADOR" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "CARGADOR") ? "selected" : ""; ?>>CARGADOR</option>
                                        <option value="CENTRAL TELEFONICA" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "CENTRAL TELEFONICA") ? "selected" : ""; ?>>CENTRAL TELEFONICA</option>
                                        <option value="COMPONENTE INTERNO" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "COMPONENTE INTERNO") ? "selected" : ""; ?>>COMPONENTE INTERNO</option>
                                        <option value="DESKTOP" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "DESKTOP") ? "selected" : ""; ?>>DESKTOP</option>
                                        <option value="DISK HDD" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "DISK HDD") ? "selected" : ""; ?>>DISK HDD</option>
                                        <option value="DISK SSD" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "DISK SSD") ? "selected" : ""; ?>>DISK SSD</option>
                                        <option value="DOCK" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "DOCK") ? "selected" : ""; ?>>DOCK</option>
                                        <option value="ESCANER" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "ESCANER") ? "selected" : ""; ?>>ESCANER</option>
                                        <option value="ETIQUETADORA" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "ETIQUETADORA") ? "selected" : ""; ?>>ETIQUETADORA</option>
                                        <option value="FUENTE DE PODER" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "FUENTE DE PODER") ? "selected" : ""; ?>>FUENTE DE PODER</option>
                                        <option value="HEADSET" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "HEADSET") ? "selected" : ""; ?>>HEADSET</option>
                                        <option value="KVM" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "KVM") ? "selected" : ""; ?>>KVM</option>
                                        <option value="MEDIACONVERT" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "MEDIACONVERT") ? "selected" : ""; ?>>MEDIACONVERT</option>
                                        <option value="MODEM" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "MODEM") ? "selected" : ""; ?>>MODEM</option>
                                        <option value="MONITOR" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "MONITOR") ? "selected" : ""; ?>>MONITOR</option>
                                        <option value="MOUSE" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "MOUSE") ? "selected" : ""; ?>>MOUSE</option>
                                        <option value="MUEBLE" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "MUEBLE") ? "selected" : ""; ?>>MUEBLE</option>
                                        <option value="NAS" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "NAS") ? "selected" : ""; ?>>NAS</option>
                                        <option value="NOTEBOOK" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "NOTEBOOK") ? "selected" : ""; ?>>NOTEBOOK</option>
                                        <option value="NVR" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "NVR") ? "selected" : ""; ?>>NVR</option>
                                        <option value="PARLANTES" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "PARLANTES") ? "selected" : ""; ?>>PARLANTES</option>
                                        <option value="PATCH PANEL" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "PATCH PANEL") ? "selected" : ""; ?>>PATCH PANEL</option>
                                        <option value="PDU" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "PDU") ? "selected" : ""; ?>>PDU</option>
                                        <option value="PRINTER" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "PRINTER") ? "selected" : ""; ?>>PRINTER</option>
                                        <option value="PROYECTOR" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "PROYECTOR") ? "selected" : ""; ?>>PROYECTOR</option>
                                        <option value="PUNTO DE RED" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "PUNTO DE RED") ? "selected" : ""; ?>>PUNTO DE RED</option>
                                        <option value="RACK" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "RACK") ? "selected" : ""; ?>>RACK</option>
                                        <option value="RELOJ-CONTROL" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "RELOJ-CONTROL") ? "selected" : ""; ?>>RELOJ-CONTROL</option>
                                        <option value="ROTULADORA" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "ROTULADORA") ? "selected" : ""; ?>>ROTULADORA</option>
                                        <option value="ROUTER" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "ROUTER") ? "selected" : ""; ?>>ROUTER</option>
                                        <option value="SERVIDOR" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "SERVIDOR") ? "selected" : ""; ?>>SERVIDOR</option>
                                        <option value="SIM CARD" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "SIM CARD") ? "selected" : ""; ?>>SIM CARD</option>
                                        <option value="SMART-LOCK" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "SMART-LOCK") ? "selected" : ""; ?>>SMART-LOCK</option>
                                        <option value="SWITCH" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "SWITCH") ? "selected" : ""; ?>>SWITCH</option>
                                        <option value="TABLET" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "TABLET") ? "selected" : ""; ?>>TABLET</option>
                                        <option value="TARJETA DE IDENTIFICACION" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "TARJETA DE IDENTIFICACION") ? "selected" : ""; ?>>TARJETA DE IDENTIFICACION</option>
                                        <option value="TECLADO" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "TECLADO") ? "selected" : ""; ?>>TECLADO</option>
                                        <option value="TELEFONO" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "TELEFONO") ? "selected" : ""; ?>>TELEFONO</option>
                                        <option value="TELEFONO-IP" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "TELEFONO-IP") ? "selected" : ""; ?>>TELEFONO-IP</option>
                                        <option value="TV" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "TV") ? "selected" : ""; ?>>TV</option>
                                        <option value="UPS" <?php echo (isset($_POST["tipo"]) && $_POST["tipo"] === "UPS") ? "selected" : ""; ?>>UPS</option>
                                    </select>
                                    
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
