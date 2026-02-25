<?php
session_start();
require_once 'includes/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION["user_id"])) {
    http_response_code(403);
    exit("Acceso denegado");
}

$procedimiento_id = $_GET["id"] ?? null;

if (!$procedimiento_id || !is_numeric($procedimiento_id)) {
    http_response_code(400);
    exit("ID de procedimiento inválido");
}

try {
    $stmt = $conexion->prepare("SELECT archivo_pdf, titulo FROM procedimientos WHERE id = ?");
    $stmt->execute([$procedimiento_id]);
    $procedimiento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$procedimiento || empty($procedimiento["archivo_pdf"])) {
        http_response_code(404);
        exit("Archivo no encontrado");
    }

    $archivo_ruta = __DIR__ . "/uploads/procedimientos/" . $procedimiento["archivo_pdf"];

    // Validar que el archivo existe y está dentro del directorio permitido
    if (!file_exists($archivo_ruta) || strpos(realpath($archivo_ruta), realpath(__DIR__ . "/uploads/procedimientos/")) !== 0) {
        http_response_code(404);
        exit("Archivo no encontrado");
    }

    // Obtener nombre limpio del archivo (sin la parte del uniqid)
    $filename = preg_replace('/^proc_[a-f0-9]+_/', '', $procedimiento["archivo_pdf"]);
    
    // Determinar si es descarga o visualización
    $ver = $_GET["ver"] ?? 0;

    if ($ver == 1) {
        // Abrir en navegador
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename*=UTF-8\'\'' . rawurlencode($filename));
    } else {
        // Descargar
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode($filename));
    }

    header('Content-Length: ' . filesize($archivo_ruta));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    readfile($archivo_ruta);

} catch (PDOException $e) {
    http_response_code(500);
    exit("Error en la base de datos");
}
?>
