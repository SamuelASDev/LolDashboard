<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

class ReplaysCutoffSource
{
    public const URL = 'https://www.replays.lol/cutoff/BR/grandmaster';

    /** One public page contains both Solo/Duo thresholds for all supported platforms. */
    public function fetch(): array
    {
        $response = Http::accept('text/html')->withUserAgent('LoLDashboard/1.0')
            ->connectTimeout(5)->timeout(15)->withoutRedirecting()->get(self::URL);
        if (! $response->successful() || trim($response->body()) === '' || strlen($response->body()) > 2_000_000) {
            throw new UnexpectedValueException('Fonte de cortes indisponível.');
        }
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($response->body(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $script = (new DOMXPath($document))->query('//script[@id="__NEXT_DATA__"]')->item(0);
        if (! $script) {
            throw new UnexpectedValueException('Estrutura da fonte de cortes alterada.');
        }
        $data = json_decode($script->textContent, true, flags: JSON_THROW_ON_ERROR);
        if (($data['query']['region'] ?? null) !== 'BR' || ($data['query']['rank'] ?? null) !== 'grandmaster'
            || ! is_array($data['props']['pageProps']['cutoffData'] ?? null)) {
            throw new UnexpectedValueException('Página de cortes não reconhecida.');
        }

        return $data['props']['pageProps']['cutoffData'];
    }

    public function platform(array $catalog, string $platform): array
    {
        $rows = array_values(array_filter($catalog, fn ($row) => is_array($row) && ($row['region'] ?? null) === strtoupper($platform)));
        if (count($rows) !== 1) {
            throw new UnexpectedValueException('Região não encontrada na fonte de cortes.');
        }
        $row = $rows[0];
        foreach (['grandmaster', 'challenger'] as $tier) {
            if (! is_int($row[$tier] ?? null) || $row[$tier] < 1 || $row[$tier] > 100000) {
                throw new UnexpectedValueException('Corte inválido.');
            }
        }
        if ($row['challenger'] < $row['grandmaster'] || ! is_int($row['timeFetched'] ?? null)
            || $row['timeFetched'] < 1 || $row['timeFetched'] > now()->addMinutes(5)->getTimestampMs()) {
            throw new UnexpectedValueException('Referência de cortes inconsistente.');
        }
        $timestamp = CarbonImmutable::createFromTimestampMs($row['timeFetched'])->utc();
        if ($timestamp->lt(now()->subHours(36))) {
            throw new UnexpectedValueException('A fonte não fornece cortes recentes.');
        }

        return ['GRANDMASTER' => $row['grandmaster'], 'CHALLENGER' => $row['challenger'], 'updated_at' => $timestamp];
    }
}
