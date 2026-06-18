<?php
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/middleware/auth_check.php';
require_once dirname(__DIR__) . '/db.php';

$usuario = requireAdmin();
$pdo     = getDB();

// RF-24: solo el administrador puede consultar la bitácora
$fechaDesde = $_GET['desde']   ?? date('Y-m-d', strtotime('-30 days'));
$fechaHasta = $_GET['hasta']   ?? date('Y-m-d');
$modulo     = $_GET['modulo']  ?? '';
$accion     = $_GET['accion']  ?? '';
$idUsuario  = (int)($_GET['id_usuario'] ?? 0);
$pagina     = max(1, (int)($_GET['pagina'] ?? 1));
$porPagina  = 50;
$offset     = ($pagina - 1) * $porPagina;

try {
    $where = ['l.fecha_hora BETWEEN ? AND ?'];
    $params = [$fechaDesde . ' 00:00:00', $fechaHasta . ' 23:59:59'];

    if ($modulo) {
        $where[] = 'l.modulo = ?';
        $params[] = $modulo;
    }
    if ($accion) {
        $where[] = 'l.accion LIKE ?';
        $params[] = '%' . $accion . '%';
    }
    if ($idUsuario) {
        $where[] = 'l.id_usuario = ?';
        $params[] = $idUsuario;
    }

    $whereSQL = implode(' AND ', $where);

    // Total de registros
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM LOG_AUDITORIA l WHERE $whereSQL");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // Registros paginados
    $stmt = $pdo->prepare(
        "SELECT l.id_log, l.modulo, l.accion, l.fecha_hora, l.ip_equipo, l.detalle_cambio,
                CONCAT(e.nombre,' ',e.apellido_paterno) AS usuario_nombre,
                e.tipo AS usuario_tipo, r.nombre_rol
         FROM LOG_AUDITORIA l
         JOIN EMPLEADO e ON l.id_usuario = e.id_empleado
         JOIN ROL r ON e.id_rol = r.id_rol
         WHERE $whereSQL
         ORDER BY l.fecha_hora DESC
         LIMIT $porPagina OFFSET $offset"
    );
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    // Módulos disponibles para filtro
    $modulos = $pdo->query("SELECT DISTINCT modulo FROM LOG_AUDITORIA ORDER BY modulo")->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'logs'          => $logs,
        'total'         => $total,
        'pagina'        => $pagina,
        'por_pagina'    => $porPagina,
        'total_paginas' => (int)ceil($total / $porPagina),
        'modulos'       => $modulos,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al cargar la bitácora']);
}
