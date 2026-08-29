<?php

namespace App\Enums;

/**
 * 背诵状态机阶段（RecitationService 产出的 phase 值，前端 lib/recitation.ts 对齐）。
 */
enum RecitationPhase: string
{
    case Empty = 'empty';

    case Fresh = 'fresh';

    case Active = 'active';

    case Completed = 'completed';
}
