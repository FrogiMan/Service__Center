<?php
require_once __DIR__.'/../includes/header.php';

$pageTitle = 'Чат-помощник';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | <?= htmlspecialchars($companySettings['company_name'] ?? 'Сервисный центр') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/assistant.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <main class="container my-4">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h2 class="h5 mb-0">Чат-помощник</h2>
                    </div>
                    <div class="card-body">
                        <div id="chat-container" class="chat-container">
                            <div id="chat-messages" class="chat-messages">
                                <div class="message bot-message">
                                    <div class="message-content">
                                        Здравствуйте! Я виртуальный помощник сервисного центра. 
                                        Чем могу помочь?
                                    </div>
                                    <div class="message-time">
                                        <?= date('H:i') ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="suggestions" class="suggestions mt-3">
                                <p class="text-muted small mb-2">Популярные вопросы:</p>
                                <div class="d-flex flex-wrap gap-2">
                                    <button class="btn btn-sm btn-outline-secondary suggestion-btn">Сколько стоит диагностика?</button>
                                    <button class="btn btn-sm btn-outline-secondary suggestion-btn">Как узнать статус ремонта?</button>
                                    <button class="btn btn-sm btn-outline-secondary suggestion-btn">Какие бренды вы обслуживаете?</button>
                                </div>
                            </div>
                            
                            <div id="service-info" class="service-info mt-3" style="display: none;"></div>
                        </div>
                        
                        <div class="input-group mt-3">
                            <input type="text" id="chat-input" class="form-control" placeholder="Введите ваш вопрос..." autocomplete="off">
                            <button id="send-btn" class="btn btn-primary">Отправить</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include __DIR__.'/../includes/footer.php'; ?>

    <script>
    $(document).ready(function() {
        const chatMessages = $('#chat-messages');
        const chatInput = $('#chat-input');
        const sendBtn = $('#send-btn');
        const suggestions = $('#suggestions');
        const serviceInfo = $('#service-info');
        
        let chatContext = {};
        
        // Функция добавления сообщения в чат
        function addMessage(text, isUser = false) {
            const messageClass = isUser ? 'user-message' : 'bot-message';
            const time = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            
            const messageHtml = `
                <div class="message ${messageClass}">
                    <div class="message-content">${text}</div>
                    <div class="message-time">${time}</div>
                </div>
            `;
            
            chatMessages.append(messageHtml);
            chatMessages.scrollTop(chatMessages[0].scrollHeight);
        }
        
        // Функция отправки сообщения
        function sendMessage() {
            const message = chatInput.val().trim();
            if (!message) return;
            
            addMessage(message, true);
            chatInput.val('');
            
            // Показываем индикатор загрузки
            const loadingHtml = `
                <div class="message bot-message">
                    <div class="message-content">
                        <div class="spinner-border spinner-border-sm" role="status">
                            <span class="visually-hidden">Загрузка...</span>
                        </div>
                        <span class="ms-2">Помощник печатает...</span>
                    </div>
                </div>
            `;
            chatMessages.append(loadingHtml);
            chatMessages.scrollTop(chatMessages[0].scrollHeight);
            
            // Отправляем запрос на сервер
            $.ajax({
                url: '/assistant/assistant_api.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    query: message,
                    context: chatContext
                }),
                success: function(response) {
                    // Удаляем индикатор загрузки
                    chatMessages.children().last().remove();
                    
                    // Добавляем ответ
                    addMessage(response.response);
                    
                    // Обновляем контекст
                    chatContext = response.context || {};
                    
                    // Показываем дополнительные материалы
                    if (response.materials && response.materials.length > 0) {
                        let materialsHtml = '<div class="materials mt-3"><p class="small text-muted">Дополнительные материалы:</p><ul>';
                        
                        response.materials.forEach(material => {
                            const typeIcon = material.type === 'video' ? '🎬' : '📄';
                            materialsHtml += `<li>${typeIcon} <a href="${material.url}" target="_blank">${material.type === 'video' ? 'Видеоинструкция' : 'Статья'}</a></li>`;
                        });
                        
                        materialsHtml += '</ul></div>';
                        chatMessages.append(materialsHtml);
                    }
                    
                    // Показываем информацию об услуге
                    if (response.service) {
                        const price = response.service.min_price < response.service.price 
                            ? `от ${response.service.min_price} руб.` 
                            : `${response.service.price} руб.`;
                            
                        serviceInfo.html(`
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">${response.service.name}</h5>
                                    <p class="card-text">Стоимость: ${price}</p>
                                    <a href="/client/create_order.php?service_id=${response.service.id}" class="btn btn-sm btn-primary">
                                        Заказать услугу
                                    </a>
                                </div>
                            </div>
                        `).show();
                    } else {
                        serviceInfo.hide();
                    }
                    
                    // Обновляем подсказки
                    if (response.suggestions && response.suggestions.length > 0) {
                        let suggestionsHtml = '<p class="text-muted small mb-2">Возможно, вас заинтересует:</p><div class="d-flex flex-wrap gap-2">';
                        
                        response.suggestions.forEach(suggestion => {
                            suggestionsHtml += `<button class="btn btn-sm btn-outline-secondary suggestion-btn">${suggestion}</button>`;
                        });
                        
                        suggestionsHtml += '</div>';
                        suggestions.html(suggestionsHtml);
                    }
                    
                    // Если проблема сложная, предлагаем обратиться в сервис
                    if (response.is_complex) {
                        chatMessages.append(`
                            <div class="alert alert-warning mt-3">
                                <strong>Рекомендация:</strong> Для решения этой проблемы рекомендуем обратиться в наш сервисный центр.
                                <div class="mt-2">
                                    <a href="/client/create_order.php" class="btn btn-sm btn-warning">Создать заявку</a>
                                    <a href="/pages/contacts.php" class="btn btn-sm btn-outline-secondary">Контакты</a>
                                </div>
                            </div>
                        `);
                    }
                },
                error: function() {
                    // Удаляем индикатор загрузки
                    chatMessages.children().last().remove();
                    
                    addMessage('Произошла ошибка при обработке запроса. Пожалуйста, попробуйте позже.');
                }
            });
        }
        
        // Обработка нажатия кнопки отправки
        sendBtn.click(sendMessage);
        chatInput.keypress(function(e) {
            if (e.which === 13) {
                sendMessage();
            }
        });
        
        // Обработка нажатия на подсказку
        $(document).on('click', '.suggestion-btn', function() {
            chatInput.val($(this).text());
            sendMessage();
        });
        
        // Фокус на поле ввода при загрузке
        chatInput.focus();
    });
    </script>
</body>
</html>