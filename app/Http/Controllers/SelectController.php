<?php

namespace App\Http\Controllers;

use App\Enums\SourceType;
use App\Models\Deck;
use App\Models\Section;
use App\Services\ScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SelectController extends Controller
{
    public function __construct(private readonly ScopeService $scope) {}

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
            'activeSource' => $user->active_source?->value,
            // 直接按外键查询，避免模型关系缓存导致的陈旧卡组
            'selectedScope' => $user->selected_deck_id !== null
                ? $this->scope->tree($user, SourceType::Selected)
                : null,
            'mistakeScope' => $this->scope->tree($user, SourceType::Mistake),
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
     * 设置当前背诵源（点击源 tab 即生效）；redirect 参数决定回跳页面。
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

        return to_route($request->input('redirect', 'select') === 'recite' ? 'recite' : 'select');
    }

    /**
     * 卡组摘要：名称、卡片总数与子卡组（含各自卡片数）。
     *
     * @param  Builder<Deck>  $query
     * @return Collection<int, array{id: int, name: string, cards_count: int, sections: Collection<int, array{id: int, name: string, cards_count: int}>}>
     */
    private function deckSummary(Builder $query)
    {
        return $query
            ->withCount('cards')
            ->with(['sections' => fn ($query) => $query->withCount('cards')])
            ->get()
            ->map(fn (Deck $deck) => [
                'id' => $deck->id,
                'name' => $deck->name,
                'cards_count' => $deck->cards_count,
                'sections' => $deck->sections->map(fn (Section $section) => [
                    'id' => $section->id,
                    'name' => $section->name,
                    'cards_count' => $section->cards_count,
                ]),
            ]);
    }
}
