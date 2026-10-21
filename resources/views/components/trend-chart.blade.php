@props(['points' => []])
@php
    $values = collect($points)->pluck('value');
    $minimum = $values->min() ?? 0;
    $range = max(100, ($values->max() ?? 0) - $minimum);
    $coordinates = [];
    foreach ($points as $index => $point) {
        $coordinates[] = ['x' => count($points) > 1 ? 30 + $index / (count($points)-1) * 660 : 360,
            'y' => 170 - ($point['value'] - $minimum) / $range * 140] + $point;
    }
@endphp
@if(count($points))
<svg viewBox="0 0 720 210" class="w-full overflow-visible" role="img" aria-label="Evolução da classificação nas últimas {{ count($points) }} observações">
    <title>Classificação observada ao sincronizar</title>
    @foreach([30,100,170] as $y)<line x1="30" y1="{{ $y }}" x2="690" y2="{{ $y }}" stroke="#29414c" stroke-dasharray="4 6"/>@endforeach
    @if(count($points) > 1)<polyline points="{{ collect($coordinates)->map(fn($p) => $p['x'].','.$p['y'])->implode(' ') }}" fill="none" stroke="#49b7bd" stroke-width="3" stroke-linejoin="round"/>@endif
    @foreach($coordinates as $point)<circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="5" fill="#c8aa6e" stroke="#091722" stroke-width="3" tabindex="0"><title>{{ $point['label'] }} · {{ $point['title'] }}</title></circle>@endforeach
    <text x="30" y="202" fill="#91a5af" font-size="11">{{ $points[0]['label'] }}</text>
    @if(count($points) > 1)<text x="690" y="202" text-anchor="end" fill="#91a5af" font-size="11">{{ $points[count($points)-1]['label'] }}</text>@endif
</svg>
<p class="mt-2 text-xs text-slate-400">{{ $points[count($points)-1]['title'] }} <span class="text-slate-600">· última observação</span></p>
@else
<div class="chart-empty"><p class="text-sm text-slate-400">Sincronize para registrar a primeira observação de ranking.</p></div>
@endif
