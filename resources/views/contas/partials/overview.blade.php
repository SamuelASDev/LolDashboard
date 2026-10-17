<div class="grid items-stretch gap-5 lg:grid-cols-[310px_1fr]">
    <section class="panel p-5">
        <p class="eyebrow mb-5 text-violet-300">Balanço do período</p>
        <x-winrate-ring :wins="$summary['wins']" :games="$summary['games']" />
        <div class="mt-6 grid grid-cols-3 gap-2 border-t border-violet-400/15 pt-4 text-center">
            <div><strong class="text-lg text-amber-200">{{ $insight['bestWins'] }}</strong><p class="mt-1 text-[9px] text-slate-500">Melhor série V</p></div>
            <div><strong class="text-lg text-violet-300">{{ $insight['bestLosses'] }}</strong><p class="mt-1 text-[9px] text-slate-500">Maior série D</p></div>
            <div><strong class="text-lg">{{ $insight['streak'] }}{{ $insight['total'] ? ($insight['previous'] ? 'V' : 'D') : '' }}</strong><p class="mt-1 text-[9px] text-slate-500">Série atual</p></div>
        </div>
    </section>
    <section class="panel overflow-hidden">
        <div class="section-heading"><div><h2 class="section-title">Ritmo de jogo</h2><p class="mt-1 text-[11px] text-slate-500">Volume e resultado das suas partidas por dia</p></div><a class="section-link" href="{{ route('contas.show', array_merge(request()->except('page'), ['conta'=>$conta,'tab'=>'progress'])) }}">Ver evolução →</a></div>
        <div class="px-5 pb-5"><x-daily-chart :points="$dailyChart" /></div>
    </section>
</div>
<div class="mt-5">@include('contas.partials.recent-form')</div>
<div class="grid items-start gap-5 lg:grid-cols-2">
    <section class="panel overflow-hidden">
        <div class="section-heading"><h2 class="section-title">Suas posições</h2><span class="text-[10px] text-slate-500">Participação no recorte</span></div>
        <div class="space-y-4 p-5">
            @foreach(\App\Support\GameLabels::ROLES as $role=>$label)
            @php
                $group = $insight['groups']['role'][$label] ?? ['games'=>0,'wins'=>0];
                $share = $insight['total'] ? round($group['games'] / $insight['total'] * 100) : 0;
            @endphp
            @if($role !== 'UNKNOWN' || $group['games'])
            <a href="{{ route('contas.show', array_merge(request()->except('page'), ['conta'=>$conta,'tab'=>'matchups','role'=>$role])) }}" class="group flex items-center gap-3">
                <span class="text-violet-300"><x-role-icon :role="$role" /></span>
                <div class="min-w-0 flex-1"><div class="mb-1.5 flex justify-between gap-2 text-xs"><span class="text-slate-300 group-hover:text-amber-200">{{ $label }}</span><span class="text-slate-500">{{ $group['games'] }} jogos · {{ $share }}%</span></div><div class="h-1.5 overflow-hidden rounded-full bg-violet-400/10"><div class="h-full rounded-full bg-violet-400" style="width:{{ $share }}%"></div></div></div>
            </a>
            @endif
            @endforeach
        </div>
    </section>
    <section class="panel overflow-hidden">
        <div class="section-heading"><h2 class="section-title">Campeões em destaque</h2><a class="section-link" href="{{ route('contas.show', array_merge(request()->except('page'), ['conta'=>$conta,'tab'=>'champions'])) }}">Todos →</a></div>
        <div class="space-y-3 p-5">
        @forelse($champions->take(4) as $champion)
            <a href="{{ route('contas.show', array_merge(request()->except('page'), ['conta'=>$conta,'tab'=>'history','champion'=>$champion['champion_name']])) }}" class="matchup-row">
                <span class="flex min-w-0 items-center gap-3"><x-game-icon :id="$champion['champion_id']" :name="$champion['champion_name']" size="h-10 w-10" /><span><strong class="block text-sm">{{ $champion['champion_name'] }}</strong><small class="text-[10px] text-slate-500">{{ $champion['games'] }} jogos · {{ $champion['kda'] }} KDA</small></span></span>
                <span class="text-sm font-bold {{ $champion['winrate'] >= 50 ? 'text-amber-200' : 'text-violet-300' }}">{{ $champion['winrate'] }}%</span>
            </a>
        @empty<p class="py-8 text-center text-sm text-slate-500">Seu repertório aparece após a importação.</p>@endforelse
        </div>
    </section>
</div>
<div class="mt-5 grid gap-5 xl:grid-cols-3">
@foreach(['Combate'=>['damage_per_minute','kill_participation','damage_taken','healing'],'Economia'=>['cs_per_minute','gold_per_minute','cs','gold'],'Visão'=>['vision_score','wards_placed','wards_killed','control_wards']] as $category=>$metrics)
<section class="panel overflow-hidden">
    <div class="section-heading"><h2 class="section-title">{{ $category }}</h2><span class="text-[10px] text-slate-500">Médias pessoais</span></div>
    <dl class="grid grid-cols-2 gap-x-5 gap-y-6 p-5">
    @foreach($metrics as $metric)<div><dt class="text-[10px] text-slate-500">{{ $metricLabels[$metric] }}</dt><dd class="mt-1 text-xl font-semibold text-amber-50">{{ \App\Support\GameLabels::number($insight['averages'][$metric]['value'] ?? null,1) }}</dd><dd class="mt-0.5 text-[9px] text-slate-600">{{ $insight['averages'][$metric]['sample'] ?? 0 }} partidas com valor</dd></div>@endforeach
    </dl>
</section>
@endforeach
</div>
