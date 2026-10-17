{{-- $err: field => messages, $old: submitted input, $done: success message. Swapped in place by htmx. --}}
@php($u = auth()->user())
<section aria-labelledby="details-h">
    <h2 id="details-h">{{ __('Account details') }}</h2>
    @if(!empty($done))<p class="status" role="status">{{ $done }}</p>@endif
    <form method="post" action="{{ route('manage.profile.update') }}" hx-put="{{ route('manage.profile.update') }}" hx-target="closest section" hx-select="unset" hx-push-url="false" hx-swap="outerHTML" hx-disabled-elt="find button">
        @csrf @method('PUT')
        <label>{{ __('Name') }}
            <input name="name" value="{{ $old['name'] ?? $u->name }}" required maxlength="100" autocomplete="name" @if(!empty($err['name'])) aria-invalid="true" aria-describedby="name-err" @endif>
            @if(!empty($err['name']))<span class="field-error" id="name-err" role="alert">{{ $err['name'][0] }}</span>@endif</label>
        <label>{{ __('E-mail') }}
            <input type="email" name="email" value="{{ $old['email'] ?? $u->email }}" required autocomplete="email" @if(!empty($err['email'])) aria-invalid="true" aria-describedby="email-err" @endif>
            @if(!empty($err['email']))<span class="field-error" id="email-err" role="alert">{{ $err['email'][0] }}</span>@endif</label>
        <p><small>{{ __('Role:') }} {{ $u->isAdmin() ? __('Administrator') : __('Standard user') }}</small></p>
        <button type="submit">{{ __('Save details') }}</button>
    </form>
</section>
@if(!empty($done))
    {{-- Keep the account menu in sync without a reload (htmx out-of-band swaps). --}}
    <span id="menu-avatar" class="avatar" aria-hidden="true" hx-swap-oob="true">{{ $u->initials() }}</span>
    <span id="menu-name" class="user-name" hx-swap-oob="true">{{ $u->name }}</span>
    <strong id="menu-name-full" hx-swap-oob="true">{{ $u->name }}</strong>
    <small id="menu-email" hx-swap-oob="true">{{ $u->email }}</small>
@endif
