<?php
/**
 * MIGRACIÓN: Agregar tabla tipos_dispositivos y relación en dispositivos_monitoreo
 * Versión: 2.0 - Para producción
 * Fecha: 2026-03-24
 */

session_start();
require_once 'includes/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

// Verificar si es admin
if ($_SESSION["role"] !== "admin") {
    http_response_code(403);
    echo "❌ Error: No tienes permisos para ejecutar migraciones. Solo administradores pueden hacerlo.";
    exit();
}

$log = [];
$errores = [];

html_header();

try {
    $log[] = "🔄 Iniciando migración completa de tipos de dispositivos...";
    
    // PASO 1: Crear tabla tipos_dispositivos (si no existe)
    $log[] = "✓ Verificando tabla 'tipos_dispositivos'...";
    $stmt = $conexion->query("SHOW TABLES LIKE 'tipos_dispositivos'");
    
    if ($stmt->rowCount() === 0) {
        $log[] = "→ Tabla no existe. Creando...";
        $sql = "CREATE TABLE tipos_dispositivos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL UNIQUE,
            color VARCHAR(7) NOT NULL DEFAULT '#6c757d',
            icono VARCHAR(50) NOT NULL DEFAULT 'bi-device-hdd',
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CHARSET=utf8mb4,
            COLLATE=utf8mb4_unicode_ci
        ) ENGINE=InnoDB";
        $conexion->exec($sql);
        $log[] = "✅ Tabla 'tipos_dispositivos' creada correctamente.";
        
        // Insertar datos predefinidos
        $log[] = "→ Insertando tipos de dispositivos predefinidos...";
        $sql = "INSERT INTO tipos_dispositivos (nombre, color, icono) VALUES 
            ('Switch', '#007bff', 'bi-diagram-3'),
            ('NVR', '#e83e8c', 'bi-camera-video'),
            ('Access Point', '#17a2b8', 'bi-wifi'),
            ('Reloj Control', '#ffc107', 'bi-clock'),
            ('Máquina Virtual', '#6610f2', 'bi-cpu'),
            ('Otro', '#6c757d', 'bi-device-hdd')";
        $conexion->exec($sql);
        $log[] = "✅ Datos predefinidos insertados (6 tipos de dispositivos).";
    } else {
        $log[] = "✅ Tabla 'tipos_dispositivos' ya existe.";
        
        // Verificar si tiene datos
        $stmt2 = $conexion->query("SELECT COUNT(*) as cnt FROM tipos_dispositivos");
        $row = $stmt2->fetch(PDO::FETCH_ASSOC);
        if ($row['cnt'] > 0) {
            $log[] = "ℹ️ La tabla ya contiene " . $row['cnt'] . " tipo(s) de dispositivo.";
        } else {
            $log[] = "→ Insertando tipos de dispositivos predefinidos...";
            $sql = "INSERT INTO tipos_dispositivos (nombre, color, icono) VALUES 
                ('Switch', '#007bff', 'bi-diagram-3'),
                ('NVR', '#e83e8c', 'bi-camera-video'),
                ('Access Point', '#17a2b8', 'bi-wifi'),
                ('Reloj Control', '#ffc107', 'bi-clock'),
                ('Máquina Virtual', '#6610f2', 'bi-cpu'),
                ('Otro', '#6c757d', 'bi-device-hdd')";
            $conexion->exec($sql);
            $log[] = "✅ Datos predefinidos insertados.";
        }
    }
    
    // PASO 2: Verificar tabla dispositivos_monitoreo
    $log[] = "✓ Verificando tabla 'dispositivos_monitoreo'...";
    $stmt = $conexion->query("SHOW TABLES LIKE 'dispositivos_monitoreo'");
    if ($stmt->rowCount() === 0) {
        throw new Exception("❌ La tabla 'dispositivos_monitoreo' no existe.");
    }
    $log[] = "✅ Tabla 'dispositivos_monitoreo' encontrada.";
    
    // PASO 3: Agregar columna tipo_dispositivo_id (si no existe)
    $log[] = "✓ Verificando columna 'tipo_dispositivo_id'...";
    $stmt = $conexion->query("SHOW COLUMNS FROM dispositivos_monitoreo LIKE 'tipo_dispositivo_id'");
    
    if ($stmt->rowCount() === 0) {
        $log[] = "→ Columna no existe. Agregando...";
        $sql = "ALTER TABLE dispositivos_monitoreo ADD COLUMN tipo_dispositivo_id INT DEFAULT NULL AFTER descripcion";
        $conexion->exec($sql);
        $log[] = "✅ Columna 'tipo_dispositivo_id' agregada correctamente.";
    } else {
        $log[] = "ℹ️ La columna 'tipo_dispositivo_id' ya existe.";
    }
    
    // PASO 4: Verificar Foreign Key
    $log[] = "✓ Verificando Foreign Key...";
    $stmt = $conexion->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_NAME='dispositivos_monitoreo' AND COLUMN_NAME='tipo_dispositivo_id' AND CONSTRAINT_NAME='fk_tipo_dispositivo'");
    
    if ($stmt->rowCount() === 0) {
        $log[] = "→ Foreign Key no existe. Agregando...";
        $sql = "ALTER TABLE dispositivos_monitoreo ADD CONSTRAINT fk_tipo_dispositivo 
            FOREIGN KEY (tipo_dispositivo_id) REFERENCES tipos_dispositivos(id) ON DELETE SET NULL";
        $conexion->exec($sql);
        $log[] = "✅ Foreign Key 'fk_tipo_dispositivo' agregada correctamente.";
    } else {
        $log[] = "ℹ️ El Foreign Key 'fk_tipo_dispositivo' ya existe.";
    }
    
    // ÉXITO
    $log[] = "🎉 ¡MIGRACIÓN COMPLETADA EXITOSAMENTE!";
    $log[] = "Los dispositivos están listos para ser clasificados por tipo.";
    
} catch (PDOException $e) {
    $errores[] = "❌ Error de Base de Datos: " . $e->getMessage();
} catch (Exception $e) {
    $errores[] = $e->getMessage();
}

// Mostrar resultados
mostrar_resultados($log, $errores);
html_footer();

function html_header() {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Migración - Tipos de Dispositivos</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background: #0d1117; color: #e0e0e0; padding: 40px 20px; }
            .container { max-width: 800px; }
            .card { background: #161b22; border: 1px solid #30363d; border-radius: 12px; }
            .card-header { background: #0d1117; border-bottom: 1px solid #30363d; }
            .log-item { padding: 10px; font-family: 'Courier New'; font-size: 0.95rem; }
            .log-success { color: #3fb950; }
            .log-info { color: #79c0ff; }
            .log-error { color: #f85149; }
            .title { color: #79c0ff; margin-bottom: 30px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1 class="title"><i class="bi bi-database"></i> Migración de Tipos de Dispositivos</h1>
            <div class="card">
                <div class="card-header">
                    <h5>Resultado de Ejecución</h5>
                </div>
                <div class="card-body">
    <?php
}

function mostrar_resultados($log, $errores) {
    ?>
                    <div style="background: #0d1117; padding: 20px; border-radius: 8px; border-left: 4px solid #79c0ff;">
    <?php foreach ($log as $linea): ?>
                        <div class="log-item log-<?php 
                            if (strpos($linea, '✅') !== false) echo 'success';
                            elseif (strpos($linea, '❌') !== false) echo 'error';
                            else echo 'info';
                        ?>">
                            <?php echo htmlspecialchars($linea); ?>
                        </div>
    <?php endforeach; ?>
    
    <?php if (!empty($errores)): ?>
                        <div style="margin-top: 20px; padding: 15px; background: rgba(248, 81, 73, 0.1); border-left: 4px solid #f85149; border-radius: 4px;">
        <?php foreach ($errores as $error): ?>
                            <div class="log-item log-error">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
        <?php endforeach; ?>
                        </div>
    <?php endif; ?>
                    </div>
    <?php
}

function html_footer() {
    ?>
                </div>
            </div>
            <div style="margin-top: 30px; padding: 15px; background: #161b22; border: 1px solid #30363d; border-radius: 8px; font-size: 0.9rem; color: #999;">
                <p><strong>📝 Nota:</strong> Esta migración agrega la capacidad de clasificar dispositivos por tipo.</p>
                <p><strong>⏱️ Duración:</strong> < 1 segundo</p>
                <p><strong>🔄 Segura:</strong> Verifica antes de cada operación si ya existe</p>
                <p><a href="monitoreo.php" style="color: #79c0ff; text-decoration: none;">← Volver a Monitoreo</a></p>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>
