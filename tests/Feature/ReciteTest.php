<?php

use App\Models\User;

test('guests are redirected to the login page when visiting the recite page', function () {
    $response = $this->get(route('recite'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the recite page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('recite'));

    $response->assertOk();
});

test('authenticated users are redirected from home to the recite page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('home'));

    $response->assertRedirect(route('recite'));
});

test('guests can visit the welcome page', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});

test('guests are redirected to the login page when visiting the select and stats pages', function () {
    $this->get(route('select'))->assertRedirect(route('login'));
    $this->get(route('stats'))->assertRedirect(route('login'));
});
