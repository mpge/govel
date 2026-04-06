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

    #[Test]
    public function it_rejects_invalid_names_via_regex(): void
    {
        $pattern = '/^[a-z0-9][a-z0-9_-]*$/';

        $invalidNames = [
            '../../etc',
            '../passwd',
            '',
            'UPPERCASE',
            '-starts-with-dash',
            '_starts-with-underscore',
            'has spaces',
            'has/slash',
            'has\\backslash',
            '.dotfile',
        ];

        foreach ($invalidNames as $name) {
            $this->assertDoesNotMatchRegularExpression(
                $pattern,
                $name,
                "Name '{$name}' should be rejected by the validation regex."
            );
        }
    }
}
