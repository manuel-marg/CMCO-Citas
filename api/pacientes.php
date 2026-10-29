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

    // Listado paginado y búsqueda integrada
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limitParam = strtolower(trim((string)($_GET['limit'] ?? '50')));
    $esTodos = in_array($limitParam, ['all', 'todos', '0', '-1'], true);
    $limit = $esTodos ? 5000 : max(10, min(500, (int)$limitParam ?: 50));
    $offset = ($page - 1) * $limit;

    $where = "";
    $params = [];
    if ($search !== '') {
        $where = "WHERE nombre LIKE :q1 OR cedula LIKE :q2 OR representante LIKE :q3";
        $term = "%{$search}%";
        $params[':q1'] = $term;
        $params[':q2'] = $term;
        $params[':q3'] = $term;
    }

    // Contar total general y total con filtros
    $totalGeneral = (int)$pdo->query("SELECT COUNT(*) FROM pacientes")->fetchColumn();

    if ($where !== '') {
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM pacientes $where");
        $countStmt->execute($params);
        $totalFiltrados = (int)$countStmt->fetchColumn();
    } else {
        $totalFiltrados = $totalGeneral;
    }

    $sql = "SELECT id, nombre, cedula, telefono, representante, tipo_beneficio, observaciones FROM pacientes $where ORDER BY nombre ASC";
    if (!$esTodos) {
        $sql .= " LIMIT :limit OFFSET :offset";
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    if (!$esTodos) {
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    }
    $stmt->execute();
    $pacientes = $stmt->fetchAll();

    $totalPages = $esTodos ? 1 : max(1, (int)ceil($totalFiltrados / $limit));

    sendJsonResponse(true, [
        'pacientes'       => $pacientes,
        'total'           => $totalGeneral,
        'total_filtrados' => $totalFiltrados,
        'page'            => $page,
        'pages'           => $totalPages,
        'limit'           => $esTodos ? 'all' : $limit
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
