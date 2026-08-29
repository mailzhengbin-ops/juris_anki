<?php

namespace App\Http\Controllers;

use App\Models\Deck;
use App\Services\DeckImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeckController extends Controller
{
    /**
     * 上传 markdown 文档一键导入创建用户卡组。
     */
    public function import(Request $request, DeckImportService $service): RedirectResponse
    {
        $request->validate([
            'document' => ['required', 'file'],
        ]);

        $deck = $service->importFor(
            $request->user(),
            $request->file('document')->getContent(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('卡组「%s」导入成功', $deck->name),
        ]);

        return to_route('select');
    }

    /**
     * 删除自己的用户卡组（级联删除子卡组/卡片/范围勾选，评价记录保留用于统计；
     * 若为当前自选卡，selected_deck_id 由外键 nullOnDelete 自动清空）。
     */
    public function destroy(Request $request, Deck $deck): RedirectResponse
    {
        abort_unless($deck->user_id === $request->user()->id, 403);

        $name = $deck->name;

        $deck->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('卡组「%s」已删除', $name),
        ]);

        return to_route('select');
    }
}
