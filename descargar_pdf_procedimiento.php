<?php
session_start();
require_once 'includes/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

// Verificar permisos
$permisos = ['tisupport', 'admin', 'viewer'];
if (!in_array($_SESSION["role"] ?? "viewer", $permisos)) {
    header("Location: tickets.php");
    exit();
}

$procedimiento_id = $_GET["id"] ?? "";

if (empty($procedimiento_id)) {
    http_response_code(404);
    die("Procedimiento no encontrado");
}

try {
    // Obtener información del PDF del procedimiento
    $stmt = $conexion->prepare("
        SELECT id, titulo, archivo_pdf
        FROM procedimientos
        WHERE id = ?
    ");
    $stmt->execute([$procedimiento_id]);
    $procedimiento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$procedimiento || empty($procedimiento["archivo_pdf"])) {
        http_response_code(404);
        die("No hay archivo PDF asociado con este procedimiento");
    }
    
    $archivo_path = 'uploads/procedimientos/' . $procedimiento["archivo_pdf"];
    
    // Validar que el archivo existe y está dentro del directorio permitido
    if (!file_exists($archivo_path) || !is_file($archivo_path)) {
        http_response_code(404);
        die("El archivo no existe");
    }
    
    // Validar que la ruta está dentro del directorio de procedimientos
    $real_path = realpath($archivo_path);
    $base_path = realpath('uploads/procedimientos');
    
    if (strpos($real_path, $base_path) !== 0) {
        http_response_code(403);
        die("Acceso denegado");
    }
    
    // Descargar el archivo
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($procedimiento["archivo_pdf"]) . '"');
    header('Content-Length: ' . filesize($archivo_path));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    
    readfile($archivo_path);
    exit();
    
} catch (Exception $e) {
    http_response_code(500);
    die("Error al descargar el archivo: " . $e->getMessage());
}
?>
