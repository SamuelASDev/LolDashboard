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
     * Exibe a página inicial do Dashboard unificado.
     */
    public function index(RiotService $riot)
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

        // Resumo Geral baseado apenas nas contas dele
        $totalWins   = $contas->sum('wins');
        $totalLosses = $contas->sum('losses');
        $totalGames  = $totalWins + $totalLosses;
        $avgWinrate  = $totalGames > 0 ? round(($totalWins / $totalGames) * 100, 1) : 0;

        // Cutoff do Grão-Mestre mais recente armazenado no banco
        $gmCutoff = auth()->user()->contas()->whereNotNull('cutoff_lp')
            ->orderBy('updated_at', 'desc')
            ->value('cutoff_lp');

        // 🔥 SE O BANCO ESTIVER ZERADO: Aciona o Scraper do seu service ao vivo para a barra carregar certa!
        if (empty($gmCutoff)) {
            $gmCutoff = $riot->getGrandmasterCutoff() ?? 600; // Fallback de segurança se o site cair
        }

        // Estatísticas Globais de OP.GG baseadas nas partidas gravadas
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

        // Agrupamento consolidado de todos os campeões
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
            ->sortByDesc('total_jogos');

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

    /**
     * Exibe a página de detalhes profundos de uma única conta específica.
     */
    public function show($id)
    {
        $conta = auth()->user()->contas()->findOrFail($id);
        $partidas = $conta->partidas()->orderByDesc('played_at')->get();

        $totalGames = $partidas->count();
        $totalWins = $partidas->where('win', true)->count();
        $totalLosses = $totalGames - $totalWins;
        $winrate = $totalGames > 0 ? round(($totalWins / $totalGames) * 100, 1) : 0;

        $totalKills = $partidas->sum('kills');
        $totalDeaths = $partidas->sum('deaths');
        $totalAssists = $partidas->sum('assists');

        $kda = $totalDeaths > 0 ? round(($totalKills + $totalAssists) / $totalDeaths, 2) : ($totalKills + $totalAssists);

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

    public function create()
    {
        return view('contas.create');
    }

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
     * Sincroniza do mais recente para o mais antigo de forma cadenciada com proteção concorrente.
     */
    public function sync($id, RiotService $riot, $isCalledFromSyncAll = false)
    {   
        set_time_limit(0);

        // Chave de sessão isolada por ID de usuário para não misturar acessos paralelos
        $userSessionKey = 'global_gm_cutoff_' . auth()->id();

        if (!$isCalledFromSyncAll) {
            // Desativa buffers para forçar saída contínua sem cache
            if (ob_get_level() == 0) ob_start();
            header('Content-Type: text/html; charset=utf-8');
            header('Cache-Control: no-cache, must-revalidate');
            echo "<pre style='background:#0f172a;color:#38bdf8;padding:20px;font-family:monospace;border-radius:12px;line-height:1.6;max-height:85vh;overflow-y:auto;' id='console-matrix'>";
            echo "🚀 [SYNC] Iniciando caçada reversa estável (Recentes -> Antigas)...\n";
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
            $totalRankedGames = $wins + $losses;

            $conta->elo = $tier . ' ' . $rankedSolo['rank'];
            $conta->pdl = $rankedSolo['leaguePoints'];
            $conta->wins = $wins;
            $conta->losses = $losses;
            $conta->winrate = round(($wins / ($wins + $losses)) * 100, 1);

            // Decay Master+ e aplicação do Corte do Grandmaster
            if (in_array($tier, ['MASTER', 'GRANDMASTER', 'CHALLENGER'])) {
                $lastMatchId = $riot->getLastMatchId($puuid);
                if ($lastMatchId) {
                    $timestampMs = $riot->getMatchTimestamp($lastMatchId);
                    if ($timestampMs) $conta->last_game_at = Carbon::createFromTimestampMs($timestampMs);
                }
                
                // 🔒 ISOLADO: Confere a sessão baseada estritamente no ID deste usuário
                if (session()->has($userSessionKey)) {
                    $conta->cutoff_lp = session($userSessionKey);
                } else {
                    $cutoff = $riot->getGrandmasterCutoff();
                    if ($cutoff) {
                        $conta->cutoff_lp = $cutoff;
                    }
                }
            } else {
                $conta->last_game_at = null; 
            }

            echo "👤 Invocador: <strong style='color:#10b981;'>{$conta->riot_id}</strong>\n";
            echo "📊 Alvo estrito: {$totalRankedGames} jogos válidos no Elo principal.\n";
            echo "📥 Coletando lista sequencial de partidas da Riot...\n";
            echo "<script>var c = document.getElementById('console-matrix'); if(c) c.scrollTop = c.scrollHeight;</script>";
            ob_flush(); flush();

            try {
                $start = 0;
                $countPerPage = 100;
                $allMatchIds = [];

                while ($start < $totalRankedGames + 60) {
                    $matchIds = $riot->getRecentMatchIds($puuid, $countPerPage, $start);
                    if (empty($matchIds)) break;

                    $allMatchIds = array_merge($allMatchIds, $matchIds);
                    $start += $countPerPage;
                    usleep(rand(400000, 800000)); // Jitter leve na paginação
                }

                $totalColetado = count($allMatchIds);
                echo "✅ Estruturados " . $totalColetado . " IDs de histórico corrido. Analisando...\n";
                echo "---------------------------------------------------------\n";
                echo "<script>var c = document.getElementById('console-matrix'); if(c) c.scrollTop = c.scrollHeight;</script>";
                ob_flush(); flush();

                $validasNoBanco = Partida::where('conta_id', $conta->id)->count();
                echo "📊 O banco de dados possui atualmente [{$validasNoBanco}/{$totalRankedGames}] jogos válidos guardados.\n";
                echo "<script>var c = document.getElementById('console-matrix'); if(c) c.scrollTop = c.scrollHeight;</script>";
                ob_flush(); flush();

                $inseridos = 0;
                foreach ($allMatchIds as $index => $matchId) {
                    $numAtual = $index + 1;

                    if ($validasNoBanco >= $totalRankedGames) {
                        echo "🎯 Meta atingida! O banco possui exatamente {$validasNoBanco} jogos correspondendo ao elo. Finalizando laço.\n";
                        echo "<script>var c = document.getElementById('console-matrix'); if(c) c.scrollTop = c.scrollHeight;</script>";
                        break;
                    }

                    // 🔥 TOPO ABSOLUTO: Pula direto antes do jitter de delay se já existir combinando match_id + conta_id!
                    // Isso acelera o script em 1000% para os jogos velhos que já estão salvos.
                    if (Partida::where('match_id', $matchId)->where('conta_id', $conta->id)->exists()) {
                        echo "[{$numAtual}/{$totalColetado}] 🟢 Partida {$matchId} já está guardada. Avançando...\n";
                        echo "<script>var c = document.getElementById('console-matrix'); if(c) c.scrollTop = c.scrollHeight;</script>";
                        ob_flush(); flush();
                        continue;
                    }

                    // 🕒 JITTER ANTI RATE-LIMIT: Só entra nessa pausa de 1s se for um jogo novo que vai ser baixado
                    usleep(rand(1000000, 1600000));

                    $matchData = null;
                    $tentativa = 1;
                    $waitTime = 5;

                    while (!$matchData) {
                        $matchData = $riot->getMatchDetail($matchId);
                        
                        if (!$matchData) {
                            echo "[{$numAtual}/{$totalColetado}] ⚠️ [Tentativa {$tentativa}] Bloqueio temporário (Rate Limit). Aguardando {$waitTime} segundos para tentar de novo...\n";
                            echo "<script>var c = document.getElementById('console-matrix'); if(c) c.scrollTop = c.scrollHeight;</script>";
                            ob_flush(); flush();
                            
                            sleep($waitTime); 
                            $tentativa++;
                            $waitTime = min(30, $waitTime + 5);
                        }
                    }

                    $queueId = $matchData['info']['queueId'] ?? 0;
                    if ($queueId !== 420) {
                        echo "[{$numAtual}/{$totalColetado}] 🚫 Ignorada: Outra Fila (Queue ID: {$queueId}).\n";
                        echo "<script>var c = document.getElementById('console-matrix'); if(c) c.scrollTop = c.scrollHeight;</script>";
                        ob_flush(); flush();
                        continue;
                    }

                    $participant = collect($matchData['info']['participants'] ?? [])->firstWhere('puuid', $puuid);

                    if ($participant) {
                        $isEarlySurrender = $participant['gameEndedInEarlySurrender'] ?? false;

                        if ($isEarlySurrender === true) {
                            echo "[{$numAtual}/{$totalColetado}] 🚫 Ignorada: Partida detectada como Remake/Anulada.\n";
                            echo "<script>var c = document.getElementById('console-matrix'); if(c) c.scrollTop = c.scrollHeight;</script>";
                            ob_flush(); flush();
                            continue;
                        }

                        // 🔥 CHAVE COMPOSTA PROTEGIDA: Inserção com firstOrCreate segura contra concorrência múltipla
                        $partidaExistente = Partida::firstOrCreate(
                            [
                                'match_id' => $matchId,
                                'conta_id' => $conta->id
                            ],
                            [
                                'champion_name' => $participant['championName'],
                                'win'           => $participant['win'],
                                'kills'         => $participant['kills'],
                                'deaths'        => $participant['deaths'],
                                'assists'       => $participant['assists'],
                                'lane'          => $participant['individualPosition'] !== 'Invalid' ? $participant['individualPosition'] : 'UTILITY',
                                'played_at'     => Carbon::createFromTimestampMs($matchData['info']['gameEndTimestamp']),
                            ]
                        );

                        if ($partidaExistente->wasRecentlyCreated) {
                            $inseridos++;
                            $validasNoBanco++;
                            echo "[{$numAtual}/{$totalColetado}] 🔥 SALVO -> Campeão: <strong style='color:#f59e0b;'>{$participant['championName']}</strong> (" . ($participant['win'] ? 'Vitória' : 'Derrota') . ") [Total Válidas: {$validasNoBanco}/{$totalRankedGames}]\n";
                        } else {
                            echo "[{$numAtual}/{$totalColetado}] 🟢 Partida {$matchId} já constava no banco de dados. Avançando...\n";
                        }

                        echo "<script>var c = document.getElementById('console-matrix'); if(c) c.scrollTop = c.scrollHeight;</script>";
                        ob_flush(); flush();
                    }
                }

                $conta->save();
                echo "---------------------------------------------------------\n";
                echo "✨ Conta [{$conta->riot_id}] sincronizada! +{$inseridos} novos jogos adicionados.\n\n";
                echo "<script>var c = document.getElementById('console-matrix'); if(c) c.scrollTop = c.scrollHeight;</script>";
                ob_flush(); flush();

            } catch (\Exception $e) {
                echo "❌ Erro no histórico de {$conta->riot_id}: " . $e->getMessage() . "\n\n";
                echo "<script>var c = document.getElementById('console-matrix'); if(c) c.scrollTop = c.scrollHeight;</script>";
                ob_flush(); flush();
            }
        } else {
            $conta->elo = 'UNRANKED';
            $conta->save();
            echo "👤 Invocador [{$conta->riot_id}] está UNRANKED.\n\n";
            ob_flush(); flush();
        }

        if (!$isCalledFromSyncAll) {
            echo "🔄 Redirecionando de volta ao dashboard em 3 segundos...\n";
            echo "</pre>"; ob_flush(); flush();
            sleep(3);
            echo "<script>window.location.href='" . route('dashboard') . "';</script>";
        }
    }

    /**
     * Sincroniza todas as contas isolando as chaves por ID de sessão de usuário logado.
     */
    public function syncAll(RiotService $riot)
    {   
        set_time_limit(0);

        $userSessionKey = 'global_gm_cutoff_' . auth()->id();

        if (ob_get_level() == 0) ob_start();
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        echo "<pre style='background:#020617;color:#10b981;padding:20px;font-family:monospace;border-radius:12px;line-height:1.6;' id='console-matrix'>";
        echo "🌐 [SYNC ALL GLOBAL] Iniciando fila sequencial de atualização...\n";
        echo "====================================================================\n";
        ob_flush(); flush();

        echo "🕵️‍♂️ [SCRAPER] Consultando corte atualizado do Grandmaster (BR)...\n";
        ob_flush(); flush();
        $globalCutoff = $riot->getGrandmasterCutoff();
        
        if ($globalCutoff) {
            echo "🎯 [SCRAPER] Sucesso! Corte detectado em: <strong style='color:#f43f5e;'>{$globalCutoff} LP</strong>\n";
            session([$userSessionKey => $globalCutoff]);
        } else {
            echo "⚠️ [SCRAPER] Falha ao raspar replays.lol. Usando fallback de segurança (600 LP).\n";
            session([$userSessionKey => 600]);
        }
        echo "====================================================================\n";
        ob_flush(); flush();

        $contas = auth()->user()->contas;
        $totalContas = count($contas);

        foreach ($contas as $index => $conta) {
            $num = $index + 1;
            echo "✨ [Fila {$num}/{$totalContas}] Carregando engine para o perfil {$conta->riot_id}...\n";
            echo "<script>var c = document.getElementById('console-matrix'); if(c) c.scrollTop = c.scrollHeight;</script>";
            ob_flush(); flush();

            $this->sync($conta->id, $riot, true);
        }

        session()->forget($userSessionKey);

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