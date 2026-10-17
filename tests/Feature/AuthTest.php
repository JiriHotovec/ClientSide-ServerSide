<?php

use App\Models\User;

it('registers a standard user and logs them in', function () {
    $this->post('/register', ['name' => 'Eve', 'email' => 'eve@example.com', 'password' => 'Secret12345', 'password_confirmation' => 'Secret12345'])
        ->assertRedirect(route('manage.overview'));

    $this->assertAuthenticated();
    expect(User::first()->role)->toBe('user');
});

it('never lets a registering user choose the admin role', function () {
    $this->post('/register', ['name' => 'Eve', 'email' => 'eve@example.com', 'password' => 'Secret12345', 'password_confirmation' => 'Secret12345', 'role' => 'admin']);

    expect(User::first()->role)->toBe('user');
});

it('rejects duplicate e-mails and weak passwords', function () {
    User::factory()->create(['email' => 'a@example.com']);

    $this->post('/register', ['name' => 'A', 'email' => 'a@example.com', 'password' => 'short', 'password_confirmation' => 'short'])
        ->assertSessionHasErrors(['email', 'password']);
});

it('redirects a standard user to the workspace after login', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('manage.overview'));
});

it('redirects an administrator to the workspace after login', function () {
    $admin = User::factory()->admin()->create();

    $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('manage.overview'));
});

it('gives a generic error for wrong credentials', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
    $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('regenerates the session id on login', function () {
    $user = User::factory()->create();
    $this->get('/login');
    $before = session()->getId();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    expect(session()->getId())->not->toBe($before);
});

it('throttles repeated failed logins', function () {
    $user = User::factory()->create();
    foreach (range(1, 5) as $_) {
        $this->post('/login', ['email' => $user->email, 'password' => 'bad']);
    }

    $this->post('/login', ['email' => $user->email, 'password' => 'bad'])->assertStatus(429);
});

it('logs out', function () {
    $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/');
    $this->assertGuest();
});

it('blocks administrators from the personal dashboard', function () {
    $this->actingAs(User::factory()->admin()->create())->get('/dashboard')->assertForbidden();
});

it('allows standard users on the personal dashboard and redirects guests to login', function () {
    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk();
});
