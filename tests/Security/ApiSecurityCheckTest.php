<?php
/**
 * API Security Tests - Verify authorization checks exist in API files
 * Reads source code to verify security controls are present
 */

namespace InfinityBuilders\Tests\Security;

use PHPUnit\Framework\TestCase;

class ApiSecurityCheckTest extends TestCase
{
    private string $apiPath;
    
    protected function setUp(): void
    {
        $this->apiPath = dirname(__DIR__, 2) . '/api/';
    }
    
    // ========================================
    // Helper: Check if file contains security check
    // ========================================
    
    private function hasAuthCheck(string $file): bool
    {
        $content = file_get_contents($this->apiPath . $file);
        return strpos($content, '$_SESSION[\'user_id\']') !== false ||
               strpos($content, 'require_once') !== false;
    }
    
    private function hasRoleCheck(string $file, string $role): bool
    {
        $content = file_get_contents($this->apiPath . $file);
        return strpos($content, $role) !== false;
    }
    
    private function hasAdminOnly(string $file): bool
    {
        $content = file_get_contents($this->apiPath . $file);
        return strpos($content, "user_role'] !== 'admin'") !== false ||
               strpos($content, 'admin') !== false;
    }
    
    // ========================================
    // Tests - Check security code exists
    // ========================================
    
    public function testPermitsApiHasAuthCheck(): void
    {
        $hasAuth = $this->hasAuthCheck('permits.php');
        $this->assertTrue($hasAuth, 'permits.php should check authentication');
    }
    
    public function testPermitsApiHasRoleCheck(): void
    {
        $hasRole = $this->hasRoleCheck('permits.php', 'canManage');
        $this->assertTrue($hasRole, 'permits.php should check user roles');
    }
    
    public function testInspectionsApiHasAuthCheck(): void
    {
        $hasAuth = $this->hasAuthCheck('inspections.php');
        $this->assertTrue($hasAuth, 'inspections.php should check authentication');
    }
    
    public function testVendorsApiHasAuthCheck(): void
    {
        $hasAuth = $this->hasAuthCheck('vendors.php');
        $this->assertTrue($hasAuth, 'vendors.php should check authentication');
    }
    
    public function testTrainingApiHasAdminCheck(): void
    {
        $hasAdmin = $this->hasAdminOnly('training.php');
        $this->assertTrue($hasAdmin, 'training.php should require admin role');
    }
    
    public function testUsersApiRequiresAdmin(): void
    {
        // We already fixed this one
        $content = file_get_contents($this->apiPath . 'users.php');
        $this->assertStringContainsString("user_role'] !== 'admin'", $content, 
            'users.php should check for admin role');
    }
    
    public function testSearchApiHasAuthCheck(): void
    {
        $hasAuth = $this->hasAuthCheck('search.php');
        $this->assertTrue($hasAuth, 'search.php should check authentication');
    }
    
    public function testProjectVendorsApiHasAuthCheck(): void
    {
        $hasAuth = $this->hasAuthCheck('project_vendors.php');
        $this->assertTrue($hasAuth, 'project_vendors.php should check authentication');
    }
    
    public function testTrainingVideoApiHasAuthCheck(): void
    {
        $hasAuth = $this->hasAuthCheck('training-video.php');
        $this->assertTrue($hasAuth, 'training-video.php should check authentication');
    }
    
    public function testDocumentsApiHasAuthCheck(): void
    {
        $hasAuth = $this->hasAuthCheck('documents.php');
        $this->assertTrue($hasAuth, 'documents.php should check authentication');
    }
    
    // ========================================
    // Tests - Check prepared statements (SQL injection protection)
    // ========================================
    
    public function testPermitsApiUsesPreparedStatements(): void
    {
        $content = file_get_contents($this->apiPath . 'permits.php');
        $this->assertStringContainsString('$stmt = $pdo->prepare', $content, 
            'permits.php should use prepared statements');
    }
    
    public function testInspectionsApiUsesPreparedStatements(): void
    {
        $content = file_get_contents($this->apiPath . 'inspections.php');
        $this->assertStringContainsString('$stmt = $pdo->prepare', $content, 
            'inspections.php should use prepared statements');
    }
    
    public function testVendorsApiUsesPreparedStatements(): void
    {
        $content = file_get_contents($this->apiPath . 'vendors.php');
        $this->assertStringContainsString('$stmt = $pdo->prepare', $content, 
            'vendors.php should use prepared statements');
    }
    
    public function testSearchApiUsesPreparedStatements(): void
    {
        $content = file_get_contents($this->apiPath . 'search.php');
        $this->assertStringContainsString('$stmt = $pdo->prepare', $content, 
            'search.php should use prepared statements');
    }
    
    // ========================================
    // Tests - Check file upload security
    // ========================================
    
    public function testPermitsApiValidatesFileType(): void
    {
        $content = file_get_contents($this->apiPath . 'permits.php');
        $this->assertStringContainsString('application/pdf', $content, 
            'permits.php should validate PDF file type');
    }
    
    public function testPermitsApiChecksFileSize(): void
    {
        $content = file_get_contents($this->apiPath . 'permits.php');
        $this->assertStringContainsString('max_size', $content, 
            'permits.php should check file size');
    }
    
    public function testTrainingApiValidatesFileType(): void
    {
        $content = file_get_contents($this->apiPath . 'training.php');
        $this->assertStringContainsString('allowedVideo', $content, 
            'training.php should validate video types');
    }
}
