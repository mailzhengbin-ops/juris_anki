<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Deck;
use App\Models\Section;
use App\Services\RecitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ScopeController extends Controller
{
    public function __construct(private readonly RecitationService $recitation) {}

    /**
     * 切换背诵范围的勾选状态（点选即保存，实时生效）。
     *
     * type：card=单卡 | section=整子卡组（错题本为 forgotten/fuzzy 子组）| deck=整卡组 | source=当前源全部（全选/清空）
     */
    public function toggle(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source' => ['required', Rule::in(['selected', 'mistake'])],
            'type' => ['required', Rule::in(['card', 'section', 'deck', 'source'])],
            'id' => ['nullable'],
            'checked' => ['required', 'boolean'],
        ]);

        $user = $request->user();

        if ($data['source'] === 'selected') {
            $deck = $user->selectedDeck;

            abort_if($deck === null, 422, '尚未选择自选卡');

            $cardIds = $this->cardIdsForToggle($deck, $data['type'], $request->input('id'));
        } else {
            $cardIds = $this->mistakeCardIdsForToggle($user, $data['type'], $request->input('id'));
        }

        if ($data['checked']) {
            $user->scopeExclusions()->whereIn('card_id', $cardIds)->delete();
        } else {
            $user->scopeExclusions()->createMany(
                $cardIds->map(fn (int $cardId) => ['card_id' => $cardId])->all(),
            );
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $data['checked'] ? '已加入背诵范围' : '已移出背诵范围',
        ]);

        return to_route('select');
    }

    /**
     * 计算本次切换影响的卡片 ID 集合（按类型展开）。
     *
     * @return Collection<int, int>
     */
    private function cardIdsForToggle(Deck $deck, string $type, mixed $id): Collection
    {
        $sectionIds = $deck->sections()->pluck('sections.id');

        return match ($type) {
            'card' => collect([
                Card::whereKey($id)
                    ->whereIn('section_id', $sectionIds)
                    ->firstOrFail()
                    ->id,
            ]),
            'section' => Section::whereKey($id)
                ->where('deck_id', $deck->id)
                ->firstOrFail()
                ->cards()
                ->pluck('id'),
            'deck', 'source' => Card::whereIn('section_id', $sectionIds)->pluck('id'),
        };
    }

    /**
     * 错题本源的切换：section 的 id 为 forgotten/fuzzy 子组标识。
     *
     * @return Collection<int, int>
     */
    private function mistakeCardIdsForToggle($user, string $type, mixed $id): Collection
    {
        $membership = $this->recitation->mistakeMembership($user);
        $inBook = $membership['forgotten']->merge($membership['fuzzy'])->unique();

        return match ($type) {
            'card' => collect([
                Card::whereKey($id)
                    ->whereIn('id', $inBook)
                    ->firstOrFail()
                    ->id,
            ]),
            'section' => match ($id) {
                'forgotten' => $membership['forgotten'],
                'fuzzy' => $membership['fuzzy'],
                default => abort(422, '未知的错题本子卡组'),
            },
            'deck', 'source' => $inBook,
        };
    }
}
