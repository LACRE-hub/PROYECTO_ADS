<?php
// ── RNF-07 / RT-06: Verificación de sesión activa ────────────────
// Reporta tiempo restante y emite advertencia cuando faltan ≤ 2 min (MSG-03).
// NO renueva last_activity — eso solo ocurre cuando el usuario ejecuta
// acciones reales (requireAdmin/requirePatient en cada endpoint).
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario'], $_SESSION['last_activity'])) {
    http_response_code(401);
    // ERR-04: Sesión expirada
    echo json_encode([
        'activa'   => false,
        'mensaje'  => 'La sesión ha expirado por inactividad. Inicie sesión nuevamente.',
        'cod'      => 'ERR-04',
    ]);
    exit;
}

$elapsed   = time() - (int)$_SESSION['last_activity'];
$restantes = max(0, 900 - $elapsed);   // 900 s = 15 min (RNF-07)

if ($elapsed >= 900) {
    // Destruir sesión expirada
    session_destroy();
    http_response_code(401);
    echo json_encode([
        'activa'   => false,
        'mensaje'  => 'La sesión ha expirado por inactividad. Inicie sesión nuevamente.',
        'cod'      => 'ERR-04',
    ]);
    exit;
}

// MSG-03: Advertencia cuando faltan ≤ 120 segundos (2 min)
$advertencia = ($restantes <= 120);

echo json_encode([
    'activa'            => true,
    'segundos_restantes'=> $restantes,
    'advertencia'       => $advertencia,
    // MSG-03 solo se incluye cuando corresponde
    'mensaje'           => $advertencia
        ? 'Su sesión está por expirar por inactividad.'
        : null,
    'cod'               => $advertencia ? 'MSG-03' : null,
]);
