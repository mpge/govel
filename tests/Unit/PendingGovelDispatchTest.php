<?php

namespace Mpge\Govel\Tests\Unit;

use Mpge\Govel\Queue\PendingGovelDispatch;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class PendingGovelDispatchTest extends TestCase
{
    /**
     * Create an instance without triggering __destruct (which calls dispatch()).
     * We use reflection to disable the destructor behavior by removing the instance
     * from scope in a controlled way.
     */
    private function makePending(): PendingGovelDispatch
    {
        // Use a real-looking task class string; we won't actually dispatch.
        return new class('Mpge\\Govel\\Tasks\\ProcessImage', ['key' => 'value']) extends PendingGovelDispatch {
            // Override __destruct to prevent it from calling dispatch()
            // during unit testing (dispatch() requires Laravel's dispatch() helper).
            public function __destruct()
            {
                // no-op
            }
        };
    }

    #[Test]
    public function on_queue_returns_self_for_fluent_chaining(): void
    {
        $pending = $this->makePending();
        $result = $pending->onQueue('high');

        $this->assertSame($pending, $result);
    }

    #[Test]
    public function on_connection_returns_self_for_fluent_chaining(): void
    {
        $pending = $this->makePending();
        $result = $pending->onConnection('redis');

        $this->assertSame($pending, $result);
    }

    #[Test]
    public function delay_returns_self_for_fluent_chaining(): void
    {
        $pending = $this->makePending();
        $result = $pending->delay(30);

        $this->assertSame($pending, $result);
    }

    #[Test]
    public function via_returns_self_for_fluent_chaining(): void
    {
        $pending = $this->makePending();
        $result = $pending->via('grpc');

        $this->assertSame($pending, $result);
    }

    #[Test]
    public function fluent_methods_can_be_chained_together(): void
    {
        $pending = $this->makePending();

        $result = $pending
            ->onQueue('default')
            ->onConnection('sqs')
            ->delay(60)
            ->via('process');

        $this->assertSame($pending, $result);
    }

    #[Test]
    public function on_queue_sets_the_queue_property(): void
    {
        $pending = $this->makePending();
        $pending->onQueue('emails');

        $reflection = new ReflectionClass(PendingGovelDispatch::class);
        $property = $reflection->getProperty('queue');

        $this->assertSame('emails', $property->getValue($pending));
    }

    #[Test]
    public function on_connection_sets_the_connection_property(): void
    {
        $pending = $this->makePending();
        $pending->onConnection('redis');

        $reflection = new ReflectionClass(PendingGovelDispatch::class);
        $property = $reflection->getProperty('connection');

        $this->assertSame('redis', $property->getValue($pending));
    }

    #[Test]
    public function delay_sets_the_delay_property(): void
    {
        $pending = $this->makePending();
        $pending->delay(120);

        $reflection = new ReflectionClass(PendingGovelDispatch::class);
        $property = $reflection->getProperty('delay');

        $this->assertSame(120, $property->getValue($pending));
    }

    #[Test]
    public function via_sets_the_govel_driver_property(): void
    {
        $pending = $this->makePending();
        $pending->via('grpc');

        $reflection = new ReflectionClass(PendingGovelDispatch::class);
        $property = $reflection->getProperty('govelDriver');

        $this->assertSame('grpc', $property->getValue($pending));
    }
}
