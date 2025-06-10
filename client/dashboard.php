<?php
require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/auth_check.php';
checkClient();

// Получаем статистику пользователя
$userId = $_SESSION['user_id'];
$userStmt = $conn->prepare("
    SELECT 
        (SELECT COUNT(*) FROM orders WHERE user_id = ?) as total_orders,
        (SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'completed') as completed_orders,
        (SELECT COUNT(*) FROM devices WHERE user_id = ?) as total_devices
");
$userStmt->bind_param("iii", $userId, $userId, $userId);
$userStmt->execute();
$stats = $userStmt->get_result()->fetch_assoc();

// Получаем последние заказы
$ordersStmt = $conn->prepare("
    SELECT o.id, o.status, o.created_at, d.brand, d.model, o.final_cost
    FROM orders o
    JOIN devices d ON o.device_id = d.id
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
    LIMIT 5
");
$ordersStmt->bind_param("i", $userId);
$ordersStmt->execute();
$lastOrders = $ordersStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Получаем непрочитанные уведомления
$notificationsStmt = $conn->prepare("
    SELECT id, message, created_at 
    FROM notifications 
    WHERE user_id = ? AND is_read = 0
    ORDER BY created_at DESC
    LIMIT 5
");
$notificationsStmt->bind_param("i", $userId);
$notificationsStmt->execute();
$notifications = $notificationsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Всего заказов</h5>
                    <p class="display-4"><?= $stats['total_orders'] ?></p>
                    <a href="/client/order_details.php" class="text-white">Просмотреть все</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Выполнено</h5>
                    <p class="display-4"><?= $stats['completed_orders'] ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Устройств</h5>
                    <p class="display-4"><?= $stats['total_devices'] ?></p>
                    <a href="/client/my_devices.php" class="text-white">Управление</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Последние заказы</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($lastOrders)): ?>
                        <div class="list-group">
                            <?php foreach ($lastOrders as $order): ?>
                                <a href="/client/order_details.php?id=<?= $order['id'] ?>" 
                                   class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?= htmlspecialchars($order['brand']) ?> <?= htmlspecialchars($order['model']) ?></h6>
                                        <small><?= formatDate($order['created_at']) ?></small>
                                    </div>
                                    <p class="mb-1">Статус: <?= getOrderStatusBadge($order['status']) ?></p>
                                    <small>Стоимость: <?= formatPrice($order['final_cost']) ?></small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-3 text-center">
                            <a href="/client/order_details.php" class="btn btn-outline-primary">Все заказы</a>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">У вас пока нет заказов</p>
                        <a href="/client/create_order.php" class="btn btn-primary">Создать заявку</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Уведомления <span class="badge bg-danger"><?= count($notifications) ?></span></h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($notifications)): ?>
                        <div class="list-group">
                            <?php foreach ($notifications as $note): ?>
                                <a href="#" class="list-group-item list-group-item-action mark-as-read" 
                                   data-id="<?= $note['id'] ?>">
                                    <div class="d-flex w-100 justify-content-between">
                                        <p class="mb-1"><?= htmlspecialchars($note['message']) ?></p>
                                        <small><?= formatDate($note['created_at'], 'H:i') ?></small>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-3 text-center">
                            <a href="#" id="mark-all-read" class="btn btn-sm btn-outline-secondary">Отметить все как прочитанные</a>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Нет непрочитанных уведомлений</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Отметить уведомление как прочитанное
    $('.mark-as-read').click(function(e) {
        e.preventDefault();
        var noteId = $(this).data('id');
        var $item = $(this);
        
        $.post('/includes/notifications.php', { action: 'mark_read', id: noteId }, function() {
            $item.removeClass('list-group-item-action').addClass('text-muted');
        });
    });
    
    // Отметить все как прочитанные
    $('#mark-all-read').click(function(e) {
        e.preventDefault();
        $.post('/includes/notifications.php', { action: 'mark_all_read' }, function() {
            $('.mark-as-read').each(function() {
                $(this).removeClass('list-group-item-action').addClass('text-muted');
            });
        });
    });
});
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>