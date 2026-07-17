@extends('layouts.main')

@section('content')

<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
    <div>
        <h2 class="text-2xl font-bold text-slate-100">Suas Contas Vinculadas</h2>
        <p class="text-sm text-slate-400">Gerencie e acompanhe o elo de suas contas in tempo real.</p>
    </div>
    
    <div class="flex gap-3">
        <a href="{{ route('contas.create') }}" class="px-4 py-2.5 bg-amber-500 hover:bg-amber-600 text-slate-950 font-semibold text-sm rounded-xl transition shadow-lg shadow-amber-500/10">
            + Nova Conta
        </a>
        
        <form method="POST" action="{{ route('contas.syncAll') }}">
            @csrf
            <button class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold text-sm rounded-xl border border-slate-700 transition">
                🔄 Atualizar Todas
            </button>
        </form>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
    @forelse($contas as $conta)
        @php
            $elo = $conta->elo ?? 'UNRANKED';
            $tier = strtolower(explode(' ', $elo)[0]);
            $tierUpper = strtoupper($tier);
            $pdl = $conta->pdl ?? 0;

            // Determinar cores dinâmicas baseadas no elo
            $eloColors = [
                'challenger' => 'from-red-500 to-amber-500 text-red-400',
                'grandmaster' => 'from-red-600 to-rose-500 text-red-500',
                'master' => 'from-purple-600 to-indigo-500 text-purple-400',
                'diamond' => 'from-cyan-500 to-blue-500 text-cyan-400',
                'emerald' => 'from-emerald-500 to-green-500 text-emerald-400',
                'platinum' => 'from-teal-500 to-emerald-400 text-teal-400',
                'gold' => 'from-amber-500 to-yellow-400 text-amber-400',
                'silver' => 'from-slate-400 to-slate-300 text-slate-300',
                'bronze' => 'from-orange-700 to-amber-800 text-orange-400',
                'iron' => 'from-neutral-600 to-neutral-700 text-neutral-400',
            ];
            $colorClass = $eloColors[$tier] ?? 'from-slate-700 to-slate-600 text-slate-400';

            // 🔥 CORREÇÃO DO BUG: Cálculo matemático dinâmico usando o cutoff_lp salvo da API
            $percent = 0;
            if($elo !== 'UNRANKED') {
                if(!in_array($tierUpper, ['MASTER','GRANDMASTER','CHALLENGER'])) {
                    $percent = min(100, ($pdl / 100) * 100);
                } elseif($tierUpper === 'MASTER') {
                    // Pega o corte do GM que buscamos na API (Padrão 600 se falhar)
                    $cutoffGM = $conta->cutoff_lp ?? 600;
                    $percent = min(100, ($pdl / max(1, $cutoffGM)) * 100);
                } elseif($tierUpper === 'GRANDMASTER') {
                    // Para o GM, o progresso vai de onde começa o GM até o corte do Desafiante (+400 LP acima)
                    $cutoffGM = $conta->cutoff_lp ?? 600;
                    $challCutoff = $cutoffGM + 400;
                    $percent = min(100, max(0, (($pdl - $cutoffGM) / max(1, $challCutoff - $cutoffGM)) * 100));
                } elseif($tierUpper === 'CHALLENGER') {
                    $percent = 100;
                }
            }
        @endphp

        <div class="bg-slate-900 border border-slate-800/80 rounded-2xl p-6 flex flex-col justify-between hover:border-slate-700 transition relative overflow-hidden group">
            <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r {{ $colorClass }}"></div>

            <div>
                <div class="flex justify-between items-start mb-4 gap-2">
                    <div class="min-w-0 flex-1">
                        <span class="text-xs font-semibold px-2.5 py-1 bg-slate-800 text-slate-400 rounded-full border border-slate-700">
                            {{ strtoupper($conta->regiao) }}
                        </span>
                        <h3 class="text-base sm:text-lg font-bold text-slate-100 mt-2 tracking-wide break-words" title="{{ $conta->riot_id }}">
                            {{ $conta->riot_id }}
                        </h3>
                    </div>
                    
                    <form method="POST" action="{{ route('contas.sync', $conta->id) }}" class="flex-shrink-0">
                        @csrf
                        <button type="submit" class="p-2 bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 rounded-lg transition" title="Sincronizar esta conta">
                            🔄
                        </button>
                    </form>
                </div>

                <div class="mb-4">
                    <p class="text-sm text-slate-400">Elo Atual</p>
                    <p class="text-xl font-black tracking-tight bg-gradient-to-r {{ $colorClass }} bg-clip-text text-transparent">
                        {{ $elo }} 
                        @if($elo !== 'UNRANKED')
                            <span class="text-sm font-semibold text-slate-300"> — {{ $pdl }} LP</span>
                        @endif
                    </p>
                </div>

                @if($elo !== 'UNRANKED')
                    <div class="w-full bg-slate-800 h-2 rounded-full overflow-hidden mb-4 border border-slate-700/50">
                        <div class="bg-gradient-to-r {{ $colorClass }} h-full transition-all duration-500" style="width: {{ $percent }}%"></div>
                    </div>
                @endif

                @if($tierUpper === 'MASTER' && !empty($conta->cutoff_lp))
                    @php $faltam = $conta->cutoff_lp - $pdl; @endphp
                    <p class="text-xs mb-4 px-3 py-2 bg-blue-500/5 border border-blue-500/10 text-blue-400 rounded-lg">
                        @if($faltam > 0)
                            ⬆️ Faltam <strong>{{ $faltam }}</strong> LP para o corte de GM
                        @else
                            🔥 Dentro do corte estimado para GM!
                        @endif
                    </p>
                @endif

                @if(in_array($tierUpper, ['MASTER', 'GRANDMASTER', 'CHALLENGER']))
                    <div class="mt-2 mb-4 p-3 rounded-xl border border-slate-800 bg-slate-950/45 text-xs font-medium">
                        @if(!empty($conta->last_game_at) && $conta->last_game_at->year > 1970)
                            @php
                                $horasPassadas = $conta->last_game_at->diffInHours(now());
                                $horasRestantes = 336 - $horasPassadas;
                                $diasRestantes = floor($horasRestantes / 24) - 1;
                            @endphp

                            @if($horasRestantes <= 0)
                                <div class="text-red-500 flex items-center gap-1.5 font-bold">
                                    🚨 <span class="animate-pulse">DECAY ATIVO:</span> Perdendo PDL por inatividade!
                                </div>
                            @elseif($diasRestantes <= 2)
                                <div class="text-rose-400 bg-rose-500/5 border-rose-500/10 p-2 rounded-lg">
                                    ⚠️ <span class="font-bold">Risco Alerta:</span> Restam apenas <strong>{{ max(0, $diasRestantes) }} dias</strong> de reserva!
                                </div>
                            @else
                                <div class="text-emerald-400 bg-emerald-500/5 border-emerald-500/10 p-2 rounded-lg">
                                    🛡️ <span class="font-bold">Status:</span> Seguro por mais <strong>{{ $diasRestantes }} dias</strong>.
                                </div>
                            @endif
                            
                            <p class="text-[10px] text-slate-500 mt-1.5 text-right">
                                Última partida: {{ $conta->last_game_at->format('d/m H:i') }}
                            </p>
                        @else
                            <div class="text-amber-400 bg-amber-500/5 border-amber-500/10 p-2 rounded-lg text-center">
                                🔄 Sincronize para buscar dados de decaimento.
                            </div>
                        @endif
                    </div>
                @endif

                <div class="grid grid-cols-2 gap-4 py-3 border-y border-slate-800/60 mb-4 text-sm">
                    <div>
                        <span class="text-slate-500 block text-xs">Taxa de Vitória</span>
                        <span class="font-bold {{ $conta->winrate >= 55 ? 'text-emerald-400' : ($conta->winrate < 50 ? 'text-red-400' : 'text-amber-400') }}">
                            {{ $conta->winrate ?? 0 }}%
                        </span>
                    </div>
                    <div>
                        <span class="text-slate-500 block text-xs">Vitórias / Derrotas</span>
                        <span class="font-medium text-slate-300">{{ $conta->wins ?? 0 }}W / {{ $conta->losses ?? 0 }}L</span>
                    </div>
                </div>

                <div class="mb-4 pt-1">
                    <p class="text-[10px] uppercase font-bold text-slate-500 tracking-wider mb-2">Top 3 Campeões Recentes (SoloQ)</p>
                    
                    @php
                        $partidasRecentes = $conta->partidas()->orderByDesc('played_at')->get();
                        
                        $campeoesAgrupados = $partidasRecentes->groupBy('champion_name')
                            ->map(function ($jogos) {
                                $total = $jogos->count();
                                $vitorias = $jogos->where('win', true)->count();
                                $kills = $jogos->sum('kills');
                                $deaths = $jogos->sum('deaths');
                                $assists = $jogos->sum('assists');
                                
                                return [
                                    'nome' => $jogos->first()->champion_name,
                                    'jogos' => $total,
                                    'winrate' => round(($vitorias / $total) * 100, 0),
                                    'kda' => $deaths > 0 ? round(($kills + $assists) / $deaths, 2) : ($kills + $assists)
                                ];
                            })
                            ->sortByDesc('jogos')
                            ->take(3);
                    @endphp

                    <div class="space-y-1.5">
                        @forelse($campeoesAgrupados as $camp)
                            <div class="flex items-center justify-between p-2 rounded-xl bg-slate-950/40 border border-slate-800/40">
                                <div class="flex items-center gap-2">
                                    <img src="https://cdn.communitydragon.org/latest/champion/{{ str_replace(' ', '', $camp['nome']) }}/square" 
                                         alt="{{ $camp['nome'] }}" 
                                         class="w-7 h-7 rounded-lg border border-slate-700/60 object-cover bg-slate-800"
                                         onerror="this.style.display='none';">
                                    <div>
                                        <span class="text-xs font-bold text-slate-300 block">{{ $camp['nome'] }}</span>
                                        <span class="text-[9px] text-slate-500">{{ $camp['jogos'] }} jogos</span>
                                    </div>
                                </div>
                                
                                <div class="text-right">
                                    <span class="text-xs font-black block {{ $camp['winrate'] >= 55 ? 'text-emerald-400' : ($camp['winrate'] < 50 ? 'text-red-400' : 'text-amber-400') }}">
                                        {{ $camp['winrate'] }}% WR
                                    </span>
                                    <span class="text-[9px] text-slate-500">KDA: <strong>{{ $camp['kda'] }}</strong></span>
                                </div>
                            </div>
                        @empty
                            <p class="text-[11px] text-slate-500 italic text-center py-2 bg-slate-950/20 border border-slate-900 rounded-lg">
                                Sincronize para carregar o histórico de campeões.
                            </p>
                        @endforelse
                    </div>
                </div>

                <div class="mb-4">
                    <a href="{{ route('contas.show', $conta->id) }}" class="block w-full text-center py-2 bg-slate-800/60 hover:bg-slate-800 text-slate-300 hover:text-slate-100 font-bold text-xs rounded-xl border border-slate-700/50 transition">
                        Ver Detalhes (Histórico Completo) 📊
                    </a>
                </div>

                <details class="text-xs text-slate-500 cursor-pointer mb-4 group/details">
                    <summary class="hover:text-slate-400 transition select-none">Identificação interna</summary>
                    <div class="mt-2 p-2 bg-slate-950/60 border border-slate-800 rounded-lg text-slate-400">
                        {{ $conta->nick }}
                    </div>
                </details>
            </div>

            <div class="flex gap-2 pt-2 border-t border-slate-800/40">
                <a href="{{ route('contas.edit', $conta->id) }}" class="flex-1 text-center py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium text-xs rounded-xl border border-slate-700/80 transition">
                    ✏️ Editar
                </a>
                
                <form method="POST" action="{{ route('contas.destroy', $conta->id) }}" onsubmit="return confirm('Tem certeza que deseja remover esta conta?')" class="flex-1">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="w-full py-2 bg-red-950/20 hover:bg-red-950/40 border border-red-900/30 text-red-400 font-medium text-xs rounded-xl transition">
                        🗑️ Excluir
                    </button>
                </form>
            </div>
        </div>
    @empty
        <div class="col-span-full bg-slate-900 border border-dashed border-slate-800 rounded-2xl p-12 text-center">
            <span class="text-4xl block mb-3">🧊</span>
            <h4 class="text-lg font-bold text-slate-300">Nenhuma conta cadastrada</h4>
            <p class="text-sm text-slate-500 mt-1 mb-4">Você ainda não vinculou nenhuma conta do League of Legends ao seu perfil.</p>
            <a href="{{ route('contas.create') }}" class="inline-block px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-semibold border border-slate-700 rounded-xl transition">
                Adicionar Primeira Conta
            </a>
        </div>
    @endforelse
</div>

<div class="bg-gradient-to-b from-slate-900 to-slate-950 border border-slate-800 rounded-2xl p-6 shadow-2xl relative overflow-hidden">
    <div class="absolute top-0 left-0 right-0 h-[1px] bg-gradient-to-r from-slate-800 via-indigo-500/20 to-slate-800"></div>

    <h3 class="text-lg font-bold text-slate-200 mb-6 tracking-wide flex items-center gap-2">
        <span>📊</span> Resumo Geral do Perfil Unificado
    </h3>
    
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="p-4 bg-slate-950/50 border border-slate-900 rounded-xl shadow-inner">
            <span class="text-xs text-slate-500 uppercase tracking-wider block mb-1">Total de Jogos</span>
            <span class="text-2xl font-black text-slate-200">{{ $totalGames }}</span>
        </div>
        <div class="p-4 bg-slate-950/50 border border-slate-900 rounded-xl shadow-inner">
            <span class="text-xs text-slate-500 uppercase tracking-wider block mb-1">Vitórias / Derrotas</span>
            <span class="text-xl font-black text-slate-200">{{ $totalWins }}W / {{ $totalLosses }}L</span>
        </div>
        <div class="p-4 bg-slate-950/50 border border-slate-900 rounded-xl shadow-inner">
            <span class="text-xs text-slate-500 uppercase tracking-wider block mb-1">Winrate Média</span>
            <span class="text-2xl font-black {{ $avgWinrate >= 55 ? 'text-emerald-400' : ($avgWinrate < 50 ? 'text-red-400' : 'text-amber-400') }}">{{ $avgWinrate }}%</span>
        </div>
        <div class="p-4 bg-slate-950/50 border border-slate-900 rounded-xl shadow-inner">
            <span class="text-xs text-slate-500 uppercase tracking-wider block mb-1">KDA Global</span>
            <span class="text-2xl font-black text-indigo-400">{{ $globalKDA ?? '0.00' }}</span>
            <span class="text-[10px] text-slate-500 block mt-1">Soma de {{ $totalGeralJogos ?? 0 }} partidas</span>
        </div>
    </div>
    
    <div class="pt-6 border-t border-slate-800/60">
        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 text-left flex items-center gap-1.5">
            <span class="text-amber-500">🔥</span> Todos os Campeões Registrados no Histórico (Soma de Todas as Contas)
        </h4>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
            @forelse($campeoesGlobais as $camp)
                <div class="p-3 bg-slate-950/40 border border-slate-900 rounded-xl flex flex-col justify-between hover:border-slate-800 transition">
                    <div class="flex items-center gap-2 mb-2 min-w-0">
                        <img src="https://cdn.communitydragon.org/latest/champion/{{ str_replace(' ', '', $camp['champion_name']) }}/square" 
                             alt="{{ $camp['champion_name'] }}" 
                             class="w-7 h-7 rounded border border-slate-700/80 shadow-md flex-shrink-0 object-cover bg-slate-800"
                             onerror="this.style.display='none';">
                        <span class="text-xs sm:text-sm font-bold text-slate-200 truncate" title="{{ $camp['champion_name'] }}">{{ $camp['champion_name'] }}</span>
                    </div>

                    <div class="mt-1 space-y-0.5">
                        <div class="flex justify-between text-[11px]">
                            <span class="text-slate-500">Jogos:</span>
                            <span class="text-slate-300 font-semibold">{{ $camp['total_jogos'] }}</span>
                        </div>
                        <div class="flex justify-between text-[11px]">
                            <span class="text-slate-500">Winrate:</span>
                            <span class="font-bold {{ $camp['winrate'] >= 55 ? 'text-emerald-400' : ($camp['winrate'] < 50 ? 'text-red-400' : 'text-amber-400') }}">
                                {{ $camp['winrate'] }}%
                            </span>
                        </div>
                        <div class="flex justify-between text-[11px] pt-1 mt-1 border-t border-slate-900">
                            <span class="text-slate-500">KDA:</span>
                            <span class="text-indigo-400 font-bold">{{ $camp['kda'] }}</span>
                        </div>
                        <div class="text-[9px] text-slate-600 text-center pt-0.5">
                            {{ $camp['avg_kills'] }}/{{ $camp['avg_deaths'] }}/{{ $camp['avg_assists'] }}
                        </div>
                    </div>
                </div>
            @empty
                <p class="col-span-full text-xs text-slate-500 italic py-4">Sincronize as contas acima para alimentar o relatório consolidado de campeões.</p>
            @endforelse
        </div>
    </div>

    <div class="mt-6 pt-4 border-t border-slate-800/40 flex justify-between items-center text-[10px] text-slate-600">
        <span>Dados processados via Elo Engine unificada</span>
        <span>Corte GM Estimado (BR): <strong class="text-rose-400 font-bold">{{ !empty($gmCutoff) ? $gmCutoff . ' LP' : '---' }}</strong></span>
    </div>
</div>

@endsection