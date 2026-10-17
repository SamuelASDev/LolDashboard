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
<section class="panel mt-5 overflow-hidden"><div class="section-heading"><div><h3 class="section-title">Companheiros recorrentes</h3><p class="mt-1 text-[10px] text-slate-500">No mesmo time; não confirma participação em grupo.</p></div></div><div class="grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-3">@forelse($insight['teammates'] as $teammate)<div class="rounded-lg border border-violet-400/10 p-3"><p class="truncate text-sm text-slate-300">{{ $teammate->riot_id }}</p><p class="mt-2 text-[10px] text-slate-500">{{ $teammate->games }} jogos <span class="mx-1">·</span><span class="text-amber-200">{{ round($teammate->wins / $teammate->games * 100,1) }}% vitórias</span></p></div>@empty<p class="text-xs text-slate-500">Sem participantes detalhados no recorte.</p>@endforelse</div></section>
