<?php

namespace Mpge\Govel\Queue;

use Illuminate\Foundation\Bus\PendingDispatch;

class PendingGovelDispatch
{
    protected bool $dispatched = false;
    protected ?string $queue = null;
    protected ?string $connection = null;
    protected ?int $delay = null;

    public function __construct(
        protected string $taskClass,
        protected array $payload = [],
        protected ?string $govelDriver = null,
    ) {}

    public function onQueue(string $queue): static
    {
        $this->queue = $queue;

        return $this;
    }

    public function onConnection(string $connection): static
    {
        $this->connection = $connection;

        return $this;
    }

    public function delay(int $seconds): static
    {
        $this->delay = $seconds;

        return $this;
    }

    public function via(string $govelDriver): static
    {
        $this->govelDriver = $govelDriver;

        return $this;
    }

    public function dispatch(): PendingDispatch
    {
        $job = new GovelJob($this->taskClass, $this->payload, $this->govelDriver);

        if ($this->queue) {
            $job->onQueue($this->queue);
        }

        if ($this->connection) {
            $job->onConnection($this->connection);
        }

        if ($this->delay) {
            $job->delay($this->delay);
        }

        $this->dispatched = true;

        return dispatch($job);
    }

    public function __destruct()
    {
        if (! $this->dispatched) {
            $this->dispatch();
        }
    }
}
