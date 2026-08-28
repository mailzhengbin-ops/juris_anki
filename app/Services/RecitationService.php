<?php

namespace App\Services;

use App\Enums\Rating;
use App\Enums\SourceType;
use App\Models\Card;
use App\Models\Deck;
use App\Models\Evaluation;
use App\Models\ScopeExclusion;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * 背诵核心语义：任务/进度/下一张/完成判定/撤销，全部由评价记录实时推导。
 */
class RecitationService
{
    /**
     * 计算背诵页状态（评价驱动，纯翻看不推进）。
     *
     * @param  bool  $forceFresh  已完成状态下请求"再背一轮"时强制回到未开始
     * @return array{source: string, phase: string, progress: array{evaluated: int, total: int}, card: array|null, task: array|null}
     */
    public function state(User $user, SourceType $source, bool $forceFresh = false): array
    {
        if ($source === SourceType::Mistake) {
            return $this->baseState($source, 'unavailable');
        }

        $deck = Deck::find($user->selected_deck_id);

        if ($deck === null) {
            return $this->baseState($source, 'empty');
        }

        $scopeIds = $this->scopeCardIds($user, $deck);
        $total = $scopeIds->count();

        if ($total === 0) {
            return $this->baseState($source, 'empty');
        }

        $task = $this->currentTask($user, $source, $deck->id);

        if ($task === null) {
            $completed = Task::where('user_id', $user->id)
                ->where('source_type', $source)
                ->where('source_deck_id', $deck->id)
                ->whereNotNull('completed_at')
                ->latest('id')
                ->first();

            if ($completed !== null && ! $forceFresh) {
                return $this->completedState($source, $completed);
            }

            return $this->freshState($user, $source, $scopeIds, $total);
        }

        $evaluatedIds = $task->evaluations()->pluck('card_id');
        $unevaluatedIds = $scopeIds->diff($evaluatedIds);
        $evaluatedInScope = $scopeIds->intersect($evaluatedIds)->count();

        // 惰性完成判定：范围实时变化，可能不经评价就耗尽
        if ($unevaluatedIds->isEmpty()) {
            $task->update(['completed_at' => now()]);

            return $this->completedState($source, $task->fresh());
        }

        return [
            ...$this->baseState($source, 'active'),
            'progress' => ['evaluated' => $evaluatedInScope, 'total' => $total],
            'card' => $this->cardPayload($user, $unevaluatedIds->first()),
        ];
    }

    /**
     * 评价一张卡片：创建任务（首次评价时）、追加评价日志、实时完成判定。
     *
     * @throws ValidationException
     */
    public function rate(User $user, SourceType $source, int $cardId, Rating $rating): array
    {
        $deck = Deck::find($user->selected_deck_id);

        abort_if($deck === null || $source === SourceType::Mistake, 422, '该背诵源暂不可用');

        $scopeIds = $this->scopeCardIds($user, $deck);

        abort_unless($scopeIds->contains($cardId), 422, '卡片不在当前背诵范围内');

        $task = $this->currentTask($user, $source, $deck->id)
            ?? Task::create([
                'user_id' => $user->id,
                'source_type' => $source,
                'source_deck_id' => $deck->id,
                'started_at' => now(),
            ]);

        Evaluation::create([
            'user_id' => $user->id,
            'card_id' => $cardId,
            'task_id' => $task->id,
            'rating' => $rating,
        ]);

        return $this->state($user, $source);
    }

    /**
     * 撤销当前任务最后一次评价；任务若因此未完成则重开。
     */
    public function undo(User $user, SourceType $source): array
    {
        $deck = Deck::find($user->selected_deck_id);

        if ($deck === null || $source === SourceType::Mistake) {
            return $this->state($user, $source);
        }

        $task = Task::where('user_id', $user->id)
            ->where('source_type', $source)
            ->where('source_deck_id', $deck->id)
            ->latest('id')
            ->first();

        if ($task === null) {
            return $this->state($user, $source);
        }

        $last = $task->evaluations()->latest('id')->first();

        if ($last === null) {
            return $this->state($user, $source);
        }

        $last->delete();

        if ($task->completed_at !== null) {
            $evaluatedIds = $task->evaluations()->pluck('card_id');
            $unevaluated = $this->scopeCardIds($user, $deck)->diff($evaluatedIds)->count();

            if ($unevaluated > 0 || $evaluatedIds->isEmpty()) {
                $task->update(['completed_at' => null]);
            }
        }

        return $this->state($user, $source);
    }

    /**
     * 当前任务：该源（自选卡按当前所选卡组）最新一条未完成任务。
     */
    private function currentTask(User $user, SourceType $source, int $deckId): ?Task
    {
        return Task::where('user_id', $user->id)
            ->where('source_type', $source)
            ->where('source_deck_id', $deckId)
            ->whereNull('completed_at')
            ->latest('id')
            ->first();
    }

    /**
     * 背诵范围内的卡片（卡组树顺序）：卡组全部卡片减去取消勾选。
     *
     * @return Collection<int, int>
     */
    private function scopeCardIds(User $user, Deck $deck): Collection
    {
        $excluded = ScopeExclusion::where('user_id', $user->id)
            ->pluck('card_id');

        return Card::query()
            ->join('sections', 'cards.section_id', '=', 'sections.id')
            ->where('sections.deck_id', $deck->id)
            ->whereNotIn('cards.id', $excluded)
            ->orderBy('sections.position')
            ->orderBy('cards.position')
            ->pluck('cards.id');
    }

    /**
     * @return array{source: string, phase: string, progress: array{evaluated: int, total: int}, card: null, task: null}
     */
    private function baseState(SourceType $source, string $phase): array
    {
        return [
            'source' => $source->value,
            'phase' => $phase,
            'progress' => ['evaluated' => 0, 'total' => 0],
            'card' => null,
            'task' => null,
        ];
    }

    /**
     * @param  Collection<int, int>  $scopeIds
     */
    private function freshState(User $user, SourceType $source, Collection $scopeIds, int $total): array
    {
        return [
            ...$this->baseState($source, 'fresh'),
            'progress' => ['evaluated' => 0, 'total' => $total],
            'card' => $this->cardPayload($user, $scopeIds->first()),
        ];
    }

    private function completedState(SourceType $source, Task $task): array
    {
        $stats = $task->evaluations()
            ->get()
            ->groupBy(fn (Evaluation $evaluation) => $evaluation->rating->value)
            ->map->count()
            ->all();

        return [
            ...$this->baseState($source, 'completed'),
            'task' => [
                'stats' => [
                    'known' => $stats['known'] ?? 0,
                    'fuzzy' => $stats['fuzzy'] ?? 0,
                    'forgotten' => $stats['forgotten'] ?? 0,
                ],
            ],
        ];
    }

    private function cardPayload(User $user, int $cardId): array
    {
        $card = Card::with('section.deck')->find($cardId);

        $history = Evaluation::where('user_id', $user->id)
            ->where('card_id', $cardId)
            ->get();

        $last = $history->last();

        return [
            'id' => $card->id,
            'question' => $card->question,
            'answer' => $card->answer,
            'path' => $card->path(),
            'history' => [
                'total' => $history->count(),
                'known' => $history->where('rating', Rating::Known)->count(),
                'fuzzy' => $history->where('rating', Rating::Fuzzy)->count(),
                'forgotten' => $history->where('rating', Rating::Forgotten)->count(),
                'last_rating' => $last?->rating->value ?? null,
                'last_at' => $last?->created_at?->toIso8601String() ?? null,
            ],
        ];
    }
}
