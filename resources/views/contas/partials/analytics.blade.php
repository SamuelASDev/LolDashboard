@php($metricLabels = ['cs'=>'Farm','gold'=>'Ouro','damage'=>'Dano a campeões','damage_taken'=>'Dano recebido','vision_score'=>'Pontuação de visão','wards_placed'=>'Sentinelas colocadas','wards_killed'=>'Sentinelas destruídas','control_wards'=>'Controles comprados','kill_participation'=>'Participação nos abates (%)','healing'=>'Cura','kills'=>'Abates','assists'=>'Assistências','penta_kills'=>'Pentakills','cs_per_minute'=>'CS / min','gold_per_minute'=>'Ouro / min','damage_per_minute'=>'Dano / min'])
@if($tab === 'overview')
    @include('contas.partials.overview')
@elseif($tab === 'history')
    @include('contas.partials.recent-form')
    <section aria-label="Histórico de partidas" class="space-y-3 history-compact" x-data="{ compact: true }" :class="{ 'history-compact': compact }">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <span class="text-xs text-slate-500">Resultado, build e confrontos em cada partida</span>
            <button type="button" class="btn-secondary text-xs" @click="compact = !compact" :aria-pressed="compact" x-text="compact ? 'Visão completa' : 'Visão compacta'">Visão completa</button>
        </div>
        @forelse($partidas as $partida)
            @php($dateLabel = $partida->played_at->timezone('America/Sao_Paulo')->format('d/m/Y'))
            @if($loop->first || $previousMatchDate !== $dateLabel)<h2 class="match-day">{{ $dateLabel }}</h2>@endif
            @php($previousMatchDate = $dateLabel)
            <x-match-card :match="$partida" />
        @empty
            <div class="panel p-10 text-center"><p class="text-sm text-slate-400">Nenhuma partida neste recorte.</p><p class="mt-2 text-xs text-slate-500">Amplie o período ou sincronize sua conta.</p></div>
        @endforelse
    </section>
    <div class="mt-5">{{ $partidas->links() }}</div>
@elseif($tab === 'champions')
    <section class="panel overflow-hidden"><div class="section-heading"><div><h2 class="section-title">Seu repertório de campeões</h2><p class="mt-1 text-xs text-slate-500">Resultados pessoais no período e nos filtros selecionados.</p></div><span class="meta-pill">{{ $champions->count() }} campeões</span></div><x-champion-table :champions="$champions" /></section>
@elseif($tab === 'matchups')
    @include('contas.partials.matchups')
@elseif($tab === 'patterns')
    @include('contas.partials.patterns')
@elseif($tab === 'records')
    <div class="mb-5"><h2 class="section-title">Seus melhores momentos</h2><p class="mt-1 text-xs text-slate-500">Máximos por partida dentro do recorte selecionado.</p></div>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse($insight['records'] as $metric=>$record)
        <section class="panel relative overflow-hidden p-5">
            <div class="absolute right-4 top-4 text-3xl text-violet-300/15" aria-hidden="true">✧</div>
            <p class="eyebrow">{{ $metricLabels[$metric] ?? $metric }}</p>
            <p class="mt-4 font-display text-4xl font-bold text-amber-200">{{ \App\Support\GameLabels::number($record['value'], $metric === 'kill_participation' ? 1 : 0) }}</p>
            <div class="mt-5 flex items-center gap-3 border-t border-violet-400/10 pt-4"><x-game-icon :id="$record['match']->champion_id" :name="$record['match']->champion_name" /><p class="text-sm">{{ $record['match']->champion_name }}<span class="mt-1 block text-[10px] text-slate-500">{{ $record['match']->played_at->timezone('America/Sao_Paulo')->format('d/m/Y · H:i') }}</span></p></div>
        </section>
        @empty
        <div class="panel col-span-full p-10 text-center text-sm text-slate-500">Importe suas partidas para descobrir seus recordes.</div>
        @endforelse
    </div>
@elseif($tab === 'progress')
    @include('contas.partials.progress')
@endif
