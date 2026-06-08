<?php
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/middleware/auth_check.php';
require_once dirname(__DIR__) . '/db.php';

$usuario = requireAdmin();
$pdo     = getDB();

try {
    $notifs = [];

    // Precios pendientes de validación
    $precios = (int)$pdo->query(
        "SELECT COUNT(*) FROM LOTE_MEDICAMENTO WHERE estatus_precio = 'Pendiente'"
    )->fetchColumn();
    if ($precios > 0) {
        $notifs[] = [
            'tipo'    => 'precio',
            'icono'   => 'fa-tag',
            'color'   => 'warning',
            'titulo'  => 'Precios pendientes de validación',
            'mensaje' => "$precios lote(s) esperan tu aprobación de precio.",
            'seccion' => 'precios',
        ];
    }

    // Modificaciones de pago pendientes
    $mods = (int)$pdo->query(
        "SELECT COUNT(*) FROM MODIFICACION_PAGO WHERE estatus = 'Pendiente'"
    )->fetchColumn();
    if ($mods > 0) {
        $notifs[] = [
            'tipo'    => 'pago',
            'icono'   => 'fa-money-bill-wave',
            'color'   => 'danger',
            'titulo'  => 'Modificaciones de pago pendientes',
            'mensaje' => "$mods solicitud(es) de modificación de pago requieren autorización.",
            'seccion' => 'pagos',
        ];
    }

    // Stock crítico
    $stock = (int)$pdo->query(
        "SELECT COUNT(*) FROM INSUMO WHERE stock_actual <= stock_minimo"
    )->fetchColumn();
    if ($stock > 0) {
        $notifs[] = [
            'tipo'    => 'stock',
            'icono'   => 'fa-boxes',
            'color'   => 'warning',
            'titulo'  => 'Stock crítico de insumos',
            'mensaje' => "$stock insumo(s) están por debajo del stock mínimo.",
            'seccion' => 'reportes',
        ];
    }

    // Medicamentos próximos a caducar (RN-17: alerta a ≤ 30 días)
    $caduca = (int)$pdo->query(
        "SELECT COUNT(*) FROM LOTE_MEDICAMENTO
         WHERE fecha_caducidad <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
           AND existencia_actual > 0"
    )->fetchColumn();
    if ($caduca > 0) {
        $notifs[] = [
            'tipo'    => 'caducidad',
            'icono'   => 'fa-exclamation-triangle',
            'color'   => 'danger',
            'titulo'  => 'Medicamentos próximos a caducar',
            'mensaje' => "$caduca lote(s) caducan en menos de 30 días.",
            'seccion' => 'reportes',
        ];
    }

    // Mantenimiento próximo (< 48 horas)
    $mant = $pdo->query(
        "SELECT id_mantenimiento, fecha_hora_inicio, duracion_minutos
         FROM MANTENIMIENTO
         WHERE estatus = 'Programado'
           AND fecha_hora_inicio BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 48 HOUR)
         ORDER BY fecha_hora_inicio LIMIT 1"
    )->fetch();
    if ($mant) {
        $notifs[] = [
            'tipo'    => 'mantenimiento',
            'icono'   => 'fa-tools',
            'color'   => 'info',
            'titulo'  => 'Mantenimiento programado próximo',
            'mensaje' => 'Mantenimiento en ' . $mant['fecha_hora_inicio'] . ' (' . $mant['duracion_minutos'] . ' min).',
            'seccion' => 'mantenimiento',
        ];
    }

    // Empleados próximos a baja definitiva (período de gracia termina < 3 días)
    $bajasProximas = $pdo->query(
        "SELECT COUNT(*) FROM EMPLEADO
         WHERE estatus = 'Por dar de baja'
           AND fecha_baja_programada <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)"
    )->fetchColumn();
    if ($bajasProximas > 0) {
        $notifs[] = [
            'tipo'    => 'baja',
            'icono'   => 'fa-user-minus',
            'color'   => 'secondary',
            'titulo'  => 'Bajas próximas a procesarse',
            'mensaje' => "$bajasProximas empleado(s) completarán su período de gracia en los próximos 3 días.",
            'seccion' => 'empleados',
        ];
    }

    echo json_encode([
        'total'         => count($notifs),
        'notificaciones'=> $notifs,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al cargar notificaciones']);
}
