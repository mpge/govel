<?php

namespace Mpge\Govel\Tests\Unit\Console;

use Mpge\Govel\Console\BuildCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BuildCommandTest extends TestCase
{
    #[Test]
    public function it_can_be_instantiated(): void
    {
        $command = new BuildCommand();

        $this->assertInstanceOf(BuildCommand::class, $command);
    }

    #[Test]
    public function it_has_correct_name(): void
    {
        $command = new BuildCommand();

        $this->assertSame('govel:build', $command->getName());
    }
}
