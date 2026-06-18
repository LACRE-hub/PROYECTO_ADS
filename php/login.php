<?php
session_start();
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}
$tipo          = trim($data['tipo']          ?? '');
$identificador = trim($data['identificador'] ?? '');
$password      = $data['password']           ?? '';
$captchaToken  = $data['captcha']            ?? '';
if (!$tipo || !$identificador || !$password) {
    echo json_encode(['success' => false, 'message' => 'Existen campos obligatorios sin completar. Revise el formulario antes de continuar.', 'cod' => 'ERR-17']);
    exit;
}
if ($tipo !== 'paciente') {
    if (!verificarCaptcha($captchaToken)) {
        echo json_encode(['success' => false, 'message' => 'El cÃ³digo CAPTCHA es incorrecto. Intente nuevamente.', 'cod' => 'ERR-02']);
        exit;
    }
}
if ($tipo !== 'paciente' && !preg_match('/^\d{10}$/', $identificador)) {
    echo json_encode(['success' => false, 'message' => 'NÃºmero de empleado invÃ¡lido.', 'cod' => 'ERR-01']);
    exit;
}
try {
    $pdo = getDB();
    if ($tipo === 'paciente') {
        autenticarPaciente($pdo, $data);
    } else {
        autenticarEmpleado($pdo, $tipo, $identificador, $password);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No fue posible conectar con el sistema. Verifique su conexiÃ³n o contacte al soporte tÃ©cnico.', 'cod' => 'ERR-21']);
}
function verificarCaptcha(string $token): bool {
    if (empty(trim($token))) return false;
    $url     = 'https://www.google.com/recaptcha/api/siteverify';
    $payload = http_build_query(['secret' => RECAPTCHA_SECRET, 'response' => $token]);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
    } else {
        $opts = ['http' => ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'content' => $payload, 'timeout' => 5]];
        $resp = @file_get_contents($url, false, stream_context_create($opts));
    }
    if (!$resp) return false;
    $data = json_decode($resp, true);
    return !empty($data['success']);
}
function autenticarEmpleado(PDO $pdo, string $tipo, string $numeroEmpleado, string $password): void {
    $rolesPermitidos = [
        'admin'      => [1],
        'medico'     => [2],
        'enfermero'  => [3],
        'worker'     => [4, 6],   // FarmacÃ©utico y TÃ©cnico Lab
        'secretario' => [5],      // Cajero
    ];
    $redirects = [
        'admin'      => '/PROYECTO_ADS/panel_Administrador.html',
        'medico'     => '/PROYECTO_ADS/panel_Doctor.html',
        'enfermero'  => '/PROYECTO_ADS/panel_Nurse.html',
        'worker'     => '/PROYECTO_ADS/panel_Worker.html',
        'secretario' => '/PROYECTO_ADS/panel_Secretary.html',
    ];
    if (!isset($rolesPermitidos[$tipo])) {
        echo json_encode(['success' => false, 'message' => 'Tipo de acceso no reconocido.', 'cod' => 'ERR-01']);
        exit;
    }
    $placeholders = implode(',', array_fill(0, count($rolesPermitidos[$tipo]), '?'));
    $sql = "SELECT id_empleado, nombre, apellido_paterno, tipo, estatus,
                   contrasena_hash, id_rol
            FROM EMPLEADO
            WHERE numero_empleado = ?
              AND id_rol IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$numeroEmpleado], $rolesPermitidos[$tipo]));
    $empleado = $stmt->fetch();
    if (!$empleado) {
        respuestaInvalida();
    }
    if ($empleado['estatus'] === 'Baja') {
        echo json_encode(['success' => false, 'message' => 'Esta cuenta estÃ¡ inactiva. Contacta a Recursos Humanos.', 'cod' => 'ERR-04']);
        exit;
    }
    if (!password_verify($password, $empleado['contrasena_hash'])) {
        respuestaInvalida();
    }
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $pdo->prepare(
            "INSERT INTO LOG_AUDITORIA (id_usuario, modulo, accion, fecha_hora, ip_equipo, detalle_cambio)
             VALUES (?, 'Seguridad', 'Inicio de sesiÃ³n', NOW(), ?, ?)"
        )->execute([
            $empleado['id_empleado'],
            $ip,
            'Portal: ' . $tipo . ' | NÃºmero empleado: ' . $numeroEmpleado
        ]);
    } catch (Exception $e) {
        error_log('Log de login fallido: ' . $e->getMessage());
    }
    $_SESSION = [];
    $_SESSION['usuario'] = [
        'id'       => $empleado['id_empleado'],
        'nombre'   => $empleado['nombre'] . ' ' . $empleado['apellido_paterno'],
        'tipo'     => $empleado['tipo'],
        'id_rol'   => $empleado['id_rol'],
        'portal'   => $tipo,
    ];
    $_SESSION['last_activity'] = time();
    session_write_close();
    echo json_encode([
        'success'  => true,
        'redirect' => $redirects[$tipo],
        'nombre'   => $empleado['nombre'] . ' ' . $empleado['apellido_paterno'],
        'mensaje'  => 'Inicio de sesiÃ³n exitoso. Bienvenido al sistema.',
        'cod'      => 'MSG-01',
    ]);
}
function autenticarPaciente(PDO $pdo, array $data): void {
    $expediente = strtoupper(trim($data['identificador'] ?? ''));
    $apellido4  = strtoupper(trim($data['password']      ?? ''));
    $fechaNac   = trim($data['fecha_nacimiento']          ?? '');
    if (!$expediente || !$apellido4 || !$fechaNac) {
        echo json_encode(['success' => false, 'message' => 'Existen campos obligatorios sin completar. Revise el formulario antes de continuar.', 'cod' => 'ERR-17']);
        exit;
    }
    if (!preg_match('/^\d{10}$/', $expediente)) {
        echo json_encode(['success' => false, 'message' => 'NÃºmero de expediente invÃ¡lido.', 'cod' => 'ERR-01']);
        exit;
    }
    if (!preg_match('/^[A-ZÃÃ‰ÃÃ“ÃšÃœÃ‘]{4}$/iu', $apellido4)) {
        echo json_encode(['success' => false, 'message' => 'ContraseÃ±a invÃ¡lida (4 letras del apellido).', 'cod' => 'ERR-01']);
        exit;
    }
    $stmt = $pdo->prepare(
        "SELECT id_paciente, nombre, apellido_paterno, fecha_nacimiento
         FROM PACIENTE
         WHERE numero_expediente = ?"
    );
    $stmt->execute([$expediente]);
    $paciente = $stmt->fetch();
    if (!$paciente) {
        respuestaInvalida();
    }
    $apellidoDB = normalizarTexto(mb_substr($paciente['apellido_paterno'], 0, 4, 'UTF-8'));
    $apellido4N = normalizarTexto($apellido4);
    $fechaDB    = $paciente['fecha_nacimiento'];   // formato YYYY-MM-DD
    if ($apellidoDB !== $apellido4N || $fechaDB !== $fechaNac) {
        respuestaInvalida();
    }
    $_SESSION = [];
    $_SESSION['usuario'] = [
        'id'     => $paciente['id_paciente'],
        'nombre' => $paciente['nombre'] . ' ' . $paciente['apellido_paterno'],
        'tipo'   => 'Paciente',
        'portal' => 'paciente',
    ];
    $_SESSION['last_activity'] = time();
    session_write_close();
    echo json_encode([
        'success'  => true,
        'redirect' => '/PROYECTO_ADS/panel_Patient.html',
        'nombre'   => $_SESSION['usuario']['nombre'],
        'mensaje'  => 'Inicio de sesiÃ³n exitoso. Bienvenido al sistema.',
        'cod'      => 'MSG-01',
    ]);
}
function normalizarTexto(string $str): string {
    $str  = mb_strtoupper($str, 'UTF-8');
    $from = ['Ã','Ã‰','Ã','Ã“','Ãš','Ãœ','Ã‘','Ã€','Ãˆ','ÃŒ','Ã’','Ã™','Ã‚','ÃŠ','ÃŽ','Ã”','Ã›'];
    $to   = ['A','E','I','O','U','U','N','A','E','I','O','U','A','E','I','O','U'];
    return str_replace($from, $to, $str);
}
function respuestaInvalida(): void {
    echo json_encode([
        'success' => false,
        'message' => 'NÃºmero de empleado o contraseÃ±a incorrectos. Verifique sus datos e intente nuevamente.',
        'cod'     => 'ERR-01',
    ]);
    exit;
}
