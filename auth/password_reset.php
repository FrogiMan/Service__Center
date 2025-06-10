<?php
require_once __DIR__.'/../includes/header.php';

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$message = '';
$email = '';

// Обработка формы сброса пароля
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверка CSRF-токена
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Ошибка безопасности. Пожалуйста, попробуйте еще раз.';
    } else {
        if ($step === 1) {
            // Шаг 1: Запрос на сброс пароля
            $email = sanitizeInput($_POST['email']);
            
            // Проверяем существование пользователя
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $userId = $user['id'];
                
                // Генерируем токен для сброса пароля
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Сохраняем токен в базе
                $conn->query("DELETE FROM password_resets WHERE user_id = $userId");
                $conn->query("
                    INSERT INTO password_resets (user_id, token, expires_at) 
                    VALUES ($userId, '$token', '$expires')
                ");
                
                // Отправляем email с ссылкой (в реальном приложении)
                $resetLink = "https://{$_SERVER['HTTP_HOST']}/auth/password_reset.php?step=2&token=$token";
                
                // В демо-версии просто показываем ссылку
                $message = "Ссылка для сброса пароля: <a href=\"$resetLink\">$resetLink</a>";
                $step = 2;
            } else {
                $error = 'Пользователь с таким email не найден.';
            }
        } elseif ($step === 2) {
            // Шаг 2: Установка нового пароля
            $token = $_POST['token'];
            $password = $_POST['password'];
            $passwordConfirm = $_POST['password_confirm'];
            
            // Проверяем токен
            $stmt = $conn->prepare("
                SELECT user_id FROM password_resets 
                WHERE token = ? AND expires_at > NOW()
                LIMIT 1
            ");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $row = $result->fetch_assoc();
                $userId = $row['user_id'];
                
                // Проверка пароля
                if (strlen($password) < 8) {
                    $error = 'Пароль должен содержать минимум 8 символов.';
                } elseif ($password !== $passwordConfirm) {
                    $error = 'Пароли не совпадают.';
                } else {
                    // Обновляем пароль
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $conn->query("UPDATE users SET password = '$hashedPassword' WHERE id = $userId");
                    
                    // Удаляем использованный токен
                    $conn->query("DELETE FROM password_resets WHERE user_id = $userId");
                    
                    $message = 'Пароль успешно изменен. Теперь вы можете <a href="/auth/login.php">войти</a> с новым паролем.';
                    $step = 3;
                }
            } else {
                $error = 'Недействительная или просроченная ссылка для сброса пароля.';
                $step = 1;
            }
        }
    }
}

// Генерация CSRF-токена
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <?= $step === 1 ? 'Сброс пароля' : 
                           ($step === 2 ? 'Установка нового пароля' : 'Пароль изменен') ?>
                    </h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-success"><?= $message ?></div>
                    <?php endif; ?>
                    
                    <?php if ($step === 1): ?>
                        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?step=1">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= htmlspecialchars($email) ?>" required>
                                <div class="form-text">
                                    На этот email будет отправлена ссылка для сброса пароля.
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Отправить ссылку</button>
                            </div>
                        </form>
                        
                        <hr>
                        
                        <div class="text-center">
                            <a href="/auth/login.php">Вернуться к входу</a>
                        </div>
                    
                    <?php elseif ($step === 2): ?>
                        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?step=2">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token'] ?? '') ?>">
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Новый пароль</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <div class="form-text">Минимум 8 символов</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password_confirm" class="form-label">Подтверждение пароля</label>
                                <input type="password" class="form-control" id="password_confirm" 
                                       name="password_confirm" required>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Изменить пароль</button>
                            </div>
                        </form>
                    
                    <?php elseif ($step === 3): ?>
                        <div class="text-center">
                            <a href="/auth/login.php" class="btn btn-primary">Войти в систему</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>