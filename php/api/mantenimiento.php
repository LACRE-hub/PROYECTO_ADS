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
        // RF-21: duración máxima 15 minutos (RN/RT)
        if ($duracion > 15) {
            echo json_encode(['error' => 'La ventana de mantenimiento no puede exceder 15 minutos (RNF-09)']); exit;
        }
        if (!$fechaInicio || $duracion <= 0 || !$desc) {
            echo json_encode(['error' => 'Todos los campos son requeridos']); exit;
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO MANTENIMIENTO (id_administrador, fecha_hora_inicio, duracion_minutos, descripcion, estatus)
                 VALUES (?, ?, ?, ?, 'Programado')"
            );
            $stmt->execute([$usuario['id'], $fechaInicio, $duracion, $desc]);
            $idMant = (int)$pdo->lastInsertId();

            // RF-22: generar alertas para áreas críticas
            $areas  = ['Urgencias', 'Hospitalización', 'Médicos', 'Enfermería', 'Farmacia'];
            $alerta = $pdo->prepare(
                "INSERT INTO ALERTA_MANTENIMIENTO (id_mantenimiento, area_notificada, fecha_envio, estatus_envio)
                 VALUES (?, ?, NOW(), 'Enviado')"
            );
            foreach ($areas as $area) {
                $alerta->execute([$idMant, $area]);
            }

            registrarLog($pdo, $usuario['id'], 'Sistema', 'Programación de mantenimiento',
                "Mantenimiento programado: $fechaInicio, $duracion min. $desc");

            $pdo->commit();
            echo json_encode(['success' => true, 'id_mantenimiento' => $idMant]);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

    } elseif ($action === 'cancelar') {
        $id = (int)($body['id_mantenimiento'] ?? 0);
        $pdo->prepare(
            "UPDATE MANTENIMIENTO SET estatus = 'Cancelado' WHERE id_mantenimiento = ? AND estatus = 'Programado'"
        )->execute([$id]);
        registrarLog($pdo, $usuario['id'], 'Sistema', 'Cancelación de mantenimiento',
            "Mantenimiento ID $id cancelado");
        echo json_encode(['success' => true]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de base de datos']);
}
