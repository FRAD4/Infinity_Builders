<?php
/**
 * Sanitization Tests - XSS and Input Validation Protection
 * Tests that user input is properly sanitized before output
 */

namespace InfinityBuilders\Tests\Security;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/sanitize.php';

class SanitizationTest extends TestCase
{
    // ========================================
    // String Sanitization Tests
    // ========================================
    
    public function testSanitizeStringEscapesHtml(): void
    {
        $input = '<script>alert("xss")</script>';
        $result = sanitize_input($input, 'string');
        
        $this->assertStringContainsString('&lt;', $result);
        $this->assertStringContainsString('&gt;', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }
    
    public function testSanitizeStringPreservesText(): void
    {
        $input = 'Hello World';
        $result = sanitize_input($input, 'string');
        
        $this->assertEquals('Hello World', $result);
    }
    
    public function testSanitizeStringTrimsWhitespace(): void
    {
        $input = '  Hello World  ';
        $result = sanitize_input($input, 'string');
        
        $this->assertEquals('Hello World', $result);
    }
    
    // ========================================
    // HTML Sanitization Tests
    // ========================================
    
    public function testSanitizeHtmlEscapesHtml(): void
    {
        $input = '<b>Hello</b> <script>alert(1)</script>';
        $result = sanitize_input($input, 'html');
        
        $this->assertStringContainsString('&lt;b&gt;', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }
    
    // ========================================
    // Email Sanitization Tests
    // ========================================
    
    public function testSanitizeEmailValidatesEmail(): void
    {
        $input = 'test@example.com';
        $result = sanitize_input($input, 'email');
        
        $this->assertEquals('test@example.com', $result);
    }
    
    public function testSanitizeEmailLowercasesEmail(): void
    {
        $input = 'Test@Example.COM';
        $result = sanitize_input($input, 'email');
        
        $this->assertEquals('test@example.com', $result);
    }
    
    public function testSanitizeEmailRejectsInvalid(): void
    {
        $input = 'not-an-email';
        $result = sanitize_input($input, 'email');
        
        $this->assertEquals('', $result);
    }
    
    // ========================================
    // Integer Sanitization Tests
    // ========================================
    
    public function testSanitizeIntValidatesInteger(): void
    {
        $input = '42';
        $result = sanitize_input($input, 'int');
        
        $this->assertEquals(42, $result);
        $this->assertIsInt($result);
    }
    
    public function testSanitizeIntRejectsNonNumeric(): void
    {
        $input = 'abc';
        $result = sanitize_input($input, 'int');
        
        $this->assertEquals(0, $result);
    }
    
    // ========================================
    // Array Sanitization Tests
    // ========================================
    
    public function testSanitizeInputArray(): void
    {
        $input = [
            'name' => '<script>alert(1)</script>',
            'email' => 'test@example.com'
        ];
        
        $result = sanitize_input_array($input);
        
        $this->assertArrayHasKey('name', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result['name']);
        $this->assertEquals('test@example.com', $result['email']);
    }
    
    public function testSanitizeInputArrayRecursion(): void
    {
        $input = [
            'user' => [
                'name' => '<b>Admin</b>',
                'role' => 'admin'
            ]
        ];
        
        $result = sanitize_input_array($input);
        
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('name', $result['user']);
        $this->assertStringContainsString('&lt;b&gt;', $result['user']['name']);
    }
    
    // ========================================
    // POST/GET Sanitization Tests
    // ========================================
    
    public function testSanitizePost(): void
    {
        $_POST = ['test' => '<script>alert(1)</script>'];
        
        $result = sanitize_post('test', 'string');
        
        $this->assertStringContainsString('&lt;script&gt;', $result);
        
        // Clean up
        $_POST = [];
    }
    
    public function testSanitizePostReturnsNullForMissing(): void
    {
        $_POST = [];
        
        $result = sanitize_post('nonexistent', 'string');
        
        $this->assertNull($result);
    }
    
    public function testSanitizeGet(): void
    {
        $_GET = ['search' => '<script>alert(1)</script>'];
        
        $result = sanitize_get('search', 'string');
        
        $this->assertStringContainsString('&lt;script&gt;', $result);
        
        // Clean up
        $_GET = [];
    }
    
    public function testSanitizeGetReturnsNullForMissing(): void
    {
        $_GET = [];
        
        $result = sanitize_get('nonexistent', 'string');
        
        $this->assertNull($result);
    }
    
    // ========================================
    // Null Handling Tests
    // ========================================
    
    public function testSanitizeInputHandlesNull(): void
    {
        $result = sanitize_input(null, 'string');
        
        $this->assertNull($result);
    }
    
    public function testSanitizeInputHandlesNullInArray(): void
    {
        $input = ['name' => null, 'value' => 'test'];
        
        $result = sanitize_input_array($input);
        
        $this->assertNull($result['name']);
        $this->assertEquals('test', $result['value']);
    }
    
    // ========================================
    // Special Characters Tests
    // ========================================
    
    public function testSanitizeStringPreservesAccents(): void
    {
        $input = 'José García';
        $result = sanitize_input($input, 'string');
        
        $this->assertEquals('José García', $result);
    }
    
    public function testSanitizeStringPreservesEmojis(): void
    {
        $input = 'Hello 👋';
        $result = sanitize_input($input, 'string');
        
        $this->assertStringContainsString('👋', $result);
    }
    
    public function testSanitizeStringHandlesQuotes(): void
    {
        $input = 'He said "Hello"';
        $result = sanitize_input($input, 'string');
        
        $this->assertStringContainsString('&quot;', $result);
    }
    
    public function testSanitizeStringHandlesApostrophe(): void
    {
        $input = "It's a test";
        $result = sanitize_input($input, 'string');
        
        $this->assertStringContainsString('&#039;', $result);
    }
}
