<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Dashboard</title>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-body">
                        <h1>Bienvenido a tu Dashboard</h1>
                        <p class="lead">Has iniciado sesión como <strong><?php echo htmlspecialchars($_SESSION["username"]); ?></strong></p>
                        
                        <hr>
                        
                        <h4>Tu Rol: 
                            <?php 
                            $role = $_SESSION["role"] ?? "viewer";
                            $badge_color = match($role) {
                                'admin' => 'danger',
                                'tisupport' => 'warning',
                                default => 'info'
                            };
                            ?>
                            <span class="badge bg-<?php echo $badge_color; ?>"><?php echo traducirRol($role); ?></span>
                        </h4>
                        
                        <div class="mt-4">
                            <h5>Tus Permisos:</h5>
                            <ul class="list-group">
                                <li class="list-group-item">
                                    <strong>Ver información:</strong> ✓ (Todos)
                                </li>
                                <?php if (in_array($role, ['tisupport', 'admin'])): ?>
                                <li class="list-group-item">
                                    <strong>Agregar usuarios:</strong> ✓
                                </li>
                                <?php else: ?>
                                <li class="list-group-item">
                                    <strong>Agregar usuarios:</strong> ✗
                                </li>
                                <?php endif; ?>
                                
                                <?php if ($role === 'admin'): ?>
                                <li class="list-group-item">
                                    <strong>Modificar roles:</strong> ✓
                                </li>
                                <li class="list-group-item">
                                    <strong>Eliminar usuarios:</strong> ✓
                                </li>
                                <?php else: ?>
                                <li class="list-group-item">
                                    <strong>Modificar roles:</strong> ✗
                                </li>
                                <li class="list-group-item">
                                    <strong>Eliminar usuarios:</strong> ✗
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        
                        <hr>
                        
                        <?php if (in_array($role, ['tisupport', 'admin'])): ?>
                            <a href="register.php" class="btn btn-success">Agregar Nuevo Usuario</a>
                        <?php endif; ?>
                        
                        <?php if ($role === 'admin'): ?>
                            <a href="usuarios.php" class="btn btn-primary">Gestionar Usuarios</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
