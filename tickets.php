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
    header("Location: dashboard.php");
    exit();
}

$error = "";
$tickets = [];
$busqueda = $_GET["buscar"] ?? "";
$estado_filtro = $_GET["estado"] ?? "";
$pagina = max(1, (int)($_GET["pagina"] ?? 1));
$orden = $_GET["orden"] ?? "fecha";
$direccion = $_GET["dir"] ?? "DESC";

// Validar orden para evitar SQL injection
$ordenes_validas = ['ticket_number', 'titulo', 'estado', 'fecha', 'solicitante', 'responsable'];
if (!in_array($orden, $ordenes_validas)) {
    $orden = "fecha";
}

// Validar dirección
if (!in_array($direccion, ['ASC', 'DESC'])) {
    $direccion = "DESC";
}

// Estados válidos abiertos
$estados_abiertos = ['sin abrir', 'en conocimiento', 'en proceso', 'pendiente de cierre'];
if (!empty($estado_filtro) && !in_array($estado_filtro, $estados_abiertos)) {
    $estado_filtro = "";
}

$tickets_por_pagina = 9;
$offset = ($pagina - 1) * $tickets_por_pagina;
$total_tickets = 0;
$total_paginas = 1;

// Mapeo de órdenes a columnas SQL
$orden_map = [
    'ticket_number' => 't.ticket_number',
    'titulo' => 't.titulo',
    'estado' => 't.estado',
    'fecha' => 't.fecha_creacion',
    'solicitante' => 't.nombre_solicitante',
    'responsable' => 't.responsable'
];
$columna_orden = $orden_map[$orden];

// Obtener conteos por estado
$conteos_estado = [];
foreach (['sin abrir', 'en conocimiento', 'en proceso', 'pendiente de cierre'] as $est) {
    $stmt_conteo = $conexion->prepare("SELECT COUNT(*) as total FROM tickets WHERE es_cerrado = 0 AND estado = ?");
    $stmt_conteo->execute([$est]);
    $conteos_estado[$est] = $stmt_conteo->fetch(PDO::FETCH_ASSOC)['total'];
}

// Obtener conteo de tickets cerrados
$stmt_conteo_cerrados = $conexion->prepare("SELECT COUNT(*) as total FROM tickets WHERE es_cerrado = 1");
$stmt_conteo_cerrados->execute();
$conteo_cerrados = $stmt_conteo_cerrados->fetch(PDO::FETCH_ASSOC)['total'];

try {
    // Contar total de tickets
    $where = "es_cerrado = 0";
    $params = [];
    
    if (!empty($estado_filtro)) {
        $where .= " AND estado = ?";
        $params[] = $estado_filtro;
    }
    
    if (!empty($busqueda)) {
        $where .= " AND (ticket_number LIKE ? OR titulo LIKE ? OR nombre_solicitante LIKE ?)";
        $params[] = '%' . $busqueda . '%';
        $params[] = '%' . $busqueda . '%';
        $params[] = '%' . $busqueda . '%';
    }
    
    $stmt_count = $conexion->prepare("SELECT COUNT(*) as total FROM tickets WHERE " . $where);
    $stmt_count->execute($params);
    $total_tickets = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Obtener tickets
    $stmt = $conexion->prepare("
        SELECT t.*, u.username as creador_nombre
        FROM tickets t
        JOIN users u ON t.usuario_creador = u.id
        WHERE " . $where . "
        ORDER BY " . $columna_orden . " " . $direccion . "
        LIMIT " . intval($tickets_por_pagina) . " OFFSET " . intval($offset) . "
    ");
    $stmt->execute($params);
    
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_paginas = ceil($total_tickets / $tickets_por_pagina);
    
} catch (PDOException $e) {
    $error = "Error al obtener tickets: " . $e->getMessage();
}

// Colores según estado
function getEstadoColor($estado) {
    return match($estado) {
        'sin abrir' => 'secondary',
        'en conocimiento' => 'info',
        'en proceso' => 'warning',
        'ticket cerrado' => 'success',
        'pendiente de cierre' => 'danger',
        default => 'secondary'
    };
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Gestionar Tickets</title>
    <style>
        .ticket-list-row { border-bottom: 1px solid #dee2e6; padding: 15px 0; cursor: pointer; transition: background-color 0.2s; }
        .ticket-list-row:hover { background-color: #f8f9fa; }
        [data-bs-theme="dark"] .ticket-list-row:hover { background-color: #2d3035; }
        .table-responsive { margin: 0 -12px; padding: 0 12px; }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container mt-5">
        <div class="row mb-4">
            <div class="col-md-12">
                <h2>Gestionar Tickets</h2>
            </div>
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
                <form method="GET" action="" class="d-flex gap-2">
                    <input type="text" class="form-control" name="buscar" placeholder="Buscar por número (DCD...), título o solicitante" value="<?php echo htmlspecialchars($busqueda); ?>">
                    <button type="submit" class="btn btn-primary">Buscar</button>
                    <?php if (!empty($busqueda)): ?>
                        <a href="tickets.php" class="btn btn-secondary">Limpiar</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <!-- Botones de bandeja por estado -->
        <div class="mb-3">
            <a href="tickets.php" class="btn <?php echo empty($estado_filtro) ? 'btn-primary' : 'btn-outline-primary'; ?>" title="Todos los tickets abiertos">Todos</a>
            <a href="tickets.php?estado=sin%20abrir<?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>" class="btn <?php echo $estado_filtro === 'sin abrir' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">Sin abrir (<?php echo $conteos_estado['sin abrir']; ?>)</a>
            <a href="tickets.php?estado=en%20conocimiento<?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>" class="btn <?php echo $estado_filtro === 'en conocimiento' ? 'btn-info' : 'btn-outline-info'; ?>">En conocimiento (<?php echo $conteos_estado['en conocimiento']; ?>)</a>
            <a href="tickets.php?estado=en%20proceso<?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>" class="btn <?php echo $estado_filtro === 'en proceso' ? 'btn-warning' : 'btn-outline-warning'; ?>">En proceso (<?php echo $conteos_estado['en proceso']; ?>)</a>
            <a href="tickets.php?estado=pendiente%20de%20cierre<?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>" class="btn <?php echo $estado_filtro === 'pendiente de cierre' ? 'btn-danger' : 'btn-outline-danger'; ?>">Pendiente de cierre (<?php echo $conteos_estado['pendiente de cierre']; ?>)</a>
            <a href="tickets_cerrados.php" class="btn btn-outline-success">Tickets Cerrados (<?php echo $conteo_cerrados; ?>)</a>
            <a href="crear_ticket.php" class="btn btn-success">+ Crear Nuevo Ticket</a>
        </div>
        
        <!-- Listado de Tickets -->
        <?php if (empty($tickets)): ?>
            <div class="alert alert-info">
                <?php echo !empty($busqueda) ? "No se encontraron tickets con esa búsqueda" : "No hay tickets aún"; ?>
            </div>
        <?php else: ?>
            <!-- Vista Lista (tabla) -->
            <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th><a href="?orden=ticket_number&dir=<?php echo $orden === 'ticket_number' && $direccion === 'ASC' ? 'DESC' : 'ASC'; ?><?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>&pagina=1" style="text-decoration: none; color: inherit;">Ticket <?php echo $orden === 'ticket_number' ? ($direccion === 'ASC' ? '▲' : '▼') : ''; ?></a></th>
                                <th><a href="?orden=titulo&dir=<?php echo $orden === 'titulo' && $direccion === 'ASC' ? 'DESC' : 'ASC'; ?><?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>&pagina=1" style="text-decoration: none; color: inherit;">Título <?php echo $orden === 'titulo' ? ($direccion === 'ASC' ? '▲' : '▼') : ''; ?></a></th>
                                <th><a href="?orden=estado&dir=<?php echo $orden === 'estado' && $direccion === 'ASC' ? 'DESC' : 'ASC'; ?><?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>&pagina=1" style="text-decoration: none; color: inherit;">Estado <?php echo $orden === 'estado' ? ($direccion === 'ASC' ? '▲' : '▼') : ''; ?></a></th>
                                <th><a href="?orden=solicitante&dir=<?php echo $orden === 'solicitante' && $direccion === 'ASC' ? 'DESC' : 'ASC'; ?><?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>&pagina=1" style="text-decoration: none; color: inherit;">Solicitante <?php echo $orden === 'solicitante' ? ($direccion === 'ASC' ? '▲' : '▼') : ''; ?></a></th>
                                <th><a href="?orden=responsable&dir=<?php echo $orden === 'responsable' && $direccion === 'ASC' ? 'DESC' : 'ASC'; ?><?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>&pagina=1" style="text-decoration: none; color: inherit;">Responsable <?php echo $orden === 'responsable' ? ($direccion === 'ASC' ? '▲' : '▼') : ''; ?></a></th>
                                <th><a href="?orden=fecha&dir=<?php echo $orden === 'fecha' && $direccion === 'ASC' ? 'DESC' : 'ASC'; ?><?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>&pagina=1" style="text-decoration: none; color: inherit;">Fecha <?php echo $orden === 'fecha' ? ($direccion === 'ASC' ? '▲' : '▼') : ''; ?></a></th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $ticket): ?>
                            <tr class="ticket-list-row">
                                <td><strong><?php echo htmlspecialchars($ticket["ticket_number"]); ?></strong></td>
                                <td><?php echo htmlspecialchars($ticket["titulo"]); ?></td>
                                <td><span class="badge bg-<?php echo getEstadoColor($ticket["estado"]); ?>"><?php echo ucfirst(htmlspecialchars($ticket["estado"])); ?></span></td>
                                <td><?php echo htmlspecialchars($ticket["nombre_solicitante"] ?? "N/A"); ?></td>
                                <td>
                                    <?php 
                                    if ($ticket["responsable"]) {
                                        $stmt = $conexion->prepare("SELECT username FROM users WHERE id = ?");
                                        $stmt->execute([$ticket["responsable"]]);
                                        $resp = $stmt->fetch(PDO::FETCH_ASSOC);
                                        echo '<span class="badge bg-success">' . htmlspecialchars($resp["username"]) . '</span>';
                                    } else {
                                        echo '<span class="badge bg-secondary">Sin asignar</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo formatearFecha($ticket["fecha_creacion"]); ?></td>
                                <td>
                                    <a href="ver_ticket.php?id=<?php echo htmlspecialchars($ticket["id"]); ?>" class="btn btn-sm btn-primary">Ver</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        
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
                
                <li class="page-item active">
                    <span class="page-link">Página <?php echo $pagina; ?> de <?php echo $total_paginas; ?></span>
                </li>
                
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
