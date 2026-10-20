{{-- Result of "Test fetch": what the source returns with the current mapping (nothing is saved). --}}
@if(! $result['ok'])
    <p class="preview-error" role="alert">{{ $result['error'] }}</p>
@else
    @php($readings = collect($result['readings']))
    @if($readings->isEmpty())
        <p class="preview-error" role="alert">{{ __('The source answered, but the mapping found nothing. Check the paths.') }}</p>
    @else
        <p class="preview-ok" role="status">{{ __('The source works. These are the values it returned:') }}</p>
        <div class="table-wrap">
            <table>
                <caption class="sr-only">{{ __('Values returned by the source') }}</caption>
                <thead><tr><th scope="col">{{ __('Dataset') }}</th><th scope="col">{{ __('Value') }}</th><th scope="col">{{ __('Time') }}</th></tr></thead>
                <tbody>
                @foreach($readings as $r)
                    @php($t = $types[$r['type']] ?? null)
                    <tr>
                        <th scope="row">{{ $t?->name ?? $r['type'] }}</th>
                        <td>@if($r['value'] === null)<span class="missing">{{ __('Missing value') }}</span>@else{{ $t ? \App\Support\DisplaySettings::format($r['value'], \App\Support\DisplaySettings::for($t)) : $r['value'] }}@endif</td>
                        <td>{{ $r['measured_at']->format('j. n. Y H:i') }} UTC</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endif
