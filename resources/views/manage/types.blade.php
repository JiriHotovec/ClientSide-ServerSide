@extends('layouts.admin')
@section('title', 'Measurement types')
@section('content')
<header class="page-head head-row">
    <div>
        <h1>Measurement types</h1>
        <p class="lead">Global types are shared with everyone. Private types are visible only to you{{ auth()->user()->isAdmin() ? ' and administrators' : '' }}.</p>
    </div>
    <a role="button" href="{{ route('manage.types.create') }}">New measurement type</a>
</header>
<form method="get" class="filters" role="search" aria-label="Filter measurement types"
      hx-get="{{ route('manage.types') }}" hx-trigger="input changed delay:600ms from:find input[type=search], change from:find select" hx-target="#results" hx-select="#results" hx-swap="outerHTML" hx-push-url="true" hx-sync="this:replace">
    <label>Search <input type="search" name="q" value="{{ $search }}" placeholder="Name or key"></label>
    <label>Show <select name="scope">
        <option value="available" @selected($scope === 'available')>Available to me</option>
        <option value="global" @selected($scope === 'global')>Global only</option>
        <option value="mine" @selected($scope === 'mine')>Created by me</option>
        @if(auth()->user()->isAdmin())<option value="all" @selected($scope === 'all')>Everything</option>@endif
    </select></label>
    <noscript><button type="submit">Apply</button></noscript>
</form>
<div id="results" hx-boost="true" hx-target="#results" hx-select="#results" hx-swap="outerHTML" hx-push-url="true">
@if($types->isEmpty())
    <p class="empty" role="status">No measurement types match. <a href="{{ route('manage.types.create') }}">Create one</a>.</p>
@else
<div class="table-wrap">
<table>
    <caption class="sr-only">Measurement types</caption>
    <thead><tr><th scope="col">Type</th><th scope="col">Example</th><th scope="col">Chart</th><th scope="col">Availability</th><th scope="col">Actions</th></tr></thead>
    <tbody>
    @foreach($types as $t)
        @php($d = \App\Support\DisplaySettings::for($t))
        <tr>
            <th scope="row">{{ $t->name }}<br><small><code>{{ $t->key }}</code></small></th>
            <td>{{ \App\Support\DisplaySettings::format(21.456, $d) }}</td>
            <td><span class="swatch" data-color="{{ $d['color'] }}" aria-hidden="true"></span>{{ $d['chart_type'] === 'bar' ? 'Bars' : 'Line' }}</td>
            <td>
                <span class="badge {{ $t->is_public ? '' : 'badge-private' }}">{{ $t->is_public ? 'Global' : 'Private' }}</span>
                @if($t->creator && $t->created_by !== auth()->id())<small>by {{ $t->creator->name }}</small>@endif
            </td>
            <td class="row-actions">
                @can('update', $t)
                    <a role="button" class="outline small" href="{{ route('manage.types.edit', $t) }}" hx-boost="false">Edit<span class="sr-only"> {{ $t->name }}</span></a>
                    <form method="post" action="{{ route('manage.types.destroy', $t) }}" class="inline-form" hx-confirm="Delete measurement type {{ $t->name }}?">@csrf @method('DELETE')
                        <button type="submit" class="outline contrast small">Delete<span class="sr-only"> {{ $t->name }}</span></button>
                    </form>
                @else
                    <small>Read only</small>
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
