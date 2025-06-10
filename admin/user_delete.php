<?php
require_once __DIR__.'/../includes/auth_check.php';
checkAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $userId = (int)$_POST['id'];
    
    // Проверяем, есть ли связанные заказы или устройства
    $check = $conn->query("SELECT 1 FROM orders WHERE user_id = $userId LIMIT 1");
    if ($check->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'Нельзя удалить пользователя с активными заказами']);
        exit;
    }
    
    $check = $conn->query("SELECT 1 FROM devices WHERE user_id = $userId LIMIT 1");
    if ($check->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'Нельзя удалить пользователя с зарегистрированными устройствами']);
        exit;
    }
    
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Ошибка при удалении пользователя']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
?>