<?php
require_once 'includes/config.php';

try {
    // Desactivar restricciones de clave foránea
    $conexion->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Eliminar todos los registros
    $conexion->exec("DELETE FROM users");
    echo "✅ Tabla users limpiada\n";
    
    // Reactivar restricciones de clave foránea
    $conexion->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // Insertar nuevo usuario con contraseña
    $password = password_hash('123456', PASSWORD_BCRYPT);
    $stmt = $conexion->prepare("
        INSERT INTO users (nombre_completo, username, password, role, email, numero_telefono, necesita_cambiar_password) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute(['Pablo Orellana', 'pablo.orellana', $password, 'admin', 'pablo.orellanaub@gmail.com', '0', 0]);
    echo "✅ Usuario pablo.orellana creado (password: 123456)\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
