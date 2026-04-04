<?php
require_once 'layout.php';

$success = '';
$error = '';

if (isset($_POST['add_customer'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $balance = 0.00;

    if ($conn->query("INSERT INTO customers (name, phone, balance) VALUES ('$name', '$phone', $balance)")) {
        $success = "Мижоз муваффақиятли қўшилди!";
    } else {
        $error = "Хатолик юз берди: " . $conn->error;
    }
}

// AJAX handler for debt repayment
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

// AJAX handler for customer info
if (isset($_GET['get_customer_info'])) {
    $customer_id = (int)$_GET['get_customer_info'];
    
    // Purchase history
    $sales = $conn->query("SELECT s.date, si.quantity, si.price, p.name FROM sale_items si 
                           JOIN sales s ON si.sale_id = s.id 
                           JOIN products p ON si.product_id = p.id 
                           WHERE s.customer_id = $customer_id ORDER BY s.date DESC LIMIT 20");
    
    // Repayment history
    $repayments = $conn->query("SELECT * FROM debt_repayments WHERE customer_id = $customer_id ORDER BY date DESC LIMIT 20");
    
    $html = '<h6 class="fw-bold mb-3 mt-4 text-primary">Сўнги харидлар</h6>';
    $html .= '<table class="table table-sm"><thead><tr><th>Сана</th><th>Маҳсулот</th><th>Миқдор</th><th>Жами</th></tr></thead><tbody>';
    while ($s = $sales->fetch_assoc()) {
        $html .= "<tr><td>" . format_date($s['date']) . "</td><td>{$s['name']}</td><td>{$s['quantity']}</td><td>" . formatMoney($s['quantity'] * $s['price']) . "</td></tr>";
    }
    $html .= '</tbody></table>';

    $html .= '<h6 class="fw-bold mb-3 mt-4 text-success">Тўловлар тарихи (қарз сўндириш)</h6>';
    $html .= '<table class="table table-sm"><thead><tr><th>Сана</th><th>Тур</th><th>Сумма</th></tr></thead><tbody>';
    while ($r = $repayments->fetch_assoc()) {
        $type = $r['payment_type'] === 'cash' ? 'Нахт' : 'Карта';
        $html .= "<tr><td>" . format_date($r['date']) . "</td><td>$type</td><td class='text-success fw-bold'>+" . formatMoney($r['amount']) . "</td></tr>";
    }
    $html .= '</tbody></table>';

    echo $html;
    exit();
}

render_header('Мижозлар рўйхати');
?>

<div class="row">
    <div class="col-md-4">
        <div class="card p-4">
            <h5 class="fw-bold mb-4">Янги мижоз қўшиш</h5>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Исми</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Телефон рақами</label>
                    <input type="text" name="phone" id="phone" class="form-control" placeholder="+998 (__) ___-__-__">
                </div>
                <button type="submit" name="add_customer" class="btn btn-primary w-100">ҚЎШИШ</button>
            </form>
        </div>
    </div>
    
    <div class="col-md-8">
        <?php if($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        
        <div class="card p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Исми</th>
                            <th>Телефон</th>
                            <th>Баланс</th>
                            <th class="text-end">Амаллар</th>
                        </tr>
                    </thead>
                    <tbody id="customerTableBody">
                        <?php
                        $customers = $conn->query("SELECT * FROM customers ORDER BY name ASC");
                        while($c = $customers->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?php echo $c['name']; ?></td>
                            <td><?php echo $c['phone']; ?></td>
                            <td class="<?php echo $c['balance'] < 0 ? 'negative-balance' : 'text-success'; ?>">
                                <?php echo formatMoney($c['balance']); ?>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-success me-1" onclick="repayDebt(<?php echo $c['id']; ?>, '<?php echo $c['name']; ?>', <?php echo abs($c['balance']); ?>)">
                                    <i class="bi bi-cash-stack"></i> Қарз сўндириш
                                </button>
                                <button class="btn btn-sm btn-outline-info" onclick="viewCustomerInfo(<?php echo $c['id']; ?>, '<?php echo $c['name']; ?>')">
                                    <i class="bi bi-info-circle"></i> Инфо
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

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

<!-- Customer Info Modal -->
<div class="modal fade" id="customerInfoModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">Мижоз маълумотлари</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="customerInfoBody">
          Загрузка...
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

function viewCustomerInfo(customerId, customerName) {
    const modal = new bootstrap.Modal(document.getElementById('customerInfoModal'));
    document.getElementById('modalTitle').innerText = customerName + ' - Олинган маҳсулотлар';
    document.getElementById('customerInfoBody').innerHTML = 'Загрузка...';
    modal.show();

    fetch('?get_customer_info=' + customerId)
    .then(r => r.text())
    .then(html => {
        document.getElementById('customerInfoBody').innerHTML = html;
    });
}
</script>
