<?php

namespace App\Support;

class RiotAssets
{
    private static ?array $catalog = null;

    public static function entry(string $kind, int|string|null $id): ?array
    {
        self::$catalog ??= json_decode(file_get_contents(resource_path('data/riot-assets.json')), true, flags: JSON_THROW_ON_ERROR);
        $entry = self::$catalog[$kind][(string) $id] ?? null;
        if (! $entry) {
            return null;
        }
        $folder = ['champions' => 'champion', 'items' => 'item', 'spells' => 'spell'][$kind] ?? null;
        $entry['url'] = $kind === 'runes'
            ? 'https://ddragon.leagueoflegends.com/cdn/img/'.$entry['image']
            : 'https://ddragon.leagueoflegends.com/cdn/'.self::$catalog['version'].'/img/'.$folder.'/'.$entry['image'];

        return $entry;
    }

    public static function champion(?int $id, string $name): ?array
    {
        $entry = self::entry('champions', $id);
        if ($entry) {
            return $entry;
        }
        foreach (self::$catalog['champions'] as $key => $champion) {
            if (pathinfo($champion['image'], PATHINFO_FILENAME) === $name) {
                return self::entry('champions', $key);
            }
        }

        return null;
    }

    public static function splash(?int $id, string $name): ?string
    {
        $entry = self::champion($id, $name);

        return $entry ? 'https://ddragon.leagueoflegends.com/cdn/img/champion/splash/'.pathinfo($entry['image'], PATHINFO_FILENAME).'_0.jpg' : null;
    }
}
