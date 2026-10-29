<?php
/**
 * CMCO-Citas - Script de Importación del Catálogo de Pacientes
 * Centro Médico Comunitario OscaryAnny (CMCO / ADH)
 * 
 * Carga todo el catálogo de pacientes desde database/pacientes.sql
 * utilizando conexión PDO con codificación utf8mb4 nativa.
 * 
 * Uso desde consola:
 * C:\xampp\php\php.exe database/import_pacientes.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$pdo = Database::getConnection();

echo "==========================================================" . PHP_EOL;
echo "  CMCO-Citas - Importación de Pacientes a MySQL           " . PHP_EOL;
echo "==========================================================" . PHP_EOL;

$sqlFile = __DIR__ . '/pacientes.sql';
if (!file_exists($sqlFile)) {
    die("Error crítico: No se encontró el archivo database/pacientes.sql" . PHP_EOL);
}

$handle = fopen($sqlFile, 'r');
if (!$handle) {
    die("Error crítico: No se pudo abrir database/pacientes.sql" . PHP_EOL);
}

$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

// Truncar o limpiar tabla pacientes si se desea una recarga limpia
$pdo->exec("TRUNCATE TABLE `pacientes`;");

$insertados = 0;
$query = '';

while (($line = fgets($handle)) !== false) {
    $trimmed = trim($line);
    if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*')) {
        continue;
    }
    $query .= $line;
    if (str_ends_with($trimmed, ';')) {
        try {
            $pdo->exec($query);
            if (stripos($query, 'INSERT INTO `pacientes`') !== false) {
                $insertados++;
            }
        } catch (Exception $e) {
            echo "Aviso al ejecutar sentencia: " . $e->getMessage() . PHP_EOL;
        }
        $query = '';
    }
}
fclose($handle);

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

$totalEnDb = (int)$pdo->query("SELECT COUNT(*) FROM `pacientes`")->fetchColumn();

echo PHP_EOL;
echo "Proceso completado exitosamente:" . PHP_EOL;
echo "-> Pacientes insertados: $insertados" . PHP_EOL;
echo "-> Total en Base de Datos: $totalEnDb" . PHP_EOL;
echo "-> Codificación: UTF-8 (utf8mb4_unicode_ci)" . PHP_EOL;
echo "==========================================================" . PHP_EOL;
