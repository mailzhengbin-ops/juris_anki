<?php

namespace App\Services;

use App\Enums\Rating;
use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * 统计口径：近 N 日（按用户时区自然日）三色评价数；
 * 同卡同日多次评价只计一次，归入当天最后一次；无评价日期补零。
 */
class StatsService
{
    /**
     * @return array{days: array<int, array{date: string, label: string, known: int, fuzzy: int, forgotten: int}>, totals: array{known: int, fuzzy: int, forgotten: int}}
     */
    public function daily(User $user, string $timezone, int $days = 7): array
    {
        $now = Carbon::now($timezone);
        $start = $now->copy()->subDays($days - 1)->startOfDay();
        $end = $now->copy()->endOfDay();

        $evaluations = Evaluation::where('user_id', $user->id)
            ->whereBetween('created_at', [$start->copy()->utc(), $end->copy()->utc()])
            ->get(['id', 'card_id', 'rating', 'created_at']);

        // 同卡同日只保留最后一次评价
        $latestPerDayCard = $evaluations
            ->groupBy(fn (Evaluation $evaluation) => $evaluation->created_at
                ->copy()
                ->setTimezone($timezone)
                ->toDateString().'|'.$evaluation->card_id)
            ->map(fn (Collection $group) => $group->sortByDesc('id')->first());

        $skeleton = [];

        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->toDateString();
            $buckets = $latestPerDayCard->filter(fn (Evaluation $evaluation) => $evaluation->created_at
                ->copy()
                ->setTimezone($timezone)
                ->toDateString() === $key);

            $skeleton[] = [
                'date' => $key,
                'label' => $day->format('m-d').' '.$this->weekdayLabel($day),
                'known' => $this->countRating($buckets, Rating::Known),
                'fuzzy' => $this->countRating($buckets, Rating::Fuzzy),
                'forgotten' => $this->countRating($buckets, Rating::Forgotten),
            ];
        }

        $totals = [
            'known' => array_sum(array_column($skeleton, 'known')),
            'fuzzy' => array_sum(array_column($skeleton, 'fuzzy')),
            'forgotten' => array_sum(array_column($skeleton, 'forgotten')),
        ];

        return ['days' => $skeleton, 'totals' => $totals];
    }

    /**
     * @param  Collection<int, Evaluation>  $buckets
     */
    private function countRating(Collection $buckets, Rating $rating): int
    {
        return $buckets->where('rating', $rating)->count();
    }

    private function weekdayLabel(Carbon $day): string
    {
        return ['日', '一', '二', '三', '四', '五', '六'][$day->dayOfWeek];
    }
}
