<?php
/**
 * Middleware de autenticación y auditoría
 * Incluir en todos los endpoints de la API
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/db.php';

// ── Verificar sesión de administrador ────────────────────────────
function requireAdmin(): array {
    if (!isset($_SESSION['usuario'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No autenticado', 'redirect' => '/PROYECTO_ADS/index.html']);
        exit;
    }
    $u = $_SESSION['usuario'];
    if (($u['id_rol'] ?? 0) !== 1) {
        http_response_code(403);
        registrarAccesoNoAutorizado($u);
        echo json_encode(['error' => 'Acceso no autorizado para este rol']);
        exit;
    }
    // Renovar sesión (reset timeout)
    $_SESSION['last_activity'] = time();
    return $u;
}

// ── Verificar sesión de paciente ─────────────────────────────────
function requirePatient(): array {
    if (!isset($_SESSION['usuario'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No autenticado', 'redirect' => '/PROYECTO_ADS/index.html']);
        exit;
    }
    $u = $_SESSION['usuario'];
    if (($u['portal'] ?? '') !== 'paciente') {
        http_response_code(403);
        echo json_encode(['error' => 'Acceso no autorizado']);
        exit;
    }
    $_SESSION['last_activity'] = time();
    return $u;
}

// ── Registrar en bitácora de auditoría ───────────────────────────
function registrarLog(PDO $pdo, int $idUsuario, string $modulo, string $accion, string $detalle = ''): void {
    try {
        $ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $stmt = $pdo->prepare(
            "INSERT INTO LOG_AUDITORIA (id_usuario, modulo, accion, fecha_hora, ip_equipo, detalle_cambio)
             VALUES (?, ?, ?, NOW(), ?, ?)"
        );
        $stmt->execute([$idUsuario, $modulo, $accion, $ip, $detalle]);
    } catch (Exception $e) {
        // No bloquear la operación principal si el log falla
        error_log('Log auditoria fallido: ' . $e->getMessage());
    }
}

// ── Registrar intento de acceso no autorizado ────────────────────
function registrarAccesoNoAutorizado(array $usuario): void {
    try {
        $pdo = getDB();
        registrarLog($pdo, $usuario['id'], 'Sistema', 'Acceso no autorizado',
            'Intento de acceso a módulo restringido. Rol: ' . ($usuario['tipo'] ?? 'desconocido'));
    } catch (Exception $e) { /* silent */ }
}

// ── Verificar sesión activa (para ping desde JS) ─────────────────
function isSessionActive(): bool {
    if (!isset($_SESSION['usuario'], $_SESSION['last_activity'])) return false;
    $elapsed = time() - (int)$_SESSION['last_activity'];
    if ($elapsed > 900) { // 15 minutos
        session_destroy();
        return false;
    }
    return true;
}
