<?php
session_start();
require_once 'includes/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION["user_id"])) {
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

// Validar que sea POST y multipart
if ($_SERVER["REQUEST_METHOD"] !== "POST" || empty($_FILES["foto"])) {
    echo json_encode(['error' => 'No se envió archivo']);
    exit();
}

$foto = $_FILES["foto"];

// Validaciones
$extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif'];
$tamaño_maximo = 5 * 1024 * 1024; // 5MB

// Validar extensión
$info = pathinfo($foto["name"]);
$extension = strtolower($info["extension"]);

if (!in_array($extension, $extensiones_permitidas)) {
    echo json_encode(['error' => 'Solo se permiten JPG, PNG y GIF']);
    exit();
}

// Validar tamaño
if ($foto["size"] > $tamaño_maximo) {
    echo json_encode(['error' => 'La imagen no puede superar 5MB']);
    exit();
}

// Validar que sea imagen
$mime_permitidos = ['image/jpeg', 'image/png', 'image/gif'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $foto["tmp_name"]);
finfo_close($finfo);

if (!in_array($mime, $mime_permitidos)) {
    echo json_encode(['error' => 'El archivo no es una imagen válida']);
    exit();
}

try {
    // Crear directorio si no existe
    $directorio = __DIR__ . '/uploads/perfil';
    if (!is_dir($directorio)) {
        mkdir($directorio, 0755, true);
    }

    // Generar nombre único
    $nombre_archivo = $_SESSION["user_id"] . "_" . time() . "." . $extension;
    $ruta_archivo = $directorio . "/" . $nombre_archivo;
    $ruta_relativa = "uploads/perfil/" . $nombre_archivo;

    // Mover archivo
    if (!move_uploaded_file($foto["tmp_name"], $ruta_archivo)) {
        echo json_encode(['error' => 'Error al guardar el archivo']);
        exit();
    }

    // Eliminar foto anterior si existe
    $stmt = $conexion->prepare("SELECT foto_perfil FROM users WHERE id = ?");
    $stmt->execute([$_SESSION["user_id"]]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && $usuario["foto_perfil"]) {
        $foto_anterior = __DIR__ . "/" . $usuario["foto_perfil"];
        if (file_exists($foto_anterior)) {
            unlink($foto_anterior);
        }
    }

    // Guardar en BD (guardar ruta completa, no solo nombre)
    $stmt = $conexion->prepare("UPDATE users SET foto_perfil = ? WHERE id = ?");
    $stmt->execute([$ruta_relativa, $_SESSION["user_id"]]);

    echo json_encode([
        'success' => true,
        'foto' => $ruta_relativa
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Error en la base de datos: ' . $e->getMessage()]);
}
?>
