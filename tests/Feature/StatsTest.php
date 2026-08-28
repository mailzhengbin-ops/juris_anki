<?php

use App\Models\Card;
use App\Models\Deck;
use App\Models\Evaluation;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

function createEvaluation(User $user, Card $card, string $rating, string $at, string $timezone = 'UTC'): void
{
    $task = Task::create([
        'user_id' => $user->id,
        'source_type' => 'selected',
        'source_deck_id' => $card->section->deck_id,
        'started_at' => now(),
    ]);

    Evaluation::create([
        'user_id' => $user->id,
        'card_id' => $card->id,
        'task_id' => $task->id,
        'rating' => $rating,
        'created_at' => Carbon::parse($at, $timezone)->utc(),
    ]);
}

function createStatCard(User $user): Card
{
    $deck = Deck::factory()->ownedBy($user)->create(['name' => '刑法']);
    $section = Section::factory()->create(['deck_id' => $deck->id, 'name' => '绪论', 'position' => 1]);

    return Card::factory()->create(['section_id' => $section->id, 'position' => 1]);
}

test('guests are redirected from the stats page', function () {
    $this->get(route('stats'))->assertRedirect(route('login'));
});

test('an empty week renders seven zeroed days', function () {
    $user = User::factory()->create();
    actingAs($user);

    $this->get(route('stats'))
        ->assertInertia(fn ($page) => $page
            ->has('stats.days', 7)
            ->where('stats.days.6.known', 0)
            ->where('stats.days.6.fuzzy', 0)
            ->where('stats.days.6.forgotten', 0)
            ->where('stats.totals.known', 0)
        );
});

test('evaluations are bucketed by day with per-rating counts', function () {
    $user = User::factory()->create();
    actingAs($user);
    $known = createStatCard($user);
    $fuzzy = createStatCard($user);
    $forgotten = createStatCard($user);
    $today = now()->toDateString();

    createEvaluation($user, $known, 'known', $today.' 10:00');
    createEvaluation($user, $fuzzy, 'fuzzy', $today.' 11:00');
    createEvaluation($user, $forgotten, 'forgotten', $today.' 12:00');

    $this->get(route('stats'))
        ->assertInertia(fn ($page) => $page
            ->where('stats.days.6.known', 1)
            ->where('stats.days.6.fuzzy', 1)
            ->where('stats.days.6.forgotten', 1)
            ->where('stats.totals.known', 1)
            ->where('stats.totals.fuzzy', 1)
            ->where('stats.totals.forgotten', 1)
        );
});

test('the same card rated multiple times on one day counts only the last rating', function () {
    $user = User::factory()->create();
    actingAs($user);
    $card = createStatCard($user);
    $today = now()->toDateString();

    createEvaluation($user, $card, 'known', $today.' 10:00');
    createEvaluation($user, $card, 'known', $today.' 11:00');
    createEvaluation($user, $card, 'fuzzy', $today.' 12:00');

    $this->get(route('stats'))
        ->assertInertia(fn ($page) => $page
            ->where('stats.days.6.fuzzy', 1)
            ->where('stats.days.6.known', 0)
            ->where('stats.days.6.forgotten', 0)
        );
});

test('different cards on the same day each count once', function () {
    $user = User::factory()->create();
    actingAs($user);
    $first = createStatCard($user);
    $second = createStatCard($user);
    $today = now()->toDateString();

    createEvaluation($user, $first, 'forgotten', $today.' 10:00');
    createEvaluation($user, $second, 'forgotten', $today.' 12:00');

    $this->get(route('stats'))
        ->assertInertia(fn ($page) => $page->where('stats.days.6.forgotten', 2));
});

test('days are bucketed by the requested timezone', function () {
    $user = User::factory()->create();
    actingAs($user);
    $card = createStatCard($user);

    // UTC 23:30 → 上海时区次日 07:30：UTC 视角在今天桶内，上海视角已属"明天"（范围外）
    createEvaluation($user, $card, 'known', now()->utc()->startOfDay()->addHours(23)->addMinutes(30)->toDateTimeString(), 'UTC');

    $this->get(route('stats', ['tz' => 'UTC']))
        ->assertInertia(fn ($page) => $page
            ->where('stats.days.6.known', 1)
            ->where('timezone', 'UTC')
        );

    $this->get(route('stats', ['tz' => 'Asia/Shanghai']))
        ->assertInertia(fn ($page) => $page
            ->where('stats.days.6.known', 0)
            ->where('timezone', 'Asia/Shanghai')
        );
});

test('evaluations from all sources are counted', function () {
    $user = User::factory()->create();
    actingAs($user);
    $card = createStatCard($user);
    $today = now()->toDateString();

    createEvaluation($user, $card, 'known', $today.' 10:00');
    $mistakeTask = Task::create([
        'user_id' => $user->id,
        'source_type' => 'mistake',
        'started_at' => now(),
    ]);
    Evaluation::create([
        'user_id' => $user->id,
        'card_id' => $card->id,
        'task_id' => $mistakeTask->id,
        'rating' => 'forgotten',
        'created_at' => Carbon::parse($today.' 11:00')->utc(),
    ]);

    // 同卡同日：只计最后一次（mistake 任务的 forgotten）
    $this->get(route('stats'))
        ->assertInertia(fn ($page) => $page
            ->where('stats.days.6.forgotten', 1)
            ->where('stats.days.6.known', 0)
        );
});

test('an invalid timezone falls back to UTC', function () {
    $user = User::factory()->create();
    actingAs($user);

    $this->get(route('stats', ['tz' => 'Not/AZone']))
        ->assertInertia(fn ($page) => $page->where('timezone', 'UTC'));
});
