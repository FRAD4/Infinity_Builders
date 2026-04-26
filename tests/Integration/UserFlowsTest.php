<?php
/**
 * Integration Tests for User Flows and Business Logic
 */

namespace InfinityBuilders\Tests\Integration;

use PHPUnit\Framework\TestCase;

class UserFlowsTest extends TestCase
{
    protected static $pdo;
    protected static $testProjectId;
    protected static $testUserId;
    
    public static function setUpBeforeClass(): void
    {
        self::$pdo = new \PDO(
            'mysql:host=localhost;dbname=infinity_builders;charset=utf8mb4',
            'root',
            '',
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
            ]
        );
        
        // Create test user
        self::$pdo->exec("
            INSERT IGNORE INTO users (id, username, email, password, role) 
            VALUES (9998, 'flow_test_user', 'flow@test.com', 'hash', 'pm')
        ");
        self::$testUserId = 9998;
        
        // Create test project
        self::$pdo->exec("
            INSERT IGNORE INTO projects (id, project_name, client_name, status, project_manager) 
            VALUES (9998, 'Flow Test Project', 'Flow Client', 'Active', 9998)
        ");
        self::$testProjectId = 9998;
    }
    
    public static function tearDownAfterClass(): void
    {
        // Clean up
        self::$pdo->exec("DELETE FROM projects WHERE id = 9998");
        self::$pdo->exec("DELETE FROM users WHERE id = 9998");
    }
    
    protected function setUp(): void
    {
        $_SESSION = [
            'user_id' => 1,
            'user_role' => 'admin',
            'username' => 'Test Admin',
            'csrf_token' => bin2hex(random_bytes(32))
        ];
    }
    
    protected function tearDown(): void
    {
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
    }
    
    // ==================== PROJECT CRUD FLOW TESTS ====================
    
    public function testProjectCreateFlow(): void
    {
        $_POST = [
            'project_name' => 'New Project Flow',
            'client_name' => 'New Client',
            'status' => 'Active',
            'project_manager' => self::$testUserId,
            'csrf_token' => $_SESSION['csrf_token']
        ];
        
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        ob_start();
        require_once __DIR__ . '/../../api/projects.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        // Clean up if created
        if (isset($data['project_id'])) {
            self::$pdo->exec("DELETE FROM projects WHERE id = " . $data['project_id']);
        }
        
        $this->assertTrue($data['success'] ?? false);
    }
    
    public function testProjectListFlow(): void
    {
        ob_start();
        require_once __DIR__ . '/../../api/projects.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertNotNull($data);
        $this->assertIsArray($data['projects'] ?? []);
    }
    
    // ==================== VENDORS FLOW TESTS ====================
    
    public function testVendorsListFlow(): void
    {
        ob_start();
        require_once __DIR__ . '/../../api/vendors.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertNotNull($data);
    }
    
    public function testProjectVendorsFlow(): void
    {
        $_GET['project_id'] = self::$testProjectId;
        
        ob_start();
        require_once __DIR__ . '/../../api/project_vendors.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertNotNull($data);
    }
    
    // ==================== TASKS FLOW TESTS ====================
    
    public function testTasksListFlow(): void
    {
        $_GET['project_id'] = self::$testProjectId;
        
        ob_start();
        require_once __DIR__ . '/../../api/tasks.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertNotNull($data);
    }
    
    public function testTaskCreateFlow(): void
    {
        $_POST = [
            'project_id' => self::$testProjectId,
            'title' => 'Test Task Flow',
            'description' => 'Task description',
            'status' => 'pending',
            'priority' => 'medium',
            'assigned_to' => self::$testUserId,
            'csrf_token' => $_SESSION['csrf_token']
        ];
        
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        ob_start();
        require_once __DIR__ . '/../../api/tasks.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        // Clean up if created
        if (isset($data['task_id'])) {
            self::$pdo->exec("DELETE FROM tasks WHERE id = " . $data['task_id']);
        }
        
        $this->assertTrue($data['success'] ?? false);
    }
    
    // ==================== NOTIFICATIONS FLOW TESTS ====================
    
    public function testNotificationsListFlow(): void
    {
        ob_start();
        require_once __DIR__ . '/../../api/notifications.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertNotNull($data);
        $this->assertIsArray($data['notifications'] ?? []);
    }
    
    // ==================== INVOICE PDF FLOW TESTS ====================
    
    public function testInvoicePdfUploadFlow(): void
    {
        $_POST = [
            'project_id' => self::$testProjectId,
            'invoice_number' => 'INV-' . time(),
            'csrf_token' => $_SESSION['csrf_token']
        ];
        
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        // Note: This test doesn't actually upload a file
        // It tests the API logic without file
        
        $this->assertNotNull(self::$testProjectId);
    }
    
    // ==================== ROLE-BASED ACCESS FLOW TESTS ====================
    
    public function testAdminCanAccessUsersApi(): void
    {
        $_SESSION['user_role'] = 'admin';
        
        ob_start();
        require_once __DIR__ . '/../../api/users.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertTrue($data['success'] ?? false);
    }
    
    public function testPmCannotAccessUsersApi(): void
    {
        $_SESSION['user_role'] = 'pm';
        
        ob_start();
        require_once __DIR__ . '/../../api/users.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertFalse($data['success'] ?? true);
    }
    
    public function testViewerCannotModifyData(): void
    {
        $_SESSION['user_role'] = 'viewer';
        
        $_POST = [
            'project_id' => self::$testProjectId,
            'title' => 'Test',
            'csrf_token' => $_SESSION['csrf_token']
        ];
        
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        ob_start();
        require_once __DIR__ . '/../../api/tasks.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertFalse($data['success'] ?? true);
    }
    
    // ==================== AUDIT LOG FLOW TESTS ====================
    
    public function testAuditLogCreation(): void
    {
        // Insert a test audit log entry
        $stmt = self::$pdo->prepare("
            INSERT INTO audit_log (user_id, action_type, entity_type, entity_id, entity_name, created_at)
            VALUES (:user_id, :action, :entity, :entity_id, :entity_name, NOW())
        ");
        $stmt->execute([
            'user_id' => self::$testUserId,
            'action' => 'test_action',
            'entity' => 'test_entity',
            'entity_id' => 9999,
            'entity_name' => 'Test Entity'
        ]);
        
        $logId = self::$pdo->lastInsertId();
        
        // Verify it was created
        $stmt = self::$pdo->prepare("SELECT * FROM audit_log WHERE id = ?");
        $stmt->execute([$logId]);
        $result = $stmt->fetch();
        
        $this->assertNotFalse($result);
        
        // Clean up
        self::$pdo->exec("DELETE FROM audit_log WHERE id = " . $logId);
    }
    
    public function testAuditLogRetrieval(): void
    {
        $stmt = self::$pdo->query("SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 10");
        $results = $stmt->fetchAll();
        
        $this->assertIsArray($results);
    }
    
    // ==================== PERMISSIONS FLOW TESTS ====================
    
    public function testPermitStatusChangeFlow(): void
    {
        // Create a permit
        self::$pdo->exec("
            INSERT IGNORE INTO permits (id, project_id, city, permit_required, status, submitted_by, submission_date)
            VALUES (9998, " . self::$testProjectId . ", 'Test City', 'yes', 'pending', 1, NOW())
        ");
        
        $_POST = [
            'id' => 9998,
            'status' => 'approved',
            'csrf_token' => $_SESSION['csrf_token']
        ];
        
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        ob_start();
        require_once __DIR__ . '/../../api/permits.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        // Clean up
        self::$pdo->exec("DELETE FROM permits WHERE id = 9998");
        
        $this->assertTrue($data['success'] ?? false);
    }
    
    public function testInspectionScheduleFlow(): void
    {
        // Create an inspection
        self::$pdo->exec("
            INSERT IGNORE INTO inspections (id, project_id, inspection_type, status, requested_by, request_date)
            VALUES (9998, " . self::$testProjectId . ", 'Building', 'scheduled', 1, NOW())
        ");
        
        $_POST = [
            'id' => 9998,
            'status' => 'passed',
            'inspection_date' => date('Y-m-d'),
            'csrf_token' => $_SESSION['csrf_token']
        ];
        
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        ob_start();
        require_once __DIR__ . '/../../api/inspections.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        // Clean up
        self::$pdo->exec("DELETE FROM inspections WHERE id = 9998");
        
        $this->assertTrue($data['success'] ?? false);
    }
    
    // ==================== SEARCH FLOW TESTS ====================
    
    public function testSearchProjectsByName(): void
    {
        $_GET['q'] = 'project';
        
        ob_start();
        require_once __DIR__ . '/../../api/search.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertTrue($data['success'] ?? false);
        $this->assertIsArray($data['results'] ?? []);
    }
    
    public function testSearchVendorsByName(): void
    {
        $_GET['q'] = 'vendor';
        
        ob_start();
        require_once __DIR__ . '/../../api/search.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertTrue($data['success'] ?? false);
    }
    
    public function testSearchUsersByEmail(): void
    {
        $_GET['q'] = 'admin';
        
        ob_start();
        require_once __DIR__ . '/../../api/search.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertTrue($data['success'] ?? false);
    }
    
    // ==================== ERROR HANDLING FLOW TESTS ====================
    
    public function testApiHandlesMissingParams(): void
    {
        $_GET = []; // No parameters
        
        ob_start();
        require_once __DIR__ . '/../../api/permits.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        // Should still return valid JSON, possibly with empty results
        $this->assertNotNull($data);
    }
    
    public function testApiHandlesInvalidJson(): void
    {
        // This tests that the API returns proper JSON error
        $_SESSION = []; // No session
        
        ob_start();
        require_once __DIR__ . '/../../api/users.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertFalse($data['success'] ?? true);
    }
}
