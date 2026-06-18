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
        if (!$idMod) {
            echo json_encode([
                'error'  => 'Existen campos obligatorios sin completar. Revise el formulario antes de continuar.',
                'cod'    => 'ERR-17',
            ]);
            exit;
        }
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "UPDATE MODIFICACION_PAGO
                 SET estatus = 'Autorizada', fecha_autorizacion = NOW(), id_administrador = ?
                 WHERE id_modificacion = ? AND estatus = 'Pendiente'"
            );
            $stmt->execute([$usuario['id'], $idMod]);
            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                echo json_encode([
                    'error' => 'La solicitud de modificaciÃ³n no existe o ya fue procesada.',
                    'cod'   => 'ERR-08',
                ]);
                exit;
            }
            $stmtPago = $pdo->prepare("SELECT id_pago FROM MODIFICACION_PAGO WHERE id_modificacion = ?");
            $stmtPago->execute([$idMod]);
            $row = $stmtPago->fetch();
            if ($row) {
                $pdo->prepare("UPDATE PAGO SET estatus_pago = 'En revisiÃ³n' WHERE id_pago = ?")
                    ->execute([$row['id_pago']]);
            }
            registrarLog($pdo, $usuario['id'], 'Pagos', 'AutorizaciÃ³n de modificaciÃ³n',
                "ModificaciÃ³n ID $idMod autorizada");
            $pdo->commit();
            echo json_encode([
                'success' => true,
                'mensaje' => 'ModificaciÃ³n de pago autorizada correctamente.',
                'cod'     => 'MSG-20',
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    } elseif ($action === 'rechazar') {
        if (!$idMod) {
            echo json_encode([
                'error' => 'Existen campos obligatorios sin completar. Revise el formulario antes de continuar.',
                'cod'   => 'ERR-17',
            ]);
            exit;
        }
        $motivo = trim($body['motivo'] ?? '');
        if (!$motivo) {
            echo json_encode([
                'error' => 'Debe indicar el motivo del rechazo de la modificaciÃ³n.',
                'cod'   => 'ERR-09',
            ]);
            exit;
        }
        $stmt = $pdo->prepare(
            "UPDATE MODIFICACION_PAGO
             SET estatus = 'Rechazada', fecha_autorizacion = NOW(), id_administrador = ?
             WHERE id_modificacion = ? AND estatus = 'Pendiente'"
        );
        $stmt->execute([$usuario['id'], $idMod]);
        if ($stmt->rowCount() === 0) {
            echo json_encode([
                'error' => 'La solicitud de modificaciÃ³n no existe o ya fue procesada.',
                'cod'   => 'ERR-08',
            ]);
            exit;
        }
        registrarLog($pdo, $usuario['id'], 'Pagos', 'Rechazo de modificaciÃ³n',
            "ModificaciÃ³n ID $idMod rechazada. Motivo: $motivo");
        echo json_encode([
            'success' => true,
            'mensaje' => 'ModificaciÃ³n de pago rechazada.',
            'cod'     => 'MSG-21',
        ]);
    } elseif ($action === 'cancelacion_emergencia') {
        $idPago = (int)($body['id_pago']  ?? 0);
        $clave  = $body['clave_admin']    ?? '';
        $motivo = trim($body['motivo']    ?? '');
        if (!$idPago || !$clave || !$motivo) {
            echo json_encode([
                'error' => 'Existen campos obligatorios sin completar. Revise el formulario antes de continuar.',
                'cod'   => 'ERR-17',
            ]);
            exit;
        }
        $stmtHash = $pdo->prepare("SELECT contrasena_hash FROM EMPLEADO WHERE id_empleado = ?");
        $stmtHash->execute([$usuario['id']]);
        $row = $stmtHash->fetch();
        if (!$row || !password_verify($clave, $row['contrasena_hash'])) {
            registrarLog($pdo, $usuario['id'], 'Pagos', 'Intento fallido cancelaciÃ³n emergencia',
                "Clave incorrecta para pago ID $idPago");
            echo json_encode([
                'error' => 'La clave de administrador es incorrecta. Verifique sus credenciales e intente nuevamente.',
                'cod'   => 'ERR-07',
            ]);
            exit;
        }
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "UPDATE PAGO SET estatus_pago = 'Cancelado' WHERE id_pago = ?"
            )->execute([$idPago]);
            $pdo->prepare(
                "INSERT INTO MODIFICACION_PAGO
                 (id_pago, id_solicitante, id_administrador, motivo, fecha_solicitud, fecha_autorizacion, estatus)
                 VALUES (?, ?, ?, ?, NOW(), NOW(), 'Autorizada')"
            )->execute([$idPago, $usuario['id'], $usuario['id'], 'CANCELACIÃ“N DE EMERGENCIA: ' . $motivo]);
            registrarLog($pdo, $usuario['id'], 'Pagos', 'CancelaciÃ³n de emergencia',
                "Pago ID $idPago cancelado. Motivo: $motivo");
            $pdo->commit();
            echo json_encode([
                'success' => true,
                'mensaje' => 'CancelaciÃ³n de emergencia aplicada correctamente.',
                'cod'     => 'MSG-20',
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    } else {
        echo json_encode([
            'error' => 'AcciÃ³n no reconocida.',
            'cod'   => 'ERR-22',
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log('pagos.php error: ' . $e->getMessage());
    echo json_encode([
        'error' => 'Ha ocurrido un error inesperado. La incidencia ha sido registrada. Contacte al administrador.',
        'cod'   => 'ERR-22',
    ]);
}
