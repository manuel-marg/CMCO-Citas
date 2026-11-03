<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

// Cargar librería FPDF (soporte local directo y vía vendor)
if (file_exists(__DIR__ . '/../assets/vendor/fpdf/fpdf.php')) {
    require_once __DIR__ . '/../assets/vendor/fpdf/fpdf.php';
} elseif (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    http_response_code(500);
    die("Error: No se encontró el componente de generación PDF (FPDF).");
}

$pdo = Database::getConnection();

// Rango de fechas según el mes solicitado (ej. 2026-10)
$mes = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $mes = date('Y-m');
}
$fecha_inicio = "{$mes}-01";
$fecha_fin = date('Y-m-t', strtotime($fecha_inicio));

// Helpers de conversión y formateo de texto
function pdfTxt(string $str): string {
    return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
}

function formatearFechaPdf(string $fecha): string {
    $partes = explode('-', $fecha);
    if (count($partes) === 3) {
        return "{$partes[2]}/{$partes[1]}/{$partes[0]}";
    }
    return $fecha;
}

function obtenerTituloMesEs(string $mesParam): string {
    $partes = explode('-', $mesParam);
    $ano = $partes[0] ?? date('Y');
    $mesNum = $partes[1] ?? date('m');
    
    $nombresMeses = [
        '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo',
        '04' => 'Abril', '05' => 'Mayo',    '06' => 'Junio',
        '07' => 'Julio', '08' => 'Agosto',  '09' => 'Septiembre',
        '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
    ];
    
    $nombreMes = $nombresMeses[$mesNum] ?? 'Mes ' . $mesNum;
    return "Mes de {$nombreMes} de {$ano}";
}

$tituloMes = obtenerTituloMesEs($mes);

// =========================================================================
// 1. CONSULTA SQL: HONORARIOS MÉDICOS
// =========================================================================
$sql = "
    SELECT 
        m.id AS medico_id,
        m.nombre AS medico,
        m.especialidad,
        m.porcentaje_honorario,
        COUNT(CASE WHEN c.estado IN ('Pagado', 'Facturado', 'Completado', 'Atendido') THEN 1 END) AS cantidad_citas,
        COUNT(CASE WHEN c.estado IN ('Pagado', 'Facturado') THEN 1 END) AS citas_pagadas,
        COALESCE(SUM(CASE WHEN c.estado IN ('Pagado', 'Facturado') THEN c.precio_usd ELSE 0 END), 0) AS total_bruto,
        
        -- Monto Cobrado en caja física: Particular + % Honorario en Familiar y Exonerado ADH + % ADH en Exonerado Doctor
        COALESCE(SUM(CASE 
            WHEN c.estado IN ('Pagado', 'Facturado') AND c.tipo = 'Particular' THEN c.precio_usd
            WHEN c.estado IN ('Pagado', 'Facturado') AND c.tipo IN ('Familiar', 'Exonerado ADH') THEN c.precio_usd * (COALESCE(m.porcentaje_honorario, 50.0) / 100.0)
            WHEN c.estado IN ('Pagado', 'Facturado') AND c.tipo = 'Exonerado Doctor' THEN c.precio_usd * ((100.0 - COALESCE(m.porcentaje_honorario, 50.0)) / 100.0)
            ELSE 0 
        END), 0) AS monto_cobrado,
        
        -- Monto ADH (Centro Médico): (100 - %Honorario)% sobre Particular y Exonerado Doctor
        COALESCE(SUM(CASE 
            WHEN c.estado IN ('Pagado', 'Facturado') AND c.tipo IN ('Particular', 'Exonerado Doctor') 
                THEN c.precio_usd * ((100.0 - COALESCE(m.porcentaje_honorario, 50.0)) / 100.0)
            ELSE 0 
        END), 0) AS monto_adh,
        
        -- Monto Médico / Laboratorio: %Honorario sobre Particular, Familiar, Participante, Empleado, Exonerado ADH
        COALESCE(SUM(CASE 
            WHEN c.estado IN ('Pagado', 'Facturado') AND c.tipo IN ('Particular', 'Familiar', 'Participante', 'Empleado', 'Exonerado ADH') 
                THEN c.precio_usd * (COALESCE(m.porcentaje_honorario, 50.0) / 100.0)
            ELSE 0 
        END), 0) AS monto_medico

    FROM medicos m
    LEFT JOIN citas c ON c.medico_id = m.id AND c.fecha BETWEEN :fini AND :ffin
    WHERE m.activo = 1
    GROUP BY m.id, m.nombre, m.especialidad, m.porcentaje_honorario
    ORDER BY m.nombre ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':fini' => $fecha_inicio, ':ffin' => $fecha_fin]);
$liquidaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totales consolidados de médicos
$totales = [
    'cantidad_citas' => 0,
    'citas_pagadas'  => 0,
    'total_bruto'    => 0.0,
    'monto_cobrado'  => 0.0,
    'monto_adh'      => 0.0,
    'monto_medico'   => 0.0
];

foreach ($liquidaciones as $l) {
    $totales['cantidad_citas'] += (int)$l['cantidad_citas'];
    $totales['citas_pagadas']  += (int)$l['citas_pagadas'];
    $totales['total_bruto']    += (float)$l['total_bruto'];
    $totales['monto_cobrado']  += (float)$l['monto_cobrado'];
    $totales['monto_adh']      += (float)$l['monto_adh'];
    $totales['monto_medico']   += (float)$l['monto_medico'];
}

// =========================================================================
// 2. CONSULTAS SQL: AUDITORÍA DE BENEFICIOS Y EXONERACIONES
// =========================================================================

// A. Beneficio de Empleados
$stmtEmp = $pdo->prepare("
    SELECT c.*, p.nombre AS paciente_nombre, p.cedula, p.telefono, m.nombre AS medico_nombre
    FROM citas c
    INNER JOIN pacientes p ON p.id = c.paciente_id
    INNER JOIN medicos m ON m.id = c.medico_id
    WHERE c.tipo = 'Empleado' 
      AND c.estado IN ('Pagado', 'Facturado', 'Atendido', 'Completado')
      AND c.fecha BETWEEN :fini AND :ffin
    ORDER BY c.fecha ASC, c.hora ASC
");
$stmtEmp->execute([':fini' => $fecha_inicio, ':ffin' => $fecha_fin]);
$empleados = $stmtEmp->fetchAll(PDO::FETCH_ASSOC);

// B. Beneficio de Familiares Directos (50% Desc.)
$stmtFam = $pdo->prepare("
    SELECT c.*, p.nombre AS paciente_nombre, p.cedula, p.telefono, p.representante, m.nombre AS medico_nombre
    FROM citas c
    INNER JOIN pacientes p ON p.id = c.paciente_id
    INNER JOIN medicos m ON m.id = c.medico_id
    WHERE c.tipo = 'Familiar' 
      AND c.estado IN ('Pagado', 'Facturado', 'Atendido', 'Completado')
      AND c.fecha BETWEEN :fini AND :ffin
    ORDER BY c.fecha ASC, c.hora ASC
");
$stmtFam->execute([':fini' => $fecha_inicio, ':ffin' => $fecha_fin]);
$familiares = $stmtFam->fetchAll(PDO::FETCH_ASSOC);

// C. Beneficio de Participantes ADH
$stmtPart = $pdo->prepare("
    SELECT c.*, p.nombre AS paciente_nombre, p.cedula, p.telefono, m.nombre AS medico_nombre
    FROM citas c
    INNER JOIN pacientes p ON p.id = c.paciente_id
    INNER JOIN medicos m ON m.id = c.medico_id
    WHERE c.tipo = 'Participante' 
      AND c.estado IN ('Pagado', 'Facturado', 'Atendido', 'Completado')
      AND c.fecha BETWEEN :fini AND :ffin
    ORDER BY c.fecha ASC, c.hora ASC
");
$stmtPart->execute([':fini' => $fecha_inicio, ':ffin' => $fecha_fin]);
$participantes = $stmtPart->fetchAll(PDO::FETCH_ASSOC);

// D. Exoneraciones Especiales (Exonerado ADH y Exonerado Doctor)
$stmtExon = $pdo->prepare("
    SELECT c.*, p.nombre AS paciente_nombre, p.cedula, p.telefono, m.nombre AS medico_nombre
    FROM citas c
    INNER JOIN pacientes p ON p.id = c.paciente_id
    INNER JOIN medicos m ON m.id = c.medico_id
    WHERE c.tipo IN ('Exonerado ADH', 'Exonerado Doctor') 
      AND c.estado IN ('Pagado', 'Facturado', 'Atendido', 'Completado')
      AND c.fecha BETWEEN :fini AND :ffin
    ORDER BY c.fecha ASC, c.hora ASC
");
$stmtExon->execute([':fini' => $fecha_inicio, ':ffin' => $fecha_fin]);
$exonerados = $stmtExon->fetchAll(PDO::FETCH_ASSOC);

/**
 * Clase para el Documento PDF de Liquidación Consolidada
 * Formato Letter apaisado (Landscape): 279.4 x 215.9 mm (ancho útil 259.4 mm)
 */
class LiquidacionConsolidadaPDF extends FPDF {
    protected string $tituloMes = '';
    protected string $tablaEnCurso = '';

    public function setTituloMes(string $titulo): void {
        $this->tituloMes = $titulo;
    }

    public function setTablaEnCurso(string $tabla): void {
        $this->tablaEnCurso = $tabla;
    }

    // Cabecera ejecutiva común en todas las páginas
    function Header(): void {
        // Logo de la institución (proporción 5:3)
        $logoPath = dirname(__DIR__) . '/assets/img/logo.png';
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 10, 8, 25);
        }

        // Título del Documento
        $this->SetXY(38, 9);
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(29, 67, 83); // #1d4353
        $this->Cell(140, 6, pdfTxt('Liquidación Mensual Consolidada'), 0, 1, 'L');

        // Subtítulo del Mes
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(79, 144, 165); // #4f90a5
        $this->SetX(38);
        $this->Cell(140, 5, pdfTxt($this->tituloMes), 0, 1, 'L');

        // Subtítulo institucional
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(108, 117, 125);
        $this->SetX(38);
        $this->Cell(140, 4, pdfTxt('Centro Médico Comunitario OscaryAnny (CMCO / ADH)'), 0, 1, 'L');

        // Fecha y hora de emisión (derecha)
        $this->SetXY(180, 10);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(89.4, 5, pdfTxt('Fecha de emisión: ' . date('d/m/Y h:i A')), 0, 0, 'R');

        // Línea sutil de separación
        $this->SetDrawColor(210, 220, 228);
        $this->SetLineWidth(0.3);
        $this->Line(10, 26, 269.4, 26);

        // Posicionar cursor después del membrete
        $this->SetY(29);

        // Si hay una tabla en curso que se extendió por salto de página, reimprimir sus cabeceras
        if ($this->tablaEnCurso === 'honorarios') {
            $this->imprimirCabeceraHonorarios();
        } elseif ($this->tablaEnCurso === 'empleados') {
            $this->imprimirCabeceraEmpleados();
        } elseif ($this->tablaEnCurso === 'familiares') {
            $this->imprimirCabeceraFamiliares();
        } elseif ($this->tablaEnCurso === 'participantes') {
            $this->imprimirCabeceraParticipantes();
        } elseif ($this->tablaEnCurso === 'exonerados') {
            $this->imprimirCabeceraExonerados();
        }
    }

    // Pie de Página
    function Footer(): void {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(130, 8, pdfTxt('Centro Médico Comunitario OscaryAnny - Documento Oficial de Liquidación'), 0, 0, 'L');
        $this->Cell(129.4, 8, pdfTxt('Página ' . $this->PageNo() . ' de {nb}'), 0, 0, 'R');
    }

    // Asegurar espacio vertical antes de imprimir un bloque
    public function asegurarEspacio(float $altoRequerido): void {
        if ($this->GetY() + $altoRequerido > ($this->h - 18)) {
            $this->AddPage();
        }
    }

    // Barra destacada para títulos de Sección Principal
    public function imprimirTituloSeccion(string $titulo): void {
        $this->asegurarEspacio(14);
        $this->SetFont('Arial', 'B', 10.5);
        $this->SetFillColor(29, 67, 83); // #1d4353
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(20, 50, 65);
        $this->SetLineWidth(0.3);
        $this->Cell(259.4, 7.5, pdfTxt('  ' . $titulo), 1, 1, 'L', true);
        $this->Ln(1.5);
    }

    // Barra destacada para títulos de Subsección de Beneficios
    public function imprimirTituloSubseccion(string $titulo, int $cantidad, float $totalMonto = 0.0, string $extra = ''): void {
        $this->asegurarEspacio(18);
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(235, 244, 248);
        $this->SetTextColor(29, 67, 83);
        $this->SetDrawColor(180, 205, 215);
        $this->SetLineWidth(0.3);

        $texto = "  " . $titulo . " (" . $cantidad . " registros" . ($totalMonto > 0 ? " - Monto Total: $" . number_format($totalMonto, 2) : "") . ($extra ? " - " . $extra : "") . ")";
        $this->Cell(259.4, 6.8, pdfTxt($texto), 1, 1, 'L', true);
    }

    // Cabecera: Honorarios Médicos (Suma de anchos: 259.4 mm)
    public function imprimirCabeceraHonorarios(): void {
        $this->SetFont('Arial', 'B', 8.5);
        $this->SetFillColor(79, 144, 165); // #4f90a5
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(56, 115, 135);
        $this->SetLineWidth(0.3);

        $this->Cell(59.4, 7.5, pdfTxt('  Médico / Especialista'), 1, 0, 'L', true);
        $this->Cell(40.0, 7.5, pdfTxt('  Especialidad'), 1, 0, 'L', true);
        $this->Cell(22.0, 7.5, pdfTxt('Total Citas'), 1, 0, 'C', true);
        $this->Cell(24.0, 7.5, pdfTxt('Citas Pagadas'), 1, 0, 'C', true);
        $this->Cell(28.5, 7.5, pdfTxt('Total Bruto  '), 1, 0, 'R', true);
        $this->Cell(28.5, 7.5, pdfTxt('Cobrado  '), 1, 0, 'R', true);
        $this->Cell(28.5, 7.5, pdfTxt('Monto ADH  '), 1, 0, 'R', true);
        $this->Cell(28.5, 7.5, pdfTxt('Monto Médico  '), 1, 1, 'R', true);
    }

    // Cabecera: Empleados del Mes (Suma de anchos: 259.4 mm)
    public function imprimirCabeceraEmpleados(): void {
        $this->SetFont('Arial', 'B', 8.2);
        $this->SetFillColor(79, 144, 165);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(56, 115, 135);
        $this->SetLineWidth(0.3);

        $this->Cell(24.0, 6.8, pdfTxt('Fecha'), 1, 0, 'C', true);
        $this->Cell(68.0, 6.8, pdfTxt('  Nombre del Empleado (Paciente)'), 1, 0, 'L', true);
        $this->Cell(28.0, 6.8, pdfTxt('Cédula'), 1, 0, 'C', true);
        $this->Cell(32.0, 6.8, pdfTxt('Teléfono'), 1, 0, 'C', true);
        $this->Cell(60.0, 6.8, pdfTxt('  Médico Tratante'), 1, 0, 'L', true);
        $this->Cell(24.0, 6.8, pdfTxt('Monto ($)  '), 1, 0, 'R', true);
        $this->Cell(23.4, 6.8, pdfTxt('Estado'), 1, 1, 'C', true);
    }

    // Cabecera: Familiares Directos con 50% de Descuento (Suma de anchos: 259.4 mm)
    public function imprimirCabeceraFamiliares(): void {
        $this->SetFont('Arial', 'B', 8);
        $this->SetFillColor(79, 144, 165);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(56, 115, 135);
        $this->SetLineWidth(0.3);

        $this->Cell(20.0, 6.8, pdfTxt('Fecha'), 1, 0, 'C', true);
        $this->Cell(45.0, 6.8, pdfTxt('  Paciente Familiar'), 1, 0, 'L', true);
        $this->Cell(42.0, 6.8, pdfTxt('  Titular ADH / Repr.'), 1, 0, 'L', true);
        $this->Cell(24.0, 6.8, pdfTxt('Cédula'), 1, 0, 'C', true);
        $this->Cell(24.0, 6.8, pdfTxt('Teléfono'), 1, 0, 'C', true);
        $this->Cell(42.0, 6.8, pdfTxt('  Médico Tratante'), 1, 0, 'L', true);
        $this->Cell(21.0, 6.8, pdfTxt('Precio ($) '), 1, 0, 'R', true);
        $this->Cell(21.0, 6.8, pdfTxt('Cobrado (50%)'), 1, 0, 'R', true);
        $this->Cell(20.4, 6.8, pdfTxt('Estado'), 1, 1, 'C', true);
    }

    // Cabecera: Participantes ADH (Suma de anchos: 259.4 mm)
    public function imprimirCabeceraParticipantes(): void {
        $this->SetFont('Arial', 'B', 8.2);
        $this->SetFillColor(79, 144, 165);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(56, 115, 135);
        $this->SetLineWidth(0.3);

        $this->Cell(24.0, 6.8, pdfTxt('Fecha'), 1, 0, 'C', true);
        $this->Cell(68.0, 6.8, pdfTxt('  Participante ADH'), 1, 0, 'L', true);
        $this->Cell(28.0, 6.8, pdfTxt('Cédula'), 1, 0, 'C', true);
        $this->Cell(32.0, 6.8, pdfTxt('Teléfono'), 1, 0, 'C', true);
        $this->Cell(60.0, 6.8, pdfTxt('  Médico Tratante'), 1, 0, 'L', true);
        $this->Cell(24.0, 6.8, pdfTxt('Monto ($)  '), 1, 0, 'R', true);
        $this->Cell(23.4, 6.8, pdfTxt('Estado'), 1, 1, 'C', true);
    }

    // Cabecera: Exoneraciones (Suma de anchos: 259.4 mm)
    public function imprimirCabeceraExonerados(): void {
        $this->SetFont('Arial', 'B', 8.2);
        $this->SetFillColor(79, 144, 165);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(56, 115, 135);
        $this->SetLineWidth(0.3);

        $this->Cell(24.0, 6.8, pdfTxt('Fecha'), 1, 0, 'C', true);
        $this->Cell(65.0, 6.8, pdfTxt('  Paciente'), 1, 0, 'L', true);
        $this->Cell(38.0, 6.8, pdfTxt('Tipo Exoneración'), 1, 0, 'C', true);
        $this->Cell(28.0, 6.8, pdfTxt('Cédula'), 1, 0, 'C', true);
        $this->Cell(58.0, 6.8, pdfTxt('  Médico Tratante'), 1, 0, 'L', true);
        $this->Cell(24.0, 6.8, pdfTxt('Monto ($)  '), 1, 0, 'R', true);
        $this->Cell(22.4, 6.8, pdfTxt('Estado'), 1, 1, 'C', true);
    }
}

// Instanciar documento PDF
$pdf = new LiquidacionConsolidadaPDF('L', 'mm', 'Letter');
$pdf->AliasNbPages();
$pdf->setTituloMes($tituloMes);
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

// =========================================================================
// SECCIÓN 1: LIQUIDACIÓN DE HONORARIOS MÉDICOS
// =========================================================================
$pdf->imprimirTituloSeccion('SECCIÓN 1: LIQUIDACIÓN DE HONORARIOS MÉDICOS');
$pdf->setTablaEnCurso('honorarios');
$pdf->imprimirCabeceraHonorarios();

$pdf->SetDrawColor(226, 232, 240);
$pdf->SetLineWidth(0.2);

$fill = false;
foreach ($liquidaciones as $l) {
    $pdf->asegurarEspacio(7);
    $pdf->SetFillColor($fill ? 248 : 255, $fill ? 249 : 255, $fill ? 250 : 255);

    // 1. Médico
    $pdf->SetFont('Arial', 'B', 8.2);
    $pdf->SetTextColor(29, 67, 83);
    $pdf->Cell(59.4, 6.6, pdfTxt(' ' . $l['medico']), 1, 0, 'L', true);

    // 2. Especialidad
    $pdf->SetFont('Arial', '', 7.8);
    $pdf->SetTextColor(90, 95, 100);
    $pdf->Cell(40.0, 6.6, pdfTxt(' ' . ($l['especialidad'] ?: 'General')), 1, 0, 'L', true);

    // 3. Total Citas
    $pdf->SetFont('Arial', '', 8.2);
    $pdf->SetTextColor(33, 37, 41);
    $pdf->Cell(22.0, 6.6, (string)$l['cantidad_citas'], 1, 0, 'C', true);

    // 4. Citas Pagadas
    $pdf->SetFont('Arial', 'B', 8.2);
    $pdf->SetTextColor(25, 135, 84);
    $pdf->Cell(24.0, 6.6, (string)$l['citas_pagadas'], 1, 0, 'C', true);

    // 5. Total Bruto ($)
    $pdf->SetFont('Arial', '', 8.2);
    $pdf->SetTextColor(33, 37, 41);
    $pdf->Cell(28.5, 6.6, '$ ' . number_format((float)$l['total_bruto'], 2, '.', ',') . '  ', 1, 0, 'R', true);

    // 6. Monto Cobrado ($)
    $pdf->SetFont('Arial', 'B', 8.2);
    $pdf->SetTextColor(33, 37, 41);
    $pdf->Cell(28.5, 6.6, '$ ' . number_format((float)$l['monto_cobrado'], 2, '.', ',') . '  ', 1, 0, 'R', true);

    // 7. Monto ADH ($)
    $pdf->SetTextColor(79, 144, 165);
    $pdf->Cell(28.5, 6.6, '$ ' . number_format((float)$l['monto_adh'], 2, '.', ',') . '  ', 1, 0, 'R', true);

    // 8. Monto Médico ($)
    $pdf->SetTextColor(25, 135, 84);
    $pdf->Cell(28.5, 6.6, '$ ' . number_format((float)$l['monto_medico'], 2, '.', ',') . '  ', 1, 1, 'R', true);

    $fill = !$fill;
}

// Fila Destacada: Totales Honorarios del Mes
$pdf->asegurarEspacio(8.5);
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetFillColor(29, 67, 83); // #1d4353
$pdf->SetTextColor(255, 255, 255);
$pdf->SetDrawColor(20, 50, 65);
$pdf->SetLineWidth(0.3);

$pdf->Cell(99.4, 7.8, pdfTxt('  TOTALES DE HONORARIOS MÉDICOS:'), 1, 0, 'L', true);
$pdf->Cell(22.0, 7.8, (string)$totales['cantidad_citas'], 1, 0, 'C', true);
$pdf->Cell(24.0, 7.8, (string)$totales['citas_pagadas'], 1, 0, 'C', true);
$pdf->Cell(28.5, 7.8, '$ ' . number_format($totales['total_bruto'], 2, '.', ',') . '  ', 1, 0, 'R', true);
$pdf->Cell(28.5, 7.8, '$ ' . number_format($totales['monto_cobrado'], 2, '.', ',') . '  ', 1, 0, 'R', true);
$pdf->Cell(28.5, 7.8, '$ ' . number_format($totales['monto_adh'], 2, '.', ',') . '  ', 1, 0, 'R', true);
$pdf->Cell(28.5, 7.8, '$ ' . number_format($totales['monto_medico'], 2, '.', ',') . '  ', 1, 1, 'R', true);

$pdf->setTablaEnCurso('');

// =========================================================================
// SECCIÓN 2: AUDITORÍA Y CONTROL DE BENEFICIOS Y EXONERACIONES
// =========================================================================
$pdf->AddPage();
$pdf->imprimirTituloSeccion('SECCIÓN 2: AUDITORÍA Y CONTROL DE BENEFICIOS Y EXONERACIONES');

// -------------------------------------------------------------------------
// 2.1 EMPLEADOS DEL MES
// -------------------------------------------------------------------------
$totalEmp = array_sum(array_map(fn($c) => (float)$c['precio_usd'], $empleados));
$pdf->imprimirTituloSubseccion('1. EMPLEADOS DEL MES (Beneficio 100% Exonerado)', count($empleados), $totalEmp);
$pdf->setTablaEnCurso('empleados');
$pdf->imprimirCabeceraEmpleados();

$pdf->SetDrawColor(226, 232, 240);
$pdf->SetLineWidth(0.2);

if (empty($empleados)) {
    $pdf->SetFont('Arial', 'I', 8.2);
    $pdf->SetTextColor(108, 117, 125);
    $pdf->Cell(259.4, 6.5, pdfTxt('No se registraron consultas de empleados en este período.'), 1, 1, 'C');
} else {
    $fill = false;
    foreach ($empleados as $c) {
        $pdf->asegurarEspacio(6.5);
        $pdf->SetFillColor($fill ? 248 : 255, $fill ? 249 : 255, $fill ? 250 : 255);
        $pdf->SetTextColor(33, 37, 41);

        $pdf->SetFont('Arial', '', 7.8);
        $pdf->Cell(24.0, 6.2, formatearFechaPdf($c['fecha']), 1, 0, 'C', true);

        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor(29, 67, 83);
        $pdf->Cell(68.0, 6.2, pdfTxt(' ' . $c['paciente_nombre']), 1, 0, 'L', true);

        $pdf->SetFont('Arial', '', 7.8);
        $pdf->SetTextColor(33, 37, 41);
        $pdf->Cell(28.0, 6.2, pdfTxt($c['cedula'] ?: '-'), 1, 0, 'C', true);
        $pdf->Cell(32.0, 6.2, pdfTxt($c['telefono'] ?: '-'), 1, 0, 'C', true);
        $pdf->Cell(60.0, 6.2, pdfTxt(' ' . $c['medico_nombre']), 1, 0, 'L', true);

        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(24.0, 6.2, '$ ' . number_format((float)$c['precio_usd'], 2) . '  ', 1, 0, 'R', true);

        $pdf->SetFont('Arial', '', 7.5);
        $pdf->SetTextColor(25, 135, 84);
        $pdf->Cell(23.4, 6.2, pdfTxt($c['estado']), 1, 1, 'C', true);

        $fill = !$fill;
    }
}
$pdf->setTablaEnCurso('');
$pdf->Ln(3.5);

// -------------------------------------------------------------------------
// 2.2 FAMILIARES DIRECTOS (50% DESCUENTO)
// -------------------------------------------------------------------------
$totalFamBase = array_sum(array_map(fn($c) => (float)$c['precio_usd'], $familiares));
$totalFamCobrado = $totalFamBase * 0.5;
$pdf->imprimirTituloSubseccion(
    '2. FAMILIARES DIRECTOS (50% Descuento)', 
    count($familiares), 
    $totalFamBase, 
    'Cobrado (50%): $' . number_format($totalFamCobrado, 2)
);
$pdf->setTablaEnCurso('familiares');
$pdf->imprimirCabeceraFamiliares();

$pdf->SetDrawColor(226, 232, 240);
$pdf->SetLineWidth(0.2);

if (empty($familiares)) {
    $pdf->SetFont('Arial', 'I', 8.2);
    $pdf->SetTextColor(108, 117, 125);
    $pdf->Cell(259.4, 6.5, pdfTxt('No se registraron consultas de familiares directos en este período.'), 1, 1, 'C');
} else {
    $fill = false;
    foreach ($familiares as $c) {
        $pdf->asegurarEspacio(6.5);
        $pdf->SetFillColor($fill ? 248 : 255, $fill ? 249 : 255, $fill ? 250 : 255);
        $pdf->SetTextColor(33, 37, 41);

        $pdf->SetFont('Arial', '', 7.8);
        $pdf->Cell(20.0, 6.2, formatearFechaPdf($c['fecha']), 1, 0, 'C', true);

        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor(29, 67, 83);
        $pdf->Cell(45.0, 6.2, pdfTxt(' ' . $c['paciente_nombre']), 1, 0, 'L', true);

        $pdf->SetFont('Arial', '', 7.8);
        $pdf->SetTextColor(60, 60, 60);
        $titular = $c['representante'] ?: ($c['factura_o_empleado'] ?: '-');
        $pdf->Cell(42.0, 6.2, pdfTxt(' ' . $titular), 1, 0, 'L', true);

        $pdf->Cell(24.0, 6.2, pdfTxt($c['cedula'] ?: '-'), 1, 0, 'C', true);
        $pdf->Cell(24.0, 6.2, pdfTxt($c['telefono'] ?: '-'), 1, 0, 'C', true);
        $pdf->Cell(42.0, 6.2, pdfTxt(' ' . $c['medico_nombre']), 1, 0, 'L', true);

        $precioBase = (float)$c['precio_usd'];
        $montoCobrado50 = $precioBase * 0.5;

        $pdf->SetFont('Arial', '', 7.8);
        $pdf->SetTextColor(33, 37, 41);
        $pdf->Cell(21.0, 6.2, '$ ' . number_format($precioBase, 2) . ' ', 1, 0, 'R', true);

        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor(25, 135, 84);
        $pdf->Cell(21.0, 6.2, '$ ' . number_format($montoCobrado50, 2) . ' ', 1, 0, 'R', true);

        $pdf->SetFont('Arial', '', 7.5);
        $pdf->Cell(20.4, 6.2, pdfTxt($c['estado']), 1, 1, 'C', true);

        $fill = !$fill;
    }
}
$pdf->setTablaEnCurso('');
$pdf->Ln(3.5);

// -------------------------------------------------------------------------
// 2.3 PARTICIPANTES ADH
// -------------------------------------------------------------------------
$totalPart = array_sum(array_map(fn($c) => (float)$c['precio_usd'], $participantes));
$pdf->imprimirTituloSubseccion('3. PARTICIPANTES ADH (Beneficio Financiado por Institución)', count($participantes), $totalPart);
$pdf->setTablaEnCurso('participantes');
$pdf->imprimirCabeceraParticipantes();

$pdf->SetDrawColor(226, 232, 240);
$pdf->SetLineWidth(0.2);

if (empty($participantes)) {
    $pdf->SetFont('Arial', 'I', 8.2);
    $pdf->SetTextColor(108, 117, 125);
    $pdf->Cell(259.4, 6.5, pdfTxt('No se registraron consultas de participantes en este período.'), 1, 1, 'C');
} else {
    $fill = false;
    foreach ($participantes as $c) {
        $pdf->asegurarEspacio(6.5);
        $pdf->SetFillColor($fill ? 248 : 255, $fill ? 249 : 255, $fill ? 250 : 255);
        $pdf->SetTextColor(33, 37, 41);

        $pdf->SetFont('Arial', '', 7.8);
        $pdf->Cell(24.0, 6.2, formatearFechaPdf($c['fecha']), 1, 0, 'C', true);

        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor(29, 67, 83);
        $pdf->Cell(68.0, 6.2, pdfTxt(' ' . $c['paciente_nombre']), 1, 0, 'L', true);

        $pdf->SetFont('Arial', '', 7.8);
        $pdf->SetTextColor(33, 37, 41);
        $pdf->Cell(28.0, 6.2, pdfTxt($c['cedula'] ?: '-'), 1, 0, 'C', true);
        $pdf->Cell(32.0, 6.2, pdfTxt($c['telefono'] ?: '-'), 1, 0, 'C', true);
        $pdf->Cell(60.0, 6.2, pdfTxt(' ' . $c['medico_nombre']), 1, 0, 'L', true);

        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(24.0, 6.2, '$ ' . number_format((float)$c['precio_usd'], 2) . '  ', 1, 0, 'R', true);

        $pdf->SetFont('Arial', '', 7.5);
        $pdf->SetTextColor(25, 135, 84);
        $pdf->Cell(23.4, 6.2, pdfTxt($c['estado']), 1, 1, 'C', true);

        $fill = !$fill;
    }
}
$pdf->setTablaEnCurso('');
$pdf->Ln(3.5);

// -------------------------------------------------------------------------
// 2.4 EXONERACIONES ESPECIALES
// -------------------------------------------------------------------------
$totalExon = array_sum(array_map(fn($c) => (float)$c['precio_usd'], $exonerados));
$pdf->imprimirTituloSubseccion('4. EXONERACIONES ESPECIALES (Exonerado ADH y Exonerado Doctor)', count($exonerados), $totalExon);
$pdf->setTablaEnCurso('exonerados');
$pdf->imprimirCabeceraExonerados();

$pdf->SetDrawColor(226, 232, 240);
$pdf->SetLineWidth(0.2);

if (empty($exonerados)) {
    $pdf->SetFont('Arial', 'I', 8.2);
    $pdf->SetTextColor(108, 117, 125);
    $pdf->Cell(259.4, 6.5, pdfTxt('No se registraron consultas exoneradas en este período.'), 1, 1, 'C');
} else {
    $fill = false;
    foreach ($exonerados as $c) {
        $pdf->asegurarEspacio(6.5);
        $pdf->SetFillColor($fill ? 248 : 255, $fill ? 249 : 255, $fill ? 250 : 255);
        $pdf->SetTextColor(33, 37, 41);

        $pdf->SetFont('Arial', '', 7.8);
        $pdf->Cell(24.0, 6.2, formatearFechaPdf($c['fecha']), 1, 0, 'C', true);

        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor(29, 67, 83);
        $pdf->Cell(65.0, 6.2, pdfTxt(' ' . $c['paciente_nombre']), 1, 0, 'L', true);

        $pdf->SetFont('Arial', 'B', 7.5);
        $pdf->SetTextColor(133, 100, 4);
        $pdf->Cell(38.0, 6.2, pdfTxt($c['tipo']), 1, 0, 'C', true);

        $pdf->SetFont('Arial', '', 7.8);
        $pdf->SetTextColor(33, 37, 41);
        $pdf->Cell(28.0, 6.2, pdfTxt($c['cedula'] ?: '-'), 1, 0, 'C', true);
        $pdf->Cell(58.0, 6.2, pdfTxt(' ' . $c['medico_nombre']), 1, 0, 'L', true);

        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(24.0, 6.2, '$ ' . number_format((float)$c['precio_usd'], 2) . '  ', 1, 0, 'R', true);

        $pdf->SetFont('Arial', '', 7.5);
        $pdf->SetTextColor(25, 135, 84);
        $pdf->Cell(22.4, 6.2, pdfTxt($c['estado']), 1, 1, 'C', true);

        $fill = !$fill;
    }
}
$pdf->setTablaEnCurso('');
$pdf->Ln(4.0);

// =========================================================================
// 3. BALANCE Y CONCILIACIÓN CONSOLIDADA GENERAL
// =========================================================================
$totalBeneficiosConsultas = count($empleados) + count($familiares) + count($participantes) + count($exonerados);
$totalBeneficiosMonto = $totalEmp + $totalFamBase + $totalPart + $totalExon;

$pdf->asegurarEspacio(38);

// Tarjeta doble de balance
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(29, 67, 83);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetDrawColor(20, 50, 65);
$pdf->SetLineWidth(0.3);

// Título general del balance
$pdf->Cell(259.4, 7.5, pdfTxt('  BALANCE CONSOLIDADO DEL MES - HONORARIOS, INGRESOS Y BENEFICIOS SOCIALES'), 1, 1, 'L', true);

// Fila 1: Subtítulos de las dos columnas (129.7 mm cada una)
$pdf->SetFont('Arial', 'B', 8.2);
$pdf->SetFillColor(79, 144, 165);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(129.7, 6.5, pdfTxt('  1. RESUMEN DE BENEFICIOS Y EXONERACIONES OTORGADAS'), 1, 0, 'L', true);
$pdf->Cell(129.7, 6.5, pdfTxt('  2. CONCILIACIÓN FINANCIERA INSTITUCIONAL CMCO / ADH'), 1, 1, 'L', true);

// Filas de desglose
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(33, 37, 41);
$pdf->SetDrawColor(226, 232, 240);
$pdf->SetLineWidth(0.2);

// Fila A
$pdf->SetFillColor(255, 255, 255);
$pdf->Cell(85.0, 5.8, pdfTxt('  - Empleados del Mes:'), 'L', 0, 'L', true);
$pdf->Cell(44.7, 5.8, pdfTxt(count($empleados) . ' citas  ($' . number_format($totalEmp, 2) . ')'), 'R', 0, 'R', true);

$pdf->Cell(85.0, 5.8, pdfTxt('  - Total Cobrado en Caja / Bancos:'), 'L', 0, 'L', true);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(44.7, 5.8, '$ ' . number_format($totales['monto_cobrado'], 2) . '  ', 'R', 1, 'R', true);

// Fila B
$pdf->SetFont('Arial', '', 8);
$pdf->SetFillColor(248, 249, 250);
$pdf->Cell(85.0, 5.8, pdfTxt('  - Familiares Directos (50% Desc.):'), 'L', 0, 'L', true);
$pdf->Cell(44.7, 5.8, pdfTxt(count($familiares) . ' citas  (Cobrado: $' . number_format($totalFamCobrado, 2) . ')'), 'R', 0, 'R', true);

$pdf->Cell(85.0, 5.8, pdfTxt('  - Total Liquidación Pagada a Médicos:'), 'L', 0, 'L', true);
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetTextColor(25, 135, 84);
$pdf->Cell(44.7, 5.8, '$ ' . number_format($totales['monto_medico'], 2) . '  ', 'R', 1, 'R', true);

// Fila C
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(33, 37, 41);
$pdf->SetFillColor(255, 255, 255);
$pdf->Cell(85.0, 5.8, pdfTxt('  - Participantes ADH:'), 'L', 0, 'L', true);
$pdf->Cell(44.7, 5.8, pdfTxt(count($participantes) . ' citas  ($' . number_format($totalPart, 2) . ')'), 'R', 0, 'R', true);

$pdf->Cell(85.0, 5.8, pdfTxt('  - Margen Neto Centro Médico (Monto ADH):'), 'L', 0, 'L', true);
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetTextColor(79, 144, 165);
$pdf->Cell(44.7, 5.8, '$ ' . number_format($totales['monto_adh'], 2) . '  ', 'R', 1, 'R', true);

// Fila D
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(33, 37, 41);
$pdf->SetFillColor(248, 249, 250);
$pdf->Cell(85.0, 5.8, pdfTxt('  - Consultas Exoneradas:'), 'L', 0, 'L', true);
$pdf->Cell(44.7, 5.8, pdfTxt(count($exonerados) . ' citas  ($' . number_format($totalExon, 2) . ')'), 'R', 0, 'R', true);

$pdf->Cell(85.0, 5.8, pdfTxt('  - Total Citas Efectivas del Período:'), 'L', 0, 'L', true);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(44.7, 5.8, $totales['citas_pagadas'] . ' pagadas de ' . $totales['cantidad_citas'] . '  ', 'R', 1, 'R', true);

// Fila Totales de Balance (Destacada)
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetFillColor(235, 244, 248);
$pdf->SetTextColor(29, 67, 83);
$pdf->SetDrawColor(180, 205, 215);
$pdf->SetLineWidth(0.3);

$pdf->Cell(85.0, 7.0, pdfTxt('  TOTAL CONSULTAS BENEFICIADAS:'), 1, 0, 'L', true);
$pdf->Cell(44.7, 7.0, $totalBeneficiosConsultas . ' Citas ($' . number_format($totalBeneficiosMonto, 2) . ')  ', 1, 0, 'R', true);

$pdf->Cell(85.0, 7.0, pdfTxt('  TOTAL BRUTO DECLARADO:'), 1, 0, 'L', true);
$pdf->Cell(44.7, 7.0, '$ ' . number_format($totales['total_bruto'], 2) . '  ', 1, 1, 'R', true);

// Limpiar buffer de salida si hubiese algo previo
if (ob_get_length()) {
    ob_end_clean();
}

$nombreArchivo = 'Liquidacion_Consolidada_' . str_replace('-', '_', $mes) . '.pdf';
$pdf->Output('I', $nombreArchivo);
exit;
