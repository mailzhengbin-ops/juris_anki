<?php

namespace App\Http\Controllers;

use App\Enums\SourceType;
use App\Models\Deck;
use App\Models\ScopeExclusion;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SelectController extends Controller
{
    /**
     * 展示选卡页面（卡组仓库 + 当前背诵源 + 背诵范围）。
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('select/index', [
            'warehouse' => [
                'systemDecks' => $this->deckSummary(Deck::system()),
                'userDecks' => $this->deckSummary(Deck::ownedBy($user)),
            ],
            'selectedDeck' => $user->selected_deck_id !== null
                ? $this->deckSummary(Deck::whereKey($user->selected_deck_id))->first()
                : null,
            'activeSource' => $user->active_source?->value ?? null,
            // 直接按外键查询，避免模型关系缓存导致的陈旧卡组
            'selectedScope' => $user->selected_deck_id !== null
                ? $this->scopeTree($user, Deck::findOrFail($user->selected_deck_id))
                : null,
        ]);
    }

    /**
     * 将卡组设为当前自选卡（用户维度唯一）。
     */
    public function setSelectedDeck(Request $request): RedirectResponse
    {
        $deck = Deck::findOrFail($request->integer('deck_id'));

        abort_unless($deck->isSystem() || $deck->user_id === $request->user()->id, 403);

        $request->user()->update(['selected_deck_id' => $deck->id]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('已将「%s」设为自选卡', $deck->name),
        ]);

        return to_route('select');
    }

    /**
     * 设置当前背诵源（点击源 tab 即生效）。
     */
    public function setActiveSource(Request $request): RedirectResponse
    {
        $source = $request->validate([
            'source' => ['required', Rule::in(['selected', 'mistake'])],
        ])['source'];

        $request->user()->update(['active_source' => SourceType::from($source)]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => SourceType::from($source)->label(),
        ]);

        return to_route('select');
    }

    /**
     * 自选卡源的背诵范围卡组树（卡片级勾选状态）。
     *
     * @return array<int, array{id: int, name: string, cards: array<int, array{id: int, question: string, checked: bool}>}>
     */
    private function scopeTree(User $user, Deck $deck): array
    {
        $excluded = ScopeExclusion::where('user_id', $user->id)
            ->whereIn('card_id', $deck->cards()->pluck('cards.id'))
            ->pluck('card_id')
            ->flip();

        return $deck->sections()
            ->with(['cards' => fn ($q) => $q->orderBy('position')->select('id', 'section_id', 'question')])
            ->get()
            ->map(fn ($section) => [
                'id' => $section->id,
                'name' => $section->name,
                'cards' => $section->cards->map(fn ($card) => [
                    'id' => $card->id,
                    'question' => $card->question,
                    'checked' => ! $excluded->has($card->id),
                ])->values(),
            ])
            ->values()
            ->all();
    }

    /**
     * 卡组摘要：名称、卡片总数与子卡组（含各自卡片数）。
     *
     * @return Collection<int, array{id: int, name: string, cards_count: int, sections: Collection<int, array{id: int, name: string, cards_count: int}>}>
     */
    private function deckSummary($query)
    {
        return $query
            ->withCount('cards')
            ->with(['sections' => fn ($q) => $q->withCount('cards')->orderBy('position')])
            ->get()
            ->map(fn (Deck $deck) => [
                'id' => $deck->id,
                'name' => $deck->name,
                'cards_count' => $deck->cards_count,
                'sections' => $deck->sections->map(fn ($section) => [
                    'id' => $section->id,
                    'name' => $section->name,
                    'cards_count' => $section->cards_count,
                ]),
            ]);
    }
}
