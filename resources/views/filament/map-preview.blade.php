@php
    /** Embedded OpenStreetMap preview of a single pin. */
    $d = 0.0035;
    $bbox = implode(',', [$lng - $d, $lat - $d, $lng + $d, $lat + $d]);
    $embed = 'https://www.openstreetmap.org/export/embed.html?bbox=' . urlencode($bbox)
        . '&layer=mapnik&marker=' . urlencode($lat . ',' . $lng);
    $osm = 'https://www.openstreetmap.org/?mlat=' . $lat . '&mlon=' . $lng . '#map=18/' . $lat . '/' . $lng;
    $gmaps = 'https://maps.google.com/?q=' . $lat . ',' . $lng;
@endphp

<div class="space-y-2">
    @isset($label)
        <p class="text-sm font-medium">{{ $label }}</p>
    @endisset
    <iframe
        src="{{ $embed }}"
        style="width: 100%; height: 320px; border: 1px solid rgba(128,128,128,.35); border-radius: 12px;"
        loading="lazy"
        referrerpolicy="no-referrer"
    ></iframe>
    <p class="text-sm">
        <span class="font-mono">{{ number_format($lat, 6) }}, {{ number_format($lng, 6) }}</span>
        &nbsp;·&nbsp;
        <a href="{{ $osm }}" target="_blank" rel="noopener" class="underline">OpenStreetMap</a>
        &nbsp;·&nbsp;
        <a href="{{ $gmaps }}" target="_blank" rel="noopener" class="underline">Google Maps</a>
    </p>
</div>
