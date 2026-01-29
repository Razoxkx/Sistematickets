<?php
session_start();
require_once 'includes/config.php';

// Solo admin y tisupport pueden acceder
$permisos = ['admin', 'tisupport'];
if (!in_array($_SESSION["role"] ?? "", $permisos)) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit();
}

// Validar que sea POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(400);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

$cuenta_id = $_POST["cuenta_id"] ?? "";
$admin_password = $_POST["admin_password"] ?? "";

if (empty($cuenta_id) || empty($admin_password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetros inválidos']);
    exit();
}

try {
    // Obtener la cuenta
    $stmt = $conexion->prepare("SELECT contraseña FROM cuentas_servicio WHERE id = ?");
    $stmt->execute([$cuenta_id]);
    $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cuenta) {
        http_response_code(404);
        echo json_encode(['error' => 'Cuenta no encontrada']);
        exit();
    }
    
    // Verificar contraseña del administrador
    $stmt = $conexion->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION["user_id"]]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        http_response_code(401);
        echo json_encode(['error' => 'Usuario no encontrado']);
        exit();
    }
    
    // Validar contraseña con password_verify
    if (!password_verify($admin_password, $usuario['password'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Contraseña de administrador incorrecta']);
        exit();
    }
    
    // Si llegamos aquí, la contraseña es válida
    echo json_encode(['contraseña' => $cuenta['contraseña']]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>
