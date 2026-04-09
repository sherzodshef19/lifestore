<?php
require_once '../config.php';

if (!isAdmin()) {
    redirect('../index.php');
}

function render_header($title = 'Admin Panel') {
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - LifeStore</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    
    <!-- PWA Support -->
    <link rel="manifest" href="../pwa/manifest.json">
    <meta name="theme-color" content="#1a1c23">
    <style>
        :root {
            --sidebar-width: 260px;
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --dark-bg: #1a1c23;
            --light-bg: #f8f9fc;
        }
        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--light-bg);
            overflow-x: hidden;
        }
        #wrapper {
            display: flex;
            width: 100%;
        }
        #sidebar-wrapper {
            min-height: 100vh;
            width: var(--sidebar-width);
            background-color: var(--dark-bg);
            color: #fff;
            transition: margin .25s ease-out;
            position: fixed;
            z-index: 1000;
        }
        
        @media (max-width: 768px) {
            #sidebar-wrapper {
                margin-left: calc(-1 * var(--sidebar-width));
            }
            #wrapper.toggled #sidebar-wrapper {
                margin-left: 0;
            }
            #page-content-wrapper {
                margin-left: 0 !important;
            }
        }
        
        /* Desktop Toggled (Hide sidebar) */
        @media (min-width: 769px) {
            #wrapper.toggled #sidebar-wrapper {
                margin-left: calc(-1 * var(--sidebar-width));
            }
            #wrapper.toggled #page-content-wrapper {
                margin-left: 0;
            }
        }

        #sidebar-wrapper {
            transition: all 0.3s ease;
        }
        #page-content-wrapper {
            transition: all 0.3s ease;
        }
        #sidebar-wrapper .sidebar-heading {
            padding: 1.5rem 1.25rem;
            font-size: 1.5rem;
            font-weight: 600;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        #sidebar-wrapper .list-group {
            width: var(--sidebar-width);
        }
        #sidebar-wrapper .list-group-item {
            background-color: transparent;
            color: rgba(255,255,255,0.8);
            border: none;
            padding: 1rem 1.5rem;
            transition: all 0.3s;
        }
        #sidebar-wrapper .list-group-item:hover {
            background-color: rgba(255,255,255,0.05);
            color: #fff;
            padding-left: 1.75rem;
        }
        #sidebar-wrapper .list-group-item.active {
            background-color: var(--primary-color);
            color: #fff;
            border-radius: 0 50px 50px 0;
            margin-right: 15px;
        }
        #sidebar-wrapper .list-group-item i {
            margin-right: 10px;
            font-size: 1.2rem;
        }
        #page-content-wrapper {
            width: 100%;
            margin-left: var(--sidebar-width);
            transition: margin .25s ease-out;
        }
        .navbar {
            background: #fff;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            padding: 0.75rem 2rem;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            transition: transform 0.3s;
        }
        .card:hover {
            transform: translateY(-3px);
        }
        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
        }
        .table thead th {
            background-color: #f8f9fc;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.05rem;
            font-weight: 700;
            color: #b7b9cc;
            border: none;
        }
        .low-stock {
            background-color: #fff5f5 !important;
            color: #dc3545 !important;
            font-weight: 600;
        }
        .negative-balance {
            color: #dc3545;
            font-weight: 600;
        }

        /* Preloader Styles */
        #preloader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #fff;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .loader-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 8px solid #f3f3f3;
            border-top: 8px solid var(--primary-color);
            animation: spin 1s linear infinite;
            position: relative;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .loader-percentage {
            position: absolute;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-bg);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        .loader-text {
            margin-top: 20px;
            font-weight: 500;
            letter-spacing: 1px;
            color: var(--dark-bg);
            text-transform: uppercase;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>

<!-- Preloader -->
<div id="preloader">
    <div style="position: relative;">
        <div class="loader-circle"></div>
        <div class="loader-percentage" id="load-pct">0%</div>
    </div>
    <div class="loader-text">LifeStore юкланмоқда...</div>
</div>

<div id="wrapper">
    <!-- Sidebar -->
    <div id="sidebar-wrapper">
        <div class="sidebar-heading">Life<span>Store</span></div>
        <div class="list-group list-group-flush">
            <a href="dashboard.php" class="list-group-item list-group-item-action <?php echo strpos($_SERVER['PHP_SELF'], 'dashboard.php') !== false ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2"></i> Дашборд
            </a>
            <a href="products.php" class="list-group-item list-group-item-action <?php echo strpos($_SERVER['PHP_SELF'], 'products.php') !== false ? 'active' : ''; ?>">
                <i class="bi bi-box-seam"></i> Товарлар
            </a>
            <a href="categories.php" class="list-group-item list-group-item-action <?php echo strpos($_SERVER['PHP_SELF'], 'categories.php') !== false ? 'active' : ''; ?>">
                <i class="bi bi-tags"></i> Категориялар
            </a>
            <a href="stock.php" class="list-group-item list-group-item-action <?php echo strpos($_SERVER['PHP_SELF'], 'stock.php') !== false ? 'active' : ''; ?>">
                <i class="bi bi-arrow-down-up"></i> Приход
            </a>
            <a href="customers.php" class="list-group-item list-group-item-action <?php echo strpos($_SERVER['PHP_SELF'], 'customers.php') !== false ? 'active' : ''; ?>">
                <i class="bi bi-people"></i> Мижозлар
            </a>
            <a href="reports.php" class="list-group-item list-group-item-action <?php echo strpos($_SERVER['PHP_SELF'], 'reports.php') !== false ? 'active' : ''; ?>">
                <i class="bi bi-file-earmark-bar-graph"></i> Ҳисоботлар
            </a>
            <a href="expenses.php" class="list-group-item list-group-item-action <?php echo strpos($_SERVER['PHP_SELF'], 'expenses.php') !== false ? 'active' : ''; ?>">
                <i class="bi bi-cash-stack"></i> Харажатлар
            </a>
            <a href="users.php" class="list-group-item list-group-item-action <?php echo strpos($_SERVER['PHP_SELF'], 'users.php') !== false ? 'active' : ''; ?>">
                <i class="bi bi-person-badge"></i> Ходимлар
            </a>
            <a href="statistics.php" class="list-group-item list-group-item-action <?php echo strpos($_SERVER['PHP_SELF'], 'statistics.php') !== false ? 'active' : ''; ?>">
                <i class="bi bi-graph-up-arrow"></i> Статистика
            </a>
            <a href="audit_log.php" class="list-group-item list-group-item-action <?php echo strpos($_SERVER['PHP_SELF'], 'audit_log.php') !== false ? 'active' : ''; ?>">
                <i class="bi bi-shield-check"></i> Кириш тарихи
            </a>
            <a href="settings.php" class="list-group-item list-group-item-action <?php echo strpos($_SERVER['PHP_SELF'], 'settings.php') !== false ? 'active' : ''; ?>">
                <i class="bi bi-gear"></i> Созламалар
            </a>
            <a href="../logout.php" class="list-group-item list-group-item-action text-danger mt-4 border-top pt-3">
                <div class="small mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;">ДАСТУР МУАЛЛИФИ:</div>
                <div class="fw-bold" style="font-size: 0.9rem;">Sherzodbek Islomov</div>
                <div class="mt-3"><i class="bi bi-box-arrow-right"></i> Чиқиш</div>
            </a>
        </div>
    </div>

    <div id="page-content-wrapper">
        <nav class="navbar navbar-expand-lg navbar-light">
            <div class="container-fluid">
                <button class="btn btn-outline-primary border-0 me-2" id="menu-toggle"><i class="bi bi-list fs-4"></i></button>
                <h4 class="m-0"><?php echo $title; ?></h4>
                <div class="ms-auto d-flex align-items-center">
                    <span class="me-3 text-dark fw-bold border-end pe-3" id="live-clock"><i class="bi bi-clock me-1"></i> --:--:--</span>
                    <span class="ms-3 text-muted"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['name']; ?> (Admin)</span>
                </div>
            </div>
        </nav>
        <div class="container-fluid p-4">
<?php
}

function render_footer() {
?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const menuToggle = document.getElementById("menu-toggle");
        const wrapper = document.getElementById("wrapper");
        
        if (menuToggle && wrapper) {
            menuToggle.onclick = function(e) {
                e.preventDefault();
                wrapper.classList.toggle("toggled");
            };
        }
    });

    // Preloader Logic - Start immediately
    (function() {
        let counter = 0;
        const pctElement = document.getElementById('load-pct');
        const preloader = document.getElementById('preloader');
        
        if (!pctElement || !preloader) return;

        const interval = setInterval(() => {
            counter += Math.floor(Math.random() * 15) + 5;
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
        }, 50);
    })();

    // Live Clock Logic
    function updateClock() {
        const now = new Date();
        const clockElement = document.getElementById('live-clock');
        if (clockElement) {
            clockElement.innerHTML = `<i class="bi bi-clock me-1 text-primary"></i> ` + now.toLocaleTimeString('uz-UZ', { hour12: false });
        }
    }
    setInterval(updateClock, 1000);
    updateClock();
</script>

<!-- PWA Registration -->
<script src="../pwa/init.js"></script>
</body>
</html>
<?php
}
?>
