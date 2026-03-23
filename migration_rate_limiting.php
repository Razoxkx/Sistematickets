<?php
/**
 * Migración: Rate Limiting para intentos de login
 * Crea tabla login_attempts para registrar intentos fallidos
 */

require_once 'includes/config.php';

try {
    // Verificar si la tabla ya existe
    $sql_check = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES 
                  WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'login_attempts'";
    
    $stmt = $conexion->query($sql_check);
    $table_exists = $stmt->rowCount() > 0;
    
    if (!$table_exists) {
        // Crear tabla login_attempts
        $sql = "CREATE TABLE login_attempts (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            failed_attempts INT DEFAULT 1,
            first_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            blocked_until TIMESTAMP NULL,
            INDEX idx_username (username),
            INDEX idx_ip (ip_address),
            INDEX idx_blocked_until (blocked_until)
        )";
        
        $conexion->exec($sql);
        echo "✅ Tabla 'login_attempts' creada exitosamente\n";
    } else {
        echo "ℹ️  Tabla 'login_attempts' ya existe\n";
    }
    
    // Limpiar intentos antiguos (más de 1 hora)
    $sql_cleanup = "DELETE FROM login_attempts 
                    WHERE last_attempt < DATE_SUB(NOW(), INTERVAL 1 HOUR)
                    AND blocked_until IS NULL";
    $conexion->exec($sql_cleanup);
    echo "✅ Registros antiguos limpiados\n";
    
} catch (PDOException $e) {
    echo "❌ Error en migración: " . $e->getMessage() . "\n";
}
?>
