<?php
session_start();
require_once 'includes/config.php';

// Validar que el usuario esté autenticado y sea admin
if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "admin") {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

// Leer JSON del cuerpo de la solicitud
$input = json_decode(file_get_contents('php://input'), true);
$password = $input['password'] ?? '';

if (empty($password)) {
    echo json_encode(['success' => false, 'error' => 'Contraseña requerida']);
    exit();
}

try {
    // Obtener la contraseña hasheada del usuario actual (admin)
    $stmt = $conexion->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION["user_id"]]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($usuario && password_verify($password, $usuario["password"])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Contraseña incorrecta']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error del servidor']);
}
?>
