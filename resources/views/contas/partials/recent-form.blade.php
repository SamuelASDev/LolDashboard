@php
    $recentGames = count($insight['recent']);
    $recentWins = collect($insight['recent'])->where('win', true)->count();
@endphp
<section class="panel mb-5">
    <div class="section-heading"><h2 class="section-title">Partidas confirmadas</h2><span class="text-[11px] text-slate-500">Últimos {{ $recentGames }} jogos do recorte</span></div>
    <div class="flex flex-wrap items-center gap-5 p-5">
        <div class="shrink-0"><strong class="text-xl text-amber-200">{{ $recentGames ? round($recentWins / $recentGames * 100) : '—' }}%</strong><p class="mt-1 text-[10px] text-slate-500"><span class="text-emerald-300">{{ $recentWins }}V</span> / <span class="text-rose-300">{{ $recentGames-$recentWins }}D</span></p></div>
        <div class="flex min-w-0 flex-1 flex-wrap gap-1.5" aria-label="Campeões dos jogos recentes">
            @forelse(array_reverse($insight['recent']) as $recent)
            <a href="{{ route('contas.show', array_merge(request()->except('page'), ['conta'=>$conta, 'tab'=>'history'])) }}#match-{{ $recent->id }}" class="rounded-md border-b-[3px] pb-1 {{ $recent->win ? 'border-amber-300' : 'border-violet-400' }}" title="{{ $recent->champion_name }} · {{ $recent->win ? 'Vitória' : 'Derrota' }} · {{ $recent->played_at->timezone('America/Sao_Paulo')->format('d/m') }}"><x-game-icon :id="$recent->champion_id" :name="$recent->champion_name" size="h-8 w-8" /></a>
            @empty<p class="text-xs text-slate-500">Seus campeões aparecerão aqui após a importação.</p>@endforelse
        </div>
    </div>
</section>
