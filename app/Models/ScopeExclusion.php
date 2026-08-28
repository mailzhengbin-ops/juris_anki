<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScopeExclusion extends Model
{
    /**
     * 范围排除表只记录“取消勾选”的卡片，无时间戳。
     */
    public $timestamps = false;

    protected $fillable = ['user_id', 'card_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
