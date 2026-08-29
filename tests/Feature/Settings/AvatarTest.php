<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/** 1×1 透明 PNG（base64），避免依赖未安装的 GD 扩展。 */
function fakeAvatar(): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        'avatar.png',
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
    );
}

test('guests are redirected from avatar upload', function () {
    $this->post(route('profile.avatar.update'))->assertRedirect(route('login'));
});

test('avatar can be uploaded', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('profile.avatar.update'), [
            'avatar' => fakeAvatar(),
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->avatar)->not->toBeNull();
    Storage::disk('public')->assertExists($user->avatar);
});

test('avatar upload rejects non-image files', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->post(route('profile.avatar.update'), [
            'avatar' => UploadedFile::fake()->create('avatar.txt', 100),
        ]);

    $response
        ->assertSessionHasErrors('avatar', 'The avatar field must be an image.')
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->avatar)->toBeNull();
});

test('avatar upload rejects files larger than 2MB', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->post(route('profile.avatar.update'), [
            'avatar' => fakeAvatar()->size(3000),
        ]);

    $response
        ->assertSessionHasErrors('avatar')
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->avatar)->toBeNull();
});

test('uploading a new avatar removes the old file', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $oldPath = 'avatars/old.png';
    Storage::disk('public')->put($oldPath, 'old');
    $user->update(['avatar' => $oldPath]);

    $this
        ->actingAs($user)
        ->post(route('profile.avatar.update'), [
            'avatar' => fakeAvatar(),
        ]);

    $user->refresh();

    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($user->avatar);
});

test('avatar can be removed', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $path = 'avatars/existing.png';
    Storage::disk('public')->put($path, 'existing');
    $user->update(['avatar' => $path]);

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.avatar.destroy'));

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->avatar)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('avatar removal is a no-op when the user has no avatar', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.avatar.destroy'));

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->avatar)->toBeNull();
});

test('profile page exposes the avatar url to the client', function () {
    $user = User::factory()->create();
    $user->fill(['avatar' => 'avatars/avatar.webp'])->save();

    $this
        ->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('auth.user.avatar_url', '/storage/avatars/avatar.webp'));
});
