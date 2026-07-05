{{-- Side-by-side "current vs suggested" location comparison for edit review. --}}
<div class="grid gap-4 sm:grid-cols-2">
    <div>
        @include('filament.map-preview', [
            'lat' => $oldLat,
            'lng' => $oldLng,
            'label' => 'Current location',
        ])
    </div>
    <div>
        @include('filament.map-preview', [
            'lat' => $newLat,
            'lng' => $newLng,
            'label' => 'Suggested location',
        ])
    </div>
</div>

@php
    $meters = (function (float $lat1, float $lng1, float $lat2, float $lng2): int {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return (int) round($r * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    })($oldLat, $oldLng, $newLat, $newLng);
@endphp

<p class="mt-3 text-sm font-medium">The pin would move ≈ {{ number_format($meters) }} m.</p>
