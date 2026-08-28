<?php

namespace App\Models;

use App\Enums\SourceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScopeExclusion extends Model
{
    /**
     * 范围排除表只记录“取消勾选”的卡片（按背诵源独立），无时间戳。
     */
    public $timestamps = false;

    protected $fillable = ['user_id', 'source', 'card_id'];

    protected $casts = [
        'source' => SourceType::class,
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Card, $this> */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
