# Govel

Execute high-performance Go tasks from Laravel as if they were native jobs.

```php
use Govel\Govel\Facades\Govel;
use Govel\Govel\Tasks\ProcessImage;

// Synchronous — blocks until the Go binary returns
$result = Govel::run(ProcessImage::class, [
    'path' => '/tmp/image.jpg',
    'width' => 800,
]);

$result->success;   // true
$result->output;    // ['status' => 'processed', 'format' => 'webp', ...]
$result->duration;  // 52.3 (milliseconds)

// Asynchronous — fire-and-forget
Govel::dispatch(ProcessImage::class, [
    'path' => '/tmp/image.jpg',
]);
```

## Requirements

- PHP 8.3+
- Laravel 11+
- Go 1.21+ (for compiling workers)

## Installation

```bash
composer require govel/govel
```

Publish the config file:

```bash
php artisan vendor:publish --tag=govel-config
```

## Configuration

**config/govel.php**

| Key | Default | Description |
|---|---|---|
| `driver` | `process` | Execution driver (`process`) |
| `bin_path` | `base_path('bin')` | Directory containing compiled Go binaries |
| `timeout` | `30` | Max execution time in seconds |

## Creating a Task

### 1. Define the PHP Task

```php
<?php

namespace App\Tasks;

use Govel\Govel\Contracts\Task;

class ProcessImage implements Task
{
    public function name(): string
    {
        return 'process-image';
    }
}
```

The `name()` method maps directly to a binary: `bin/process-image`.

### 2. Write the Go Worker

Create `bin/workers/process-image/main.go`:

```go
package main

import (
    "encoding/json"
    "fmt"
    "io"
    "os"
)

func main() {
    input, _ := io.ReadAll(os.Stdin)

    var payload map[string]interface{}
    json.Unmarshal(input, &payload)

    // Do your work here...

    result, _ := json.Marshal(map[string]interface{}{
        "status": "done",
    })
    fmt.Println(string(result))
}
```

### 3. Compile the Worker

```bash
cd bin/workers/process-image
go build -o ../../process-image .
```

On Windows:

```bash
go build -o ../../process-image.exe .
```

## Go Worker Contract

Every Go binary must:

1. **Read JSON from stdin** — the payload passed from PHP
2. **Write JSON to stdout** — the response back to PHP
3. **Exit 0 on success**, non-zero on failure
4. **Write errors to stderr** — captured by Govel for logging

## Result DTO

`Govel::run()` returns a `Result` object:

```php
$result->success;   // bool
$result->output;    // array (decoded JSON from Go)
$result->error;     // string|null
$result->duration;  // float (milliseconds)
```

## Error Handling

```php
use Govel\Govel\Exceptions\BinaryNotFoundException;
use Govel\Govel\Exceptions\TaskExecutionException;

try {
    $result = Govel::run(ProcessImage::class, $payload);
} catch (BinaryNotFoundException $e) {
    // Binary not found at expected path
} catch (TaskExecutionException $e) {
    // Process timed out or failed critically
}

// Non-critical failures are returned in the Result:
if (! $result->success) {
    logger()->error($result->error);
}
```

## Extending Govel

Register custom drivers:

```php
use Govel\Govel\Facades\Govel;

Govel::extend('grpc', new GrpcDriver(/* ... */));
```

## Architecture

```
Laravel (PHP)
  → Govel Facade
    → GoManager (resolves driver)
      → ProcessDriver (Symfony Process)
        → Go binary (stdin/stdout JSON)
          → Result DTO
```

## License

MIT
