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
    | Used by the "process" driver.
    |
    */

    'bin_path' => env('GOVEL_BIN_PATH', base_path('bin')),

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum number of seconds a Go task is allowed to run before
    | being killed. Set to null for no timeout.
    |
    */

    'timeout' => env('GOVEL_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | gRPC Driver
    |--------------------------------------------------------------------------
    |
    | Configuration for the gRPC driver. This connects to a persistent
    | Go server over HTTP/JSON (no PHP extensions required).
    |
    */

    'grpc' => [
        'host' => env('GOVEL_GRPC_HOST', '127.0.0.1'),
        'port' => (int) env('GOVEL_GRPC_PORT', 9800),
        'tls' => (bool) env('GOVEL_GRPC_TLS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Distributed Driver
    |--------------------------------------------------------------------------
    |
    | Configuration for the distributed driver. Distributes tasks across
    | multiple Govel worker nodes with load balancing and failover.
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
    | Default queue settings when dispatching Go tasks onto Laravel queues
    | via Govel::queue().
    |
    */

    'queue' => [
        'connection' => env('GOVEL_QUEUE_CONNECTION'),
        'queue' => env('GOVEL_QUEUE_NAME', 'govel'),
    ],

];
