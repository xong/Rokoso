<?php

declare(strict_types=1);

namespace App\Enum;

enum SurveyQuestionType: string
{
    case Single = 'single';
    case Multiple = 'multiple';
    /** 1 to 5 */
    case Scale = 'scale';
    case Text = 'text';

    public function hasOptions(): bool
    {
        return self::Single === $this || self::Multiple === $this;
    }
}
