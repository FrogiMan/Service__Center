<?php
require_once "db.php";

function redirect($url) {
    if (!headers_sent()) {
        header("Location: $url");
        exit;
    } else {
        echo "<script>window.location.href='$url';</script>";
        exit;
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Форматирование данных
function formatDate($date, $format = 'd.m.Y') {
    return date($format, strtotime($date));
}

function formatPrice($price) {
    return number_format($price, 2, '.', ' ') . ' руб.';
}

// Форматирование размера файла
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return '1 byte';
    } else {
        return '0 bytes';
    }
}

// Получение статуса заказа в виде бейджа
function getOrderStatusBadge($status) {
    $statuses = [
        'new' => ['text' => 'Новая', 'class' => 'secondary'],
        'in_progress' => ['text' => 'В работе', 'class' => 'primary'],
        'completed' => ['text' => 'Завершена', 'class' => 'success'],
        'cancelled' => ['text' => 'Отменена', 'class' => 'danger'],
        'paid' => ['text' => 'Оплачена', 'class' => 'success']
    ];
    
    $status = strtolower($status);
    $data = $statuses[$status] ?? ['text' => $status, 'class' => 'light'];
    
    return '<span class="badge bg-'.$data['class'].'">'.$data['text'].'</span>';
}

// Валидация
function sanitizeInput($data) {
    global $db;
    return htmlspecialchars(stripslashes(trim($db->escape($data))));
}
?>