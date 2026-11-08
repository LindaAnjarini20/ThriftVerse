@extends('layouts.admin')
@section('title', 'Escrow & Sengketa')
@section('content')
<h1 class="mb-1 text-2xl font-extrabold">Pusat Resolusi Sengketa Transaksi</h1><p class="mb-5 text-sm text-ink/60">Komplain, penolakan di tempat, gagal bertemu, dan laporan listing/pengguna.</p>
<div class="space-y-4">
@forelse($reports as $r)@php $t = $r->transaction; @endphp
  <div class="card p-5"><div class="flex flex-wrap items-center justify-between gap-2"><div><span class="mono rounded bg-red-100 px-2 py-0.5 text-[10px] font-bold uppercase text-red-700">{{ $r->type }}</span> <span class="mono text-[11px] text-ink/50">#{{ $r->id }} · {{ $r->created_at->diffForHumans() }}</span></div>
    <span class="mono rounded px-2 py-1 text-[10px] font-bold uppercase {{ $r->status==='open' ? 'bg-amber-100 text-amber-800' : 'bg-brand-light text-brand-dark' }}">{{ $r->status }}{{ $r->resolution ? ' · '.$r->resolution : '' }}</span></div>
    <div class="mt-2 text-sm"><b>{{ $r->reporter->name }}</b> melaporkan <b>{{ $r->target?->name ?? '—' }}</b>@if($t) · {{ $t->transaction_number }} · {{ $t->listing->title }} · escrow {{ $t->escrow_status }} (@rp($t->total))@elseif($r->listing) · listing {{ $r->listing->title }}@endif</div>
    <p class="mt-2 rounded-lg bg-sand p-3 text-sm">"{{ $r->reason }}"</p>
    @if($r->evidence)<div class="mt-2 flex gap-2">@foreach($r->evidence as $e)<a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($e) }}" target="_blank"><img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($e) }}" class="h-20 w-20 rounded-lg object-cover" alt="bukti"></a>@endforeach</div>@endif
    @if($r->status==='open')
    <form method="POST" action="{{ route('admin.resolve', $r) }}" class="mt-3 flex flex-wrap items-center gap-2">@csrf<input name="admin_note" placeholder="Catatan admin" class="field max-w-xs">
      @if($t)<button name="resolution" value="refund" class="btn btn-brand">Lepas Dana ke Pembeli (Refund)</button><button name="resolution" value="release" class="btn btn-indigo">Cairkan ke Penjual</button>@endif
      <button name="resolution" value="dismissed" class="btn btn-soft">Tolak / Tutup</button></form>@elseif($r->admin_note)<p class="mt-2 text-xs text-ink/60">Catatan: {{ $r->admin_note }}</p>@endif
  </div>
@empty<div class="card p-10 text-center text-ink/60">Tidak ada laporan.</div>@endforelse</div>
<div class="mt-4">{{ $reports->links() }}</div>
<h2 class="mt-8 mb-2 text-lg font-extrabold">Dana escrow tertahan</h2>
<div class="card divide-y divide-sand text-sm">@forelse($escrow as $t)<div class="flex justify-between p-3"><span>{{ $t->transaction_number }} · {{ $t->listing->title }} · {{ $t->statusLabel() }}</span><b>@rp($t->total)</b></div>@empty<div class="p-4 text-ink/60">Tidak ada dana tertahan.</div>@endforelse</div>
@endsection
