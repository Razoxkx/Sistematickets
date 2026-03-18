<?php
session_start();
require_once 'includes/config.php';

header('Content-Type: application/json');

// Verificar si el usuario está autenticado y es admin
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    http_response_code(403);
    echo json_encode(['success' => false, 'mensaje' => 'No autorizado']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(400);
    echo json_encode(['success' => false, 'mensaje' => 'Método no permitido']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$dispositivo_id = $input['dispositivo_id'] ?? '';
$nombre = trim($input['nombre'] ?? '');
$descripcion = trim($input['descripcion'] ?? '');
$ip = trim($input['ip'] ?? '');

if (empty($dispositivo_id) || empty($nombre) || empty($ip)) {
    echo json_encode(['success' => false, 'mensaje' => 'Campos requeridos incompletos']);
    exit();
}

try {
    // Verificar que el dispositivo existe
    $stmt = $conexion->prepare("SELECT id FROM dispositivos_monitoreo WHERE id = ?");
    $stmt->execute([$dispositivo_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'mensaje' => 'Dispositivo no encontrado']);
        exit();
    }
    
    // Verificar si la nueva IP ya está siendo monitoreada (en otro dispositivo)
    $stmt = $conexion->prepare("
        SELECT id FROM dispositivos_monitoreo 
        WHERE ip = ? AND id != ?
    ");
    $stmt->execute([$ip, $dispositivo_id]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'mensaje' => 'La IP ' . htmlspecialchars($ip) . ' ya está siendo monitoreada']);
        exit();
    }
    
    // Actualizar el dispositivo
    $stmt = $conexion->prepare("
        UPDATE dispositivos_monitoreo 
        SET nombre = ?, descripcion = ?, ip = ?
        WHERE id = ?
    ");
    $stmt->execute([$nombre, $descripcion, $ip, $dispositivo_id]);
    
    echo json_encode(['success' => true, 'mensaje' => 'Dispositivo actualizado correctamente']);
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate') !== false) {
        echo json_encode(['success' => false, 'mensaje' => 'La IP ' . htmlspecialchars($ip) . ' ya está siendo monitoreada']);
    } else {
        echo json_encode(['success' => false, 'mensaje' => 'Error al actualizar dispositivo']);
    }
}
?>
