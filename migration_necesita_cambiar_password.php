<?php
/**
 * Migración: Agregar columna necesita_cambiar_password a tabla users
 * 
 * Esta migración agrega una bandera para forzar a los usuarios a cambiar su contraseña
 * en el próximo inicio de sesión. Útil para contraseñas temporales.
 * 
 * Ejecución: Abriendo este archivo en el navegador o con php CLI
 */

require_once 'includes/config.php';

$resultado = [];
$error = "";

try {
    // Verificar si la columna ya existe
    $stmt = $conexion->query("SHOW COLUMNS FROM users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $column_names = array_column($columns, 'Field');
    
    if (in_array('necesita_cambiar_password', $column_names)) {
        $resultado[] = "✅ La columna 'necesita_cambiar_password' ya existe en la tabla 'users'";
    } else {
        // Agregar la columna
        $sql = "ALTER TABLE users ADD COLUMN necesita_cambiar_password BOOLEAN DEFAULT 0 AFTER password";
        $conexion->exec($sql);
        $resultado[] = "✅ Columna 'necesita_cambiar_password' agregada exitosamente";
    }
} catch (PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Migración: Cambio de Contraseña Forzado</title>
</head>
<body class="bg-light p-4">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">🔒 Migración: Forzar Cambio de Contraseña</h5>
                    </div>
                    <div class="card-body">
                        <h6>Descripción:</h6>
                        <p class="text-muted">Esta migración agrega soporte para obligar a los usuarios a cambiar su contraseña en el próximo inicio de sesión.</p>
                        
                        <h6 class="mt-4">Resultados:</h6>
                        <?php if (!empty($resultado)): ?>
                            <?php foreach ($resultado as $msg): ?>
                                <div class="alert alert-success mb-2" role="alert">
                                    <?php echo htmlspecialchars($msg); ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <h6 class="mt-4">Cómo funciona:</h6>
                        <ol class="text-muted">
                            <li>Cuando creas un usuario con contraseña <strong>"111111"</strong>, se marca con <code>necesita_cambiar_password = 1</code></li>
                            <li>En el próximo inicio de sesión, el usuario es redirigido automáticamente a cambiar su contraseña</li>
                            <li>El usuario DEBE ingresar la contraseña actual ("111111") y establecer una nueva contraseña segura</li>
                            <li>Una vez cambiada, se marca con <code>necesita_cambiar_password = 0</code></li>
                        </ol>
                        
                        <div class="alert alert-info mt-4">
                            <strong>Nota:</strong> Si ya tienes el código desplegado, puedes ejecutar esta migración para agregar la columna automáticamente.
                        </div>
                        
                        <a href="usuarios.php" class="btn btn-primary mt-3">
                            ← Volver a Usuarios
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
