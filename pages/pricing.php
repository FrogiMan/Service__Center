<?php
require_once '../includes/header.php';

$pageTitle = 'Цены на услуги';

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

<section class="pricing-section py-5">
    <div class="container">
        <h1 class="text-center mb-5">Цены на услуги</h1>
        
        <div class="alert alert-info mb-4">
            <p class="mb-0">Указанные цены являются ориентировочными. Точная стоимость ремонта определяется после диагностики устройства.</p>
        </div>
        
        <div class="row">
            <div class="col-md-3">
                <div class="sticky-top pt-3" style="top: 20px;">
                    <div class="list-group">
                        <?php foreach ($categories as $category): ?>
                            <a href="#category-<?= htmlspecialchars(str_replace(' ', '-', strtolower($category))) ?>" class="list-group-item list-group-item-action">
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
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Услуга</th>
                                        <th>Описание</th>
                                        <th>Цена</th>
                                        <th>Срок</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categoryServices as $service): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($service['service_name']) ?></td>
                                            <td><?= htmlspecialchars($service['description']) ?></td>
                                            <td>
                                                <?php if ($service['min_price'] < $service['price']): ?>
                                                    от <?= formatPrice($service['min_price']) ?>
                                                <?php else: ?>
                                                    <?= formatPrice($service['price']) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($service['estimated_time']) ?></td>
                                            <td class="text-end">
                                                <a href="/client/create_order.php?service_id=<?= $service['id'] ?>" class="btn btn-sm btn-outline-primary">Заказать</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once '../includes/footer.php'; ?>