<?php
/**
 * Base Test Case for Infinity Builders
 * Provides PDO connection and helper methods
 */

namespace InfinityBuilders\Tests;

use PHPUnit\Framework\TestCase;
use PDO;

abstract class BaseTestCase extends TestCase
{
    protected static PDO $pdo;
    protected static string $dbHost = 'localhost';
    protected static string $dbName = 'infinity_builders';
    protected static string $dbUser = 'root';
    protected static string $dbPass = '';
    
    // Test user credentials
    protected const TEST_ADMIN_EMAIL = 'test_admin@infinity.com';
    protected const TEST_ADMIN_PASSWORD = 'testadmin123';
    protected const TEST_USER_EMAIL = 'test_user@infinity.com';
    protected const TEST_USER_PASSWORD = 'testuser123';
    
    public static function setUpBeforeClass(): void
    {
        // Create PDO connection
        self::$pdo = new PDO(
            'mysql:host=' . self::$dbHost . ';dbname=' . self::$dbName . ';charset=utf8mb4',
            self::$dbUser,
            self::$dbPass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        
        // Ensure test users exist
        self::ensureTestUsers();
    }
    
    public static function tearDownAfterClass(): void
    {
        // Clean up test data if needed
    }
    
    /**
     * Create test users if they don't exist
     */
    protected static function ensureTestUsers(): void
    {
        // Test admin user
        $adminHash = hash('sha256', self::TEST_ADMIN_PASSWORD);
        self::$pdo->exec("
            INSERT IGNORE INTO users (username, email, password, password_algo, role) 
            VALUES ('Test Admin', '" . self::TEST_ADMIN_EMAIL . "', '$adminHash', 'sha256', 'admin')
        ");
        
        // Test regular user
        $userHash = hash('sha256', self::TEST_USER_PASSWORD);
        self::$pdo->exec("
            INSERT IGNORE INTO users (username, email, password, password_algo, role) 
            VALUES ('Test User', '" . self::TEST_USER_EMAIL . "', '$userHash', 'sha256', 'viewer')
        ");
    }
    
    /**
     * Get user by email
     */
    protected function getUserByEmail(string $email): ?array
    {
        $stmt = self::$pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Create authenticated session for user
     */
    protected function createSession(int $userId, string $role): array
    {
        return [
            'user_id' => $userId,
            'user_role' => $role,
            'username' => 'Test User',
            'csrf_token' => bin2hex(random_bytes(32))
        ];
    }
    
    /**
     * Assert response is JSON with success
     */
    protected function assertJsonSuccess(string $response): array
    {
        $data = json_decode($response, true);
        $this->assertNotNull($data, 'Response is not valid JSON');
        $this->assertArrayHasKey('success', $data);
        $this->assertTrue($data['success'], 'Expected success=true but got: ' . $response);
        return $data;
    }
    
    /**
     * Assert response is JSON with error
     */
    protected function assertJsonError(string $response, int $expectedCode = 403): array
    {
        $data = json_decode($response, true);
        $this->assertNotNull($data, 'Response is not valid JSON');
        $this->assertArrayHasKey('error', $data);
        return $data;
    }
    
    /**
     * Simulate a GET request to an API endpoint
     */
    protected function apiGet(string $endpoint, array $session = []): string
    {
        // Save current session
        $originalSession = $_SESSION ?? [];
        
        // Set up test session
        $_SESSION = array_merge([
            'user_id' => 1,
            'user_role' => 'admin',
            'username' => 'Test Admin'
        ], $session);
        
        // Capture output
        ob_start();
        
        try {
            // Include the API file
            require $endpoint;
        } catch (\Throwable $e) {
            // API might call exit/die
        }
        
        $output = ob_get_clean();
        
        // Restore session
        $_SESSION = $originalSession;
        
        return $output;
    }
    
    /**
     * Simulate a POST request to an API endpoint
     */
    protected function apiPost(string $endpoint, array $postData = [], array $session = []): string
    {
        // Save current state
        $originalSession = $_SESSION ?? [];
        $originalPost = $_POST ?? [];
        $originalMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        // Set up test state
        $_SESSION = array_merge([
            'user_id' => 1,
            'user_role' => 'admin',
            'username' => 'Test Admin',
            'csrf_token' => 'test_token_123456789012345678901234567890123456789012345678901234567890'
        ], $session);
        
        $_POST = array_merge([
            'csrf_token' => 'test_token_123456789012345678901234567890123456789012345678901234567890'
        ], $postData);
        
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        // Capture output
        ob_start();
        
        try {
            require $endpoint;
        } catch (\Throwable $e) {
            // API might call exit/die
        }
        
        $output = ob_get_clean();
        
        // Restore state
        $_SESSION = $originalSession;
        $_POST = $originalPost;
        $_SERVER['REQUEST_METHOD'] = $originalMethod;
        
        return $output;
    }
}
