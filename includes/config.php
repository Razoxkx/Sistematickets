<?php
$host = "localhost";
$db   = "test";
$user = "root";
$pass = "r652Is-scVT1HX3@";
$port = 8889;

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

// Función para convertir menciones de tickets y activos en enlaces
function procesarMenciones($texto) {
    // Primero procesar menciones de tickets (#DCDXXXXXX)
    $texto = htmlspecialchars($texto);
    $texto = preg_replace(
        '/#(DCD\d{6})/i',
        '<a href="ver_ticket.php?id=$1" class="ticket-mention">$1</a>',
        $texto
    );
    // Luego procesar menciones de activos (#AKXXXXXXX)
    $texto = preg_replace(
        '/#(AK\d{7})/i',
        '<a href="ver_activo.php?id=$1" class="ticket-mention">$1</a>',
        $texto
    );
    return $texto;
}

// Función antigua mantenida por compatibilidad
function procesarMencionesTikets($texto) {
    return procesarMenciones($texto);
}
?>

