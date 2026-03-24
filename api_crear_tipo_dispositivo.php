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

$nombre = trim($input['nombre'] ?? '');
$color = trim($input['color'] ?? '');
$icono = trim($input['icono'] ?? '');

if (empty($nombre) || empty($color) || empty($icono)) {
    echo json_encode(['success' => false, 'mensaje' => 'Todos los campos son obligatorios']);
    exit();
}

// Validar formato de color
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
    echo json_encode(['success' => false, 'mensaje' => 'Color inválido (debe ser formato hex como #007bff)']);
    exit();
}

try {
    // Verificar que no existe un tipo con ese nombre
    $stmt = $conexion->prepare("SELECT id FROM tipos_dispositivos WHERE nombre = ?");
    $stmt->execute([$nombre]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'mensaje' => 'Ya existe un tipo de dispositivo con ese nombre']);
        exit();
    }

    // Crear tipo
    $stmt = $conexion->prepare("
        INSERT INTO tipos_dispositivos (nombre, color, icono)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$nombre, $color, $icono]);

    echo json_encode(['success' => true, 'mensaje' => 'Tipo de dispositivo creado exitosamente']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'mensaje' => 'Error: ' . $e->getMessage()]);
}
?>
