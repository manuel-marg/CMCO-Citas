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

// Helper para convertir caracteres UTF-8 a ISO-8859-1 sin errores en PHP 8.2+
function pdfTxt(string $str): string {
    return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
}

// Formatear fecha YYYY-MM-DD a DD/MM/YYYY
function formatearFechaPdf(string $fecha): string {
    $partes = explode('-', $fecha);
    if (count($partes) === 3) {
        return "{$partes[2]}/{$partes[1]}/{$partes[0]}";
    }
    return $fecha;
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

// 1. Beneficio de Empleados
$stmtEmp = $pdo->prepare("
    SELECT c.*, p.nombre AS paciente_nombre, p.cedula, p.telefono, m.nombre AS medico_nombre
    FROM citas c
    INNER JOIN pacientes p ON p.id = c.paciente_id
    INNER JOIN medicos m ON m.id = c.medico_id
    WHERE c.tipo = 'Empleado' 
      AND c.estado IN ('Pagado', 'Facturado')
      AND c.fecha BETWEEN :fini AND :ffin
    ORDER BY c.fecha ASC, c.hora ASC
");
$stmtEmp->execute([':fini' => $fecha_inicio, ':ffin' => $fecha_fin]);
$empleados = $stmtEmp->fetchAll(PDO::FETCH_ASSOC);

// 2. Beneficio de Familiares Directos (50% desc.)
$stmtFam = $pdo->prepare("
    SELECT c.*, p.nombre AS paciente_nombre, p.cedula, p.telefono, p.representante, m.nombre AS medico_nombre
    FROM citas c
    INNER JOIN pacientes p ON p.id = c.paciente_id
    INNER JOIN medicos m ON m.id = c.medico_id
    WHERE c.tipo = 'Familiar' 
      AND c.estado IN ('Pagado', 'Facturado')
      AND c.fecha BETWEEN :fini AND :ffin
    ORDER BY c.fecha ASC, c.hora ASC
");
$stmtFam->execute([':fini' => $fecha_inicio, ':ffin' => $fecha_fin]);
$familiares = $stmtFam->fetchAll(PDO::FETCH_ASSOC);

// 3. Beneficio de Participantes ADH
$stmtPart = $pdo->prepare("
    SELECT c.*, p.nombre AS paciente_nombre, p.cedula, p.telefono, m.nombre AS medico_nombre
    FROM citas c
    INNER JOIN pacientes p ON p.id = c.paciente_id
    INNER JOIN medicos m ON m.id = c.medico_id
    WHERE c.tipo = 'Participante' 
      AND c.estado IN ('Pagado', 'Facturado')
      AND c.fecha BETWEEN :fini AND :ffin
    ORDER BY c.fecha ASC, c.hora ASC
");
$stmtPart->execute([':fini' => $fecha_inicio, ':ffin' => $fecha_fin]);
$participantes = $stmtPart->fetchAll(PDO::FETCH_ASSOC);

// 4. Exoneraciones (ADH y Doctor)
$stmtExon = $pdo->prepare("
    SELECT c.*, p.nombre AS paciente_nombre, p.cedula, p.telefono, m.nombre AS medico_nombre
    FROM citas c
    INNER JOIN pacientes p ON p.id = c.paciente_id
    INNER JOIN medicos m ON m.id = c.medico_id
    WHERE c.tipo IN ('Exonerado ADH', 'Exonerado Doctor')
      AND c.estado IN ('Pagado', 'Facturado')
      AND c.fecha BETWEEN :fini AND :ffin
    ORDER BY c.fecha ASC, c.hora ASC
");
$stmtExon->execute([':fini' => $fecha_inicio, ':ffin' => $fecha_fin]);
$exonerados = $stmtExon->fetchAll(PDO::FETCH_ASSOC);

/**
 * Clase para el Documento PDF de Control de Beneficios en Orientación Horizontal (Landscape)
 */
class BeneficiosDocumentoPDF extends FPDF {
    protected string $tituloDocumento;

    public function setTituloDocumento(string $titulo): void {
        $this->tituloDocumento = $titulo;
    }

    // Cabecera de Página (automática al crear cada página)
    function Header(): void {
        // Logo de la institución
        $logoPath = dirname(__DIR__) . '/assets/img/logo.png';
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 10, 8, 25);
        }

        // Título del Documento
        $this->SetXY(38, 9);
        $this->SetFont('Arial', 'B', 15);
        $this->SetTextColor(29, 67, 83); // #1d4353
        $this->Cell(140, 6, pdfTxt('Control de Beneficios y Exoneraciones'), 0, 1, 'L');

        // Subtítulo del Mes
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(79, 144, 165); // #4f90a5
        $this->SetX(38);
        $this->Cell(140, 5, pdfTxt($this->tituloDocumento), 0, 1, 'L');

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

        // Espacio para arrancar el contenido debajo del header
        $this->SetY(28);
    }

    // Pie de Página
    function Footer(): void {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 8, pdfTxt('Página ' . $this->PageNo() . ' de {nb}'), 0, 0, 'C');
    }

    // Verificar si queda suficiente espacio en la página actual antes de imprimir un bloque
    public function asegurarEspacio(float $altoRequerido): void {
        if ($this->GetY() + $altoRequerido > ($this->h - 18)) {
            $this->AddPage();
        }
    }

    // Imprimir Título de Sección con barra elegante
    public function imprimirTituloSeccion(string $titulo, int $cantidad, float $totalMonto): void {
        $this->asegurarEspacio(18);
        $this->SetFont('Arial', 'B', 9.5);
        $this->SetFillColor(235, 244, 248); // Fondo suave institucional
        $this->SetTextColor(29, 67, 83);     // Texto azul oscuro
        $this->SetDrawColor(180, 205, 215);
        $this->SetLineWidth(0.3);

        $texto = "  " . $titulo . " (" . $cantidad . " registros" . ($totalMonto > 0 ? " - Total: $" . number_format($totalMonto, 2) : "") . ")";
        $this->Cell(259.4, 7, pdfTxt($texto), 1, 1, 'L', true);
    }
}

// Crear instancia del PDF en orientación Horizontal (Landscape) - Formato Letter (Carta)
$pdf = new BeneficiosDocumentoPDF('L', 'mm', 'Letter');
$pdf->AliasNbPages();
$pdf->setTituloDocumento($tituloMes);
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

// =========================================================================
// 1. TABLA: EMPLEADOS DEL MES
// =========================================================================
$totalEmp = array_sum(array_map(fn($c) => (float)$c['precio_usd'], $empleados));
$pdf->imprimirTituloSeccion('1. EMPLEADOS DEL MES', count($empleados), $totalEmp);

// Cabecera de la tabla
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetFillColor(79, 144, 165);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetDrawColor(56, 115, 135);
$pdf->SetLineWidth(0.3);

// Anchos suman 259.4 mm
$pdf->Cell(24.0, 7, pdfTxt('Fecha'), 1, 0, 'C', true);
$pdf->Cell(68.0, 7, pdfTxt('Nombre del Empleado (Paciente)'), 1, 0, 'L', true);
$pdf->Cell(28.0, 7, pdfTxt('Cédula'), 1, 0, 'C', true);
$pdf->Cell(32.0, 7, pdfTxt('Teléfono'), 1, 0, 'C', true);
$pdf->Cell(60.0, 7, pdfTxt('Médico Tratante'), 1, 0, 'L', true);
$pdf->Cell(24.0, 7, pdfTxt('Monto ($)'), 1, 0, 'R', true);
$pdf->Cell(23.4, 7, pdfTxt('Estado'), 1, 1, 'C', true);

$pdf->SetDrawColor(226, 232, 240);
$pdf->SetLineWidth(0.2);

if (empty($empleados)) {
    $pdf->SetFont('Arial', 'I', 8.5);
    $pdf->SetTextColor(108, 117, 125);
    $pdf->Cell(259.4, 7, pdfTxt('No se registraron consultas de empleados en este período.'), 1, 1, 'C');
} else {
    $fill = false;
    foreach ($empleados as $c) {
        $pdf->asegurarEspacio(7);
        $pdf->SetFillColor($fill ? 248 : 255, $fill ? 249 : 255, $fill ? 250 : 255);
        $pdf->SetTextColor(33, 37, 41);

        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(24.0, 6.5, formatearFechaPdf($c['fecha']), 1, 0, 'C', true);

        $pdf->SetFont('Arial', 'B', 8.2);
        $pdf->SetTextColor(29, 67, 83);
        $pdf->Cell(68.0, 6.5, pdfTxt(' ' . $c['paciente_nombre']), 1, 0, 'L', true);

        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(33, 37, 41);
        $pdf->Cell(28.0, 6.5, pdfTxt($c['cedula'] ?: '-'), 1, 0, 'C', true);
        $pdf->Cell(32.0, 6.5, pdfTxt($c['telefono'] ?: '-'), 1, 0, 'C', true);
        $pdf->Cell(60.0, 6.5, pdfTxt(' ' . $c['medico_nombre']), 1, 0, 'L', true);

        $pdf->SetFont('Arial', 'B', 8.2);
        $pdf->Cell(24.0, 6.5, '$ ' . number_format((float)$c['precio_usd'], 2) . ' ', 1, 0, 'R', true);

        $pdf->SetFont('Arial', '', 7.8);
        $pdf->SetTextColor(25, 135, 84);
        $pdf->Cell(23.4, 6.5, pdfTxt($c['estado']), 1, 1, 'C', true);

        $fill = !$fill;
    }
}

$pdf->Ln(5);

// =========================================================================
// 2. TABLA: FAMILIAR DIRECTO (50% DESCUENTO)
// =========================================================================
$totalFam = array_sum(array_map(fn($c) => (float)$c['precio_usd'], $familiares));
$pdf->imprimirTituloSeccion('2. FAMILIARES DIRECTOS (50% DESCUENTO)', count($familiares), $totalFam);

// Cabecera
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetFillColor(79, 144, 165);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetDrawColor(56, 115, 135);
$pdf->SetLineWidth(0.3);

// Anchos suman 259.4 mm (8 columnas)
$pdf->Cell(22.0, 7, pdfTxt('Fecha'), 1, 0, 'C', true);
$pdf->Cell(50.0, 7, pdfTxt('Paciente Familiar'), 1, 0, 'L', true);
$pdf->Cell(45.0, 7, pdfTxt('Personal ADH / Titular'), 1, 0, 'L', true);
$pdf->Cell(26.0, 7, pdfTxt('Cédula'), 1, 0, 'C', true);
$pdf->Cell(28.0, 7, pdfTxt('Teléfono'), 1, 0, 'C', true);
$pdf->Cell(45.0, 7, pdfTxt('Médico Tratante'), 1, 0, 'L', true);
$pdf->Cell(22.0, 7, pdfTxt('Monto ($)'), 1, 0, 'R', true);
$pdf->Cell(21.4, 7, pdfTxt('Estado'), 1, 1, 'C', true);

$pdf->SetDrawColor(226, 232, 240);
$pdf->SetLineWidth(0.2);

if (empty($familiares)) {
    $pdf->SetFont('Arial', 'I', 8.5);
    $pdf->SetTextColor(108, 117, 125);
    $pdf->Cell(259.4, 7, pdfTxt('No se registraron consultas de familiares directos en este período.'), 1, 1, 'C');
} else {
    $fill = false;
    foreach ($familiares as $c) {
        $pdf->asegurarEspacio(7);
        $pdf->SetFillColor($fill ? 248 : 255, $fill ? 249 : 255, $fill ? 250 : 255);
        $pdf->SetTextColor(33, 37, 41);

        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(22.0, 6.5, formatearFechaPdf($c['fecha']), 1, 0, 'C', true);

        $pdf->SetFont('Arial', 'B', 8.2);
        $pdf->SetTextColor(29, 67, 83);
        $pdf->Cell(50.0, 6.5, pdfTxt(' ' . $c['paciente_nombre']), 1, 0, 'L', true);

        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(50, 50, 50);
        $titular = $c['representante'] ?: ($c['factura_o_empleado'] ?: '-');
        $pdf->Cell(45.0, 6.5, pdfTxt(' ' . $titular), 1, 0, 'L', true);

        $pdf->Cell(26.0, 6.5, pdfTxt($c['cedula'] ?: '-'), 1, 0, 'C', true);
        $pdf->Cell(28.0, 6.5, pdfTxt($c['telefono'] ?: '-'), 1, 0, 'C', true);
        $pdf->Cell(45.0, 6.5, pdfTxt(' ' . $c['medico_nombre']), 1, 0, 'L', true);

        $pdf->SetFont('Arial', 'B', 8.2);
        $pdf->Cell(22.0, 6.5, '$ ' . number_format((float)$c['precio_usd'], 2) . ' ', 1, 0, 'R', true);

        $pdf->SetFont('Arial', '', 7.8);
        $pdf->SetTextColor(25, 135, 84);
        $pdf->Cell(21.4, 6.5, pdfTxt($c['estado']), 1, 1, 'C', true);

        $fill = !$fill;
    }
}

$pdf->Ln(5);

// =========================================================================
// 3. TABLA: PARTICIPANTES ADH
// =========================================================================
$totalPart = array_sum(array_map(fn($c) => (float)$c['precio_usd'], $participantes));
$pdf->imprimirTituloSeccion('3. PARTICIPANTES ADH', count($participantes), $totalPart);

// Cabecera
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetFillColor(79, 144, 165);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetDrawColor(56, 115, 135);
$pdf->SetLineWidth(0.3);

// Anchos suman 259.4 mm (7 columnas)
$pdf->Cell(24.0, 7, pdfTxt('Fecha'), 1, 0, 'C', true);
$pdf->Cell(68.0, 7, pdfTxt('Participante ADH'), 1, 0, 'L', true);
$pdf->Cell(28.0, 7, pdfTxt('Cédula'), 1, 0, 'C', true);
$pdf->Cell(32.0, 7, pdfTxt('Teléfono'), 1, 0, 'C', true);
$pdf->Cell(60.0, 7, pdfTxt('Médico Tratante'), 1, 0, 'L', true);
$pdf->Cell(24.0, 7, pdfTxt('Monto ($)'), 1, 0, 'R', true);
$pdf->Cell(23.4, 7, pdfTxt('Estado'), 1, 1, 'C', true);

$pdf->SetDrawColor(226, 232, 240);
$pdf->SetLineWidth(0.2);

if (empty($participantes)) {
    $pdf->SetFont('Arial', 'I', 8.5);
    $pdf->SetTextColor(108, 117, 125);
    $pdf->Cell(259.4, 7, pdfTxt('No se registraron consultas de participantes en este período.'), 1, 1, 'C');
} else {
    $fill = false;
    foreach ($participantes as $c) {
        $pdf->asegurarEspacio(7);
        $pdf->SetFillColor($fill ? 248 : 255, $fill ? 249 : 255, $fill ? 250 : 255);
        $pdf->SetTextColor(33, 37, 41);

        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(24.0, 6.5, formatearFechaPdf($c['fecha']), 1, 0, 'C', true);

        $pdf->SetFont('Arial', 'B', 8.2);
        $pdf->SetTextColor(29, 67, 83);
        $pdf->Cell(68.0, 6.5, pdfTxt(' ' . $c['paciente_nombre']), 1, 0, 'L', true);

        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(33, 37, 41);
        $pdf->Cell(28.0, 6.5, pdfTxt($c['cedula'] ?: '-'), 1, 0, 'C', true);
        $pdf->Cell(32.0, 6.5, pdfTxt($c['telefono'] ?: '-'), 1, 0, 'C', true);
        $pdf->Cell(60.0, 6.5, pdfTxt(' ' . $c['medico_nombre']), 1, 0, 'L', true);

        $pdf->SetFont('Arial', 'B', 8.2);
        $pdf->Cell(24.0, 6.5, '$ ' . number_format((float)$c['precio_usd'], 2) . ' ', 1, 0, 'R', true);

        $pdf->SetFont('Arial', '', 7.8);
        $pdf->SetTextColor(25, 135, 84);
        $pdf->Cell(23.4, 6.5, pdfTxt($c['estado']), 1, 1, 'C', true);

        $fill = !$fill;
    }
}

$pdf->Ln(5);

// =========================================================================
// 4. TABLA: EXONERADOS (ADH Y MÉDICO)
// =========================================================================
$totalExon = array_sum(array_map(fn($c) => (float)$c['precio_usd'], $exonerados));
$pdf->imprimirTituloSeccion('4. EXONERADOS (ADH Y MÉDICO)', count($exonerados), $totalExon);

// Cabecera
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetFillColor(79, 144, 165);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetDrawColor(56, 115, 135);
$pdf->SetLineWidth(0.3);

// Anchos suman 259.4 mm (7 columnas)
$pdf->Cell(24.0, 7, pdfTxt('Fecha'), 1, 0, 'C', true);
$pdf->Cell(65.0, 7, pdfTxt('Paciente'), 1, 0, 'L', true);
$pdf->Cell(38.0, 7, pdfTxt('Tipo Exoneración'), 1, 0, 'C', true);
$pdf->Cell(28.0, 7, pdfTxt('Cédula'), 1, 0, 'C', true);
$pdf->Cell(58.0, 7, pdfTxt('Médico Tratante'), 1, 0, 'L', true);
$pdf->Cell(24.0, 7, pdfTxt('Monto ($)'), 1, 0, 'R', true);
$pdf->Cell(22.4, 7, pdfTxt('Estado'), 1, 1, 'C', true);

$pdf->SetDrawColor(226, 232, 240);
$pdf->SetLineWidth(0.2);

if (empty($exonerados)) {
    $pdf->SetFont('Arial', 'I', 8.5);
    $pdf->SetTextColor(108, 117, 125);
    $pdf->Cell(259.4, 7, pdfTxt('No se registraron consultas exoneradas en este período.'), 1, 1, 'C');
} else {
    $fill = false;
    foreach ($exonerados as $c) {
        $pdf->asegurarEspacio(7);
        $pdf->SetFillColor($fill ? 248 : 255, $fill ? 249 : 255, $fill ? 250 : 255);
        $pdf->SetTextColor(33, 37, 41);

        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(24.0, 6.5, formatearFechaPdf($c['fecha']), 1, 0, 'C', true);

        $pdf->SetFont('Arial', 'B', 8.2);
        $pdf->SetTextColor(29, 67, 83);
        $pdf->Cell(65.0, 6.5, pdfTxt(' ' . $c['paciente_nombre']), 1, 0, 'L', true);

        $pdf->SetFont('Arial', 'B', 7.8);
        $pdf->SetTextColor(133, 100, 4); // tono ámbar / advertencia
        $pdf->Cell(38.0, 6.5, pdfTxt($c['tipo']), 1, 0, 'C', true);

        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(33, 37, 41);
        $pdf->Cell(28.0, 6.5, pdfTxt($c['cedula'] ?: '-'), 1, 0, 'C', true);
        $pdf->Cell(58.0, 6.5, pdfTxt(' ' . $c['medico_nombre']), 1, 0, 'L', true);

        $pdf->SetFont('Arial', 'B', 8.2);
        $pdf->Cell(24.0, 6.5, '$ ' . number_format((float)$c['precio_usd'], 2) . ' ', 1, 0, 'R', true);

        $pdf->SetFont('Arial', '', 7.8);
        $pdf->SetTextColor(25, 135, 84);
        $pdf->Cell(22.4, 6.5, pdfTxt($c['estado']), 1, 1, 'C', true);

        $fill = !$fill;
    }
}

// =========================================================================
// TOTAL GENERAL CONSOLIDADO
// =========================================================================
$pdf->asegurarEspacio(15);
$pdf->Ln(6);
$totalGeneralConsultas = count($empleados) + count($familiares) + count($participantes) + count($exonerados);
$totalGeneralMonto = $totalEmp + $totalFam + $totalPart + $totalExon;

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(29, 67, 83); // #1d4353
$pdf->SetTextColor(255, 255, 255);
$pdf->SetDrawColor(20, 50, 65);
$pdf->SetLineWidth(0.3);

$pdf->Cell(170.0, 8.5, pdfTxt('  TOTAL GENERAL DE CONSULTAS CON BENEFICIO / EXONERACIÓN DEL MES:'), 1, 0, 'L', true);
$pdf->Cell(40.0, 8.5, pdfTxt($totalGeneralConsultas . ' Citas'), 1, 0, 'C', true);
$pdf->Cell(49.4, 8.5, '$ ' . number_format($totalGeneralMonto, 2) . '  ', 1, 1, 'R', true);

// Limpiar buffer de salida si hubiese algo previo
if (ob_get_length()) {
    ob_end_clean();
}

$nombreArchivo = 'Beneficios_' . str_replace('-', '_', $mes) . '.pdf';
$pdf->Output('I', $nombreArchivo);
exit;
