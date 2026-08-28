<?php

namespace App\Services;

use App\Enums\Rating;
use App\Enums\SourceType;
use App\Models\Card;
use App\Models\Evaluation;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * 背诵核心语义：任务/进度/下一张/完成判定/撤销，全部由评价记录实时推导。
 *
 * state() 是纯投影（不写库）；唯一的写路径是 rate() 与 undo()。
 */
class RecitationService
{
    public function __construct(private readonly ScopeService $scope) {}

    /**
     * 计算背诵页状态（评价驱动，纯翻看不推进）。无副作用。
     *
     * @param  bool  $forceFresh  已完成状态下请求"再背一轮"时强制回到未开始
     * @return array{source: string, phase: string, progress: array{evaluated: int, total: int}, card: array<string, mixed>|null, task: array<string, mixed>|null}
     */
    public function state(User $user, SourceType $source, bool $forceFresh = false): array
    {
        $deckId = $source === SourceType::Mistake ? null : $user->selected_deck_id;

        if ($source === SourceType::Selected && $deckId === null) {
            return $this->baseState($source, 'empty');
        }

        $scopeIds = $this->scope->cardIds($user, $source);

        if ($scopeIds->isEmpty()) {
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

            return $this->freshState($user, $source, $scopeIds);
        }

        $evaluatedIds = $task->evaluations()->pluck('card_id');
        $unevaluatedIds = $scopeIds->diff($evaluatedIds);
        $evaluatedInScope = $scopeIds->intersect($evaluatedIds)->count();

        // 纯推导完成：范围实时收缩可能不经评价就耗尽（不落库，由下一次 rate 收尾）
        if ($unevaluatedIds->isEmpty()) {
            return $forceFresh
                ? $this->freshState($user, $source, $scopeIds)
                : $this->completedState($source, $task);
        }

        $state = $this->baseState($source, 'active');
        $state['progress'] = ['evaluated' => $evaluatedInScope, 'total' => $scopeIds->count()];
        $state['card'] = $this->cardPayload(
            $user,
            $unevaluatedIds->first(),
            $source === SourceType::Mistake ? $this->enrolledRatingFor($user, $unevaluatedIds->first()) : null,
        );

        return $state;
    }

    /**
     * 评价一张卡片：创建任务（首次评价时）、追加评价日志、实时完成判定。
     *
     * @return array<string, mixed> 评价后的最新背诵状态（供控制器直接渲染，避免重定向后再算一遍）
     *
     * @throws ValidationException
     */
    public function rate(User $user, SourceType $source, int $cardId, Rating $rating): array
    {
        $deckId = $source === SourceType::Mistake ? null : $user->selected_deck_id;

        abort_if($source === SourceType::Selected && $deckId === null, 422, '尚未选择自选卡');

        $scopeIds = $this->scope->cardIds($user, $source);

        abort_unless($scopeIds->contains($cardId), 422, '卡片不在当前背诵范围内');

        $task = $this->currentTask($user, $source, $deckId);

        // 范围收缩导致任务已耗尽：先收尾旧任务，评价归入新任务
        if ($task !== null && $this->isExhausted($task, $scopeIds)) {
            $task->update(['completed_at' => now()]);
            $task = null;
        }

        $task ??= Task::create([
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
        $deckId = $source === SourceType::Mistake ? null : $user->selected_deck_id;

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
            $scopeIds = $this->scope->cardIds($user, $source);
            $unevaluated = $scopeIds->diff($evaluatedIds)->count();

            if ($unevaluated > 0 || $evaluatedIds->isEmpty()) {
                $task->update(['completed_at' => null]);
            }
        }

        return $this->state($user, $source);
    }

    /**
     * 任务是否已被当前范围耗尽（无未评价卡片）。
     *
     * @param  Collection<int, int>  $scopeIds
     */
    private function isExhausted(Task $task, Collection $scopeIds): bool
    {
        return $scopeIds->diff($task->evaluations()->pluck('card_id'))->isEmpty();
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
    private function freshState(User $user, SourceType $source, Collection $scopeIds): array
    {
        $firstCardId = $scopeIds->first();
        $state = $this->baseState($source, 'fresh');
        $state['progress'] = ['evaluated' => 0, 'total' => $scopeIds->count()];
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
