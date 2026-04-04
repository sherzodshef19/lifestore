<?php
require_once 'layout.php';

$success = '';
$error = '';

if (isset($_POST['add_stock'])) {
    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    $cost_price = (float)$_POST['cost_price'];
    $sale_price = (float)$_POST['sale_price'];

    $conn->begin_transaction();
    try {
        // Update product table
        $conn->query("UPDATE products SET quantity = quantity + $quantity, cost_price = $cost_price, sale_price = $sale_price WHERE id = $product_id");
        
        // Log to stock history
        $conn->query("INSERT INTO stock_history (product_id, quantity, cost_price, sale_price, type) VALUES ($product_id, $quantity, $cost_price, $sale_price, 'in')");
        
        $conn->commit();
        $success = "Сток янгиланди ва тарихга қўшилди!";
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Хатолик: " . $e->getMessage();
    }
}

render_header('Приход қилиш');
?>

<div class="row">
    <div class="col-md-5">
        <div class="card p-4">
            <h5 class="fw-bold mb-4">Янги приход</h5>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Маҳсулот</label>
                    <select name="product_id" class="form-select select2" required>
                        <option value="">Танланг...</option>
                        <?php
                        $prods = $conn->query("SELECT * FROM products ORDER BY name ASC");
                        while($p = $prods->fetch_assoc()):
                        ?>
                        <option value="<?php echo $p['id']; ?>" data-cost="<?php echo $p['cost_price']; ?>" data-sale="<?php echo $p['sale_price']; ?>"><?php echo $p['name']; ?> (<?php echo $p['barcode']; ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Сони</label>
                    <input type="number" name="quantity" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Келган нархи</label>
                    <input type="number" step="0.01" name="cost_price" id="cost_price" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Сотиш нархи</label>
                    <input type="number" step="0.01" name="sale_price" id="sale_price" class="form-control" required>
                </div>
                <button type="submit" name="add_stock" class="btn btn-primary w-100">ҚЎШИШ</button>
            </form>
        </div>
    </div>
    <div class="col-md-7">
        <?php if($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        
        <div class="card p-4">
            <h5 class="fw-bold mb-4">Охирги приходлар</h5>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Сана</th>
                            <th>Маҳсулот</th>
                            <th>Сони</th>
                            <th>Келган нарх</th>
                            <th>Сотиш нарх</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $history = $conn->query("SELECT h.*, p.name FROM stock_history h JOIN products p ON h.product_id = p.id WHERE h.type = 'in' ORDER BY h.created_at DESC LIMIT 10");
                        while($h = $history->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?php echo format_date($h['created_at']); ?></td>
                            <td><?php echo $h['name']; ?></td>
                            <td><span class="badge bg-success">+<?php echo $h['quantity']; ?></span></td>
                            <td><?php echo formatMoney($h['cost_price']); ?></td>
                            <td><?php echo formatMoney($h['sale_price']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-fill prices when product selected
document.querySelector('select[name="product_id"]').addEventListener('change', function() {
    let option = this.options[this.selectedIndex];
    document.getElementById('cost_price').value = option.getAttribute('data-cost') || '';
    document.getElementById('sale_price').value = option.getAttribute('data-sale') || '';
});
</script>

<?php render_footer(); ?>
