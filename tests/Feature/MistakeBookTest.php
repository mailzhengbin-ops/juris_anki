<?php

use App\Models\Card;
use App\Models\Deck;
use App\Models\Section;
use App\Models\User;

use function Pest\Laravel\actingAs;

function createBookDeck(User $user): array
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

function rateCard(User $user, Card $card, string $rating): void
{
    test()->post(route('recite.rate'), [
        'card_id' => $card->id,
        'rating' => $rating,
    ])->assertOk();
}

test('rating a card forgotten enrolls it in the mistake book', function () {
    [$deck, $cards] = createBookDeck(User::factory()->create());
    $user = $deck->owner;
    actingAs($user);
    rateCard($user, $cards[0], 'forgotten');

    $this->get(route('select'))
        ->assertInertia(fn ($page) => $page
            ->where('mistakeScope.0.name', '忘记')
            ->has('mistakeScope.0.cards', 1)
            ->where('mistakeScope.0.cards.0.id', $cards[0]->id)
            ->where('mistakeScope.0.cards.0.path', '刑法 / 绪论')
            ->where('mistakeScope.0.cards.0.checked', true)
            ->where('mistakeScope.1.name', '模糊')
            ->has('mistakeScope.1.cards', 0)
        );
});

test('fuzzy and forgotten cards land in their respective subgroups', function () {
    [$deck, $cards] = createBookDeck(User::factory()->create());
    $user = $deck->owner;
    actingAs($user);
    rateCard($user, $cards[0], 'fuzzy');
    rateCard($user, $cards[2], 'forgotten');

    $this->get(route('select'))
        ->assertInertia(fn ($page) => $page
            ->where('mistakeScope.0.cards.0.id', $cards[2]->id)
            ->where('mistakeScope.1.cards.0.id', $cards[0]->id)
        );
});

test('rating known removes the card from the book and re-enrolling works', function () {
    [$deck, $cards] = createBookDeck(User::factory()->create());
    $user = $deck->owner;
    actingAs($user);
    rateCard($user, $cards[0], 'forgotten');
    rateCard($user, $cards[0], 'known');

    $this->get(route('select'))
        ->assertInertia(fn ($page) => $page->has('mistakeScope.0.cards', 0));

    rateCard($user, $cards[0], 'fuzzy');

    $this->get(route('select'))
        ->assertInertia(fn ($page) => $page->where('mistakeScope.1.cards.0.id', $cards[0]->id));
});

test('the latest evaluation wins when switching between mistake ratings', function () {
    [$deck, $cards] = createBookDeck(User::factory()->create());
    $user = $deck->owner;
    actingAs($user);
    rateCard($user, $cards[0], 'forgotten');
    rateCard($user, $cards[0], 'fuzzy');

    $this->get(route('select'))
        ->assertInertia(fn ($page) => $page
            ->has('mistakeScope.0.cards', 0)
            ->where('mistakeScope.1.cards.0.id', $cards[0]->id)
        );
});

test('mistake book cards appear in original deck-tree order across sections', function () {
    [$deck, $cards] = createBookDeck(User::factory()->create());
    $user = $deck->owner;
    actingAs($user);

    // 乱序评价：树序应为 cards[0]（绪论A）先于 cards[2]（犯罪构成A）
    rateCard($user, $cards[2], 'forgotten');
    rateCard($user, $cards[0], 'forgotten');

    $this->get(route('select'))
        ->assertInertia(fn ($page) => $page
            ->where('mistakeScope.0.cards.0.id', $cards[0]->id)
            ->where('mistakeScope.0.cards.1.id', $cards[2]->id)
        );
});

test('the mistake source recites its own independent task line', function () {
    [$deck, $cards] = createBookDeck(User::factory()->create());
    $user = $deck->owner;
    actingAs($user);

    // 自选卡任务：评第一张
    rateCard($user, $cards[0], 'forgotten');
    rateCard($user, $cards[1], 'known');

    // 切到错题本：在册 = cards[0]
    $user->update(['active_source' => 'mistake']);

    $this->get(route('recite'))
        ->assertInertia(fn ($page) => $page
            ->where('state.source', 'mistake')
            ->where('state.phase', 'fresh')
            ->where('state.card.id', $cards[0]->id)
            ->where('state.card.enrolled', 'forgotten')
            ->where('state.progress.total', 1)
        );

    rateCard($user, $cards[0], 'known');

    // 错题本被清空（评认识出册）→ 空态；自选卡任务未完成（独立进度）
    $this->get(route('recite'))
        ->assertInertia(fn ($page) => $page->where('state.phase', 'empty'));

    $user->update(['active_source' => 'selected']);

    $this->get(route('recite'))
        ->assertInertia(fn ($page) => $page
            ->where('state.source', 'selected')
            ->where('state.phase', 'active')
            ->where('state.progress.evaluated', 2)
            ->where('state.card.id', $cards[2]->id)
        );
});

test('rating known inside the mistake task shrinks its scope immediately', function () {
    [$deck, $cards] = createBookDeck(User::factory()->create());
    $user = $deck->owner;
    actingAs($user);

    // 先在自选卡任务中让两张卡在册
    rateCard($user, $cards[0], 'forgotten');
    rateCard($user, $cards[1], 'fuzzy');

    $user->update(['active_source' => 'mistake']);

    $this->get(route('recite'))
        ->assertInertia(fn ($page) => $page
            ->where('state.phase', 'fresh')
            ->where('state.progress.total', 2)
        );

    // 在错题本任务中评"认识"：出册，范围缩水（已评卡不在册，进度分子归零）
    rateCard($user, $cards[0], 'known');

    $this->get(route('recite'))
        ->assertInertia(fn ($page) => $page
            ->where('state.phase', 'active')
            ->where('state.progress.evaluated', 0)
            ->where('state.progress.total', 1)
            ->where('state.card.id', $cards[1]->id)
        );
});

test('the enrolled badge and the mistake book groups derive from the same rule', function () {
    [$deck, $cards] = createBookDeck(User::factory()->create());
    $user = $deck->owner;
    actingAs($user);

    rateCard($user, $cards[0], 'forgotten');
    rateCard($user, $cards[1], 'fuzzy');

    // 选卡页视图：忘记组 / 模糊组各归其位
    $this->get(route('select'))
        ->assertInertia(fn ($page) => $page
            ->where('mistakeScope.0.cards.0.id', $cards[0]->id)
            ->where('mistakeScope.1.cards.0.id', $cards[1]->id)
        );

    // 背诵页视图：卡片的在册标记与组别一致
    $user->update(['active_source' => 'mistake']);

    $this->get(route('recite'))
        ->assertInertia(fn ($page) => $page
            ->where('state.card.id', $cards[0]->id)
            ->where('state.card.enrolled', 'forgotten')
        );

    rateCard($user, $cards[0], 'forgotten');

    $this->get(route('recite'))
        ->assertInertia(fn ($page) => $page
            ->where('state.card.id', $cards[1]->id)
            ->where('state.card.enrolled', 'fuzzy')
        );
});

test('rating known in the selected task removes the card from the mistake queue', function () {
    [$deck, $cards] = createBookDeck(User::factory()->create());
    $user = $deck->owner;
    actingAs($user);

    rateCard($user, $cards[0], 'forgotten');
    rateCard($user, $cards[1], 'fuzzy');

    // 切错题本任务：队列 [cards[0], cards[1]]
    $user->update(['active_source' => 'mistake']);

    $this->get(route('recite'))
        ->assertInertia(fn ($page) => $page->where('state.card.id', $cards[0]->id));

    // 回自选卡任务评"认识" cards[0]：出册
    $user->update(['active_source' => 'selected']);
    rateCard($user, $cards[0], 'known');

    // 切回错题本：cards[0] 已不在队列，下一张为 cards[1]
    $user->update(['active_source' => 'mistake']);

    $this->get(route('recite'))
        ->assertInertia(fn ($page) => $page->where('state.card.id', $cards[1]->id));
});
