<?php
session_start();
require_once 'includes/config.php';

// Verificar sesión y permisos
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== 'admin') {
    http_response_code(403);
    die(json_encode(['error' => 'No autorizado']));
}

$accion = $_GET['accion'] ?? $_POST['accion'] ?? null;

if ($accion === 'obtener' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Obtener configuración de backup programado
    try {
        // Crear tabla si no existe
        $conexion->exec("
            CREATE TABLE IF NOT EXISTS backup_programado (
                id INT AUTO_INCREMENT PRIMARY KEY,
                frecuencia VARCHAR(50) NOT NULL DEFAULT 'diario',
                hora_backup VARCHAR(5) NOT NULL DEFAULT '02:00',
                ultimo_backup DATETIME NULL,
                proxima_programacion DATETIME NULL,
                activo TINYINT DEFAULT 1,
                usuario_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_backup (id),
                FOREIGN KEY (usuario_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        $stmt = $conexion->query("SELECT * FROM backup_programado LIMIT 1");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$config) {
            // Crear configuración por defecto
            $conexion->prepare("
                INSERT INTO backup_programado (frecuencia, hora_backup, usuario_id, activo) 
                VALUES (?, ?, ?, ?)
            ")->execute(['diario', '02:00', $_SESSION['user_id'], 0]);
            
            $config = [
                'frecuencia' => 'diario',
                'hora_backup' => '02:00',
                'activo' => 0,
                'ultimo_backup' => null,
                'proxima_programacion' => null
            ];
        }
        
        echo json_encode([
            'success' => true,
            'config' => $config
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

if ($accion === 'actualizar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Actualizar configuración de backup programado
    try {
        $frecuencia = $_POST['frecuencia'] ?? 'diario';
        $hora_backup = $_POST['hora_backup'] ?? '02:00';
        $activo = isset($_POST['activo']) ? (int)$_POST['activo'] : 0;
        
        // Validar frecuencia
        if (!in_array($frecuencia, ['diario', 'semanal', 'mensual', 'manual'])) {
            throw new Exception('Frecuencia inválida');
        }
        
        // Validar hora
        if (!preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $hora_backup)) {
            throw new Exception('Hora inválida (formato HH:MM)');
        }
        
        // Crear tabla si no existe
        $conexion->exec("
            CREATE TABLE IF NOT EXISTS backup_programado (
                id INT AUTO_INCREMENT PRIMARY KEY,
                frecuencia VARCHAR(50) NOT NULL DEFAULT 'diario',
                hora_backup VARCHAR(5) NOT NULL DEFAULT '02:00',
                ultimo_backup DATETIME NULL,
                proxima_programacion DATETIME NULL,
                activo TINYINT DEFAULT 1,
                usuario_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_backup (id),
                FOREIGN KEY (usuario_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        // Verificar si existe configuración
        $stmt = $conexion->query("SELECT id FROM backup_programado LIMIT 1");
        $existe = $stmt->fetch();
        
        if ($existe) {
            $stmt = $conexion->prepare("
                UPDATE backup_programado 
                SET frecuencia = ?, hora_backup = ?, activo = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$frecuencia, $hora_backup, $activo, $existe['id']]);
        } else {
            $stmt = $conexion->prepare("
                INSERT INTO backup_programado (frecuencia, hora_backup, activo, usuario_id) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$frecuencia, $hora_backup, $activo, $_SESSION['user_id']]);
        }
        
        echo json_encode([
            'success' => true,
            'mensaje' => 'Configuración actualizada exitosamente'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

if ($accion === 'ejecutar_ahora' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ejecutar backup inmediatamente
    try {
        // Crear tabla si no existe
        $conexion->exec("
            CREATE TABLE IF NOT EXISTS backup_programado (
                id INT AUTO_INCREMENT PRIMARY KEY,
                frecuencia VARCHAR(50) NOT NULL DEFAULT 'diario',
                hora_backup VARCHAR(5) NOT NULL DEFAULT '02:00',
                ultimo_backup DATETIME NULL,
                proxima_programacion DATETIME NULL,
                activo TINYINT DEFAULT 1,
                usuario_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_backup (id),
                FOREIGN KEY (usuario_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        // Generar backup
        $database = obtenerEnv('DB_NAME', 'jupiter');
        $backup = "-- ===============================================\n";
        $backup .= "-- Backup de Base de Datos: $database\n";
        $backup .= "-- Fecha: " . date('Y-m-d H:i:s') . "\n";
        $backup .= "-- Usuario: " . htmlspecialchars($_SESSION['username']) . "\n";
        $backup .= "-- Programado: YES\n";
        $backup .= "-- ===============================================\n\n";
        $backup .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        
        // Obtener lista de tablas
        $stmt = $conexion->query("SHOW TABLES");
        $tablas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tablas as $tabla) {
            $stmt = $conexion->query("SHOW CREATE TABLE `$tabla`");
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            $createTable = $resultado['Create Table'] ?? $resultado[key($resultado)];
            
            $backup .= "-- ===============================================\n";
            $backup .= "-- Tabla: `$tabla`\n";
            $backup .= "-- ===============================================\n";
            $backup .= "DROP TABLE IF EXISTS `$tabla`;\n";
            $backup .= $createTable . ";\n\n";
            
            $stmt = $conexion->query("SELECT * FROM `$tabla`");
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($filas) > 0) {
                $columnas = array_keys($filas[0]);
                $columnasStr = '`' . implode('`, `', $columnas) . '`';
                
                foreach ($filas as $fila) {
                    $valores = [];
                    foreach ($fila as $valor) {
                        if ($valor === null) {
                            $valores[] = 'NULL';
                        } else {
                            $valores[] = "'" . addslashes($valor) . "'";
                        }
                    }
                    $valoresStr = implode(', ', $valores);
                    $backup .= "INSERT INTO `$tabla` ($columnasStr) VALUES ($valoresStr);\n";
                }
                $backup .= "\n";
            }
        }
        
        $backup .= "SET FOREIGN_KEY_CHECKS=1;\n";
        $backup .= "\n-- ===============================================\n";
        $backup .= "-- Fin del Backup\n";
        $backup .= "-- ===============================================\n";
        
        // Guardar en directorio
        $directorio_backups = __DIR__ . '/uploads/backups';
        if (!is_dir($directorio_backups)) {
            mkdir($directorio_backups, 0755, true);
        }
        
        $nombreArchivo = $database . '_' . date('Y-m-d_His') . '.sql';
        $rutaCompleta = $directorio_backups . '/' . $nombreArchivo;
        file_put_contents($rutaCompleta, $backup);
        
        // Actualizar último backup en configuración
        $stmt = $conexion->prepare("UPDATE backup_programado SET ultimo_backup = NOW() WHERE id = 1");
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'mensaje' => 'Backup ejecutado exitosamente',
            'archivo' => $nombreArchivo
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

http_response_code(400);
echo json_encode(['error' => 'Acción no válida']);
?>
