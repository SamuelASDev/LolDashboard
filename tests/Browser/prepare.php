<?php

// Isolated browser fixture. Never accepts a database path from the shell.
require __DIR__.'/../../vendor/autoload.php';

use App\Actions\Sync\ImportMatch;
use App\Models\RankCutoff;
use App\Models\User;
use App\Services\ReplaysCutoffSource;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

$expected = realpath(__DIR__.'/../../storage/framework/testing').DIRECTORY_SEPARATOR.'browser.sqlite';
if (getenv('BROWSER_TEST') !== '1' || getenv('APP_ENV') !== 'testing'
    || getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== $expected) {
    throw new RuntimeException('Browser fixture refuses to touch a non-test database.');
}
if (is_file($expected)) {
    unlink($expected);
}
touch($expected);
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
Artisan::call('migrate', ['--force' => true]);
$user = User::factory()->create(['name' => 'Jogador de demonstração', 'email' => 'browser@example.test', 'role' => 'admin']);
$conta = $user->contas()->make(['nick' => 'Conta principal', 'riot_id' => 'Jogador#BR1', 'regiao' => 'br1']);
$conta->forceFill(['puuid' => 'demo-puuid', 'elo' => 'DIAMOND II', 'pdl' => 63, 'wins' => 42,
    'losses' => 28, 'winrate' => 60, 'rank_synced_at' => now(), 'last_game_at' => now()->subHours(5)])->save();
for ($i = 0; $i < 25; $i++) {
    $conta->partidas()->create(['match_id' => 'BR1_DEMO_'.$i, 'champion_name' => ['Ahri', 'Lux', 'Jinx'][$i % 3],
        'win' => $i % 3 !== 0, 'kills' => 5 + $i % 7, 'deaths' => 2 + $i % 4, 'assists' => 7,
        'lane' => ['MIDDLE', 'TOP', 'JUNGLE', 'BOTTOM', 'UTILITY'][$i % 5],
        'champion_id' => [103, 99, 222][$i % 3], 'team_id' => $i % 2 ? 100 : 200,
        'duration_seconds' => 1500 + $i * 35, 'cs' => 150 + $i, 'gold' => 11000 + $i * 100,
        'damage' => 20000 + $i * 500, 'vision_score' => 15 + $i, 'kill_participation' => 30 + $i,
        'wards_placed' => 8 + $i % 6, 'wards_killed' => 3, 'control_wards' => 2,
        'opponent_champion_name' => ['Lux', 'Aatrox', 'LeeSin', 'Jinx', 'Ahri'][$i % 5],
        'opponent_champion_id' => [99, 266, 64, 222, 103][$i % 5],
        'played_at' => now()->subDays(intdiv($i, 2))->subHours($i % 2), 'identity_puuid' => 'demo-puuid',
        'queue_id' => 420, 'is_remake' => false]);
}

$participants = [];
foreach (range(1, 10) as $slot) {
    $participants[] = ['participantId' => $slot, 'puuid' => $slot === 1 ? 'demo-puuid' : 'demo-player-'.$slot,
        'teamId' => $slot <= 5 ? 100 : 200, 'championId' => [103, 99, 222, 266, 64][($slot - 1) % 5],
        'championName' => ['Ahri', 'Lux', 'Jinx', 'Aatrox', 'LeeSin'][($slot - 1) % 5],
        'riotIdGameName' => 'Jogador '.$slot, 'riotIdTagline' => 'BR1', 'win' => $slot <= 5,
        'kills' => 5, 'deaths' => 2, 'assists' => 7, 'champLevel' => 18,
        'individualPosition' => ['MIDDLE', 'UTILITY', 'BOTTOM', 'TOP', 'JUNGLE'][($slot - 1) % 5],
        'totalMinionsKilled' => 170, 'neutralMinionsKilled' => 10, 'goldEarned' => 12000,
        'totalDamageDealtToChampions' => 24000, 'totalDamageTaken' => 16000, 'visionScore' => 25,
        'wardsPlaced' => 12, 'wardsKilled' => 3, 'visionWardsBoughtInGame' => 2,
        'totalHeal' => 1000, 'pentaKills' => 0, 'item0' => 3089, 'item1' => 3020, 'item2' => 3157,
        'summoner1Id' => 4, 'summoner2Id' => 14, 'perks' => ['styles' => [['selections' => [['perk' => 8112]]]]]];
}
app(ImportMatch::class)->execute($conta, 'BR1_DEMO_0', ['info' => [
    'queueId' => 420, 'gameEndTimestamp' => now()->getTimestampMs(), 'gameDuration' => 1800,
    'gameVersion' => '16.20.1', 'participants' => $participants,
    'teams' => [['teamId' => 100, 'objectives' => ['dragon' => ['kills' => 3], 'tower' => ['kills' => 8]],
        'bans' => [['championId' => 266]]]],
]]);
$conta->rankSnapshots()->create(['tier' => 'DIAMOND', 'division' => 'II', 'lp' => 63,
    'wins' => 42, 'losses' => 28, 'observed_at' => now()]);
foreach (range(1, 7) as $day) {
    $conta->rankSnapshots()->create(['tier' => 'DIAMOND', 'division' => $day > 3 ? 'III' : 'II',
        'lp' => 100 - $day * 11, 'wins' => 42 - $day, 'losses' => 28, 'observed_at' => now()->subDays($day)]);
}
User::factory()->create(['name' => 'Outro jogador', 'email' => 'other@example.test']);

foreach (['GRANDMASTER' => 1400, 'CHALLENGER' => 2000] as $tier => $lp) {
    RankCutoff::create(['region' => 'br1', 'queue_id' => 420, 'tier' => $tier, 'lp' => $lp,
        'source_url' => ReplaysCutoffSource::URL,
        'source_updated_at' => now(), 'fetched_at' => now(), 'last_attempt_at' => now()]);
}
$apex = User::factory()->create(['name' => 'Jogador Mestre', 'email' => 'apex@example.test']);
$apexAccount = $apex->contas()->make(['nick' => 'Mestre', 'riot_id' => 'Mestre#BR1', 'regiao' => 'br1']);
$apexAccount->forceFill(['elo' => 'MASTER I', 'pdl' => 1500, 'rank_synced_at' => now()])->save();

echo "Isolated browser fixture ready.\n";
