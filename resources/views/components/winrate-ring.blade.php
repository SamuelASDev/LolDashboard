@props(['wins' => 0, 'games' => 0, 'caption' => 'Taxa de vitória'])
@php($rate = $games ? round($wins / $games * 100, 1) : 0)
<div class="flex items-center gap-5">
    <div class="relative h-28 w-28 shrink-0">
        <svg class="h-full w-full -rotate-90" viewBox="0 0 120 120" role="img" aria-label="{{ $games ? $rate.'% de vitórias em '.$games.' partidas' : 'Sem partidas para calcular a taxa' }}">
            <circle cx="60" cy="60" r="50" fill="none" stroke="#29414c" stroke-width="9"/>
            @if($games)<circle cx="60" cy="60" r="50" fill="none" stroke="#49b7bd" stroke-width="9" pathLength="100" stroke-dasharray="{{ $rate }} 100" stroke-linecap="round"/>@endif
        </svg>
        <div class="absolute inset-0 grid place-content-center text-center"><strong class="text-2xl text-amber-200">{{ $games ? $rate.'%' : '—' }}</strong><span class="text-[10px] uppercase tracking-widest text-slate-400">Vitórias</span></div>
    </div>
    <div><p class="eyebrow">{{ $caption }}</p><p class="mt-2 text-lg font-semibold">{{ $games }} partidas</p><p class="mt-1 text-sm"><span class="text-emerald-300">{{ $wins }}V</span><span class="mx-2 text-slate-600">/</span><span class="text-rose-300">{{ $games - $wins }}D</span></p></div>
</div>
