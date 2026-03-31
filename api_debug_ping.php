<?php
session_start();
require_once 'includes/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION["user_id"])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$ip = $_GET['ip'] ?? $_POST['ip'] ?? null;

if (!$ip) {
    echo json_encode(['success' => false, 'error' => 'IP no especificada']);
    exit();
}

$info = [
    'ip' => $ip,
    'php_os' => PHP_OS,
    'is_windows' => strtoupper(substr(PHP_OS, 0, 3)) === 'WIN',
    'shell_exec_available' => function_exists('shell_exec'),
    'disabled_functions' => ini_get('disable_functions'),
    'tests' => []
];

// Test 1: Validación de IP
$info['tests'][] = [
    'test' => 'IP Validation',
    'valid' => filter_var($ip, FILTER_VALIDATE_IP) !== false
];

// Test 2: Ping simple
if (!$info['is_windows']) {
    $ping_path = '/bin/ping';
    if (!file_exists($ping_path)) {
        $ping_path = '/sbin/ping';
    }
    
    $cmd = $ping_path . " -c 1 -W 2000 " . escapeshellarg($ip) . " 2>&1";
    $info['tests'][] = [
        'test' => 'Ping Command',
        'ping_path' => $ping_path,
        'ping_exists' => file_exists($ping_path),
        'command' => $cmd,
        'output' => @shell_exec($cmd),
        'shell_functions' => [
            'shell_exec' => function_exists('shell_exec'),
            'exec' => function_exists('exec'),
            'system' => function_exists('system'),
            'passthru' => function_exists('passthru'),
            'proc_open' => function_exists('proc_open')
        ]
    ];
}

echo json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
