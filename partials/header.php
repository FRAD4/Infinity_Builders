<?php
/**
 * header.php - Shared header with sidebar navigation
 * Infinity Builders Design System
 * 
 * Required variables before including:
 *   - $pageTitle (string) - Page title
 *   - $currentPage (string) - Active nav item (e.g. 'dashboard', 'projects')
 */

// Theme resolution order:
// 1. Server session override (from settings preference)
// 2. 'system' placeholder (resolved client-side via localStorage/prefers-color-scheme)
$themePref = $_SESSION['theme_override'] ?? null;
$initialTheme = ($themePref && $themePref !== 'system') ? $themePref : 'system';
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
      
      // Priority: localStorage saved choice > server preference > system default
      const savedTheme = localStorage.getItem('infinity-theme');
      
      if (savedTheme && savedTheme !== 'system') {
        // User has explicit choice saved - use it
        html.setAttribute('data-theme', savedTheme);
      } else if (serverTheme === 'system') {
        // No saved choice and server says 'system' - detect from OS
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        html.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
      }
      // If server sent explicit dark/light, keep it
      
      // Listen for system preference changes (only if no explicit choice)
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
        if (!localStorage.getItem('infinity-theme') || localStorage.getItem('infinity-theme') === 'system') {
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
      <img src="assets/infinity-logo.png" alt="Infinity Builders" onerror="this.style.display='none'">
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
      
      <a class="nav-item<?php echo ($currentPage ?? '') === 'permits' ? ' active' : ''; ?>" href="permits.php">
        <i class="fa-solid fa-file-contract"></i>
        Permits
      </a>
      
      <a class="nav-item<?php echo ($currentPage ?? '') === 'inspections' ? ' active' : ''; ?>" href="inspections.php">
        <i class="fa-solid fa-clipboard-check"></i>
        Inspections
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
      
      </div>
    
    <div class="nav-section">
      <div class="nav-section-title">Settings</div>
      
      <a class="nav-item<?php echo ($currentPage ?? '') === 'settings' ? ' active' : ''; ?>" href="settings.php">
        <i class="fa-solid fa-gear"></i>
        Settings
      </a>
      
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
      <div class="nav-section-title">Training</div>
      
      <a class="nav-item<?php echo ($currentPage ?? '') === 'training' ? ' active' : ''; ?>" href="training.php">
        <i class="fa-solid fa-graduation-cap"></i>
        Training
      </a>
    </div>
  </nav>
  
  <!-- User at bottom -->
  <div class="sidebar-footer">
    <div class="user-info">
      <div class="user-avatar">
        <?php echo strtoupper(substr($currentUser ?? 'U', 0, 1)); ?>
      </div>
      <div style="flex: 1; min-width: 0;">
        <div class="sidebar-user-name">
          <?php echo htmlspecialchars($currentUser ?? 'User'); ?>
        </div>
        <div class="sidebar-user-role">
          <?php echo htmlspecialchars(ucfirst($_SESSION['user_role'] ?? 'user')); ?>
        </div>
      </div>
      <form method="post" action="logout.php" class="sidebar-logout-form">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['logout_token'] ?? ''); ?>">
        <button type="submit" class="sidebar-logout-btn" title="Logout">
          <i class="fa-solid fa-right-from-bracket"></i>
        </button>
      </form>
    </div>
  </div>
</aside>

<!-- Sidebar Overlay (for mobile) -->
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<!-- Main Content -->
<main class="main-content">
  
  <!-- Main Header -->
  <div class="main-header-wrapper">
    <!-- Mobile Menu Button -->
    <button class="mobile-menu-btn" onclick="toggleSidebar()" aria-label="Toggle menu">
      <i class="fa-solid fa-bars"></i>
    </button>
    
    <!-- Main Header Content -->
    <div class="main-header">
      <div class="main-header-title">
        <h1><?php echo htmlspecialchars($pageTitle ?? 'Dashboard'); ?></h1>
      </div>
      
      <div class="main-header-actions">
        <!-- Shortcuts Dropdown (Desktop) -->
        <button class="shortcuts-trigger" onclick="toggleShortcuts()" title="Add new" id="shortcutsBtn">
          <i class="fa-solid fa-plus"></i>
        </button>
        <div class="shortcuts-dropdown" id="shortcutsDropdown">
          <div class="shortcuts-dropdown-items">
            <div class="shortcuts-dropdown-item" onclick="navigateAndCreate('projects.php')">
              <i class="fa-solid fa-folder-open"></i>
              Add Project
            </div>
            <div class="shortcuts-dropdown-item" onclick="navigateAndCreate('vendors.php')">
              <i class="fa-solid fa-user"></i>
              Add Vendor
            </div>
            <div class="shortcuts-dropdown-item" onclick="navigateAndCreate('permits.php')">
              <i class="fa-solid fa-file-contract"></i>
              Add Permit
            </div>
            <div class="shortcuts-dropdown-item" onclick="navigateAndCreate('inspections.php')">
              <i class="fa-solid fa-clipboard-check"></i>
              Add Inspection
            </div>
            <div class="shortcuts-dropdown-item" onclick="openNoteModal()">
              <i class="fa-solid fa-note-sticky"></i>
              Send Note
            </div>
          </div>
        </div>
        
        <!-- Notifications Bell -->
        <button class="notifications-trigger" onclick="toggleNotifications()" title="Notifications" id="notificationsBtn">
          <i class="fa-solid fa-bell"></i>
          <span class="notifications-badge" id="notificationsBadge" style="display: none;">0</span>
        </button>
        <!-- Search Trigger -->
        <button class="search-trigger" onclick="openGlobalSearch()" title="Search (Ctrl+K)">
          <i class="fa-solid fa-magnifying-glass"></i>
        </button>
        
        <!-- Theme Toggle -->
        <div class="theme-toggle" title="Toggle theme">
          <span class="theme-toggle-knob"></span>
        </div>
        
        <!-- User Avatar -->
        <div class="user-pill-avatar">
          <?php echo strtoupper(substr($currentUser ?? 'U', 0, 1)); ?>
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

<!-- Mobile Shortcuts FAB -->
<button class="mobile-shortcuts-fab" onclick="toggleMobileShortcuts()" title="Menu">
  <i class="fa-solid fa-ellipsis-vertical"></i>
</button>

<!-- Mobile Shortcuts Panel -->
<div class="mobile-shortcuts-panel" id="mobileShortcutsPanel">
<div class="mobile-shortcuts-items">
      <div class="shortcuts-dropdown-item" onclick="openGlobalSearch()">
        <i class="fa-solid fa-magnifying-glass"></i>
        Search
      </div>
      <div class="shortcuts-dropdown-item" onclick="navigateAndCreate('projects.php')">
        <i class="fa-solid fa-folder-open"></i>
        Add Project
      </div>
      <div class="shortcuts-dropdown-item" onclick="navigateAndCreate('vendors.php')">
        <i class="fa-solid fa-user"></i>
        Add Vendor
      </div>
      <div class="shortcuts-dropdown-item" onclick="navigateAndCreate('permits.php')">
        <i class="fa-solid fa-file-contract"></i>
        Add Permit
      </div>
      <div class="shortcuts-dropdown-item" onclick="navigateAndCreate('inspections.php')">
        <i class="fa-solid fa-clipboard-check"></i>
        Add Inspection
      </div>
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
 
