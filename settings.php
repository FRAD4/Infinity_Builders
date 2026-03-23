<?php
/**
 * Settings Page
 * User preferences and profile settings
 */

require_once 'partials/init.php';

$pageTitle = 'Settings';
$currentPage = 'settings';
$userId = $_SESSION['user_id'] ?? 0;

// Load preferences helper
require_once __DIR__ . '/includes/preferences.php';

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_preferences' && $userId > 0) {
        $prefs = [
            'dashboard_layout' => $_POST['dashboard_layout'] ?? 'default',
            'dashboard_theme' => $_POST['dashboard_theme'] ?? 'system',
            'show_charts' => isset($_POST['show_charts']) ? '1' : '0',
            'show_recent_projects' => isset($_POST['show_recent_projects']) ? '1' : '0',
            'show_recent_vendors' => isset($_POST['show_recent_vendors']) ? '1' : '0',
            'show_recent_payments' => isset($_POST['show_recent_payments']) ? '1' : '0',
            'notifications_enabled' => isset($_POST['notifications_enabled']) ? '1' : '0',
            'email_alerts' => isset($_POST['email_alerts']) ? '1' : '0',
            'default_page' => $_POST['default_page'] ?? 'dashboard'
        ];
        
        if (set_user_preferences($userId, $prefs)) {
            $message = 'Preferences saved successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error saving preferences.';
            $messageType = 'error';
        }
    }
    
    if ($_POST['action'] === 'update_profile' && $userId > 0) {
        $newUsername = trim($_POST['username'] ?? '');
        $newEmail = trim($_POST['email'] ?? '');
        
        if ($newUsername && $newEmail) {
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
            if ($stmt->execute([$newUsername, $newEmail, $userId])) {
                $_SESSION['user_name'] = $newUsername;
                $message = 'Profile updated successfully!';
                $messageType = 'success';
            } else {
                $message = 'Error updating profile.';
                $messageType = 'error';
            }
        }
    }
    
    if ($_POST['action'] === 'change_password' && $userId > 0) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $message = 'All password fields are required.';
            $messageType = 'error';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'New passwords do not match.';
            $messageType = 'error';
        } elseif (strlen($newPassword) < 6) {
            $message = 'Password must be at least 6 characters.';
            $messageType = 'error';
        } else {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password, password_algo, password_hash FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $valid = false;
                if ($user['password_algo'] === 'bcrypt' && $user['password_hash']) {
                    $valid = password_verify($currentPassword, $user['password_hash']);
                } else {
                    $valid = hash('sha256', $currentPassword) === $user['password'];
                }
                
                if ($valid) {
                    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET password = '', password_algo = 'bcrypt', password_hash = ? WHERE id = ?");
                    $stmt->execute([$newHash, $userId]);
                    $message = 'Password changed successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Current password is incorrect.';
                    $messageType = 'error';
                }
            }
        }
    }
}

// Load current preferences
$preferences = get_dashboard_preferences($userId);

// Load current user data
$stmt = $pdo->prepare("SELECT username, email, role, created_at FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentUserData = $stmt->fetch(PDO::FETCH_ASSOC);

require_once 'partials/header.php';
?>

<div class="page-header">
  <h1>Settings</h1>
  <p class="text-secondary">Manage your account and dashboard preferences</p>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $messageType; ?>" style="margin-bottom: 24px;">
  <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<div class="grid grid-2" style="gap: 24px;">
  <!-- Profile Settings -->
  <div class="card">
    <div class="card-header">
      <h3>Profile Settings</h3>
    </div>
    <form method="POST" style="padding: 20px;">
      <input type="hidden" name="action" value="update_profile">
      
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" class="form-control" 
               value="<?php echo htmlspecialchars($currentUserData['username'] ?? ''); ?>" required>
      </div>
      
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" class="form-control" 
               value="<?php echo htmlspecialchars($currentUserData['email'] ?? ''); ?>" required>
      </div>
      
      <div class="form-group">
        <label>Role</label>
        <input type="text" class="form-control" value="<?php echo htmlspecialchars(ucfirst($currentUserData['role'] ?? 'User')); ?>" readonly>
      </div>
      
      <button type="submit" class="btn btn-primary">Save Profile</button>
    </form>
  </div>

  <!-- Change Password -->
  <div class="card">
    <div class="card-header">
      <h3>Change Password</h3>
    </div>
    <form method="POST" style="padding: 20px;">
      <input type="hidden" name="action" value="change_password">
      
      <div class="form-group">
        <label for="current_password">Current Password</label>
        <input type="password" id="current_password" name="current_password" class="form-control" required>
      </div>
      
      <div class="form-group">
        <label for="new_password">New Password</label>
        <input type="password" id="new_password" name="new_password" class="form-control" required minlength="6">
      </div>
      
      <div class="form-group">
        <label for="confirm_password">Confirm New Password</label>
        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="6">
      </div>
      
      <button type="submit" class="btn btn-primary">Change Password</button>
    </form>
  </div>
</div>

<!-- Dashboard Preferences -->
<div class="card" style="margin-top: 24px;">
  <div class="card-header">
    <h3>Dashboard Preferences</h3>
  </div>
  <form method="POST" style="padding: 20px;">
    <input type="hidden" name="action" value="update_preferences">
    
    <div class="grid grid-2" style="gap: 20px;">
      <!-- Layout & Theme -->
      <div>
        <h4 style="margin-bottom: 16px; color: var(--text-secondary);">Layout & Display</h4>
        
        <div class="form-group">
          <label for="dashboard_layout">Dashboard Layout</label>
          <select id="dashboard_layout" name="dashboard_layout" class="form-control">
            <option value="default" <?php echo ($preferences['dashboard_layout'] ?? 'default') === 'default' ? 'selected' : ''; ?>>Default</option>
            <option value="compact" disabled>Compact (Coming Soon)</option>
            <option value="expanded" disabled>Expanded (Coming Soon)</option>
          </select>
          <small class="text-secondary" style="font-size: 12px;">Additional layouts coming in future updates</small>
        </div>
        
        <div class="form-group">
          <label for="dashboard_theme">Theme</label>
          <select id="dashboard_theme" name="dashboard_theme" class="form-control">
            <option value="system" <?php echo ($preferences['dashboard_theme'] ?? 'system') === 'system' ? 'selected' : ''; ?>>System Default</option>
            <option value="dark" <?php echo ($preferences['dashboard_theme'] ?? '') === 'dark' ? 'selected' : ''; ?>>Dark</option>
            <option value="light" <?php echo ($preferences['dashboard_theme'] ?? '') === 'light' ? 'selected' : ''; ?>>Light</option>
          </select>
        </div>
        
        <div class="form-group">
          <label for="default_page">Default Page</label>
          <select id="default_page" name="default_page" class="form-control">
            <option value="dashboard" <?php echo ($preferences['default_page'] ?? 'dashboard') === 'dashboard' ? 'selected' : ''; ?>>Dashboard</option>
            <option value="projects" <?php echo ($preferences['default_page'] ?? '') === 'projects' ? 'selected' : ''; ?>>Projects</option>
            <option value="vendors" <?php echo ($preferences['default_page'] ?? '') === 'vendors' ? 'selected' : ''; ?>>Vendors</option>
            <option value="reports" <?php echo ($preferences['default_page'] ?? '') === 'reports' ? 'selected' : ''; ?>>Reports</option>
          </select>
        </div>
      </div>
      
      <!-- Dashboard Widgets -->
      <div>
        <h4 style="margin-bottom: 16px; color: var(--text-secondary);">Dashboard Widgets</h4>
        
        <div class="form-group">
          <label class="checkbox-label">
            <input type="checkbox" name="show_charts" value="1" <?php echo ($preferences['show_charts'] ?? '1') === '1' ? 'checked' : ''; ?>>
            <span>Show Charts</span>
          </label>
        </div>
        
        <div class="form-group">
          <label class="checkbox-label">
            <input type="checkbox" name="show_recent_projects" value="1" <?php echo ($preferences['show_recent_projects'] ?? '1') === '1' ? 'checked' : ''; ?>>
            <span>Show Recent Projects</span>
          </label>
        </div>
        
        <div class="form-group">
          <label class="checkbox-label">
            <input type="checkbox" name="show_recent_vendors" value="1" <?php echo ($preferences['show_recent_vendors'] ?? '1') === '1' ? 'checked' : ''; ?>>
            <span>Show Recent Vendors</span>
          </label>
        </div>
        
        <div class="form-group">
          <label class="checkbox-label">
            <input type="checkbox" name="show_recent_payments" value="1" <?php echo ($preferences['show_recent_payments'] ?? '1') === '1' ? 'checked' : ''; ?>>
            <span>Show Recent Payments</span>
          </label>
        </div>
      </div>
    </div>
    
    <hr style="margin: 24px 0; border-color: var(--border-color);">
    
    <!-- Notifications -->
    <div>
      <h4 style="margin-bottom: 16px; color: var(--text-secondary);">Notifications</h4>
      
      <div class="grid grid-2" style="gap: 16px;">
        <div class="form-group">
          <label class="checkbox-label">
            <input type="checkbox" name="notifications_enabled" value="1" <?php echo ($preferences['notifications_enabled'] ?? '1') === '1' ? 'checked' : ''; ?>>
            <span>Enable In-App Notifications</span>
          </label>
        </div>
        
        <div class="form-group">
          <label class="checkbox-label">
            <input type="checkbox" name="email_alerts" value="1" <?php echo ($preferences['email_alerts'] ?? '1') === '1' ? 'checked' : ''; ?>>
            <span>Receive Email Alerts</span>
          </label>
        </div>
      </div>
    </div>
    
    <button type="submit" class="btn btn-primary" style="margin-top: 24px;">Save Preferences</button>
  </form>
</div>

<style>
.checkbox-label {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  margin-bottom: 8px;
}

.checkbox-label input[type="checkbox"] {
  width: 18px;
  height: 18px;
  cursor: pointer;
}

.checkbox-label span {
  font-weight: normal;
}
</style>

<?php require_once 'partials/footer.php'; ?>
