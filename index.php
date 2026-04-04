<?php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    if (isAdmin()) redirect('admin/dashboard.php');
    if (isCashier()) redirect('cashier/dashboard.php');
}

$error = '';
$blocked_minutes = is_ip_blocked();

if ($blocked_minutes) {
    $error = "Жуда кўп хато уринишлар. Илтимос, $blocked_minutes дақиқа кутинг.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$blocked_minutes) {
    $login = $conn->real_escape_string($_POST['login']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE login = ?");
    $stmt->bind_param("s", $login);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            log_login_attempt(true); // Reset attempts
            log_audit_login($user['id']); // Record login history
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];
            
            if ($user['role'] === 'admin') redirect('admin/dashboard.php');
            else redirect('cashier/dashboard.php');
        } else {
            log_login_attempt(false);
            $error = 'Хато пароль!';
        }
    } else {
        log_login_attempt(false);
        $error = 'Фойдаланувчи топилмади!';
    }
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LifeStore - Кириш</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            color: #fff;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 24px;
            padding: 45px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 25px 45px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        .logo {
            font-size: 2.5rem;
            font-weight: 600;
            text-align: center;
            margin-bottom: 35px;
            letter-spacing: 2px;
            color: #fff;
        }
        .logo span {
            color: #00d2ff;
        }
        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.9);
        }
        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #fff;
            border-radius: 12px;
            padding: 14px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.2);
            border-color: #00d2ff;
            color: #fff;
            box-shadow: 0 0 0 4px rgba(0, 210, 255, 0.2);
        }
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        .btn-primary {
            background: linear-gradient(90deg, #00d2ff 0%, #3a7bd5 100%);
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-weight: 600;
            width: 100%;
            margin-top: 25px;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 210, 255, 0.4);
        }
        .alert-danger {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: #ff8e8e;
            border-radius: 12px;
            padding: 12px;
            font-size: 0.9rem;
        }

        /* Preloader Styles */
        #preloader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #1e3c72; /* Background to match login page */
            z-index: 9999;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            transition: opacity 0.5s ease;
        }
        .loader-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 6px solid rgba(255, 255, 255, 0.1);
            border-top: 6px solid #00d2ff;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .loader-percentage {
            position: absolute;
            font-size: 1.2rem;
            font-weight: 600;
            color: #fff;
        }
        .loader-text {
            margin-top: 20px;
            font-weight: 500;
            color: #fff;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 2px;
        }
    </style>
</head>
<body>

<!-- Preloader -->
<div id="preloader">
    <div style="position: relative; display: flex; justify-content: center; align-items: center;">
        <div class="loader-circle"></div>
        <div class="loader-percentage" id="load-pct">0%</div>
    </div>
    <div class="loader-text">LifeStore юкланмоқда...</div>
</div>

<div class="login-card">
    <div class="logo">Life<span>Store</span></div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger text-center mb-4"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (!$blocked_minutes): ?>
    <form method="POST">
        <div class="mb-4">
            <label class="form-label">Логин</label>
            <input type="text" name="login" class="form-control" placeholder="Логинни киритинг" required autofocus>
        </div>

        <div class="mb-3">
            <label class="form-label">Пароль</label>
            <input type="password" name="password" class="form-control" placeholder="****" required>
        </div>

        <button type="submit" class="btn btn-primary">ТИЗИМГА КИРИШ</button>
    </form>
    <?php else: ?>
        <div class="text-center mt-4">
            <i class="bi bi-shield-lock text-warning" style="font-size: 3rem;"></i>
            <p class="mt-3">Ҳимоя тизими ишга тушди.</p>
        </div>
    <?php endif; ?>
</div>

</body>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        let counter = 0;
        const pctElement = document.getElementById('load-pct');
        const preloader = document.getElementById('preloader');
        
        const interval = setInterval(() => {
            counter += Math.floor(Math.random() * 25) + 10;
            if (counter >= 100) {
                counter = 100;
                clearInterval(interval);
                setTimeout(() => {
                    preloader.style.opacity = '0';
                    setTimeout(() => {
                        preloader.style.display = 'none';
                    }, 500);
                }, 200);
            }
            pctElement.innerText = counter + '%';
        }, 60);
    });
</script>
</html>
