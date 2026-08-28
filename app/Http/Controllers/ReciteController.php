<?php

namespace App\Http\Controllers;

use App\Enums\Rating;
use App\Enums\SourceType;
use App\Services\RecitationService;
use Illuminate\Http\RedirectResponse;
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
     */
    public function rate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'card_id' => ['required', 'integer'],
            'rating' => ['required', Rule::enum(Rating::class)],
        ]);

        $user = $request->user();
        $source = $user->active_source ?? SourceType::Selected;

        $this->service->rate($user, $source, $data['card_id'], Rating::from($data['rating']));

        return to_route('recite');
    }

    /**
     * 撤销上一次评价。
     */
    public function undo(Request $request): RedirectResponse
    {
        $user = $request->user();
        $source = $user->active_source ?? SourceType::Selected;

        $this->service->undo($user, $source);

        return to_route('recite');
    }
}
