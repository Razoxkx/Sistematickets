<?php
require_once 'includes/config.php';

try {
    // Verificar si las columnas ya existen antes de agregarlas
    $stmt = $conexion->query("SHOW COLUMNS FROM activos LIKE 'usuario_creado_por'");
    $existe_usuario = $stmt->rowCount() > 0;
    
    $stmt = $conexion->query("SHOW COLUMNS FROM activos LIKE 'fecha_creacion'");
    $existe_fecha_creacion = $stmt->rowCount() > 0;
    
    $stmt = $conexion->query("SHOW COLUMNS FROM activos LIKE 'usuario_modificado_por'");
    $existe_usuario_mod = $stmt->rowCount() > 0;
    
    $stmt = $conexion->query("SHOW COLUMNS FROM activos LIKE 'fecha_ultima_modificacion'");
    $existe_fecha_mod = $stmt->rowCount() > 0;
    
    $cambios = 0;
    
    // Agregar columna usuario_creado_por
    if (!$existe_usuario) {
        $conexion->exec("ALTER TABLE activos ADD COLUMN usuario_creado_por VARCHAR(255) AFTER titulo");
        echo "✓ Columna 'usuario_creado_por' agregada<br>";
        $cambios++;
    }
    
    // Agregar columna fecha_creacion
    if (!$existe_fecha_creacion) {
        $conexion->exec("ALTER TABLE activos ADD COLUMN fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER usuario_creado_por");
        echo "✓ Columna 'fecha_creacion' agregada<br>";
        $cambios++;
    }
    
    // Agregar columna usuario_modificado_por
    if (!$existe_usuario_mod) {
        $conexion->exec("ALTER TABLE activos ADD COLUMN usuario_modificado_por VARCHAR(255) AFTER fecha_creacion");
        echo "✓ Columna 'usuario_modificado_por' agregada<br>";
        $cambios++;
    }
    
    // Agregar columna fecha_ultima_modificacion
    if (!$existe_fecha_mod) {
        $conexion->exec("ALTER TABLE activos ADD COLUMN fecha_ultima_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER usuario_modificado_por");
        echo "✓ Columna 'fecha_ultima_modificacion' agregada<br>";
        $cambios++;
    }
    
    if ($cambios === 0) {
        echo "⚠ Todas las columnas ya existen en la tabla<br>";
    } else {
        echo "<br><strong>✓ Migración completada: $cambios columnas agregadas</strong>";
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
