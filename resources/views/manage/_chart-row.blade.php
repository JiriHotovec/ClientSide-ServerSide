{{-- One row of the chart editor. $key: unique field index, $options: datasets to pick from, $row: current values. --}}
<li class="chart-row" data-chart-row>
    <label>{{ __('Dataset') }}
        <select name="charts[{{ $key }}][type]">
            @foreach($options as $t)
                <option value="{{ $t->id }}" @selected((int) ($row['type'] ?? 0) === $t->id)>{{ $t->name }}</option>
            @endforeach
        </select></label>
    <label>{{ __('Chart style') }}
        <select name="charts[{{ $key }}][kind]">
            @foreach(\App\Support\ChartKinds::ALL as $k => $l)
                <option value="{{ $k }}" @selected(($row['kind'] ?? 'line') === $k)>{{ __($l) }}</option>
            @endforeach
        </select></label>
    <label>{{ __('Title (optional)') }} <input name="charts[{{ $key }}][title]" maxlength="80" value="{{ $row['title'] ?? '' }}"></label>
    <div class="chart-row-actions">
        <button type="button" class="outline small" data-chart-move="up">{{ __('Move up') }}</button>
        <button type="button" class="outline small" data-chart-move="down">{{ __('Move down') }}</button>
        <button type="button" class="outline contrast small" data-chart-remove>{{ __('Remove') }}</button>
    </div>
</li>
