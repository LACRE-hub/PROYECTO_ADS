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
    if (!isset($_SESSION['usuario'], $_SESSION['last_activity'])) {
        http_response_code(401);
        echo json_encode([
            'error'    => 'No autenticado',
            'mensaje'  => 'La sesión ha expirado por inactividad. Inicie sesión nuevamente.',
            'cod'      => 'ERR-04',
            'redirect' => '/PROYECTO_ADS/index.html',
        ]);
        exit;
    }

    // RNF-07 / RT-06: verificar timeout de 15 min (900 s)
    if ((time() - (int)$_SESSION['last_activity']) >= 900) {
        session_destroy();
        http_response_code(401);
        echo json_encode([
            'error'    => 'Sesión expirada',
            'mensaje'  => 'La sesión ha expirado por inactividad. Inicie sesión nuevamente.',
            'cod'      => 'ERR-04',
            'redirect' => '/PROYECTO_ADS/index.html',
        ]);
        exit;
    }

    $u = $_SESSION['usuario'];

    // RN-02 / RNF-04: solo rol Administrador (id_rol = 1)
    if (($u['id_rol'] ?? 0) !== 1) {
        http_response_code(403);
        registrarAccesoNoAutorizado($u);
        echo json_encode([
            'error'  => 'No cuenta con permisos para acceder a este módulo.',
            'cod'    => 'ERR-03',
        ]);
        exit;
    }

    // Renovar last_activity en cada acción real del usuario
    $_SESSION['last_activity'] = time();
    return $u;
}

// ── Verificar sesión de paciente ─────────────────────────────────
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
            'error'    => 'Sesión expirada',
            'mensaje'  => 'La sesión ha expirado por inactividad. Inicie sesión nuevamente.',
            'cod'      => 'ERR-04',
            'redirect' => '/PROYECTO_ADS/index.html',
        ]);
        exit;
    }

    $u = $_SESSION['usuario'];
    if (($u['portal'] ?? '') !== 'paciente') {
        http_response_code(403);
        echo json_encode(['error' => 'No cuenta con permisos para acceder a este módulo.', 'cod' => 'ERR-03']);
        exit;
    }

    $_SESSION['last_activity'] = time();
    return $u;
}

// ── Registrar en bitácora de auditoría (RT-08) ───────────────────
// Guarda: actor, módulo, acción, timestamp, IP, detalle (valor anterior vs. nuevo)
function registrarLog(PDO $pdo, int $idUsuario, string $modulo, string $accion, string $detalle = ''): void {
    try {
        $ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $pdo->prepare(
            "INSERT INTO LOG_AUDITORIA (id_usuario, modulo, accion, fecha_hora, ip_equipo, detalle_cambio)
             VALUES (?, ?, ?, NOW(), ?, ?)"
        )->execute([$idUsuario, $modulo, $accion, $ip, $detalle]);
    } catch (Exception $e) {
        // No bloquear la operación principal si el log falla
        error_log('Log auditoria fallido: ' . $e->getMessage());
    }
}

// ── Registrar intento de acceso no autorizado (RN-02) ───────────
function registrarAccesoNoAutorizado(array $usuario): void {
    try {
        $pdo = getDB();
        registrarLog(
            $pdo,
            $usuario['id'],
            'Seguridad',
            'Acceso no autorizado',
            'Intento de acceso a módulo restringido. Rol: ' . ($usuario['tipo'] ?? 'desconocido') .
            ' | Portal: ' . ($usuario['portal'] ?? '—')
        );
    } catch (Exception $e) { /* silent */ }
}

// ── Verificar si sesión está activa (solo lectura, sin renovar) ──
// Usar en session_ping.php — NO renueva last_activity
function isSessionActive(): bool {
    if (!isset($_SESSION['usuario'], $_SESSION['last_activity'])) return false;
    if ((time() - (int)$_SESSION['last_activity']) >= 900) {
        session_destroy();
        return false;
    }
    return true;
}
