<?php
/**
 * login.php - Infinity Builders Login
 * Design System Ready
 */
session_start();
require_once 'config.php';
require_once 'includes/security.php';
require_once 'includes/sanitize.php';

$error = "";
csrf_token_generate();

// Rate limiting: check failed attempts in current session
$failed_attempts = (int)($_SESSION['login_failed_attempts'] ?? 0);
$lockout_until = (int)($_SESSION['login_lockout_until'] ?? 0);
$now = time();

if ($failed_attempts >= 5 && $now < $lockout_until) {
    $remaining = ceil(($lockout_until - $now) / 60);
    $error = "Too many failed attempts. Please try again in $remaining minutes.";
} else {
    // Handle login
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        try {
            $stmt = $pdo->prepare("SELECT id, username, email, password, role, password_algo, password_hash FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Verify password with migration support
                $storedHash = ($user['password_algo'] === 'bcrypt') ? ($user['password_hash'] ?? '') : $user['password'];
                $result = verify_password($password, $storedHash, $user['password_algo']);
                
                if ($result === true || $result === 'migrate') {
                    // Password verified - reset failed attempts
                    $_SESSION['login_failed_attempts'] = 0;
                    $_SESSION['login_lockout_until'] = 0;
                    
                    // Log successful login
                    error_log("LOGIN_SUCCESS: user_id={$user['id']} username={$user['username']} ip={$_SERVER['REMOTE_ADDR']}");
                    
                    // Password migration if needed
                    if ($result === 'migrate') {
                        $newHash = hash_password($password);
                        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, password_algo = 'bcrypt' WHERE id = ?");
                        $stmt->execute([$newHash, $user['id']]);
                    }
                    
                    // Regenerate session
                    secure_session_start();
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    
                    header("Location: dashboard.php");
                    exit;
                }
            }
            // Failed login attempt
            $failed_attempts++;
            $_SESSION['login_failed_attempts'] = $failed_attempts;
            if ($failed_attempts >= 5) {
                $_SESSION['login_lockout_until'] = $now + (15 * 60); // 15 minute lockout
                error_log("LOGIN_LOCKOUT: ip={$_SERVER['REMOTE_ADDR']} attempts=$failed_attempts");
            }
            $error = "Invalid email or password.";
        } catch (Exception $e) {
            error_log("LOGIN_ERROR: " . $e->getMessage());
            $error = "Login error. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Infinity Builders</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  
  <style>
    body {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      background: var(--bg-primary);
    }
    
    .login-container {
      width: 100%;
      max-width: 400px;
      padding: 20px;
    }
    
    .login-card {
      background: var(--bg-card);
      border: 1px solid var(--border-color);
      border-radius: 16px;
      padding: 40px;
      text-align: center;
    }
    
    .login-logo {
      margin-bottom: 32px;
    }
    
    .login-logo img {
      width: 80px;
      height: 80px;
      border-radius: 16px;
    }
    
    .login-title {
      font-size: 24px;
      font-weight: 700;
      margin-bottom: 8px;
    }
    
    .login-subtitle {
      color: var(--text-muted);
      font-size: 14px;
      margin-bottom: 32px;
    }
    
    .login-form .form-row {
      margin-bottom: 20px;
      text-align: left;
    }
    
    .login-form input {
      padding: 14px 16px;
      font-size: 15px;
    }
    
    .login-form .btn {
      width: 100%;
      padding: 14px;
      font-size: 15px;
      margin-top: 8px;
    }
    
    .login-error {
      background: var(--danger-light);
      color: var(--danger);
      padding: 12px 16px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 14px;
    }
    
    .login-footer {
      margin-top: 32px;
      padding-top: 24px;
      border-top: 1px solid var(--border-color);
      font-size: 12px;
      color: var(--text-muted);
    }
  </style>
</head>
<body>

<div class="login-container">
  <div class="login-card animate-slide-up">
    <!-- Logo -->
    <div class="login-logo">
      <img src="assets/infinity-logo.webp" alt="Infinity Builders" onerror="this.style.display='none'">
      <div style="width:80px;height:80px;background:var(--gradient-primary);border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto;">
        <span style="font-size:32px;">🏗️</span>
      </div>
    </div>
    
    <h1 class="login-title">Welcome Back</h1>
    <p class="login-subtitle">Sign in to Infinity Builders</p>
    
    <?php if ($error): ?>
    <div class="login-error">
      <i class="fa-solid fa-circle-exclamation"></i>
      <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>
    
    <form method="post" class="login-form">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
      
      <div class="form-row">
        <label for="email">Email</label>
        <input 
          type="email" 
          id="email" 
          name="email" 
          placeholder="you@company.com" 
          required 
          autofocus
        >
      </div>
      
      <div class="form-row">
        <label for="password">Password</label>
        <input 
          type="password" 
          id="password" 
          name="password" 
          placeholder="Enter your password" 
          required
        >
      </div>
      
      <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-right-to-bracket"></i>
        Sign In
      </button>
    </form>
    
    <div class="login-footer">
      &copy; <?php echo date('Y'); ?> Infinity Builders. All rights reserved.
    </div>
  </div>
</div>

</body>
</html>
