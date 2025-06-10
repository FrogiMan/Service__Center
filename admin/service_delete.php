<?php
require_once __DIR__.'/../includes/auth_check.php';
checkAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $serviceId = (int)$_POST['id'];
    
    // Проверяем, используется ли услуга в заказах
    $check = $conn->query("SELECT 1 FROM order_work_details WHERE service_id = $serviceId LIMIT 1");
    if ($check->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'Нельзя удалить услугу, которая используется в заказах']);
        exit;
    }
    
    $stmt = $conn->prepare("DELETE FROM services WHERE id = ?");
    $stmt->bind_param("i", $serviceId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Ошибка при удалении услуги']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
?>