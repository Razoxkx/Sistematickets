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

try {
    $stmt = $conexion->prepare("SELECT id, nombre, color, icono FROM tipos_dispositivos ORDER BY nombre ASC");
    $stmt->execute();
    $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'tipos' => $tipos
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'mensaje' => 'Error: ' . $e->getMessage()]);
}
?>
