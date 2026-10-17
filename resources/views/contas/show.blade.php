@extends('layouts.main')
@section('title', 'Detalhes da conta')
@section('content')
@php
    $favorite = $champions->first();
    $splash = $favorite ? \App\Support\RiotAssets::splash($favorite['champion_id'], $favorite['champion_name']) : null;
    $queueLabel = (int) request('queue', 420) === 440 ? 'Flex' : 'Solo/Duo';
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
        </aside>
    </div>
</header>
@include('contas.partials.sync-status')
<nav class="account-tabs mb-5" aria-label="Estatísticas da conta">
    @foreach(['overview'=>'Visão geral','history'=>'Partidas','champions'=>'Campeões','matchups'=>'Confrontos','patterns'=>'Padrões','records'=>'Recordes','progress'=>'Evolução'] as $key=>$label)
        <a href="{{ route('contas.show', array_merge(request()->except('page'), ['conta'=>$conta, 'tab'=>$key, 'season'=>$season->id])) }}" class="account-tab" @if($tab === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
<details class="panel mb-5" x-data="{ expanded: window.innerWidth >= 768 }" :open="expanded" @toggle="expanded = $el.open" open>
<summary class="flex cursor-pointer flex-wrap items-center justify-between gap-2 px-4 py-3 text-xs"><span class="font-semibold text-slate-300">Filtros do recorte <span class="ml-1 text-violet-300" aria-hidden="true">⌄</span></span><span class="text-[10px] text-slate-500">{{ $queueLabel }} · {{ $days ? $days.' dias' : 'Toda a temporada' }}{{ request('role') ? ' · '.(\App\Support\GameLabels::ROLES[request('role')] ?? '') : '' }}</span></summary>
<form method="GET" class="filter-bar rounded-t-none border-x-0 border-b-0" aria-label="Filtrar estatísticas">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div class="grid items-end gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <x-season-select :season="$season" :seasons="$seasons" />
        <label class="text-xs text-slate-400">Período
            <select class="field mt-1.5" name="days">@foreach([7=>'7 dias',30=>'30 dias',90=>'90 dias',365=>'1 ano',0=>'Toda a temporada'] as $value=>$label)<option value="{{ $value }}" @selected($days === $value)>{{ $label }}</option>@endforeach</select>
        </label>
        <label class="text-xs text-slate-400">Fila
            <select class="field mt-1.5" name="queue"><option value="420" @selected((int) request('queue',420) === 420)>Solo/Duo</option><option value="440" @selected((int) request('queue') === 440)>Flex</option></select>
        </label>
        <label class="text-xs text-slate-400">Campeão
            <select class="field mt-1.5" name="champion"><option value="">Todos os campeões</option>@foreach($championOptions as $name)<option @selected(request('champion') === $name)>{{ $name }}</option>@endforeach</select>
        </label>
        <label class="text-xs text-slate-400">Posição
            <select class="field mt-1.5" name="role"><option value="">Todas as posições</option>@foreach(\App\Support\GameLabels::ROLES as $value=>$label)<option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>@endforeach</select>
        </label>
        <button class="btn-primary" type="submit">Aplicar filtros</button>
    </div>
    <div class="flex flex-wrap items-start justify-between gap-3 border-t border-violet-400/10 pt-3">
        <details @if(request('opponent') || request('result')) open @endif class="min-w-0 flex-1">
            <summary class="cursor-pointer text-xs text-slate-400 hover:text-violet-200">Mais filtros</summary>
            <div class="mt-3 grid gap-3 sm:max-w-xl sm:grid-cols-2">
                <label class="text-xs text-slate-400">Campeão adversário<select class="field mt-1" name="opponent"><option value="">Todos</option>@foreach($opponentOptions as $name)<option @selected(request('opponent') === $name)>{{ $name }}</option>@endforeach</select></label>
                <label class="text-xs text-slate-400">Resultado<select class="field mt-1" name="result"><option value="">Todos</option><option value="win" @selected(request('result') === 'win')>Vitórias</option><option value="loss" @selected(request('result') === 'loss')>Derrotas</option></select></label>
            </div>
        </details>
        <a href="{{ route('contas.show', ['conta'=>$conta, 'tab'=>$tab, 'season'=>$season->id]) }}" class="text-xs text-violet-300 hover:text-violet-100">Limpar filtros</a>
    </div>
</form>
</details>
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
        <label class="text-xs text-slate-400">Fila<select class="field mt-1" name="queue"><option value="420">Solo/Duo</option><option value="440">Flex</option></select></label>
        <button type="submit" class="btn-secondary" @disabled($conta->latestSync?->isActive())>Importar temporada {{ $season->name }}</button>
    </form>
</details>
<details class="mt-5 rounded-lg border border-violet-400/10 p-5">
    <summary class="cursor-pointer text-xs text-slate-500">Gerenciar cadastro e informações dos dados</summary>
    <p class="mt-4 text-xs text-slate-500">Última atividade Solo/Duo: {{ $conta->last_game_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') ?? 'não disponível' }}. Ranking e histórico podem abranger períodos diferentes. O painel não calcula a reserva real de decay; consulte o cliente para informações oficiais.</p>
    <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-rose-900/30 pt-4">
        <p class="text-xs text-slate-500">Excluir remove definitivamente este cadastro e suas partidas.</p>
        <form method="POST" action="{{ route('contas.destroy', $conta) }}" onsubmit="return confirm('Excluir esta conta e todo o histórico? Esta ação é permanente.')">
            @csrf @method('DELETE')<button class="btn-danger" type="submit" @disabled($conta->latestSync?->isActive())>Excluir conta</button>
        </form>
    </div>
</details>
@endsection
