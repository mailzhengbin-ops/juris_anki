<?php

namespace App\Policies;

use App\Models\Deck;
use App\Models\User;

class DeckPolicy
{
    /**
     * 管理端仅可管理系统卡组；用户私有卡组一律拒绝（403）。
     */
    public function manage(User $user, Deck $deck): bool
    {
        return $deck->isSystem();
    }
}
