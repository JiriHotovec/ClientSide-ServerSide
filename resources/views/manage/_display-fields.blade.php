{{-- Display settings. $name(field) builds the input name; $v = current values; $inherit = blank means "use the type's default".
     $part: 'all' (type form), 'basic' (unit, decimals, color) or 'advanced' (everything else) for the device form. --}}
@php($part = $part ?? 'all')
@php($basicFields = ['unit', 'decimals', 'color'])
@php($show = fn (string $f) => $part === 'all' || ($part === 'basic') === in_array($f, $basicFields, true))
@php($errKey = fn (string $f) => str_replace(['][', '[', ']'], ['.', '.', ''], $name($f)))
@php($errId = fn (string $f) => 'err-'.str_replace('.', '-', $errKey($f)))
@php($invalid = fn (string $f) => $errors->has($errKey($f)) ? 'aria-invalid="true" aria-describedby="'.e($errId($f)).'"' : '')
<div class="settings-grid" data-display-fields>
    @if($show('unit'))
    <label>{{ __('Unit') }} @if($inherit)<small>{{ __('(blank = default)') }}</small>@endif
        <input name="{{ $name('unit') }}" maxlength="20" value="{{ $v['unit'] ?? '' }}" data-field="unit" {!! $invalid('unit') !!} @unless($inherit) required @endunless>@include('manage._field-error', ['field' => $errKey('unit'), 'id' => $errId('unit')])</label>
    @endif
    @if($show('unit_position'))
    <label>{{ __('Unit position') }}
        <select name="{{ $name('unit_position') }}" data-field="unit_position" {!! $invalid('unit_position') !!}>
            @if($inherit)<option value="">{{ __('Use default') }}</option>@endif
            @foreach(['after' => __('After the value (21.4 °C)'), 'before' => __('Before the value (€ 21.4)'), 'none' => __('Not shown in values')] as $k => $l)
                <option value="{{ $k }}" @selected(($v['unit_position'] ?? ($inherit ? '' : 'after')) === $k)>{{ $l }}</option>
            @endforeach
        </select>@include('manage._field-error', ['field' => $errKey('unit_position'), 'id' => $errId('unit_position')])</label>
    @endif
    @if($show('unit_space'))
    <label>{{ __('Space between value and unit') }}
        <select name="{{ $name('unit_space') }}" data-field="unit_space" {!! $invalid('unit_space') !!}>
            @if($inherit)<option value="">{{ __('Use default') }}</option>@endif
            <option value="1" @selected(isset($v['unit_space']) && $v['unit_space'] !== null && (bool) $v['unit_space'])>{{ __('Yes') }}</option>
            <option value="0" @selected(isset($v['unit_space']) && $v['unit_space'] !== null && ! $v['unit_space'])>{{ __('No') }}</option>
        </select>@include('manage._field-error', ['field' => $errKey('unit_space'), 'id' => $errId('unit_space')])</label>
    @endif
    @if($show('decimals'))
    <label>{{ __('Decimal places') }}
        <input type="number" min="0" max="6" name="{{ $name('decimals') }}" value="{{ $v['decimals'] ?? ($inherit ? '' : 1) }}" data-field="decimals" {!! $invalid('decimals') !!} @if($inherit) placeholder="{{ __('Default') }}" @endif>@include('manage._field-error', ['field' => $errKey('decimals'), 'id' => $errId('decimals')])</label>
    @endif
    @if($show('chart_type'))
    <label>{{ __('Chart type') }}
        <select name="{{ $name('chart_type') }}" data-field="chart_type" {!! $invalid('chart_type') !!}>
            @if($inherit)<option value="">{{ __('Use default') }}</option>@endif
            @foreach(\App\Support\ChartKinds::ALL as $k => $l)
                <option value="{{ $k }}" @selected(($v['chart_type'] ?? ($inherit ? '' : 'line')) === $k)>{{ __($l) }}</option>
            @endforeach
        </select>@include('manage._field-error', ['field' => $errKey('chart_type'), 'id' => $errId('chart_type')])</label>
    @endif
    @if($show('color'))
    <label>{{ __('Chart color') }}
        <input type="color" name="{{ $name('color') }}" value="{{ $v['color'] ?? ($defaults['color'] ?? '#0077b6') }}" data-field="color" {!! $invalid('color') !!} data-default="{{ $defaults['color'] ?? '' }}">
        @if($inherit)<small>{{ __('Leave unchanged to use the default color.') }}</small>@endif@include('manage._field-error', ['field' => $errKey('color'), 'id' => $errId('color')])</label>
    @endif
    @if($show('y_min'))
    <label>{{ __('Axis minimum') }} <input type="number" step="any" name="{{ $name('y_min') }}" value="{{ $v['y_min'] ?? '' }}" data-field="y_min" {!! $invalid('y_min') !!} placeholder="{{ __('Automatic') }}">@include('manage._field-error', ['field' => $errKey('y_min'), 'id' => $errId('y_min')])</label>
    @endif
    @if($show('y_max'))
    <label>{{ __('Axis maximum') }} <input type="number" step="any" name="{{ $name('y_max') }}" value="{{ $v['y_max'] ?? '' }}" data-field="y_max" {!! $invalid('y_max') !!} placeholder="{{ __('Automatic') }}">@include('manage._field-error', ['field' => $errKey('y_max'), 'id' => $errId('y_max')])</label>
    @endif
</div>
