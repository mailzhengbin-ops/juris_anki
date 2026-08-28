<?php

use App\Models\Card;
use App\Models\Deck;
use App\Models\Evaluation;
use App\Models\ScopeExclusion;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;

function userAdmin(): User
{
    return User::factory()->admin()->create();
}

test('guests are redirected from admin user routes', function () {
    $this->get(route('admin.users'))->assertRedirect(route('login'));
    $this->get(route('admin.users.show', 1))->assertRedirect(route('login'));
});

test('regular users are forbidden from admin user routes', function () {
    $user = User::factory()->create();
    actingAs($user);

    $this->get(route('admin.users'))->assertForbidden();
    $this->get(route('admin.users.show', $user))->assertForbidden();
    $this->delete(route('admin.users.destroy', $user))->assertForbidden();
});

test('the user list can be searched by name or email', function () {
    $admin = userAdmin();
    User::factory()->create(['name' => '张三', 'email' => 'zhangsan@example.com']);
    User::factory()->create(['name' => '李四', 'email' => 'lisi@example.com']);
    actingAs($admin);

    $this->get(route('admin.users', ['q' => '张三']))
        ->assertInertia(fn ($page) => $page
            ->has('users', 1)
            ->where('users.0.name', '张三')
        );

    $this->get(route('admin.users', ['q' => 'lisi']))
        ->assertInertia(fn ($page) => $page->has('users', 1));

    $this->get(route('admin.users'))
        ->assertInertia(fn ($page) => $page->has('users', 3));
});

test('logging in records the last login time', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

test('the user detail shows aggregate counts', function () {
    $admin = userAdmin();
    $user = User::factory()->create();
    $deck = Deck::factory()->ownedBy($user)->create(['name' => '卡组']);
    $section = Section::factory()->create(['deck_id' => $deck->id, 'position' => 1]);
    $card = Card::factory()->create(['section_id' => $section->id, 'position' => 1]);
    $task = Task::create(['user_id' => $user->id, 'source_type' => 'selected', 'source_deck_id' => $deck->id, 'started_at' => now()]);
    Evaluation::create(['user_id' => $user->id, 'card_id' => $card->id, 'task_id' => $task->id, 'rating' => 'known']);
    actingAs($admin);

    $this->get(route('admin.users.show', $user))
        ->assertInertia(fn ($page) => $page
            ->where('user.name', $user->name)
            ->where('user.decks_count', 1)
            ->where('user.evaluations_count', 1)
        );
});

test('deleting a user cascades all of their data', function () {
    $admin = userAdmin();
    $user = User::factory()->create();
    $deck = Deck::factory()->ownedBy($user)->create(['name' => '卡组']);
    $section = Section::factory()->create(['deck_id' => $deck->id, 'position' => 1]);
    $card = Card::factory()->create(['section_id' => $section->id, 'position' => 1]);
    $task = Task::create(['user_id' => $user->id, 'source_type' => 'selected', 'source_deck_id' => $deck->id, 'started_at' => now()]);
    Evaluation::create(['user_id' => $user->id, 'card_id' => $card->id, 'task_id' => $task->id, 'rating' => 'known']);
    ScopeExclusion::create(['user_id' => $user->id, 'card_id' => $card->id]);
    actingAs($admin);

    $this->delete(route('admin.users.destroy', $user))->assertRedirect(route('admin.users'));

    expect(User::find($user->id))->toBeNull()
        ->and(Deck::find($deck->id))->toBeNull()
        ->and(Evaluation::count())->toBe(0)
        ->and(ScopeExclusion::count())->toBe(0);
});

test('the admin cannot delete themselves', function () {
    $admin = userAdmin();
    actingAs($admin);

    $this->delete(route('admin.users.destroy', $admin))->assertStatus(422);

    expect(User::find($admin->id))->not->toBeNull();
});

test('an admin can delete another admin when more than one exists', function () {
    $first = userAdmin();
    $second = userAdmin();
    actingAs($second);

    $this->delete(route('admin.users.destroy', $first))->assertRedirect(route('admin.users'));

    expect(User::find($first->id))->toBeNull();
});

test('the last remaining admin cannot be deleted', function () {
    $first = userAdmin();
    $second = userAdmin();
    actingAs($second);

    $this->delete(route('admin.users.destroy', $first))->assertRedirect(route('admin.users'));

    // 现在只剩一个管理员，另一个普通用户（管理员身份下）无法删除它
    $this->delete(route('admin.users.destroy', $second))->assertStatus(422);

    expect(User::find($second->id))->not->toBeNull();
});
