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
    header("Location: dashboard.php");
    exit();
}

// Procesar cierre masivo
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["cerrar_masivo"])) {
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
            
            $success = "Se cerraron " . count($ticket_ids) . " ticket(s) correctamente";
        } catch (PDOException $e) {
            $error = "Error al cerrar tickets: " . $e->getMessage();
        }
    }
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
    <title>Tickets</title>
    <style>
        .ticket-list-row { border-bottom: 1px solid #dee2e6; padding: 15px 0; cursor: pointer; transition: background-color 0.2s; }
        .ticket-list-row:hover { background-color: #f8f9fa; }
        [data-bs-theme="dark"] .ticket-list-row:hover { background-color: #2d3035; }
        .table-responsive { margin: 0 -12px; padding: 0 12px; }
        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <!-- Toast Notification -->
    <div class="toast-container" id="toastContainer"></div>
    
    <div class="container mt-5">
        <div class="row mb-4">
            <div class="col-md-12">
                <h2>Tickets</h2>
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
        
        <!-- Botón de cierre masivo -->
        <div class="mb-3" id="btnCerrarMasivo" style="display: none;">
            <button type="button" class="btn btn-danger" onclick="abrirModalCierre()">🗑️ Cerrar Seleccionados</button>
            <span class="ms-2"><strong id="countSeleccionados">0</strong> ticket(s) seleccionado(s)</span>
        </div>
        
        <!-- Botones de bandeja por estado -->
        <div class="mb-3">
            <a href="tickets.php" class="btn <?php echo empty($estado_filtro) ? 'btn-primary' : 'btn-outline-primary'; ?>" title="Todos los tickets abiertos">Todos</a>
            <a href="tickets.php?estado=sin%20abrir<?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>" class="btn <?php echo $estado_filtro === 'sin abrir' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">Sin abrir (<?php echo $conteos_estado['sin abrir']; ?>)</a>
            <a href="tickets.php?estado=en%20conocimiento<?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>" class="btn <?php echo $estado_filtro === 'en conocimiento' ? 'btn-info' : 'btn-outline-info'; ?>">En conocimiento (<?php echo $conteos_estado['en conocimiento']; ?>)</a>
            <a href="tickets.php?estado=en%20proceso<?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>" class="btn <?php echo $estado_filtro === 'en proceso' ? 'btn-warning' : 'btn-outline-warning'; ?>">En proceso (<?php echo $conteos_estado['en proceso']; ?>)</a>
            <a href="tickets.php?estado=pendiente%20de%20cierre<?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>" class="btn <?php echo $estado_filtro === 'pendiente de cierre' ? 'btn-danger' : 'btn-outline-danger'; ?>">Pendiente de cierre (<?php echo $conteos_estado['pendiente de cierre']; ?>)</a>
            <a href="tickets_cerrados.php" class="btn btn-outline-success">Tickets Cerrados (<?php echo $conteo_cerrados; ?>)</a>
            <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#modalReportes">📊 Reportes</button>
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
                            <tr class="ticket-list-row">
                                <td><input type="checkbox" class="ticket-checkbox" value="<?php echo $ticket['id']; ?>"></td>
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
    
    <!-- Obtener lista de solicitantes únicos para modal -->
    <?php
    try {
        $stmt = $conexion->query("SELECT DISTINCT nombre_solicitante FROM tickets ORDER BY nombre_solicitante");
        $solicitantes_reporte = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        $solicitantes_reporte = [];
    }
    ?>
    
    <!-- Modal de Reportes -->
    <div class="modal fade" id="modalReportes" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Descargar Reporte de Tickets</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="generar_reporte_pdf.php" target="_blank">
                    <div class="modal-body">
                        <h6 class="mb-3">Filtros del Reporte</h6>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="filtro_estado" class="form-label"><strong>Estado del Ticket</strong></label>
                                <select class="form-select" name="filtro_estado">
                                    <option value="">-- Todos los estados --</option>
                                    <option value="sin abrir">Sin abrir</option>
                                    <option value="en conocimiento">En conocimiento</option>
                                    <option value="en proceso">En proceso</option>
                                    <option value="pendiente de cierre">Pendiente de cierre</option>
                                    <option value="ticket cerrado">Ticket Cerrado</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="filtro_solicitante" class="form-label"><strong>Solicitante</strong></label>
                                <select class="form-select" name="filtro_solicitante">
                                    <option value="">-- Todos los solicitantes --</option>
                                    <?php foreach ($solicitantes_reporte as $solicitante): ?>
                                        <option value="<?php echo htmlspecialchars($solicitante); ?>">
                                            <?php echo htmlspecialchars($solicitante); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="fecha_desde" class="form-label"><strong>Fecha Desde</strong></label>
                                <input type="date" class="form-control" name="fecha_desde">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="fecha_hasta" class="form-label"><strong>Fecha Hasta</strong></label>
                                <input type="date" class="form-control" name="fecha_hasta">
                            </div>
                        </div>
                        
                        <hr>
                        <h6 class="mb-3">Tipo de Reporte</h6>
                        
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="tipo_reporte" value="completo" id="tipo_completo" checked>
                            <label class="form-check-label" for="tipo_completo">
                                <strong>Reporte Completo</strong> - Incluye detalles y comentarios
                            </label>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipo_reporte" value="resumen" id="tipo_resumen">
                            <label class="form-check-label" for="tipo_resumen">
                                <strong>Reporte Resumido</strong> - Solo información principal
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">📄 Generar Reporte</button>
                    </div>
                </form>
            </div>
        </div>
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
                        
                        <div class="mb-3" id="divOtros" style="display: none;">
                            <label for="especificar" class="form-label">Especificar motivo:</label>
                            <input type="text" class="form-control" id="especificar" name="motivo_otros" placeholder="Describe el motivo del cierre">
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
        
        // Mostrar input para "Otros"
        document.getElementById('motivoCierre').addEventListener('change', function() {
            document.getElementById('divOtros').style.display = this.value === 'Otros' ? 'block' : 'none';
        });
        
        // Validar que si se selecciona "Otros", el campo esté lleno
        document.querySelector('form').addEventListener('submit', function(e) {
            const motivo = document.getElementById('motivoCierre').value;
            const especificar = document.getElementById('especificar').value;
            
            if (motivo === 'Otros' && !especificar.trim()) {
                e.preventDefault();
                alert('Debes especificar el motivo cuando seleccionas "Otros"');
                return false;
            }
            
            if (motivo === 'Otros' && especificar.trim()) {
                document.getElementById('ticketIdsInput').form.motivo_cierre.value = 'Otros: ' + especificar;
            }
        });
        
        // Listener para checkboxes
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.ticket-checkbox').forEach(cb => {
                cb.addEventListener('change', updateCheckboxes);
            });
            
            // Mostrar toast si hay mensaje de success
            const successMessage = '<?php echo htmlspecialchars($success ?? ''); ?>';
            if (successMessage) {
                mostrarToast(successMessage, 'success');
            }
        });
        
        function mostrarToast(mensaje, tipo) {
            const toastHTML = `
                <div class="toast align-items-center text-white bg-${tipo === 'success' ? 'success' : 'danger'} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            ${tipo === 'success' ? '✓' : '✕'} ${mensaje}
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
        
        // Validar fechas en modal de reportes
        document.querySelectorAll('#modalReportes form')[0]?.addEventListener('submit', function(e) {
            const fechaDesde = document.querySelector('[name="fecha_desde"]').value;
            const fechaHasta = document.querySelector('[name="fecha_hasta"]').value;
            
            if (fechaDesde && fechaHasta && fechaDesde > fechaHasta) {
                e.preventDefault();
                alert('La fecha "Desde" no puede ser mayor que la fecha "Hasta"');
                return false;
            }
        });
    </script>
