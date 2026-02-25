<?php
session_start();
require_once 'includes/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

// Verificar permisos: admin accede a todo, tisupport solo a su propio usuario
if (($_SESSION["role"] ?? "") !== "admin" && ($_SESSION["role"] ?? "") !== "tisupport") {
    header("Location: dashboard.php");
    exit();
}

// Flag para indicar si el usuario actual es tisupport
$es_tisupport = $_SESSION["role"] === "tisupport";

$error = "";
$success = "";
$usuarios = [];
$usuario_editar = null;

// Capturar mensaje de éxito del query string
if (isset($_GET["success"])) {
    if ($_GET["success"] === "creado") {
        $success = "✅ Usuario creado exitosamente. Contraseña temporal: 111111";
    } elseif ($_GET["success"] === "actualizado") {
        $success = "✅ Usuario actualizado exitosamente";
    } elseif ($_GET["success"] === "eliminado") {
        $success = "✅ Usuario eliminado exitosamente";
    }
}

// CREAR USUARIO - POST (solo admin)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["accion_crear"]) && !$es_tisupport) {
    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $role = $_POST["role"] ?? "viewer";
    
    if (empty($username)) {
        $error = "El nombre de usuario es obligatorio";
    } else {
        try {
            $stmt = $conexion->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = "El usuario ya existe";
            } else {
                $columns_query = $conexion->query("SHOW COLUMNS FROM users");
                $columns = $columns_query->fetchAll(PDO::FETCH_ASSOC);
                $column_names = array_column($columns, 'Field');
                $has_email = in_array('email', $column_names);
                $has_necesita_cambiar = in_array('necesita_cambiar_password', $column_names);
                
                $password_hash = password_hash("111111", PASSWORD_BCRYPT);
                
                if ($has_email && $has_necesita_cambiar) {
                    $stmt = $conexion->prepare("INSERT INTO users (username, password, email, role, necesita_cambiar_password) VALUES (?, ?, ?, ?, 1)");
                    $stmt->execute([$username, $password_hash, $email, $role]);
                } elseif ($has_email) {
                    $stmt = $conexion->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$username, $password_hash, $email, $role]);
                } elseif ($has_necesita_cambiar) {
                    $stmt = $conexion->prepare("INSERT INTO users (username, password, role, necesita_cambiar_password) VALUES (?, ?, ?, 1)");
                    $stmt->execute([$username, $password_hash, $role]);
                } else {
                    $stmt = $conexion->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                    $stmt->execute([$username, $password_hash, $role]);
                }
                header("Location: usuarios.php?success=creado");
                exit();
            }
        } catch (PDOException $e) {
            $error = "Error al crear usuario: " . $e->getMessage();
        }
    }
}

// EDITAR USUARIO - POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["accion_editar"])) {
    $user_id = $_POST["user_id"] ?? "";
    
    // Verificar permisos: tisupport solo puede editar su propio usuario
    if ($es_tisupport && $user_id != $_SESSION["user_id"]) {
        $error = "No tienes permisos para editar otros usuarios";
    } else {
        $username = trim($_POST["username"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $role = $_POST["role"] ?? "viewer";
        $password_nueva = trim($_POST["password_nueva"] ?? "");
    
    if (empty($username)) {
        $error = "El nombre de usuario es obligatorio";
    } else {
        try {
            $columns_query = $conexion->query("SHOW COLUMNS FROM users");
            $columns = $columns_query->fetchAll(PDO::FETCH_ASSOC);
            $column_names = array_column($columns, 'Field');
            $has_email = in_array('email', $column_names);
            $has_necesita_cambiar = in_array('necesita_cambiar_password', $column_names);
            
            if (!empty($password_nueva)) {
                $password_hash = password_hash($password_nueva, PASSWORD_BCRYPT);
                if ($has_email && $has_necesita_cambiar) {
                    $stmt = $conexion->prepare("UPDATE users SET username = ?, email = ?, role = ?, password = ?, necesita_cambiar_password = 0 WHERE id = ?");
                    $stmt->execute([$username, $email, $role, $password_hash, $user_id]);
                } elseif ($has_email) {
                    $stmt = $conexion->prepare("UPDATE users SET username = ?, email = ?, role = ?, password = ? WHERE id = ?");
                    $stmt->execute([$username, $email, $role, $password_hash, $user_id]);
                } else {
                    $stmt = $conexion->prepare("UPDATE users SET username = ?, role = ?, password = ? WHERE id = ?");
                    $stmt->execute([$username, $role, $password_hash, $user_id]);
                }
            } else {
                if ($has_email) {
                    $stmt = $conexion->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
                    $stmt->execute([$username, $email, $role, $user_id]);
                } else {
                    $stmt = $conexion->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
                    $stmt->execute([$username, $role, $user_id]);
                }
            }
            header("Location: usuarios.php?success=actualizado");
            exit();
        } catch (PDOException $e) {
            $error = "Error al actualizar usuario: " . $e->getMessage();
        }
    }
    }
}

// ELIMINAR USUARIO - POST (solo admin)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["accion_eliminar"]) && !$es_tisupport) {
    $user_id = $_POST["user_id"] ?? "";
    if (!empty($user_id) && $user_id != $_SESSION["user_id"]) {
        try {
            $stmt = $conexion->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            header("Location: usuarios.php?success=eliminado");
            exit();
        } catch (PDOException $e) {
            // Verificar si es un error de integridad de clave externa
            if ($e->getCode() == '23000') {
                $error = "No se puede eliminar este usuario porque tiene registros asociados en el sistema (comentarios, tickets, etc.). Por favor, contacta al administrador si necesitas eliminar esta cuenta.";
            } else {
                $error = "Error al eliminar usuario: " . $e->getMessage();
            }
        }
    } else {
        $error = "No puedes eliminar tu propia cuenta";
    }
}

// OBTENER LISTA DE USUARIOS
try {
    $columns_query = $conexion->query("SHOW COLUMNS FROM users");
    $columns = $columns_query->fetchAll(PDO::FETCH_ASSOC);
    $column_names = array_column($columns, 'Field');
    $has_email = in_array('email', $column_names);
    $has_necesita_cambiar = in_array('necesita_cambiar_password', $column_names);
    
    $select_fields = "id, username, role";
    if ($has_email) $select_fields .= ", email";
    if ($has_necesita_cambiar) $select_fields .= ", necesita_cambiar_password";
    
    // tisupport solo ve su propio usuario, admin ve todos
    if ($es_tisupport) {
        $stmt = $conexion->prepare("SELECT $select_fields FROM users WHERE id = ? AND role != 'contacto' ORDER BY username");
        $stmt->execute([$_SESSION["user_id"]]);
    } else {
        $stmt = $conexion->query("SELECT $select_fields FROM users WHERE role != 'contacto' ORDER BY username");
    }
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agregar campos faltantes si no existen
    $usuarios_procesados = [];
    foreach ($usuarios as $user) {
        if (!$has_email) $user['email'] = null;
        if (!$has_necesita_cambiar) $user['necesita_cambiar_password'] = 0;
        $usuarios_procesados[] = $user;
    }
    $usuarios = $usuarios_procesados;
} catch (PDOException $e) {
    $error = "Error al obtener usuarios: " . $e->getMessage();
}

// EDITAR - GET
if (isset($_GET["editar"])) {
    $user_id = $_GET["editar"];
    
    // tisupport solo puede editar su propio usuario
    if ($es_tisupport && $user_id != $_SESSION["user_id"]) {
        $error = "No tienes permisos para editar otros usuarios";
    } else {
    try {
        $select_fields = "id, username, role";
        if ($has_email) $select_fields .= ", email";
        if ($has_necesita_cambiar) $select_fields .= ", necesita_cambiar_password";
        
        $stmt = $conexion->prepare("SELECT $select_fields FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $usuario_editar = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($usuario_editar) {
            if (!$has_email) $usuario_editar['email'] = null;
            if (!$has_necesita_cambiar) $usuario_editar['necesita_cambiar_password'] = 0;
        }
    } catch (PDOException $e) {
        $error = "Error al obtener datos del usuario: " . $e->getMessage();
    }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/dark-mode.css" rel="stylesheet">
    <title>Gestión de Usuarios</title>
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
                    <h3>Gestión de Usuarios</h3>
                </div>
                
                <?php if ($es_tisupport): ?>
                <div class="alert alert-info mb-4">
                    <i class="bi bi-info-circle"></i> <strong>Información:</strong> Como usuario de Soporte TI, solo puedes editar tu propia información de usuario.
                </div>
                <?php endif; ?>
                
                <!-- Botones de acción -->
                <?php if (!$es_tisupport): ?>
                <div class="btn-accion-container">
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalCrearUsuario">
                        <i class="bi bi-plus-circle"></i> Crear Nuevo Usuario
                    </button>
                </div>
                <?php endif; ?>
                
                <!-- Tabla de Usuarios -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">📋 Usuarios del Sistema</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Usuario</th>
                                        <th>Email</th>
                                        <th>Rol</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($usuarios) > 0): ?>
                                        <?php foreach ($usuarios as $user): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($user["username"]); ?></strong>
                                                <?php if ($user["id"] == $_SESSION["user_id"]): ?>
                                                    <span class="badge bg-info">👤 (Tu)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($user["email"] ?? "N/A"); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $user["role"] === 'admin' ? 'danger' : ($user["role"] === 'tisupport' ? 'warning' : 'info'); ?>">
                                                    <?php echo traducirRol($user["role"]); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($user["necesita_cambiar_password"]): ?>
                                                    <span class="badge bg-warning">⚠️ Cambio pendiente</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">✅ Activo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="perfil_usuario.php?username=<?php echo urlencode($user['username']); ?>" class="btn btn-sm btn-info" title="Ver perfil">
                                                    <i class="bi bi-person"></i> Perfil
                                                </a>
                                                <?php if (!$es_tisupport || $user["id"] == $_SESSION["user_id"]): ?>
                                                <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalEditarUsuario" onclick="cargarDatosEditar(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                                    <i class="bi bi-pencil"></i> Editar
                                                </button>
                                                <?php endif; ?>
                                                <?php if (!$es_tisupport && $user["id"] != $_SESSION["user_id"]): ?>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de eliminar este usuario?');">
                                                        <input type="hidden" name="user_id" value="<?php echo $user["id"]; ?>">
                                                        <input type="hidden" name="accion_eliminar" value="1">
                                                        <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i> Eliminar</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">No hay usuarios registrados</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Crear Usuario -->
    <?php if (!$es_tisupport): ?>
    <div class="modal fade" id="modalCrearUsuario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Crear Nuevo Usuario</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="accion_crear" value="1">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="username_new" class="form-label">Nombre de Usuario *</label>
                            <input type="text" class="form-control" id="username_new" name="username" required placeholder="ej: juan.garcia">
                        </div>
                        <div class="mb-3">
                            <label for="email_new" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email_new" name="email" placeholder="ej: juan@empresa.com">
                        </div>
                        <div class="mb-3">
                            <label for="role_new" class="form-label">Rol *</label>
                            <select class="form-select" id="role_new" name="role" required>
                                <option value="viewer">👁️ Lector</option>
                                <option value="contacto">📇 Contacto</option>
                                <option value="tisupport">🔧 Soporte TI</option>
                                <option value="admin">🔑 Administrador</option>
                            </select>
                        </div>
                        <div class="alert alert-info">
                            <strong>🔐 Contraseña Temporal:</strong> <code>111111</code><br>
                            <small>El usuario deberá cambiarla en su primer inicio de sesión</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Crear Usuario</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal Editar Usuario -->
    <div class="modal fade" id="modalEditarUsuario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="accion_editar" value="1">
                    <input type="hidden" name="user_id" id="edit_user_id" value="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="username_edit" class="form-label">Nombre de Usuario *</label>
                            <input type="text" class="form-control" id="username_edit" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="email_edit" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email_edit" name="email">
                        </div>
                        <div class="mb-3">
                            <label for="role_edit" class="form-label">Rol *</label>
                            <select class="form-select" id="role_edit" name="role" required <?php echo $es_tisupport ? 'disabled' : ''; ?>>
                                <option value="viewer">👁️ Lector</option>
                                <option value="contacto">📇 Contacto</option>
                                <option value="tisupport">🔧 Soporte TI</option>
                                <option value="admin">🔑 Administrador</option>
                            </select>
                            <?php if ($es_tisupport): ?>
                            <input type="hidden" name="role" id="role_edit_hidden" value="">
                            <small class="text-muted">Tu rol no puede ser modificado</small>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label for="password_edit" class="form-label">Nueva Contraseña (opcional)</label>
                            <input type="password" class="form-control" id="password_edit" name="password_nueva" placeholder="Dejar en blanco para no cambiar">
                            <small class="text-muted">Mínimo 6 caracteres si decides cambiarla</small>
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
        function cargarDatosEditar(usuario) {
            document.getElementById('edit_user_id').value = usuario.id;
            document.getElementById('username_edit').value = usuario.username;
            document.getElementById('email_edit').value = usuario.email || '';
            document.getElementById('role_edit').value = usuario.role;
            <?php if ($es_tisupport): ?>
            document.getElementById('role_edit_hidden').value = usuario.role;
            <?php endif; ?>
            document.getElementById('password_edit').value = '';
        }

        function cargarDatosEditarContacto(contacto) {
            document.getElementById('edit_contacto_id').value = contacto.id;
            document.getElementById('nombre_completo_edit').value = contacto.nombre_completo;
            document.getElementById('nombre_usuario_edit').value = contacto.nombre_usuario || '';
            document.getElementById('correo_edit').value = contacto.correo || '';
            document.getElementById('numero_telefono_edit').value = contacto.numero_telefono || '';
            document.getElementById('division_departamento_edit').value = contacto.division_departamento || '';
        }
    </script>
</body>
</html>
