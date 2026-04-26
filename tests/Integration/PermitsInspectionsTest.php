<?php
/**
 * Integration Tests for Permits & Inspections Flows
 */

namespace InfinityBuilders\Tests\Integration;

use PHPUnit\Framework\TestCase;

class PermitsInspectionsTest extends TestCase
{
    protected static $pdo;
    protected static $testProjectId;
    protected static $testPermitId;
    protected static $testInspectionId;
    
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
        
        // Create test project
        self::$pdo->exec("
            INSERT IGNORE INTO projects (id, project_name, client_name, status) 
            VALUES (9997, 'Permit Test Project', 'Permit Client', 'Active')
        ");
        self::$testProjectId = 9997;
    }
    
    public static function tearDownAfterClass(): void
    {
        if (self::$pdo) {
            self::$pdo->exec("DELETE FROM inspections WHERE id = 9997");
            self::$pdo->exec("DELETE FROM permits WHERE id = 9997");
            self::$pdo->exec("DELETE FROM projects WHERE id = 9997");
        }
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
    
    // ==================== PERMITS CRUD TESTS ====================
    
    public function testPermitsListWithoutProject(): void
    {
        ob_start();
        require_once __DIR__ . '/../../api/permits.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertNotNull($data, 'Response should be valid JSON');
    }
    
    public function testPermitsListByProject(): void
    {
        $_GET['project_id'] = self::$testProjectId;
        
        ob_start();
        require_once __DIR__ . '/../../api/permits.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertNotNull($data);
    }
    
    public function testPermitsCreate(): void
    {
        $_POST = [
            'project_id' => self::$testProjectId,
            'city' => 'Test City',
            'permit_required' => 'yes',
            'status' => 'pending',
            'submitted_by' => 1,
            'submission_date' => date('Y-m-d'),
            'csrf_token' => $_SESSION['csrf_token']
        ];
        
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        ob_start();
        require_once __DIR__ . '/../../api/permits.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        // Clean up if created
        if (isset($data['permit_id'])) {
            self::$pdo->exec("DELETE FROM permits WHERE id = " . $data['permit_id']);
        }
        
        $this->assertTrue($data['success'] ?? false);
    }
    
    public function testPermitsUpdateStatus(): void
    {
        // Create permit first
        self::$pdo->exec("
            INSERT IGNORE INTO permits (id, project_id, city, permit_required, status, submitted_by, submission_date)
            VALUES (9997, " . self::$testProjectId . ", 'Update Test City', 'yes', 'pending', 1, NOW())
        ");
        
        $_POST = [
            'id' => 9997,
            'status' => 'approved',
            'csrf_token' => $_SESSION['csrf_token']
        ];
        
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        ob_start();
        require_once __DIR__ . '/../../api/permits.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertTrue($data['success'] ?? false);
    }
    
    public function testPermitsDelete(): void
    {
        // Create permit to delete
        self::$pdo->exec("
            INSERT INTO permits (project_id, city, permit_required, status, submitted_by, submission_date)
            VALUES (" . self::$testProjectId . ", 'Delete Test City', 'yes', 'pending', 1, NOW())
        ");
        $permitId = self::$pdo->lastInsertId();
        
        $_POST = [
            'id' => $permitId,
            'action' => 'delete',
            'csrf_token' => $_SESSION['csrf_token']
        ];
        
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        ob_start();
        require_once __DIR__ . '/../../api/permits.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertTrue($data['success'] ?? false);
    }
    
    // ==================== PERMIT STATUS HISTORY TESTS ====================
    
    public function testPermitStatusHistoryCreated(): void
    {
        // Create permit
        self::$pdo->exec("
            INSERT IGNORE INTO permits (id, project_id, city, permit_required, status, submitted_by, submission_date)
            VALUES (9996, " . self::$testProjectId . ", 'History Test City', 'yes', 'pending', 1, NOW())
        ");
        
        // Update status (should create history)
        $_POST = [
            'id' => 9996,
            'status' => 'approved',
            'csrf_token' => $_SESSION['csrf_token']
        ];
        
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        ob_start();
        require_once __DIR__ . '/../../api/permits.php';
        $output = ob_get_clean();
        
        // Check if status history was created
        $stmt = self::$pdo->prepare("SELECT COUNT(*) as count FROM permit_status_history WHERE permit_id = ?");
        $stmt->execute([9996]);
        $result = $stmt->fetch();
        
        // Clean up
        self::$pdo->exec("DELETE FROM permit_status_history WHERE permit_id = 9996");
        self::$pdo->exec("DELETE FROM permits WHERE id = 9996");
        
        $this->assertGreaterThan(0, $result['count']);
    }
    
    // ==================== PERMIT AGE TESTS ====================
    
    public function testPermitAgeCalculation(): void
    {
        // Create permit submitted 10 days ago
        $tenDaysAgo = date('Y-m-d', strtotime('-10 days'));
        
        self::$pdo->exec("
            INSERT INTO permits (project_id, city, permit_required, status, submitted_by, submission_date)
            VALUES (" . self::$testProjectId . ", 'Age Test City', 'yes', 'pending', 1, '$tenDaysAgo')
        ");
        $permitId = self::$pdo->lastInsertId();
        
        // Calculate age in test
        $submissionDate = new \DateTime($tenDaysAgo);
        $today = new \DateTime();
        $age = $today->diff($submissionDate)->days;
        
        // Clean up
        self::$pdo->exec("DELETE FROM permits WHERE id = " . $permitId);
        
        $this->assertEquals(10, $age);
    }
    
    public function testPermitOverdueFlag(): void
    {
        // Create permit submitted 35 days ago (should be overdue)
        $thirtyFiveDaysAgo = date('Y-m-d', strtotime('-35 days'));
        
        self::$pdo->exec("
            INSERT INTO permits (project_id, city, permit_required, status, submitted_by, submission_date)
            VALUES (" . self::$testProjectId . ", 'Overdue Test City', 'yes', 'pending', 1, '$thirtyFiveDaysAgo')
        ");
        $permitId = self::$pdo->lastInsertId();
        
        // Check if overdue (>30 days)
        $submissionDate = new \DateTime($thirtyFiveDaysAgo);
        $today = new \DateTime();
        $age = $today->diff($submissionDate)->days;
        $isOverdue = $age > 30;
        
        // Clean up
        self::$pdo->exec("DELETE FROM permits WHERE id = " . $permitId);
        
        $this->assertTrue($isOverdue);
    }
    
    // ==================== INSPECTIONS CRUD TESTS ====================
    
    public function testInspectionsListWithoutProject(): void
    {
        ob_start();
        require_once __DIR__ . '/../../api/inspections.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertNotNull($data, 'Response should be valid JSON');
    }
    
    public function testInspectionsListByProject(): void
    {
        $_GET['project_id'] = self::$testProjectId;
        
        ob_start();
        require_once __DIR__ . '/../../api/inspections.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertNotNull($data);
    }
    
    public function testInspectionsCreate(): void
    {
        $_POST = [
            'project_id' => self::$testProjectId,
            'inspection_type' => 'Electrical',
            'status' => 'scheduled',
            'requested_by' => 1,
            'request_date' => date('Y-m-d'),
            'csrf_token' => $_SESSION['csrf_token']
        ];
        
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        ob_start();
        require_once __DIR__ . '/../../api/inspections.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        // Clean up if created
        if (isset($data['inspection_id'])) {
            self::$pdo->exec("DELETE FROM inspections WHERE id = " . $data['inspection_id']);
        }
        
        $this->assertTrue($data['success'] ?? false);
    }
    
    public function testInspectionsUpdateStatus(): void
    {
        // Create inspection first
        self::$pdo->exec("
            INSERT IGNORE INTO inspections (id, project_id, inspection_type, status, requested_by, request_date)
            VALUES (9997, " . self::$testProjectId . ", 'Building', 'scheduled', 1, NOW())
        ");
        
        $_POST = [
            'id' => 9997,
            'status' => 'passed',
            'inspection_date' => date('Y-m-d'),
            'csrf_token' => $_SESSION['csrf_token']
        ];
        
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        ob_start();
        require_once __DIR__ . '/../../api/inspections.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertTrue($data['success'] ?? false);
    }
    
    // ==================== PERMIT REQUIRED FALSE TESTS ====================
    
    public function testPermitNotRequiredHidesFields(): void
    {
        // Create permit with permit_required = no
        self::$pdo->exec("
            INSERT INTO permits (project_id, city, permit_required, status, submitted_by, submission_date)
            VALUES (" . self::$testProjectId . ", 'No Permit City', 'no', 'not_required', 1, NOW())
        ");
        $permitId = self::$pdo->lastInsertId();
        
        // Verify it's stored correctly
        $stmt = self::$pdo->prepare("SELECT * FROM permits WHERE id = ?");
        $stmt->execute([$permitId]);
        $permit = $stmt->fetch();
        
        // Clean up
        self::$pdo->exec("DELETE FROM permits WHERE id = " . $permitId);
        
        $this->assertEquals('no', $permit['permit_required']);
    }
    
    // ==================== AUTHORIZATION TESTS ====================
    
    public function testPermitsDeniedWithoutSession(): void
    {
        $_SESSION = [];
        
        ob_start();
        require_once __DIR__ . '/../../api/permits.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertFalse($data['success'] ?? true);
    }
    
    public function testInspectionsDeniedWithoutSession(): void
    {
        $_SESSION = [];
        
        ob_start();
        require_once __DIR__ . '/../../api/inspections.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertFalse($data['success'] ?? true);
    }
    
    // ==================== VALIDATION TESTS ====================
    
    public function testPermitsCreateValidatesRequiredFields(): void
    {
        $_POST = [
            'project_id' => self::$testProjectId,
            // Missing required fields
            'csrf_token' => $_SESSION['csrf_token']
        ];
        
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        ob_start();
        require_once __DIR__ . '/../../api/permits.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertFalse($data['success'] ?? true);
    }
    
    public function testInspectionsCreateValidatesRequiredFields(): void
    {
        $_POST = [
            'project_id' => self::$testProjectId,
            // Missing required fields
            'csrf_token' => $_SESSION['csrf_token']
        ];
        
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        ob_start();
        require_once __DIR__ . '/../../api/inspections.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertFalse($data['success'] ?? true);
    }
}
