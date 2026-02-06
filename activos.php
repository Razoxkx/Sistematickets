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
$activos = [];
$busqueda = $_GET["buscar"] ?? "";
$pagina = max(1, (int)($_GET["pagina"] ?? 1));
$orden = $_GET["orden"] ?? "rfk";
$direccion = $_GET["dir"] ?? "ASC";

// Validar orden
$ordenes_validas = ['rfk', 'titulo', 'tipo', 'fabricante', 'ubicacion', 'propietario'];
if (!in_array($orden, $ordenes_validas)) {
    $orden = "rfk";
}

// Validar dirección
if (!in_array($direccion, ['ASC', 'DESC'])) {
    $direccion = "ASC";
}

$activos_por_pagina = 9;
$offset = ($pagina - 1) * $activos_por_pagina;
$total_activos = 0;
$total_paginas = 1;

try {
    $params = [];
    $where = "1=1";
    
    // Si hay búsqueda, buscar en TODOS los campos con LIKE
    if (!empty($busqueda)) {
        $busqueda_term = '%' . $busqueda . '%';
        // Construir búsqueda en todos los campos
        $where = "(LOWER(rfk) LIKE LOWER(?) OR LOWER(titulo) LIKE LOWER(?) OR LOWER(descripcion) LIKE LOWER(?) OR LOWER(propietario) LIKE LOWER(?) OR LOWER(ubicacion) LIKE LOWER(?) OR LOWER(tipo) LIKE LOWER(?) OR LOWER(fabricante) LIKE LOWER(?) OR LOWER(modelo) LIKE LOWER(?) OR LOWER(serie) LIKE LOWER(?))";
        $params = array_fill(0, 9, $busqueda_term);
    }
    
    // Contar total
    $sql_count = "SELECT COUNT(*) as total FROM activos WHERE " . $where;
    $stmt_count = $conexion->prepare($sql_count);
    $stmt_count->execute($params);
    $total_activos = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Obtener activos
    $orden_map = [
        'rfk' => 'rfk',
        'titulo' => 'titulo',
        'tipo' => 'tipo',
        'fabricante' => 'fabricante',
        'ubicacion' => 'ubicacion',
        'propietario' => 'propietario'
    ];
    $columna_orden = $orden_map[$orden];
    
    $sql = "SELECT * FROM activos WHERE " . $where . " ORDER BY " . $columna_orden . " " . $direccion . " LIMIT " . intval($activos_por_pagina) . " OFFSET " . intval($offset);
    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $activos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_paginas = ceil($total_activos / $activos_por_pagina);
    
} catch (PDOException $e) {
    $error = "Error al obtener activos: " . $e->getMessage();
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
    <title>Activos - Sistema de Gestión</title>
    <style>
        body {
            background: linear-gradient(to bottom, #f8f9fa, #ffffff);
        }
        
        [data-bs-theme="dark"] body {
            background: linear-gradient(to bottom, #1a1a1a, #0d0d0d);
        }
        
        .table-hover tbody tr:hover { 
            background-color: rgba(102, 126, 234, 0.1);
        }
        
        [data-bs-theme="dark"] .table-hover tbody tr:hover {
            background-color: rgba(139, 157, 255, 0.1);
        }
        
        .card {
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        
        [data-bs-theme="dark"] .card {
            border-color: #444;
            background: #1e1e1e;
        }
        
        .container-fluid {
            padding-top: 30px;
            padding-bottom: 30px;
        }
        
        h2 {
            color: #667eea;
            font-weight: 700;
            margin-bottom: 30px;
        }
        
        [data-bs-theme="dark"] h2 {
            color: #8b9dff;
        }
        
        .table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        
        [data-bs-theme="dark"] .table {
            background: #1e1e1e;
            color: #e0e0e0;
        }
        
        .table thead th {
            background: #f8f9fa;
            color: #667eea;
            font-weight: 600;
            border-color: #e0e0e0;
        }
        
        [data-bs-theme="dark"] .table thead th {
            background: #262626;
            color: #8b9dff;
            border-color: #444;
        }
        
        .btn-success {
            background: #28a745;
            border-color: #28a745;
        }
        
        .btn-success:hover {
            background: #218838;
            border-color: #218838;
        }
        
        [data-bs-theme="dark"] .btn-success {
            background: #28a745;
            border-color: #28a745;
        }
        
        .form-control {
            border-radius: 6px;
            border: 1px solid #ddd;
        }
        
        [data-bs-theme="dark"] .form-control {
            background: #2a2a2a;
            border-color: #444;
            color: #e0e0e0;
        }
        
        [data-bs-theme="dark"] .form-control:focus {
            background: #2a2a2a;
            border-color: #667eea;
            color: #e0e0e0;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-outline-primary {
            color: #667eea;
            border-color: #667eea;
        }
        
        .btn-outline-primary:hover {
            background: #667eea;
            border-color: #667eea;
        }
        
        [data-bs-theme="dark"] .btn-outline-primary {
            color: #8b9dff;
            border-color: #8b9dff;
        }
        
        [data-bs-theme="dark"] .btn-outline-primary:hover {
            background: #8b9dff;
            border-color: #8b9dff;
            color: #1e1e1e;
        }
        
        .btn-group .btn {
            border-radius: 6px;
            margin-right: 10px;
        }
        
        .badge {
            font-size: 0.85rem;
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
    
    <div class="container-fluid">
        <div class="row mb-5">
            <div class="col-md-12">
                <h2><i class="bi bi-boxes"></i> Gestión de Activos</h2>
            </div>
        </div>
        
        <!-- Búsqueda -->
        <div class="card shadow mb-4">
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-9">
                        <input type="text" id="searchActivos" class="form-control" placeholder="Buscar por RFK (ej: AK7902031), título, tipo, fabricante, ubicación o propietario..." value="<?php echo htmlspecialchars($busqueda); ?>">
                    </div>
                    <div class="col-md-3">
                        <?php if (!empty($busqueda)): ?>
                            <a href="activos.php" class="btn btn-secondary w-100"><i class="bi bi-x-circle"></i> Limpiar</a>
                        <?php else: ?>
                            <a href="crear_activo.php" class="btn btn-success w-100"><i class="bi bi-plus-circle"></i> Crear Activo</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Listado de Activos -->
        <?php if (empty($activos)): ?>
            <div class="alert alert-info">
                <?php echo !empty($busqueda) ? "No se encontraron activos con esa búsqueda" : "No hay activos aún"; ?>
            </div>
        <?php else: ?>
            <!-- Vista Lista (tabla) -->
            <div class="table-responsive" style="border-radius: 8px; overflow: hidden;">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 120px;"><a href="?orden=rfk&dir=<?php echo $orden === 'rfk' && $direccion === 'ASC' ? 'DESC' : 'ASC'; ?><?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>&pagina=1" style="text-decoration: none; color: inherit;"><i class="bi bi-box"></i> RFK <?php echo $orden === 'rfk' ? ($direccion === 'ASC' ? '▲' : '▼') : ''; ?></a></th>
                            <th><a href="?orden=titulo&dir=<?php echo $orden === 'titulo' && $direccion === 'ASC' ? 'DESC' : 'ASC'; ?><?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>&pagina=1" style="text-decoration: none; color: inherit;"><i class="bi bi-file-text"></i> Título <?php echo $orden === 'titulo' ? ($direccion === 'ASC' ? '▲' : '▼') : ''; ?></a></th>
                            <th style="width: 120px;"><a href="?orden=tipo&dir=<?php echo $orden === 'tipo' && $direccion === 'ASC' ? 'DESC' : 'ASC'; ?><?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>&pagina=1" style="text-decoration: none; color: inherit;"><i class="bi bi-tag"></i> Tipo <?php echo $orden === 'tipo' ? ($direccion === 'ASC' ? '▲' : '▼') : ''; ?></a></th>
                            <th style="width: 140px;"><a href="?orden=fabricante&dir=<?php echo $orden === 'fabricante' && $direccion === 'ASC' ? 'DESC' : 'ASC'; ?><?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>&pagina=1" style="text-decoration: none; color: inherit;"><i class="bi bi-industry"></i> Fabricante <?php echo $orden === 'fabricante' ? ($direccion === 'ASC' ? '▲' : '▼') : ''; ?></a></th>
                            <th style="width: 140px;"><a href="?orden=ubicacion&dir=<?php echo $orden === 'ubicacion' && $direccion === 'ASC' ? 'DESC' : 'ASC'; ?><?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>&pagina=1" style="text-decoration: none; color: inherit;"><i class="bi bi-geo-alt"></i> Ubicación <?php echo $orden === 'ubicacion' ? ($direccion === 'ASC' ? '▲' : '▼') : ''; ?></a></th>
                            <th style="width: 120px;"><a href="?orden=propietario&dir=<?php echo $orden === 'propietario' && $direccion === 'ASC' ? 'DESC' : 'ASC'; ?><?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>&pagina=1" style="text-decoration: none; color: inherit;"><i class="bi bi-person"></i> Propietario <?php echo $orden === 'propietario' ? ($direccion === 'ASC' ? '▲' : '▼') : ''; ?></a></th>
                            <th style="width: 140px;"><i class="bi bi-person-plus"></i> Creado por</th>
                            <!-- <th style="width: 150px;"><i class="bi bi-calendar-event"></i> Fecha Creación</th>
                            <th style="width: 140px;"><i class="bi bi-person-check"></i> Modificado por</th>
                            <th style="width: 150px;"><i class="bi bi-calendar-check"></i> Última Modificación</th> -->
                            <th style="width: 100px; text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activos as $activo): ?>
                            <tr style="cursor: pointer;" onclick="window.location='ver_activo.php?id=<?php echo htmlspecialchars($activo['id']); ?>'">
                                <td><strong style="color: #667eea;"><?php echo htmlspecialchars($activo["rfk"]); ?></strong></td>
                                <td><?php echo htmlspecialchars(substr($activo["titulo"], 0, 50)); ?></td>
                                <td><span class="badge" style="background-color: #667eea; color: white;"><?php echo htmlspecialchars($activo["tipo"]); ?></span></td>
                                <td><?php echo htmlspecialchars($activo["fabricante"]); ?></td>
                                <td><?php echo htmlspecialchars($activo["ubicacion"]); ?></td>
                                <td><?php echo htmlspecialchars($activo["propietario"]); ?></td>
                                <td><?php echo htmlspecialchars($activo["usuario_creado_por"] ?? "-"); ?></td>
                                <td style="text-align: center;">
                                    <a href="editar_activo.php?id=<?php echo htmlspecialchars($activo["id"]); ?>" class="btn btn-sm btn-outline-warning" onclick="event.stopPropagation();"><i class="bi bi-pencil"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <!-- Paginación -->
        <?php if ($total_paginas > 1): ?>
            <nav aria-label="Paginación" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $pagina === 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?pagina=1<?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>&orden=<?php echo $orden; ?>&dir=<?php echo $direccion; ?>">Primera</a>
                    </li>
                    <li class="page-item <?php echo $pagina === 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?pagina=<?php echo $pagina - 1; ?><?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>&orden=<?php echo $orden; ?>&dir=<?php echo $direccion; ?>">Anterior</a>
                    </li>
                    
                    <?php for ($i = max(1, $pagina - 2); $i <= min($total_paginas, $pagina + 2); $i++): ?>
                        <li class="page-item <?php echo $i === $pagina ? 'active' : ''; ?>">
                            <a class="page-link" href="?pagina=<?php echo $i; ?><?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>&orden=<?php echo $orden; ?>&dir=<?php echo $direccion; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo $pagina === $total_paginas ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?pagina=<?php echo $pagina + 1; ?><?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>&orden=<?php echo $orden; ?>&dir=<?php echo $direccion; ?>">Siguiente</a>
                    </li>
                    <li class="page-item <?php echo $pagina === $total_paginas ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?pagina=<?php echo $total_paginas; ?><?php echo !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : ''; ?>&orden=<?php echo $orden; ?>&dir=<?php echo $direccion; ?>">Última</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
        
        <div class="text-center text-muted mt-3">
            <small>Total: <?php echo $total_activos; ?> activo(s) | Página <?php echo $pagina; ?> de <?php echo $total_paginas; ?></small>
        </div>
    </div>
    
    <!-- Modal Ver Activo -->
    <div class="modal fade" id="modalVerActivo" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Detalles del Activo - <span id="modalRFK"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Información Básica</h6>
                            <dl class="row">
                                <dt class="col-sm-5">RFK:</dt>
                                <dd class="col-sm-7"><strong id="modalRFKText"></strong></dd>
                                
                                <dt class="col-sm-5">Título:</dt>
                                <dd class="col-sm-7" id="modalTitulo"></dd>
                                
                                <dt class="col-sm-5">Tipo:</dt>
                                <dd class="col-sm-7" id="modalTipo"></dd>
                                
                                <dt class="col-sm-5">Fabricante:</dt>
                                <dd class="col-sm-7" id="modalFabricante"></dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <h6>Ubicación y Asignación</h6>
                            <dl class="row">
                                <dt class="col-sm-5">Ubicación:</dt>
                                <dd class="col-sm-7" id="modalUbicacion"></dd>
                                
                                <dt class="col-sm-5">Propietario:</dt>
                                <dd class="col-sm-7" id="modalPropietario"></dd>
                                
                                <dt class="col-sm-5">Fecha Adquisición:</dt>
                                <dd class="col-sm-7" id="modalFecha"></dd>
                            </dl>
                        </div>
                    </div>
                    <hr>
                    <h6>Descripción</h6>
                    <p id="modalDescripcion"></p>
                    <hr>
                    <h6>Mencionado En Tickets</h6>
                    <div id="modalTicketsMencionados"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <a href="#" id="modalEditBtn" class="btn btn-warning">✏️ Editar</a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function cargarActivo(activo) {
            document.getElementById('modalRFK').textContent = activo.rfk;
            document.getElementById('modalRFKText').textContent = activo.rfk;
            document.getElementById('modalTitulo').textContent = activo.titulo;
            document.getElementById('modalTipo').textContent = activo.tipo;
            document.getElementById('modalFabricante').textContent = activo.fabricante;
            document.getElementById('modalUbicacion').textContent = activo.ubicacion;
            document.getElementById('modalPropietario').textContent = activo.propietario;
            document.getElementById('modalFecha').textContent = activo.fecha_adquisicion || 'N/A';
            document.getElementById('modalDescripcion').textContent = activo.descripcion || 'Sin descripción';
            document.getElementById('modalEditBtn').href = 'editar_activo.php?id=' + activo.id;
            
            // Cargar tickets mencionados
            cargarTicketsMencionados(activo.rfk);
        }
        
        function cargarTicketsMencionados(rfk) {
            const contenedor = document.getElementById('modalTicketsMencionados');
            contenedor.innerHTML = '<div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Cargando...</span></div>';
            
            fetch('api_ticket.php?action=obtener_tickets_por_activo&rfk=' + encodeURIComponent(rfk))
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.tickets && data.tickets.length > 0) {
                        let html = '<div class="row g-2">';
                        data.tickets.forEach(ticket => {
                            const estado = ticket.es_cerrado ? 'Cerrado' : ticket.estado;
                            const colorEstado = ticket.es_cerrado ? 'secondary' : 'success';
                            html += `
                                <div class="col-12">
                                    <div class="d-flex justify-content-between align-items-center p-2 border-bottom">
                                        <div>
                                            <a href="ver_ticket.php?id=${encodeURIComponent(ticket.ticket_number)}" class="badge bg-primary text-decoration-none" style="font-size: 0.9rem;">
                                                ${escapeHtml(ticket.ticket_number)}
                                            </a>
                                            <span class="ms-2">${escapeHtml(ticket.titulo.substring(0, 50))}</span>
                                            ${ticket.titulo.length > 50 ? '...' : ''}
                                        </div>
                                        <span class="badge bg-${colorEstado}">${escapeHtml(estado)}</span>
                                    </div>
                                </div>
                            `;
                        });
                        html += '</div>';
                        contenedor.innerHTML = html;
                    } else {
                        contenedor.innerHTML = '<div class="alert alert-info small mb-0">Este activo no ha sido mencionado en ningún ticket abierto.</div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    contenedor.innerHTML = '<div class="alert alert-danger small mb-0">Error al cargar los tickets.</div>';
                });
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Real-time search
        let searchTimeout;
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchActivos');
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    clearTimeout(searchTimeout);
                    const query = e.target.value.trim();
                    searchTimeout = setTimeout(() => {
                        const url = new URL(window.location);
                        if (query) {
                            url.searchParams.set('buscar', query);
                            url.searchParams.set('pagina', '1'); // Resetear a página 1 al buscar
                        } else {
                            url.searchParams.delete('buscar');
                            url.searchParams.set('pagina', '1'); // Resetear a página 1 al limpiar
                        }
                        window.location.search = url.search;
                    }, 1000);
                });
            }
        });
    </script>
</body>
</html>
