<?php
/**
 * Security Tests - Authentication & Authorization
 * Tests CSRF protection, session security, and role-based access
 */

namespace InfinityBuilders\Tests\Security;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/security.php';

class AuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        // Start fresh session for each test
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_unset();
    }
    
    protected function tearDown(): void
    {
        session_unset();
    }
    
    // ========================================
    // CSRF Token Tests
    // ========================================
    
    public function testCsrfTokenGeneration(): void
    {
        $token = csrf_token_generate();
        
        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token), 'CSRF token should be 64 characters (32 bytes hex)');
        $this->assertTrue(ctype_xdigit($token), 'Token should be hex characters');
    }
    
    public function testCsrfTokenIsStoredInSession(): void
    {
        $token = csrf_token_generate();
        
        $this->assertArrayHasKey('csrf_token', $_SESSION);
        $this->assertEquals($token, $_SESSION['csrf_token']);
    }
    
    public function testCsrfTokenValidationWithValidToken(): void
    {
        $token = csrf_token_generate();
        $isValid = csrf_token_validate($token);
        
        $this->assertTrue($isValid, 'Valid CSRF token should pass validation');
    }
    
    public function testCsrfTokenValidationWithInvalidToken(): void
    {
        csrf_token_generate();
        $isValid = csrf_token_validate('invalid_token_123');
        
        $this->assertFalse($isValid, 'Invalid CSRF token should fail validation');
    }
    
    public function testCsrfTokenValidationWithEmptyToken(): void
    {
        $isValid = csrf_token_validate('');
        
        $this->assertFalse($isValid, 'Empty token should fail validation');
    }
    
    public function testCsrfTokenValidationWithNullToken(): void
    {
        $isValid = csrf_token_validate(null);
        
        $this->assertFalse($isValid, 'Null token should fail validation');
    }
    
    public function testCsrfTokenFieldGeneratesHtml(): void
    {
        $field = csrf_token_field();
        
        $this->assertStringContainsString('<input', $field);
        $this->assertStringContainsString('type="hidden"', $field);
        $this->assertStringContainsString('name="csrf_token"', $field);
    }
    
    // ========================================
    // Password Security Tests
    // ========================================
    
    public function testBcryptHashGeneratesValidHash(): void
    {
        $password = 'testPassword123';
        $hash = hash_password($password);
        
        $this->assertNotEmpty($hash);
        $this->assertStringStartsWith('$2y$', $hash, 'Bcrypt hash should start with $2y$');
    }
    
    public function testPasswordVerifyWithBcrypt(): void
    {
        $password = 'testPassword123';
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        
        $result = verify_password($password, $hash, 'bcrypt');
        
        $this->assertTrue($result, 'Bcrypt password should verify correctly');
    }
    
    public function testPasswordVerifyWithWrongPassword(): void
    {
        $password = 'testPassword123';
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        
        $result = verify_password('wrongPassword', $hash, 'bcrypt');
        
        $this->assertFalse($result, 'Wrong password should not verify');
    }
    
    public function testLegacySha256PasswordVerify(): void
    {
        $password = 'testPassword123';
        $sha256Hash = hash('sha256', $password);
        
        $result = verify_password($password, $sha256Hash, 'sha256');
        
        $this->assertEquals('migrate', $result, 'Legacy SHA256 should return migrate flag');
    }
    
    public function testLegacySha256WrongPassword(): void
    {
        $sha256Hash = hash('sha256', 'correctPassword');
        
        $result = verify_password('wrongPassword', $sha256Hash, 'sha256');
        
        $this->assertFalse($result, 'Wrong SHA256 password should fail');
    }
    
    public function testInvalidAlgorithmFails(): void
    {
        $result = verify_password('password', 'hash', 'unknown_algo');
        
        $this->assertFalse($result, 'Unknown algorithm should fail');
    }
    
    // ========================================
    // Role-Based Access Control Tests
    // ========================================
    
    public function testHasRoleWithMatchingRole(): void
    {
        $_SESSION['user_role'] = 'admin';
        
        $this->assertTrue(has_role('admin'));
    }
    
    public function testHasRoleWithNonMatchingRole(): void
    {
        $_SESSION['user_role'] = 'viewer';
        
        $this->assertFalse(has_role('admin'));
    }
    
    public function testHasRoleWithNoRole(): void
    {
        unset($_SESSION['user_role']);
        
        $this->assertFalse(has_role('admin'));
    }
    
    public function testIsAdminWithAdminRole(): void
    {
        $_SESSION['user_role'] = 'admin';
        
        $this->assertTrue(is_admin());
    }
    
    public function testIsAdminWithNonAdminRole(): void
    {
        $_SESSION['user_role'] = 'viewer';
        
        $this->assertFalse(is_admin());
    }
    
    // ========================================
    // Session Security Tests
    // ========================================
    
    public function testSecureSessionStartRegeneratesId(): void
    {
        $originalId = session_id();
        
        secure_session_start();
        
        $newId = session_id();
        
        $this->assertNotEquals($originalId, $newId, 'Session ID should be regenerated');
    }
}
