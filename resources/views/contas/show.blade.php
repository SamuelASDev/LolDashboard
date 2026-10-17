@extends('layouts.main')
@section('title', 'Detalhes da conta')
@section('content')
@php
    $favorite = $champions->first();
    $splash = $favorite ? \App\Support\RiotAssets::splash($favorite['champion_id'], $favorite['champion_name']) : null;
    $queueLabel = 'Solo/Duo';
@endphp
<div class="mb-5 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500">
    <a href="{{ route('dashboard', ['days' => $days, 'season' => $season->id]) }}" class="hover:text-amber-200">← Suas contas</a>
    <span>Temporada {{ $season->name }} <span class="mx-2 text-violet-400/40">/</span> {{ strtoupper($conta->regiao) }}</span>
</div>
<header class="profile-hero mb-5">
    @if($splash)<img src="{{ $splash }}" alt="" aria-hidden="true" class="hero-art">@endif
    <div class="hero-shade"></div>
    <div class="grid items-center gap-7 xl:grid-cols-[1fr_340px]">
        <div class="min-w-0">
            <div class="mb-4 flex flex-wrap gap-2"><span class="meta-pill">{{ $conta->nick }}</span><span class="meta-pill">{{ $conta->puuid ? 'Identidade confirmada' : 'Aguardando sincronização' }}</span></div>
            <h1 class="hero-title break-words">{{ $conta->riot_id }}</h1>
            <p class="mt-3 max-w-xl text-sm leading-relaxed text-slate-400">Seu histórico. Suas escolhas. Uma visão mais clara da próxima partida.</p>
            <div class="mt-6 flex flex-wrap gap-2">
                <form method="POST" action="{{ route('contas.sync', $conta) }}">
                    @csrf<input type="hidden" name="season" value="{{ $season->id }}"><input type="hidden" name="queue" value="{{ request('queue',420) }}">
                    <button class="btn-primary" type="submit" @disabled($conta->latestSync?->isActive())>Sincronizar conta <span aria-hidden="true">↻</span></button>
                </form>
                <a class="btn-secondary" href="{{ route('contas.edit', $conta) }}">Editar</a>
            </div>
            <p class="mt-4 text-[10px] text-slate-500">Última consulta de ranking: {{ $conta->rank_synced_at?->timezone('America/Sao_Paulo')->format('d/m/Y · H:i') ?? 'ainda não realizada' }}</p>
        </div>
        <aside class="rounded-lg border border-violet-300/15 bg-[#130d1e]/85 p-5" aria-label="Classificação Solo/Duo">
            <p class="eyebrow text-violet-300">Ranqueada Solo/Duo · Ranking atual</p>
            <div class="mt-3 flex items-end justify-between gap-3"><p class="font-display text-2xl font-bold text-amber-100">{{ $conta->elo ?? 'Não sincronizada' }}</p><p class="text-xl font-semibold tabular-nums">{{ $conta->pdl ?? 0 }} <span class="text-xs text-slate-500">LP</span></p></div>
            <p class="mt-2 text-xs text-slate-400">{{ $conta->wins ?? 0 }}V <span class="text-slate-600">/</span> {{ $conta->losses ?? 0 }}D na liga</p>
            <x-rank-progress :progress="$progress" />
            <x-decay-status :decay="$decay" :account="$conta" />
        </aside>
    </div>
</header>
@include('contas.partials.sync-status')
@include('contas.partials.decay-reference')
@include('contas.partials.tabs')
@include('contas.partials.filters')
@if($legacyCount)<div class="notice mb-5">{{ $legacyCount }} registros antigos aguardam confirmação de identidade e estão fora dos indicadores.</div>@endif
<x-stats-summary :summary="$summary" />
<div class="mb-6 mt-3 flex flex-wrap items-center justify-between gap-2 text-[10px] text-slate-500">
    <span><span class="mr-1 inline-block h-1.5 w-1.5 rounded-full bg-violet-400"></span>{{ $queueLabel }} · {{ $insight['enriched'] }} de {{ $insight['total'] }} partidas com detalhes · Remakes curtos excluídos</span>
    <span>Horários de Brasília</span>
</div>
@include('contas.partials.analytics')
<details class="panel mt-8 p-5">
    <summary class="cursor-pointer text-sm font-semibold text-slate-400">Importação da temporada {{ $season->name }}</summary>
    <p class="my-4 max-w-3xl text-xs leading-relaxed text-slate-500">A atualização comum verifica até 60 partidas recentes. Para ampliar o histórico no período da temporada selecionada, importe a temporada. Seus dados confirmados são preservados ao cancelar.</p>
    <form method="POST" action="{{ route('contas.sync', $conta) }}" class="flex flex-wrap items-end gap-3">
        @csrf<input type="hidden" name="season" value="{{ $season->id }}"><input type="hidden" name="mode" value="full">
        <label class="text-xs text-slate-400">Fila<select class="field mt-1" name="queue"><option value="420">Solo/Duo</option></select></label>
        <button type="submit" class="btn-secondary" @disabled($conta->latestSync?->isActive())>Importar temporada {{ $season->name }}</button>
    </form>
</details>
<details class="mt-5 rounded-lg border border-violet-400/10 p-5">
    <summary class="cursor-pointer text-xs text-slate-500">Gerenciar cadastro e informações dos dados</summary>
    <p class="mt-4 text-xs text-slate-500">Última atividade Solo/Duo: {{ $conta->last_game_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') ?? 'não disponível' }}. Ranking e histórico podem abranger períodos diferentes. O acompanhamento de decay Mestre+ é uma previsão local; consulte o cliente para o saldo oficial.</p>
    <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-rose-900/30 pt-4">
        <p class="text-xs text-slate-500">Excluir remove definitivamente este cadastro e suas partidas.</p>
        <form method="POST" action="{{ route('contas.destroy', $conta) }}" onsubmit="return confirm('Excluir esta conta e todo o histórico? Esta ação é permanente.')">
            @csrf @method('DELETE')<button class="btn-danger" type="submit" @disabled($conta->latestSync?->isActive())>Excluir conta</button>
        </form>
    </div>
</details>
@endsection
