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
        // Lista de precios pendientes de validación
        $rows = $pdo->query(
            "SELECT l.id_lote, l.numero_lote, l.precio_propuesto, l.precio_vigente,
                    l.estatus_precio, l.fecha_caducidad, l.existencia_actual,
                    m.nombre_sustancia, m.tipo_medicamento,
                    CONCAT(e.nombre,' ',e.apellido_paterno) AS farmaceutico
             FROM LOTE_MEDICAMENTO l
             JOIN MEDICAMENTO m ON l.id_medicamento = m.id_medicamento
             LEFT JOIN EMPLEADO e ON l.id_farmaceutico = e.id_empleado
             ORDER BY
               CASE l.estatus_precio WHEN 'Pendiente' THEN 0 WHEN 'Vigente' THEN 1 ELSE 2 END,
               m.nombre_sustancia"
        )->fetchAll();
        echo json_encode(['lotes' => $rows]);
        exit;
    }

    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';
    $idLote = (int)($body['id_lote'] ?? 0);

    if (!$idLote) { echo json_encode(['error' => 'ID de lote requerido']); exit; }

    // Obtener datos actuales del lote
    $lote = $pdo->prepare("SELECT * FROM LOTE_MEDICAMENTO WHERE id_lote = ?")->execute([$idLote]);

    if ($action === 'aprobar') {
        // RN-12: validar precio propuesto → vigente
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "UPDATE LOTE_MEDICAMENTO
                 SET estatus_precio = 'Vigente',
                     precio_vigente = precio_propuesto,
                     id_administrador_valida = ?
                 WHERE id_lote = ? AND estatus_precio = 'Pendiente'"
            );
            $stmt->execute([$usuario['id'], $idLote]);

            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                echo json_encode(['error' => 'Precio ya validado o lote no encontrado']); exit;
            }

            registrarLog($pdo, $usuario['id'], 'Farmacia', 'Validación de precio',
                "Precio aprobado para lote ID $idLote");

            $pdo->commit();
            echo json_encode(['success' => true, 'mensaje' => 'Precio validado y activado en caja']);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

    } elseif ($action === 'rechazar') {
        $motivo = trim($body['motivo'] ?? '');
        $pdo->prepare(
            "UPDATE LOTE_MEDICAMENTO SET estatus_precio = 'Rechazado' WHERE id_lote = ?"
        )->execute([$idLote]);

        registrarLog($pdo, $usuario['id'], 'Farmacia', 'Rechazo de precio',
            "Precio rechazado para lote ID $idLote. Motivo: $motivo");

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Acción no reconocida']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de base de datos']);
}
