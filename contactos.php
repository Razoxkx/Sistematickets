<?php
session_start();
require_once 'includes/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

// Verificar permisos: admin y tisupport pueden acceder
$permisos = ['admin', 'tisupport'];
if (!in_array($_SESSION["role"] ?? "", $permisos)) {
    header("Location: dashboard.php");
    exit();
}

$error = "";
$success = "";
$contactos = [];

// Capturar mensaje de éxito del query string
if (isset($_GET["success"])) {
    if ($_GET["success"] === "contacto_creado") {
        $success = "✅ Contacto creado exitosamente";
    } elseif ($_GET["success"] === "contacto_actualizado") {
        $success = "✅ Contacto actualizado exitosamente";
    } elseif ($_GET["success"] === "contacto_eliminado") {
        $success = "✅ Contacto eliminado exitosamente";
    }
}

// CONTACTOS - CREAR
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["accion_crear_contacto"])) {
    // Validar token CSRF
    if (!validarTokenCSRF()) {
        $error = "Sesión expirada. Por favor intenta de nuevo.";
    } else {
    $nombre_completo = trim($_POST["nombre_completo"] ?? "");
    $nombre_usuario = trim($_POST["nombre_usuario"] ?? "");
    $correo = trim($_POST["correo"] ?? "");
    $numero_telefono = trim($_POST["numero_telefono"] ?? "");
    $division_departamento = trim($_POST["division_departamento"] ?? "");
    $role = $_SESSION["role"] === "admin" ? trim($_POST["role"] ?? "contacto") : "contacto";
    
    if (empty($nombre_completo)) {
        $error = "El nombre completo es obligatorio";
    } else if (($_SESSION["role"] === "tisupport") && in_array($role, ['admin', 'tisupport'])) {
        // tisupport no puede crear usuarios con roles admin o tisupport
        $error = "No tienes permiso para asignar el rol " . traducirRol($role);
    } else {
        try {
            $stmt = $conexion->prepare("
                INSERT INTO users (nombre_completo, username, email, numero_telefono, dpto_division, role, password)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nombre_completo, $nombre_usuario, $correo, '', $division_departamento, $role, password_hash('', PASSWORD_BCRYPT)]);
            
            header("Location: contactos.php?success=contacto_creado");
            exit();
        } catch (PDOException $e) {
            // Intentar mostrar mensaje amigable para usuario duplicado
            $error_amigable = manejarErrorUsuarioDuplicado($e, $conexion);
            $error = $error_amigable ?? "Error al crear contacto: " . $e->getMessage();
        }
    }
    }
}

// CONTACTOS - EDITAR
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["accion_editar_contacto"])) {
    // Validar token CSRF
    if (!validarTokenCSRF()) {
        $error = "Sesión expirada. Por favor intenta de nuevo.";
    } else {
    $contacto_id = $_POST["contacto_id"] ?? "";
    $nombre_completo = trim($_POST["nombre_completo"] ?? "");
    $nombre_usuario = trim($_POST["nombre_usuario"] ?? "");
    $correo = trim($_POST["correo"] ?? "");
    $numero_telefono = trim($_POST["numero_telefono"] ?? "");
    $division_departamento = trim($_POST["division_departamento"] ?? "");
    $role = trim($_POST["role"] ?? "contacto");
    
    if (empty($nombre_completo)) {
        $error = "El nombre completo es obligatorio";
    } else if (($_SESSION["role"] === "tisupport") && in_array($role, ['admin', 'tisupport'])) {
        // tisupport no puede crear/editar usuarios con roles admin o tisupport
        $error = "No tienes permiso para asignar el rol " . traducirRol($role);
    } else {
        try {
            $stmt = $conexion->prepare("
    UPDATE users 
    SET nombre_completo = ?, username = ?, email = ?, numero_telefono = ?, dpto_division = ?, role = ?
    WHERE id = ?
");
$stmt->execute([$nombre_completo, $nombre_usuario, $correo, $numero_telefono, $division_departamento, $role, $contacto_id]);
            header("Location: contactos.php?success=contacto_actualizado");
            exit();
        } catch (PDOException $e) {
            // Intentar mostrar mensaje amigable para usuario duplicado
            $error_amigable = manejarErrorUsuarioDuplicado($e, $conexion);
            $error = $error_amigable ?? "Error al actualizar contacto: " . $e->getMessage();
        }
    }
    }
}

// CONTACTOS - ELIMINAR
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["accion_eliminar_contacto"])) {
    // Validar token CSRF
    if (!validarTokenCSRF()) {
        $error = "Sesión expirada. Por favor intenta de nuevo.";
    } else {
    $contacto_id = $_POST["contacto_id"] ?? "";
    if (!empty($contacto_id)) {
        // No permitir autoeliminación
        if ($contacto_id == $_SESSION["user_id"]) {
            $error = "No puedes eliminar tu propia cuenta";
        } else {
            try {
                // Solo admin puede eliminar otros usuarios admin/tisupport
                // Contactos pueden ser eliminados por anyone con permiso
                if ($_SESSION["role"] === "admin") {
                    $stmt = $conexion->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$contacto_id]);
                } else {
                    // tisupport solo puede eliminar contactos
                    $stmt = $conexion->prepare("DELETE FROM users WHERE id = ? AND role = 'contacto'");
                    $stmt->execute([$contacto_id]);
                }
                header("Location: contactos.php?success=contacto_eliminado");
                exit();
            } catch (PDOException $e) {
                $error = "Error al eliminar contacto: " . $e->getMessage();
            }
        }
    }
    }
}

// OBTENER LISTA DE CONTACTOS con paginación
$contactos = [];
$total_contactos = 0;
$contactos_por_pagina = 10;
$pagina_contactos = isset($_GET["pagina_contactos"]) ? (int)$_GET["pagina_contactos"] : 1;
$offset = ($pagina_contactos - 1) * $contactos_por_pagina;
$busqueda_contacto = isset($_GET["busqueda_contacto"]) ? trim($_GET["busqueda_contacto"]) : "";

try {
    // Contar total de contactos y usuarios
    if (!empty($busqueda_contacto)) {
        $stmt = $conexion->prepare(
            "SELECT COUNT(*) as total FROM users WHERE (nombre_completo LIKE ? OR username LIKE ? OR email LIKE ? OR dpto_division LIKE ?)"
        );
        $search_term = "%{$busqueda_contacto}%";
        $stmt->execute([$search_term, $search_term, $search_term, $search_term]);
    } else {
        $stmt = $conexion->prepare("SELECT COUNT(*) as total FROM users");
        $stmt->execute();
    }
    $total_contactos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Obtener contactos y usuarios con paginación desde users
if (!empty($busqueda_contacto)) {
    $stmt = $conexion->prepare(
        "SELECT id, nombre_completo, username AS nombre_usuario, email AS correo, numero_telefono, dpto_division AS division_departamento, role
         FROM users
         WHERE (nombre_completo LIKE ? OR username LIKE ? OR email LIKE ? OR dpto_division LIKE ?)
         ORDER BY nombre_completo
         LIMIT {$contactos_por_pagina} OFFSET {$offset}"
    );
    $search_term = "%{$busqueda_contacto}%";
    $stmt->execute([$search_term, $search_term, $search_term, $search_term]);
} else {
    // Consulta SIN búsqueda
    $stmt = $conexion->prepare("SELECT id, nombre_completo, username AS nombre_usuario, email AS correo, numero_telefono, dpto_division AS division_departamento, role FROM users ORDER BY nombre_completo LIMIT {$contactos_por_pagina} OFFSET {$offset}");
    $stmt->execute();
}
$contactos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al obtener contactos: " . $e->getMessage();
}

$total_paginas_contactos = ceil($total_contactos / $contactos_por_pagina);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="css/dark-mode.css" rel="stylesheet">
    <title>Gestión de Contactos</title>
    <style>
        h1, h2, h3 {
            color: #8b9dff;
            font-weight: 700;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        h2 {
            font-size: 1.75rem;
        }
        
        /* Optimizaciones para responsividad en tablets */
        .btn-accion-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 1rem;
        }
        
        .btn-accion-container .btn {
            padding: 8px 12px;
            font-size: 13px;
            white-space: nowrap;
            flex: 0 1 auto;
        }
        
        /* Tablet Landscape (MD: 768px-991px) */
        @media (max-width: 991px) and (orientation: landscape) {
            .container {
                padding-left: 10px;
                padding-right: 10px;
            }
            
            h2, h3 {
                font-size: 1.5rem;
            }
            
            .btn-accion-container .btn {
                padding: 6px 10px;
                font-size: 11px;
                flex: 0 1 calc(50% - 4px);
            }
        }
        
        /* Tablet Portrait o muy angosto (max-width: 991px) */
        @media (max-width: 991px) {
            .container {
                padding-left: 8px;
                padding-right: 8px;
            }
            
            h2, h3 {
                font-size: 1.5rem;
            }
            
            .btn-accion-container {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                gap: 8px;
                margin-bottom: 1rem;
            }
            
            .btn-accion-container .btn {
                padding: 6px 8px;
                font-size: 11px;
                width: 100%;
            }
            
            table {
                font-size: 13px;
            }
            
            table th, table td {
                padding: 10px 6px;
            }
            
            .btn-sm {
                padding: 4px 8px;
                font-size: 11px;
            }
            
            .badge {
                font-size: 11px;
                padding: 4px 6px;
            }
            
            input[type="text"],
            input[type="email"],
            select {
                font-size: 13px;
                padding: 6px 8px;
            }
            
            .form-control, .form-select {
                font-size: 13px;
            }
        }
        
        /* Tablets pequeñas/Móvil (max-width: 768px) */
        @media (max-width: 768px) {
            .btn-accion-container {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 6px;
            }
            
            .btn-accion-container .btn {
                padding: 5px 6px;
                font-size: 10px;
                width: 100%;
            }
            
            table thead {
                font-size: 12px;
            }
        }
        
        /* Muy pequeño */
        @media (max-width: 600px) {
            .btn-accion-container {
                grid-template-columns: 1fr;
            }
        }
        
        .table-responsive {
            -webkit-overflow-scrolling: touch;
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
    
    <div class="container mt-5">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3>Gestión de Contactos y Usuarios</h3>
                </div>
                
                <!-- Botones de acción y búsqueda -->
                <div class="btn-accion-container">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrearContacto">
                        <i class="bi bi-plus-circle"></i> Crear Nuevo Contacto
                    </button>
                    <div class="flex-grow-1">
                        <input type="text" class="form-control" id="busquedaContactos" placeholder="🔍 Buscar contactos..." onkeyup="buscarContactos()">
                    </div>
                </div>
                
                <!-- Tabla de Contactos -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">📋 Contactos y Usuarios del Sistema (<?php echo $total_contactos; ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped" id="tablaContactos">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Nombre Completo</th>
                                        <th>Usuario</th>
                                        <th>Correo</th>
                                        <th>Teléfono</th>
                                        <th>División/Departamento</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($contactos) > 0): ?>
                                        <?php foreach ($contactos as $contacto): ?>
                                        <tr data-contacto='<?php echo htmlspecialchars(json_encode($contacto)); ?>'>
                                            <td><strong><?php echo htmlspecialchars($contacto["nombre_completo"] ?? "N/A"); ?></strong></td>
                                            <td><?php echo htmlspecialchars($contacto["nombre_usuario"] ?? "N/A"); ?></td>
                                            <td><?php echo htmlspecialchars($contacto["correo"] ?? "N/A"); ?></td>
                                            <td><?php echo htmlspecialchars($contacto["numero_telefono"] ?? "N/A"); ?></td>
                                            <td><?php echo htmlspecialchars($contacto["division_departamento"] ?? "N/A"); ?></td>
                                            <td>
                                                <?php if ($contacto["role"] === "contacto"): ?>
                                                    <a href="perfil_contacto.php?username=<?php echo urlencode($contacto["nombre_usuario"]); ?>" class="btn btn-sm btn-outline-info" title="Ver Perfil">
                                                        <i class="bi bi-person-circle"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="perfil_usuario.php?username=<?php echo urlencode($contacto["nombre_usuario"]); ?>" class="btn btn-sm btn-outline-info" title="Ver Perfil">
                                                        <i class="bi bi-person-circle"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($contacto["role"] === "contacto" || ($_SESSION["role"] === "admin")): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-warning" title="Editar" data-bs-toggle="modal" data-bs-target="#modalEditarContacto" onclick="cargarDatosEditarContacto(<?php echo htmlspecialchars(json_encode($contacto)); ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if (($contacto["role"] === "contacto" || ($_SESSION["role"] === "admin")) && $contacto["id"] != $_SESSION["user_id"]): ?>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro?');">
                                                        <input type="hidden" name="contacto_id" value="<?php echo $contacto["id"]; ?>">
                                                        <input type="hidden" name="accion_eliminar_contacto" value="1">
                                                        <?php echo inputTokenCSRF(); ?>
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                <?php echo !empty($busqueda_contacto) ? "No se encontraron contactos o usuarios" : "No hay contactos o usuarios registrados aún"; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación -->
                        <?php if ($total_paginas_contactos > 1): ?>
                        <nav aria-label="Paginación de contactos" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($pagina_contactos > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="contactos.php?pagina_contactos=1<?php echo !empty($busqueda_contacto) ? '&busqueda_contacto=' . urlencode($busqueda_contacto) : ''; ?>">Primera</a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="contactos.php?pagina_contactos=<?php echo $pagina_contactos - 1; ?><?php echo !empty($busqueda_contacto) ? '&busqueda_contacto=' . urlencode($busqueda_contacto) : ''; ?>">Anterior</a>
                                    </li>
                                <?php endif; ?>

                                <?php 
                                $start_page = max(1, $pagina_contactos - 2);
                                $end_page = min($total_paginas_contactos, $pagina_contactos + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++): 
                                ?>
                                    <li class="page-item <?php echo $i === $pagina_contactos ? 'active' : ''; ?>">
                                        <a class="page-link" href="contactos.php?pagina_contactos=<?php echo $i; ?><?php echo !empty($busqueda_contacto) ? '&busqueda_contacto=' . urlencode($busqueda_contacto) : ''; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($pagina_contactos < $total_paginas_contactos): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="contactos.php?pagina_contactos=<?php echo $pagina_contactos + 1; ?><?php echo !empty($busqueda_contacto) ? '&busqueda_contacto=' . urlencode($busqueda_contacto) : ''; ?>">Siguiente</a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="contactos.php?pagina_contactos=<?php echo $total_paginas_contactos; ?><?php echo !empty($busqueda_contacto) ? '&busqueda_contacto=' . urlencode($busqueda_contacto) : ''; ?>">Última</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Crear Contacto -->
    <div class="modal fade" id="modalCrearContacto" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Crear Nuevo Contacto</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="accion_crear_contacto" value="1">
                    <?php echo inputTokenCSRF(); ?>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="nombre_completo_new" class="form-label">Nombre Completo *</label>
                            <input type="text" class="form-control" id="nombre_completo_new" name="nombre_completo" required placeholder="ej: Juan García López">
                        </div>
                        <div class="mb-3">
                            <label for="nombre_usuario_new" class="form-label">Nombre de Usuario</label>
                            <input type="text" class="form-control" id="nombre_usuario_new" name="nombre_usuario" placeholder="ej: jgarcia">
                        </div>
                        <div class="mb-3">
                            <label for="correo_new" class="form-label">Correo</label>
                            <input type="email" class="form-control" id="correo_new" name="correo" placeholder="ej: juan.garcia@empresa.com">
                        </div>
                        <div class="mb-3">
                            <label for="numero_telefono_new" class="form-label">Número de Teléfono</label>
                            <input type="tel" class="form-control" id="numero_telefono_new" name="numero_telefono" placeholder="ej: +1 234 567 8900">
                        </div>
                        <div class="mb-3">
                            <label for="division_departamento_new" class="form-label">División/Departamento</label>
                            <input type="text" class="form-control" id="division_departamento_new" name="division_departamento" placeholder="ej: Recursos Humanos">
                        </div>
                        <?php if ($_SESSION["role"] === "admin"): ?>
                        <div class="mb-3">
                            <label for="role_new_contacto" class="form-label">Rol</label>
                            <select class="form-select" id="role_new_contacto" name="role">
                                <option value="contacto" selected>📇 Contacto</option>
                                <option value="viewer">👁️ Lector</option>
                                <option value="tisupport">🔧 Soporte TI</option>
                                <option value="admin">🔑 Administrador</option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Crear Contacto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Contacto -->
    <div class="modal fade" id="modalEditarContacto" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar Contacto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="accion_editar_contacto" value="1">
                    <input type="hidden" name="contacto_id" id="edit_contacto_id" value="">
                    <?php echo inputTokenCSRF(); ?>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="nombre_completo_edit" class="form-label">Nombre Completo *</label>
                            <input type="text" class="form-control" id="nombre_completo_edit" name="nombre_completo" required>
                        </div>
                        <div class="mb-3">
                            <label for="nombre_usuario_edit" class="form-label">Nombre de Usuario</label>
                            <input type="text" class="form-control" id="nombre_usuario_edit" name="nombre_usuario">
                        </div>
                        <div class="mb-3">
                            <label for="correo_edit" class="form-label">Correo</label>
                            <input type="email" class="form-control" id="correo_edit" name="correo">
                        </div>
                       <div class="mb-3">
                            <label for="numero_telefono_edit" class="form-label">Número de Teléfono</label>
                            <input type="tel" class="form-control" id="numero_telefono_edit" name="numero_telefono">
                         </div>
                        <div class="mb-3">
                            <label for="division_departamento_edit" class="form-label">División/Departamento</label>
                            <input type="text" class="form-control" id="division_departamento_edit" name="division_departamento">
                        </div>
                        <div class="mb-3">
                            <label for="role_edit_contacto" class="form-label">Rol</label>
                            <select class="form-select" id="role_edit_contacto" name="role">
                                <option value="contacto">📇 Contacto</option>
                                <option value="viewer">👁️ Lector</option>
                                <option value="tisupport">🔧 Soporte TI</option>
                                <option value="admin">🔑 Administrador</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function cargarDatosEditarContacto(contacto) {
            document.getElementById('edit_contacto_id').value = contacto.id;
            document.getElementById('nombre_completo_edit').value = contacto.nombre_completo;
            document.getElementById('nombre_usuario_edit').value = contacto.nombre_usuario || '';
            document.getElementById('correo_edit').value = contacto.correo || '';
            document.getElementById('numero_telefono_edit').value = contacto.numero_telefono || '';
            document.getElementById('division_departamento_edit').value = contacto.division_departamento || '';
            // Set role if provided
            if (contacto.role) {
                var roleSelect = document.getElementById('role_edit_contacto');
                if (roleSelect) roleSelect.value = contacto.role;
            }
        }

        // Búsqueda en tiempo real de contactos (busca en toda la BD)
        function buscarContactos() {
            const termino = document.getElementById('busquedaContactos').value.trim();
            const tabla = document.getElementById('tablaContactos');
            const tbody = tabla.querySelector('tbody');

            // Si está vacío, recarga la página para mostrar paginación normal
            if (termino === '') {
                location.reload();
                return;
            }

            // Buscar en la API
            fetch(`api_buscar_contactos.php?q=${encodeURIComponent(termino)}`)
                .then(response => response.json())
                .then(data => {
                    // Limpiar tabla
                    tbody.innerHTML = '';

                    if (data.contactos && data.contactos.length > 0) {
                        data.contactos.forEach(contacto => {
                            const fila = document.createElement('tr');
                            const perfilURL = contacto.role === 'contacto' 
                                ? `perfil_contacto.php?username=${encodeURIComponent(contacto.nombre_usuario)}`
                                : `perfil_usuario.php?username=${encodeURIComponent(contacto.nombre_usuario)}`;
                            fila.innerHTML = `
                                <td><strong>${htmlEscape(contacto.nombre_completo)}</strong></td>
                                <td>${htmlEscape(contacto.nombre_usuario || 'N/A')}</td>
                                <td>${htmlEscape(contacto.correo || 'N/A')}</td>
                                <td>${htmlEscape(contacto.numero_telefono || 'N/A')}</td>
                                <td>${htmlEscape(contacto.division_departamento || 'N/A')}</td>
                                <td>
                                    <a href="${perfilURL}" class="btn btn-sm btn-outline-info" title="Ver Perfil">
                                        <i class="bi bi-person-circle"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-warning" title="Editar" data-bs-toggle="modal" data-bs-target="#modalEditarContacto" onclick="cargarDatosEditarContacto(${JSON.stringify(contacto).replace(/"/g, '&quot;')})">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro?');">
                                        <input type="hidden" name="contacto_id" value="${contacto.id}">
                                        <input type="hidden" name="accion_eliminar_contacto" value="1">
                                        <?php echo inputTokenCSRF(); ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            `;
                            tbody.appendChild(fila);
                        });
                    } else {
                        const fila = document.createElement('tr');
                        fila.innerHTML = '<td colspan="6" class="text-center text-muted py-4">No se encontraron contactos o usuarios</td>';
                        tbody.appendChild(fila);
                    }
                })
                .catch(error => {
                    console.error('Error en la búsqueda:', error);
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error al buscar contactos</td></tr>';
                });
        }

        // Función para escapar HTML
        function htmlEscape(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
    </script>
</body>
</html>
