<?php
require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/auth_check.php';
checkAdmin();

$services = $conn->query("SELECT * FROM services ORDER BY category, service_name")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <h1>Управление услугами</h1>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5>Список услуг</h5>
            <a href="/admin/service_edit.php" class="btn btn-primary">Добавить услугу</a>
        </div>
        <div class="card-body">
            <?php if (!empty($services)): ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Название</th>
                            <th>Категория</th>
                            <th>Цена</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $service): ?>
                            <tr>
                                <td><?= htmlspecialchars($service['service_name']) ?></td>
                                <td><?= htmlspecialchars($service['category']) ?></td>
                                <td><?= formatPrice($service['price']) ?></td>
                                <td><?= $service['is_active'] ? 'Активна' : 'Неактивна' ?></td>
                                <td>
                                    <a href="/admin/service_edit.php?id=<?= $service['id'] ?>" class="btn btn-sm btn-primary">Редактировать</a>
                                    <button class="btn btn-sm btn-danger delete-service" data-id="<?= $service['id'] ?>">Удалить</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-muted">Нет услуг</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.delete-service').click(function() {
        if (confirm('Вы уверены, что хотите удалить эту услугу?')) {
            var serviceId = $(this).data('id');
            $.post('/admin/service_delete.php', { id: serviceId }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Ошибка при удалении услуги');
                }
            });
        }
    });
});
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>