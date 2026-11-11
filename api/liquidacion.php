<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

$pdo = Database::getConnection();

// Rango de fechas por mes o específico
$mes = $_GET['mes'] ?? date('Y-m'); // ej. 2026-10
$fecha_inicio = $_GET['fecha_inicio'] ?? "{$mes}-01";
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t', strtotime($fecha_inicio));

$sql = "
    SELECT 
        m.id AS medico_id,
        m.nombre AS medico,
        m.especialidad,
        m.porcentaje_honorario,
        COUNT(CASE WHEN c.estado IN ('Pagado', 'Facturado', 'Completado', 'Atendido') THEN 1 END) AS cantidad_citas,
        COUNT(CASE WHEN c.estado IN ('Pagado', 'Facturado') THEN 1 END) AS citas_pagadas,
        COALESCE(SUM(CASE WHEN c.estado IN ('Pagado', 'Facturado') THEN (c.precio_usd + COALESCE(c.adicional_monto, 0)) ELSE 0 END), 0) AS total_bruto,
        
        -- Monto Cobrado en caja física: Base de consulta según beneficio + Adicionales cobrados al paciente
        COALESCE(SUM(CASE 
            WHEN c.estado IN ('Pagado', 'Facturado') AND c.tipo = 'Particular' THEN c.precio_usd + COALESCE(c.adicional_monto, 0)
            WHEN c.estado IN ('Pagado', 'Facturado') AND c.tipo IN ('Familiar', 'Exonerado ADH') THEN (c.precio_usd * (COALESCE(m.porcentaje_honorario, 50.0) / 100.0)) + COALESCE(c.adicional_monto, 0)
            WHEN c.estado IN ('Pagado', 'Facturado') AND c.tipo = 'Exonerado Doctor' THEN (c.precio_usd * ((100.0 - COALESCE(m.porcentaje_honorario, 50.0)) / 100.0)) + COALESCE(c.adicional_monto, 0)
            WHEN c.estado IN ('Pagado', 'Facturado') AND c.tipo IN ('Empleado', 'Participante') THEN COALESCE(c.adicional_monto, 0)
            ELSE 0 
        END), 0) AS monto_cobrado,
        
        -- Monto ADH (Centro Médico): (100 - %Honorario)% sobre Particular y Exonerado Doctor + margen sobre adicionales netos de laboratorio
        COALESCE(SUM(CASE 
            WHEN c.estado IN ('Pagado', 'Facturado') THEN
                (CASE WHEN c.tipo IN ('Particular', 'Exonerado Doctor') 
                      THEN c.precio_usd * ((100.0 - COALESCE(m.porcentaje_honorario, 50.0)) / 100.0)
                      ELSE 0 
                 END)
                + (GREATEST(0, COALESCE(c.adicional_monto, 0) - COALESCE(c.costo_laboratorio, 0)) * ((100.0 - COALESCE(m.porcentaje_honorario, 50.0)) / 100.0))
            ELSE 0 
        END), 0) AS monto_adh,
        
        -- Monto Médico: %Honorario sobre base + honorario sobre adicionales netos de laboratorio
        COALESCE(SUM(CASE 
            WHEN c.estado IN ('Pagado', 'Facturado') THEN
                (CASE WHEN c.tipo IN ('Particular', 'Familiar', 'Participante', 'Empleado', 'Exonerado ADH') 
                      THEN c.precio_usd * (COALESCE(m.porcentaje_honorario, 50.0) / 100.0)
                      ELSE 0 
                 END)
                + (GREATEST(0, COALESCE(c.adicional_monto, 0) - COALESCE(c.costo_laboratorio, 0)) * (COALESCE(m.porcentaje_honorario, 50.0) / 100.0))
            ELSE 0 
        END), 0) AS monto_medico,

        -- Costo de Laboratorio / Insumos de Terceros deducido
        COALESCE(SUM(CASE WHEN c.estado IN ('Pagado', 'Facturado') THEN COALESCE(c.costo_laboratorio, 0) ELSE 0 END), 0) AS monto_laboratorio

    FROM medicos m
    LEFT JOIN citas c ON c.medico_id = m.id AND c.fecha BETWEEN :fini AND :ffin
    WHERE m.activo = 1
    GROUP BY m.id, m.nombre, m.especialidad, m.porcentaje_honorario
    ORDER BY m.nombre ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':fini' => $fecha_inicio, ':ffin' => $fecha_fin]);
$liquidaciones = $stmt->fetchAll();

// Totales consolidados del mes
$totales = [
    'cantidad_citas'    => 0,
    'citas_pagadas'     => 0,
    'total_bruto'       => 0.0,
    'monto_cobrado'     => 0.0,
    'monto_adh'         => 0.0,
    'monto_medico'      => 0.0,
    'monto_laboratorio' => 0.0
];

foreach ($liquidaciones as $l) {
    $totales['cantidad_citas']    += (int)$l['cantidad_citas'];
    $totales['citas_pagadas']     += (int)$l['citas_pagadas'];
    $totales['total_bruto']       += (float)$l['total_bruto'];
    $totales['monto_cobrado']     += (float)$l['monto_cobrado'];
    $totales['monto_adh']         += (float)$l['monto_adh'];
    $totales['monto_medico']      += (float)$l['monto_medico'];
    $totales['monto_laboratorio'] += (float)($l['monto_laboratorio'] ?? 0.0);
}

// Citas con deducciones a laboratorios externos o insumos de terceros
$stmtDeducLab = $pdo->prepare("
    SELECT c.*, p.nombre AS paciente_nombre, p.cedula AS paciente_cedula, m.nombre AS medico_nombre, m.especialidad AS medico_especialidad
    FROM citas c
    INNER JOIN pacientes p ON p.id = c.paciente_id
    INNER JOIN medicos m ON m.id = c.medico_id
    WHERE c.costo_laboratorio > 0
      AND c.estado IN ('Pagado', 'Facturado')
      AND c.fecha BETWEEN :fini AND :ffin
    ORDER BY c.fecha ASC, c.hora ASC
");
$stmtDeducLab->execute([':fini' => $fecha_inicio, ':ffin' => $fecha_fin]);
$deducciones_laboratorio = $stmtDeducLab->fetchAll();

// Si se pide el detalle de citas de un médico específico
$medico_id = isset($_GET['medico_id']) ? (int)$_GET['medico_id'] : 0;
$citas_detalle = [];
if ($medico_id > 0) {
    $detStmt = $pdo->prepare("
        SELECT c.*, p.nombre AS paciente_nombre, p.cedula AS paciente_cedula, cons.nombre AS consultorio_nombre
        FROM citas c
        INNER JOIN pacientes p ON p.id = c.paciente_id
        INNER JOIN consultorios cons ON cons.id = c.consultorio_id
        WHERE c.medico_id = :mid AND c.fecha BETWEEN :fini AND :ffin
        ORDER BY c.fecha ASC, c.hora ASC
    ");
    $detStmt->execute([':mid' => $medico_id, ':fini' => $fecha_inicio, ':ffin' => $fecha_fin]);
    $citas_detalle = $detStmt->fetchAll();
}

sendJsonResponse(true, [
    'mes'                     => $mes,
    'fecha_inicio'            => $fecha_inicio,
    'fecha_fin'               => $fecha_fin,
    'liquidaciones'           => $liquidaciones,
    'totales'                 => $totales,
    'deducciones_laboratorio' => $deducciones_laboratorio,
    'citas_detalle'           => $citas_detalle
]);
