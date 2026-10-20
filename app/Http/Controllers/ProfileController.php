<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Profile forms work with and without JavaScript: htmx requests get the re-rendered section, plain posts get a redirect. */
class ProfileController extends Controller
{
    public function edit()
    {
        return view('manage.profile');
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($request->user()->id)],
        ]);

        if ($validator->fails()) {
            return $this->respond($request, 'manage._profile-details', 'details', $validator->errors()->messages(), $request->only('name', 'email'));
        }

        $request->user()->update($validator->validated());

        return $this->respond($request, 'manage._profile-details', 'details', [], [], __('Profile updated.'));
    }

    public function password(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ]);

        if ($validator->fails()) {
            return $this->respond($request, 'manage._profile-password', 'password', $validator->errors()->messages(), []);
        }

        $request->user()->update(['password' => $request->input('password')]);
        $request->session()->regenerate();

        return $this->respond($request, 'manage._profile-password', 'password', [], [], __('Password changed.'));
    }

    private function respond(Request $request, string $partial, string $bag, array $errors, array $old, ?string $done = null)
    {
        if ($request->header('HX-Request')) {
            return response(view($partial, ['err' => $errors, 'old' => $old, 'done' => $done]), $errors ? 422 : 200);
        }

        $redirect = redirect()->route('manage.profile');

        return $errors
            ? $redirect->withErrors($errors, $bag)->withInput($old)
            : $redirect->with($bag.'_done', $done);
    }
}
