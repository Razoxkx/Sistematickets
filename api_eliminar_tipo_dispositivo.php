<?php
session_start();
require_once 'includes/config.php';

header('Content-Type: application/json');

// Verificar autenticación
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

$tipo_id = $input['tipo_id'] ?? '';

if (empty($tipo_id)) {
    echo json_encode(['success' => false, 'mensaje' => 'ID de tipo requerido']);
    exit();
}

try {
    // Verificar que el tipo existe
    $stmt = $conexion->prepare("SELECT id FROM tipos_dispositivos WHERE id = ?");
    $stmt->execute([$tipo_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'mensaje' => 'Tipo de dispositivo no encontrado']);
        exit();
    }

    // Contar cuántos dispositivos usan este tipo
    $stmt = $conexion->prepare("SELECT COUNT(*) as cnt FROM dispositivos_monitoreo WHERE tipo_dispositivo_id = ?");
    $stmt->execute([$tipo_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $cant_dispositivos = $row['cnt'];

    // Eliminar tipo (los dispositivos quedarán con tipo_dispositivo_id = NULL gracias al ON DELETE SET NULL)
    $stmt = $conexion->prepare("DELETE FROM tipos_dispositivos WHERE id = ?");
    $stmt->execute([$tipo_id]);

    echo json_encode([
        'success' => true, 
        'mensaje' => 'Tipo de dispositivo eliminado exitosamente',
        'dispositivos_sin_clasificar' => $cant_dispositivos
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'mensaje' => 'Error: ' . $e->getMessage()]);
}
?>
