<?php
require_once 'functions.php';

function checkAuth() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        redirect('/auth/login.php');
    }
}

function checkAdmin() {
    checkAuth();
    
    if (!isAdmin()) {
        $_SESSION['error'] = 'Доступ запрещен';
        redirect('/');
    }
}

function checkClient() {
    checkAuth();
    
    if (isAdmin()) {
        redirect('/admin/dashboard.php');
    }
}

// Проверка CSRF-токена
function verifyCsrfToken() {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Недействительный CSRF-токен');
    }
}
?>