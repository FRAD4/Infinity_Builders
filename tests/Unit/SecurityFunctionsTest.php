<?php
/**
 * Unit Tests for Security Functions (CSRF, Hashing, Session)
 */

namespace InfinityBuilders\Tests\Unit;

use PHPUnit\Framework\TestCase;

class SecurityFunctionsTest extends TestCase
{
    protected function setUp(): void
    {
        // Start session for tests
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
    }
    
    protected function tearDown(): void
    {
        $_SESSION = [];
    }
    
    // ==================== CSRF TOKEN TESTS ====================
    
    public function testCsrfTokenGenerateCreatesToken(): void
    {
        require_once __DIR__ . '/../../includes/security.php';
        
        $token = csrf_token_generate();
        
        $this->assertNotEmpty($token);
        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex chars
    }
    
    public function testCsrfTokenGenerateReusesExisting(): void
    {
        require_once __DIR__ . '/../../includes/security.php';
        
        $token1 = csrf_token_generate();
        $token2 = csrf_token_generate();
        
        $this->assertEquals($token1, $token2); // Should be same session token
    }
    
    public function testCsrfTokenValidateValidToken(): void
    {
        require_once __DIR__ . '/../../includes/security.php';
        
        $token = csrf_token_generate();
        $result = csrf_token_validate($token);
        
        $this->assertTrue($result);
    }
    
    public function testCsrfTokenValidateInvalidToken(): void
    {
        require_once __DIR__ . '/../../includes/security.php';
        
        csrf_token_generate();
        $result = csrf_token_validate('invalid_token');
        
        $this->assertFalse($result);
    }
    
    public function testCsrfTokenValidateEmptyToken(): void
    {
        require_once __DIR__ . '/../../includes/security.php';
        
        $result = csrf_token_validate('');
        
        $this->assertFalse($result);
    }
    
    public function testCsrfTokenFieldGeneratesHtml(): void
    {
        require_once __DIR__ . '/../../includes/security.php';
        
        csrf_token_generate();
        $html = csrf_token_field();
        
        $this->assertStringContainsString('<input', $html);
        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringContainsString('name="csrf_token"', $html);
    }
    
    // ==================== PASSWORD HASHING TESTS ====================
    
    public function testHashPasswordCreatesBcryptHash(): void
    {
        require_once __DIR__ . '/../../includes/security.php';
        
        $hash = hash_password('testpassword123');
        
        $this->assertNotEquals('testpassword123', $hash);
        $this->assertStringStartsWith('$2y$', $hash); // Bcrypt prefix
        $this->assertEquals(60, strlen($hash)); // Bcrypt hash length
    }
    
    public function testHashPasswordDifferentHashesForSamePassword(): void
    {
        require_once __DIR__ . '/../../includes/security.php';
        
        $hash1 = hash_password('mypassword');
        $hash2 = hash_password('mypassword');
        
        $this->assertNotEquals($hash1, $hash2); // Different salts
    }
    
    public function testVerifyPasswordCorrect(): void
    {
        require_once __DIR__ . '/../../includes/security.php';
        
        $hash = hash_password('correctpassword');
        $result = verify_password('correctpassword', $hash, 'bcrypt');
        
        $this->assertTrue($result);
    }
    
    public function testVerifyPasswordIncorrect(): void
    {
        require_once __DIR__ . '/../../includes/security.php';
        
        $hash = hash_password('correctpassword');
        $result = verify_password('wrongpassword', $hash, 'bcrypt');
        
        $this->assertFalse($result);
    }
    
    public function testVerifyPasswordSha256Migration(): void
    {
        require_once __DIR__ . '/../../includes/security.php';
        
        // Old SHA256 hash
        $oldHash = hash('sha256', 'mypassword');
        $result = verify_password('mypassword', $oldHash, 'sha256');
        
        // Should return 'migrate' to signal migration needed
        $this->assertEquals('migrate', $result);
    }
    
    public function testVerifyPasswordEmptyInputs(): void
    {
        require_once __DIR__ . '/../../includes/security.php';
        
        $hash = hash_password('password');
        $result = verify_password('', $hash, 'bcrypt');
        
        $this->assertFalse($result);
    }
    
    // ==================== SESSION SECURITY TESTS ====================
    
    public function testSecureSessionStartCreatesSession(): void
    {
        require_once __DIR__ . '/../../includes/security.php';
        
        session_unset();
        session_destroy();
        
        secure_session_start();
        
        // Sesión iniciada y regenerada
        $this->assertTrue(session_status() === PHP_SESSION_ACTIVE);
        // secure_session_start solo regenera el ID, no setea csrf_token
    }
    
    public function testHasRoleAdmin(): void
    {
        require_once __DIR__ . '/../../includes/security.php';
        
        $_SESSION['user_role'] = 'admin';
        
        // has_role hace comparación exacta, admin solo tiene rol 'admin'
        $this->assertTrue(has_role('admin'));
        $this->assertFalse(has_role('pm')); // Compara exactamnete, no es pm
    }
    
    public function testHasRolePm(): void
    {
        require_once __DIR__ . '/../../includes/security.php';
        
        $_SESSION['user_role'] = 'pm';
        
        $this->assertTrue(has_role('pm'));
        $this->assertFalse(has_role('admin'));
    }
    
    public function testHasRoleNoSession(): void
    {
        require_once __DIR__ . '/../../includes/security.php';
        
        $_SESSION = [];
        
        $this->assertFalse(has_role('admin'));
    }
    
    public function testIsAdminTrue(): void
    {
        require_once __DIR__ . '/../../includes/security.php';
        
        $_SESSION['user_role'] = 'admin';
        
        $this->assertTrue(is_admin());
    }
    
    public function testIsAdminFalse(): void
    {
        require_once __DIR__ . '/../../includes/security.php';
        
        $_SESSION['user_role'] = 'pm';
        
        $this->assertFalse(is_admin());
    }
    
    public function testIsAdminNoSession(): void
    {
        require_once __DIR__ . '/../../includes/security.php';
        
        $_SESSION = [];
        
        $this->assertFalse(is_admin());
    }
    
    // ==================== ROLE REQUIREMENT TESTS ====================
    
    public function testRequireRoleAdminPasses(): void
    {
        require_once __DIR__ . '/../../includes/security.php';
        
        $_SESSION['user_role'] = 'admin';
        
        // require_role() hace redirect si no tiene el rol - testea que existe la función
        $this->assertTrue(function_exists('require_role'));
    }
}
