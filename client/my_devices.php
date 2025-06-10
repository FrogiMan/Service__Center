<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth_check.php';
checkClient();

$userId = $_SESSION['user_id'];
$errors = [];

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deviceId = (int)$_GET['delete'];

    $checkStmt = $conn->prepare("
        SELECT 1 FROM devices d
        LEFT JOIN orders o ON d.id = o.device_id
        WHERE d.id = ? AND d.user_id = ?
        GROUP BY d.id
        HAVING COUNT(o.id) = 0
    ");
    if ($checkStmt === false) {
        $errors[] = 'Error preparing statement: ' . $conn->error;
    } else {
        $checkStmt->bind_param("ii", $deviceId, $userId);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows === 1) {
            $deleteStmt = $conn->prepare("DELETE FROM devices WHERE id = ? AND user_id = ?");
            if ($deleteStmt === false) {
                $errors[] = 'Error preparing delete statement: ' . $conn->error;
            } else {
                $deleteStmt->bind_param("ii", $deviceId, $userId);
                if ($deleteStmt->execute()) {
                    $_SESSION['message'] = 'Device deleted successfully';
                    redirect('/client/my_devices.php');
                } else {
                    $errors[] = 'Error deleting device: ' . $deleteStmt->error;
                }
            }
        } else {
            $errors[] = 'Cannot delete device with existing orders';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deviceTypeId = (int)$_POST['device_type_id'];
    $brand = sanitizeInput($_POST['brand']);
    $model = sanitizeInput($_POST['model']);
    $serialNumber = sanitizeInput($_POST['serial_number']);
    $purchaseDate = $_POST['purchase_date'];
    $warrantyMonths = (int)$_POST['warranty_months'];
    $specifications = sanitizeInput($_POST['specifications']);

    if (empty($brand)) {
        $errors[] = 'Enter the device brand';
    }
    if (empty($model)) {
        $errors[] = 'Enter the device model';
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO devices (user_id, device_type_id, brand, model, serial_number, purchase_date, warranty_months, specifications)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if ($stmt === false) {
            $errors[] = 'Error preparing statement: ' . $conn->error;
        } else {
            $stmt->bind_param("iissssis", $userId, $deviceTypeId, $brand, $model, $serialNumber, $purchaseDate, $warrantyMonths, $specifications);
            if ($stmt->execute()) {
                $deviceId = $stmt->insert_id;
                if (isset($_GET['add_to_order'])) {
                    $_SESSION['message'] = 'Device added successfully';
                    redirect("/client/create_order.php?device_id={$deviceId}");
                } else {
                    $_SESSION['message'] = 'Device added successfully';
                    redirect('/client/my_devices.php');
                }
            } else {
                $errors[] = 'Error adding device: ' . $stmt->error;
            }
        }
    }
}

$devicesStmt = $conn->prepare("
    SELECT d.*, dt.name as device_type, dt.icon
    FROM devices d
    JOIN device_types dt ON d.device_type_id = dt.id
    WHERE d.user_id = ?
    ORDER BY d.id DESC
");
if ($devicesStmt === false) {
    $errors[] = 'Error preparing devices query: ' . $conn->error;
} else {
    $devicesStmt->bind_param("i", $userId);
    $devicesStmt->execute();
    $devices = $devicesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$deviceTypes = $conn->query("SELECT id, name FROM device_types ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['message']) ?></div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Мои устройства</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
            Добавить устройство
        </button>
    </div>

    <?php if (!empty($devices)): ?>
        <div class="row">
            <?php foreach ($devices as $device): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <?php if ($device['icon']): ?>
                                    <img src="<?= htmlspecialchars($device['icon']) ?>" alt="<?= htmlspecialchars($device['device_type']) ?>" class="me-2" width="30">
                                <?php endif; ?>
                                <h5 class="card-title mb-0"><?= htmlspecialchars($device['brand']) ?> <?= htmlspecialchars($device['model']) ?></h5>
                            </div>
                            <div class="card-text">
                                <p class="mb-1"><small class="text-muted">Тип:</small> <?= htmlspecialchars($device['device_type']) ?></p>
                                <?php if ($device['serial_number']): ?>
                                    <p class="mb-1"><small class="text-muted">Серийный номер:</small> <?= htmlspecialchars($device['serial_number']) ?></p>
                                <?php endif; ?>
                                <?php if ($device['purchase_date']): ?>
                                    <p class="mb-1"><small class="text-muted">Дата покупки:</small> <?= formatDate($device['purchase_date']) ?></p>
                                <?php endif; ?>
                                <?php if ($device['warranty_months'] > 0 && $device['purchase_date']): ?>
                                    <?php
                                    $warrantyEnd = date('Y-m-d', strtotime($device['purchase_date'] . " + {$device['warranty_months']} months"));
                                    $isWarrantyActive = strtotime($warrantyEnd) > time();
                                    ?>
                                    <p class="mb-1">
                                        <small class="text-muted">Гарантия:</small>
                                        <span class="<?= $isWarrantyActive ? 'text-success' : 'text-muted' ?>">
                                            until <?= formatDate($warrantyEnd) ?>
                                        </span>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-footer bg-white">
                            <div class="d-flex justify-content-between">
                                <a href="/client/create_order.php?device_id=<?= $device['id'] ?>" class="btn btn-sm btn-outline-primary">Create Order</a>
                                <button class="btn btn-sm btn-outline-danger delete-device" data-id="<?= $device['id'] ?>">Delete</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            У вас пока нет добавленных устройств. Добавьте устройство, чтобы создать заказ на ремонт.
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="addDeviceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Добавить устройство</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) . (isset($_GET['add_to_order']) ? '?add_to_order=1' : '') ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="device_type_id" class="form-label">Тип устройства</label>
                        <select class="form-select" id="device_type_id" name="device_type_id" required>
                            <option value="">Выберите тип</option>
                            <?php foreach ($deviceTypes as $type): ?>
                                <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="brand" class="form-label">Бренд</label>
                        <input type="text" class="form-control" id="brand" name="brand" required>
                    </div>
                    <div class="mb-3">
                        <label for="model" class="form-label">Модель</label>
                        <input type="text" class="form-control" id="model" name="model" required>
                    </div>
                    <div class="mb-3">
                        <label for="serial_number" class="form-label">Серийный номер</label>
                        <input type="text" class="form-control" id="serial_number" name="serial_number">
                    </div>
                    <div class="mb-3">
                        <label for="purchase_date" class="form-label">Дата покупки</label>
                        <input type="date" class="form-control" id="purchase_date" name="purchase_date">
                    </div>
                    <div class="mb-3">
                        <label for="warranty_months" class="form-label">Гарантия (мес.)</label>
                        <input type="number" class="form-control" id="warranty_months" name="warranty_months" value="0" min="0">
                    </div>
                    <div class="mb-3">
                        <label for="specifications" class="form-label">Технические характеристики</label>
                        <textarea class="form-control" id="specifications" name="specifications" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Добавить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.delete-device').click(function() {
        var deviceId = $(this).data('id');
        if (confirm('Are you sure you want to delete this device?')) {
            window.location.href = '/client/my_devices.php?delete=' + deviceId;
        }
    });

    <?php if (isset($_GET['add_to_order'])): ?>
        $('#addDeviceModal').modal('show');
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>