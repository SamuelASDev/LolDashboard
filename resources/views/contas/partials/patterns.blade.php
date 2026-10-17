<div class="mb-5"><h2 class="section-title">O que seu histórico revela</h2><p class="mt-2 text-xs text-slate-500">Padrões descritivos do recorte. Amostras pequenas podem variar bastante.</p></div>
<div class="grid items-start gap-5 lg:grid-cols-2">
@foreach(['side'=>'Lado do mapa','duration'=>'Duração das partidas','day'=>'Dia da semana','hour'=>'Horário do fim da partida','after'=>'Depois do resultado anterior','surrender'=>'Rendição'] as $dimension=>$heading)
<section class="panel overflow-hidden">
    <div class="section-heading"><h3 class="section-title">{{ $heading }}</h3><span class="text-[10px] text-slate-500">Taxa de vitória</span></div>
    <div class="space-y-4 p-5">
    @forelse($insight['groups'][$dimension] as $label=>$group)
        @php($rate = round($group['wins'] / $group['games'] * 100,1))
        <div><div class="mb-2 flex justify-between gap-3 text-xs"><span class="text-slate-300">{{ $label }}</span><span class="text-slate-500">{{ $group['games'] }} jogos · <strong class="text-amber-200">{{ $rate }}%</strong></span></div><div class="flex h-2 overflow-hidden rounded-full bg-violet-400/15"><div class="bg-amber-300" style="width:{{ $rate }}%"></div></div></div>
    @empty<p class="py-6 text-center text-xs text-slate-500">Aguardando partidas com esses dados.</p>@endforelse
    </div>
</section>
@endforeach
</div>
<section class="panel mt-5 overflow-hidden"><div class="section-heading"><div><h3 class="section-title">Companheiros recorrentes</h3><p class="mt-1 text-[10px] text-slate-500">No mesmo time; não confirma participação em grupo.</p></div></div><div class="grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-3">@forelse($insight['teammates'] as $teammate)<div class="rounded-lg border border-violet-400/10 p-3"><p class="truncate text-sm text-slate-300"><a class="underline" href="{{ route('players.show', ['puuid'=>$teammate->puuid, 'relation'=>'ally']) }}">{{ $teammate->riot_id }}</a>@if($playerNotes->has($teammate->puuid))<small class="block text-amber-200">Com anotacao</small>@endif</p><p class="mt-2 text-[10px] text-slate-500">{{ $teammate->games }} jogos <span class="mx-1">·</span><span class="text-amber-200">{{ round($teammate->wins / $teammate->games * 100,1) }}% vitórias</span></p></div>@empty<p class="text-xs text-slate-500">Sem participantes detalhados no recorte.</p>@endforelse</div></section>
<div class="mt-5 grid items-start gap-5 xl:grid-cols-2">
@foreach(['laneOpponents' => ['Adversários na sua posição', 'Mesma posição que você em cada partida; posições ambíguas ficam fora desta lista.'], 'allOpponents' => ['Adversários em todas as posições', 'Qualquer jogador do time inimigo, independentemente da posição dele.']] as $key => [$heading, $description])
    <section class="panel overflow-hidden" aria-label="{{ $heading }}">
        <div class="section-heading"><div><h3 class="section-title">{{ $heading }}</h3><p class="mt-1 text-[10px] leading-relaxed text-slate-500">{{ $description }}</p></div></div>
        <ol class="space-y-3 p-5">
        @forelse($insight[$key] as $opponent)
            <li class="flex items-start gap-3 rounded-lg border border-violet-400/10 p-3">
                <span class="mt-0.5 text-xs tabular-nums text-violet-300">{{ $loop->iteration }}.</span>
                <div class="min-w-0 flex-1"><p class="break-words text-sm text-slate-300"><a class="underline" href="{{ route('players.show', ['puuid'=>$opponent->puuid, 'relation'=>'enemy']) }}">{{ $opponent->riot_id }}</a>@if($playerNotes->has($opponent->puuid))<small class="block text-amber-200">Com anotacao</small>@endif</p><p class="mt-2 text-[10px] leading-relaxed text-slate-500">{{ $opponent->games }} jogos · {{ $opponent->wins }}V / {{ $opponent->games - $opponent->wins }}D <span class="text-amber-200">· {{ round($opponent->wins / $opponent->games * 100, 1) }}% de vitórias suas</span></p></div>
            </li>
        @empty
            <li class="text-xs text-slate-500">Sem adversários identificados neste recorte.</li>
        @endforelse
        </ol>
        <p class="border-t border-violet-400/10 px-5 py-3 text-[10px] leading-relaxed text-slate-500">Até 10 jogadores mais enfrentados nos filtros selecionados. {{ $aggregate ? 'Contagem por participação de cada conta; uma partida pode aparecer em duas contas.' : 'Cada jogador conta uma vez por partida.' }} A taxa mede o resultado da partida.</p>
    </section>
@endforeach
</div>
