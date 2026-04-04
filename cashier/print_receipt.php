<?php
require_once '../config.php';

if (!isset($_GET['id'])) die('ID NOT FOUND');
$sale_id = (int)$_GET['id'];

// Fetch sale details
$sale = $conn->query("SELECT s.*, c.name as customer_name, u.name as cashier_name 
                      FROM sales s 
                      LEFT JOIN customers c ON s.customer_id = c.id 
                      LEFT JOIN users u ON s.user_id = u.id 
                      WHERE s.id = $sale_id")->fetch_assoc();

if (!$sale) die('SALE NOT FOUND');

$items = $conn->query("SELECT si.*, p.name 
                       FROM sale_items si 
                       JOIN products p ON si.product_id = p.id 
                       WHERE si.sale_id = $sale_id");
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Чек #<?php echo $sale_id; ?></title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            width: 80mm;
            margin: 0 auto;
            padding: 5mm;
            font-size: 14px;
            color: #000;
        }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .bold { font-weight: bold; }
        .dashed-line { border-top: 1px dashed #000; margin: 10px 0; }
        .item-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .item-name { width: 60%; }
        .item-price { width: 40%; text-align: right; }
        @media print {
            body { width: 100%; padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="text-center">
    <h2 style="margin: 0;"><?php echo strtoupper(getSetting('receipt_store_name', 'LIFE STORE')); ?></h2>
    <p style="margin: 5px 0;"><?php echo getSetting('receipt_header_msg', 'ИНВЕНТАРНИ БОШҚАРИШ ТИЗИМИ'); ?></p>
    <p style="margin: 5px 0; font-size: 12px;"><?php echo getSetting('receipt_address', ''); ?> | <?php echo getSetting('receipt_phone', ''); ?></p>
    <p style="margin: 5px 0; font-size: 12px;"><?php echo format_date($sale['date']); ?></p>
</div>

<div class="dashed-line"></div>

<div style="font-size: 12px; margin-bottom: 10px;">
    <div>Чек №: <span class="bold"><?php echo $sale_id; ?></span></div>
    <div>Кассир: <span class="bold"><?php echo $sale['cashier_name']; ?></span></div>
    <div>Мижоз: <span class="bold"><?php echo $sale['customer_name'] ?: 'Оддий мижоз'; ?></span></div>
</div>

<div class="dashed-line"></div>

<div class="bold" style="margin-bottom: 10px;">
    <div class="item-row">
        <span>Маҳсулот</span>
        <span>Жами</span>
    </div>
</div>

<?php while($item = $items->fetch_assoc()): ?>
    <div class="item-row">
        <div class="item-name"><?php echo $item['name']; ?></div>
        <div class="item-price"><?php echo formatMoney($item['price'] * $item['quantity']); ?></div>
    </div>
    <div style="font-size: 11px; margin-bottom: 5px;">
        <?php echo $item['quantity']; ?> x <?php echo formatMoney($item['price']); ?>
    </div>
<?php endwhile; ?>

<div class="dashed-line"></div>

<div class="text-end" style="font-size: 16px;">
    <div>ЖАМИ: <span class="bold"><?php echo formatMoney($sale['total_amount']); ?></span></div>
</div>

<div style="margin-top: 10px; font-size: 12px;">
    Тўлов тури: <span class="bold"><?php 
        echo $sale['payment_type'] === 'cash' ? 'Нахт' : ($sale['payment_type'] === 'card' ? 'Карта' : 'Қарз'); 
    ?></span>
</div>

<div class="dashed-line"></div>

<div class="text-center" style="margin-top: 20px; font-size: 12px;">
    <?php echo nl2br(getSetting('receipt_footer_msg', "ҲАРИДИНГИЗ УЧУН РАҲМАТ!\nЯНА КУТИБ ҚОЛАМИЗ.")); ?>
</div>

<div class="no-print" style="margin-top: 30px; text-align: center;">
    <button onclick="window.print()" style="padding: 10px 20px;">ЧОП ЭТИШ</button>
</div>

<script>
    window.onload = function() {
        window.print();
        // Optional: window.close(); // Close window after printing
    };
</script>

</body>
</html>
