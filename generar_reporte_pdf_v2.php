<?php
session_start();
require_once 'includes/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    exit('No autorizado');
}

// Verificar permisos
$permisos = ['tisupport', 'admin'];
if (!in_array($_SESSION["role"] ?? "viewer", $permisos)) {
    http_response_code(403);
    exit('Acceso denegado');
}

// Obtener parámetros
$periodo = $_GET['periodo'] ?? 'mes';
$fecha_desde = $_GET['fecha_desde'] ?? null;
$fecha_hasta = $_GET['fecha_hasta'] ?? null;
$solicitante = $_GET['solicitante'] ?? null;
$responsable = $_GET['responsable'] ?? null;

// Función para construir WHERE clause
function construirWhere($periodo, $fecha_desde, $fecha_hasta, $solicitante, $responsable) {
    $where = "WHERE t.ticket_padre_id IS NULL";
    $params = [];
    
    if ($periodo === 'semana') {
        $where .= " AND t.fecha_creacion >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    } elseif ($periodo === 'mes') {
        $where .= " AND t.fecha_creacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    } elseif ($periodo === 'año') {
        $where .= " AND t.fecha_creacion >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
    } elseif ($periodo === 'custom' && $fecha_desde && $fecha_hasta) {
        $where .= " AND DATE(t.fecha_creacion) >= ? AND DATE(t.fecha_creacion) <= ?";
        $params[] = $fecha_desde;
        $params[] = $fecha_hasta;
    }
    
    if (!empty($solicitante)) {
        $where .= " AND t.nombre_solicitante = ?";
        $params[] = $solicitante;
    }
    
    if (!empty($responsable)) {
        $where .= " AND u.username = ?";
        $params[] = $responsable;
    }
    
    return [$where, $params];
}

// Obtener datos
try {
    list($where, $params) = construirWhere($periodo, $fecha_desde, $fecha_hasta, $solicitante, $responsable);
    
    // Estadísticas de estados
    $sql = "SELECT t.estado, COUNT(*) as cantidad FROM tickets t LEFT JOIN users u ON t.responsable = u.id $where GROUP BY t.estado ORDER BY cantidad DESC";
    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $estadisticas_estados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top 5 solicitantes
    $sql = "SELECT t.nombre_solicitante, COUNT(*) as total FROM tickets t LEFT JOIN users u ON t.responsable = u.id $where GROUP BY t.nombre_solicitante ORDER BY total DESC LIMIT 5";
    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $top_solicitantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estadísticas generales
    $sql = "SELECT COUNT(*) as total, SUM(CASE WHEN t.es_cerrado = 1 THEN 1 ELSE 0 END) as cerrados, SUM(CASE WHEN t.es_cerrado = 0 THEN 1 ELSE 0 END) as abiertos FROM tickets t LEFT JOIN users u ON t.responsable = u.id $where";
    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $estadisticas_generales = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    http_response_code(500);
    exit('Error: ' . $e->getMessage());
}

// Generar HTML para PDF
$fecha_actual = date('d/m/Y H:i');
$titulo_periodo = 'Período desconocido';

if ($periodo === 'semana') {
    $titulo_periodo = 'Última Semana';
} elseif ($periodo === 'mes') {
    $titulo_periodo = 'Último Mes';
} elseif ($periodo === 'año') {
    $titulo_periodo = 'Último Año';
} elseif ($periodo === 'custom') {
    $titulo_periodo = "Del " . $fecha_desde . " al " . $fecha_hasta;
}

$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; color: #333; }
        .container { width: 100%; max-width: 800px; margin: 0 auto; }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .header h1 { font-size: 28px; margin-bottom: 10px; }
        .header p { font-size: 12px; opacity: 0.9; }
        .stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            gap: 10px;
        }
        .stat-box {
            flex: 1;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
        }
        .stat-box h3 { font-size: 32px; color: #667eea; margin-bottom: 5px; }
        .stat-box p { font-size: 12px; color: #666; }
        .section { margin-bottom: 30px; }
        .section h2 {
            font-size: 18px;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #667eea; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .chart-section { 
            background: #f8f9fa; 
            padding: 20px; 
            border-radius: 8px; 
            margin-bottom: 20px;
        }
        .badge { 
            display: inline-block; 
            padding: 5px 10px; 
            background: #667eea; 
            color: white; 
            border-radius: 4px; 
            font-size: 12px;
        }
        .footer {
            text-align: center;
            font-size: 11px;
            color: #999;
            margin-top: 40px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Reporte de Tickets</h1>
            <p>$titulo_periodo | Generado: $fecha_actual</p>
        </div>
        
        <div class="stats">
            <div class="stat-box">
                <h3>{$estadisticas_generales['total']}</h3>
                <p>Total de Tickets</p>
            </div>
            <div class="stat-box">
                <h3>{$estadisticas_generales['cerrados']}</h3>
                <p>Tickets Cerrados</p>
            </div>
            <div class="stat-box">
                <h3>{$estadisticas_generales['abiertos']}</h3>
                <p>Tickets Abiertos</p>
            </div>
        </div>
        
        <div class="section">
            <h2>Estado de Tickets</h2>
            <table>
                <thead>
                    <tr>
                        <th>Estado</th>
                        <th>Cantidad</th>
                        <th>Porcentaje</th>
                    </tr>
                </thead>
                <tbody>
HTML;

$total = $estadisticas_generales['total'] ?? 1;
foreach ($estadisticas_estados as $estado) {
    $porcentaje = ($total > 0) ? number_format(($estado['cantidad'] / $total) * 100, 1) : 0;
    $html .= <<<HTML
                    <tr>
                        <td>{$estado['estado']}</td>
                        <td><span class="badge">{$estado['cantidad']}</span></td>
                        <td>{$porcentaje}%</td>
                    </tr>
HTML;
}

$html .= <<<HTML
                </tbody>
            </table>
        </div>
        
        <div class="section">
            <h2>Top 5 Solicitantes</h2>
            <table>
                <thead>
                    <tr>
                        <th>Solicitante</th>
                        <th>Cantidad de Tickets</th>
                    </tr>
                </thead>
                <tbody>
HTML;

foreach ($top_solicitantes as $idx => $sol) {
    $numero = $idx + 1;
    $nombre = $sol['nombre_solicitante'];
    $total = $sol['total'];
    $html .= <<<HTML
                    <tr>
                        <td>$numero. $nombre</td>
                        <td><span class="badge">$total</span></td>
                    </tr>
HTML;
}

$html .= <<<HTML
                </tbody>
            </table>
        </div>
        
        <div class="footer">
            <p>Este reporte fue generado automáticamente desde el Sistema de Tickets.</p>
            <p>Para más información, contacte al equipo de administración.</p>
        </div>
    </div>
</body>
</html>
HTML;

// Enviar como PDF
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="reporte_tickets_' . date('Ymd_His') . '.pdf"');

// Convertir HTML a PDF usando técnica simple (output como HTML)
// Para producción, usar bibliotecas como TCPDF o Dompdf
// Por ahora usamos mPDF o similar. Aquí usamos una solución simple:

// Usar wkhtmltopdf o similar si está disponible
$temp_html = tempnam(sys_get_temp_dir(), 'pdf');
file_put_contents($temp_html, $html);

// Intentar usar wkhtmltopdf si está disponible
$output = shell_exec("which wkhtmltopdf 2>/dev/null");
if ($output) {
    $pdf_file = tempnam(sys_get_temp_dir(), 'pdf') . '.pdf';
    shell_exec("wkhtmltopdf $temp_html $pdf_file");
    if (file_exists($pdf_file)) {
        readfile($pdf_file);
        unlink($pdf_file);
        unlink($temp_html);
        exit();
    }
}

// Si no está disponible wkhtmltopdf, enviar como HTML que se puede imprimir como PDF
header('Content-Type: text/html; charset=utf-8');
echo $html;
unlink($temp_html);
?>
