@extends('layouts.app')
@section('title', 'Jadwal COD')
@section('content')
<div class="mb-5"><div class="mono text-[11px] font-bold uppercase text-brand">Safe meetup hub</div><h1 class="text-2xl font-extrabold">Jadwal COD & Ketemuan</h1></div>
<div class="space-y-3">@forelse($schedules as $s)@php $t = $s->transaction; @endphp
<a href="{{ route('cod.show', $s) }}" class="card flex flex-wrap items-center gap-4 p-4 hover:ring-2 hover:ring-brand/30"><div class="h-14 w-14 overflow-hidden rounded-lg bg-sand">@include('partials.cover', ['listing' => $t->listing])</div>
  <div class="min-w-0 flex-1"><div class="font-bold">{{ $t->listing->title }}</div><div class="text-xs text-ink/60">📍 {{ $s->point?->name ?? '—' }}</div><div class="text-xs text-ink/60">🗓 {{ $s->scheduled_at->translatedFormat('l, d M Y H:i') }} WIB</div></div>
  <span class="mono rounded px-2 py-1 text-[10px] font-bold uppercase {{ $s->status==='confirmed' ? 'bg-brand-light text-brand-dark' : ($s->status==='completed' ? 'bg-sand' : ($s->status==='cancelled' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800')) }}">{{ $s->status }}</span></a>
@empty<div class="card p-10 text-center text-ink/60">Belum ada jadwal COD. Pilih opsi COD saat checkout atau barter.</div>@endforelse</div>
@endsection
