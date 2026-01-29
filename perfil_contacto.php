<?php
session_start();
require_once 'includes/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

// Obtener el nombre_usuario del contacto desde la URL
$contacto_username = $_GET["username"] ?? "";
$seccion = $_GET["seccion"] ?? "tickets_hasheado";
$busqueda = $_GET["buscar"] ?? "";
$pagina = max(1, (int)($_GET["pagina"] ?? 1));

$error = "";
$contacto = null;
$tickets_hasheado = [];
$tickets_solicitante = [];
$activos_asociados = [];
$total_items = 0;
$items_por_pagina = 9;

try {
    // Obtener datos del contacto
    $stmt = $conexion->prepare("SELECT * FROM users WHERE username = ? AND role = 'contacto'");
    $stmt->execute([$contacto_username]);
    $contacto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$contacto) {
        $error = "Contacto no encontrado";
    } else {
        $offset = ($pagina - 1) * $items_por_pagina;
        
        // SECCIÓN: TICKETS DONDE HA SIDO HASHEADO
        if ($seccion === "tickets_hasheado") {
            // Buscar comentarios con #nombre_usuario o @nombre_usuario del contacto
            $where = "(c.comentario LIKE ? OR c.comentario LIKE ?) AND t.es_cerrado = 0";
            $params = ['%#' . $contacto_username . '%', '%@' . $contacto_username . '%'];
            
            if (!empty($busqueda)) {
                $where .= " AND (t.ticket_number LIKE ? OR t.titulo LIKE ?)";
                $busqueda_param = '%' . $busqueda . '%';
                $params[] = $busqueda_param;
                $params[] = $busqueda_param;
            }
            
            // Contar total (usando GROUP BY para evitar duplicados)
            $stmt_count = $conexion->prepare("
                SELECT COUNT(DISTINCT t.id) as total 
                FROM tickets t
                JOIN comentarios_tickets c ON t.id = c.ticket_id
                WHERE " . $where . "
            ");
            $stmt_count->execute($params);
            $total_items = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Obtener tickets
            $stmt = $conexion->prepare("
                SELECT DISTINCT t.*, u.username as responsable_nombre
                FROM tickets t
                JOIN comentarios_tickets c ON t.id = c.ticket_id
                LEFT JOIN users u ON t.responsable = u.id
                WHERE " . $where . "
                ORDER BY t.fecha_creacion DESC
                LIMIT " . intval($items_por_pagina) . " OFFSET " . intval($offset) . "
            ");
            $stmt->execute($params);
            $tickets_hasheado = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // SECCIÓN: TICKETS DONDE FUE SOLICITANTE
        elseif ($seccion === "tickets_solicitante") {
            // Buscar por nombre_usuario o nombre_completo (flexible)
            $where = "(t.nombre_solicitante = ? OR t.nombre_solicitante = ?) AND t.es_cerrado = 0";
            $params = [$contacto["username"], $contacto["nombre_completo"]];
            
            if (!empty($busqueda)) {
                $where .= " AND (t.ticket_number LIKE ? OR t.titulo LIKE ? OR t.descripcion LIKE ?)";
                $busqueda_param = '%' . $busqueda . '%';
                $params[] = $busqueda_param;
                $params[] = $busqueda_param;
                $params[] = $busqueda_param;
            }
            
            // Contar total
            $stmt_count = $conexion->prepare("SELECT COUNT(*) as total FROM tickets t WHERE " . $where);
            $stmt_count->execute($params);
            $total_items = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Obtener tickets
            $stmt = $conexion->prepare("
                SELECT t.*, u.username as responsable_nombre
                FROM tickets t
                LEFT JOIN users u ON t.responsable = u.id
                WHERE " . $where . "
                ORDER BY t.fecha_creacion DESC
                LIMIT " . intval($items_por_pagina) . " OFFSET " . intval($offset) . "
            ");
            $stmt->execute($params);
            $tickets_solicitante = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // SECCIÓN: ACTIVOS ASOCIADOS (por ubicación)
        elseif ($seccion === "activos") {
            $where = "a.ubicacion LIKE ?";
            $params = ['%' . $contacto_username . '%'];
            
            if (!empty($busqueda)) {
                $where .= " AND (a.titulo LIKE ? OR a.modelo LIKE ?)";
                $busqueda_param = '%' . $busqueda . '%';
                $params[] = $busqueda_param;
                $params[] = $busqueda_param;
            }
            
            // Contar total
            $stmt_count = $conexion->prepare("SELECT COUNT(*) as total FROM activos a WHERE " . $where);
            $stmt_count->execute($params);
            $total_items = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Obtener activos
            $stmt = $conexion->prepare("
                SELECT *
                FROM activos a
                WHERE " . $where . "
                ORDER BY a.id DESC
                LIMIT " . intval($items_por_pagina) . " OFFSET " . intval($offset) . "
            ");
            $stmt->execute($params);
            $activos_asociados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        $total_paginas = ceil($total_items / $items_por_pagina);
    }
} catch (PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/dark-mode.css" rel="stylesheet">
    <title>Perfil Contacto - <?php echo htmlspecialchars($contacto_username ?? ""); ?></title>
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
    <style>
        body {
            background: linear-gradient(to bottom, #f8f9fa, #ffffff);
        }
        
        [data-bs-theme="dark"] body {
            background: linear-gradient(to bottom, #1a1a1a, #0d0d0d);
        }
        
        .perfil-header-gradient {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 40px 50px;
            margin: 0;
            border-radius: 0;
        }
        
        .perfil-header-gradient h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .perfil-header-gradient .bi-person-vcard {
            font-size: 4rem;
            opacity: 0.9;
        }
        
        .perfil-info {
            padding: 30px 50px;
            background: white;
            border-bottom: 1px solid #e9ecef;
        }
        
        [data-bs-theme="dark"] .perfil-info {
            background: #1e1e1e;
            border-bottom-color: #444;
        }
        
        .perfil-content {
            padding: 40px 50px;
        }
        
        [data-bs-theme="dark"] .perfil-content {
            background: #0d0d0d;
        }
        
        .seccion-tab {
            cursor: pointer;
            border-bottom: 3px solid transparent;
            padding: 1rem 1.5rem;
            transition: all 0.3s;
            font-weight: 500;
            color: #6c757d;
        }
        
        [data-bs-theme="dark"] .seccion-tab {
            color: #999;
        }
        
        .seccion-tab.active {
            border-bottom-color: #28a745;
            color: #28a745;
            font-weight: 600;
        }
        
        [data-bs-theme="dark"] .seccion-tab.active {
            color: #5dd09f;
            border-bottom-color: #5dd09f;
        }
        
        .seccion-tab:hover {
            background-color: rgba(40, 167, 69, 0.05);
            color: #28a745;
        }
        
        [data-bs-theme="dark"] .seccion-tab:hover {
            background-color: rgba(93, 208, 159, 0.1);
            color: #5dd09f;
        }
        
        .card-item {
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid #e9ecef;
            border-radius: 8px;
        }
        
        [data-bs-theme="dark"] .card-item {
            border-color: #444;
            background: #1e1e1e;
            color: #e0e0e0;
        }
        
        .card-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }
        
        [data-bs-theme="dark"] .card-item:hover {
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5);
        }
        
        .search-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        [data-bs-theme="dark"] .search-card {
            background: #1e1e1e;
            border-color: #444;
        }
        
        [data-bs-theme="dark"] .form-control,
        [data-bs-theme="dark"] .form-select {
            background-color: #2a2a2a;
            border-color: #444;
            color: #e0e0e0;
        }
        
        [data-bs-theme="dark"] .form-control:focus,
        [data-bs-theme="dark"] .form-select:focus {
            background-color: #2a2a2a;
            border-color: #28a745;
            color: #e0e0e0;
        }
        
        [data-bs-theme="dark"] .text-muted {
            color: #999 !important;
        }
        
        [data-bs-theme="dark"] .card-title {
            color: #e0e0e0;
        }
        
        .pestanas-wrapper {
            background: linear-gradient(90deg, rgba(40, 167, 69, 0.08) 0%, transparent 100%);
            border-bottom: 1px solid #e9ecef;
        }
        
        [data-bs-theme="dark"] .pestanas-wrapper {
            background: linear-gradient(90deg, rgba(93, 208, 159, 0.1) 0%, transparent 100%);
            border-bottom-color: #444;
        }
        
        .contact-info-item {
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        [data-bs-theme="dark"] .contact-info-item {
            border-bottom-color: #444;
        }
        
        .contact-info-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="container-fluid p-0">
        
        <?php if (!empty($error)): ?>
            <div style="padding: 30px 50px;">
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        <?php elseif ($contacto): ?>
        
            <!-- Encabezado del Perfil Gradiente -->
            <div class="perfil-header-gradient">
                <div class="row align-items-center g-3">
                    <div class="col-auto">
                        <i class="bi bi-person-vcard"></i>
                    </div>
                    <div class="col">
                        <h1><?php echo htmlspecialchars($contacto["nombre_completo"]); ?></h1>
                        <p class="mb-2" style="font-size: 1.05rem; opacity: 0.95;"><strong>Usuario:</strong> #<?php echo htmlspecialchars($contacto["username"] ?? "N/A"); ?></p>
                        <p class="mb-0" style="opacity: 0.9;"><strong>División/Departamento:</strong> <?php echo htmlspecialchars($contacto["division_departamento"] ?? "N/A"); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Información de Contacto -->
            <div class="perfil-info">
                <div class="row">
                    <div class="col-md-6">
                        <div class="contact-info-item">
                            <strong>📧 Correo:</strong> <?php echo htmlspecialchars($contacto["correo"] ?? "No disponible"); ?>
                        </div>
                        <div class="contact-info-item">
                            <strong>📱 Teléfono:</strong> <?php echo htmlspecialchars($contacto["numero_telefono"] ?? "No disponible"); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Buscador de la Sección -->
            <div style="padding: 20px 50px;">
                <div class="search-card">
                    <form method="GET" class="d-flex gap-2">
                        <input type="hidden" name="username" value="<?php echo urlencode($contacto_username); ?>">
                        <input type="hidden" name="seccion" value="<?php echo urlencode($seccion); ?>">
                        <input type="text" class="form-control" name="buscar" placeholder="🔍 Buscar..." value="<?php echo htmlspecialchars($busqueda); ?>">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-search"></i> Buscar
                        </button>
                        <?php if (!empty($busqueda)): ?>
                            <a href="?username=<?php echo urlencode($contacto_username); ?>&seccion=<?php echo urlencode($seccion); ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-x"></i> Limpiar
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <!-- Pestañas de Secciones -->
            <div style="background: linear-gradient(90deg, rgba(40, 167, 69, 0.08) 0%, transparent 100%); border-bottom: 1px solid #e9ecef; padding: 0 50px;" class="pestanas-wrapper">
                <div class="row g-0">
                    <div class="col-auto">
                        <a href="?username=<?php echo urlencode($contacto_username); ?>&seccion=tickets_hasheado" class="seccion-tab <?php echo $seccion === 'tickets_hasheado' ? 'active' : ''; ?>">
                            <i class="bi bi-hash"></i> Hasheado En
                        </a>
                    </div>
                    <div class="col-auto">
                        <a href="?username=<?php echo urlencode($contacto_username); ?>&seccion=tickets_solicitante" class="seccion-tab <?php echo $seccion === 'tickets_solicitante' ? 'active' : ''; ?>">
                            <i class="bi bi-file-earmark"></i> Como Solicitante
                        </a>
                    </div>
                    <div class="col-auto">
                        <a href="?username=<?php echo urlencode($contacto_username); ?>&seccion=activos" class="seccion-tab <?php echo $seccion === 'activos' ? 'active' : ''; ?>">
                            <i class="bi bi-box"></i> Activos Asociados
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Contenido de Secciones -->
            <div class="perfil-content">
            
            <!-- SECCIÓN: TICKETS HASHEADO -->
            <?php if ($seccion === 'tickets_hasheado'): ?>
                <div class="section-content">
                    <?php if (empty($tickets_hasheado)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> 
                            <?php echo !empty($busqueda) ? "No se encontraron tickets con esa búsqueda" : "No hay tickets donde haya sido hasheado"; ?>
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($tickets_hasheado as $ticket): ?>
                                <div class="col-md-4">
                                    <a href="ver_ticket.php?id=<?php echo htmlspecialchars($ticket['id']); ?>" class="text-decoration-none">
                                        <div class="card card-item h-100">
                                            <div class="card-body">
                                                <div class="mb-2">
                                                    <span class="badge bg-info"><?php echo htmlspecialchars($ticket["ticket_number"]); ?></span>
                                                </div>
                                                <h5 class="card-title text-truncate"><?php echo htmlspecialchars($ticket["titulo"]); ?></h5>
                                                <p class="card-text text-muted small text-truncate"><?php echo htmlspecialchars(substr($ticket["descripcion"], 0, 80)); ?></p>
                                                <div class="mt-3">
                                                    <small><strong>Estado:</strong> <?php echo htmlspecialchars($ticket["estado"]); ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- SECCIÓN: TICKETS SOLICITANTE -->
            <?php if ($seccion === 'tickets_solicitante'): ?>
                <div class="section-content">
                    <?php if (empty($tickets_solicitante)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> 
                            <?php echo !empty($busqueda) ? "No se encontraron tickets con esa búsqueda" : "No hay tickets donde sea solicitante"; ?>
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($tickets_solicitante as $ticket): ?>
                                <div class="col-md-4">
                                    <a href="ver_ticket.php?id=<?php echo htmlspecialchars($ticket['id']); ?>" class="text-decoration-none">
                                        <div class="card card-item h-100">
                                            <div class="card-body">
                                                <div class="mb-2">
                                                    <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($ticket["ticket_number"]); ?></span>
                                                </div>
                                                <h5 class="card-title text-truncate"><?php echo htmlspecialchars($ticket["titulo"]); ?></h5>
                                                <p class="card-text text-muted small text-truncate"><?php echo htmlspecialchars(substr($ticket["descripcion"], 0, 80)); ?></p>
                                                <div class="mt-3">
                                                    <small><strong>Estado:</strong> <?php echo htmlspecialchars($ticket["estado"]); ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- SECCIÓN: ACTIVOS ASOCIADOS -->
            <?php if ($seccion === 'activos'): ?>
                <div class="section-content">
                    <?php if (empty($activos_asociados)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> 
                            <?php echo !empty($busqueda) ? "No se encontraron activos con esa búsqueda" : "No hay activos asociados"; ?>
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($activos_asociados as $activo): ?>
                                <div class="col-md-4">
                                    <a href="ver_activo.php?id=<?php echo htmlspecialchars($activo['id']); ?>" class="text-decoration-none">
                                        <div class="card card-item h-100">
                                            <div class="card-body">
                                                <h5 class="card-title text-truncate"><?php echo htmlspecialchars($activo["titulo"]); ?></h5>
                                                <p class="card-text text-muted small">
                                                    <strong>Modelo:</strong> <?php echo htmlspecialchars(substr($activo["modelo"], 0, 50)); ?>
                                                </p>
                                                <p class="card-text text-muted small text-truncate">
                                                    <strong>Serie:</strong> <?php echo htmlspecialchars($activo["serie"] ?? "N/A"); ?>
                                                </p>
                                                <div class="mt-3">
                                                    <small class="d-block">
                                                        <strong>Ubicación:</strong> <?php echo htmlspecialchars($activo["ubicacion"] ?? "N/A"); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Paginación -->
            <?php if ($total_paginas > 1): ?>
                <nav aria-label="Paginación" class="mt-5">
                    <ul class="pagination justify-content-center">
                        <?php if ($pagina > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?username=<?php echo urlencode($contacto_username); ?>&seccion=<?php echo urlencode($seccion); ?>&pagina=1<?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>">
                                    Primera
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?username=<?php echo urlencode($contacto_username); ?>&seccion=<?php echo urlencode($seccion); ?>&pagina=<?php echo $pagina - 1; ?><?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>">
                                    ← Anterior
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $pagina - 1); $i <= min($total_paginas, $pagina + 1); $i++): ?>
                            <li class="page-item <?php echo $i === $pagina ? 'active' : ''; ?>">
                                <a class="page-link" href="?username=<?php echo urlencode($contacto_username); ?>&seccion=<?php echo urlencode($seccion); ?>&pagina=<?php echo $i; ?><?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($pagina < $total_paginas): ?>
                            <li class="page-item">
                                <a class="page-link" href="?username=<?php echo urlencode($contacto_username); ?>&seccion=<?php echo urlencode($seccion); ?>&pagina=<?php echo $pagina + 1; ?><?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>">
                                    Siguiente →
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?username=<?php echo urlencode($contacto_username); ?>&seccion=<?php echo urlencode($seccion); ?>&pagina=<?php echo $total_paginas; ?><?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>">
                                    Última
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
            
        <?php endif; ?>
        
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
