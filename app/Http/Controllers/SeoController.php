<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Support\Locales;

class SeoController extends Controller
{
    /** sitemap.xml with an entry per language and hreflang alternates, for public pages only. */
    public function sitemap()
    {
        $urls = [];
        $add = function (string $route, array $params, ?\DateTimeInterface $lastmod = null) use (&$urls) {
            $alt = [];
            foreach (Locales::codes() as $code) {
                $alt[$code] = route($route, $params + ['locale' => $code]);
            }
            foreach ($alt as $code => $loc) {
                $urls[] = ['loc' => $loc, 'alt' => $alt, 'lastmod' => $lastmod?->format('c')];
            }
        };

        $add('welcome', []);
        $add('devices.index', []);
        Device::public()->orderBy('id')->get(['id', 'uuid', 'updated_at'])->each(
            fn ($d) => $add('devices.show', ['device' => $d->uuid], $d->updated_at)
        );

        return response()->view('seo.sitemap', ['urls' => $urls, 'default' => Locales::DEFAULT])->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots()
    {
        $lines = ['User-agent: *', 'Allow: /'];
        foreach (['/manage', '/dashboard', '/api/', '/locale/', '/logout'] as $path) {
            $lines[] = 'Disallow: '.$path;
        }
        foreach (Locales::codes() as $code) {
            $lines[] = "Disallow: /{$code}/login";
            $lines[] = "Disallow: /{$code}/register";
            $lines[] = "Disallow: /{$code}/devices/*/table/";
        }
        $lines[] = '';
        $lines[] = 'Sitemap: '.route('sitemap');

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
