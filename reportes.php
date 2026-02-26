<?php
session_start();
require_once 'includes/config.php';

// Prevenir cacheo
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Verificar sesión
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

// ==================== OBTENER OPCIONES DE FILTRO ====================
$solicitantes_lista = [];
$responsables_lista = [];

try {
    // Solicitantes únicos
    $stmt = $conexion->query("SELECT DISTINCT nombre_solicitante FROM tickets WHERE nombre_solicitante IS NOT NULL AND nombre_solicitante != '' ORDER BY nombre_solicitante");
    $solicitantes_lista = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $solicitantes_lista = [];
}

try {
    // Responsables únicos (usernames desde users table, joined con tickets que tienen asignación)
    $stmt = $conexion->query("
        SELECT DISTINCT u.username 
        FROM tickets t
        LEFT JOIN users u ON t.responsable = u.id
        WHERE t.responsable IS NOT NULL AND u.username IS NOT NULL
        ORDER BY u.username
    ");
    $responsables_lista = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $responsables_lista = [];
}

// ==================== DATOS PARA GRÁFICOS INICIALES ====================

// 1. Tickets por estado (abiertos, cerrados, en proceso)
try {
    $stmt = $conexion->query("
        SELECT estado, COUNT(*) as cantidad 
        FROM tickets 
        GROUP BY estado 
        ORDER BY cantidad DESC
    ");
    $datos_estados = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $datos_estados = [];
}

// 2. Top 5 solicitantes del mes en curso
try {
    $stmt = $conexion->query("
        SELECT nombre_solicitante, COUNT(*) as total 
        FROM tickets 
        WHERE YEAR(fecha_creacion) = YEAR(NOW()) AND MONTH(fecha_creacion) = MONTH(NOW())
        GROUP BY nombre_solicitante 
        ORDER BY total DESC 
        LIMIT 5
    ");
    $top5_solicitantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $top5_solicitantes = [];
}

// 3. Ranking de técnicos (responsables con más tickets)
try {
    $stmt = $conexion->query("
        SELECT u.username as responsable, COUNT(*) as total 
        FROM tickets t
        LEFT JOIN users u ON t.responsable = u.id
        WHERE t.responsable IS NOT NULL
        GROUP BY t.responsable, u.username
        ORDER BY total DESC 
        LIMIT 10
    ");
    $ranking_tecnicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $ranking_tecnicos = [];
}

// 4. Estadísticas generales
try {
    $stmt = $conexion->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'ticket cerrado' THEN 1 ELSE 0 END) as cerrados,
            SUM(CASE WHEN estado != 'ticket cerrado' THEN 1 ELSE 0 END) as abiertos,
            SUM(CASE WHEN estado = 'en proceso' THEN 1 ELSE 0 END) as en_proceso
        FROM tickets
    ");
    $stats_generales = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats_generales = ['total' => 0, 'cerrados' => 0, 'abiertos' => 0, 'en_proceso' => 0];
}


?>
<!DOCTYPE html>
<html lang="es" id="htmlRoot">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/dark-mode.css" rel="stylesheet">
    <title>Reportes</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.js"></script>
    <style>
        h1, h2, h3 {
            color: #8b9dff;
            font-weight: 700;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        h2 { font-size: 1.75rem; margin-bottom: 30px; }
        
        .stat-card {
            border-radius: 12px;
            padding: 20px;
            color: white;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .stat-total {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-cerrados {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stat-abiertos {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stat-proceso {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .filtro-section {
            background-color: rgba(139, 157, 255, 0.1);
            border: 1px solid #8b9dff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .btn-grupo {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }

        .tabla-ranking {
            font-size: 0.95rem;
        }

        .tabla-ranking th {
            background-color: #667eea;
            color: white;
            font-weight: 600;
        }

        .tabla-ranking tbody tr {
            border-bottom: 1px solid #ddd;
        }

        .tabla-ranking tbody tr:hover {
            background-color: rgba(139, 157, 255, 0.1);
        }

        .grafica-container {
            position: relative;
            height: 300px;
            margin-bottom: 30px;
        }

        .card-header {
            background-color: #667eea;
            color: white;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .btn-grupo {
                grid-template-columns: repeat(2, 1fr);
            }
            .stat-number {
                font-size: 2rem;
            }
        }
    </style>
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
    
    <div class="container-fluid mt-5 mb-5">
        <!-- Encabezado -->
        <div class="row mb-4">
            <div class="col-md-12">
                <h2>📊 Reportes y Gráficas</h2>
            </div>
        </div>

        <!-- TARJETAS DE ESTADÍSTICAS -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card stat-total">
                    <div class="stat-number"><?php echo $stats_generales['total']; ?></div>
                    <div class="stat-label">Total de Tickets</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card stat-cerrados">
                    <div class="stat-number"><?php echo $stats_generales['cerrados']; ?></div>
                    <div class="stat-label">Tickets Cerrados</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card stat-abiertos">
                    <div class="stat-number"><?php echo $stats_generales['abiertos']; ?></div>
                    <div class="stat-label">Tickets Abiertos</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card stat-proceso">
                    <div class="stat-number"><?php echo $stats_generales['en_proceso']; ?></div>
                    <div class="stat-label">En Proceso</div>
                </div>
            </div>
        </div>

        <!-- SECCIÓN DE FILTROS -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="filtro-section">
                    <h5 class="mb-3"><i class="bi bi-funnel"></i> Filtros Avanzados</h5>
                    
                    <form id="formFiltros" class="row g-3">
                        <div class="col-md-3">
                            <label for="filtro_estado" class="form-label">Estado</label>
                            <select class="form-select" id="filtro_estado" name="estado">
                                <option value="">-- Todos --</option>
                                <option value="sin abrir">Sin abrir</option>
                                <option value="en conocimiento">En conocimiento</option>
                                <option value="en proceso">En proceso</option>
                                <option value="pendiente de cierre">Pendiente de cierre</option>
                                <option value="ticket cerrado">Ticket Cerrado</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="filtro_solicitante" class="form-label">Solicitante</label>
                            <select class="form-select" id="filtro_solicitante" name="solicitante">
                                <option value="">-- Todos --</option>
                                <?php foreach ($solicitantes_lista as $sol): ?>
                                    <option value="<?php echo htmlspecialchars($sol); ?>">
                                        <?php echo htmlspecialchars($sol); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="filtro_responsable" class="form-label">Responsable</label>
                            <select class="form-select" id="filtro_responsable" name="responsable">
                                <option value="">-- Todos --</option>
                                <?php foreach ($responsables_lista as $resp): ?>
                                    <option value="<?php echo htmlspecialchars($resp); ?>">
                                        <?php echo htmlspecialchars($resp); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="filtro_fecha_desde" class="form-label">Desde</label>
                            <input type="date" class="form-control" id="filtro_fecha_desde" name="fecha_desde">
                        </div>

                        <div class="col-md-3">
                            <label for="filtro_fecha_hasta" class="form-label">Hasta</label>
                            <input type="date" class="form-control" id="filtro_fecha_hasta" name="fecha_hasta">
                        </div>

                        <div class="col-md-9">
                            <div class="btn-grupo">
                                <button type="button" class="btn btn-primary" onclick="aplicarFiltros()">
                                    <i class="bi bi-search"></i> Aplicar Filtros
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="limpiarFiltros()">
                                    <i class="bi bi-x"></i> Limpiar
                                </button>
                                <button type="button" class="btn btn-info" onclick="descargarPDFPersonalizado()">
                                    <i class="bi bi-file-pdf"></i> PDF Filtrado
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Botones de descarga rápida -->
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <h6 class="mb-2">📥 Descargas Rápidas</h6>
                            <div class="btn-grupo" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));">
                                <button type="button" class="btn btn-success" onclick="descargarPDFMes()">
                                    <i class="bi bi-calendar3"></i> PDF Mes Actual
                                </button>
                                <button type="button" class="btn btn-warning" onclick="descargarPDF7Dias()">
                                    <i class="bi bi-calendar-event"></i> PDF Últimos 7 Días
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- GRÁFICO PRINCIPAL -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Estados de Tickets</h5>
                    </div>
                    <div class="card-body">
                        <div class="grafica-container">
                            <canvas id="chartEstados"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estadísticas de mes -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-calendar3"></i> Mes en Curso</h5>
                    </div>
                    <div class="card-body">
                        <div id="mesActualStats">
                            <div class="alert alert-info">Cargando...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TOP 5 SOLICITANTES -->
        <div class="row mb-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-people"></i> Top 5 Solicitantes (Mes Actual)</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="chartTop5Solicitantes" class="grafica-container"></canvas>
                    </div>
                </div>
            </div>

            <!-- Ranking de técnicos -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-person-badge"></i> Top 10 Técnicos (Responsables)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive tabla-ranking" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-sm table-hover">
                                <thead class="sticky-top">
                                    <tr>
                                        <th>#</th>
                                        <th>Técnico</th>
                                        <th>Tickets</th>
                                        <th>%</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaRankingTecnicos">
                                    <?php
                                    $total_tecnico = array_sum(array_column($ranking_tecnicos, 'total'));
                                    foreach ($ranking_tecnicos as $i => $tecnico):
                                        $porcentaje = $total_tecnico > 0 ? round(($tecnico['total'] / $total_tecnico) * 100, 1) : 0;
                                    ?>
                                        <tr>
                                            <td><span class="badge bg-primary"><?php echo $i + 1; ?></span></td>
                                            <td><strong><?php echo htmlspecialchars($tecnico['responsable']); ?></strong></td>
                                            <td><span class="badge bg-success"><?php echo $tecnico['total']; ?></span></td>
                                            <td><?php echo $porcentaje; ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABLA DE SOLICITANTES -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-list"></i> Detalle Top 5 Solicitantes (Mes Actual)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover tabla-ranking">
                                <thead>
                                    <tr>
                                        <th>Posición</th>
                                        <th>Solicitante</th>
                                        <th>Total Tickets</th>
                                        <th>Porcentaje</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaTop5">
                                    <!-- Se llena con JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-4 mb-4">
            <a href="tickets.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver a Tickets
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let chartEstados = null;
        let chartSolicitantes = null;

        // Datos iniciales
        const datosIniciales = {
            estados: <?php echo json_encode($datos_estados); ?>,
            top5: <?php echo json_encode($top5_solicitantes); ?>,
            tecnicos: <?php echo json_encode($ranking_tecnicos); ?>
        };

        // Inicializar gráficas al cargar
        document.addEventListener('DOMContentLoaded', function() {
            crearGraficaEstados(datosIniciales.estados);
            crearGraficaTop5(datosIniciales.top5);
            actualizarTablaTop5(datosIniciales.top5);
            cargarEstadisticasMes();
        });

        // Crear gráfica de estados (Doughnut)
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

        // Crear gráfica Top 5 (Bar)
        function crearGraficaTop5(datos) {
            const ctx = document.getElementById('chartTop5Solicitantes').getContext('2d');

            if (chartSolicitantes) {
                chartSolicitantes.destroy();
            }

            if (datos.length === 0) {
                ctx.font = '16px Arial';
                ctx.fillText('Sin datos disponibles', 50, 50);
                return;
            }

            const labels = datos.map(d => d.nombre_solicitante.substring(0, 12));
            const values = datos.map(d => d.total);

            chartSolicitantes = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Tickets Reportados',
                        data: values,
                        backgroundColor: '#667eea',
                        borderColor: '#4a5fc1',
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
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

        // Actualizar tabla Top 5
        function actualizarTablaTop5(datos) {
            const tbody = document.getElementById('tablaTop5');
            tbody.innerHTML = '';

            const total = datos.reduce((sum, d) => sum + d.total, 0);

            datos.forEach((sol, index) => {
                const porcentaje = total > 0 ? ((sol.total / total) * 100).toFixed(1) : 0;
                const row = `
                    <tr>
                        <td><span class="badge bg-primary">${index + 1}</span></td>
                        <td><strong>${sol.nombre_solicitante}</strong></td>
                        <td><span class="badge bg-success">${sol.total}</span></td>
                        <td>
                            <div class="progress" style="height: 20px; width: 150px;">
                                <div class="progress-bar" style="width: ${porcentaje}%;" role="progressbar">
                                    ${porcentaje}%
                                </div>
                            </div>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        }

        // Cargar estadísticas del mes
        function cargarEstadisticasMes() {
            fetch('api_reportes.php?accion=mes_actual')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const html = `
                            <table class="table table-sm">
                                <tr>
                                    <td>Total:</td>
                                    <td><strong>${data.total}</strong></td>
                                </tr>
                                <tr>
                                    <td>Abiertos:</td>
                                    <td><span class="badge bg-info">${data.abiertos}</span></td>
                                </tr>
                                <tr>
                                    <td>Cerrados:</td>
                                    <td><span class="badge bg-success">${data.cerrados}</span></td>
                                </tr>
                                <tr>
                                    <td>En Proceso:</td>
                                    <td><span class="badge bg-warning">${data.en_proceso}</span></td>
                                </tr>
                            </table>
                        `;
                        document.getElementById('mesActualStats').innerHTML = html;
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // Aplicar filtros
        function aplicarFiltros() {
            const estado = document.getElementById('filtro_estado').value;
            const solicitante = document.getElementById('filtro_solicitante').value;
            const responsable = document.getElementById('filtro_responsable').value;
            const fecha_desde = document.getElementById('filtro_fecha_desde').value;
            const fecha_hasta = document.getElementById('filtro_fecha_hasta').value;

            const params = new URLSearchParams({
                accion: 'filtros',
                estado: estado || '',
                solicitante: solicitante || '',
                responsable: responsable || '',
                fecha_desde: fecha_desde || '',
                fecha_hasta: fecha_hasta || ''
            });

            fetch(`api_reportes.php?${params}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        crearGraficaEstados(data.estados);
                        actualizarTablaTop5(data.top5);
                        
                        const toast = `<div class="alert alert-success alert-dismissible fade show" role="alert">
                            ✓ Filtros aplicados correctamente
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>`;
                        document.querySelector('.container-fluid').insertAdjacentHTML('afterbegin', toast);
                        setTimeout(() => document.querySelector('.alert')?.remove(), 3000);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // Limpiar filtros
        function limpiarFiltros() {
            document.getElementById('formFiltros').reset();
            crearGraficaEstados(datosIniciales.estados);
            crearGraficaTop5(datosIniciales.top5);
            actualizarTablaTop5(datosIniciales.top5);
        }

        // Descargar PDF personalizado
        function descargarPDFPersonalizado() {
            const estado = document.getElementById('filtro_estado').value;
            const solicitante = document.getElementById('filtro_solicitante').value;
            const responsable = document.getElementById('filtro_responsable').value;
            const fecha_desde = document.getElementById('filtro_fecha_desde').value;
            const fecha_hasta = document.getElementById('filtro_fecha_hasta').value;

            const params = new URLSearchParams({
                tipo: 'personalizado',
                estado: estado || '',
                solicitante: solicitante || '',
                responsable: responsable || '',
                fecha_desde: fecha_desde || '',
                fecha_hasta: fecha_hasta || ''
            });

            window.open(`generar_reporte_pdf.php?${params}`, '_blank');
        }

        // Descargar PDF mes actual
        function descargarPDFMes() {
            window.open('generar_reporte_pdf.php?tipo=mes_actual', '_blank');
        }

        // Descargar PDF últimos 7 días
        function descargarPDF7Dias() {
            window.open('generar_reporte_pdf.php?tipo=ultimos_7_dias', '_blank');
        }
    </script>
</body>
</html>
