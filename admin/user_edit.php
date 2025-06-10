<?php
require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/auth_check.php';
checkAdmin();

$userId = $_GET['id'] ?? 0;
$errors = [];
$user = null;

if ($userId) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $role = sanitizeInput($_POST['role']);
    $notificationMethod = sanitizeInput($_POST['preferred_notification_method']);
    $password = $_POST['password'] ? password_hash($_POST['password'], PASSWORD_DEFAULT) : ($user['password'] ?? '');
    
    if (empty($name)) $errors[] = 'Введите имя';
    if (empty($email)) $errors[] = 'Введите email';
    if (!$userId && empty($_POST['password'])) $errors[] = 'Введите пароль для нового пользователя';
    
    if (empty($errors)) {
        if ($userId) {
            $stmt = $conn->prepare("
                UPDATE users 
                SET name = ?, email = ?, phone = ?, role = ?, preferred_notification_method = ?, password = ?
                WHERE id = ?
            ");
            $stmt->bind_param("ssssssi", $name, $email, $phone, $role, $notificationMethod, $password, $userId);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO users (name, email, phone, role, preferred_notification_method, password)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("ssssss", $name, $email, $phone, $role, $notificationMethod, $password);
        }
        
        if ($stmt->execute()) {
            $_SESSION['message'] = $userId ? 'Пользователь обновлен' : 'Пользователь добавлен';
            redirect('/admin/users.php');
        } else {
            $errors[] = 'Ошибка при сохранении пользователя';
        }
    }
}

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<div class="container mt-4">
    <h1><?= $userId ? 'Редактировать пользователя' : 'Добавить пользователя' ?></h1>
    
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
            <label for="name" class="form-label">Имя</label>
            <input type="text" class="form-control" id="name" name="name" 
                   value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
        </div>
        
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" name="email" 
                   value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
        </div>
        
        <div class="mb-3">
            <label for="phone" class="form-label">Телефон</label>
            <input type="tel" class="form-control" id="phone" name="phone" 
                   value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
        </div>
        
        <div class="mb-3">
            <label for="role" class="form-label">Роль</label>
            <select class="form-select" id="role" name="role" required>
                <option value="client" <?= $user && $user['role'] === 'client' ? 'selected' : '' ?>>Клиент</option>
                <option value="admin" <?= $user && $user['role'] === 'admin' ? 'selected' : '' ?>>Администратор</option>
                <option value="technician" <?= $user && $user['role'] === 'technician' ? 'selected' : '' ?>>Мастер</option>
            </select>
        </div>
        
        <div class="mb-3">
            <label for="preferred_notification_method" class="form-label">Метод уведомлений</label>
            <select class="form-select" id="preferred_notification_method" name="preferred_notification_method">
                <option value="email" <?= $user && $user['preferred_notification_method'] === 'email' ? 'selected' : '' ?>>Email</option>
                <option value="sms" <?= $user && $user['preferred_notification_method'] === 'sms' ? 'selected' : '' ?>>SMS</option>
                <option value="both" <?= $user && $user['preferred_notification_method'] === 'both' ? 'selected' : '' ?>>Оба</option>
            </select>
        </div>
        
        <div class="mb-3">
            <label for="password" class="form-label">Пароль <?= $userId ? '(оставьте пустым, чтобы не менять)' : '' ?></label>
            <input type="password" class="form-control" id="password" name="password">
        </div>
        
        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary">Сохранить</button>
            <a href="/admin/users.php" class="btn btn-secondary">Отмена</a>
        </div>
    </form>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>