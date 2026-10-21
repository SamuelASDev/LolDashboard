@extends('layouts.main')
@section('title', 'Jogadores encontrados')
@section('content')
<h1 class="section-title mb-4">Jogadores encontrados no seu histórico</h1>
<p class="mb-4 text-sm text-slate-400">Pesquise pelo nome ou tag. Inclui suas contas Solo/Duo, em todas as temporadas importadas.</p>
<form class="filter-bar mb-5" method="GET">
    <label>Jogador<input class="field" name="q" maxlength="100" value="{{ request('q') }}" placeholder="Nome#TAG"></label>
    <label>Encontro<select class="field" name="relation"><option value="">Todos</option><option value="ally" @selected(request('relation') === 'ally')>No meu time</option><option value="enemy" @selected(request('relation') === 'enemy')>Contra mim</option></select></label>
    <button class="btn-primary">Pesquisar jogadores</button>
</form>
<div class="grid gap-4 sm:grid-cols-2">
@forelse($players as $player)
<a class="panel p-5" href="{{ route('players.show', ['puuid'=>$player->puuid]) }}">
    <h2 class="text-cyan-200 break-words">{{ $player->riot_id }}</h2>
    <p class="mt-2 text-xs text-slate-400">{{ $player->games }} participações · {{ $player->allies }} no seu time · {{ $player->games - $player->allies }} contra você</p>
    @if($notes->has($player->puuid))<p class="mt-2 text-xs text-amber-200">Com anotação: {{ Str::limit($notes->get($player->puuid), 120) }}</p>@endif
</a>
@empty<p class="panel p-6 text-slate-400">Nenhum jogador encontrado. São necessárias partidas com placar importado.</p>@endforelse
</div>
<div class="mt-5">{{ $players->links() }}</div>
@endsection
