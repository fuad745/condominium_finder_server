{{-- Leaflet preview of a drawn footprint. $polygon = [[lat,lng],...];
     optional $oldPolygon shown dashed for area-edit comparison.

     Rendered inside a Filament modal, where <script> tags injected by
     Livewire do not execute — so the map boots from Alpine's x-init,
     and Leaflet is served locally (public/vendor/leaflet) instead of a
     CDN the admin's browser may not reach. --}}
<div
    x-data='{
        polygon: @json($polygon),
        oldPolygon: @json($oldPolygon ?? null),
        async boot() {
            const el = this.$refs.map;
            if (el.dataset.booted) return;
            el.dataset.booted = "1";
            try {
                if (! document.getElementById("leaflet-css")) {
                    const css = document.createElement("link");
                    css.id = "leaflet-css";
                    css.rel = "stylesheet";
                    css.href = @json(asset('vendor/leaflet/leaflet.css'));
                    document.head.appendChild(css);
                }
                if (! window.L) {
                    await new Promise((done, fail) => {
                        let js = document.getElementById("leaflet-js");
                        if (! js) {
                            js = document.createElement("script");
                            js.id = "leaflet-js";
                            js.src = @json(asset('vendor/leaflet/leaflet.js'));
                            document.head.appendChild(js);
                        }
                        js.addEventListener("load", done);
                        js.addEventListener("error", fail);
                        if (window.L) done();
                    });
                }
                // Let the modal finish its opening transition first,
                // otherwise Leaflet measures a hidden 0×0 container.
                await new Promise(r => setTimeout(r, 200));
                const map = L.map(el);
                L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
                    attribution: "© OpenStreetMap contributors",
                }).addTo(map);
                const suggested = L.polygon(this.polygon, {
                    color: "#D97706", weight: 3, fillOpacity: 0.15,
                }).addTo(map);
                if (this.oldPolygon) {
                    L.polygon(this.oldPolygon, {
                        color: "#2563EB", weight: 2, dashArray: "6 6", fillOpacity: 0.05,
                    }).addTo(map);
                }
                map.fitBounds(suggested.getBounds().pad(0.4));
                setTimeout(() => map.invalidateSize(), 300);
            } catch (e) {
                el.textContent = "Could not load the map: " + e;
            }
        },
    }'
    x-init="boot()"
>
    <div x-ref="map"
         style="height: 340px; border-radius: 12px; border: 1px solid rgba(128,128,128,.35);"></div>
    <p class="text-sm" style="margin-top:.5rem">
        @isset($oldPolygon)
            <span style="color:#2563EB">■</span> current
            &nbsp;<span style="color:#D97706">■</span> suggested
        @endisset
    </p>
</div>
