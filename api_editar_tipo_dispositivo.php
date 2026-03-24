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
$nombre = trim($input['nombre'] ?? '');
$color = trim($input['color'] ?? '');
$icono = trim($input['icono'] ?? '');

if (empty($tipo_id) || empty($nombre) || empty($color) || empty($icono)) {
    echo json_encode(['success' => false, 'mensaje' => 'Todos los campos son obligatorios']);
    exit();
}

// Validar formato de color
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
    echo json_encode(['success' => false, 'mensaje' => 'Color inválido (debe ser formato hex como #007bff)']);
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

    // Verificar que no existe otro tipo con ese nombre
    $stmt = $conexion->prepare("SELECT id FROM tipos_dispositivos WHERE nombre = ? AND id != ?");
    $stmt->execute([$nombre, $tipo_id]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'mensaje' => 'Ya existe otro tipo de dispositivo con ese nombre']);
        exit();
    }

    // Actualizar tipo
    $stmt = $conexion->prepare("
        UPDATE tipos_dispositivos 
        SET nombre = ?, color = ?, icono = ?
        WHERE id = ?
    ");
    $stmt->execute([$nombre, $color, $icono, $tipo_id]);

    echo json_encode(['success' => true, 'mensaje' => 'Tipo de dispositivo actualizado exitosamente']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'mensaje' => 'Error: ' . $e->getMessage()]);
}
?>
