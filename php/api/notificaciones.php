<?php
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/middleware/auth_check.php';
require_once dirname(__DIR__) . '/db.php';
$usuario = requireAdmin();
$pdo     = getDB();
try {
    $notifs = [];
    $precios = (int)$pdo->query(
        "SELECT COUNT(*) FROM LOTE_MEDICAMENTO WHERE estatus_precio = 'Pendiente'"
    )->fetchColumn();
    if ($precios > 0) {
        $notifs[] = [
            'tipo'    => 'precio',
            'icono'   => 'fa-tag',
            'color'   => 'warning',
            'titulo'  => 'Precios pendientes de validaciÃ³n',
            'mensaje' => "$precios lote(s) esperan tu aprobaciÃ³n de precio.",
            'seccion' => 'precios',
        ];
    }
    $mods = (int)$pdo->query(
        "SELECT COUNT(*) FROM MODIFICACION_PAGO WHERE estatus = 'Pendiente'"
    )->fetchColumn();
    if ($mods > 0) {
        $notifs[] = [
            'tipo'    => 'pago',
            'icono'   => 'fa-money-bill-wave',
            'color'   => 'danger',
            'titulo'  => 'Modificaciones de pago pendientes',
            'mensaje' => "$mods solicitud(es) de modificaciÃ³n de pago requieren autorizaciÃ³n.",
            'seccion' => 'pagos',
        ];
    }
    $stock = (int)$pdo->query(
        "SELECT COUNT(*) FROM INSUMO WHERE stock_actual <= stock_minimo"
    )->fetchColumn();
    if ($stock > 0) {
        $notifs[] = [
            'tipo'    => 'stock',
            'icono'   => 'fa-boxes',
            'color'   => 'warning',
            'titulo'  => 'Stock crÃ­tico de insumos',
            'mensaje' => "$stock insumo(s) estÃ¡n por debajo del stock mÃ­nimo.",
            'seccion' => 'reportes',
        ];
    }
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
            'titulo'  => 'Medicamentos prÃ³ximos a caducar',
            'mensaje' => "$caduca lote(s) caducan en menos de 30 dÃ­as.",
            'seccion' => 'reportes',
        ];
    }
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
            'titulo'  => 'Mantenimiento programado prÃ³ximo',
            'mensaje' => 'Mantenimiento en ' . $mant['fecha_hora_inicio'] . ' (' . $mant['duracion_minutos'] . ' min).',
            'seccion' => 'mantenimiento',
        ];
    }
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
            'titulo'  => 'Bajas prÃ³ximas a procesarse',
            'mensaje' => "$bajasProximas empleado(s) completarÃ¡n su perÃ­odo de gracia en los prÃ³ximos 3 dÃ­as.",
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
