<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/** Language priority: account setting, session, cookie, browser (Accept-Language), default. */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // An explicit language in the URL wins; the rest is the visitor's saved preference or the browser's.
        $prefixed = Locales::isPrefixed($request) && str_starts_with($request->route()->uri(), '{locale}');
        $fromUrl = $prefixed ? $request->route()->parameter('locale') : null;
        if ($prefixed) {
            $request->route()->forgetParameter('locale'); // controllers must not receive it as an argument
        }

        $locale = collect([
            $fromUrl,
            $request->user()?->locale,
            $request->hasSession() ? $request->session()->get('locale') : null,
            $request->cookie('locale'),
            Locales::fromHeader($request->header('Accept-Language')),
        ])->first(fn ($l) => Locales::supported($l)) ?? Locales::DEFAULT;

        app()->setLocale($locale);
        Carbon::setLocale($locale);
        URL::defaults(['locale' => $locale]); // route('devices.index') => /{current language}/devices

        $response = $next($request);

        // Remember the language of a visited page so the workspace and later visits follow it.
        if (Locales::supported($fromUrl) && $request->cookie('locale') !== $fromUrl && $request->isMethodSafe()) {
            $response->headers->setCookie(cookie('locale', $fromUrl, 60 * 24 * 365));
            if ($request->hasSession()) {
                $request->session()->put('locale', $fromUrl);
            }
        }

        return $response;
    }
}
