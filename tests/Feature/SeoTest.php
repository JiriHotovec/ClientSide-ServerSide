<?php

use App\Models\Device;
use App\Models\User;

it('gives indexable pages a canonical URL and hreflang alternates for every language', function () {
    [$device] = deviceWithData(['is_public' => true]);
    $html = $this->get("/cs/devices/{$device->uuid}")->getContent();

    expect($html)->toContain('<meta name="robots" content="index,follow">')
        ->toContain('<link rel="canonical" href="http://localhost:8000/cs/devices/'.$device->uuid.'">')
        ->toContain('<link rel="alternate" hreflang="en" href="http://localhost:8000/en/devices/'.$device->uuid.'">')
        ->toContain('<link rel="alternate" hreflang="cs" href="http://localhost:8000/cs/devices/'.$device->uuid.'">')
        ->toContain('<link rel="alternate" hreflang="x-default" href="http://localhost:8000/en/devices/'.$device->uuid.'">')
        ->toContain('<meta property="og:locale" content="cs_CZ">')
        ->toContain('<meta property="og:locale:alternate" content="en_US">')
        ->toContain('<html lang="cs">');
});

it('has a unique title and description per public page', function () {
    [$device] = deviceWithData(['is_public' => true, 'name' => 'Garden', 'location' => 'Prague']);

    $this->get("/en/devices/{$device->uuid}")->assertSee('<title>Garden – MeteoView</title>', false)->assertSee('<meta name="description" content="Garden, Prague – Temperature">', false);
    $this->get('/en/devices')->assertSee('<title>Public devices – MeteoView</title>', false);
    $this->get('/cs/devices')->assertSee('<title>Veřejná zařízení – MeteoView</title>', false);
});

it('keeps the language when paginating and links alternates with the same query', function () {
    Device::factory()->count(12)->public()->create();

    $res = $this->get('/cs/devices?page=2')->assertOk();

    $res->assertSee('hreflang="en" href="http://localhost:8000/en/devices?page=2"', false);
});

it('marks login, registration, workspace and dashboard as noindex', function () {
    $this->get('/en/login')->assertSee('<meta name="robots" content="noindex,follow">', false)->assertDontSee('rel="canonical"', false);
    $this->get('/en/register')->assertSee('noindex', false);

    $this->actingAs(User::factory()->create());
    $this->get('/manage')->assertSee('<meta name="robots" content="noindex,nofollow">', false);
    $this->get('/dashboard')->assertSee('noindex', false);
});

it('serves a sitemap with every public page in every language and hreflang alternates', function () {
    [$public] = deviceWithData(['is_public' => true]);
    [$private] = deviceWithData(['is_public' => false]);

    $res = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    $xml = $res->getContent();

    expect($xml)->toContain('<loc>http://localhost:8000/en</loc>')->toContain('<loc>http://localhost:8000/cs</loc>')
        ->toContain('<loc>http://localhost:8000/en/devices</loc>')->toContain('<loc>http://localhost:8000/cs/devices</loc>')
        ->toContain("<loc>http://localhost:8000/en/devices/{$public->uuid}</loc>")->toContain("<loc>http://localhost:8000/cs/devices/{$public->uuid}</loc>")
        ->toContain('hreflang="x-default"')->toContain('<lastmod>')
        ->not->toContain($private->uuid)->not->toContain('/manage');
    expect(simplexml_load_string($xml))->not->toBeFalse();
});

it('serves robots.txt that hides private areas and points to the sitemap', function () {
    $txt = $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->getContent();

    expect($txt)->toContain('Disallow: /manage')->toContain('Disallow: /api/')->toContain('Disallow: /en/login')->toContain('Disallow: /cs/register')
        ->toContain('Sitemap: http://localhost:8000/sitemap.xml');
});

it('uses crawlable links for the language switch on public pages', function () {
    $html = $this->get('/en/devices')->getContent();

    expect($html)->toContain('<a href="http://localhost:8000/cs/devices" hreflang="cs" lang="cs"')
        ->and($html)->not->toContain('action="http://localhost:8000/locale/cs"');
});

it('remembers the language of a visited page for the workspace', function () {
    $this->get('/cs/devices')->assertCookie('locale', 'cs');

    $this->actingAs(User::factory()->create())->get('/manage')->assertSee('Přehled');
});

it('generates all links in the current language', function () {
    $html = $this->get('/cs')->getContent();

    expect($html)->toContain('href="http://localhost:8000/cs/devices"')->not->toContain('href="http://localhost:8000/en/devices"');
});

it('sends guests to the login page of their language', function () {
    $this->get('/manage', ['Accept-Language' => 'cs'])->assertRedirect('/cs/login');
    $this->get('/manage')->assertRedirect('/en/login');
});
