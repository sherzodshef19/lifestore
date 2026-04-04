<?php

function printToIp($sale_id) {
    global $conn;
    
    $ip = getSetting('printer_ip');
    if (!$ip) return false;

    // Fetch sale details
    $sale = $conn->query("SELECT s.*, c.name as customer_name, u.name as cashier_name 
                          FROM sales s 
                          LEFT JOIN customers c ON s.customer_id = c.id 
                          LEFT JOIN users u ON s.user_id = u.id 
                          WHERE s.id = $sale_id")->fetch_assoc();

    if (!$sale) return false;

    $items = $conn->query("SELECT si.*, p.name 
                           FROM sale_items si 
                           JOIN products p ON si.product_id = p.id 
                           WHERE si.sale_id = $sale_id");

    // ESC/POS Commands
    $ESC = "\x1B";
    $GS  = "\x1D";
    $LF  = "\x0A";
    
    $data = "";
    
    // Initialize
    $data .= $ESC . "@";
    
    // Center Align Header
    $data .= $ESC . "a" . "\x01";
    $data .= $ESC . "!" . "\x30"; // Double height/width
    $data .= getSetting('receipt_store_name', 'LIFE STORE') . "\n";
    $data .= $ESC . "!" . "\x00"; // Reset size
    $data .= getSetting('receipt_header_msg', 'Inventarni boshqarish tizimi') . "\n";
    $data .= "Tel: " . getSetting('receipt_phone', '') . "\n";
    $data .= date("d.m.Y H:i", strtotime($sale['date'])) . "\n";
    $data .= "--------------------------------\n";
    
    // Left Align Content
    $data .= $ESC . "a" . "\x00";
    $data .= "Chek №: " . $sale_id . "\n";
    $data .= "Kassir: " . $sale['cashier_name'] . "\n";
    $data .= "Mijoz: " . ($sale['customer_name'] ?: 'Oddiy mijoz') . "\n";
    $data .= "--------------------------------\n";
    
    // Items
    while($item = $items->fetch_assoc()) {
        $name = $item['name'];
        $qty = $item['quantity'];
        $price = $item['price'];
        $subtotal = $qty * $price;
        
        $data .= $name . "\n";
        $data .= $qty . " x " . number_format($price, 0) . " = " . number_format($subtotal, 0) . " sum\n";
    }
    
    $data .= "--------------------------------\n";
    
    // Total
    $data .= $ESC . "a" . "\x02"; // Right align
    $data .= $ESC . "!" . "\x10"; // Double height
    $data .= "JAMI: " . number_format($sale['total_amount'], 0) . " sum\n";
    $data .= $ESC . "!" . "\x00"; 
    
    $data .= $ESC . "a" . "\x00"; // Left align
    $data .= "To'lov turi: " . ($sale['payment_type'] === 'cash' ? 'Naxt' : ($sale['payment_type'] === 'card' ? 'Karta' : 'Qarz')) . "\n";
    $data .= "--------------------------------\n";
    
    // Footer
    $data .= $ESC . "a" . "\x01"; // Center align
    $data .= getSetting('receipt_footer_msg', 'Haridingiz uchun rahmat!') . "\n";
    $data .= getSetting('receipt_address', '') . "\n";
    $data .= "Yana kutib qolamiz.\n\n\n\n\n";

    // Cut Paper
    $data .= $GS . "V" . "\x41" . "\x00";

    // Socket Connection
    try {
        $fp = @fsockopen($ip, 9100, $errno, $errstr, 5);
        if (!$fp) {
            error_log("Printer Error ($errno): $errstr");
            return false;
        }
        fwrite($fp, $data);
        fclose($fp);
        return true;
    } catch (Exception $e) {
        error_log("Printer Exception: " . $e->getMessage());
        return false;
    }
}

function testPrinterConnection($ip) {
    // ESC/POS Commands
    $ESC = "\x1B";
    $GS  = "\x1D";
    
    $data = "";
    $data .= $ESC . "@";
    $data .= $ESC . "a" . "\x01"; // Center align
    $data .= "--------------------------------\n";
    $data .= getSetting('receipt_store_name', 'LIFE STORE') . " - TEST\n";
    $data .= "--------------------------------\n";
    $data .= "Printer Connection: OK\n";
    $data .= "Date: " . date("d.m.Y H:i:s") . "\n";
    $data .= "--------------------------------\n\n\n\n\n";
    $data .= $GS . "V" . "\x41" . "\x00"; // Cut

    try {
        $fp = @fsockopen($ip, 9100, $errno, $errstr, 3);
        if (!$fp) return "Хато: $errstr ($errno)";
        fwrite($fp, $data);
        fclose($fp);
        return true;
    } catch (Exception $e) {
        return "Хатолик: " . $e->getMessage();
    }
}
?>
