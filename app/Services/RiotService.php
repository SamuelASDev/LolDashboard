<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class RiotService
{
    private $apiKey;

    public function __construct()
    {
        // Certifique-se de colocar RIOT_KEY=sua_chave no seu .env
        $this->apiKey = env('RIOT_KEY');
    }

    // ✅ PASSO 1 — Riot ID (Nome#TAG) → PUUID
    public function getAccount($gameName, $tagLine)
    {
        $response = Http::get(
            "https://americas.api.riotgames.com/riot/account/v1/accounts/by-riot-id/$gameName/$tagLine",
            [
                'api_key' => $this->apiKey
            ]
        );

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }

    // ✅ PASSO 2 — PUUID → RANK
    public function getRankByPuuid($puuid, $regiao = 'br1')
    {
        // Usamos a região dinâmica que o usuário escolheu no cadastro
        $response = Http::get(
            "https://{$regiao}.api.riotgames.com/lol/league/v4/entries/by-puuid/$puuid",
            [
                'api_key' => $this->apiKey
            ]
        );

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }

    // ✅ PASSO 3 — Scraper do Cutoff do Grandmaster
    public function getGrandmasterCutoff()
    {
        $response = Http::get(
            'https://www.replays.lol/cutoff/BR/grandmaster'
        );

        $html = $response->body();

        // ✅ remove comentários HTML do React
        $html = preg_replace('/<!--.*?-->/', '', $html);

        // ✅ agora captura o número corretamente
        preg_match('/(\d+)\s*LP/i', $html, $matches);

        return isset($matches[1]) ? (int)$matches[1] : null;
    }

    // ✅ PASSO 4 — PUUID → Pegar ID do último jogo Ranqueado Solo (Queue 420)
    public function getLastMatchId($puuid)
    {
        // A Match API usa a rota regional 'americas', indepedente de ser BR, NA, etc.
        $response = Http::get(
            "https://americas.api.riotgames.com/lol/match/v5/matches/by-puuid/{$puuid}/ids",
            [
                'api_key' => $this->apiKey,
                'queue' => 420, // 420 = Ranked Solo/Duo
                'start' => 0,
                'count' => 1
            ]
        );

        if ($response->failed() || empty($response->json())) {
            return null;
        }

        return $response->json()[0]; // Retorna o ID da partida mais recente
    }

    // ✅ PASSO 5 — Match ID → Pegar a data de finalização do jogo
    public function getMatchTimestamp($matchId)
    {
        $response = Http::get(
            "https://americas.api.riotgames.com/lol/match/v5/matches/{$matchId}",
            ['api_key' => $this->apiKey]
        );

        if ($response->failed()) {
            return null;
        }

        $data = $response->json();
        
        // Retorna o timestamp em milissegundos de quando o jogo terminou
        return $data['info']['gameEndTimestamp'] ?? null;
    }

}