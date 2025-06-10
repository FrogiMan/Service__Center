<?php
require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/auth_check.php';
checkAdmin();

$users = $conn->query("SELECT id, name, email, phone, role, preferred_notification_method FROM users ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <h1>Управление пользователями</h1>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5>Список пользователей</h5>
            <a href="/admin/user_edit.php" class="btn btn-primary">Добавить пользователя</a>
        </div>
        <div class="card-body">
            <?php if (!empty($users)): ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Имя</th>
                            <th>Email</th>
                            <th>Телефон</th>
                            <th>Роль</th>
                            <th>Уведомления</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['name']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><?= htmlspecialchars($user['phone']) ?></td>
                                <td><?= htmlspecialchars($user['role']) ?></td>
                                <td><?= htmlspecialchars($user['preferred_notification_method'] ?? 'email') ?></td>
                                <td>
                                    <a href="/admin/user_edit.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-primary">Редактировать</a>
                                    <button class="btn btn-sm btn-danger delete-user" data-id="<?= $user['id'] ?>">Удалить</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-muted">Нет пользователей</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.delete-user').click(function() {
        if (confirm('Вы уверены, что хотите удалить этого пользователя?')) {
            var userId = $(this).data('id');
            $.post('/admin/user_delete.php', { id: userId }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Ошибка при удалении пользователя: ' + response.error);
                }
            });
        }
    });
});
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>