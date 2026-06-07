<?php
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/middleware/auth_check.php';
require_once dirname(__DIR__) . '/db.php';

$usuario = requirePatient();
$pdo     = getDB();
$idPac   = (int)$usuario['id'];

try {
    // Datos del paciente
    $paciente = $pdo->prepare(
        "SELECT p.nombre, p.apellido_paterno, p.apellido_materno,
                p.numero_expediente, p.fecha_nacimiento, p.sexo,
                e.grupo_sanguineo, e.alergias
         FROM PACIENTE p
         LEFT JOIN EXPEDIENTE_CLINICO e ON p.id_paciente = e.id_paciente
         WHERE p.id_paciente = ?"
    );
    $paciente->execute([$idPac]);
    $infoPac = $paciente->fetch();

    // Citas futuras (próximas citas)
    $proximas = $pdo->prepare(
        "SELECT c.id_cita, c.fecha_hora, c.motivo_consulta, c.estatus, c.tipo_cita,
                CONCAT(e.nombre,' ',e.apellido_paterno) AS medico,
                e.tipo AS tipo_medico,
                con.area AS consultorio_area, con.numero_consultorio
         FROM CITA c
         JOIN EMPLEADO e ON c.id_medico = e.id_empleado
         LEFT JOIN CONSULTORIO con ON e.id_consultorio = con.id_consultorio
         WHERE c.id_paciente = ?
           AND c.fecha_hora >= NOW()
           AND c.estatus NOT IN ('Cancelada','Completada')
         ORDER BY c.fecha_hora ASC"
    );
    $proximas->execute([$idPac]);

    // Historial de citas pasadas
    $historial = $pdo->prepare(
        "SELECT c.id_cita, c.fecha_hora, c.motivo_consulta, c.estatus, c.tipo_cita,
                CONCAT(em.nombre,' ',em.apellido_paterno) AS medico,
                em.tipo AS tipo_medico,
                con.diagnostico, con.tratamiento
         FROM CITA c
         JOIN EMPLEADO em ON c.id_medico = em.id_empleado
         LEFT JOIN CONSULTA con ON con.id_cita = c.id_cita
         WHERE c.id_paciente = ?
           AND (c.fecha_hora < NOW() OR c.estatus IN ('Completada','Cancelada'))
         ORDER BY c.fecha_hora DESC
         LIMIT 20"
    );
    $historial->execute([$idPac]);

    echo json_encode([
        'paciente'  => $infoPac,
        'proximas'  => $proximas->fetchAll(),
        'historial' => $historial->fetchAll(),
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al cargar los datos. Intenta de nuevo.']);
}
