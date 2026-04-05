<?php

namespace Mpge\Govel\Tests\Unit;

use Mpge\Govel\DTO\Result;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ResultTest extends TestCase
{
    #[Test]
    public function it_creates_from_valid_json_output(): void
    {
        $json = json_encode(['status' => 'ok', 'data' => [1, 2, 3]]);

        $result = Result::fromOutput($json, 12.5);

        $this->assertTrue($result->success);
        $this->assertSame(['status' => 'ok', 'data' => [1, 2, 3]], $result->output);
        $this->assertNull($result->error);
        $this->assertSame(12.5, $result->duration);
    }

    #[Test]
    public function it_returns_failure_for_invalid_json(): void
    {
        $result = Result::fromOutput('not json', 5.0);

        $this->assertFalse($result->success);
        $this->assertSame([], $result->output);
        $this->assertStringContainsString('Invalid JSON', $result->error);
    }

    #[Test]
    public function it_creates_a_failure_result(): void
    {
        $result = Result::failure('something broke', 3.0);

        $this->assertFalse($result->success);
        $this->assertSame('something broke', $result->error);
        $this->assertSame(3.0, $result->duration);
    }

    #[Test]
    public function it_serializes_to_array_and_json(): void
    {
        $result = new Result(true, ['key' => 'value'], null, 1.0);

        $array = $result->toArray();
        $this->assertSame([
            'success' => true,
            'output' => ['key' => 'value'],
            'error' => null,
            'duration' => 1.0,
        ], $array);

        $this->assertSame($array, $result->jsonSerialize());
    }
}
