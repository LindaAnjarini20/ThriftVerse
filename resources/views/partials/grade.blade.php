@php $c = ['A' => 'bg-brand text-white', 'B' => 'bg-indigoX text-white', 'C' => 'bg-clay text-white'][$grade] ?? 'bg-sand'; @endphp
<span class="mono inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[10px] font-bold uppercase {{ $c }}">Grade {{ $grade }}@isset($verified) @if($verified)· ✓ Verified @endif @endisset</span>
