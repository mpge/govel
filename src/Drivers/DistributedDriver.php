<?php

namespace Mpge\Govel\Drivers;

use Mpge\Govel\Concerns\LogsMessages;
use Mpge\Govel\Contracts\Driver;
use Mpge\Govel\Contracts\Task;
use Mpge\Govel\DTO\Result;

/**
 * Distributes Go tasks across multiple worker nodes.
 *
 * Supports round-robin and least-connections load balancing.
 * Each node is a Govel gRPC server (HTTP/JSON bridge).
 * Includes health checking and automatic failover.
 */
class DistributedDriver implements Driver
{
    use LogsMessages;

    /** @var array<int, GrpcDriver> */
    protected array $nodes = [];

    /** @var array<int, bool> */
    protected array $healthy = [];

    /** @var array<int, int> */
    protected array $connections = [];

    protected int $roundRobinIndex = 0;

    /**
     * @param array<int, array{host: string, port: int}> $nodeConfigs
     */
    public function __construct(
        protected array $nodeConfigs,
        protected int $timeout,
        protected string $strategy = 'round-robin',
        protected bool $tls = false,
        protected int $healthCheckInterval = 30,
    ) {
        foreach ($nodeConfigs as $i => $config) {
            $this->nodes[$i] = new GrpcDriver(
                host: $config['host'],
                port: $config['port'],
                timeout: $timeout,
                tls: $tls,
            );
            $this->healthy[$i] = true;
            $this->connections[$i] = 0;
        }
    }

    public function run(Task $task, array $payload = []): Result
    {
        $attempts = count($this->nodes);

        for ($i = 0; $i < $attempts; $i++) {
            $nodeIndex = $this->selectNode();

            if ($nodeIndex === null) {
                return Result::failure('No healthy Govel worker nodes available', 0);
            }

            $this->connections[$nodeIndex]++;

            try {
                $result = $this->nodes[$nodeIndex]->run($task, $payload);
                $this->connections[$nodeIndex]--;

                // Connection-level failure — mark node unhealthy and try next
                if (! $result->success && $this->isConnectionError($result->error)) {
                    $this->markUnhealthy($nodeIndex);
                    $this->log("Govel node {$nodeIndex} failed, trying next: {$result->error}");

                    continue;
                }

                return $result;
            } catch (\Throwable $e) {
                $this->connections[$nodeIndex]--;
                $this->markUnhealthy($nodeIndex);

                $this->log("Govel node {$nodeIndex} failed, trying next: {$e->getMessage()}");

                continue;
            }
        }

        return Result::failure('All Govel worker nodes failed', 0);
    }

    public function dispatch(Task $task, array $payload = []): void
    {
        $attempts = count($this->nodes);

        for ($i = 0; $i < $attempts; $i++) {
            $nodeIndex = $this->selectNode();

            if ($nodeIndex === null) {
                $this->log('No healthy Govel worker nodes available for dispatch');

                return;
            }

            try {
                $this->nodes[$nodeIndex]->dispatch($task, $payload);

                return;
            } catch (\Throwable $e) {
                $this->markUnhealthy($nodeIndex);
                $this->log("Govel node {$nodeIndex} dispatch failed, trying next: {$e->getMessage()}");

                continue;
            }
        }

        $this->log('All Govel worker nodes failed for dispatch');
    }

    /**
     * Get the health status of all nodes.
     *
     * @return array<int, array{host: string, port: int, healthy: bool, connections: int}>
     */
    public function nodeStatus(): array
    {
        $status = [];

        foreach ($this->nodeConfigs as $i => $config) {
            $status[$i] = [
                'host' => $config['host'],
                'port' => $config['port'],
                'healthy' => $this->healthy[$i],
                'connections' => $this->connections[$i],
            ];
        }

        return $status;
    }

    /**
     * Force a health check on all nodes.
     */
    public function checkHealth(): void
    {
        foreach ($this->nodes as $i => $node) {
            try {
                $task = new class implements Task {
                    public function name(): string
                    {
                        return '__health';
                    }
                };

                $result = $node->run($task);
                $this->healthy[$i] = $result->success;
            } catch (\Throwable) {
                $this->healthy[$i] = false;
            }
        }
    }

    /**
     * Mark all nodes as healthy (reset after recovery).
     */
    public function resetHealth(): void
    {
        foreach ($this->healthy as $i => $status) {
            $this->healthy[$i] = true;
        }
    }

    protected function selectNode(): ?int
    {
        $healthyNodes = array_keys(array_filter($this->healthy));

        if (empty($healthyNodes)) {
            return null;
        }

        return match ($this->strategy) {
            'least-connections' => $this->selectLeastConnections($healthyNodes),
            default => $this->selectRoundRobin($healthyNodes),
        };
    }

    protected function selectRoundRobin(array $healthyNodes): int
    {
        $index = $healthyNodes[$this->roundRobinIndex % count($healthyNodes)];
        $this->roundRobinIndex++;

        return $index;
    }

    protected function selectLeastConnections(array $healthyNodes): int
    {
        $min = PHP_INT_MAX;
        $selected = $healthyNodes[0];

        foreach ($healthyNodes as $i) {
            if ($this->connections[$i] < $min) {
                $min = $this->connections[$i];
                $selected = $i;
            }
        }

        return $selected;
    }

    protected function isConnectionError(?string $error): bool
    {
        if ($error === null) {
            return false;
        }

        return str_contains($error, 'Failed to connect')
            || str_contains($error, 'Connection refused')
            || str_contains($error, 'timed out');
    }

    protected function markUnhealthy(int $index): void
    {
        $this->healthy[$index] = false;

        $this->log("Govel node {$index} ({$this->nodeConfigs[$index]['host']}:{$this->nodeConfigs[$index]['port']}) marked unhealthy");
    }

}
