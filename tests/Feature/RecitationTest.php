<?php

use App\Models\Card;
use App\Models\Deck;
use App\Models\Evaluation;
use App\Models\ScopeExclusion;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;

use function Pest\Laravel\actingAs;

function createRecitationDeck(User $user): array
{
    $deck = Deck::factory()->ownedBy($user)->create(['name' => '刑法']);
    $cards = [];

    foreach (['绪论', '犯罪构成'] as $i => $sectionName) {
        $section = Section::factory()->create([
            'deck_id' => $deck->id,
            'name' => $sectionName,
            'position' => $i + 1,
        ]);

        foreach (["问题{$i}A", "问题{$i}B"] as $j => $question) {
            $cards[] = Card::factory()->create([
                'section_id' => $section->id,
                'question' => $question,
                'position' => $j,
            ]);
        }
    }

    $user->update(['selected_deck_id' => $deck->id]);

    return [$deck, $cards];
}

function setupRecitation(): array
{
    $user = User::factory()->create();

    return createRecitationDeck($user);
}

test('the recite page shows an empty phase when no selected deck exists', function () {
    $user = User::factory()->create();
    actingAs($user);

    $this->get(route('recite'))
        ->assertInertia(fn ($page) => $page
            ->where('state.phase', 'empty')
            ->where('state.card', null)
        );
});

test('a fresh source shows the first card in deck-tree order without a task', function () {
    [$deck, $cards] = setupRecitation();
    $user = $deck->owner;
    actingAs($user);

    $this->get(route('recite'))
        ->assertInertia(fn ($page) => $page
            ->where('state.phase', 'fresh')
            ->where('state.progress.evaluated', 0)
            ->where('state.progress.total', 4)
            ->where('state.card.id', $cards[0]->id)
            ->where('state.card.path', '刑法 / 绪论')
        );

    expect(Task::count())->toBe(0);
});

test('browsing without rating does not advance the position', function () {
    [$deck, $cards] = setupRecitation();
    actingAs($deck->owner);

    $this->get(route('recite'))->assertInertia(fn ($page) => $page->where('state.card.id', $cards[0]->id));
    $this->get(route('recite'))->assertInertia(fn ($page) => $page->where('state.card.id', $cards[0]->id));

    expect(Task::count())->toBe(0)
        ->and(Evaluation::count())->toBe(0);
});

test('rating creates a task and advances in tree order', function () {
    [$deck, $cards] = setupRecitation();
    actingAs($deck->owner);

    $this->post(route('recite.rate'), [
        'card_id' => $cards[0]->id,
        'rating' => 'known',
    ])->assertRedirect(route('recite'));

    expect(Task::count())->toBe(1)
        ->and(Evaluation::count())->toBe(1);

    $this->get(route('recite'))
        ->assertInertia(fn ($page) => $page
            ->where('state.phase', 'active')
            ->where('state.progress.evaluated', 1)
            ->where('state.progress.total', 4)
            ->where('state.card.id', $cards[1]->id)
        );
});

test('the card history shows cumulative counts and last rating', function () {
    [$deck, $cards] = setupRecitation();
    actingAs($deck->owner);

    foreach (['forgotten', 'known', 'fuzzy', 'known'] as $index => $rating) {
        $this->post(route('recite.rate'), [
            'card_id' => $cards[$index]->id,
            'rating' => $rating,
        ]);
    }

    // 完成一轮后"再背一轮"，第一张卡重新出现并携带历史
    $this->get(route('recite', ['start' => 1]))
        ->assertInertia(fn ($page) => $page
            ->where('state.phase', 'fresh')
            ->where('state.card.id', $cards[0]->id)
            ->where('state.card.history.total', 1)
            ->where('state.card.history.known', 0)
            ->where('state.card.history.forgotten', 1)
            ->where('state.card.history.last_rating', 'forgotten')
            ->where('state.card.history.last_at', fn ($value) => is_string($value))
        );
});

test('rating every card completes the task with correct stats', function () {
    [$deck, $cards] = setupRecitation();
    actingAs($deck->owner);

    $ratings = ['known', 'fuzzy', 'forgotten', 'known'];

    foreach ($cards as $index => $card) {
        $this->post(route('recite.rate'), [
            'card_id' => $card->id,
            'rating' => $ratings[$index],
        ]);
    }

    $this->get(route('recite'))
        ->assertInertia(fn ($page) => $page
            ->where('state.phase', 'completed')
            ->where('state.card', null)
            ->where('state.task.stats.known', 2)
            ->where('state.task.stats.fuzzy', 1)
            ->where('state.task.stats.forgotten', 1)
        );
});

test('a completed task stays completed on refresh', function () {
    [$deck, $cards] = setupRecitation();
    actingAs($deck->owner);

    foreach ($cards as $card) {
        $this->post(route('recite.rate'), ['card_id' => $card->id, 'rating' => 'known']);
    }

    $this->get(route('recite'))->assertInertia(fn ($page) => $page->where('state.phase', 'completed'));
    $this->get(route('recite'))->assertInertia(fn ($page) => $page->where('state.phase', 'completed'));
});

test('reciting again starts a fresh round and a new task', function () {
    [$deck, $cards] = setupRecitation();
    actingAs($deck->owner);

    foreach ($cards as $card) {
        $this->post(route('recite.rate'), ['card_id' => $card->id, 'rating' => 'known']);
    }

    $this->get(route('recite', ['start' => 1]))
        ->assertInertia(fn ($page) => $page
            ->where('state.phase', 'fresh')
            ->where('state.progress.evaluated', 0)
            ->where('state.card.id', $cards[0]->id)
        );

    $this->post(route('recite.rate'), ['card_id' => $cards[0]->id, 'rating' => 'known']);

    expect(Task::count())->toBe(2)
        ->and(Task::whereNull('completed_at')->count())->toBe(1);
});

test('undo removes the last evaluation and returns to the card', function () {
    [$deck, $cards] = setupRecitation();
    actingAs($deck->owner);

    $this->post(route('recite.rate'), ['card_id' => $cards[0]->id, 'rating' => 'known']);

    $this->post(route('recite.undo'))->assertRedirect(route('recite'));

    expect(Evaluation::count())->toBe(0);

    $this->get(route('recite'))
        ->assertInertia(fn ($page) => $page
            ->where('state.phase', 'active')
            ->where('state.progress.evaluated', 0)
            ->where('state.progress.total', 4)
            ->where('state.card.id', $cards[0]->id)
        );
});

test('undo reopens a completed task', function () {
    [$deck, $cards] = setupRecitation();
    actingAs($deck->owner);

    foreach ($cards as $card) {
        $this->post(route('recite.rate'), ['card_id' => $card->id, 'rating' => 'known']);
    }

    $this->get(route('recite'))->assertInertia(fn ($page) => $page->where('state.phase', 'completed'));

    $this->post(route('recite.undo'));

    expect(Task::first()->completed_at)->toBeNull();

    $this->get(route('recite'))
        ->assertInertia(fn ($page) => $page
            ->where('state.phase', 'active')
            ->where('state.card.id', $cards[3]->id)
            ->where('state.progress.evaluated', 3)
        );
});

test('rating a card outside the scope is rejected', function () {
    [$deck, $cards] = setupRecitation();
    $foreignCard = Card::factory()->create();
    actingAs($deck->owner);

    $this->post(route('recite.rate'), [
        'card_id' => $foreignCard->id,
        'rating' => 'known',
    ])->assertStatus(422);
});

test('unchecking an evaluated card shrinks the progress denominator and numerator', function () {
    [$deck, $cards] = setupRecitation();
    $user = $deck->owner;
    actingAs($user);

    $this->post(route('recite.rate'), ['card_id' => $cards[0]->id, 'rating' => 'known']);

    ScopeExclusion::create(['user_id' => $user->id, 'card_id' => $cards[0]->id]);

    $this->get(route('recite'))
        ->assertInertia(fn ($page) => $page
            ->where('state.progress.evaluated', 0)
            ->where('state.progress.total', 3)
            ->where('state.card.id', $cards[1]->id)
        );
});

test('rechecking an evaluated card does not show it again in the current task', function () {
    [$deck, $cards] = setupRecitation();
    $user = $deck->owner;
    actingAs($user);

    $this->post(route('recite.rate'), ['card_id' => $cards[0]->id, 'rating' => 'known']);

    ScopeExclusion::create(['user_id' => $user->id, 'card_id' => $cards[0]->id]);
    ScopeExclusion::where('user_id', $user->id)->where('card_id', $cards[0]->id)->delete();

    $this->get(route('recite'))
        ->assertInertia(fn ($page) => $page
            ->where('state.progress.evaluated', 1)
            ->where('state.progress.total', 4)
            ->where('state.card.id', $cards[1]->id)
        );

    // 完成判定：范围内无未评价卡片即完成（已评过的不再出现）
    $this->post(route('recite.rate'), ['card_id' => $cards[1]->id, 'rating' => 'known']);
    $this->post(route('recite.rate'), ['card_id' => $cards[2]->id, 'rating' => 'known']);
    $this->post(route('recite.rate'), ['card_id' => $cards[3]->id, 'rating' => 'known']);

    $this->get(route('recite'))->assertInertia(fn ($page) => $page->where('state.phase', 'completed'));
});

test('rating while the mistake source is active is not available yet', function () {
    [$deck, $cards] = setupRecitation();
    $user = $deck->owner;
    $user->update(['active_source' => 'mistake']);
    actingAs($user);

    $this->get(route('recite'))
        ->assertInertia(fn ($page) => $page->where('state.phase', 'unavailable'));

    $this->post(route('recite.rate'), ['card_id' => $cards[0]->id, 'rating' => 'known'])
        ->assertStatus(422);
});

test('guests cannot rate or undo', function () {
    $this->post(route('recite.rate'))->assertRedirect(route('login'));
    $this->post(route('recite.undo'))->assertRedirect(route('login'));
});
