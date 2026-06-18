<?php
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/middleware/auth_check.php';
require_once dirname(__DIR__) . '/db.php';
$usuario = requireAdmin();
$pdo     = getDB();
$method  = $_SERVER['REQUEST_METHOD'];
try {
    if ($method === 'GET') {
        $rows = $pdo->query(
            "SELECT m.id_mantenimiento, m.fecha_hora_inicio, m.duracion_minutos,
                    m.descripcion, m.estatus, m.id_administrador,
                    COUNT(am.id_alerta) AS total_alertas
             FROM MANTENIMIENTO m
             LEFT JOIN ALERTA_MANTENIMIENTO am ON am.id_mantenimiento = m.id_mantenimiento
             GROUP BY m.id_mantenimiento
             ORDER BY m.fecha_hora_inicio DESC"
        )->fetchAll();
        echo json_encode(['mantenimientos' => $rows]);
        exit;
    }
    $body        = json_decode(file_get_contents('php://input'), true) ?? [];
    $action      = $body['action'] ?? 'crear';
    $fechaInicio = $body['fecha_hora_inicio'] ?? '';
    $duracion    = (int)($body['duracion_minutos'] ?? 0);
    $desc        = trim($body['descripcion'] ?? '');
    if ($action === 'crear') {
        if (!$fechaInicio || $duracion <= 0 || !$desc) {
            echo json_encode([
                'error' => 'Existen campos obligatorios sin completar. Revise el formulario antes de continuar.',
                'cod'   => 'ERR-17',
            ]);
            exit;
        }
        if ($duracion > 15) {
            echo json_encode([
                'error' => 'La ventana de mantenimiento no puede exceder 15 minutos (RNF-09). Ajuste la duraciÃ³n e intente nuevamente.',
                'cod'   => 'ERR-25',
            ]);
            exit;
        }
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO MANTENIMIENTO (id_administrador, fecha_hora_inicio, duracion_minutos, descripcion, estatus)
                 VALUES (?, ?, ?, ?, 'Programado')"
            );
            $stmt->execute([$usuario['id'], $fechaInicio, $duracion, $desc]);
            $idMant = (int)$pdo->lastInsertId();
            $areas  = ['Urgencias', 'HospitalizaciÃ³n', 'MÃ©dicos', 'EnfermerÃ­a', 'Farmacia'];
            $alerta = $pdo->prepare(
                "INSERT INTO ALERTA_MANTENIMIENTO (id_mantenimiento, area_notificada, fecha_envio, estatus_envio)
                 VALUES (?, ?, NOW(), 'Enviado')"
            );
            foreach ($areas as $area) {
                $alerta->execute([$idMant, $area]);
            }
            registrarLog($pdo, $usuario['id'], 'Sistema', 'ProgramaciÃ³n de mantenimiento',
                "Mantenimiento programado: $fechaInicio, $duracion min. $desc");
            $pdo->commit();
            echo json_encode([
                'success'         => true,
                'id_mantenimiento'=> $idMant,
                'mensaje'         => 'Ventana de mantenimiento programada. Las Ã¡reas crÃ­ticas han sido notificadas.',
                'cod'             => 'MSG-36',
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    } elseif ($action === 'cancelar') {
        $id = (int)($body['id_mantenimiento'] ?? 0);
        if (!$id) {
            echo json_encode([
                'error' => 'Existen campos obligatorios sin completar. Revise el formulario antes de continuar.',
                'cod'   => 'ERR-17',
            ]);
            exit;
        }
        $stmt = $pdo->prepare(
            "UPDATE MANTENIMIENTO SET estatus = 'Cancelado' WHERE id_mantenimiento = ? AND estatus = 'Programado'"
        );
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            echo json_encode([
                'error' => 'El mantenimiento no existe o ya no estÃ¡ en estado programado.',
                'cod'   => 'ERR-22',
            ]);
            exit;
        }
        registrarLog($pdo, $usuario['id'], 'Sistema', 'CancelaciÃ³n de mantenimiento',
            "Mantenimiento ID $id cancelado");
        echo json_encode([
            'success' => true,
            'mensaje' => 'Ventana de mantenimiento cancelada correctamente.',
            'cod'     => 'MSG-37',
        ]);
    } else {
        echo json_encode([
            'error' => 'AcciÃ³n no reconocida.',
            'cod'   => 'ERR-22',
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log('mantenimiento.php error: ' . $e->getMessage());
    echo json_encode([
        'error' => 'Ha ocurrido un error inesperado. La incidencia ha sido registrada. Contacte al administrador.',
        'cod'   => 'ERR-22',
    ]);
}
