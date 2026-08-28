<?php

namespace App\Models;

use Database\Factories\CardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $section_id
 * @property string $question
 * @property string $answer
 * @property int $position
 * @property-read Section $section
 * @property-read string|null $deck_name
 * @property-read string|null $section_name
 */
class Card extends Model
{
    /** @use HasFactory<CardFactory> */
    use HasFactory;

    protected $fillable = ['section_id', 'question', 'answer', 'position'];

    /** @return BelongsTo<Section, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** @return HasMany<Evaluation, $this> */
    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    /**
     * 卡片在卡组树中的路径，如 "刑法 / 绪论"。
     */
    public function path(): string
    {
        return $this->section->deck->name.' / '.$this->section->name;
    }
}
