<?php
header('Content-Type: application/json');

echo json_encode([
    'status'  => 'ok',
    'message' => 'API funcionando',
    'server'  => $_SERVER['SERVER_SOFTWARE'] ?? 'desconocido',
    'php'     => PHP_VERSION,
    'time'    => date('Y-m-d H:i:s'),
]);
