<?php

namespace Govel\Govel\Drivers;

use Govel\Govel\Contracts\Driver;
use Govel\Govel\Contracts\Task;
use Govel\Govel\DTO\Result;
use Govel\Govel\Exceptions\BinaryNotFoundException;
use Govel\Govel\Exceptions\TaskExecutionException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class ProcessDriver implements Driver
{
    public function __construct(
        protected string $binPath,
        protected int $timeout,
    ) {}

    public function run(Task $task, array $payload = []): Result
    {
        $binaryPath = $this->resolveBinaryPath($task);
        $this->ensureBinaryExists($task->name(), $binaryPath);

        $input = json_encode($payload, JSON_THROW_ON_ERROR);
        $start = hrtime(true);

        try {
            $process = new Process([$binaryPath], null, null, $input, $this->timeout);
            $process->run();

            $duration = (hrtime(true) - $start) / 1e6; // nanoseconds → milliseconds

            if (! $process->isSuccessful()) {
                return Result::failure(
                    error: trim($process->getErrorOutput()) ?: "Process exited with code {$process->getExitCode()}",
                    duration: $duration,
                );
            }

            return Result::fromOutput($process->getOutput(), $duration);
        } catch (ProcessTimedOutException) {
            $duration = (hrtime(true) - $start) / 1e6;

            throw TaskExecutionException::timeout($task->name(), $this->timeout);
        }
    }

    public function dispatch(Task $task, array $payload = []): void
    {
        $binaryPath = $this->resolveBinaryPath($task);
        $this->ensureBinaryExists($task->name(), $binaryPath);

        $input = json_encode($payload, JSON_THROW_ON_ERROR);

        $process = new Process([$binaryPath], null, null, $input, null);
        $process->start();

        // Fire-and-forget: detach the process.
        // Optionally log when complete.
        $process->wait(function (string $type, string $buffer) use ($task) {
            if ($type === Process::ERR) {
                Log::warning("Govel async task [{$task->name()}] stderr: {$buffer}");
            }
        });
    }

    protected function resolveBinaryPath(Task $task): string
    {
        $binary = $task->name();

        // On Windows, append .exe if not present
        if (PHP_OS_FAMILY === 'Windows' && ! str_ends_with($binary, '.exe')) {
            $binary .= '.exe';
        }

        return rtrim($this->binPath, '/\\') . DIRECTORY_SEPARATOR . $binary;
    }

    protected function ensureBinaryExists(string $name, string $path): void
    {
        if (! file_exists($path)) {
            throw BinaryNotFoundException::forTask($name, $path);
        }
    }
}
