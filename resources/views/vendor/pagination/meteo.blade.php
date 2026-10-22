@if ($paginator->hasPages())
<nav class="pager" aria-label="{{ __('Page navigation') }}">
    <ul>
        <li>@if($paginator->onFirstPage())<span class="pg disabled">{{ __('Previous') }}</span>@else<a class="pg" href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('Previous') }}</a>@endif</li>
        @foreach ($elements as $element)
            @if (is_string($element))<li><span class="pg gap" aria-hidden="true">…</span></li>@endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    <li>@if ($page == $paginator->currentPage())<span class="pg current" aria-current="page"><span class="sr-only">{{ __('Page') }} </span>{{ $page }}</span>@else<a class="pg" href="{{ $url }}"><span class="sr-only">{{ __('Page') }} </span>{{ $page }}</a>@endif</li>
                @endforeach
            @endif
        @endforeach
        <li>@if($paginator->hasMorePages())<a class="pg" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('Next') }}</a>@else<span class="pg disabled">{{ __('Next') }}</span>@endif</li>
    </ul>
    <p class="pager-info">{{ __('Showing :from–:to of :total', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()]) }}</p>
</nav>
@endif
