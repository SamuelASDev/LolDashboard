@extends('layouts.main')
@section('title', 'Histórico do jogador')
@section('content')
<a class="text-xs text-violet-300" href="{{ route('players.index') }}">← Buscar jogadores</a>
<h1 class="section-title mt-4 break-words">{{ $player->riot_id }}</h1>
<form class="panel p-5 my-5" method="POST" action="{{ route('players.note', $player->puuid) }}">
    @csrf @method('PUT')
    <label class="text-sm" for="player-note">Sua anotação privada sobre este jogador</label>
    <textarea class="field mt-2" id="player-note" name="body" maxlength="5000" rows="3">{{ old('body', $note->body ?? '') }}</textarea>
    <p class="my-2 text-xs text-slate-500">A anotação acompanha a identidade do jogador. Deixe vazia para remover.</p>
    <button class="btn-primary">Salvar anotação do jogador</button>
</form>
<form method="GET" class="filter-bar mb-5"><label>Partidas<select class="field" name="relation"><option value="">Com e contra ele</option><option value="ally" @selected(request('relation') === 'ally')>No meu time</option><option value="enemy" @selected(request('relation') === 'enemy')>Contra ele</option></select></label><button class="btn-secondary">Filtrar encontros</button></form>
<p class="mb-4 text-xs text-slate-400">{{ $matches->total() }} participações das suas contas. A mesma partida pode aparecer em mais de uma conta.</p>
<div class="space-y-4">
@forelse($matches as $match)
    <p class="text-xs text-violet-300">{{ $match->conta->riot_id }}</p>
    <x-match-card :match="$match" :player-notes="$playerNotes" />
    @include('workspace.match-note', ['partida'=>$match])
@empty<p class="panel p-5 text-slate-400">Nenhuma partida neste filtro.</p>@endforelse
</div>
<div class="mt-5">{{ $matches->links() }}</div>
@endsection
