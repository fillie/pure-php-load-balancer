<?php

declare(strict_types=1);

namespace App\Application\Http\Server;

use OpenSwoole\Http\Server;
use Psr\Log\LoggerInterface;

readonly class ServerEventHandler
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }
    
    public function handleShutdown(Server $server): void
    {
        $this->logger->info('Server shutdown initiated');
    }

    public function handleWorkerStop(Server $server, int $workerId): void
    {
        $this->logger->info('Worker stopping', ['worker_id' => $workerId]);
    }

    public function handleManagerStop(Server $server): void
    {
        $this->logger->info('Manager stopping');
    }

    public function handleWorkerError(Server $server, int $workerId, int $workerPid, int $exitCode, int $signal): void
    {
        $this->logger->error('Worker error', [
            'worker_id' => $workerId,
            'worker_pid' => $workerPid,
            'exit_code' => $exitCode,
            'signal' => $signal
        ]);
    }
}