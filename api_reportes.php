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

$accion = $_GET['accion'] ?? 'todos';
$estado = $_GET['estado'] ?? '';
$solicitante = $_GET['solicitante'] ?? '';
$responsable = $_GET['responsable'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

/**
 * Construir WHERE clause dinámicamente según filtros
 */
function construirWhereConFiltros($estado, $solicitante, $responsable_username, $fecha_desde, $fecha_hasta) {
    $where = "WHERE t.ticket_padre_id IS NULL";
    $params = [];
    
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
    
    return [$where, $params];
}

try {
    
    // Endpoint: Mes actual
    if ($accion === 'mes_actual') {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN t.es_cerrado = 1 THEN 1 ELSE 0 END) as cerrados,
                    SUM(CASE WHEN t.es_cerrado = 0 AND t.estado != 'ticket cerrado' THEN 1 ELSE 0 END) as abiertos,
                    SUM(CASE WHEN t.estado = 'en proceso' THEN 1 ELSE 0 END) as en_proceso
                FROM tickets t
                WHERE YEAR(t.fecha_creacion) = YEAR(NOW())
                AND MONTH(t.fecha_creacion) = MONTH(NOW())
                AND t.ticket_padre_id IS NULL";
        
        $stmt = $conexion->prepare($sql);
        $stmt->execute();
        $datos = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'total' => (int)$datos['total'],
            'cerrados' => (int)$datos['cerrados'] ?? 0,
            'abiertos' => (int)$datos['abiertos'] ?? 0,
            'en_proceso' => (int)$datos['en_proceso'] ?? 0
        ]);
    }
    
    // Endpoint: Últimos 7 días
    elseif ($accion === 'ultimos_7_dias') {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN t.es_cerrado = 1 THEN 1 ELSE 0 END) as cerrados,
                    SUM(CASE WHEN t.es_cerrado = 0 AND t.estado != 'ticket cerrado' THEN 1 ELSE 0 END) as abiertos,
                    SUM(CASE WHEN t.estado = 'en proceso' THEN 1 ELSE 0 END) as en_proceso
                FROM tickets t
                WHERE t.fecha_creacion >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                AND t.ticket_padre_id IS NULL";
        
        $stmt = $conexion->prepare($sql);
        $stmt->execute();
        $datos = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'total' => (int)$datos['total'],
            'cerrados' => (int)$datos['cerrados'] ?? 0,
            'abiertos' => (int)$datos['abiertos'] ?? 0,
            'en_proceso' => (int)$datos['en_proceso'] ?? 0
        ]);
    }
    
    // Endpoint: Filtros personalizados
    elseif ($accion === 'filtros') {
        list($where, $params) = construirWhereConFiltros($estado, $solicitante, $responsable, $fecha_desde, $fecha_hasta);
        
        // Obtener estados - siempre LEFT JOIN con users para compatibilidad
        $sql = "SELECT t.estado, COUNT(*) as cantidad FROM tickets t LEFT JOIN users u ON t.responsable = u.id $where GROUP BY t.estado ORDER BY cantidad DESC";
        $stmt = $conexion->prepare($sql);
        $stmt->execute($params);
        $estados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener top 5 solicitantes
        $sql = "SELECT t.nombre_solicitante, COUNT(*) as total FROM tickets t LEFT JOIN users u ON t.responsable = u.id $where GROUP BY t.nombre_solicitante ORDER BY total DESC LIMIT 5";
        $stmt = $conexion->prepare($sql);
        $stmt->execute($params);
        $top5 = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'estados' => $estados,
            'top5' => $top5
        ]);
    }
    
    // Endpoint por defecto: Todos
    else {
        // Obtener todos los datos sin filtros
        $sql = "SELECT t.estado, COUNT(*) as cantidad FROM tickets t 
                WHERE t.ticket_padre_id IS NULL
                GROUP BY t.estado ORDER BY cantidad DESC";
        $stmt = $conexion->prepare($sql);
        $stmt->execute();
        $estados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $sql = "SELECT t.nombre_solicitante, COUNT(*) as total FROM tickets t 
                WHERE t.ticket_padre_id IS NULL
                GROUP BY t.nombre_solicitante ORDER BY total DESC LIMIT 5";
        $stmt = $conexion->prepare($sql);
        $stmt->execute();
        $top5 = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'estados' => $estados,
            'top5' => $top5
        ]);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>
