<?php
session_start();
require_once 'includes/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

// Verificar permisos
$permisos = ['tisupport', 'admin'];
if (!in_array($_SESSION["role"] ?? "viewer", $permisos)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit();
}

header('Content-Type: application/json');

$periodo = $_GET['periodo'] ?? 'todo';
$fecha_desde = $_GET['fecha_desde'] ?? null;
$fecha_hasta = $_GET['fecha_hasta'] ?? null;

// Función para obtener estadísticas de estados
function obtenerEstadisticasEstados($conexion, $periodo = 'todo', $fecha_desde = null, $fecha_hasta = null) {
    $whereClause = "";
    
    if ($periodo === 'personalizado' && $fecha_desde && $fecha_hasta) {
        $whereClause = "WHERE DATE(fecha) >= ? AND DATE(fecha) <= ?";
    } elseif ($periodo === 'semana') {
        $whereClause = "WHERE fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    } elseif ($periodo === 'mes') {
        $whereClause = "WHERE fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    } elseif ($periodo === 'año') {
        $whereClause = "WHERE fecha >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
    }
    
    try {
        if ($periodo === 'personalizado' && $fecha_desde && $fecha_hasta) {
            $stmt = $conexion->prepare("
                SELECT estado, COUNT(*) as cantidad 
                FROM tickets 
                $whereClause
                GROUP BY estado
                ORDER BY cantidad DESC
            ");
            $stmt->execute([$fecha_desde, $fecha_hasta]);
        } else {
            $stmt = $conexion->query("
                SELECT estado, COUNT(*) as cantidad 
                FROM tickets 
                $whereClause
                GROUP BY estado
                ORDER BY cantidad DESC
            ");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Función para obtener top 10 solicitantes
function obtenerTop10Solicitantes($conexion, $periodo = 'todo', $fecha_desde = null, $fecha_hasta = null) {
    $whereClause = "";
    
    if ($periodo === 'personalizado' && $fecha_desde && $fecha_hasta) {
        $whereClause = "WHERE DATE(fecha) >= ? AND DATE(fecha) <= ?";
    } elseif ($periodo === 'semana') {
        $whereClause = "WHERE fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    } elseif ($periodo === 'mes') {
        $whereClause = "WHERE fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    } elseif ($periodo === 'año') {
        $whereClause = "WHERE fecha >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
    }
    
    try {
        if ($periodo === 'personalizado' && $fecha_desde && $fecha_hasta) {
            $stmt = $conexion->prepare("
                SELECT nombre_solicitante, COUNT(*) as total_reportes 
                FROM tickets 
                $whereClause
                GROUP BY nombre_solicitante 
                ORDER BY total_reportes DESC 
                LIMIT 10
            ");
            $stmt->execute([$fecha_desde, $fecha_hasta]);
        } else {
            $stmt = $conexion->query("
                SELECT nombre_solicitante, COUNT(*) as total_reportes 
                FROM tickets 
                $whereClause
                GROUP BY nombre_solicitante 
                ORDER BY total_reportes DESC 
                LIMIT 10
            ");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

try {
    $datos_estados = obtenerEstadisticasEstados($conexion, $periodo, $fecha_desde, $fecha_hasta);
    $datos_solicitantes = obtenerTop10Solicitantes($conexion, $periodo, $fecha_desde, $fecha_hasta);
    
    echo json_encode([
        'success' => true,
        'estados' => $datos_estados,
        'solicitantes' => $datos_solicitantes
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
