<?php
/**
 * Configuración Global de la Aplicación CMCO-Citas
 */
date_default_timezone_set('America/Caracas');

define('APP_NAME', 'CMCO - Control de Citas');
define('APP_ORG', 'Centro Médico Comunitario OscaryAnny');
define('BASE_URL', '/CMCO-Citas');

// Catálogo Oficial de Estados con Emojis y Clases de Bootstrap
const ESTADOS_CITAS = [
    'Agendada'      => ['emoji' => '📌', 'label' => 'Agendada',      'badge' => 'primary',   'color' => '#4f90a5'],
    'Sin confirmar' => ['emoji' => '📞', 'label' => 'Sin confirmar', 'badge' => 'warning',   'color' => '#ffc107'],
    'Confirmado'    => ['emoji' => '✔️', 'label' => 'Confirmado',    'badge' => 'info',      'color' => '#0dcaf0'],
    'Completado'    => ['emoji' => '✅', 'label' => 'Completado',    'badge' => 'success',   'color' => '#198754'],
    'No asistio'    => ['emoji' => '🚫', 'label' => 'No asistió',    'badge' => 'secondary', 'color' => '#6c757d'],
    'Pagado'        => ['emoji' => '💲', 'label' => 'Pagado',        'badge' => 'success',   'color' => '#20c997'],
    'Facturado'     => ['emoji' => '📦', 'label' => 'Facturado',     'badge' => 'dark',      'color' => '#6f42c1']
];

// Catálogo de Tipos de Cita y Beneficios
const TIPOS_CITAS = [
    'Particular'       => ['label' => 'Particular (Estándar 100%)',          'desc' => 'Paciente paga 100% (50% ADH / 50% Médico)'],
    'Empleado'         => ['label' => 'Empleado (Beneficio 100% CMCO)',      'desc' => 'Paciente paga $0 (ADH asume 50% médico)'],
    'Familiar'         => ['label' => 'Familiar Directo (50% Descuento)',   'desc' => 'Paciente paga 50% (va 100% al médico)'],
    'Participante'     => ['label' => 'Participante ADH (Beneficio)',       'desc' => 'Paciente paga $0 (ADH asume 50% médico)'],
    'Exonerado ADH'    => ['label' => 'Exonerado ADH (50% Descuento)',      'desc' => 'Paciente paga 50% médico (ADH 0%)'],
    'Exonerado Doctor' => ['label' => 'Exonerado Doctor (50% Descuento)',   'desc' => 'Paciente paga 50% ADH (Médico 0%)']
];

// Formas de Pago
const FORMAS_PAGO = [
    'Efectivo ($)'          => 'Efectivo en Dólares ($)',
    'Punto de venta'        => 'Punto de Venta (Bs)',
    'Pago Movil Mercantil'  => 'Pago Móvil Mercantil (Bs)',
    'Tran. Mercantil (Bs)'  => 'Transferencia Mercantil (Bs)',
    'Tran. BNC ($)'         => 'Transferencia BNC ($)',
    'Zelle'                 => 'Zelle ($)'
];

// Horarios de Atención (13 Bloques de 1 Hora)
const HORARIOS_ATENCION = [
    '08:00:00' => '08:00 AM',
    '09:00:00' => '09:00 AM',
    '10:00:00' => '10:00 AM',
    '11:00:00' => '11:00 AM',
    '12:00:00' => '12:00 PM',
    '13:00:00' => '01:00 PM',
    '14:00:00' => '02:00 PM',
    '15:00:00' => '03:00 PM',
    '16:00:00' => '04:00 PM',
    '17:00:00' => '05:00 PM',
    '18:00:00' => '06:00 PM',
    '19:00:00' => '07:00 PM',
    '20:00:00' => '08:00 PM'
];

/**
 * Función auxiliar para respuesta JSON
 */
function sendJsonResponse(bool $success, mixed $data = null, string $message = '', int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'data'    => $data,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
