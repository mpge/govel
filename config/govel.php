<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Driver
    |--------------------------------------------------------------------------
    |
    | The driver used to execute Go tasks. Currently only "process" is
    | supported, which uses Symfony Process to run Go binaries directly.
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
    | Maximum number of seconds a Go process is allowed to run before
    | being killed. Set to null for no timeout.
    |
    */

    'timeout' => env('GOVEL_TIMEOUT', 30),

];
