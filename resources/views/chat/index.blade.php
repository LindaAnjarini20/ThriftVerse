@extends('layouts.app')
@section('title', 'Chat')
@section('content')
<div class="mx-auto max-w-3xl">
  <h1 class="mb-4 text-2xl font-extrabold">Chat</h1>
  <div class="space-y-2">
  @forelse($convs as $c)
    @php $o = $c->other($me); $msgs = $last[$c->id] ?? collect(); $lm = $msgs->last(); $unread = $msgs->where('sender_id', '!=', $me->id)->whereNull('read_at')->count(); @endphp
    <a href="{{ route('chat.show', $c) }}" class="card flex items-center gap-4 p-4 hover:ring-2 hover:ring-brand/30">
      <div class="h-14 w-14 shrink-0 overflow-hidden rounded-lg bg-sand">@include('partials.cover', ['listing' => $c->listing])</div>
      <div class="min-w-0 flex-1"><div class="flex items-center gap-2"><b>{{ $o->name }}</b><span class="mono text-[10px] text-ink/50">{{ $c->buyer_id === $me->id ? 'Penjual' : 'Pembeli' }}</span></div>
        <div class="truncate text-xs text-ink/60">{{ $c->listing->title }}</div>
        <div class="truncate text-sm {{ $unread ? 'font-bold' : 'text-ink/70' }}">{{ $lm ? ($lm->sender_id === $me->id ? 'Anda: ' : '').$lm->body : 'Belum ada pesan' }}</div></div>
      <div class="text-right text-[11px] text-ink/50">{{ $c->last_message_at?->diffForHumans() }}@if($unread)<div class="mt-1"><span class="rounded-full bg-red-500 px-2 py-0.5 text-[10px] font-bold text-white">{{ $unread }}</span></div>@endif</div>
    </a>
  @empty
    <div class="card p-10 text-center text-ink/60">Belum ada percakapan. Buka sebuah listing dan tekan "Chat Penjual".</div>
  @endforelse
  </div>
</div>
@endsection
