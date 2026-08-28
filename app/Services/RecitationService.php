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
     * @return array{source: string, phase: string, progress: array{evaluated: int, total: int}, card: array<string, mixed>|null, task: array<string, mixed>|null}
     */
    public function state(User $user, SourceType $source, bool $forceFresh = false): array
    {
        if ($source === SourceType::Mistake) {
            $deckId = null;
            $scopeIds = $this->mistakeScopeCardIds($user);
        } else {
            $deck = Deck::find($user->selected_deck_id);

            if ($deck === null) {
                return $this->baseState($source, 'empty');
            }

            $deckId = $deck->id;
            $scopeIds = $this->selectedScopeCardIds($user, $deck);
        }

        $total = $scopeIds->count();

        if ($total === 0) {
            return $this->baseState($source, 'empty');
        }

        $task = $this->currentTask($user, $source, $deckId);

        if ($task === null) {
            $completed = Task::where('user_id', $user->id)
                ->where('source_type', $source)
                ->where('source_deck_id', $deckId)
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

        $nextCardId = $unevaluatedIds->first();
        $state = $this->baseState($source, 'active');
        $state['progress'] = ['evaluated' => $evaluatedInScope, 'total' => $total];
        $state['card'] = $this->cardPayload(
            $user,
            $nextCardId,
            $source === SourceType::Mistake ? $this->enrolledRatingFor($user, $nextCardId) : null,
        );

        return $state;
    }

    /**
     * 评价一张卡片：创建任务（首次评价时）、追加评价日志、实时完成判定。
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function rate(User $user, SourceType $source, int $cardId, Rating $rating): array
    {
        if ($source === SourceType::Mistake) {
            $deckId = null;
            $scopeIds = $this->mistakeScopeCardIds($user);
        } else {
            $deck = Deck::find($user->selected_deck_id);

            abort_if($deck === null, 422, '尚未选择自选卡');

            $deckId = $deck->id;
            $scopeIds = $this->selectedScopeCardIds($user, $deck);
        }

        abort_unless($scopeIds->contains($cardId), 422, '卡片不在当前背诵范围内');

        $task = $this->currentTask($user, $source, $deckId)
            ?? Task::create([
                'user_id' => $user->id,
                'source_type' => $source,
                'source_deck_id' => $deckId,
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
     *
     * @return array<string, mixed>
     */
    public function undo(User $user, SourceType $source): array
    {
        $deckId = $source === SourceType::Mistake
            ? null
            : Deck::find($user->selected_deck_id)?->id;

        if ($deckId === null && $source === SourceType::Selected) {
            return $this->state($user, $source);
        }

        $task = Task::where('user_id', $user->id)
            ->where('source_type', $source)
            ->where('source_deck_id', $deckId)
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
            $scopeIds = $source === SourceType::Mistake
                ? $this->mistakeScopeCardIds($user)
                : $this->selectedScopeCardIds($user, Deck::find($deckId));
            $unevaluated = $scopeIds->diff($evaluatedIds)->count();

            if ($unevaluated > 0 || $evaluatedIds->isEmpty()) {
                $task->update(['completed_at' => null]);
            }
        }

        return $this->state($user, $source);
    }

    /**
     * 错题本在册成员：最新评价为"忘记"/"模糊"的卡片，按原属卡组树顺序。
     *
     * @return array{forgotten: Collection<int, int>, fuzzy: Collection<int, int>}
     */
    public function mistakeMembership(User $user): array
    {
        $latestPerCard = Evaluation::where('user_id', $user->id)
            ->whereNotNull('card_id')
            ->selectRaw('MAX(id) as id')
            ->groupBy('card_id')
            ->pluck('id');

        $latest = Evaluation::whereIn('id', $latestPerCard)
            ->get()
            ->filter(fn (Evaluation $evaluation) => $evaluation->rating->enrollsInMistakeBook());

        $forgotten = $latest->where('rating', Rating::Forgotten)->pluck('card_id');
        $fuzzy = $latest->where('rating', Rating::Fuzzy)->pluck('card_id');

        return [
            'forgotten' => $this->inTreeOrder($forgotten),
            'fuzzy' => $this->inTreeOrder($fuzzy),
        ];
    }

    /**
     * 某卡片当前的在册评价（不在册返回 null）。
     */
    private function enrolledRatingFor(User $user, int $cardId): ?Rating
    {
        $latest = Evaluation::where('user_id', $user->id)
            ->where('card_id', $cardId)
            ->latest('id')
            ->first();

        return $latest !== null && $latest->rating->enrollsInMistakeBook()
            ? $latest->rating
            : null;
    }

    /**
     * 当前任务：该源（自选卡按当前所选卡组，错题本为 null）最新一条未完成任务。
     */
    private function currentTask(User $user, SourceType $source, ?int $deckId): ?Task
    {
        return Task::where('user_id', $user->id)
            ->where('source_type', $source)
            ->where('source_deck_id', $deckId)
            ->whereNull('completed_at')
            ->latest('id')
            ->first();
    }

    /**
     * 自选卡源的背诵范围（卡组树顺序）：卡组全部卡片减去取消勾选。
     *
     * @return Collection<int, int>
     */
    private function selectedScopeCardIds(User $user, Deck $deck): Collection
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
     * 错题本源的背诵范围（原属卡组树顺序）：在册卡片减去取消勾选。
     *
     * @return Collection<int, int>
     */
    private function mistakeScopeCardIds(User $user): Collection
    {
        $excluded = ScopeExclusion::where('user_id', $user->id)
            ->pluck('card_id');

        $membership = $this->mistakeMembership($user);

        return $this->inTreeOrder(
            $membership['forgotten']->merge($membership['fuzzy'])->unique()->diff($excluded),
        );
    }

    /**
     * 按原属卡组树顺序排序给定的卡片 ID 集合。
     *
     * @param  Collection<int, int>  $cardIds
     * @return Collection<int, int>
     */
    private function inTreeOrder(Collection $cardIds): Collection
    {
        return Card::query()
            ->join('sections', 'cards.section_id', '=', 'sections.id')
            ->whereIn('cards.id', $cardIds)
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
     * @return array{source: string, phase: string, progress: array{evaluated: int, total: int}, card: array<string, mixed>|null, task: array<string, mixed>|null}
     */
    private function freshState(User $user, SourceType $source, Collection $scopeIds, int $total): array
    {
        $firstCardId = $scopeIds->first();
        $state = $this->baseState($source, 'fresh');
        $state['progress'] = ['evaluated' => 0, 'total' => $total];
        $state['card'] = $this->cardPayload(
            $user,
            $firstCardId,
            $source === SourceType::Mistake ? $this->enrolledRatingFor($user, $firstCardId) : null,
        );

        return $state;
    }

    /** @return array{source: string, phase: string, progress: array{evaluated: int, total: int}, card: array<string, mixed>|null, task: array<string, mixed>|null} */
    private function completedState(SourceType $source, Task $task): array
    {
        $stats = $task->evaluations()
            ->get()
            ->groupBy(fn (Evaluation $evaluation) => $evaluation->rating->value)
            ->map->count()
            ->all();

        $state = $this->baseState($source, 'completed');
        $state['task'] = [
            'stats' => [
                'known' => $stats['known'] ?? 0,
                'fuzzy' => $stats['fuzzy'] ?? 0,
                'forgotten' => $stats['forgotten'] ?? 0,
            ],
        ];

        return $state;
    }

    /**
     * @return array{id: int, question: string, answer: string, path: string, enrolled: string|null, history: array{total: int, known: int, fuzzy: int, forgotten: int, last_rating: string|null, last_at: string|null}}
     */
    private function cardPayload(User $user, int $cardId, ?Rating $enrolled = null): array
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
            'enrolled' => $enrolled?->value,
            'history' => [
                'total' => $history->count(),
                'known' => $history->where('rating', Rating::Known)->count(),
                'fuzzy' => $history->where('rating', Rating::Fuzzy)->count(),
                'forgotten' => $history->where('rating', Rating::Forgotten)->count(),
                'last_rating' => $last?->rating->value,
                'last_at' => $last?->created_at->toIso8601String(),
            ],
        ];
    }
}
