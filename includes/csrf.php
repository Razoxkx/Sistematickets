<?php
/**
 * Protección CSRF (Cross-Site Request Forgery)
 * 
 * Genera tokens únicos para cada formulario y sesión
 * Previene ataques donde un sitio malicioso intenta hacer acciones
 * sin consentimiento del usuario
 */

/**
 * Inicializar protección CSRF
 * Debe llamarse después de session_start()
 */
function inicializarCSRF() {
    // Si no hay token en sesión, generar uno nuevo
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Obtener el token CSRF actual
 * 
 * @return string Token CSRF de la sesión actual
 */
function obtenerTokenCSRF() {
    if (empty($_SESSION['csrf_token'])) {
        inicializarCSRF();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Generar campo HTML oculto con token CSRF
 * 
 * @return string HTML con input oculto
 */
function inputTokenCSRF() {
    $token = obtenerTokenCSRF();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Validar token CSRF de formulario
 * 
 * @param string $token Token enviado por el formulario (GET o POST)
 * @return bool true si es válido, false si no
 */
function validarTokenCSRF($token = null) {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    }
    
    if (empty($token)) {
        return false;
    }
    
    // Comparación segura contra timing attacks
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/**
 * Middleware que valida CSRF automáticamente
 * 
 * Llama a esta función en métodos POST/PUT/DELETE
 * Si el token es inválido, redirige y termina
 */
function validarCSRFOFail() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && 
        $_SERVER['REQUEST_METHOD'] !== 'PUT' && 
        $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        return; // No validar en GET
    }
    
    if (!validarTokenCSRF()) {
        // Token inválido o faltante
        $_SESSION['csrf_error'] = true;
        header('Location: ' . $_SERVER['HTTP_REFERER'] ?? 'index.php');
        exit();
    }
}

/**
 * Regenerar token CSRF después de cambios críticos
 * 
 * Mejora seguridad regenerando el token después de login/logout
 */
function regenerarTokenCSRF() {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
