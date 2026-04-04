<?php
require_once 'layout.php';
render_header('Маҳсулотлар рўйхати');
?>

<div class="card p-4">
    <div class="input-group mb-4">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" id="productListSearch" class="form-control" placeholder="Ном ёки категория бўйича излаш...">
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Расм</th>
                    <th>Номи</th>
                    <th>Категория</th>
                    <th>Сони</th>
                    <th>Нархи</th>
                    <th>Штрих-код</th>
                </tr>
            </thead>
            <tbody id="productTableBody">
                <?php
                $prods = $conn->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.name ASC");
                while($p = $prods->fetch_assoc()):
                ?>
                <tr class="<?php echo $p['quantity'] < 5 ? 'low-stock' : ''; ?>">
                    <td>
                        <?php if($p['image']): ?>
                            <img src="../<?php echo $p['image']; ?>" width="40" height="40" style="object-fit: cover; border-radius: 5px;">
                        <?php else: ?>
                            <div class="bg-light d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; border-radius: 5px;"><i class="bi bi-image text-muted"></i></div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $p['name']; ?></td>
                    <td><?php echo $p['category_name']; ?></td>
                    <td><span class="badge <?php echo $p['quantity'] < 5 ? 'bg-danger' : 'bg-primary'; ?>"><?php echo $p['quantity']; ?></span></td>
                    <td><?php echo formatMoney($p['sale_price']); ?></td>
                    <td><code><?php echo $p['barcode']; ?></code></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.getElementById('productListSearch').addEventListener('input', function() {
    let q = this.value.toLowerCase();
    document.querySelectorAll('#productTableBody tr').forEach(row => {
        let text = row.innerText.toLowerCase();
        row.style.display = text.indexOf(q) > -1 ? '' : 'none';
    });
});
</script>

<?php render_footer(); ?>
