<?php
require_once '../config.php';

if (!isCashier() && !isAdmin()) {
    redirect('../index.php');
}

function render_header($title = 'Kassir Paneli') {
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
    <style>
        :root {
            --sidebar-width: 240px;
            --primary-color: #00d2ff;
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
            position: fixed;
            z-index: 1000;
            transition: margin .25s ease-out;
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
            color: #fff;
            border-bottom: 1px solid rgba(255,255,255,0.1);
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
        }
        #sidebar-wrapper .list-group-item.active {
            background-color: var(--primary-color);
            color: #fff;
        }
        #page-content-wrapper {
            width: 100%;
            margin-left: var(--sidebar-width);
        }
        .navbar {
            background: #fff;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            padding: 0.75rem 2rem;
        }
        .pos-card {
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
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
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 6px solid #f3f3f3;
            border-top: 6px solid var(--primary-color);
            animation: spin 1s linear infinite;
            position: relative;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .loader-percentage {
            position: absolute;
            font-size: 1.2rem;
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
            font-size: 0.7rem;
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
                <i class="bi bi-cart-plus"></i> Сотув (POS)
            </a>
            <a href="products.php" class="list-group-item list-group-item-action <?php echo strpos($_SERVER['PHP_SELF'], 'products.php') !== false ? 'active' : ''; ?>">
                <i class="bi bi-box-seam"></i> Товарлар рўйхати
            </a>
            <a href="customers.php" class="list-group-item list-group-item-action <?php echo strpos($_SERVER['PHP_SELF'], 'customers.php') !== false ? 'active' : ''; ?>">
                <i class="bi bi-people"></i> Мижозлар
            </a>
            <a href="reports.php" class="list-group-item list-group-item-action <?php echo strpos($_SERVER['PHP_SELF'], 'reports.php') !== false ? 'active' : ''; ?>">
                <i class="bi bi-file-earmark-bar-graph"></i> Менинг ҳисоботларим
            </a>
            <a href="expenses.php" class="list-group-item list-group-item-action <?php echo strpos($_SERVER['PHP_SELF'], 'expenses.php') !== false ? 'active' : ''; ?>">
                <i class="bi bi-cash-stack"></i> Кунлик харажат
            </a>
            <a href="../logout.php" class="list-group-item list-group-item-action text-danger mt-5">
                <i class="bi bi-box-arrow-right"></i> Чиқиш
            </a>
        </div>
    </div>

    <div id="page-content-wrapper">
        <nav class="navbar navbar-expand-lg navbar-light">
            <div class="container-fluid">
                <button class="btn btn-outline-info border-0 me-2" id="menu-toggle"><i class="bi bi-list fs-4"></i></button>
                <h4 class="m-0"><?php echo $title; ?></h4>
                <div class="ms-auto d-flex align-items-center">
                    <span class="me-3 text-dark fw-bold border-end pe-3" id="live-clock"><i class="bi bi-clock me-1"></i> --:--:--</span>
                    <span class="ms-3 text-muted"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['name']; ?></span>
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
            counter += Math.floor(Math.random() * 20) + 10;
            if (counter >= 100) {
                counter = 100;
                clearInterval(interval);
                setTimeout(() => {
                    if (preloader) {
                        preloader.style.opacity = '0';
                        setTimeout(() => {
                            preloader.style.display = 'none';
                        }, 400);
                    }
                }, 100);
            }
            if (pctElement) pctElement.innerText = counter + '%';
        }, 40);
    })();

    // Live Clock Logic
    function updateClock() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('uz-UZ', { hour12: false });
        const clockElement = document.getElementById('live-clock');
        if (clockElement) {
            clockElement.innerHTML = `<i class="bi bi-clock me-1 text-primary"></i> ${timeString}`;
        }
    }
    setInterval(updateClock, 1000);
    updateClock();
</script>
</body>
</html>
<?php
}
?>
