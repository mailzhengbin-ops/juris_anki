<?php

use App\Models\Card;
use App\Models\Deck;
use App\Models\Evaluation;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;
use App\Services\DeckImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;

const VALID_DOCUMENT = <<<'MD'
# 民法

## 总则

### 民法的概念
```
民法是调整平等主体之间人身关系和财产关系的法律规范的总称。
```
### 民法的原则
```
平等原则、自愿原则、公平原则、诚实信用原则等。
```
## 物权
### 物权的概念
```
物权是权利人依法对特定的物享有直接支配和排他的权利。
```
MD;

test('guests cannot import decks', function () {
    $this->post(route('decks.import'))->assertRedirect(route('login'));
});

test('a user can import a valid markdown document as their user deck', function () {
    $user = User::factory()->create();
    actingAs($user);

    $response = $this->post(route('decks.import'), [
        'document' => UploadedFile::fake()->createWithContent('minfa.md', VALID_DOCUMENT),
    ]);

    $response->assertRedirect(route('select'));

    $deck = Deck::where('name', '民法')->first();
    expect($deck)->not->toBeNull()
        ->and($deck->user_id)->toBe($user->id)
        ->and($deck->sections)->toHaveCount(2)
        ->and($deck->sections->first()->cards)->toHaveCount(2)
        ->and($deck->cards->first()->answer)->toContain('平等主体');
});

test('importing a deck with a duplicate name fails', function () {
    $user = User::factory()->create();
    Deck::factory()->ownedBy($user)->create(['name' => '民法']);
    actingAs($user);

    $response = $this->post(route('decks.import'), [
        'document' => UploadedFile::fake()->createWithContent('minfa.md', VALID_DOCUMENT),
    ]);

    $response->assertSessionHasErrors('document');
});

test('the same deck name is allowed for different users', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    Deck::factory()->ownedBy($first)->create(['name' => '民法']);

    actingAs($second);

    $this->post(route('decks.import'), [
        'document' => UploadedFile::fake()->createWithContent('minfa.md', VALID_DOCUMENT),
    ])->assertRedirect(route('select'));

    expect(Deck::where('name', '民法')->count())->toBe(2);
});

test('an invalid document fails atomically with a line-numbered message', function () {
    $user = User::factory()->create();
    actingAs($user);

    $response = $this->post(route('decks.import'), [
        'document' => UploadedFile::fake()->createWithContent('bad.md', "# 民法\n## 总则\n#### 太深\n"),
    ]);

    $response->assertSessionHasErrors('document');
    expect(session('errors')->first('document'))->toContain('第 3 行');
    expect(Deck::count())->toBe(0);
});

test('a document with an empty title at any level is rejected without creating a deck', function (string $document) {
    $user = User::factory()->create();
    actingAs($user);

    $response = $this->post(route('decks.import'), [
        'document' => UploadedFile::fake()->createWithContent('empty-title.md', $document),
    ]);

    $response->assertSessionHasErrors('document');
    expect(Deck::count())->toBe(0);
})->with([
    'empty deck title' => "# \n## 总则\n### 问题\n```\n答案\n```\n",
    'empty section title' => "# 民法\n## \n### 问题\n```\n答案\n```\n",
    'empty card question' => "# 民法\n## 总则\n### \n```\n答案\n```\n",
]);

test('an oversized document is rejected', function () {
    $user = User::factory()->create();
    actingAs($user);

    $response = $this->post(route('decks.import'), [
        'document' => UploadedFile::fake()->create('big.md', 3000),
    ]);

    $response->assertSessionHasErrors('document');
    expect(Deck::count())->toBe(0);
});

test('a user can delete their own user deck', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->ownedBy($user)->create(['name' => '民法']);
    $section = Section::factory()->create(['deck_id' => $deck->id]);
    Card::factory()->count(2)->create(['section_id' => $section->id]);
    actingAs($user);

    $this->delete(route('decks.destroy', $deck))->assertRedirect(route('select'));

    expect(Deck::find($deck->id))->toBeNull()
        ->and(Section::where('deck_id', $deck->id)->count())->toBe(0)
        ->and(Card::where('section_id', $section->id)->count())->toBe(0);
});

test('deleting a deck keeps evaluation records for statistics', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->ownedBy($user)->create(['name' => '民法']);
    $section = Section::factory()->create(['deck_id' => $deck->id]);
    $card = Card::factory()->create(['section_id' => $section->id]);
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
    actingAs($user);

    $this->delete(route('decks.destroy', $deck));

    expect(Evaluation::find($evaluation->id))->not->toBeNull()
        ->and(Evaluation::find($evaluation->id)->card_id)->toBeNull();
});

test('deleting a deck that is the current selected deck clears the selection', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->ownedBy($user)->create(['name' => '民法']);
    $user->update(['selected_deck_id' => $deck->id]);
    actingAs($user);

    $this->delete(route('decks.destroy', $deck))->assertRedirect(route('select'));

    expect($user->fresh()->selected_deck_id)->toBeNull();
});

test('a user cannot delete another user\'s deck', function () {
    $owner = User::factory()->create();
    $deck = Deck::factory()->ownedBy($owner)->create(['name' => '民法']);

    $user = User::factory()->create();
    actingAs($user);

    $this->delete(route('decks.destroy', $deck))->assertForbidden();

    expect(Deck::find($deck->id))->not->toBeNull();
});

test('a user cannot delete a system deck', function () {
    $deck = Deck::factory()->system()->create(['name' => '刑法']);

    $user = User::factory()->create();
    actingAs($user);

    $this->delete(route('decks.destroy', $deck))->assertForbidden();

    expect(Deck::find($deck->id))->not->toBeNull();
});

test('the import service rejects documents over 2MB', function () {
    $user = User::factory()->create();
    $service = new DeckImportService;

    try {
        $service->importFor($user, str_repeat('x', DeckImportService::MAX_DOCUMENT_BYTES + 1));
        test()->fail('Expected ValidationException.');
    } catch (ValidationException $e) {
        expect($e->errors()['document'][0])->toContain('2MB');
    }
});
