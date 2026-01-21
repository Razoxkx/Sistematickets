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
?>

