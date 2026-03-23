<?php
/**
 * Email to Vendors Page
 * Send emails to vendors using PHPMailer
 */

require_once 'partials/init.php';

$pageTitle = 'Email';
$currentPage = 'email';
$currentUser = $_SESSION['user_name'] ?? 'User';

require_once 'partials/header.php';

// Get vendors for dropdown
$vendors = [];
try {
    $db = getDB();
    $stmt = $db->query("SELECT id, name, email FROM vendors ORDER BY name");
    $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Database not available, continue with empty array
}

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    require_once 'includes/email.php';
    
    $to = filter_input(INPUT_POST, 'vendor_email', FILTER_SANITIZE_EMAIL);
    $subject = filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_STRING);
    $body = filter_input(INPUT_POST, 'body', FILTER_SANITIZE_STRING);
    
    if ($to && $subject && $body) {
        $result = sendEmail($to, $subject, $body);
        if ($result) {
            $message = 'Email sent successfully!';
            $messageType = 'success';
        } else {
            $message = 'Failed to send email. Please try again.';
            $messageType = 'error';
        }
    } else {
        $message = 'Please fill in all fields.';
        $messageType = 'error';
    }
}
?>

<div class="page-header">
  <h1>Email to Vendors</h1>
  <p class="text-secondary">Send emails to your vendors</p>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $messageType; ?>">
  <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<div class="card">
  <h2 style="margin-bottom: 20px;">Compose Email</h2>
  
  <form method="POST" action="" style="max-width: 600px;">
    <div class="form-group">
      <label for="vendor_id">Select Vendor</label>
      <select id="vendor_id" name="vendor_id" required onchange="updateVendorEmail(this)">
        <option value="">-- Select a Vendor --</option>
        <?php foreach ($vendors as $vendor): ?>
        <option value="<?php echo $vendor['id']; ?>" data-email="<?php echo htmlspecialchars($vendor['email']); ?>">
          <?php echo htmlspecialchars($vendor['name']); ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    
    <div class="form-group">
      <label for="vendor_email">Vendor Email</label>
      <input type="email" id="vendor_email" name="vendor_email" required readonly placeholder="Email will be filled automatically">
    </div>
    
    <div class="form-group">
      <label for="subject">Subject</label>
      <input type="text" id="subject" name="subject" required placeholder="Enter email subject">
    </div>
    
    <div class="form-group">
      <label for="body">Message</label>
      <textarea id="body" name="body" rows="8" required placeholder="Enter your message"></textarea>
    </div>
    
    <div class="form-actions">
      <button type="submit" name="send_email" class="btn btn-primary">
        <i class="fa-solid fa-paper-plane"></i> Send Email
      </button>
    </div>
  </form>
</div>

<script>
function updateVendorEmail(select) {
  const option = select.options[select.selectedIndex];
  const email = option.getAttribute('data-email');
  document.getElementById('vendor_email').value = email || '';
}
</script>

<style>
.form-group {
  margin-bottom: 16px;
}
.form-group label {
  display: block;
  margin-bottom: 6px;
  font-weight: 500;
}
.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--border-color);
  border-radius: 6px;
  background: var(--bg-primary);
  color: var(--text-primary);
  font-size: 14px;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: var(--primary);
}
.form-actions {
  margin-top: 20px;
}
.btn {
  padding: 10px 20px;
  border-radius: 6px;
  font-weight: 500;
  cursor: pointer;
  border: none;
}
.btn-primary {
  background: var(--primary);
  color: white;
}
.btn-primary:hover {
  background: var(--primary-hover);
}
.alert {
  padding: 12px 16px;
  border-radius: 6px;
  margin-bottom: 20px;
}
.alert-success {
  background: rgba(16, 185, 129, 0.2);
  color: #10B981;
}
.alert-error {
  background: rgba(239, 68, 68, 0.2);
  color: #EF4444;
}
</style>

<?php require_once 'partials/footer.php'; ?>
