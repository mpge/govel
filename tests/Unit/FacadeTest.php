<?php

namespace Mpge\Govel\Tests\Unit;

use Mpge\Govel\Facades\Govel;
use Mpge\Govel\Services\GoManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class FacadeTest extends TestCase
{
    #[Test]
    public function get_facade_accessor_returns_go_manager_class(): void
    {
        $method = new ReflectionMethod(Govel::class, 'getFacadeAccessor');

        $result = $method->invoke(null);

        $this->assertSame(GoManager::class, $result);
    }
}
