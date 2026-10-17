@if($run = $conta->latestSync)
@if(! $run->isActive())
<details class="panel mb-5 px-4 py-3">
    <summary class="flex cursor-pointer flex-wrap items-center justify-between gap-2 text-xs">
        <span class="text-slate-400">{{ $run->message }}</span>
        <span class="meta-pill">{{ ['completed'=>'Concluída','failed'=>'Interrompida','cancelled'=>'Cancelada','partial'=>'Parcial'][$run->status] ?? $run->status }}</span>
    </summary>
    <p class="mt-3 text-[10px] text-slate-500">{{ $run->processed }} IDs processados · {{ $run->imported }} novas partidas · {{ $run->updated_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</p>
</details>
@else
@php $initial = ['status' => $run->status, 'message' => $run->message, 'processed' => $run->processed, 'imported' => $run->imported, 'active' => $run->isActive()]; @endphp
<div class="panel mb-6 p-5" x-data="syncProgress(@js(route('contas.syncStatus', $conta)), @js($initial))" aria-live="polite">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-sm font-semibold">Sincronização <span class="ml-2 text-xs font-normal text-amber-300" x-text="label"></span></h2>
        <form x-show="run.active" method="POST" action="{{ route('contas.cancelSync', $conta) }}">@csrf @method('DELETE')<button class="text-xs text-slate-400 hover:text-rose-300" type="submit">Cancelar</button></form>
    </div>
    <p class="mt-2 text-sm text-slate-400" x-text="run.message">{{ $run->message }}</p>
    @if($window = $run->context['history_window'] ?? null)
        <p class="mt-2 text-xs text-slate-500">Temporada {{ $window['season'] }} · {{ \Carbon\CarbonImmutable::createFromTimestamp($window['start'], 'UTC')->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }} até {{ \Carbon\CarbonImmutable::createFromTimestamp($window['end'], 'UTC')->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }} (Brasília)</p>
    @endif
    <p class="mt-2 text-xs text-slate-500"><span x-text="run.processed">{{ $run->processed }}</span> IDs processados · <span x-text="run.imported">{{ $run->imported }}</span> novas partidas</p>
    <p x-cloak x-show="error" x-text="error" class="mt-3 text-xs text-rose-300"></p>
    <a x-cloak x-show="finishedNow || error" href="{{ route('contas.show', array_merge(request()->except('page'), ['conta' => $conta, 'days' => $days])) }}" class="btn-secondary mt-4">Atualizar os dados da página</a>
    <noscript><p class="mt-3 text-xs text-slate-400">Atualize a página para acompanhar o progresso.</p></noscript>
</div>
@endif
@endif
