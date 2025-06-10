<?php
require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/auth_check.php';
checkClient();

$orderId = $_GET['order_id'] ?? 0;
$userId = $_SESSION['user_id'];

// Проверяем, что заказ принадлежит пользователю и требует оплаты
$orderStmt = $conn->prepare("
    SELECT o.id, o.final_cost, o.payment_status, d.brand, d.model
    FROM orders o
    JOIN devices d ON o.device_id = d.id
    WHERE o.id = ? AND o.user_id = ? AND o.final_cost > 0 AND o.payment_status != 'paid'
");
$orderStmt->bind_param("ii", $orderId, $userId);
$orderStmt->execute();
$order = $orderStmt->get_result()->fetch_assoc();

if (!$order) {
    $_SESSION['error'] = 'Заказ не найден или не требует оплаты';
    redirect('/client/dashboard.php');
}

// Обработка платежа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentMethod = sanitizeInput($_POST['payment_method']);
    $transactionId = sanitizeInput($_POST['transaction_id'] ?? '');
    
    // В реальном приложении здесь была бы интеграция с платежной системой
    // Для демо просто отмечаем как оплаченное
    
    $conn->begin_transaction();
    
    try {
        // Обновляем статус заказа
        $conn->query("
            UPDATE orders 
            SET payment_status = 'paid', 
                payment_method = '$paymentMethod',
                updated_at = NOW()
            WHERE id = $orderId
        ");
        
        // Добавляем запись в историю платежей
        $conn->query("
            INSERT INTO payment_history (
                order_id, amount, payment_date, payment_method, 
                transaction_id, status
            ) VALUES (
                $orderId, {$order['final_cost']}, NOW(), '$paymentMethod',
                '$transactionId', 'completed'
            )
        ");
        
        // Добавляем запись в историю заказа
        $conn->query("
            INSERT INTO order_history (
                order_id, user_id, action, comment
            ) VALUES (
                $orderId, $userId, 'Оплата', 
                'Заказ оплачен ($paymentMethod)'
            )
        ");
        
        // Отправляем уведомление
        sendNotification($userId, "Заказ #{$orderId} оплачен", 'payment_received', $orderId);
        
        $conn->commit();
        
        $_SESSION['message'] = 'Оплата прошла успешно!';
        redirect("/client/order_details.php?id=$orderId");
    } catch (Exception $e) {
        $conn->rollback();
        $error = 'Ошибка при обработке платежа. Пожалуйста, попробуйте позже.';
    }
}
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4>Оплата заказа #<?= $orderId ?></h4>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <div class="mb-4">
                        <h5><?= htmlspecialchars($order['brand']) ?> <?= htmlspecialchars($order['model']) ?></h5>
                        <p class="lead">Сумма к оплате: <?= formatPrice($order['final_cost']) ?></p>
                    </div>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label for="payment_method" class="form-label">Способ оплаты</label>
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                <option value="">Выберите способ оплаты</option>
                                <option value="credit_card">Банковская карта</option>
                                <option value="yoomoney">ЮMoney</option>
                                <option value="sbp">Система быстрых платежей</option>
                                <option value="cash">Наличные в сервисе</option>
                            </select>
                        </div>
                        
                        <div id="cardFields" class="mb-3" style="display: none;">
                            <div class="mb-3">
                                <label for="card_number" class="form-label">Номер карты</label>
                                <input type="text" class="form-control" id="card_number" 
                                       placeholder="0000 0000 0000 0000" data-mask="0000 0000 0000 0000">
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="card_expiry" class="form-label">Срок действия</label>
                                    <input type="text" class="form-control" id="card_expiry" 
                                           placeholder="MM/YY" data-mask="00/00">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="card_cvv" class="form-label">CVV/CVC</label>
                                    <input type="text" class="form-control" id="card_cvv" 
                                           placeholder="000" data-mask="000">
                                </div>
                            </div>
                        </div>
                        
                        <div id="transactionIdField" class="mb-3" style="display: none;">
                            <label for="transaction_id" class="form-label">Номер транзакции</label>
                            <input type="text" class="form-control" id="transaction_id" name="transaction_id">
                        </div>
                        
                        <div class="alert alert-info">
                            <small>
                                После нажатия кнопки "Оплатить" вы будете перенаправлены на страницу платежной системы.
                                Если оплата не прошла, свяжитесь с нами по телефону.
                            </small>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success btn-lg">Оплатить</button>
                            <a href="/client/order_details.php?id=<?= $orderId ?>" 
                               class="btn btn-outline-secondary">Вернуться к заказу</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Показываем/скрываем дополнительные поля в зависимости от способа оплаты
    $('#payment_method').change(function() {
        const method = $(this).val();
        $('#cardFields').toggle(method === 'credit_card');
        $('#transactionIdField').toggle(method === 'yoomoney' || method === 'sbp');
    });
    
    // Маска для полей ввода
    $('[data-mask]').each(function() {
        $(this).mask($(this).data('mask'));
    });
});
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>