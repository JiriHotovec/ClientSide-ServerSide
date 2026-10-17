<section aria-labelledby="pw-h">
    <h2 id="pw-h">Change password</h2>
    @if(!empty($done))<p class="status" role="status">{{ $done }}</p>@endif
    <form method="post" action="{{ route('manage.profile.password') }}" hx-put="{{ route('manage.profile.password') }}" hx-target="closest section" hx-swap="outerHTML" hx-disabled-elt="find button">
        @csrf @method('PUT')
        <label>Current password
            <input type="password" name="current_password" required autocomplete="current-password" @if(!empty($err['current_password'])) aria-invalid="true" aria-describedby="cur-err" @endif>
            @if(!empty($err['current_password']))<span class="field-error" id="cur-err" role="alert">{{ $err['current_password'][0] }}</span>@endif</label>
        <label>New password
            <input type="password" name="password" required autocomplete="new-password" aria-describedby="pw-help" @if(!empty($err['password'])) aria-invalid="true" @endif>
            <small id="pw-help">At least 10 characters, with letters and numbers.</small>
            @if(!empty($err['password']))<span class="field-error" role="alert">{{ $err['password'][0] }}</span>@endif</label>
        <label>Confirm new password
            <input type="password" name="password_confirmation" required autocomplete="new-password"></label>
        <button type="submit">Change password</button>
    </form>
</section>
