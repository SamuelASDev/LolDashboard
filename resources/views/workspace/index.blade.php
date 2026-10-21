@extends('layouts.main')
@section('title', 'Meu caderno')
@section('content')
<h1 class="section-title mb-5">Meu caderno</h1>
<section class="panel p-5 mb-5"><h2 class="section-title mb-4">Filtros favoritos</h2>
@forelse($favorites as $favorite)
    @php($parameters = json_decode($favorite->filters, true))
    <div class="flex gap-3 justify-between items-center py-3 border-b border-cyan-400/10">
        <a class="text-cyan-200" href="{{ $favorite->conta_id ? route('contas.show', ['conta'=>$favorite->conta_id] + $parameters) : route('dashboard', $parameters) }}">{{ $favorite->name }}</a>
        <form method="POST" action="{{ route('workspace.destroy', ['type'=>'favoritos','id'=>$favorite->id]) }}">@csrf @method('DELETE')<button class="text-xs text-slate-400">Remover favorito</button></form>
    </div>
@empty<p class="text-sm text-slate-400">Salve um filtro no dashboard ou na página de uma conta.</p>@endforelse
</section>
<section class="panel p-5 mb-5"><h2 class="section-title mb-4">Metas</h2>
<p class="mb-4 text-xs text-slate-400">Soma das suas contas Solo/Duo, sem remakes, no intervalo UTC escolhido. Uma partida com duas contas conta duas participações.</p>
@foreach($goals as $goal)
    <div class="py-4 border-b border-cyan-400/10"><div class="flex justify-between gap-3"><h3>{{ $goal->title }}</h3><form method="POST" action="{{ route('workspace.destroy', ['type'=>'metas','id'=>$goal->id]) }}">@csrf @method('DELETE')<button class="text-xs text-slate-400">Remover meta</button></form></div>
    <p class="my-2 text-sm text-amber-200">{{ $goal->value }} / {{ (float) $goal->target }} {{ ['games'=>'partidas','wins'=>'vitórias','winrate'=>'% de vitórias'][$goal->metric] }} · {{ $goal->games }} partidas na amostra</p>
    <progress class="w-full" value="{{ min($goal->value, $goal->target) }}" max="{{ $goal->target }}"></progress>
    <p class="text-xs text-slate-500">{{ $goal->starts_on }} até {{ $goal->ends_on }} · {{ $goal->value >= $goal->target && $goal->games ? 'Meta atingida' : (now()->toDateString() > $goal->ends_on ? 'Período encerrado' : 'Em andamento') }}</p></div>
@endforeach
<form class="grid gap-3 mt-5 sm:grid-cols-2" method="POST" action="{{ route('workspace.goals') }}">@csrf
    <label>Título<input class="field" name="title" maxlength="100" required></label>
    <label>Métrica<select class="field" name="metric"><option value="games">Partidas jogadas</option><option value="wins">Vitórias</option><option value="winrate">Taxa de vitória (%)</option></select></label>
    <label>Alvo<input class="field" name="target" type="number" min="1" max="100000" step="0.01" required></label>
    <label>Início (UTC)<input class="field" name="starts_on" type="date" value="{{ now()->toDateString() }}" required></label>
    <label>Fim (UTC)<input class="field" name="ends_on" type="date" value="{{ now()->addMonth()->toDateString() }}" required></label>
    <button class="btn-primary self-end">Criar meta</button>
</form></section>
<section class="panel p-5"><h2 class="section-title mb-4">Jogadores anotados</h2>
@forelse($notes as $note)<a class="block py-3 border-b border-cyan-400/10" href="{{ route('players.show', $note->puuid) }}"><span class="text-cyan-200">{{ $note->riot_id }}</span><p class="mt-2 text-sm text-slate-400 whitespace-pre-wrap break-words">{{ $note->body }}</p></a>@empty<p class="text-sm text-slate-400">Busque um jogador e abra seu histórico para anotar.</p>@endforelse
{{ $notes->links() }}
</section>
@endsection
