<?php
session_start();
header('Content-Type: application/json');
echo json_encode([
    'session_id'      => session_id(),
    'session_status'  => session_status(),
    'usuario'         => $_SESSION['usuario'] ?? null,
    'last_activity'   => $_SESSION['last_activity'] ?? null,
    'all_keys'        => array_keys($_SESSION),
    'save_path'       => session_save_path(),
    'cookie_params'   => session_get_cookie_params(),
    'phpsessid_cookie'=> $_COOKIE['PHPSESSID'] ?? 'NO COOKIE',
]);
