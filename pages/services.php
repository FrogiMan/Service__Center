<?php
require_once '../includes/header.php';

$pageTitle = 'Наши услуги';

// Получаем категории услуг
$categories = [];
$result = $conn->query("SELECT DISTINCT category FROM services WHERE is_active = 1");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row['category'];
    }
}

// Получаем все активные услуги
$services = [];
$result = $conn->query("SELECT * FROM services WHERE is_active = 1 ORDER BY category, service_name");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $services[$row['category']][] = $row;
    }
}
?>

<section class="services-section py-5">
    <div class="container">
        <h1 class="text-center mb-5">Наши услуги</h1>
        
        <div class="row">
            <div class="col-md-3">
                <div class="sticky-top pt-3" style="top: 20px;">
                    <div class="list-group">
                        <?php foreach ($categories as $category): ?>
                            <a href="#category-<?= htmlspecialchars(str_replace(' ', '-', strtolower($category))) ?>" 
                               class="list-group-item list-group-item-action">
                                <?= htmlspecialchars($category) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-9">
                <?php foreach ($services as $category => $categoryServices): ?>
                    <div class="mb-5" id="category-<?= htmlspecialchars(str_replace(' ', '-', strtolower($category))) ?>">
                        <h2 class="mb-4"><?= htmlspecialchars($category) ?></h2>
                        
                        <div class="row">
                            <?php foreach ($categoryServices as $service): ?>
                                <div class="col-md-6 mb-4">
                                    <div class="card h-100">
                                        <?php if ($service['image_path']): ?>
                                            <img src="/<?= htmlspecialchars($service['image_path']) ?>" class="card-img-top img-service" alt="<?= htmlspecialchars($service['service_name']) ?>">
                                            <?php endif; ?>
                                        <div class="card-body">
                                            <h5 class="card-title"><?= htmlspecialchars($service['service_name']) ?></h5>
                                            <p class="card-text"><?= htmlspecialchars($service['description']) ?></p>
                                            <p class="text-primary fw-bold">
                                                <?php if ($service['min_price'] < $service['price']): ?>
                                                    От <?= formatPrice($service['min_price']) ?>
                                                <?php else: ?>
                                                    <?= formatPrice($service['price']) ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="card-footer bg-white">
                                            <a href="/client/create_order.php?service_id=<?= $service['id'] ?>" 
                                               class="btn btn-outline-primary">
                                                Заказать
                                            </a>
                                            <button class="btn btn-link text-muted float-end" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#serviceModal<?= $service['id'] ?>">
                                                Подробнее
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Модальное окно с деталями услуги -->
                                <div class="modal fade" id="serviceModal<?= $service['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title"><?= htmlspecialchars($service['service_name']) ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <?php if ($service['image_path']): ?>
                                                    <img src="/<?= htmlspecialchars($service['image_path']) ?>" class="card-img-top img-service" alt="<?= htmlspecialchars($service['service_name']) ?>">
                                                    <?php endif; ?>
                                                <p><?= htmlspecialchars($service['description']) ?></p>
                                                
                                                <?php if ($service['details']): ?>
                                                    <h6>Подробное описание:</h6>
                                                    <p><?= nl2br(htmlspecialchars($service['details'])) ?></p>
                                                <?php endif; ?>
                                                
                                                <div class="row mt-3">
                                                    <div class="col-md-6">
                                                        <p><strong>Цена:</strong> 
                                                            <?php if ($service['min_price'] < $service['price']): ?>
                                                                от <?= formatPrice($service['min_price']) ?>
                                                            <?php else: ?>
                                                                <?= formatPrice($service['price']) ?>
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <p><strong>Срок выполнения:</strong> <?= htmlspecialchars($service['estimated_time']) ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                                                <a href="/client/create_order.php?service_id=<?= $service['id'] ?>" 
                                                   class="btn btn-primary">
                                                    Заказать услугу
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once '../includes/footer.php'; ?>