<?php

namespace Mpge\Govel\Tests\Unit;

use Mpge\Govel\Contracts\Task;
use Mpge\Govel\Tasks\ProcessImage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProcessImageTaskTest extends TestCase
{
    #[Test]
    public function it_implements_task_interface(): void
    {
        $task = new ProcessImage();

        $this->assertInstanceOf(Task::class, $task);
    }

    #[Test]
    public function name_returns_process_image(): void
    {
        $task = new ProcessImage();

        $this->assertSame('process-image', $task->name());
    }
}
