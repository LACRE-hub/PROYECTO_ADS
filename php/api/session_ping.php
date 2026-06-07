<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['activa' => false]);
    exit;
}

$elapsed = time() - (int)($_SESSION['last_activity'] ?? time());

if ($elapsed > 900) {
    session_destroy();
    http_response_code(401);
    echo json_encode(['activa' => false]);
    exit;
}

$_SESSION['last_activity'] = time();
echo json_encode(['activa' => true, 'segundos_restantes' => 900 - $elapsed]);
