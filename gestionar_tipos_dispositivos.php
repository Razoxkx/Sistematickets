<?php
session_start();
require_once 'includes/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

// Verificar si es admin
if ($_SESSION["role"] !== "admin") {
    header("Location: monitoreo.php");
    exit();
}

$error = "";
$success = "";
$tipos = [];

// Obtener todos los tipos de dispositivos
try {
    $stmt = $conexion->prepare("SELECT * FROM tipos_dispositivos ORDER BY nombre ASC");
    $stmt->execute();
    $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al obtener tipos de dispositivos: " . $e->getMessage();
}

// Capturar mensajes de éxito
if (isset($_GET["success"])) {
    if ($_GET["success"] === "creado") {
        $success = "✅ Tipo de dispositivo agregado exitosamente";
    } elseif ($_GET["success"] === "editado") {
        $success = "✅ Tipo de dispositivo actualizado exitosamente";
    } elseif ($_GET["success"] === "eliminado") {
        $success = "✅ Tipo de dispositivo eliminado exitosamente";
    }
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
    <title>Gestionar Tipos de Dispositivos</title>
    <style>
        body {
            background: linear-gradient(to bottom, #f8f9fa, #ffffff);
        }
        
        [data-bs-theme="dark"] body {
            background: linear-gradient(to bottom, #1a1a1a, #0d0d0d);
        }

        h1, h2, h3 {
            color: #8b9dff;
            font-weight: 700;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        h1 {
            font-size: 2rem;
        }

        .tipo-card {
            border-radius: 12px;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            margin-bottom: 15px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .tipo-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        [data-bs-theme="dark"] .tipo-card {
            background: #1e1e1e;
            border: 1px solid #333;
        }

        .icono-preview {
            font-size: 2rem;
            min-width: 60px;
            text-align: center;
        }

        .tipo-info {
            flex: 1;
        }

        .tipo-nombre {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 5px;
        }

        .tipo-meta {
            font-size: 0.85rem;
            color: #999;
            display: flex;
            gap: 15px;
        }

        .color-box {
            width: 30px;
            height: 30px;
            border-radius: 6px;
            border: 2px solid #ddd;
            cursor: pointer;
        }

        [data-bs-theme="dark"] .color-box {
            border-color: #444;
        }

        .tipo-acciones {
            display: flex;
            gap: 8px;
        }

        [data-bs-theme="dark"] .form-control,
        [data-bs-theme="dark"] .form-select {
            background-color: #2a2a2a;
            border-color: #444;
            color: #e0e0e0;
        }

        [data-bs-theme="dark"] .form-control:focus,
        [data-bs-theme="dark"] .form-select:focus {
            background-color: #2a2a2a;
            border-color: #8b9dff;
            color: #e0e0e0;
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
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h1><i class="bi bi-gear"></i> Gestionar Tipos de Dispositivos</h1>
                <p class="text-muted">Crea, edita y gestiona los tipos de dispositivos disponibles para el monitoreo</p>
            </div>
        </div>

        <!-- Botón Agregar -->
        <div class="row mb-4">
            <div class="col-12">
                <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#modalAgregar">
                    <i class="bi bi-plus-circle"></i> Agregar Tipo de Dispositivo
                </button>
            </div>
        </div>

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

        <!-- Lista de Tipos -->
        <?php if (empty($tipos)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> No hay tipos de dispositivos creados.
                <button class="btn btn-sm btn-info ms-2" data-bs-toggle="modal" data-bs-target="#modalAgregar">
                    Crear el primero
                </button>
            </div>
        <?php else: ?>
            <div class="row">
                <div class="col-12">
                    <h5 style="color: #667eea; font-weight: 700; margin-bottom: 20px;">
                        <i class="bi bi-list"></i> Tipos Disponibles (<?php echo count($tipos); ?>)
                    </h5>
                    <?php foreach ($tipos as $tipo): ?>
                    <div class="tipo-card" id="tipo-<?php echo $tipo["id"]; ?>">
                        <div class="icono-preview">
                            <i class="bi <?php echo htmlspecialchars($tipo['icono']); ?>" style="color: <?php echo htmlspecialchars($tipo['color']); ?>;"></i>
                        </div>
                        <div class="tipo-info">
                            <div class="tipo-nombre"><?php echo htmlspecialchars($tipo['nombre']); ?></div>
                            <div class="tipo-meta">
                                <span><i class="bi bi-palette"></i> Color: <code><?php echo htmlspecialchars($tipo['color']); ?></code></span>
                                <span><i class="bi bi-tag"></i> Icono: <code><?php echo htmlspecialchars($tipo['icono']); ?></code></span>
                            </div>
                        </div>
                        <div class="tipo-acciones">
                            <button class="btn btn-sm btn-warning" onclick="abrirEditar(<?php echo htmlspecialchars(json_encode($tipo), ENT_QUOTES, 'UTF-8'); ?>)">
                                <i class="bi bi-pencil"></i> Editar
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="confirmarEliminar(<?php echo $tipo['id']; ?>, '<?php echo htmlspecialchars($tipo['nombre']); ?>')">
                                <i class="bi bi-trash"></i> Eliminar
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Agregar Tipo -->
    <div class="modal fade" id="modalAgregar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle"></i> Agregar Tipo de Dispositivo
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formAgregar">
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" placeholder="Ej: Switch, NVR, etc." required>
                        </div>
                        <div class="mb-3">
                            <label for="color" class="form-label">Color *</label>
                            <input type="color" class="form-control form-control-color" id="color" name="color" value="#007bff" required>
                            <small class="text-muted">Selecciona un color para identificar este tipo</small>
                        </div>
                        <div class="mb-3">
                            <label for="icono" class="form-label">Icono *</label>
                            <input type="text" class="form-control" id="icono" name="icono" placeholder="Ej: bi-wifi, bi-camera-video" required>
                            <small class="text-muted">Usa iconos de <a href="https://icons.getbootstrap.com/" target="_blank">Bootstrap Icons</a></small>
                        </div>
                        <div id="mensajeError" class="alert alert-danger d-none" role="alert"></div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-check-circle"></i> Crear Tipo
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Editar Tipo -->
    <div class="modal fade" id="modalEditar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil-square"></i> Editar Tipo de Dispositivo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formEditar">
                        <input type="hidden" id="editarTipoId" name="tipo_id">
                        <div class="mb-3">
                            <label for="editarNombre" class="form-label">Nombre *</label>
                            <input type="text" class="form-control" id="editarNombre" name="nombre" required>
                        </div>
                        <div class="mb-3">
                            <label for="editarColor" class="form-label">Color *</label>
                            <input type="color" class="form-control form-control-color" id="editarColor" name="color" required>
                        </div>
                        <div class="mb-3">
                            <label for="editarIcono" class="form-label">Icono *</label>
                            <input type="text" class="form-control" id="editarIcono" name="icono" required>
                            <small class="text-muted">Usa iconos de <a href="https://icons.getbootstrap.com/" target="_blank">Bootstrap Icons</a></small>
                        </div>
                        <div id="mensajeEditarError" class="alert alert-danger d-none" role="alert"></div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-warning btn-lg">
                                <i class="bi bi-check-circle"></i> Actualizar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Eliminar -->
    <div class="modal fade" id="modalEliminar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle"></i> Confirmar Eliminación
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro que desea eliminar el tipo <strong id="nombreAEliminar"></strong>?</p>
                    <p class="text-muted small">⚠️ Los dispositivos asociados quedarán sin clasificar (tipo = NULL).</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="btnConfirmarEliminar">Eliminar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const modalAgregar = new bootstrap.Modal(document.getElementById('modalAgregar'));
        const modalEditar = new bootstrap.Modal(document.getElementById('modalEditar'));
        const modalEliminar = new bootstrap.Modal(document.getElementById('modalEliminar'));
        let tipoIdAEliminar = null;

        // Agregar tipo
        document.getElementById('formAgregar').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const nombre = document.getElementById('nombre').value;
            const color = document.getElementById('color').value;
            const icono = document.getElementById('icono').value;

            try {
                const response = await fetch('api_crear_tipo_dispositivo.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ nombre, color, icono })
                });

                const data = await response.json();
                
                if (data.success) {
                    modalAgregar.hide();
                    location.href = 'gestionar_tipos_dispositivos.php?success=creado';
                } else {
                    document.getElementById('mensajeError').textContent = data.mensaje || 'Error al crear tipo';
                    document.getElementById('mensajeError').classList.remove('d-none');
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('mensajeError').textContent = 'Error al crear tipo';
                document.getElementById('mensajeError').classList.remove('d-none');
            }
        });

        // Editar tipo
        function abrirEditar(tipo) {
            document.getElementById('editarTipoId').value = tipo.id;
            document.getElementById('editarNombre').value = tipo.nombre;
            document.getElementById('editarColor').value = tipo.color;
            document.getElementById('editarIcono').value = tipo.icono;
            document.getElementById('mensajeEditarError').classList.add('d-none');
            modalEditar.show();
        }

        document.getElementById('formEditar').addEventListener('submit', async function(e) {
            e.preventDefault();

            const tipo_id = document.getElementById('editarTipoId').value;
            const nombre = document.getElementById('editarNombre').value;
            const color = document.getElementById('editarColor').value;
            const icono = document.getElementById('editarIcono').value;

            try {
                const response = await fetch('api_editar_tipo_dispositivo.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ tipo_id, nombre, color, icono })
                });

                const data = await response.json();

                if (data.success) {
                    modalEditar.hide();
                    location.href = 'gestionar_tipos_dispositivos.php?success=editado';
                } else {
                    document.getElementById('mensajeEditarError').textContent = data.mensaje || 'Error al actualizar tipo';
                    document.getElementById('mensajeEditarError').classList.remove('d-none');
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('mensajeEditarError').textContent = 'Error al actualizar tipo';
                document.getElementById('mensajeEditarError').classList.remove('d-none');
            }
        });

        // Eliminar tipo
        function confirmarEliminar(tipoId, tipoNombre) {
            tipoIdAEliminar = tipoId;
            document.getElementById('nombreAEliminar').textContent = tipoNombre;
            modalEliminar.show();
        }

        document.getElementById('btnConfirmarEliminar').addEventListener('click', async function() {
            try {
                const response = await fetch('api_eliminar_tipo_dispositivo.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ tipo_id: tipoIdAEliminar })
                });

                const data = await response.json();

                if (data.success) {
                    modalEliminar.hide();
                    location.href = 'gestionar_tipos_dispositivos.php?success=eliminado';
                } else {
                    alert('Error: ' + (data.mensaje || 'No se pudo eliminar el tipo'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error al eliminar tipo');
            }
        });
    </script>
</body>
</html>
