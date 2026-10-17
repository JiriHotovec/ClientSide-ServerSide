{{-- Leaflet (vendored) + our map script; map tiles come from OpenStreetMap (allowed in the CSP img-src). --}}
@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
@endpush
@push('scripts')
    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
    <script src="{{ asset('js/map.js') }}" data-leaflet-images="{{ asset('vendor/leaflet/images') }}/"></script>
@endpush
