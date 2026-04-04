<?php
date_default_timezone_set('Asia/Tashkent');
// Session Security: Expires on browser close
ini_set('session.cookie_lifetime', 0);
session_start();

// Session Inactivity Timeout (30 minutes)
$timeout_duration = 1800; // 30 mins
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    if (basename($_SERVER['PHP_SELF']) !== 'index.php') {
        header("Location: " . (strpos($_SERVER['PHP_SELF'], 'admin/') !== false || strpos($_SERVER['PHP_SELF'], 'cashier/') !== false ? '../index.php' : 'index.php'));
        exit();
    }
}
$_SESSION['last_activity'] = time();

$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'lifestore';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
$conn->query("SET time_zone = '+05:00'");

// Initialize Security Tables
$conn->query("CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    attempts INT DEFAULT 1,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_blocked TINYINT(1) DEFAULT 0,
    INDEX (ip_address)
)");

$conn->query("CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    login_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id)
)");

// Global Settings Helper
function getSetting($key, $default = '') {
    global $conn;
    $key = $conn->real_escape_string($key);
    $result = $conn->query("SELECT setting_value FROM settings WHERE setting_key = '$key'");
    if ($result && $row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    return $default;
}

function setSetting($key, $value) {
    global $conn;
    $key = $conn->real_escape_string($key);
    $value = $conn->real_escape_string($value);
    $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('$key', '$value') 
                  ON DUPLICATE KEY UPDATE setting_value = '$value'");
}

// Authentication Helpers
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isCashier() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'cashier';
}

function redirect($url) {
    header("Location: $url");
    exit();
}

// Formatting Helpers
function formatMoney($amount) {
    return number_format($amount, 0, '.', ' ') . " сум";
}

function format_date($date) {
    return date("d.m.Y H:i", strtotime($date));
}

// Security Helpers
function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
    return $_SERVER['REMOTE_ADDR'];
}

function is_ip_blocked() {
    global $conn;
    $ip = get_client_ip();
    $threshold = 5;
    $lockout_minutes = 15;
    
    $res = $conn->query("SELECT * FROM login_attempts WHERE ip_address = '$ip'");
    if ($row = $res->fetch_assoc()) {
        if ($row['attempts'] >= $threshold) {
            $last = strtotime($row['last_attempt']);
            $diff = (time() - $last) / 60;
            if ($diff < $lockout_minutes) {
                return round($lockout_minutes - $diff); // Return remaining minutes
            } else {
                // Lockout expired, reset for next attempt
                $conn->query("UPDATE login_attempts SET attempts = 0, is_blocked = 0 WHERE ip_address = '$ip'");
            }
        }
    }
    return false;
}

function log_login_attempt($success) {
    global $conn;
    $ip = get_client_ip();
    
    if ($success) {
        $conn->query("DELETE FROM login_attempts WHERE ip_address = '$ip'");
    } else {
        $res = $conn->query("SELECT * FROM login_attempts WHERE ip_address = '$ip'");
        if ($row = $res->fetch_assoc()) {
            $conn->query("UPDATE login_attempts SET attempts = attempts + 1 WHERE ip_address = '$ip'");
        } else {
            $conn->query("INSERT INTO login_attempts (ip_address, attempts) VALUES ('$ip', 1)");
        }
    }
}

function log_audit_login($user_id) {
    global $conn;
    $ip = get_client_ip();
    $ua = $conn->real_escape_string($_SERVER['HTTP_USER_AGENT']);
    $conn->query("INSERT INTO audit_log (user_id, ip_address, user_agent) VALUES ($user_id, '$ip', '$ua')");
}

// Telegram Helper
function sendTelegram($message) {
    $token = getSetting('telegram_token');
    $chat_id = getSetting('telegram_userid');
    if (!$token || !$chat_id) return;

    $url = "https://api.telegram.org/bot$token/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ],
    ];
    $context  = stream_context_create($options);
    @file_get_contents($url, false, $context);
}

function checkStockAndNotify($product_id) {
    global $conn;
    $res = $conn->query("SELECT * FROM products WHERE id = $product_id");
    if ($p = $res->fetch_assoc()) {
        if ($p['quantity'] < 5) {
            $unit = $p['unit'] ?? 'шт';
            $msg = "⚠️ <b>Омборда маҳсулот оз қолди!</b>\n\n🔹 Номи: <b>{$p['name']}</b>\n🔹 Қолдиқ: <b>" . (float)$p['quantity'] . " $unit</b>\n🔹 Штрих-код: <code>{$p['barcode']}</code>";
            sendTelegram($msg);
        }
    }
}

function notifySale($sale_id) {
    global $conn;
    $sale = $conn->query("SELECT s.*, c.name as customer_name, u.name as user_name FROM sales s 
                          LEFT JOIN customers c ON s.customer_id = c.id 
                          LEFT JOIN users u ON s.user_id = u.id 
                          WHERE s.id = $sale_id")->fetch_assoc();
    
    $items = $conn->query("SELECT si.*, p.name, p.unit FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id = $sale_id");
    
    $msg = "💰 <b>ЯНГИ СОТУВ! (№$sale_id)</b>\n";
    $msg .= "⏰ Вақт: " . date('d.m.Y H:i', strtotime($sale['date'])) . "\n";
    $msg .= "👤 Кассир: <b>{$sale['user_name']}</b>\n";
    $msg .= "👥 Мижоз: <b>" . ($sale['customer_name'] ?: 'Оддий мижоз') . "</b>\n\n";
    
    $msg .= "📦 <b>Маҳсулотлар:</b>\n";
    while ($item = $items->fetch_assoc()) {
        $msg .= "— {$item['name']}: " . (float)$item['quantity'] . " {$item['unit']} x " . number_format($item['price'], 0, '.', ' ') . "\n";
    }
    
    $msg .= "\n💵 <b>Жами: " . number_format($sale['total_amount'], 0, '.', ' ') . " сум</b>\n";
    $msg .= "💳 Нахт: " . number_format($sale['cash_amount'], 0, '.', ' ') . " | Карта: " . number_format($sale['card_amount'], 0, '.', ' ') . "\n";
    if ($sale['debt_amount'] > 0) {
        $msg .= "🔻 Қарз: <b>" . number_format($sale['debt_amount'], 0, '.', ' ') . " сум</b>\n";
    }
    
    sendTelegram($msg);
}

function sendTelegramFile($filePath, $caption = '') {
    $token = getSetting('telegram_token');
    $chat_id = getSetting('telegram_userid');
    if (!$token || !$chat_id || !file_exists($filePath)) return false;

    $url = "https://api.telegram.org/bot$token/sendDocument";
    $post_fields = [
        'chat_id'   => $chat_id,
        'caption'   => $caption,
        'document'  => new CURLFile(realpath($filePath))
    ];

    $ch = curl_init(); 
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type:multipart/form-data"));
    curl_setopt($ch, CURLOPT_URL, $url); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields); 
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}

function backupDatabaseToTelegram($manual = false) {
    global $host, $user, $pass, $db;
    
    // Check if backup already done today (for auto-backup)
    if (!$manual) {
        $last_backup = getSetting('last_backup_date');
        if ($last_backup == date('Y-m-d')) return false;
    }

    $backupDir = __DIR__ . '/backups';
    if (!is_dir($backupDir)) mkdir($backupDir, 0777, true);
    
    $fileName = 'backup_' . $db . '_' . date('Y-m-d_H-i-s') . '.sql';
    $filePath = $backupDir . '/' . $fileName;

    // Build mysqldump command
    $command = "mysqldump --user=$user " . ($pass ? "--password=$pass " : "") . "--host=$host $db > \"$filePath\" 2>&1";
    exec($command, $output, $return_var);

    if ($return_var !== 0 || !file_exists($filePath) || filesize($filePath) == 0) {
        // Fallback for environment where mysqldump is not in PATH
        // We'll use a simple manual SQL export logic if needed, but usually exec works in OSPanel
        if ($manual) return "Error: mysqldump failed. Check if it's in your PATH.";
        return false;
    }

    $caption = "💾 <b>DATABASE BACKUP</b>\n📅 Date: " . date('d.m.Y H:i:s') . "\n📦 DB: <code>$db</code>";
    $sent = sendTelegramFile($filePath, $caption);
    
    // DELETE FILE AFTER SENDING
    if ($sent) {
        unlink($filePath);
        setSetting('last_backup_date', date('Y-m-d'));
        return true;
    }
    
    return false;
}
?>
