<?php
/**
 * Script de Actualización Segura de Base de Datos para CMCO-Citas
 * Puede ejecutarse desde el navegador web (http://localhost/CMCO-Citas/database/actualizar_bd.php)
 * o desde la consola PHP (php database/actualizar_bd.php).
 *
 * Aplica migraciones pendientes de forma idempotente sin borrar datos de producción.
 */

declare(strict_types=1);

// Cargar configuración de base de datos
$configPath = dirname(__DIR__) . '/config/database.php';
if (!file_exists($configPath)) {
    die("Error: No se encontró el archivo de configuración en: {$configPath}\n");
}
require_once $configPath;

$isCli = (php_sapi_name() === 'cli');

$logs = [];
function registrarLog(string $mensaje, string $tipo = 'info') {
    global $logs, $isCli;
    $logs[] = ['mensaje' => $mensaje, 'tipo' => $tipo];
    if ($isCli) {
        $prefijo = match($tipo) {
            'success' => '[OK] ',
            'warning' => '[AVISO] ',
            'danger' => '[ERROR] ',
            default => '[INFO] '
        };
        echo $prefijo . $mensaje . "\n";
    }
}

try {
    $db = Database::getInstance()->getConnection();
    registrarLog("Conexión a MariaDB/MySQL establecida exitosamente.", 'success');

    // 1. Verificar y agregar columna 'color' en tabla 'medicos'
    $colMedColor = $db->query("SHOW COLUMNS FROM medicos LIKE 'color'")->fetch();
    if (!$colMedColor) {
        $db->exec("ALTER TABLE medicos ADD COLUMN color VARCHAR(20) DEFAULT '#4f90a5' AFTER porcentaje_honorario");
        registrarLog("Columna 'color' agregada a la tabla 'medicos'.", 'success');
    } else {
        registrarLog("La columna 'color' ya existe en 'medicos'. Omitiendo.", 'info');
    }

    // 2. Asignar colores históricos por defecto a los médicos existentes si están vacíos o nulos
    $coloresMedicos = [
        'Rosanny García'    => '#6aa3b4',
        'Maribel Guilarte'  => '#f0a8a8',
        'Lyl Belisario'     => '#f5be9e',
        'Auxiliadora'       => '#f9d884',
        'Jorge Valeri'      => '#b2d8a4',
        'José Francisco'    => '#b8d5e5',
        'Mónica Ramos'      => '#d8b4e2',
        'Luisana Valera'    => '#f5c2c7',
        'Luzmir'            => '#784516',
        'Nehomar'           => '#3d8bfd',
        'María Eugenia'     => '#8540f5',
        'Johanna Ramos'     => '#e599f7',
        'Alejandra'         => '#63e6be',
        'Génesis'           => '#ffd43b',
        'Alejandro'         => '#69db7c',
        'Kassandra'         => '#ff922b',
        'Nairobys'          => '#20c997',
        'José Miguel'       => '#4dabf7'
    ];

    $medicosActualizados = 0;
    foreach ($coloresMedicos as $nombreParcial => $colorHex) {
        $stmt = $db->prepare("UPDATE medicos SET color = :color WHERE nombre LIKE :nombre AND (color IS NULL OR color = '' OR color = '#4f90a5')");
        $stmt->execute([
            ':color' => $colorHex,
            ':nombre' => "%{$nombreParcial}%"
        ]);
        $medicosActualizados += $stmt->rowCount();
    }
    if ($medicosActualizados > 0) {
        registrarLog("Se actualizaron los colores históricos de {$medicosActualizados} médicos.", 'success');
    } else {
        registrarLog("Los médicos ya cuentan con su paleta de colores configurada.", 'info');
    }

    // 3. Verificar y agregar columnas de adicionales en 'citas'
    $colAdicConcepto = $db->query("SHOW COLUMNS FROM citas LIKE 'adicional_concepto'")->fetch();
    if (!$colAdicConcepto) {
        $db->exec("ALTER TABLE citas ADD COLUMN adicional_concepto VARCHAR(150) NULL AFTER precio_usd");
        registrarLog("Columna 'adicional_concepto' agregada a 'citas'.", 'success');
    } else {
        registrarLog("Columna 'adicional_concepto' ya existe en 'citas'.", 'info');
    }

    $colAdicMonto = $db->query("SHOW COLUMNS FROM citas LIKE 'adicional_monto'")->fetch();
    if (!$colAdicMonto) {
        $db->exec("ALTER TABLE citas ADD COLUMN adicional_monto DECIMAL(10,2) DEFAULT 0.00 AFTER adicional_concepto");
        registrarLog("Columna 'adicional_monto' agregada a 'citas'.", 'success');
    } else {
        registrarLog("Columna 'adicional_monto' ya existe en 'citas'.", 'info');
    }

    $colCostoLab = $db->query("SHOW COLUMNS FROM citas LIKE 'costo_laboratorio'")->fetch();
    if (!$colCostoLab) {
        $db->exec("ALTER TABLE citas ADD COLUMN costo_laboratorio DECIMAL(10,2) DEFAULT 0.00 AFTER adicional_monto");
        registrarLog("Columna 'costo_laboratorio' agregada a 'citas'.", 'success');
    } else {
        registrarLog("Columna 'costo_laboratorio' ya existe en 'citas'.", 'info');
    }

    registrarLog("¡Base de datos actualizada al 100%! La estructura se encuentra sincronizada.", 'success');

} catch (Exception $e) {
    registrarLog("Error al ejecutar actualización: " . $e->getMessage(), 'danger');
}

if (!$isCli): ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualización de Base de Datos - CMCO Citas</title>
    <link rel="stylesheet" href="../assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <style>
        body { background-color: #f0f4f8; font-family: system-ui, -apple-system, sans-serif; }
        .card-custom { max-width: 680px; margin: 40px auto; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="card card-custom bg-white border-0">
            <div class="card-header bg-primary text-white py-3 px-4 rounded-top-3 d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="mb-0 fw-bold"><i class="bi bi-database-check me-2"></i>Actualizador de Base de Datos</h5>
                    <small class="opacity-75">CMCO-Citas · Migraciones y Parches Seguros</small>
                </div>
                <span class="badge bg-white text-primary fw-bold">v1.2.0</span>
            </div>
            <div class="card-body p-4">
                <p class="text-secondary small mb-3">
                    Este proceso revisa la estructura de la base de datos de producción y aplica las columnas faltantes sin alterar ni eliminar ningún registro de pacientes, citas o pagos.
                </p>

                <div class="list-group list-group-flush border rounded-3 mb-4">
                    <?php foreach ($logs as $log): ?>
                        <div class="list-group-item d-flex align-items-center py-2.5 px-3">
                            <?php if ($log['tipo'] === 'success'): ?>
                                <i class="bi bi-check-circle-fill text-success fs-5 me-3"></i>
                            <?php elseif ($log['tipo'] === 'warning'): ?>
                                <i class="bi bi-exclamation-triangle-fill text-warning fs-5 me-3"></i>
                            <?php elseif ($log['tipo'] === 'danger'): ?>
                                <i class="bi bi-x-circle-fill text-danger fs-5 me-3"></i>
                            <?php else: ?>
                                <i class="bi bi-info-circle-fill text-primary fs-5 me-3"></i>
                            <?php endif; ?>
                            <div class="small flex-grow-1 <?php echo $log['tipo'] === 'danger' ? 'text-danger fw-bold' : 'text-dark'; ?>">
                                <?php echo htmlspecialchars($log['mensaje']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-2">
                    <a href="actualizar_bd.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-repeat me-1"></i> Volver a verificar
                    </a>
                    <a href="../index.php" class="btn btn-success btn-sm px-4 fw-bold">
                        <i class="bi bi-calendar-check me-1"></i> Ir a la Agenda
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php endif; ?>
