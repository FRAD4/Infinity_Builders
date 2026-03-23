<?php
/**
 * customize.php - App settings & customization
 */

$pageTitle = 'Customization';
$currentPage = 'customize';

require_once 'partials/init.php';

$message = '';

// Generate CSRF token
require_once 'includes/security.php';
$csrf_token = csrf_token_generate();

$keys = [
  'company_name',
  'dashboard_show_budget_widget',
  'dashboard_show_alerts_widget',
  'feature_tasks_enabled',
  'feature_gantt_enabled'
];

$settings = array_fill_keys($keys, '');
try {
    $placeholders = rtrim(str_repeat('?,', count($keys)), ',');
    $stmt = $pdo->prepare("SELECT `key`, `value` FROM app_settings WHERE `key` IN ($placeholders)");
    $stmt->execute($keys);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $settings[$row['key']] = $row['value'];
    }
} catch (Exception $e) {}

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $submitted_token = $_POST['csrf_token'] ?? '';
    if (!csrf_token_validate($submitted_token)) {
        $message = "Invalid request. Please try again.";
    } else {
        $company_name                  = trim($_POST['company_name'] ?? '');
        $dashboard_show_budget_widget  = isset($_POST['dashboard_show_budget_widget']) ? '1' : '0';
        $dashboard_show_alerts_widget  = isset($_POST['dashboard_show_alerts_widget']) ? '1' : '0';
        $feature_tasks_enabled         = isset($_POST['feature_tasks_enabled']) ? '1' : '0';
        $feature_gantt_enabled         = isset($_POST['feature_gantt_enabled']) ? '1' : '0';

        $newValues = [
            'company_name' => $company_name,
            'dashboard_show_budget_widget' => $dashboard_show_budget_widget,
            'dashboard_show_alerts_widget' => $dashboard_show_alerts_widget,
            'feature_tasks_enabled' => $feature_tasks_enabled,
            'feature_gantt_enabled' => $feature_gantt_enabled
        ];

        try {
            $stmt = $pdo->prepare("REPLACE INTO app_settings (`key`, `value`) VALUES (?, ?)");
            foreach ($newValues as $k => $v) {
                $stmt->execute([$k, $v]);
            }
            $message  = "Settings saved.";
            $settings = array_merge($settings, $newValues);
            if ($company_name !== '') {
                $companyName = $company_name;
            }
        } catch (Exception $e) {
            $message = "Error saving settings: " . $e->getMessage();
        }
    }
} else {
    if (!empty($settings['company_name'])) {
        $companyName = $settings['company_name'];
    }
}

require_once 'partials/header.php';
?>

<div class="main-header">
  <div class="main-header-title">
    <h1>Customization</h1>
    <div class="breadcrumb">
      <?php echo htmlspecialchars($companyName); ?> &bull; System Settings
    </div>
  </div>

  <div class="user-pill">
    <div class="user-pill-avatar">
      <?php echo strtoupper(substr($currentUser, 0, 1)); ?>
    </div>
    <?php echo htmlspecialchars($currentUser); ?>
  </div>
</div>

<?php if ($message): ?>
  <p class="muted" style="margin-bottom:12px;"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>

<div class="grid">
  <div class="card">
    <div class="card-header">
      <h3>Company &amp; Dashboard</h3>
    </div>
    <div class="card-subtitle">Branding and high-level layout options.</div>

    <form method="post">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
      <div class="form-row">
        <label>Company Name</label>
        <input type="text" name="company_name" value="<?php echo htmlspecialchars($settings['company_name'] ?? ''); ?>">
      </div>

      <hr style="border-color:rgba(255,255,255,0.06); margin:12px 0;">

      <div class="card-header" style="padding:0;margin-bottom:6px;">
        <h3>Dashboard Widgets</h3>
      </div>
      <div class="small-text" style="margin-bottom:8px;">Choose what appears on the main dashboard.</div>

      <div class="form-row">
        <label>
          <input type="checkbox" name="dashboard_show_budget_widget"
                 <?php echo (($settings['dashboard_show_budget_widget'] ?? '1') === '1') ? 'checked' : ''; ?>>
          <span class="small-text"> Show budget overview widget</span>
        </label>
      </div>
      <div class="form-row">
        <label>
          <input type="checkbox" name="dashboard_show_alerts_widget"
                 <?php echo (($settings['dashboard_show_alerts_widget'] ?? '1') === '1') ? 'checked' : ''; ?>>
          <span class="small-text"> Show alerts / notes widget</span>
        </label>
      </div>

      <hr style="border-color:rgba(255,255,255,0.06); margin:12px 0;">

      <div class="card-header" style="padding:0;margin-bottom:6px;">
        <h3>Modules</h3>
      </div>
      <div class="small-text" style="margin-bottom:8px;">Enable extra modules as your system grows.</div>

      <div class="form-row">
        <label>
          <input type="checkbox" name="feature_tasks_enabled"
                 <?php echo (($settings['feature_tasks_enabled'] ?? '0') === '1') ? 'checked' : ''; ?>>
          <span class="small-text"> Tasks &amp; punch list module (future)</span>
        </label>
      </div>
      <div class="form-row">
        <label>
          <input type="checkbox" name="feature_gantt_enabled"
                 <?php echo (($settings['feature_gantt_enabled'] ?? '0') === '1') ? 'checked' : ''; ?>>
          <span class="small-text"> Gantt / schedule view (future)</span>
        </label>
      </div>

      <button class="btn" type="submit">Save Settings</button>
    </form>
  </div>

  <div class="card">
    <div class="card-header">
      <h3>Future Custom Fields (Ideas)</h3>
    </div>
    <div class="card-subtitle">Things you may want to track per project.</div>
    <ul class="small-text">
      <li>Subdivision / community name.</li>
      <li>Lot # / unit #.</li>
      <li>Superintendent assigned.</li>
      <li>Architect / designer.</li>
      <li>Contract type (Cost+, Lump Sum, T&M).</li>
      <li>Permit number and expiration.</li>
    </ul>
    <p class="small-text">
      Later we can add tables for custom project fields and a UI here to manage them,
      so each project can store exactly the extra info you need.
    </p>
  </div>
</div>

<?php require_once 'partials/footer.php'; ?>
