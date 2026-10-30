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
$fecha_inicio = "{$mes}-01";
$fecha_fin = date('Y-m-t', strtotime($fecha_inicio));

// Consulta SQL con la lógica de negocio según porcentaje de honorario asignado a cada profesional/laboratorio
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

// Totales consolidados del mes
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

// Convertir mes a formato formal en español: "Mes de Octubre de 2026"
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

// Helper para convertir caracteres UTF-8 a ISO-8859-1 sin errores en PHP 8.2+
function pdfTxt(string $str): string {
    return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
}

/**
 * Clase para el Documento PDF de Liquidación en Orientación Horizontal (Landscape)
 */
class LiquidacionDocumentoPDF extends FPDF {
    protected string $tituloDocumento;

    public function setTituloDocumento(string $titulo): void {
        $this->tituloDocumento = $titulo;
    }

    // Cabecera de Página (Se ejecuta automáticamente al agregar cada página)
    function Header(): void {
        // Logo de la institución (proporción 5:3)
        $logoPath = dirname(__DIR__) . '/assets/img/logo.png';
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 10, 9, 25);
        }

        // Título del Mes
        $this->SetXY(38, 10);
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(29, 67, 83); // #1d4353
        $this->Cell(140, 7, pdfTxt($this->tituloDocumento), 0, 1, 'L');

        // Subtítulo institucional
        $this->SetFont('Arial', '', 8.5);
        $this->SetTextColor(108, 117, 125);
        $this->SetX(38);
        $this->Cell(140, 5, pdfTxt('Centro Médico Comunitario OscaryAnny (CMCO / ADH)'), 0, 1, 'L');

        // Fecha y hora de emisión (derecha)
        $this->SetXY(180, 11);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(89.4, 5, pdfTxt('Fecha de emisión: ' . date('d/m/Y h:i A')), 0, 0, 'R');

        // Espacio superior (margin-top) para que la tabla arranque bien abajo del logo
        $this->SetY(32);

        // Encabezados de la Tabla de Datos (directamente después del título)
        $this->SetFont('Arial', 'B', 8.5);
        $this->SetFillColor(79, 144, 165); // #4f90a5 (Azul corporativo)
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(56, 115, 135);
        $this->SetLineWidth(0.3);

        // Suma exacta de anchos: 59.4 + 40 + 22 + 24 + (28.5 * 4) = 259.4 mm (ancho imprimible de Letter horizontal)
        $this->Cell(59.4, 8, pdfTxt('  Médico / Especialista'), 1, 0, 'L', true);
        $this->Cell(40.0, 8, pdfTxt('  Especialidad'), 1, 0, 'L', true);
        $this->Cell(22.0, 8, pdfTxt('Total Citas'), 1, 0, 'C', true);
        $this->Cell(24.0, 8, pdfTxt('Citas Pagadas'), 1, 0, 'C', true);
        $this->Cell(28.5, 8, pdfTxt('Total Bruto  '), 1, 0, 'R', true);
        $this->Cell(28.5, 8, pdfTxt('Cobrado  '), 1, 0, 'R', true);
        $this->Cell(28.5, 8, pdfTxt('Monto ADH  '), 1, 0, 'R', true);
        $this->Cell(28.5, 8, pdfTxt('Monto Médico  '), 1, 1, 'R', true);
    }

    // Pie de Página
    function Footer(): void {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 8, pdfTxt('Página ' . $this->PageNo() . ' de {nb}'), 0, 0, 'C');
    }
}

// Crear instancia del PDF en orientación Horizontal (Landscape) - Formato Letter (Carta)
$pdf = new LiquidacionDocumentoPDF('L', 'mm', 'Letter');
$pdf->AliasNbPages();
$pdf->setTituloDocumento($tituloMes);
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

// Cuerpo de la tabla
$pdf->SetFont('Arial', '', 8.5);
$pdf->SetDrawColor(226, 232, 240);
$pdf->SetLineWidth(0.2);

$fill = false;
foreach ($liquidaciones as $l) {
    // Alternar sombreado de filas
    if ($fill) {
        $pdf->SetFillColor(248, 249, 250);
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }

    // 1. Nombre del Médico
    $pdf->SetFont('Arial', 'B', 8.5);
    $pdf->SetTextColor(29, 67, 83);
    $pdf->Cell(59.4, 7.2, pdfTxt(' ' . $l['medico']), 1, 0, 'L', true);

    // 2. Especialidad
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(90, 95, 100);
    $pdf->Cell(40.0, 7.2, pdfTxt(' ' . ($l['especialidad'] ?: 'General')), 1, 0, 'L', true);

    // 3. Total Citas
    $pdf->SetFont('Arial', '', 8.5);
    $pdf->SetTextColor(33, 37, 41);
    $pdf->Cell(22.0, 7.2, (string)$l['cantidad_citas'], 1, 0, 'C', true);

    // 4. Citas Pagadas
    $pdf->SetFont('Arial', 'B', 8.5);
    $pdf->SetTextColor(25, 135, 84); // verde
    $pdf->Cell(24.0, 7.2, (string)$l['citas_pagadas'], 1, 0, 'C', true);

    // 5. Total Bruto ($)
    $pdf->SetFont('Arial', '', 8.5);
    $pdf->SetTextColor(33, 37, 41);
    $pdf->Cell(28.5, 7.2, '$ ' . number_format((float)$l['total_bruto'], 2, '.', ',') . '  ', 1, 0, 'R', true);

    // 6. Monto Cobrado ($)
    $pdf->SetFont('Arial', 'B', 8.5);
    $pdf->SetTextColor(33, 37, 41);
    $pdf->Cell(28.5, 7.2, '$ ' . number_format((float)$l['monto_cobrado'], 2, '.', ',') . '  ', 1, 0, 'R', true);

    // 7. Monto ADH ($)
    $pdf->SetTextColor(79, 144, 165);
    $pdf->Cell(28.5, 7.2, '$ ' . number_format((float)$l['monto_adh'], 2, '.', ',') . '  ', 1, 0, 'R', true);

    // 8. Monto Médico ($)
    $pdf->SetTextColor(25, 135, 84);
    $pdf->Cell(28.5, 7.2, '$ ' . number_format((float)$l['monto_medico'], 2, '.', ',') . '  ', 1, 1, 'R', true);

    $fill = !$fill;
}

// Fila de Totales del Mes (Destacada)
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(29, 67, 83); // #1d4353
$pdf->SetTextColor(255, 255, 255);
$pdf->SetDrawColor(20, 50, 65);
$pdf->SetLineWidth(0.3);

// Fusión de las dos primeras columnas: 59.4 + 40 = 99.4 mm
$pdf->Cell(99.4, 8.5, pdfTxt('  TOTALES DEL MES:'), 1, 0, 'L', true);
$pdf->Cell(22.0, 8.5, (string)$totales['cantidad_citas'], 1, 0, 'C', true);
$pdf->Cell(24.0, 8.5, (string)$totales['citas_pagadas'], 1, 0, 'C', true);
$pdf->Cell(28.5, 8.5, '$ ' . number_format($totales['total_bruto'], 2, '.', ',') . '  ', 1, 0, 'R', true);
$pdf->Cell(28.5, 8.5, '$ ' . number_format($totales['monto_cobrado'], 2, '.', ',') . '  ', 1, 0, 'R', true);
$pdf->Cell(28.5, 8.5, '$ ' . number_format($totales['monto_adh'], 2, '.', ',' ) . '  ', 1, 0, 'R', true);
$pdf->Cell(28.5, 8.5, '$ ' . number_format($totales['monto_medico'], 2, '.', ',') . '  ', 1, 1, 'R', true);

// Limpiar buffer de salida si hubiese algo previo
if (ob_get_length()) {
    ob_end_clean();
}

$nombreArchivo = 'Liquidacion_' . str_replace('-', '_', $mes) . '.pdf';
$pdf->Output('I', $nombreArchivo);
exit;
