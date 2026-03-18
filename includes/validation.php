<?php
/**
 * Sistema de validación e sanitización de datos
 * Previene: SQL Injection, XSS, Inyección de código
 */

/**
 * Sanitizar entrada de texto general
 * Elimina/escapa caracteres peligrosos
 */
function sanitizarTexto($texto) {
    if (!is_string($texto)) {
        return '';
    }
    
    // Remover espacios en blanco al inicio/final
    $texto = trim($texto);
    
    // Escaper para evitar XSS
    $texto = htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
    
    return $texto;
}

/**
 * Sanitizar y validar email
 */
function sanitizarEmail($email) {
    $email = trim($email);
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return '';
    }
    
    return $email;
}

/**
 * Sanitizar nombre de usuario
 * Permite: letras, números, punto, guión, guión bajo
 */
function sanitizarUsername($username) {
    $username = trim($username);
    
    // Permitir solo alfanuméricos, punto, guión y guión bajo
    if (!preg_match('/^[a-zA-Z0-9._-]{3,30}$/', $username)) {
        return '';
    }
    
    return htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
}

/**
 * Validar y sanitizar número telefónico
 * Acepta varios formatos
 */
function sanitizarTelefono($telefono) {
    $telefono = trim($telefono);
    
    if (empty($telefono)) {
        return '';
    }
    
    // Permitir solo dígitos, espacios, guiones, paréntesis y símbolo +
    if (!preg_match('/^[\d\s\-\(\)\+]+$/', $telefono)) {
        return '';
    }
    
    // Limitar a 20 caracteres
    if (strlen($telefono) > 20) {
        return '';
    }
    
    return htmlspecialchars($telefono, ENT_QUOTES, 'UTF-8');
}

/**
 * Validar y sanitizar contraseña
 * Requisitos: mínimo 6 caracteres
 */
function validarContrasena($password) {
    if (!is_string($password)) {
        return false;
    }
    
    // Mínimo 6 caracteres
    if (strlen($password) < 6) {
        return false;
    }
    
    // Máximo 255 caracteres
    if (strlen($password) > 255) {
        return false;
    }
    
    return true;
}

/**
 * Sanitizar descripción/textarea
 * Preserva saltos de línea pero escapa HTML
 */
function sanitizarDescripcion($texto) {
    if (!is_string($texto)) {
        return '';
    }
    
    $texto = trim($texto);
    
    // Escapear HTML pero permitir saltos de línea
    $texto = htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
    
    // Convertir saltos de línea a <br> para display (opcional al mostrar)
    // No lo hacemos aquí, lo hacemos en la visualización
    
    return $texto;
}

/**
 * Validar ID (debe ser entero positivo)
 */
function validarID($id) {
    $id = intval($id);
    return $id > 0 ? $id : null;
}

/**
 * Validar estado de ticket
 */
function validarEstadoTicket($estado) {
    $estados_validos = [
        'sin abrir',
        'en conocimiento',
        'en proceso',
        'pendiente de cierre',
        'ticket cerrado'
    ];
    
    return in_array($estado, $estados_validos) ? $estado : null;
}

/**
 * Validar rol de usuario
 */
function validarRol($rol) {
    $roles_validos = ['admin', 'tisupport', 'viewer', 'contacto'];
    return in_array($rol, $roles_validos) ? $rol : 'viewer';
}

/**
 * Validar motivo de cierre
 */
function validarMotivoCierre($motivo) {
    $motivos_validos = [
        'Spam',
        'Ticket repetido',
        'Resuelto',
        'No procede',
        'Duplicado',
        'Solucionado',
        'Otros'
    ];
    
    $motivo = trim($motivo);
    return in_array($motivo, $motivos_validos) ? $motivo : null;
}

/**
 * Validar fecha en formato YYYY-MM-DD
 */
function validarFecha($fecha) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        return false;
    }
    
    $partes = explode('-', $fecha);
    return checkdate($partes[1], $partes[2], $partes[0]);
}

/**
 * Sanitizar y validar búsqueda
 * Previene SQL injection en LIKE queries
 */
function sanitizarBusqueda($busqueda) {
    $busqueda = trim($busqueda);
    
    // Limitar longitud
    if (strlen($busqueda) > 100) {
        $busqueda = substr($busqueda, 0, 100);
    }
    
    // Escapear caracteres especiales de SQL LIKE
    $busqueda = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $busqueda);
    
    // Escapear HTML
    $busqueda = htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8');
    
    return $busqueda;
}

/**
 * Validar orden (para ORDER BY)
 * Whitelist de columnas permitidas
 */
function validarOrden($orden, $columnas_permitidas) {
    if (!in_array($orden, $columnas_permitidas)) {
        return null;
    }
    
    return $orden;
}

/**
 * Validar dirección de orden (ASC o DESC)
 */
function validarDireccion($direccion) {
    $direccion = strtoupper($direccion);
    return in_array($direccion, ['ASC', 'DESC']) ? $direccion : 'ASC';
}

/**
 * Limpiar y validar división/departamento
 */
function sanitizarDepartamento($depto) {
    $depto = trim($depto);
    
    // Limitar longitud
    if (strlen($depto) > 100) {
        $depto = substr($depto, 0, 100);
    }
    
    return htmlspecialchars($depto, ENT_QUOTES, 'UTF-8');
}

/**
 * Validar título de ticket
 */
function validarTitulo($titulo) {
    $titulo = trim($titulo);
    
    if (empty($titulo)) {
        return '';
    }
    
    // Mínimo 5 caracteres, máximo 200
    if (strlen($titulo) < 5 || strlen($titulo) > 200) {
        return '';
    }
    
    return htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
}

/**
 * Validar parámetro página (paginación)
 */
function validarPagina($pagina) {
    $pagina = intval($pagina);
    return $pagina > 0 ? $pagina : 1;
}

/**
 * Validar número de registros por página
 */
function validarLimitePaginacion($limite) {
    $limite = intval($limite);
    $min = 5;
    $max = 100;
    
    if ($limite < $min) return $min;
    if ($limite > $max) return $max;
    
    return $limite;
}
?>
