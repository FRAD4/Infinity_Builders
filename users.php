<?php
/**
 * users.php - User management & permissions
 */

$pageTitle = 'Users';
$currentPage = 'users';

require_once 'partials/init.php';
require_role('admin');

$message = '';
$roles = ['admin', 'pm', 'estimator', 'accounting', 'viewer'];
$roleLabels = [
    'admin' => 'Admin',
    'pm' => 'Project Manager',
    'estimator' => 'Estimator',
    'accounting' => 'Accounting',
    'viewer' => 'Viewer'
];

// Determine current user's role
$current_role = 'user';
try {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_role = ($row && !empty($row['role'])) ? $row['role'] : 'user';
} catch (Exception $e) {
    $current_role = 'user';
}

// Restrict access to Admins only (case-insensitive)
if (strtolower($current_role) !== 'admin') {
    http_response_code(403);
    echo "<p style='font-family:Arial;padding:20px;color:red;'>Access denied. Only Admin users can manage users.</p>";
    exit;
}

// Handle role update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_role') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $new_role = $_POST['role'] ?? '';

    if ($user_id > 0 && in_array($new_role, $roles, true)) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$new_role, $user_id]);
            $message = "Role updated.";
        } catch (Exception $e) {
            $message = "Error updating role: " . $e->getMessage();
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    $user_id  = (int)($_POST['user_id'] ?? 0);
    $new_pass = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($user_id <= 0) {
        $message = "Invalid user.";
    } elseif ($new_pass === '' || $confirm === '') {
        $message = "Password fields cannot be empty.";
    } elseif ($new_pass !== $confirm) {
        $message = "Passwords do not match.";
    } elseif (strlen($new_pass) < 6) {
        $message = "Password should be at least 6 characters.";
    } else {
        try {
            $hash = hash_password($new_pass);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, password_algo = 'bcrypt' WHERE id = ?");
            $stmt->execute([$hash, $user_id]);
            $message = "Password updated.";
        } catch (Exception $e) {
            $message = "Error updating password: " . $e->getMessage();
        }
    }
}

// Handle create user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_user') {
    $new_username = trim($_POST['username'] ?? '');
    $new_email = trim($_POST['email'] ?? '');
    $new_password = $_POST['password'] ?? '';
    $new_role = $_POST['role'] ?? 'user';
    
    // Validation
    $errors = [];
    
    if (empty($new_username)) {
        $errors[] = "Username is required.";
    } elseif (strlen($new_username) < 3) {
        $errors[] = "Username must be at least 3 characters.";
    }
    
    if (empty($new_email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    
    if (empty($new_password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($new_password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }
    
    if (!in_array($new_role, ['admin', 'pm', 'estimator', 'accounting', 'viewer'], true)) {
        $errors[] = "Invalid role selected.";
    }
    
    // Check for duplicate username
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$new_username]);
            if ($stmt->fetch()) {
                $errors[] = "Username already exists.";
            }
        } catch (Exception $e) {
            $errors[] = "Error checking username: " . $e->getMessage();
        }
    }
    
    // Check for duplicate email
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$new_email]);
            if ($stmt->fetch()) {
                $errors[] = "Email already exists.";
            }
        } catch (Exception $e) {
            $errors[] = "Error checking email: " . $e->getMessage();
        }
    }
    
    // Create user if no errors
    if (empty($errors)) {
        try {
            // Use bcrypt for new users
            require_once 'includes/security.php';
            $password_hash = hash_password($new_password);
            
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, password_hash, password_algo, role, created_at) 
                VALUES (?, ?, '', ?, 'bcrypt', ?, NOW())
            ");
            $stmt->execute([$new_username, $new_email, $password_hash, $new_role]);
            $message = "User '$new_username' created successfully.";
        } catch (Exception $e) {
            $message = "Error creating user: " . $e->getMessage();
        }
    } else {
        $message = implode(" ", $errors);
    }
}

// Handle delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_user') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    
    if ($user_id <= 0) {
        $message = "Invalid user selected for deletion.";
    } elseif ($user_id === (int)($_SESSION['user_id'] ?? 0)) {
        $message = "You cannot delete yourself.";
    } else {
        // Check if this is the last admin
        try {
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_to_delete = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user_to_delete) {
                $message = "User not found.";
            } elseif (strtolower($user_to_delete['role']) === 'admin') {
                // Count remaining admins
                $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'admin'");
                $admin_count = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
                
                if ($admin_count <= 1) {
                    $message = "Cannot delete the last admin user.";
                } else {
                    // Delete the user
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $message = "User deleted successfully.";
                }
            } else {
                // Delete non-admin user
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $message = "User deleted successfully.";
            }
        } catch (Exception $e) {
            $message = "Error deleting user: " . $e->getMessage();
        }
    }
}

// Fetch users
$users   = [];
$hasRole = true;
try {
    $stmt = $pdo->query("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $hasRole = false;
    $stmt = $pdo->query("SELECT id, username, email, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($message === "") {
        $message = "Note: 'role' column not found, showing users without roles.";
    }
}

require_once 'partials/header.php';
?>

<!-- Header removed - now handled by partials/header.php -->

<div class="card">
  <div class="card-header">
    <div style="display:flex; justify-content:space-between; align-items:center;">
      <h3>User Management</h3>
      <button class="btn" onclick="openCreateUserModal()">+ Add User</button>
    </div>
  </div>
  <div class="card-subtitle">
    Assign roles, reset passwords, create or delete users. Only <strong>Admin</strong> users can see this page.
  </div>

  <?php if ($message): ?>
    <p class="muted" style="margin-bottom:10px;"><?php echo htmlspecialchars($message); ?></p>
  <?php endif; ?>

  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>User</th>
          <?php if ($hasRole): ?>
            <th>Role</th>
            <th>Change Role</th>
          <?php endif; ?>
          <th>Change Password</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$users): ?>
          <tr>
            <td colspan="<?php echo $hasRole ? 5 : 3; ?>" class="muted">No users found.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($users as $u): ?>
            <tr>
              <td>
                <strong><?php echo htmlspecialchars($u['username']); ?></strong><br>
                <span class="small-text"><?php echo htmlspecialchars($u['email']); ?></span><br>
                <span class="small-text">Created: <?php echo htmlspecialchars($u['created_at']); ?></span>
              </td>

              <?php if ($hasRole): ?>
                <td>
                  <span class="small-text">Current:</span><br>
                  <strong><?php echo htmlspecialchars($roleLabels[$u['role']] ?? $u['role'] ?? ''); ?></strong>
                </td>
                <td>
                  <form method="post" style="margin:0;">
                    <input type="hidden" name="action" value="update_role">
                    <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                    <div class="form-row">
                      <label>New Role</label>
                      <select name="role">
                        <?php foreach ($roles as $r): ?>
                          <option value="<?php echo $r; ?>" <?php echo (($u['role'] ?? '') === $r ? 'selected' : ''); ?>>
                            <?php echo $roleLabels[$r]; ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <button class="btn-secondary" type="submit">Update Role</button>
                  </form>
                </td>
              <?php endif; ?>

              <td>
                <form method="post" style="margin:0;">
                  <input type="hidden" name="action" value="change_password">
                  <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                  <div class="form-row">
                    <label>New Password</label>
                    <input type="password" name="new_password">
                  </div>
                  <div class="form-row">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password">
                  </div>
                  <button class="btn" type="submit">Change Password</button>
                  <div class="small-text">Min 6 characters.</div>
                </form>
              </td>
              <td style="white-space:nowrap;">
                <?php 
                $is_self = ((int)$u['id']) === ((int)($_SESSION['user_id'] ?? 0));
                $is_last_admin = false;
                if (!$is_self && strtolower($u['role'] ?? '') === 'admin') {
                    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'admin'");
                    $is_last_admin = ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] <= 1);
                }
                ?>
                <?php if (!$is_self): ?>
                  <button class="btn-danger btn-small" onclick="confirmDelete(<?php echo (int)$u['id']; ?>, '<?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>', <?php echo $is_last_admin ? 'true' : 'false'; ?>)">Delete</button>
                <?php else: ?>
                  <span class="small-text muted">(You)</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once 'partials/footer.php'; ?>

<!-- Create User Modal -->
<div id="createUserModal" class="modal-overlay">
  <div class="modal-content">
    <div class="modal-header">
      <h2>Add New User</h2>
      <button onclick="closeCreateUserModal()" class="modal-close">&times;</button>
    </div>
    
    <form method="post">
      <input type="hidden" name="action" value="create_user">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
      
      <div class="form-group">
        <label for="new-username">Username *</label>
        <input type="text" id="new-username" name="username" required minlength="3" placeholder="Enter username">
        <small>Min 3 characters, must be unique</small>
      </div>
      
      <div class="form-group">
        <label for="new-email">Email *</label>
        <input type="email" id="new-email" name="email" required placeholder="user@example.com">
        <small>Must be a valid email address</small>
      </div>
      
      <div class="form-group">
        <label for="new-password">Password *</label>
        <input type="password" id="new-password" name="password" required minlength="6" placeholder="Enter password">
        <small>Min 6 characters</small>
      </div>
      
      <div class="form-group">
        <label for="new-role">Role *</label>
        <select id="new-role" name="role" required>
          <option value="viewer" selected>Viewer</option>
          <option value="pm">Project Manager</option>
          <option value="estimator">Estimator</option>
          <option value="accounting">Accounting</option>
          <option value="admin">Admin</option>
        </select>
      </div>
      
      <div class="modal-actions">
        <button type="submit" class="btn btn-primary">Create User</button>
        <button type="button" class="btn btn-secondary" onclick="closeCreateUserModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal-overlay">
  <div class="modal-content modal-small">
    <div class="modal-header">
      <h2 class="text-danger">Confirm Delete</h2>
      <button onclick="closeDeleteModal()" class="modal-close">&times;</button>
    </div>
    
    <p id="deleteMessage">Are you sure you want to delete this user?</p>
    
    <form method="post">
      <input type="hidden" name="action" value="delete_user">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
      <input type="hidden" name="user_id" id="deleteUserId" value="">
      
      <div class="modal-actions">
        <button type="submit" class="btn btn-danger">Delete</button>
        <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function openCreateUserModal() {
  document.getElementById('createUserModal').style.display = 'block';
}

function closeCreateUserModal() {
  document.getElementById('createUserModal').style.display = 'none';
}

function confirmDelete(userId, username, isLastAdmin) {
  var modal = document.getElementById('deleteModal');
  var message = document.getElementById('deleteMessage');
  var userIdInput = document.getElementById('deleteUserId');
  
  userIdInput.value = userId;
  
  if (isLastAdmin) {
    message.innerHTML = '<strong>Warning:</strong> This is the last admin user. Deleting this user will remove all admin access. Are you sure?';
  } else {
    message.textContent = 'Are you sure you want to delete user "' + username + '"? This action cannot be undone.';
  }
  
  modal.style.display = 'block';
}

function closeDeleteModal() {
  document.getElementById('deleteModal').style.display = 'none';
}

// Close modals when clicking outside
window.onclick = function(event) {
  var createModal = document.getElementById('createUserModal');
  var deleteModal = document.getElementById('deleteModal');
  
  if (event.target === createModal) {
    closeCreateUserModal();
  }
  if (event.target === deleteModal) {
    closeDeleteModal();
  }
}
</script>


