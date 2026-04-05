<?php

namespace Mpge\Govel\Tests\Unit;

use Mpge\Govel\Contracts\Task;
use Mpge\Govel\Drivers\DistributedDriver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DistributedDriverTest extends TestCase
{
    private function makeTask(string $name = 'test'): Task
    {
        return new class($name) implements Task {
            public function __construct(private string $taskName) {}

            public function name(): string
            {
                return $this->taskName;
            }
        };
    }

    #[Test]
    public function it_reports_node_status(): void
    {
        $driver = new DistributedDriver(
            nodeConfigs: [
                ['host' => '10.0.0.1', 'port' => 9800],
                ['host' => '10.0.0.2', 'port' => 9800],
            ],
            timeout: 5,
        );

        $status = $driver->nodeStatus();

        $this->assertCount(2, $status);
        $this->assertSame('10.0.0.1', $status[0]['host']);
        $this->assertTrue($status[0]['healthy']);
        $this->assertSame(0, $status[0]['connections']);
        $this->assertSame('10.0.0.2', $status[1]['host']);
    }

    #[Test]
    public function it_returns_failure_when_all_nodes_are_down(): void
    {
        $driver = new DistributedDriver(
            nodeConfigs: [
                ['host' => '127.0.0.1', 'port' => 19991],
                ['host' => '127.0.0.1', 'port' => 19992],
            ],
            timeout: 1,
        );

        $result = $driver->run($this->makeTask());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('All Govel worker nodes failed', $result->error);
    }

    #[Test]
    public function it_resets_health_for_all_nodes(): void
    {
        $driver = new DistributedDriver(
            nodeConfigs: [
                ['host' => '127.0.0.1', 'port' => 19991],
            ],
            timeout: 1,
        );

        // Trigger failure to mark unhealthy
        $driver->run($this->makeTask());

        $status = $driver->nodeStatus();
        $this->assertFalse($status[0]['healthy']);

        $driver->resetHealth();

        $status = $driver->nodeStatus();
        $this->assertTrue($status[0]['healthy']);
    }

    #[Test]
    public function it_supports_least_connections_strategy(): void
    {
        $driver = new DistributedDriver(
            nodeConfigs: [
                ['host' => '10.0.0.1', 'port' => 9800],
                ['host' => '10.0.0.2', 'port' => 9800],
            ],
            timeout: 5,
            strategy: 'least-connections',
        );

        $status = $driver->nodeStatus();
        $this->assertCount(2, $status);
    }
}
