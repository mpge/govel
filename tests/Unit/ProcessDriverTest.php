<?php

namespace Mpge\Govel\Tests\Unit;

use Mpge\Govel\Contracts\Task;
use Mpge\Govel\Drivers\ProcessDriver;
use Mpge\Govel\DTO\Result;
use Mpge\Govel\Exceptions\BinaryNotFoundException;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProcessDriverTest extends TestCase
{
    private function binPath(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'bin';
    }

    private function makeTask(string $name = 'process-image'): Task
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
    public function run_executes_binary_and_returns_successful_result(): void
    {
        $driver = new ProcessDriver(
            binPath: $this->binPath(),
            timeout: 30,
        );

        $task = $this->makeTask('process-image');
        $result = $driver->run($task, ['path' => 'test.jpg']);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertTrue($result->success);
        $this->assertIsArray($result->output);
        $this->assertArrayHasKey('status', $result->output);
        $this->assertSame('processed', $result->output['status']);
        $this->assertNull($result->error);
        $this->assertGreaterThan(0, $result->duration);
    }

    #[Test]
    public function run_returns_failure_result_when_process_exits_non_zero(): void
    {
        $driver = new ProcessDriver(
            binPath: $this->binPath(),
            timeout: 30,
        );

        $task = $this->makeTask('process-image');

        // The binary requires a "path" field; omitting it causes exit code 1.
        $result = $driver->run($task, ['action' => 'ping']);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertFalse($result->success);
        $this->assertNotNull($result->error);
    }

    #[Test]
    public function run_throws_binary_not_found_exception_when_binary_missing(): void
    {
        $driver = new ProcessDriver(
            binPath: $this->binPath(),
            timeout: 30,
        );

        $task = $this->makeTask('nonexistent-binary');

        $this->expectException(BinaryNotFoundException::class);

        $driver->run($task, []);
    }

    #[Test]
    public function dispatch_completes_without_error(): void
    {
        // dispatch() uses Log::warning() internally, so we need a facade root.
        $app = new \Illuminate\Container\Container();
        $app->instance('log', new class {
            public function warning(string $message, array $context = []): void
            {
                // no-op for testing
            }
        });
        Facade::setFacadeApplication($app);

        $driver = new ProcessDriver(
            binPath: $this->binPath(),
            timeout: 30,
        );

        $task = $this->makeTask('process-image');

        // dispatch() is fire-and-forget; it should return void without throwing.
        $driver->dispatch($task, ['path' => 'test.jpg']);

        // If we reach here, dispatch completed without error.
        $this->assertTrue(true);

        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
    }

    #[Test]
    public function dispatch_throws_binary_not_found_exception_for_missing_binary(): void
    {
        $driver = new ProcessDriver(
            binPath: $this->binPath(),
            timeout: 30,
        );

        $task = $this->makeTask('nonexistent-binary');

        $this->expectException(BinaryNotFoundException::class);

        $driver->dispatch($task, []);
    }
}
