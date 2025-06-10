<?php
require_once 'includes/header.php';

// Получаем популярные услуги
$popularServices = [];
$result = $conn->query("SELECT * FROM services WHERE is_popular = 1 AND is_active = 1 LIMIT 3");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $popularServices[] = $row;
    }
}

// Получаем последние отзывы
$recentReviews = [];
$result = $conn->query("SELECT r.*, u.name as user_name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.is_approved = 1 ORDER BY r.created_at DESC LIMIT 3");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recentReviews[] = $row;
    }
}

// Получаем настройки компании
$companySettings = [];
$result = $conn->query("SELECT * FROM settings WHERE is_public = 1");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $companySettings[$row['setting_key']] = $row['setting_value'];
    }
}
?>

<section class="hero-section bg-light py-5">
    <div class="container text-center">
        <h1 class="display-4"><?= htmlspecialchars($companySettings['company_name'] ?? 'Сервисный центр') ?></h1>
        <p class="lead">Профессиональный ремонт техники с гарантией</p>
        <a href="/client/create_order.php" class="btn btn-primary btn-lg mt-3">Оставить заявку</a>
    </div>
</section>

<section class="services-section py-5">
    <div class="container">
        <h2 class="text-center mb-5">Популярные услуги</h2>
        <div class="row">
            <?php foreach ($popularServices as $service): ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <img src="<?= htmlspecialchars($service['image_path']) ?>" class="card-img-top img-service" alt="<?= htmlspecialchars($service['service_name']) ?>">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($service['service_name']) ?></h5>
                            <p class="card-text"><?= htmlspecialchars($service['description']) ?></p>
                            <p class="text-primary fw-bold">От <?= formatPrice($service['min_price']) ?></p>
                        </div>
                        <div class="card-footer bg-white">
                            <a href="/client/create_order.php?service_id=<?= $service['id'] ?>" class="btn btn-outline-primary">Заказать</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="reviews-section bg-light py-5">
    <div class="container">
        <h2 class="text-center mb-5">Отзывы клиентов</h2>
        <div class="row">
            <?php foreach ($recentReviews as $review): ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="rating me-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="star <?= $i <= $review['rating'] ? 'filled' : '' ?>">★</span>
                                    <?php endfor; ?>
                                </div>
                                <span class="text-muted"><?= formatDate($review['created_at']) ?></span>
                            </div>
                            <p class="card-text">"<?= htmlspecialchars($review['comment']) ?>"</p>
                            <p class="text-end fw-bold">— <?= htmlspecialchars($review['user_name']) ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4">
            <a href="/pages/reviews.php" class="btn btn-outline-primary">Все отзывы</a>
        </div>
    </div>
</section>

<section class="contact-section py-5">
    <div class="container">
        <div class="row">
            <div class="col-md-6">
                <h2>Контакты</h2>
                <ul class="list-unstyled">
                    <li class="mb-2"><strong>Телефон:</strong> <?= htmlspecialchars($companySettings['company_phone'] ?? '') ?></li>
                    <li class="mb-2"><strong>Email:</strong> <?= htmlspecialchars($companySettings['company_email'] ?? '') ?></li>
                    <li class="mb-2"><strong>Часы работы:</strong> <?= htmlspecialchars($companySettings['work_hours'] ?? '') ?></li>
                    <li class="mb-2"><strong>Адрес:</strong> г. Москва, ул. Примерная, д. 123</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h2>Как нас найти</h2>
                <div class="map-container">
                    <iframe src="https://yandex.ru/map-widget/v1/?um=constructor%3A1a2b3c4d5e6f7g8h9i0j&amp;source=constructor" width="100%" height="300" frameborder="0"></iframe>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>