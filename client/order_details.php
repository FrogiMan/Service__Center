<?php
require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/auth_check.php';
checkClient();

$orderId = $_GET['id'] ?? 0;
$userId = $_SESSION['user_id'];

// Проверяем, что заказ принадлежит пользователю
$orderStmt = $conn->prepare("
    SELECT o.*, d.brand, d.model, dt.name as device_type, pt.name as problem_type,
           u.name as technician_name, u.phone as technician_phone
    FROM orders o
    JOIN devices d ON o.device_id = d.id
    JOIN device_types dt ON d.device_type_id = dt.id
    JOIN problem_types pt ON o.problem_type_id = pt.id
    LEFT JOIN users u ON o.technician_id = u.id
    WHERE o.id = ? AND o.user_id = ?
");
$orderStmt->bind_param("ii", $orderId, $userId);
$orderStmt->execute();
$order = $orderStmt->get_result()->fetch_assoc();

if (!$order) {
    $_SESSION['error'] = 'Заказ не найден или у вас нет доступа';
    redirect('/client/dashboard.php');
}

// Получаем услуги по заказу
$services = $conn->query("
    SELECT ow.*, s.service_name, s.description
    FROM order_work_details ow
    LEFT JOIN services s ON ow.service_id = s.id
    WHERE ow.order_id = $orderId
")->fetch_all(MYSQLI_ASSOC);

// Получаем использованные запчасти
$parts = $conn->query("
    SELECT op.*, i.part_name, i.part_number, i.price as catalog_price
    FROM order_parts op
    JOIN inventory i ON op.part_id = i.id
    WHERE op.order_id = $orderId
")->fetch_all(MYSQLI_ASSOC);

// Получаем историю заказа
$history = $conn->query("
    SELECT * FROM order_history 
    WHERE order_id = $orderId
    ORDER BY created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Получаем вложения
$attachments = $conn->query("
    SELECT * FROM order_attachments 
    WHERE order_id = $orderId
    ORDER BY uploaded_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Получаем платежи
$payments = $conn->query("
    SELECT * FROM payment_history 
    WHERE order_id = $orderId
    ORDER BY payment_date DESC
")->fetch_all(MYSQLI_ASSOC);

// Помечаем уведомления по этому заказу как прочитанные
$conn->query("
    UPDATE notifications 
    SET is_read = 1 
    WHERE user_id = $userId AND related_order_id = $orderId
");
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Заявка #<?= $orderId ?></h1>
        <span class="badge bg-<?= 
            $order['status'] === 'completed' ? 'success' : 
            ($order['status'] === 'in_progress' ? 'primary' : 'secondary') ?>">
            <?= $order['status'] === 'completed' ? 'Завершена' : 
               ($order['status'] === 'in_progress' ? 'В работе' : 'Новая') ?>
        </span>
    </div>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Информация о заявке</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6>Устройство</h6>
                            <p><?= htmlspecialchars("{$order['device_type']} {$order['brand']} {$order['model']}") ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6>Тип проблемы</h6>
                            <p><?= htmlspecialchars($order['problem_type']) ?></p>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <h6>Описание проблемы</h6>
                        <p><?= nl2br(htmlspecialchars($order['issue_description'])) ?></p>
                    </div>
                    
                    <?php if ($order['diagnostic_report']): ?>
                        <div class="mb-3">
                            <h6>Диагностика</h6>
                            <p><?= nl2br(htmlspecialchars($order['diagnostic_report'])) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($attachments)): ?>
                        <div class="mb-3">
                            <h6>Прикрепленные файлы</h6>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($attachments as $file): ?>
                                    <div class="border p-2">
                                        <a href="<?= htmlspecialchars($file['file_path']) ?>" target="_blank">
                                            <?= htmlspecialchars($file['original_name']) ?>
                                        </a>
                                        <div class="text-muted small"><?= formatFileSize($file['file_size']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5>История заявки</h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php foreach ($history as $event): ?>
                            <div class="timeline-item">
                                <div class="timeline-date"><?= formatDate($event['created_at'], 'd.m.Y H:i') ?></div>
                                <div class="timeline-content">
                                    <h6><?= htmlspecialchars($event['action']) ?></h6>
                                    <?php if ($event['comment']): ?>
                                        <p><?= nl2br(htmlspecialchars($event['comment'])) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="timeline-item">
                            <div class="timeline-date"><?= formatDate($order['created_at'], 'd.m.Y H:i') ?></div>
                            <div class="timeline-content">
                                <h6>Заявка создана</h6>
                            <p>Статус: Новая</p>
                            <?php if ($order['urgency'] === 'high'): ?>
                                <p class="text-danger">Срочный заказ (+20% к стоимости)</p>
                            <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Стоимость и оплата</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($services)): ?>
                        <h6>Услуги:</h6>
                        <ul class="list-group mb-3">
                            <?php foreach ($services as $service): ?>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span><?= htmlspecialchars($service['service_name'] ?? $service['custom_description']) ?></span>
                                    <span><?= formatPrice($service['cost']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    
                    <?php if (!empty($parts)): ?>
                        <h6>Запчасти:</h6>
                        <ul class="list-group mb-3">
                            <?php foreach ($parts as $part): ?>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>
                                        <?= htmlspecialchars($part['part_name']) ?> (x<?= $part['quantity'] ?>)
                                        <?php if ($part['actual_cost'] != $part['catalog_price']): ?>
                                            <br><small class="text-muted">
                                                Обычная цена: <?= formatPrice($part['catalog_price']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </span>
                                    <span><?= formatPrice($part['actual_cost'] * $part['quantity']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between fw-bold">
                        <span>Итого:</span>
                        <span><?= formatPrice($order['final_cost']) ?></span>
                    </div>
                    
                    <?php if ($order['payment_status'] !== 'paid' && $order['final_cost'] > 0): ?>
                        <div class="mt-3">
                            <a href="/client/payment.php?order_id=<?= $orderId ?>" 
                               class="btn btn-success w-100">Оплатить</a>
                        </div>
                    <?php elseif ($order['payment_status'] === 'paid'): ?>
                        <div class="alert alert-success mt-3">
                            <p class="mb-0">Оплачено <?= formatDate($order['updated_at']) ?></p>
                            <?php if (!empty($payments)): ?>
                                <div class="mt-2">
                                    <?php foreach ($payments as $payment): ?>
                                        <div class="small">
                                            <?= formatPrice($payment['amount']) ?> 
                                            (<?= $payment['payment_method'] ?>)
                                            <?= $payment['receipt_path'] 
                                                ? '<a href="'.htmlspecialchars($payment['receipt_path']).'" target="_blank">Квитанция</a>' 
                                                : '' ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($order['status'] === 'completed'): ?>
                        <div class="mt-3">
                            <a href="/client/review.php?order_id=<?= $orderId ?>" 
                               class="btn btn-outline-primary w-100">Оставить отзыв</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($order['technician_id']): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Мастер</h5>
                    </div>
                    <div class="card-body">
                        <h6><?= htmlspecialchars($order['technician_name']) ?></h6>
                        <p class="mb-1">Телефон: <?= htmlspecialchars($order['technician_phone']) ?></p>
                        <a href="#" class="btn btn-sm btn-outline-primary mt-2">Написать сообщение</a>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h5>Гарантия</h5>
                </div>
                <div class="card-body">
                    <?php if ($order['warranty_period'] > 0): ?>
                        <p>Гарантия <?= $order['warranty_period'] ?> мес.</p>
                        <p>До <?= date('d.m.Y', strtotime($order['completion_date'] . " + {$order['warranty_period']} months")) ?></p>
                        
                        <?php if (strtotime($order['completion_date'] . " + {$order['warranty_period']} months") > time()): ?>
                            <a href="/client/warranty_claim.php?order_id=<?= $orderId ?>" 
                               class="btn btn-sm btn-warning">Гарантийный случай</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>Без гарантии</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 20px;
    border-left: 2px solid #dee2e6;
}
.timeline-item {
    position: relative;
    padding-bottom: 20px;
}
.timeline-date {
    font-size: 0.8rem;
    color: #6c757d;
    margin-bottom: 5px;
}
.timeline-content {
    background: #f8f9fa;
    padding: 10px 15px;
    border-radius: 5px;
}
.timeline-item:last-child {
    padding-bottom: 0;
}
</style>

<?php require_once __DIR__.'/../includes/footer.php'; ?>