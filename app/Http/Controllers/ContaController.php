<?php

namespace App\Http\Controllers;

use App\Models\Conta;
use App\Models\Partida;
use Illuminate\Http\Request;
use App\Services\RiotService;
use Carbon\Carbon;

class ContaController extends Controller
{

    /**
     * Exibe a página de detalhes profundos de uma única conta específica.
     */
    public function show($id)
    {
        // Garante a segurança pegando apenas contas vinculadas ao usuário logado
        $conta = auth()->user()->contas()->findOrFail($id);

        // Busca todas as partidas registradas no banco para esta conta específica
        $partidas = $conta->partidas()->orderByDesc('played_at')->get();

        $totalGames = $partidas->count();
        $totalWins = $partidas->where('win', true)->count();
        $totalLosses = $totalGames - $totalWins;
        $winrate = $totalGames > 0 ? round(($totalWins / $totalGames) * 100, 1) : 0;

        $totalKills = $partidas->sum('kills');
        $totalDeaths = $partidas->sum('deaths');
        $totalAssists = $partidas->sum('assists');

        $kda = $totalDeaths > 0 
            ? round(($totalKills + $totalAssists) / $totalDeaths, 2) 
            : ($totalKills + $totalAssists);

        // Agrupa e calcula as estatísticas de TODOS os campeões jogados nessa conta isolada
        $campeoesConta = $partidas->groupBy('champion_name')
            ->map(function ($jogos) {
                $total = $jogos->count();
                $vitorias = $jogos->where('win', true)->count();
                $kills = $jogos->sum('kills');
                $deaths = $jogos->sum('deaths');
                $assists = $jogos->sum('assists');

                return [
                    'champion_name' => $jogos->first()->champion_name,
                    'total_jogos' => $total,
                    'winrate' => round(($vitorias / $total) * 100, 1),
                    'kda' => $deaths > 0 ? round(($kills + $assists) / $deaths, 2) : ($kills + $assists),
                    'avg_kills' => round($kills / $total, 1),
                    'avg_deaths' => round($deaths / $total, 1),
                    'avg_assists' => round($assists / $total, 1),
                ];
            })
            ->sortByDesc('total_jogos');

        return view('contas.show', compact('conta', 'partidas', 'totalGames', 'totalWins', 'totalLosses', 'winrate', 'kda', 'campeoesConta'));
    }
    /**
     * Exibe o Dashboard com as contas APENAS do usuário logado e os dados agregados de OP.GG.
     */
    public function index()
    {
        // 🔒 Segurança: Pega apenas as contas vinculadas ao ID do usuário logado
        $contas = auth()->user()->contas()
            ->orderByRaw("
                CASE
                    WHEN elo LIKE 'CHALLENGER%' THEN 9
                    WHEN elo LIKE 'GRANDMASTER%' THEN 8
                    WHEN elo LIKE 'MASTER%' THEN 7
                    WHEN elo LIKE 'DIAMOND%' THEN 6
                    WHEN elo LIKE 'EMERALD%' THEN 5
                    WHEN elo LIKE 'PLATINUM%' THEN 4
                    WHEN elo LIKE 'GOLD%' THEN 3
                    WHEN elo LIKE 'SILVER%' THEN 2
                    WHEN elo LIKE 'BRONZE%' THEN 1
                    WHEN elo LIKE 'IRON%' THEN 0
                    ELSE -1
                END DESC
            ")
            ->orderByRaw("
                CASE
                    WHEN elo LIKE '% I' THEN 1
                    WHEN elo LIKE '% II' THEN 2
                    WHEN elo LIKE '% III' THEN 3
                    WHEN elo LIKE '% IV' THEN 4
                    ELSE 5
                END ASC
            ")
            ->orderBy('pdl', 'DESC')
            ->get();

        // Resumo Geral baseado apenas nas contas dele (pelo elo das contas)
        $totalWins   = $contas->sum('wins');
        $totalLosses = $contas->sum('losses');
        $totalGames  = $totalWins + $totalLosses;
        $avgWinrate  = $totalGames > 0 ? round(($totalWins / $totalGames) * 100, 1) : 0;

        // Cutoff do Grão-Mestre mais recente armazenado
        $gmCutoff = auth()->user()->contas()->whereNotNull('cutoff_lp')
            ->orderBy('updated_at', 'desc')
            ->value('cutoff_lp') ?? 0;

        // 🔥 Estatísticas Globais de OP.GG (Calculado com base nas partidas gravadas)
        $contasIds = $contas->pluck('id');
        $partidasGlobais = Partida::whereIn('conta_id', $contasIds)->get();

        $totalGeralJogos = $partidasGlobais->count();
        $totalGeralVitorias = $partidasGlobais->where('win', true)->count();
        
        $globalWinrate = $totalGeralJogos > 0 
            ? round(($totalGeralVitorias / $totalGeralJogos) * 100, 1) 
            : 0;

        $totalKills = $partidasGlobais->sum('kills');
        $totalDeaths = $partidasGlobais->sum('deaths');
        $totalAssists = $partidasGlobais->sum('assists');

        $globalKDA = $totalDeaths > 0 
            ? round(($totalKills + $totalAssists) / $totalDeaths, 2) 
            : ($totalKills + $totalAssists);

        // 🔥 ATUALIZADO: Agrupamento consolidado de TODOS os campeões (Sem o ->take(5))
        $campeoesGlobais = $partidasGlobais->groupBy('champion_name')
            ->map(function ($jogos) {
                $total = $jogos->count();
                $vitorias = $jogos->where('win', true)->count();
                $kills = $jogos->sum('kills');
                $deaths = $jogos->sum('deaths');
                $assists = $jogos->sum('assists');

                return [
                    'champion_name' => $jogos->first()->champion_name,
                    'total_jogos' => $total,
                    'winrate' => round(($vitorias / $total) * 100, 1),
                    'kda' => $deaths > 0 ? round(($kills + $assists) / $deaths, 2) : ($kills + $assists),
                    'avg_kills' => round($kills / $total, 1),
                    'avg_deaths' => round($deaths / $total, 1),
                    'avg_assists' => round($assists / $total, 1),
                ];
            })
            ->sortByDesc('total_jogos'); // Mantém ordenado do mais jogado para o menos jogado

        return view('dashboard', compact(
            'contas', 
            'totalWins', 
            'totalLosses', 
            'totalGames', 
            'avgWinrate', 
            'gmCutoff',
            'totalGeralJogos',
            'globalWinrate',
            'globalKDA',
            'campeoesGlobais'
        ));
    }

    public function create()
    {
        return view('contas.create');
    }

    /**
     * Salva a conta vinculando-a ao usuário logado (Sem senha!)
     */
    public function store(Request $request)
    {
        $request->validate([
            'nick' => 'required|string',
            'riot_id' => 'required|string',
            'regiao' => 'required|string',
        ]);

        auth()->user()->contas()->create([
            'nick' => $request->nick,
            'riot_id' => $request->riot_id,
            'regiao' => $request->regiao,
        ]);

        return redirect()->route('dashboard')->with('success', 'Conta adicionada com sucesso!');
    }
    /**
     * Sincroniza do mais recente para o mais antigo até atingir a meta exata de jogos válidos da conta,
     * forçando retentativas infinitas caso a API da Riot falhe temporariamente.
     */
    public function sync($id, RiotService $riot, $isCalledFromSyncAll = false)
    {   
        set_time_limit(0);

        // Prepara a tela para enviar textos em tempo real apenas se não veio do syncAll para não duplicar cabeçalhos
        if (!$isCalledFromSyncAll) {
            if (ob_get_level() == 0) ob_start();
            echo "<pre style='background:#0f172a;color:#38bdf8;padding:20px;font-family:monospace;border-radius:12px;line-height:1.6;'>";
            echo "🚀 [SYNC] Iniciando caçada reversa resiliente (Recentes -> Antigas)...\n";
            ob_flush(); flush();
        }
        
        $conta = auth()->user()->contas()->findOrFail($id);

        if (!str_contains($conta->riot_id, '#')) {
            echo "❌ Erro: Riot ID inválido.\n"; ob_flush(); flush();
            return;
        }

        [$gameName, $tagLine] = explode('#', $conta->riot_id);
        $account = $riot->getAccount($gameName, $tagLine);
        
        if (!$account) {
            echo "❌ Erro: Conta não encontrada na Riot.\n"; ob_flush(); flush();
            return;
        }

        $puuid = $account['puuid'];
        $league = $riot->getRankByPuuid($puuid, $conta->regiao);
        $rankedSolo = collect($league)->firstWhere('queueType', 'RANKED_SOLO_5x5');

        if ($rankedSolo) {
            $tier = $rankedSolo['tier'];
            $wins = $rankedSolo['wins'];
            $losses = $rankedSolo['losses'];
            $totalRankedGames = $wins + $losses; // 🎯 Nossa meta estrita de jogos válidos

            $conta->elo = $tier . ' ' . $rankedSolo['rank'];
            $conta->pdl = $rankedSolo['leaguePoints'];
            $conta->wins = $wins;
            $conta->losses = $losses;
            $conta->winrate = round(($wins / ($wins + $losses)) * 100, 1);

            // Decay Master+
            if (in_array($tier, ['MASTER', 'GRANDMASTER', 'CHALLENGER'])) {
                $lastMatchId = $riot->getLastMatchId($puuid);
                if ($lastMatchId) {
                    $timestampMs = $riot->getMatchTimestamp($lastMatchId);
                    if ($timestampMs) $conta->last_game_at = Carbon::createFromTimestampMs($timestampMs);
                }
                $cutoff = $riot->getGrandmasterCutoff();
                if ($cutoff) $conta->cutoff_lp = $cutoff;
            } else {
                $conta->last_game_at = null; 
            }

            echo "👤 Invocador: <strong style='color:#10b981;'>{$conta->riot_id}</strong>\n";
            echo "📊 Alvo estrito: {$totalRankedGames} jogos válidos no Elo principal.\n";
            echo "📥 Coletando lista sequencial de partidas da Riot...\n";
            ob_flush(); flush();

            try {
                $start = 0;
                $countPerPage = 100;
                $allMatchIds = [];

                // Recolhe os códigos ordenados por padrão da Riot (Mais novas primeiro)
                while ($start < $totalRankedGames + 60) { // Margem extra para cobrir os pulos de remakes
                    $matchIds = $riot->getRecentMatchIds($puuid, $countPerPage, $start);
                    if (empty($matchIds)) break;

                    $allMatchIds = array_merge($allMatchIds, $matchIds);
                    $start += $countPerPage;
                }

                $totalColetado = count($allMatchIds);
                echo "✅ Estruturados " . $totalColetado . " IDs de histórico corrido. Analisando...\n";
                echo "---------------------------------------------------------\n";
                ob_flush(); flush();

                // Verifica quantas partidas VÁLIDAS já temos salvas no banco de dados para essa conta
                $validasNoBanco = Partida::where('conta_id', $conta->id)->count();
                echo "📊 O banco de dados possui atualmente [{$validasNoBanco}/{$totalRankedGames}] jogos válidos guardados.\n";
                ob_flush(); flush();

                $inseridos = 0;
                foreach ($allMatchIds as $index => $matchId) {
                    $numAtual = $index + 1;

                    // 🛑 CONDIÇÃO DE PARADA: Se já atingimos a meta perfeita de jogos da season, encerra na hora!
                    if ($validasNoBanco >= $totalRankedGames) {
                        echo "🎯 Meta atingida! O banco possui exatamente {$validasNoBanco} jogos correspondendo ao elo. Finalizando laço.\n";
                        break;
                    }

                    // Se a partida já existe, ela conta como válida no banco. Pula a chamada de detalhes (economiza API)
                    if (Partida::where('match_id', $matchId)->where('conta_id', $conta->id)->exists()) {
                        echo "[{$numAtual}/{$totalColetado}] 🟢 Partida {$matchId} já está guardada. Avançando...\n";
                        ob_flush(); flush();
                        continue;
                    }

                    // 🔥 MECANISMO FORÇADO DE RETENTATIVA INFINITA (Insiste até coletar)
                    $matchData = null;
                    $tentativa = 1;

                    while (!$matchData) {
                        $matchData = $riot->getMatchDetail($matchId);
                        
                        if (!$matchData) {
                            // Se falhar (Instabilidade da Riot ou Rate Limit), cospe o aviso e espera para tentar de novo
                            echo "[{$numAtual}/{$totalColetado}] ⚠️ [Tentativa {$tentativa}] Falha ao ler detalhes da partida {$matchId}. API sobrecarregada? Aguardando 5 segundos para re-tentar...\n";
                            ob_flush(); flush();
                            
                            sleep(5); // Segura 5 segundos para a API respirar antes do próximo tiro
                            $tentativa++;
                        }
                    }

                    // 🔒 TRAVA 1: Valida se a fila é estritamente SoloQ (Queue 420)
                    $queueId = $matchData['info']['queueId'] ?? 0;
                    if ($queueId !== 420) {
                        echo "[{$numAtual}/{$totalColetado}] 🚫 Ignorada: Outra Fila (Queue ID: {$queueId}).\n";
                        ob_flush(); flush();
                        continue;
                    }

                    $participant = collect($matchData['info']['participants'] ?? [])->firstWhere('puuid', $puuid);

                    if ($participant) {
                        // 🔒 TRAVAS DE REMAKE: gameDuration < 5 min OU Early Surrender OU eligibleForProgression falso
                        $duration = $matchData['info']['gameDuration'] ?? 0;
                        $isEarlySurrender = $participant['gameEndedInEarlySurrender'] ?? false;
                        $eligibleForProgression = $participant['eligibleForProgression'] ?? true;

                        if ($duration < 300 || $isEarlySurrender === true || $eligibleForProgression === false) {
                            echo "[{$numAtual}/{$totalColetado}] 🚫 Ignorada: Partida detectada como Remake/Anulada.\n";
                            ob_flush(); flush();
                            continue; // Descarta e não conta como válida
                        }

                        // Se passou nas travas, é uma partida legítima do elo
                        Partida::create([
                            'conta_id' => $conta->id,
                            'match_id' => $matchId,
                            'champion_name' => $participant['championName'],
                            'win' => $participant['win'],
                            'kills' => $participant['kills'],
                            'deaths' => $participant['deaths'],
                            'assists' => $participant['assists'],
                            'lane' => $participant['individualPosition'] !== 'Invalid' ? $participant['individualPosition'] : 'UTILITY',
                            'played_at' => Carbon::createFromTimestampMs($matchData['info']['gameEndTimestamp']),
                        ]);

                        $inseridos++;
                        $validasNoBanco++; // Registra o incremento na meta
                        echo "[{$numAtual}/{$totalColetado}] 🔥 SALVO -> Campeão: <strong style='color:#f59e0b;'>{$participant['championName']}</strong> (" . ($participant['win'] ? 'Vitória' : 'Derrota') . ") [Total Válidas: {$validasNoBanco}/{$totalRankedGames}]\n";
                        ob_flush(); flush();
                    }
                }

                $conta->save();
                echo "---------------------------------------------------------\n";
                echo "✨ Conta [{$conta->riot_id}] sincronizada! +{$inseridos} novos jogos adicionados.\n\n";
                ob_flush(); flush();

            } catch (\Exception $e) {
                echo "❌ Erro no histórico de {$conta->riot_id}: " . $e->getMessage() . "\n\n";
                ob_flush(); flush();
            }
        } else {
            $conta->elo = 'UNRANKED';
            $conta->save();
            echo "👤 Invocador [{$conta->riot_id}] está UNRANKED.\n\n";
            ob_flush(); flush();
        }

        // Se a chamada foi individual, faz o redirecionamento final da página aqui
        if (!$isCalledFromSyncAll) {
            echo "🔄 Redirecionando de volta ao dashboard em 3 segundos...\n";
            echo "</pre>"; ob_flush(); flush();
            sleep(3);
            echo "<script>window.location.href='" . route('dashboard') . "';</script>";
        }
    }

    /**
     * 🔥 PAPO RETO: O SyncAll apenas reaproveita a engine visual do Sync normal conta a conta
     */
    public function syncAll(RiotService $riot)
    {   
        set_time_limit(0);

        // Prepara a tela preta unificada da Matrix
        if (ob_get_level() == 0) ob_start();
        echo "<pre style='background:#020617;color:#10b981;padding:20px;font-family:monospace;border-radius:12px;line-height:1.6;'>";
        echo "🌐 [SYNC ALL GLOBAL] Iniciando fila sequencial de atualização...\n";
        echo "====================================================================\n";
        ob_flush(); flush();

        $contas = auth()->user()->contas;
        $totalContas = count($contas);

        foreach ($contas as $index => $conta) {
            $num = $index + 1;
            echo "✨ [Fila {$num}/{$totalContas}] Carregando engine para o perfil...\n";
            ob_flush(); flush();

            // Executa a lógica de caçada completa daquela conta específica passando 'true' no flag
            $this->sync($conta->id, $riot, true);
        }

        echo "====================================================================\n";
        echo "🎉 Sincronização geral de todas as contas concluída!\n";
        echo "🔄 Retornando ao dashboard em 3 segundos...\n";
        echo "</pre>";
        ob_flush(); flush();

        sleep(3);
        echo "<script>window.location.href='" . route('dashboard') . "';</script>";
        return;
    }

    public function edit($id)
    {
        $conta = auth()->user()->contas()->findOrFail($id);
        return view('contas.edit', compact('conta'));
    }

    public function update(Request $request, $id)
    {
        $conta = auth()->user()->contas()->findOrFail($id);

        $request->validate([
            'nick' => 'required|string',
            'riot_id' => 'required|string',
            'regiao' => 'required|string',
        ]);

        $conta->update([
            'nick' => $request->nick,
            'riot_id' => $request->riot_id,
            'regiao' => $request->regiao,
        ]);

        return redirect()->route('dashboard')->with('success', 'Conta actualizada com sucesso!');
    }

    public function destroy($id)
    {
        $conta = auth()->user()->contas()->findOrFail($id);
        $conta->delete();

        return redirect()->route('dashboard')->with('success', 'Conta removida com sucesso!');
    }
}