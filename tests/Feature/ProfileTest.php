<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('requires login for the profile page', function () {
    $this->get('/manage/profile')->assertRedirect(route('login'));
});

it('uses the workspace layout with a single navigation and the account menu', function () {
    $user = User::factory()->create(['name' => 'Jana Nováková']);

    $html = $this->actingAs($user)->get('/manage')->assertOk()->getContent();

    expect($html)->toContain('class="admin-shell"')->toContain('data-user-menu')->toContain('>JN<')
        ->toContain('aria-label="Workspace"')->not->toContain('aria-label="Main"')
        ->and(substr_count($html, '<nav'))->toBe(1);
});

it('shows the role in the account menu', function () {
    $this->actingAs(User::factory()->admin()->create())->get('/manage')->assertSee('Administrator');
    $this->actingAs(User::factory()->create())->get('/manage')->assertSee('Standard user')->assertSee('My dashboard');
});

it('updates name and e-mail', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put('/manage/profile', ['name' => 'New Name', 'email' => 'new@example.com'])->assertRedirect(route('manage.profile'));

    expect($user->fresh())->name->toBe('New Name')->email->toBe('new@example.com');
    $this->get('/manage/profile')->assertSee('Profile updated.');
});

it('rejects a duplicate e-mail but allows keeping your own', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create(['email' => 'mine@example.com']);

    $this->actingAs($user)->put('/manage/profile', ['name' => 'X', 'email' => 'taken@example.com'])->assertSessionHasErrors('email', null, 'details');
    $this->put('/manage/profile', ['name' => 'X', 'email' => 'mine@example.com'])->assertSessionDoesntHaveErrors(null, null, 'details');
});

it('cannot change the role through the profile form', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put('/manage/profile', ['name' => 'X', 'email' => $user->email, 'role' => 'admin']);

    expect($user->fresh()->role)->toBe('user');
});

it('changes the password when the current one is correct', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put('/manage/profile/password', ['current_password' => 'password', 'password' => 'NewSecret12345', 'password_confirmation' => 'NewSecret12345'])
        ->assertRedirect(route('manage.profile'));

    expect(Hash::check('NewSecret12345', $user->fresh()->password))->toBeTrue();
});

it('rejects a wrong current password, weak, unconfirmed or unchanged passwords', function (array $payload) {
    $user = User::factory()->create();

    $this->actingAs($user)->put('/manage/profile/password', $payload)->assertSessionHasErrors(null, null, 'password');
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
})->with([
    'wrong current' => [['current_password' => 'nope', 'password' => 'NewSecret12345', 'password_confirmation' => 'NewSecret12345']],
    'weak' => [['current_password' => 'password', 'password' => 'short', 'password_confirmation' => 'short']],
    'not confirmed' => [['current_password' => 'password', 'password' => 'NewSecret12345', 'password_confirmation' => 'different']],
    'same as current' => [['current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password']],
]);

it('throttles password change attempts', function () {
    $this->actingAs(User::factory()->create());
    foreach (range(1, 6) as $_) {
        $this->put('/manage/profile/password', ['current_password' => 'bad']);
    }

    $this->put('/manage/profile/password', ['current_password' => 'bad'])->assertStatus(429);
});

it('updates the profile through htmx and refreshes the account menu out-of-band', function () {
    $user = User::factory()->create(['name' => 'Old Name']);

    $res = $this->actingAs($user)->put('/manage/profile', ['name' => 'Anna Bila', 'email' => $user->email], ['HX-Request' => 'true'])->assertOk();

    $res->assertSee('Profile updated.')->assertSee('hx-swap-oob="true"', false)->assertSee('>AB<', false)->assertDontSee('<html', false);
    expect($user->fresh()->name)->toBe('Anna Bila');
});

it('returns the form with inline errors and status 422 for invalid htmx input', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put('/manage/profile', ['name' => '', 'email' => 'bad'], ['HX-Request' => 'true'])
        ->assertStatus(422)->assertSee('role="alert"', false)->assertSee('aria-invalid="true"', false)->assertDontSee('<html', false);
    expect($user->fresh()->email)->toBe($user->email);
});

it('changes the password through htmx and reports a wrong current password inline', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->put('/manage/profile/password', ['current_password' => 'bad', 'password' => 'NewSecret12345', 'password_confirmation' => 'NewSecret12345'], ['HX-Request' => 'true'])
        ->assertStatus(422)->assertSee('role="alert"', false);
    $this->put('/manage/profile/password', ['current_password' => 'password', 'password' => 'NewSecret12345', 'password_confirmation' => 'NewSecret12345'], ['HX-Request' => 'true'])
        ->assertOk()->assertSee('Password changed.');
});

it('wires the profile forms to htmx and marks exit links in the workspace menu', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/manage/profile')->assertSee('hx-put=', false)->assertSee('hx-swap="outerHTML"', false)->assertSee('responseHandling', false);
    $this->get('/manage')->assertSee('exit-link', false)->assertSee('(leaves the workspace)');
});
