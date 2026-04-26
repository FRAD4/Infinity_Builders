<?php
/**
 * Global Search API
 * Returns search results from Projects, Vendors, and Users
 */

// Database connection
$db_host = 'localhost';
$db_name = 'infinity_builders';
$db_user = 'root';
$db_pass = '';

header('Content-Type: application/json');

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Get search query
$query = trim($_GET['q'] ?? '');

if (strlen($query) < 2) {
    echo json_encode(['results' => [], 'message' => 'Min 2 characters']);
    exit;
}

$results = [
    'projects' => [],
    'vendors' => [],
    'users' => []
];

try {
    // Search Projects
    $stmt = $pdo->prepare("
        SELECT id, name, client_name, status, total_budget 
        FROM projects 
        WHERE name LIKE ? OR client_name LIKE ?
        ORDER BY name ASC 
        LIMIT 5
    ");
    $searchTerm = '%' . $query . '%';
    $stmt->execute([$searchTerm, $searchTerm]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results['projects'][] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'client' => $row['client_name'] ?? '',
            'status' => $row['status'],
            'budget' => $row['total_budget'] ?? 0,
            'url' => 'projects.php?open=' . $row['id']
        ];
    }
    
    // Search Vendors
    $stmt = $pdo->prepare("
        SELECT id, name, type, trade, email, phone
        FROM vendors 
        WHERE name LIKE ? OR trade LIKE ? OR email LIKE ?
        ORDER BY name ASC 
        LIMIT 5
    ");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results['vendors'][] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'type' => $row['type'] ?? '',
            'trade' => $row['trade'] ?? '',
            'email' => $row['email'] ?? '',
            'phone' => $row['phone'] ?? '',
            'url' => 'vendors.php?open=' . $row['id']
        ];
    }
    
    // Note: Users search removed - requires session/auth
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

// Format response
$totalResults = count($results['projects']) + count($results['vendors']) + count($results['users']);

echo json_encode([
    'results' => $results,
    'total' => $totalResults,
    'query' => $query
]);