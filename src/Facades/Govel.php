<?php

namespace Mpge\Govel\Facades;

use Mpge\Govel\Contracts\Driver;
use Mpge\Govel\DTO\Result;
use Mpge\Govel\Queue\PendingGovelDispatch;
use Mpge\Govel\Services\GoManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Result run(string|\Mpge\Govel\Contracts\Task $task, array $payload = [])
 * @method static void dispatch(string|\Mpge\Govel\Contracts\Task $task, array $payload = [])
 * @method static PendingGovelDispatch queue(string|\Mpge\Govel\Contracts\Task $task, array $payload = [])
 * @method static Driver driver(?string $name = null)
 * @method static GoManager extend(string $name, Driver $driver)
 *
 * @see \Mpge\Govel\Services\GoManager
 */
class Govel extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return GoManager::class;
    }
}
