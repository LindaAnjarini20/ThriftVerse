@extends('layouts.admin')
@section('title', 'Pengguna')
@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3"><h1 class="text-2xl font-extrabold">Manajemen Pengguna</h1>
  <form class="flex gap-2"><input name="q" value="{{ request('q') }}" placeholder="Cari nama / email" class="field"><button class="btn btn-brand">Cari</button></form></div>
<div class="card overflow-x-auto"><table class="w-full text-sm"><thead class="bg-sand text-left"><tr class="mono text-[10px] uppercase"><th class="p-3">Pengguna</th><th>Area</th><th>Peran</th><th>Skor</th><th>Status</th><th></th></tr></thead><tbody class="divide-y divide-sand">
@foreach($users as $u)<tr><td class="p-3"><b>{{ $u->name }}</b> <span class="text-ink/50">{{ $u->handle() }}</span><br><span class="text-xs text-ink/60">{{ $u->email }}</span></td><td>{{ $u->area ?? '—' }}</td>
  <td>{{ $u->isAdmin() ? 'Admin' : ($u->seller_mode ? 'Pembeli + Penjual' : 'Pembeli') }}</td><td>{{ $u->trust_score }}</td>
  <td><span class="mono rounded px-2 py-1 text-[10px] font-bold uppercase {{ $u->is_active ? 'bg-brand-light text-brand-dark' : 'bg-red-100 text-red-800' }}">{{ $u->is_active ? 'Aktif' : 'Ditangguhkan' }}</span></td>
  <td class="pr-3 text-right">@unless($u->isAdmin())<form method="POST" action="{{ route('admin.users.toggle', $u) }}">@csrf<button class="btn {{ $u->is_active ? 'btn-danger' : 'btn-brand' }}">{{ $u->is_active ? 'Suspend' : 'Aktifkan' }}</button></form>@endunless</td></tr>@endforeach</tbody></table></div>
<div class="mt-4">{{ $users->links() }}</div>
@endsection
