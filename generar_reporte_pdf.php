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

// Obtener filtros
$filtro_estado = $_POST["filtro_estado"] ?? "";
$filtro_solicitante = $_POST["filtro_solicitante"] ?? "";
$fecha_desde = $_POST["fecha_desde"] ?? "";
$fecha_hasta = $_POST["fecha_hasta"] ?? "";
$tipo_reporte = $_POST["tipo_reporte"] ?? "completo";

// Construir query
$where = "1=1";
$params = [];

if (!empty($filtro_estado)) {
    $where .= " AND t.estado = ?";
    $params[] = $filtro_estado;
}

if (!empty($filtro_solicitante)) {
    $where .= " AND t.nombre_solicitante = ?";
    $params[] = $filtro_solicitante;
}

if (!empty($fecha_desde)) {
    $where .= " AND DATE(t.fecha_creacion) >= ?";
    $params[] = $fecha_desde;
}

if (!empty($fecha_hasta)) {
    $where .= " AND DATE(t.fecha_creacion) <= ?";
    $params[] = $fecha_hasta;
}

try {
    $stmt = $conexion->prepare("
        SELECT t.*, u.username as creador_nombre
        FROM tickets t
        JOIN users u ON t.usuario_creador = u.id
        WHERE " . $where . "
        ORDER BY t.fecha_creacion DESC
    ");
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener comentarios si es reporte completo
    $comentarios_map = [];
    if ($tipo_reporte === "completo") {
        foreach ($tickets as $ticket) {
            $stmt = $conexion->prepare("
                SELECT c.*, u.username
                FROM comentarios_tickets c
                JOIN users u ON c.usuario_id = u.id
                WHERE c.ticket_id = ?
                ORDER BY c.fecha ASC
            ");
            $stmt->execute([$ticket["id"]]);
            $comentarios_map[$ticket["id"]] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    die("Error al obtener datos: " . $e->getMessage());
}

// Generar HTML para PDF
$html = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 10pt; margin: 10px; }
        h1, h2 { color: #8b9dff; font-weight: 700; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #0d6efd; color: white; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .ticket-section { page-break-inside: avoid; margin: 15px 0; border: 1px solid #ddd; padding: 10px; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 9pt; }
        .badge-success { background-color: #28a745; color: white; }
        .badge-warning { background-color: #ffc107; color: black; }
        .badge-info { background-color: #17a2b8; color: white; }
        .badge-danger { background-color: #dc3545; color: white; }
        .badge-secondary { background-color: #6c757d; color: white; }
        .comentario { margin: 8px 0; padding: 8px; background-color: #f5f5f5; border-left: 3px solid #0d6efd; }
        .fecha { font-size: 9pt; color: #666; }
    </style>
</head>
<body>
    <h1>Reporte de Tickets</h1>
    <p>Generado el: ' . date('d/m/Y H:i') . '</p>
    <p>Usuario: ' . htmlspecialchars($_SESSION["username"]) . '</p>
    <hr>
';

if (empty($tickets)) {
    $html .= '<p style="color: red;">No se encontraron tickets con los filtros especificados.</p>';
} else {
    $html .= '<p><strong>Total de tickets: ' . count($tickets) . '</strong></p>';
    
    if ($tipo_reporte === "resumen") {
        // Reporte Resumido - Tabla
        $html .= '<table>
            <thead>
                <tr>
                    <th>Ticket</th>
                    <th>Título</th>
                    <th>Estado</th>
                    <th>Solicitante</th>
                    <th>Responsable</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($tickets as $ticket) {
            $responsable = "Sin asignar";
            if ($ticket["responsable"]) {
                $stmt = $conexion->prepare("SELECT username FROM users WHERE id = ?");
                $stmt->execute([$ticket["responsable"]]);
                $resp = $stmt->fetch(PDO::FETCH_ASSOC);
                $responsable = $resp["username"] ?? "Sin asignar";
            }
            
            $estado_class = match($ticket["estado"]) {
                'sin abrir' => 'secondary',
                'en conocimiento' => 'info',
                'en proceso' => 'warning',
                'ticket cerrado' => 'success',
                'pendiente de cierre' => 'danger',
                default => 'secondary'
            };
            
            $html .= '<tr>
                <td><strong>' . htmlspecialchars($ticket["ticket_number"]) . '</strong></td>
                <td>' . htmlspecialchars($ticket["titulo"]) . '</td>
                <td><span class="badge badge-' . $estado_class . '">' . htmlspecialchars($ticket["estado"]) . '</span></td>
                <td>' . htmlspecialchars($ticket["nombre_solicitante"]) . '</td>
                <td>' . htmlspecialchars($responsable) . '</td>
                <td class="fecha">' . formatearFechaHora($ticket["fecha_creacion"]) . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
    } else {
        // Reporte Completo
        foreach ($tickets as $ticket) {
            $responsable = "Sin asignar";
            if ($ticket["responsable"]) {
                $stmt = $conexion->prepare("SELECT username FROM users WHERE id = ?");
                $stmt->execute([$ticket["responsable"]]);
                $resp = $stmt->fetch(PDO::FETCH_ASSOC);
                $responsable = $resp["username"] ?? "Sin asignar";
            }
            
            $estado_class = match($ticket["estado"]) {
                'sin abrir' => 'secondary',
                'en conocimiento' => 'info',
                'en proceso' => 'warning',
                'ticket cerrado' => 'success',
                'pendiente de cierre' => 'danger',
                default => 'secondary'
            };
            
            $html .= '<div class="ticket-section">
                <h2>' . htmlspecialchars($ticket["ticket_number"]) . ': ' . htmlspecialchars($ticket["titulo"]) . '</h2>
                <p>
                    <strong>Estado:</strong> <span class="badge badge-' . $estado_class . '">' . htmlspecialchars($ticket["estado"]) . '</span><br>
                    <strong>Solicitante:</strong> ' . htmlspecialchars($ticket["nombre_solicitante"]) . '<br>
                    <strong>Responsable:</strong> ' . htmlspecialchars($responsable) . '<br>
                    <strong>Creado por:</strong> ' . htmlspecialchars($ticket["creador_nombre"]) . '<br>
                    <strong>Fecha de Creación:</strong> <span class="fecha">' . formatearFechaHora($ticket["fecha_creacion"]) . '</span><br>
                    <strong>Última Modificación:</strong> <span class="fecha">' . formatearFechaHora($ticket["fecha_ultima_modificacion"]) . '</span>
                </p>
                
                <h4>Descripción:</h4>
                <p style="background-color: #f9f9f9; padding: 8px; border-left: 3px solid #0d6efd;">
                    ' . nl2br(htmlspecialchars($ticket["descripcion"])) . '
                </p>';
            
            // Incluir comentarios
            if (!empty($comentarios_map[$ticket["id"]])) {
                $html .= '<h4>Comentarios:</h4>';
                foreach ($comentarios_map[$ticket["id"]] as $comentario) {
                    $html .= '<div class="comentario">
                        <strong>' . htmlspecialchars($comentario["username"]) . '</strong>
                        <span class="fecha">(' . formatearFechaHora($comentario["fecha"]) . ')</span><br>
                        ' . nl2br(htmlspecialchars($comentario["comentario"])) . '
                    </div>';
                }
            }
            
            $html .= '</div><br><page>';
        }
    }
}

$html .= '</body></html>';

// Convertir HTML a PDF usando una solución alternativa
// Para este caso, voy a generar HTML que el navegador puede imprimir como PDF

header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: inline; filename="reporte_tickets_' . date('Y-m-d_H-i-s') . '.html"');

// Agregar JavaScript para forzar apertura en nueva ventana
echo '<script>window.print();</script>';
echo $html;
?>
