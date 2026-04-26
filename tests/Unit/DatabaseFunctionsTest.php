<?php
/**
 * Unit Tests for Database Functions
 */

namespace InfinityBuilders\Tests\Unit;

use PHPUnit\Framework\TestCase;

class DatabaseFunctionsTest extends TestCase
{
    protected static $pdo;
    
    public static function setUpBeforeClass(): void
    {
        // Create test database connection
        self::$pdo = new \PDO(
            'mysql:host=localhost;dbname=infinity_builders;charset=utf8mb4',
            'root',
            '',
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
            ]
        );
    }
    
    protected function setUp(): void
    {
        // Create a clean test record for each test
        self::$pdo->exec("
            INSERT IGNORE INTO users (id, username, email, password, role) 
            VALUES (9999, 'test_user', 'test_db@test.com', 'hash', 'viewer')
        ");
    }
    
    protected function tearDown(): void
    {
        // Clean up test data
        self::$pdo->exec("DELETE FROM users WHERE id = 9999");
    }
    
    // ==================== BASIC QUERY TESTS ====================
    
    public function testDatabaseConnection(): void
    {
        $this->assertNotNull(self::$pdo);
        $this->assertInstanceOf(\PDO::class, self::$pdo);
    }
    
    public function testSimpleQuery(): void
    {
        $stmt = self::$pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([9999]);
        $result = $stmt->fetch();
        
        $this->assertNotFalse($result);
        $this->assertEquals('test_user', $result['username']);
    }
    
    public function testQueryWithMultipleResults(): void
    {
        $stmt = self::$pdo->query("SELECT * FROM users LIMIT 10");
        $results = $stmt->fetchAll();
        
        $this->assertIsArray($results);
    }
    
    // ==================== INSERT TESTS ====================
    
    public function testInsertReturnsLastInsertId(): void
    {
        $stmt = self::$pdo->prepare("
            INSERT INTO users (username, email, password, role) 
            VALUES (:username, :email, :password, :role)
        ");
        $stmt->execute([
            'username' => 'insert_test_' . time(),
            'email' => 'insert_' . time() . '@test.com',
            'password' => 'hash',
            'role' => 'viewer'
        ]);
        
        $lastId = self::$pdo->lastInsertId();
        $this->assertGreaterThan(0, $lastId);
        
        // Clean up
        self::$pdo->exec("DELETE FROM users WHERE id = " . $lastId);
    }
    
    public function testInsertWithTransaction(): void
    {
        self::$pdo->beginTransaction();
        
        try {
            $stmt = self::$pdo->prepare("
                INSERT INTO users (username, email, password, role) 
                VALUES (:username, :email, :password, :role)
            ");
            $stmt->execute([
                'username' => 'trans_test_' . time(),
                'email' => 'trans_' . time() . '@test.com',
                'password' => 'hash',
                'role' => 'viewer'
            ]);
            
            self::$pdo->commit();
            $this->assertTrue(true);
        } catch (\Exception $e) {
            self::$pdo->rollBack();
            throw $e;
        }
    }
    
    // ==================== UPDATE TESTS ====================
    
    public function testUpdateAffectedRows(): void
    {
        $stmt = self::$pdo->prepare("
            UPDATE users SET username = :username WHERE id = :id
        ");
        $stmt->execute([
            'username' => 'updated_user',
            'id' => 9999
        ]);
        
        $affected = $stmt->rowCount();
        $this->assertEquals(1, $affected);
    }
    
    // ==================== DELETE TESTS ====================
    
    public function testDeleteAffectedRows(): void
    {
        // First insert a record to delete
        self::$pdo->exec("
            INSERT INTO users (username, email, password, role) 
            VALUES ('to_delete', 'delete@test.com', 'hash', 'viewer')
        ");
        
        $stmt = self::$pdo->prepare("DELETE FROM users WHERE username = ?");
        $stmt->execute(['to_delete']);
        
        $affected = $stmt->rowCount();
        $this->assertEquals(1, $affected);
    }
    
    // ==================== ERROR HANDLING TESTS ====================
    
    public function testQueryWithInvalidTable(): void
    {
        $this->expectException(\PDOException::class);
        
        $stmt = self::$pdo->query("SELECT * FROM nonexistent_table");
    }
    
    public function testPreparedStatementWithMissingParam(): void
    {
        $this->expectException(\PDOException::class);
        
        $stmt = self::$pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([]); // Missing parameter
    }
    
    // ==================== TRANSACTION TESTS ====================
    
    public function testTransactionRollback(): void
    {
        $username = 'rollback_test_' . time();
        
        // Insert first
        self::$pdo->exec("
            INSERT INTO users (username, email, password, role) 
            VALUES ('$username', 'rollback@test.com', 'hash', 'viewer')
        ");
        
        // Begin transaction and rollback
        self::$pdo->beginTransaction();
        self::$pdo->exec("UPDATE users SET username = 'rolled_back' WHERE username = '$username'");
        self::$pdo->rollBack();
        
        // Verify original value still exists
        $stmt = self::$pdo->prepare("SELECT username FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $result = $stmt->fetch();
        
        $this->assertEquals($username, $result['username']);
        
        // Clean up
        self::$pdo->exec("DELETE FROM users WHERE username = '$username'");
    }
    
    // ==================== FETCH MODE TESTS ====================
    
    public function testFetchAssoc(): void
    {
        $stmt = self::$pdo->query("SELECT id, username FROM users LIMIT 1");
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('username', $result);
    }
    
    public function testFetchAll(): void
    {
        $stmt = self::$pdo->query("SELECT id, username FROM users LIMIT 5");
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $this->assertIsArray($results);
        $this->assertCount(5, $results);
    }
    
    // ==================== EDGE CASES ====================
    
    public function testEmptyResult(): void
    {
        $stmt = self::$pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([999999]);
        $result = $stmt->fetch();
        
        $this->assertFalse($result);
    }
    
    public function testQueryWithLike(): void
    {
        $stmt = self::$pdo->prepare("SELECT * FROM users WHERE username LIKE ?");
        $stmt->execute(['%test%']);
        $results = $stmt->fetchAll();
        
        $this->assertIsArray($results);
    }
    
    public function testQueryWithInClause(): void
    {
        $stmt = self::$pdo->prepare("SELECT * FROM users WHERE id IN (?, ?)");
        $stmt->execute([1, 9999]);
        $results = $stmt->fetchAll();
        
        $this->assertIsArray($results);
    }
}
