<?php

namespace Mpge\Govel\Tests\Unit;

use Mpge\Govel\Contracts\Task;
use Mpge\Govel\Drivers\GrpcDriver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GrpcDriverTest extends TestCase
{
    #[Test]
    public function it_returns_failure_when_server_unreachable(): void
    {
        $driver = new GrpcDriver('127.0.0.1', 19999, 2);

        $task = new class implements Task {
            public function name(): string
            {
                return 'test-task';
            }
        };

        $result = $driver->run($task, ['key' => 'value']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Failed to connect', $result->error);
    }

    #[Test]
    public function it_dispatches_without_throwing_on_failure(): void
    {
        $driver = new GrpcDriver('127.0.0.1', 19999, 2);

        $task = new class implements Task {
            public function name(): string
            {
                return 'test-task';
            }
        };

        // Should not throw — logs warning instead
        $driver->dispatch($task, ['key' => 'value']);

        $this->assertTrue(true);
    }
}
