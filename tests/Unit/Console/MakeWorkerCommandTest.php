<?php

namespace Mpge\Govel\Tests\Unit\Console;

use Mpge\Govel\Console\MakeWorkerCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MakeWorkerCommandTest extends TestCase
{
    #[Test]
    public function it_can_be_instantiated(): void
    {
        $command = new MakeWorkerCommand();

        $this->assertInstanceOf(MakeWorkerCommand::class, $command);
    }

    #[Test]
    public function it_has_correct_name(): void
    {
        $command = new MakeWorkerCommand();

        $this->assertSame('govel:make-worker', $command->getName());
    }
}
