<section class="panel overflow-hidden"><div class="section-heading"><h2 class="section-title">Resultados por dia · Todas as contas</h2></div><div class="p-5"><x-daily-chart :points="$dailyChart" /></div></section>
<details class="panel mt-5 p-5"><summary class="cursor-pointer text-xs text-slate-400">Consultar resultados diários em tabela</summary><div class="mt-4 overflow-x-auto"><table class="w-full text-left text-xs"><caption class="sr-only">Resultados diários de todas as contas</caption><thead><tr><th class="py-3">Data</th><th>Participações</th><th>Vitórias</th><th>Derrotas</th></tr></thead><tbody>@foreach(array_reverse($insight['days'], true) as $date=>$day)<tr class="border-t border-cyan-400/10"><th class="py-3 font-normal">{{ $date }}</th><td>{{ $day['games'] }}</td><td>{{ $day['wins'] }}</td><td>{{ $day['games'] - $day['wins'] }}</td></tr>@endforeach</tbody></table></div></details>
<h2 class="mb-4 mt-8 section-title">Evolução de ranking por conta</h2>
<p class="mb-4 text-xs text-slate-500">Observações Solo/Duo no recorte. Cada conta tem sua própria classificação; os elos e LP são apresentados separadamente.</p>
<div class="grid gap-5 xl:grid-cols-2">
    @forelse($rankHistories as $history)
    <section class="panel p-5"><h3 class="mb-4 text-sm font-semibold text-cyan-200">{{ $history['account']->riot_id }}</h3><x-trend-chart :points="$history['points']" /></section>
    @empty
    <p class="text-sm text-slate-500">Cadastre uma conta para acompanhar a evolução.</p>
    @endforelse
</div>
