<?php
require_once __DIR__.'/../includes/auth_check.php';
checkAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $orderId = (int)$_POST['id'];
    
    $conn->begin_transaction();
    try {
        $conn->query("DELETE FROM order_work_details WHERE order_id = $orderId");
        $conn->query("DELETE FROM order_parts WHERE order_id = $orderId");
        $conn->query("DELETE FROM order_history WHERE order_id = $orderId");
        $conn->query("DELETE FROM order_attachments WHERE order_id = $orderId");
        $conn->query("DELETE FROM payment_history WHERE order_id = $orderId");
        $conn->query("DELETE FROM notifications WHERE related_order_id = $orderId");
        $conn->query("DELETE FROM orders WHERE id = $orderId");
        
        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
?>