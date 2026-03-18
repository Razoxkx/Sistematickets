<?php
session_start();
require_once 'includes/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

// Verificar si es admin
if ($_SESSION["role"] !== "admin") {
    header("Location: tickets.php");
    exit();
}

try {
    // Crear tabla de dispositivos de monitoreo
    $sql = "CREATE TABLE IF NOT EXISTS dispositivos_monitoreo (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(15) NOT NULL UNIQUE,
        nombre VARCHAR(255) NOT NULL,
        descripcion TEXT,
        estado VARCHAR(50) DEFAULT 'desconocido',
        fecha_ultima_verificacion TIMESTAMP NULL,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        usuario_creador INT NOT NULL,
        activo TINYINT DEFAULT 1,
        FOREIGN KEY (usuario_creador) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $conexion->exec($sql);
    echo "✅ Tabla 'dispositivos_monitoreo' creada correctamente.";
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "ℹ️ La tabla 'dispositivos_monitoreo' ya existe.";
    } else {
        echo "❌ Error: " . $e->getMessage();
    }
}
?>
