{{-- Tiny preview of a chart style (decorative). --}}
<svg viewBox="0 0 40 24" width="40" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($kind)
    @case('area')<path d="M2 20 L2 14 L10 8 L18 13 L26 5 L38 10 L38 20 Z" fill="currentColor" fill-opacity=".25" stroke="none"/><path d="M2 14 L10 8 L18 13 L26 5 L38 10"/>@break
    @case('step')<path d="M2 18 H10 V9 H18 V15 H26 V5 H38"/>@break
    @case('bar')<path d="M6 22 V13 M13 22 V7 M20 22 V16 M27 22 V9 M34 22 V12" stroke-width="4" stroke-linecap="butt"/>@break
    @case('scatter')<circle cx="6" cy="16" r="1.6" fill="currentColor"/><circle cx="14" cy="8" r="1.6" fill="currentColor"/><circle cx="21" cy="14" r="1.6" fill="currentColor"/><circle cx="28" cy="6" r="1.6" fill="currentColor"/><circle cx="35" cy="11" r="1.6" fill="currentColor"/>@break
    @case('gauge')<path d="M5 21 A15 15 0 0 1 35 21" stroke-opacity=".3"/><path d="M5 21 A15 15 0 0 1 24 7"/>@break
    @case('rose')<path d="M20 12 L20 2 M20 12 L30 6 M20 12 L33 14 M20 12 L27 22 M20 12 L12 21 M20 12 L7 15 M20 12 L10 5" stroke-width="2.4"/>@break
    @default<path d="M2 18 L10 9 L18 14 L26 5 L38 11"/>
@endswitch
</svg>
