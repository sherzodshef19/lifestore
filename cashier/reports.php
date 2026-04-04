<?php
require_once 'layout.php';

$user_id = $_SESSION['user_id'];
$where = "WHERE s.user_id = $user_id";

if (!empty($_GET['from_date'])) $where .= " AND s.date >= '" . $conn->real_escape_string($_GET['from_date']) . " 00:00:00'";
if (!empty($_GET['to_date'])) $where .= " AND s.date <= '" . $conn->real_escape_string($_GET['to_date']) . " 23:59:59'";

// AJAX handler for printing
if (isset($_GET['print_now'])) {
    require_once '../includes/printer_helper.php';
    $sale_id = (int)$_GET['print_now'];
    $res = printToIp($sale_id);
    echo json_encode(['success' => $res]);
    exit();
}

// AJAX handler for sale details
if (isset($_GET['get_details'])) {
    $sale_id = (int)$_GET['get_details'];
    $sale = $conn->query("SELECT s.*, c.name as customer_name FROM sales s LEFT JOIN customers c ON s.customer_id = c.id WHERE s.id = $sale_id")->fetch_assoc();
    $items = $conn->query("SELECT si.*, p.name, p.unit FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id = $sale_id");
    
    $html = '<div class="mb-4 d-flex justify-content-between align-items-center bg-light p-3 rounded-3">
                <div>
                    <div class="text-muted small text-uppercase fw-bold">Чек №' . $sale_id . '</div>
                    <div class="fw-bold fs-5">' . date('d.m.Y H:i', strtotime($sale['date'])) . '</div>
                </div>
                <div class="text-end">
                    <div class="text-muted small text-uppercase fw-bold">Мижоз</div>
                    <div class="fw-bold fs-6">' . ($sale['customer_name'] ?: 'Оддий мижоз') . '</div>
                </div>
            </div>';
            
    $html .= '<div class="table-responsive"><table class="table table-borderless align-middle">
                <thead class="text-muted small text-uppercase">
                    <tr class="border-bottom">
                        <th class="py-3">Маҳсулот</th>
                        <th class="py-3 text-center">Миқдор</th>
                        <th class="py-3 text-end">Нарх</th>
                        <th class="py-3 text-end">Жами</th>
                    </tr>
                </thead>
                <tbody>';
                
    $total = 0;
    while ($item = $items->fetch_assoc()) {
        $subtotal = $item['price'] * $item['quantity'];
        $total += $subtotal;
        $html .= "<tr class='border-bottom'>
                    <td class='py-3'>
                        <div class='fw-bold text-dark'>{$item['name']}</div>
                    </td>
                    <td class='py-3 text-center'><span class='badge bg-light text-dark rounded-pill px-3'>" . (float)$item['quantity'] . " " . $item['unit'] . "</span></td>
                    <td class='py-3 text-end'>" . formatMoney($item['price']) . "</td>
                    <td class='py-3 text-end fw-bold text-primary'>" . formatMoney($subtotal) . "</td>
                  </tr>";
    }
    
    $html .= '</tbody>
              </table></div>
              <div class="mt-4 p-3 rounded-4 bg-primary bg-opacity-10 d-flex justify-content-between align-items-center">
                <div class="fw-bold text-primary">ЖАМИ СУММА:</div>
                <div class="fs-4 fw-extrabold text-primary">' . formatMoney($total) . '</div>
              </div>';
    
    // Add payment breakdown
    $html .= '<div class="row g-2 mt-2">
                <div class="col-4"><div class="p-2 bg-light rounded-3 text-center small"><div class="text-muted">Нахт</div><div class="fw-bold text-success">' . formatMoney($sale['cash_amount']) . '</div></div></div>
                <div class="col-4"><div class="p-2 bg-light rounded-3 text-center small"><div class="text-muted">Карта</div><div class="fw-bold text-info">' . formatMoney($sale['card_amount']) . '</div></div></div>
                <div class="col-4"><div class="p-2 bg-light rounded-3 text-center small"><div class="text-muted">Қарз</div><div class="fw-bold text-danger">' . formatMoney($sale['debt_amount']) . '</div></div></div>
              </div>
              <div class="mt-4"><button class="btn btn-primary w-100 py-3" onclick="printReceipt(' . $sale_id . ')"><i class="bi bi-printer me-2"></i> ЧЕКНИ ЧИҚАРИШ</button></div>';
              
    echo $html;
    exit();
}

render_header('Менинг ҳисоботларим');
?>

<div class="card p-4 mb-4">
    <h5 class="fw-bold mb-4">Давр бўйича филтр</h5>
    <form method="GET" class="row g-3">
        <div class="col-md-5">
            <label class="form-label">Даврдан</label>
            <input type="date" name="from_date" class="form-control" value="<?php echo $_GET['from_date'] ?? ''; ?>">
        </div>
        <div class="col-md-5">
            <label class="form-label">Давргача</label>
            <input type="date" name="to_date" class="form-control" value="<?php echo $_GET['to_date'] ?? ''; ?>">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">Филтр</button>
        </div>
    </form>
</div>

<div class="card p-4">
    <h5 class="fw-bold mb-4">Сотувларим рўйхати</h5>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Сана</th>
                    <th>Мижоз</th>
                    <th>Жами</th>
                    <th>Нахт</th>
                    <th>Карта</th>
                    <th>Қарз</th>
                    <th>Инфо</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql = "SELECT s.*, c.name as customer_name FROM sales s 
                        LEFT JOIN customers c ON s.customer_id = c.id 
                        $where ORDER BY s.date DESC";
                $query = $conn->query($sql);
                $total_my = 0;
                $cash_total = 0;
                $card_total = 0;
                $debt_total = 0;
                
                while($row = $query->fetch_assoc()):
                    $total_my += $row['total_amount'];
                    $cash_total += (float)$row['cash_amount'];
                    $card_total += (float)$row['card_amount'];
                    $debt_total += (float)$row['debt_amount'];
                ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td class="text-nowrap small text-muted"><?php echo format_date($row['date']); ?></td>
                    <td><span class="badge bg-light text-dark fw-normal"><?php echo $row['customer_name'] ?: 'Оддий мижоз'; ?></span></td>
                    <td class="fw-bold text-dark"><?php echo formatMoney($row['total_amount']); ?></td>
                    <td class="text-success small fw-medium"><?php echo formatMoney($row['cash_amount']); ?></td>
                    <td class="text-info small fw-medium"><?php echo formatMoney($row['card_amount']); ?></td>
                    <td class="text-danger small fw-medium"><?php echo formatMoney($row['debt_amount']); ?></td>
                    <td class="text-end text-nowrap">
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-primary" onclick="viewDetails(<?php echo $row['id']; ?>)" title="Кўриш">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="window.open('../print_receipt.php?id=<?php echo $row['id']; ?>', '_blank')" title="Печат">
                                <i class="bi bi-printer"></i>
                            </button>
                        </div>
                    </td>
                </tr>
<?php endwhile; ?>
            </tbody>
            <tfoot class="table-light fw-bold border-top-2">
                <tr>
                    <td colspan="3" class="text-end py-3 text-secondary text-uppercase small">Жами (Филтр бўйича):</td>
                    <td class="py-3 text-primary fs-5"><?php echo formatMoney($total_my); ?></td>
                    <td class="py-3 text-success"><?php echo formatMoney($cash_total); ?></td>
                    <td class="py-3 text-info"><?php echo formatMoney($card_total); ?></td>
                    <td class="py-3 text-danger"><?php echo formatMoney($debt_total); ?></td>
                    <td></td>
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
function viewDetails(saleId) {
    const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
    document.getElementById('detailsBody').innerHTML = 'Загрузка...';
    modal.show();

    fetch('?get_details=' + saleId)
    .then(r => r.text())
    .then(html => {
        document.getElementById('detailsBody').innerHTML = html;
    });
}

function printReceipt(saleId) {
    // Show loading or disable buttons if needed
    fetch('?print_now=' + saleId)
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            alert('Чек принтерга юборилди!');
        } else {
            alert('Принтер билан алоқада хатолик!');
        }
    })
    .catch(err => {
        alert('Тизимда хатолик: ' + err);
    });
}
</script>
