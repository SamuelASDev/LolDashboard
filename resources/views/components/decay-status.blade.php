@props(['decay', 'account', 'compact' => false])
@if($decay)
<section class="mt-4 rounded-lg border p-4 {{ $decay['status'] === 'possible_decay' ? 'border-rose-400/30 bg-rose-400/5' : ($decay['status'] === 'protected' ? 'border-violet-400/20 bg-violet-400/5' : 'border-amber-400/30 bg-amber-400/5') }}" aria-label="Decay Solo/Duo">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-xs font-semibold text-slate-200">Decay · Solo/Duo Mestre+</h3>
        <span class="text-[10px] text-slate-400">Estimativa{{ $decay['manual'] ? ' com referência do cliente' : ' automática' }}</span>
    </div>
    <p class="mt-2 text-sm font-semibold {{ $decay['status'] === 'possible_decay' ? 'text-rose-300' : 'text-amber-200' }}">
        @switch($decay['status'])
            @case('possible_decay') Possível decay em andamento @break
            @case('uncertain') Reserva incerta · pode haver risco agora @break
            @case('warning') Atenção · proteção estimada perto do fim @break
            @default Proteção estimada disponível
        @endswitch
    </p>
    @if($decay['upper'] > 0)
        <p class="mt-1 text-sm tabular-nums text-slate-200">Reserva estimada: {{ $decay['remaining'] }}</p>
        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-800" role="progressbar" aria-label="Reserva estimada de decay" aria-valuemin="0" aria-valuemax="14" aria-valuenow="{{ round($decay['lower'] / 86400, 2) }}" aria-valuetext="{{ $decay['remaining'] }}; estimativa">
            <div class="h-full rounded-full bg-amber-300" style="width:{{ $decay['percent'] }}%"></div>
        </div>
        <p class="mt-2 text-[10px] leading-relaxed text-slate-400">{{ $decay['lower'] === $decay['upper'] ? 'Esgotamento previsto' : 'Limite superior previsto' }}: {{ $decay['deadline']->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }} (Brasília), se não jogar.</p>
    @else
        <p class="mt-1 text-xs leading-relaxed text-slate-300">Reserva estimada esgotada. Se confirmada no cliente, a penalidade é de 75 LP por dia.</p>
    @endif
    @if($decay['stale'])<p class="mt-2 text-xs text-amber-300">Dados incompletos ou desatualizados. Sincronize Solo/Duo antes de confiar na previsão.</p>@endif
    <p class="mt-2 text-[10px] leading-relaxed text-slate-400">{{ $decay['manual'] ? 'Base: dias informados por você' : 'Saldo inicial desconhecido (0–14 dias)' }} + partidas importadas. O horário diário da Riot pode diferir. Perda real de LP não confirmada.</p>
    @if($compact)
        <a class="mt-3 inline-block text-xs text-violet-300 underline" href="{{ route('contas.show', $account) }}#decay-reference">Conferir e ajustar reserva</a>
    @endif
</section>
@endif
