@extends('layouts.app')
@section('title', 'Chat dengan '.$other->name)
@section('content')
<div class="mx-auto max-w-2xl">
  <a href="{{ route('chat.index') }}" class="text-sm font-bold">← Semua chat</a>
  <div class="card mt-3 overflow-hidden">
    <div class="flex items-center gap-3 border-b border-sand p-4">
      <div class="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-sand">@include('partials.cover', ['listing' => $c->listing])</div>
      <div class="min-w-0 flex-1"><div class="font-extrabold">{{ $other->name }}</div><a href="{{ route('listings.show', $c->listing) }}" class="truncate text-xs text-indigoX">{{ $c->listing->title }} · @rp($c->listing->current_price)</a></div>
    </div>
    <div id="msgs" class="h-[55vh] space-y-2 overflow-y-auto bg-cream p-4">
      @foreach($c->messages as $m)
        <div class="flex {{ $m->sender_id === $me->id ? 'justify-end' : 'justify-start' }}" data-id="{{ $m->id }}">
          <div class="max-w-[80%] rounded-2xl px-3 py-2 text-sm {{ $m->sender_id === $me->id ? 'bg-brand text-white' : 'bg-white shadow' }}"><div class="whitespace-pre-line">{{ $m->body }}</div><div class="mono mt-0.5 text-[9px] opacity-60">{{ $m->created_at->format('H:i') }}</div></div>
        </div>
      @endforeach
    </div>
    <form id="chat-form" method="POST" action="{{ route('chat.store', $c) }}" class="flex gap-2 border-t border-sand p-3">@csrf
      <input id="chat-body" name="body" required maxlength="1000" autocomplete="off" placeholder="Tulis pesan…" class="field">
      <button class="btn btn-brand">Kirim</button>
    </form>
    <p class="px-4 pb-3 text-[11px] text-ink/50">Demi keamanan, nomor telepon dan tautan chat luar disamarkan. Lakukan transaksi lewat ThriftVerse agar dana terlindungi rekber.</p>
  </div>
</div>
@push('scripts')
<script>
(function () {
  var box = document.getElementById('msgs'), form = document.getElementById('chat-form'), input = document.getElementById('chat-body');
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var pollUrl = @json(route('chat.poll', $c)), last = {{ (int) ($c->messages->last()->id ?? 0) }};
  box.scrollTop = box.scrollHeight;
  function add(m) {
    if (box.querySelector('[data-id="' + m.id + '"]')) return;
    var row = document.createElement('div'); row.className = 'flex ' + (m.mine ? 'justify-end' : 'justify-start'); row.dataset.id = m.id;
    var b = document.createElement('div'); b.className = 'max-w-[80%] rounded-2xl px-3 py-2 text-sm ' + (m.mine ? 'bg-brand text-white' : 'bg-white shadow');
    var t = document.createElement('div'); t.className = 'whitespace-pre-line'; t.textContent = m.body;
    var h = document.createElement('div'); h.className = 'mono mt-0.5 text-[9px] opacity-60'; h.textContent = m.time;
    b.appendChild(t); b.appendChild(h); row.appendChild(b); box.appendChild(row); box.scrollTop = box.scrollHeight;
    if (m.id > last) last = m.id;
  }
  function poll() {
    fetch(pollUrl + '?after=' + last, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : []; }).then(function (list) { list.forEach(add); }).catch(function () {});
  }
  setInterval(poll, 5000);
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var body = input.value.trim(); if (!body) return;
    var fd = new FormData(); fd.append('body', body); fd.append('_token', csrf);
    fetch(form.action, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) throw new Error('gagal'); return r.json(); })
      .then(function (m) { add(m); input.value = ''; })
      .catch(function () { form.submit(); });
  });
})();
</script>
@endpush
@endsection
