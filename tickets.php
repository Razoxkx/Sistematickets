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

// Procesar cierre masivo
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["cerrar_masivo"])) {
    // Validar token CSRF
    if (!validarTokenCSRF()) {
        $error = "Sesión expirada. Por favor intenta de nuevo.";
    } else {
    $ticket_ids_input = $_POST["ticket_ids"] ?? "";
    $motivo = $_POST["motivo_cierre"] ?? "";
    
    // Convertir string separado por comas a array
    $ticket_ids = array_filter(array_map('intval', explode(',', $ticket_ids_input)));
    
    if (empty($ticket_ids) || empty($motivo)) {
        $error = "Debe seleccionar tickets y especificar un motivo de cierre";
    } else {
        try {
            $placeholders = implode(',', array_fill(0, count($ticket_ids), '?'));
            
            // Actualizar tickets
            $stmt = $conexion->prepare(
                "UPDATE tickets SET es_cerrado = 1, estado = 'ticket cerrado', motivo_cierre = ?, fecha_ultima_modificacion = NOW() WHERE id IN (" . $placeholders . ")"
            );
            $params = array_merge([$motivo], $ticket_ids);
            $stmt->execute($params);
            
            // Agregar comentarios de cierre masivo
            foreach ($ticket_ids as $ticket_id) {
                $comentario = "Ticket cerrado masivamente. Motivo: " . htmlspecialchars($motivo);
                $stmt = $conexion->prepare("INSERT INTO comentarios_tickets (ticket_id, usuario_id, comentario) VALUES (?, ?, ?)");
                $stmt->execute([$ticket_id, $_SESSION["user_id"], $comentario]);
            }
            
            // Redirigir con parámetro de éxito
            $redirect_url = "tickets.php?success=cerrados_masivo&count=" . count($ticket_ids);
            header("Location: " . $redirect_url);
            exit();
        } catch (PDOException $e) {
            $error = "Error al cerrar tickets: " . $e->getMessage();
        }
    }
    }
}

$error = "";
$success = "";
$tickets = [];
$busqueda = $_GET["buscar"] ?? "";
$estado_filtro = $_GET["estado"] ?? "";
$mis_tickets = $_GET["mis_tickets"] ?? "";
$mostrar_cerrados = $_GET["cerrados"] ?? "0";
$pagina = max(1, (int)($_GET["pagina"] ?? 1));
$orden = $_GET["orden"] ?? "fecha";
$direccion = $_GET["dir"] ?? "DESC";

// Procesar parámetro de éxito del cierre masivo
if (isset($_GET["success"])) {
    if ($_GET["success"] === "cerrados_masivo") {
        $count = intval($_GET["count"] ?? 0);
        $success = "Se cerraron " . $count . " ticket(s) correctamente";
    }
}

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
// Si mostrar cerrados, no permitir filtro por estado abierto
if ($mostrar_cerrados === "1") {
    $estado_filtro = "";
} elseif (!empty($estado_filtro) && !in_array($estado_filtro, $estados_abiertos)) {
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

// Obtener conteos por estado (incluyendo tickets padre e hijo)
$conteos_estado = [];
foreach (['sin abrir', 'en conocimiento', 'en proceso', 'pendiente de cierre'] as $est) {
    $stmt_conteo = $conexion->prepare("SELECT COUNT(*) as total FROM tickets WHERE es_cerrado = 0 AND estado = ?");
    $stmt_conteo->execute([$est]);
    $conteos_estado[$est] = $stmt_conteo->fetch(PDO::FETCH_ASSOC)['total'];
}

// Obtener conteo de tickets cerrados (incluyendo tickets padre e hijo)
$stmt_conteo_cerrados = $conexion->prepare("SELECT COUNT(*) as total FROM tickets WHERE es_cerrado = 1");
$stmt_conteo_cerrados->execute();
$conteo_cerrados = $stmt_conteo_cerrados->fetch(PDO::FETCH_ASSOC)['total'];

try {
    // Contar total de tickets
    // Determinar si mostrar cerrados o abiertos
    $es_cerrado_condicion = ($mostrar_cerrados === "1") ? "1" : "0";
    
    // Si es "mis tickets", mostrar solo tickets padre e hijo que me pertenecen
    // Si no, mostrar todos los tickets (padre e hijo) - apartado TODOS
    if (!empty($mis_tickets) && $mis_tickets === "1") {
        // Mostrar solo tickets padre e hijo que me pertenecen
        $where = "t.es_cerrado = " . $es_cerrado_condicion . " AND t.responsable = ? AND (t.ticket_padre_id IS NULL OR t.usuario_creador = ?)";
        $params = [$_SESSION["user_id"], $_SESSION["user_id"]];
    } else {
        // Mostrar TODOS los tickets (padre e hijo) - vista general
        $where = "t.es_cerrado = " . $es_cerrado_condicion;
        $params = [];
    }
    
    if (!empty($estado_filtro)) {
        $where .= " AND t.estado = ?";
        $params[] = $estado_filtro;
    }
    
    if (!empty($busqueda)) {
        // Detectar búsqueda por usuario (#usuario)
        if (preg_match('/^#([a-z0-9._]+)$/i', $busqueda, $matches)) {
            $usuario_busca = $matches[1];
            // Buscar el ID del usuario
            $stmt_user = $conexion->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(?)");
            $stmt_user->execute([$usuario_busca]);
            $user_encontrado = $stmt_user->fetch(PDO::FETCH_ASSOC);
            
            if ($user_encontrado) {
                // Buscar tickets donde el usuario es responsable o fue creador
                $where .= " AND (t.responsable = ? OR t.usuario_creador = ?)";
                $params[] = $user_encontrado["id"];
                $params[] = $user_encontrado["id"];
            } else {
                // Usuario no encontrado, sin resultados
                $where .= " AND 1=0";
            }
        } else {
            // Búsqueda normal
            $where .= " AND (t.ticket_number LIKE ? OR t.titulo LIKE ? OR t.nombre_solicitante LIKE ?)";
            $params[] = '%' . $busqueda . '%';
            $params[] = '%' . $busqueda . '%';
            $params[] = '%' . $busqueda . '%';
        }
    }
    
    $stmt_count = $conexion->prepare("SELECT COUNT(*) as total FROM tickets t WHERE " . $where);
    $stmt_count->execute($params);
    $total_tickets = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Obtener tickets
    $stmt = $conexion->prepare("
        SELECT t.*, u.username as creador_nombre,
               tp.ticket_number as padre_numero
        FROM tickets t
        JOIN users u ON t.usuario_creador = u.id
        LEFT JOIN tickets tp ON t.ticket_padre_id = tp.id
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
<html lang="es" id="htmlRoot">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/dark-mode.css" rel="stylesheet">
    <title>Tickets</title>
    <style>
        .ticket-list-row { border-bottom: 1px solid #dee2e6; padding: 15px 0; cursor: pointer; }
        .ticket-list-row:hover { background-color: #f8f9fa; }
        [data-bs-theme="dark"] .ticket-list-row:hover { background-color: #2d3035; }
        .ticket-list-row input[type="checkbox"] { cursor: pointer; }
        .table-responsive { margin: 0 -12px; padding: 0 12px;}
        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }
        
        h1, h2, h3 {
            color: #8b9dff;
            font-weight: 700;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        h2 {
            font-size: 1.75rem;
        }
        
        /* Optimizaciones para responsividad */
        .btn-filtro-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 1rem;
        }
        
        .btn-filtro-container .btn {
            padding: 8px 12px;
            font-size: 13px;
            white-space: nowrap;
            flex: 0 1 auto;
        }
        
        /* Tablet Landscape o Normal (MD: 768px-991px) */
        @media (max-width: 991px) and (orientation: landscape) {
            .container {
                padding-left: 10px;
                padding-right: 10px;
            }
            
            h2 {
                font-size: 1.5rem;
            }
            
            .btn-filtro-container .btn {
                padding: 6px 8px;
                font-size: 11px;
                flex: 0 1 calc(50% - 4px);
            }
        }
        
        /* Tablet Portrait o muy angosto (max-width: 991px) */
        @media (max-width: 991px) {
            .container {
                padding-left: 8px;
                padding-right: 8px;
            }
            
            h2 {
                font-size: 1.5rem;
            }
            
            .btn-filtro-container .btn {
                padding: 5px 7px;
                font-size: 10.5px;
                flex: 0 1 calc(33.333% - 5px);
            }
            
            table {
                font-size: 13px;
            }
            
            table th, table td {
                padding: 10px 6px;
            }
            
            .btn-sm {
                padding: 4px 8px;
                font-size: 11px;
            }
            
            #searchTickets {
                font-size: 13px;
                padding: 8px 10px;
            }
            
            .badge {
                font-size: 11px;
                padding: 4px 6px;
            }
            
            .page-link {
                padding: 0.5rem 0.75rem;
                font-size: 12px;
            }
        }
        
        /* Tablets medianas y pequeñas (LG: 992px y más) ocultar columnas menos importantes */
        @media (max-width: 1199px) {
            .table-responsive table tr td:nth-child(5),
            .table-responsive table tr th:nth-child(5) {
                display: none;
            }
        }
        
        /* Muy pequeño - ocultar más columnas */
        @media (max-width: 768px) {
            .table-responsive table tr td:nth-child(4),
            .table-responsive table tr th:nth-child(4),
            .table-responsive table tr td:nth-child(6),
            .table-responsive table tr th:nth-child(6) {
                display: none;
            }
            
            .btn-filtro-container .btn {
                padding: 5px 6px;
                font-size: 9px;
                flex: 0 1 calc(50% - 4px);
            }
        }
        
        /* Extra pequeño */
        @media (max-width: 600px) {
            .btn-filtro-container .btn {
                flex: 0 1 calc(50% - 3px);
                padding: 4px 5px;
                font-size: 8.5px;
            }
        }
        
        /* Hacer scroll horizontal más suave en tablets */
        .table-responsive {
            -webkit-overflow-scrolling: touch;
        }
        
        /* Mejorar botón crear ticket en tablet */
        @media (max-width: 991px) {
            .btn-create-ticket {
                padding: 10px 16px !important;
                font-size: 15px !important;
                width: 100%;
            }
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
    
    <!-- Toast Notification -->
    <div class="toast-container" id="toastContainer"></div>
    
    <div class="container mt-5">
        <div class="row mb-4">
            <div class="col-md-12">
                <h2>Tickets</h2><br>
                <a href="crear_ticket.php" class="btn btn-danger btn-create-ticket">+ Crear Nuevo Ticket</a>
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
                <div class="d-flex gap-2 flex-wrap">
                    <input type="text" id="searchTickets" class="form-control" placeholder="Buscar ticket..." value="<?php echo htmlspecialchars($busqueda); ?>">
                </div>
            </div>
        </div>
        
        <!-- Botón de cierre masivo -->
        <div class="mb-3" id="btnCerrarMasivo" style="display: none;">
            <button type="button" class="btn btn-danger" onclick="abrirModalCierre()"><i class="bi bi-trash"></i> Cerrar Seleccionados</button>
            <span class="ms-2"><strong id="countSeleccionados">0</strong> ticket(s) seleccionado(s)</span>
        </div>
        
        <!-- Botones de bandeja por estado -->
        <div class="btn-filtro-container">
            <a href="tickets.php" class="btn <?php echo empty($estado_filtro) && empty($mis_tickets) ? 'btn-primary' : 'btn-outline-primary'; ?>" title="Todos los tickets abiertos">Todos</a>
            <a href="tickets.php?mis_tickets=1<?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>" class="btn <?php echo !empty($mis_tickets) ? 'btn-info' : 'btn-outline-info'; ?>">Mis Tickets</a>
            <a href="tickets.php?estado=sin%20abrir<?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>" class="btn <?php echo $estado_filtro === 'sin abrir' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">Sin abrir (<?php echo $conteos_estado['sin abrir']; ?>)</a>
            <a href="tickets.php?estado=en%20conocimiento<?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>" class="btn <?php echo $estado_filtro === 'en conocimiento' ? 'btn-info' : 'btn-outline-info'; ?>">En conocimiento (<?php echo $conteos_estado['en conocimiento']; ?>)</a>
            <a href="tickets.php?estado=en%20proceso<?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>" class="btn <?php echo $estado_filtro === 'en proceso' ? 'btn-warning' : 'btn-outline-warning'; ?>">En proceso (<?php echo $conteos_estado['en proceso']; ?>)</a>
            <a href="tickets.php?estado=pendiente%20de%20cierre<?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>" class="btn <?php echo $estado_filtro === 'pendiente de cierre' ? 'btn-danger' : 'btn-outline-danger'; ?>">Pendiente (<?php echo $conteos_estado['pendiente de cierre']; ?>)</a>
            <a href="tickets.php?cerrados=1<?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>" class="btn <?php echo $mostrar_cerrados === '1' ? 'btn-success' : 'btn-outline-success'; ?>">Cerrados (<?php echo $conteo_cerrados; ?>)</a>
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
                                <th style="width: 40px;"><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)"></th>
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
                            <tr class="ticket-list-row" onclick="if(event.target.tagName !== 'INPUT') window.location='ver_ticket.php?id=<?php echo htmlspecialchars($ticket["id"]); ?>'">
                                <td onclick="event.stopPropagation();"><input type="checkbox" class="ticket-checkbox" value="<?php echo $ticket['id']; ?>"></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($ticket["ticket_number"]); ?></strong>
                                    <?php if ($ticket["ticket_padre_id"]): ?>
                                        <br><span class="badge bg-info" style="font-size: 0.75rem;"><i class="bi bi-diagram-3"></i> SUBTAREA</span>
                                    <?php endif; ?>
                                </td>
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
                                <td onclick="event.stopPropagation();">
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
    
    <!-- Modal de Cierre Masivo -->
    <div class="modal fade" id="modalCierre" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cerrar Tickets Seleccionados</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <?php echo inputTokenCSRF(); ?>
                    <div class="modal-body">
                        <p class="text-muted">Selecciona un motivo para cerrar los <strong id="modalCountTickets">0</strong> ticket(s) seleccionado(s):</p>
                        
                        <div class="mb-3">
                            <label for="motivoCierre" class="form-label"><strong>Motivo del Cierre <span class="text-danger">*</span></strong></label>
                            <select class="form-select" id="motivoCierre" name="motivo_cierre" required>
                                <option value="">-- Seleccionar motivo --</option>
                                <option value="Spam">Spam</option>
                                <option value="Ticket repetido">Ticket repetido</option>
                                <option value="Resuelto">Resuelto</option>
                                <option value="No procede">No procede</option>
                                <option value="Otros">Otros</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="cerrar_masivo" value="1" class="btn btn-danger" onclick="return confirm('¿Estás seguro de cerrar estos tickets?');">Cerrar Tickets</button>
                    </div>
                    <input type="hidden" id="ticketIdsInput" name="ticket_ids" value="">
                </form>
            </div>
        </div>
    </div>
    
    <script>
        const modalCierre = new bootstrap.Modal(document.getElementById('modalCierre'));
        
        function updateCheckboxes() {
            const checkboxes = document.querySelectorAll('.ticket-checkbox');
            const seleccionados = Array.from(checkboxes).filter(cb => cb.checked);
            const btnCerrar = document.getElementById('btnCerrarMasivo');
            const countSpan = document.getElementById('countSeleccionados');
            
            countSpan.textContent = seleccionados.length;
            btnCerrar.style.display = seleccionados.length > 0 ? 'block' : 'none';
        }
        
        function toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('.ticket-checkbox');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
            updateCheckboxes();
        }
        
        function abrirModalCierre() {
            const checkboxes = document.querySelectorAll('.ticket-checkbox:checked');
            const ids = Array.from(checkboxes).map(cb => cb.value);
            
            if (ids.length === 0) {
                alert('Debes seleccionar al menos un ticket');
                return;
            }
            
            document.getElementById('ticketIdsInput').value = ids.join(',');
            document.getElementById('modalCountTickets').textContent = ids.length;
            modalCierre.show();
        }
        
        // Validar antes de enviar
        document.querySelector('form').addEventListener('submit', function(e) {
            const motivo = document.getElementById('motivoCierre').value;
            
            if (!motivo) {
                e.preventDefault();
                alert('Debes seleccionar un motivo de cierre');
                return false;
            }
        });
        
        // Listener para checkboxes y búsqueda en tiempo real
        document.addEventListener('DOMContentLoaded', function() {
            // Checkboxes
            document.querySelectorAll('.ticket-checkbox').forEach(cb => {
                cb.addEventListener('change', updateCheckboxes);
            });
            
            // Mostrar toast si hay mensaje de success
            const successMessage = '<?php echo htmlspecialchars($success ?? ''); ?>';
            if (successMessage) {
                mostrarToast(successMessage, 'success');
            }
            
            // Búsqueda en tiempo real
            const searchInput = document.getElementById('searchTickets');
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', function(e) {
                    clearTimeout(searchTimeout);
                    const query = e.target.value.trim();
                    
                    searchTimeout = setTimeout(() => {
                        // Actualizar URL y recargar
                        const url = new URL(window.location);
                        if (query) {
                            url.searchParams.set('buscar', query);
                        } else {
                            url.searchParams.delete('buscar');
                        }
                        window.location.search = url.search;
                    }, 500);
                });
            }
        });
        
        function mostrarToast(mensaje, tipo) {
            const toastHTML = `
                <div class="toast align-items-center text-white bg-${tipo === 'success' ? 'success' : 'danger'} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            ${tipo === 'success' ? '<i class="bi bi-check-circle"></i>' : '<i class="bi bi-exclamation-circle"></i>'} ${mensaje}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `;
            const toastContainer = document.getElementById('toastContainer');
            const toastElement = document.createElement('div');
            toastElement.innerHTML = toastHTML;
            toastContainer.appendChild(toastElement);
            
            const toast = new bootstrap.Toast(toastElement.querySelector('.toast'));
            toast.show();
            
            // Remover elemento después de que se cierre
            toastElement.querySelector('.toast').addEventListener('hidden.bs.toast', function() {
                toastElement.remove();
            });
        }
    </script>
</body>
</html>
