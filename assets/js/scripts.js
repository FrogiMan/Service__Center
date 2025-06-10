// Анимация загрузки
function showLoader() {
    $('#loader').fadeIn();
}

function hideLoader() {
    $('#loader').fadeOut();
}

// Обработка форм с анимацией
$('form').on('submit', function() {
    showLoader();
});

// Динамическое обновление статуса заказа
$('.status-select').change(function() {
    var orderId = $(this).data('order-id');
    var newStatus = $(this).val();
    
    $.ajax({
        url: 'update_order_status.php',
        method: 'POST',
        data: {
            order_id: orderId,
            status: newStatus
        },
        success: function(response) {
            location.reload();
        }
    });
});

// Инициализация datepicker для форм
$('input[type="date"]').datepicker({
    dateFormat: 'yy-mm-dd',
    minDate: 0
});

// Валидация форм
$('form').validate({
    rules: {
        email: {
            required: true,
            email: true
        },
        phone: {
            required: true,
            minlength: 10
        }
    },
    messages: {
        email: {
            required: "Пожалуйста, введите ваш email",
            email: "Пожалуйста, введите корректный email"
        },
        phone: {
            required: "Пожалуйста, введите ваш телефон",
            minlength: "Телефон должен содержать не менее 10 цифр"
        }
    }
});

// Инициализация модальных окон
document.addEventListener('DOMContentLoaded', function() {
    var modalElements = document.querySelectorAll('.modal');
    modalElements.forEach(function(modal) {
        var modalInstance = new bootstrap.Modal(modal, {
            backdrop: true, // Ensure backdrop is enabled
            keyboard: true
        });

        // Ensure modal is properly displayed and centered
        modal.addEventListener('show.bs.modal', function() {
            modal.style.display = 'block';
            modal.classList.add('show');
            document.body.classList.add('modal-open');
        });

        // Clean up when modal is hidden
        modal.addEventListener('hidden.bs.modal', function() {
            modal.style.display = 'none';
            document.body.classList.remove('modal-open');
            var backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(function(backdrop) {
                backdrop.remove(); // Remove backdrop to prevent stacking
            });
        });
    });
});