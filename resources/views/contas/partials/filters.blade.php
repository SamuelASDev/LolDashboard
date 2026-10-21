<details class="panel mb-5" x-data="{ expanded: window.innerWidth >= 768 }" :open="expanded" @toggle="expanded = $el.open" open>
<summary class="flex cursor-pointer flex-wrap items-center justify-between gap-2 px-4 py-3 text-xs"><span class="font-semibold text-slate-300">Filtros do recorte <span class="ml-1 text-cyan-300" aria-hidden="true">⌄</span></span><span class="text-[10px] text-slate-500">{{ $queueLabel }} · {{ $days ? $days.' dias' : 'Toda a temporada' }}{{ request('role') ? ' · '.(\App\Support\GameLabels::ROLES[request('role')] ?? '') : '' }}</span></summary>
<form method="GET" class="filter-bar rounded-t-none border-x-0 border-b-0" aria-label="Filtrar estatísticas">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div class="grid items-end gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <x-season-select :season="$season" :seasons="$seasons" />
        <label class="text-xs text-slate-400">Período
            <select class="field mt-1.5" name="days">@foreach([7=>'7 dias',30=>'30 dias',90=>'90 dias',365=>'1 ano',0=>'Toda a temporada'] as $value=>$label)<option value="{{ $value }}" @selected($days === $value)>{{ $label }}</option>@endforeach</select>
        </label>
        <label class="text-xs text-slate-400">Fila
            <select class="field mt-1.5" name="queue"><option value="420" @selected((int) request('queue',420) === 420)>Solo/Duo</option></select>
        </label>
        <label class="text-xs text-slate-400">Campeão
            <select class="field mt-1.5" name="champion"><option value="">Todos os campeões</option>@foreach($championOptions as $name)<option @selected(request('champion') === $name)>{{ $name }}</option>@endforeach</select>
        </label>
        <label class="text-xs text-slate-400">Posição
            <select class="field mt-1.5" name="role"><option value="">Todas as posições</option>@foreach(\App\Support\GameLabels::ROLES as $value=>$label)<option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>@endforeach</select>
        </label>
        <button class="btn-primary" type="submit">Aplicar filtros</button>
    </div>
    <div class="flex flex-wrap items-start justify-between gap-3 border-t border-cyan-400/10 pt-3">
        <details @if(request('opponent') || request('result')) open @endif class="min-w-0 flex-1">
            <summary class="cursor-pointer text-xs text-slate-400 hover:text-cyan-200">Mais filtros</summary>
            <div class="mt-3 grid gap-3 sm:max-w-xl sm:grid-cols-2">
                <label class="text-xs text-slate-400">Campeão adversário<select class="field mt-1" name="opponent"><option value="">Todos</option>@foreach($opponentOptions as $name)<option @selected(request('opponent') === $name)>{{ $name }}</option>@endforeach</select></label>
                <label class="text-xs text-slate-400">Resultado<select class="field mt-1" name="result"><option value="">Todos</option><option value="win" @selected(request('result') === 'win')>Vitórias</option><option value="loss" @selected(request('result') === 'loss')>Derrotas</option></select></label>
            </div>
        </details>
        <a href="{{ route($analyticsRoute, array_merge($analyticsParams, ['tab'=>$tab, 'season'=>$season->id])) }}" class="text-xs text-cyan-300 hover:text-cyan-100">Limpar filtros</a>
    </div>
</form>
</details>

<form class="panel p-4 mb-5 flex flex-wrap gap-3 items-end" method="POST" action="{{ route('workspace.favorites') }}">
    @csrf
    @if(!$aggregate)<input type="hidden" name="conta_id" value="{{ $conta->id }}">@endif
    @foreach(array_merge(request()->only('champion','opponent','role','result'), ['tab'=>$tab,'days'=>$days,'season'=>$season->id]) as $key=>$value)
        <input type="hidden" name="filters[{{ $key }}]" value="{{ $value }}">
    @endforeach
    <label class="text-xs text-slate-400">Nome do filtro favorito<input class="field mt-1" name="name" maxlength="80" required placeholder="Ex.: Mid - ultimos 30 dias"></label>
    <button class="btn-secondary">Salvar filtro favorito</button>
    <a class="text-xs text-cyan-300" href="{{ route('workspace.index') }}">Favoritos e metas</a>
</form>
