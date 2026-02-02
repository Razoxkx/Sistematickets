<?php
session_start();
require_once 'includes/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

// Obtener información del usuario
try {
    // Verificar si la columna existe
    $check_columns = $conexion->query("SHOW COLUMNS FROM users");
    $columns = $check_columns->fetchAll(PDO::FETCH_ASSOC);
    $column_names = array_column($columns, 'Field');
    $has_necesita_cambiar = in_array('necesita_cambiar_password', $column_names);
    
    if ($has_necesita_cambiar) {
        $stmt = $conexion->prepare("SELECT necesita_cambiar_password FROM users WHERE id = ?");
    } else {
        $stmt = $conexion->prepare("SELECT 1 as necesita_cambiar_password FROM users WHERE id = ?");
    }
    
    $stmt->execute([$_SESSION["user_id"]]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Si el usuario ya cambió su contraseña, redirigir al dashboard
    // (Solo si la columna existe y vale 0)
    if ($has_necesita_cambiar && $user && !$user["necesita_cambiar_password"]) {
        header("Location: dashboard.php");
        exit();
    }
} catch (PDOException $e) {
    // Error en la consulta, permitir continuar
}

$error = "";
$success = "";

// Procesar el cambio de contraseña
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $password_actual = $_POST["password_actual"] ?? "";
    $password_nueva = $_POST["password_nueva"] ?? "";
    $password_confirmar = $_POST["password_confirmar"] ?? "";
    
    if (empty($password_actual) || empty($password_nueva) || empty($password_confirmar)) {
        $error = "Todos los campos son obligatorios";
    } elseif ($password_nueva !== $password_confirmar) {
        $error = "Las contraseñas no coinciden";
    } elseif (strlen($password_nueva) < 6) {
        $error = "La nueva contraseña debe tener al menos 6 caracteres";
    } else {
        try {
            // Obtener la contraseña actual del usuario
            $stmt = $conexion->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION["user_id"]]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verificar que la contraseña actual sea correcta
            if (!$user || !password_verify($password_actual, $user["password"])) {
                $error = "La contraseña actual es incorrecta";
            } else {
                // Actualizar la contraseña
                $password_hash = password_hash($password_nueva, PASSWORD_BCRYPT);
                
                // Verificar si la columna necesita_cambiar_password existe
                $check_columns = $conexion->query("SHOW COLUMNS FROM users");
                $columns = $check_columns->fetchAll(PDO::FETCH_ASSOC);
                $column_names = array_column($columns, 'Field');
                $has_necesita_cambiar = in_array('necesita_cambiar_password', $column_names);
                
                if ($has_necesita_cambiar) {
                    $stmt = $conexion->prepare("UPDATE users SET password = ?, necesita_cambiar_password = 0 WHERE id = ?");
                    $stmt->execute([$password_hash, $_SESSION["user_id"]]);
                } else {
                    $stmt = $conexion->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$password_hash, $_SESSION["user_id"]]);
                }
                
                $success = "Contraseña cambida exitosamente. Redireccionando...";
                // No usar header Refresh, la redirección se maneja con JavaScript
            }
        } catch (PDOException $e) {
            $error = "Error al cambiar contraseña: " . $e->getMessage();
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
    <title>Cambiar Contraseña</title>
    <style>
        body {
            
        }
        body.fade-out {
            opacity: 0;
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
    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">⚠️ Cambiar Contraseña Temporal</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            Por seguridad, debe cambiar su contraseña temporal en el primer acceso.
                        </p>
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                ✓ <?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="password_actual" class="form-label">Contraseña Actual *</label>
                                <input type="password" class="form-control" id="password_actual" name="password_actual" required autofocus>
                                <small class="text-muted">Por defecto: 111111</small>
                            </div>
                            
                            <hr>
                            
                            <div class="mb-3">
                                <label for="password_nueva" class="form-label">Nueva Contraseña *</label>
                                <input type="password" class="form-control" id="password_nueva" name="password_nueva" required>
                                <small class="text-muted">Mínimo 6 caracteres</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password_confirmar" class="form-label">Confirmar Nueva Contraseña *</label>
                                <input type="password" class="form-control" id="password_confirmar" name="password_confirmar" required>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Cambiar Contraseña</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Detectar si hubo un cambio exitoso de contraseña
        const successAlert = document.querySelector('.alert-success');
        if (successAlert) {
            // Esperar un poco para que se muestre el mensaje
            setTimeout(() => {
                // Agregar la clase para la animación de desvanecimiento
                document.body.classList.add('fade-out');
                // Redirigir después de que termine la animación
                setTimeout(() => {
                    window.location.href = 'dashboard.php';
                }, 600);
            }, 500);
        }
    </script>
</body>
</html>
