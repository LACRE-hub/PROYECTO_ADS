<?php
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/middleware/auth_check.php';
require_once dirname(__DIR__) . '/db.php';
$usuario = requireAdmin();
$pdo = getDB();
try {
    $kpis = [];
    $kpis['total_empleados'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM EMPLEADO WHERE estatus = 'Activo'"
    )->fetchColumn();
    $kpis['total_pacientes'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM PACIENTE"
    )->fetchColumn();
    $kpis['citas_hoy'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM CITA WHERE DATE(fecha_hora) = CURDATE()"
    )->fetchColumn();
    $kpis['pagos_pendientes'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM PAGO WHERE estatus_pago = 'Pendiente'"
    )->fetchColumn();
    $kpis['precios_pendientes'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM LOTE_MEDICAMENTO WHERE estatus_precio = 'Pendiente'"
    )->fetchColumn();
    $kpis['modificaciones_pendientes'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM MODIFICACION_PAGO WHERE estatus = 'Pendiente'"
    )->fetchColumn();
    $kpis['mantenimiento_proximo'] = $pdo->query(
        "SELECT fecha_hora_inicio, duracion_minutos FROM MANTENIMIENTO
         WHERE estatus = 'Programado' AND fecha_hora_inicio > NOW()
         ORDER BY fecha_hora_inicio LIMIT 1"
    )->fetch() ?: null;
    $kpis['alertas_stock'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM ALERTA_STOCK WHERE estatus = 'Activa'"
    )->fetchColumn();
    $ingresos = $pdo->query(
        "SELECT DATE_FORMAT(fecha_hora, '%Y-%m') AS mes,
                SUM(monto_total) AS total
         FROM PAGO
         WHERE estatus_pago = 'Pagado'
           AND fecha_hora >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
         GROUP BY mes
         ORDER BY mes"
    )->fetchAll();
    $citas_estado = $pdo->query(
        "SELECT estatus, COUNT(*) AS total FROM CITA GROUP BY estatus"
    )->fetchAll();
    $top_meds = $pdo->query(
        "SELECT m.nombre_sustancia, SUM(d.cantidad) AS total_dispensado
         FROM DESPACHO_FARMACIA d
         JOIN LOTE_MEDICAMENTO l ON d.id_lote = l.id_lote
         JOIN MEDICAMENTO m ON l.id_medicamento = m.id_medicamento
         GROUP BY m.id_medicamento, m.nombre_sustancia
         ORDER BY total_dispensado DESC
         LIMIT 5"
    )->fetchAll();
    $stock_critico = $pdo->query(
        "SELECT nombre_articulo, stock_actual, stock_minimo
         FROM INSUMO
         WHERE stock_actual <= stock_minimo
         ORDER BY (stock_actual / GREATEST(stock_minimo,1)) ASC
         LIMIT 6"
    )->fetchAll();
    $consultas_tipo = $pdo->query(
        "SELECT e.tipo,
                COUNT(*) AS total,
                CASE WHEN e.tipo = 'MÃ©dico General' THEN 'General' ELSE 'Especialista' END AS categoria
         FROM CONSULTA c
         JOIN EMPLEADO e ON c.id_medico = e.id_empleado
         WHERE c.fecha_hora >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY e.tipo
         ORDER BY categoria, total DESC"
    )->fetchAll();
    $consultas_resumen = $pdo->query(
        "SELECT
           SUM(CASE WHEN e.tipo = 'MÃ©dico General' THEN 1 ELSE 0 END) AS general,
           SUM(CASE WHEN e.tipo != 'MÃ©dico General' THEN 1 ELSE 0 END) AS especialista
         FROM CONSULTA c
         JOIN EMPLEADO e ON c.id_medico = e.id_empleado
         WHERE c.fecha_hora >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    )->fetch();
    $adminRow = $pdo->prepare("SELECT nombre, apellido_paterno FROM EMPLEADO WHERE id_empleado = ?");
    $adminRow->execute([$usuario['id']]);
    $adm = $adminRow->fetch();
    $adminNombre = $adm ? $adm['nombre'] . ' ' . $adm['apellido_paterno'] : 'Administrador';
    echo json_encode([
        'kpis'           => $kpis,
        'ingresos'       => $ingresos,
        'citas_estado'   => $citas_estado,
        'top_meds'       => $top_meds,
        'stock_critico'  => $stock_critico,
        'consultas_tipo'    => $consultas_tipo,
        'consultas_resumen' => $consultas_resumen,
        'admin_nombre'   => $adminNombre,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al cargar el dashboard']);
}
