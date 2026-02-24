<?php
session_start();
require_once 'includes/config.php';

// Prevenir cacheo
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

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
$tickets = [];
$busqueda = $_GET["buscar"] ?? "";
$pagina = (int)($_GET["pagina"] ?? 1);
$pagina = max(1, $pagina);

try {
    $where = "es_cerrado = 1";
    $params = [];
    
    if (!empty($busqueda)) {
        $where .= " AND (ticket_number LIKE ? OR titulo LIKE ? OR nombre_solicitante LIKE ?)";
        $params = ["%$busqueda%", "%$busqueda%", "%$busqueda%"];
    }
    
    // Contar total
    $stmt = $conexion->prepare("SELECT COUNT(*) as total FROM tickets WHERE $where");
    $stmt->execute($params);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    $total = $resultado["total"];
    $por_pagina = 9;
    $total_paginas = ceil($total / $por_pagina);
    $offset = ($pagina - 1) * $por_pagina;
    
    // Obtener tickets
    $stmt = $conexion->prepare("
        SELECT * FROM tickets 
        WHERE $where
        ORDER BY fecha_ultima_modificacion DESC
        LIMIT ? OFFSET ?
    ");
    
    // Agregar límite y offset a los parámetros
    $stmt->bindValue(1, $por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    
    // Ejecutar con los primeros parámetros
    $all_params = $params;
    $all_params[] = $por_pagina;
    $all_params[] = $offset;
    
    $stmt = $conexion->prepare("
        SELECT * FROM tickets 
        WHERE $where
        ORDER BY fecha_ultima_modificacion DESC
        LIMIT :limit OFFSET :offset
    ");
    
    foreach ($params as $index => $param) {
        $stmt->bindValue(':param' . ($index + 1), $param);
    }
    $stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    // Mejor enfoque - usar array_merge
    $query = "SELECT * FROM tickets WHERE $where ORDER BY fecha_ultima_modificacion DESC LIMIT " . $por_pagina . " OFFSET " . $offset;
    $stmt = $conexion->prepare($query);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Error al obtener tickets: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es" id="htmlRoot">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/dark-mode.css" rel="stylesheet">
    <title>Tickets Cerrados</title>
    <style>
        .ticket-list-row { border-bottom: 1px solid #dee2e6; padding: 15px 0; cursor: pointer; }
        .ticket-list-row:hover { background-color: #f8f9fa; }
        [data-bs-theme="dark"] .ticket-list-row:hover { background-color: #2d3035; }
        .table-responsive { margin: 0 -12px; padding: 0 12px; }
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
    
    <div class="container-fluid mt-5">
        <div class="row">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3><i class="bi bi-ticket-detailed"></i> Tickets Cerrados</h3>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Buscador -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="d-flex gap-2">
                            <input type="text" name="buscar" class="form-control" placeholder="Buscar por ticket, título o solicitante..." value="<?php echo htmlspecialchars($busqueda); ?>">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Buscar</button>
                            <a href="tickets_cerrados.php" class="btn btn-secondary">Limpiar</a>
                        </form>
                    </div>
                </div>
                
                <!-- Lista de Tickets -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">📋 Total de tickets cerrados: <?php echo $total; ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($tickets) == 0): ?>
                            <p class="text-muted text-center">No hay tickets cerrados <?php echo !empty($busqueda) ? 'que coincidan con la búsqueda' : ''; ?></p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Ticket</th>
                                            <th>Título</th>
                                            <th>Estado</th>
                                            <th>Solicitante</th>
                                            <th>Fecha Cierre</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tickets as $ticket): ?>
                                            <tr class="ticket-list-row">
                                                <td>
                                                    <a href="ver_ticket.php?id=<?php echo $ticket['id']; ?>" class="text-decoration-none">
                                                        <strong><?php echo htmlspecialchars($ticket["ticket_number"]); ?></strong>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars(substr($ticket["titulo"], 0, 50)); ?></td>
                                                <td>
                                                    <span class="badge bg-success"><i class="bi bi-check-circle"></i> Cerrado</span>
                                                </td>
                                                <td><?php echo htmlspecialchars($ticket["nombre_solicitante"]); ?></td>
                                                <td>
                                                    <small class="text-muted"><?php echo formatearFechaHora($ticket["fecha_ultima_modificacion"]); ?></small>
                                                </td>
                                                <td>
                                                    <a href="ver_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-info">👁️ Ver</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Paginación -->
                            <?php if ($total_paginas > 1): ?>
                                <nav aria-label="Paginación" class="mt-4">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($pagina > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?pagina=1<?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>">Primera</a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?pagina=<?php echo $pagina - 1; ?><?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>">Anterior</a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $pagina - 2); $i <= min($total_paginas, $pagina + 2); $i++): ?>
                                            <li class="page-item <?php echo $i == $pagina ? 'active' : ''; ?>">
                                                <a class="page-link" href="?pagina=<?php echo $i; ?><?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($pagina < $total_paginas): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?pagina=<?php echo $pagina + 1; ?><?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>">Siguiente</a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?pagina=<?php echo $total_paginas; ?><?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>">Última</a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
