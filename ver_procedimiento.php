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
    header("Location: tickets.php");
    exit();
}

$procedimiento = null;
$menciones = [];
$historial = [];
$error = "";
$success = $_GET["success"] ?? "";

// Obtener ID del procedimiento
$procedimiento_id = $_GET["id"] ?? "";
if (empty($procedimiento_id)) {
    header("Location: procedimientos.php");
    exit();
}

try {
    // Obtener procedimiento
    $stmt = $conexion->prepare("
        SELECT p.*, u.username as autor_nombre
        FROM procedimientos p
        JOIN users u ON p.usuario_creador = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$procedimiento_id]);
    $procedimiento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$procedimiento) {
        $error = "Procedimiento no encontrado";
    } else {
        // Obtener menciones
        $stmt = $conexion->prepare("
            SELECT mp.*, t.ticket_number, c.comentario, c.fecha as fecha_comentario, u.username
            FROM menciones_procedimientos mp
            JOIN tickets t ON mp.ticket_id = t.id
            JOIN comentarios_tickets c ON mp.comentario_id = c.id
            JOIN users u ON c.usuario_id = u.id
            WHERE mp.procedimiento_id = ?
            ORDER BY mp.fecha_mencion DESC
        ");
        $stmt->execute([$procedimiento_id]);
        $menciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener historial
        $stmt = $conexion->prepare("
            SELECT h.*, u.username
            FROM historial_procedimientos h
            JOIN users u ON h.usuario_id = u.id
            WHERE h.procedimiento_id = ?
            ORDER BY h.fecha_cambio DESC
        ");
        $stmt->execute([$procedimiento_id]);
        $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="css/dark-mode.css" rel="stylesheet">
    <title><?php echo $procedimiento ? htmlspecialchars($procedimiento["titulo"]) : "Procedimiento"; ?></title>
    <style>
        body {
            background-color: #f8f9fa;
        }
        [data-bs-theme="dark"] body {
            background-color: #1a1d23;
        }
        
        .header-procedimiento {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        [data-bs-theme="dark"] .header-procedimiento {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .cuerpo-procedimiento {
            background: white;
            padding: 30px;
            border-radius: 8px;
            line-height: 1.8;
            font-size: 16px;
            max-width: 100%;
            margin: 0 0 30px 0;
            white-space: pre-line;
            word-wrap: break-word;
            overflow-x: auto;
            border-left: 5px solid #667eea;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: left;
        }
        
        [data-bs-theme="dark"] .cuerpo-procedimiento {
            background: #252a32;
            border-left-color: #667eea;
        }
        
        .info-meta {
            display: flex;
            gap: 40px;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-item strong {
            color: #667eea;
        }
        
        .mencion-card {
            border-left: 4px solid #17a2b8;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 4px;
            background: white;
        }
        
        [data-bs-theme="dark"] .mencion-card {
            background: #252a32;
        }
        
        .pdf-adjunto-card {
            border-left: 5px solid #dc3545;
            padding: 25px;
            margin: 30px 0;
            border-radius: 8px;
            background: white;
            box-shadow: 0 2px 8px rgba(220, 53, 69, 0.1);
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        [data-bs-theme="dark"] .pdf-adjunto-card {
            background: #252a32;
            border-left-color: #dc3545;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.3);
        }
        
        .pdf-icon-container {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            border-radius: 8px;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.2);
        }
        
        .pdf-icon-container i {
            font-size: 32px;
            color: white;
        }
        
        .pdf-info {
            flex: 1;
        }
        
        .pdf-nombre {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
            word-break: break-word;
            font-size: 16px;
        }
        
        [data-bs-theme="dark"] .pdf-nombre {
            color: #e0e0e0;
        }
        
        .pdf-meta {
            font-size: 13px;
            color: #6c757d;
            margin-bottom: 12px;
        }
        
        [data-bs-theme="dark"] .pdf-meta {
            color: #a0a0a0;
        }
        
        .pdf-acciones {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .pdf-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            font-weight: 500;
            text-decoration: none;
        }
        
        .pdf-btn-descargar {
            background-color: #dc3545;
            color: white;
        }
        
        .pdf-btn-descargar:hover {
            background-color: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }
        
        .pdf-btn-ver {
            background-color: transparent;
            color: #667eea;
            border: 2px solid #667eea;
        }
        
        .pdf-btn-ver:hover {
            background-color: #667eea;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        [data-bs-theme="dark"] .pdf-btn-ver {
            color: #8b9dff;
            border-color: #8b9dff;
        }
        
        [data-bs-theme="dark"] .pdf-btn-ver:hover {
            background-color: #8b9dff;
            color: #1a1d23;
        }
        
        .timeline-item {
            padding: 20px;
            border-left: 3px solid #667eea;
            margin-bottom: 20px;
            background: white;
            border-radius: 4px;
        }
        
        [data-bs-theme="dark"] .timeline-item {
            background: #252a32;
        }
        
        .btn-section {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .procedimiento-id {
            font-family: 'Courier New', monospace;
            background: rgba(255,255,255,0.2);
            padding: 8px 12px;
            border-radius: 4px;
            font-weight: bold;
        }
        
        h1, h2, h3 {
            color: white;
            font-weight: 700;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        h1 {\n            font-size: 2rem;\n        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="container mt-5">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php else: ?>
            <?php if (!empty($success)): ?>
                <div class="position-fixed top-0 start-50 translate-middle-x mt-3" style="z-index: 1080;">
                    <div id="successToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="2000">
                        <div class="d-flex">
                            <div class="toast-body">
                                <?php
                                    if ($success === 'creado') echo 'Procedimiento creado exitosamente';
                                    elseif ($success === 'cambios') echo 'Cambios realizados';
                                    else echo htmlspecialchars($success);
                                ?>
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Header -->
            <div class="header-procedimiento">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h1 class="mb-2"><?php echo htmlspecialchars($procedimiento["titulo"]); ?></h1>
                        <div class="procedimiento-id">
                            <i class="bi bi-key"></i> <?php echo htmlspecialchars($procedimiento["id_procedimiento"]); ?>
                        </div>
                    </div>
                    <span class="badge <?php echo $procedimiento["tipo_procedimiento"] === "técnico" ? "bg-info" : "bg-warning"; ?>" style="font-size: 1rem; padding: 10px 15px;">
                        <i class="bi <?php echo $procedimiento["tipo_procedimiento"] === "técnico" ? "bi-wrench" : "bi-clipboard-check"; ?>"></i>
                        <?php echo ucfirst($procedimiento["tipo_procedimiento"]); ?>
                    </span>
                </div>
            </div>
            
            <!-- Botones de acción -->
            <div class="btn-section">
                <a href="editar_procedimiento.php?id=<?php echo $procedimiento_id; ?>" class="btn btn-primary">
                    <i class="bi bi-pencil"></i> Editar
                </a>
                <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#modalHistorial">
                    <i class="bi bi-clock-history"></i> Actividad
                </button>
                <a href="procedimientos.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>
            
            <!-- Información Meta -->
            <div class="info-meta">
                <div class="info-item">
                    <i class="bi bi-person"></i>
                    <span><strong>Autor:</strong> <?php echo htmlspecialchars($procedimiento["autor_nombre"]); ?></span>
                </div>
                <div class="info-item">
                    <i class="bi bi-calendar"></i>
                    <span><strong>Creado:</strong> <?php echo date('d/m/Y H:i', strtotime($procedimiento["fecha_creacion"])); ?></span>
                </div>
                <div class="info-item">
                    <i class="bi bi-pencil"></i>
                    <span><strong>Modificado:</strong> <?php echo date('d/m/Y H:i', strtotime($procedimiento["fecha_ultima_modificacion"])); ?></span>
                </div>
            </div>
            
            <!-- Contenido -->
            <h3 class="mb-3"><i class="bi bi-file-text"></i> Contenido</h3>
            <div class="cuerpo-procedimiento">
                <?php 
                // Procesar el contenido para mejorar la alineación
                $contenido = htmlspecialchars($procedimiento["cuerpo"]);
                // Dividir por líneas y limpiar espacios en blanco
                $lineas = explode("\n", $contenido);
                $contenido_procesado = implode("\n", array_map('trim', $lineas));
                // Eliminar líneas vacías al inicio y final
                $contenido_procesado = trim($contenido_procesado);
                echo $contenido_procesado;
                ?>
            </div>
            
            <!-- PDF Adjunto -->
            <?php if (!empty($procedimiento["archivo_pdf"])): ?>
            <div class="pdf-adjunto-card">
                <div class="pdf-icon-container">
                    <i class="bi bi-file-pdf"></i>
                </div>
                <div class="pdf-info">
                    <div class="pdf-nombre">
                        <?php 
                        $filename = preg_replace('/^proc_[a-f0-9]+_/', '', $procedimiento["archivo_pdf"]);
                        echo htmlspecialchars($filename);
                        ?>
                    </div>
                    <div class="pdf-meta">
                        <i class="bi bi-paperclip"></i> Documento PDF adjunto
                    </div>
                    <div class="pdf-acciones">
                        <a href="descargar_pdf_procedimiento.php?id=<?php echo htmlspecialchars($procedimiento_id); ?>" 
                           class="pdf-btn pdf-btn-descargar" 
                           download>
                            <i class="bi bi-download"></i> Descargar
                        </a>
                        <button class="pdf-btn pdf-btn-ver" 
                                onclick="abrirVisorPDF('descargar_pdf_procedimiento.php?id=<?php echo htmlspecialchars($procedimiento_id); ?>&ver=1')">
                            <i class="bi bi-eye"></i> Ver
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Menciones -->
            <h3 class="mb-3"><i class="bi bi-link-45deg"></i> Menciones en Tickets (<?php echo count($menciones); ?>)</h3>
            <div class="card mb-4">
                <div class="card-body">
                    <?php if (empty($menciones)): ?>
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-info-circle"></i> Este procedimiento aún no ha sido mencionado en ningún ticket.
                        </div>
                    <?php else: ?>
                        <?php foreach ($menciones as $mencion): ?>
                            <div class="mencion-card">
                                <h6 class="mb-2">
                                    <a href="ver_ticket.php?id=<?php echo htmlspecialchars($mencion["ticket_id"]); ?>">
                                        <i class="bi bi-ticket-detailed"></i> <?php echo htmlspecialchars($mencion["ticket_number"]); ?>
                                    </a>
                                </h6>
                                <p class="mb-2 small text-muted">
                                    <strong><?php echo htmlspecialchars($mencion["username"]); ?></strong> - 
                                    <?php echo date('d/m/Y H:i', strtotime($mencion["fecha_comentario"])); ?>
                                </p>
                                <p class="mb-0" style="max-height: 100px; overflow: hidden;">
                                    <?php echo htmlspecialchars(substr($mencion["comentario"], 0, 200)); ?>
                                    <?php echo strlen($mencion["comentario"]) > 200 ? "..." : ""; ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal Historial -->
    <div class="modal fade" id="modalHistorial" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-clock-history"></i> Actividad del Procedimiento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($historial)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> No hay cambios registrados aún.
                        </div>
                    <?php else: ?>
                        <?php foreach ($historial as $cambio): ?>
                            <div class="timeline-item">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <strong><?php echo htmlspecialchars($cambio["username"]); ?></strong>
                                        <span class="text-muted ms-2">modificó <strong><?php echo htmlspecialchars($cambio["campo_modificado"]); ?></strong></span>
                                    </div>
                                    <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($cambio["fecha_cambio"])); ?></small>
                                </div>
                                
                                <?php if ($cambio["campo_modificado"] === "cuerpo"): ?>
                                    <p class="small text-muted mb-0">Se actualizó el contenido del procedimiento</p>
                                <?php else: ?>
                                    <div class="small">
                                        <span class="badge bg-danger">Antes: <?php echo htmlspecialchars(substr($cambio["valor_anterior"], 0, 30)); ?></span>
                                        <span class="badge bg-success ms-2">Después: <?php echo htmlspecialchars(substr($cambio["valor_nuevo"], 0, 30)); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Visor PDF -->
    <div class="modal fade" id="modalVisorPDF" tabindex="-1" aria-labelledby="modalVisorPDFLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalVisorPDFLabel"><i class="bi bi-file-pdf"></i> Previsualización PDF</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="height: 80vh; overflow-y: auto; background: #f0f0f0; padding: 10px;">
                    <div id="pdfContainer" style="display: flex; justify-content: center; align-items: flex-start;">
                        <canvas id="pdfCanvas" style="max-width: 100%; box-shadow: 0 2px 8px rgba(0,0,0,0.15);"></canvas>
                    </div>
                    <div id="pdfLoading" style="text-align: center; padding: 20px; display: none;">
                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="mt-2 text-muted">Cargando documento PDF...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="me-auto">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnPrevPage" onclick="previousPage()" title="Página anterior">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <span id="pageInfo" class="ms-2 me-2" style="font-size: 0.9rem;">-</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnNextPage" onclick="nextPage()" title="Página siguiente">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                    <a href="#" id="btnDescargarPDF" class="btn btn-primary" download>
                        <i class="bi bi-download"></i> Descargar
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x"></i> Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- PDF.js Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Configurar worker de PDF.js
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        
        let currentPDF = null;
        let currentPageNumber = 1;
        let totalPages = 0;
        let currentScale = 1.5;
        
        // Función para renderizar una página
        function renderPage(pageNumber) {
            if (!currentPDF) return;
            
            currentPDF.getPage(pageNumber).then(function(page) {
                const viewport = page.getViewport({scale: currentScale});
                const canvas = document.getElementById('pdfCanvas');
                const context = canvas.getContext('2d');
                canvas.height = viewport.height;
                canvas.width = viewport.width;
                
                page.render({
                    canvasContext: context,
                    viewport: viewport
                });
                
                // Actualizar información de página
                document.getElementById('pageInfo').textContent = `${pageNumber} / ${totalPages}`;
                currentPageNumber = pageNumber;
                
                // Actualizar estado de botones
                document.getElementById('btnPrevPage').disabled = pageNumber <= 1;
                document.getElementById('btnNextPage').disabled = pageNumber >= totalPages;
            });
        }
        
        // Función para ir a página anterior
        function previousPage() {
            if (currentPageNumber > 1) {
                renderPage(currentPageNumber - 1);
            }
        }
        
        // Función para ir a página siguiente
        function nextPage() {
            if (currentPageNumber < totalPages) {
                renderPage(currentPageNumber + 1);
            }
        }
        
        // Función para abrir el visor de PDF en modal
        function abrirVisorPDF(url) {
            const btnDescargar = document.getElementById('btnDescargarPDF');
            const pdfLoading = document.getElementById('pdfLoading');
            const pdfContainer = document.getElementById('pdfContainer');
            
            // Mostrar loading
            pdfLoading.style.display = 'block';
            pdfContainer.style.display = 'none';
            
            // Establecer la URL de descarga
            btnDescargar.href = url;
            
            // Cargar el PDF con PDF.js
            pdfjsLib.getDocument(url).promise.then(function(pdf) {
                currentPDF = pdf;
                totalPages = pdf.numPages;
                currentPageNumber = 1;
                
                // Ocultar loading y mostrar contenedor
                pdfLoading.style.display = 'none';
                pdfContainer.style.display = 'flex';
                
                // Renderizar primera página
                renderPage(1);
            }).catch(function(error) {
                console.error('Error al cargar PDF:', error);
                pdfLoading.innerHTML = '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> No se pudo cargar el PDF. <a href="' + url + '" class="alert-link" download>Descargar archivo</a></div>';
            });
            
            // Abrir el modal
            const modal = new bootstrap.Modal(document.getElementById('modalVisorPDF'));
            modal.show();
        }
        
        document.addEventListener('DOMContentLoaded', function () {
            const toastEl = document.getElementById('successToast');
            if (toastEl) {
                const toast = new bootstrap.Toast(toastEl);
                toast.show();
            }
        });
    </script>
    <script src="includes/dark-mode.js"></script>
</body>
</html>
