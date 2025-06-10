<?php
require_once '../includes/header.php';

$pageTitle = 'Контакты';

// Получаем настройки компании
$companySettings = [];
$result = $conn->query("SELECT * FROM settings WHERE is_public = 1");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $companySettings[$row['setting_key']] = $row['setting_value'];
    }
}
?>

<section class="contacts-section py-5">
    <div class="container">
        <h1 class="text-center mb-5">Контакты</h1>
        
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h3 class="card-title">Контактная информация</h3>
                        <ul class="list-unstyled">
                            <li class="mb-3">
                                <strong>Адрес:</strong> г. Москва, ул. Примерная, д. 123, офис 45
                            </li>
                            <li class="mb-3">
                                <strong>Телефон:</strong> <a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $companySettings['company_phone'] ?? '')) ?>"><?= htmlspecialchars($companySettings['company_phone'] ?? '') ?></a>
                            </li>
                            <li class="mb-3">
                                <strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($companySettings['company_email'] ?? '') ?>"><?= htmlspecialchars($companySettings['company_email'] ?? '') ?></a>
                            </li>
                            <li class="mb-3">
                                <strong>Часы работы:</strong> <?= htmlspecialchars($companySettings['work_hours'] ?? '') ?>
                            </li>
                        </ul>
                        
                        <h4 class="mt-4">Реквизиты</h4>
                        <ul class="list-unstyled">
                            <li><strong>ИНН:</strong> 1234567890</li>
                            <li><strong>ОГРН:</strong> 1234567890123</li>
                            <li><strong>Расчетный счет:</strong> 40702810123456789012</li>
                            <li><strong>Банк:</strong> ПАО "Сбербанк"</li>
                            <li><strong>БИК:</strong> 044525225</li>
                            <li><strong>Корр. счет:</strong> 30101810400000000225</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h3 class="card-title">Напишите нам</h3>
                        <form id="contactForm" action="/includes/send_contact.php" method="POST">
                            <div class="mb-3">
                                <label for="name" class="form-label">Ваше имя</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">Телефон</label>
                                <input type="tel" class="form-control" id="phone" name="phone">
                            </div>
                            <div class="mb-3">
                                <label for="message" class="form-label">Сообщение</label>
                                <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Отправить</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body p-0">
                        <div class="map-container">
                            <iframe src="https://yandex.ru/map-widget/v1/?um=constructor%3A1a2b3c4d5e6f7g8h9i0j&amp;source=constructor" width="100%" height="400" frameborder="0"></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once '../includes/footer.php'; ?>