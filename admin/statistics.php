<?php
require_once 'layout.php';

// 1. Last 7 Days Revenue (ASC for charts)
$sales_by_day_res = $conn->query("SELECT DATE(date) as day, SUM(total_amount) as total FROM sales WHERE status = 'completed' GROUP BY day ORDER BY day ASC LIMIT 7");
$revenue_labels = [];
$revenue_data = [];
$table_data = [];
while($s = $sales_by_day_res->fetch_assoc()) {
    $date = date("d.m.Y", strtotime($s['day']));
    $revenue_labels[] = $date;
    $revenue_data[] = (float)$s['total'];
    $table_data[] = $s;
}
$table_data = array_reverse($table_data); // Reverse back for table view (DESC)

// 2. Top 5 Products
$top_products_res = $conn->query("SELECT p.name, SUM(si.quantity) as qty FROM sale_items si JOIN sales s ON si.sale_id = s.id JOIN products p ON si.product_id = p.id WHERE s.status = 'completed' GROUP BY p.id ORDER BY qty DESC LIMIT 5");
$product_labels = [];
$product_data = [];
$product_table = [];
while($p = $top_products_res->fetch_assoc()) {
    $product_labels[] = $p['name'];
    $product_data[] = (float)$p['qty'];
    $product_table[] = $p;
}

// 3. Payment Totals
$s_tot = $conn->query("SELECT SUM(cash_amount) as c, SUM(card_amount) as k, SUM(debt_amount) as d FROM sales WHERE status = 'completed'")->fetch_assoc();
$r_tot = $conn->query("SELECT SUM(CASE WHEN payment_type='cash' THEN amount ELSE 0 END) as c, SUM(CASE WHEN payment_type='card' THEN amount ELSE 0 END) as k FROM debt_repayments")->fetch_assoc();

$total_cash = ($s_tot['c'] ?? 0) + ($r_tot['c'] ?? 0);
$total_card = ($s_tot['k'] ?? 0) + ($r_tot['k'] ?? 0);
$total_debt = ($s_tot['d'] ?? 0) - (($r_tot['c'] ?? 0) + ($r_tot['k'] ?? 0));

render_header('Статистика');
?>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="row g-4 mb-4">
    <div class="col-md-8">
        <div class="card p-4 h-100 border-0 shadow-sm">
            <h5 class="fw-bold mb-4">Охирги 7 кунлик тушум (Диаграмма)</h5>
            <canvas id="revenueChart" style="max-height: 300px;"></canvas>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-4 h-100 border-0 shadow-sm">
            <h5 class="fw-bold mb-4">Тўлов турлари (Улуш)</h5>
            <canvas id="paymentChart" style="max-height: 300px;"></canvas>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card p-4 border-0 shadow-sm">
            <h5 class="fw-bold mb-4">Савдо статистикаси (Жадвал)</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Кун</th>
                            <th class="text-end">Тушум</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($table_data as $s): ?>
                        <tr>
                            <td><?php echo date("d.m.Y", strtotime($s['day'])); ?></td>
                            <td class="text-end fw-bold text-success"><?php echo formatMoney($s['total']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card p-4 h-100 border-0 shadow-sm">
            <h5 class="fw-bold mb-4">Энг кўп сотилган товарлар</h5>
            <canvas id="productsChart" style="max-height: 250px;" class="mb-4"></canvas>
            <div class="table-responsive">
                <table class="table table-sm">
                    <tbody>
                        <?php foreach($product_table as $p): ?>
                        <tr>
                            <td><?php echo $p['name']; ?></td>
                            <td class="text-end fw-bold text-primary"><?php echo (float)$p['qty']; ?> та</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-2">
    <div class="col-md-12">
        <div class="card p-4 border-0 shadow-sm" style="background: linear-gradient(135deg, #ffffff 0%, #f8f9fc 100%);">
            <h5 class="fw-bold mb-4">Умумий молиявий ҳолат</h5>
            <div class="row text-center g-3">
                <div class="col-md-4">
                    <div class="p-4 border-start border-4 border-success rounded-3 bg-white shadow-sm">
                        <h6 class="text-muted text-uppercase small ls-1">Жами Нахт</h6>
                        <h3 class="fw-bold mb-0 text-success"><?php echo formatMoney($total_cash); ?></h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-4 border-start border-4 border-info rounded-3 bg-white shadow-sm">
                        <h6 class="text-muted text-uppercase small ls-1">Жами Карта</h6>
                        <h3 class="fw-bold mb-0 text-info"><?php echo formatMoney($total_card); ?></h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-4 border-start border-4 border-danger rounded-3 bg-white shadow-sm">
                        <h6 class="text-muted text-uppercase small ls-1">Жорий Қарз</h6>
                        <h3 class="fw-bold mb-0 text-danger"><?php echo formatMoney($total_debt); ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// 1. Revenue Line Chart
const revCtx = document.getElementById('revenueChart').getContext('2d');
new Chart(revCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($revenue_labels); ?>,
        datasets: [{
            label: 'Тушум (сум)',
            data: <?php echo json_encode($revenue_data); ?>,
            borderColor: '#4e73df',
            backgroundColor: 'rgba(78, 115, 223, 0.05)',
            fill: true,
            tension: 0.3,
            borderWidth: 3,
            pointRadius: 4,
            pointBackgroundColor: '#4e73df'
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { drawBorder: false } },
            x: { grid: { display: false } }
        }
    }
});

// 2. Products Bar Chart
const prodCtx = document.getElementById('productsChart').getContext('2d');
new Chart(prodCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($product_labels); ?>,
        datasets: [{
            label: 'Сони',
            data: <?php echo json_encode($product_data); ?>,
            backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'],
            borderRadius: 5
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { beginAtZero: true },
            y: { grid: { display: false } }
        }
    }
});

// 3. Payment Pie Chart
const payCtx = document.getElementById('paymentChart').getContext('2d');
new Chart(payCtx, {
    type: 'doughnut',
    data: {
        labels: ['Нахт', 'Карта', 'Қарз'],
        datasets: [{
            data: [<?php echo (float)$total_cash; ?>, <?php echo (float)$total_card; ?>, <?php echo (float)$total_debt; ?>],
            backgroundColor: ['#1cc88a', '#36b9cc', '#e74a3b'],
            hoverOffset: 10,
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        cutout: '70%',
        plugins: {
            legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } }
        }
    }
});
</script>

<style>
.ls-1 { letter-spacing: 1px; }
.card { transition: all 0.3s ease; }
.card:hover { transform: translateY(-5px); box-shadow: 0 1rem 3rem rgba(0,0,0,0.1) !important; }
</style>

<?php render_footer(); ?>
