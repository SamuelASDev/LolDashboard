@props(['players', 'identity' => null])
<div class="rounded-lg border border-violet-400/10 bg-[#100c18]/50 p-3" aria-label="Enfrentamentos por posição">
    <div class="mb-2 flex justify-between px-2 text-[9px] uppercase tracking-widest"><span class="text-sky-300/70">Time azul</span><span class="text-rose-300/70">Time vermelho</span></div>
    @foreach(\App\Support\GameLabels::ROLES as $role=>$label)
        @if($role === 'UNKNOWN') @continue @endif
        @php
            $blue = $players->where('team_id', 100)->where('position', $role);
            $red = $players->where('team_id', 200)->where('position', $role);
            $left = $blue->count() === 1 ? $blue->first() : null;
            $right = $red->count() === 1 ? $red->first() : null;
        @endphp
        <div class="match-lineup">
            <div class="flex min-w-0 items-center gap-2">
                @if($left)<x-game-icon :id="$left->champion_id" :name="$left->champion_name" size="h-6 w-6" /><span class="min-w-0 truncate text-[10px] {{ $left->puuid === $identity ? 'font-bold text-amber-200' : 'text-slate-400' }}" title="{{ $left->riot_id }}">{{ $left->riot_id }}</span>@else<span class="text-[10px] text-slate-600">Não identificado</span>@endif
            </div>
            <span class="flex flex-col items-center gap-1 text-violet-300/70" title="{{ $label }}"><x-role-icon :role="$role" size="h-3.5 w-3.5" /><span class="text-[7px] uppercase tracking-tight">{{ ['TOP'=>'TOP','JUNGLE'=>'JG','MIDDLE'=>'MID','BOTTOM'=>'ADC','UTILITY'=>'SUP'][$role] }}</span></span>
            <div class="flex min-w-0 items-center justify-end gap-2">
                @if($right)<span class="min-w-0 truncate text-right text-[10px] {{ $right->puuid === $identity ? 'font-bold text-amber-200' : 'text-slate-400' }}" title="{{ $right->riot_id }}">{{ $right->riot_id }}</span><x-game-icon :id="$right->champion_id" :name="$right->champion_name" size="h-6 w-6" />@else<span class="text-[10px] text-slate-600">Não identificado</span>@endif
            </div>
        </div>
    @endforeach
    @if($players->whereNotIn('position', ['TOP','JUNGLE','MIDDLE','BOTTOM','UTILITY'])->isNotEmpty())<p class="mt-2 text-[9px] text-slate-600">Há posições não identificadas. Todos os jogadores estão no placar completo.</p>@endif
</div>
