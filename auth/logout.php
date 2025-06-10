<?php
require_once __DIR__.'/../includes/header.php';

// Проверяем, авторизован ли пользователь
if (!isLoggedIn()) {
    redirect('/auth/login.php');
}

// Запоминаем, кто вышел (для логов)
$userId = $_SESSION['user_id'];
$userEmail = $_SESSION['user_email'];

// Очищаем сессию
$_SESSION = [];
session_destroy();

// Перенаправляем на главную с сообщением
$_SESSION['message'] = "Вы успешно вышли из системы (пользователь $userEmail).";
redirect('/');
?>