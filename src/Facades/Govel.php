<?php

namespace Govel\Govel\Facades;

use Govel\Govel\Contracts\Driver;
use Govel\Govel\DTO\Result;
use Govel\Govel\Services\GoManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Result run(string|\Govel\Govel\Contracts\Task $task, array $payload = [])
 * @method static void dispatch(string|\Govel\Govel\Contracts\Task $task, array $payload = [])
 * @method static Driver driver(?string $name = null)
 * @method static GoManager extend(string $name, Driver $driver)
 *
 * @see \Govel\Govel\Services\GoManager
 */
class Govel extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return GoManager::class;
    }
}
