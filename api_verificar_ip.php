<?php
session_start();
require_once 'includes/config.php';

// Verificar si el usuario está logueado y es admin
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

// Leer JSON del cuerpo de la solicitud
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$dispositivo_id = $data["dispositivo_id"] ?? null;

if (!$dispositivo_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Dispositivo no especificado']);
    exit();
}

try {
    // Obtener dispositivo
    $stmt = $conexion->prepare("SELECT ip FROM dispositivos_monitoreo WHERE id = ?");
    $stmt->execute([$dispositivo_id]);
    $dispositivo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dispositivo) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Dispositivo no encontrado']);
        exit();
    }
    
    $ip = $dispositivo['ip'];
    
    // Función para validar IP
    function validarIP($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP);
    }
    
    // Función para verificar conectividad usando sockets (método principal)
    function verificarConectividadSocket($ip, $puertos = [80, 443, 22]) {
        foreach ($puertos as $puerto) {
            $inicio = microtime(true);
            $fp = @fsockopen($ip, $puerto, $errno, $errstr, 2);
            $latencia = (microtime(true) - $inicio) * 1000; // ms
            
            if ($fp) {
                fclose($fp);
                return ['online' => true, 'latencia' => round($latencia, 2)];
            }
        }
        return ['online' => false, 'latencia' => null];
    }
    
    // Función para hacer ping como alternativa
    function hacerPing($ip) {
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        
        if ($isWindows) {
            $cmd = "ping -n 1 " . escapeshellarg($ip);
            $output = @shell_exec($cmd);
            
            if (preg_match('/time[<=](\d+)ms/', $output, $matches)) {
                return ['online' => true, 'latencia' => (float)$matches[1]];
            }
            return ['online' => false, 'latencia' => null];
        } else {
            if (PHP_OS === 'Darwin') {
                $cmd = "ping -c 1 -W 2000 " . escapeshellarg($ip) . " 2>&1";
            } else {
                $cmd = "ping -c 1 -W 2 " . escapeshellarg($ip) . " 2>&1";
            }
            $output = @shell_exec($cmd);
            
            if (preg_match('/time=(\d+\.?\d*)\s*ms/', $output, $matches)) {
                return ['online' => true, 'latencia' => (float)$matches[1]];
            }
            
            if (preg_match('/bytes from|bytes=/', $output)) {
                return ['online' => true, 'latencia' => null];
            }
            
            return ['online' => false, 'latencia' => null];
        }
    }
    
    // Validar IP
    $latencia = null;
    if (!validarIP($ip)) {
        $estado = "offline";
    } else {
        // Intentar primero con socket (más fiable)
        $resultado_socket = verificarConectividadSocket($ip);
        
        if ($resultado_socket['online']) {
            $estado = "online";
            $latencia = $resultado_socket['latencia'];
        } else {
            // Si socket falla, intentar con ping
            $resultado_ping = hacerPing($ip);
            $estado = $resultado_ping['online'] ? "online" : "offline";
            $latencia = $resultado_ping['latencia'];
        }
    }
    
    // Actualizar BD
    $stmt = $conexion->prepare("
        UPDATE dispositivos_monitoreo 
        SET estado = ?, fecha_ultima_verificacion = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$estado, $dispositivo_id]);
    
    // Obtener fecha formateada
    $stmt = $conexion->prepare("
        SELECT DATE_FORMAT(fecha_ultima_verificacion, '%b %d %Y - %H:%i') as fecha_formateada
        FROM dispositivos_monitoreo
        WHERE id = ?
    ");
    $stmt->execute([$dispositivo_id]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Traducir fecha si es necesario
    $fecha = $resultado['fecha_formateada'] ?? 'Hace poco';
    
    // Retraducir meses al español
    $meses_en = ['Jan' => 'ENE', 'Feb' => 'FEB', 'Mar' => 'MAR', 'Apr' => 'ABR', 'May' => 'MAY', 'Jun' => 'JUN',
                 'Jul' => 'JUL', 'Aug' => 'AGO', 'Sep' => 'SEP', 'Oct' => 'OCT', 'Nov' => 'NOV', 'Dec' => 'DIC'];
    foreach ($meses_en as $en => $es) {
        $fecha = str_replace($en, $es, $fecha);
    }
    
    echo json_encode([
        'success' => true,
        'estado' => $estado,
        'latencia' => $latencia,
        'fecha_verificacion' => $fecha
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
