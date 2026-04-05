<?php

namespace Mpge\Govel\Console;

use Illuminate\Console\Command;

class ListCommand extends Command
{
    protected $signature = 'govel:list';

    protected $description = 'List all available Govel tasks and their status';

    public function handle(): int
    {
        $binPath = config('govel.bin_path', base_path('bin'));
        $workersPath = base_path('bin/workers');

        $tasks = $this->collectTasks($binPath, $workersPath);

        if (empty($tasks)) {
            $this->info('No tasks found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Task Name', 'Binary Path', 'Status'],
            $tasks,
        );

        return self::SUCCESS;
    }

    /**
     * Collect task data from binaries and worker source directories.
     *
     * @return array<int, array{string, string, string}>
     */
    protected function collectTasks(string $binPath, string $workersPath): array
    {
        $tasks = [];
        $seen = [];

        // Scan worker source directories
        if (is_dir($workersPath)) {
            $dirs = glob("{$workersPath}/*/go.mod");

            foreach ($dirs as $goMod) {
                $name = basename(dirname($goMod));
                $ext = PHP_OS_FAMILY === 'Windows' ? '.exe' : '';
                $binaryPath = "{$binPath}/{$name}{$ext}";
                $found = file_exists($binaryPath);

                $tasks[] = [
                    $name,
                    $binaryPath,
                    $found ? '<info>found</info>' : '<error>missing</error>',
                ];
                $seen[$name] = true;
            }
        }

        // Scan bin_path for any binaries not already covered by workers
        if (is_dir($binPath)) {
            $files = scandir($binPath);

            foreach ($files as $file) {
                if ($file === '.' || $file === '..' || is_dir("{$binPath}/{$file}")) {
                    continue;
                }

                $name = preg_replace('/\.(exe|bin)$/', '', $file);

                if (isset($seen[$name])) {
                    continue;
                }

                $tasks[] = [
                    $name,
                    "{$binPath}/{$file}",
                    '<info>found</info>',
                ];
            }
        }

        return $tasks;
    }
}
