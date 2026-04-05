<?php

namespace Govel\Govel\Tasks;

use Govel\Govel\Contracts\Task;

class ProcessImage implements Task
{
    public function name(): string
    {
        return 'process-image';
    }
}
