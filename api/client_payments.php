<?php
/**
 * API: Manage client payments (payments from client to Infinity Builders)
 * GET: Get payments for a project
 * POST: Add new payment
 * PUT: Update payment
 * DELETE: Delete payment
 */

header('Content-Type: application/json');
require_once '../partials/init.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $project_id = $_GET['project_id'] ?? null;
    
    if (!$project_id) {
        echo json_encode(['success' => false, 'error' => 'Missing project_id']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        SELECT id, project_id, amount, payment_date, description, created_at
        FROM client_payments
        WHERE project_id = ?
        ORDER BY payment_date DESC, created_at DESC
    ");
    $stmt->execute([$project_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'payments' => $payments]);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $project_id = $input['project_id'] ?? null;
    $amount = $input['amount'] ?? null;
    $payment_date = $input['payment_date'] ?? null;
    $description = $input['description'] ?? null;
    
    if (!$project_id || !$amount || !$payment_date) {
        echo json_encode(['success' => false, 'error' => 'Missing project_id, amount, or payment_date']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO client_payments (project_id, amount, payment_date, description)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$project_id, $amount, $payment_date, $description]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;
    $amount = $input['amount'] ?? null;
    $payment_date = $input['payment_date'] ?? null;
    $description = $input['description'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Missing id']);
        exit;
    }
    
    try {
        $updates = [];
        $params = [];
        
        if ($amount !== null) {
            $updates[] = 'amount = ?';
            $params[] = $amount;
        }
        if ($payment_date !== null) {
            $updates[] = 'payment_date = ?';
            $params[] = $payment_date;
        }
        if (array_key_exists('description', $input)) {
            $updates[] = 'description = ?';
            $params[] = $description;
        }
        
        if (empty($updates)) {
            echo json_encode(['success' => false, 'error' => 'No fields to update']);
            exit;
        }
        
        $params[] = $id;
        $stmt = $pdo->prepare("UPDATE client_payments SET " . implode(', ', $updates) . " WHERE id = ?");
        $stmt->execute($params);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Missing id']);
        exit;
    }
    
    $stmt = $pdo->prepare("DELETE FROM client_payments WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid method']);