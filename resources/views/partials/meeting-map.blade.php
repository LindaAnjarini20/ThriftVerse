{{-- Peta Leaflet + OpenStreetMap untuk titik temu.
     $points   : koleksi MeetingPoint (Safe Meeting Point)
     $selected : id titik yang terpilih (opsional)
     $sync     : true = sinkron dengan input name="meeting_point_id" dan mengaktifkan mode pilih titik sendiri
     $pick     : (opsional) paksa aktif/nonaktif mode pilih titik sendiri; default = nilai $sync
     $circle   : (opsional) ['lat'=>, 'lng'=>, 'km'=>] radius COD
     $customInit: (opsional) ['lat'=>, 'lng'=>, 'name'=>] titik kustom yang sudah tersimpan
     $height   : tinggi peta (px) --}}
@php
  $mapId = $mapId ?? 'map-'.substr(md5(uniqid('', true)), 0, 6);
  $pickMode = (bool) ($pick ?? ($sync ?? false));
  $mapPoints = collect($points)->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'address' => $p->address, 'lat' => (float) $p->latitude, 'lng' => (float) $p->longitude, 'rec' => (bool) $p->recommended])->values();
@endphp
@once
  @push('head')<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">@endpush
  @push('scripts')<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>@endpush
@endonce
@if($pickMode)
<div class="mb-2 rounded-xl border-2 border-dashed border-brand/40 bg-brand-light/20 p-3 text-sm">
  <div class="flex flex-wrap items-center gap-2">
    <button type="button" id="{{ $mapId }}-gps" class="btn btn-brand !py-2">📍 Gunakan lokasi saya</button>
    <span class="text-xs text-ink/70">atau klik peta / geser penanda 📍 untuk menentukan tempat janjian sendiri.</span>
  </div>
  <p id="{{ $mapId }}-msg" class="mt-2 text-xs font-semibold" role="status" aria-live="polite"></p>
  <div id="{{ $mapId }}-fields" class="mt-2 hidden space-y-2">
    <input type="hidden" name="custom_lat"><input type="hidden" name="custom_lng">
    <div class="grid gap-2 sm:grid-cols-2">
      <input name="custom_name" maxlength="100" placeholder="Nama tempat (mis. Depan Indomaret Jl. Melati)" class="field">
      <input name="custom_address" maxlength="200" placeholder="Alamat / patokan" class="field">
    </div>
    <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
      <span class="text-amber-800">Titik buatan sendiri bukan Safe Meeting Point terverifikasi. Pilih tempat yang ramai dan publik.</span>
      <button type="button" id="{{ $mapId }}-clear" class="font-bold text-red-600">Batalkan titik ini</button>
    </div>
  </div>
</div>
@endif
<div id="{{ $mapId }}" class="z-0 w-full overflow-hidden rounded-xl bg-sand" style="height: {{ $height ?? 300 }}px" role="application" aria-label="Peta titik temu"></div>
<p class="mt-1 text-[11px] text-ink/50">Peta © kontributor OpenStreetMap.@if($sync ?? false) Klik penanda biru untuk memilih Safe Meeting Point.@endif</p>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof L === 'undefined') return;
  var mapId = @json($mapId), el = document.getElementById(mapId);
  if (!el) return;
  L.Icon.Default.imagePath = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/';
  var pts = @json($mapPoints), sync = @json((bool) ($sync ?? false)), circle = @json($circle ?? null),
      pick = @json($pickMode), init = @json($customInit ?? null);
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
  var ring = null;
  if (circle && circle.lat) {
    ring = L.circle([circle.lat, circle.lng], { radius: circle.km * 1000, color: '#006B4A', fillOpacity: 0.06 }).addTo(map);
    bounds.push([circle.lat, circle.lng]);
  }
  if (bounds.length > 1) map.fitBounds(bounds, { padding: [30, 30], maxZoom: 15 });
  else if (bounds.length === 1) map.setView(bounds[0], 15);

  function presetInputs() { return document.querySelectorAll('[name="meeting_point_id"]'); }
  function choose(id) {
    clearCustom();
    presetInputs().forEach(function (i) {
      if (i.tagName === 'SELECT') { i.value = String(id); i.dispatchEvent(new Event('change', { bubbles: true })); }
      else if (i.value === String(id)) { i.checked = true; i.dispatchEvent(new Event('change', { bubbles: true })); }
    });
    focus(id);
  }
  function focus(id) { var m = markers[id]; if (m) { map.setView(m.getLatLng(), Math.max(map.getZoom(), 14)); m.openPopup(); } }
  if (sync) {
    presetInputs().forEach(function (i) { i.addEventListener('change', function () {
      var v = i.tagName === 'SELECT' ? i.value : (i.checked ? i.value : null);
      if (v) { clearCustom(); focus(parseInt(v, 10)); }
    }); });
  }

  // ---- Titik buatan sendiri (lokasi saya / klik peta) ----
  var box = pick ? document.getElementById(mapId + '-fields') : null, cMarker = null, rev = 0;
  var msgEl = pick ? document.getElementById(mapId + '-msg') : null;
  function q(n) { return box ? box.querySelector('[name="' + n + '"]') : null; }
  function msg(t, bad) { if (!msgEl) return; msgEl.textContent = t || ''; msgEl.className = 'mt-2 text-xs font-semibold ' + (bad ? 'text-red-700' : 'text-brand'); }
  function clearCustom() {
    if (!pick) return;
    if (cMarker) { map.removeLayer(cMarker); cMarker = null; }
    q('custom_lat').value = ''; q('custom_lng').value = ''; box.classList.add('hidden'); msg('');
  }
  function deselectPresets() {
    presetInputs().forEach(function (i) { if (i.tagName === 'SELECT') i.value = ''; else i.checked = false; });
  }
  function reverse(lat, lng) {
    var id = ++rev, name = q('custom_name'), addr = q('custom_address');
    fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&accept-language=id&zoom=18&lat=' + lat + '&lon=' + lng)
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (id !== rev || !j || !j.display_name) return;
        if (!addr.value || addr.dataset.auto === '1') { addr.value = String(j.display_name).slice(0, 200); addr.dataset.auto = '1'; }
        if (!name.value || name.dataset.auto === '1') {
          var nm = j.name || (j.address && (j.address.road || j.address.suburb)) || 'Titik temu';
          name.value = String(nm).slice(0, 100); name.dataset.auto = '1';
        }
      }).catch(function () {});
  }
  function setCustom(lat, lng, opts) {
    opts = opts || {};
    if (!cMarker) {
      cMarker = L.marker([lat, lng], { draggable: true, icon: L.divIcon({ html: '📍', className: '', iconSize: [30, 30], iconAnchor: [15, 28] }) }).addTo(map);
      cMarker.on('dragend', function () { var p = cMarker.getLatLng(); setCustom(p.lat, p.lng); });
    } else { cMarker.setLatLng([lat, lng]); }
    q('custom_lat').value = lat.toFixed(7); q('custom_lng').value = lng.toFixed(7);
    box.classList.remove('hidden');
    deselectPresets();
    if (opts.keepText !== true) reverse(lat, lng);
    var text = opts.note || 'Titik dipilih. Geser penanda 📍 untuk menyesuaikan.', bad = false;
    if (circle && circle.lat) {
      var km = map.distance(L.latLng(circle.lat, circle.lng), L.latLng(lat, lng)) / 1000;
      if (km > circle.km) { text = 'Titik ini ' + km.toFixed(1) + ' km dari titik COD penjual (batas ' + circle.km + ' km). Pilih yang lebih dekat.'; bad = true; }
      else { text += ' (' + km.toFixed(1) + ' km dari titik COD penjual)'; }
    }
    msg(text, bad);
    map.setView([lat, lng], Math.max(map.getZoom(), 16));
  }
  if (pick) {
    ['custom_name', 'custom_address'].forEach(function (n) { q(n).addEventListener('input', function () { this.dataset.auto = '0'; }); });
    map.on('click', function (e) { setCustom(e.latlng.lat, e.latlng.lng); });
    document.getElementById(mapId + '-clear').addEventListener('click', clearCustom);
    document.getElementById(mapId + '-gps').addEventListener('click', function () {
      if (!navigator.geolocation) { msg('Browser ini tidak mendukung deteksi lokasi. Klik peta untuk memilih titik.', true); return; }
      msg('Mencari lokasi Anda…');
      navigator.geolocation.getCurrentPosition(function (pos) {
        setCustom(pos.coords.latitude, pos.coords.longitude, { note: 'Lokasi ditemukan (akurasi ±' + Math.round(pos.coords.accuracy) + ' m). Geser penanda 📍 untuk menyesuaikan.' });
      }, function (err) {
        msg(err.code === 1 ? 'Izin lokasi ditolak. Izinkan lokasi di pengaturan browser, atau klik peta untuk memilih titik.' : 'Lokasi belum bisa ditentukan. Klik peta untuk memilih titik.', true);
      }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 });
    });
    if (init && init.lat) {
      setCustom(parseFloat(init.lat), parseFloat(init.lng), { keepText: true, note: 'Titik temu buatan sendiri tersimpan di listing ini.' });
      if (init.name) q('custom_name').value = init.name;
    }
  }

  @if(! empty($selected)) focus({{ (int) $selected }}); @endif
  // Peta di dalam elemen tersembunyi (x-show / <details>) perlu dihitung ulang ukurannya saat tampil.
  if (window.ResizeObserver) new ResizeObserver(function () { map.invalidateSize(); }).observe(el);
});
</script>
@endpush
