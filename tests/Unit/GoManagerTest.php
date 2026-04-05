<?php

namespace Mpge\Govel\Tests\Unit;

use Mpge\Govel\Contracts\Driver;
use Mpge\Govel\Contracts\Task;
use Mpge\Govel\DTO\Result;
use Mpge\Govel\Services\GoManager;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GoManagerTest extends TestCase
{
    private function makeContainer(array $config = []): Container
    {
        $container = new Container();
        $container->instance('config', new Repository($config));

        return $container;
    }

    #[Test]
    public function it_delegates_run_to_the_driver(): void
    {
        $expected = new Result(true, ['done' => true], null, 5.0);

        $driver = $this->createMock(Driver::class);
        $driver->expects($this->once())
            ->method('run')
            ->willReturn($expected);

        $container = $this->makeContainer(['govel' => ['driver' => 'custom']]);

        $manager = new GoManager($container);
        $manager->extend('custom', $driver);

        $task = new class implements Task {
            public function name(): string
            {
                return 'test-task';
            }
        };

        $result = $manager->run($task, ['foo' => 'bar']);

        $this->assertTrue($result->success);
        $this->assertSame(['done' => true], $result->output);
    }

    #[Test]
    public function it_resolves_task_from_class_string(): void
    {
        $expected = new Result(true, [], null, 1.0);

        $driver = $this->createMock(Driver::class);
        $driver->expects($this->once())
            ->method('run')
            ->willReturn($expected);

        $task = new class implements Task {
            public function name(): string
            {
                return 'resolve-test';
            }
        };

        $container = $this->makeContainer(['govel' => ['driver' => 'custom']]);
        $container->instance($task::class, $task);

        $manager = new GoManager($container);
        $manager->extend('custom', $driver);

        $result = $manager->run($task::class);

        $this->assertTrue($result->success);
    }

    #[Test]
    public function it_allows_extending_with_custom_drivers(): void
    {
        $driver = $this->createMock(Driver::class);

        $container = $this->makeContainer(['govel' => ['driver' => 'process']]);

        $manager = new GoManager($container);
        $manager->extend('my-driver', $driver);

        $this->assertSame($driver, $manager->driver('my-driver'));
    }

    #[Test]
    public function dispatch_delegates_to_driver(): void
    {
        $driver = $this->createMock(Driver::class);
        $driver->expects($this->once())
            ->method('dispatch');

        $container = $this->makeContainer(['govel' => ['driver' => 'custom']]);

        $manager = new GoManager($container);
        $manager->extend('custom', $driver);

        $task = new class implements Task {
            public function name(): string
            {
                return 'dispatch-test';
            }
        };

        $manager->dispatch($task, ['key' => 'value']);
    }

    #[Test]
    public function get_default_driver_returns_config_value(): void
    {
        $container = $this->makeContainer(['govel' => ['driver' => 'grpc']]);
        $manager = new GoManager($container);

        $this->assertSame('grpc', $manager->getDefaultDriver());
    }

    #[Test]
    public function get_default_driver_falls_back_to_process(): void
    {
        $container = $this->makeContainer([]);
        $manager = new GoManager($container);

        $this->assertSame('process', $manager->getDefaultDriver());
    }

    #[Test]
    public function it_throws_invalid_argument_exception_for_unsupported_driver(): void
    {
        $container = $this->makeContainer(['govel' => ['driver' => 'foobar']]);
        $manager = new GoManager($container);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported Govel driver [foobar]');

        $manager->driver('foobar');
    }
}
