<?php
session_start();
require_once 'config.php';
require_once 'rate_limit.php';

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"] ?? "";
    $password = $_POST["password"] ?? "";
    
    // Validar token CSRF
    if (!validarTokenCSRF()) {
        $error = "Sesión expirada. Por favor intenta de nuevo.";
    } else if (empty($username) || empty($password)) {
        $error = "Usuario y contraseña son obligatorios";
    } else {
        // Verificar rate limiting ANTES de cualquier validación
        $verificacion = verificarBloqueoPorRateLimit($conexion, $username);
        if ($verificacion['bloqueado']) {
            $error = obtenerMensajeBloqueoPorRateLimit($verificacion['tiempo_espera']);
        } else {
            try {
                // Verificar si la columna necesita_cambiar_password existe
                $check_columns = $conexion->query("SHOW COLUMNS FROM users");
                $columns = $check_columns->fetchAll(PDO::FETCH_ASSOC);
                $column_names = array_column($columns, 'Field');
                $has_necesita_cambiar = in_array('necesita_cambiar_password', $column_names);
                
                // Construir query según columnas disponibles
                if ($has_necesita_cambiar) {
                    $stmt = $conexion->prepare("SELECT id, username, password, role, necesita_cambiar_password FROM users WHERE username = ?");
                } else {
                    $stmt = $conexion->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
                }
                
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verificar si existe y validar contraseña
                if ($user && password_verify($password, $user["password"])) {
                    // ✅ LOGIN EXITOSO
                    // Limpiar intentos fallidos
                    limpiarIntentosFallidos($conexion, $username);
                    
                    // Regenerar ID de sesión para seguridad
                    session_regenerate_id(true);
                    
                    // Guardar datos de sesión
                    $_SESSION["user_id"] = $user["id"];
                    $_SESSION["username"] = $user["username"];
                    $_SESSION["role"] = $user["role"] ?? "viewer";
                    
                    // Regenerar token CSRF después de login (mejora seguridad)
                    regenerarTokenCSRF();
                    
                    // Verificar si necesita cambiar contraseña (solo si la columna existe)
                    if ($has_necesita_cambiar && isset($user["necesita_cambiar_password"]) && $user["necesita_cambiar_password"]) {
                        // Redirigir a la página de cambio de contraseña
                        header("Location: cambiar_contrasena.php", true, 302);
                        exit();
                    } else {
                        // Ir a reportes normalmente
                        header("Location: reportes.php", true, 302);
                        exit();
                    }
                } else {
                    // ❌ CREDENCIALES INCORRECTAS
                    // Registrar intento fallido
                    registrarIntentoFallido($conexion, $username);
                    
                    $error = "Usuario o contraseña incorrectos";
                }
            } catch (PDOException $e) {
                $error = "Error en la base de datos: " . $e->getMessage();
            }
        }
    }
}

if (isset($_GET["logout"])) {
    session_destroy();
    header("Location: index.php", true, 302);
    exit();
}
?>
