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
            // Buscar usuario en la base de datos
            $stmt = $conexion->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verificar si existe y validar contraseña
            if ($user && password_verify($password, $user["password"])) {
                // Login exitoso
                $_SESSION["user_id"] = $user["id"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["role"] = $user["role"] ?? "viewer";
                header("Location: dashboard.php");
                exit();
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
