<?php
/**
 * Sistema de Rate Limiting para prevenir ataques de fuerza bruta
 */

/**
 * Obtener IP del cliente
 */
function obtenerIPCliente() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        // Cloudflare
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Proxy
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

/**
 * Verificar si el usuario/IP está bloqueado
 * @return array ['bloqueado' => bool, 'tiempo_espera' => int segundos]
 */
function verificarBloqueoPorRateLimit($conexion, $username) {
    try {
        $ip = obtenerIPCliente();
        
        $stmt = $conexion->prepare("
            SELECT failed_attempts, blocked_until
            FROM login_attempts
            WHERE username = ? AND ip_address = ?
            LIMIT 1
        ");
        $stmt->execute([$username, $ip]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$record) {
            return ['bloqueado' => false, 'tiempo_espera' => 0];
        }
        
        // Verificar si está bloqueado
        if ($record['blocked_until']) {
            $blocked_until = strtotime($record['blocked_until']);
            $now = time();
            
            if ($now < $blocked_until) {
                $tiempo_espera = $blocked_until - $now;
                return ['bloqueado' => true, 'tiempo_espera' => $tiempo_espera];
            } else {
                // El bloqueo expiró, limpiar
                limpiarIntentosFallidos($conexion, $username, $ip);
                return ['bloqueado' => false, 'tiempo_espera' => 0];
            }
        }
        
        return ['bloqueado' => false, 'tiempo_espera' => 0];
    } catch (PDOException $e) {
        // Si hay error en la BD, permitir intento (fail open)
        return ['bloqueado' => false, 'tiempo_espera' => 0];
    }
}

/**
 * Registrar intento fallido
 */
function registrarIntentoFallido($conexion, $username) {
    try {
        $ip = obtenerIPCliente();
        $max_intentos = 5;
        $duracion_bloqueo = 15 * 60; // 15 minutos en segundos
        
        $stmt = $conexion->prepare("
            SELECT failed_attempts, last_attempt
            FROM login_attempts
            WHERE username = ? AND ip_address = ?
            LIMIT 1
        ");
        $stmt->execute([$username, $ip]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($record) {
            // Verificar si han pasado más de 15 minutos desde el último intento
            $last_attempt = strtotime($record['last_attempt']);
            $tiempo_transcurrido = time() - $last_attempt;
            
            if ($tiempo_transcurrido > 900) { // 15 minutos
                // Reiniciar contador
                $nuevo_intento = 1;
            } else {
                // Incrementar contador
                $nuevo_intento = $record['failed_attempts'] + 1;
            }
            
            // Determinar si debe ser bloqueado
            $blocked_until = NULL;
            if ($nuevo_intento >= $max_intentos) {
                $blocked_until = date('Y-m-d H:i:s', time() + $duracion_bloqueo);
            }
            
            // Actualizar registro
            $stmt = $conexion->prepare("
                UPDATE login_attempts 
                SET failed_attempts = ?, 
                    last_attempt = NOW(),
                    blocked_until = ?
                WHERE username = ? AND ip_address = ?
            ");
            $stmt->execute([$nuevo_intento, $blocked_until, $username, $ip]);
        } else {
            // Crear nuevo registro
            $stmt = $conexion->prepare("
                INSERT INTO login_attempts (username, ip_address, failed_attempts, blocked_until)
                VALUES (?, ?, 1, NULL)
            ");
            $stmt->execute([$username, $ip]);
        }
    } catch (PDOException $e) {
        // Silenciosamente ignorar errores de BD en rate limiting
    }
}

/**
 * Limpiar intentos fallidos después de login exitoso
 */
function limpiarIntentosFallidos($conexion, $username, $ip = null) {
    try {
        if (!$ip) {
            $ip = obtenerIPCliente();
        }
        
        $stmt = $conexion->prepare("
            DELETE FROM login_attempts
            WHERE username = ? AND ip_address = ?
        ");
        $stmt->execute([$username, $ip]);
    } catch (PDOException $e) {
        // Silenciosamente ignorar errores
    }
}

/**
 * Obtener mensaje de bloqueo amigable
 */
function obtenerMensajeBloqueoPorRateLimit($tiempo_espera) {
    $minutos = ceil($tiempo_espera / 60);
    return "Demasiados intentos fallidos. Por favor intenta de nuevo en $minutos minuto(s).";
}
?>
