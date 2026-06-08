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
        // Lista de precios pendientes de validación (RF-14)
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

    if (!$idLote) {
        echo json_encode([
            'error' => 'Existen campos obligatorios sin completar. Revise el formulario antes de continuar.',
            'cod'   => 'ERR-17',
        ]);
        exit;
    }

    // Obtener datos actuales del lote (RT-11: prepared statement)
    $stmtLote = $pdo->prepare("SELECT * FROM LOTE_MEDICAMENTO WHERE id_lote = ?");
    $stmtLote->execute([$idLote]);
    $lote = $stmtLote->fetch();

    if (!$lote) {
        // ERR-10: Lote no encontrado
        echo json_encode([
            'error' => 'El lote de medicamento no fue encontrado en el sistema.',
            'cod'   => 'ERR-10',
        ]);
        exit;
    }

    if ($action === 'aprobar') {
        // RN-12: validar precio propuesto → vigente (RT-12: transacción)
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
                // ERR-11: Precio ya validado o no en estado pendiente
                echo json_encode([
                    'error' => 'El precio ya fue validado anteriormente o no está en estado pendiente.',
                    'cod'   => 'ERR-11',
                ]);
                exit;
            }

            registrarLog($pdo, $usuario['id'], 'Farmacia', 'Validación de precio',
                "Precio aprobado para lote ID $idLote. Precio anterior: " . $lote['precio_vigente'] .
                " | Precio nuevo: " . $lote['precio_propuesto']);

            $pdo->commit();
            // MSG-23: Precio validado exitosamente
            echo json_encode([
                'success' => true,
                'mensaje' => 'Precio validado y activado en caja correctamente.',
                'cod'     => 'MSG-23',
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

    } elseif ($action === 'rechazar') {
        $motivo = trim($body['motivo'] ?? '');
        if (!$motivo) {
            echo json_encode([
                'error' => 'Debe indicar el motivo del rechazo del precio propuesto.',
                'cod'   => 'ERR-17',
            ]);
            exit;
        }

        $pdo->prepare(
            "UPDATE LOTE_MEDICAMENTO SET estatus_precio = 'Rechazado' WHERE id_lote = ?"
        )->execute([$idLote]);

        registrarLog($pdo, $usuario['id'], 'Farmacia', 'Rechazo de precio',
            "Precio rechazado para lote ID $idLote. Motivo: $motivo");

        // MSG-24: Precio rechazado
        echo json_encode([
            'success' => true,
            'mensaje' => 'Precio rechazado. El farmacéutico deberá proponer un nuevo precio.',
            'cod'     => 'MSG-24',
        ]);

    } else {
        echo json_encode([
            'error' => 'Acción no reconocida.',
            'cod'   => 'ERR-22',
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log('precios.php error: ' . $e->getMessage());
    echo json_encode([
        'error' => 'Ha ocurrido un error inesperado. La incidencia ha sido registrada. Contacte al administrador.',
        'cod'   => 'ERR-22',
    ]);
}
