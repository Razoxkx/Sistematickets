<?php
session_start();
require_once '../includes/config.php';

// Prevenir cacheo
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Verificar si el usuario está logueado
if (!isset($_SESSION["user_id"])) {
    header("Location: ../index.php");
    exit();
}

// Verificar permisos
$permisos = ['tisupport', 'admin'];
if (!in_array($_SESSION["role"] ?? "viewer", $permisos)) {
    header("Location: tickets.php");
    exit();
}

// Obtener lista de solicitantes únicos
try {
    $stmt = $conexion->query("SELECT DISTINCT nombre_solicitante FROM tickets ORDER BY nombre_solicitante");
    $solicitantes = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $solicitantes = [];
}

$error = "";
?>

<!DOCTYPE html>
<html lang="es" id="htmlRoot">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="src/img/D.jpg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/dark-mode.css" rel="stylesheet">
    <title>Reportes de Tickets</title>
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
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card shadow">
                    <div class="card-body">
                        <h2 class="card-title mb-4">Descargar Reportes de Tickets</h2>
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="../docs/generar_reporte_pdf.php" target="_blank">
                            <div class="mb-4">
                                <h5>Filtros del Reporte</h5>
                                <hr>
                            </div>
                            
                            <!-- Filtro por Estado -->
                            <div class="mb-3">
                                <label for="filtro_estado" class="form-label"><strong>Estado del Ticket</strong></label>
                                <select class="form-select" id="filtro_estado" name="filtro_estado">
                                    <option value="">-- Todos los estados --</option>
                                    <option value="sin abrir">Sin abrir</option>
                                    <option value="en conocimiento">En conocimiento</option>
                                    <option value="en proceso">En proceso</option>
                                    <option value="pendiente de cierre">Pendiente de cierre</option>
                                    <option value="ticket cerrado">Ticket Cerrado</option>
                                </select>
                                <small class="text-muted">Si no seleccionas, se incluirán todos los estados</small>
                            </div>
                            
                            <!-- Filtro por Solicitante -->
                            <div class="mb-3">
                                <label for="filtro_solicitante" class="form-label"><strong>Solicitante</strong></label>
                                <select class="form-select" id="filtro_solicitante" name="filtro_solicitante">
                                    <option value="">-- Todos los solicitantes --</option>
                                    <?php foreach ($solicitantes as $solicitante): ?>
                                        <option value="<?php echo htmlspecialchars($solicitante); ?>">
                                            <?php echo htmlspecialchars($solicitante); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Si no seleccionas, se incluirán todos los solicitantes</small>
                            </div>
                            
                            <!-- Filtro por Rango de Fechas -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="fecha_desde" class="form-label"><strong>Fecha Desde</strong></label>
                                    <input type="date" class="form-control" id="fecha_desde" name="fecha_desde">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="fecha_hasta" class="form-label"><strong>Fecha Hasta</strong></label>
                                    <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta">
                                </div>
                            </div>
                            
                            <!-- Opciones de Reporte -->
                            <div class="mb-4">
                                <h5>Tipo de Reporte</h5>
                                <hr>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" id="tipo_completo" name="tipo_reporte" value="completo" checked>
                                <label class="form-check-label" for="tipo_completo">
                                    <strong>Reporte Completo</strong> - Incluye todos los detalles y comentarios
                                </label>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" id="tipo_resumen" name="tipo_reporte" value="resumen">
                                <label class="form-check-label" for="tipo_resumen">
                                    <strong>Reporte Resumido</strong> - Solo información principal
                                </label>
                            </div>
                            
                            <div class="mb-4">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    📄 Descargar PDF
                                </button>
                                <a href="tickets.php" class="btn btn-secondary btn-lg">Volver</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Validar fechas
        document.querySelector('form').addEventListener('submit', function(e) {
            const fechaDesde = document.querySelector('[name="fecha_desde"]').value;
            const fechaHasta = document.querySelector('[name="fecha_hasta"]').value;
            
            if (fechaDesde && fechaHasta && fechaDesde > fechaHasta) {
                e.preventDefault();
                alert('La fecha "Desde" no puede ser mayor que la fecha "Hasta"');
                return false;
            }
        });
    </script>
</body>
</html>
