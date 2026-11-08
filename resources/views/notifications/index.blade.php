@extends('layouts.app')
@section('title', 'Notifikasi')
@section('content')
<div class="mx-auto max-w-3xl"><h1 class="mb-4 text-2xl font-extrabold">Notifikasi</h1>
<div class="card divide-y divide-sand">@forelse($items as $n)<a href="{{ $n->data['url'] ?? '#' }}" class="block p-4 hover:bg-cream"><div class="font-bold">{{ $n->data['title'] }}</div><div class="text-sm text-ink/70">{{ $n->data['message'] }}</div><div class="mono mt-1 text-[10px] text-ink/40">{{ $n->created_at->diffForHumans() }}</div></a>
@empty<div class="p-10 text-center text-ink/60">Belum ada notifikasi.</div>@endforelse</div><div class="mt-4">{{ $items->links() }}</div></div>
@endsection
