<?php

namespace App\Enums;

enum SourceType: string
{
    case Selected = 'selected';

    case Mistake = 'mistake';

    public function label(): string
    {
        return match ($this) {
            self::Selected => '自选卡',
            self::Mistake => '错题本',
        };
    }
}
