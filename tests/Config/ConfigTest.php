<?php

declare(strict_types=1);

namespace App\Tests\Config;

use App\Config\Config;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private Config $config;

    protected function setUp(): void
    {
        $this->config = new Config([
            'string_key' => 'test_value',
            'int_key' => 42,
            'bool_key' => true,
            'bool_string_true' => 'true',
            'bool_string_false' => 'false',
            'bool_string_1' => '1',
            'bool_string_0' => '0',
            'bool_string_yes' => 'yes',
            'bool_string_no' => 'no',
            'bool_string_on' => 'on',
            'bool_string_off' => 'off',
            'array_key' => ['item1', 'item2', 'item3'],
            'app.env' => 'testing',
        ]);
    }

    public function testGet(): void
    {
        $this->assertEquals('test_value', $this->config->get('string_key'));
        $this->assertEquals(42, $this->config->get('int_key'));
        $this->assertTrue($this->config->get('bool_key'));
        $this->assertNull($this->config->get('non_existent'));
        $this->assertEquals('default', $this->config->get('non_existent', 'default'));
    }

    public function testString(): void
    {
        $this->assertEquals('test_value', $this->config->string('string_key'));
        $this->assertEquals('42', $this->config->string('int_key'));
        $this->assertEquals('1', $this->config->string('bool_key'));
        $this->assertEquals('', $this->config->string('non_existent'));
        $this->assertEquals('default', $this->config->string('non_existent', 'default'));
    }

    public function testInt(): void
    {
        $this->assertEquals(42, $this->config->int('int_key'));
        $this->assertEquals(0, $this->config->int('string_key'));
        $this->assertEquals(1, $this->config->int('bool_key'));
        $this->assertEquals(0, $this->config->int('non_existent'));
        $this->assertEquals(100, $this->config->int('non_existent', 100));
    }

    public function testBool(): void
    {
        $this->assertTrue($this->config->bool('bool_key'));
        $this->assertTrue($this->config->bool('bool_string_true'));
        $this->assertTrue($this->config->bool('bool_string_1'));
        $this->assertTrue($this->config->bool('bool_string_yes'));
        $this->assertTrue($this->config->bool('bool_string_on'));
        
        $this->assertFalse($this->config->bool('bool_string_false'));
        $this->assertFalse($this->config->bool('bool_string_0'));
        $this->assertFalse($this->config->bool('bool_string_no'));
        $this->assertFalse($this->config->bool('bool_string_off'));
        
        $this->assertFalse($this->config->bool('non_existent'));
        $this->assertTrue($this->config->bool('non_existent', true));
    }

    public function testArray(): void
    {
        $expected = ['item1', 'item2', 'item3'];
        $this->assertEquals($expected, $this->config->array('array_key'));
        $this->assertEquals([], $this->config->array('non_existent'));
        $this->assertEquals(['default'], $this->config->array('non_existent', ['default']));
        
        // Non-array values should return default
        $this->assertEquals([], $this->config->array('string_key'));
        $this->assertEquals(['fallback'], $this->config->array('string_key', ['fallback']));
    }

    public function testIsDevelopment(): void
    {
        $devConfig = new Config(['app.env' => 'development']);
        $prodConfig = new Config(['app.env' => 'production']);
        $testConfig = new Config(['app.env' => 'testing']);
        
        $this->assertTrue($devConfig->isDevelopment());
        $this->assertFalse($prodConfig->isDevelopment());
        $this->assertFalse($testConfig->isDevelopment());
        $this->assertFalse($this->config->isDevelopment()); // testing env
    }

    public function testFromEnvCreatesConfigWithEnvironmentVariables(): void
    {
        // Set up environment variables
        $_ENV['APP_ENV'] = 'test_environment';
        $_ENV['ENABLE_OUTPUT'] = 'true';
        $_ENV['SERVER_HOST'] = '127.0.0.1';
        $_ENV['SERVER_PORT'] = '8080';
        $_ENV['DEFAULT_SERVERS'] = 'server1,server2,server3';
        
        $config = Config::fromEnv();
        
        $this->assertEquals('test_environment', $config->string('app.env'));
        $this->assertTrue($config->bool('logging.enabled'));
        $this->assertEquals('127.0.0.1', $config->string('server.host'));
        $this->assertEquals(8080, $config->int('server.port'));
        $this->assertEquals(['server1', 'server2', 'server3'], $config->array('backend.servers'));
        $this->assertTrue($config->bool('server.reload_async'));
        $this->assertEquals(60, $config->int('server.max_wait_time'));
        
        // Clean up
        unset($_ENV['APP_ENV'], $_ENV['ENABLE_OUTPUT'], $_ENV['SERVER_HOST'], 
              $_ENV['SERVER_PORT'], $_ENV['DEFAULT_SERVERS']);
    }

    public function testFromEnvWithDefaults(): void
    {
        // Clear any existing env vars that might affect the test
        $originalEnv = $_ENV;
        $_ENV = [];
        
        $config = Config::fromEnv();
        
        $this->assertEquals('production', $config->string('app.env'));
        $this->assertTrue($config->bool('logging.enabled')); // default 'true' string evaluates to true
        $this->assertEquals('0.0.0.0', $config->string('server.host'));
        $this->assertEquals(9501, $config->int('server.port'));
        $this->assertEquals([''], $config->array('backend.servers')); // empty string splits to ['']
        
        // Restore original environment
        $_ENV = $originalEnv;
    }

    public function testBooleanParsing(): void
    {
        $truthy = ['1', 'true', 'TRUE', 'True', 'yes', 'YES', 'Yes', 'on', 'ON', 'On'];
        $falsy = ['0', 'false', 'FALSE', 'False', 'no', 'NO', 'No', 'off', 'OFF', 'Off', '', 'invalid'];
        
        foreach ($truthy as $value) {
            $config = new Config(['test' => $value]);
            $this->assertTrue($config->bool('test'), "Value '$value' should be true");
        }
        
        foreach ($falsy as $value) {
            $config = new Config(['test' => $value]);
            $this->assertFalse($config->bool('test'), "Value '$value' should be false");
        }
    }

    public function testBoolWithActualBooleanValues(): void
    {
        $config = new Config([
            'true_bool' => true,
            'false_bool' => false,
        ]);
        
        $this->assertTrue($config->bool('true_bool'));
        $this->assertFalse($config->bool('false_bool'));
    }
}