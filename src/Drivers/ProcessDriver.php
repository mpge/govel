<?php

namespace Mpge\Govel\Drivers;

use Mpge\Govel\Contracts\Driver;
use Mpge\Govel\Contracts\Task;
use Mpge\Govel\DTO\Result;
use Mpge\Govel\Exceptions\BinaryNotFoundException;
use Mpge\Govel\Exceptions\TaskExecutionException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class ProcessDriver implements Driver
{
    public function __construct(
        protected string $binPath,
        protected int $timeout,
        protected int $maxPayloadSize = 0,
        protected array $envPassthrough = [],
        protected ?string $cwd = null,
        protected int $memoryLimit = 0,
    ) {}

    public function run(Task $task, array $payload = []): Result
    {
        $binaryPath = $this->resolveBinaryPath($task);
        $this->ensureBinaryExists($task->name(), $binaryPath);

        $input = json_encode($payload, JSON_THROW_ON_ERROR);
        $this->validatePayloadSize($input, $task->name());

        $start = hrtime(true);

        try {
            $process = $this->createProcess($binaryPath, $input);
            $process->run();

            $duration = (hrtime(true) - $start) / 1e6;

            if (! $process->isSuccessful()) {
                return Result::failure(
                    error: trim($process->getErrorOutput()) ?: "Process exited with code {$process->getExitCode()}",
                    duration: $duration,
                );
            }

            return Result::fromOutput($process->getOutput(), $duration);
        } catch (ProcessTimedOutException) {
            throw TaskExecutionException::timeout($task->name(), $this->timeout);
        }
    }

    public function dispatch(Task $task, array $payload = []): void
    {
        $binaryPath = $this->resolveBinaryPath($task);
        $this->ensureBinaryExists($task->name(), $binaryPath);

        $input = json_encode($payload, JSON_THROW_ON_ERROR);
        $this->validatePayloadSize($input, $task->name());

        $process = $this->createProcess($binaryPath, $input, async: true);
        $process->start();
    }

    protected function createProcess(string $binaryPath, string $input, bool $async = false): Process
    {
        $command = [];

        // Apply memory limit on Linux via ulimit wrapper
        if ($this->memoryLimit > 0 && PHP_OS_FAMILY === 'Linux') {
            $limitKb = $this->memoryLimit * 1024;
            $command = ['sh', '-c', "ulimit -v {$limitKb} && " . escapeshellarg($binaryPath)];
        } else {
            $command = [$binaryPath];
        }

        $env = $this->buildEnvironment();

        $process = new Process(
            $command,
            $this->cwd,
            $env ?: null,
            $input,
            $async ? null : $this->timeout,
        );

        return $process;
    }

    protected function buildEnvironment(): ?array
    {
        if ($this->envPassthrough === ['*']) {
            return null; // explicitly inherit all
        }

        $env = [];
        foreach ($this->envPassthrough as $key) {
            $key = trim($key);
            if ($key !== '' && isset($_SERVER[$key])) {
                $env[$key] = $_SERVER[$key];
            } elseif ($key !== '' && ($val = getenv($key)) !== false) {
                $env[$key] = $val;
            }
        }

        return $env;
    }

    protected function validatePayloadSize(string $input, string $taskName): void
    {
        if ($this->maxPayloadSize > 0 && strlen($input) > $this->maxPayloadSize) {
            throw new \OverflowException(
                "Payload for task [{$taskName}] exceeds maximum size of {$this->maxPayloadSize} bytes."
            );
        }
    }

    protected function validateTaskName(string $name): void
    {
        if (! preg_match('/^[a-zA-Z0-9_-]{1,128}$/', $name)) {
            throw new \InvalidArgumentException(
                "Invalid task name [{$name}]. Task names must match /^[a-zA-Z0-9_-]{1,128}$/."
            );
        }
    }

    protected function resolveBinaryPath(Task $task): string
    {
        $this->validateTaskName($task->name());

        $binary = $task->name();

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
