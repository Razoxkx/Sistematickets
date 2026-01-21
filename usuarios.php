<?php
session_start();
require_once 'includes/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

// Verificar permisos: solo admin puede acceder
if (($_SESSION["role"] ?? "") !== "admin") {
    header("Location: dashboard.php");
    exit();
}

$error = "";
$success = "";

// Eliminar usuario
if (isset($_GET["delete"]) && is_numeric($_GET["delete"])) {
    $id = $_GET["delete"];
    
    // No permitir eliminarse a sí mismo
    if ($id == $_SESSION["user_id"]) {
        $error = "No puedes eliminar tu propia cuenta";
    } else {
        try {
            $stmt = $conexion->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $success = "Usuario eliminado correctamente";
        } catch (PDOException $e) {
            $error = "Error al eliminar el usuario: " . $e->getMessage();
        }
    }
}

// Modificar rol
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["usuario_id"]) && isset($_POST["nuevo_rol"])) {
    $usuario_id = $_POST["usuario_id"];
    $nuevo_rol = $_POST["nuevo_rol"];
    
    // No permitir cambiar su propio rol
    if ($usuario_id == $_SESSION["user_id"]) {
        $error = "No puedes cambiar tu propio rol";
    } else {
        try {
            $stmt = $conexion->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$nuevo_rol, $usuario_id]);
            $success = "Rol actualizado correctamente";
        } catch (PDOException $e) {
            $error = "Error al actualizar el rol: " . $e->getMessage();
        }
    }
}

// Obtener todos los usuarios
try {
    $stmt = $conexion->query("SELECT id, username, role, created_at FROM users ORDER BY created_at DESC");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al obtener usuarios: " . $e->getMessage();
    $usuarios = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Gestionar Usuarios</title>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12">
                <h2 class="mb-4">Gestionar Usuarios</h2>
                
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
                
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Usuario</th>
                                <th>Rol</th>
                                <th>Creado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user["id"]); ?></td>
                                <td><?php echo htmlspecialchars($user["username"]); ?></td>
                                <td>
                                    <?php if ($user["id"] == $_SESSION["user_id"]): ?>
                                        <span class="badge bg-primary"><?php echo traducirRol($user["role"]); ?> (TÚ)</span>
                                    <?php else: ?>
                                        <form method="POST" style="display: inline;">
                                            <select name="nuevo_rol" class="form-select form-select-sm" onchange="this.form.submit()" style="max-width: 150px; display: inline-block;">
                                                <option value="viewer" <?php echo $user["role"] === "viewer" ? "selected" : ""; ?>>Lector</option>
                                                <option value="tisupport" <?php echo $user["role"] === "tisupport" ? "selected" : ""; ?>>Soporte TI</option>
                                                <option value="admin" <?php echo $user["role"] === "admin" ? "selected" : ""; ?>>Admin</option>
                                            </select>
                                            <input type="hidden" name="usuario_id" value="<?php echo htmlspecialchars($user["id"]); ?>">
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($user["created_at"])); ?></td>
                                <td>
                                    <?php if ($user["id"] != $_SESSION["user_id"]): ?>
                                        <a href="usuarios.php?delete=<?php echo htmlspecialchars($user["id"]); ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Estás seguro de eliminar este usuario?');">Eliminar</a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <a href="dashboard.php" class="btn btn-secondary mt-3">Volver</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
