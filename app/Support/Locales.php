<?php

namespace App\Support;

/** Supported UI languages. Only the interface is translated; user-created content (names, descriptions) stays as written. */
class Locales
{
    public const ALL = ['en' => 'English', 'cs' => 'Čeština'];
    public const DEFAULT = 'en';

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::ALL);
    }

    public static function supported(?string $locale): bool
    {
        return $locale !== null && array_key_exists($locale, self::ALL);
    }

    /** Picks the best supported language from an Accept-Language header ("cs-CZ,cs;q=0.9,en;q=0.8"). */
    public static function fromHeader(?string $header): ?string
    {
        if (! $header) {
            return null;
        }
        $candidates = [];
        foreach (explode(',', $header) as $i => $part) {
            [$tag, $q] = array_pad(explode(';q=', trim($part)), 2, '1');
            $candidates[] = [strtolower(substr($tag, 0, 2)), (float) $q, $i];
        }
        usort($candidates, fn ($a, $b) => [$b[1], $a[2]] <=> [$a[1], $b[2]]);
        foreach ($candidates as [$lang]) {
            if (self::supported($lang)) {
                return $lang;
            }
        }

        return null;
    }

    /**
     * URLs of the current page in every language ("/en/devices?page=2" => "/cs/devices?page=2"),
     * for hreflang alternates and the language switch. Only meaningful on prefixed (public) pages.
     *
     * @return array<string, string>
     */
    public static function alternates(\Illuminate\Http\Request $request): array
    {
        $path = $request->getPathInfo();
        $query = $request->getQueryString();
        $out = [];
        foreach (self::codes() as $code) {
            $out[$code] = $request->getSchemeAndHttpHost().preg_replace('#^/(?:'.implode('|', self::codes()).')(?=/|$)#', '/'.$code, $path).($query ? '?'.$query : '');
        }

        return $out;
    }

    public static function isPrefixed(\Illuminate\Http\Request $request): bool
    {
        $route = $request->route();

        return $route !== null && str_starts_with($route->uri(), '{locale}');
    }
}
