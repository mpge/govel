<?php

namespace Mpge\Govel\Tasks;

use Mpge\Govel\Contracts\Task;

class ProcessImage implements Task
{
    public function name(): string
    {
        return 'process-image';
    }
}
