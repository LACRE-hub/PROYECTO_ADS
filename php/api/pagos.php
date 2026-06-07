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
        $tipo = $_GET['tipo'] ?? 'pendientes';

        if ($tipo === 'pendientes') {
            $rows = $pdo->query(
                "SELECT mp.id_modificacion, mp.motivo, mp.fecha_solicitud, mp.estatus,
                        p.folio_pago, p.monto_total, p.metodo_pago, p.fecha_hora AS fecha_pago,
                        p.estatus_pago,
                        CONCAT(pac.nombre,' ',pac.apellido_paterno) AS paciente,
                        CONCAT(sol.nombre,' ',sol.apellido_paterno) AS solicitante
                 FROM MODIFICACION_PAGO mp
                 JOIN PAGO p ON mp.id_pago = p.id_pago
                 JOIN PACIENTE pac ON p.id_paciente = pac.id_paciente
                 JOIN EMPLEADO sol ON mp.id_solicitante = sol.id_empleado
                 WHERE mp.estatus = 'Pendiente'
                 ORDER BY mp.fecha_solicitud DESC"
            )->fetchAll();
        } else {
            $rows = $pdo->query(
                "SELECT mp.id_modificacion, mp.motivo, mp.fecha_solicitud,
                        mp.fecha_autorizacion, mp.estatus,
                        p.folio_pago, p.monto_total,
                        CONCAT(pac.nombre,' ',pac.apellido_paterno) AS paciente,
                        CONCAT(sol.nombre,' ',sol.apellido_paterno) AS solicitante
                 FROM MODIFICACION_PAGO mp
                 JOIN PAGO p ON mp.id_pago = p.id_pago
                 JOIN PACIENTE pac ON p.id_paciente = pac.id_paciente
                 JOIN EMPLEADO sol ON mp.id_solicitante = sol.id_empleado
                 ORDER BY mp.fecha_solicitud DESC LIMIT 50"
            )->fetchAll();
        }
        echo json_encode($rows);
        exit;
    }

    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';
    $idMod  = (int)($body['id_modificacion'] ?? 0);

    if ($action === 'aprobar') {
        // RF-10: Administrador autoriza modificación
        if (!$idMod) { echo json_encode(['error' => 'ID requerido']); exit; }

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "UPDATE MODIFICACION_PAGO
                 SET estatus = 'Autorizada', fecha_autorizacion = NOW(), id_administrador = ?
                 WHERE id_modificacion = ? AND estatus = 'Pendiente'"
            )->execute([$usuario['id'], $idMod]);

            // Obtener id_pago para actualizar estado
            $idPago = $pdo->prepare("SELECT id_pago FROM MODIFICACION_PAGO WHERE id_modificacion = ?")->execute([$idMod]);
            $row = $pdo->query("SELECT id_pago FROM MODIFICACION_PAGO WHERE id_modificacion = $idMod")->fetch();
            if ($row) {
                $pdo->prepare("UPDATE PAGO SET estatus_pago = 'En revisión' WHERE id_pago = ?")
                    ->execute([$row['id_pago']]);
            }

            registrarLog($pdo, $usuario['id'], 'Pagos', 'Autorización de modificación',
                "Modificación ID $idMod autorizada");

            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

    } elseif ($action === 'rechazar') {
        $motivo = trim($body['motivo'] ?? '');
        $pdo->prepare(
            "UPDATE MODIFICACION_PAGO
             SET estatus = 'Rechazada', fecha_autorizacion = NOW(), id_administrador = ?
             WHERE id_modificacion = ? AND estatus = 'Pendiente'"
        )->execute([$usuario['id'], $idMod]);

        registrarLog($pdo, $usuario['id'], 'Pagos', 'Rechazo de modificación',
            "Modificación ID $idMod rechazada. Motivo: $motivo");

        echo json_encode(['success' => true]);

    } elseif ($action === 'cancelacion_emergencia') {
        // RF-11: cancelación de emergencia requiere clave del administrador
        $idPago  = (int)($body['id_pago']  ?? 0);
        $clave   = $body['clave_admin']     ?? '';
        $motivo  = trim($body['motivo']     ?? '');

        if (!$idPago || !$clave || !$motivo) {
            echo json_encode(['error' => 'ID de pago, clave y motivo son requeridos']); exit;
        }

        // Verificar clave del administrador
        $hash = $pdo->prepare("SELECT contrasena_hash FROM EMPLEADO WHERE id_empleado = ?")->execute([$usuario['id']]);
        $row  = $pdo->query("SELECT contrasena_hash FROM EMPLEADO WHERE id_empleado = " . $usuario['id'])->fetch();

        if (!$row || !password_verify($clave, $row['contrasena_hash'])) {
            registrarLog($pdo, $usuario['id'], 'Pagos', 'Intento fallido cancelación emergencia',
                "Clave incorrecta para pago ID $idPago");
            echo json_encode(['error' => 'Clave de administrador incorrecta']); exit;
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "UPDATE PAGO SET estatus_pago = 'Cancelado' WHERE id_pago = ?"
            )->execute([$idPago]);

            // Crear registro de modificación
            $pdo->prepare(
                "INSERT INTO MODIFICACION_PAGO
                 (id_pago, id_solicitante, id_administrador, motivo, fecha_solicitud, fecha_autorizacion, estatus)
                 VALUES (?, ?, ?, ?, NOW(), NOW(), 'Autorizada')"
            )->execute([$idPago, $usuario['id'], $usuario['id'], 'CANCELACIÓN DE EMERGENCIA: ' . $motivo]);

            registrarLog($pdo, $usuario['id'], 'Pagos', 'Cancelación de emergencia',
                "Pago ID $idPago cancelado. Motivo: $motivo");

            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    } else {
        echo json_encode(['error' => 'Acción no reconocida']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de base de datos']);
}
