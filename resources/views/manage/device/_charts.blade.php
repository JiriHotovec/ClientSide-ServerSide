<p class="hint">{{ __('Arrange the charts of the device dashboard: pick a dataset and a style, add as many as you like. Without any, each dataset gets one default chart.') }}</p>

<div class="chart-empty" data-chart-empty @if($chartRows->isNotEmpty()) hidden @endif>
    <p>{{ __('No charts arranged. Each selected dataset gets one default chart.') }}</p>
    <button type="button" class="outline small" data-chart-autofill>{{ __('One chart per dataset') }}</button>
</div>
<ol id="chart-rows" class="chart-rows" hx-select="unset" hx-push-url="false" data-chart-rows data-row-url="{{ route('manage.charts.row') }}">
    @foreach($chartRows as $i => $row)
        @include('manage._chart-row', ['key' => $i, 'row' => $row, 'options' => $types])
    @endforeach
</ol>
<button type="button" class="outline small" hx-get="{{ route('manage.charts.row') }}" hx-include="[name='types[]']:checked" hx-target="#chart-rows" hx-select="unset" hx-push-url="false" hx-swap="beforeend" data-chart-add>{{ __('Add a chart') }}</button>
