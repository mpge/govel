<?php

namespace Mpge\Govel\Tests\Unit;

use Mpge\Govel\Exceptions\BinaryNotFoundException;
use Mpge\Govel\Exceptions\TaskExecutionException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ExceptionTest extends TestCase
{
    #[Test]
    public function binary_not_found_exception_for_task_creates_correct_message(): void
    {
        $exception = BinaryNotFoundException::forTask('process-image', '/usr/local/bin/process-image');

        $this->assertInstanceOf(BinaryNotFoundException::class, $exception);
        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertStringContainsString('process-image', $exception->getMessage());
        $this->assertStringContainsString('/usr/local/bin/process-image', $exception->getMessage());
        $this->assertSame(
            'Go binary not found for task [process-image] at path: /usr/local/bin/process-image',
            $exception->getMessage()
        );
    }

    #[Test]
    public function task_execution_exception_process_error_creates_correct_message(): void
    {
        $exception = TaskExecutionException::processError('resize-image', 'segfault', 139);

        $this->assertInstanceOf(TaskExecutionException::class, $exception);
        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertSame(
            'Go task [resize-image] failed with exit code 139: segfault',
            $exception->getMessage()
        );
    }

    #[Test]
    public function task_execution_exception_timeout_creates_correct_message(): void
    {
        $exception = TaskExecutionException::timeout('long-task', 60);

        $this->assertInstanceOf(TaskExecutionException::class, $exception);
        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertSame(
            'Go task [long-task] exceeded the timeout of 60 seconds.',
            $exception->getMessage()
        );
    }
}
