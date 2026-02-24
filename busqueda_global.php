<?php
session_start();
require_once 'includes/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

$busqueda = $_GET["q"] ?? "";
$resultados = [
    "tickets" => [],
    "activos" => [],
    "usuarios" => []
];
$error = "";

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
                    $busqueda_param, // titulo
                    $busqueda_param, // rfk
                    $busqueda_param, // descripcion
                    $busqueda_param, // tipo
                    $busqueda_param, // fabricante
                    $busqueda_param, // modelo
                    $busqueda_param, // serie
                    $busqueda_param, // ubicacion
                    $busqueda_param, // propietario
                    $busqueda_param  // fallas_activas
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
        $error = "Error en la búsqueda: " . $e->getMessage();
    }
}
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
    <style>
        .search-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            margin-bottom: 2rem;
            border-radius: 0.5rem;
        }
        
        .resultado-card {
            
        }
        
        .resultado-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="container mt-5 mb-5" style="margin-left: auto; margin-right: auto; max-width: 1000px;">
        
        <!-- Encabezado de Búsqueda -->
        <div class="search-header">
            <h1><i class="bi bi-search"></i> Búsqueda Global</h1>
            <p class="mb-0">Busca tickets, activos o usuarios en todo el sistema</p>
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
                        value="<?php echo htmlspecialchars($busqueda); ?>"
                        autofocus
                    >
                </div>
                <small class="text-muted d-block mt-2">
                    💡 Tip: Busca por usuario con <strong>#usuario</strong>, por ticket con <strong>#DCD000001</strong>, o por activo con <strong>AK79XXXX</strong>
                </small>
            </div>
        </div>
        
        <!-- Contenedor de Resultados -->
        <div id="resultadosContainer"></div>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif (!empty($busqueda)): ?>
            
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
                                            <span class="badge <?php echo $ticket["es_cerrado"] ? 'bg-secondary' : 'bg-success'; ?>">
                                                <?php echo $ticket["es_cerrado"] ? "Cerrado" : "Abierto"; ?>
                                            </span>
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
                                                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($usuario["username"]); ?>
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
            
            <!-- Sin resultados -->
            <?php if (empty($resultados["tickets"]) && empty($resultados["activos"]) && empty($resultados["usuarios"])): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No se encontraron resultados para "<strong><?php echo htmlspecialchars($busqueda); ?></strong>"
                </div>
            <?php endif; ?>
        
        <?php else: ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Ingresa un término de búsqueda para comenzar
            </div>
        <?php endif; ?>
        
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
