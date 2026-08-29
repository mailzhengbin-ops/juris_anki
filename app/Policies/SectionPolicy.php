<?php

namespace App\Policies;

use App\Models\Section;
use App\Models\User;

class SectionPolicy
{
    /**
     * 管理端仅可管理系统卡组下的子卡组。
     */
    public function manage(User $user, Section $section): bool
    {
        return $section->deck->isSystem();
    }
}
