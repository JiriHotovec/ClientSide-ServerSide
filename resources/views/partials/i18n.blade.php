{{-- Interface strings and language for the browser scripts (not executed: type="application/json"). --}}
<script type="application/json" id="i18n">{!! json_encode(['locale' => app()->getLocale(), 'strings' => \App\Support\JsStrings::all()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
