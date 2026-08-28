<?php

use App\Models\Card;
use App\Models\Deck;
use App\Models\ScopeExclusion;
use App\Models\Section;
use App\Models\User;

use function Pest\Laravel\actingAs;

function createDeckWithSections(User $user, int $cardsPerSection = 2): array
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

test('guests cannot toggle scope', function () {
    $this->post(route('scope.toggle'))->assertRedirect(route('login'));
});

test('every card is checked by default in the scope tree', function () {
    [$deck] = createDeckWithSections(User::factory()->create());

    $user = User::factory()->create();
    $user->update(['selected_deck_id' => $deck->id]);
    actingAs($user);

    $this->get(route('select'))
        ->assertInertia(fn ($page) => $page
            ->has('selectedScope.0.cards', 2)
            ->where('selectedScope.0.cards.0.checked', true)
            ->where('selectedScope.0.cards.1.checked', true)
        );
});

test('unchecking a card records an exclusion that persists', function () {
    [$deck, $sections] = createDeckWithSections(User::factory()->create());
    $card = $sections[0]->cards()->first();

    $user = User::factory()->create();
    $user->update(['selected_deck_id' => $deck->id]);
    actingAs($user);

    $this->post(route('scope.toggle'), [
        'source' => 'selected',
        'type' => 'card',
        'id' => $card->id,
        'checked' => false,
    ])->assertRedirect(route('select'));

    expect(ScopeExclusion::where('user_id', $user->id)->where('card_id', $card->id)->exists())->toBeTrue();

    $this->get(route('select'))
        ->assertInertia(fn ($page) => $page
            ->where('selectedScope.0.cards.0.checked', false)
            ->where('selectedScope.0.cards.1.checked', true)
        );
});

test('rechecking a card removes the exclusion', function () {
    [$deck, $sections] = createDeckWithSections(User::factory()->create());
    $card = $sections[0]->cards()->first();
    ScopeExclusion::create(['user_id' => $deck->user_id, 'card_id' => $card->id]);

    $user = $deck->owner;
    $user->update(['selected_deck_id' => $deck->id]);
    actingAs($user);

    $this->post(route('scope.toggle'), [
        'source' => 'selected',
        'type' => 'card',
        'id' => $card->id,
        'checked' => true,
    ])->assertRedirect(route('select'));

    expect(ScopeExclusion::where('user_id', $user->id)->where('card_id', $card->id)->exists())->toBeFalse();
});

test('unchecking a section excludes all of its cards', function () {
    [$deck, $sections] = createDeckWithSections(User::factory()->create());

    $user = User::factory()->create();
    $user->update(['selected_deck_id' => $deck->id]);
    actingAs($user);

    $this->post(route('scope.toggle'), [
        'source' => 'selected',
        'type' => 'section',
        'id' => $sections[0]->id,
        'checked' => false,
    ])->assertRedirect(route('select'));

    expect(ScopeExclusion::where('user_id', $user->id)->count())->toBe(2)
        ->and($sections[1]->cards()->whereNotIn('id', ScopeExclusion::pluck('card_id'))->count())->toBe(2);
});

test('clearing the whole source then selecting all restores every card', function () {
    [$deck, $sections] = createDeckWithSections(User::factory()->create());

    $user = User::factory()->create();
    $user->update(['selected_deck_id' => $deck->id]);
    actingAs($user);

    $this->post(route('scope.toggle'), [
        'source' => 'selected',
        'type' => 'source',
        'checked' => false,
    ])->assertRedirect(route('select'));

    expect(ScopeExclusion::where('user_id', $user->id)->count())->toBe(4);

    $this->post(route('scope.toggle'), [
        'source' => 'selected',
        'type' => 'source',
        'checked' => true,
    ])->assertRedirect(route('select'));

    expect(ScopeExclusion::where('user_id', $user->id)->count())->toBe(0);
});

test('toggling requires a selected deck', function () {
    $user = User::factory()->create();
    actingAs($user);

    $this->post(route('scope.toggle'), [
        'source' => 'selected',
        'type' => 'source',
        'checked' => false,
    ])->assertStatus(422);
});

test('toggling a card outside the selected deck returns 404', function () {
    [$deck] = createDeckWithSections(User::factory()->create());
    [$otherDeck, $otherSections] = createDeckWithSections(User::factory()->create());
    $foreignCard = $otherSections[0]->cards()->first();

    $user = User::factory()->create();
    $user->update(['selected_deck_id' => $deck->id]);
    actingAs($user);

    $this->post(route('scope.toggle'), [
        'source' => 'selected',
        'type' => 'card',
        'id' => $foreignCard->id,
        'checked' => false,
    ])->assertNotFound();
});

test('the mistake source is not available yet', function () {
    [$deck] = createDeckWithSections(User::factory()->create());

    $user = User::factory()->create();
    $user->update(['selected_deck_id' => $deck->id]);
    actingAs($user);

    $this->post(route('scope.toggle'), [
        'source' => 'mistake',
        'type' => 'source',
        'checked' => false,
    ])->assertStatus(422);
});

test('switching decks keeps exclusions per deck', function () {
    [$deckA] = createDeckWithSections(User::factory()->create());
    [$deckB] = createDeckWithSections(User::factory()->create());

    $user = User::factory()->create();
    $user->update(['selected_deck_id' => $deckA->id]);
    actingAs($user);

    $firstCardA = $deckA->cards()->first();
    $this->post(route('scope.toggle'), [
        'source' => 'selected',
        'type' => 'card',
        'id' => $firstCardA->id,
        'checked' => false,
    ]);

    $user->update(['selected_deck_id' => $deckB->id]);

    $this->get(route('select'))
        ->assertInertia(fn ($page) => $page
            ->where('selectedScope.0.cards.0.checked', true)
            ->where('selectedScope.0.cards.1.checked', true)
        );

    $user->update(['selected_deck_id' => $deckA->id]);

    $this->get(route('select'))
        ->assertInertia(fn ($page) => $page
            ->where('selectedScope.0.cards.0.checked', false)
        );
});

test('clicking a source tab sets the active source', function () {
    $user = User::factory()->create();
    actingAs($user);

    $this->post(route('select.source'), ['source' => 'selected'])
        ->assertRedirect(route('select'));

    expect($user->fresh()->active_source->value)->toBe('selected');

    $this->get(route('select'))
        ->assertInertia(fn ($page) => $page->where('activeSource', 'selected'));
});
