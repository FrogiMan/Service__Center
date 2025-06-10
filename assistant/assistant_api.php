<?php
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/functions.php';

header('Content-Type: application/json');

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Метод не поддерживается']));
}

// Получение и валидация данных
$data = json_decode(file_get_contents('php://input'), true);
if (empty($data['query'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Пустой запрос']));
}

$userQuery = sanitizeInput($data['query']);
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$context = $data['context'] ?? [];

try {
    // 1. Проверка простых команд
    $simpleCommands = [
        'привет' => 'Здравствуйте! Чем могу помочь?',
        'спасибо' => 'Пожалуйста! Обращайтесь, если будут ещё вопросы.',
        'пока' => 'До свидания! Если возникнут вопросы - буду рад помочь.'
    ];
    
    foreach ($simpleCommands as $cmd => $response) {
        if (mb_stripos($userQuery, $cmd) !== false) {
            die(json_encode(['response' => $response, 'context' => $context]));
        }
    }

    // 2. Поиск в базе знаний (используем LIKE если FULLTEXT не работает)
    $searchQuery = "%$userQuery%";
    $stmt = $conn->prepare("
        SELECT id, question, answer, article_link, video_link, is_complex, related_service_id
        FROM knowledge_base
        WHERE question LIKE ? OR answer LIKE ? OR keywords LIKE ?
        ORDER BY views DESC
        LIMIT 3
    ");
    $stmt->bind_param("sss", $searchQuery, $searchQuery, $searchQuery);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Если найдены совпадения
    if (!empty($results)) {
        // Увеличиваем счетчик просмотров
        $updateStmt = $conn->prepare("UPDATE knowledge_base SET views = views + 1 WHERE id = ?");
        $updateStmt->bind_param("i", $results[0]['id']);
        $updateStmt->execute();
        
        $response = $results[0]['answer'];
        $isComplex = (bool)$results[0]['is_complex'];
        $serviceId = $results[0]['related_service_id'];
        
        // Добавляем дополнительные материалы
        $materials = [];
        if (!empty($results[0]['article_link'])) {
            $materials[] = ['type' => 'article', 'url' => $results[0]['article_link']];
        }
        if (!empty($results[0]['video_link'])) {
            $materials[] = ['type' => 'video', 'url' => $results[0]['video_link']];
        }
        
        // Если есть связанная услуга
        $serviceInfo = [];
        if ($serviceId) {
            $serviceStmt = $conn->prepare("SELECT id, service_name, price, min_price FROM services WHERE id = ?");
            $serviceStmt->bind_param("i", $serviceId);
            $serviceStmt->execute();
            $serviceResult = $serviceStmt->get_result()->fetch_assoc();
            
            if ($serviceResult) {
                $serviceInfo = [
                    'id' => $serviceResult['id'],
                    'name' => $serviceResult['service_name'],
                    'price' => $serviceResult['price'],
                    'min_price' => $serviceResult['min_price']
                ];
            }
        }
        
        die(json_encode([
            'response' => $response,
            'materials' => $materials,
            'is_complex' => $isComplex,
            'service' => $serviceInfo,
            'context' => $context,
            'suggestions' => getRelatedQuestions($userQuery, $conn)
        ]));
    }

    // 3. Поиск по услугам
    $serviceStmt = $conn->prepare("
        SELECT id, service_name, description, price, min_price 
        FROM services 
        WHERE service_name LIKE ? OR description LIKE ?
        AND is_active = 1
        LIMIT 3
    ");
    $serviceStmt->bind_param("ss", $searchQuery, $searchQuery);
    $serviceStmt->execute();
    $services = $serviceStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (!empty($services)) {
        $response = "Возможно, вас интересуют наши услуги:\n";
        foreach ($services as $service) {
            $price = $service['min_price'] < $service['price'] 
                ? "от " . formatPrice($service['min_price'])
                : formatPrice($service['price']);
                
            $response .= "- {$service['service_name']} ({$price})\n";
        }
        $response .= "\nПодробнее: <a href='/pages/pricing.php'>Цены на услуги</a>";
        
        die(json_encode([
            'response' => $response,
            'context' => array_merge($context, ['looking_for_services' => true]),
            'suggestions' => getServiceQuestions($services)
        ]));
    }

    // 4. Проверка статуса заказа (если пользователь авторизован)
    if ($userId && preg_match('/статус.*заявк|заказ|ремонт/ui', $userQuery)) {
        $orderStmt = $conn->prepare("
            SELECT o.id, o.device_id, o.status, o.created_at, d.brand, d.model
            FROM orders o
            JOIN devices d ON o.device_id = d.id
            WHERE o.user_id = ?
            ORDER BY o.created_at DESC
            LIMIT 1
        ");
        $orderStmt->bind_param("i", $userId);
        $orderStmt->execute();
        $order = $orderStmt->get_result()->fetch_assoc();
        
        if ($order) {
            $statusText = strip_tags(getOrderStatusBadge($order['status']));
            $response = "Ваша последняя заявка #{$order['id']} (устройство: {$order['brand']} {$order['model']}):\n";
            $response .= "Статус: {$statusText}\n";
            $response .= "Дата создания: " . formatDate($order['created_at']) . "\n";
            $response .= "<a href='/client/order_details.php?id={$order['id']}'>Подробнее о заявке</a>";
            
            die(json_encode(['response' => $response, 'context' => $context]));
        } elseif ($userId) {
            die(json_encode([
                'response' => 'У вас нет активных заявок. Хотите создать новую?',
                'context' => $context,
                'suggestions' => [
                    'Как создать заявку на ремонт?',
                    'Какие услуги вы предоставляете?'
                ]
            ]));
        }
    }

    // 5. Если ничего не найдено - используем общий ответ
    $defaultResponses = [
        "Извините, я не совсем понял ваш вопрос. Можете уточнить?",
        "К сожалению, я не нашел информации по вашему запросу. Попробуйте переформулировать вопрос.",
        "Этот вопрос требует уточнения. Опишите проблему подробнее, и я постараюсь помочь."
    ];
    
    $response = $defaultResponses[array_rand($defaultResponses)];
    $suggestions = getPopularQuestions($conn);
    
    die(json_encode([
        'response' => $response,
        'context' => $context,
        'suggestions' => $suggestions
    ]));

} catch (Exception $e) {
    error_log("Assistant error: " . $e->getMessage());
    http_response_code(500);
    die(json_encode(['error' => 'Ошибка обработки запроса: ' . $e->getMessage()]));
}

// Вспомогательные функции
function getRelatedQuestions($query, $conn) {
    $searchQuery = "%$query%";
    $stmt = $conn->prepare("
        SELECT question FROM knowledge_base 
        WHERE question LIKE ? OR keywords LIKE ?
        ORDER BY views DESC
        LIMIT 3
    ");
    $stmt->bind_param("ss", $searchQuery, $searchQuery);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    return array_column($results, 'question');
}

function getServiceQuestions($services) {
    $questions = [];
    foreach ($services as $service) {
        $questions[] = "Сколько стоит {$service['service_name']}?";
        $questions[] = "Как выполняется {$service['service_name']}?";
    }
    return array_slice($questions, 0, 3);
}

function getPopularQuestions($conn) {
    $result = $conn->query("
        SELECT question FROM knowledge_base 
        ORDER BY views DESC 
        LIMIT 3
    ");
    $questions = $result->fetch_all(MYSQLI_ASSOC);
    return array_column($questions, 'question');
}