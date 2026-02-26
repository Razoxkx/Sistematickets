<?php
$host = "localhost";
$db   = "jupiter";
$user = "root";
$pass = "";
$port = 3306;

try {
    $conexion = new PDO(
        "mysql:host=$host;dbname=$db;port=$port",
        $user,
        $pass
    );
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Función para traducir roles
function traducirRol($rol) {
    $traducciones = [
        'admin' => 'Admin',
        'tisupport' => 'Soporte TI',
        'viewer' => 'Lector'
    ];
    return $traducciones[$rol] ?? $rol;
}

// Función para traducir roles al valor en inglés
function rolAlIngles($rol) {
    $traducciones = [
        'admin' => 'admin',
        'soporte ti' => 'tisupport',
        'soporte TI' => 'tisupport',
        'lector' => 'viewer'
    ];
    return $traducciones[strtolower($rol)] ?? $rol;
}

// Función para formatear fecha con hora
function formatearFechaHora($fecha) {
    $meses = ['', 'ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];
    $timestamp = strtotime($fecha);
    $mes = $meses[date('n', $timestamp)];
    $dia = date('d', $timestamp);
    $año = date('Y', $timestamp);
    $hora = date('H:i', $timestamp);
    return "$mes $dia $año - $hora";
}

// Función para formatear solo fecha
function formatearFecha($fecha) {
    $meses = ['', 'ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];
    $timestamp = strtotime($fecha);
    $mes = $meses[date('n', $timestamp)];
    $dia = date('d', $timestamp);
    $año = date('Y', $timestamp);
    return "$mes $dia $año";
}

// Función para convertir menciones de tickets, activos y usuarios en enlaces
function procesarMenciones($texto) {
    // Primero procesar las menciones ANTES de escapar HTML
    
    // 1. Procesar menciones de procedimientos (#DCD.T0000001)
    $texto = preg_replace_callback(
        '/#(DCD\.T\d{7})/i',
        function($matches) {
            global $conexion;
            $id_proc = $matches[1];
            try {
                $stmt = $conexion->prepare("SELECT id FROM procedimientos WHERE id_procedimiento = ?");
                $stmt->execute([$id_proc]);
                $proc = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($proc) {
                    return '<<<PROC_' . $proc['id'] . '_' . $id_proc . '>>>';
                }
            } catch (Exception $e) {
                // Si hay error, dejar el texto como está
            }
            return $matches[0];
        },
        $texto
    );
    
    // 2. Procesar menciones de tickets (#DCDXXXXXX)
    $texto = preg_replace_callback(
        '/#(DCD\d{6})/i',
        function($matches) {
            return '<<<TICKET_' . $matches[1] . '>>>';
        },
        $texto
    );
    
    // 3. Procesar menciones de activos (#AKXXXXXXX)
    $texto = preg_replace_callback(
        '/#(AK\d{7})/i',
        function($matches) {
            return '<<<ACTIVO_' . $matches[1] . '>>>';
        },
        $texto
    );
    
    // 4. Procesar menciones de usuarios (#usuario.nombre)
    $texto = preg_replace_callback(
        '/#([a-z0-9._-]+)/i',
        function($matches) {
            $usuario = $matches[1];
            // Evitar que procese usuarios que ya fueron procesados
            if (preg_match('/^(DCD\.T\d{7}|DCD\d{6}|AK\d{7})$/i', $usuario)) {
                return $matches[0];
            }
            return '<<<USER_' . $usuario . '>>>';
        },
        $texto
    );
    
    // Ahora escapar HTML en todo el texto
    $texto = htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
    
    // Restaurar las menciones procesadas como HTML
    // Procedimientos
    $texto = preg_replace_callback(
        '/&lt;&lt;&lt;PROC_(\d+)_(DCD\.T\d{7})&gt;&gt;&gt;/i',
        function($matches) {
            return '<a href="ver_procedimiento.php?id=' . $matches[1] . '" class="procedimiento-mention" style="color: #fd7e14;" title="Ver procedimiento ' . $matches[2] . '"><i class="bi bi-file-earmark-text"></i> ' . $matches[2] . '</a>';
        },
        $texto
    );
    
    // Tickets
    $texto = preg_replace_callback(
        '/&lt;&lt;&lt;TICKET_(DCD\d{6})&gt;&gt;&gt;/i',
        function($matches) {
            return '<a href="ver_ticket.php?id=' . $matches[1] . '" class="ticket-mention" title="Ver ticket ' . $matches[1] . '">' . $matches[1] . '</a>';
        },
        $texto
    );
    
    // Activos
    $texto = preg_replace_callback(
        '/&lt;&lt;&lt;ACTIVO_(AK\d{7})&gt;&gt;&gt;/i',
        function($matches) {
            return '<a href="ver_activo.php?id=' . $matches[1] . '" class="activo-mention" title="Ver activo ' . $matches[1] . '">' . $matches[1] . '</a>';
        },
        $texto
    );
    
    // Usuarios
    $texto = preg_replace_callback(
        '/&lt;&lt;&lt;USER_([a-z0-9._-]+)&gt;&gt;&gt;/i',
        function($matches) {
            $usuario = $matches[1];
            return '<a href="perfil_usuario.php?username=' . urlencode($usuario) . '" title="Ver perfil de ' . $usuario . '">#' . $usuario . '</a>';
        },
        $texto
    );
    
    return $texto;
}

// Función antigua mantenida por compatibilidad
function procesarMencionesTikets($texto) {
    return procesarMenciones($texto);
}

// Función para procesar hashtags de contactos (#nombre.apellido) y convertirlos en links
function procesarHashtagsContactos($texto) {
    global $conexion;
    
    // Encontrar todos los hashtags
    $texto = preg_replace_callback(
        '/#([a-z0-9._-]+)/i',
        function($matches) use ($conexion) {
            $hashtag = $matches[1];
            
            // Verificar si es un contacto o usuario
            try {
                // Primero buscar en users con rol 'contacto'
                $stmt = $conexion->prepare("SELECT id FROM users WHERE username = ? AND role = 'contacto' LIMIT 1");
                $stmt->execute([$hashtag]);
                $contacto = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($contacto) {
                    // Es un contacto (usuario con rol 'contacto')
                    return '<a href="perfil_contacto.php?username=' . urlencode($hashtag) . '" class="usuario-mention" title="Ver perfil de contacto #' . htmlspecialchars($hashtag) . '">#' . htmlspecialchars($hashtag) . '</a>';
                }
                
                // Si no es contacto, verificar si es usuario regular
                $stmt = $conexion->prepare("SELECT id FROM users WHERE username = ? AND role != 'contacto' LIMIT 1");
                $stmt->execute([$hashtag]);
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($usuario) {
                    // Es un usuario
                    return '<a href="perfil_usuario.php?username=' . urlencode($hashtag) . '" class="usuario-mention" title="Ver perfil de usuario #' . htmlspecialchars($hashtag) . '">#' . htmlspecialchars($hashtag) . '</a>';
                }
                
                // No es ni contacto ni usuario, devolver como está
                return $matches[0];
            } catch (PDOException $e) {
                return $matches[0];
            }
        },
        $texto
    );
    
    return $texto;
}

