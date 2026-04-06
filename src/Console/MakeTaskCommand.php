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

        return "<?php\n"
            . "\n"
            . "namespace {$rootNamespace}\\Tasks;\n"
            . "\n"
            . "use Mpge\\Govel\\Contracts\\Task;\n"
            . "\n"
            . "class {$name} implements Task\n"
            . "{\n"
            . "    public function name(): string\n"
            . "    {\n"
            . "        return '{$kebab}';\n"
            . "    }\n"
            . "}\n";
    }

    /**
     * Convert a StudlyCase name to kebab-case.
     */
    public static function toKebabCase(string $name): string
    {
        return Str::kebab($name);
    }
}
