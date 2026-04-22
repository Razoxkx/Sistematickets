<?php
session_start();
require_once 'includes/config.php';

// Verificar permisos
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["role"] ?? "", ['admin', 'tisupport'])) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($q)) {
    echo json_encode(['contactos' => []]);
    exit();
}

try {
    $search_term = "%{$q}%";
    $stmt = $conexion->prepare("
        SELECT id, nombre_completo, username AS nombre_usuario, email AS correo, numero_telefono, dpto_division AS division_departamento, role
        FROM users
        WHERE (nombre_completo LIKE ? OR username LIKE ? OR email LIKE ? OR dpto_division LIKE ?)
        ORDER BY nombre_completo
        LIMIT 50
    ");
    $stmt->execute([$search_term, $search_term, $search_term, $search_term]);
    $contactos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['contactos' => $contactos]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
