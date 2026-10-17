{{-- One row of the chart editor. $key: unique field index, $options: datasets to pick from, $row: current values. --}}
<li class="chart-row" data-chart-row>
    <div class="chart-row-main">
        <label>{{ __('Dataset') }}
            <select name="charts[{{ $key }}][type]" data-chart-type>
                @foreach($options as $t)
                    <option value="{{ $t->id }}" @selected((int) ($row['type'] ?? 0) === $t->id)>{{ $t->name }}</option>
                @endforeach
            </select></label>
        <label>{{ __('Title (optional)') }} <input name="charts[{{ $key }}][title]" maxlength="80" value="{{ $row['title'] ?? '' }}"></label>
    </div>
    <fieldset class="style-cards">
        <legend>{{ __('Chart style') }}</legend>
        @foreach(\App\Support\ChartKinds::ALL as $k => $l)
            <label class="style-card">
                <input type="radio" name="charts[{{ $key }}][kind]" value="{{ $k }}" @checked(($row['kind'] ?? 'line') === $k)>
                @include('manage.device._chart-icon', ['kind' => $k])
                <span>{{ __($l) }}</span>
            </label>
        @endforeach
    </fieldset>
    <p class="row-warning" data-chart-orphan role="alert" hidden>{{ __('This dataset is not selected for the device anymore. Select it again or remove the chart.') }}</p>
    <div class="chart-row-actions">
        <button type="button" class="outline small" data-chart-move="up">{{ __('Move up') }}</button>
        <button type="button" class="outline small" data-chart-move="down">{{ __('Move down') }}</button>
        <button type="button" class="outline contrast small" data-chart-remove>{{ __('Remove') }}</button>
    </div>
</li>
