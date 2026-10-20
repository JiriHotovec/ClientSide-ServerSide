<form method="get" class="filters" role="search" aria-label="{{ __('Filter devices') }}"
      hx-get="{{ url()->current() }}" hx-trigger="input changed delay:600ms from:find input[type=search], change from:find select" hx-target="#results" hx-select="#results" hx-swap="outerHTML" hx-push-url="true" hx-sync="this:replace">
    <label>{{ __('Search') }} <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('Name or location') }}"></label>
    @if(auth()->user()->isAdmin())
        <label>{{ __('Show') }} <select name="scope">
            <option value="mine" @selected($scope === 'mine')>{{ __('My devices') }}</option>
            <option value="all" @selected($scope === 'all')>{{ __('All devices') }}</option>
        </select></label>
    @endif
    <label>{{ __('Visibility') }} <select name="visibility">
        <option value="">{{ __('Any') }}</option>
        <option value="public" @selected($visibility === 'public')>{{ __('Public') }}</option>
        <option value="private" @selected($visibility === 'private')>{{ __('Private') }}</option>
    </select></label>
    @if($withSource ?? false)
    <label>{{ __('Data source') }} <select name="source">
        <option value="">{{ __('Any') }}</option>
        <option value="push" @selected($source === 'push')>{{ __('Own data (push)') }}</option>
        <option value="pull" @selected($source === 'pull')>{{ __('External endpoint (pull)') }}</option>
        <option value="error" @selected($source === 'error')>{{ __('With errors') }}</option>
    </select></label>
    @endif
    <noscript><button type="submit">{{ __('Apply') }}</button></noscript>
</form>
