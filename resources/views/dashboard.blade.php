@extends('layouts.main')
@section('title', 'Visão geral')
@section('content')
<header class="profile-hero mb-7">
    <div class="hero-shade"></div>
    <div class="flex flex-wrap items-end justify-between gap-6">
        <div><p class="eyebrow text-violet-300">Seu acompanhamento · Temporada {{ $season->name }}</p><h1 class="hero-title mt-3">Visão geral</h1><p class="mt-3 text-sm text-slate-400">Suas contas, seu repertório e o caminho para o próximo elo.</p></div>
        <div class="flex flex-wrap gap-2">
            @if($contas->isNotEmpty())<form method="POST" action="{{ route('contas.syncAll') }}">@csrf<input type="hidden" name="season" value="{{ $season->id }}"><button class="btn-secondary" type="submit">Sincronizar todas</button></form>@endif
            <a class="btn-primary" href="{{ route('contas.create') }}">+ Adicionar conta</a>
        </div>
    </div>
</header>
<div class="mb-5"><x-period-filter :days="$days" :season="$season" :seasons="$seasons" /></div>
<x-stats-summary :summary="$summary" />
<section class="mt-10" aria-labelledby="accounts-title">
    <div class="mb-4 flex items-center justify-between"><h2 id="accounts-title" class="text-lg font-semibold">Suas contas</h2><span class="text-xs text-slate-500">{{ $contas->count() }} cadastradas</span></div>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($contas as $conta)
            <article class="panel flex flex-col overflow-hidden">
                <div class="h-0.5 bg-gradient-to-r from-amber-300 via-violet-400/50 to-transparent"></div>
                <div class="flex-1 p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0"><p class="eyebrow">{{ strtoupper($conta->regiao) }} · {{ $conta->nick }}</p><h3 class="mt-2 break-words text-lg font-semibold"><a class="hover:text-amber-300" href="{{ route('contas.show', ['conta' => $conta, 'days' => $days, 'season' => $season->id]) }}">{{ $conta->riot_id }}</a></h3></div>
                        <span class="rounded-lg border border-slate-700 px-2 py-1 text-[10px] text-slate-400">{{ $conta->puuid ? 'Confirmada' : 'Pendente' }}</span>
                    </div>
                    <p class="mt-6 font-display text-2xl font-bold {{ $conta->elo === 'UNRANKED' ? 'text-slate-400' : 'text-amber-300' }}">{{ $conta->elo ?? 'Não sincronizada' }}</p>
                    <p class="mt-1 text-sm text-slate-400">{{ $conta->pdl ?? 0 }} LP <span class="px-2 text-slate-700">/</span> {{ $conta->wins ?? 0 }}V · {{ $conta->losses ?? 0 }}D <span class="text-xs text-slate-500">(ranking atual)</span></p>
                    <p class="mt-3 text-xs text-slate-500">Ranking consultado: {{ $conta->rank_synced_at?->timezone('America/Sao_Paulo')->format('d/m H:i') ?? 'ainda não consultado' }}</p>
                    <x-rank-progress :progress="$rankProgress->get($conta->id)" />
                    @if($conta->latestSync)<p class="mt-3 text-xs {{ $conta->latestSync->status === 'failed' ? 'text-rose-300' : 'text-slate-400' }}">{{ $conta->latestSync->message }}</p>@endif
                    <div class="mt-5 border-t border-violet-400/15 pt-4">
                        <p class="eyebrow">Mais jogados no período</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @forelse(($topByAccount->get($conta->id) ?? collect())->take(3) as $champion)
                                <span class="inline-flex items-center gap-1.5 rounded-md border border-violet-400/10 bg-violet-400/5 px-2 py-1 text-xs text-slate-300"><x-game-icon :id="$champion['champion_id']" :name="$champion['champion_name']" size="h-5 w-5" />{{ $champion['champion_name'] }} <span class="text-slate-500">{{ $champion['games'] }}</span></span>
                            @empty<span class="text-xs text-slate-500">Sem partidas confirmadas</span>@endforelse
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between border-t border-violet-400/15 px-5 py-3">
                    <a class="text-sm font-semibold text-amber-300 hover:text-amber-200" href="{{ route('contas.show', ['conta' => $conta, 'days' => $days, 'season' => $season->id]) }}">Ver detalhes →</a>
                    <form method="POST" action="{{ route('contas.sync', $conta) }}">@csrf<input type="hidden" name="season" value="{{ $season->id }}"><button type="submit" class="text-xs text-slate-400 hover:text-slate-100" @disabled($conta->latestSync?->isActive())>{{ $conta->latestSync?->isActive() ? 'Em andamento' : 'Sincronizar' }}</button></form>
                </div>
            </article>
        @empty
            <div class="panel col-span-full p-12 text-center"><h3 class="text-xl font-semibold">Seu painel começa com uma conta</h3><p class="mx-auto mt-3 max-w-md text-sm leading-relaxed text-slate-400">Adicione um Riot ID e sincronize para acompanhar seu ranking e suas partidas. Nenhuma senha Riot é necessária.</p><a href="{{ route('contas.create') }}" class="btn-primary mt-6">Adicionar minha primeira conta</a></div>
        @endforelse
    </div>
</section>
<section class="panel mt-10 overflow-hidden" aria-labelledby="champions-title">
    <div class="border-b border-violet-400/15 p-5"><h2 id="champions-title" class="text-lg font-semibold">Desempenho por campeão</h2><p class="mt-1 text-xs text-slate-500">Todas as suas contas · Uma participação por conta em cada partida · Remakes curtos excluídos</p></div>
    <x-champion-table :champions="$champions" />
</section>
@endsection
