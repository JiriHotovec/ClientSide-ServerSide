<?php

use App\Models\User;

it('rejects web form posts without a CSRF token (419)', function () {
    app()['env'] = 'local'; // Laravel skips CSRF verification while the environment is "testing"
    $this->post('/en/login', ['email' => 'a@b.cz', 'password' => 'x'])->assertStatus(419);
});

it('rejects session-authenticated API writes without a CSRF token (419)', function () {
    app()['env'] = 'local';
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->postJson('/api/devices', ['name' => 'X'])->assertStatus(419);
});

it('sends security headers including a strict CSP', function () {
    $res = $this->get('/en');

    $res->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('Referrer-Policy');
    expect($res->headers->get('Content-Security-Policy'))->toContain("default-src 'self'")->toContain("frame-ancestors 'none'")->not->toContain('unsafe-inline');
});

it('ships no inline scripts or styles (required by the CSP)', function () {
    $html = $this->get('/en')->getContent();

    expect(preg_match('/<script(?![^>]*(\ssrc=|type="application\/json"))[^>]*>/i', $html))->toBe(0)
        ->and(preg_match('/<style/i', $html))->toBe(0);
});

it('embeds the CSRF token for htmx and fetch', function () {
    $this->get('/en')->assertSee('name="csrf-token"', false)->assertSee('hx-headers', false);
});

it('escapes user-supplied content in pages (XSS)', function () {
    [$device] = deviceWithData(['is_public' => true, 'name' => '<script>alert(1)</script>']);

    $this->get('/en/devices')->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    $this->get("/en/devices/{$device->uuid}")->assertDontSee('<script>alert(1)</script>', false);
});

it('does not allow mass-assigning role or owner', function () {
    $user = new User(['name' => 'x', 'email' => 'x@x.cz', 'password' => 'y', 'role' => 'admin']);

    expect($user->role)->toBeNull();
});

it('hides password hashes and ingest token hashes from serialization', function () {
    [$device] = deviceWithData();
    $device->regenerateIngestToken();

    expect($device->fresh()->toArray())->not->toHaveKey('ingest_token_hash')
        ->and(User::factory()->create()->toArray())->not->toHaveKey('password');
});

it('invalidates the session on logout', function () {
    $user = User::factory()->create();
    $this->post('/en/login', ['email' => $user->email, 'password' => 'password']);
    $id = session()->getId();

    $this->post('/logout');

    expect(session()->getId())->not->toBe($id);
    $this->assertGuest();
});
