<?php
require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/auth_check.php';
checkAdmin();

// Получаем все заказы
$orders = $conn->query("
    SELECT o.id, o.status, o.created_at, o.final_cost, d.brand, d.model, u.name as client_name
    FROM orders o
    JOIN devices d ON o.device_id = d.id
    JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

?>

<div class="container mt-4">
    <h1>Управление заказами</h1>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5>Список заказов</h5>
            <a href="/admin/order_edit.php" class="btn btn-primary">Создать заказ</a>
        </div>
        <div class="card-body">
            <?php if (!empty($orders)): ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Клиент</th>
                            <th>Устройство</th>
                            <th>Статус</th>
                            <th>Стоимость</th>
                            <th>Дата</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?= $order['id'] ?></td>
                                <td><?= htmlspecialchars($order['client_name']) ?></td>
                                <td><?= htmlspecialchars($order['brand']) ?> <?= htmlspecialchars($order['model']) ?></td>
                                <td><?= getOrderStatusBadge($order['status']) ?></td>
                                <td><?= formatPrice($order['final_cost']) ?></td>
                                <td><?= formatDate($order['created_at']) ?></td>
                                <td>
                                    <a href="/admin/order_edit.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-primary">Редактировать</a>
                                    <button class="btn btn-sm btn-danger delete-order" data-id="<?= $order['id'] ?>">Удалить</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-muted">Нет заказов</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.delete-order').click(function() {
        if (confirm('Вы уверены, что хотите удалить этот заказ?')) {
            var orderId = $(this).data('id');
            $.post('/admin/order_delete.php', { id: orderId }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Ошибка при удалении заказа');
                }
            });
        }
    });
});
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>