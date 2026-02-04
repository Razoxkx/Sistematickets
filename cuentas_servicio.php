<?php
session_start();
require_once 'includes/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

// Verificar permisos: admin y tisupport
$permisos = ['admin', 'tisupport'];
if (!in_array($_SESSION["role"] ?? "", $permisos)) {
    header("Location: dashboard.php");
    exit();
}

$error = "";
$success = "";
$cuentas = [];
$busqueda = $_GET["buscar"] ?? "";

// Capturar mensaje de éxito
if (isset($_GET["success"])) {
    if ($_GET["success"] === "creado") {
        $success = "✅ Cuenta de servicio creada exitosamente";
    } elseif ($_GET["success"] === "actualizado") {
        $success = "✅ Cuenta de servicio actualizada exitosamente";
    } elseif ($_GET["success"] === "eliminado") {
        $success = "✅ Cuenta de servicio eliminada exitosamente";
    }
}

// Procesar eliminación
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["eliminar_cuenta"])) {
    $cuenta_id = $_POST["cuenta_id"] ?? "";
    
    if (!empty($cuenta_id)) {
        try {
            $stmt = $conexion->prepare("DELETE FROM cuentas_servicio WHERE id = ?");
            $stmt->execute([$cuenta_id]);
            header("Location: cuentas_servicio.php?success=eliminado");
            exit();
        } catch (PDOException $e) {
            $error = "Error al eliminar la cuenta: " . $e->getMessage();
        }
    }
}

// Obtener todas las cuentas con búsqueda
try {
    $where = "1=1";
    $params = [];
    
    if (!empty($busqueda)) {
        $where .= " AND (c.plataforma LIKE ? OR c.correo LIKE ? OR c.descripcion LIKE ? OR u.username LIKE ?)";
        $busqueda_param = '%' . $busqueda . '%';
        $params[] = $busqueda_param;
        $params[] = $busqueda_param;
        $params[] = $busqueda_param;
        $params[] = $busqueda_param;
    }
    
    $stmt = $conexion->prepare("
        SELECT c.*, u.username as creador_nombre
        FROM cuentas_servicio c
        JOIN users u ON c.usuario_creador = u.id
        WHERE " . $where . "
        ORDER BY c.fecha_creacion DESC
    ");
    $stmt->execute($params);
    $cuentas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al obtener las cuentas: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es" id="htmlRoot">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="css/dark-mode.css" rel="stylesheet">
    <title>Cuentas de Servicio</title>
    <style>
        body {
            background: linear-gradient(to bottom, #f8f9fa, #ffffff);
        }
        
        [data-bs-theme="dark"] body {
            background: linear-gradient(to bottom, #1a1a1a, #0d0d0d);
        }

        /* Contenedor principal responsivo */
        .contenedor-principal {
            margin-top: 20px;
            padding-left: 0;
            padding-right: 0;
        }

        /* Sidebar adjustments */
        body {
            margin-left: 280px;
        }

        @media (max-width: 768px) {
            body {
                margin-left: 70px;
            }

            .contenedor-principal {
                margin-top: 15px;
            }
        }

        .cuenta-card {
            
        }

        .cuenta-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .password-container {
            position: relative;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .password-input {
            font-family: 'Courier New', monospace;
            letter-spacing: 3px;
        }

        .btn-revelar {
            padding: 6px 12px;
            font-size: 0.85rem;
            flex-shrink: 0;
        }

        .modal-password-input {
            font-size: 1rem;
        }

        /* Buscador */
        .search-container {
            position: relative;
            margin-bottom: 20px;
        }

        .search-input {
            padding-left: 40px;
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            pointer-events: none;
        }

        /* Header responsivo */
        .header-cuentas {
            flex-wrap: wrap;
            gap: 10px;
            margin-right: 0 !important;
        }

        .header-cuentas h1 {
            margin-bottom: 5px;
            margin-right: 0;
            font-size: clamp(1.5rem, 4vw, 1.8rem);
        }

        .header-cuentas .text-muted {
            font-size: clamp(0.9rem, 2vw, 0.95rem);
            margin-right: 0;
        }

        /* Cards responsivas */
        @media (max-width: 1200px) {
            .row .col-lg-4 {
                flex: 0 0 auto;
                width: 50%;
            }
        }

        @media (max-width: 768px) {
            .row .col-md-6, 
            .row .col-lg-4,
            .row .col-12 {
                flex: 0 0 auto;
                width: 100%;
            }

            .header-cuentas {
                flex-direction: column;
                margin-right: 0 !important;
            }

            .header-cuentas .col-md-8,
            .header-cuentas .col-md-4 {
                width: 100% !important;
            }

            .header-cuentas .text-end {
                text-align: left !important;
            }

            .password-container {
                flex-wrap: wrap;
            }

            .btn-revelar {
                padding: 8px 10px;
            }
        }

        /* Input de búsqueda responsivo */
        .search-input {
            width: 100%;
            font-size: 1rem;
        }

        @media (max-width: 768px) {
            .search-container {
                margin-bottom: 15px;
            }
        }

        /* Ajustes para las columnas */
        .col-lg-4 {
            padding-right: 10px;
            padding-left: 0;
        }

        .col-md-6 {
            padding-right: 10px;
            padding-left: 0;
        }

        .col-12 {
            padding-left: 0;
            padding-right: 5px;
        }

        .row {
            margin-right: 0 !important;
            margin-left: 0 !important;
        }

        /* Container adjustments */
        .container-fluid {
            padding-left: 20px !important;
            padding-right: 20px !important;
        }

        @media (max-width: 768px) {
            .container-fluid {
                padding-left: 15px !important;
                padding-right: 15px !important;
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
    
    <div class="container-fluid contenedor-principal">
        <!-- Header -->
        <div class="row header-cuentas mb-4">
            <div class="col-12">
                <h1><i class="bi bi-key-fill"></i> Cuentas de Servicio</h1>
                <p class="text-muted">Catálogo centralizado de credenciales de servicios</p>
            </div>
        </div>

        <!-- Botón Nueva Cuenta -->
        <div class="row mb-4">
            <div class="col-12">
                <a href="crear_cuenta_servicio.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Nueva Cuenta
                </a>
            </div>
        </div>

        <!-- Buscador -->
        <?php if (!empty($cuentas)): ?>
        <div class="row mb-4">
            <div class="col-12 col-lg-6">
                <div class="search-container">
                    <i class="bi bi-search search-icon"></i>
                    <input 
                        type="text" 
                        class="form-control search-input" 
                        id="inputBusqueda" 
                        placeholder="Buscar por plataforma, correo, descripción o usuario..."
                        value="<?php echo htmlspecialchars($busqueda); ?>"
                        onkeyup="filtrarCuentas()"
                    >
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Alertas -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Contenido -->
        <?php if (empty($cuentas)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> No hay cuentas de servicio registradas aún.
                <a href="crear_cuenta_servicio.php" class="alert-link">Crear la primera</a>
            </div>
        <?php else: ?>
            <div class="row" id="containerCuentas">
                <?php foreach ($cuentas as $cuenta): ?>
                    <div class="col-12 col-md-6 col-lg-4 mb-4 cuenta-item" data-busqueda="<?php echo htmlspecialchars(strtolower($cuenta["plataforma"] . ' ' . $cuenta["correo"] . ' ' . $cuenta["descripcion"] . ' ' . $cuenta["creador_nombre"])); ?>">
                        <div class="card cuenta-card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="flex-grow-1">
                                        <h5 class="card-title mb-1"><?php echo htmlspecialchars($cuenta["plataforma"]); ?></h5>
                                        <small class="text-muted">
                                            <i class="bi bi-person"></i> <?php echo htmlspecialchars($cuenta["creador_nombre"]); ?>
                                        </small>
                                    </div>
                                    <span class="badge bg-info flex-shrink-0 ms-2"><?php echo htmlspecialchars(substr($cuenta["plataforma"], 0, 3)); ?></span>
                                </div>

                                <hr>

                                <!-- Correo -->
                                <div class="mb-3">
                                    <label class="form-label small text-muted mb-1">
                                        <i class="bi bi-envelope"></i> Correo o usuario
                                    </label>
                                    <input type="text" class="form-control form-control-sm" value="<?php echo htmlspecialchars($cuenta["correo"]); ?>" readonly>
                                </div>

                                <!-- Contraseña -->
                                <div class="mb-3">
                                    <label class="form-label small text-muted mb-1">
                                        <i class="bi bi-lock"></i> Contraseña
                                    </label>
                                    <div class="password-container">
                                        <input type="password" class="form-control form-control-sm password-input flex-grow-1" value="<?php echo htmlspecialchars($cuenta["contraseña"]); ?>" readonly id="pwd_<?php echo $cuenta["id"]; ?>">
                                        <button type="button" class="btn btn-outline-secondary btn-revelar" onclick="revelarPassword(<?php echo $cuenta["id"]; ?>)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Descripción (si existe) -->
                                <?php if (!empty($cuenta["descripcion"])): ?>
                                    <div class="mb-3">
                                        <label class="form-label small text-muted mb-1">
                                            <i class="bi bi-file-text"></i> Descripción
                                        </label>
                                        <small class="d-block" style="word-wrap: break-word;">
                                            <?php echo htmlspecialchars(substr($cuenta["descripcion"], 0, 100)); ?>
                                            <?php if (strlen($cuenta["descripcion"]) > 100): ?>
                                                ...
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                <?php endif; ?>

                                <!-- Fecha de creación -->
                                <small class="text-muted d-block mb-3">
                                    <i class="bi bi-calendar"></i> 
                                    <?php echo formatearFechaHora($cuenta["fecha_creacion"]); ?>
                                </small>

                                <hr>

                                <!-- Botones de acción -->
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalAutenticacion" onclick="document.getElementById('cuentaIdAutenticacion').value = <?php echo $cuenta['id']; ?>; document.getElementById('passwordAutenticacion').value = '';">
                                        <i class="bi bi-pencil"></i> Editar
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="confirmarEliminar(<?php echo $cuenta["id"]; ?>)">
                                        <i class="bi bi-trash"></i> Eliminar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Mensaje cuando no hay resultados de búsqueda -->
            <div id="noResults" class="alert alert-info d-none">
                <i class="bi bi-search"></i> No se encontraron cuentas que coincidan con tu búsqueda.
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal para ingresar contraseña para revelar -->
    <div class="modal fade" id="modalPassword" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-shield-lock"></i> Verificación de Seguridad
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Para revelar esta contraseña, debes ingresar tu contraseña:</p>
                    <div class="mb-3">
                        <label for="adminPassword" class="form-label">Tu Contraseña</label>
                        <input type="password" class="form-control modal-password-input" id="adminPassword" placeholder="Ingresa tu contraseña">
                    </div>
                    <div id="errorMensaje" class="alert alert-danger d-none" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="confirmarPassword()">Revelar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para confirmar eliminación -->
    <div class="modal fade" id="modalEliminar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle"></i> Confirmar Eliminación
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro que desea eliminar esta cuenta de servicio? Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="cuenta_id" id="cuentaIdEliminar">
                        <input type="hidden" name="eliminar_cuenta" value="1">
                        <button type="submit" class="btn btn-danger">Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let cuentaIdActual = null;
        const modalPassword = new bootstrap.Modal(document.getElementById('modalPassword'));
        const modalEliminar = new bootstrap.Modal(document.getElementById('modalEliminar'));

        function revelarPassword(cuentaId) {
            cuentaIdActual = cuentaId;
            document.getElementById('adminPassword').value = '';
            document.getElementById('errorMensaje').classList.add('d-none');
            modalPassword.show();
        }

        function confirmarPassword() {
            const adminPassword = document.getElementById('adminPassword').value;

            if (!adminPassword) {
                mostrarError('Por favor ingresa tu contraseña');
                return;
            }

            const formData = new FormData();
            formData.append('cuenta_id', cuentaIdActual);
            formData.append('admin_password', adminPassword);

            fetch('api_revelar_password.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        throw new Error(data.error || 'Error desconocido');
                    });
                }
                return response.json();
            })
            .then(data => {
                // Cambiar el tipo del input a text para mostrar la contraseña
                const inputPassword = document.getElementById('pwd_' + cuentaIdActual);
                inputPassword.type = 'text';
                inputPassword.value = data.contraseña;

                // Cambiar el botón a ocultar
                const botonRevelar = inputPassword.nextElementSibling;
                botonRevelar.innerHTML = '<i class="bi bi-eye-slash"></i>';
                botonRevelar.onclick = function() {
                    ocultarPassword(cuentaIdActual);
                };

                modalPassword.hide();
            })
            .catch(error => {
                mostrarError(error.message);
            });
        }

        function ocultarPassword(cuentaId) {
            const inputPassword = document.getElementById('pwd_' + cuentaId);
            inputPassword.type = 'password';

            const botonRevelar = inputPassword.nextElementSibling;
            botonRevelar.innerHTML = '<i class="bi bi-eye"></i>';
            botonRevelar.onclick = function() {
                revelarPassword(cuentaId);
            };
        }

        function mostrarError(mensaje) {
            const errorDiv = document.getElementById('errorMensaje');
            errorDiv.textContent = mensaje;
            errorDiv.classList.remove('d-none');
        }

        function confirmarEliminar(cuentaId) {
            document.getElementById('cuentaIdEliminar').value = cuentaId;
            modalEliminar.show();
        }

        // Función para filtrar cuentas
        function filtrarCuentas() {
            const busqueda = document.getElementById('inputBusqueda').value.toLowerCase().trim();
            const cuentas = document.querySelectorAll('.cuenta-item');
            const noResults = document.getElementById('noResults');
            let visibles = 0;

            cuentas.forEach(cuenta => {
                const datos = cuenta.getAttribute('data-busqueda');
                if (datos.includes(busqueda) || busqueda === '') {
                    cuenta.style.display = '';
                    visibles++;
                } else {
                    cuenta.style.display = 'none';
                }
            });

            // Mostrar mensaje de no resultados
            if (visibles === 0 && busqueda !== '') {
                noResults.classList.remove('d-none');
            } else {
                noResults.classList.add('d-none');
            }
        }

        // Función para toggle dark mode
        function toggleDarkMode() {
            const htmlRoot = document.documentElement;
            const isDark = htmlRoot.getAttribute('data-bs-theme') === 'dark';
            
            if (isDark) {
                htmlRoot.removeAttribute('data-bs-theme');
                localStorage.setItem('darkMode', 'disabled');
            } else {
                htmlRoot.setAttribute('data-bs-theme', 'dark');
                localStorage.setItem('darkMode', 'enabled');
            }
        }

        // Función para autenticar y editar cuenta
        function autenticarYEditar() {
            const cuentaId = document.getElementById('cuentaIdAutenticacion').value;
            const password = document.getElementById('passwordAutenticacion').value;
            
            if (!password.trim()) {
                alert('Por favor ingresa tu contraseña');
                return;
            }
            
            fetch('api_verificar_contraseña.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    password: password
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Contraseña correcta, redirigir a editar
                    window.location.href = 'editar_cuenta_servicio.php?id=' + cuentaId;
                    // Cerrar modal
                    bootstrap.Modal.getInstance(document.getElementById('modalAutenticacion')).hide();
                } else {
                    alert('Contraseña incorrecta');
                    document.getElementById('passwordAutenticacion').value = '';
                    document.getElementById('passwordAutenticacion').focus();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al verificar la contraseña');
            });
        }

        // Permitir Enter para confirmar contraseña cuando el modal está abierto
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('passwordAutenticacion');
            if (passwordInput) {
                passwordInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        autenticarYEditar();
                    }
                });
            }
        });
    </script>

    <!-- Modal de Autenticación -->
    <div class="modal fade" id="modalAutenticacion" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="bi bi-shield-lock"></i> Confirmar Contraseña</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Por seguridad, debes confirmar tu contraseña de administrador para editar esta cuenta.</p>
                    <input type="hidden" id="cuentaIdAutenticacion">
                    <div class="mb-3">
                        <label for="passwordAutenticacion" class="form-label">Tu Contraseña</label>
                        <input type="password" class="form-control" id="passwordAutenticacion" placeholder="Ingresa tu contraseña" autocomplete="off">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-warning" onclick="autenticarYEditar()">
                        <i class="bi bi-check-circle"></i> Confirmar
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
