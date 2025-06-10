<?php
require_once __DIR__.'/../includes/header.php';
require_once __DIR__ . '/../includes/notifications.php';

// Если пользователь уже авторизован - перенаправляем
if (isLoggedIn()) {
    redirect(isAdmin() ? '/admin/dashboard.php' : '/client/dashboard.php');
}

$errors = [];
$formData = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'address' => ''
];

// Обработка формы регистрации
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверка CSRF-токена
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Ошибка безопасности. Пожалуйста, попробуйте еще раз.';
    } else {
        // Валидация данных
        $formData['name'] = sanitizeInput($_POST['name']);
        $formData['email'] = sanitizeInput($_POST['email']);
        $formData['phone'] = sanitizeInput($_POST['phone']);
        $formData['address'] = sanitizeInput($_POST['address']);
        $password = $_POST['password'];
        $passwordConfirm = $_POST['password_confirm'];
        
        // Проверка имени
        if (empty($formData['name'])) {
            $errors[] = 'Введите ваше имя.';
        }
        
        // Проверка email
        if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Введите корректный email.';
        } else {
            // Проверка на существующий email
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $formData['email']);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $errors[] = 'Этот email уже зарегистрирован.';
            }
        }
        
        // Проверка пароля
        if (strlen($password) < 8) {
            $errors[] = 'Пароль должен содержать минимум 8 символов.';
        } elseif ($password !== $passwordConfirm) {
            $errors[] = 'Пароли не совпадают.';
        }
        
        // Если ошибок нет - регистрируем пользователя
        if (empty($errors)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $role = 'client'; // Все новые пользователи - клиенты
            
            $stmt = $conn->prepare("
                INSERT INTO users (name, email, phone, password, role, address, created_at, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), 1)
            ");
            $stmt->bind_param(
                "ssssss", 
                $formData['name'], 
                $formData['email'], 
                $formData['phone'], 
                $hashedPassword, 
                $role,
                $formData['address']
            );
            
            if ($stmt->execute()) {
                // Автоматическая авторизация после регистрации
                $userId = $stmt->insert_id;
                
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_email'] = $formData['email'];
                $_SESSION['user_name'] = $formData['name'];
                $_SESSION['user_role'] = $role;
                
                // Отправляем уведомление
                sendNotification($userId, 'Добро пожаловать в наш сервисный центр!');
                
                // Перенаправление
                $redirectUrl = $_SESSION['redirect_url'] ?? '/client/dashboard.php';
                unset($_SESSION['redirect_url']);
                redirect($redirectUrl);
            } else {
                $errors[] = 'Ошибка при регистрации. Пожалуйста, попробуйте позже.';
            }
        }
    }
}

// Генерация CSRF-токена
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Регистрация</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">ФИО</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?= htmlspecialchars($formData['name']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?= htmlspecialchars($formData['email']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Телефон</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?= htmlspecialchars($formData['phone']) ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Адрес</label>
                            <textarea class="form-control" id="address" name="address" rows="2"><?= 
                                htmlspecialchars($formData['address']) ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Пароль</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <div class="form-text">Минимум 8 символов</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password_confirm" class="form-label">Подтверждение пароля</label>
                            <input type="password" class="form-control" id="password_confirm" 
                                   name="password_confirm" required>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Зарегистрироваться</button>
                        </div>
                    </form>
                    
                    <hr>
                    
                    <div class="text-center">
                        Уже есть аккаунт? <a href="/auth/login.php">Войдите</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>