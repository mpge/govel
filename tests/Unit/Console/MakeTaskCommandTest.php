<?php

namespace Mpge\Govel\Tests\Unit\Console;

use Mpge\Govel\Console\MakeTaskCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MakeTaskCommandTest extends TestCase
{
    #[Test]
    public function it_can_be_instantiated(): void
    {
        $command = new MakeTaskCommand();

        $this->assertInstanceOf(MakeTaskCommand::class, $command);
    }

    #[Test]
    #[DataProvider('kebabCaseProvider')]
    public function it_converts_names_to_kebab_case(string $input, string $expected): void
    {
        $this->assertSame($expected, MakeTaskCommand::toKebabCase($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function kebabCaseProvider(): array
    {
        return [
            'simple two words' => ['ProcessImage', 'process-image'],
            'three words' => ['SendEmailNotification', 'send-email-notification'],
            'single word' => ['Process', 'process'],
            'already kebab-ish' => ['PDF', 'p-d-f'],
            'mixed case' => ['GeneratePDFReport', 'generate-p-d-f-report'],
        ];
    }
}
