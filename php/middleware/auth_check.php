<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/db.php';
function requireAdmin(): array {
    if (!isset($_SESSION['usuario'], $_SESSION['last_activity'])) {
        http_response_code(401);
        echo json_encode([
            'error'    => 'No autenticado',
            'mensaje'  => 'La sesiÃ³n ha expirado por inactividad. Inicie sesiÃ³n nuevamente.',
            'cod'      => 'ERR-04',
            'redirect' => '/PROYECTO_ADS/index.html',
        ]);
        exit;
    }
    if ((time() - (int)$_SESSION['last_activity']) >= 900) {
        session_destroy();
        http_response_code(401);
        echo json_encode([
            'error'    => 'SesiÃ³n expirada',
            'mensaje'  => 'La sesiÃ³n ha expirado por inactividad. Inicie sesiÃ³n nuevamente.',
            'cod'      => 'ERR-04',
            'redirect' => '/PROYECTO_ADS/index.html',
        ]);
        exit;
    }
    $u = $_SESSION['usuario'];
    if (($u['id_rol'] ?? 0) !== 1) {
        http_response_code(403);
        registrarAccesoNoAutorizado($u);
        echo json_encode([
            'error'  => 'No cuenta con permisos para acceder a este mÃ³dulo.',
            'cod'    => 'ERR-03',
        ]);
        exit;
    }
    $_SESSION['last_activity'] = time();
    return $u;
}
function requirePatient(): array {
    if (!isset($_SESSION['usuario'], $_SESSION['last_activity'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No autenticado', 'redirect' => '/PROYECTO_ADS/index.html']);
        exit;
    }
    if ((time() - (int)$_SESSION['last_activity']) >= 900) {
        session_destroy();
        http_response_code(401);
        echo json_encode([
            'error'    => 'SesiÃ³n expirada',
            'mensaje'  => 'La sesiÃ³n ha expirado por inactividad. Inicie sesiÃ³n nuevamente.',
            'cod'      => 'ERR-04',
            'redirect' => '/PROYECTO_ADS/index.html',
        ]);
        exit;
    }
    $u = $_SESSION['usuario'];
    if (($u['portal'] ?? '') !== 'paciente') {
        http_response_code(403);
        echo json_encode(['error' => 'No cuenta con permisos para acceder a este mÃ³dulo.', 'cod' => 'ERR-03']);
        exit;
    }
    $_SESSION['last_activity'] = time();
    return $u;
}
function registrarLog(PDO $pdo, int $idUsuario, string $modulo, string $accion, string $detalle = ''): void {
    try {
        $ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $pdo->prepare(
            "INSERT INTO LOG_AUDITORIA (id_usuario, modulo, accion, fecha_hora, ip_equipo, detalle_cambio)
             VALUES (?, ?, ?, NOW(), ?, ?)"
        )->execute([$idUsuario, $modulo, $accion, $ip, $detalle]);
    } catch (Exception $e) {
        error_log('Log auditoria fallido: ' . $e->getMessage());
    }
}
function registrarAccesoNoAutorizado(array $usuario): void {
    try {
        $pdo = getDB();
        registrarLog(
            $pdo,
            $usuario['id'],
            'Seguridad',
            'Acceso no autorizado',
            'Intento de acceso a mÃ³dulo restringido. Rol: ' . ($usuario['tipo'] ?? 'desconocido') .
            ' | Portal: ' . ($usuario['portal'] ?? 'â€”')
        );
    } catch (Exception $e) { }
}
function isSessionActive(): bool {
    if (!isset($_SESSION['usuario'], $_SESSION['last_activity'])) return false;
    if ((time() - (int)$_SESSION['last_activity']) >= 900) {
        session_destroy();
        return false;
    }
    return true;
}
