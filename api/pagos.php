<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

$pdo = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    $cita_id = (int)($input['cita_id'] ?? 0);
    $estado_final = trim($input['estado_final'] ?? 'Pagado'); // 'Pagado' o 'Facturado'
    $factura_o_empleado = trim($input['factura_o_empleado'] ?? '') ?: null;
    $tasa_bcv = (float)($input['tasa_bcv'] ?? 888.8909);
    $precio_usd = isset($input['precio_usd']) ? max(0.0, (float)$input['precio_usd']) : null;
    $adicional_concepto = array_key_exists('adicional_concepto', $input) ? (trim($input['adicional_concepto'] ?? '') ?: null) : null;
    $adicional_monto = max(0.0, (float)($input['adicional_monto'] ?? 0.0));
    $costo_laboratorio = max(0.0, (float)($input['costo_laboratorio'] ?? 0.0));

    if ($cita_id <= 0) {
        sendJsonResponse(false, null, 'ID de cita no válido', 400);
    }

    // Pagos
    $forma_1 = trim($input['forma_1'] ?? '');
    $monto_1 = (float)($input['monto_1'] ?? 0.0);
    $forma_2 = trim($input['forma_2'] ?? '');
    $monto_2 = (float)($input['monto_2'] ?? 0.0);

    $pdo->beginTransaction();
    try {
        // Eliminar pagos anteriores si existían
        $delStmt = $pdo->prepare("DELETE FROM pagos_citas WHERE cita_id = :cid");
        $delStmt->execute([':cid' => $cita_id]);

        $insPago = $pdo->prepare("
            INSERT INTO pagos_citas (cita_id, numero_pago, forma_pago, monto_usd, monto_bs, tasa_cambio, referencia)
            VALUES (:cid, :num, :forma, :musd, :mbs, :tasa, :ref)
        ");

        if ($forma_1 !== '' && $monto_1 > 0) {
            $mbs_1 = round($monto_1 * $tasa_bcv, 2);
            $insPago->execute([
                ':cid'   => $cita_id,
                ':num'   => 1,
                ':forma' => $forma_1,
                ':musd'  => $monto_1,
                ':mbs'   => $mbs_1,
                ':tasa'  => $tasa_bcv,
                ':ref'   => trim($input['referencia_1'] ?? '') ?: null
            ]);
        }

        if ($forma_2 !== '' && $monto_2 > 0) {
            $mbs_2 = round($monto_2 * $tasa_bcv, 2);
            $insPago->execute([
                ':cid'   => $cita_id,
                ':num'   => 2,
                ':forma' => $forma_2,
                ':musd'  => $monto_2,
                ':mbs'   => $mbs_2,
                ':tasa'  => $tasa_bcv,
                ':ref'   => trim($input['referencia_2'] ?? '') ?: null
            ]);
        }

        // Actualizar la cita con el nuevo estado, tasa, comprobante y adicionales
        $paramsUp = [
            ':estado'     => $estado_final,
            ':tasa'       => $tasa_bcv,
            ':fac'        => $factura_o_empleado,
            ':adic_conc'  => $adicional_concepto,
            ':adic_monto' => $adicional_monto,
            ':costo_lab'  => $costo_laboratorio,
            ':id'         => $cita_id
        ];
        $precioSql = "";
        if ($precio_usd !== null) {
            $precioSql = ", precio_usd = :precio";
            $paramsUp[':precio'] = $precio_usd;
        }

        $upCita = $pdo->prepare("
            UPDATE citas
            SET estado = :estado,
                tasa_bcv_aplicada = :tasa,
                factura_o_empleado = :fac,
                adicional_concepto = :adic_conc,
                adicional_monto = :adic_monto,
                costo_laboratorio = :costo_lab
                {$precioSql}
            WHERE id = :id
        ");
        $upCita->execute($paramsUp);

        $pdo->commit();
        sendJsonResponse(true, null, "Pago registrado y cita marcada como {$estado_final}");
    } catch (Exception $e) {
        $pdo->rollBack();
        sendJsonResponse(false, null, 'Error al procesar pago: ' . $e->getMessage(), 500);
    }
}
