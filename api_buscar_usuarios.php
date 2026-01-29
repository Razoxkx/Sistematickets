<?php
/**
 * API para buscar usuarios/contactos por nombre completo
 * Retorna lista de usuarios con username para autocomplete
 */
session_start();
require_once 'includes/config.php';

// Verificar permisos (cualquier usuario logueado puede buscar)
if (!isset($_SESSION["user_id"])) {
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$busqueda = isset($_GET["q"]) ? trim($_GET["q"]) : "";

if (empty($busqueda) || strlen($busqueda) < 2) {
    echo json_encode(['usuarios' => []]);
    exit();
}

try {
    // Buscar en users por nombre_completo, username o email
    // Incluye usuarios del sistema (admin, tisupport, viewer) y contactos (role='contacto')
    $stmt = $conexion->prepare(
        "SELECT id, nombre_completo, username, role 
         FROM users 
         WHERE (nombre_completo LIKE ? OR username LIKE ? OR email LIKE ?)
         ORDER BY nombre_completo
         LIMIT 20"
    );
    $search_term = "%{$busqueda}%";
    $stmt->execute([$search_term, $search_term, $search_term]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mapear a formato de respuesta para autocomplete
    $usuarios = array_map(function($r) {
        return [
            'id' => $r['id'],
            'nombre_completo' => $r['nombre_completo'] ?? $r['username'],
            'username' => $r['username'],
            'role' => $r['role'],
            'label' => ($r['nombre_completo'] ?? $r['username']) . ' (@' . $r['username'] . ')'
        ];
    }, $rows);

    echo json_encode(['usuarios' => $usuarios]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
