<?php
require_once '../config.php';

if (!isAdmin()) {
    redirect('../index.php');
}

if (!isset($_GET['id'])) {
    die("ID NOT FOUND");
}

$id = (int)$_GET['id'];
$product = $conn->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = $id")->fetch_assoc();

if (!$product) {
    die("PRODUCT NOT FOUND");
}

if (!$product['barcode']) {
    die("BARCODE NOT SET FOR THIS PRODUCT");
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Shtrix-kod: <?php echo $product['name']; ?></title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        body {
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            font-family: Arial, sans-serif;
            text-align: center;
        }
        .container {
            /* Optimized for 40x30mm or 50x30 labels */
            width: 50mm;
            height: 30mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2mm;
            box-sizing: border-box;
            background-color: white;
        }
        .product-name {
            font-size: 10px;
            font-weight: bold;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
        }
        .price {
            font-size: 12px;
            font-weight: bold;
            margin-top: 2px;
        }
        #barcode {
            width: 100%;
            height: auto;
            max-height: 15mm;
        }
        @media print {
            body { height: auto; }
            .no-print { display: none; }
            @page {
                size: 50mm 30mm;
                margin: 0;
            }
        }
    </style>
</head>
<body>

<div class="container" id="printable">
    <div class="product-name"><?php echo $product['name']; ?></div>
    <svg id="barcode"></svg>
    <div class="price"><?php echo number_format($product['sale_price'], 0, '.', ' '); ?> sum</div>
</div>

<div class="no-print" style="margin-top: 20px;">
    <button onclick="window.print()" style="padding: 10px 20px; background: #4e73df; color: white; border: none; border-radius: 5px; cursor: pointer;">ЧОП ЭТИШ</button>
    <button onclick="window.close()" style="padding: 10px 20px; background: #858796; color: white; border: none; border-radius: 5px; cursor: pointer;">ЁПИШ</button>
</div>

<script>
    JsBarcode("#barcode", "<?php echo $product['barcode']; ?>", {
        format: "CODE128",
        lineColor: "#000",
        width: 2,
        height: 50,
        displayValue: true,
        fontSize: 14,
        margin: 0
    });

    // Auto-print after loading
    window.onload = function() {
        setTimeout(() => {
            window.print();
        }, 500);
    };
</script>

</body>
</html>
