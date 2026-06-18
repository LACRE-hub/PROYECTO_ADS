<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['usuario'], $_SESSION['last_activity'])) {
    http_response_code(401);
    echo json_encode([
        'activa'   => false,
        'mensaje'  => 'La sesiÃ³n ha expirado por inactividad. Inicie sesiÃ³n nuevamente.',
        'cod'      => 'ERR-04',
    ]);
    exit;
}
$elapsed   = time() - (int)$_SESSION['last_activity'];
$restantes = max(0, 900 - $elapsed);   // 900 s = 15 min (RNF-07)
if ($elapsed >= 900) {
    session_destroy();
    http_response_code(401);
    echo json_encode([
        'activa'   => false,
        'mensaje'  => 'La sesiÃ³n ha expirado por inactividad. Inicie sesiÃ³n nuevamente.',
        'cod'      => 'ERR-04',
    ]);
    exit;
}
$advertencia = ($restantes <= 120);
echo json_encode([
    'activa'            => true,
    'segundos_restantes'=> $restantes,
    'advertencia'       => $advertencia,
    'mensaje'           => $advertencia
        ? 'Su sesiÃ³n estÃ¡ por expirar por inactividad.'
        : null,
    'cod'               => $advertencia ? 'MSG-03' : null,
]);
