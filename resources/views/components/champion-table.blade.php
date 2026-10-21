@props(['champions'])
<div class="overflow-x-auto">
    <table class="compact-table w-full min-w-[780px] text-left text-xs">
        <caption class="sr-only">Desempenho por campeão no período selecionado</caption>
        <thead class="border-b border-cyan-400/15 text-xs text-slate-500"><tr>
            <th scope="col" class="px-4 py-3.5 font-medium">Campeão</th><th scope="col" class="px-4 py-3.5 font-medium">Jogos</th>
            <th scope="col" class="px-4 py-3.5 font-medium">Vitórias</th><th scope="col" class="px-4 py-3.5 font-medium">KDA</th>
            <th scope="col" class="px-4 py-3.5 font-medium">Média A / M / A</th><th class="px-4 py-3.5">CS/min</th><th class="px-4 py-3.5">Ouro/min</th><th class="px-4 py-3.5">Dano/min</th><th class="px-4 py-3.5">PA</th><th class="px-4 py-3.5">Visão</th>
        </tr></thead>
        <tbody class="divide-y divide-cyan-400/10">
            @forelse($champions as $champion)
                <tr class="hover:bg-cyan-400/5">
                    <th scope="row" aria-label="{{ $champion['champion_name'] }}" class="px-4 py-3.5 font-semibold"><span class="flex items-center gap-2"><x-game-icon :id="$champion['champion_id'] ?? null" :name="$champion['champion_name']" size="h-7 w-7" />{{ $champion['champion_name'] }}</span></th>
                    <td class="px-4 py-3.5 tabular-nums text-slate-400">{{ $champion['games'] }}</td>
                    <td class="px-4 py-3.5 tabular-nums {{ $champion['winrate'] >= 50 ? 'text-amber-200' : 'text-cyan-300' }}">{{ $champion['winrate'] }}%</td>
                    <td class="px-4 py-3.5 tabular-nums">{{ $champion['kda'] }}</td>
                    <td class="whitespace-nowrap px-4 py-3.5 tabular-nums text-slate-400">{{ $champion['avg_kills'] }} / {{ $champion['avg_deaths'] }} / {{ $champion['avg_assists'] }}</td>
                @foreach(['cs_per_minute','gold_per_minute','damage_per_minute','participation','vision'] as $metric)<td class="px-4 py-3.5 tabular-nums text-slate-400">{{ \App\Support\GameLabels::number($champion[$metric] ?? null,1) }}</td>@endforeach
                </tr>
            @empty
                <tr><td colspan="10" class="px-5 py-12 text-center text-slate-500">Nenhuma partida confirmada neste período. Sincronize uma conta ou amplie o período.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
