<?php
/**
 * CMCO-Citas - Script de Verificación y Restauración de Codificación UTF-8
 * 
 * Garantiza que los registros históricos de pacientes, citas y pagos
 * se importen con codificación nativa utf8mb4 sin que los acentos
 * o caracteres especiales en español se corrompan por el codepage de Windows.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$pdo = Database::getConnection();

echo "Iniciando verificación y restauración UTF-8 en MySQL..." . PHP_EOL;

$sqlFile = __DIR__ . '/imported_data.sql';
if (!file_exists($sqlFile)) {
    die("Error: No se encontró el archivo imported_data.sql" . PHP_EOL);
}

$handle = fopen($sqlFile, 'r');
if (!$handle) {
    die("Error: No se pudo abrir imported_data.sql" . PHP_EOL);
}

$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("TRUNCATE TABLE pagos_citas;");
$pdo->exec("TRUNCATE TABLE citas;");
$pdo->exec("TRUNCATE TABLE pacientes;");

$count = 0;
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
            $count++;
        } catch (Exception $e) {
            echo "Aviso en sentencia $count: " . $e->getMessage() . PHP_EOL;
        }
        $query = '';
    }
}
fclose($handle);

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

$stmt = $pdo->query("SELECT COUNT(*) AS total FROM pacientes WHERE nombre LIKE '%?%' OR representante LIKE '%?%'");
$broken = (int)$stmt->fetch()['total'];

$totalPacientes = (int)$pdo->query("SELECT COUNT(*) FROM pacientes")->fetchColumn();
$totalCitas = (int)$pdo->query("SELECT COUNT(*) FROM citas")->fetchColumn();

echo "Proceso finalizado exitosamente:" . PHP_EOL;
echo "- Pacientes registrados: $totalPacientes" . PHP_EOL;
echo "- Citas restauradas: $totalCitas" . PHP_EOL;
echo "- Caracteres corruptos restantes: $broken" . PHP_EOL;
