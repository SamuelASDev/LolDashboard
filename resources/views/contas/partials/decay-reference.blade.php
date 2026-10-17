@if($decay)
<details id="decay-reference" class="panel mb-5 p-5" @if($errors->has('decay_days')) open @endif>
    <summary class="cursor-pointer text-sm font-semibold text-slate-300">Ajustar reserva de decay pelo cliente</summary>
    <p class="mt-3 max-w-3xl text-xs leading-relaxed text-slate-400">Confira a reserva Solo/Duo no cliente do LoL e informe quantos dias restam agora. Cada partida acrescenta 1 dia, até 14; jogar uma vez não restaura todos os dias. Use 0 se o cliente indicar que a proteção acabou.</p>
    <form method="POST" action="{{ route('contas.decay.update', $conta) }}" class="mt-4 flex flex-wrap items-end gap-3">
        @csrf @method('PUT')
        <div>
            <label for="decay-days" class="text-xs text-slate-300">Dias restantes agora (0 a 14)</label>
            <input id="decay-days" name="decay_days" type="number" min="0" max="14" step="1" required value="{{ old('decay_days') }}" class="field mt-1 max-w-48" aria-describedby="decay-help{{ $errors->has('decay_days') ? ' decay-error' : '' }}" @if($errors->has('decay_days')) aria-invalid="true" @endif>
        </div>
        <button class="btn-secondary" type="submit">Salvar reserva atual</button>
    </form>
    @error('decay_days')<p id="decay-error" class="mt-2 text-xs text-rose-300">{{ $message }}</p>@enderror
    <p id="decay-help" class="mt-3 max-w-3xl text-[11px] leading-relaxed text-slate-500">O painel projeta períodos de 24 horas e considera apenas partidas Solo/Duo detalhadas, sem remakes, após a referência. O desconto da Riot ocorre no ciclo diário, então o horário é aproximado. Histórico incompleto, promoção/rebaixamento e jogos ainda não sincronizados podem alterar o resultado. A estimativa automática pressupõe permanência em Mestre+ durante a janela analisada. Confirme o saldo e a perda efetiva no cliente.</p>
    @if($decay['manual'])
        <p class="mt-3 text-xs text-slate-400">Referência informada em {{ $decay['reference_at']->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }} (Brasília) · {{ $decay['games'] }} partidas consideradas depois dela.</p>
        <form method="POST" action="{{ route('contas.decay.destroy', $conta) }}" class="mt-3">@csrf @method('DELETE')<button class="text-xs text-violet-300 underline" type="submit">Voltar à estimativa automática</button></form>
    @endif
    <p class="mt-3 text-[10px] text-slate-500"><a class="underline" href="{{ \App\Queries\AccountDecay::SOURCE }}" target="_blank" rel="noopener noreferrer">Regras da Riot</a>: Mestre, Grão-Mestre e Desafiante · 1 dia por partida · máximo de 14 · 75 LP por dia sem reserva. Monitoramento deste painel restrito a Mestre+ Solo/Duo.</p>
</details>
@endif
