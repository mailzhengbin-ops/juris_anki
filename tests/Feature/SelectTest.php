<?php

use App\Models\Card;
use App\Models\Deck;
use App\Models\Section;
use App\Models\User;

use function Pest\Laravel\actingAs;

function createDeckWithCards(?User $owner, string $name, array $sectionCards): Deck
{
    $deck = Deck::factory()->create([
        'user_id' => $owner?->id,
        'name' => $name,
    ]);

    foreach ($sectionCards as $sectionName => $cardCount) {
        $section = Section::factory()->create([
            'deck_id' => $deck->id,
            'name' => $sectionName,
            'position' => $deck->sections()->count() + 1,
        ]);

        Card::factory()->count($cardCount)->create([
            'section_id' => $section->id,
            'position' => 0,
        ]);
    }

    return $deck;
}

test('guests are redirected to the login page when visiting the select page', function () {
    $this->get(route('select'))->assertRedirect(route('login'));
});

test('the warehouse lists system decks with card and section counts', function () {
    createDeckWithCards(null, '刑法', ['绪论' => 3, '犯罪构成' => 2]);

    $user = User::factory()->create();
    actingAs($user);

    $response = $this->get(route('select'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('select/index')
            ->has('warehouse.systemDecks', 1)
            ->where('warehouse.systemDecks.0.name', '刑法')
            ->where('warehouse.systemDecks.0.cards_count', 5)
            ->has('warehouse.systemDecks.0.sections', 2)
            ->where('warehouse.systemDecks.0.sections.0.name', '绪论')
            ->where('warehouse.systemDecks.0.sections.0.cards_count', 3)
            ->where('warehouse.systemDecks.0.sections.1.name', '犯罪构成')
            ->where('warehouse.systemDecks.0.sections.1.cards_count', 2)
        );
});

test('the warehouse lists only the current user\'s user decks', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    createDeckWithCards($owner, '我的民法', ['总则' => 4]);
    createDeckWithCards($other, '别人的卡组', ['分则' => 2]);

    actingAs($owner);

    $response = $this->get(route('select'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('select/index')
            ->has('warehouse.userDecks', 1)
            ->where('warehouse.userDecks.0.name', '我的民法')
            ->where('warehouse.userDecks.0.cards_count', 4)
        );
});

test('a user can set a system deck as their selected deck', function () {
    $deck = createDeckWithCards(null, '刑法', ['绪论' => 3]);

    $user = User::factory()->create();
    actingAs($user);

    $response = $this->post(route('select.deck'), ['deck_id' => $deck->id]);

    $response->assertRedirect(route('select'));
    expect($user->fresh()->selected_deck_id)->toBe($deck->id);
});

test('a user can set their own user deck as the selected deck', function () {
    $user = User::factory()->create();
    $deck = createDeckWithCards($user, '我的民法', ['总则' => 1]);

    actingAs($user);

    $this->post(route('select.deck'), ['deck_id' => $deck->id])
        ->assertRedirect(route('select'));

    expect($user->fresh()->selected_deck_id)->toBe($deck->id);
});

test('a user cannot set another user\'s deck as their selected deck', function () {
    $other = User::factory()->create();
    $deck = createDeckWithCards($other, '别人的卡组', ['分则' => 1]);

    $user = User::factory()->create();
    actingAs($user);

    $this->post(route('select.deck'), ['deck_id' => $deck->id])->assertForbidden();

    expect($user->fresh()->selected_deck_id)->toBeNull();
});

test('setting a nonexistent deck returns 404', function () {
    $user = User::factory()->create();
    actingAs($user);

    $this->post(route('select.deck'), ['deck_id' => 9999])->assertNotFound();
});

test('the selected deck is unique per user', function () {
    $deckA = createDeckWithCards(null, '刑法', ['绪论' => 2]);
    $deckB = createDeckWithCards(null, '民法', ['总则' => 2]);

    $user = User::factory()->create();
    actingAs($user);

    $this->post(route('select.deck'), ['deck_id' => $deckA->id]);
    $this->post(route('select.deck'), ['deck_id' => $deckB->id]);

    expect($user->fresh()->selected_deck_id)->toBe($deckB->id);
});
