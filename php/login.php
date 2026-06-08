<?php
session_start();
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

// Solo POST
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

// ── RN-01 / RT-05: Verificar CAPTCHA en el servidor ──────────────
// El paciente usa credenciales distintas (no tiene CAPTCHA de área)
if ($tipo !== 'paciente') {
    if (!verificarCaptcha($captchaToken)) {
        echo json_encode(['success' => false, 'message' => 'El código CAPTCHA es incorrecto. Intente nuevamente.', 'cod' => 'ERR-02']);
        exit;
    }
}

// Validación básica server-side
if ($tipo !== 'paciente' && !preg_match('/^\d{10}$/', $identificador)) {
    echo json_encode(['success' => false, 'message' => 'Número de empleado inválido.', 'cod' => 'ERR-01']);
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
    // ERR-21: No exponer detalles técnicos (RT-13)
    echo json_encode(['success' => false, 'message' => 'No fue posible conectar con el sistema. Verifique su conexión o contacte al soporte técnico.', 'cod' => 'ERR-21']);
}

// ── Verificación reCAPTCHA (server-side) ─────────────────────────
function verificarCaptcha(string $token): bool {
    if (empty(trim($token))) return false;

    $url     = 'https://www.google.com/recaptcha/api/siteverify';
    $payload = http_build_query(['secret' => RECAPTCHA_SECRET, 'response' => $token]);

    // Intentar con cURL primero, luego file_get_contents
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

// ── Autenticación de empleado ────────────────────────────────────
function autenticarEmpleado(PDO $pdo, string $tipo, string $numeroEmpleado, string $password): void {
    // Mapeo portal → ids de rol permitidos
    $rolesPermitidos = [
        'admin'      => [1],
        'medico'     => [2],
        'enfermero'  => [3],
        'worker'     => [4, 6],   // Farmacéutico y Técnico Lab
        'secretario' => [5],      // Cajero
    ];

    // Destino de redirección por tipo
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
        // ERR-01: No revelar si el usuario existe o no (seguridad)
        respuestaInvalida();
    }

    // RN-03 / ERR-04: cuenta inactiva
    if ($empleado['estatus'] === 'Baja') {
        echo json_encode(['success' => false, 'message' => 'Esta cuenta está inactiva. Contacta a Recursos Humanos.', 'cod' => 'ERR-04']);
        exit;
    }

    if (!password_verify($password, $empleado['contrasena_hash'])) {
        // ERR-01
        respuestaInvalida();
    }

    // ── RT-08: Registrar inicio de sesión en bitácora ────────────
    // (CU-ADM-01 CA-03, CU-ADM-01 Paso 7)
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $pdo->prepare(
            "INSERT INTO LOG_AUDITORIA (id_usuario, modulo, accion, fecha_hora, ip_equipo, detalle_cambio)
             VALUES (?, 'Seguridad', 'Inicio de sesión', NOW(), ?, ?)"
        )->execute([
            $empleado['id_empleado'],
            $ip,
            'Portal: ' . $tipo . ' | Número empleado: ' . $numeroEmpleado
        ]);
    } catch (Exception $e) {
        error_log('Log de login fallido: ' . $e->getMessage());
    }

    // Sesión
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

    // MSG-01: Inicio de sesión exitoso
    echo json_encode([
        'success'  => true,
        'redirect' => $redirects[$tipo],
        'nombre'   => $empleado['nombre'] . ' ' . $empleado['apellido_paterno'],
        'mensaje'  => 'Inicio de sesión exitoso. Bienvenido al sistema.',
        'cod'      => 'MSG-01',
    ]);
}

// ── Autenticación de paciente ────────────────────────────────────
// Credenciales: número de expediente + primeras 4 letras del apellido paterno + fecha de nacimiento
function autenticarPaciente(PDO $pdo, array $data): void {
    $expediente = strtoupper(trim($data['identificador'] ?? ''));
    $apellido4  = strtoupper(trim($data['password']      ?? ''));
    $fechaNac   = trim($data['fecha_nacimiento']          ?? '');

    if (!$expediente || !$apellido4 || !$fechaNac) {
        echo json_encode(['success' => false, 'message' => 'Existen campos obligatorios sin completar. Revise el formulario antes de continuar.', 'cod' => 'ERR-17']);
        exit;
    }

    if (!preg_match('/^\d{10}$/', $expediente)) {
        echo json_encode(['success' => false, 'message' => 'Número de expediente inválido.', 'cod' => 'ERR-01']);
        exit;
    }

    if (!preg_match('/^[A-ZÁÉÍÓÚÜÑ]{4}$/iu', $apellido4)) {
        echo json_encode(['success' => false, 'message' => 'Contraseña inválida (4 letras del apellido).', 'cod' => 'ERR-01']);
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

    // Normalizar acentos para comparación (López → LOPE, Ávila → AVIL)
    $apellidoDB = normalizarTexto(mb_substr($paciente['apellido_paterno'], 0, 4, 'UTF-8'));
    $apellido4N = normalizarTexto($apellido4);
    $fechaDB    = $paciente['fecha_nacimiento'];   // formato YYYY-MM-DD

    if ($apellidoDB !== $apellido4N || $fechaDB !== $fechaNac) {
        respuestaInvalida();
    }

    // Limpiar sesión anterior sin destruirla
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
        'mensaje'  => 'Inicio de sesión exitoso. Bienvenido al sistema.',
        'cod'      => 'MSG-01',
    ]);
}

function normalizarTexto(string $str): string {
    $str  = mb_strtoupper($str, 'UTF-8');
    $from = ['Á','É','Í','Ó','Ú','Ü','Ñ','À','È','Ì','Ò','Ù','Â','Ê','Î','Ô','Û'];
    $to   = ['A','E','I','O','U','U','N','A','E','I','O','U','A','E','I','O','U'];
    return str_replace($from, $to, $str);
}

function respuestaInvalida(): void {
    // ERR-01: No distinguir si es usuario o contraseña incorrectos (seguridad)
    echo json_encode([
        'success' => false,
        'message' => 'Número de empleado o contraseña incorrectos. Verifique sus datos e intente nuevamente.',
        'cod'     => 'ERR-01',
    ]);
    exit;
}
