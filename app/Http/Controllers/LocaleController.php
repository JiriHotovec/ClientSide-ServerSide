<?php

namespace App\Http\Controllers;

use App\Support\Locales;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request, string $locale)
    {
        abort_unless(Locales::supported($locale), 404);

        $request->session()->put('locale', $locale);
        $request->user()?->forceFill(['locale' => $locale])->save();

        return back()->withCookie(cookie('locale', $locale, 60 * 24 * 365));
    }
}
