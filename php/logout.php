<?php
session_start();
if (isset($_SESSION['usuario'])) {
    try {
        require_once __DIR__ . '/db.php';
        $pdo    = getDB();
        $userId = $_SESSION['usuario']['id'] ?? null;
        $portal = $_SESSION['usuario']['portal'] ?? 'desconocido';
        if ($userId) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $motivo = isset($_GET['timeout']) ? 'Cierre automÃ¡tico por inactividad (15 min)' : 'Cierre de sesiÃ³n manual';
            $pdo->prepare(
                "INSERT INTO LOG_AUDITORIA (id_usuario, modulo, accion, fecha_hora, ip_equipo, detalle_cambio)
                 VALUES (?, 'Seguridad', 'Cierre de sesiÃ³n', NOW(), ?, ?)"
            )->execute([$userId, $ip, $motivo . ' | Portal: ' . $portal]);
        }
    } catch (Exception $e) {
        error_log('Log de logout fallido: ' . $e->getMessage());
    }
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'SesiÃ³n cerrada correctamente.', 'cod' => 'MSG-04']);
} else {
    header('Location: /PROYECTO_ADS/index.html');
}
exit;
