<?php
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/middleware/auth_check.php';
require_once dirname(__DIR__) . '/db.php';

$usuario = requireAdmin();
$pdo     = getDB();
$method  = $_SERVER['REQUEST_METHOD'];

try {

    /* ══════════════════════════════════════════════════════════
       GET
    ══════════════════════════════════════════════════════════ */
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'list';

        /* ── LISTAR ────────────────────────────────────────── */
        if ($action === 'list') {
            $where  = ['1=1'];
            $params = [];

            // Filtro por id individual (editar)
            if (!empty($_GET['id'])) {
                $where[]  = 'e.id_empleado = ?';
                $params[] = (int)$_GET['id'];
            }

            // Búsqueda por nombre completo concatenado O número de empleado
            if (!empty($_GET['nombre'])) {
                $like     = '%' . trim($_GET['nombre']) . '%';
                $where[]  = "(CONCAT(e.nombre,' ',e.apellido_paterno,' ',IFNULL(e.apellido_materno,'')) LIKE ?
                              OR e.numero_empleado LIKE ?)";
                $params[] = $like;
                $params[] = $like;
            }

            // Filtro por rol
            if (!empty($_GET['rol'])) {
                $where[]  = 'e.id_rol = ?';
                $params[] = (int)$_GET['rol'];
            }

            // Filtro por estatus
            if (!empty($_GET['estatus'])) {
                $where[]  = 'e.estatus = ?';
                $params[] = trim($_GET['estatus']);
            }

            $whereSQL = implode(' AND ', $where);

            $stmt = $pdo->prepare(
                "SELECT e.id_empleado, e.numero_empleado, e.nombre,
                        e.apellido_paterno, e.apellido_materno,
                        e.tipo, e.turno, e.area_asignada,
                        e.estatus, e.fecha_baja_programada,
                        e.id_rol, r.nombre_rol,
                        e.id_consultorio,
                        con.area          AS consultorio_area,
                        con.numero_consultorio
                 FROM EMPLEADO e
                 JOIN ROL r ON e.id_rol = r.id_rol
                 LEFT JOIN CONSULTORIO con ON e.id_consultorio = con.id_consultorio
                 WHERE $whereSQL
                 ORDER BY FIELD(e.estatus,'Activo','Por dar de baja','Baja'), e.apellido_paterno"
            );
            $stmt->execute($params);
            echo json_encode(['empleados' => $stmt->fetchAll()]);
            exit;
        }

        /* ── ROLES ─────────────────────────────────────────── */
        if ($action === 'roles') {
            $rows = $pdo->query("SELECT id_rol, nombre_rol FROM ROL ORDER BY id_rol")->fetchAll();
            echo json_encode(['roles' => $rows]);
            exit;
        }

        /* ── CONSULTORIOS ──────────────────────────────────── */
        if ($action === 'consultorios') {
            $rows = $pdo->query(
                "SELECT id_consultorio, area, numero_consultorio
                 FROM CONSULTORIO ORDER BY area, numero_consultorio"
            )->fetchAll();
            echo json_encode(['consultorios' => $rows]);
            exit;
        }

        /* ── PACIENTES / CITAS ACTIVAS del empleado ────────── */
        if ($action === 'pacientes_activos') {
            $id = (int)($_GET['id'] ?? 0);

            $stmtCitas = $pdo->prepare(
                "SELECT c.id_cita, c.fecha_hora, c.tipo_cita AS tipo,
                        CONCAT(p.nombre,' ',p.apellido_paterno) AS paciente_nombre
                 FROM CITA c
                 JOIN PACIENTE p ON c.id_paciente = p.id_paciente
                 WHERE c.id_medico = ?
                   AND c.estatus NOT IN ('Cancelada','Completada')
                   AND c.fecha_hora >= NOW()
                 ORDER BY c.fecha_hora"
            );
            $stmtCitas->execute([$id]);

            $stmtHosp = $pdo->prepare(
                "SELECT h.id_hospitalizacion, h.fecha_ingreso,
                        CONCAT(p.nombre,' ',p.apellido_paterno) AS paciente_nombre,
                        'Hospitalización' AS tipo
                 FROM HOSPITALIZACION h
                 JOIN PACIENTE p ON h.id_paciente = p.id_paciente
                 WHERE h.id_medico = ? AND h.fecha_egreso IS NULL"
            );
            $stmtHosp->execute([$id]);

            echo json_encode([
                'citas'             => $stmtCitas->fetchAll(),
                'hospitalizaciones' => $stmtHosp->fetchAll(),
            ]);
            exit;
        }

        /* ── EMPLEADOS ACTIVOS para reasignar ──────────────── */
        if ($action === 'medicos_activos') {
            $excluir = (int)($_GET['id'] ?? 0);
            $stmt = $pdo->prepare(
                "SELECT e.id_empleado,
                        CONCAT(e.nombre,' ',e.apellido_paterno) AS nombre,
                        e.tipo, r.nombre_rol
                 FROM EMPLEADO e
                 JOIN ROL r ON e.id_rol = r.id_rol
                 WHERE e.estatus = 'Activo' AND e.id_empleado != ?
                 ORDER BY e.apellido_paterno"
            );
            $stmt->execute([$excluir]);
            echo json_encode(['medicos' => $stmt->fetchAll()]);
            exit;
        }

        echo json_encode(['error' => 'Acción GET no reconocida']);
        exit;
    }

    /* ══════════════════════════════════════════════════════════
       POST
    ══════════════════════════════════════════════════════════ */
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';

    /* ── CREAR ─────────────────────────────────────────────── */
    if ($action === 'crear') {
        $num    = trim($body['numero_empleado']  ?? '');
        $nombre = trim($body['nombre']           ?? '');
        $apPat  = trim($body['apellido_paterno'] ?? '');
        $apMat  = trim($body['apellido_materno'] ?? '');
        $tipo   = trim($body['tipo']             ?? '');
        $turno  = trim($body['turno']            ?? 'Matutino');
        $area   = trim($body['area_asignada']    ?? '');
        $idRol  = (int)($body['id_rol']          ?? 0);
        $idCon  = !empty($body['id_consultorio']) ? (int)$body['id_consultorio'] : null;
        $pw     = trim($body['password']         ?? '');

        // Validaciones
        if (!preg_match('/^\d{10}$/', $num))
            { echo json_encode(['error' => 'Número de empleado: exactamente 10 dígitos']); exit; }
        if (!$nombre || !$apPat || !$idRol)
            { echo json_encode(['error' => 'Nombre, apellido y rol son obligatorios']); exit; }
        if (!$pw || strlen($pw) < 8)
            { echo json_encode(['error' => 'La contraseña debe tener al menos 8 caracteres']); exit; }

        // Número único
        $stmtDup = $pdo->prepare("SELECT COUNT(*) FROM EMPLEADO WHERE numero_empleado = ?");
        $stmtDup->execute([$num]);
        if ((int)$stmtDup->fetchColumn() > 0)
            { echo json_encode(['error' => "El número de empleado $num ya existe"]); exit; }

        // Máximo 2 administradores (RN-07)
        if ($idRol === 1) {
            $stmtAdm = $pdo->query("SELECT COUNT(*) FROM EMPLEADO WHERE id_rol=1 AND estatus='Activo'");
            if ((int)$stmtAdm->fetchColumn() >= 2)
                { echo json_encode(['error' => 'No pueden existir más de 2 administradores activos (RN-07)']); exit; }
        }

        $hash = password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $pdo->prepare(
            "INSERT INTO EMPLEADO
             (numero_empleado, nombre, apellido_paterno, apellido_materno,
              tipo, turno, area_asignada, estatus, contrasena_hash, id_rol, id_consultorio)
             VALUES (?,?,?,?,?,?,?,'Activo',?,?,?)"
        );
        $stmt->execute([
            $num, $nombre, $apPat,
            $apMat ?: null,
            $tipo  ?: null,
            $turno,
            $area  ?: null,
            $hash, $idRol, $idCon
        ]);
        $newId = (int)$pdo->lastInsertId();

        registrarLog($pdo, $usuario['id'], 'Empleados', 'Alta empleado',
            "Alta $num – $nombre $apPat, rol $idRol");

        echo json_encode(['success' => true, 'id' => $newId]);
        exit;
    }

    /* ── ACTUALIZAR ────────────────────────────────────────── */
    if ($action === 'actualizar') {
        $id     = (int)($body['id_empleado']      ?? 0);
        $nombre = trim($body['nombre']            ?? '');
        $apPat  = trim($body['apellido_paterno']  ?? '');
        $apMat  = trim($body['apellido_materno']  ?? '');
        $tipo   = trim($body['tipo']              ?? '');
        $turno  = trim($body['turno']             ?? '');
        $area   = trim($body['area_asignada']     ?? '');
        $idRol  = (int)($body['id_rol']           ?? 0);
        $idCon  = !empty($body['id_consultorio']) ? (int)$body['id_consultorio'] : null;

        if (!$id || !$nombre || !$apPat || !$idRol)
            { echo json_encode(['error' => 'Datos incompletos']); exit; }

        // Verificar máx 2 admins si cambia a rol 1
        if ($idRol === 1) {
            $stmtA = $pdo->prepare("SELECT id_rol FROM EMPLEADO WHERE id_empleado=?");
            $stmtA->execute([$id]);
            $rolActual = (int)($stmtA->fetchColumn() ?: 0);
            if ($rolActual !== 1) {
                $stmtC = $pdo->query("SELECT COUNT(*) FROM EMPLEADO WHERE id_rol=1 AND estatus='Activo'");
                if ((int)$stmtC->fetchColumn() >= 2)
                    { echo json_encode(['error' => 'Ya hay 2 administradores activos (RN-07)']); exit; }
            }
        }

        $stmt = $pdo->prepare(
            "UPDATE EMPLEADO
             SET nombre=?, apellido_paterno=?, apellido_materno=?,
                 tipo=?, turno=?, area_asignada=?, id_rol=?, id_consultorio=?
             WHERE id_empleado=?"
        );
        $stmt->execute([
            $nombre, $apPat, $apMat ?: null,
            $tipo ?: null, $turno ?: null, $area ?: null,
            $idRol, $idCon, $id
        ]);

        // Cambio de contraseña opcional
        if (!empty($body['password'])) {
            $pw = trim($body['password']);
            if (strlen($pw) < 8)
                { echo json_encode(['error' => 'Contraseña: mínimo 8 caracteres']); exit; }
            $hash = password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("UPDATE EMPLEADO SET contrasena_hash=? WHERE id_empleado=?")
                ->execute([$hash, $id]);
        }

        registrarLog($pdo, $usuario['id'], 'Empleados', 'Actualización empleado',
            "Actualización ID $id – $nombre $apPat");
        echo json_encode(['success' => true]);
        exit;
    }

    /* ── INICIAR BAJA (30 días gracia) ─────────────────────── */
    if ($action === 'iniciar_baja') {
        $id     = (int)($body['id_empleado'] ?? 0);
        $motivo = trim($body['motivo']       ?? '');

        if (!$id)     { echo json_encode(['error' => 'ID inválido']); exit; }
        if (!$motivo) { echo json_encode(['error' => 'El motivo de baja es requerido']); exit; }
        if ($id === (int)$usuario['id'])
            { echo json_encode(['error' => 'No puedes darte de baja a ti mismo']); exit; }

        $fechaBaja = date('Y-m-d', strtotime('+30 days'));
        $pdo->prepare(
            "UPDATE EMPLEADO
             SET estatus='Por dar de baja', fecha_baja_programada=?, motivo_baja=?
             WHERE id_empleado=?"
        )->execute([$fechaBaja, $motivo, $id]);

        registrarLog($pdo, $usuario['id'], 'Empleados', 'Inicio baja programada',
            "Baja programada ID $id, efectiva $fechaBaja. Motivo: $motivo");

        echo json_encode(['success' => true, 'fecha_baja' => $fechaBaja]);
        exit;
    }

    /* ── REASIGNAR pacientes ────────────────────────────────── */
    if ($action === 'reasignar') {
        $idSaliente = (int)($body['id_empleado'] ?? 0);
        $idReceptor = (int)($body['id_receptor'] ?? 0);

        if (!$idSaliente || !$idReceptor)
            { echo json_encode(['error' => 'IDs inválidos']); exit; }

        $pdo->beginTransaction();

        // Pacientes con citas futuras activas
        $stmtPac = $pdo->prepare(
            "SELECT DISTINCT id_paciente FROM CITA
             WHERE id_medico=?
               AND estatus NOT IN ('Cancelada','Completada')
               AND fecha_hora >= NOW()"
        );
        $stmtPac->execute([$idSaliente]);
        $listaPac = $stmtPac->fetchAll(PDO::FETCH_COLUMN);

        foreach ($listaPac as $idPac) {
            $pdo->prepare(
                "INSERT INTO REASIGNACION_PACIENTE
                 (id_empleado_saliente, id_empleado_receptor, id_paciente, fecha_reasignacion, id_administrador)
                 VALUES (?,?,?,NOW(),?)"
            )->execute([$idSaliente, $idReceptor, $idPac, $usuario['id']]);

            $pdo->prepare(
                "UPDATE CITA SET id_medico=?
                 WHERE id_medico=? AND id_paciente=?
                   AND estatus NOT IN ('Cancelada','Completada')
                   AND fecha_hora >= NOW()"
            )->execute([$idReceptor, $idSaliente, $idPac]);
        }

        // Hospitalizaciones activas
        $pdo->prepare(
            "UPDATE HOSPITALIZACION SET id_medico=? WHERE id_medico=? AND fecha_egreso IS NULL"
        )->execute([$idReceptor, $idSaliente]);

        $pdo->commit();

        registrarLog($pdo, $usuario['id'], 'Empleados', 'Reasignación',
            "De $idSaliente → $idReceptor. " . count($listaPac) . " pacientes");

        echo json_encode(['success' => true, 'reasignados' => count($listaPac)]);
        exit;
    }

    /* ── BAJA DEFINITIVA ───────────────────────────────────── */
    if ($action === 'baja_definitiva') {
        $id = (int)($body['id_empleado'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'ID inválido']); exit; }

        $stmtPend = $pdo->prepare(
            "SELECT COUNT(*) FROM CITA
             WHERE id_medico=?
               AND estatus NOT IN ('Cancelada','Completada')
               AND fecha_hora >= NOW()"
        );
        $stmtPend->execute([$id]);
        if ((int)$stmtPend->fetchColumn() > 0)
            { echo json_encode(['error' => 'Aún tiene citas pendientes. Reasigna primero (RN-06)']); exit; }

        $pdo->prepare(
            "UPDATE EMPLEADO SET estatus='Baja', fecha_separacion=CURDATE() WHERE id_empleado=?"
        )->execute([$id]);

        registrarLog($pdo, $usuario['id'], 'Empleados', 'Baja definitiva',
            "Baja definitiva ID $id");

        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['error' => 'Acción no reconocida: ' . htmlspecialchars($action)]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error BD: ' . $e->getMessage()]);
}
