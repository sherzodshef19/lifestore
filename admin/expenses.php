<?php
require_once 'layout.php';

// Handle Excel Export (Styled) - Must be before render_header
if (isset($_GET['export'])) {
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=lifestore_expenses_" . date('Y-m-d') . ".xls");
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <style>
            .header-info { font-size: 18px; font-weight: bold; color: #e74a3b; margin-bottom: 20px; }
            table { border-collapse: collapse; width: 100%; border: 1px solid #000; }
            th { background-color: #e74a3b; color: #ffffff; border: 1px solid #000; padding: 10px; font-weight: bold; }
            td { border: 1px solid #000; padding: 8px; text-align: left; }
            .money { text-align: right; mso-number-format:"\#\,\#\#0"; }
            .total-row { background-color: #f8f9fc; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="header-info">LIFESTORE - ХАРАЖАТЛАР ВА ЧИҚИМЛАР ҲИСОБОТИ (' . date('d.m.Y H:i') . ')</div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Сана ва Вақт</th>
                    <th>Ходим (Киритган)</th>
                    <th>Харажат сабаби</th>
                    <th>Суммаси</th>
                    <th>Изоҳ</th>
                </tr>
            </thead>
            <tbody>';
    
    $where = "WHERE 1=1";
    if (!empty($_GET['from_date'])) $where .= " AND e.date >= '" . $conn->real_escape_string($_GET['from_date']) . " 00:00:00'";
    if (!empty($_GET['to_date'])) $where .= " AND e.date <= '" . $conn->real_escape_string($_GET['to_date']) . " 23:59:59'";
    if (!empty($_GET['user_id'])) $where .= " AND e.user_id = " . (int)$_GET['user_id'];

    $sql = "SELECT e.*, u.name as user_name FROM expenses e JOIN users u ON e.user_id = u.id $where ORDER BY e.date DESC";
    $query = $conn->query($sql);
    $total_val = 0;
    while ($row = $query->fetch_assoc()) {
        $total_val += (float)$row['amount'];
        echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['date']}</td>
                <td>{$row['user_name']}</td>
                <td>{$row['category']}</td>
                <td class='money'>" . number_format($row['amount'], 0, '.', ' ') . " сум</td>
                <td>{$row['description']}</td>
              </tr>";
    }

    echo '</tbody>
            <tfoot class="total-row">
                <tr>
                    <td colspan="4" style="text-align: right;">УМУМИЙ ХАРАЖАТЛАР СУММАСИ:</td>
                    <td class="money">' . number_format($total_val, 0, '.', ' ') . ' сум</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </body>
    </html>';
    exit();
}

render_header('Харажатлар бошқаруви');

$success = '';
$error = '';

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($conn->query("DELETE FROM expenses WHERE id=$id")) {
        $success = "Харажат ўчирилди!";
    } else {
        $error = "Хатолик: " . $conn->error;
    }
}


$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-d');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');
$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : '';
?>

<div class="card p-4 mb-4">
    <h5 class="fw-bold mb-4">Давр бўйича филтр</h5>
    <form method="GET" class="row g-3">
        <div class="col-md-3">
            <label class="form-label">Даврдан</label>
            <input type="date" name="from_date" class="form-control" value="<?php echo $from_date; ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Давргача</label>
            <input type="date" name="to_date" class="form-control" value="<?php echo $to_date; ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Ходим</label>
            <select name="user_id" class="form-select">
                <option value="">Барчаси</option>
                <?php
                $users = $conn->query("SELECT id, name FROM users");
                while($u = $users->fetch_assoc()):
                ?>
                <option value="<?php echo $u['id']; ?>" <?php echo $user_id == $u['id'] ? 'selected' : ''; ?>><?php echo $u['name']; ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary me-2 w-100">ФИЛТР</button>
            <a href="expenses.php?export=1&from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>&user_id=<?php echo $user_id; ?>" class="btn btn-success w-100">EXCEL ГА</a>
        </div>
    </form>
</div>

<?php if($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>
<?php if($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card p-4">
    <h5 class="fw-bold mb-4">Барча харажатлар рўйхати</h5>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Сана ва Вақт</th>
                    <th>Ходим (Киритган)</th>
                    <th>Сабаби</th>
                    <th>Суммаси</th>
                    <th>Изоҳ</th>
                    <th class="text-center">Амал</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $where = "WHERE 1=1";
                if (!empty($from_date)) $where .= " AND e.date >= '" . $conn->real_escape_string($from_date) . " 00:00:00'";
                if (!empty($to_date)) $where .= " AND e.date <= '" . $conn->real_escape_string($to_date) . " 23:59:59'";
                if (!empty($user_id)) $where .= " AND e.user_id = " . (int)$user_id;

                $sql = "SELECT e.*, u.name as user_name FROM expenses e JOIN users u ON e.user_id = u.id $where ORDER BY e.date DESC";
                $res = $conn->query($sql);
                $total_sum = 0;
                
                while($row = $res->fetch_assoc()):
                    $total_sum += $row['amount'];
                ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td class="small text-muted"><?php echo date('d.m.Y H:i', strtotime($row['date'])); ?></td>
                    <td><span class="badge bg-light text-dark fw-normal"><?php echo $row['user_name']; ?></span></td>
                    <td><span class="fw-bold"><?php echo $row['category']; ?></span></td>
                    <td class="text-danger fw-bold"><?php echo formatMoney($row['amount']); ?></td>
                    <td class="small text-muted italic"><?php echo $row['description'] ?: '—'; ?></td>
                    <td class="text-center">
                        <a href="?delete=<?php echo $row['id']; ?>&from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Харажатни ўчиришни тасдиқлайсизми?')">
                            <i class="bi bi-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot class="table-light fw-bold border-top-2">
                <tr>
                    <td colspan="4" class="text-end py-3 text-secondary text-uppercase small">Жами харажатлар:</td>
                    <td colspan="3" class="py-3 text-danger fs-5"><?php echo formatMoney($total_sum); ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php render_footer(); ?>
