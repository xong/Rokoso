<?php

declare(strict_types=1);

namespace App\Enum;

enum TaskStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function label(): string
    {
        return 'task.status.'.$this->value;
    }
}
