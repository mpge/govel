<?php

namespace Mpge\Govel\DTO;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

final readonly class Result implements Arrayable, JsonSerializable
{
    public function __construct(
        public bool $success,
        public array $output,
        public ?string $error,
        public float $duration,
    ) {}

    public static function fromOutput(string $json, float $duration): self
    {
        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new self(
                success: false,
                output: [],
                error: 'Invalid JSON response from Go binary: ' . json_last_error_msg(),
                duration: $duration,
            );
        }

        return new self(
            success: true,
            output: $decoded,
            error: null,
            duration: $duration,
        );
    }

    public static function failure(string $error, float $duration): self
    {
        return new self(
            success: false,
            output: [],
            error: $error,
            duration: $duration,
        );
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'output' => $this->output,
            'error' => $this->error,
            'duration' => $this->duration,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
