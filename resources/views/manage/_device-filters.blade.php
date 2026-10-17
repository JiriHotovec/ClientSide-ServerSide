<form method="get" class="filters" role="search" aria-label="Filter devices"
      hx-get="{{ url()->current() }}" hx-trigger="input changed delay:600ms from:find input[type=search], change from:find select" hx-target="#results" hx-select="#results" hx-swap="outerHTML" hx-push-url="true" hx-sync="this:replace">
    <label>Search <input type="search" name="q" value="{{ $search }}" placeholder="Name or location"></label>
    @if(auth()->user()->isAdmin())
        <label>Show <select name="scope">
            <option value="mine" @selected($scope === 'mine')>My devices</option>
            <option value="all" @selected($scope === 'all')>All devices</option>
        </select></label>
    @endif
    <label>Visibility <select name="visibility">
        <option value="">Any</option>
        <option value="public" @selected($visibility === 'public')>Public</option>
        <option value="private" @selected($visibility === 'private')>Private</option>
    </select></label>
    @if($withSource ?? false)
    <label>Data source <select name="source">
        <option value="">Any</option>
        <option value="push" @selected($source === 'push')>Own data (push)</option>
        <option value="pull" @selected($source === 'pull')>External endpoint (pull)</option>
        <option value="error" @selected($source === 'error')>With errors</option>
    </select></label>
    @endif
    <noscript><button type="submit">Apply</button></noscript>
</form>
