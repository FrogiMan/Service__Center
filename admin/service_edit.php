<?php
require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/auth_check.php';
checkAdmin();

$serviceId = $_GET['id'] ?? 0;
$errors = [];
$service = null;

if ($serviceId) {
    $stmt = $conn->prepare("SELECT * FROM services WHERE id = ?");
    $stmt->bind_param("i", $serviceId);
    $stmt->execute();
    $service = $stmt->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    
    $serviceName = sanitizeInput($_POST['service_name']);
    $category = sanitizeInput($_POST['category']);
    $description = sanitizeInput($_POST['description']);
    $price = (float)$_POST['price'];
    $minPrice = (float)$_POST['min_price'];
    $details = sanitizeInput($_POST['details']);
    $estimatedTime = sanitizeInput($_POST['estimated_time']);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $isPopular = isset($_POST['is_popular']) ? 1 : 0;
    
    if (empty($serviceName)) $errors[] = 'Введите название услуги';
    if (empty($category)) $errors[] = 'Введите категорию';
    if ($price < $minPrice) $errors[] = 'Минимальная цена не может быть больше обычной';
    
    if (empty($errors)) {
        if ($serviceId) {
            $stmt = $conn->prepare("
                UPDATE services 
                SET service_name = ?, category = ?, description = ?, price = ?, min_price = ?, 
                    details = ?, estimated_time = ?, is_active = ?, is_popular = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sssddssiii", $serviceName, $category, $description, $price, $minPrice, 
                $details, $estimatedTime, $isActive, $isPopular, $serviceId);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO services (
                    service_name, category, description, price, min_price, details, 
                    estimated_time, is_active, is_popular
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sssddssii", $serviceName, $category, $description, $price, $minPrice, 
                $details, $estimatedTime, $isActive, $isPopular);
        }
        
        if ($stmt->execute()) {
            $_SESSION['message'] = $serviceId ? 'Услуга обновлена' : 'Услуга добавлена';
            redirect('/admin/services.php');
        } else {
            $errors[] = 'Ошибка при сохранении услуги';
        }
    }
}

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<div class="container mt-4">
    <h1><?= $serviceId ? 'Редактировать услугу' : 'Добавить услугу' ?></h1>
    
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
            <label for="service_name" class="form-label">Название услуги</label>
            <input type="text" class="form-control" id="service_name" name="service_name" 
                   value="<?= htmlspecialchars($service['service_name'] ?? '') ?>" required>
        </div>
        
        <div class="mb-3">
            <label for="category" class="form-label">Категория</label>
            <input type="text" class="form-control" id="category" name="category" 
                   value="<?= htmlspecialchars($service['category'] ?? '') ?>" required>
        </div>
        
        <div class="mb-3">
            <label for="description" class="form-label">Описание</label>
            <textarea class="form-control" id="description" name="description" rows="4"><?= 
                htmlspecialchars($service['description'] ?? '') ?></textarea>
        </div>
        
        <div class="mb-3">
            <label for="price" class="form-label">Цена</label>
            <input type="number" class="form-control" id="price" name="price" step="0.01" 
                   value="<?= htmlspecialchars($service['price'] ?? '0.00') ?>" required>
        </div>
        
        <div class="mb-3">
            <label for="min_price" class="form-label">Минимальная цена</label>
            <input type="number" class="form-control" id="min_price" name="min_price" step="0.01" 
                   value="<?= htmlspecialchars($service['min_price'] ?? '0.00') ?>">
        </div>
        
        <div class="mb-3">
            <label for="details" class="form-label">Подробное описание</label>
            <textarea class="form-control" id="details" name="details" rows="5"><?= 
                htmlspecialchars($service['details'] ?? '') ?></textarea>
        </div>
        
        <div class="mb-3">
            <label for="estimated_time" class="form-label">Ориентировочное время выполнения</label>
            <input type="text" class="form-control" id="estimated_time" name="estimated_time" 
                   value="<?= htmlspecialchars($service['estimated_time'] ?? '') ?>">
        </div>
        
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                   <?= $service && $service['is_active'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active">Активна</label>
        </div>
        
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="is_popular" name="is_popular" 
                   <?= $service && $service['is_popular'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_popular">Популярная</label>
        </div>
        
        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary">Сохранить</button>
            <a href="/admin/services.php" class="btn btn-secondary">Отмена</a>
        </div>
    </form>
</div>

<?php require_once __DIR__.'/../includes/footer.php'; ?>