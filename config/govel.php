<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Driver
    |--------------------------------------------------------------------------
    |
    | The driver used to execute Go tasks.
    |
    | Supported: "process", "grpc", "distributed"
    |
    */

    'driver' => env('GOVEL_DRIVER', 'process'),

    /*
    |--------------------------------------------------------------------------
    | Binary Path
    |--------------------------------------------------------------------------
    |
    | The directory where compiled Go binaries are located. Each task's
    | name() method maps to a binary inside this directory.
    |
    */

    'bin_path' => env('GOVEL_BIN_PATH', base_path('bin')),

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum number of seconds a Go task is allowed to run before
    | being killed. Set to 0 for no timeout (not recommended).
    |
    */

    'timeout' => (int) env('GOVEL_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Max Payload Size
    |--------------------------------------------------------------------------
    |
    | Maximum size in bytes for the JSON payload sent to Go workers.
    | Payloads exceeding this limit will be rejected before execution.
    | Default: 10MB. Set to 0 for unlimited (not recommended).
    |
    */

    'max_payload_size' => (int) env('GOVEL_MAX_PAYLOAD_SIZE', 10 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Process Driver
    |--------------------------------------------------------------------------
    |
    | Fine-tuning options for the process driver.
    |
    */

    'process' => [

        /*
        | Environment variables to pass to Go child processes.
        | Set to ['*'] to pass all environment variables (not recommended).
        | Set to [] to pass none.
        */
        'env' => explode(',', env('GOVEL_PROCESS_ENV', 'PATH,HOME,TMPDIR,TEMP,TMP,LANG')),

        /*
        | Working directory for Go child processes.
        | Defaults to null (inherits current working directory).
        */
        'cwd' => env('GOVEL_PROCESS_CWD'),

        /*
        | Memory limit for child processes in MB. Linux only (ulimit).
        | Set to 0 for no limit.
        */
        'memory_limit' => (int) env('GOVEL_PROCESS_MEMORY_LIMIT', 0),

    ],

    /*
    |--------------------------------------------------------------------------
    | gRPC Driver
    |--------------------------------------------------------------------------
    |
    | Configuration for the gRPC driver. Connects to a persistent Go
    | server over HTTP/JSON (no PHP extensions required).
    |
    */

    'grpc' => [
        'host' => env('GOVEL_GRPC_HOST', '127.0.0.1'),
        'port' => (int) env('GOVEL_GRPC_PORT', 9800),
        'tls' => (bool) env('GOVEL_GRPC_TLS', false),

        /*
        | Connection timeout in seconds (how long to wait to establish
        | the connection, separate from the task execution timeout).
        */
        'connect_timeout' => (int) env('GOVEL_GRPC_CONNECT_TIMEOUT', 5),

        /*
        | Number of retry attempts when a connection fails.
        */
        'retries' => (int) env('GOVEL_GRPC_RETRIES', 0),

        /*
        | Delay in milliseconds between retry attempts.
        */
        'retry_delay' => (int) env('GOVEL_GRPC_RETRY_DELAY', 100),

        /*
        | Optional authentication token sent as a Bearer token
        | in the Authorization header to the gRPC server.
        */
        'auth_token' => env('GOVEL_AUTH_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Distributed Driver
    |--------------------------------------------------------------------------
    |
    | Distributes tasks across multiple Govel worker nodes with
    | load balancing and automatic failover.
    |
    */

    'distributed' => [
        'strategy' => env('GOVEL_DIST_STRATEGY', 'round-robin'), // round-robin, least-connections
        'tls' => (bool) env('GOVEL_DIST_TLS', false),

        'nodes' => [
            // ['host' => '10.0.0.1', 'port' => 9800],
            // ['host' => '10.0.0.2', 'port' => 9800],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Default queue settings when dispatching Go tasks via Govel::queue().
    |
    */

    'queue' => [
        'connection' => env('GOVEL_QUEUE_CONNECTION'),
        'queue' => env('GOVEL_QUEUE_NAME', 'govel'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Server
    |--------------------------------------------------------------------------
    |
    | Configuration for the Govel Go server (used by gRPC and distributed
    | drivers). These values are passed when starting the server binary.
    |
    */

    'server' => [
        'port' => (int) env('GOVEL_SERVER_PORT', 9800),

        /*
        | Maximum request body size in bytes accepted by the server.
        | Default: 10MB.
        */
        'max_request_body' => (int) env('GOVEL_SERVER_MAX_BODY', 10 * 1024 * 1024),

        /*
        | Read timeout in seconds for incoming HTTP requests.
        */
        'read_timeout' => (int) env('GOVEL_SERVER_READ_TIMEOUT', 30),

        /*
        | Write timeout in seconds for outgoing HTTP responses.
        | Should be higher than the task timeout.
        */
        'write_timeout' => (int) env('GOVEL_SERVER_WRITE_TIMEOUT', 35),

        /*
        | Idle timeout in seconds for keep-alive connections.
        */
        'idle_timeout' => (int) env('GOVEL_SERVER_IDLE_TIMEOUT', 120),

        /*
        | Graceful shutdown timeout in seconds.
        */
        'shutdown_timeout' => (int) env('GOVEL_SERVER_SHUTDOWN_TIMEOUT', 10),
    ],

];
