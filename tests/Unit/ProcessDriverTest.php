<?php

namespace Mpge\Govel\Tests\Unit;

use Mpge\Govel\Contracts\Task;
use Mpge\Govel\Drivers\ProcessDriver;
use Mpge\Govel\DTO\Result;
use Mpge\Govel\Exceptions\BinaryNotFoundException;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProcessDriverTest extends TestCase
{
    private function fixturesPath(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'fixtures';
    }

    private function hasEchoTask(): bool
    {
        $path = $this->fixturesPath() . DIRECTORY_SEPARATOR . 'echo-task';

        // On Windows, the shebang script won't run directly
        if (PHP_OS_FAMILY === 'Windows') {
            return false;
        }

        return file_exists($path) && is_executable($path);
    }

    private function makeTask(string $name = 'echo-task'): Task
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
        if (! $this->hasEchoTask()) {
            $this->markTestSkipped('echo-task fixture not available on this platform.');
        }

        $driver = new ProcessDriver(
            binPath: $this->fixturesPath(),
            timeout: 30,
        );

        $result = $driver->run($this->makeTask(), ['hello' => 'world']);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertTrue($result->success);
        $this->assertSame('ok', $result->output['status']);
        $this->assertSame(['hello' => 'world'], $result->output['echo']);
        $this->assertNull($result->error);
        $this->assertGreaterThan(0, $result->duration);
    }

    #[Test]
    public function run_returns_failure_result_when_process_exits_non_zero(): void
    {
        if (! $this->hasEchoTask()) {
            $this->markTestSkipped('echo-task fixture not available on this platform.');
        }

        $driver = new ProcessDriver(
            binPath: $this->fixturesPath(),
            timeout: 30,
        );

        $result = $driver->run($this->makeTask(), ['fail' => true]);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertFalse($result->success);
        $this->assertNotNull($result->error);
        $this->assertStringContainsString('forced failure', $result->error);
    }

    #[Test]
    public function run_throws_binary_not_found_exception_when_binary_missing(): void
    {
        $driver = new ProcessDriver(
            binPath: $this->fixturesPath(),
            timeout: 30,
        );

        $task = $this->makeTask('nonexistent-binary');

        $this->expectException(BinaryNotFoundException::class);

        $driver->run($task, []);
    }

    #[Test]
    public function dispatch_completes_without_error(): void
    {
        if (! $this->hasEchoTask()) {
            $this->markTestSkipped('echo-task fixture not available on this platform.');
        }

        $app = new \Illuminate\Container\Container();
        $app->instance('log', new class {
            public function warning(string $message, array $context = []): void {}
        });
        Facade::setFacadeApplication($app);

        $driver = new ProcessDriver(
            binPath: $this->fixturesPath(),
            timeout: 30,
        );

        $driver->dispatch($this->makeTask(), ['hello' => 'world']);

        $this->assertTrue(true);

        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
    }

    #[Test]
    public function dispatch_throws_binary_not_found_exception_for_missing_binary(): void
    {
        $driver = new ProcessDriver(
            binPath: $this->fixturesPath(),
            timeout: 30,
        );

        $task = $this->makeTask('nonexistent-binary');

        $this->expectException(BinaryNotFoundException::class);

        $driver->dispatch($task, []);
    }
}
