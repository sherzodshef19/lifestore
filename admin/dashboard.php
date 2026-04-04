<?php
require_once 'layout.php';

// Automatic Daily Backup Check
backupDatabaseToTelegram();

// Fetch statistics
$product_count = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
$low_stock_count = $conn->query("SELECT COUNT(*) as count FROM products WHERE quantity < 5")->fetch_assoc()['count'];
$customer_count = $conn->query("SELECT COUNT(*) as count FROM customers")->fetch_assoc()['count'];
$total_customers_balance = $conn->query("SELECT SUM(balance) as total FROM customers")->fetch_assoc()['total'];

render_header('Дашборд');
?>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card p-3">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0 bg-primary text-white p-3 rounded-3 me-3">
                    <i class="bi bi-box-seam fs-3"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Товарлар</h6>
                    <h4 class="mb-0 fw-bold"><?php echo $product_count; ?></h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0 bg-danger text-white p-3 rounded-3 me-3">
                    <i class="bi bi-exclamation-triangle fs-3"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Кам қолган</h6>
                    <h4 class="mb-0 fw-bold"><?php echo $low_stock_count; ?></h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0 bg-success text-white p-3 rounded-3 me-3">
                    <i class="bi bi-people fs-3"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Мижозлар</h6>
                    <h4 class="mb-0 fw-bold"><?php echo $customer_count; ?></h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0 bg-warning text-dark p-3 rounded-3 me-3">
                    <i class="bi bi-cash-stack fs-3"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1">Мижозлар баланси</h6>
                    <h4 class="mb-0 fw-bold"><?php echo formatMoney($total_customers_balance); ?></h4>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card p-4">
            <h5 class="mb-4 fw-bold">Охирги товарлар</h5>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Товар номи</th>
                            <th>Нархи</th>
                            <th>Сони</th>
                            <th>Ҳолати</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $latest_products = $conn->query("SELECT * FROM products ORDER BY created_at DESC LIMIT 5");
                        while($p = $latest_products->fetch_assoc()):
                        ?>
                        <tr class="<?php echo $p['quantity'] < 5 ? 'low-stock' : ''; ?>">
                            <td><?php echo $p['name']; ?></td>
                            <td><?php echo formatMoney($p['sale_price']); ?></td>
                            <td><?php echo $p['quantity']; ?></td>
                            <td>
                                <?php if($p['quantity'] < 5): ?>
                                    <span class="badge bg-danger">Кам қолган</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Етарли</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-4">
            <h5 class="mb-4 fw-bold">Тезкор ҳаракатлар</h5>
            <div class="list-group list-group-flush">
                <a href="products.php?action=add" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-3">
                    <span><i class="bi bi-plus-circle me-2"></i> Янги товар қўшиш</span>
                    <i class="bi bi-chevron-right text-muted"></i>
                </a>
                <a href="stock.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-3">
                    <span><i class="bi bi-download me-2"></i> Приход қилиш</span>
                    <i class="bi bi-chevron-right text-muted"></i>
                </a>
                <a href="customers.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-3">
                    <span><i class="bi bi-person-plus me-2"></i> Мижоз қўшиш</span>
                    <i class="bi bi-chevron-right text-muted"></i>
                </a>
                <a href="reports.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-3">
                    <span><i class="bi bi-file-earmark-excel me-2"></i> Экспорт Excel</span>
                    <i class="bi bi-chevron-right text-muted"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<?php render_footer(); ?>
