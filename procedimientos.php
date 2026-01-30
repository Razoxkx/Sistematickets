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

$procedimientos = [];
$busqueda = $_GET["buscar"] ?? "";
$tipo_filtro = $_GET["tipo"] ?? "";

try {
    $where = "es_activo = 1";
    $params = [];
    
    if (!empty($busqueda)) {
        $where .= " AND titulo LIKE ?";
        $params[] = '%' . $busqueda . '%';
    }
    
    if (!empty($tipo_filtro) && in_array($tipo_filtro, ['técnico', 'administrativo'])) {
        $where .= " AND tipo_procedimiento = ?";
        $params[] = $tipo_filtro;
    }
    
    $stmt = $conexion->prepare("
        SELECT p.*, u.username as autor_nombre,
               (SELECT COUNT(*) FROM menciones_procedimientos WHERE procedimiento_id = p.id) as total_menciones
        FROM procedimientos p
        JOIN users u ON p.usuario_creador = u.id
        WHERE $where
        ORDER BY p.fecha_creacion DESC
    ");
    $stmt->execute($params);
    $procedimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al obtener procedimientos: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="css/dark-mode.css" rel="stylesheet">
    <title>Procedimientos</title>
    <style>
        body {
            background: linear-gradient(to bottom, #f8f9fa, #ffffff);
        }
        
        [data-bs-theme="dark"] body {
            background: linear-gradient(to bottom, #1a1a1a, #0d0d0d);
        }
        
        .procedimiento-card {
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }
        
        .procedimiento-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        [data-bs-theme="dark"] .procedimiento-card {
            background: #1e1e1e;
            color: #e0e0e0;
        }
        
        [data-bs-theme="dark"] .procedimiento-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.5);
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
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-10">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="bi bi-file-earmark-text"></i> Procedimientos</h1>
                    <a href="crear_procedimiento.php" class="btn btn-success">
                        <i class="bi bi-plus-circle"></i> Nuevo Procedimiento
                    </a>
                </div>
                
                <!-- Búsqueda y filtros -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-8">
                                <input type="text" id="searchProcedimientos" class="form-control" 
                                       placeholder="Buscar por título..." 
                                       value="<?php echo htmlspecialchars($busqueda); ?>">
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" id="tipoFiltro">
                                    <option value="">Todos los tipos</option>
                                    <option value="técnico" <?php echo $tipo_filtro === "técnico" ? "selected" : ""; ?>>
                                        <i class="bi bi-wrench"></i> Técnico
                                    </option>
                                    <option value="administrativo" <?php echo $tipo_filtro === "administrativo" ? "selected" : ""; ?>>
                                        <i class="bi bi-clipboard-check"></i> Administrativo
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Listado de procedimientos -->
                <?php if (empty($procedimientos)): ?>
                    <div class="alert alert-info text-center py-5">
                        <i class="bi bi-info-circle" style="font-size: 2rem;"></i>
                        <p class="mt-3 mb-0">
                            <?php echo !empty($busqueda) ? "No se encontraron procedimientos que coincidan con tu búsqueda." : "Aún no hay procedimientos creados."; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($procedimientos as $proc): ?>
                            <div class="col-md-6">
                                <a href="ver_procedimiento.php?id=<?php echo $proc["id"]; ?>" class="text-decoration-none">
                                    <div class="card procedimiento-card h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h5 class="card-title mb-0"><?php echo htmlspecialchars($proc["titulo"]); ?></h5>
                                                <span class="badge <?php echo $proc["tipo_procedimiento"] === "técnico" ? "bg-info" : "bg-warning"; ?>">
                                                    <?php echo ucfirst(substr($proc["tipo_procedimiento"], 0, 1)); ?>
                                                </span>
                                            </div>
                                            
                                            <p class="text-muted small mb-2">
                                                <i class="bi bi-key"></i> <?php echo htmlspecialchars($proc["id_procedimiento"]); ?>
                                            </p>
                                            
                                            <p class="card-text text-muted small" style="height: 60px; overflow: hidden;">
                                                <?php echo htmlspecialchars(substr($proc["cuerpo"], 0, 150)); ?>...
                                            </p>
                                            
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <i class="bi bi-person"></i> <?php echo htmlspecialchars($proc["autor_nombre"]); ?>
                                                </small>
                                                <span class="badge bg-secondary">
                                                    <i class="bi bi-link-45deg"></i> <?php echo $proc["total_menciones"]; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="includes/dark-mode.js"></script>
    
    <script>
        let searchTimeout;
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchProcedimientos');
            const tipoSelect = document.getElementById('tipoFiltro');
            
            if (searchInput && searchInput.value.trim()) {
                // Si hay búsqueda anterior, mantenerla
            }
            
            function updateSearch() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    const query = searchInput.value.trim();
                    const tipo = tipoSelect.value;
                    const url = new URL(window.location);
                    
                    if (query) {
                        url.searchParams.set('buscar', query);
                    } else {
                        url.searchParams.delete('buscar');
                    }
                    
                    if (tipo) {
                        url.searchParams.set('tipo', tipo);
                    } else {
                        url.searchParams.delete('tipo');
                    }
                    
                    window.location.search = url.search;
                }, 1000);
            }
            
            searchInput.addEventListener('input', updateSearch);
            tipoSelect.addEventListener('change', updateSearch);
        });
    </script>
</body>
</html>
