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

$tipo = $_GET['tipo'] ?? 'mes_actual';
$estado = $_GET['estado'] ?? '';
$solicitante = $_GET['solicitante'] ?? '';
$responsable = $_GET['responsable'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

// Función para formatear fechas en español
function formatearFechaEs($fecha) {
    $meses = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];
    $date = new DateTime($fecha);
    $dia = (int)$date->format('d');
    $mes = $meses[(int)$date->format('m') - 1];
    $año = $date->format('Y');
    $hora = $date->format('H:i');
    return strtoupper("$mes $dia $año - $hora");
}

// Función para obtener datos según el tipo
function obtenerDatos($conexion, $tipo, $estado, $solicitante, $responsable_username, $fecha_desde, $fecha_hasta) {
    
    // Construir WHERE dinámicamente
    $where = "WHERE t.ticket_padre_id IS NULL";
    $params = [];
    
    if ($tipo === 'mes_actual') {
        $where .= " AND YEAR(t.fecha_creacion) = YEAR(NOW()) AND MONTH(t.fecha_creacion) = MONTH(NOW())";
    } elseif ($tipo === 'ultimos_7_dias') {
        $where .= " AND t.fecha_creacion >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    } elseif ($tipo === 'personalizado') {
        if (!empty($estado)) {
            $where .= " AND t.estado = ?";
            $params[] = $estado;
        }
        if (!empty($solicitante)) {
            $where .= " AND t.nombre_solicitante = ?";
            $params[] = $solicitante;
        }
        if (!empty($responsable_username)) {
            $where .= " AND u.username = ?";
            $params[] = $responsable_username;
        }
        if (!empty($fecha_desde)) {
            $where .= " AND DATE(t.fecha_creacion) >= ?";
            $params[] = $fecha_desde;
        }
        if (!empty($fecha_hasta)) {
            $where .= " AND DATE(t.fecha_creacion) <= ?";
            $params[] = $fecha_hasta;
        }
    } else {
        // Por defecto: todos
        // Sin filtros adicionales
    }
    
    // Estadísticas de estados
    $sql = "SELECT t.estado, COUNT(*) as cantidad FROM tickets t $where GROUP BY t.estado ORDER BY cantidad DESC";
    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $estados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top 5 solicitantes
    $sql = "SELECT t.nombre_solicitante, COUNT(*) as total FROM tickets t $where GROUP BY t.nombre_solicitante ORDER BY total DESC LIMIT 5";
    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $top5_solicitantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ranking técnicos
    $sql = "SELECT u.username as responsable, COUNT(*) as total FROM tickets t LEFT JOIN users u ON t.responsable = u.id $where GROUP BY u.username ORDER BY total DESC LIMIT 10";
    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $ranking_tecnicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estadísticas generales
    $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN t.es_cerrado = 1 THEN 1 ELSE 0 END) as cerrados,
                SUM(CASE WHEN t.es_cerrado = 0 AND t.estado != 'ticket cerrado' THEN 1 ELSE 0 END) as abiertos,
                SUM(CASE WHEN t.estado = 'en proceso' THEN 1 ELSE 0 END) as en_proceso
            FROM tickets t $where";
    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'estados' => $estados,
        'top5_solicitantes' => $top5_solicitantes,
        'ranking_tecnicos' => $ranking_tecnicos,
        'stats' => $stats
    ];
}

try {
    $datos = obtenerDatos($conexion, $tipo, $estado, $solicitante, $responsable, $fecha_desde, $fecha_hasta);
    
    // Determinar título según tipo
    $titulo_reporte = '';
    if ($tipo === 'mes_actual') {
        $titulo_reporte = 'Reporte Mes Actual';
    } elseif ($tipo === 'ultimos_7_dias') {
        $titulo_reporte = 'Reporte Últimos 7 Días';
    } else {
        $titulo_reporte = 'Reporte Personalizado';
    }
    
    // Generar HTML para imprimir
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $titulo_reporte; ?></title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Arial', sans-serif;
                color: #333;
                line-height: 1.6;
            }
            
            .page {
                width: 210mm;
                height: 297mm;
                margin: 20px auto;
                padding: 20px;
                background: white;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
            }
            
            header {
                text-align: center;
                border-bottom: 3px solid #667eea;
                padding-bottom: 20px;
                margin-bottom: 30px;
            }
            
            h1 {
                color: #667eea;
                font-size: 28px;
                margin-bottom: 5px;
            }
            
            .fecha-generacion {
                color: #666;
                font-size: 12px;
                margin-top: 10px;
            }
            
            h2 {
                color: #667eea;
                font-size: 18px;
                margin-top: 30px;
                margin-bottom: 15px;
                padding-bottom: 5px;
                border-bottom: 2px solid #8b9dff;
            }
            
            .stats-container {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 15px;
                margin-bottom: 30px;
            }
            
            .stat-box {
                padding: 15px;
                border-radius: 5px;
                color: white;
                text-align: center;
            }
            
            .stat-total {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }
            
            .stat-cerrados {
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            }
            
            .stat-abiertos {
                background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            }
            
            .stat-proceso {
                background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            }
            
            .stat-number {
                font-size: 24px;
                font-weight: bold;
                margin-bottom: 5px;
            }
            
            .stat-label {
                font-size: 11px;
                opacity: 0.9;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            
            th {
                background-color: #667eea;
                color: white;
                padding: 10px;
                text-align: left;
                font-weight: 600;
            }
            
            td {
                padding: 8px 10px;
                border-bottom: 1px solid #ddd;
            }
            
            tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            
            tr:hover {
                background-color: #f0f0f0;
            }
            
            .badge {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: bold;
                color: white;
            }
            
            .badge-primary {
                background-color: #667eea;
            }
            
            .badge-success {
                background-color: #28a745;
            }
            
            .badge-warning {
                background-color: #ffc107;
                color: #333;
            }
            
            .badge-danger {
                background-color: #dc3545;
            }
            
            .badge-info {
                background-color: #17a2b8;
            }
            
            .progress-bar-container {
                width: 100%;
                height: 20px;
                background-color: #eee;
                border-radius: 3px;
                overflow: hidden;
            }
            
            .progress-bar {
                height: 100%;
                background-color: #667eea;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 10px;
                font-weight: bold;
            }
            
            .page-break {
                page-break-after: always;
            }
            
            .footer {
                text-align: center;
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #ddd;
                font-size: 11px;
                color: #666;
            }
            
            @media print {
                body {
                    margin: 0;
                    padding: 0;
                }
                .page {
                    width: 100%;
                    height: auto;
                    margin: 0;
                    padding: 0;
                    box-shadow: none;
                }
            }
        </style>
    </head>
    <body>
        <div class="page">
            <header>
                <h1>📊 <?php echo $titulo_reporte; ?></h1>
                <p class="fecha-generacion">Generado: <?php echo formatearFechaEs(date('Y-m-d H:i:s')); ?></p>
            </header>
            
            <!-- ESTADÍSTICAS GENERALES -->
            <h2>Estadísticas Generales</h2>
            <div class="stats-container">
                <div class="stat-box stat-total">
                    <div class="stat-number"><?php echo $datos['stats']['total']; ?></div>
                    <div class="stat-label">Total Tickets</div>
                </div>
                <div class="stat-box stat-cerrados">
                    <div class="stat-number"><?php echo $datos['stats']['cerrados']; ?></div>
                    <div class="stat-label">Cerrados</div>
                </div>
                <div class="stat-box stat-abiertos">
                    <div class="stat-number"><?php echo $datos['stats']['abiertos']; ?></div>
                    <div class="stat-label">Abiertos</div>
                </div>
                <div class="stat-box stat-proceso">
                    <div class="stat-number"><?php echo $datos['stats']['en_proceso']; ?></div>
                    <div class="stat-label">En Proceso</div>
                </div>
            </div>
            
            <!-- ESTADOS DE TICKETS -->
            <h2>Distribución por Estado</h2>
            <table>
                <thead>
                    <tr>
                        <th>Estado</th>
                        <th>Cantidad</th>
                        <th>Porcentaje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total_estados = array_sum(array_column($datos['estados'], 'cantidad'));
                    foreach ($datos['estados'] as $est):
                        $porcentaje = $total_estados > 0 ? round(($est['cantidad'] / $total_estados) * 100, 1) : 0;
                    ?>
                        <tr>
                            <td><?php echo $est['estado']; ?></td>
                            <td><span class="badge badge-primary"><?php echo $est['cantidad']; ?></span></td>
                            <td>
                                <div class="progress-bar-container">
                                    <div class="progress-bar" style="width: <?php echo $porcentaje; ?>%;">
                                        <?php echo $porcentaje; ?>%
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- TOP 5 SOLICITANTES -->
            <h2>Top 5 Solicitantes</h2>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Solicitante</th>
                        <th>Tickets</th>
                        <th>Porcentaje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total_sol = array_sum(array_column($datos['top5_solicitantes'], 'total'));
                    foreach ($datos['top5_solicitantes'] as $i => $sol):
                        $porcentaje = $total_sol > 0 ? round(($sol['total'] / $total_sol) * 100, 1) : 0;
                    ?>
                        <tr>
                            <td><span class="badge badge-primary"><?php echo $i + 1; ?></span></td>
                            <td><?php echo htmlspecialchars($sol['nombre_solicitante']); ?></td>
                            <td><span class="badge badge-success"><?php echo $sol['total']; ?></span></td>
                            <td>
                                <div class="progress-bar-container">
                                    <div class="progress-bar" style="width: <?php echo $porcentaje; ?>%;">
                                        <?php echo $porcentaje; ?>%
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- RANKING DE TÉCNICOS -->
            <h2>Ranking de Técnicos (Top 10)</h2>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Técnico</th>
                        <th>Tickets Asignados</th>
                        <th>Porcentaje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total_tech = array_sum(array_column($datos['ranking_tecnicos'], 'total'));
                    foreach ($datos['ranking_tecnicos'] as $i => $tech):
                        $porcentaje = $total_tech > 0 ? round(($tech['total'] / $total_tech) * 100, 1) : 0;
                    ?>
                        <tr>
                            <td><span class="badge badge-primary"><?php echo $i + 1; ?></span></td>
                            <td><?php echo htmlspecialchars($tech['responsable'] ?? 'Sin asignar'); ?></td>
                            <td><span class="badge badge-warning"><?php echo $tech['total']; ?></span></td>
                            <td>
                                <div class="progress-bar-container">
                                    <div class="progress-bar" style="width: <?php echo $porcentaje; ?>%;">
                                        <?php echo $porcentaje; ?>%
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="footer">
                <p>Reporte generado por Sistema de Tickets • <?php echo formatearFechaEs(date('Y-m-d H:i:s')); ?></p>
            </div>
        </div>
        
        <script>
            // Abrir diálogo de impresión automáticamente
            window.addEventListener('load', function() {
                window.print();
            });
        </script>
    </body>
    </html>
    <?php
    
} catch (Exception $e) {
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Error</title>
    </head>
    <body>
        <h1>Error en la generación del reporte</h1>
        <p><?php echo htmlspecialchars($e->getMessage()); ?></p>
        <a href="reportes.php">Volver a reportes</a>
    </body>
    </html>
    <?php
}
?>