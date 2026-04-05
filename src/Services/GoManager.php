<?php

namespace Mpge\Govel\Services;

use Mpge\Govel\Contracts\Driver;
use Mpge\Govel\Contracts\Task;
use Mpge\Govel\Drivers\DistributedDriver;
use Mpge\Govel\Drivers\GrpcDriver;
use Mpge\Govel\Drivers\ProcessDriver;
use Mpge\Govel\DTO\Result;
use Mpge\Govel\Queue\PendingGovelDispatch;
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
     * Dispatch a Go task asynchronously (fire-and-forget via process).
     */
    public function dispatch(string|Task $task, array $payload = []): void
    {
        $this->driver()->dispatch(
            $this->resolveTask($task),
            $payload,
        );
    }

    /**
     * Dispatch a Go task onto a Laravel queue.
     */
    public function queue(string|Task $task, array $payload = []): PendingGovelDispatch
    {
        $taskClass = $task instanceof Task ? $task::class : $task;

        $pending = new PendingGovelDispatch($taskClass, $payload);

        $config = $this->container['config']['govel.queue'] ?? [];

        if ($config['connection'] ?? null) {
            $pending->onConnection($config['connection']);
        }

        if ($config['queue'] ?? null) {
            $pending->onQueue($config['queue']);
        }

        return $pending;
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

    /**
     * Resolve a task class string to a Task instance.
     */
    public function resolve(string|Task $task): Task
    {
        return $this->resolveTask($task);
    }

    protected function createDriver(string $name): Driver
    {
        return match ($name) {
            'process' => $this->createProcessDriver(),
            'grpc' => $this->createGrpcDriver(),
            'distributed' => $this->createDistributedDriver(),
            default => throw new InvalidArgumentException("Unsupported Govel driver [{$name}]."),
        };
    }

    protected function createProcessDriver(): ProcessDriver
    {
        $process = $this->container['config']['govel.process'] ?? [];

        return new ProcessDriver(
            binPath: $this->container['config']['govel.bin_path'] ?? base_path('bin'),
            timeout: (int) ($this->container['config']['govel.timeout'] ?? 30),
            maxPayloadSize: (int) ($this->container['config']['govel.max_payload_size'] ?? 0),
            envPassthrough: $process['env'] ?? [],
            cwd: $process['cwd'] ?? null,
            memoryLimit: (int) ($process['memory_limit'] ?? 0),
        );
    }

    protected function createGrpcDriver(): GrpcDriver
    {
        $config = $this->container['config']['govel.grpc'] ?? [];

        return new GrpcDriver(
            host: $config['host'] ?? '127.0.0.1',
            port: (int) ($config['port'] ?? 9800),
            timeout: (int) ($this->container['config']['govel.timeout'] ?? 30),
            tls: (bool) ($config['tls'] ?? false),
            connectTimeout: (int) ($config['connect_timeout'] ?? 5),
            retries: (int) ($config['retries'] ?? 0),
            retryDelay: (int) ($config['retry_delay'] ?? 100),
            maxPayloadSize: (int) ($this->container['config']['govel.max_payload_size'] ?? 0),
        );
    }

    protected function createDistributedDriver(): DistributedDriver
    {
        $config = $this->container['config']['govel.distributed'] ?? [];

        $nodes = $config['nodes'] ?? [];

        if (empty($nodes)) {
            throw new InvalidArgumentException(
                'Distributed driver requires at least one node in config govel.distributed.nodes'
            );
        }

        return new DistributedDriver(
            nodeConfigs: $nodes,
            timeout: (int) ($this->container['config']['govel.timeout'] ?? 30),
            strategy: $config['strategy'] ?? 'round-robin',
            tls: (bool) ($config['tls'] ?? false),
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
