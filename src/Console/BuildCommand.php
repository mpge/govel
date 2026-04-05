<?php

namespace Mpge\Govel\Console;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class BuildCommand extends Command
{
    protected $signature = 'govel:build {name? : The worker name to build (builds all if omitted)}';

    protected $description = 'Compile Go workers into binaries';

    public function handle(): int
    {
        if (! $this->goIsAvailable()) {
            $this->error('The `go` binary was not found. Please install Go: https://go.dev/dl/');

            return self::FAILURE;
        }

        $name = $this->argument('name');
        $workersBase = base_path('bin/workers');

        if ($name && ! preg_match('/^[a-zA-Z0-9_-]{1,128}$/', $name)) {
            $this->error('Worker name must contain only alphanumeric characters, hyphens, and underscores.');

            return self::FAILURE;
        }

        if ($name) {
            $workers = ["{$workersBase}/{$name}"];

            if (! is_dir($workers[0]) || ! file_exists("{$workers[0]}/go.mod")) {
                $this->error("Worker [{$name}] not found at [{$workers[0]}].");

                return self::FAILURE;
            }
        } else {
            $workers = $this->discoverWorkers($workersBase);

            if (empty($workers)) {
                $this->info('No workers found in bin/workers/.');

                return self::SUCCESS;
            }
        }

        $failed = 0;

        foreach ($workers as $workerDir) {
            $workerName = basename($workerDir);
            $this->info("Building [{$workerName}]...");

            $ext = PHP_OS_FAMILY === 'Windows' ? '.exe' : '';
            $binDir = config('govel.bin_path', base_path('bin'));
            $outputPath = $binDir . DIRECTORY_SEPARATOR . "{$workerName}{$ext}";

            $process = new Process(['go', 'build', '-o', $outputPath, '.'], $workerDir);
            $process->setTimeout(120);
            $process->run();

            if ($process->isSuccessful()) {
                $this->info("  -> Built successfully: bin/{$workerName}{$ext}");
            } else {
                $this->error("  -> Failed to build [{$workerName}]:");
                $this->error($process->getErrorOutput());
                $failed++;
            }
        }

        if ($failed > 0) {
            $this->error("{$failed} worker(s) failed to build.");

            return self::FAILURE;
        }

        $this->info('All workers built successfully.');

        return self::SUCCESS;
    }

    /**
     * Discover all worker directories containing a go.mod file.
     *
     * @return string[]
     */
    protected function discoverWorkers(string $basePath): array
    {
        if (! is_dir($basePath)) {
            return [];
        }

        $workers = [];
        $dirs = glob("{$basePath}/*/go.mod");

        foreach ($dirs as $goMod) {
            $workers[] = dirname($goMod);
        }

        return $workers;
    }

    protected function goIsAvailable(): bool
    {
        $process = new Process(['go', 'version']);
        $process->setTimeout(10);

        try {
            $process->run();

            return $process->isSuccessful();
        } catch (\Throwable) {
            return false;
        }
    }
}
