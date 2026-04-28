<?php
/**
 * Global Search API
 * Returns search results from Projects, Vendors, Users, Tasks, Permits, Inspections
 */

// Load config to get database credentials
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Check for actions FIRST
if (isset($_GET['action']) && $_GET['action'] === 'projects_list') {
    $stmt = $pdo->query("SELECT id, name, status FROM projects ORDER BY name ASC LIMIT 100");
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['projects' => $projects]);
    exit;
}

// Get search query
$query = trim($_GET['q'] ?? '');

if (strlen($query) < 2) {
    echo json_encode(['results' => [], 'message' => 'Min 2 characters']);
    exit;
}

// Global search across all entities
function globalSearch($pdo, $query) {
    $q = "%" . $query . "%";
    $results = [
        'projects' => [],
        'vendors' => [],
        'users' => [],
        'tasks' => [],
        'permits' => [],
        'inspections' => []
    ];

    // Projects - search by name, description, client_name
    $stmt = $pdo->prepare("
        SELECT id, name, description, client_name, status, total_budget
        FROM projects 
        WHERE name LIKE ? OR description LIKE ? OR client_name LIKE ?
        ORDER BY name ASC LIMIT 15
    ");
    $stmt->execute([$q, $q, $q]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results['projects'][] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'client' => $row['client_name'] ?? '',
            'status' => $row['status'],
            'budget' => $row['total_budget'] ?? 0,
            'type' => 'project',
            'url' => 'projects.php?open=' . $row['id']
        ];
    }

    // Vendors - search by name, email, type, trade
    $stmt = $pdo->prepare("
        SELECT id, name, email, type, trade, phone 
        FROM vendors 
        WHERE name LIKE ? OR email LIKE ? OR type LIKE ? OR trade LIKE ?
        ORDER BY name ASC LIMIT 10
    ");
    $stmt->execute([$q, $q, $q, $q]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results['vendors'][] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'email' => $row['email'] ?? '',
            'type' => $row['type'] ?? '',
            'trade' => $row['trade'] ?? '',
            'phone' => $row['phone'] ?? '',
            'type' => 'vendor',
            'url' => 'vendors.php?open=' . $row['id']
        ];
    }

    // Users - search by username, email
    $stmt = $pdo->prepare("
        SELECT id, username, email, role 
        FROM users 
        WHERE username LIKE ? OR email LIKE ?
        ORDER BY username ASC LIMIT 10
    ");
    $stmt->execute([$q, $q]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results['users'][] = [
            'id' => $row['id'],
            'username' => $row['username'],
            'full_name' => $row['username'],
            'email' => $row['email'] ?? '',
            'role' => $row['role'] ?? '',
            'type' => 'user',
            'url' => 'users.php?open=' . $row['id']
        ];
    }

    // Tasks (from project_tasks table)
    $stmt = $pdo->prepare("
        SELECT pt.id, pt.title, pt.description, pt.status, p.name as project_name, pt.project_id 
        FROM project_tasks pt 
        LEFT JOIN projects p ON pt.project_id = p.id
        WHERE pt.title LIKE ? OR pt.description LIKE ?
        ORDER BY pt.title ASC LIMIT 10
    ");
    $stmt->execute([$q, $q]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results['tasks'][] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'description' => $row['description'] ?? '',
            'status' => $row['status'] ?? '',
            'project_name' => $row['project_name'] ?? '',
            'type' => 'task',
            'url' => 'projects.php?open=' . ($row['project_id'] ?? '')
        ];
    }

    // Permits - search by permit_number, city, notes, project name
    $stmt = $pdo->prepare("
        SELECT pe.id, pe.permit_number, pe.city, pe.status, pe.submitted_date, pe.submission_date, pe.notes,
               p.name as project_name, p.id as project_id
        FROM permits pe
        LEFT JOIN projects p ON pe.project_id = p.id
        WHERE pe.permit_number LIKE ? 
           OR pe.city LIKE ?
           OR pe.notes LIKE ?
           OR p.name LIKE ?
         ORDER BY pe.submission_date DESC LIMIT 10
    ");
    $stmt->execute([$q, $q, $q, $q]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results['permits'][] = [
            'id' => $row['id'],
            'permit_number' => $row['permit_number'],
            'city' => $row['city'],
            'status' => $row['status'],
            'project_name' => $row['project_name'] ?? '',
            'submitted_date' => $row['submission_date'],
            'type' => 'permit',
            'url' => 'permits.php?open=' . $row['id']
        ];
    }

    // Inspections - search by city, requested_by, notes, project name
    $stmt = $pdo->prepare("
        SELECT i.id, i.status, i.scheduled_date, i.city, i.requested_by, i.notes, i.inspection_type,
               p.name as project_name, p.id as project_id
        FROM inspections i
        LEFT JOIN projects p ON i.project_id = p.id
        WHERE i.city LIKE ? 
           OR i.requested_by LIKE ?
           OR i.notes LIKE ?
           OR i.inspection_type LIKE ?
           OR p.name LIKE ?
        ORDER BY i.scheduled_date DESC LIMIT 10
    ");
    $stmt->execute([$q, $q, $q, $q, $q]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results['inspections'][] = [
            'id' => $row['id'],
            'status' => $row['status'],
            'scheduled_date' => $row['scheduled_date'],
            'inspector' => $row['requested_by'] ?? '',
            'city' => $row['city'] ?? '',
            'inspection_type' => $row['inspection_type'] ?? '',
            'notes' => $row['notes'] ?? '',
            'project_name' => $row['project_name'] ?? '',
            'type' => 'inspection',
            'url' => 'inspections.php?open=' . $row['id']
        ];
    }

    return $results;
}

$results = globalSearch($pdo, $query);

// Format response
$totalResults = count($results['projects']) + count($results['vendors']) + count($results['users']) 
              + count($results['tasks']) + count($results['permits']) + count($results['inspections']);

echo json_encode([
    'results' => $results,
    'total' => $totalResults,
    'query' => $query
]);
