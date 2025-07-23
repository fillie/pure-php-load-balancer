<?php

declare(strict_types=1);

namespace App\Tests\Server;

use App\Server\ServerEventHandler;
use OpenSwoole\Http\Server;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ServerEventHandlerTest extends TestCase
{
    private LoggerInterface $logger;
    private ServerEventHandler $eventHandler;
    private Server $server;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->eventHandler = new ServerEventHandler($this->logger);
        $this->server = $this->createMock(Server::class);
    }

    public function testHandleShutdown(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Server shutdown initiated');
            
        $this->eventHandler->handleShutdown($this->server);
    }

    public function testHandleWorkerStop(): void
    {
        $workerId = 5;
        
        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Worker stopping', ['worker_id' => $workerId]);
            
        $this->eventHandler->handleWorkerStop($this->server, $workerId);
    }

    public function testHandleWorkerStopWithDifferentWorkerIds(): void
    {
        $workerIds = [1, 3, 7, 12];
        
        $this->logger
            ->expects($this->exactly(count($workerIds)))
            ->method('info')
            ->with('Worker stopping', $this->callback(function($context) {
                return isset($context['worker_id']) && is_int($context['worker_id']);
            }));
        
        foreach ($workerIds as $workerId) {
            $this->eventHandler->handleWorkerStop($this->server, $workerId);
        }
    }

    public function testHandleManagerStop(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Manager stopping');
            
        $this->eventHandler->handleManagerStop($this->server);
    }

    public function testHandleWorkerError(): void
    {
        $workerId = 2;
        $workerPid = 12345;
        $exitCode = 1;
        $signal = 9;
        
        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Worker error', [
                'worker_id' => $workerId,
                'worker_pid' => $workerPid,
                'exit_code' => $exitCode,
                'signal' => $signal
            ]);
            
        $this->eventHandler->handleWorkerError($this->server, $workerId, $workerPid, $exitCode, $signal);
    }

    public function testHandleWorkerErrorWithDifferentParameters(): void
    {
        $testCases = [
            [1, 1001, 0, 15],
            [3, 1003, 2, 2],
            [5, 1005, 255, 9],
            [10, 1010, 127, 6]
        ];
        
        $this->logger
            ->expects($this->exactly(count($testCases)))
            ->method('error')
            ->with('Worker error', $this->callback(function($context) {
                return isset($context['worker_id']) && 
                       isset($context['worker_pid']) && 
                       isset($context['exit_code']) && 
                       isset($context['signal']);
            }));
        
        foreach ($testCases as [$workerId, $workerPid, $exitCode, $signal]) {
            $this->eventHandler->handleWorkerError($this->server, $workerId, $workerPid, $exitCode, $signal);
        }
    }

    public function testAllMethodsAcceptServerParameter(): void
    {
        // Test that all methods accept the Server parameter without issues
        // This verifies the correct method signatures
        
        $this->logger->expects($this->exactly(3))->method('info');
        $this->logger->expects($this->once())->method('error');
        
        $this->eventHandler->handleShutdown($this->server);
        $this->eventHandler->handleWorkerStop($this->server, 1);
        $this->eventHandler->handleManagerStop($this->server);
        $this->eventHandler->handleWorkerError($this->server, 1, 1000, 0, 15);
    }

    public function testMethodsIgnoreServerParameter(): void
    {
        // Verify that the Server parameter is accepted but not used
        // (as expected based on the IDE warnings about unused parameters)
        
        $server1 = $this->createMock(Server::class);
        $server2 = $this->createMock(Server::class);
        
        $this->logger->expects($this->exactly(2))->method('info')->with('Server shutdown initiated');
        
        // Same behavior regardless of which server instance is passed
        $this->eventHandler->handleShutdown($server1);
        $this->eventHandler->handleShutdown($server2);
    }

    public function testLoggerIsUsedForAllEvents(): void
    {
        // Verify that the logger is the only external dependency used
        
        $this->logger
            ->expects($this->exactly(3))
            ->method('info');
            
        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Worker error', [
                'worker_id' => 3,
                'worker_pid' => 1234,
                'exit_code' => 1,
                'signal' => 9
            ]);
        
        $this->eventHandler->handleShutdown($this->server);
        $this->eventHandler->handleWorkerStop($this->server, 2);
        $this->eventHandler->handleManagerStop($this->server);
        $this->eventHandler->handleWorkerError($this->server, 3, 1234, 1, 9);
    }

    public function testWorkerErrorWithZeroValues(): void
    {
        // Test edge case with zero values
        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Worker error', [
                'worker_id' => 0,
                'worker_pid' => 0,
                'exit_code' => 0,
                'signal' => 0
            ]);
            
        $this->eventHandler->handleWorkerError($this->server, 0, 0, 0, 0);
    }

    public function testWorkerErrorWithNegativeValues(): void
    {
        // Test edge case with negative values (though unlikely in practice)
        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Worker error', [
                'worker_id' => -1,
                'worker_pid' => -100,
                'exit_code' => -1,
                'signal' => -5
            ]);
            
        $this->eventHandler->handleWorkerError($this->server, -1, -100, -1, -5);
    }
}