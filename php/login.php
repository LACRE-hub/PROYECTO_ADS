<?php
session_start();
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/db.php';

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

$tipo        = trim($data['tipo']        ?? '');
$identificador = trim($data['identificador'] ?? '');
$password    = $data['password']         ?? '';

if (!$tipo || !$identificador || !$password) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
    exit;
}

// Validación básica server-side
if ($tipo !== 'paciente' && !preg_match('/^\d{10}$/', $identificador)) {
    echo json_encode(['success' => false, 'message' => 'Número de empleado inválido.']);
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
    echo json_encode(['success' => false, 'message' => 'Error de base de datos. Contacta al administrador.']);
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
        echo json_encode(['success' => false, 'message' => 'Tipo de acceso no reconocido.']);
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

    if ($empleado['estatus'] !== 'Activo') {
        echo json_encode(['success' => false, 'message' => 'Esta cuenta está inactiva. Contacta a Recursos Humanos.']);
        exit;
    }

    if (!password_verify($password, $empleado['contrasena_hash'])) {
        respuestaInvalida();
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

    echo json_encode([
        'success'  => true,
        'redirect' => $redirects[$tipo],
        'nombre'   => $_SESSION['usuario']['nombre'],
    ]);
}

// ── Autenticación de paciente ────────────────────────────────────
// Credenciales: número de expediente + primeras 4 letras del apellido paterno + fecha de nacimiento
function autenticarPaciente(PDO $pdo, array $data): void {
    $expediente = strtoupper(trim($data['identificador'] ?? ''));
    $apellido4  = strtoupper(trim($data['password']      ?? ''));
    $fechaNac   = trim($data['fecha_nacimiento']          ?? '');

    if (!$expediente || !$apellido4 || !$fechaNac) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
        exit;
    }

    if (!preg_match('/^\d{10}$/', $expediente)) {
        echo json_encode(['success' => false, 'message' => 'Número de expediente inválido.']);
        exit;
    }

    if (!preg_match('/^[A-ZÁÉÍÓÚÜÑ]{4}$/iu', $apellido4)) {
        echo json_encode(['success' => false, 'message' => 'Contraseña inválida (4 letras del apellido).']);
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

    // Limpiar sesión anterior sin destruirla (evita perder el nuevo cookie)
    $_SESSION = [];
    $_SESSION['usuario'] = [
        'id'     => $paciente['id_paciente'],
        'nombre' => $paciente['nombre'] . ' ' . $paciente['apellido_paterno'],
        'tipo'   => 'Paciente',
        'portal' => 'paciente',
    ];
    $_SESSION['last_activity'] = time();
    session_write_close(); // Forzar escritura a disco antes de responder

    echo json_encode([
        'success'  => true,
        'redirect' => '/PROYECTO_ADS/panel_Patient.html',
        'nombre'   => $_SESSION['usuario']['nombre'],
    ]);
}

function normalizarTexto(string $str): string {
    // Convertir a mayúsculas con soporte UTF-8
    $str = mb_strtoupper($str, 'UTF-8');
    // Eliminar acentos y caracteres especiales del español
    $from = ['Á','É','Í','Ó','Ú','Ü','Ñ','À','È','Ì','Ò','Ù','Â','Ê','Î','Ô','Û'];
    $to   = ['A','E','I','O','U','U','N','A','E','I','O','U','A','E','I','O','U'];
    return str_replace($from, $to, $str);
}

function respuestaInvalida(): void {
    echo json_encode(['success' => false, 'message' => 'Credenciales incorrectas.']);
    exit;
}
