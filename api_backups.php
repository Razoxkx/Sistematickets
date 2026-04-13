<?php
session_start();
require_once 'includes/config.php';

// Verificar sesión y permisos
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== 'admin') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['error' => 'No autorizado']));
}

$accion = $_GET['accion'] ?? $_POST['accion'] ?? null;

// Header JSON para respuestas normales (no descargas)
if ($accion !== 'descargar') {
    header('Content-Type: application/json; charset=utf-8');
}

if ($accion === 'listar') {
    // Listar backups disponibles
    try {
        // Crear tabla si no existe
        $conexion->exec("
            CREATE TABLE IF NOT EXISTS backup_list (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombre_archivo VARCHAR(255) NOT NULL,
                usuario_id INT NOT NULL,
                fecha_backup DATETIME NOT NULL,
                tamano BIGINT NOT NULL,
                restaurado_en DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (usuario_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        $stmt = $conexion->query("
            SELECT bl.*, u.username 
            FROM backup_list bl
            JOIN users u ON bl.usuario_id = u.id
            ORDER BY bl.fecha_backup DESC
            LIMIT 50
        ");
        $backups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $directorio_backups = __DIR__ . '/uploads/backups';
        $backups_sistema = [];
        
        if (is_dir($directorio_backups)) {
            $archivos = scandir($directorio_backups, SCANDIR_SORT_DESCENDING);
            foreach ($archivos as $archivo) {
                if ($archivo !== '.' && $archivo !== '..' && pathinfo($archivo, PATHINFO_EXTENSION) === 'sql') {
                    $ruta_completa = $directorio_backups . '/' . $archivo;
                    $backups_sistema[] = [
                        'nombre' => $archivo,
                        'tamano' => filesize($ruta_completa),
                        'fecha' => filemtime($ruta_completa),
                        'tipo' => 'archivo'
                    ];
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'backups_bd' => $backups,
            'backups_sistema' => $backups_sistema
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al listar backups: ' . $e->getMessage()]);
    }
    exit();
}

if ($accion === 'restaurar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Restaurar un backup
    try {
        $archivo = trim($_POST['archivo'] ?? '');
        
        if (empty($archivo) || strpos($archivo, '..') !== false) {
            throw new Exception('Nombre de archivo inválido');
        }
        
        $directorio_backups = __DIR__ . '/uploads/backups';
        $ruta_backup = $directorio_backups . '/' . $archivo;
        
        if (!file_exists($ruta_backup)) {
            throw new Exception('Archivo no encontrado');
        }
        
        // Leer el contenido del backup
        $sql_content = file_get_contents($ruta_backup);
        if ($sql_content === false) {
            throw new Exception('No se pudo leer el archivo de backup');
        }
        
        // Ejecutar el SQL dividiendo por puntos y comas
        $statements = array_filter(array_map('trim', explode(';', $sql_content)));
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                try {
                    $conexion->exec($statement);
                } catch (Exception $stmtErr) {
                    // Continuar con otros statements incluso si uno falla
                    error_log("Error en statement: " . $stmtErr->getMessage());
                }
            }
        }
        
        // Registrar la restauración
        try {
            $stmt = $conexion->prepare("
                INSERT INTO backup_list (nombre_archivo, usuario_id, fecha_backup, tamano, restaurado_en) 
                VALUES (?, ?, NOW(), ?, NOW())
                ON DUPLICATE KEY UPDATE restaurado_en = NOW()
            ");
            $tamano = filesize($ruta_backup);
            $stmt->execute([$archivo, $_SESSION['user_id'], $tamano]);
        } catch (Exception $e) {
            // Si falla el registro, el backup ya fue restaurado
            error_log("Error registrando restauración: " . $e->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'mensaje' => 'Backup restaurado exitosamente'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al restaurar backup: ' . $e->getMessage()]);
    }
    exit();
}

if ($accion === 'descargar' && isset($_GET['archivo'])) {
    // Descargar un backup específico
    try {
        $archivo = trim($_GET['archivo']);
        
        if (empty($archivo) || strpos($archivo, '..') !== false) {
            throw new Exception('Nombre de archivo inválido');
        }
        
        $directorio_backups = __DIR__ . '/uploads/backups';
        $ruta_backup = $directorio_backups . '/' . $archivo;
        
        if (!file_exists($ruta_backup)) {
            throw new Exception('Archivo no encontrado');
        }
        
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $archivo . '"');
        header('Content-Length: ' . filesize($ruta_backup));
        header('Pragma: public');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        
        readfile($ruta_backup);
        exit();
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

if ($accion === 'eliminar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Eliminar un backup
    try {
        $archivo = trim($_POST['archivo'] ?? '');
        
        if (empty($archivo) || strpos($archivo, '..') !== false) {
            throw new Exception('Nombre de archivo inválido');
        }
        
        $directorio_backups = __DIR__ . '/uploads/backups';
        $ruta_backup = $directorio_backups . '/' . $archivo;
        
        if (!file_exists($ruta_backup)) {
            throw new Exception('Archivo no encontrado');
        }
        
        unlink($ruta_backup);
        
        echo json_encode([
            'success' => true,
            'mensaje' => 'Backup eliminado exitosamente'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al eliminar backup: ' . $e->getMessage()]);
    }
    exit();
}

http_response_code(400);
echo json_encode(['error' => 'Acción no válida']);
?>
