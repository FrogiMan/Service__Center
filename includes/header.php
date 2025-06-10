<?php
session_start();
require_once "functions.php";
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle : 'Сервисный центр' ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/styl.css">
    <link rel="icon" href="/assets/images/logo.png">
</head>
<body>
    <header class="bg-dark text-white">
        <div class="container">
            <nav class="navbar navbar-expand-lg navbar-dark">
                <a class="navbar-brand" href="/">
                    <img src="/assets/images/logo.png" alt="Логотип" class="img-logo">
                    Сервисный центр
                </a>
                
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="/">Главная</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/pages/services.php">Услуги</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/pages/contacts.php">Контакты</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/assistant/assistant.php">Помощник</a>
                        </li>
                    </ul>
                    
                    <ul class="navbar-nav">
                        <?php if (isLoggedIn()): ?>
                            <?php if (isAdmin()): ?>
                                <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        Админ-панель
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminDropdown">
                                        <li><a class="dropdown-item" href="/admin/dashboard.php">Панель управления</a></li>
                                        <li><a class="dropdown-item" href="/admin/orders.php">Заказы</a></li>
                                        <li><a class="dropdown-item" href="/admin/services.php">Услуги</a></li>
                                        <li><a class="dropdown-item" href="/admin/users.php">Пользователи</a></li>
                                    </ul>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="/auth/logout.php">Выйти</a>
                                </li>
                            <?php else: ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="/client/dashboard.php">Личный кабинет</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="/auth/logout.php">Выйти</a>
                                </li>
                            <?php endif; ?>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/auth/login.php">Вход</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/auth/register.php">Регистрация</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </nav>
        </div>
    </header>
    
    <main class="container my-4">