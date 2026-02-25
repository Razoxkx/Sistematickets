<?php
require_once 'includes/config.php';

try {
    // Verificar si la columna ya existe
    $stmt = $conexion->prepare("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'procedimientos' 
        AND TABLE_SCHEMA = 'jupiter'
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
        
        echo "✅ Columna archivo_pdf agregada exitosamente";
    } else {
        echo "✅ La columna archivo_pdf ya existe";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
