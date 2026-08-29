<?php

namespace App\Policies;

use App\Models\Card;
use App\Models\User;

class CardPolicy
{
    /**
     * 管理端仅可管理系统卡组下的卡片。
     */
    public function manage(User $user, Card $card): bool
    {
        return $card->section->deck->isSystem();
    }
}
