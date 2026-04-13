<?php
session_start();
require_once 'includes/config.php';

// Verificar sesión
if (!isset($_SESSION["user_id"])) {
    http_response_code(403);
    die(json_encode(['error' => 'No autorizado']));
}

// Verificar que sea admin
if ($_SESSION["role"] !== 'admin') {
    http_response_code(403);
    die(json_encode(['error' => 'Solo administradores pueden descargar backups']));
}

// Verificar que sea una solicitud POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    die(json_encode(['error' => 'Método no permitido']));
}

try {
    // Nombre de la base de datos
    $database = obtenerEnv('DB_NAME', 'jupiter');
    
    // Iniciar contenido del backup SQL
    $backup = "-- ===============================================\n";
    $backup .= "-- Backup de Base de Datos: $database\n";
    $backup .= "-- Fecha: " . date('Y-m-d H:i:s') . "\n";
    $backup .= "-- Usuario: " . htmlspecialchars($_SESSION['username']) . "\n";
    $backup .= "-- ===============================================\n\n";
    $backup .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    // Obtener lista de tablas
    $stmt = $conexion->query("SHOW TABLES");
    $tablas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tablas as $tabla) {
        // Obtener estructura de la tabla
        $stmt = $conexion->query("SHOW CREATE TABLE `$tabla`");
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        $createTable = $resultado['Create Table'] ?? $resultado[key($resultado)];
        
        $backup .= "-- ===============================================\n";
        $backup .= "-- Tabla: `$tabla`\n";
        $backup .= "-- ===============================================\n";
        $backup .= "DROP TABLE IF EXISTS `$tabla`;\n";
        $backup .= $createTable . ";\n\n";
        
        // Obtener datos de la tabla
        $stmt = $conexion->query("SELECT * FROM `$tabla`");
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($filas) > 0) {
            // Obtener nombres de columnas
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
    
    // Obtener parámetro de acción
    $accion = isset($_POST['accion']) ? $_POST['accion'] : 'descargar';
    
    if ($accion === 'guardar') {
        // Guardar en directorio de backups
        $directorio_backups = __DIR__ . '/uploads/backups';
        if (!is_dir($directorio_backups)) {
            mkdir($directorio_backups, 0755, true);
        }
        
        $nombreArchivo = $database . '_' . date('Y-m-d_His') . '.sql';
        $rutaCompleta = $directorio_backups . '/' . $nombreArchivo;
        
        file_put_contents($rutaCompleta, $backup);
        
        // Guardar registro en base de datos
        $stmt = $conexion->prepare("
            INSERT INTO backup_list (nombre_archivo, usuario_id, fecha_backup, tamano) 
            VALUES (?, ?, NOW(), ?)
        ");
        $tamano = strlen($backup);
        $stmt->execute([$nombreArchivo, $_SESSION['user_id'], $tamano]);
        
        echo json_encode([
            'success' => true,
            'mensaje' => 'Backup guardado exitosamente',
            'archivo' => $nombreArchivo,
            'tamano' => formatearTamano($tamano)
        ]);
        
    } elseif ($accion === 'descargar') {
        // Descargar directamente
        $nombreArchivo = $database . '_' . date('Y-m-d_His') . '.sql';
        
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        header('Content-Length: ' . strlen($backup));
        header('Pragma: public');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        
        echo $backup;
        exit();
    }
    
} catch (Exception $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Error al generar backup: ' . $e->getMessage()]));
}

function formatearTamano($bytes) {
    $unidades = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($unidades) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $unidades[$pow];
}
?>

