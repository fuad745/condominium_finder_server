{{-- Leaflet preview of a drawn footprint. $polygon = [[lat,lng],...];
     optional $oldPolygon shown dashed for area-edit comparison. --}}
@php $mapId = 'area-map-'.uniqid(); @endphp

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div id="{{ $mapId }}"
     style="height: 340px; border-radius: 12px; border: 1px solid rgba(128,128,128,.35);"></div>
<p class="text-sm" style="margin-top:.5rem">
    @isset($oldPolygon)
        <span style="color:#2563EB">■</span> current
        &nbsp;<span style="color:#D97706">■</span> suggested
    @endisset
</p>

<script>
  (function () {
    const map = L.map(@json($mapId));
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors',
    }).addTo(map);

    const suggested = L.polygon(@json($polygon), {
      color: '#D97706', weight: 3, fillOpacity: 0.15,
    }).addTo(map);

    @isset($oldPolygon)
      L.polygon(@json($oldPolygon), {
        color: '#2563EB', weight: 2, dashArray: '6 6', fillOpacity: 0.05,
      }).addTo(map);
    @endisset

    map.fitBounds(suggested.getBounds().pad(0.4));
  })();
</script>
