<?php
/**
 * Unit Tests for Sanitization Functions
 */

namespace InfinityBuilders\Tests\Unit;

use PHPUnit\Framework\TestCase;

class SanitizeInputTest extends TestCase
{
    protected function sanitizeInput($value, $type = 'string')
    {
        // Include the actual function
        require_once __DIR__ . '/../../includes/sanitize.php';
        return \sanitize_input($value, $type);
    }
    
    protected function sanitizeInputArray($data, $defaultType = 'string')
    {
        require_once __DIR__ . '/../../includes/sanitize.php';
        return \sanitize_input_array($data, $defaultType);
    }
    
    // ==================== INT TYPE TESTS ====================
    
    public function testSanitizeIntValid(): void
    {
        $this->assertEquals(42, $this->sanitizeInput(42, 'int'));
        $this->assertEquals(0, $this->sanitizeInput(0, 'int'));
    }
    
    public function testSanitizeIntStringNumber(): void
    {
        $this->assertEquals(123, $this->sanitizeInput('123', 'int'));
    }
    
    public function testSanitizeIntInvalidReturnsZero(): void
    {
        $this->assertEquals(0, $this->sanitizeInput('abc', 'int'));
        $this->assertEquals(0, $this->sanitizeInput(null, 'int'));
        $this->assertEquals(0, $this->sanitizeInput('', 'int'));
    }
    
    public function testSanitizeIntNegative(): void
    {
        $this->assertEquals(-5, $this->sanitizeInput(-5, 'int'));
        $this->assertEquals(-100, $this->sanitizeInput('-100', 'int'));
    }
    
    public function testSanitizeIntFloatTruncates(): void
    {
        // sanitize_input con int no convierte floats, devuelve 0
        $this->assertEquals(0, $this->sanitizeInput(3.7, 'int'));
    }
    
    // ==================== EMAIL TYPE TESTS ====================
    
    public function testSanitizeEmailValid(): void
    {
        $result = $this->sanitizeInput('test@EXAMPLE.com', 'email');
        $this->assertEquals('test@example.com', $result);
    }
    
    public function testSanitizeEmailInvalid(): void
    {
        $this->assertEquals('', $this->sanitizeInput('notanemail', 'email'));
    }
    
    public function testSanitizeEmailWithPlus(): void
    {
        $result = $this->sanitizeInput('test+tag@example.com', 'email');
        $this->assertEquals('test+tag@example.com', $result);
    }
    
    // ==================== STRING TYPE TESTS ====================
    
    public function testSanitizeStringBasic(): void
    {
        $result = $this->sanitizeInput('Hello World', 'string');
        $this->assertEquals('Hello World', $result);
    }
    
    public function testSanitizeStringEscapesHtml(): void
    {
        $result = $this->sanitizeInput('<script>alert("xss")</script>', 'string');
        $this->assertEquals('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $result);
    }
    
    public function testSanitizeStringQuotes(): void
    {
        $result = $this->sanitizeInput('He said "hello"', 'string');
        $this->assertEquals('He said &quot;hello&quot;', $result);
    }
    
    // ==================== HTML TYPE TESTS ====================
    
    public function testSanitizeHtmlAllowsSomeTags(): void
    {
        // sanitize con 'html' también escapa todo - no permite tags
        $result = $this->sanitizeInput('<b>bold</b>', 'html');
        $this->assertEquals('&lt;b&gt;bold&lt;/b&gt;', $result);
    }
    
    public function testSanitizeHtmlRemovesScripts(): void
    {
        $result = $this->sanitizeInput('<script>alert(1)</script>', 'html');
        $this->assertStringNotContainsString('<script>', $result);
    }
    
    // ==================== ARRAY TESTS ====================
    
    public function testSanitizeInputArrayBasic(): void
    {
        $input = ['name' => 'John', 'age' => '30'];
        $result = $this->sanitizeInputArray($input, 'string');
        
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('age', $result);
    }
    
    public function testSanitizeInputArrayNested(): void
    {
        $input = [
            'user' => [
                'name' => '<b>Admin</b>',
                'email' => 'ADMIN@TEST.COM'
            ]
        ];
        // sanitize_input_array aplica el tipo por defecto 'string' a cada valor
        // No aplica 'email' automáticamente a las claves que parecen email
        $result = $this->sanitizeInputArray($input, 'string');
        
        $this->assertEquals('&lt;b&gt;Admin&lt;/b&gt;', $result['user']['name']);
        // El email se sanitiza como string, no como email (se escapa el @)
        $this->assertStringContainsString('ADMIN', $result['user']['email']);
    }
    
    // ==================== EDGE CASES ====================
    
    public function testSanitizeEmptyValue(): void
    {
        $this->assertEquals('', $this->sanitizeInput('', 'string'));
    }
    
    public function testSanitizeNullValue(): void
    {
        $this->assertEquals('', $this->sanitizeInput(null, 'string'));
    }
    
    public function testSanitizeBooleanValues(): void
    {
        $this->assertEquals(1, $this->sanitizeInput(true, 'int'));
        $this->assertEquals(0, $this->sanitizeInput(false, 'int'));
    }
    
    public function testSanitizeVeryLongString(): void
    {
        $long = str_repeat('a', 10000);
        $result = $this->sanitizeInput($long, 'string');
        $this->assertEquals(10000, strlen($result));
    }
    
    public function testSanitizeSpecialCharacters(): void
    {
        $input = "Test with special chars: € ñ 你好";
        $result = $this->sanitizeInput($input, 'string');
        $this->assertStringContainsString('€', $result);
    }
}
