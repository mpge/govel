<?php

namespace Govel\Govel\Contracts;

interface Task
{
    /**
     * The name of the Go binary to execute.
     *
     * Maps to a binary at: {bin_path}/{name}
     * Example: "process-image" → bin/process-image
     */
    public function name(): string;
}
