<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

$pdo = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Soporte para rango de fechas (Semana o Mes) o fecha individual (Día)
    $fecha = $_GET['fecha'] ?? date('Y-m-d');
    $fecha_inicio = $_GET['fecha_inicio'] ?? null;
    $fecha_fin = $_GET['fecha_fin'] ?? null;
    
    $consultorio_filtro = isset($_GET['consultorio_id']) && $_GET['consultorio_id'] !== '' ? (int)$_GET['consultorio_id'] : null;
    $medico_filtro = isset($_GET['medico_id']) && $_GET['medico_id'] !== '' ? (int)$_GET['medico_id'] : null;

    $params = [];
    if ($fecha_inicio && $fecha_fin) {
        $whereSql = "c.fecha BETWEEN :fini AND :ffin";
        $params[':fini'] = $fecha_inicio;
        $params[':ffin'] = $fecha_fin;
    } else {
        $whereSql = "c.fecha = :fecha";
        $params[':fecha'] = $fecha;
    }

    if ($consultorio_filtro !== null) {
        $whereSql .= " AND c.consultorio_id = :cons_id";
        $params[':cons_id'] = $consultorio_filtro;
    }

    if ($medico_filtro !== null) {
        $whereSql .= " AND c.medico_id = :med_id";
        $params[':med_id'] = $medico_filtro;
    }
    
    // Obtener todas las citas para el filtro dado
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            p.nombre AS paciente_nombre,
            p.cedula AS paciente_cedula,
            p.telefono AS paciente_telefono,
            p.representante AS paciente_representante,
            p.tipo_beneficio AS paciente_tipo_beneficio,
            m.nombre AS medico_nombre,
            m.especialidad AS medico_especialidad,
            cons.nombre AS consultorio_nombre,
            cons.numero AS consultorio_numero
        FROM citas c
        INNER JOIN pacientes p ON p.id = c.paciente_id
        INNER JOIN medicos m ON m.id = c.medico_id
        INNER JOIN consultorios cons ON cons.id = c.consultorio_id
        WHERE {$whereSql}
        ORDER BY c.fecha ASC, c.hora ASC, cons.numero ASC
    ");
    $stmt->execute($params);
    $citas = $stmt->fetchAll();

    // Adjuntar pagos de cada cita y generar mapa por fecha_hora_consultorio
    $citasMap = [];
    $citasPorDia = [];

    foreach ($citas as &$c) {
        $cstmt = $pdo->prepare("SELECT * FROM pagos_citas WHERE cita_id = :cid ORDER BY numero_pago ASC");
        $cstmt->execute([':cid' => $c['id']]);
        $c['pagos'] = $cstmt->fetchAll();
        
        // Clave única para la celda diaria: "hora_consultorioId"
        $key = $c['hora'] . '_' . $c['consultorio_id'];
        $citasMap[$key] = $c;

        // Agrupación por día para vistas semanal y mensual
        $diaKey = $c['fecha'];
        if (!isset($citasPorDia[$diaKey])) {
            $citasPorDia[$diaKey] = [];
        }
        $citasPorDia[$diaKey][] = $c;
    }

    // Obtener catálogo de consultorios
    $consultorios = $pdo->query("SELECT * FROM consultorios WHERE activo = 1 ORDER BY numero ASC")->fetchAll();
    
    // Obtener catálogo de médicos activos
    $medicos = $pdo->query("SELECT * FROM medicos WHERE activo = 1 ORDER BY nombre ASC")->fetchAll();

    // Obtener última tasa BCV
    $stmtTasa = $pdo->prepare("SELECT * FROM tasas_cambio ORDER BY fecha DESC LIMIT 1");
    $stmtTasa->execute();
    $tasa = $stmtTasa->fetch() ?: ['tasa_usd_ves' => 888.8909, 'fecha_valor' => $fecha];

    // Métricas del día
    $metricas = [
        'total'       => count($citas),
        'agendadas'   => 0,
        'confirmadas' => 0,
        'completadas' => 0,
        'no_asistio'  => 0,
        'pagadas'     => 0,
        'facturadas'  => 0,
        'monto_usd'   => 0.0
    ];

    foreach ($citas as $c) {
        $est = $c['estado'];
        if ($est === 'Agendada') $metricas['agendadas']++;
        elseif ($est === 'Confirmado') $metricas['confirmadas']++;
        elseif ($est === 'Completado') $metricas['completadas']++;
        elseif ($est === 'No asistio') $metricas['no_asistio']++;
        elseif ($est === 'Pagado') $metricas['pagadas']++;
        elseif ($est === 'Facturado') $metricas['facturadas']++;
        
        if (in_array($est, ['Pagado', 'Facturado'])) {
            $metricas['monto_usd'] += (float)$c['precio_usd'];
        }
    }

    sendJsonResponse(true, [
        'fecha'         => $fecha,
        'fecha_inicio'  => $fecha_inicio,
        'fecha_fin'     => $fecha_fin,
        'tasa'          => $tasa,
        'citas'         => $citas,
        'citas_map'     => $citasMap,
        'citas_por_dia' => $citasPorDia,
        'consultorios'  => $consultorios,
        'medicos'       => $medicos,
        'horarios'      => HORARIOS_ATENCION,
        'estados'       => ESTADOS_CITAS,
        'tipos'         => TIPOS_CITAS,
        'formas_pago'   => FORMAS_PAGO,
        'metricas'      => $metricas
    ]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $input['action'] ?? 'crear';

    if ($action === 'crear') {
        $fecha = trim($input['fecha'] ?? date('Y-m-d'));
        $hora = trim($input['hora'] ?? '08:00:00');
        $consultorio_id = (int)($input['consultorio_id'] ?? 1);
        $medico_id = (int)($input['medico_id'] ?? 1);
        $tipo = trim($input['tipo'] ?? 'Particular');
        $precio_usd = isset($input['precio_usd']) ? (float)$input['precio_usd'] : 50.00;
        $motivo = trim($input['motivo_consulta'] ?? '') ?: null;
        $observaciones = trim($input['observaciones'] ?? '') ?: null;

        // Validar paciente (existente o nuevo)
        $paciente_id = (int)($input['paciente_id'] ?? 0);
        if ($paciente_id <= 0) {
            $paciente_nombre = trim($input['paciente_nombre'] ?? '');
            if ($paciente_nombre === '') {
                sendJsonResponse(false, null, 'Debe indicar el nombre del paciente', 400);
            }
            $cedula = trim($input['paciente_cedula'] ?? '') ?: null;
            $telefono = trim($input['paciente_telefono'] ?? '') ?: null;
            $representante = trim($input['paciente_representante'] ?? '') ?: null;

            $stmtP = $pdo->prepare("
                INSERT INTO pacientes (nombre, cedula, telefono, representante, tipo_beneficio)
                VALUES (:nom, :ced, :tel, :rep, :tip)
            ");
            $stmtP->execute([
                ':nom' => $paciente_nombre,
                ':ced' => $cedula,
                ':tel' => $telefono,
                ':rep' => $representante,
                ':tip' => $tipo
            ]);
            $paciente_id = (int)$pdo->lastInsertId();
        }

        // Verificar si ya existe una cita en esa fecha, hora y consultorio
        $checkStmt = $pdo->prepare("
            SELECT id FROM citas 
            WHERE fecha = :f AND hora = :h AND consultorio_id = :c
            LIMIT 1
        ");
        $checkStmt->execute([':f' => $fecha, ':h' => $hora, ':c' => $consultorio_id]);
        if ($checkStmt->fetch()) {
            sendJsonResponse(false, null, 'Ya existe una cita agendada en este consultorio a esa misma hora.', 409);
        }

        // Insertar cita
        $insStmt = $pdo->prepare("
            INSERT INTO citas (fecha, hora, consultorio_id, medico_id, paciente_id, estado, tipo, precio_usd, motivo_consulta, observaciones)
            VALUES (:fecha, :hora, :cons_id, :med_id, :pac_id, 'Agendada', :tipo, :precio, :motivo, :obs)
        ");
        $insStmt->execute([
            ':fecha'    => $fecha,
            ':hora'     => $hora,
            ':cons_id'  => $consultorio_id,
            ':med_id'   => $medico_id,
            ':pac_id'   => $paciente_id,
            ':tipo'     => $tipo,
            ':precio'   => $precio_usd,
            ':motivo'   => $motivo,
            ':obs'      => $observaciones
        ]);

        sendJsonResponse(true, ['cita_id' => $pdo->lastInsertId()], 'Cita agendada exitosamente', 201);
    }

    if ($action === 'cambiar_estado') {
        $cita_id = (int)($input['cita_id'] ?? 0);
        $nuevo_estado = trim($input['estado'] ?? '');

        if (!array_key_exists($nuevo_estado, ESTADOS_CITAS)) {
            sendJsonResponse(false, null, 'Estado no válido', 400);
        }

        $stmt = $pdo->prepare("UPDATE citas SET estado = :est WHERE id = :id");
        $stmt->execute([':est' => $nuevo_estado, ':id' => $cita_id]);
        sendJsonResponse(true, null, "Estado actualizado a {$nuevo_estado}");
    }

    if ($action === 'editar') {
        $cita_id = (int)($input['cita_id'] ?? 0);
        $medico_id = (int)($input['medico_id'] ?? 1);
        $tipo = trim($input['tipo'] ?? 'Particular');
        $precio_usd = (float)($input['precio_usd'] ?? 50.00);
        $observaciones = trim($input['observaciones'] ?? '') ?: null;
        $motivo = trim($input['motivo_consulta'] ?? '') ?: null;

        $stmt = $pdo->prepare("
            UPDATE citas
            SET medico_id = :med_id, tipo = :tipo, precio_usd = :precio,
                observaciones = :obs, motivo_consulta = :motivo
            WHERE id = :id
        ");
        $stmt->execute([
            ':med_id' => $medico_id,
            ':tipo'   => $tipo,
            ':precio' => $precio_usd,
            ':obs'    => $observaciones,
            ':motivo' => $motivo,
            ':id'     => $cita_id
        ]);
        sendJsonResponse(true, null, 'Cita modificada exitosamente');
    }

    if ($action === 'cancelar') {
        $cita_id = (int)($input['cita_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM citas WHERE id = :id");
        $stmt->execute([':id' => $cita_id]);
        sendJsonResponse(true, null, 'Cita eliminada');
    }
}
