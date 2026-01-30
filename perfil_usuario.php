<?php
session_start();
require_once 'includes/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

// Obtener el username a buscar desde la URL o usar el del usuario actual
$usuario_busca = $_GET["username"] ?? $_SESSION["username"];
$seccion = $_GET["seccion"] ?? "tickets_creados"; // tickets_creados, tickets_mencionados, activos
$busqueda = $_GET["buscar"] ?? "";
$pagina = max(1, (int)($_GET["pagina"] ?? 1));

$error = "";
$usuario_perfil = null;
$tickets_creados = [];
$tickets_mencionados = [];
$activos_usuario = [];
$total_items = 0;
$items_por_pagina = 9;

try {
    // Obtener datos del usuario
    $stmt = $conexion->prepare("SELECT id, username, email, role, foto_perfil FROM users WHERE username = ?");
    $stmt->execute([$usuario_busca]);
    $usuario_perfil = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario_perfil) {
        $error = "Usuario no encontrado";
    } else {
        $offset = ($pagina - 1) * $items_por_pagina;
        
        // SECCIÓN: TICKETS CREADOS
        if ($seccion === "tickets_creados") {
            $where = "t.usuario_creador = ? AND t.es_cerrado = 0";
            $params = [$usuario_perfil["id"]];
            
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
            $tickets_creados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // SECCIÓN: TICKETS MENCIONADOS
        elseif ($seccion === "tickets_mencionados") {
            $username_search = $usuario_busca;
            
            // Buscar por @usuario O #usuario (ambos formatos)
            $where = "(c.comentario LIKE ? OR c.comentario LIKE ?) AND t.es_cerrado = 0";
            $params = ['%@' . $username_search . '%', '%#' . $username_search . '%'];
            
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
            $tickets_mencionados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // SECCIÓN: ACTIVOS
        elseif ($seccion === "activos") {
            $where = "a.propietario = ?";
            $params = [$usuario_busca];
            
            if (!empty($busqueda)) {
                $where .= " AND (a.rfk LIKE ? OR a.titulo LIKE ? OR a.descripcion LIKE ?)";
                $busqueda_param = '%' . $busqueda . '%';
                $params[] = $busqueda_param;
                $params[] = $busqueda_param;
                $params[] = $busqueda_param;
            }
            
            // Contar total
            $stmt_count = $conexion->prepare("SELECT COUNT(*) as total FROM activos a WHERE " . $where);
            $stmt_count->execute($params);
            $total_items = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Obtener activos
            $stmt = $conexion->prepare("
                SELECT a.*
                FROM activos a
                WHERE " . $where . "
                ORDER BY a.rfk ASC
                LIMIT " . intval($items_por_pagina) . " OFFSET " . intval($offset) . "
            ");
            $stmt->execute($params);
            $activos_usuario = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    $error = "Error al obtener datos: " . $e->getMessage();
}

$total_paginas = ceil($total_items / $items_por_pagina);
?>

<!DOCTYPE html>
<html lang="es" id="htmlRoot">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="css/dark-mode.css" rel="stylesheet">
    <title>Perfil - <?php echo htmlspecialchars($usuario_busca); ?></title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        
        .perfil-header-gradient .bi-person-circle {
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
            border-bottom-color: #667eea;
            color: #667eea;
            font-weight: 600;
        }
        
        [data-bs-theme="dark"] .seccion-tab.active {
            color: #8b9dff;
            border-bottom-color: #8b9dff;
        }
        
        .seccion-tab:hover {
            background-color: rgba(102, 126, 234, 0.05);
            color: #667eea;
        }
        
        [data-bs-theme="dark"] .seccion-tab:hover {
            background-color: rgba(139, 157, 255, 0.1);
            color: #8b9dff;
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
            border-color: #667eea;
            color: #e0e0e0;
        }
        
        [data-bs-theme="dark"] .text-muted {
            color: #999 !important;
        }
        
        [data-bs-theme="dark"] .card-title {
            color: #e0e0e0;
        }
        
        .pestanas-wrapper {
            background: linear-gradient(90deg, rgba(102, 126, 234, 0.08) 0%, transparent 100%);
            border-bottom: 1px solid #e9ecef;
        }
        
        [data-bs-theme="dark"] .pestanas-wrapper {
            background: linear-gradient(90deg, rgba(139, 157, 255, 0.1) 0%, transparent 100%);
            border-bottom-color: #444;
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
        <?php elseif ($usuario_perfil): ?>
        
            <!-- Encabezado del Perfil Gradiente -->
            <div class="perfil-header-gradient">
                <div class="row align-items-center g-3">
                    <div class="col-auto">
                        <?php if (!empty($usuario_perfil['foto_perfil']) && file_exists($usuario_perfil['foto_perfil'])): ?>
                            <img src="<?php echo htmlspecialchars($usuario_perfil['foto_perfil']); ?>" 
                                 alt="<?php echo htmlspecialchars($usuario_perfil['username']); ?>" 
                                 style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid white;">
                        <?php else: ?>
                            <i class="bi bi-person-circle" style="font-size: 4rem;"></i>
                        <?php endif; ?>
                    </div>
                    <div class="col">
                        <h1><?php echo htmlspecialchars($usuario_perfil["username"]); ?></h1>
                        <p class="mb-2" style="font-size: 1.05rem; opacity: 0.95;"><strong>Rol:</strong> <?php echo traducirRol($usuario_perfil["role"]); ?></p>
                        <p class="mb-0" style="opacity: 0.9;"><strong>Email:</strong> <?php echo htmlspecialchars($usuario_perfil["email"] ?? "N/A"); ?></p>
                        <?php if ($_SESSION["user_id"] === $usuario_perfil["id"]): ?>
                            <button class="btn btn-sm btn-light mt-2" data-bs-toggle="modal" data-bs-target="#modalFotoPerfil">
                                <i class="bi bi-camera"></i> Cambiar Foto
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Buscador de la Sección -->
            <div style="padding: 20px 50px;">
                <div class="search-card">
                    <form method="GET" class="d-flex gap-2">
                        <input type="hidden" name="username" value="<?php echo urlencode($usuario_busca); ?>">
                        <input type="hidden" name="seccion" value="<?php echo urlencode($seccion); ?>">
                        <input type="text" class="form-control" name="buscar" placeholder="🔍 Buscar..." value="<?php echo htmlspecialchars($busqueda); ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Buscar
                        </button>
                        <?php if (!empty($busqueda)): ?>
                            <a href="?username=<?php echo urlencode($usuario_busca); ?>&seccion=<?php echo urlencode($seccion); ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-x"></i> Limpiar
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <!-- Pestañas de Secciones -->
            <div style="background: linear-gradient(90deg, rgba(102, 126, 234, 0.08) 0%, transparent 100%); border-bottom: 1px solid #e9ecef; padding: 0 50px;" class="pestanas-wrapper">
                <div class="row g-0">
                    <div class="col-auto">
                        <a href="?username=<?php echo urlencode($usuario_busca); ?>&seccion=tickets_creados" class="seccion-tab <?php echo $seccion === 'tickets_creados' ? 'active' : ''; ?>">
                            <i class="bi bi-ticket-detailed"></i> Tickets Creados
                        </a>
                    </div>
                    <div class="col-auto">
                        <a href="?username=<?php echo urlencode($usuario_busca); ?>&seccion=tickets_mencionados" class="seccion-tab <?php echo $seccion === 'tickets_mencionados' ? 'active' : ''; ?>">
                            <i class="bi bi-chat-dots"></i> Mencionado En
                        </a>
                    </div>
                    <div class="col-auto">
                        <a href="?username=<?php echo urlencode($usuario_busca); ?>&seccion=activos" class="seccion-tab <?php echo $seccion === 'activos' ? 'active' : ''; ?>">
                            <i class="bi bi-box"></i> Activos
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Contenido de Secciones -->
            <div class="perfil-content">
            
            <!-- SECCIÓN: TICKETS CREADOS -->
            <?php if ($seccion === 'tickets_creados'): ?>
                <div class="section-content">
                    <?php if (empty($tickets_creados)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> 
                            <?php echo !empty($busqueda) ? "No se encontraron tickets con esa búsqueda" : "No hay tickets creados aún"; ?>
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($tickets_creados as $ticket): ?>
                                <div class="col-md-4">
                                    <a href="ver_ticket.php?id=<?php echo htmlspecialchars($ticket['id']); ?>" class="text-decoration-none">
                                        <div class="card card-item h-100">
                                            <div class="card-body">
                                                <div class="mb-2">
                                                    <span class="badge bg-primary"><?php echo htmlspecialchars($ticket["ticket_number"]); ?></span>
                                                </div>
                                                <h5 class="card-title text-truncate"><?php echo htmlspecialchars($ticket["titulo"]); ?></h5>
                                                <p class="card-text text-muted small text-truncate"><?php echo htmlspecialchars(substr($ticket["descripcion"], 0, 80)); ?></p>
                                                <div class="mt-3">
                                                    <small class="d-block mb-1">
                                                        <strong>Estado:</strong> 
                                                        <span class="badge bg-secondary"><?php echo ucfirst(htmlspecialchars($ticket["estado"])); ?></span>
                                                    </small>
                                                    <small class="d-block">
                                                        <strong>Responsable:</strong> 
                                                        <?php echo htmlspecialchars($ticket["responsable_nombre"] ?? "Sin asignar"); ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <div class="card-footer bg-transparent small text-muted">
                                                <?php echo formatearFecha($ticket["fecha_creacion"]); ?>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- SECCIÓN: TICKETS MENCIONADOS -->
            <?php if ($seccion === 'tickets_mencionados'): ?>
                <div class="section-content">
                    <?php if (empty($tickets_mencionados)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> 
                            <?php echo !empty($busqueda) ? "No se encontraron tickets con esa búsqueda" : "No hay tickets donde fue mencionado"; ?>
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($tickets_mencionados as $ticket): ?>
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
                                                    <small class="d-block mb-1">
                                                        <strong>Estado:</strong> 
                                                        <span class="badge bg-secondary"><?php echo ucfirst(htmlspecialchars($ticket["estado"])); ?></span>
                                                    </small>
                                                    <small class="d-block">
                                                        <strong>Reportado por:</strong> 
                                                        <?php echo htmlspecialchars($ticket["nombre_solicitante"] ?? "N/A"); ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <div class="card-footer bg-transparent small text-muted">
                                                <?php echo formatearFecha($ticket["fecha_creacion"]); ?>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- SECCIÓN: ACTIVOS -->
            <?php if ($seccion === 'activos'): ?>
                <div class="section-content">
                    <?php if (empty($activos_usuario)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> 
                            <?php echo !empty($busqueda) ? "No se encontraron activos con esa búsqueda" : "No hay activos asignados"; ?>
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($activos_usuario as $activo): ?>
                                <div class="col-md-4">
                                    <a href="ver_activo.php?id=<?php echo htmlspecialchars($activo['id']); ?>" class="text-decoration-none">
                                        <div class="card card-item h-100">
                                            <div class="card-body">
                                                <div class="mb-2">
                                                    <span class="badge bg-success"><?php echo htmlspecialchars($activo["rfk"]); ?></span>
                                                </div>
                                                <h5 class="card-title text-truncate"><?php echo htmlspecialchars($activo["titulo"]); ?></h5>
                                                <p class="card-text text-muted small">
                                                    <strong>Tipo:</strong> <?php echo htmlspecialchars($activo["tipo"] ?? "N/A"); ?><br>
                                                    <strong>Fabricante:</strong> <?php echo htmlspecialchars($activo["fabricante"] ?? "N/A"); ?>
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
                                <a class="page-link" href="?username=<?php echo urlencode($usuario_busca); ?>&seccion=<?php echo urlencode($seccion); ?>&pagina=1<?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>">
                                    Primera
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?username=<?php echo urlencode($usuario_busca); ?>&seccion=<?php echo urlencode($seccion); ?>&pagina=<?php echo $pagina - 1; ?><?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>">
                                    ← Anterior
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $pagina - 1); $i <= min($total_paginas, $pagina + 1); $i++): ?>
                            <li class="page-item <?php echo $i === $pagina ? 'active' : ''; ?>">
                                <a class="page-link" href="?username=<?php echo urlencode($usuario_busca); ?>&seccion=<?php echo urlencode($seccion); ?>&pagina=<?php echo $i; ?><?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($pagina < $total_paginas): ?>
                            <li class="page-item">
                                <a class="page-link" href="?username=<?php echo urlencode($usuario_busca); ?>&seccion=<?php echo urlencode($seccion); ?>&pagina=<?php echo $pagina + 1; ?><?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>">
                                    Siguiente →
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?username=<?php echo urlencode($usuario_busca); ?>&seccion=<?php echo urlencode($seccion); ?>&pagina=<?php echo $total_paginas; ?><?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>">
                                    Última
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
            
        <?php endif; ?>
        
    </div>
    
    <!-- Modal para cambiar foto de perfil -->
    <div class="modal fade" id="modalFotoPerfil" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cambiar Foto de Perfil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formSubirFoto">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="fotoPerfil" class="form-label">Seleccionar Foto (JPG, PNG, GIF)</label>
                            <input type="file" class="form-control" id="fotoPerfil" name="foto" accept=".jpg,.jpeg,.png,.gif" required>
                            <small class="form-text text-muted">
                                Máximo 5MB. Formatos: JPG, PNG, GIF (incluyendo animados)
                            </small>
                        </div>
                        <div id="previewFoto" class="text-center mb-3" style="display: none;">
                            <img id="previewImg" src="" style="max-width: 200px; max-height: 200px; border-radius: 8px;">
                        </div>
                        <div id="estadoSubida" style="display: none;"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btnSubirFoto">
                            <i class="bi bi-upload"></i> Subir Foto
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Preview de imagen antes de subir
        document.getElementById('fotoPerfil').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    document.getElementById('previewImg').src = event.target.result;
                    document.getElementById('previewFoto').style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });

        // Manejo del formulario de subida de foto
        document.getElementById('formSubirFoto').addEventListener('submit', async function(e) {
            e.preventDefault();
            const file = document.getElementById('fotoPerfil').files[0];
            
            if (!file) {
                alert('Por favor selecciona una foto');
                return;
            }

            // Validar tamaño (5MB)
            if (file.size > 5 * 1024 * 1024) {
                document.getElementById('estadoSubida').innerHTML = '<div class="alert alert-danger">El archivo es demasiado grande (máximo 5MB)</div>';
                document.getElementById('estadoSubida').style.display = 'block';
                return;
            }

            const formData = new FormData();
            formData.append('foto', file);

            const btnSubir = document.getElementById('btnSubirFoto');
            const btnOriginalHTML = btnSubir.innerHTML;
            btnSubir.disabled = true;
            btnSubir.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Subiendo...';

            try {
                const response = await fetch('api_subir_foto.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    document.getElementById('estadoSubida').innerHTML = '<div class="alert alert-success"><i class="bi bi-check-circle"></i> Foto subida exitosamente. Recargando página...</div>';
                    document.getElementById('estadoSubida').style.display = 'block';
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    document.getElementById('estadoSubida').innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> Error: ' + (data.error || 'No se pudo subir la foto') + '</div>';
                    document.getElementById('estadoSubida').style.display = 'block';
                }
            } catch (error) {
                document.getElementById('estadoSubida').innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> Error al subir la foto: ' + error.message + '</div>';
                document.getElementById('estadoSubida').style.display = 'block';
            } finally {
                btnSubir.disabled = false;
                btnSubir.innerHTML = btnOriginalHTML;
            }
        });
    </script>
