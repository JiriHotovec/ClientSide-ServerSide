{{-- Language switch. Only the interface is translated; content created by users stays as written.
     Public pages have one URL per language, so the switch there is a plain link (crawlable, no form). --}}
@php($alt = \App\Support\Locales::isPrefixed(request()) ? \App\Support\Locales::alternates(request()) : null)
<div class="lang-switch" role="group" aria-label="{{ __('Language') }}">
    @foreach(\App\Support\Locales::ALL as $code => $name)
        @if($alt)
            <a href="{{ $alt[$code] }}" hreflang="{{ $code }}" lang="{{ $code }}" title="{{ $name }}" @if(app()->getLocale() === $code) aria-current="true" @endif><span aria-hidden="true">{{ strtoupper($code) }}</span><span class="sr-only">{{ $name }}</span></a>
        @else
            <form method="post" action="{{ route('locale', $code) }}" hx-boost="false">@csrf
                <button type="submit" lang="{{ $code }}" @if(app()->getLocale() === $code) aria-current="true" @endif title="{{ $name }}"><span aria-hidden="true">{{ strtoupper($code) }}</span><span class="sr-only">{{ $name }}</span></button>
            </form>
        @endif
    @endforeach
</div>
