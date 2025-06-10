<?php
require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/auth_check.php';
checkAdmin();

// Получаем статистику
$stats = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM orders) as total_orders,
        (SELECT COUNT(*) FROM orders WHERE status = 'completed') as completed_orders,
        (SELECT COUNT(*) FROM users WHERE role = 'client') as total_users,
        (SELECT COUNT(*) FROM services WHERE is_active = 1) as active_services
")->fetch_assoc();

// Последние заказы
$recentOrders = $conn->query("
    SELECT o.id, o.status, o.created_at, d.brand, d.model, u.name as client_name
    FROM orders o
    JOIN devices d ON o.device_id = d.id
    JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

?>

<div class="container mt-4">
    <h1>Админ-панель</h1>
    
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Всего заказов</h5>
                    <p class="display-4"><?= $stats['total_orders'] ?></p>
                    <a href="/admin/orders.php" class="text-white">Просмотреть</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Выполнено</h5>
                    <p class="display-4"><?= $stats['completed_orders'] ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Клиентов</h5>
                    <p class="display-4"><?= $stats['total_users'] ?></p>
                    <a href="/admin/users.php" class="text-white">Просмотреть</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Активных услуг</h5>
                    <p class="display-4"><?= $stats['active_services'] ?></p>
                    <a href="/admin/services.php" class="text-white">Просмотреть</a>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5>Последние заказы</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($recentOrders)): ?>
                <div class="list-group">
                    <?php foreach ($recentOrders as $order): ?>
                        <a href="/admin/order_edit.php?id=<?= $order['id'] ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">Заказ #<?= $order['id'] ?> - <?= htmlspecialchars($order['client_name']) ?></h6>
                                <small><?= formatDate($order['created_at']) ?></small>
                            </div>
                            <p class="mb-1"><?= htmlspecialchars($order['brand']) ?> <?= htmlspecialchars($order['model']) ?></p>
                            <p class="mb-1">Статус: <?= getOrderStatusBadge($order['status']) ?></p>
                        </a>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3 text-center">
                    <a href="/admin/orders.php" class="btn btn-outline-primary">Все заказы</a>
                </div>
            <?php else: ?>
                <p class="text-muted">Нет заказов</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>