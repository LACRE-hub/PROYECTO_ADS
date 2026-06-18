<?php
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/middleware/auth_check.php';
require_once dirname(__DIR__) . '/db.php';

$usuario = requireAdmin();
$pdo     = getDB();
$tipo    = $_GET['tipo'] ?? 'financiero';
$mes     = $_GET['mes']  ?? date('Y-m');

[$anio, $mesNum] = explode('-', $mes . '-01');

try {
    switch ($tipo) {

        case 'financiero':
            // RF-31: ingreso mensual + desglose
            $ingreso = $pdo->prepare(
                "SELECT SUM(monto_total) AS total_mes,
                        COUNT(*) AS num_pagos,
                        SUM(CASE WHEN metodo_pago='Efectivo'      THEN monto_total ELSE 0 END) AS efectivo,
                        SUM(CASE WHEN metodo_pago='Tarjeta'       THEN monto_total ELSE 0 END) AS tarjeta,
                        SUM(CASE WHEN metodo_pago='Transferencia' THEN monto_total ELSE 0 END) AS transferencia,
                        SUM(CASE WHEN metodo_pago='Seguro IMSS'   THEN monto_total ELSE 0 END) AS seguro
                 FROM PAGO
                 WHERE estatus_pago = 'Pagado'
                   AND YEAR(fecha_hora) = ? AND MONTH(fecha_hora) = ?"
            );
            $ingreso->execute([$anio, $mesNum]);

            $desglose = $pdo->prepare(
                "SELECT pd.tipo_concepto, SUM(pd.monto) AS total
                 FROM PAGO_DETALLE pd
                 JOIN PAGO p ON pd.id_pago = p.id_pago
                 WHERE p.estatus_pago = 'Pagado'
                   AND YEAR(p.fecha_hora) = ? AND MONTH(p.fecha_hora) = ?
                 GROUP BY pd.tipo_concepto
                 ORDER BY total DESC"
            );
            $desglose->execute([$anio, $mesNum]);

            $historial = $pdo->query(
                "SELECT DATE_FORMAT(fecha_hora,'%Y-%m') AS mes, SUM(monto_total) AS total
                 FROM PAGO WHERE estatus_pago='Pagado'
                 GROUP BY mes ORDER BY mes DESC LIMIT 12"
            )->fetchAll();

            echo json_encode([
                'resumen'  => $ingreso->fetch(),
                'desglose' => $desglose->fetchAll(),
                'historial'=> array_reverse($historial),
            ]);
            break;

        case 'clinico':
            // RF-32: consultas por médico y por especialidad
            $por_medico = $pdo->prepare(
                "SELECT CONCAT(e.nombre,' ',e.apellido_paterno) AS medico,
                        e.tipo, COUNT(c.id_consulta) AS total_consultas
                 FROM CONSULTA c
                 JOIN EMPLEADO e ON c.id_medico = e.id_empleado
                 WHERE YEAR(c.fecha_hora) = ? AND MONTH(c.fecha_hora) = ?
                 GROUP BY c.id_medico, medico, e.tipo
                 ORDER BY total_consultas DESC"
            );
            $por_medico->execute([$anio, $mesNum]);

            $por_tipo = $pdo->prepare(
                "SELECT e.tipo, COUNT(c.id_consulta) AS total
                 FROM CONSULTA c JOIN EMPLEADO e ON c.id_medico = e.id_empleado
                 WHERE YEAR(c.fecha_hora) = ? AND MONTH(c.fecha_hora) = ?
                 GROUP BY e.tipo"
            );
            $por_tipo->execute([$anio, $mesNum]);

            $diagnosticos = $pdo->prepare(
                "SELECT diagnostico, COUNT(*) AS frecuencia
                 FROM CONSULTA
                 WHERE YEAR(fecha_hora)=? AND MONTH(fecha_hora)=?
                   AND diagnostico IS NOT NULL
                 GROUP BY diagnostico ORDER BY frecuencia DESC LIMIT 10"
            );
            $diagnosticos->execute([$anio, $mesNum]);

            echo json_encode([
                'por_medico'   => $por_medico->fetchAll(),
                'por_tipo'     => $por_tipo->fetchAll(),
                'diagnosticos' => $diagnosticos->fetchAll(),
            ]);
            break;

        case 'laboratorio':
            // RF-33: estudios realizados por tipo y cantidad
            $estudios = $pdo->prepare(
                "SELECT tipo_estudio, nombre_estudio, COUNT(*) AS total,
                        SUM(CASE WHEN estatus='Resultado disponible' THEN 1 ELSE 0 END) AS completados,
                        SUM(CASE WHEN estatus='Pendiente'            THEN 1 ELSE 0 END) AS pendientes
                 FROM ORDEN_ESTUDIO
                 WHERE YEAR(fecha_solicitud)=? AND MONTH(fecha_solicitud)=?
                 GROUP BY tipo_estudio, nombre_estudio
                 ORDER BY total DESC"
            );
            $estudios->execute([$anio, $mesNum]);

            $por_tipo = $pdo->prepare(
                "SELECT tipo_estudio, COUNT(*) AS total
                 FROM ORDEN_ESTUDIO
                 WHERE YEAR(fecha_solicitud)=? AND MONTH(fecha_solicitud)=?
                 GROUP BY tipo_estudio"
            );
            $por_tipo->execute([$anio, $mesNum]);

            echo json_encode([
                'estudios' => $estudios->fetchAll(),
                'por_tipo' => $por_tipo->fetchAll(),
            ]);
            break;

        case 'citas':
            // RF-34: citas programadas, canceladas, reagendadas, por especialidad
            $resumen = $pdo->prepare(
                "SELECT estatus, COUNT(*) AS total FROM CITA
                 WHERE YEAR(fecha_hora)=? AND MONTH(fecha_hora)=?
                 GROUP BY estatus"
            );
            $resumen->execute([$anio, $mesNum]);

            $por_medico = $pdo->prepare(
                "SELECT CONCAT(e.nombre,' ',e.apellido_paterno) AS medico,
                        e.tipo, COUNT(*) AS total
                 FROM CITA c JOIN EMPLEADO e ON c.id_medico = e.id_empleado
                 WHERE YEAR(c.fecha_hora)=? AND MONTH(c.fecha_hora)=?
                 GROUP BY c.id_medico, medico, e.tipo
                 ORDER BY total DESC"
            );
            $por_medico->execute([$anio, $mesNum]);

            echo json_encode([
                'resumen'   => $resumen->fetchAll(),
                'por_medico'=> $por_medico->fetchAll(),
            ]);
            break;

        case 'farmacia':
            // RF-35: medicamentos disponibles, agotados, por caducar, más utilizados
            $disponibles = $pdo->query(
                "SELECT m.nombre_sustancia, m.tipo_medicamento,
                        SUM(l.existencia_actual) AS stock_total,
                        MIN(l.fecha_caducidad)   AS proxima_caducidad
                 FROM MEDICAMENTO m
                 JOIN LOTE_MEDICAMENTO l ON m.id_medicamento = l.id_medicamento
                 WHERE l.estatus_precio = 'Vigente'
                 GROUP BY m.id_medicamento, m.nombre_sustancia, m.tipo_medicamento
                 ORDER BY stock_total ASC"
            )->fetchAll();

            $por_caducar = $pdo->query(
                "SELECT m.nombre_sustancia, l.numero_lote, l.existencia_actual, l.fecha_caducidad,
                        DATEDIFF(l.fecha_caducidad, CURDATE()) AS dias_restantes
                 FROM LOTE_MEDICAMENTO l
                 JOIN MEDICAMENTO m ON l.id_medicamento = m.id_medicamento
                 WHERE l.fecha_caducidad <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
                   AND l.existencia_actual > 0
                 ORDER BY l.fecha_caducidad ASC"
            )->fetchAll();

            $mas_usados = $pdo->query(
                "SELECT m.nombre_sustancia, SUM(d.cantidad) AS total_dispensado
                 FROM DESPACHO_FARMACIA d
                 JOIN LOTE_MEDICAMENTO l ON d.id_lote = l.id_lote
                 JOIN MEDICAMENTO m ON l.id_medicamento = m.id_medicamento
                 GROUP BY m.id_medicamento, m.nombre_sustancia
                 ORDER BY total_dispensado DESC LIMIT 10"
            )->fetchAll();

            echo json_encode([
                'disponibles' => $disponibles,
                'por_caducar' => $por_caducar,
                'mas_usados'  => $mas_usados,
            ]);
            break;

        case 'inventario':
            $insumos = $pdo->query(
                "SELECT i.nombre_articulo, i.categoria, i.stock_actual, i.stock_minimo,
                        i.area_asignada,
                        CASE WHEN i.stock_actual <= i.stock_minimo THEN 'Crítico'
                             WHEN i.stock_actual <= i.stock_minimo * 1.5 THEN 'Bajo'
                             ELSE 'Normal' END AS estado
                 FROM INSUMO i ORDER BY estado ASC, i.nombre_articulo"
            )->fetchAll();

            $equipos = $pdo->query(
                "SELECT i.nombre_articulo, eq.estatus_equipo,
                        eq.fecha_ultimo_mantenimiento, eq.proxima_revision,
                        DATEDIFF(eq.proxima_revision, CURDATE()) AS dias_para_revision
                 FROM EQUIPO_MEDICO eq
                 JOIN INSUMO i ON eq.id_insumo = i.id_insumo
                 ORDER BY eq.proxima_revision ASC"
            )->fetchAll();

            echo json_encode([
                'insumos' => $insumos,
                'equipos' => $equipos,
            ]);
            break;

        default:
            echo json_encode(['error' => 'Tipo de reporte no reconocido']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al generar el reporte']);
}
