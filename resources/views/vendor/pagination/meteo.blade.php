@if ($paginator->hasPages())
<nav class="pager" aria-label="Pagination">
    <ul>
        <li>@if($paginator->onFirstPage())<span class="pg disabled" aria-disabled="true">Previous</span>@else<a class="pg" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>@endif</li>
        @foreach ($elements as $element)
            @if (is_string($element))<li><span class="pg gap" aria-hidden="true">…</span></li>@endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    <li>@if ($page == $paginator->currentPage())<span class="pg current" aria-current="page"><span class="sr-only">Page </span>{{ $page }}</span>@else<a class="pg" href="{{ $url }}"><span class="sr-only">Page </span>{{ $page }}</a>@endif</li>
                @endforeach
            @endif
        @endforeach
        <li>@if($paginator->hasMorePages())<a class="pg" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>@else<span class="pg disabled" aria-disabled="true">Next</span>@endif</li>
    </ul>
    <p class="pager-info">Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}</p>
</nav>
@endif
