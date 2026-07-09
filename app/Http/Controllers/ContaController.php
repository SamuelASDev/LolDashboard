<?php

namespace App\Http\Controllers;

use App\Models\Conta;
use Illuminate\Http\Request;
use App\Services\RiotService;

class ContaController extends Controller
{
    /**
     * Exibe o Dashboard com as contas APENAS do usuário logado.
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

        // Resumo Geral baseado apenas nas contas dele
        $totalWins   = $contas->sum('wins');
        $totalLosses = $contas->sum('losses');
        $totalGames  = $totalWins + $totalLosses;

        $avgWinrate = $totalGames > 0 ? round(($totalWins / $totalGames) * 100, 1) : 0;

        // Cutoff mais recente armazenado
        $gmCutoff = auth()->user()->contas()->whereNotNull('cutoff_lp')
            ->orderBy('updated_at', 'desc')
            ->value('cutoff_lp') ?? 0;

        return view('dashboard', compact('contas', 'totalWins', 'totalLosses', 'totalGames', 'avgWinrate', 'gmCutoff'));
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

        // Vincula automaticamente usando o relacionamento do Usuário
        auth()->user()->contas()->create([
            'nick' => $request->nick,
            'riot_id' => $request->riot_id,
            'regiao' => $request->regiao,
        ]);

        return redirect()->route('dashboard')->with('success', 'Conta adicionada com sucesso!');
    }

    /**
     * Sincroniza uma conta específica (Verifica se ela pertence ao usuário)
     */
    public function sync($id, RiotService $riot)
    {
        // Garante que o usuário só atualize uma conta que seja DELE
        $conta = auth()->user()->contas()->findOrFail($id);

        if (!str_contains($conta->riot_id, '#')) {
            return back()->with('error', 'Riot ID inválido. Deve conter #TAG');
        }

        [$gameName, $tagLine] = explode('#', $conta->riot_id);

        $account = $riot->getAccount($gameName, $tagLine);
        if (!$account) return back()->with('error', 'Perfil da Riot não encontrado.');

        $puuid = $account['puuid'];
        $league = $riot->getRankByPuuid($puuid, $conta->regiao);

        $rankedSolo = collect($league)->firstWhere('queueType', 'RANKED_SOLO_5x5');

        if ($rankedSolo) {
            $tier = $rankedSolo['tier'];
            $wins = $rankedSolo['wins'];
            $losses = $rankedSolo['losses'];

            $conta->elo = $tier . ' ' . $rankedSolo['rank'];
            $conta->pdl = $rankedSolo['leaguePoints'];
            $conta->wins = $wins;
            $conta->losses = $losses;
            $conta->winrate = round(($wins / ($wins + $losses)) * 100, 1);

            // 🔥 NOVO: Lógica de Busca de Decay para Elo Master+
            if (in_array($tier, ['MASTER', 'GRANDMASTER', 'CHALLENGER'])) {
                // Busca o ID do último jogo SoloQ
                $lastMatchId = $riot->getLastMatchId($puuid);
                
                if ($lastMatchId) {
                    // Busca o timestamp do fim do jogo
                    $timestampMs = $riot->getMatchTimestamp($lastMatchId);
                    
                    if ($timestampMs) {
                        // Converte milissegundos da Riot para data legível no banco
                        $conta->last_game_at = \Carbon\Carbon::createFromTimestampMs($timestampMs);
                    }
                }

                $cutoff = $riot->getGrandmasterCutoff();
                if ($cutoff) $conta->cutoff_lp = $cutoff;
            } else {
                // Limpa o campo se ele caiu de elo e não tem mais decay
                $conta->last_game_at = null; 
            }
        } else {
            $conta->elo = 'UNRANKED';
        }

        $conta->save();
        return back()->with('success', 'Conta sincronizada!');
    }

    /**
     * Sincroniza todas as contas do usuário logado
     */
    public function syncAll(RiotService $riot)
    {
        $contas = auth()->user()->contas;

        foreach ($contas as $conta) {
            if (!str_contains($conta->riot_id, '#')) continue;

            [$gameName, $tagLine] = explode('#', $conta->riot_id);
            $account = $riot->getAccount($gameName, $tagLine);
            if (!$account) continue;

            $puuid = $account['puuid'];
            $league = $riot->getRankByPuuid($puuid, $conta->regiao);
            
            $rankedSolo = collect($league)->firstWhere('queueType', 'RANKED_SOLO_5x5');

            if ($rankedSolo) {
                $tier = $rankedSolo['tier'];
                $wins = $rankedSolo['wins'];
                $losses = $rankedSolo['losses'];

                $conta->elo = $tier . ' ' . $rankedSolo['rank'];
                $conta->pdl = $rankedSolo['leaguePoints'];
                $conta->wins = $wins;
                $conta->losses = $losses;
                $conta->winrate = round(($wins / ($wins + $losses)) * 100, 1);

                // 🔥 NOVO: Lógica de Busca de Decay para Elo Master+
                if (in_array($tier, ['MASTER', 'GRANDMASTER', 'CHALLENGER'])) {
                    // Busca o ID do último jogo SoloQ
                    $lastMatchId = $riot->getLastMatchId($puuid);
                    
                    if ($lastMatchId) {
                        // Busca o timestamp do fim do jogo
                        $timestampMs = $riot->getMatchTimestamp($lastMatchId);
                        
                        if ($timestampMs) {
                            // Converte milissegundos da Riot para data legível no banco
                            $conta->last_game_at = \Carbon\Carbon::createFromTimestampMs($timestampMs);
                        }
                    }

                    $cutoff = $riot->getGrandmasterCutoff();
                    if ($cutoff) $conta->cutoff_lp = $cutoff;
                } else {
                    // Limpa o campo se ele caiu de elo e não tem mais decay
                    $conta->last_game_at = null; 
                }
            }
            $conta->save();
        }

        return back()->with('success', 'Todas as suas contas foram atualizadas!');
    }

    /**
     * Mostra o formulário para editar a conta (apenas se for dono).
     */
    public function edit($id)
    {
        // 🔒 Segurança: findOrFail garante que se não for do usuário logado, dá erro 404
        $conta = auth()->user()->contas()->findOrFail($id);

        return view('contas.edit', compact('conta'));
    }

    /**
     * Atualiza as informações básicas da conta.
     */
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

        return redirect()->route('dashboard')->with('success', 'Conta atualizada com sucesso!');
    }

    /**
     * Remove a conta do banco de dados.
     */
    public function destroy($id)
    {
        $conta = auth()->user()->contas()->findOrFail($id);
        $conta->delete();

        return redirect()->route('dashboard')->with('success', 'Conta removida com sucesso!');
    }
}