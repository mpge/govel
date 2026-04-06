<?php

namespace Mpge\Govel\Drivers;

use Mpge\Govel\Concerns\LogsMessages;
use Mpge\Govel\Contracts\Driver;
use Mpge\Govel\Contracts\Task;
use Mpge\Govel\DTO\Result;
use Mpge\Govel\Exceptions\TaskExecutionException;

/**
 * Communicates with a persistent Go gRPC server over HTTP/JSON.
 *
 * The Go server exposes an HTTP bridge alongside its gRPC service,
 * so PHP can communicate without requiring the grpc PECL extension.
 */
class GrpcDriver implements Driver
{
    use LogsMessages;

    public function __construct(
        protected string $host,
        protected int $port,
        protected int $timeout,
        protected bool $tls = false,
        protected int $connectTimeout = 5,
        protected int $retries = 0,
        protected int $retryDelay = 100,
        protected int $maxPayloadSize = 0,
        protected ?string $authToken = null,
    ) {}

    public function run(Task $task, array $payload = []): Result
    {
        $start = hrtime(true);

        try {
            $response = $this->requestWithRetry($task->name(), $payload);
            $duration = (hrtime(true) - $start) / 1e6;

            return Result::fromOutput($response, $duration);
        } catch (\Throwable $e) {
            $duration = (hrtime(true) - $start) / 1e6;

            return Result::failure($e->getMessage(), $duration);
        }
    }

    public function dispatch(Task $task, array $payload = []): void
    {
        try {
            $this->requestWithRetry($task->name(), $payload, async: true);
        } catch (\Throwable $e) {
            $this->log("Govel gRPC async task [{$task->name()}] failed: {$e->getMessage()}");
        }
    }

    protected function requestWithRetry(string $taskName, array $payload, bool $async = false): string
    {
        $lastException = null;

        for ($attempt = 0; $attempt <= $this->retries; $attempt++) {
            try {
                return $this->request($taskName, $payload, $async);
            } catch (\Throwable $e) {
                $lastException = $e;

                if ($attempt < $this->retries) {
                    usleep($this->retryDelay * 1000);
                }
            }
        }

        throw $lastException;
    }

    protected function request(string $taskName, array $payload, bool $async = false): string
    {
        $scheme = $this->tls ? 'https' : 'http';
        $url = "{$scheme}://{$this->host}:{$this->port}/govel/execute";

        $body = json_encode([
            'task' => $taskName,
            'payload' => $payload,
            'async' => $async,
        ], JSON_THROW_ON_ERROR);

        if ($this->maxPayloadSize > 0 && strlen($body) > $this->maxPayloadSize) {
            throw new \OverflowException(
                "Payload for task [{$taskName}] exceeds maximum size of {$this->maxPayloadSize} bytes."
            );
        }

        $headers = "Content-Type: application/json\r\nAccept: application/json\r\n";

        if ($this->authToken !== null && $this->authToken !== '') {
            $headers .= "Authorization: Bearer {$this->authToken}\r\n";
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => $headers,
                'content' => $body,
                'timeout' => max($this->connectTimeout, $this->timeout),
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => $this->tls,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw TaskExecutionException::processError(
                $taskName,
                "Failed to connect to Govel gRPC server at {$this->host}:{$this->port}",
                1,
            );
        }

        $statusCode = $this->parseStatusCode($http_response_header ?? []);

        if ($statusCode === 0) {
            throw TaskExecutionException::processError(
                $taskName,
                'Unable to determine HTTP status from response',
                0,
            );
        }

        if ($statusCode >= 400) {
            $decoded = json_decode($response, true);
            $error = $decoded['error'] ?? "HTTP {$statusCode}";

            throw TaskExecutionException::processError($taskName, $error, $statusCode);
        }

        if ($async) {
            return '{"status":"dispatched"}';
        }

        return $response;
    }

    protected function parseStatusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/[\d.]+ (\d{3})/', $header, $matches)) {
                return (int) $matches[1];
            }
        }

        return 0;
    }

}
