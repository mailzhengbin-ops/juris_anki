<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    protected $fillable = ['deck_id', 'name', 'position'];

    public function deck(): BelongsTo
    {
        return $this->belongsTo(Deck::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class)->orderBy('position');
    }
}
