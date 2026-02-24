<?php
session_start();
require_once 'includes/config.php';

// Prevenir cacheo
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Verificar si el usuario está logueado
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

// Verificar permisos
$permisos = ['tisupport', 'admin'];
if (!in_array($_SESSION["role"] ?? "viewer", $permisos)) {
    header("Location: tickets.php");
    exit();
}

// Obtener lista de solicitantes únicos
try {
    $stmt = $conexion->query("SELECT DISTINCT nombre_solicitante FROM tickets ORDER BY nombre_solicitante");
    $solicitantes = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $solicitantes = [];
}

// Función para obtener estadísticas de estados
function obtenerEstadisticasEstados($conexion, $periodo = 'todo') {
    $whereClause = "";
    
    if ($periodo === 'semana') {
        $whereClause = "WHERE fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    } elseif ($periodo === 'mes') {
        $whereClause = "WHERE fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    } elseif ($periodo === 'año') {
        $whereClause = "WHERE fecha >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
    }
    
    try {
        $stmt = $conexion->query("
            SELECT estado, COUNT(*) as cantidad 
            FROM tickets 
            $whereClause 
            GROUP BY estado
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Función para obtener top 10 solicitantes
function obtenerTop10Solicitantes($conexion, $periodo = 'todo') {
    $whereClause = "";
    
    if ($periodo === 'semana') {
        $whereClause = "WHERE fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    } elseif ($periodo === 'mes') {
        $whereClause = "WHERE fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    } elseif ($periodo === 'año') {
        $whereClause = "WHERE fecha >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
    }
    
    try {
        $stmt = $conexion->query("
            SELECT nombre_solicitante, COUNT(*) as total_reportes 
            FROM tickets 
            $whereClause 
            GROUP BY nombre_solicitante 
            ORDER BY total_reportes DESC 
            LIMIT 10
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

$error = "";
$datos_estados = obtenerEstadisticasEstados($conexion, 'todo');
$datos_solicitantes = obtenerTop10Solicitantes($conexion, 'todo');
?>

<!DOCTYPE html>
<html lang="es" id="htmlRoot">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/dark-mode.css" rel="stylesheet">
    <title>Reportes de Tickets</title>
    <style>
        h1, h2, h3 {
            color: #8b9dff;
            font-weight: 700;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        h2 {
            font-size: 1.75rem;
            margin-bottom: 30px;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.js"></script>
    <script>
        (function() {
            const darkMode = localStorage.getItem('darkMode');
            if (darkMode === 'enabled') {
                document.documentElement.setAttribute('data-bs-theme', 'dark');
            } else {
                document.documentElement.removeAttribute('data-bs-theme');
            }
        })();
    </script>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="container mt-5 mb-5">
        <div class="row mb-4">
            <div class="col-md-12">
                <h2 class="mb-4"><i></i>Reportes y Gráficas</h2>
            </div>
        </div>

        <!-- Selector de Período -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row align-items-end">
                            <div class="col-md-8">
                                <label class="form-label"><strong>Filtrar por período:</strong></label>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-outline-primary" onclick="cargarDatos('semana')">📅 Última Semana</button>
                                    <button class="btn btn-sm btn-outline-primary" onclick="cargarDatos('mes')">📆 Último Mes</button>
                                    <button class="btn btn-sm btn-outline-primary" onclick="cargarDatos('año')">Último Año</button>
                                    <button class="btn btn-sm btn-outline-primary active" onclick="cargarDatos('todo')">🔢 Todo el Tiempo</button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <button class="btn btn-sm btn-info" data-bs-toggle="collapse" data-bs-target="#filtrosPersonalizados">
                                    <i class="bi bi-funnel"></i> Filtro Personalizado
                                </button>
                            </div>
                        </div>

                        <!-- Filtros Personalizados (Ocultos por defecto) -->
                        <div class="collapse mt-3" id="filtrosPersonalizados">
                            <div class="card card-body border-info">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="fecha_desde_grafico" class="form-label"><strong>📅 Desde:</strong></label>
                                        <input type="date" class="form-control" id="fecha_desde_grafico">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="fecha_hasta_grafico" class="form-label"><strong>📅 Hasta:</strong></label>
                                        <input type="date" class="form-control" id="fecha_hasta_grafico">
                                    </div>
                                    <div class="col-md-4 d-flex align-items-end gap-2">
                                        <button class="btn btn-success" onclick="aplicarFiltroPersonalizado()">
                                            <i class="bi bi-check"></i> Aplicar
                                        </button>
                                        <button class="btn btn-secondary" onclick="limpiarFiltroPersonalizado()">
                                            <i class="bi bi-x"></i> Limpiar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficas -->
        <div class="row mb-4">
            <!-- Gráfica de Estados -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Estados de Tickets</h5>
                    </div>
                    <div class="card-body">
                        <div style="position: relative; height: 300px;">
                            <canvas id="chartEstados"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráfica de Top 10 Solicitantes -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Top 10 Solicitantes</h5>
                    </div>
                    <div class="card-body">
                        <div style="position: relative; height: 300px;">
                            <canvas id="chartSolicitantes"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de Solicitantes -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header text-white" style="background-color: #667eea;">
                        <h5 class="mb-0"><i class="bi bi-list"></i> Detalle de Top 10 Solicitantes</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped" id="tablaSolicitantes">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Posición</th>
                                        <th>Solicitante</th>
                                        <th>Total Incidencias</th>
                                        <th>Porcentaje</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaBody">
                                    <!-- Se llena con JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sección de Descargar PDF -->
        <div class="row">
            <div class="col-md-12">
                <div class="card bg-light">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-file-pdf"></i> Descargar Reportes en PDF</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="generar_reporte_pdf.php" target="_blank">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label for="filtro_estado" class="form-label"><strong>Estado del Ticket</strong></label>
                                    <select class="form-select" id="filtro_estado" name="filtro_estado">
                                        <option value="">-- Todos los estados --</option>
                                        <option value="sin abrir">Sin abrir</option>
                                        <option value="en conocimiento">En conocimiento</option>
                                        <option value="en proceso">En proceso</option>
                                        <option value="pendiente de cierre">Pendiente de cierre</option>
                                        <option value="ticket cerrado">Ticket Cerrado</option>
                                    </select>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label for="filtro_solicitante" class="form-label"><strong>Solicitante</strong></label>
                                    <select class="form-select" id="filtro_solicitante" name="filtro_solicitante">
                                        <option value="">-- Todos los solicitantes --</option>
                                        <?php foreach ($solicitantes as $solicitante): ?>
                                            <option value="<?php echo htmlspecialchars($solicitante); ?>">
                                                <?php echo htmlspecialchars($solicitante); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-2 mb-3">
                                    <label for="fecha_desde" class="form-label"><strong>Fecha Desde</strong></label>
                                    <input type="date" class="form-control" id="fecha_desde" name="fecha_desde">
                                </div>

                                <div class="col-md-2 mb-3">
                                    <label for="fecha_hasta" class="form-label"><strong>Fecha Hasta</strong></label>
                                    <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta">
                                </div>

                                <div class="col-md-2 mb-3">
                                    <label for="tipo_reporte" class="form-label"><strong>Tipo Reporte</strong></label>
                                    <select class="form-select" id="tipo_reporte" name="tipo_reporte">
                                        <option value="completo">Completo</option>
                                        <option value="resumen">Resumido</option>
                                    </select>
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-file-pdf"></i> Descargar PDF
                                </button>
                                <a href="tickets.php" class="btn btn-secondary">Volver</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let chartEstados = null;
        let chartSolicitantes = null;

        // Datos iniciales
        const datosIniciales = {
            estados: <?php echo json_encode($datos_estados); ?>,
            solicitantes: <?php echo json_encode($datos_solicitantes); ?>
        };

        function inicializarGraficas() {
            crearGraficaEstados(datosIniciales.estados);
            crearGraficaSolicitantes(datosIniciales.solicitantes);
            actualizarTabla(datosIniciales.solicitantes);
        }

        function crearGraficaEstados(datos) {
            const ctx = document.getElementById('chartEstados').getContext('2d');
            
            const colores = {
                'sin abrir': '#FFB6C1',
                'en conocimiento': '#FFA500',
                'en proceso': '#87CEEB',
                'pendiente de cierre': '#FFD700',
                'ticket cerrado': '#90EE90'
            };

            if (chartEstados) {
                chartEstados.destroy();
            }

            const labels = datos.map(d => d.estado);
            const values = datos.map(d => d.cantidad);
            const backgroundColors = labels.map(l => colores[l] || '#808080');

            chartEstados = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: backgroundColors,
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        function crearGraficaSolicitantes(datos) {
            const ctx = document.getElementById('chartSolicitantes').getContext('2d');

            if (chartSolicitantes) {
                chartSolicitantes.destroy();
            }

            const labels = datos.map(d => d.nombre_solicitante.substring(0, 15));
            const values = datos.map(d => d.total_reportes);

            chartSolicitantes = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Incidencias Reportadas',
                        data: values,
                        backgroundColor: '#28a745',
                        borderColor: '#1e7e34',
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        function actualizarTabla(datos) {
            const tbody = document.getElementById('tablaBody');
            tbody.innerHTML = '';

            const totalIncidencias = datos.reduce((sum, d) => sum + parseInt(d.total_reportes), 0);

            datos.forEach((solicitante, index) => {
                const porcentaje = ((solicitante.total_reportes / totalIncidencias) * 100).toFixed(1);
                const row = `
                    <tr>
                        <td>
                            <span class="badge bg-primary rounded-pill">${index + 1}</span>
                        </td>
                        <td><strong>${solicitante.nombre_solicitante}</strong></td>
                        <td>
                            <span class="badge bg-success">${solicitante.total_reportes}</span>
                        </td>
                        <td>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar" role="progressbar" style="width: ${porcentaje}%;" aria-valuenow="${porcentaje}" aria-valuemin="0" aria-valuemax="100">
                                    ${porcentaje}%
                                </div>
                            </div>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        }
        function cargarDatos(periodo) {
            // Limpiar filtro personalizado
            document.getElementById('fecha_desde_grafico').value = '';
            document.getElementById('fecha_hasta_grafico').value = '';
            
            // Actualizar botones
            document.querySelectorAll('.btn-outline-primary, .btn-outline-primary.active').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');

            // Cargar datos desde servidor
            fetch(`api_reportes.php?periodo=${periodo}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        crearGraficaEstados(data.estados);
                        crearGraficaSolicitantes(data.solicitantes);
                        actualizarTabla(data.solicitantes);
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error cargando datos:', error);
                    alert('Error al cargar los datos');
                });
        }

        function aplicarFiltroPersonalizado() {
            const fechaDesde = document.getElementById('fecha_desde_grafico').value;
            const fechaHasta = document.getElementById('fecha_hasta_grafico').value;

            if (!fechaDesde || !fechaHasta) {
                alert('Por favor completa ambas fechas');
                return;
            }

            if (fechaDesde > fechaHasta) {
                alert('La fecha "Desde" no puede ser mayor que la fecha "Hasta"');
                return;
            }

            // Limpiar estado de botones
            document.querySelectorAll('.btn-outline-primary').forEach(btn => {
                btn.classList.remove('active');
            });

            // Cargar datos con rango personalizado
            const params = new URLSearchParams({
                periodo: 'personalizado',
                fecha_desde: fechaDesde,
                fecha_hasta: fechaHasta
            });

            fetch(`api_reportes.php?${params}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        crearGraficaEstados(data.estados);
                        crearGraficaSolicitantes(data.solicitantes);
                        actualizarTabla(data.solicitantes);
                        
                        // Mostrar mensaje
                        const alert = document.createElement('div');
                        alert.className = 'alert alert-success alert-dismissible fade show';
                        alert.innerHTML = `
                            ✓ Gráficas filtradas por rango: ${fechaDesde} a ${fechaHasta}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        `;
                        document.querySelector('.container').insertBefore(alert, document.querySelector('.row').nextSibling);
                        setTimeout(() => alert.remove(), 4000);
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error cargando datos:', error);
                    alert('Error al cargar los datos');
                });
        }

        function limpiarFiltroPersonalizado() {
            document.getElementById('fecha_desde_grafico').value = '';
            document.getElementById('fecha_hasta_grafico').value = '';
            cargarDatos('todo');
        }

        // Validar fechas en formulario PDF
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const fechaDesde = document.querySelector('[name="fecha_desde"]');
                const fechaHasta = document.querySelector('[name="fecha_hasta"]');
                
                if (fechaDesde && fechaHasta) {
                    const desde = fechaDesde.value;
                    const hasta = fechaHasta.value;
                    
                    if (desde && hasta && desde > hasta) {
                        e.preventDefault();
                        alert('La fecha "Desde" no puede ser mayor que la fecha "Hasta"');
                        return false;
                    }
                }
            });
        });

        // Inicializar al cargar
        document.addEventListener('DOMContentLoaded', inicializarGraficas);
    </script>
</body>
</html>
