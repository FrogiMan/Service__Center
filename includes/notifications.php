<?php
function sendEmail($to, $subject, $message) {
    $headers = "From: safronov.snowmaster@yandex.ru\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    return mail($to, $subject, nl2br(htmlspecialchars($message)), $headers);
}

function sendSMS($phone, $message) {
    // Replace with actual SMS provider's API (e.g., Twilio, Nexmo, etc.)
    // This is a placeholder implementation
    $apiUrl = "https://api.sms-provider.com/send";
    $apiKey = "YOUR_API_KEY"; // Replace with your actual API key
    $data = [
        'to' => $phone,
        'message' => $message,
        'api_key' => $apiKey
    ];
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    return $response !== false;
}

function sendTelegramNotification($chatId, $message) {
    $botToken = '8083927637:AAEIVWxYO0nyPGWlHINCd6FhhhyiSbFjgLs'; // Your bot token
    $apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
    
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Ensure SSL verification is enabled
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $result = json_decode($response, true);
        if ($result['ok']) {
            return true;
        } else {
            error_log("Telegram API error: " . $response);
            return false;
        }
    } else {
        error_log("cURL error: $curlError, HTTP Code: $httpCode, Response: $response");
        return false;
    }
}

function sendNotification($userId, $message, $type = 'system', $relatedOrderId = null) {
    global $conn;
    
    // Sanitize message
    $message = sanitizeInput($message);
    
    // Insert notification into database
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, message, notification_type, related_order_id, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    if ($stmt === false) {
        error_log("Failed to prepare statement: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("issi", $userId, $message, $type, $relatedOrderId);
    if (!$stmt->execute()) {
        error_log("Failed to execute statement: " . $stmt->error);
        return false;
    }
    
    // Get user details
    $user = getUserById($userId);
    if (!$user) {
        error_log("User not found: ID $userId");
        return false;
    }
    
    // Send notification based on type and availability
    if (in_array($type, ['important', 'order_created', 'payment_received', 'order_completed'])) {
        $preferredMethod = $user['preferred_notification_method'] ?? 'email';
        
        if ($preferredMethod === 'email' || $preferredMethod === 'both') {
            if (!empty($user['email'])) {
                $subject = $type === 'order_completed' ? 'Ваш заказ завершен' : 'Новое уведомление';
                sendEmail($user['email'], $subject, $message);
            }
        }
        
        if ($preferredMethod === 'sms' || ($preferredMethod === 'both' && empty($user['email']))) {
            if (!empty($user['phone'])) {
                sendSMS($user['phone'], $message);
            }
        }
    }
    
    return true;
}

function getUserNotifications($userId, $unreadOnly = false) {
    global $conn;
    
    $sql = "SELECT * FROM notifications WHERE user_id = ?";
    if ($unreadOnly) {
        $sql .= " AND is_read = 0";
    }
    $sql .= " ORDER BY created_at DESC LIMIT 10";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("Failed to prepare getUserNotifications statement: " . $conn->error);
        return [];
    }
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getUserById($userId) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    if ($stmt === false) {
        error_log("Failed to prepare getUserById statement: " . $conn->error);
        return null;
    }
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function markAsRead($notificationId) {
    global $conn;
    
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    if ($stmt === false) {
        error_log("Failed to prepare markAsRead statement: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("i", $notificationId);
    $stmt->execute();
    return true;
}

// Handle AJAX requests for marking notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'mark_read') {
        markAsRead((int)$_POST['id']);
        echo json_encode(['success' => true]);
        exit;
    } elseif ($_POST['action'] === 'mark_all_read') {
        $userId = $_SESSION['user_id'];
        $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $userId");
        echo json_encode(['success' => true]);
        exit;
    }
}
?>