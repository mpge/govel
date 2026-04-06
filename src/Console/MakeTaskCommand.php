<?php

namespace Mpge\Govel\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeTaskCommand extends Command
{
    protected $signature = 'govel:make-task {name : The name of the task class (e.g. ProcessImage)}';

    protected $description = 'Create a new Govel task class';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $kebab = self::toKebabCase($name);

        $path = app_path("Tasks/{$name}.php");

        if (file_exists($path)) {
            $this->error("Task [{$path}] already exists!");

            return self::FAILURE;
        }

        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $stub = $this->buildStub($name, $kebab);
        file_put_contents($path, $stub);

        $this->info("Task [{$name}] created successfully at [{$path}].");

        return self::SUCCESS;
    }

    protected function buildStub(string $name, string $kebab): string
    {
        $rootNamespace = rtrim($this->laravel->getNamespace(), '\\');

        return <<<PHP
        <?php

        namespace {$rootNamespace}\Tasks;

        use Mpge\Govel\Contracts\Task;

        class {$name} implements Task
        {
            public function name(): string
            {
                return '{$kebab}';
            }
        }
        PHP;
    }

    /**
     * Convert a StudlyCase name to kebab-case.
     */
    public static function toKebabCase(string $name): string
    {
        return Str::kebab($name);
    }
}
