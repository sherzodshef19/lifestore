<?php
require_once 'layout.php';

$success = '';
$error = '';

// Handle Excel Export (Styled)
if (isset($_GET['export'])) {
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=lifestore_customers_" . date('Y-m-d') . ".xls");
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <style>
            .header-info { font-size: 18px; font-weight: bold; color: #1cc88a; margin-bottom: 20px; }
            table { border-collapse: collapse; width: 100%; border: 1px solid #000; }
            th { background-color: #1cc88a; color: #ffffff; border: 1px solid #000; padding: 10px; font-weight: bold; }
            td { border: 1px solid #000; padding: 8px; text-align: left; }
            .money { text-align: right; mso-number-format:"\#\,\#\#0"; }
            .negative { color: #e74a3b; font-weight: bold; }
            .total-row { background-color: #f8f9fc; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="header-info">LIFESTORE - МИЖОЗЛАР ВА ҚАРЗДОРЛИК ҲИСОБОТИ (' . date('d.m.Y H:i') . ')</div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Исм</th>
                    <th>Телефон</th>
                    <th>Баланс (Қарз)</th>
                    <th>Рўйхатдан ўтган вақти</th>
                </tr>
            </thead>
            <tbody>';
    
    $query = $conn->query("SELECT * FROM customers ORDER BY balance ASC, name ASC");
    $total_debt = 0;
    $count = 0;

    while ($row = $query->fetch_assoc()) {
        $total_debt += (float)$row['balance'];
        $count++;
        $balance_class = $row['balance'] < 0 ? 'negative' : '';

        echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['name']}</td>
                <td>{$row['phone']}</td>
                <td class='money $balance_class'>" . number_format($row['balance'], 0, '.', ' ') . " сум</td>
                <td>{$row['created_at']}</td>
              </tr>";
    }

    echo '</tbody>
            <tfoot class="total-row">
                <tr>
                    <td colspan="3" style="text-align: right;">УМУМИЙ БАЛАНС (ҚАРЗДОРЛИК):</td>
                    <td class="money ' . ($total_debt < 0 ? 'negative' : '') . '">' . number_format($total_debt, 0, '.', ' ') . ' сум</td>
                    <td>—</td>
                </tr>
                <tr>
                    <td colspan="3" style="text-align: right;">ЖАМИ МИЖОЗЛАР:</td>
                    <td>' . $count . '</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </body>
    </html>';
    exit();
}

if (isset($_POST['save_customer'])) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = $conn->real_escape_string($_POST['name']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $balance = (float)$_POST['balance'];

    if ($id > 0) {
        $sql = "UPDATE customers SET name='$name', phone='$phone', balance=$balance WHERE id=$id";
    } else {
        $sql = "INSERT INTO customers (name, phone, balance) VALUES ('$name', '$phone', $balance)";
    }

    if ($conn->query($sql)) {
        $success = "Мижоз маълумотлари сақланди!";
    } else {
        $error = "Хатолик: " . $conn->error;
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM customers WHERE id = $id");
    $success = "Мижоз ўчирилди!";
}

// Handle Debt Repayment via AJAX (called from modal)
if (isset($_POST['repay_debt'])) {
    $customer_id = (int)$_POST['customer_id'];
    $amount = (float)$_POST['amount'];
    $payment_type = $conn->real_escape_string($_POST['payment_type']);
    $user_id = $_SESSION['user_id'];

    $conn->begin_transaction();
    try {
        $conn->query("INSERT INTO debt_repayments (customer_id, user_id, amount, payment_type) VALUES ($customer_id, $user_id, $amount, '$payment_type')");
        $conn->query("UPDATE customers SET balance = balance + $amount WHERE id = $customer_id");
        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$edit_customer = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $edit_customer = $conn->query("SELECT * FROM customers WHERE id = $id")->fetch_assoc();
}

if ($action === 'info' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $customer_info = $conn->query("SELECT * FROM customers WHERE id = $id")->fetch_assoc();
}

render_header('Мижозлар');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="?action=list" class="btn btn-outline-primary <?php echo $action === 'list' ? 'active' : ''; ?>">Рўйхат</a>
        <a href="?action=add" class="btn btn-outline-primary <?php echo $action === 'add' ? 'active' : ''; ?>">Янги қўшиш</a>
    </div>
    <div>
        <a href="?export=1" class="btn btn-success"><i class="bi bi-file-earmark-spreadsheet"></i> Excel га (CSV)</a>
    </div>
</div>

<?php if($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if($action === 'list'): ?>
    <div class="card p-4">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Исми</th>
                        <th>Телефон</th>
                        <th>Баланс</th>
                        <th class="text-end">Амаллар</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total_balance = 0;
                    $customers = $conn->query("SELECT * FROM customers ORDER BY id DESC");
                    while($c = $customers->fetch_assoc()):
                        $total_balance += $c['balance'];
                    ?>
                    <tr>
                        <td><?php echo $c['id']; ?></td>
                        <td><?php echo $c['name']; ?></td>
                        <td><?php echo $c['phone']; ?></td>
                        <td class="<?php echo $c['balance'] < 0 ? 'negative-balance' : 'text-success'; ?>">
                            <?php echo formatMoney($c['balance']); ?>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-success me-1" onclick="repayDebt(<?php echo $c['id']; ?>, '<?php echo $c['name']; ?>', <?php echo abs($c['balance']); ?>)">
                                <i class="bi bi-cash-stack"></i> Тўлов
                            </button>
                            <a href="?action=info&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-info" title="Инфо"><i class="bi bi-info-circle"></i> Инфо</a>
                            <a href="?action=edit&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                            <a href="?delete=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Ўчирмоқчимисиз?')"><i class="bi bi-trash"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot class="table-light fw-bold text-uppercase small">
                    <tr>
                        <td colspan="3" class="text-end py-3">Жами қарздорлик:</td>
                        <td class="text-danger py-3 fs-4"><?php echo formatMoney($total_balance); ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
<?php elseif($action === 'info'): ?>
    <div class="card p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold m-0"><?php echo $customer_info['name']; ?> - Олинган маҳсулотлар</h5>
            <div class="text-end">
                <span class="text-muted">Телефон: <?php echo $customer_info['phone']; ?></span><br>
                <span class="fw-bold <?php echo $customer_info['balance'] < 0 ? 'negative-balance' : 'text-success'; ?>">Қарзи: <?php echo formatMoney($customer_info['balance']); ?></span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Сана</th>
                        <th>Маҳсулот</th>
                        <th>Миқдор</th>
                        <th>Нарх</th>
                        <th>Жами</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sales = $conn->query("SELECT s.date, si.quantity, si.price, p.name FROM sale_items si 
                                           JOIN sales s ON si.sale_id = s.id 
                                           JOIN products p ON si.product_id = p.id 
                                           WHERE s.customer_id = $id ORDER BY s.date DESC");
                    while($s = $sales->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?php echo format_date($s['date']); ?></td>
                        <td><?php echo $s['name']; ?></td>
                        <td><?php echo $s['quantity']; ?></td>
                        <td><?php echo formatMoney($s['price']); ?></td>
                        <td><?php echo formatMoney($s['quantity'] * $s['price']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <h5 class="fw-bold mb-3 mt-5 text-success">Тўловлар тарихи (қарз сўндириш)</h5>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Сана</th>
                        <th>Қабул қилди</th>
                        <th>Тўлов тури</th>
                        <th>Сумма</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $repayments = $conn->query("SELECT r.*, u.name as user_name FROM debt_repayments r 
                                                LEFT JOIN users u ON r.user_id = u.id 
                                                WHERE r.customer_id = $id ORDER BY r.date DESC");
                    while($r = $repayments->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?php echo format_date($r['date']); ?></td>
                        <td><?php echo $r['user_name']; ?></td>
                        <td><?php echo $r['payment_type'] === 'cash' ? 'Нахт' : 'Карта'; ?></td>
                        <td class="text-success fw-bold">+<?php echo formatMoney($r['amount']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="card p-4">
        <h5 class="fw-bold mb-4"><?php echo $action === 'edit' ? 'Мижозни таҳрирлаш' : 'Янги мижоз'; ?></h5>
        <form method="POST">
            <input type="hidden" name="id" value="<?php echo $edit_customer ? $edit_customer['id'] : ''; ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Мижоз исми</label>
                    <input type="text" name="name" class="form-control" value="<?php echo $edit_customer ? $edit_customer['name'] : ''; ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Телефон</label>
                    <input type="text" name="phone" class="form-control" value="<?php echo $edit_customer ? $edit_customer['phone'] : ''; ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Баланс (Карз ҳолати)</label>
                    <input type="number" step="0.01" name="balance" class="form-control" value="<?php echo $edit_customer ? $edit_customer['balance'] : '0.00'; ?>">
                </div>
            </div>
            <button type="submit" name="save_customer" class="btn btn-primary mt-4 px-5">САҚЛАШ</button>
        </form>
    </div>
<?php endif; ?>

<?php render_footer(); ?>

<!-- Repayment Modal -->
<div class="modal fade" id="repayModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">Қарзни сўндириш</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
          <form id="repayForm">
              <input type="hidden" name="customer_id" id="repayCustomerId">
              <div class="mb-3">
                  <label class="form-label">Мижоз:</label>
                  <p class="fw-bold" id="repayCustomerName"></p>
              </div>
              <div class="mb-3">
                  <label class="form-label">Тўлов суммаси:</label>
                  <input type="number" name="amount" id="repayAmount" class="form-control form-control-lg" required>
              </div>
              <div class="mb-3">
                  <label class="form-label">Тўлов тури:</label>
                  <select name="payment_type" class="form-select" required>
                      <option value="cash">Нахт</option>
                      <option value="card">Карта</option>
                  </select>
              </div>
              <button type="submit" class="btn btn-success w-100 py-3 mt-3">ТЎЛОВНИ ҚАБУЛ ҚИЛИШ</button>
          </form>
      </div>
    </div>
  </div>
</div>

<script>
function repayDebt(id, name, balance) {
    document.getElementById('repayCustomerId').value = id;
    document.getElementById('repayCustomerName').innerText = name;
    document.getElementById('repayAmount').value = balance;
    new bootstrap.Modal(document.getElementById('repayModal')).show();
}

document.getElementById('repayForm').onsubmit = function(e) {
    e.preventDefault();
    let formData = new FormData(this);
    formData.append('repay_debt', '1');

    fetch('customers.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if(res.success) {
            location.reload();
        } else {
            alert('Хатолик: ' + res.error);
        }
    });
};
</script>
