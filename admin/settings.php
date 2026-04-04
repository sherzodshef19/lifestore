<?php
require_once 'layout.php';

$success = '';
$error = '';

if (isset($_POST['update_settings'])) {
    setSetting('telegram_token', $_POST['telegram_token']);
    setSetting('telegram_userid', $_POST['telegram_userid']);
    setSetting('printer_ip', $_POST['printer_ip']);
    
    // New Receipt Settings
    setSetting('receipt_store_name', $_POST['receipt_store_name']);
    setSetting('receipt_address', $_POST['receipt_address']);
    setSetting('receipt_phone', $_POST['receipt_phone']);
    setSetting('receipt_header_msg', $_POST['receipt_header_msg']);
    setSetting('receipt_footer_msg', $_POST['receipt_footer_msg']);
    
    $success = "Созламалар сақланди!";
}

// Handle Manual Backup
if (isset($_POST['trigger_backup'])) {
    $res = backupDatabaseToTelegram(true);
    if ($res === true) {
        $success = "Бэкап муваффақиятли юборилди!";
    } else {
        $error = $res ?: "Хатолик: Бэкап юборилмади. Созламаларни текширинг.";
    }
}

if (isset($_POST['test_printer'])) {
    require_once '../includes/printer_helper.php';
    $ip = $_POST['printer_ip'];
    $res = testPrinterConnection($ip);
    if ($res === true) {
        $success = "Принтер билан алоқа ўрнатилди! Тест чеки чиқарилди.";
    } else {
        $error = "Принтер билан алоқада хатолик: " . $res;
    }
}

if (isset($_POST['change_password'])) {
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];
    
    if ($new_pass === $confirm_pass) {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password = '$hashed' WHERE login = 'admin'");
        $success = "Админ пароли ўзгарди!";
    } else {
        $error = "Пароллар мос келмади!";
    }
}

if (isset($_POST['clear_db'])) {
    $pass = $_POST['clear_password'];
    if ($pass === '1998') {
        $conn->query("TRUNCATE TABLE sale_items");
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        $conn->query("TRUNCATE TABLE sales");
        $conn->query("TRUNCATE TABLE stock_history");
        $conn->query("TRUNCATE TABLE products");
        $conn->query("TRUNCATE TABLE customers");
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        $success = "База тозаланди!";
    } else {
        $error = "Хато пароль!";
    }
}

// Database Export
if (isset($_GET['export_db'])) {
    $tables = ['users', 'categories', 'products', 'stock_history', 'customers', 'sales', 'sale_items', 'settings'];
    $content = "";
    foreach ($tables as $table) {
        $result = $conn->query("SELECT * FROM $table");
        while ($row = $result->fetch_assoc()) {
            $keys = array_keys($row);
            $values = array_values($row);
            $content .= "INSERT INTO $table (".implode(',', $keys).") VALUES ('".implode("','", array_map([$conn, 'real_escape_string'], $values))."');\n";
        }
    }
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename=lifestore_backup.sql');
    echo $content;
    exit();
}

render_header('Созламалар');
?>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card p-4">
            <h5 class="fw-bold mb-4">Умумий созламалар</h5>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Telegram Bot Token</label>
                    <input type="text" name="telegram_token" class="form-control" value="<?php echo getSetting('telegram_token'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Telegram User ID / Chat ID</label>
                    <input type="text" name="telegram_userid" class="form-control" value="<?php echo getSetting('telegram_userid'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Printer IP</label>
                    <div class="input-group">
                        <input type="text" name="printer_ip" class="form-control" value="<?php echo getSetting('printer_ip'); ?>">
                        <button type="submit" name="test_printer" class="btn btn-outline-secondary">TEST</button>
                    </div>
                </div>
                <button type="submit" name="update_settings" class="btn btn-primary">САҚЛАШ</button>
            </form>
        </div>

        <div class="card p-4 mt-4 border-0 shadow-sm" style="border-radius: 20px; background: linear-gradient(135deg, #ffffff 0%, #f8f9fc 100%);">
            <div class="d-flex align-items-center mb-4">
                <div class="bg-primary bg-opacity-10 p-2 rounded-3 me-3 text-primary"><i class="bi bi-receipt fs-4"></i></div>
                <h5 class="fw-bold mb-0">Чек Созламалари</h5>
            </div>
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label small fw-bold text-muted">Дўкон номи</label>
                        <input type="text" name="receipt_store_name" class="form-control" value="<?php echo getSetting('receipt_store_name', 'LIFE STORE'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Манзил</label>
                        <input type="text" name="receipt_address" class="form-control" value="<?php echo getSetting('receipt_address', 'Тошкент ш.'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Телефон</label>
                        <input type="text" name="receipt_phone" class="form-control" value="<?php echo getSetting('receipt_phone', '+998'); ?>">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-bold text-muted">Чек юқори қисми (Header)</label>
                        <input type="text" name="receipt_header_msg" class="form-control" value="<?php echo getSetting('receipt_header_msg', 'ИНВЕНТАРНИ БОШҚАРИШ ТИЗИМИ'); ?>">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-bold text-muted">Чек пастки қисми (Footer)</label>
                        <input type="text" name="receipt_footer_msg" class="form-control" value="<?php echo getSetting('receipt_footer_msg', 'Ҳаридингиз учун раҳмат!'); ?>">
                    </div>
                </div>
                <button type="submit" name="update_settings" class="btn btn-primary w-100 mt-4 py-3 shadow-sm rounded-3">САҚЛАШ</button>
            </form>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card p-4 mb-4">
            <h5 class="fw-bold mb-4 text-danger">Маълумотлар базаси</h5>
            <div class="d-grid gap-2">
                <a href="?export_db=1" class="btn btn-outline-success"><i class="bi bi-download"></i> Базани экспорт қилиш (SQL)</a>
                <form method="POST">
                    <button type="submit" name="trigger_backup" class="btn btn-outline-info w-100"><i class="bi bi-telegram"></i> Бэкапни Telegram-га юбориш</button>
                </form>
                <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#clearModal"><i class="bi bi-trash"></i> Базани тозалаш</button>
            </div>
        </div>
        <div class="card p-4">
            <h5 class="fw-bold mb-4">Паролни ўзгартириш (Admin)</h5>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Янги пароль</label>
                    <input type="password" name="new_password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Паролни тасдиқланг</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                <button type="submit" name="change_password" class="btn btn-primary">ЎЗГАРТИРИШ</button>
            </form>
        </div>

        <?php if($success): ?>
            <div class="alert alert-success mt-4"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-danger mt-4"><?php echo $error; ?></div>
        <?php endif; ?>
    </div>
</div>

<!-- Clear Modal -->
<div class="modal fade" id="clearModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Базани тозалаш</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
            <p class="text-danger">ОГОҲЛАНТИРИШ: Барча товарлар, сотувлар ва мижозлар ўчирилиб юборилади!</p>
            <label class="form-label">Тасдиқлаш учун парол теринг (1998)</label>
            <input type="password" name="clear_password" class="form-control" required>
        </div>
        <div class="modal-footer">
            <button type="submit" name="clear_db" class="btn btn-danger">ТОЗАЛАШ</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php render_footer(); ?>
