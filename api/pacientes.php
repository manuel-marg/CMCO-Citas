<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

$pdo = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $search = trim($_GET['q'] ?? '');
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM pacientes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $paciente = $stmt->fetch();
        if ($paciente) {
            // Obtener historial de citas de este paciente
            $cstmt = $pdo->prepare("
                SELECT c.*, m.nombre AS medico_nombre, cons.nombre AS consultorio_nombre
                FROM citas c
                LEFT JOIN medicos m ON m.id = c.medico_id
                LEFT JOIN consultorios cons ON cons.id = c.consultorio_id
                WHERE c.paciente_id = :pid
                ORDER BY c.fecha DESC, c.hora DESC
            ");
            $cstmt->execute([':pid' => $id]);
            $paciente['citas'] = $cstmt->fetchAll();
            sendJsonResponse(true, $paciente);
        } else {
            sendJsonResponse(false, null, 'Paciente no encontrado', 404);
        }
    }

    if ($search !== '') {
        $stmt = $pdo->prepare("
            SELECT id, nombre, cedula, telefono, representante, tipo_beneficio
            FROM pacientes
            WHERE nombre LIKE :q1 OR cedula LIKE :q2
            ORDER BY nombre ASC
            LIMIT 20
        ");
        $term = "%{$search}%";
        $stmt->execute([':q1' => $term, ':q2' => $term]);
        $pacientes = $stmt->fetchAll();
        sendJsonResponse(true, $pacientes);
    }

    // Listado paginado de pacientes
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 50;
    $offset = ($page - 1) * $limit;

    $stmtCount = $pdo->query("SELECT COUNT(*) FROM pacientes");
    $total = (int)$stmtCount->fetchColumn();

    $stmt = $pdo->prepare("SELECT * FROM pacientes ORDER BY nombre ASC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $pacientes = $stmt->fetchAll();

    sendJsonResponse(true, [
        'pacientes' => $pacientes,
        'total'     => $total,
        'page'      => $page,
        'pages'     => ceil($total / $limit)
    ]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $nombre = trim($input['nombre'] ?? '');
    $cedula = trim($input['cedula'] ?? '') ?: null;
    $telefono = trim($input['telefono'] ?? '') ?: null;
    $representante = trim($input['representante'] ?? '') ?: null;
    $tipo_beneficio = trim($input['tipo_beneficio'] ?? 'Particular');
    $observaciones = trim($input['observaciones'] ?? '') ?: null;

    if ($nombre === '') {
        sendJsonResponse(false, null, 'El nombre del paciente es obligatorio', 400);
    }

    // Comprobar si ya existe por nombre exacto o actualizar
    $id = isset($input['id']) ? (int)$input['id'] : 0;

    if ($id > 0) {
        $stmt = $pdo->prepare("
            UPDATE pacientes
            SET nombre = :nombre, cedula = :cedula, telefono = :telefono,
                representante = :representante, tipo_beneficio = :tipo_beneficio,
                observaciones = :observaciones
            WHERE id = :id
        ");
        $stmt->execute([
            ':nombre'         => $nombre,
            ':cedula'         => $cedula,
            ':telefono'       => $telefono,
            ':representante'  => $representante,
            ':tipo_beneficio' => $tipo_beneficio,
            ':observaciones'  => $observaciones,
            ':id'             => $id
        ]);
        sendJsonResponse(true, ['id' => $id], 'Paciente actualizado exitosamente');
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO pacientes (nombre, cedula, telefono, representante, tipo_beneficio, observaciones)
            VALUES (:nombre, :cedula, :telefono, :representante, :tipo_beneficio, :observaciones)
        ");
        $stmt->execute([
            ':nombre'         => $nombre,
            ':cedula'         => $cedula,
            ':telefono'       => $telefono,
            ':representante'  => $representante,
            ':tipo_beneficio' => $tipo_beneficio,
            ':observaciones'  => $observaciones
        ]);
        $newId = (int)$pdo->lastInsertId();
        sendJsonResponse(true, ['id' => $newId, 'nombre' => $nombre], 'Paciente creado exitosamente', 201);
    }
}
