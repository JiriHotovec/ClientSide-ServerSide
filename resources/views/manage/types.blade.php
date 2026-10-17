@extends('layouts.admin')
@section('title', __('Measurement types'))
@section('content')
<header class="page-head head-row">
    <div>
        <h1>{{ __('Measurement types') }}</h1>
        <p class="lead">{{ auth()->user()->isAdmin() ? __('Global types are shared with everyone. Private types are visible only to you and administrators.') : __('Global types are shared with everyone. Private types are visible only to you.') }}</p>
    </div>
    <a role="button" href="{{ route('manage.types.create') }}">{{ __('New measurement type') }}</a>
</header>
<form method="get" class="filters" role="search" aria-label="{{ __('Filter measurement types') }}"
      hx-get="{{ route('manage.types') }}" hx-trigger="input changed delay:600ms from:find input[type=search], change from:find select" hx-target="#results" hx-select="#results" hx-swap="outerHTML" hx-push-url="true" hx-sync="this:replace">
    <label>{{ __('Search') }} <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('Name or key') }}"></label>
    <label>{{ __('Show') }} <select name="scope">
        <option value="available" @selected($scope === 'available')>{{ __('Available to me') }}</option>
        <option value="global" @selected($scope === 'global')>{{ __('Global only') }}</option>
        <option value="mine" @selected($scope === 'mine')>{{ __('Created by me') }}</option>
        @if(auth()->user()->isAdmin())<option value="all" @selected($scope === 'all')>{{ __('Everything') }}</option>@endif
    </select></label>
    <noscript><button type="submit">{{ __('Apply') }}</button></noscript>
</form>
<div id="results" hx-boost="true" hx-target="#results" hx-select="#results" hx-swap="outerHTML" hx-push-url="true">
@if($types->isEmpty())
    <p class="empty" role="status">{{ __('No measurement types match.') }} <a href="{{ route('manage.types.create') }}">{{ __('Create one') }}</a>.</p>
@else
<div class="table-wrap">
<table>
    <caption class="sr-only">{{ __('Measurement types') }}</caption>
    <thead><tr><th scope="col">{{ __('Type') }}</th><th scope="col">{{ __('Example') }}</th><th scope="col">{{ __('Category') }}</th><th scope="col">{{ __('Chart') }}</th><th scope="col">{{ __('Availability') }}</th><th scope="col">{{ __('Actions') }}</th></tr></thead>
    <tbody>
    @foreach($types as $t)
        @php($d = \App\Support\DisplaySettings::for($t))
        <tr>
            <th scope="row">{{ $t->name }}<br><small><code>{{ $t->key }}</code></small></th>
            <td>{{ \App\Support\DisplaySettings::format(21.456, $d) }}</td>
            <td>{{ \App\Support\Categories::label($t->category) }}</td>
            <td><span class="swatch" data-color="{{ $d['color'] }}" aria-hidden="true"></span>{{ $d['chart_type'] === 'bar' ? __('Bars') : __('Line') }}</td>
            <td>
                <span class="badge {{ $t->is_public ? '' : 'badge-private' }}">{{ $t->is_public ? __('Global') : __('Private') }}</span>
                @if($t->creator && $t->created_by !== auth()->id())<small>{{ __('by :name', ['name' => $t->creator->name]) }}</small>@endif
            </td>
            <td class="row-actions">
                @can('update', $t)
                    <a role="button" class="outline small" href="{{ route('manage.types.edit', $t) }}" hx-boost="false">{{ __('Edit') }}<span class="sr-only"> {{ $t->name }}</span></a>
                    <form method="post" action="{{ route('manage.types.destroy', $t) }}" class="inline-form" hx-confirm="{{ __('Delete measurement type :name?', ['name' => $t->name]) }}">@csrf @method('DELETE')
                        <button type="submit" class="outline contrast small">{{ __('Delete') }}<span class="sr-only"> {{ $t->name }}</span></button>
                    </form>
                @else
                    <small>{{ __('Read only') }}</small>
                @endcan
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
{{ $types->links() }}
@endif
</div>
@endsection
