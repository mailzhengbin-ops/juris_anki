<?php

use App\Models\Card;
use App\Models\Deck;
use App\Models\Evaluation;
use App\Models\ScopeExclusion;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;

use function Pest\Laravel\actingAs;

function applyCreateDeckWithSections(User $user, int $cardsPerSection = 2): array
{
    $deck = Deck::factory()->ownedBy($user)->create(['name' => '刑法']);
    $sections = [];

    foreach (['绪论', '犯罪构成'] as $i => $name) {
        $section = Section::factory()->create([
            'deck_id' => $deck->id,
            'name' => $name,
            'position' => $i + 1,
        ]);
        Card::factory()->count($cardsPerSection)->create([
            'section_id' => $section->id,
            'position' => 0,
        ]);
        $sections[] = $section;
    }

    return [$deck, $sections];
}

test('guests cannot apply scope', function () {
    $this->post(route('scope.apply'), ['source' => 'selected', 'card_ids' => []])
        ->assertRedirect(route('login'));
});

test('applying a card set replaces the whole scope', function () {
    [$deck, $sections] = applyCreateDeckWithSections(User::factory()->create());
    $user = $deck->owner;
    $user->update(['selected_deck_id' => $deck->id]);
    $first = $sections[0]->cards()->first();
    $keep = $sections[1]->cards()->first();
    ScopeExclusion::create(['user_id' => $user->id, 'source' => 'selected', 'card_id' => $first->id]);

    actingAs($user);
    $this->post(route('scope.apply'), [
        'source' => 'selected',
        'card_ids' => [$keep->id],
    ])->assertRedirect(route('select'));

    $excluded = ScopeExclusion::where('user_id', $user->id)
        ->where('source', 'selected')
        ->pluck('card_id');

    expect($excluded)->toHaveCount(3)
        ->and($excluded)->toContain($first->id)
        ->and($excluded)->not->toContain($keep->id);
});

test('applying every card restores the whole scope', function () {
    [$deck, $sections] = applyCreateDeckWithSections(User::factory()->create());
    $user = $deck->owner;
    $user->update(['selected_deck_id' => $deck->id]);
    $first = $sections[0]->cards()->first();
    ScopeExclusion::create(['user_id' => $user->id, 'source' => 'selected', 'card_id' => $first->id]);

    actingAs($user);
    $this->post(route('scope.apply'), [
        'source' => 'selected',
        'card_ids' => $deck->cards()->pluck('cards.id')->all(),
    ])->assertRedirect(route('select'));

    expect(ScopeExclusion::where('user_id', $user->id)->where('source', 'selected')->count())->toBe(0);
});

test('applying an empty set excludes every card', function () {
    [$deck] = applyCreateDeckWithSections(User::factory()->create());
    $user = $deck->owner;
    $user->update(['selected_deck_id' => $deck->id]);

    actingAs($user);
    $this->post(route('scope.apply'), [
        'source' => 'selected',
        'card_ids' => [],
    ])->assertRedirect(route('select'));

    expect(ScopeExclusion::where('user_id', $user->id)->where('source', 'selected')->count())->toBe(4);
});

test('applying requires a selected deck', function () {
    $user = User::factory()->create();
    actingAs($user);

    $this->post(route('scope.apply'), [
        'source' => 'selected',
        'card_ids' => [],
    ])->assertStatus(422);
});

test('applying a card outside the selected deck is rejected', function () {
    [$deck] = applyCreateDeckWithSections(User::factory()->create());
    [$otherDeck, $otherSections] = applyCreateDeckWithSections(User::factory()->create());
    $foreignCard = $otherSections[0]->cards()->first();

    $user = User::factory()->create();
    $user->update(['selected_deck_id' => $deck->id]);
    actingAs($user);

    $this->post(route('scope.apply'), [
        'source' => 'selected',
        'card_ids' => [$foreignCard->id],
    ])->assertStatus(422);

    expect(ScopeExclusion::where('user_id', $user->id)->where('source', 'selected')->count())->toBe(0);
});

test('the mistake source scope can be applied', function () {
    [$deck, $sections] = applyCreateDeckWithSections(User::factory()->create());
    $inBook = $sections[0]->cards()->first();
    $user = $deck->owner;
    $user->update(['selected_deck_id' => $deck->id]);
    $task = Task::create(['user_id' => $user->id, 'source_type' => 'mistake', 'started_at' => now()]);
    Evaluation::create([
        'user_id' => $user->id,
        'card_id' => $inBook->id,
        'task_id' => $task->id,
        'rating' => 'forgotten',
    ]);

    // 先取消勾选，再确认清空：全量替换应排除在册卡
    actingAs($user);
    $this->post(route('scope.apply'), [
        'source' => 'mistake',
        'card_ids' => [],
    ])->assertRedirect(route('select'));

    expect(ScopeExclusion::where('user_id', $user->id)->where('source', 'mistake')->pluck('card_id'))
        ->toContain($inBook->id);

    // 确认恢复勾选：排除被清空
    $this->post(route('scope.apply'), [
        'source' => 'mistake',
        'card_ids' => [$inBook->id],
    ])->assertRedirect(route('select'));

    expect(ScopeExclusion::where('user_id', $user->id)->where('source', 'mistake')->count())->toBe(0);
});
