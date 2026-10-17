<p class="hint">{{ __('Pick everything this device measures. You can tune how a dataset looks after you tick it.') }}
    {{ __('Need a type that is not listed?') }} <a href="{{ route('manage.types.create') }}" hx-boost="false">{{ __('Create a measurement type') }}</a> {{ __('first.') }}</p>

<div class="chips" data-chips aria-live="polite" aria-label="{{ __('Selected datasets') }}">
    @foreach($types->whereIn('id', $pickedIds->all()) as $t)
        <button type="button" class="chip" data-chip="{{ $t->id }}">{{ $t->name }} <span aria-hidden="true">×</span><span class="sr-only">{{ __('Remove') }}</span></button>
    @endforeach
    <span class="chips-empty" data-chips-empty @if($pickedIds->isNotEmpty()) hidden @endif>{{ __('No dataset selected yet.') }}</span>
</div>

<label class="type-filter">{{ __('Search datasets') }} <input type="search" data-type-filter placeholder="{{ __('Temperature, wind, electricity…') }}" autocomplete="off"></label>

@forelse($types->groupBy('category')->sortBy(fn ($g, $k) => array_search($k, \App\Support\Categories::keys())) as $cat => $group)
    <fieldset class="type-group" data-type-group>
        <legend>{{ \App\Support\Categories::label($cat) }} <small>{{ $group->count() }}</small></legend>
        @foreach($group as $t)
            @php($on = $pickedIds->contains($t->id))
            @php($pv = old("ts.{$t->id}", $selected->has($t->id) ? $selected[$t->id]->pivot->only(\App\Support\DisplaySettings::FIELDS) : []))
            @php($defaults = \App\Support\DisplaySettings::for($t))
            @php($customized = collect($pv)->filter(fn ($v, $k) => filled($v) && ! ($k === 'color' && strtolower((string) $v) === strtolower((string) $defaults['color'])))->isNotEmpty())
            <div class="type-pick" data-type-row data-search="{{ mb_strtolower($t->name.' '.$t->key.' '.$t->unit) }}">
                <label class="pick-line">
                    <input type="checkbox" name="types[]" value="{{ $t->id }}" data-type-toggle data-name="{{ $t->name }}" data-key="{{ $t->key }}" data-default-chart="{{ $defaults['chart_type'] }}" @checked($on)>
                    <span>{{ $t->name }}</span> <small>({{ $t->unit ?: '–' }}{{ $t->is_public ? '' : ', '.__('private') }})</small>
                </label>
                <div class="appearance {{ $on ? 'is-on' : '' }}" data-type-settings="{{ $t->id }}" data-defaults="{{ json_encode($defaults, JSON_UNESCAPED_UNICODE) }}">
                    <div class="appearance-head">
                        <span class="badge {{ $customized ? '' : 'badge-private' }}" data-appearance-state data-label-default="{{ __('Default appearance') }}" data-label-custom="{{ __('Customized') }}">{{ $customized ? __('Customized') : __('Default appearance') }}</span>
                        <span class="appearance-preview" data-appearance-preview></span>
                        <button type="button" class="outline small" data-appearance-toggle aria-expanded="false" data-label-open="{{ __('Hide appearance') }}" data-label-closed="{{ __('Customize appearance') }}">{{ __('Customize appearance') }}</button>
                    </div>
                    <div class="appearance-body" data-appearance-body>
                        <p class="hint">{{ __('Only change what should differ from the type defaults.') }}</p>
                        @include('manage._display-fields', ['inherit' => true, 'part' => 'basic', 'v' => $pv, 'defaults' => $defaults, 'name' => fn ($f) => "ts[{$t->id}][{$f}]"])
                        <details class="more">
                            <summary>{{ __('More options') }}</summary>
                            @include('manage._display-fields', ['inherit' => true, 'part' => 'advanced', 'v' => $pv, 'defaults' => $defaults, 'name' => fn ($f) => "ts[{$t->id}][{$f}]"])
                        </details>
                        <button type="button" class="outline secondary small" data-appearance-reset>{{ __('Use defaults') }}</button>
                    </div>
                </div>
            </div>
        @endforeach
    </fieldset>
@empty
    <p class="empty">{{ __('No measurement types are available yet.') }} <a href="{{ route('manage.types.create') }}">{{ __('Create one') }}</a>.</p>
@endforelse
<p class="empty" data-type-filter-empty hidden role="status">{{ __('No dataset matches your search.') }}</p>
