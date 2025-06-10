<?php
require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/notifications.php';
checkAdmin();

$orderId = $_GET['id'] ?? 0;
$errors = [];
$order = null;

// Получаем данные для выпадающих списков
$users = $conn->query("SELECT id, name FROM users WHERE role = 'client'")->fetch_all(MYSQLI_ASSOC);
$technicians = $conn->query("SELECT id, name FROM users WHERE role = 'admin' OR role = 'technician'")->fetch_all(MYSQLI_ASSOC);
$devices = $conn->query("SELECT d.id, d.brand, d.model, u.name as user_name FROM devices d JOIN users u ON d.user_id = u.id")->fetch_all(MYSQLI_ASSOC);
$problemTypes = $conn->query("SELECT id, name FROM problem_types ORDER BY category, name")->fetch_all(MYSQLI_ASSOC);
$services = $conn->query("SELECT id, service_name, price FROM services WHERE is_active = 1")->fetch_all(MYSQLI_ASSOC);

if ($orderId) {
    $orderStmt = $conn->prepare("
        SELECT o.*, d.brand, d.model, pt.name as problem_type
        FROM orders o
        JOIN devices d ON o.device_id = d.id
        JOIN problem_types pt ON o.problem_type_id = pt.id
        WHERE o.id = ?
    ");
    $orderStmt->bind_param("i", $orderId);
    $orderStmt->execute();
    $order = $orderStmt->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    
    $userId = (int)$_POST['user_id'];
    $deviceId = (int)$_POST['device_id'];
    $problemTypeId = (int)$_POST['problem_type_id'];
    $description = sanitizeInput($_POST['issue_description']);
    $urgency = sanitizeInput($_POST['urgency']);
    $status = sanitizeInput($_POST['status']);
    $technicianId = (int)$_POST['technician_id'] ?: null;
    $diagnosticReport = sanitizeInput($_POST['diagnostic_report']);
    $adminNotes = sanitizeInput($_POST['admin_notes']);
    $finalCost = (float)$_POST['final_cost'];
    $warrantyPeriod = (int)$_POST['warranty_period'];
    
    if (empty($deviceId)) $errors[] = 'Выберите устройство';
    if (empty($problemTypeId)) $errors[] = 'Выберите тип проблемы';
    if (strlen($description) < 10) $errors[] = 'Описание должно содержать минимум 10 символов';
    
    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            if ($orderId) {
                // Обновление заказа
                $stmt = $conn->prepare("
                    UPDATE orders 
                    SET user_id = ?, device_id = ?, problem_type_id = ?, issue_description = ?, 
                        urgency = ?, status = ?, technician_id = ?, diagnostic_report = ?, 
                        admin_notes = ?, final_cost = ?, warranty_period = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param("iiisssiisdii", $userId, $deviceId, $problemTypeId, $description, 
                    $urgency, $status, $technicianId, $diagnosticReport, $adminNotes, $finalCost, 
                    $warrantyPeriod, $orderId);
            } else {
                // Создание заказа
                $stmt = $conn->prepare("
                    INSERT INTO orders (
                        user_id, device_id, problem_type_id, issue_description, urgency, 
                        status, technician_id, diagnostic_report, admin_notes, final_cost, 
                        warranty_period, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->bind_param("iiissssisdi", $userId, $deviceId, $problemTypeId, $description, 
                    $urgency, $status, $technicianId, $diagnosticReport, $adminNotes, $finalCost, 
                    $warrantyPeriod);
            }
            
            if ($stmt->execute()) {
                if (!$orderId) $orderId = $stmt->insert_id;
                
                // Обновление услуг
                if (!empty($_POST['services'])) {
                    $conn->query("DELETE FROM order_work_details WHERE order_id = $orderId");
                    foreach ($_POST['services'] as $serviceId => $cost) {
                        $serviceStmt = $conn->prepare("
                            INSERT INTO order_work_details (order_id, service_id, cost)
                            VALUES (?, ?, ?)
                        ");
                        $serviceStmt->bind_param("iid", $orderId, $serviceId, $cost);
                        $serviceStmt->execute();
                    }
                }
                
                // Добавление записи в историю
                $action = $orderId ? 'Заказ обновлен' : 'Заказ создан';
                $conn->query("
                    INSERT INTO order_history (order_id, user_id, action, comment)
                    VALUES ($orderId, {$_SESSION['user_id']}, '$action', 'Администратор обновил заказ')
                ");
                
                // Отправка уведомления при завершении заказа
// Send Telegram notification on order completion
                if ($status === 'completed' && (!$order || $order['status'] !== 'completed')) {
                    // Get admin chat ID from settings
                    $adminChatIdResult = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'admin_telegram_chat_id'");
                    $adminChatId = $adminChatIdResult->fetch_assoc()['setting_value'] ?? '';
                    
                    if ($adminChatId) {
                        // Get order details for notification
                        $deviceStmt = $conn->prepare("
                            SELECT d.brand, d.model, dt.name as device_type
                            FROM devices d
                            JOIN device_types dt ON d.device_type_id = dt.id
                            WHERE d.id = ?
                        ");
                        $deviceStmt->bind_param("i", $deviceId);
                        $deviceStmt->execute();
                        $device = $deviceStmt->get_result()->fetch_assoc();
                        
                        $user = getUserById($userId);
                        $telegramMessage = "✅ <b>Заказ #{$orderId} завершен</b>\n";
                        $telegramMessage .= "Клиент: {$user['name']}\n";
                        $telegramMessage .= "Устройство: {$device['device_type']} {$device['brand']} {$device['model']}\n";
                        $telegramMessage .= "Стоимость: " . formatPrice($finalCost) . "\n";
                        $telegramMessage .= "Дата завершения: " . date('d.m.Y H:i') . "\n";
                        
                        // Send Telegram notification
                        if (!sendTelegramNotification($adminChatId, $telegramMessage)) {
                            error_log("Failed to send Telegram notification for order completion #$orderId");
                        }
                    } else {
                        error_log("Admin Telegram chat ID not found in settings");
                    }
                    
                    // Send notification to user
                    sendNotification($userId, "Заказ #{$orderId} завершен. Устройство готово к выдаче.", 
                        'order_completed', $orderId);
                }
                
                $conn->commit();
                $_SESSION['message'] = $orderId ? 'Заказ обновлен' : 'Заказ создан';
                redirect('/admin/orders.php');
            } else {
                throw new Exception('Ошибка при сохранении заказа');
            }
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = $e->getMessage();
        }
    }
}

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<div class="container mt-4">
    <h1><?= $orderId ? 'Редактировать заказ #'.$orderId : 'Создать заказ' ?></h1>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        
        <div class="mb-3">
            <label for="user_id" class="form-label">Клиент</label>
            <select class="form-select" id="user_id" name="user_id" required>
                <option value="">Выберите клиента</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= $user['id'] ?>" <?= $order && $order['user_id'] == $user['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($user['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="mb-3">
            <label for="device_id" class="form-label">Устройство</label>
            <select class="form-select" id="device_id" name="device_id" required>
                <option value="">Выберите устройство</option>
                <?php foreach ($devices as $device): ?>
                    <option value="<?= $device['id'] ?>" <?= $order && $order['device_id'] == $device['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars("{$device['user_name']} - {$device['brand']} {$device['model']}") ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="mb-3">
            <label for="problem_type_id" class="form-label">Тип проблемы</label>
            <select class="form-select" id="problem_type_id" name="problem_type_id" required>
                <option value="">Выберите тип проблемы</option>
                <?php foreach ($problemTypes as $type): ?>
                    <option value="<?= $type['id'] ?>" <?= $order && $order['problem_type_id'] == $type['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($type['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="mb-3">
            <label for="issue_description" class="form-label">Описание проблемы</label>
            <textarea class="form-control" id="issue_description" name="issue_description" rows="5" required><?= 
                htmlspecialchars($order['issue_description'] ?? '') ?></textarea>
        </div>
        
        <div class="mb-3">
            <label for="urgency" class="form-label">Срочность</label>
            <select class="form-select" id="urgency" name="urgency">
                <option value="normal" <?= $order && $order['urgency'] === 'normal' ? 'selected' : '' ?>>Обычная</option>
                <option value="high" <?= $order && $order['urgency'] === 'high' ? 'selected' : '' ?>>Срочная</option>
            </select>
        </div>
        
        <div class="mb-3">
            <label for="status" class="form-label">Статус</label>
            <select class="form-select" id="status" name="status">
                <option value="new" <?= $order && $order['status'] === 'new' ? 'selected' : '' ?>>Новая</option>
                <option value="in_progress" <?= $order && $order['status'] === 'in_progress' ? 'selected' : '' ?>>В работе</option>
                <option value="completed" <?= $order && $order['status'] === 'completed' ? 'selected' : '' ?>>Завершена</option>
                <option value="cancelled" <?= $order && $order['status'] === 'cancelled' ? 'selected' : '' ?>>Отменена</option>
            </select>
        </div>
        
        <div class="mb-3">
            <label for="technician_id" class="form-label">Мастер</label>
            <select class="form-select" id="technician_id" name="technician_id">
                <option value="">Без мастера</option>
                <?php foreach ($technicians as $tech): ?>
                    <option value="<?= $tech['id'] ?>" <?= $order && $order['technician_id'] == $tech['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tech['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="mb-3">
            <label for="diagnostic_report" class="form-label">Диагностика</label>
            <textarea class="form-control" id="diagnostic_report" name="diagnostic_report" rows="4"><?= 
                htmlspecialchars($order['diagnostic_report'] ?? '') ?></textarea>
        </div>
        
        <div class="mb-3">
            <label for="admin_notes" class="form-label">Заметки администратора</label>
            <textarea class="form-control" id="admin_notes" name="admin_notes" rows="4"><?= 
                htmlspecialchars($order['admin_notes'] ?? '') ?></textarea>
        </div>
        
        <div class="mb-3">
            <label for="final_cost" class="form-label">Итоговая стоимость</label>
            <input type="number" class="form-control" id="final_cost" name="final_cost" step="0.01" value="<?= 
                htmlspecialchars($order['final_cost'] ?? '0.00') ?>">
        </div>
        
        <div class="mb-3">
            <label for="warranty_period" class="form-label">Гарантия (мес.)</label>
            <input type="number" class="form-control" id="warranty_period" name="warranty_period" value="<?= 
                htmlspecialchars($order['warranty_period'] ?? '0') ?>">
        </div>
        
        <div class="mb-3">
            <label class="form-label">Услуги</label>
            <?php foreach ($services as $service): ?>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="services[<?= $service['id'] ?>]" 
                           id="service_<?= $service['id'] ?>" <?= $order && in_array($service['id'], array_column($conn->query("SELECT service_id FROM order_work_details WHERE order_id = $orderId")->fetch_all(MYSQLI_ASSOC), 'service_id')) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="service_<?= $service['id'] ?>">
                        <?= htmlspecialchars($service['service_name']) ?> (<?= formatPrice($service['price']) ?>)
                    </label>
                    <input type="number" class="form-control mt-1" name="services[<?= $service['id'] ?>]" 
                           placeholder="Стоимость" value="<?= $order ? ($conn->query("SELECT cost FROM order_work_details WHERE order_id = $orderId AND service_id = {$service['id']}")->fetch_assoc()['cost'] ?? $service['price']) : $service['price'] ?>">
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary">Сохранить</button>
            <a href="/admin/orders.php" class="btn btn-secondary">Отмена</a>
        </div>
    </form>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>