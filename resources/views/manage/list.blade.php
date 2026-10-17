@if($devices->isEmpty())
    <p>No devices yet. Use the form below to create one.</p>
@else
<div class="table-wrap">
<table>
    <caption class="sr-only">Devices you can manage</caption>
    <thead><tr>
        <th scope="col">Name</th>
        @if(auth()->user()->isAdmin())<th scope="col">Owner</th>@endif
        <th scope="col">Datasets</th><th scope="col">Visibility</th><th scope="col">Data source</th><th scope="col">Actions</th>
    </tr></thead>
    <tbody>
    @foreach($devices as $device)
        <tr>
            <th scope="row">{{ $device->name }}<br><small>{{ $device->location }}</small></th>
            @if(auth()->user()->isAdmin())<td>{{ $device->owner->name }}</td>@endif
            <td>{{ $device->types->pluck('name')->join(', ') ?: '–' }}</td>
            <td>
                <span class="badge">{{ $device->is_public ? 'Public' : 'Private' }}</span>
                <button type="button" class="outline secondary small" data-action="visibility" data-id="{{ $device->id }}" data-public="{{ $device->is_public ? 0 : 1 }}">
                    Make {{ $device->is_public ? 'private' : 'public' }}<span class="sr-only"> {{ $device->name }}</span>
                </button>
            </td>
            <td>
                @if($device->last_error)<span role="note" class="error-text">Error: {{ $device->last_error }}</span>
                @elseif($device->source_url)Pulled every {{ $device->fetch_interval_min }} min{{ $device->last_fetched_at ? ', last '.$device->last_fetched_at->diffForHumans() : '' }}
                @else Own data (push)@endif
            </td>
            <td class="row-actions">
                <button type="button" class="outline small" hx-get="{{ route('manage.form', $device) }}" hx-target="#device-form" hx-swap="innerHTML">Edit<span class="sr-only"> {{ $device->name }}</span></button>
                <button type="button" class="outline small" data-action="token" data-id="{{ $device->id }}">New token<span class="sr-only"> for {{ $device->name }}</span></button>
                <button type="button" class="outline contrast small" data-action="delete" data-id="{{ $device->id }}" data-name="{{ $device->name }}">Delete<span class="sr-only"> {{ $device->name }}</span></button>
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
@endif
