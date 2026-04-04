<?php
require_once 'layout.php';

$success = '';
$error = '';

// Handle Excel Export (Styled)
if (isset($_GET['export'])) {
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=lifestore_products_" . date('Y-m-d') . ".xls");
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <style>
            .header-info { font-size: 18px; font-weight: bold; color: #4e73df; margin-bottom: 20px; }
            table { border-collapse: collapse; width: 100%; border: 1px solid #000; }
            th { background-color: #4e73df; color: #ffffff; border: 1px solid #000; padding: 10px; font-weight: bold; }
            td { border: 1px solid #000; padding: 8px; text-align: left; }
            .money { text-align: right; }
            .barcode { mso-number-format:"\@"; }
            .qty { mso-number-format:"\#\,\#\#0.\#\#\#"; text-align: center; }
            .total-row { background-color: #f8f9fc; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="header-info">LIFESTORE - МАҲСУЛОТЛАР ҲИСОБОТИ (' . date('d.m.Y H:i') . ')</div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Номи</th>
                    <th>Категория</th>
                    <th>Сони</th>
                    <th>Бирлик</th>
                    <th>Келган нархи</th>
                    <th>Сотиш нархи</th>
                    <th>Штрих-код</th>
                    <th>Қиймати (Келган нархда)</th>
                </tr>
            </thead>
            <tbody>';
    
    $query = $conn->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC");
    $total_qty = 0;
    $total_value = 0;
    $count = 0;

    while ($row = $query->fetch_assoc()) {
        $row_value = $row['quantity'] * $row['cost_price'];
        $total_qty += (float)$row['quantity'];
        $total_value += $row_value;
        $count++;

        echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['name']}</td>
                <td>{$row['category_name']}</td>
                <td class='qty'>" . (float)$row['quantity'] . "</td>
                <td>{$row['unit']}</td>
                <td class='money'>" . number_format($row['cost_price'], 0, '.', ' ') . " сум</td>
                <td class='money'>" . number_format($row['sale_price'], 0, '.', ' ') . " сум</td>
                <td class='barcode'>{$row['barcode']}</td>
                <td class='money'>" . number_format($row_value, 0, '.', ' ') . " сум</td>
              </tr>";
    }

    echo '</tbody>
            <tfoot class="total-row">
                <tr>
                    <td colspan="3" style="text-align: right;">ЖАМИ:</td>
                    <td class="qty">' . $total_qty . '</td>
                    <td>—</td>
                    <td colspan="3" style="text-align: right;">УМУМИЙ ҚИЙМАТИ:</td>
                    <td class="money">' . number_format($total_value, 0, '.', ' ') . ' сум</td>
                </tr>
                <tr>
                    <td colspan="3" style="text-align: right;">МАҲСУЛОТ ТУРЛАРИ:</td>
                    <td>' . $count . '</td>
                    <td colspan="5"></td>
                </tr>
            </tfoot>
        </table>
    </body>
    </html>';
    exit();
}

// Handle CSV Import
if (isset($_POST['import_csv'])) {
    if ($_FILES['csv_file']['name']) {
        $filename = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($filename, "r");
        fgetcsv($handle); // Skip header
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $name = $conn->real_escape_string($data[1]);
            $cat_name = $conn->real_escape_string($data[2]);
            $qty = (float)$data[3];
            $unit = $conn->real_escape_string($data[4]);
            $cost = (float)$data[5];
            $sale = (float)$data[6];
            $barcode = $conn->real_escape_string($data[7]);
            
            // Find category ID
            $cat_res = $conn->query("SELECT id FROM categories WHERE name = '$cat_name'");
            $cat_id = ($cat_res && $row = $cat_res->fetch_assoc()) ? $row['id'] : null;
            
            $conn->query("INSERT INTO products (name, category_id, quantity, unit, cost_price, sale_price, barcode) 
                          VALUES ('$name', ".($cat_id ? $cat_id : 'NULL').", $qty, '$unit', $cost, $sale, '$barcode')
                          ON DUPLICATE KEY UPDATE quantity = quantity + $qty");
        }
        fclose($handle);
        $success = "Импорт муваффақиятли якунланди!";
    }
}

// Handle Add/Edit
if (isset($_POST['save_product'])) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = $conn->real_escape_string($_POST['name']);
    $category_id = (int)$_POST['category_id'];
    $cost_price = (float)$_POST['cost_price'];
    $sale_price = (float)$_POST['sale_price'];
    $barcode = $conn->real_escape_string($_POST['barcode']);
    $quantity = (float)$_POST['quantity'];
    $unit = $conn->real_escape_string($_POST['unit']);

    $image_path = '';
    if ($_FILES['image']['name']) {
        $target_dir = "../uploads/products/";
        $image_path = "uploads/products/" . time() . "_" . basename($_FILES["image"]["name"]);
        move_uploaded_file($_FILES["image"]["tmp_name"], "../" . $image_path);
    } else {
        $image_path = $_POST['existing_image'];
    }

    // Check for duplicate barcode
    $check = $conn->query("SELECT id FROM products WHERE barcode = '$barcode' AND id != $id");
    if ($check->num_rows > 0) {
        $error = "Хатолик: Бу штрих-код ($barcode) бошқа маҳсулотга бириктирилган!";
    } else {
        if ($id > 0) {
            $sql = "UPDATE products SET name='$name', category_id=$category_id, cost_price=$cost_price, sale_price=$sale_price, barcode='$barcode', quantity=$quantity, unit='$unit', image='$image_path' WHERE id=$id";
        } else {
            $sql = "INSERT INTO products (name, category_id, cost_price, sale_price, barcode, quantity, unit, image) VALUES ('$name', $category_id, $cost_price, $sale_price, '$barcode', $quantity, '$unit', '$image_path')";
        }

        if ($conn->query($sql)) {
            $success = "Маълумотлар сақланди!";
        } else {
            $error = "Хатолик: " . $conn->error;
        }
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM products WHERE id = $id");
    $success = "Товар ўчирилди!";
}

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$edit_product = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $edit_product = $conn->query("SELECT * FROM products WHERE id = $id")->fetch_assoc();
}

$low_count = $conn->query("SELECT COUNT(*) FROM products WHERE quantity < 5")->fetch_row()[0];
render_header('Товарлар');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="?action=list" class="btn btn-outline-primary <?php echo ($action === 'list' && !isset($_GET['filter'])) ? 'active' : ''; ?>">Рўйхат</a>
        <a href="?action=list&filter=low" class="btn btn-outline-danger <?php echo (isset($_GET['filter']) && $_GET['filter'] === 'low') ? 'active' : ''; ?>">
            Кам қолганлар <span class="badge bg-danger ms-1"><?php echo $low_count; ?></span>
        </a>
        <a href="?action=add" class="btn btn-outline-primary <?php echo $action === 'add' ? 'active' : ''; ?>">Янги қўшиш</a>
    </div>
    <div>
        <a href="?export=1" class="btn btn-success me-2"><i class="bi bi-file-earmark-spreadsheet"></i> Excel га (CSV)</a>
        <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#importModal"><i class="bi bi-file-earmark-arrow-up"></i> Импорт</button>
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
                        <th>Расм</th>
                        <th>Номи</th>
                        <th>Категория</th>
                        <th>Сони</th>
                        <th>Келган нарх</th>
                        <th>Сотиш нарх</th>
                        <th>Штрих</th>
                        <th>Сана</th>
                        <th class="text-end">Амаллар</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id";
                    if (isset($_GET['filter']) && $_GET['filter'] === 'low') {
                        $sql .= " WHERE p.quantity < 5";
                    }
                    $sql .= " ORDER BY p.id DESC";
                    $products = $conn->query($sql);
                    
                    $total_qty = 0;
                    $total_cost_val = 0;
                    $total_sale_val = 0;
                    $product_count = 0;

                    while($p = $products->fetch_assoc()):
                        $total_qty += (float)$p['quantity'];
                        $total_cost_val += (float)$p['cost_price'] * (float)$p['quantity'];
                        $total_sale_val += (float)$p['sale_price'] * (float)$p['quantity'];
                        $product_count++;
                    ?>
                    <tr class="<?php echo $p['quantity'] < 5 ? 'low-stock' : ''; ?>">
                        <td>
                            <?php if($p['image']): ?>
                                <img src="../<?php echo $p['image']; ?>" width="50" height="50" style="object-fit: cover; border-radius: 5px;">
                            <?php else: ?>
                                <div class="bg-light d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; border-radius: 5px;"><i class="bi bi-image text-muted"></i></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $p['name']; ?></td>
                        <td><?php echo $p['category_name']; ?></td>
                        <td>
                            <span class="badge <?php echo $p['quantity'] < 5 ? 'bg-danger' : 'bg-primary'; ?>">
                                <?php echo (float)$p['quantity']; ?> <?php echo $p['unit']; ?>
                            </span>
                        </td>
                        <td><?php echo formatMoney($p['cost_price']); ?></td>
                        <td><?php echo formatMoney($p['sale_price']); ?></td>
                        <td><code><?php echo $p['barcode']; ?></code></td>
                        <td class="small text-muted"><?php echo date("d.m.Y", strtotime($p['created_at'])); ?></td>
                        <td class="text-end">
                            <a href="print_barcode.php?id=<?php echo $p['id']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Штрих-код чиқариш"><i class="bi bi-upc-scan"></i></a>
                            <a href="?action=edit&id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                            <a href="?delete=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Ўчирмоқчимисиз?')"><i class="bi bi-trash"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot class="table-light fw-bold border-top-2">
                    <tr>
                        <td colspan="3" class="text-end py-3">ЖАМИ (<?php echo $product_count; ?> тур):</td>
                        <td class="text-primary py-3"><?php echo $total_qty; ?></td>
                        <td class="text-success py-3 small"><?php echo formatMoney($total_cost_val); ?></td>
                        <td class="text-info py-3 small"><?php echo formatMoney($total_sale_val); ?></td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="card p-4">
        <h5 class="fw-bold mb-4"><?php echo $action === 'edit' ? 'Товарни таҳрирлаш' : 'Янги товар'; ?></h5>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo $edit_product ? $edit_product['id'] : ''; ?>">
            <input type="hidden" name="existing_image" value="<?php echo $edit_product ? $edit_product['image'] : ''; ?>">
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Товар номи</label>
                    <input type="text" name="name" class="form-control" value="<?php echo $edit_product ? $edit_product['name'] : ''; ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Категория</label>
                    <select name="category_id" class="form-select">
                        <option value="">Танланг...</option>
                        <?php
                        $cats = $conn->query("SELECT * FROM categories");
                        while($c = $cats->fetch_assoc()):
                        ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($edit_product && $edit_product['category_id'] == $c['id']) ? 'selected' : ''; ?>><?php echo $c['name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Келган нархи</label>
                    <input type="number" step="0.01" name="cost_price" class="form-control" value="<?php echo $edit_product ? $edit_product['cost_price'] : ''; ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Сотиш нархи</label>
                    <input type="number" step="0.01" name="sale_price" class="form-control" value="<?php echo $edit_product ? $edit_product['sale_price'] : ''; ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Сони</label>
                    <div class="input-group">
                        <input type="number" step="0.001" name="quantity" class="form-control" value="<?php echo $edit_product ? (float)$edit_product['quantity'] : ''; ?>" required>
                        <select name="unit" class="form-select" required style="max-width: 100px;">
                            <option value="шт" <?php echo ($edit_product && $edit_product['unit'] == 'шт') ? 'selected' : ''; ?>>шт</option>
                            <option value="кг" <?php echo ($edit_product && $edit_product['unit'] == 'кг') ? 'selected' : ''; ?>>кг</option>
                            <option value="метр" <?php echo ($edit_product && $edit_product['unit'] == 'метр') ? 'selected' : ''; ?>>метр</option>
                            <option value="литр" <?php echo ($edit_product && $edit_product['unit'] == 'литр') ? 'selected' : ''; ?>>литр</option>
                            <option value="боғ" <?php echo ($edit_product && $edit_product['unit'] == 'боғ') ? 'selected' : ''; ?>>боғ</option>
                            <option value="паз" <?php echo ($edit_product && $edit_product['unit'] == 'паз') ? 'selected' : ''; ?>>паз</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Штрих-код</label>
                    <div class="input-group">
                        <input type="text" name="barcode" id="barcodeInput" class="form-control" value="<?php echo $edit_product ? $edit_product['barcode'] : ''; ?>">
                        <button type="button" class="btn btn-outline-secondary" onclick="generateBarcode()">ГЕНЕРАЦИЯ</button>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Расм</label>
                    <input type="file" name="image" class="form-control">
                </div>
            </div>
            <button type="submit" name="save_product" class="btn btn-primary mt-4 px-5">САҚЛАШ</button>
        </form>
    </div>
<?php endif; ?>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Excel (CSV) дан импорт қилиш</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
        <div class="modal-body">
            <p class="text-muted small">Файл устунлари: ID, Номи, Категория, Сони, Келган нарх, Сотиш нарх, Штрих-код, Сана</p>
            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
        </div>
        <div class="modal-footer">
            <button type="submit" name="import_csv" class="btn btn-primary">ИМПОРТ</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function generateBarcode() {
    // Generate a random 12-digit number (EAN-13 like, without checksum for simplicity)
    let barcode = "";
    for(let i = 0; i < 12; i++) {
        barcode += Math.floor(Math.random() * 10);
    }
    document.getElementById('barcodeInput').value = barcode;
}
</script>

<?php render_footer(); ?>
