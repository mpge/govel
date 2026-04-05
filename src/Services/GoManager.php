<?php

namespace Mpge\Govel\Services;

use Mpge\Govel\Contracts\Driver;
use Mpge\Govel\Contracts\Task;
use Mpge\Govel\Drivers\ProcessDriver;
use Mpge\Govel\DTO\Result;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class GoManager
{
    /** @var array<string, Driver> */
    protected array $drivers = [];

    public function __construct(
        protected Container $container,
    ) {}

    /**
     * Execute a Go task synchronously.
     */
    public function run(string|Task $task, array $payload = []): Result
    {
        return $this->driver()->run(
            $this->resolveTask($task),
            $payload,
        );
    }

    /**
     * Dispatch a Go task asynchronously (fire-and-forget).
     */
    public function dispatch(string|Task $task, array $payload = []): void
    {
        $this->driver()->dispatch(
            $this->resolveTask($task),
            $payload,
        );
    }

    /**
     * Get a driver instance by name.
     */
    public function driver(?string $name = null): Driver
    {
        $name ??= $this->getDefaultDriver();

        return $this->drivers[$name] ??= $this->createDriver($name);
    }

    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->container['config']['govel.driver'] ?? 'process';
    }

    /**
     * Register a custom driver.
     */
    public function extend(string $name, Driver $driver): static
    {
        $this->drivers[$name] = $driver;

        return $this;
    }

    protected function createDriver(string $name): Driver
    {
        return match ($name) {
            'process' => $this->createProcessDriver(),
            default => throw new InvalidArgumentException("Unsupported Govel driver [{$name}]."),
        };
    }

    protected function createProcessDriver(): ProcessDriver
    {
        return new ProcessDriver(
            binPath: $this->container['config']['govel.bin_path'] ?? base_path('bin'),
            timeout: (int) ($this->container['config']['govel.timeout'] ?? 30),
        );
    }

    protected function resolveTask(string|Task $task): Task
    {
        if ($task instanceof Task) {
            return $task;
        }

        return $this->container->make($task);
    }
}
