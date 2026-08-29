<?php

namespace App\Services;

use App\Enums\Rating;
use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * 错题本在册规则的单一来源：卡片最新一次评价为「模糊」或「忘记」即在册，
 * 评为「认识」即出册。范围树分组（ScopeService）与卡片在册标记（RecitationService）均由此派生。
 */
class MistakeBookService
{
    /**
     * 全部在册评价，以 card_id 为键。
     *
     * @return Collection<int, Rating>
     */
    public function enrolledRatings(User $user): Collection
    {
        $latestPerCard = Evaluation::where('user_id', $user->id)
            ->whereNotNull('card_id')
            ->selectRaw('MAX(id) as id')
            ->groupBy('card_id')
            ->pluck('id');

        return Evaluation::whereIn('id', $latestPerCard)
            ->get()
            ->filter(fn (Evaluation $evaluation) => $evaluation->rating->enrollsInMistakeBook())
            ->mapWithKeys(fn (Evaluation $evaluation) => [$evaluation->card_id => $evaluation->rating]);
    }

    /**
     * 某卡片当前的在册评价（不在册返回 null）。
     */
    public function enrolledRating(User $user, int $cardId): ?Rating
    {
        $latest = Evaluation::where('user_id', $user->id)
            ->where('card_id', $cardId)
            ->latest('id')
            ->first();

        return $latest !== null && $latest->rating->enrollsInMistakeBook()
            ? $latest->rating
            : null;
    }
}
