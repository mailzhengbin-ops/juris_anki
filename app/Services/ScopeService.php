<?php

namespace App\Services;

use App\Enums\Rating;
use App\Enums\SourceType;
use App\Models\Card;
use App\Models\Deck;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

/**
 * 背诵范围（Scope）：一个背诵源内的卡片集合 = 源内全部卡片（卡组树顺序）减去取消勾选。
 *
 * 深模块：范围推导、树序查询、勾选切换、错题本在册全部收纳于此，
 * 调用方（控制器与背诵会话）只接触 cardIds / toggle / mistakeMembership 三个接口。
 */
class ScopeService
{
    public function __construct(private readonly MistakeBookService $mistakeBook) {}

    /**
     * 某背诵源的范围卡片 ID（卡组树顺序）。
     *
     * @return Collection<int, int>
     */
    public function cardIds(User $user, SourceType $source): Collection
    {
        return $this->fullCardIds($user, $source)
            ->diff($this->excludedCardIds($user, $source));
    }

    /**
     * 切换范围勾选（点选即保存，实时生效）。
     *
     * type：card=单卡 | section=整子卡组（错题本为 forgotten/fuzzy 子组标识）| deck=整卡组 | source=当前源全部
     *
     * @throws ModelNotFoundException 目标不属于该源时
     */
    public function toggle(User $user, SourceType $source, string $type, mixed $id, bool $checked): void
    {
        $cardIds = match ($type) {
            'card' => $this->singleCardIds($user, $source, (int) $id),
            'section' => $this->sectionCardIds($user, $source, (string) $id),
            'deck', 'source' => $this->fullCardIds($user, $source),
            default => abort(422, '无效的切换类型'),
        };

        if ($checked) {
            $user->scopeExclusions()
                ->where('source', $source)
                ->whereIn('card_id', $cardIds)
                ->delete();
        } else {
            $user->scopeExclusions()->createMany(
                $cardIds->map(fn (int $cardId) => ['source' => $source, 'card_id' => $cardId])->all(),
            );
        }
    }

    /**
     * 整体应用背诵范围（选卡页 Dialog 确认提交）：范围 = 源内全部卡片 ∩ 勾选集，
     * 一次调用替换整个源的勾选状态。勾选集必须是源内卡片。
     *
     * @param  array<int, int>  $checkedCardIds
     */
    public function apply(User $user, SourceType $source, array $checkedCardIds): void
    {
        $full = $this->fullCardIds($user, $source);
        $checked = array_unique($checkedCardIds);
        $validated = collect($checked)->filter(fn (int $id) => $full->contains($id));

        abort_unless(
            $validated->count() === count($checked),
            422,
            '勾选的卡片不属于当前背诵源',
        );

        $excluded = $full->diff($validated);

        $user->scopeExclusions()->where('source', $source)->delete();

        if ($excluded->isNotEmpty()) {
            $user->scopeExclusions()->createMany(
                $excluded->map(fn (int $cardId) => ['source' => $source, 'card_id' => $cardId])->all(),
            );
        }
    }

    /**
     * 错题本在册成员：最新评价为"忘记"/"模糊"的卡片，按原属卡组树顺序。
     *
     * @return array{forgotten: Collection<int, int>, fuzzy: Collection<int, int>}
     */
    public function mistakeMembership(User $user): array
    {
        $enrolled = $this->mistakeBook->enrolledRatings($user);

        return [
            'forgotten' => $this->inTreeOrder($enrolled->filter(fn (Rating $rating) => $rating === Rating::Forgotten)->keys()),
            'fuzzy' => $this->inTreeOrder($enrolled->filter(fn (Rating $rating) => $rating === Rating::Fuzzy)->keys()),
        ];
    }

    /**
     * 选卡页范围树（读投影）：自选卡按子卡组分组，错题本按「忘记/模糊」分组；
     * 卡片带勾选状态（排除推导）与错题本原属路径。
     *
     * @return array<int, array{id: int|string, name: string, cards: array<int, array{id: int, question: string, checked: bool, path?: string}>}>
     */
    public function tree(User $user, SourceType $source): array
    {
        return $source === SourceType::Mistake
            ? $this->mistakeTree($user)
            : $this->selectedTree($user);
    }

    /**
     * 错题本范围树：忘记/模糊两个子组，卡片按原属卡组树顺序并带路径。
     *
     * @return array<int, array{id: string, name: string, cards: array<int, array{id: int, question: string, checked: bool, path: string}>}>
     */
    private function mistakeTree(User $user): array
    {
        $excluded = $this->excludedCardIds($user, SourceType::Mistake);
        $membership = $this->mistakeMembership($user);

        return [
            $this->mistakeGroupPayload(Rating::Forgotten, $membership['forgotten'], $excluded),
            $this->mistakeGroupPayload(Rating::Fuzzy, $membership['fuzzy'], $excluded),
        ];
    }

    /**
     * @param  Collection<int, int>  $cardIds
     * @param  Collection<int, int>  $excluded
     * @return array{id: string, name: string, cards: array<int, array{id: int, question: string, checked: bool, path: string}>}
     */
    private function mistakeGroupPayload(Rating $rating, Collection $cardIds, Collection $excluded): array
    {
        $cards = Card::query()
            ->join('sections', 'cards.section_id', '=', 'sections.id')
            ->join('decks', 'sections.deck_id', '=', 'decks.id')
            ->whereIn('cards.id', $cardIds)
            ->orderBy('sections.position')
            ->orderBy('cards.position')
            ->get(['cards.id', 'cards.question', 'sections.name as section_name', 'decks.name as deck_name']);

        return [
            'id' => $rating->value,
            'name' => $rating->label(),
            'cards' => $cards
                ->map(fn (Card $card) => [
                    'id' => $card->id,
                    'question' => $card->question,
                    'path' => "{$card->deck_name} / {$card->section_name}",
                    'checked' => ! $excluded->contains($card->id),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * 自选卡范围树：子卡组分组，卡片带勾选状态。
     *
     * @return array<int, array{id: int, name: string, cards: array<int, array{id: int, question: string, checked: bool}>}>
     */
    private function selectedTree(User $user): array
    {
        $deckId = $user->selected_deck_id;

        abort_if($deckId === null, 422, '尚未选择自选卡');

        $excluded = $this->excludedCardIds($user, SourceType::Selected)->flip();

        return Deck::whereKey($deckId)
            ->firstOrFail()
            ->sections()
            ->with('cards')
            ->get()
            ->map(fn (Section $section) => [
                'id' => $section->id,
                'name' => $section->name,
                'cards' => $section->cards->map(fn (Card $card) => [
                    'id' => $card->id,
                    'question' => $card->question,
                    'checked' => ! $excluded->has($card->id),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * 某源的完整卡片集（未应用排除，卡组树顺序）。
     *
     * @return Collection<int, int>
     */
    private function fullCardIds(User $user, SourceType $source): Collection
    {
        if ($source === SourceType::Mistake) {
            $membership = $this->mistakeMembership($user);

            return $membership['forgotten']->merge($membership['fuzzy'])->unique();
        }

        $deckId = $user->selected_deck_id;

        abort_if($deckId === null, 422, '尚未选择自选卡');

        return Card::query()
            ->join('sections', 'cards.section_id', '=', 'sections.id')
            ->where('sections.deck_id', $deckId)
            ->orderBy('sections.position')
            ->orderBy('cards.position')
            ->pluck('cards.id');
    }

    /**
     * 某源中被取消勾选的卡片 ID（供范围树视图计算勾选状态）。
     *
     * @return Collection<int, int>
     */
    public function excludedCardIds(User $user, SourceType $source): Collection
    {
        return $user->scopeExclusions()
            ->where('source', $source)
            ->pluck('card_id');
    }

    /**
     * 单卡切换：按归属校验（不属于该源的卡 404；已排除的卡允许重新勾选）。
     *
     * @return Collection<int, int>
     */
    private function singleCardIds(User $user, SourceType $source, int $cardId): Collection
    {
        $belongs = $this->fullCardIds($user, $source)->contains($cardId);

        abort_unless($belongs, 404, '卡片不属于当前背诵源');

        return collect([$cardId]);
    }

    /**
     * 子卡组切换：错题本以 forgotten/fuzzy 标识映射子组，自选卡为真实子卡组。
     *
     * @return Collection<int, int>
     */
    private function sectionCardIds(User $user, SourceType $source, string $id): Collection
    {
        if ($source === SourceType::Mistake) {
            $membership = $this->mistakeMembership($user);

            return match ($id) {
                'forgotten' => $membership['forgotten'],
                'fuzzy' => $membership['fuzzy'],
                default => abort(422, '未知的错题本子卡组'),
            };
        }

        $deckId = $user->selected_deck_id;

        abort_if($deckId === null, 422, '尚未选择自选卡');

        $section = Section::where('deck_id', $deckId)->findOrFail((int) $id);

        return $section->cards()->pluck('id');
    }

    /**
     * 按原属卡组树顺序排序给定的卡片 ID 集合。
     *
     * @param  Collection<int, int>  $cardIds
     * @return Collection<int, int>
     */
    private function inTreeOrder(Collection $cardIds): Collection
    {
        if ($cardIds->isEmpty()) {
            return collect();
        }

        return Card::query()
            ->join('sections', 'cards.section_id', '=', 'sections.id')
            ->whereIn('cards.id', $cardIds)
            ->orderBy('sections.position')
            ->orderBy('cards.position')
            ->pluck('cards.id');
    }
}
