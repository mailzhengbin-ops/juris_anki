<?php

namespace App\Http\Controllers;

use App\Models\Deck;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class SelectController extends Controller
{
    /**
     * 展示选卡页面（卡组仓库 + 当前自选卡）。
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
