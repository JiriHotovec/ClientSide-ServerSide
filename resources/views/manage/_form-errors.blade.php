@if($errors->any())
    <div class="status status-error" role="alert"><strong>{{ __('Please fix the following:') }}</strong>
        <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif
