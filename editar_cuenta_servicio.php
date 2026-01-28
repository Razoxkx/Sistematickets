<?php
session_start();
require_once 'includes/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

// Verificar permisos: solo admin
if (($_SESSION["role"] ?? "") !== "admin") {
    header("Location: dashboard.php");
    exit();
}

$error = "";
$success = "";
$cuenta = null;

// Obtener ID de la cuenta
$cuenta_id = $_GET["id"] ?? "";
if (empty($cuenta_id)) {
    header("Location: cuentas_servicio.php");
    exit();
}

try {
    $stmt = $conexion->prepare("SELECT * FROM cuentas_servicio WHERE id = ?");
    $stmt->execute([$cuenta_id]);
    $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cuenta) {
        header("Location: cuentas_servicio.php?error=not_found");
        exit();
    }
} catch (PDOException $e) {
    $error = "Error al obtener la cuenta: " . $e->getMessage();
}

// Procesar actualización
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["actualizar_cuenta"])) {
    $plataforma = trim($_POST["plataforma"] ?? "");
    $correo = trim($_POST["correo"] ?? "");
    $contraseña = $_POST["contraseña"] ?? "";
    $descripcion = trim($_POST["descripcion"] ?? "");
    
    if (empty($plataforma) || empty($correo) || empty($contraseña)) {
        $error = "Plataforma, correo y contraseña son obligatorios";
    } else {
        try {
            $stmt = $conexion->prepare("
                UPDATE cuentas_servicio 
                SET plataforma = ?, correo = ?, contraseña = ?, descripcion = ?, fecha_ultima_modificacion = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$plataforma, $correo, $contraseña, $descripcion, $cuenta_id]);
            
            $success = "Cuenta actualizada correctamente";
            
            // Recargar datos
            $stmt = $conexion->prepare("SELECT * FROM cuentas_servicio WHERE id = ?");
            $stmt->execute([$cuenta_id]);
            $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $error = "Error al actualizar la cuenta: " . $e->getMessage();
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="css/dark-mode.css" rel="stylesheet">
    <title>Editar Cuenta de Servicio</title>
    <style>
        body {
            background: linear-gradient(to bottom, #f8f9fa, #ffffff);
            margin-left: 280px;
        }
        
        [data-bs-theme="dark"] body {
            background: linear-gradient(to bottom, #1a1a1a, #0d0d0d);
        }

        /* Contenedor principal responsivo */
        .contenedor-principal {
            margin-top: 20px;
            padding-left: 0;
            padding-right: 0;
        }

        @media (max-width: 768px) {
            body {
                margin-left: 70px;
            }

            .contenedor-principal {
                margin-top: 15px;
            }
        }

        /* Header responsivo */
        .header-editar {
            flex-wrap: wrap;
            gap: 10px;
            margin-right: 0 !important;
        }

        .header-editar h1 {
            margin-bottom: 5px;
            margin-right: 0;
            font-size: clamp(1.5rem, 4vw, 1.8rem);
        }

        /* Container adjustments */
        .container-fluid {
            padding-left: 20px !important;
            padding-right: 20px !important;
        }

        @media (max-width: 768px) {
            .container-fluid {
                padding-left: 15px !important;
                padding-right: 15px !important;
            }
        }

        /* Cards responsivas */
        @media (max-width: 768px) {
            .row .col-md-8 {
                flex: 0 0 auto;
                width: 100%;
            }

            .row .col-md-4 {
                flex: 0 0 auto;
                width: 100%;
            }

            .header-editar {
                flex-direction: column;
                margin-right: 0 !important;
            }

            .header-editar .col-md-8,
            .header-editar .col-md-4 {
                width: 100% !important;
            }

            .header-editar .text-end {
                text-align: left !important;
            }
        }

        /* Ajustes para las columnas */
        .row {
            margin-right: 0 !important;
            margin-left: 0 !important;
        }

        .col-md-8 {
            padding-right: 10px;
            padding-left: 0;
        }

        .col-md-4 {
            padding-right: 10px;
            padding-left: 0;
        }

        .col-12 {
            padding-left: 0;
            padding-right: 5px;
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
    
    <div class="container-fluid contenedor-principal">
        <div class="row header-editar mb-4">
            <div class="col-12">
                <h1><i class="bi bi-pencil-square"></i> Editar Cuenta de Servicio</h1>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <a href="cuentas_servicio.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($cuenta): ?>
                            <form method="POST" action="">
                                <!-- Plataforma -->
                                <div class="mb-3">
                                    <label for="plataforma" class="form-label">
                                        <i class="bi bi-window"></i> Plataforma <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="plataforma" name="plataforma" required value="<?php echo htmlspecialchars($cuenta["plataforma"]); ?>">
                                </div>

                                <!-- Usuario/Cuenta -->
                                <div class="mb-3">
                                    <label for="correo" class="form-label">
                                        <i class="bi bi-person"></i> Usuario/Cuenta <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="correo" name="correo" required value="<?php echo htmlspecialchars($cuenta["correo"]); ?>">
                                </div>

                                <!-- Contraseña -->
                                <div class="mb-3">
                                    <label for="contraseña" class="form-label">
                                        <i class="bi bi-lock"></i> Contraseña <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="contraseña" name="contraseña" required value="<?php echo htmlspecialchars($cuenta["contraseña"]); ?>">
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Descripción -->
                                <div class="mb-3">
                                    <label for="descripcion" class="form-label">
                                        <i class="bi bi-file-text"></i> Descripción
                                    </label>
                                    <textarea class="form-control" id="descripcion" name="descripcion" rows="4"><?php echo htmlspecialchars($cuenta["descripcion"]); ?></textarea>
                                </div>

                                <!-- Información de auditoría -->
                                <div class="bg-light p-3 rounded mb-3">
                                    <small class="text-muted">
                                        <i class="bi bi-clock-history"></i> 
                                        Creado: <?php echo formatearFechaHora($cuenta["fecha_creacion"]); ?><br>
                                        Última modificación: <?php echo formatearFechaHora($cuenta["fecha_ultima_modificacion"]); ?>
                                    </small>
                                </div>

                                <hr>

                                <!-- Botones -->
                                <div class="d-flex gap-2">
                                    <button type="submit" name="actualizar_cuenta" class="btn btn-primary">
                                        <i class="bi bi-check-circle"></i> Guardar Cambios
                                    </button>
                                    <a href="cuentas_servicio.php" class="btn btn-secondary">
                                        <i class="bi bi-x-circle"></i> Cancelar
                                    </a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Panel informativo -->
            <div class="col-md-4">
                <div class="card bg-warning text-dark">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-exclamation-triangle"></i> Cambios de Seguridad</h5>
                        <hr>
                        <ul class="small mb-0">
                            <li>Cualquier cambio será registrado</li>
                            <li>Considera cambiar la contraseña si está comprometida</li>
                            <li>Los administradores pueden ver el historial</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const input = document.getElementById('contraseña');
            if (input.type === 'password') {
                input.type = 'text';
            } else {
                input.type = 'password';
            }
        }

        function toggleDarkMode() {
            const htmlRoot = document.documentElement;
            const isDark = htmlRoot.getAttribute('data-bs-theme') === 'dark';
            
            if (isDark) {
                htmlRoot.removeAttribute('data-bs-theme');
                localStorage.setItem('darkMode', 'disabled');
            } else {
                htmlRoot.setAttribute('data-bs-theme', 'dark');
                localStorage.setItem('darkMode', 'enabled');
            }
        }
    </script>
</body>
</html>
