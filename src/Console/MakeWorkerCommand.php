<?php

namespace Mpge\Govel\Console;

use Illuminate\Console\Command;

class MakeWorkerCommand extends Command
{
    protected $signature = 'govel:make-worker {name : The worker name in kebab-case (e.g. process-image)}';

    protected $description = 'Scaffold a new Go worker';

    public function handle(): int
    {
        $name = $this->argument('name');
        $workerDir = base_path("bin/workers/{$name}");

        if (is_dir($workerDir)) {
            $this->error("Worker directory [{$workerDir}] already exists!");

            return self::FAILURE;
        }

        mkdir($workerDir, 0755, true);

        file_put_contents("{$workerDir}/go.mod", $this->goModStub($name));
        file_put_contents("{$workerDir}/main.go", $this->mainGoStub($name));

        $this->info("Worker [{$name}] scaffolded at [{$workerDir}].");
        $this->info("Edit main.go to implement your worker logic, then run: php artisan govel:build {$name}");

        return self::SUCCESS;
    }

    protected function goModStub(string $name): string
    {
        return "module govel/workers/{$name}\n\ngo 1.21\n";
    }

    protected function mainGoStub(string $name): string
    {
        return <<<'GO'
        package main

        import (
        	"encoding/json"
        	"fmt"
        	"io"
        	"os"
        	"time"
        )

        // Request represents the incoming JSON payload from PHP.
        // TODO: Define your input fields here.
        type Request struct {
        	Input string `json:"input"`
        }

        // Response represents the JSON result sent back to PHP.
        type Response struct {
        	Status   string `json:"status"`
        	Output   string `json:"output"`
        	Duration string `json:"duration"`
        }

        func main() {
        	start := time.Now()

        	input, err := io.ReadAll(os.Stdin)
        	if err != nil {
        		fatal("failed to read stdin: " + err.Error())
        	}

        	var req Request
        	if err := json.Unmarshal(input, &req); err != nil {
        		fatal("invalid JSON input: " + err.Error())
        	}

        	// TODO: Implement your worker logic here.

        	resp := Response{
        		Status:   "completed",
        		Output:   "processed: " + req.Input,
        		Duration: time.Since(start).String(),
        	}

        	out, err := json.Marshal(resp)
        	if err != nil {
        		fatal("failed to marshal response: " + err.Error())
        	}
        	fmt.Print(string(out))
        }

        func fatal(msg string) {
        	fmt.Fprint(os.Stderr, msg)
        	os.Exit(1)
        }
        GO;
    }
}
