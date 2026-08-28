<?php

namespace App\Http\Controllers;

use App\Enums\SourceType;
use App\Services\ScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * 范围勾选的 HTTP adapter：翻译请求 → 委托 Scope 深模块。
 */
class ScopeController extends Controller
{
    public function __construct(private readonly ScopeService $scope) {}

    /**
     * 切换背诵范围的勾选状态（点选即保存，实时生效）。
     *
     * type：card=单卡 | section=整子卡组（错题本为 forgotten/fuzzy 子组标识）| deck=整卡组 | source=当前源全部
     */
    public function toggle(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source' => ['required', Rule::in(['selected', 'mistake'])],
            'type' => ['required', Rule::in(['card', 'section', 'deck', 'source'])],
            'id' => ['nullable'],
            'checked' => ['required', 'boolean'],
        ]);

        $this->scope->toggle(
            $request->user(),
            SourceType::from($data['source']),
            $data['type'],
            $request->input('id'),
            $data['checked'],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $data['checked'] ? '已加入背诵范围' : '已移出背诵范围',
        ]);

        return to_route('select');
    }
}
