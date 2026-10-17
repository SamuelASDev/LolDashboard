@props(['progress'])
<section class="mt-4 space-y-4" aria-label="Progresso de elo Solo/Duo">
@foreach($progress['bars'] as $bar)
    <div>
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2 text-xs"><span class="font-semibold text-slate-300">{{ $bar['label'] }}</span>@if($bar['target'] !== null)<span class="tabular-nums text-slate-400">{{ number_format($bar['current'], 0, ',', '.') }} / {{ number_format($bar['target'], 0, ',', '.') }} LP</span>@endif</div>
        @if($bar['percent'] !== null)
            <div role="progressbar" aria-label="{{ $bar['label'] }}" aria-valuemin="0" aria-valuemax="{{ $bar['target'] }}" aria-valuenow="{{ min($bar['current'], $bar['target']) }}" aria-valuetext="{{ $bar['percent'] }} por cento{{ $bar['stale'] ? ', referência desatualizada' : '' }}" class="h-2 overflow-hidden rounded-full bg-slate-800">
                <div class="h-full rounded-full {{ $bar['stale'] ? 'bg-slate-500' : 'bg-gradient-to-r from-amber-500 to-amber-300' }}" style="width:{{ $bar['percent'] }}%"></div>
            </div>
            <p class="mt-2 text-xs text-slate-400">
                @if($bar['remaining'] > 0)
                    Faltam {{ number_format($bar['remaining'], 0, ',', '.') }} LP {{ $bar['dynamic'] ? 'para a referência consultada' : 'para o próximo elo' }}.
                @elseif($bar['dynamic'])
                    Referência alcançada{{ $bar['margin'] > 0 ? ' · '.number_format($bar['margin'], 0, ',', '.').' LP acima' : '' }}.
                @else
                    Progresso de LP concluído; sincronize para atualizar o elo.
                @endif
            </p>
        @else
            <p class="rounded-lg border border-dashed border-slate-700 p-3 text-xs text-slate-500">Corte ainda indisponível. Aguardando a consulta diária.</p>
        @endif
        @if($bar['dynamic'] && $bar['fetched_at'])
            <p class="mt-1 text-[10px] leading-relaxed text-slate-500"><a class="underline hover:text-slate-300" href="{{ $bar['source_url'] }}" target="_blank" rel="noopener noreferrer">Replays.lol</a> · Consultado {{ $bar['fetched_at']->timezone('America/Sao_Paulo')->format('d/m H:i') }} · Fonte {{ $bar['source_updated_at']?->timezone('America/Sao_Paulo')->format('d/m H:i') }} (Brasília)</p>
        @endif
        @if($bar['dynamic'] && $bar['stale'] && $bar['target'] !== null)<p class="mt-1 text-xs text-amber-300">Referência desatualizada; último valor preservado.</p>@endif
    </div>
@endforeach
<p class="text-[10px] leading-relaxed text-slate-500">{{ $progress['message'] }}</p>
</section>
