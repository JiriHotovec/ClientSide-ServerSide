<?php

use App\Models\Device;
use App\Models\Measurement;
use App\Models\MeasurementType;
use App\Models\User;
use App\Support\Categories;
use App\Support\ChartKinds;
use App\Support\DisplaySettings;
use App\Support\JsStrings;

it('renders each language under its own URL', function () {
    $this->get('/en/login')->assertSee('Welcome <em>back.</em>', false)->assertSee('<html lang="en">', false);
    $this->get('/cs/login')->assertSee('Vítejte <em>zpátky.</em>', false)->assertSee('<html lang="cs">', false);
});

it('lets the language in the URL win over the browser header and saved preferences', function () {
    $this->get('/en/login', ['Accept-Language' => 'cs'])->assertSee('Welcome <em>back.</em>', false);
    $this->post('/locale/cs');
    $this->get('/en/login')->assertSee('Welcome <em>back.</em>', false);
});

it('redirects language-less addresses to the visitor language', function () {
    $this->get('/')->assertRedirect('/en');
    $this->get('/', ['Accept-Language' => 'cs-CZ,cs;q=0.9,en;q=0.8'])->assertRedirect('/cs');
    $this->get('/', ['Accept-Language' => 'de,en;q=0.5'])->assertRedirect('/en');
    $this->get('/', ['Accept-Language' => 'en;q=0.4, cs;q=0.9'])->assertRedirect('/cs');
});

it('permanently redirects the old language-less public URLs', function () {
    [$device] = deviceWithData(['is_public' => true]);

    $this->get('/devices?q=x', ['Accept-Language' => 'cs'])->assertStatus(301)->assertRedirect('/cs/devices?q=x');
    $this->get("/devices/{$device->uuid}")->assertStatus(301)->assertRedirect("/en/devices/{$device->uuid}");
    $this->get('/login', ['Accept-Language' => 'cs'])->assertRedirect('/cs/login');
    $this->get('/register')->assertRedirect('/en/register');
});

it('rejects unknown language prefixes', function () {
    $this->get('/de/devices')->assertNotFound();
    $this->get('/xx')->assertNotFound();
});

it('switches the language, remembers it in session and cookie, and wins over the browser header', function () {
    $this->post('/locale/cs')->assertRedirect()->assertCookie('locale', 'cs');

    $this->get('/', ['Accept-Language' => 'en'])->assertRedirect('/cs'); // redirects follow the saved preference
    $this->actingAs(User::factory()->create())->get('/manage', ['Accept-Language' => 'en'])->assertSee('Přehled');

    $this->post('/locale/en');
    $this->get('/', ['Accept-Language' => 'cs'])->assertRedirect('/en');
});

it('uses the cookie for visitors without a session and rejects unknown languages', function () {
    $this->withCookie('locale', 'cs')->get('/')->assertRedirect('/cs');
    $this->post('/locale/de')->assertNotFound();
});

it('stores the language on the account and applies it on other devices', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/locale/cs');
    expect($user->fresh()->locale)->toBe('cs');

    auth()->logout();
    $this->flushSession();
    $this->actingAs($user->fresh())->get('/manage', ['Accept-Language' => 'en'])->assertSee('Přehled');
});

it('cannot set the language through mass assignment of the profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put('/manage/profile', ['name' => 'X', 'email' => $user->email, 'locale' => 'cs']);

    expect($user->fresh()->locale)->toBeNull();
});

it('shows the language switch on public pages and in the account menu', function () {
    $this->get('/en')->assertSee('class="lang-switch"', false)->assertSee('href="http://localhost:8000/cs"', false)->assertSee('hreflang="cs"', false);
    $this->actingAs(User::factory()->create())->get('/manage')->assertSee('class="lang-switch"', false);
});

it('translates the main interface to Czech', function (string $url, string $expected) {
    $this->actingAs(User::factory()->create());

    $this->get($url, ['Accept-Language' => 'cs'])->assertOk()->assertSee($expected, false);
    $this->get($url, ['Accept-Language' => 'en'])->assertOk();
})->with([
    ['/cs', 'Počasí blíž domovu'],
    ['/cs/devices', 'Veřejná zařízení'],
    ['/dashboard', 'Můj přehled'],
    ['/manage', 'Můj panel'],
    ['/manage/devices', 'Zařízení a zdroje dat'],
    ['/manage/devices/create', 'Jak zařízení dodává měření?'],
    ['/manage/types', 'Typy měření'],
    ['/manage/types/create', 'Výchozí zobrazení'],
    ['/manage/profile', 'Změnit heslo'],
]);

it('translates validation messages and field names', function () {
    $this->actingAs(User::factory()->create());

    $this->postJson('/api/devices', ['name' => ''], ['Accept-Language' => 'cs'])->assertUnprocessable()->assertJsonPath('errors.name.0', 'Pole název je povinné.');
});

it('translates registration errors and flash messages', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post('/manage/types', ['key' => 'uv', 'name' => 'UV', 'unit' => 'UVI'], ['Accept-Language' => 'cs'])->assertSessionHas('status', 'Typ měření „UV“ byl vytvořen.');
    $this->put('/manage/profile', ['name' => 'Eva', 'email' => $user->email], ['Accept-Language' => 'cs', 'HX-Request' => 'true'])->assertSee('Profil byl uložen.');
});

it('translates API messages', function () {
    $this->postJson('/api/ingest', [], ['Accept-Language' => 'cs'])->assertUnauthorized()->assertJsonPath('message', 'Neplatný nebo chybějící token zařízení.');
    $this->postJson('/api/ingest', [])->assertJsonPath('message', 'Invalid or missing device token.');
});

it('translates built-in measurement types but leaves user-created names untouched', function () {
    $system = MeasurementType::factory()->create(['key' => 'temperature', 'name' => 'Temperature', 'is_system' => true, 'is_public' => true]);
    $custom = MeasurementType::factory()->create(['key' => 'moods', 'name' => 'Nálada týmu', 'is_public' => true]);

    app()->setLocale('cs');
    expect($system->fresh()->name)->toBe('Teplota')->and($custom->fresh()->name)->toBe('Nálada týmu');
    app()->setLocale('en');
    expect($system->fresh()->name)->toBe('Temperature');
});

it('does not require translations for user content: names stay as written in both languages', function () {
    [$device] = deviceWithData(['is_public' => true, 'name' => 'Zahrada u řeky', 'description' => 'Only English here']);

    foreach (['cs', 'en'] as $lang) {
        $this->get("/{$lang}/devices/{$device->uuid}")->assertSee('Zahrada u řeky')->assertSee('Only English here');
    }
});

it('translates category labels and the dataset plural', function () {
    [$device, $type] = deviceWithData(['is_public' => true]);
    $type->update(['category' => 'air']);
    $device->types()->attach(MeasurementType::create(['key' => 'noise', 'name' => 'Noise', 'unit' => 'dB', 'category' => 'other']));

    $this->get("/cs/devices/{$device->uuid}")->assertSee('Kvalita vzduchu')->assertSee('Ostatní')->assertSee('graf');
});

it('formats numbers with a decimal comma in Czech', function () {
    $d = ['unit' => '°C', 'unit_position' => 'after', 'unit_space' => true, 'decimals' => 1];

    app()->setLocale('cs');
    expect(DisplaySettings::format(21.46, $d))->toBe("21,5\u{00A0}°C");
    app()->setLocale('en');
    expect(DisplaySettings::format(21.46, $d))->toBe("21.5\u{00A0}°C");
});

it('localizes relative times in Czech', function () {
    [$device, $type] = deviceWithData(['is_public' => true]);
    Measurement::create(['device_id' => $device->id, 'measurement_type_id' => $type->id, 'value' => 1, 'measured_at' => now()->subMinutes(10), 'source' => 'seed']);

    $this->get("/cs/devices/{$device->uuid}")->assertSee('Aktualizováno před 10 minutami');
});

it('shows stored data source errors in the viewer language', function () {
    $device = Device::factory()->make(['last_error' => 'Data source responded with HTTP 503.']);

    app()->setLocale('cs');
    expect($device->lastErrorText())->toBe('Zdroj dat odpověděl chybou HTTP 503.');
    app()->setLocale('en');
    expect($device->lastErrorText())->toBe('Data source responded with HTTP 503.');
});

it('sends the translations and the locale to the browser scripts as non-executable JSON', function () {
    $html = $this->get('/cs')->getContent();

    expect($html)->toContain('<script type="application/json" id="i18n">')->toContain('"locale":"cs"')->toContain('Načítám měření…');
});

it('has a Czech translation for every key used in views, code and scripts', function () {
    $cs = json_decode(file_get_contents(base_path('lang/cs.json')), true);
    $keys = [];
    $pattern = '/(?:__|trans_choice)\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1/';
    $files = array_merge(
        glob_recursive(resource_path('views'), 'php'),
        glob_recursive(app_path(), 'php'),
    );
    foreach ($files as $file) {
        preg_match_all($pattern, file_get_contents($file), $m);
        foreach ($m[2] as $k) {
            $k = str_replace(["\\'", '\\"'], ["'", '"'], $k);
            if (! (str_contains($k, '.') && ! str_contains($k, ' ') && ! str_ends_with($k, '.'))) {
                $keys[] = $k;
            }
        }
    }
    $keys = array_merge($keys, JsStrings::KEYS, array_values(Categories::ALL), array_values(ChartKinds::ALL));

    $missing = array_values(array_filter(array_unique($keys), fn ($k) => ! array_key_exists($k, $cs)));

    expect($missing)->toBe([]);
});

it('keeps placeholders identical in every translation', function () {
    $cs = json_decode(file_get_contents(base_path('lang/cs.json')), true);

    foreach ($cs as $en => $translated) {
        preg_match_all('/:[a-z]+/', $en, $a);
        preg_match_all('/:[a-z]+/', $translated, $b);
        sort($a[0]);
        sort($b[0]);
        expect($b[0])->toBe($a[0], "Placeholders differ for: {$en}");
    }
});

function glob_recursive(string $dir, string $ext): array
{
    $out = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
        if (str_ends_with($f->getFilename(), ".$ext")) {
            $out[] = $f->getPathname();
        }
    }

    return $out;
}
