<?php
/**
 * API: Manage project-vendor assignments
 * GET: Get vendors assigned to a project
 * POST: Assign vendor to project
 * DELETE: Remove vendor from project
 * PUT: Update paid amount for vendor in project
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
        SELECT pv.id, pv.vendor_id, v.name as vendor_name, pv.assigned_at, pv.paid_amount, pv.bid_amount,
            (SELECT COALESCE(SUM(amount), 0) FROM vendor_payments WHERE project_id = pv.project_id AND vendor_id = pv.vendor_id) as total_paid
        FROM project_vendors pv
        LEFT JOIN vendors v ON pv.vendor_id = v.id
        WHERE pv.project_id = ?
        ORDER BY pv.assigned_at DESC
    ");
    $stmt->execute([$project_id]);
    $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'vendors' => $vendors]);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $project_id = $input['project_id'] ?? null;
    $vendor_id = $input['vendor_id'] ?? null;
    $bid_amount = $input['bid_amount'] ?? null;
    
    if (!$project_id || !$vendor_id) {
        echo json_encode(['success' => false, 'error' => 'Missing project_id or vendor_id']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO project_vendors (project_id, vendor_id, bid_amount) VALUES (?, ?, ?)");
        $stmt->execute([$project_id, $vendor_id, $bid_amount ? floatval($bid_amount) : null]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;
    $paid_amount = $input['paid_amount'] ?? null;
    $bid_amount = $input['bid_amount'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Missing id']);
        exit;
    }
    
    try {
        if ($bid_amount !== null) {
            $stmt = $pdo->prepare("UPDATE project_vendors SET bid_amount = ? WHERE id = ?");
            $stmt->execute([floatval($bid_amount), $id]);
        } elseif ($paid_amount !== null) {
            $stmt = $pdo->prepare("UPDATE project_vendors SET paid_amount = ? WHERE id = ?");
            $stmt->execute([$paid_amount, $id]);
        }
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
    
    $stmt = $pdo->prepare("DELETE FROM project_vendors WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid method']);
