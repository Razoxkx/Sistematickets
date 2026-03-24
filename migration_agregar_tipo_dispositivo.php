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
    // Agregar columna tipo_dispositivo_id a dispositivos_monitoreo
    $sql = "ALTER TABLE dispositivos_monitoreo ADD COLUMN tipo_dispositivo_id INT DEFAULT NULL AFTER descripcion";
    
    try {
        $conexion->exec($sql);
        echo "✅ Columna 'tipo_dispositivo_id' agregada correctamente a 'dispositivos_monitoreo'.";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "ℹ️ La columna 'tipo_dispositivo_id' ya existe en 'dispositivos_monitoreo'.";
        } else {
            throw $e;
        }
    }
    
    // Agregar FOREIGN KEY
    $sql = "ALTER TABLE dispositivos_monitoreo ADD CONSTRAINT fk_tipo_dispositivo FOREIGN KEY (tipo_dispositivo_id) REFERENCES tipos_dispositivos(id) ON DELETE SET NULL";
    
    try {
        $conexion->exec($sql);
        echo "<br>✅ Foreign Key agregada correctamente.";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "<br>ℹ️ El Foreign Key ya existe.";
        } else {
            throw $e;
        }
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
