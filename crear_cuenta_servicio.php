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

// Procesar creación
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["crear_cuenta"])) {
    $plataforma = trim($_POST["plataforma"] ?? "");
    $correo = trim($_POST["correo"] ?? "");
    $contraseña = $_POST["contraseña"] ?? "";
    $descripcion = trim($_POST["descripcion"] ?? "");
    
    if (empty($plataforma) || empty($correo) || empty($contraseña)) {
        $error = "Plataforma, correo y contraseña son obligatorios";
    } else {
        try {
            $stmt = $conexion->prepare("
                INSERT INTO cuentas_servicio (plataforma, correo, contraseña, descripcion, usuario_creador)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$plataforma, $correo, $contraseña, $descripcion, $_SESSION["user_id"]]);
            
            header("Location: cuentas_servicio.php?success=creado");
            exit();
        } catch (PDOException $e) {
            $error = "Error al crear la cuenta: " . $e->getMessage();
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
    <title>Crear Cuenta de Servicio</title>
    <style>
        body {
            background: linear-gradient(to bottom, #f8f9fa, #ffffff);
        }
        
        [data-bs-theme="dark"] body {
            background: linear-gradient(to bottom, #1a1a1a, #0d0d0d);
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
    
    <div class="container-fluid" style="margin-left: 280px; padding: 20px;">
        <div class="row mb-4">
            <div class="col-md-8">
                <h1><i class="bi bi-plus-circle"></i> Crear Cuenta de Servicio</h1>
            </div>
            <div class="col-md-4 text-end">
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

                        <form method="POST" action="">
                            <!-- Plataforma -->
                            <div class="mb-3">
                                <label for="plataforma" class="form-label">
                                    <i class="bi bi-window"></i> Plataforma <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="plataforma" name="plataforma" required placeholder="ej: Gmail, AWS, Slack, GitHub...">
                                <small class="text-muted">Nombre del servicio o plataforma</small>
                            </div>

                            <!-- Correo -->
                            <div class="mb-3">
                                <label for="correo" class="form-label">
                                    <i class="bi bi-envelope"></i> Correo <span class="text-danger">*</span>
                                </label>
                                <input type="email" class="form-control" id="correo" name="correo" required placeholder="usuario@ejemplo.com">
                                <small class="text-muted">Correo o usuario de la cuenta</small>
                            </div>

                            <!-- Contraseña -->
                            <div class="mb-3">
                                <label for="contraseña" class="form-label">
                                    <i class="bi bi-lock"></i> Contraseña <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="contraseña" name="contraseña" required placeholder="••••••••">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Contraseña de acceso a la plataforma</small>
                            </div>

                            <!-- Descripción -->
                            <div class="mb-3">
                                <label for="descripcion" class="form-label">
                                    <i class="bi bi-file-text"></i> Descripción
                                </label>
                                <textarea class="form-control" id="descripcion" name="descripcion" rows="4" placeholder="Notas adicionales, permisos, información relevante..."></textarea>
                                <small class="text-muted">Información adicional sobre esta cuenta</small>
                            </div>

                            <hr>

                            <!-- Botones -->
                            <div class="d-flex gap-2">
                                <button type="submit" name="crear_cuenta" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Crear Cuenta
                                </button>
                                <a href="cuentas_servicio.php" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Cancelar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Panel informativo -->
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-shield-check"></i> Información de Seguridad</h5>
                        <hr class="border-white-50">
                        <ul class="small mb-0">
                            <li>Las contraseñas se guardan de forma segura</li>
                            <li>Solo los administradores pueden revelar contraseñas</li>
                            <li>Se requiere validación con contraseña de admin</li>
                            <li>Todos los accesos se registran en auditoría</li>
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
