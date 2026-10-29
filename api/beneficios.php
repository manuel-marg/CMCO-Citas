<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

$pdo = Database::getConnection();

$mes = $_GET['mes'] ?? date('Y-m');
$fecha_inicio = $_GET['fecha_inicio'] ?? "{$mes}-01";
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t', strtotime($fecha_inicio));

// 1. Beneficio de Empleados
$stmtEmp = $pdo->prepare("
    SELECT c.*, p.nombre AS paciente_nombre, p.cedula, p.telefono, m.nombre AS medico_nombre
    FROM citas c
    INNER JOIN pacientes p ON p.id = c.paciente_id
    INNER JOIN medicos m ON m.id = c.medico_id
    WHERE c.tipo = 'Empleado' 
      AND c.estado IN ('Pagado', 'Facturado')
      AND c.fecha BETWEEN :fini AND :ffin
    ORDER BY c.fecha ASC
");
$stmtEmp->execute([':fini' => $fecha_inicio, ':ffin' => $fecha_fin]);
$empleados = $stmtEmp->fetchAll();

// 2. Beneficio de Familiares Directos (50% desc.)
$stmtFam = $pdo->prepare("
    SELECT c.*, p.nombre AS paciente_nombre, p.cedula, p.telefono, p.representante, m.nombre AS medico_nombre
    FROM citas c
    INNER JOIN pacientes p ON p.id = c.paciente_id
    INNER JOIN medicos m ON m.id = c.medico_id
    WHERE c.tipo = 'Familiar' 
      AND c.estado IN ('Pagado', 'Facturado')
      AND c.fecha BETWEEN :fini AND :ffin
    ORDER BY c.fecha ASC
");
$stmtFam->execute([':fini' => $fecha_inicio, ':ffin' => $fecha_fin]);
$familiares = $stmtFam->fetchAll();

// 3. Beneficio de Participantes ADH
$stmtPart = $pdo->prepare("
    SELECT c.*, p.nombre AS paciente_nombre, p.cedula, p.telefono, m.nombre AS medico_nombre
    FROM citas c
    INNER JOIN pacientes p ON p.id = c.paciente_id
    INNER JOIN medicos m ON m.id = c.medico_id
    WHERE c.tipo = 'Participante' 
      AND c.estado IN ('Pagado', 'Facturado')
      AND c.fecha BETWEEN :fini AND :ffin
    ORDER BY c.fecha ASC
");
$stmtPart->execute([':fini' => $fecha_inicio, ':ffin' => $fecha_fin]);
$participantes = $stmtPart->fetchAll();

// 4. Exoneraciones (ADH y Doctor)
$stmtExon = $pdo->prepare("
    SELECT c.*, p.nombre AS paciente_nombre, p.cedula, p.telefono, m.nombre AS medico_nombre
    FROM citas c
    INNER JOIN pacientes p ON p.id = c.paciente_id
    INNER JOIN medicos m ON m.id = c.medico_id
    WHERE c.tipo IN ('Exonerado ADH', 'Exonerado Doctor')
      AND c.estado IN ('Pagado', 'Facturado')
      AND c.fecha BETWEEN :fini AND :ffin
    ORDER BY c.fecha ASC
");
$stmtExon->execute([':fini' => $fecha_inicio, ':ffin' => $fecha_fin]);
$exonerados = $stmtExon->fetchAll();

sendJsonResponse(true, [
    'mes'           => $mes,
    'fecha_inicio'  => $fecha_inicio,
    'fecha_fin'     => $fecha_fin,
    'empleados'     => $empleados,
    'familiares'    => $familiares,
    'participantes' => $participantes,
    'exonerados'    => $exonerados
]);
