<?php
session_start();
require_once 'includes/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

// Verificar permisos: solo admin puede acceder
if (($_SESSION["role"] ?? "") !== "admin") {
    header("Location: dashboard.php");
    exit();
}

$error = "";
$success = "";
$usuarios = [];
$usuario_editar = null;

// CREAR USUARIO - POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["accion_crear"])) {
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
                $success = "Usuario '$username' creado exitosamente. Contraseña temporal: 111111";
            }
        } catch (PDOException $e) {
            $error = "Error al crear usuario: " . $e->getMessage();
        }
    }
}

// EDITAR USUARIO - POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["accion_editar"])) {
    $user_id = $_POST["user_id"] ?? "";
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
            $success = "Usuario '$username' actualizado exitosamente";
        } catch (PDOException $e) {
            $error = "Error al actualizar usuario: " . $e->getMessage();
        }
    }
}

// ELIMINAR USUARIO - POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["accion_eliminar"])) {
    $user_id = $_POST["user_id"] ?? "";
    if (!empty($user_id) && $user_id != $_SESSION["user_id"]) {
        try {
            $stmt = $conexion->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $success = "Usuario eliminado exitosamente";
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
    
    $stmt = $conexion->query("SELECT $select_fields FROM users ORDER BY username");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($usuarios as &$user) {
        if (!$has_email) $user['email'] = null;
        if (!$has_necesita_cambiar) $user['necesita_cambiar_password'] = 0;
    }
} catch (PDOException $e) {
    $error = "Error al obtener usuarios: " . $e->getMessage();
}

// EDITAR - GET
if (isset($_GET["editar"])) {
    $user_id = $_GET["editar"];
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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/dark-mode.css" rel="stylesheet">
    <title>Gestión de Usuarios</title>
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
                    <h3>👥 Gestión de Usuarios</h3>
                </div>
                
                <!-- Botones de acción -->
                <div class="mb-3">
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalCrearUsuario">
                        ➕ Crear Nuevo Usuario
                    </button>
                </div>
                
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
                                                <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalEditarUsuario" onclick="cargarDatosEditar(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                                    ✏️ Editar
                                                </button>
                                                <?php if ($user["id"] != $_SESSION["user_id"]): ?>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de eliminar este usuario?');">
                                                        <input type="hidden" name="user_id" value="<?php echo $user["id"]; ?>">
                                                        <input type="hidden" name="accion_eliminar" value="1">
                                                        <button type="submit" class="btn btn-sm btn-danger">🗑️ Eliminar</button>
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
    <div class="modal fade" id="modalCrearUsuario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">➕ Crear Nuevo Usuario</h5>
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
    
    <!-- Modal Editar Usuario -->
    <div class="modal fade" id="modalEditarUsuario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">✏️ Editar Usuario</h5>
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
                            <select class="form-select" id="role_edit" name="role" required>
                                <option value="viewer">👁️ Lector</option>
                                <option value="tisupport">🔧 Soporte TI</option>
                                <option value="admin">🔑 Administrador</option>
                            </select>
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
            document.getElementById('password_edit').value = '';
        }
    </script>
</body>
</html>
