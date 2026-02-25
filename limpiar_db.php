<?php
require_once 'includes/config.php';

try {
    // Deshabilitar restricciones de clave foránea
    $conexion->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Borrar en el orden correcto (de abajo hacia arriba de las referencias)
    $conexion->exec("DELETE FROM menciones_procedimientos");
    $conexion->exec("DELETE FROM comentarios_tickets");
    $conexion->exec("DELETE FROM tickets");
    
    // Habilitar restricciones nuevamente
    $conexion->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "✅ Tablas vaciadas correctamente";
    echo "<br><a href='tickets.php'>Volver al dashboard</a>";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
    echo "<br><a href='tickets.php'>Volver al dashboard</a>";
}
?>
