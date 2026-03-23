<?php
/**
 * header.php - Shared header with sidebar navigation
 * Infinity Builders Design System
 * 
 * Required variables before including:
 *   - $pageTitle (string) - Page title
 *   - $currentPage (string) - Active nav item (e.g. 'dashboard', 'projects')
 */

// Theme resolution: session override > localStorage > system preference
$themePref = $_SESSION['theme_override'] ?? null;
if ($themePref === 'system' || $themePref === null) {
    // Will be resolved client-side with prefers-color-scheme
    $initialTheme = 'system';
} else {
    $initialTheme = $themePref;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($initialTheme); ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($pageTitle ?? 'Infinity'); ?> - Infinity Builders</title>
  
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  
  <!-- Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- App CSS -->
  <link rel="stylesheet" href="css/style.css">
  
  <!-- Theme Initialization (must run before body renders) -->
  <script>
    (function() {
      const html = document.documentElement;
      const serverTheme = html.getAttribute('data-theme');
      
      // If server sent 'system', resolve it now
      if (serverTheme === 'system') {
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        html.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
      }
      
      // Sync to localStorage
      localStorage.setItem('infinity-theme', html.getAttribute('data-theme'));
      
      // Listen for system preference changes
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
        if (localStorage.getItem('infinity-theme') === 'system') {
          html.setAttribute('data-theme', e.matches ? 'dark' : 'light');
        }
      });
    })();
  </script>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
  <!-- Logo & Company -->
  <div class="sidebar-header">
    <div class="sidebar-logo">
      <img src="assets/infinity-logo.webp" alt="Infinity Builders" onerror="this.style.display='none'">
      <span class="sidebar-logo-text">Infinity</span>
    </div>
  </div>
  
  <!-- Navigation -->
  <nav class="sidebar-nav">
    <div class="nav-section">
      <div class="nav-section-title">Menu</div>
      
      <a class="nav-item<?php echo ($currentPage ?? '') === 'dashboard' ? ' active' : ''; ?>" href="dashboard.php">
        <i class="fa-solid fa-chart-pie"></i>
        Dashboard
      </a>
      
      <a class="nav-item<?php echo ($currentPage ?? '') === 'projects' ? ' active' : ''; ?>" href="projects.php">
        <i class="fa-solid fa-folder-open"></i>
        Projects
      </a>
      
      <a class="nav-item<?php echo ($currentPage ?? '') === 'calendar' ? ' active' : ''; ?>" href="calendar.php">
        <i class="fa-solid fa-calendar"></i>
        Calendar
      </a>
      
      <a class="nav-item<?php echo ($currentPage ?? '') === 'vendors' ? ' active' : ''; ?>" href="vendors.php">
        <i class="fa-solid fa-users-gear"></i>
        Vendors
      </a>
      
      <?php 
        $report_roles = ['admin', 'pm', 'accounting', 'estimator'];
        if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], $report_roles)): 
      ?>
      <a class="nav-item<?php echo ($currentPage ?? '') === 'reports' ? ' active' : ''; ?>" href="reports.php">
        <i class="fa-solid fa-chart-bar"></i>
        Reports
      </a>
      <?php endif; ?>
      
      <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
      <a class="nav-item<?php echo ($currentPage ?? '') === 'users' ? ' active' : ''; ?>" href="users.php">
        <i class="fa-solid fa-user-gear"></i>
        Users
      </a>
      <a class="nav-item<?php echo ($currentPage ?? '') === 'audit' ? ' active' : ''; ?>" href="audit.php">
        <i class="fa-solid fa-clipboard-list"></i>
        Activity Log
      </a>
      <?php endif; ?>
    </div>
    
    <div class="nav-section">
      <div class="nav-section-title">Settings</div>
      
      <a class="nav-item<?php echo ($currentPage ?? '') === 'settings' ? ' active' : ''; ?>" href="settings.php">
        <i class="fa-solid fa-gear"></i>
        Settings
      </a>
    </div>
  </nav>
  
  <!-- User at bottom -->
  <div class="sidebar-footer" style="padding: 16px; border-top: 1px solid var(--border-color);">
    <div class="user-info" style="display: flex; align-items: center; gap: 12px;">
      <div class="user-avatar" style="
        width: 36px; 
        height: 36px; 
        border-radius: 50%; 
        background: var(--gradient-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 14px;
      ">
        <?php echo strtoupper(substr($currentUser ?? 'U', 0, 1)); ?>
      </div>
      <div style="flex: 1; min-width: 0;">
        <div style="font-size: 14px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
          <?php echo htmlspecialchars($currentUser ?? 'User'); ?>
        </div>
        <div style="font-size: 12px; color: var(--text-muted);">
          <?php echo htmlspecialchars(ucfirst($_SESSION['user_role'] ?? 'user')); ?>
        </div>
      </div>
      <form method="post" action="logout.php" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['logout_token'] ?? ''); ?>">
        <button type="submit" style="background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:14px;" title="Logout">
          <i class="fa-solid fa-right-from-bracket"></i>
        </button>
      </form>
    </div>
  </div>
</aside>

<!-- Main Content -->
<main class="main-content">
  
  <!-- Main Header -->
  <div class="main-header-wrapper">
    <!-- Mobile Menu Button -->
    <button class="mobile-menu-btn" onclick="toggleSidebar()" aria-label="Toggle menu">
      <i class="fa-solid fa-bars"></i>
    </button>
    
    <!-- Sidebar Overlay (mobile) -->
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
  
    <!-- Main Header -->
    <div class="main-header">
      <div class="main-header-title">
        <h1 style="margin: 0 0 4px 0;"><?php echo htmlspecialchars($pageTitle ?? 'Dashboard'); ?></h1>
        <div class="breadcrumb">
          Infinity Builders &bull; <?php echo htmlspecialchars($pageTitle ?? 'Menu'); ?>
        </div>
      </div>
      
      <div class="main-header-actions">
        <!-- Notifications Bell -->
        <button class="notifications-trigger" onclick="toggleNotifications()" title="Notifications" id="notificationsBtn">
          <i class="fa-solid fa-bell"></i>
          <span class="notifications-badge" id="notificationsBadge" style="display: none;">0</span>
        </button>
        
        <!-- Global Search Button -->
        <button class="search-trigger" onclick="openGlobalSearch()" title="Search (Ctrl+K)">
          <i class="fa-solid fa-magnifying-glass"></i>
        </button>
        
        <!-- Theme Toggle -->
        <div class="theme-toggle" title="Toggle theme">
          <span class="theme-toggle-icon theme-toggle-dark">🌙</span>
          <span class="theme-toggle-icon theme-toggle-light">☀️</span>
          <span class="theme-toggle-knob"></span>
        </div>
        
        <!-- User Pill -->
        <div class="user-pill">
          <div class="user-pill-avatar">
            <?php echo strtoupper(substr($currentUser ?? 'U', 0, 1)); ?>
          </div>
          <span class="user-pill-name"><?php echo htmlspecialchars($currentUser ?? 'User'); ?></span>
        </div>
</div>
  </div>
</div>

<!-- Notifications Panel -->
<div class="notifications-panel" id="notificationsPanel">
  <div class="notifications-header">
    <h3>Notifications</h3>
    <button onclick="markAllRead()">Mark all read</button>
  </div>
  <div class="notifications-list" id="notificationsList">
    <div class="notifications-empty">No notifications</div>
  </div>
</div>

<!-- Global Search Modal -->
<div class="global-search-overlay" id="globalSearchOverlay" onclick="closeGlobalSearch(event)">
  <div class="global-search-modal" onclick="event.stopPropagation()">
    <div class="global-search-header">
      <input type="text" id="globalSearchInput" placeholder="Search projects, vendors, users..." autocomplete="off">
      <button class="global-search-close" onclick="closeGlobalSearch()">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <div class="global-search-results" id="globalSearchResults">
      <div class="global-search-hint">
        <p><i class="fa-solid fa-keyboard"></i> Type to search (Ctrl+K to open)</p>
      </div>
    </div>
  </div>
</div>
 
