{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach($urls as $u)
    <url>
        <loc>{{ $u['loc'] }}</loc>
@foreach($u['alt'] as $code => $href)
        <xhtml:link rel="alternate" hreflang="{{ $code }}" href="{{ $href }}"/>
@endforeach
        <xhtml:link rel="alternate" hreflang="x-default" href="{{ $u['alt'][$default] }}"/>
@if($u['lastmod'])
        <lastmod>{{ $u['lastmod'] }}</lastmod>
@endif
    </url>
@endforeach
</urlset>
