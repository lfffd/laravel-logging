<?php

namespace Superlog\Tests;

use PHPUnit\Framework\TestCase;
use Superlog\Processors\ModelQueryProcessor;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Connection;
use Superlog\Logger\StructuredLogger;

class ModelQueryProcessorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock application container
        $this->app = $this->createMock(\Illuminate\Contracts\Foundation\Application::class);
        $this->app->method('instance')->willReturn(null);
        $this->app->method('bound')->willReturn(true);
        $this->app->method('make')->willReturn([
            'model' => 'User',
            'verb' => 'select',
            'timestamp' => microtime(true)
        ]);
        
        // Set up global app function
        if (!function_exists('app')) {
            function app() {
                global $app;
                return $app;
            }
        }
        
        global $app;
        $app = $this->app;
    }
    
    public function test_format_sql_with_bindings()
    {
        $sql = "SELECT * FROM users WHERE id = ? AND status = ?";
        $bindings = [1, 'active'];
        
        $method = new \ReflectionMethod(ModelQueryProcessor::class, 'formatSqlWithBindings');
        $method->setAccessible(true);
        
        $formattedSql = $method->invoke(null, $sql, $bindings);
        
        $this->assertEquals("SELECT * FROM users WHERE id = 1 AND status = 'active'", $formattedSql);
    }
    
    public function test_format_sql_with_null_binding()
    {
        $sql = "SELECT * FROM users WHERE deleted_at IS ?";
        $bindings = [null];
        
        $method = new \ReflectionMethod(ModelQueryProcessor::class, 'formatSqlWithBindings');
        $method->setAccessible(true);
        
        $formattedSql = $method->invoke(null, $sql, $bindings);
        
        $this->assertEquals("SELECT * FROM users WHERE deleted_at IS NULL", $formattedSql);
    }
    
    public function test_format_sql_with_boolean_binding()
    {
        $sql = "SELECT * FROM users WHERE active = ?";
        $bindings = [true];
        
        $method = new \ReflectionMethod(ModelQueryProcessor::class, 'formatSqlWithBindings');
        $method->setAccessible(true);
        
        $formattedSql = $method->invoke(null, $sql, $bindings);
        
        $this->assertEquals("SELECT * FROM users WHERE active = TRUE", $formattedSql);
    }
}