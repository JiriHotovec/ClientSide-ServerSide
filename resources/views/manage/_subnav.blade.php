<nav class="subnav" aria-label="Administration">
    <ul>
        <li><a href="{{ route('manage.overview') }}" @if(request()->routeIs('manage.overview')) aria-current="page" @endif>Overview</a></li>
        <li><a href="{{ route('manage.devices') }}" @if(request()->routeIs('manage.devices*')) aria-current="page" @endif>Devices and data sources</a></li>
        <li><a href="{{ route('manage.types') }}" @if(request()->routeIs('manage.types*')) aria-current="page" @endif>Measurement types</a></li>
    </ul>
</nav>
<div role="status" aria-live="polite" data-manage-status class="status"></div>
