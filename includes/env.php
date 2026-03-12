<?php
/**
 * Cargador de Variables de Entorno
 * 
 * Lee el archivo .env y carga las variables en $_ENV
 * Si no existe .env, intenta usar variables del sistema
 */

function cargarVariablesEntorno() {
    $archivo_env = __DIR__ . '/../.env';
    
    if (file_exists($archivo_env)) {
        $lineas = file($archivo_env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lineas as $linea) {
            // Ignorar comentarios
            if (strpos(trim($linea), '#') === 0) {
                continue;
            }
            
            // Parsear KEY=VALUE
            if (strpos($linea, '=') !== false) {
                list($clave, $valor) = explode('=', $linea, 2);
                $clave = trim($clave);
                $valor = trim($valor);
                
                // Eliminar comillas si existen
                if ((substr($valor, 0, 1) === '"' && substr($valor, -1) === '"') ||
                    (substr($valor, 0, 1) === "'" && substr($valor, -1) === "'")) {
                    $valor = substr($valor, 1, -1);
                }
                
                $_ENV[$clave] = $valor;
            }
        }
    }
}

/**
 * Obtener variable de entorno
 * 
 * @param string $clave Nombre de la variable
 * @param mixed $defecto Valor por defecto si no existe
 * @return mixed Valor de la variable
 */
function obtenerEnv($clave, $defecto = null) {
    // Primero intenta $_ENV, luego getenv(), luego el defecto
    if (isset($_ENV[$clave])) {
        return $_ENV[$clave];
    }
    
    $valor = getenv($clave);
    if ($valor !== false) {
        return $valor;
    }
    
    return $defecto;
}

// Cargar variables al incluir este archivo
cargarVariablesEntorno();
?>
