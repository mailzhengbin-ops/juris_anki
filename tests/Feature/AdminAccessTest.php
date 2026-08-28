<?php

use App\Models\Deck;
use App\Models\User;

use function Pest\Laravel\actingAs;

test('guests are redirected from admin pages', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    $this->get(route('admin.decks'))->assertRedirect(route('login'));
    $this->get(route('admin.users'))->assertRedirect(route('login'));
});

test('regular users are forbidden from all admin pages', function () {
    $user = User::factory()->create();
    actingAs($user);

    $this->get(route('admin.dashboard'))->assertForbidden();
    $this->get(route('admin.decks'))->assertForbidden();
    $this->get(route('admin.users'))->assertForbidden();
});

test('admins can visit the dashboard with data and environment info', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(3)->create();
    Deck::factory()->system()->create(['name' => '刑法']);
    actingAs($admin);

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/dashboard')
            ->where('data.users_count', 4)
            ->where('data.system_decks_count', 1)
            ->where('environment.php_version', PHP_VERSION)
            ->where('environment.laravel_version', app()->version())
            ->has('environment.database')
            ->has('environment.server_software')
        );
});

test('admins can visit the deck and user management placeholders', function () {
    $admin = User::factory()->admin()->create();
    actingAs($admin);

    $this->get(route('admin.decks'))->assertOk();
    $this->get(route('admin.users'))->assertOk();
});
