<?php
/**
 * CMCO-Citas - Sistema de Control de Citas y Liquidación Médica
 * Centro Médico Comunitario OscaryAnny / ADH
 * 
 * Punto de entrada principal de la aplicación.
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

// Verificar conexión a Base de Datos
try {
    $pdo = Database::getConnection();
} catch (Exception $e) {
    die("Error crítico al inicializar la base de datos: " . $e->getMessage());
}

// Cargar Componentes de la Interfaz
require_once __DIR__ . '/views/layouts/header.php';
require_once __DIR__ . '/views/layouts/navbar.php';

echo '<main class="pb-5">';
require_once __DIR__ . '/views/agenda/index.php';
require_once __DIR__ . '/views/liquidacion/index.php';
require_once __DIR__ . '/views/beneficios/index.php';
require_once __DIR__ . '/views/pacientes/index.php';
require_once __DIR__ . '/views/medicos/index.php';
echo '</main>';

require_once __DIR__ . '/views/layouts/footer.php';
