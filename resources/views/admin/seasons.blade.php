@extends('layouts.main')
@section('title', 'Temporadas')
@section('content')
<header class="mb-7"><p class="eyebrow">Administração</p><h1 class="hero-title mt-3">Temporadas</h1><p class="mt-3 max-w-3xl text-sm text-slate-400">Cadastre a próxima temporada e inicie quando estiver pronto. A troca encerra a atual no mesmo instante, sem apagar partidas. Horários de Brasília.</p></header>
<form method="POST" action="{{ route('admin.seasons.store') }}" class="panel mb-7 flex flex-wrap items-end gap-3 p-5">
    @csrf<label class="text-sm text-slate-400">Nome da temporada<input name="name" maxlength="80" value="{{ old('name') }}" placeholder="Ex.: 2027" class="field mt-2" required></label><button class="btn-primary" type="submit">Cadastrar temporada</button>
</form>
<div class="grid gap-4">
@foreach($seasons as $item)
<article class="panel flex flex-wrap items-center justify-between gap-5 p-5">
    <div><h2 class="text-lg font-semibold">{{ $item->name }} <span class="ml-2 text-xs text-cyan-300">{{ ['draft'=>'Não iniciada','active'=>'Atual','ended'=>'Encerrada'][$item->status] }}</span></h2>
    <p class="mt-2 text-xs text-slate-400">Início: {{ $item->starts_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s') ?? 'ao iniciar' }} · Fim: {{ $item->ends_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s') ?? '—' }}</p></div>
    @if($item->status === 'draft')
    <form method="POST" action="{{ route('admin.seasons.activate', $item) }}" onsubmit="return confirm('Iniciar esta temporada agora e encerrar a atual? O histórico anterior será preservado.')">
        @csrf<button class="btn-primary" type="submit">Iniciar {{ $item->name }}</button>
    </form>
    @else
    <a class="btn-secondary" href="{{ route('dashboard', ['season'=>$item->id, 'days'=>0]) }}">Consultar histórico</a>
    @endif
</article>
@endforeach
</div>
<p class="mt-5 text-xs text-slate-500">Temporadas encerradas ficam disponíveis nos filtros. O ranking exibido nas contas continua sendo o ranking atual consultado na Riot; as estatísticas de partidas respeitam a temporada selecionada.</p>
@endsection
