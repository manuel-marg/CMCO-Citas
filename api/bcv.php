<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

$pdo = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];

/**
 * Función para consultar la tasa oficial directamente de www.bcv.org.ve
 */
function obtenerTasaDesdeBcv(): ?array {
    $url = 'https://www.bcv.org.ve/';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $html) {
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML($html);
        $xpath = new DOMXPath($doc);

        // Selector: #dolar > div > div > div.col-sm-6.col-xs-6.centrado.textp > strong
        $nodes = $xpath->query("//*[@id='dolar']//strong");
        if ($nodes->length > 0) {
            $valRaw = trim($nodes->item(0)->textContent);
            // Reemplazar coma por punto decimal y eliminar espacios
            $valLimpio = str_replace(' ', '', $valRaw);
            // Si tiene coma como separador decimal
            $valLimpio = str_replace(',', '.', $valLimpio);
            $tasaNum = round((float)$valLimpio, 2);

            if ($tasaNum > 0) {
                return [
                    'tasa_usd_ves' => $tasaNum,
                    'fecha_valor'  => date('Y-m-d'),
                    'fuente'       => 'BCV Oficial (bcv.org.ve)'
                ];
            }
        }
    }
    return null;
}

if ($method === 'GET') {
    $hoy = date('Y-m-d');
    $force = isset($_GET['force']) && $_GET['force'] == '1';

    // 1. Verificar si ya tenemos la tasa del día de hoy registrada en la BD
    $stmt = $pdo->prepare("SELECT * FROM tasas_cambio WHERE fecha = :fecha LIMIT 1");
    $stmt->execute([':fecha' => $hoy]);
    $tasa = $stmt->fetch();

    // 2. Si no la tenemos para hoy o se solicita forzar actualización, consultar al BCV
    if (!$tasa || $force) {
        $bcvData = obtenerTasaDesdeBcv();
        if ($bcvData) {
            $insStmt = $pdo->prepare("
                INSERT INTO tasas_cambio (fecha, tasa_usd_ves, fecha_valor, fuente)
                VALUES (:fecha, :tasa, :fecha_valor, :fuente)
                ON DUPLICATE KEY UPDATE tasa_usd_ves = :tasa_up, fecha_valor = :fecha_valor_up, fuente = :fuente_up
            ");
            $insStmt->execute([
                ':fecha'          => $hoy,
                ':tasa'           => $bcvData['tasa_usd_ves'],
                ':fecha_valor'    => $bcvData['fecha_valor'],
                ':fuente'         => $bcvData['fuente'],
                ':tasa_up'        => $bcvData['tasa_usd_ves'],
                ':fecha_valor_up' => $bcvData['fecha_valor'],
                ':fuente_up'      => $bcvData['fuente']
            ]);

            // Reconsultar tasa guardada
            $stmt->execute([':fecha' => $hoy]);
            $tasa = $stmt->fetch();
        }
    }

    // 3. Si por alguna razón la página del BCV no respondió y no hay registro hoy, usar la última histórica
    if (!$tasa) {
        $stmtLast = $pdo->query("SELECT * FROM tasas_cambio ORDER BY fecha DESC LIMIT 1");
        $tasa = $stmtLast->fetch();
    }

    // 4. Si aún no hay nada (primera ejecución sin internet), valor por defecto
    if (!$tasa) {
        $tasa = [
            'fecha'        => $hoy,
            'tasa_usd_ves' => 888.8909,
            'fecha_valor'  => $hoy,
            'fuente'       => 'Predeterminada'
        ];
    }

    sendJsonResponse(true, $tasa, 'Tasa obtenida');
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $tasa_val = isset($input['tasa']) ? round((float)$input['tasa'], 2) : 0.0;
    $fecha_valor = !empty($input['fecha_valor']) ? $input['fecha_valor'] : date('Y-m-d');
    $fuente = !empty($input['fuente']) ? $input['fuente'] : 'Manual';

    if ($tasa_val <= 0) {
        sendJsonResponse(false, null, 'El valor de la tasa debe ser mayor a 0', 400);
    }

    $hoy = date('Y-m-d');
    $stmt = $pdo->prepare("
        INSERT INTO tasas_cambio (fecha, tasa_usd_ves, fecha_valor, fuente)
        VALUES (:fecha, :tasa, :fecha_valor, :fuente)
        ON DUPLICATE KEY UPDATE tasa_usd_ves = :tasa_up, fecha_valor = :fecha_valor_up, fuente = :fuente_up
    ");
    $stmt->execute([
        ':fecha'          => $hoy,
        ':tasa'           => $tasa_val,
        ':fecha_valor'    => $fecha_valor,
        ':fuente'         => $fuente,
        ':tasa_up'        => $tasa_val,
        ':fecha_valor_up' => $fecha_valor,
        ':fuente_up'      => $fuente
    ]);

    sendJsonResponse(true, ['tasa_usd_ves' => $tasa_val, 'fecha_valor' => $fecha_valor], 'Tasa actualizada exitosamente');
}
