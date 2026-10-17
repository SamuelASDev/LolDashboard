<?php

namespace App\Http\Controllers;

use App\Actions\Accounts\SavePersonalWorkspace;
use App\Models\Partida;
use App\Queries\AccountAnalytics;
use App\Queries\PersonalWorkspace;
use App\Queries\PlayerHistory;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WorkspaceController extends Controller
{
    public function players(Request $request, PlayerHistory $history)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'relation' => ['nullable', 'in:ally,enemy']]);
        $query = $history->encounters($request->user());
        if ($search = $data['q'] ?? null) {
            $literal = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
            $query->whereRaw("players.riot_id LIKE ? ESCAPE '!'", ['%'.$literal.'%']);
        }
        if ($relation = $data['relation'] ?? null) {
            $query->whereColumn('players.team_id', $relation === 'ally' ? '=' : '!=', 'partidas.team_id');
        }
        $players = $query->select('players.puuid')->selectRaw('MAX(players.riot_id) as riot_id, COUNT(DISTINCT partidas.id) as games, COUNT(DISTINCT CASE WHEN players.team_id = partidas.team_id THEN partidas.id END) as allies')
            ->groupBy('players.puuid')->orderByDesc('games')->orderBy('players.puuid')->paginate(20)->withQueryString();
        $notes = DB::table('player_notes')->where('user_id', $request->user()->id)->whereIn('puuid', $players->pluck('puuid'))->pluck('body', 'puuid');

        return view('workspace.players', compact('players', 'notes'));
    }

    public function player(Request $request, string $puuid, PlayerHistory $history)
    {
        abort_if(strlen($puuid) > 128, 404);
        $request->validate(['relation' => ['nullable', 'in:ally,enemy']]);
        $query = $history->encounters($request->user())->where('players.puuid', $puuid);
        $player = (clone $query)->select('players.riot_id', 'players.puuid')->first();
        $note = DB::table('player_notes')->where('user_id', $request->user()->id)->where('puuid', $puuid)->first();
        abort_unless($player || $note, 404);
        $player ??= $note;
        if ($relation = $request->input('relation')) {
            $query->whereColumn('players.team_id', $relation === 'ally' ? '=' : '!=', 'partidas.team_id');
        }
        $matches = Partida::whereIn('id', $query->select('partidas.id'))
            ->with(['conta:id,riot_id', 'game' => fn ($q) => $q->forDisplay(), 'game.participants'])
            ->orderByDesc('played_at')->orderByDesc('id')->paginate(20)->withQueryString();

        return view('workspace.player', compact('player', 'note', 'matches') + $history->notes($request->user(), $matches->items()));
    }

    public function playerNote(Request $request, string $puuid, PlayerHistory $history, SavePersonalWorkspace $save)
    {
        abort_if(strlen($puuid) > 128, 404);
        $data = $request->validate(['body' => ['nullable', 'string', 'max:5000']]);
        $player = $history->encounters($request->user())->where('players.puuid', $puuid)->select('players.riot_id')->first();
        $existing = DB::table('player_notes')->where('user_id', $request->user()->id)->where('puuid', $puuid)->first();
        abort_unless($player || $existing, 404);
        $save->note($request->user(), 'player_notes', 'puuid', $puuid, $data['body'] ?? null, ['riot_id' => ($player ?? $existing)->riot_id]);

        return back()->with('success', 'Anotação do jogador salva.');
    }

    public function matchNote(Request $request, int $partida, SavePersonalWorkspace $save)
    {
        $match = Partida::whereHas('conta', fn ($q) => $q->where('user_id', $request->user()->id))
            ->where('queue_id', 420)->findOrFail($partida);
        abort_unless($match->identity_puuid && $match->identity_puuid === $match->conta->puuid, 404);
        $data = $request->validate(['body' => ['nullable', 'string', 'max:5000']]);
        $save->note($request->user(), 'match_notes', 'match_id', $match->match_id, $data['body'] ?? null);

        return back()->with('success', 'Anotação da partida salva.');
    }

    public function index(Request $request)
    {
        return view('workspace.index', app(PersonalWorkspace::class)->read($request->user()));
    }

    public function favorite(Request $request, AccountAnalytics $analytics, SavePersonalWorkspace $save)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'conta_id' => ['nullable', 'integer'], 'filters' => ['required', 'array']]);
        $account = isset($data['conta_id']) ? $request->user()->contas()->findOrFail($data['conta_id']) : null;
        $input = Request::create('/', 'GET', $data['filters']);
        $filters = $analytics->filters($input);
        $extra = validator($data['filters'], ['days' => ['nullable', 'integer', 'in:0,7,30,90,365'], 'season' => ['nullable', 'integer', Rule::exists('seasons', 'id')->where(fn ($q) => $q->where('status', '!=', 'draft'))]])->validate();
        $save->create($request->user(), 'filter_favorites', ['name' => $data['name'], 'conta_id' => $account?->id, 'filters' => json_encode($filters + $extra, JSON_THROW_ON_ERROR)]);

        return to_route('workspace.index')->with('success', 'Filtro favorito salvo.');
    }

    public function goal(Request $request, SavePersonalWorkspace $save)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:100'], 'metric' => ['required', 'in:games,wins,winrate'],
            'target' => ['required', 'numeric', 'min:1', 'max:100000'], 'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on']]);
        validator($data, ['ends_on' => ['before_or_equal:'.CarbonImmutable::parse($data['starts_on'])->addYear()->format('Y-m-d')]])->validate();
        if ($data['metric'] === 'winrate') {
            validator($data, ['target' => ['numeric', 'max:100']])->validate();
        }
        $save->create($request->user(), 'personal_goals', $data);

        return back()->with('success', 'Meta criada.');
    }

    public function destroy(Request $request, string $type, int $id, SavePersonalWorkspace $save)
    {
        $table = match ($type) {
            'favoritos' => 'filter_favorites', 'metas' => 'personal_goals', default => abort(404)
        };
        $save->remove($request->user(), $table, $id);

        return back()->with('success', 'Item removido.');
    }
}
