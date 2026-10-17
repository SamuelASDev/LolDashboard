@props(['summary'])
<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    @foreach(['games' => 'Partidas no recorte', 'winrate' => 'Taxa de vitória', 'kda' => 'KDA acumulado', 'wins' => 'Vitórias'] as $key => $label)
        <div class="stat-tile">
            <p class="eyebrow">{{ $label }}</p>
            <p class="mt-3 metric-value {{ $key === 'wins' ? 'text-emerald-400' : 'text-slate-100' }}">{{ $summary[$key] }}{{ $key === 'winrate' ? '%' : '' }}</p>
            <p class="mt-2 text-xs text-slate-500">{{ $key === 'wins' ? $summary['losses'].' derrotas' : ($key === 'kda' ? '(abates + assistências) / mortes' : 'No período selecionado') }}</p>
        </div>
    @endforeach
</div>
