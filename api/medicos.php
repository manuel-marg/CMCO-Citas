<?php
/**
 * API REST para Gestión de Médicos y Especialistas
 * Centro Médico Comunitario OscaryAnny (CMCO / ADH)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

$pdo = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $search = trim($_GET['q'] ?? '');
    $filtroActivo = $_GET['activo'] ?? 'all'; // '1', '0' o 'all'

    // Obtener un solo médico con sus métricas
    if ($id > 0) {
        $stmt = $pdo->prepare("
            SELECT m.*, COUNT(c.id) AS total_citas
            FROM medicos m
            LEFT JOIN citas c ON c.medico_id = m.id
            WHERE m.id = :id
            GROUP BY m.id
        ");
        $stmt->execute([':id' => $id]);
        $medico = $stmt->fetch();

        if ($medico) {
            sendJsonResponse(true, $medico);
        } else {
            sendJsonResponse(false, null, 'Médico no encontrado', 404);
        }
    }

    // Listado general de médicos
    $where = [];
    $params = [];

    if ($filtroActivo === '1') {
        $where[] = "m.activo = 1";
    } elseif ($filtroActivo === '0') {
        $where[] = "m.activo = 0";
    }

    if ($search !== '') {
        $where[] = "(m.nombre LIKE :q1 OR m.especialidad LIKE :q2)";
        $params[':q1'] = "%{$search}%";
        $params[':q2'] = "%{$search}%";
    }

    $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "
        SELECT 
            m.id,
            m.nombre,
            m.especialidad,
            m.telefono,
            m.porcentaje_honorario,
            m.activo,
            m.created_at,
            COUNT(c.id) AS total_citas,
            SUM(CASE WHEN c.estado IN ('Atendido', 'Completado', 'Pagado', 'Facturado') THEN 1 ELSE 0 END) AS citas_completadas
        FROM medicos m
        LEFT JOIN citas c ON c.medico_id = m.id
        $whereSql
        GROUP BY m.id
        ORDER BY m.activo DESC, m.nombre ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $medicos = $stmt->fetchAll();

    // Contadores globales
    $totalGeneral = (int)$pdo->query("SELECT COUNT(*) FROM medicos")->fetchColumn();
    $totalActivos = (int)$pdo->query("SELECT COUNT(*) FROM medicos WHERE activo = 1")->fetchColumn();
    $totalInactivos = (int)$pdo->query("SELECT COUNT(*) FROM medicos WHERE activo = 0")->fetchColumn();

    sendJsonResponse(true, [
        'medicos'         => $medicos,
        'total'           => $totalGeneral,
        'total_activos'   => $totalActivos,
        'total_inactivos' => $totalInactivos
    ]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = trim($input['action'] ?? '');

    // Acción de alternar estado activo/inactivo
    if ($action === 'toggle_activo') {
        $id = (int)($input['id'] ?? 0);
        $activo = (int)($input['activo'] ?? 1);

        if ($id <= 0) {
            sendJsonResponse(false, null, 'ID de médico inválido', 400);
        }

        $stmt = $pdo->prepare("UPDATE medicos SET activo = :activo WHERE id = :id");
        $stmt->execute([':activo' => $activo, ':id' => $id]);

        $estadoStr = $activo === 1 ? 'activado' : 'desactivado';
        sendJsonResponse(true, ['id' => $id, 'activo' => $activo], "Médico {$estadoStr} exitosamente");
    }

    // Acción de eliminar médico
    if ($action === 'eliminar') {
        $id = (int)($input['id'] ?? 0);

        if ($id <= 0) {
            sendJsonResponse(false, null, 'ID de médico inválido', 400);
        }

        // Verificar si tiene citas asociadas
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM citas WHERE medico_id = :id");
        $checkStmt->execute([':id' => $id]);
        $citasCount = (int)$checkStmt->fetchColumn();

        if ($citasCount > 0) {
            // Desactivar lógicamente para preservar la integridad del historial contable y clínico
            $updStmt = $pdo->prepare("UPDATE medicos SET activo = 0 WHERE id = :id");
            $updStmt->execute([':id' => $id]);

            sendJsonResponse(true, [
                'id' => $id,
                'desactivado' => true,
                'total_citas' => $citasCount
            ], "El médico posee {$citasCount} citas registradas. Por seguridad e integridad histórica, fue desactivado del catálogo en lugar de borrado.");
        } else {
            // Eliminación física si no tiene citas
            $delStmt = $pdo->prepare("DELETE FROM medicos WHERE id = :id");
            $delStmt->execute([':id' => $id]);

            sendJsonResponse(true, [
                'id' => $id,
                'eliminado' => true
            ], 'Médico eliminado exitosamente del catálogo.');
        }
    }

    // Registrar o Editar Médico
    $nombre = trim($input['nombre'] ?? '');
    $especialidad = trim($input['especialidad'] ?? 'Medicina General');
    $telefono = trim($input['telefono'] ?? '') ?: null;
    $porcentaje = isset($input['porcentaje_honorario']) ? (float)$input['porcentaje_honorario'] : 50.00;
    $id = isset($input['id']) ? (int)$input['id'] : 0;

    if ($nombre === '') {
        sendJsonResponse(false, null, 'El nombre del médico o especialista es obligatorio', 400);
    }

    if ($especialidad === '') {
        $especialidad = 'Medicina General';
    }

    // Verificar unicidad de nombre (excepto si es el mismo registro en edición)
    $checkSql = "SELECT id FROM medicos WHERE nombre = :nombre" . ($id > 0 ? " AND id != :id" : "");
    $checkParams = [':nombre' => $nombre];
    if ($id > 0) $checkParams[':id'] = $id;

    $stmtCheck = $pdo->prepare($checkSql);
    $stmtCheck->execute($checkParams);
    if ($stmtCheck->fetch()) {
        sendJsonResponse(false, null, "Ya existe un médico registrado con el nombre '{$nombre}'", 400);
    }

    if ($id > 0) {
        // Actualizar
        $stmt = $pdo->prepare("
            UPDATE medicos
            SET nombre = :nombre,
                especialidad = :especialidad,
                telefono = :telefono,
                porcentaje_honorario = :porcentaje
            WHERE id = :id
        ");
        $stmt->execute([
            ':nombre'       => $nombre,
            ':especialidad' => $especialidad,
            ':telefono'     => $telefono,
            ':porcentaje'   => $porcentaje,
            ':id'           => $id
        ]);

        sendJsonResponse(true, ['id' => $id, 'nombre' => $nombre], 'Médico actualizado exitosamente');
    } else {
        // Registrar nuevo
        $stmt = $pdo->prepare("
            INSERT INTO medicos (nombre, especialidad, telefono, porcentaje_honorario, activo)
            VALUES (:nombre, :especialidad, :telefono, :porcentaje, 1)
        ");
        $stmt->execute([
            ':nombre'       => $nombre,
            ':especialidad' => $especialidad,
            ':telefono'     => $telefono,
            ':porcentaje'   => $porcentaje
        ]);

        $newId = (int)$pdo->lastInsertId();
        sendJsonResponse(true, ['id' => $newId, 'nombre' => $nombre], 'Médico registrado exitosamente', 201);
    }
}
