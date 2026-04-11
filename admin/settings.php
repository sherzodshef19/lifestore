<?php
require_once 'layout.php';

$success = '';
$error = '';

if (isset($_POST['update_general'])) {
    setSetting('telegram_token', $_POST['telegram_token']);
    setSetting('telegram_userid', $_POST['telegram_userid']);
    setSetting('printer_ip', $_POST['printer_ip']);
    $success = "Умумий созламалар сақланди!";
}

if (isset($_POST['update_receipt'])) {
    setSetting('receipt_store_name', $_POST['receipt_store_name']);
    setSetting('receipt_address', $_POST['receipt_address']);
    setSetting('receipt_phone', $_POST['receipt_phone']);
    setSetting('receipt_header_msg', $_POST['receipt_header_msg']);
    setSetting('receipt_footer_msg', $_POST['receipt_footer_msg']);
    setSetting('receipt_qr_link', $_POST['receipt_qr_link']);
    $success = "Чек созламалари сақланди!";
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
                <button type="submit" name="update_general" class="btn btn-primary">САҚЛАШ</button>
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
                        <input type="text" name="receipt_store_name" id="inp_store_name" class="form-control" value="<?php echo getSetting('receipt_store_name', 'LIFE STORE'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Манзил</label>
                        <input type="text" name="receipt_address" id="inp_address" class="form-control" value="<?php echo getSetting('receipt_address', 'Тошкент ш.'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Телефон</label>
                        <input type="text" name="receipt_phone" id="inp_phone" class="form-control" value="<?php echo getSetting('receipt_phone', '+998'); ?>">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-bold text-muted">Чек юқори қисми (Header)</label>
                        <input type="text" name="receipt_header_msg" id="inp_header_msg" class="form-control" value="<?php echo getSetting('receipt_header_msg', 'ИНВЕНТАРНИ БОШҚАРИШ ТИЗИМИ'); ?>">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-bold text-muted">Чек пастки қисми (Footer)</label>
                        <textarea name="receipt_footer_msg" id="inp_footer_msg" class="form-control" rows="2"><?php echo getSetting('receipt_footer_msg', 'Ҳаридингиз учун раҳмат!'); ?></textarea>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-bold text-muted">QR код учун ҳавола (Link)</label>
                        <input type="text" name="receipt_qr_link" id="inp_qr_link" class="form-control" placeholder="https://..." value="<?php echo getSetting('receipt_qr_link', ''); ?>">
                    </div>
                </div>
                <div class="d-flex gap-2 mt-4">
                    <button type="button" class="btn btn-outline-secondary w-50 py-3 shadow-sm rounded-3" data-bs-toggle="modal" data-bs-target="#receiptPreviewModal">
                        <i class="bi bi-eye"></i> Кўриш (Preview)
                    </button>
                    <button type="submit" name="update_receipt" class="btn btn-primary w-50 py-3 shadow-sm rounded-3">САҚЛАШ</button>
                </div>
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

<!-- Receipt Preview Modal -->
<div class="modal fade" id="receiptPreviewModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content" style="background-color: #e9ecef;">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fs-6 text-muted">Чек кўриниши</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body d-flex justify-content-center pt-2">
         <div id="mock-receipt" style="background:#fff; width:100%; max-width:300px; padding:15px; font-family:'Courier New', Courier, monospace; font-size:12px; color:#000; box-shadow:0 4px 6px rgba(0,0,0,0.1);">
            <div class="text-center mb-2" style="text-align: center;">
                <h2 id="prev_store_name" style="margin: 0; font-size:18px; font-weight:bold; text-transform:uppercase;">LIFE STORE</h2>
                <p id="prev_header_msg" style="margin: 5px 0 0 0;">ИНВЕНТАРНИ БОШҚАРИШ ТИЗИМИ</p>
                <p style="margin: 5px 0 0 0; font-size: 10px;"><span id="prev_address">Тошкент ш.</span> | <span id="prev_phone">+998</span></p>
                <p style="margin: 5px 0 0 0; font-size: 10px;"><?php echo date('d.m.Y H:i'); ?></p>
            </div>
            <div style="border-top:1px dashed #000; margin:8px 0;"></div>
            <div style="font-size: 10px; margin-bottom: 8px;">
                <div>Чек №: <span style="font-weight:bold;">12345</span></div>
                <div>Кассир: <span style="font-weight:bold;">Admin</span></div>
                <div>Мижоз: <span style="font-weight:bold;">Оддий мижоз</span></div>
            </div>
            <div style="border-top:1px dashed #000; margin:8px 0;"></div>
            <div style="font-weight:bold; margin-bottom: 8px; display:flex; justify-content:space-between;">
                <span>Маҳсулот</span><span>Жами</span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                <div style="width:60%;">Намуна товар 1</div><div style="width:40%; text-align:right;">15,000</div>
            </div>
            <div style="font-size: 9px; margin-bottom: 5px;">2 x 7,500</div>
            
            <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                <div style="width:60%;">Намуна товар 2</div><div style="width:40%; text-align:right;">25,000</div>
            </div>
            <div style="font-size: 9px; margin-bottom: 5px;">1 x 25,000</div>

            <div style="border-top:1px dashed #000; margin:8px 0;"></div>
            <div style="text-align:right; font-size:14px;">
                ЖАМИ: <span style="font-weight:bold;">40,000</span>
            </div>
            <div style="margin-top: 8px; font-size: 10px;">
                Тўлов тури: <span style="font-weight:bold;">Нахт</span>
            </div>
            <div style="border-top:1px dashed #000; margin:8px 0;"></div>
            <div id="prev_footer_msg" style="text-align:center; margin-top:15px; font-size:10px;">
                ҲАРИДИНГИЗ УЧУН РАҲМАТ!<br>ЯНА КУТИБ ҚОЛАМИЗ.
            </div>
            <div id="prev_qr_container" style="display:flex; justify-content:center; margin-top:10px;">
                <div id="prev_qr"></div>
            </div>
         </div>
      </div>
    </div>
  </div>
</div>

<script src="../includes/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const inputs = ['store_name', 'address', 'phone', 'header_msg', 'footer_msg'];
    const qrLinkInput = document.getElementById('inp_qr_link');
    const qrContainer = document.getElementById('prev_qr_container');
    const qrDiv = document.getElementById('prev_qr');
    let qrcodeInstance = null;
    
    inputs.forEach(id => {
        const inputEl = document.getElementById('inp_' + id);
        const prevEl = document.getElementById('prev_' + id);
        if(inputEl && prevEl) {
            inputEl.addEventListener('input', (e) => {
                let val = e.target.value;
                if(id === 'store_name') val = val.toUpperCase();
                if(id === 'footer_msg') val = val.replace(/\n|\\n/g, '<br>');
                prevEl.innerHTML = val || '...';
            });
            // Initial call
            const evt = new Event('input');
            inputEl.dispatchEvent(evt);
        }
    });

    if (qrLinkInput) {
        qrLinkInput.addEventListener('input', (e) => {
            const val = e.target.value.trim();
            qrDiv.innerHTML = '';
            if (val) {
                qrContainer.style.display = 'flex';
                qrcodeInstance = new QRCode(qrDiv, {
                    text: val,
                    width: 70,
                    height: 70,
                    colorDark : "#000000",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.L
                });
            } else {
                qrContainer.style.display = 'none';
            }
        });
        qrLinkInput.dispatchEvent(new Event('input'));
    }

    // Make sure preview opens cleanly
    const previewBtn = document.querySelector('[data-bs-target="#receiptPreviewModal"]');
    if (previewBtn) {
        previewBtn.addEventListener('click', () => {
            inputs.forEach(id => {
                const inputEl = document.getElementById('inp_' + id);
                if (inputEl) inputEl.dispatchEvent(new Event('input'));
            });
            if (qrLinkInput) qrLinkInput.dispatchEvent(new Event('input'));
        });
    }
});
</script>

<?php render_footer(); ?>
