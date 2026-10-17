{{-- Display settings. $name(field) builds the input name; $v = current values; $inherit = blank means "use the type's default". --}}
@php($blank = $inherit ? 'Use default' : null)
<div class="settings-grid" data-display-fields>
    <label>{{ __('Unit') }} @if($inherit)<small>{{ __('(blank = default)') }}</small>@endif
        <input name="{{ $name('unit') }}" maxlength="20" value="{{ $v['unit'] ?? '' }}" data-field="unit" @unless($inherit) required @endunless></label>
    <label>{{ __('Unit position') }}
        <select name="{{ $name('unit_position') }}" data-field="unit_position">
            @if($inherit)<option value="">{{ __('Use default') }}</option>@endif
            @foreach(['after' => __('After the value (21.4 °C)'), 'before' => __('Before the value (€ 21.4)'), 'none' => __('Not shown in values')] as $k => $l)
                <option value="{{ $k }}" @selected(($v['unit_position'] ?? ($inherit ? '' : 'after')) === $k)>{{ $l }}</option>
            @endforeach
        </select></label>
    <label>{{ __('Space between value and unit') }}
        <select name="{{ $name('unit_space') }}" data-field="unit_space">
            @if($inherit)<option value="">{{ __('Use default') }}</option>@endif
            <option value="1" @selected(isset($v['unit_space']) && $v['unit_space'] !== null && (bool) $v['unit_space'])>{{ __('Yes') }}</option>
            <option value="0" @selected(isset($v['unit_space']) && $v['unit_space'] !== null && ! $v['unit_space'])>{{ __('No') }}</option>
        </select></label>
    <label>{{ __('Decimal places') }}
        <input type="number" min="0" max="6" name="{{ $name('decimals') }}" value="{{ $v['decimals'] ?? ($inherit ? '' : 1) }}" data-field="decimals" @if($inherit) placeholder="{{ __('Default') }}" @endif></label>
    <label>{{ __('Chart type') }}
        <select name="{{ $name('chart_type') }}" data-field="chart_type">
            @if($inherit)<option value="">{{ __('Use default') }}</option>@endif
            <option value="line" @selected(($v['chart_type'] ?? ($inherit ? '' : 'line')) === 'line')>{{ __('Line') }}</option>
            <option value="bar" @selected(($v['chart_type'] ?? '') === 'bar')>{{ __('Bars') }}</option>
        </select></label>
    <label>{{ __('Chart color') }}
        <input type="color" name="{{ $name('color') }}" value="{{ $v['color'] ?? ($defaults['color'] ?? '#0077b6') }}" data-field="color" data-default="{{ $defaults['color'] ?? '' }}">
        @if($inherit)<small>{{ __('Leave unchanged to use the default color.') }}</small>@endif</label>
    <label>{{ __('Axis minimum') }} <input type="number" step="any" name="{{ $name('y_min') }}" value="{{ $v['y_min'] ?? '' }}" data-field="y_min" placeholder="{{ __('Automatic') }}"></label>
    <label>{{ __('Axis maximum') }} <input type="number" step="any" name="{{ $name('y_max') }}" value="{{ $v['y_max'] ?? '' }}" data-field="y_max" placeholder="{{ __('Automatic') }}"></label>
</div>
