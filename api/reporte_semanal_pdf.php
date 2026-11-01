<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

// Cargar librería FPDF
if (file_exists(__DIR__ . '/../assets/vendor/fpdf/fpdf.php')) {
    require_once __DIR__ . '/../assets/vendor/fpdf/fpdf.php';
} elseif (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    http_response_code(500);
    die("Error: No se encontró el componente de generación PDF (FPDF).");
}

$pdo = Database::getConnection();

// Parámetros de rango de fecha para la semana (Lunes a Domingo)
$inicio = $_GET['inicio'] ?? '';
$fin = $_GET['fin'] ?? '';

// Si no vienen los parámetros, calcular la semana actual
if (empty($inicio) || empty($fin)) {
    $hoy = new DateTime();
    $diaSemana = (int)$hoy->format('N'); // 1 = Lunes, 7 = Domingo
    $diffALunes = $diaSemana - 1;
    $lunes = clone $hoy;
    $lunes->modify("-{$diffALunes} days");
    $domingo = clone $lunes;
    $domingo->modify("+6 days");
    $inicio = $lunes->format('Y-m-d');
    $fin = $domingo->format('Y-m-d');
}

// Sanitizar formato YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio)) $inicio = date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fin)) $fin = date('Y-m-d');
if ($inicio > $fin) {
    $temp = $inicio;
    $inicio = $fin;
    $fin = $temp;
}

// Helper para convertir caracteres UTF-8 a ISO-8859-1 sin errores en PHP 8.2+
function pdfTxt(string $str): string {
    return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
}

// Formateador textual de rango de semana
function formatearRangoSemanaTexto(string $fini, string $ffin): string {
    $dias = [
        'Sunday' => 'Domingo', 'Monday' => 'Lunes', 'Tuesday' => 'Martes',
        'Wednesday' => 'Miércoles', 'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado'
    ];
    $meses = [
        '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
        '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
        '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
    ];
    
    $tsIni = strtotime($fini);
    $tsFin = strtotime($ffin);
    
    $diaSemIni = $dias[date('l', $tsIni)] ?? '';
    $diaNumIni = date('d', $tsIni);
    $mesNomIni = $meses[date('m', $tsIni)] ?? '';
    $anoIni = date('Y', $tsIni);
    
    $diaSemFin = $dias[date('l', $tsFin)] ?? '';
    $diaNumFin = date('d', $tsFin);
    $mesNomFin = $meses[date('m', $tsFin)] ?? '';
    $anoFin = date('Y', $tsFin);
    
    if ($anoIni === $anoFin) {
        if ($mesNomIni === $mesNomFin) {
            return "Semana del {$diaSemIni} {$diaNumIni} al {$diaSemFin} {$diaNumFin} de {$mesNomFin} de {$anoFin}";
        }
        return "Semana del {$diaSemIni} {$diaNumIni} de {$mesNomIni} al {$diaSemFin} {$diaNumFin} de {$mesNomFin} de {$anoFin}";
    }
    return "Semana del {$diaSemIni} {$diaNumIni} de {$mesNomIni} de {$anoIni} al {$diaSemFin} {$diaNumFin} de {$mesNomFin} de {$anoFin}";
}

// Clasificación de la moneda base del método de pago
function clasificarMonedaFormaPago(string $forma): string {
    $formaLower = mb_strtolower($forma);
    if (str_contains($formaLower, 'punto') || 
        str_contains($formaLower, 'pago movil') || 
        str_contains($formaLower, 'pago móvil') || 
        str_contains($formaLower, '(bs)') || 
        str_contains($formaLower, 'bolivar') || 
        str_contains($formaLower, 'bolívar')) {
        return 'Bs';
    }
    return 'USD';
}

// Consulta de pagos en el rango seleccionado
$sql = "
    SELECT 
        p.forma_pago,
        COUNT(p.id) AS total_transacciones,
        COALESCE(SUM(p.monto_usd), 0) AS total_usd,
        COALESCE(SUM(p.monto_bs), 0) AS total_bs
    FROM pagos_citas p
    JOIN citas c ON p.cita_id = c.id
    WHERE c.fecha BETWEEN :fini AND :ffin
      AND c.estado IN ('Pagado', 'Facturado')
    GROUP BY p.forma_pago
    ORDER BY total_usd DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':fini' => $inicio, ':ffin' => $fin]);
$filasMetodos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Consulta de detalle individual de pagos para la Sección 3
$sqlDetalle = "
    SELECT 
        c.fecha,
        COALESCE(pac.nombre, 'Sin Nombre') AS paciente_nombre,
        p.forma_pago,
        p.monto_usd,
        p.monto_bs,
        p.referencia
    FROM pagos_citas p
    JOIN citas c ON p.cita_id = c.id
    LEFT JOIN pacientes pac ON c.paciente_id = pac.id
    WHERE c.fecha BETWEEN :fini AND :ffin
      AND c.estado IN ('Pagado', 'Facturado')
    ORDER BY c.fecha ASC, c.hora ASC, p.id ASC
";
$stmtDetalle = $pdo->prepare($sqlDetalle);
$stmtDetalle->execute([':fini' => $inicio, ':ffin' => $fin]);
$pagosDetalle = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);

// Totales Generales
$granTotalTransacciones = 0;
$granTotalUsd = 0.0;
$granTotalBs = 0.0;

// Totales agrupados por Moneda (Tabla 2)
$resumenMoneda = [
    'Bs' => [
        'titulo'        => 'Pagos Recaudados en Bolívares (Bs.)',
        'instrumentos'  => 'Punto / Pago Móvil / Tran. Bs',
        'transacciones' => 0,
        'total_bs'      => 0.0,
        'total_usd'     => 0.0
    ],
    'USD' => [
        'titulo'        => 'Pagos Recaudados en Dólares ($ USD)',
        'instrumentos'  => 'Efectivo $ / Tran. BNC $ / Zelle',
        'transacciones' => 0,
        'total_bs'      => 0.0,
        'total_usd'     => 0.0
    ]
];

foreach ($filasMetodos as $f) {
    $trans = (int)$f['total_transacciones'];
    $usd = (float)$f['total_usd'];
    $bs = (float)$f['total_bs'];
    
    $granTotalTransacciones += $trans;
    $granTotalUsd += $usd;
    $granTotalBs += $bs;

    $tipoMoneda = clasificarMonedaFormaPago($f['forma_pago']);
    $resumenMoneda[$tipoMoneda]['transacciones'] += $trans;
    $resumenMoneda[$tipoMoneda]['total_bs'] += $bs;
    $resumenMoneda[$tipoMoneda]['total_usd'] += $usd;
}

$tituloSemana = formatearRangoSemanaTexto($inicio, $fin);

/**
 * Clase para el Reporte Semanal en PDF (Portrait - Formato Carta)
 */
class ReporteSemanalDocumentoPDF extends FPDF {
    protected string $periodoTexto;
    protected string $rangoFechas;

    public function setPeriodo(string $periodo, string $rango): void {
        $this->periodoTexto = $periodo;
        $this->rangoFechas = $rango;
    }

    function Header(): void {
        // Logo de la institución
        $logoPath = dirname(__DIR__) . '/assets/img/logo.png';
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 10, 10, 24);
        }

        // Título del Reporte
        $this->SetXY(38, 10);
        $this->SetFont('Arial', 'B', 15);
        $this->SetTextColor(29, 67, 83); // #1d4353
        $this->Cell(115, 6, pdfTxt('REPORTE SEMANAL DE INGRESOS'), 0, 1, 'L');

        // Subtítulo institucional
        $this->SetFont('Arial', '', 8.5);
        $this->SetTextColor(108, 117, 125);
        $this->SetX(38);
        $this->Cell(115, 4.5, pdfTxt('Centro Médico Comunitario OscaryAnny (CMCO / ADH)'), 0, 1, 'L');

        // Período
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(79, 144, 165); // #4f90a5
        $this->SetX(38);
        $this->Cell(115, 5, pdfTxt($this->periodoTexto), 0, 1, 'L');

        // Fecha y hora de emisión (arriba a la derecha)
        $this->SetXY(145, 11);
        $this->SetFont('Arial', 'I', 7.5);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(60, 4, pdfTxt('Emisión: ' . date('d/m/Y h:i A')), 0, 1, 'R');
        $this->SetX(145);
        $this->Cell(60, 4, pdfTxt('Rango: ' . $this->rangoFechas), 0, 1, 'R');

        // Línea divisoria decorativa
        $this->SetY(28);
        $this->SetDrawColor(220, 225, 230);
        $this->SetLineWidth(0.4);
        $this->Line(10, 28, 205, 28);

        $this->SetY(32);
    }

    function Footer(): void {
        $this->SetY(-14);
        $this->SetDrawColor(220, 225, 230);
        $this->SetLineWidth(0.3);
        $this->Line(10, $this->GetY(), 205, $this->GetY());

        $this->SetFont('Arial', 'I', 7.5);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(130, 8, pdfTxt('CMCO-Citas - Sistema de Control y Facturación Médica | Documento Oficial'), 0, 0, 'L');
        $this->Cell(65, 8, pdfTxt('Página ' . $this->PageNo() . ' de {nb}'), 0, 0, 'R');
    }
}

// Inicializar PDF (Portrait, mm, Letter)
$pdf = new ReporteSemanalDocumentoPDF('P', 'mm', 'Letter');
$pdf->AliasNbPages();
$pdf->setPeriodo($tituloSemana, date('d/m/Y', strtotime($inicio)) . ' al ' . date('d/m/Y', strtotime($fin)));
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

// -------------------------------------------------------------
// SECCIÓN 1: RESUMEN EJECUTIVO (KPIs)
// -------------------------------------------------------------
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(29, 67, 83);
$pdf->Cell(195, 6, pdfTxt('1. DESGLOSE GENERAL POR MÉTODO DE PAGO'), 0, 1, 'L');
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(195, 4, pdfTxt('Detalle de transacciones e importes percibidos en caja clasificados por método de pago.'), 0, 1, 'L');
$pdf->Ln(2);

// Cabecera de Tabla 1
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetFillColor(79, 144, 165); // #4f90a5
$pdf->SetTextColor(255, 255, 255);
$pdf->SetDrawColor(56, 115, 135);
$pdf->SetLineWidth(0.3);

// Anchos: 80 + 40 + 30 + 45 = 195 mm
$pdf->Cell(80, 7.5, pdfTxt('  Método de Pago'), 1, 0, 'L', true);
$pdf->Cell(40, 7.5, pdfTxt('Moneda Base'), 1, 0, 'C', true);
$pdf->Cell(30, 7.5, pdfTxt('N° Transac.'), 1, 0, 'C', true);
$pdf->Cell(45, 7.5, pdfTxt('Total  '), 1, 1, 'R', true);

// Cuerpo de Tabla 1
$pdf->SetFont('Arial', '', 8.5);
$pdf->SetDrawColor(226, 232, 240);
$pdf->SetLineWidth(0.2);

if (empty($filasMetodos)) {
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->Cell(195, 10, pdfTxt('No se registraron cobros ni pagos de citas en el período seleccionado.'), 1, 1, 'C', true);
} else {
    $fill = false;
    foreach ($filasMetodos as $fila) {
        if ($fill) {
            $pdf->SetFillColor(248, 249, 250);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }

        $monedaBase = clasificarMonedaFormaPago($fila['forma_pago']);
        $monedaEtiqueta = ($monedaBase === 'Bs') ? 'Bolívares (Bs.)' : 'Dólares ($ USD)';

        // Nombre método
        $pdf->SetFont('Arial', 'B', 8.5);
        $pdf->SetTextColor(29, 67, 83);
        $pdf->Cell(80, 7.5, pdfTxt('  ' . $fila['forma_pago']), 1, 0, 'L', true);

        // Moneda
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(100, 105, 110);
        $pdf->Cell(40, 7.5, pdfTxt($monedaEtiqueta), 1, 0, 'C', true);

        // Transacciones
        $pdf->SetFont('Arial', 'B', 8.5);
        $pdf->SetTextColor(40, 40, 40);
        $pdf->Cell(30, 7.5, (string)$fila['total_transacciones'], 1, 0, 'C', true);

        // Total según la moneda de la forma de pago
        $pdf->SetFont('Arial', 'B', 8.5);
        if ($monedaBase === 'Bs') {
            $pdf->SetTextColor(30, 30, 30);
            $textoMonto = number_format((float)$fila['total_bs'], 2, ',', '.') . ' Bs.  ';
        } else {
            $pdf->SetTextColor(29, 67, 83);
            $textoMonto = '$ ' . number_format((float)$fila['total_usd'], 2, '.', ',') . '  ';
        }
        $pdf->Cell(45, 7.5, $textoMonto, 1, 1, 'R', true);

        $fill = !$fill;
    }
}

$pdf->Ln(8);

// -------------------------------------------------------------
// SECCIÓN 2: CONSOLIDADO POR TIPO DE MONEDA
// -------------------------------------------------------------
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(29, 67, 83);
$pdf->Cell(195, 6, pdfTxt('2. RESUMEN CONSOLIDADO POR TIPO DE MONEDA'), 0, 1, 'L');
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(195, 4, pdfTxt('Totalización de ingresos agrupada según la moneda original de recepción (Bolívares y Dólares).'), 0, 1, 'L');
$pdf->Ln(2);

// Cabecera de Tabla 2
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetFillColor(41, 128, 150); // Un tono ligeramente más contrastado
$pdf->SetTextColor(255, 255, 255);
$pdf->SetDrawColor(29, 67, 83);
$pdf->SetLineWidth(0.3);

// Anchos: 80 + 40 + 30 + 45 = 195 mm
$pdf->Cell(80, 7.5, pdfTxt('  Tipo de Moneda / Clasificación'), 1, 0, 'L', true);
$pdf->Cell(40, 7.5, pdfTxt('Instrumentos'), 1, 0, 'C', true);
$pdf->Cell(30, 7.5, pdfTxt('N° Transac.'), 1, 0, 'C', true);
$pdf->Cell(45, 7.5, pdfTxt('Total  '), 1, 1, 'R', true);

// Fila 1: Métodos en Bolívares (Bs.)
$pdf->SetDrawColor(226, 232, 240);
$pdf->SetLineWidth(0.2);
$pdf->SetFillColor(255, 255, 255);

$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(29, 67, 83);
$pdf->Cell(80, 8, pdfTxt('  ' . $resumenMoneda['Bs']['titulo']), 1, 0, 'L', true);

$pdf->SetFont('Arial', '', 7.5);
$pdf->SetTextColor(100, 105, 110);
$pdf->Cell(40, 8, pdfTxt($resumenMoneda['Bs']['instrumentos']), 1, 0, 'C', true);

$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(40, 40, 40);
$pdf->Cell(30, 8, (string)$resumenMoneda['Bs']['transacciones'], 1, 0, 'C', true);

$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(30, 30, 30);
$pdf->Cell(45, 8, number_format($resumenMoneda['Bs']['total_bs'], 2, ',', '.') . ' Bs.  ', 1, 1, 'R', true);

// Fila 2: Métodos en Dólares ($ USD)
$pdf->SetFillColor(248, 249, 250);

$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(29, 67, 83);
$pdf->Cell(80, 8, pdfTxt('  ' . $resumenMoneda['USD']['titulo']), 1, 0, 'L', true);

$pdf->SetFont('Arial', '', 7.5);
$pdf->SetTextColor(100, 105, 110);
$pdf->Cell(40, 8, pdfTxt($resumenMoneda['USD']['instrumentos']), 1, 0, 'C', true);

$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(40, 40, 40);
$pdf->Cell(30, 8, (string)$resumenMoneda['USD']['transacciones'], 1, 0, 'C', true);

$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(29, 67, 83);
$pdf->Cell(45, 8, '$ ' . number_format($resumenMoneda['USD']['total_usd'], 2, '.', ',') . '  ', 1, 1, 'R', true);

// -------------------------------------------------------------
// SECCIÓN 3: DETALLE DE PAGOS DE LA SEMANA
// -------------------------------------------------------------
$pdf->Ln(8);

// Si queda poco espacio para arrancar la sección 3 con su cabecera y primeras filas, iniciar en nueva página
if ($pdf->GetY() > 215) {
    $pdf->AddPage();
}

$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(29, 67, 83);
$pdf->Cell(195, 6, pdfTxt('3. DETALLE DE PAGOS DE LA SEMANA'), 0, 1, 'L');
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(195, 4, pdfTxt('Relación pormenorizada de cada uno de los cobros ejecutados durante la semana seleccionada.'), 0, 1, 'L');
$pdf->Ln(2);

function imprimirCabeceraTablaDetalle(FPDF $pdf): void {
    $pdf->SetFont('Arial', 'B', 8.5);
    $pdf->SetFillColor(79, 144, 165); // #4f90a5
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetDrawColor(56, 115, 135);
    $pdf->SetLineWidth(0.3);

    // Suma de anchos: 25 + 75 + 55 + 40 = 195 mm
    $pdf->Cell(25, 7.5, pdfTxt('Fecha'), 1, 0, 'C', true);
    $pdf->Cell(75, 7.5, pdfTxt('  Nombre del Paciente'), 1, 0, 'L', true);
    $pdf->Cell(55, 7.5, pdfTxt('  Método de Pago'), 1, 0, 'L', true);
    $pdf->Cell(40, 7.5, pdfTxt('Monto  '), 1, 1, 'R', true);
}

imprimirCabeceraTablaDetalle($pdf);

$pdf->SetFont('Arial', '', 8.5);
$pdf->SetDrawColor(226, 232, 240);
$pdf->SetLineWidth(0.2);

if (empty($pagosDetalle)) {
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->Cell(195, 10, pdfTxt('No se registraron transacciones individuales en este período.'), 1, 1, 'C', true);
} else {
    $fill = false;
    foreach ($pagosDetalle as $d) {
        if ($pdf->GetY() > 255) {
            $pdf->AddPage();
            imprimirCabeceraTablaDetalle($pdf);
            $pdf->SetFont('Arial', '', 8.5);
            $pdf->SetDrawColor(226, 232, 240);
            $pdf->SetLineWidth(0.2);
        }

        if ($fill) {
            $pdf->SetFillColor(248, 249, 250);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }

        $monedaDet = clasificarMonedaFormaPago($d['forma_pago']);
        $fechaFmt = date('d/m/Y', strtotime($d['fecha']));
        $pacienteStr = mb_substr($d['paciente_nombre'], 0, 42);

        // 1. Fecha
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(70, 70, 70);
        $pdf->Cell(25, 7, $fechaFmt, 1, 0, 'C', true);

        // 2. Nombre del Paciente
        $pdf->SetFont('Arial', 'B', 8.5);
        $pdf->SetTextColor(29, 67, 83);
        $pdf->Cell(75, 7, pdfTxt('  ' . $pacienteStr), 1, 0, 'L', true);

        // 3. Método de Pago
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(80, 85, 90);
        $pdf->Cell(55, 7, pdfTxt('  ' . $d['forma_pago']), 1, 0, 'L', true);

        // 4. Monto
        $pdf->SetFont('Arial', 'B', 8.5);
        if ($monedaDet === 'Bs') {
            $pdf->SetTextColor(30, 30, 30);
            $montoStr = number_format((float)$d['monto_bs'], 2, ',', '.') . ' Bs.  ';
        } else {
            $pdf->SetTextColor(29, 67, 83);
            $montoStr = '$ ' . number_format((float)$d['monto_usd'], 2, '.', ',') . '  ';
        }
        $pdf->Cell(40, 7, $montoStr, 1, 1, 'R', true);

        $fill = !$fill;
    }
}

// Enviar PDF al navegador
$nombreArchivo = 'Reporte_Semanal_Pagos_' . $inicio . '_al_' . $fin . '.pdf';
$pdf->Output('I', $nombreArchivo);
