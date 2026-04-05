<?php

namespace Mpge\Govel\Concerns;

trait LogsMessages
{
    protected function log(string $message): void
    {
        if (class_exists(\Illuminate\Support\Facades\Log::class)) {
            try {
                \Illuminate\Support\Facades\Log::warning($message);

                return;
            } catch (\Throwable) {
                // Facade not booted
            }
        }

        error_log($message);
    }
}
