<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Deck;
use App\Models\Section;
use App\Services\DeckImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminDeckController extends Controller
{
    /**
     * 系统卡组列表。
     */
    public function index(): Response
    {
        $decks = Deck::system()
            ->withCount(['cards', 'sections'])
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/decks/index', [
            'decks' => $decks->map(fn (Deck $deck) => [
                'id' => $deck->id,
                'name' => $deck->name,
                'cards_count' => $deck->cards_count,
                'sections_count' => $deck->sections_count,
            ]),
        ]);
    }

    /**
     * 通过 markdown 文档导入创建系统卡组。
     */
    public function import(Request $request, DeckImportService $service): RedirectResponse
    {
        $request->validate([
            'document' => ['required', 'file'],
        ]);

        $deck = $service->importFor(null, $request->file('document')->getContent());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('系统卡组「%s」导入成功', $deck->name),
        ]);

        return to_route('admin.decks');
    }

    /**
     * 系统卡组详情（子卡组与卡片全量）。
     */
    public function show(Deck $deck): Response
    {
        $this->assertSystemDeck($deck);

        return Inertia::render('admin/decks/show', [
            'deck' => [
                'id' => $deck->id,
                'name' => $deck->name,
                'sections' => $deck->sections()
                    ->with('cards')
                    ->get()
                    ->map(fn (Section $section) => [
                        'id' => $section->id,
                        'name' => $section->name,
                        'cards' => $section->cards->map(fn (Card $card) => [
                            'id' => $card->id,
                            'question' => $card->question,
                            'answer' => $card->answer,
                        ])->values(),
                    ]),
            ],
        ]);
    }

    /**
     * 原位编辑卡组名。
     */
    public function update(Request $request, Deck $deck): RedirectResponse
    {
        $this->assertSystemDeck($deck);

        $name = $request->validate(['name' => ['required', 'string', 'max:100']])['name'];

        if (Deck::system()->where('name', $name)->whereKeyNot($deck->id)->exists()) {
            throw ValidationException::withMessages(['name' => '已存在同名系统卡组']);
        }

        $deck->update(['name' => $name]);

        Inertia::flash('toast', ['type' => 'success', 'message' => '卡组名已更新']);

        return to_route('admin.decks.show', $deck);
    }

    /**
     * 原位编辑子卡组名。
     */
    public function updateSection(Request $request, Section $section): RedirectResponse
    {
        $this->assertSystemDeck($section->deck);

        $section->update($request->validate(['name' => ['required', 'string', 'max:100']]));

        Inertia::flash('toast', ['type' => 'success', 'message' => '子卡组名已更新']);

        return to_route('admin.decks.show', $section->deck_id);
    }

    /**
     * 原位编辑卡片问题与答案（卡片按 ID 保持身份，用户侧评价/在册/进度不受影响）。
     */
    public function updateCard(Request $request, Card $card): RedirectResponse
    {
        $this->assertSystemDeck($card->section->deck);

        $card->update($request->validate([
            'question' => ['required', 'string'],
            'answer' => ['required', 'string'],
        ]));

        Inertia::flash('toast', ['type' => 'success', 'message' => '卡片已更新']);

        return to_route('admin.decks.show', $card->section->deck_id);
    }

    /**
     * 删除系统卡组（级联子卡组/卡片；评价记录保留用于统计）。
     */
    public function destroy(Deck $deck): RedirectResponse
    {
        $this->assertSystemDeck($deck);

        $name = $deck->name;
        $deck->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => sprintf('系统卡组「%s」已删除', $name)]);

        return to_route('admin.decks');
    }

    /**
     * 删除子卡组（级联卡片）。
     */
    public function destroySection(Section $section): RedirectResponse
    {
        $this->assertSystemDeck($section->deck);

        $section->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => '子卡组已删除']);

        return to_route('admin.decks.show', $section->deck_id);
    }

    /**
     * 删除卡片（从所有用户的范围与错题本中消失，评价记录保留）。
     */
    public function destroyCard(Card $card): RedirectResponse
    {
        $this->assertSystemDeck($card->section->deck);

        $card->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => '卡片已删除']);

        return to_route('admin.decks.show', $card->section->deck_id);
    }

    private function assertSystemDeck(?Deck $deck): void
    {
        abort_unless($deck !== null && $deck->isSystem(), 403);
    }
}
