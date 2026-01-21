<?php
session_start();
require_once 'includes/config.php';

// Verificar que sea admin
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== 'admin') {
    die('<div class="alert alert-danger">Solo administradores pueden ejecutar esta acción</div>');
}

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["insertar_tickets"])) {
    try {
        // Desactivar autocommit para mejor control
        $conexion->beginTransaction();
        
        // Obtener IDs válidos de usuarios soporte TI y admin
        $stmt = $conexion->query("SELECT id FROM users WHERE role IN ('tisupport', 'admin') ORDER BY id");
        $usuarios_soporte = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($usuarios_soporte)) {
            throw new Exception("No hay usuarios de soporte TI o admin en el sistema");
        }
        
        // Array de solicitantes
        $solicitantes = ['Pablo Orellana', 'Felipe Muñoz', 'Marcos Concha', 'John Lizana'];
        
        // Array de estados
        $estados = ['sin abrir', 'en conocimiento', 'en proceso', 'pendiente de cierre', 'ticket cerrado'];
        
        // Títulos tipo asuntos de correo
        $titulos = [
            '[URGENTE] Error en el login - No se puede acceder al sistema',
            '[INFO] Solicitud de actualización de permisos en carpeta compartida',
            '[CRÍTICO] Base de datos respondiendo lentamente',
            '[SOPORTE] Reseteo de contraseña de usuario corporativo',
            '[BUG] Reporte incorrecto en módulo de estadísticas',
            '[HARDWARE] Printer parada en piso 3',
            '[SOLICITUD] Instalación de software especializado - AutoCAD 2024',
            '[FALLO] Sincronización de carpeta OneDrive no funciona',
            '[MANTENIMIENTO] Actualización de software corporativo - Office 365',
            '[INCIDENT] VPN desconecta después de 30 minutos de inactividad'
        ];
        
        $descripciones = [
            'Desde hace 2 horas no puedo acceder a mi cuenta. Intento con mi usuario y contraseña pero me aparece error 401. Necesito acceso urgente.',
            'Necesito permisos de escritura en la carpeta /shared/proyectos_2026. Actualmente solo tengo lectura y no puedo guardar mis cambios.',
            'Las consultas a la base de datos están tardando más de 30 segundos. Esto está afectando el desempeño de toda la aplicación. Requiere atención inmediata.',
            'Usuario: jlizana@empresa.com. Ha olvidado su contraseña y no puede recuperarla por el método automático.',
            'Al generar el reporte de enero, los números no coinciden con los cálculos manuales. Hay una diferencia de aprox 15% en las ventas totales.',
            'La impresora HP LJ Pro M404 del piso 3 no imprime. Muestra error de papel atascado pero ya fue revisada.',
            'Se necesita instalar AutoCAD 2024 en 5 máquinas del departamento de diseño. Favor verificar licencias disponibles.',
            'La carpeta de proyecto no se sincroniza con OneDrive. Cambios locales no se reflejan en la nube y causa conflictos con el equipo remoto.',
            'Se requiere actualizar Office 365 a la última versión. Favor agendar la ventana de mantenimiento para el próximo fin de semana.',
            'Cuando trabajo remoto, la VPN se desconecta automáticamente cada 30 minutos. Tengo que reconectarme constantemente, muy incómodo.'
        ];
        
        // Insertar 10 tickets
        for ($i = 0; $i < 10; $i++) {
            $ticket_number = "DCD" . str_pad(($i + 1), 6, "0", STR_PAD_LEFT);
            $titulo = $titulos[$i];
            $descripcion = $descripciones[$i];
            $solicitante = $solicitantes[$i % count($solicitantes)];
            $estado = $estados[array_rand($estados)];
            $es_cerrado = ($estado === 'ticket cerrado') ? 1 : 0;
            // Asignar propietario aleatoriamente de los usuarios disponibles, o null
            $propietario = (rand(0, 1) === 1) ? $usuarios_soporte[array_rand($usuarios_soporte)] : null;
            
            $stmt = $conexion->prepare("
                INSERT INTO tickets (ticket_number, titulo, descripcion, estado, es_cerrado, usuario_creador, nombre_solicitante, propietario, fecha_creacion, fecha_ultima_modificacion)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$ticket_number, $titulo, $descripcion, $estado, $es_cerrado, $_SESSION["user_id"], $solicitante, $propietario]);
            
            $ticket_id = $conexion->lastInsertId();
            
            // Agregar comentario inicial
            $comentario_inicial = "Ticket reportado por: " . $solicitante;
            $stmt = $conexion->prepare("INSERT INTO comentarios_tickets (ticket_id, usuario_id, comentario) VALUES (?, ?, ?)");
            $stmt->execute([$ticket_id, $_SESSION["user_id"], $comentario_inicial]);
        }
        
        $conexion->commit();
        
        $mensaje = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <strong>¡Éxito!</strong> Se insertaron 10 tickets de prueba en la base de datos.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
        
    } catch (PDOException $e) {
        $conexion->rollBack();
        $mensaje = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Insertar Tickets de Prueba</title>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card shadow">
                    <div class="card-body">
                        <h2 class="card-title mb-4">Insertar Tickets de Prueba</h2>
                        
                        <?php echo $mensaje; ?>
                        
                        <div class="alert alert-info">
                            <h5>Esta utilidad insertará 10 tickets de prueba con:</h5>
                            <ul>
                                <li>10 tickets con estados variados (sin abrir, en conocimiento, en proceso, pendiente de cierre, ticket cerrado)</li>
                                <li>Solicitantes: Pablo Orellana, Felipe Muñoz, Marcos Concha, John Lizana</li>
                                <li>Títulos tipo asuntos de correos electrónicos para simular incidentes reales</li>
                                <li>Descripciones detalladas y comentarios iniciales</li>
                            </ul>
                        </div>
                        
                        <form method="POST">
                            <button type="submit" name="insertar_tickets" class="btn btn-primary btn-lg">
                                Insertar 10 Tickets de Prueba
                            </button>
                            <a href="tickets.php" class="btn btn-secondary btn-lg">Cancelar</a>
                        </form>
                        
                        <hr>
                        
                        <h5 class="mt-4">Distribución de datos:</h5>
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Ticket</th>
                                    <th>Solicitante</th>
                                    <th>Título (Asunto)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>DCD000001</td>
                                    <td>Pablo Orellana</td>
                                    <td>[URGENTE] Error en el login</td>
                                </tr>
                                <tr>
                                    <td>DCD000002</td>
                                    <td>Felipe Muñoz</td>
                                    <td>[INFO] Actualización de permisos</td>
                                </tr>
                                <tr>
                                    <td>DCD000003</td>
                                    <td>Marcos Concha</td>
                                    <td>[CRÍTICO] Base de datos lenta</td>
                                </tr>
                                <tr>
                                    <td>DCD000004</td>
                                    <td>John Lizana</td>
                                    <td>[SOPORTE] Reseteo de contraseña</td>
                                </tr>
                                <tr>
                                    <td>DCD000005</td>
                                    <td>Pablo Orellana</td>
                                    <td>[BUG] Reporte incorrecto</td>
                                </tr>
                                <tr>
                                    <td>DCD000006</td>
                                    <td>Felipe Muñoz</td>
                                    <td>[HARDWARE] Printer parada</td>
                                </tr>
                                <tr>
                                    <td>DCD000007</td>
                                    <td>Marcos Concha</td>
                                    <td>[SOLICITUD] AutoCAD 2024</td>
                                </tr>
                                <tr>
                                    <td>DCD000008</td>
                                    <td>John Lizana</td>
                                    <td>[FALLO] OneDrive sincronización</td>
                                </tr>
                                <tr>
                                    <td>DCD000009</td>
                                    <td>Pablo Orellana</td>
                                    <td>[MANTENIMIENTO] Office 365</td>
                                </tr>
                                <tr>
                                    <td>DCD000010</td>
                                    <td>Felipe Muñoz</td>
                                    <td>[INCIDENT] VPN desconecta</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
