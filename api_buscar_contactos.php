<?php
session_start();
require_once 'includes/config.php';

// Verificar si el usuario está logueado y es admin
if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "admin") {
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$busqueda = isset($_GET["q"]) ? trim($_GET["q"]) : "";

if (empty($busqueda)) {
    echo json_encode(['contactos' => []]);
    exit();
}

try {
    $stmt = $conexion->prepare("
        SELECT * FROM contactos 
        WHERE nombre_completo LIKE ? OR nombre_usuario LIKE ? OR correo LIKE ? OR numero_telefono LIKE ? OR division_departamento LIKE ?
        ORDER BY nombre_completo
    ");
    $search_term = "%{$busqueda}%";
    $stmt->execute([$search_term, $search_term, $search_term, $search_term, $search_term]);
    $contactos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['contactos' => $contactos]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
