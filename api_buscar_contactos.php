<?php
session_start();
require_once 'includes/config.php';

// Verificar si el usuario está logueado y tiene permisos (admin o tisupport)
$permisos = ['admin', 'tisupport'];
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["role"] ?? "", $permisos)) {
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$busqueda = isset($_GET["q"]) ? trim($_GET["q"]) : "";

if (empty($busqueda)) {
    echo json_encode(['contactos' => []]);
    exit();
}

try {
    // Buscar en users con role = 'contacto' para mantener solo una tabla
    $stmt = $conexion->prepare(
        "SELECT id, nombre_completo, username AS nombre_usuario, email AS correo, dpto_division AS division_departamento, role
         FROM users
         WHERE role = 'contacto' AND (nombre_completo LIKE ? OR username LIKE ? OR email LIKE ? OR dpto_division LIKE ?)
         ORDER BY nombre_completo"
    );
    $search_term = "%{$busqueda}%";
    $stmt->execute([$search_term, $search_term, $search_term, $search_term]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mantener compatibilidad con la estructura anterior: incluir numero_telefono si existe en users, sino cadena vacía
    $contactos = array_map(function($r) {
        return [
            'id' => $r['id'],
            'nombre_completo' => $r['nombre_completo'] ?? '',
            'nombre_usuario' => $r['nombre_usuario'] ?? '',
            'correo' => $r['correo'] ?? '',
            'numero_telefono' => $r['numero_telefono'] ?? '',
            'division_departamento' => $r['division_departamento'] ?? '',
            'role' => $r['role'] ?? 'contacto'
        ];
    }, $rows);

    echo json_encode(['contactos' => $contactos]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
