<?php
/**
 * Integration Tests for API Endpoints
 */

namespace InfinityBuilders\Tests\Integration;

use PHPUnit\Framework\TestCase;

class ApiEndpointsTest extends TestCase
{
    protected static $pdo;
    protected static $testProjectId;
    protected static $testPermitId;
    protected static $testInspectionId;
    
    public static function setUpBeforeClass(): void
    {
        // Connect to test database
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
            INSERT IGNORE INTO projects (project_name, client_name, status, created_at) 
            VALUES ('Test Project API', 'Test Client', 'Active', NOW())
        ");
        self::$testProjectId = self::$pdo->lastInsertId();
        
        // Create test permit
        if (self::$testProjectId) {
            self::$pdo->exec("
                INSERT IGNORE INTO permits (project_id, city, permit_required, status, submitted_by, submission_date)
                VALUES (" . self::$testProjectId . ", 'Test City', 'yes', 'pending', 1, NOW())
            ");
            self::$testPermitId = self::$pdo->lastInsertId();
        }
        
        // Create test inspection
        if (self::$testProjectId) {
            self::$pdo->exec("
                INSERT IGNORE INTO inspections (project_id, inspection_type, status, requested_by, request_date)
                VALUES (" . self::$testProjectId . ", 'Electrical', 'pending', 1, NOW())
            ");
            self::$testInspectionId = self::$pdo->lastInsertId();
        }
    }
    
    public static function tearDownAfterClass(): void
    {
        // Clean up test data
        if (self::$pdo) {
            if (self::$testInspectionId) {
                self::$pdo->exec("DELETE FROM inspections WHERE id = " . self::$testInspectionId);
            }
            if (self::$testPermitId) {
                self::$pdo->exec("DELETE FROM permits WHERE id = " . self::$testPermitId);
            }
            if (self::$testProjectId) {
                self::$pdo->exec("DELETE FROM projects WHERE id = " . self::$testProjectId);
            }
        }
    }
    
    protected function setUp(): void
    {
        // Set up session for API tests
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
    }
    
    // ==================== USERS API TESTS ====================
    
    public function testUsersApiReturnsJson(): void
    {
        $_SESSION['user_role'] = 'admin';
        
        ob_start();
        require_once __DIR__ . '/../../api/users.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertNotNull($data, 'Response is not valid JSON');
        $this->assertTrue($data['success'] ?? false);
    }
    
    public function testUsersApiDeniedForNonAdmin(): void
    {
        $_SESSION['user_role'] = 'pm';
        
        ob_start();
        require_once __DIR__ . '/../../api/users.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertFalse($data['success'] ?? true);
    }
    
    // ==================== PERMITS API TESTS ====================
    
    public function testPermitsApiList(): void
    {
        ob_start();
        require_once __DIR__ . '/../../api/permits.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertNotNull($data, 'Response is not valid JSON');
    }
    
    public function testPermitsApiGetById(): void
    {
        if (!self::$testPermitId) {
            $this->markTestSkipped('No test permit available');
        }
        
        $_GET['id'] = self::$testPermitId;
        
        ob_start();
        require_once __DIR__ . '/../../api/permits.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertNotNull($data);
    }
    
    public function testPermitsApiCreate(): void
    {
        $_POST = [
            'project_id' => self::$testProjectId,
            'city' => 'New Test City',
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
        
        // Clean up created permit
        if (isset($data['permit_id'])) {
            self::$pdo->exec("DELETE FROM permits WHERE id = " . $data['permit_id']);
        }
        
        $this->assertTrue($data['success'] ?? false);
    }
    
    // ==================== INSPECTIONS API TESTS ====================
    
    public function testInspectionsApiList(): void
    {
        ob_start();
        require_once __DIR__ . '/../../api/inspections.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertNotNull($data, 'Response is not valid JSON');
    }
    
    public function testInspectionsApiGetByProject(): void
    {
        if (!self::$testProjectId) {
            $this->markTestSkipped('No test project available');
        }
        
        $_GET['project_id'] = self::$testProjectId;
        
        ob_start();
        require_once __DIR__ . '/../../api/inspections.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertNotNull($data);
    }
    
    public function testInspectionsApiCreate(): void
    {
        $_POST = [
            'project_id' => self::$testProjectId,
            'inspection_type' => 'Plumbing',
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
        
        // Clean up created inspection
        if (isset($data['inspection_id'])) {
            self::$pdo->exec("DELETE FROM inspections WHERE id = " . $data['inspection_id']);
        }
        
        $this->assertTrue($data['success'] ?? false);
    }
    
    // ==================== SEARCH API TESTS ====================
    
    public function testSearchApiRequiresMinLength(): void
    {
        $_GET['q'] = 'a'; // Too short
        
        ob_start();
        require_once __DIR__ . '/../../api/search.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertFalse($data['success'] ?? true);
        $this->assertStringContainsString('minimum', $data['error'] ?? '');
    }
    
    public function testSearchApiReturnsResults(): void
    {
        $_GET['q'] = 'test'; // Minimum 4 chars
        
        ob_start();
        require_once __DIR__ . '/../../api/search.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertNotNull($data);
        $this->assertIsArray($data['results'] ?? []);
    }
    
    public function testSearchApiEmptyQuery(): void
    {
        $_GET['q'] = '';
        
        ob_start();
        require_once __DIR__ . '/../../api/search.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertFalse($data['success'] ?? true);
    }
    
    // ==================== DOCUMENTS API TESTS ====================
    
    public function testDocumentsApiList(): void
    {
        $_GET['project_id'] = self::$testProjectId;
        
        ob_start();
        require_once __DIR__ . '/../../api/documents.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertNotNull($data, 'Response is not valid JSON');
    }
    
    // ==================== PROJECT TEAM API TESTS ====================
    
    public function testProjectTeamApiList(): void
    {
        $_GET['project_id'] = self::$testProjectId;
        
        ob_start();
        require_once __DIR__ . '/../../api/project_team.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertNotNull($data);
    }
    
    // ==================== TIMELINE API TESTS ====================
    
    public function testTimelineApiReturnsData(): void
    {
        if (!self::$testProjectId) {
            $this->markTestSkipped('No test project available');
        }
        
        $_GET['project_id'] = self::$testProjectId;
        
        ob_start();
        require_once __DIR__ . '/../../api/project_timeline.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertNotNull($data);
    }
    
    // ==================== AUTHORIZATION TESTS ====================
    
    public function testApiDeniedWithoutSession(): void
    {
        $_SESSION = []; // Clear session
        
        ob_start();
        require_once __DIR__ . '/../../api/users.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        $this->assertFalse($data['success'] ?? true);
    }
}
