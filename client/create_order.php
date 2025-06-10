<?php
require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__ . '/../includes/notifications.php';
checkClient();

$userId = $_SESSION['user_id'];
$errors = [];
$success = false;

// Получаем устройства пользователя
$devicesStmt = $conn->prepare("
    SELECT d.id, d.brand, d.model, dt.name as type
    FROM devices d
    JOIN device_types dt ON d.device_type_id = dt.id
    WHERE d.user_id = ?
");
$devicesStmt->bind_param("i", $userId);
$devicesStmt->execute();
$userDevices = $devicesStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Получаем типы проблем
$problemTypes = $conn->query("
    SELECT id, name, category 
    FROM problem_types 
    ORDER BY category, name
")->fetch_all(MYSQLI_ASSOC);

// Получаем популярные услуги
$popularServices = $conn->query("
    SELECT id, service_name, price, min_price 
    FROM services 
    WHERE is_popular = 1 AND is_active = 1
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deviceId = (int)$_POST['device_id'];
    $problemTypeId = (int)$_POST['problem_type_id'];
    $description = sanitizeInput($_POST['description']);
    $urgency = sanitizeInput($_POST['urgency']);
    $selectedServices = $_POST['services'] ?? [];
    
    // Валидация
    if (empty($deviceId)) {
        $errors[] = 'Выберите устройство';
    }
    
    if (empty($problemTypeId)) {
        $errors[] = 'Выберите тип проблемы';
    }
    
    if (strlen($description) < 10) {
        $errors[] = 'Описание проблемы должно содержать минимум 10 символов';
    }
    
    if (empty($errors)) {
        // Создаем заказ
        $stmt = $conn->prepare("
            INSERT INTO orders (
                user_id, device_id, problem_type_id, issue_description, 
                urgency, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, 'new', NOW(), NOW())
        ");
        $stmt->bind_param("iiiss", $userId, $deviceId, $problemTypeId, $description, $urgency);
        
        if ($stmt->execute()) {
            $orderId = $stmt->insert_id;
            
            // Добавляем выбранные услуги
            if (!empty($selectedServices)) {
                foreach ($selectedServices as $serviceId) {
                    $serviceId = (int)$serviceId;
                    $serviceStmt = $conn->prepare("
                        INSERT INTO order_work_details (order_id, service_id, cost)
                        SELECT ?, id, price FROM services WHERE id = ?
                    ");
                    $serviceStmt->bind_param("ii", $orderId, $serviceId);
                    $serviceStmt->execute();
                }
            }
            
            // Обработка загруженных файлов
            if (!empty($_FILES['attachments']['name'][0])) {
                $uploadDir = __DIR__.'/../uploads/orders/'.$orderId.'/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                foreach ($_FILES['attachments']['tmp_name'] as $key => $tmpName) {
                    if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                        $originalName = basename($_FILES['attachments']['name'][$key]);
                        $fileExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                        $newName = uniqid().'.'.$fileExt;
                        $filePath = $uploadDir.$newName;
                        
                        if (move_uploaded_file($tmpName, $filePath)) {
                            $fileType = $_FILES['attachments']['type'][$key];
                            $fileSize = $_FILES['attachments']['size'][$key];
                            
                            $fileStmt = $conn->prepare("
                                INSERT INTO order_attachments (
                                    order_id, file_path, original_name, 
                                    file_type, file_size, uploaded_at
                                ) VALUES (?, ?, ?, ?, ?, NOW())
                            ");
                            $relativePath = '/uploads/orders/'.$orderId.'/'.$newName;
                            $fileStmt->bind_param(
                                "isssi", 
                                $orderId, 
                                $relativePath, 
                                $originalName,
                                $fileType,
                                $fileSize
                            );
                            $fileStmt->execute();
                        }
                    }
                }
            }
            
$adminChatIdResult = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'admin_telegram_chat_id'");
            $adminChatId = $adminChatIdResult->fetch_assoc()['setting_value'] ?? '';
            
            if ($adminChatId) {
                // Prepare Telegram notification message
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
                $telegramMessage = "🔔 <b>Новая заявка #{$orderId}</b>\n";
                $telegramMessage .= "Клиент: {$user['name']}\n";
                $telegramMessage .= "Устройство: {$device['device_type']} {$device['brand']} {$device['model']}\n";
                $telegramMessage .= "Проблема: {$description}\n";
                $telegramMessage .= "Срочность: " . ($urgency === 'high' ? 'Срочная' : 'Обычная') . "\n";
                $telegramMessage .= "Дата: " . date('d.m.Y H:i') . "\n";
                
                // Send Telegram notification
                if (!sendTelegramNotification($adminChatId, $telegramMessage)) {
                    error_log("Failed to send Telegram notification for order creation #$orderId");
                }
            } else {
                error_log("Admin Telegram chat ID not found in settings");
            }
            
            // Send notification to user
            if (sendNotification($userId, "Заявка #{$orderId} успешно создана", 'order_created', $orderId)) {
                $success = true;
                $_SESSION['message'] = "Заявка #{$orderId} успешно создана!";
                redirect("/client/order_details.php?id={$orderId}");
            } else {
                $errors[] = 'Ошибка при отправке уведомления. Заявка создана, но уведомление не отправлено.';
            }
        }
    }
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4>Создание заявки на ремонт</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="device_id" class="form-label">Устройство</label>
                            <select class="form-select" id="device_id" name="device_id" required>
                                <option value="">Выберите устройство</option>
                                <?php foreach ($userDevices as $device): ?>
                                    <option value="<?= $device['id'] ?>" <?= 
                                        isset($_POST['device_id']) && $_POST['device_id'] == $device['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars("{$device['type']} {$device['brand']} {$device['model']}") ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                <a href="/client/my_devices.php?add_to_order=1">Добавить новое устройство</a>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="problem_type_id" class="form-label">Тип проблемы</label>
                            <select class="form-select" id="problem_type_id" name="problem_type_id" required>
                                <option value="">Выберите тип проблемы</option>
                                <?php 
                                $currentCategory = '';
                                foreach ($problemTypes as $type): 
                                    if ($type['category'] !== $currentCategory) {
                                        if ($currentCategory !== '') echo '</optgroup>';
                                        echo '<optgroup label="'.htmlspecialchars($type['category']).'">';
                                        $currentCategory = $type['category'];
                                    }
                                ?>
                                    <option value="<?= $type['id'] ?>" <?= 
                                        isset($_POST['problem_type_id']) && $_POST['problem_type_id'] == $type['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($type['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if ($currentCategory !== '') echo '</optgroup>'; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Подробное описание проблемы</label>
                            <textarea class="form-control" id="description" name="description" rows="5" required><?= 
                                htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                            <div class="form-text">
                                Опишите симптомы проблемы, когда она появилась, что предшествовало поломке
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="urgency" class="form-label">Срочность</label>
                            <select class="form-select" id="urgency" name="urgency">
                                <option value="normal" <?= 
                                    (!isset($_POST['urgency']) || $_POST['urgency'] === 'normal') ? 'selected' : '' ?>>Обычная</option>
                                <option value="high" <?= 
                                    isset($_POST['urgency']) && $_POST['urgency'] === 'high' ? 'selected' : '' ?>>Срочная (+20% к стоимости)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Рекомендуемые услуги</label>
                            <div class="list-group">
                                <?php foreach ($popularServices as $service): ?>
                                    <label class="list-group-item">
                                        <input class="form-check-input me-1" type="checkbox" 
                                               name="services[]" value="<?= $service['id'] ?>">
                                        <?= htmlspecialchars($service['service_name']) ?> - 
                                        <?= $service['min_price'] < $service['price'] 
                                            ? 'от '.formatPrice($service['min_price']) 
                                            : formatPrice($service['price']) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="form-text">
                                <a href="/pages/pricing.php">Посмотреть все услуги и цены</a>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="attachments" class="form-label">Прикрепить файлы</label>
                            <input class="form-control" type="file" id="attachments" 
                                   name="attachments[]" multiple accept="image/*,.pdf,.doc,.docx">
                            <div class="form-text">
                                Максимум 5 файлов (фото, PDF, DOC). До 5MB каждый.
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">Создать заявку</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>