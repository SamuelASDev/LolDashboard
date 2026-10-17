<?php

namespace App\Services;

use App\Enums\RiotRegion;
use App\Exceptions\RiotException;
use App\Support\HistoryWindow;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

/** API boundary: fixed hosts, encoded paths, safe failures and shared request budget. */
class RiotService
{
    public function account(string $riotId, RiotRegion $region, ?string $puuid = null): array
    {
        if ($puuid) {
            $path = '/riot/account/v1/accounts/by-puuid/'.rawurlencode($puuid);
        } else {
            if (substr_count($riotId, '#') !== 1) {
                throw new RiotException('Riot ID inválido. Use Nome#TAG.');
            }
            [$name, $tag] = explode('#', $riotId, 2);
            $path = '/riot/account/v1/accounts/by-riot-id/'.rawurlencode($name).'/'.rawurlencode($tag);
        }
        $data = $this->get($region->routing(), $path);
        if (! is_string($data['puuid'] ?? null) || empty($data['puuid'])) {
            throw new RiotException('A Riot retornou uma identidade inválida.');
        }

        return $data;
    }

    public function rank(string $puuid, RiotRegion $region): ?array
    {
        $data = $this->get($region->value, '/lol/league/v4/entries/by-puuid/'.rawurlencode($puuid));
        if (! array_is_list($data)) {
            throw new RiotException('A Riot retornou um ranking inválido.');
        }
        foreach ($data as $entry) {
            if (! is_array($entry) || ! is_string($entry['queueType'] ?? null)) {
                throw new RiotException('A Riot retornou uma lista de ligas inválida.');
            }
        }
        $rank = collect($data)->firstWhere('queueType', 'RANKED_SOLO_5x5');
        if ($rank !== null) {
            foreach (['tier', 'rank', 'leaguePoints', 'wins', 'losses'] as $field) {
                if (! isset($rank[$field])) {
                    throw new RiotException('A Riot retornou um ranking incompleto.');
                }
            }
            if (! in_array($rank['tier'], ['IRON', 'BRONZE', 'SILVER', 'GOLD', 'PLATINUM', 'EMERALD', 'DIAMOND', 'MASTER', 'GRANDMASTER', 'CHALLENGER'], true)
                || ! in_array($rank['rank'], ['I', 'II', 'III', 'IV'], true)) {
                throw new RiotException('A Riot retornou uma classificação inválida.');
            }
            foreach (['leaguePoints', 'wins', 'losses'] as $field) {
                if (! is_int($rank[$field]) || $rank[$field] < 0) {
                    throw new RiotException('A Riot retornou valores de ranking inválidos.');
                }
            }
        }

        return $rank;
    }

    public function matchIds(string $puuid, RiotRegion $region, int $cursor, int $queueId = 420, ?array $window = null): array
    {
        $window ??= HistoryWindow::forRegion($region);
        $data = $this->get($region->routing(), '/lol/match/v5/matches/by-puuid/'.rawurlencode($puuid).'/ids',
            ['queue' => $queueId, 'start' => $cursor, 'count' => config('riot.page_size'),
                'startTime' => $window['start'], 'endTime' => $window['end']]);
        if (! array_is_list($data) || count(array_filter($data, 'is_string')) !== count($data)) {
            throw new RiotException('A Riot retornou uma lista de partidas inválida.');
        }

        return $data;
    }

    public function match(string $id, RiotRegion $region): array
    {
        return $this->get($region->routing(), '/lol/match/v5/matches/'.rawurlencode($id));
    }

    private function reserveBudget(): void
    {
        $key = 'riot:budget:'.hash('sha256', (string) config('riot.key'));
        $blockedUntil = Cache::get($key.':blocked', 0);
        if ($blockedUntil > time()) {
            throw new RiotException('Aguardando pausa compartilhada da Riot.', true, $blockedUntil - time(), true);
        }
        $lock = Cache::lock($key.':lock', 5);
        if (! $lock->get()) {
            throw new RiotException('Aguardando disponibilidade da API.', true, 2, true);
        }
        try {
            foreach ([':second' => [max(1, config('riot.requests_per_second')), 1],
                ':window' => [max(1, config('riot.requests_per_two_minutes')), 120]] as $suffix => [$limit, $seconds]) {
                if (RateLimiter::tooManyAttempts($key.$suffix, $limit)) {
                    throw new RiotException('Aguardando limite compartilhado da API.', true,
                        max(1, RateLimiter::availableIn($key.$suffix)), true);
                }
            }
            RateLimiter::hit($key.':second', 1);
            RateLimiter::hit($key.':window', 120);
        } finally {
            $lock->release();
        }
    }

    private function get(string $host, string $path, array $query = []): array
    {
        if (! config('riot.key')) {
            throw new RiotException('A integração Riot não está configurada. Contate o administrador.');
        }
        $this->reserveBudget();
        try {
            $response = Http::acceptJson()->withHeaders(['X-Riot-Token' => config('riot.key')])
                ->connectTimeout(config('riot.connect_timeout'))->timeout(config('riot.timeout'))
                ->withoutRedirecting()->get('https://'.$host.'.api.riotgames.com'.$path, $query);
        } catch (ConnectionException) {
            throw new RiotException('Não foi possível conectar à Riot. Tentaremos novamente.', true);
        }
        // Server-advertised application/method windows can be stricter than local configuration.
        $pause = 0;
        foreach (['X-App-Rate-Limit', 'X-Method-Rate-Limit'] as $header) {
            $limits = [];
            foreach (explode(',', $response->header($header) ?? '') as $pair) {
                if (preg_match('/^(\d+):(\d+)$/', trim($pair), $parts)) {
                    $limits[(int) $parts[2]] = (int) $parts[1];
                }
            }
            foreach (explode(',', $response->header($header.'-Count') ?? '') as $pair) {
                if (preg_match('/^(\d+):(\d+)$/', trim($pair), $parts)
                    && isset($limits[(int) $parts[2]]) && (int) $parts[1] >= $limits[(int) $parts[2]]) {
                    $pause = max($pause, min(86400, (int) $parts[2] + 1));
                }
            }
        }
        if ($pause > 0) {
            Cache::put('riot:budget:'.hash('sha256', (string) config('riot.key')).':blocked', time() + $pause, $pause);
        }
        if (! $response->successful()) {
            $delay = $response->header('Retry-After');
            $wait = is_numeric($delay) ? (int) $delay : max(1, (strtotime($delay ?? '') ?: time() + 30) - time());
            if ($response->status() === 429) {
                Cache::put('riot:budget:'.hash('sha256', (string) config('riot.key')).':blocked', time() + max(1, $wait), max(1, $wait));
            }
            throw match ($response->status()) {
                401, 403 => new RiotException('A chave Riot foi recusada. Contate o administrador.'),
                404 => new RiotException('Conta ou partida não encontrada na região informada.'),
                429 => new RiotException('A Riot solicitou uma pausa. A sincronização será retomada.', true, max(1, $wait)),
                default => new RiotException('A Riot está indisponível.', $response->serverError()),
            };
        }
        $data = $response->json();
        if (! is_array($data)) {
            throw new RiotException('A Riot retornou uma resposta inválida.');
        }

        return $data;
    }
}
