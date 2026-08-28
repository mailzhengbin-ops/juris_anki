<?php

namespace App\Models;

use Database\Factories\DeckFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property-read int $cards_count
 * @property-read int $sections_count
 * @property-read Collection<int, Section> $sections
 * @property-read Collection<int, Card> $cards
 */
class Deck extends Model
{
    /** @use HasFactory<DeckFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'name'];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<Section, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('position');
    }

    /** @return HasManyThrough<Card, Section, $this> */
    public function cards(): HasManyThrough
    {
        return $this->hasManyThrough(Card::class, Section::class)
            ->orderBy('sections.position')
            ->orderBy('cards.position');
    }

    /** @param Builder<Deck> $query
     *  @return Builder<Deck> */
    public function scopeSystem(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }

    /** @param Builder<Deck> $query
     *  @return Builder<Deck> */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function isSystem(): bool
    {
        return $this->user_id === null;
    }
}
