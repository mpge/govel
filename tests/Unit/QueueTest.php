<?php

namespace Mpge\Govel\Tests\Unit;

use Mpge\Govel\Queue\GovelJob;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class QueueTest extends TestCase
{
    #[Test]
    public function it_creates_a_govel_job_with_task_and_payload(): void
    {
        $job = new GovelJob('App\\Tasks\\ProcessImage', ['path' => '/tmp/img.jpg']);

        $this->assertSame('App\\Tasks\\ProcessImage', $job->taskClass);
        $this->assertSame(['path' => '/tmp/img.jpg'], $job->payload);
        $this->assertNull($job->onDriver);
    }

    #[Test]
    public function it_creates_a_govel_job_with_custom_driver(): void
    {
        $job = new GovelJob('App\\Tasks\\ProcessImage', [], 'grpc');

        $this->assertSame('grpc', $job->onDriver);
    }

    #[Test]
    public function it_returns_correct_tags(): void
    {
        $job = new GovelJob('App\\Tasks\\ProcessImage', []);

        $this->assertSame(['govel', 'App\\Tasks\\ProcessImage'], $job->tags());
    }
}
