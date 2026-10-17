import { writeFile } from 'node:fs/promises';

const base = 'https://ddragon.leagueoflegends.com';
async function json(path) {
    const response = await fetch(base + path, { signal: AbortSignal.timeout(20000), redirect: 'error' });
    if (!response.ok) throw new Error('Data Dragon respondeu HTTP ' + response.status);
    return response.json();
}
const version = (await json('/api/versions.json'))[0];
if (!/^\d+\.\d+\.\d+$/.test(version)) throw new Error('Versão inválida');
const catalog = { version, champions: {}, items: {}, spells: {}, runes: {} };
const results = await Promise.all([
    json('/cdn/'+version+'/data/pt_BR/champion.json'),
    json('/cdn/'+version+'/data/pt_BR/item.json'),
    json('/cdn/'+version+'/data/pt_BR/summoner.json'),
    json('/cdn/'+version+'/data/pt_BR/runesReforged.json'),
]);
for (const [index, kind] of ['champions', 'items', 'spells'].entries()) {
    for (const [id, entry] of Object.entries(results[index].data)) {
        catalog[kind][entry.key ?? id] = { name: entry.name, image: entry.image.full };
    }
}
for (const tree of results[3]) {
    for (const rune of [tree, ...tree.slots.flatMap(slot => slot.runes)]) {
        catalog.runes[rune.id] = { name: rune.name, image: rune.icon };
    }
}
await writeFile(new URL('../resources/data/riot-assets.json', import.meta.url), JSON.stringify(catalog), 'utf8');
console.log('Catálogo Data Dragon atualizado: ' + version);
