<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            // Generic message: do not reveal whether the e-mail exists.
            return back()->withErrors(['email' => 'These credentials do not match our records.'])->onlyInput('email');
        }

        $request->session()->regenerate(); // prevents session fixation

        return $this->done($request, redirect()->intended($this->home($request->user())));
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create($data); // role defaults to "user"; it is not mass-assignable
        Auth::login($user);
        $request->session()->regenerate();

        return $this->done($request, redirect($this->home($user)));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /** htmx forms can't swap a page with a different layout, so tell it to navigate normally. */
    private function done(Request $request, \Illuminate\Http\RedirectResponse $redirect)
    {
        return $request->header('HX-Request')
            ? response('', 204, ['HX-Redirect' => $redirect->getTargetUrl()])
            : $redirect;
    }

    private function home(User $user): string
    {
        return route('manage.overview');
    }
}
