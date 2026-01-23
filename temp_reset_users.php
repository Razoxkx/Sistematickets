<?php
require_once 'includes/config.php';

try {
    // Eliminar todos los registros
    $conexion->exec("DELETE FROM users");
    echo "✅ Tabla users limpiada\n";
    
    // Insertar nuevo usuario con contraseña
    $password = password_hash('123456', PASSWORD_BCRYPT);
    $stmt = $conexion->prepare("
        INSERT INTO users (username, password, role, email, necesita_cambiar_password) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute(['pablo.orellana', $password, 'admin', 'pablo.orellanaub@gmail.com', 0]);
    echo "✅ Usuario pablo.orellana creado (password: 123456)\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
