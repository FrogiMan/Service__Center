<?php
require_once __DIR__.'/../includes/header.php';

// Если пользователь уже авторизован - перенаправляем
if (isLoggedIn()) {
    redirect(isAdmin() ? '/admin/dashboard.php' : '/client/dashboard.php');
}

$error = '';
$email = '';

// Обработка формы входа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    
    // Проверка CSRF-токена
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Ошибка безопасности. Пожалуйста, попробуйте еще раз.';
    } else {
        // Поиск пользователя в базе данных
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Проверка пароля
            if (password_verify($password, $user['password'])) {
                // Проверка активности аккаунта
                if (!$user['is_active']) {
                    $error = 'Ваш аккаунт деактивирован. Обратитесь к администратору.';
                } else {
                    // Успешная авторизация
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_role'] = $user['role'];
                    
                    // Обновляем время последней активности
                    $conn->query("UPDATE users SET last_activity = NOW() WHERE id = {$user['id']}");
                    
                    // Перенаправление
                    $redirectUrl = $_SESSION['redirect_url'] ?? 
                        (isAdmin() ? '/admin/dashboard.php' : '/client/dashboard.php');
                    unset($_SESSION['redirect_url']);
                    redirect($redirectUrl);
                }
            } else {
                $error = 'Неверный email или пароль.';
            }
        } else {
            $error = 'Неверный email или пароль.';
        }
    }
}

// Генерация CSRF-токена
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Вход в систему</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?= htmlspecialchars($email) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Пароль</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <div class="form-text">
                                <a href="/auth/password_reset.php">Забыли пароль?</a>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Войти</button>
                        </div>
                    </form>
                    
                    <hr>
                    
                    <div class="text-center">
                        Нет аккаунта? <a href="/auth/register.php">Зарегистрируйтесь</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>