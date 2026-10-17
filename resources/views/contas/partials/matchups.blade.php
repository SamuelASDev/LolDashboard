<header class="mb-5 flex flex-wrap items-start justify-between gap-3"><div><h2 class="text-lg font-semibold text-amber-50">Confrontos por posição</h2><p class="mt-2 max-w-2xl text-xs leading-relaxed text-slate-500">Contra quem você jogou na mesma posição. As taxas representam o resultado da partida, não uma medição de vitória de rota. Cada confronto usa somente seu histórico.</p></div><span class="meta-pill">{{ $matchups->flatten(1)->sum('games') }} confrontos identificados</span></header>
<div class="mb-5 flex flex-wrap gap-2" aria-label="Posições dos confrontos">
    <a class="role-pill" href="{{ route('contas.show', array_merge(request()->except(['page','role']), ['conta'=>$conta,'tab'=>'matchups'])) }}" aria-current="{{ request('role') ? 'false' : 'true' }}">Todas</a>
    @foreach(\App\Support\GameLabels::ROLES as $role=>$label)
    @if($role !== 'UNKNOWN')<a class="role-pill" href="{{ route('contas.show', array_merge(request()->except('page'), ['conta'=>$conta,'tab'=>'matchups','role'=>$role])) }}" aria-current="{{ request('role') === $role ? 'true' : 'false' }}"><x-role-icon :role="$role" />{{ $label }}</a>@endif
    @endforeach
</div>
<div class="grid items-start gap-5 xl:grid-cols-2">
@foreach(\App\Support\GameLabels::ROLES as $role=>$label)
    @php($opponents = $matchups->get($role, collect()))
    @if($opponents->isNotEmpty())
    <section class="panel overflow-hidden" aria-label="Confrontos: {{ $label }}">
        <div class="section-heading"><h3 class="flex items-center gap-3 text-sm font-semibold"><span class="text-violet-300"><x-role-icon :role="$role" /></span>{{ $label }}</h3><span class="text-[10px] text-slate-500">{{ $opponents->sum('games') }} partidas · {{ $opponents->count() }} adversários</span></div>
        <div class="space-y-2 p-4">
            @foreach($opponents->take(8) as $opponent)
            <x-opponent-row :opponent="$opponent" :role="$role" :account="$conta" />
            @endforeach
            @if($opponents->count() > 8)
            <details class="pt-2"><summary class="cursor-pointer px-2 py-2 text-xs text-violet-300">Ver mais {{ $opponents->count()-8 }} adversários</summary><div class="mt-2 space-y-2">@foreach($opponents->skip(8) as $opponent)<x-opponent-row :opponent="$opponent" :role="$role" :account="$conta" />@endforeach</div></details>
            @endif
        </div>
    </section>
    @endif
@endforeach
@if($matchups->isEmpty())<div class="panel col-span-full p-12 text-center"><span class="mx-auto block w-fit text-violet-400"><x-role-icon role="MIDDLE" size="h-10 w-10" /></span><h3 class="mt-4 text-sm font-semibold">Ainda não há confrontos neste recorte</h3><p class="mx-auto mt-2 max-w-lg text-xs leading-relaxed text-slate-500">Importe os detalhes das partidas ou amplie os filtros. A posição precisa estar identificada nos dois times para classificar o adversário.</p></div>@endif
</div>
