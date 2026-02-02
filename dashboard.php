<?php
session_start();
require_once 'includes/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

// Obtener cantidad de tickets asignados al usuario
$tickets_asignados = 0;
try {
    $stmt = $conexion->prepare("SELECT COUNT(*) as total FROM tickets WHERE responsable = ? AND es_cerrado = 0");
    $stmt->execute([$_SESSION["user_id"]]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $tickets_asignados = $result['total'] ?? 0;
} catch (PDOException $e) {
    // Si hay error, dejar en 0
}

// Fecha actual
$fecha_actual = new DateTime();
$fecha_formateada = $fecha_actual->format('d \d\e F \d\e Y');
?>

<!DOCTYPE html>
<html lang="es" id="htmlRoot">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/dark-mode.css" rel="stylesheet">
    <title>Dashboard</title>
    <style>
        a[href*="mis_tickets"] .card {
            
        }
        a[href*="mis_tickets"] .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.2);
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
            <div class="col-md-8 offset-md-2">
                <div class="card card-gestion">
                    <div class="card-body">
                        <h1>Bienvenido a tu Dashboard</h1>
                        <p class="lead">Has iniciado sesión como <strong><?php echo htmlspecialchars($_SESSION["username"]); ?></strong></p>
                        
                        <hr>
                        
                        <!-- Tarjetas de información -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card card-gestion border-primary">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">📅 Fecha Actual</h5>
                                        <p class="card-text display-6"><?php echo ucfirst($fecha_formateada); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <a href="tickets.php?mis_tickets" style="text-decoration: none; color: inherit;">
                                    <div class="card card-gestion border-success" style="cursor: pointer;">
                                        <div class="card-body text-center">
                                            <h5 class="card-title">🎫 Tickets Asignados</h5>
                                            <p class="card-text display-6"><?php echo $tickets_asignados; ?></p>
                                            <small class="text-muted">Abiertos y en progreso</small>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                        
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
