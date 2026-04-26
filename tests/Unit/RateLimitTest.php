<?php
/**
 * Unit Tests for Rate Limiting Functions
 */

namespace InfinityBuilders\Tests\Unit;

use PHPUnit\Framework\TestCase;

class RateLimitTest extends TestCase
{
    protected static $pdo;
    protected static $testEmail = 'ratelimit_test@example.com';
    
    public static function setUpBeforeClass(): void
    {
        // Connect to database
        self::$pdo = new \PDO(
            'mysql:host=localhost;dbname=infinity_builders;charset=utf8mb4',
            'root',
            '',
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
            ]
        );
        
        // Create login_attempts table if not exists
        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                attempt_time DATETIME NOT NULL,
                success BOOLEAN DEFAULT FALSE,
                ip_address VARCHAR(45),
                INDEX idx_email (email),
                INDEX idx_attempt_time (attempt_time)
            )
        ");
    }
    
    protected function setUp(): void
    {
        // Clear test data
        self::$pdo->exec("DELETE FROM login_attempts WHERE email = '" . self::$testEmail . "'");
    }
    
    protected function tearDown(): void
    {
        // Clean up
        self::$pdo->exec("DELETE FROM login_attempts WHERE email = '" . self::$testEmail . "'");
    }
    
    // ==================== RECORD ATTEMPTS TESTS ====================
    
    public function testRecordFailedAttempt(): void
    {
        $stmt = self::$pdo->prepare("
            INSERT INTO login_attempts (email, attempt_time, success, ip_address) 
            VALUES (:email, NOW(), :success, :ip)
        ");
        $stmt->execute([
            'email' => self::$testEmail,
            'success' => false,
            'ip' => '127.0.0.1'
        ]);
        
        $this->assertGreaterThan(0, self::$pdo->lastInsertId());
    }
    
    public function testRecordSuccessfulAttempt(): void
    {
        $stmt = self::$pdo->prepare("
            INSERT INTO login_attempts (email, attempt_time, success, ip_address) 
            VALUES (:email, NOW(), :success, :ip)
        ");
        $stmt->execute([
            'email' => self::$testEmail,
            'success' => true,
            'ip' => '127.0.0.1'
        ]);
        
        $this->assertGreaterThan(0, self::$pdo->lastInsertId());
    }
    
    // ==================== COUNT ATTEMPTS TESTS ====================
    
    public function testCountFailedAttemptsWithinWindow(): void
        {
        // Insert 4 failed attempts in the last 15 minutes
        for ($i = 0; $i < 4; $i++) {
            $stmt = self::$pdo->prepare("
                INSERT INTO login_attempts (email, attempt_time, success, ip_address) 
                VALUES (:email, DATE_SUB(NOW(), INTERVAL :minutes MINUTE), :success, :ip)
            ");
            $stmt->execute([
                'email' => self::$testEmail,
                'minutes' => $i * 5,
                'success' => false,
                'ip' => '127.0.0.1'
            ]);
        }
        
        // Count attempts in last 15 minutes
        $stmt = self::$pdo->prepare("
            SELECT COUNT(*) as count FROM login_attempts 
            WHERE email = :email 
            AND success = FALSE 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $stmt->execute(['email' => self::$testEmail]);
        $result = $stmt->fetch();
        
        $this->assertEquals(4, $result['count']);
    }
    
    public function testNoFailedAttempts(): void
    {
        $stmt = self::$pdo->prepare("
            SELECT COUNT(*) as count FROM login_attempts 
            WHERE email = :email 
            AND success = FALSE 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $stmt->execute(['email' => self::$testEmail]);
        $result = $stmt->fetch();
        
        $this->assertEquals(0, $result['count']);
    }
    
    // ==================== RATE LIMIT CHECK TESTS ====================
    
    public function testRateLimitExceeded(): void
    {
        // Insert 5 failed attempts
        for ($i = 0; $i < 5; $i++) {
            $stmt = self::$pdo->prepare("
                INSERT INTO login_attempts (email, attempt_time, success, ip_address) 
                VALUES (:email, NOW(), :success, :ip)
            ");
            $stmt->execute([
                'email' => self::$testEmail,
                'success' => false,
                'ip' => '127.0.0.1'
            ]);
        }
        
        // Check if locked out
        $stmt = self::$pdo->prepare("
            SELECT COUNT(*) as count FROM login_attempts 
            WHERE email = :email 
            AND success = FALSE 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $stmt->execute(['email' => self::$testEmail]);
        $result = $stmt->fetch();
        
        $this->assertTrue($result['count'] >= 5);
    }
    
    public function testRateLimitNotExceeded(): void
    {
        // Insert only 2 failed attempts
        for ($i = 0; $i < 2; $i++) {
            $stmt = self::$pdo->prepare("
                INSERT INTO login_attempts (email, attempt_time, success, ip_address) 
                VALUES (:email, NOW(), :success, :ip)
            ");
            $stmt->execute([
                'email' => self::$testEmail,
                'success' => false,
                'ip' => '127.0.0.1'
            ]);
        }
        
        $stmt = self::$pdo->prepare("
            SELECT COUNT(*) as count FROM login_attempts 
            WHERE email = :email 
            AND success = FALSE 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $stmt->execute(['email' => self::$testEmail]);
        $result = $stmt->fetch();
        
        $this->assertFalse($result['count'] >= 5);
    }
    
    // ==================== CLEAR ATTEMPTS TESTS ====================
    
    public function testClearAttemptsOnSuccess(): void
    {
        // Insert failed attempts
        $stmt = self::$pdo->prepare("
            INSERT INTO login_attempts (email, attempt_time, success, ip_address) 
            VALUES (:email, NOW(), :success, :ip)
        ");
        $stmt->execute([
            'email' => self::$testEmail,
            'success' => false,
            'ip' => '127.0.0.1'
        ]);
        
        // Insert successful attempt
        $stmt = self::$pdo->prepare("
            INSERT INTO login_attempts (email, attempt_time, success, ip_address) 
            VALUES (:email, NOW(), :success, :ip)
        ");
        $stmt->execute([
            'email' => self::$testEmail,
            'success' => true,
            'ip' => '127.0.0.1'
        ]);
        
        // Delete failed attempts after successful login
        self::$pdo->exec("
            DELETE FROM login_attempts 
            WHERE email = '" . self::$testEmail . "' AND success = FALSE
        ");
        
        // Verify only successful attempt remains
        $stmt = self::$pdo->prepare("
            SELECT COUNT(*) as count FROM login_attempts 
            WHERE email = :email AND success = FALSE
        ");
        $stmt->execute(['email' => self::$testEmail]);
        $result = $stmt->fetch();
        
        $this->assertEquals(0, $result['count']);
    }
    
    // ==================== CLEANUP TESTS ====================
    
    public function testCleanupOldAttempts(): void
    {
        // Insert old attempt (more than 24 hours ago)
        $stmt = self::$pdo->prepare("
            INSERT INTO login_attempts (email, attempt_time, success, ip_address) 
            VALUES (:email, DATE_SUB(NOW(), INTERVAL 25 HOUR), :success, :ip)
        ");
        $stmt->execute([
            'email' => self::$testEmail,
            'success' => false,
            'ip' => '127.0.0.1'
        ]);
        
        // Run cleanup
        self::$pdo->exec("
            DELETE FROM login_attempts 
            WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        
        // Verify cleanup
        $stmt = self::$pdo->prepare("SELECT COUNT(*) as count FROM login_attempts WHERE email = :email");
        $stmt->execute(['email' => self::$testEmail]);
        $result = $stmt->fetch();
        
        $this->assertEquals(0, $result['count']);
    }
    
    // ==================== EDGE CASES ====================
    
    public function testEmptyEmail(): void
    {
        $this->expectException(\PDOException::class);
        
        $stmt = self::$pdo->prepare("
            INSERT INTO login_attempts (email, attempt_time, success, ip_address) 
            VALUES (:email, NOW(), :success, :ip)
        ");
        $stmt->execute([
            'email' => '',
            'success' => false,
            'ip' => '127.0.0.1'
        ]);
    }
    
    public function testIpAddressValidation(): void
    {
        $stmt = self::$pdo->prepare("
            INSERT INTO login_attempts (email, attempt_time, success, ip_address) 
            VALUES (:email, NOW(), :success, :ip)
        ");
        $stmt->execute([
            'email' => self::$testEmail,
            'success' => false,
            'ip' => '192.168.1.1'
        ]);
        
        $stmt = self::$pdo->prepare("SELECT ip_address FROM login_attempts WHERE email = ?");
        $stmt->execute([self::$testEmail]);
        $result = $stmt->fetch();
        
        $this->assertEquals('192.168.1.1', $result['ip_address']);
    }
}
