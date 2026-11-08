{{-- Peta Leaflet + OpenStreetMap untuk Safe Meeting Point.
     Parameter: $points (koleksi MeetingPoint), $selected (id terpilih, opsional), $sync (true = sinkron dengan input name="meeting_point_id"),
                $circle (opsional: ['lat'=>, 'lng'=>, 'km'=>] radius COD), $height (px, default 300), $mapId (opsional) --}}
@php
  $mapId = $mapId ?? 'map-'.substr(md5(uniqid('', true)), 0, 6);
  $mapPoints = collect($points)->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'address' => $p->address, 'lat' => (float) $p->latitude, 'lng' => (float) $p->longitude, 'rec' => (bool) $p->recommended])->values();
@endphp
@once
  @push('head')<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">@endpush
  @push('scripts')<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>@endpush
@endonce
<div id="{{ $mapId }}" class="z-0 w-full overflow-hidden rounded-xl bg-sand" style="height: {{ $height ?? 300 }}px" role="application" aria-label="Peta titik temu"></div>
<p class="mt-1 text-[11px] text-ink/50">Peta © kontributor OpenStreetMap.@if($sync ?? false) Klik penanda untuk memilih titik temu.@endif</p>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof L === 'undefined') return;
  var el = document.getElementById(@json($mapId));
  if (!el) return;
  L.Icon.Default.imagePath = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/';
  var pts = @json($mapPoints), sync = @json((bool) ($sync ?? false)), circle = @json($circle ?? null);
  var map = L.map(el, { scrollWheelZoom: false }).setView([-6.2, 106.82], 11);
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
  var markers = {}, bounds = [];
  pts.forEach(function (p) {
    var m = L.marker([p.lat, p.lng]).addTo(map);
    var pop = document.createElement('div');
    var b = document.createElement('b'); b.textContent = p.name; pop.appendChild(b);
    pop.appendChild(document.createElement('br'));
    pop.appendChild(document.createTextNode(p.address + (p.rec ? ' · Rekomendasi Aman' : '')));
    m.bindPopup(pop);
    markers[p.id] = m; bounds.push([p.lat, p.lng]);
    if (sync) m.on('click', function () { choose(p.id); });
  });
  if (circle && circle.lat) {
    L.circle([circle.lat, circle.lng], { radius: circle.km * 1000, color: '#006B4A', fillOpacity: 0.06 }).addTo(map);
    bounds.push([circle.lat, circle.lng]);
  }
  if (bounds.length > 1) map.fitBounds(bounds, { padding: [30, 30], maxZoom: 15 });
  else if (bounds.length === 1) map.setView(bounds[0], 15);

  function inputs() { return document.querySelectorAll('[name="meeting_point_id"]'); }
  function choose(id) {
    inputs().forEach(function (i) {
      if (i.tagName === 'SELECT') { i.value = String(id); i.dispatchEvent(new Event('change', { bubbles: true })); }
      else if (i.value === String(id)) { i.checked = true; i.dispatchEvent(new Event('change', { bubbles: true })); }
    });
    focus(id);
  }
  function focus(id) { var m = markers[id]; if (m) { map.setView(m.getLatLng(), Math.max(map.getZoom(), 14)); m.openPopup(); } }
  if (sync) {
    inputs().forEach(function (i) { i.addEventListener('change', function () { var v = i.tagName === 'SELECT' ? i.value : (i.checked ? i.value : null); if (v) focus(parseInt(v, 10)); }); });
  }
  @if(! empty($selected)) focus({{ (int) $selected }}); @endif
  // Peta di dalam elemen tersembunyi (x-show) perlu dihitung ulang ukurannya saat tampil.
  if (window.ResizeObserver) new ResizeObserver(function () { map.invalidateSize(); }).observe(el);
});
</script>
@endpush
