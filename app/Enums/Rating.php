<?php

namespace App\Enums;

enum Rating: string
{
    case Known = 'known';

    case Fuzzy = 'fuzzy';

    case Forgotten = 'forgotten';

    public function label(): string
    {
        return match ($this) {
            self::Known => '认识',
            self::Fuzzy => '模糊',
            self::Forgotten => '忘记',
        };
    }

    /**
     * 该评价是否使卡片在错题本在册。
     */
    public function enrollsInMistakeBook(): bool
    {
        return $this !== self::Known;
    }
}
