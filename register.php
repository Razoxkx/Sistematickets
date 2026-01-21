<?php
session_start();
require_once 'includes/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

// Verificar permisos: solo tisupport y admin pueden agregar usuarios
$permisos_agregar = ['tisupport', 'admin'];
if (!in_array($_SESSION["role"] ?? "viewer", $permisos_agregar)) {
    header("Location: dashboard.php");
    exit();
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"] ?? "";
    $password = $_POST["password"] ?? "";
    $password_confirm = $_POST["password_confirm"] ?? "";
    $role = $_POST["role"] ?? "viewer";
    
    // Solo admin puede crear otros roles que no sean viewer
    if ($role !== "viewer" && $_SESSION["role"] !== "admin") {
        $error = "No tienes permisos para crear usuarios con este rol";
    } elseif (empty($username) || empty($password) || empty($password_confirm)) {
        $error = "Todos los campos son obligatorios";
    } elseif (strlen($username) < 3) {
        $error = "El usuario debe tener al menos 3 caracteres";
    } elseif (strlen($password) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres";
    } elseif ($password !== $password_confirm) {
        $error = "Las contraseñas no coinciden";
    } else {
        try {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conexion->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $stmt->execute([$username, $password_hash, $role]);
            $success = "Usuario '$username' creado correctamente con rol '$role'";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), "Duplicate entry") !== false) {
                $error = "El usuario '$username' ya existe";
            } else {
                $error = "Error al crear el usuario: " . $e->getMessage();
            }
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
    <title>Agregar Usuario</title>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <h3 class="card-title text-center mb-4">Crear Nuevo Usuario</h3>
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                ✓ <?php echo htmlspecialchars($success); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="username" class="form-label">Nombre de usuario</label>
                                <input type="text" class="form-control" id="username" name="username" required placeholder="Ingrese el nombre de usuario">
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Contraseña</label>
                                <input type="password" class="form-control" id="password" name="password" required placeholder="Ingrese la contraseña">
                            </div>
                            
                            <div class="mb-3">
                                <label for="password_confirm" class="form-label">Confirmar Contraseña</label>
                                <input type="password" class="form-control" id="password_confirm" name="password_confirm" required placeholder="Confirme la contraseña">
                            </div>
                            
                            <div class="mb-3">
                                <label for="role" class="form-label">Rol</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="viewer">Lector (solo lectura)</option>
                                    <?php if ($_SESSION["role"] === "admin"): ?>
                                        <option value="tisupport">Soporte TI</option>
                                        <option value="admin">Admin</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">Crear Usuario</button>
                            <a href="dashboard.php" class="btn btn-secondary w-100 mt-2">Volver</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
