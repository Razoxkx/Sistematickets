<?php
/**
 * Script de migración para cambiar "finalizado" a "ticket cerrado"
 * Ejecutar una sola vez
 */

require_once 'includes/config.php';

try {
    // Actualizar todos los tickets con estado "finalizado" a "ticket cerrado"
    $stmt = $conexion->prepare("UPDATE tickets SET estado = ? WHERE estado = ?");
    $stmt->execute(['ticket cerrado', 'finalizado']);
    
    $rows_updated = $stmt->rowCount();
    
    echo "<div class='alert alert-success' style='margin: 20px;'>";
    echo "<h4>Migración completada exitosamente</h4>";
    echo "<p>Se actualizaron <strong>$rows_updated</strong> ticket(s) de 'finalizado' a 'ticket cerrado'</p>";
    echo "</div>";
    
    // Mostrar tickets actualizados
    $stmt = $conexion->query("SELECT id, ticket_number, titulo, estado FROM tickets WHERE estado = 'ticket cerrado' ORDER BY fecha_ultima_modificacion DESC LIMIT 10");
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($tickets) {
        echo "<div style='margin: 20px;'>";
        echo "<h5>Últimos tickets con estado 'ticket cerrado':</h5>";
        echo "<ul>";
        foreach ($tickets as $ticket) {
            echo "<li>#" . $ticket['ticket_number'] . " - " . htmlspecialchars($ticket['titulo']) . "</li>";
        }
        echo "</ul>";
        echo "</div>";
    }
    
} catch (PDOException $e) {
    echo "<div class='alert alert-danger' style='margin: 20px;'>";
    echo "Error: " . $e->getMessage();
    echo "</div>";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Migración de Estado Finalizado</title>
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h2>Estado de la Migración</h2>
        <p><a href="tickets.php" class="btn btn-primary">Volver a Tickets</a></p>
    </div>
</body>
</html>
