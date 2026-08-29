<?php

use App\Models\Card;
use App\Models\Deck;
use App\Models\Evaluation;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\UploadedFile;

use function Pest\Laravel\actingAs;

const SYSTEM_DOCUMENT = <<<'MD'
# 民法

## 总则

### 民法的概念
```
民法是调整平等主体之间人身关系和财产关系的法律规范的总称。
```
MD;

function deckAdmin(): User
{
    return User::factory()->admin()->create();
}

function createSystemDeck(string $name = '刑法'): Deck
{
    return Deck::factory()->system()->create(['name' => $name]);
}

test('guests are redirected from admin deck routes', function () {
    $this->get(route('admin.decks'))->assertRedirect(route('login'));
    $this->post(route('admin.decks.import'))->assertRedirect(route('login'));
});

test('regular users are forbidden from admin deck routes', function () {
    $user = User::factory()->create();
    actingAs($user);

    $deck = createSystemDeck();

    $this->get(route('admin.decks'))->assertForbidden();
    $this->post(route('admin.decks.import'))->assertForbidden();
    $this->get(route('admin.decks.show', $deck))->assertForbidden();
    $this->patch(route('admin.decks.update', $deck))->assertForbidden();
    $this->delete(route('admin.decks.destroy', $deck))->assertForbidden();
});

test('an admin can import a system deck from markdown', function () {
    actingAs(deckAdmin());

    $this->post(route('admin.decks.import'), [
        'document' => UploadedFile::fake()->createWithContent('minfa.md', SYSTEM_DOCUMENT),
    ])->assertRedirect(route('admin.decks'));

    $deck = Deck::where('name', '民法')->first();
    expect($deck)->not->toBeNull()
        ->and($deck->user_id)->toBeNull()
        ->and($deck->sections()->first()->cards)->toHaveCount(1);
});

test('an admin can rename a system deck and sections', function () {
    $deck = createSystemDeck();
    $section = Section::factory()->create(['deck_id' => $deck->id, 'name' => '绪论', 'position' => 1]);
    actingAs(deckAdmin());

    $this->patch(route('admin.decks.update', $deck), ['name' => '刑法（新版）'])
        ->assertRedirect();

    expect($deck->fresh()->name)->toBe('刑法（新版）');

    $this->patch(route('admin.sections.update', $section), ['name' => '导论'])
        ->assertRedirect();

    expect($section->fresh()->name)->toBe('导论');
});

test('duplicate system deck names are rejected', function () {
    createSystemDeck('刑法');
    $other = createSystemDeck('民法');
    actingAs(deckAdmin());

    $this->patch(route('admin.decks.update', $other), ['name' => '刑法'])
        ->assertSessionHasErrors('name');
});

test('system and user decks may share a name', function () {
    Deck::factory()->ownedBy(User::factory()->create())->create(['name' => '刑法']);
    $deck = createSystemDeck('民法');
    actingAs(deckAdmin());

    $this->patch(route('admin.decks.update', $deck), ['name' => '刑法'])
        ->assertRedirect();

    expect($deck->fresh()->name)->toBe('刑法');
});

test('an admin can edit a card without disturbing user-side data', function () {
    $deck = createSystemDeck();
    $section = Section::factory()->create(['deck_id' => $deck->id, 'name' => '绪论', 'position' => 1]);
    $card = Card::factory()->create(['section_id' => $section->id, 'question' => '旧问题', 'answer' => '旧答案', 'position' => 1]);

    $user = User::factory()->create();
    $task = Task::create([
        'user_id' => $user->id,
        'source_type' => 'selected',
        'source_deck_id' => $deck->id,
        'started_at' => now(),
    ]);
    $evaluation = Evaluation::create([
        'user_id' => $user->id,
        'card_id' => $card->id,
        'task_id' => $task->id,
        'rating' => 'forgotten',
    ]);
    actingAs(deckAdmin());

    $this->patch(route('admin.cards.update', $card), [
        'question' => '新问题',
        'answer' => '新答案',
    ])->assertRedirect();

    expect($card->fresh()->question)->toBe('新问题')
        ->and($evaluation->fresh()->rating->value)->toBe('forgotten')
        ->and(Evaluation::where('card_id', $card->id)->count())->toBe(1);
});

test('deleting a card keeps evaluations but removes it from scope and mistake book', function () {
    $deck = createSystemDeck();
    $section = Section::factory()->create(['deck_id' => $deck->id, 'name' => '绪论', 'position' => 1]);
    $card = Card::factory()->create(['section_id' => $section->id, 'position' => 1]);

    $user = User::factory()->create();
    $user->update(['selected_deck_id' => $deck->id]);
    $task = Task::create([
        'user_id' => $user->id,
        'source_type' => 'selected',
        'source_deck_id' => $deck->id,
        'started_at' => now(),
    ]);
    $evaluation = Evaluation::create([
        'user_id' => $user->id,
        'card_id' => $card->id,
        'task_id' => $task->id,
        'rating' => 'forgotten',
    ]);
    actingAs(deckAdmin());

    $this->delete(route('admin.cards.destroy', $card))->assertRedirect();

    expect(Card::find($card->id))->toBeNull()
        ->and(Evaluation::find($evaluation->id)->card_id)->toBeNull();
});

test('deleting a system deck cascades its sections and cards', function () {
    $deck = createSystemDeck();
    $section = Section::factory()->create(['deck_id' => $deck->id, 'position' => 1]);
    $card = Card::factory()->create(['section_id' => $section->id, 'position' => 1]);
    actingAs(deckAdmin());

    $this->delete(route('admin.decks.destroy', $deck))->assertRedirect(route('admin.decks'));

    expect(Deck::find($deck->id))->toBeNull()
        ->and(Section::find($section->id))->toBeNull()
        ->and(Card::find($card->id))->toBeNull();
});

test('admin routes cannot touch user decks', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->ownedBy($user)->create(['name' => '我的卡组']);
    $section = Section::factory()->create(['deck_id' => $deck->id, 'position' => 1]);
    $card = Card::factory()->create(['section_id' => $section->id, 'position' => 1]);
    actingAs(deckAdmin());

    $this->patch(route('admin.decks.update', $deck), ['name' => '改名'])->assertForbidden();
    $this->patch(route('admin.sections.update', $section), ['name' => '改名'])->assertForbidden();
    $this->patch(route('admin.cards.update', $card), ['question' => 'x', 'answer' => 'y'])->assertForbidden();
    $this->delete(route('admin.cards.destroy', $card))->assertForbidden();
    $this->delete(route('admin.decks.destroy', $deck))->assertForbidden();
});

test('the deck list shows system decks with counts', function () {
    $deck = createSystemDeck('刑法');
    $section = Section::factory()->create(['deck_id' => $deck->id, 'position' => 1]);
    Card::factory()->count(2)->create(['section_id' => $section->id, 'position' => 0]);
    actingAs(deckAdmin());

    $this->get(route('admin.decks'))
        ->assertInertia(fn ($page) => $page
            ->has('decks', 1)
            ->where('decks.0.name', '刑法')
            ->where('decks.0.cards_count', 2)
            ->where('decks.0.sections_count', 1)
        );
});
