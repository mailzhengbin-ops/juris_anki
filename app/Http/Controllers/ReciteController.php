<?php

namespace App\Http\Controllers;

use App\Enums\Rating;
use App\Enums\SourceType;
use App\Services\RecitationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReciteController extends Controller
{
    public function __construct(private readonly RecitationService $service) {}

    /**
     * 背诵页面：展示当前背诵源的背诵状态。
     */
    public function show(Request $request): Response
    {
        $user = $request->user();
        $source = $user->active_source ?? SourceType::Selected;

        return Inertia::render('recite/index', [
            'state' => $this->service->state($user, $source, $request->boolean('start')),
        ]);
    }

    /**
     * 评价当前卡片（认识/模糊/忘记），自动前进。
     * 直接渲染最新状态（无重定向），避免同一状态计算两遍。
     */
    public function rate(Request $request): Response
    {
        $data = $request->validate([
            'card_id' => ['required', 'integer'],
            'rating' => ['required', Rule::enum(Rating::class)],
        ]);

        $user = $request->user();
        $source = $user->active_source ?? SourceType::Selected;

        $state = $this->service->rate($user, $source, $data['card_id'], Rating::from($data['rating']));

        return Inertia::render('recite/index', [
            'state' => $state,
        ]);
    }

    /**
     * 撤销上一次评价。
     */
    public function undo(Request $request): Response
    {
        $user = $request->user();
        $source = $user->active_source ?? SourceType::Selected;

        $state = $this->service->undo($user, $source);

        return Inertia::render('recite/index', [
            'state' => $state,
        ]);
    }
}
