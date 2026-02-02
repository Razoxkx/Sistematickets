<?php
session_start();
require_once 'includes/config.php';

// Prevenir cualquier tipo de caché - MUY IMPORTANTE
header("Cache-Control: no-store, no-cache, must-revalidate, proxy-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
header("Etag: " . uniqid());  // ETag único para cada solicitud
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");

// Verificar si el usuario está logueado
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    exit();
}

// Si es una solicitud AJAX, solo devolver los resultados
$is_ajax = isset($_GET["ajax"]) && $_GET["ajax"] == "1";
$busqueda = $_GET["q"] ?? "";
$resultados = [
    "tickets" => [],
    "activos" => [],
    "usuarios" => []
];

if (!empty($busqueda)) {
    try {
        // Buscar tickets
        $stmt = $conexion->prepare("
            SELECT id, ticket_number, titulo, es_cerrado
            FROM tickets
            WHERE es_cerrado = 0 AND (ticket_number LIKE ? OR titulo LIKE ?)
            LIMIT 20
        ");
        $stmt->execute(['%' . $busqueda . '%', '%' . $busqueda . '%']);
        $resultados["tickets"] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Buscar activos (si el usuario tiene permisos)
        $permisos = ['tisupport', 'admin'];
        if (in_array($_SESSION["role"] ?? "viewer", $permisos)) {
            $es_numero_activo = preg_match('/^AK\d{5,7}$/i', $busqueda);
            
            if ($es_numero_activo) {
                $stmt = $conexion->prepare("
                    SELECT id, rfk, titulo, ubicacion
                    FROM activos
                    WHERE rfk LIKE ?
                    LIMIT 20
                ");
                $busqueda_param = '%' . strtoupper($busqueda) . '%';
                $stmt->execute([$busqueda_param]);
            } else {
                $stmt = $conexion->prepare("
                    SELECT id, rfk, titulo, ubicacion
                    FROM activos
                    WHERE titulo LIKE ? OR rfk LIKE ? OR descripcion LIKE ? OR tipo LIKE ? 
                       OR fabricante LIKE ? OR modelo LIKE ? OR serie LIKE ? 
                       OR ubicacion LIKE ? OR propietario LIKE ? OR fallas_activas LIKE ?
                    LIMIT 20
                ");
                $busqueda_param = '%' . $busqueda . '%';
                $stmt->execute([
                    $busqueda_param, $busqueda_param, $busqueda_param, $busqueda_param,
                    $busqueda_param, $busqueda_param, $busqueda_param,
                    $busqueda_param, $busqueda_param, $busqueda_param
                ]);
            }
            $resultados["activos"] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Buscar usuarios por username (si comienza con #)
        if (strpos($busqueda, '#') === 0) {
            $usuario_busca = substr($busqueda, 1);
            $stmt = $conexion->prepare("
                SELECT id, username, role, email
                FROM users
                WHERE username LIKE ?
                LIMIT 20
            ");
            $stmt->execute(['%' . $usuario_busca . '%']);
            $resultados["usuarios"] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
    } catch (PDOException $e) {
        // Error
    }
}

// Si es AJAX, devolver solo los resultados HTML
if ($is_ajax) {
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <?php if (empty($resultados["tickets"]) && empty($resultados["activos"]) && empty($resultados["usuarios"])): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> No se encontraron resultados para "<strong><?php echo htmlspecialchars($busqueda); ?></strong>"
        </div>
    <?php else: ?>
        
        <!-- Resultados de Tickets -->
        <?php if (!empty($resultados["tickets"])): ?>
            <div class="mb-4">
                <h5><i class="bi bi-ticket-detailed"></i> Tickets (<?php echo count($resultados["tickets"]); ?>)</h5>
                <div class="row g-3">
                    <?php foreach ($resultados["tickets"] as $ticket): ?>
                        <div class="col-md-6">
                            <a href="ver_ticket.php?id=<?php echo $ticket['id']; ?>" class="text-decoration-none">
                                <div class="card resultado-card h-100">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($ticket["ticket_number"]); ?></span>
                                        </h6>
                                        <p class="card-text"><?php echo htmlspecialchars($ticket["titulo"]); ?></p>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Resultados de Activos -->
        <?php if (!empty($resultados["activos"])): ?>
            <div class="mb-4">
                <h5><i class="bi bi-box"></i> Activos (<?php echo count($resultados["activos"]); ?>)</h5>
                <div class="row g-3">
                    <?php foreach ($resultados["activos"] as $activo): ?>
                        <div class="col-md-6">
                            <a href="ver_activo.php?id=<?php echo $activo['id']; ?>" class="text-decoration-none">
                                <div class="card resultado-card h-100">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <span class="badge bg-success"><?php echo htmlspecialchars($activo["rfk"]); ?></span>
                                        </h6>
                                        <p class="card-text"><?php echo htmlspecialchars($activo["titulo"]); ?></p>
                                        <small class="text-muted">Ubicación: <?php echo htmlspecialchars($activo["ubicacion"] ?? "No especificada"); ?></small>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Resultados de Usuarios -->
        <?php if (!empty($resultados["usuarios"])): ?>
            <div class="mb-4">
                <h5><i class="bi bi-people"></i> Usuarios (<?php echo count($resultados["usuarios"]); ?>)</h5>
                <div class="row g-3">
                    <?php foreach ($resultados["usuarios"] as $usuario): ?>
                        <div class="col-md-6">
                            <a href="perfil_usuario.php?username=<?php echo urlencode($usuario['username']); ?>" class="text-decoration-none">
                                <div class="card resultado-card h-100">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <span class="badge bg-info"><?php echo htmlspecialchars($usuario["username"]); ?></span>
                                        </h6>
                                        <p class="card-text">
                                            <span class="badge bg-info"><?php echo traducirRol($usuario["role"]); ?></span>
                                        </p>
                                        <small class="text-muted"><?php echo htmlspecialchars($usuario["email"] ?? ""); ?></small>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
    <?php endif; ?>
    <?php
    exit();
}

// Si no es AJAX, devolver la página completa
?>

<!DOCTYPE html>
<html lang="es" id="htmlRoot">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="css/dark-mode.css" rel="stylesheet">
    <title>Búsqueda Global</title>
    <style>
        .resultado-card {  }
        .resultado-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    </style>
    <script>
        // Detectar cuando se regresa del historial y recargar la página
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                // La página se restauró desde caché, recargar
                window.location.reload();
            }
        });
        
        // Script de dark mode
        (function() {
            const darkMode = localStorage.getItem('darkMode');
            if (darkMode === 'enabled') {
                document.documentElement.setAttribute('data-bs-theme', 'dark');
            }
        })();
    </script>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <h1><i class="bi bi-search"></i> Búsqueda Global</h1>
                <p class="mb-0">Busca tickets, activos o usuarios en todo el sistema</p>
            </div>
        </div>
        
        <!-- Formulario de Búsqueda -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex gap-2">
                    <input 
                        type="text" 
                        id="searchInput" 
                        class="form-control" 
                        placeholder="Busca por: #DCD000001, AK79XXXX, #usuario, etc." 
                        autofocus
                    >
                </div>
                <small class="text-muted d-block mt-2">
                    💡 Tip: Busca por usuario con <strong>#usuario</strong>, por ticket con <strong>#DCD000001</strong>, o por activo con <strong>AK79XXXX</strong>
                </small>
            </div>
        </div>
        
        <!-- Contenedor de Resultados -->
        <div id="resultadosContainer" class="mb-4"></div>
        
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let searchTimeout;
        
        function performSearch(query) {
            const container = document.getElementById('resultadosContainer');
            if (!container) return;
            
            // Limpiar si está vacío
            if (!query) {
                container.innerHTML = '<div class="alert alert-info"><i class="bi bi-info-circle"></i> Ingresa un término de búsqueda para comenzar</div>';
                return;
            }
            
            // Mostrar loading
            container.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Buscando...</span></div></div>';
            
            // Esperar 100ms antes de hacer la búsqueda
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                fetch('busqueda_global_v2.php?q=' + encodeURIComponent(query) + '&ajax=1')
                    .then(r => r.text())
                    .then(html => {
                        container.innerHTML = html;
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        container.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> Error en la búsqueda</div>';
                    });
            }, 100);
        }
        
        function initializeSearch() {
            const searchInput = document.getElementById('searchInput');
            const container = document.getElementById('resultadosContainer');
            
            if (!searchInput || !container) return;
            
            // Si el input tiene contenido (del caché o persistido), ejecutar búsqueda inmediatamente
            const query = searchInput.value.trim();
            if (query) {
                performSearch(query);
            } else {
                container.innerHTML = '<div class="alert alert-info"><i class="bi bi-info-circle"></i> Ingresa un término de búsqueda para comenzar</div>';
            }
            
            // Listener para cambios en el input
            searchInput.addEventListener('input', function(e) {
                const inputQuery = e.target.value.trim();
                performSearch(inputQuery);
            });
        }
        
        // Inicializar cuando el DOM esté listo
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeSearch);
        } else {
            // Si el DOM ya está listo (caché), inicializar inmediatamente
            initializeSearch();
        }
        
        // También ejecutar en load para estar seguro
        window.addEventListener('load', function() {
            const searchInput = document.getElementById('searchInput');
            if (searchInput && searchInput.value.trim()) {
                performSearch(searchInput.value.trim());
            }
        });
        
        // Re-ejecutar búsqueda cuando se regresa del historial del navegador
        window.addEventListener('pageshow', function(event) {
            const searchInput = document.getElementById('searchInput');
            const container = document.getElementById('resultadosContainer');
            
            if (!searchInput || !container) return;
            
            // Siempre limpiar el contenedor primero
            container.innerHTML = '<div class="alert alert-info"><i class="bi bi-info-circle"></i> Ingresa un término de búsqueda para comenzar</div>';
            
            // Luego ejecutar búsqueda si hay texto
            const query = searchInput.value.trim();
            if (query) {
                // Pequeño delay para asegurar que el DOM esté listo
                setTimeout(() => {
                    performSearch(query);
                }, 50);
            }
        });
    </script>
</body>
</html>
