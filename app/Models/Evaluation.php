<?php

namespace App\Models;

use App\Enums\Rating;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evaluation extends Model
{
    /**
     * 评价为不可变追加日志，无 updated_at。
     */
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'card_id', 'task_id', 'rating', 'created_at'];

    protected $casts = [
        'rating' => Rating::class,
        'created_at' => 'datetime',
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

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
