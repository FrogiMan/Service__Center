<?php
require_once '../includes/header.php';

$pageTitle = 'Гарантия';

// Получаем настройки гарантии
$warrantySettings = [];
$result = $conn->query("SELECT * FROM settings WHERE setting_key LIKE 'warranty_%'");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $warrantySettings[$row['setting_key'] = $row['setting_value']];
    }
}

$defaultWarranty = $warrantySettings['default_warranty_period'] ?? 3;
?>

<section class="warranty-section py-5">
    <div class="container">
        <h1 class="text-center mb-5">Гарантия</h1>
        
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="card-title">Гарантия на ремонт</h2>
                        <p>Мы предоставляем гарантию на все выполненные работы и установленные комплектующие. Гарантийный срок составляет <?= htmlspecialchars($defaultWarranty) ?> месяца с момента завершения ремонта.</p>
                        
                        <h4 class="mt-4">Условия гарантии:</h4>
                        <ul>
                            <li>Гарантия распространяется только на выполненные нами работы и установленные комплектующие</li>
                            <li>Гарантия не распространяется на случаи механических повреждений, попадания жидкости, самостоятельного вскрытия устройства или ремонта в других сервисных центрах</li>
                            <li>Для получения гарантийного обслуживания необходимо предъявить договор или акт выполненных работ</li>
                            <li>Гарантия не распространяется на расходные материалы (батареи, аккумуляторы и т.д.)</li>
                        </ul>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="card-title">Порядок гарантийного обслуживания</h2>
                        
                        <div class="steps">
                            <div class="step">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <h5>Обращение в сервисный центр</h5>
                                    <p>При возникновении проблемы в течение гарантийного срока обратитесь в наш сервисный центр с устройством и документами.</p>
                                </div>
                            </div>
                            
                            <div class="step">
                                <div class="step-number">2</div>
                                <div class="step-content">
                                    <h5>Диагностика</h5>
                                    <p>Наши специалисты проведут бесплатную диагностику для выявления причины неисправности.</p>
                                </div>
                            </div>
                            
                            <div class="step">
                                <div class="step-number">3</div>
                                <div class="step-content">
                                    <h5>Ремонт или замена</h5>
                                    <p>Если неисправность подтвердится как гарантийный случай, мы бесплатно устраним проблему или заменим комплектующие.</p>
                                </div>
                            </div>
                            
                            <div class="step">
                                <div class="step-number">4</div>
                                <div class="step-content">
                                    <h5>Выдача устройства</h5>
                                    <p>После ремонта вы получите устройство с новым гарантийным сроком на выполненные работы.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <h2 class="card-title">Гарантийные обязательства производителей</h2>
                        <p>Если ваше устройство находится на гарантии производителя, мы можем помочь с его отправкой в авторизованный сервисный центр. В некоторых случаях мы являемся авторизованным сервисным центром и можем выполнить гарантийный ремонт на месте.</p>
                        
                        <div class="brands mt-4">
                            <h4>Сотрудничаем с брендами:</h4>
                            <div class="d-flex flex-wrap gap-3 align-items-center">
                                <img src="/assets/images/brands/apple.png" alt="Apple" class="img-brand">
                                <img src="/assets/images/brands/samsung.png" alt="Samsung" class="img-brand">
                                <img src="/assets/images/brands/hp.png" alt="HP" class="img-brand">
                                <img src="/assets/images/brands/dell.png" alt="Dell" class="img-brand">
                                <img src="/assets/images/brands/lenovo.png" alt="Lenovo" class="img-brand">
                                <img src="/assets/images/brands/asus.png" alt="Asus" class="img-brand">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once '../includes/footer.php'; ?>