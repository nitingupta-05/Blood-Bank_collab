<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <link href="<?php echo APP_URL; ?>/css/style.css" rel="stylesheet">
    <meta name="csrf-token" content="<?php echo generate_csrf_token(); ?>">
    <script>
        window.BASE_URL = '<?php echo APP_URL; ?>';
        window.apiUrl = function(route) {
            return window.BASE_URL + '/api.php?route=' + encodeURIComponent(route);
        };
        window.getCsrfToken = function() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        };
    </script>
</head>
<body data-base-url="<?php echo APP_URL; ?>">
<body>
    <!-- Top Navbar -->
    <nav class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-droplet"></i>
            <span><?php echo APP_NAME; ?></span>
        </div>
        
        <div class="navbar-menu">
            <div class="global-search-wrap">
                <input type="text" id="globalSearchInput" class="navbar-search" placeholder="Search donors, units, banks, emergencies...">
                <div id="globalSearchResults" class="global-search-results"></div>
            </div>
            
            <div class="navbar-item notification-trigger" id="notificationTrigger">
                <i class="fas fa-bell"></i>
                <span class="notification-badge" id="notificationBadge">0</span>
                <div class="notification-panel" id="notificationPanel"></div>
            </div>
            
            <div class="navbar-item" id="darkModeToggle" title="Toggle dark mode">
                <i class="fas fa-moon"></i>
            </div>
            
            <div class="navbar-item profile-trigger" id="profileTrigger" title="<?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?>">
                <i class="fas fa-user-circle"></i>
                <?php if (!empty($_SESSION['user_id'])): ?><span style="font-size:0.85rem;"><?php echo htmlspecialchars(ucfirst(str_replace('_',' ', $_SESSION['role'] ?? 'user'))); ?></span><?php endif; ?>
                <div class="profile-panel" id="profilePanel"></div>
            </div>
        </div>
    </nav>

    <!-- Sidebar Navigation -->
    <aside class="sidebar">
        <ul class="sidebar-menu">
            <li class="sidebar-item">
                <a href="?route=dashboard" class="sidebar-link active">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="?route=donors" class="sidebar-link">
                    <i class="fas fa-users"></i>
                    <span>Donors</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="?route=inventory" class="sidebar-link">
                    <i class="fas fa-vial"></i>
                    <span>Blood Inventory</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="?route=storage" class="sidebar-link">
                    <i class="fas fa-snowflake"></i>
                    <span>Storage</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="?route=bookings" class="sidebar-link">
                    <i class="fas fa-calendar-check"></i>
                    <span>Bookings</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="?route=emergency" class="sidebar-link">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>Emergency</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="?route=reports" class="sidebar-link">
                    <i class="fas fa-file-chart-line"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="?route=logout" class="sidebar-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <?php include $contentView; ?>
    </main>

    <!-- Toast Container -->
    <div id="toastContainer"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo APP_URL; ?>/js/app.js"></script>
</body>
</html>
