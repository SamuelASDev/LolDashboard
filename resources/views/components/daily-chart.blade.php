@props(['points' => []])
@php($maximum = max(1, collect($points)->max('games') ?? 1))
<div>
    @if(count($points))
    <div class="chart-scroll">
        <div class="daily-chart" style="--columns:{{ count($points) }}">
        @foreach($points as $point)
            <div class="chart-column group" tabindex="0" aria-label="{{ $point['title'] }}: {{ $point['wins'] }} vitórias e {{ $point['losses'] }} derrotas">
                <div class="chart-tooltip">{{ $point['title'] }}<br>{{ $point['wins'] }}V · {{ $point['losses'] }}D</div>
                <span class="mb-2 text-[10px] text-slate-500">{{ $point['games'] }}</span>
                <div class="flex w-full max-w-8 flex-col justify-end overflow-hidden rounded-t-sm" style="height:{{ max(3, $point['games'] / $maximum * 130) }}px">
                    <div class="bg-violet-400" style="height:{{ $point['losses'] / $point['games'] * 100 }}%"></div>
                    <div class="bg-amber-300" style="height:{{ $point['wins'] / $point['games'] * 100 }}%"></div>
                </div>
                <span class="mt-3 text-[9px] tabular-nums text-slate-500">{{ $point['label'] }}</span>
            </div>
        @endforeach
        </div>
    </div>
    <div class="mt-5 flex flex-wrap items-center justify-between gap-3 text-[11px] text-slate-400"><span><i class="mr-1 inline-block h-2 w-2 rounded-full bg-amber-300"></i> Vitórias <i class="ml-4 mr-1 inline-block h-2 w-2 rounded-full bg-violet-400"></i> Derrotas</span><span>Últimos {{ count($points) }} dias com partidas · Brasília</span></div>
    @else
    <div class="chart-empty"><span class="text-3xl text-violet-400/50">◇</span><p class="mt-3 text-sm text-slate-400">Sua evolução começa com a primeira partida importada.</p></div>
    @endif
</div>
