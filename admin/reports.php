<?php
require_once 'layout.php';

$success = '';
$error = '';

// Handle Excel Export (Styled)
if (isset($_GET['export'])) {
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=lifestore_sales_report_" . date('Y-m-d') . ".xls");
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <style>
            .header-info { font-size: 18px; font-weight: bold; color: #4e73df; margin-bottom: 20px; }
            table { border-collapse: collapse; width: 100%; border: 1px solid #000; }
            th { background-color: #4e73df; color: #ffffff; border: 1px solid #000; padding: 10px; font-weight: bold; }
            td { border: 1px solid #000; padding: 8px; text-align: left; }
            .money { text-align: right; mso-number-format:"\#\,\#\#0"; }
            .cash { color: #1cc88a; }
            .card { color: #36b9cc; }
            .debt { color: #e74a3b; font-weight: bold; }
            .total-row { background-color: #f8f9fc; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="header-info">LIFESTORE - СОТУВЛАР ВА МОЛИЯВИЙ ҲИСОБОТ (' . date('d.m.Y H:i') . ')</div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Сана</th>
                    <th>Мижоз</th>
                    <th>Кассир</th>
                    <th>Жами</th>
                    <th>Нахт</th>
                    <th>Карта</th>
                    <th>Қарз</th>
                </tr>
            </thead>
            <tbody>';
    
    $where = "WHERE 1=1";
    if (!empty($_GET['category_id'])) $where .= " AND p.category_id = " . (int)$_GET['category_id'];
    if (!empty($_GET['customer_id'])) $where .= " AND s.customer_id = " . (int)$_GET['customer_id'];
    if (!empty($_GET['user_id'])) $where .= " AND s.user_id = " . (int)$_GET['user_id'];
    if (!empty($_GET['from_date'])) $where .= " AND s.date >= '" . $conn->real_escape_string($_GET['from_date']) . " 00:00:00'";
    if (!empty($_GET['to_date'])) $where .= " AND s.date <= '" . $conn->real_escape_string($_GET['to_date']) . " 23:59:59'";

    $sql = "SELECT s.*, c.name as customer_name, u.name as user_name FROM sales s 
            LEFT JOIN customers c ON s.customer_id = c.id 
            LEFT JOIN users u ON s.user_id = u.id 
            $where ORDER BY s.date DESC";
            
    $query = $conn->query($sql);
    $total_all = 0;
    $cash_all = 0;
    $card_all = 0;
    $debt_all = 0;
    $count = 0;

    while ($row = $query->fetch_assoc()) {
        $total_all += (float)$row['total_amount'];
        $cash_all += (float)$row['cash_amount'];
        $card_all += (float)$row['card_amount'];
        $debt_all += (float)$row['debt_amount'];
        $count++;

        echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['date']}</td>
                <td>" . ($row['customer_name'] ?: 'Оддий мижоз') . "</td>
                <td>{$row['user_name']}</td>
                <td class='money'><b>" . number_format($row['total_amount'], 0, '.', ' ') . " сум</b></td>
                <td class='money cash'>" . number_format($row['cash_amount'], 0, '.', ' ') . " сум</td>
                <td class='money card'>" . number_format($row['card_amount'], 0, '.', ' ') . " сум</td>
                <td class='money debt'>" . number_format($row['debt_amount'], 0, '.', ' ') . " сум</td>
              </tr>";
    }

    echo '</tbody>
            <tfoot class="total-row">
                <tr>
                    <td colspan="4" style="text-align: right;">ФИЛТР БЎЙИЧА ЖАМИ:</td>
                    <td class="money">' . number_format($total_all, 0, '.', ' ') . ' сум</td>
                    <td class="money cash">' . number_format($cash_all, 0, '.', ' ') . ' сум</td>
                    <td class="money card">' . number_format($card_all, 0, '.', ' ') . ' сум</td>
                    <td class="money debt">' . number_format($debt_all, 0, '.', ' ') . ' сум</td>
                </tr>
                <tr>
                    <td colspan="4" style="text-align: right;">СОТУВЛАР СОНИ:</td>
                    <td>' . $count . ' та</td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        </table>
    </body>
    </html>';
    exit();
}

// AJAX handler for sale details
if (isset($_GET['get_details'])) {
    $sale_id = (int)$_GET['get_details'];
    $items = $conn->query("SELECT si.*, p.name, p.unit FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id = $sale_id");
    $html = '<table class="table"><thead><tr><th>Маҳсулот</th><th>Миқдор</th><th>Нарх</th><th>Жами</th></tr></thead><tbody>';
    $total = 0;
    while ($item = $items->fetch_assoc()) {
        $subtotal = $item['price'] * $item['quantity'];
        $total += $subtotal;
        $html .= "<tr><td>{$item['name']}</td><td>" . (float)$item['quantity'] . " " . $item['unit'] . "</td><td>" . formatMoney($item['price']) . "</td><td>" . formatMoney($subtotal) . "</td></tr>";
    }
    $html .= '</tbody><tfoot><tr><th colspan="3" class="text-end">Жами:</th><th>' . formatMoney($total) . '</th></tr></tfoot></table>';
    echo $html;
    exit();
}

// Handle Sale Cancellation
if (isset($_POST['cancel_sale'])) {
    $sale_id = (int)$_POST['sale_id'];
    
    $conn->begin_transaction();
    try {
        $sale = $conn->query("SELECT * FROM sales WHERE id = $sale_id FOR UPDATE")->fetch_assoc();
        if ($sale['status'] === 'cancelled') throw new Exception("Сотув аллақачон бекор қилинган!");

        // Return items to inventory
        $items = $conn->query("SELECT * FROM sale_items WHERE sale_id = $sale_id");
        while ($item = $items->fetch_assoc()) {
            $conn->query("UPDATE products SET quantity = quantity + {$item['quantity']} WHERE id = {$item['product_id']}");
        }

        // Restore customer balance if debt existed
        if ($sale['customer_id'] && $sale['debt_amount'] > 0) {
            $conn->query("UPDATE customers SET balance = balance + {$sale['debt_amount']} WHERE id = {$sale['customer_id']}");
        }

        $conn->query("UPDATE sales SET status = 'cancelled' WHERE id = $sale_id");
        
        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

render_header('Ҳисоботлар');
?>

<div class="card p-4 mb-4">
    <h5 class="fw-bold mb-4">Филтрлар</h5>
    <form method="GET" class="row g-3">
        <div class="col-md-3">
            <label class="form-label">Даврдан</label>
            <input type="date" name="from_date" class="form-control" value="<?php echo $_GET['from_date'] ?? ''; ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Давргача</label>
            <input type="date" name="to_date" class="form-control" value="<?php echo $_GET['to_date'] ?? ''; ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Мижоз</label>
            <select name="customer_id" class="form-select">
                <option value="">Барчаси</option>
                <?php
                $customers = $conn->query("SELECT * FROM customers ORDER BY name ASC");
                while($c = $customers->fetch_assoc()) echo "<option value='{$c['id']}' ".((isset($_GET['customer_id']) && $_GET['customer_id'] == $c['id']) ? 'selected' : '').">{$c['name']}</option>";
                ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Ходим</label>
            <select name="user_id" class="form-select">
                <option value="">Барчаси</option>
                <?php
                $users = $conn->query("SELECT * FROM users ORDER BY name ASC");
                while($u = $users->fetch_assoc()) echo "<option value='{$u['id']}' ".((isset($_GET['user_id']) && $_GET['user_id'] == $u['id']) ? 'selected' : '').">{$u['name']}</option>";
                ?>
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter"></i> Қидириш</button>
        </div>
    </form>
</div>

<div class="card p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="fw-bold m-0">Сотувлар рўйхати</h5>
        <a href="?export=1&<?php echo $_SERVER['QUERY_STRING']; ?>" class="btn btn-success"><i class="bi bi-file-earmark-spreadsheet"></i> Excel га (CSV)</a>
    </div>
    
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Сана</th>
                    <th>Мижоз</th>
                    <th>Ходим</th>
                    <th>Жами</th>
                    <th>Нахт</th>
                    <th>Карта</th>
                    <th>Қарз</th>
                    <th>Амаллар</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $where = "WHERE 1=1";
                if (!empty($_GET['customer_id'])) $where .= " AND s.customer_id = " . (int)$_GET['customer_id'];
                if (!empty($_GET['user_id'])) $where .= " AND s.user_id = " . (int)$_GET['user_id'];
                if (!empty($_GET['from_date'])) $where .= " AND s.date >= '" . $conn->real_escape_string($_GET['from_date']) . " 00:00:00'";
                if (!empty($_GET['to_date'])) $where .= " AND s.date <= '" . $conn->real_escape_string($_GET['to_date']) . " 23:59:59'";

                $sql = "SELECT s.*, c.name as customer_name, u.name as user_name FROM sales s 
                        LEFT JOIN customers c ON s.customer_id = c.id 
                        LEFT JOIN users u ON s.user_id = u.id 
                        $where ORDER BY s.date DESC";
                $query = $conn->query($sql);
                $total_all = 0;
                $total_cash = 0;
                $total_card = 0;
                $total_debt = 0;
                
                while($row = $query->fetch_assoc()):
                    if ($row['status'] !== 'cancelled') {
                        $total_all += $row['total_amount'];
                        $total_cash += $row['cash_amount'];
                        $total_card += $row['card_amount'];
                        $total_debt += $row['debt_amount'];
                    }
                ?>
                <tr class="<?php echo $row['status'] === 'cancelled' ? 'table-danger opacity-75' : ''; ?>">
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo format_date($row['date']); ?></td>
                    <td><?php echo $row['customer_name'] ?: 'Оддий мижоз'; ?></td>
                    <td><?php echo $row['user_name']; ?></td>
                    <td class="fw-bold"><?php echo formatMoney($row['total_amount']); ?></td>
                    <td class="text-success"><?php echo formatMoney($row['cash_amount']); ?></td>
                    <td class="text-info"><?php echo formatMoney($row['card_amount']); ?></td>
                    <td class="text-danger"><?php echo formatMoney($row['debt_amount']); ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-info" onclick="viewSaleDetails(<?php echo $row['id']; ?>)" title="Инфо"><i class="bi bi-eye"></i></button>
                        <?php if($row['status'] === 'completed'): ?>
                            <button class="btn btn-sm btn-outline-danger" onclick="cancelSale(<?php echo $row['id']; ?>)" title="Отказ"><i class="bi bi-x-circle"></i></button>
                        <?php else: ?>
                            <span class="badge bg-danger">ОТКАЗ</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="4" class="text-end">Жами (Филтр бўйича):</th>
                    <th class="text-primary fw-bold"><?php echo formatMoney($total_all); ?></th>
                    <th class="text-success fw-bold"><?php echo formatMoney($total_cash); ?></th>
                    <th class="text-info fw-bold"><?php echo formatMoney($total_card); ?></th>
                    <th class="text-danger fw-bold"><?php echo formatMoney($total_debt); ?></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php render_footer(); ?>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Сотув тафсилотлари</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="detailsBody">
          Загрузка...
      </div>
    </div>
  </div>
</div>

<script>
function viewSaleDetails(saleId) {
    const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
    document.getElementById('detailsBody').innerHTML = 'Загрузка...';
    modal.show();

    fetch('?get_details=' + saleId)
    .then(r => r.text())
    .then(html => {
        document.getElementById('detailsBody').innerHTML = html;
    });
}

function cancelSale(saleId) {
    if(!confirm('Ҳақиқатан ҳам ушбу сотувни бекор қилмоқчимисиз? (Маҳсулотлар омборга қайтади ва қарз ўчирилади)')) return;

    let formData = new FormData();
    formData.append('cancel_sale', '1');
    formData.append('sale_id', saleId);

    fetch('reports.php', {
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
}
</script>
