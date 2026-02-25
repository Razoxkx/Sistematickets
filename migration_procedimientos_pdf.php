<?php
date_default_timezone_set('America/Bogota');
require_once 'includes/config.php';

try {
    // Verificar si la columna ya existe
    $stmt = $conexion->prepare("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'procedimientos' 
        AND COLUMN_NAME = 'archivo_pdf'
    ");
    $stmt->execute();
    $columnExists = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$columnExists) {
        // Agregar columna archivo_pdf a procedimientos
        $conexion->exec("
            ALTER TABLE procedimientos 
            ADD COLUMN archivo_pdf VARCHAR(255) NULL DEFAULT NULL
            AFTER cuerpo
        ");
        
        echo "✅ Columna 'archivo_pdf' agreg ala tabla 'procedimientos' correctamente.<br>";
    } else {
        echo "ℹ️ La columna 'archivo_pdf' ya existe en la tabla 'procedimientos'.<br>";
    }
    
    echo "<br><a href='procedimientos.php'>Volver a procedimientos</a>";
    
} catch (PDOException $e) {
    echo "❌ Error en la migración: " . $e->getMessage();
    echo "<br><a href='procedimientos.php'>Volver a procedimientos</a>";
}
?>
