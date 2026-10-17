<?php

namespace App\Http\Controllers;

use App\Actions\Seasons\ActivateSeason;
use App\Models\Season;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SeasonController extends Controller
{
    public function index(): View
    {
        return view('admin.seasons', ['seasons' => Season::orderByDesc('id')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80', 'unique:seasons,name']]);
        Season::create($data);

        return to_route('admin.seasons.index')->with('success', 'Temporada cadastrada. Inicie quando quiser começar a contagem.');
    }

    public function activate(Request $request, Season $season, ActivateSeason $activate): RedirectResponse
    {
        $activate->execute($season, $request->user());

        return to_route('admin.seasons.index')->with('success', 'Temporada iniciada. A anterior foi encerrada e seu histórico preservado.');
    }
}
