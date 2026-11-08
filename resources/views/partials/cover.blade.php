@php
  $url = $listing->coverUrl();
  $tones = [['#D9C9B8','#8B6F52'],['#C8D6D0','#3E6B5A'],['#D5D0E6','#5B4FA3'],['#E8CFC3','#A35A3C'],['#CBD5E1','#475569']];
  [$bg,$fg] = $tones[$listing->id % 5];
@endphp
@if($url)<img src="{{ $url }}" alt="{{ $listing->title }}" class="{{ $class ?? 'h-full w-full object-cover' }}" loading="lazy">
@else<div class="{{ $class ?? 'h-full w-full' }} flex flex-col items-center justify-center text-center" style="background:linear-gradient(135deg,{{ $bg }},#fff8f4);color:{{ $fg }}">
  <div class="text-3xl font-extrabold leading-none">{{ strtoupper(substr($listing->brand ?: $listing->title, 0, 2)) }}</div><div class="mono mt-1 px-2 text-[10px] uppercase">{{ $listing->category }}</div></div>@endif
