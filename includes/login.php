<?php
session_start();
require_once 'config.php';

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"] ?? "";
    $password = $_POST["password"] ?? "";
    
    // Validar que no estén vacíos
    if (empty($username) || empty($password)) {
        $error = "Usuario y contraseña son obligatorios";
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
                // Login exitoso
                $_SESSION["user_id"] = $user["id"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["role"] = $user["role"] ?? "viewer";
                
                // Debug: Log del estado
                error_log("Login exitoso para: " . $username);
                error_log("has_necesita_cambiar: " . ($has_necesita_cambiar ? "true" : "false"));
                if ($has_necesita_cambiar && isset($user["necesita_cambiar_password"])) {
                    error_log("necesita_cambiar_password value: " . ($user["necesita_cambiar_password"] ? "1" : "0"));
                }
                
                // Verificar si necesita cambiar contraseña (solo si la columna existe)
                if ($has_necesita_cambiar && isset($user["necesita_cambiar_password"]) && $user["necesita_cambiar_password"]) {
                    // Redirigir a la página de cambio de contraseña
                    error_log("Redirigiendo a cambiar_contrasena.php");
                    header("Location: cambiar_contrasena.php");
                    exit();
                } else {
                    // Ir a reportes normalmente
                    error_log("Redirigiendo a reportes.php");
                    header("Location: reportes.php");
                    exit();
                }
            } else {
                $error = "Usuario o contraseña incorrectos";
            }
        } catch (PDOException $e) {
            $error = "Error en la base de datos: " . $e->getMessage();
        }
    }
}

if (isset($_GET["logout"])) {
    session_destroy();
    header("Location: index.php");
    exit();
}
?>
