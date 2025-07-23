<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Logger;

use App\Infrastructure\Logger\ConsoleLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

class ConsoleLoggerTest extends TestCase
{
    private string $output = '';

    protected function setUp(): void
    {
        $this->output = '';
        // Capture output
        ob_start();
    }

    protected function tearDown(): void
    {
        if (ob_get_level() > 0) {
            $this->output = ob_get_clean();
        }
    }

    private function getOutput(): string
    {
        if (ob_get_level() > 0) {
            $this->output = ob_get_clean();
            ob_start(); // Restart output buffering for next test
        }
        return $this->output;
    }

    public function testLoggerOutputsToConsole(): void
    {
        $logger = new ConsoleLogger(true, LogLevel::DEBUG);
        $logger->info('Test message');
        
        $output = $this->getOutput();
        $this->assertStringContainsString('INFO: Test message', $output);
    }

    public function testDisabledLoggerDoesNotOutput(): void
    {
        $logger = new ConsoleLogger(false, LogLevel::DEBUG);
        $logger->info('Test message');
        
        $output = $this->getOutput();
        $this->assertEmpty($output);
    }

    public function testLogLevels(): void
    {
        $logger = new ConsoleLogger(true, LogLevel::DEBUG);
        
        $logger->emergency('Emergency message');
        $output1 = $this->getOutput();
        $this->assertStringContainsString('EMERGENCY: Emergency message', $output1);
        
        $logger->alert('Alert message');
        $output2 = $this->getOutput();
        $this->assertStringContainsString('ALERT: Alert message', $output2);
        
        $logger->critical('Critical message');
        $output3 = $this->getOutput();
        $this->assertStringContainsString('CRITICAL: Critical message', $output3);
        
        $logger->error('Error message');
        $output4 = $this->getOutput();
        $this->assertStringContainsString('ERROR: Error message', $output4);
        
        $logger->warning('Warning message');
        $output5 = $this->getOutput();
        $this->assertStringContainsString('WARNING: Warning message', $output5);
        
        $logger->notice('Notice message');
        $output6 = $this->getOutput();
        $this->assertStringContainsString('NOTICE: Notice message', $output6);
        
        $logger->info('Info message');
        $output7 = $this->getOutput();
        $this->assertStringContainsString('INFO: Info message', $output7);
        
        $logger->debug('Debug message');
        $output8 = $this->getOutput();
        $this->assertStringContainsString('DEBUG: Debug message', $output8);
    }

    public function testLogLevelFiltering(): void
    {
        $logger = new ConsoleLogger(true, LogLevel::ERROR);
        
        // These should be logged (ERROR level and above)
        $logger->emergency('Emergency');
        $output1 = $this->getOutput();
        $this->assertStringContainsString('EMERGENCY: Emergency', $output1);
        
        $logger->alert('Alert');
        $output2 = $this->getOutput();
        $this->assertStringContainsString('ALERT: Alert', $output2);
        
        $logger->critical('Critical');
        $output3 = $this->getOutput();
        $this->assertStringContainsString('CRITICAL: Critical', $output3);
        
        $logger->error('Error');
        $output4 = $this->getOutput();
        $this->assertStringContainsString('ERROR: Error', $output4);
        
        // These should NOT be logged (below ERROR level)
        $logger->warning('Warning');
        $output5 = $this->getOutput();
        $this->assertEmpty($output5);
        
        $logger->notice('Notice');
        $output6 = $this->getOutput();
        $this->assertEmpty($output6);
        
        $logger->info('Info');
        $output7 = $this->getOutput();
        $this->assertEmpty($output7);
        
        $logger->debug('Debug');
        $output8 = $this->getOutput();
        $this->assertEmpty($output8);
    }

    public function testContextInterpolation(): void
    {
        $logger = new ConsoleLogger(true, LogLevel::DEBUG);
        
        $logger->info('Hello {name}, you are {age} years old', [
            'name' => 'John',
            'age' => 25
        ]);
        
        $output = $this->getOutput();
        $this->assertStringContainsString('INFO: Hello John, you are 25 years old', $output);
    }

    public function testContextInterpolationWithDifferentTypes(): void
    {
        $logger = new ConsoleLogger(true, LogLevel::DEBUG);
        
        $logger->info('String: {str}, Int: {int}, Bool: {bool}, Array: {arr}, Object: {obj}', [
            'str' => 'test',
            'int' => 42,
            'bool' => true,
            'arr' => ['a', 'b'],
            'obj' => (object)['prop' => 'value']
        ]);
        
        $output = $this->getOutput();
        $this->assertStringContainsString('String: test', $output);
        $this->assertStringContainsString('Int: 42', $output);
        $this->assertStringContainsString('Bool: 1', $output);
        $this->assertStringContainsString('Array: ["a","b"]', $output);
        $this->assertStringContainsString('Object: {"prop":"value"}', $output);
    }

    public function testExceptionLogging(): void
    {
        $logger = new ConsoleLogger(true, LogLevel::DEBUG);
        $exception = new \RuntimeException('Test exception', 123);
        
        $logger->error('An error occurred', ['exception' => $exception]);
        
        $output = $this->getOutput();
        $this->assertStringContainsString('ERROR: An error occurred', $output);
        $this->assertStringContainsString('RuntimeException: Test exception', $output);
        $this->assertStringContainsString(__FILE__, $output); // Should contain filename
    }

    public function testExceptionWithStackTraceInDebugMode(): void
    {
        $logger = new ConsoleLogger(true, LogLevel::DEBUG);
        $exception = new \RuntimeException('Test exception');
        
        $logger->debug('Debug with exception', ['exception' => $exception]);
        
        $output = $this->getOutput();
        $this->assertStringContainsString('DEBUG: Debug with exception', $output);
        $this->assertStringContainsString('RuntimeException: Test exception', $output);
        $this->assertStringContainsString('Stack trace:', $output);
    }

    public function testExceptionWithoutStackTraceInNonDebugMode(): void
    {
        $logger = new ConsoleLogger(true, LogLevel::DEBUG);
        $exception = new \RuntimeException('Test exception');
        
        $logger->info('Info with exception', ['exception' => $exception]);
        
        $output = $this->getOutput();
        $this->assertStringContainsString('INFO: Info with exception', $output);
        $this->assertStringContainsString('RuntimeException: Test exception', $output);
        $this->assertStringNotContainsString('Stack trace:', $output);
    }

    public function testTimestampFormat(): void
    {
        $logger = new ConsoleLogger(true, LogLevel::DEBUG);
        
        $logger->info('Test message');
        
        $output = $this->getOutput();
        // Should match format [YYYY-MM-DD HH:MM:SS]
        $this->assertMatchesRegularExpression('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] INFO: Test message/', $output);
    }

    public function testStringableMessage(): void
    {
        $logger = new ConsoleLogger(true, LogLevel::DEBUG);
        
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'Stringable message';
            }
        };
        
        $logger->info($stringable);
        
        $output = $this->getOutput();
        $this->assertStringContainsString('INFO: Stringable message', $output);
    }

    public function testContextWithExceptionKeyDoesNotInterpolate(): void
    {
        $logger = new ConsoleLogger(true, LogLevel::DEBUG);
        $exception = new \RuntimeException('Test exception');
        
        $logger->info('Message with {exception} placeholder', ['exception' => $exception]);
        
        $output = $this->getOutput();
        // The {exception} placeholder should NOT be replaced because exception context is special
        $this->assertStringContainsString('Message with {exception} placeholder', $output);
        $this->assertStringContainsString('RuntimeException: Test exception', $output);
    }

    public function testDefaultConstructorValues(): void
    {
        $logger = new ConsoleLogger();
        
        $logger->info('Test message');
        $output = $this->getOutput();
        $this->assertStringContainsString('INFO: Test message', $output);
        
        // Debug should not show with default min level (INFO)
        $logger->debug('Debug message');
        $output = $this->getOutput();
        $this->assertEmpty($output);
    }

    public function testUnknownLogLevel(): void
    {
        $logger = new ConsoleLogger(true, 'unknown_level');
        
        $logger->info('Test message');
        $output = $this->getOutput();
        $this->assertStringContainsString('INFO: Test message', $output);
        
        // Unknown log level defaults to INFO level (6), so DEBUG (7) should not show
        $logger->debug('Debug message');
        $output = $this->getOutput();
        $this->assertEmpty($output);
    }
}