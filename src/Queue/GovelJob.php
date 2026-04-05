<?php

namespace Mpge\Govel\Queue;

use Mpge\Govel\Contracts\Task;
use Mpge\Govel\DTO\Result;
use Mpge\Govel\Services\GoManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GovelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $taskClass,
        public readonly array $payload = [],
        public readonly ?string $onDriver = null,
    ) {}

    public function handle(GoManager $manager): void
    {
        if (!is_subclass_of($this->taskClass, \Mpge\Govel\Contracts\Task::class)) {
            $this->fail(new \RuntimeException("Invalid task class: {$this->taskClass}"));
            return;
        }

        $result = $this->onDriver
            ? $manager->driver($this->onDriver)->run($manager->resolve($this->taskClass), $this->payload)
            : $manager->run($this->taskClass, $this->payload);

        if (! $result->success) {
            Log::error("Govel queued task [{$this->taskClass}] failed: {$result->error}", [
                'task' => $this->taskClass,
                'payload' => $this->payload,
                'duration' => $result->duration,
            ]);

            $this->fail(new \RuntimeException($result->error ?? 'Go task failed'));

            return;
        }

        Log::debug("Govel queued task [{$this->taskClass}] completed in {$result->duration}ms", [
            'output' => $result->output,
        ]);
    }

    public function tags(): array
    {
        return ['govel', $this->taskClass];
    }
}
