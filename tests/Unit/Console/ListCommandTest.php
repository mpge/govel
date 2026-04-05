<?php

namespace Mpge\Govel\Tests\Unit\Console;

use Mpge\Govel\Console\ListCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ListCommandTest extends TestCase
{
    #[Test]
    public function it_can_be_instantiated(): void
    {
        $command = new ListCommand();

        $this->assertInstanceOf(ListCommand::class, $command);
    }

    #[Test]
    public function it_has_correct_name(): void
    {
        $command = new ListCommand();

        $this->assertSame('govel:list', $command->getName());
    }
}
