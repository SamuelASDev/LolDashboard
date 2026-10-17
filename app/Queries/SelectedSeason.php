<?php

namespace App\Queries;

use App\Models\Season;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SelectedSeason
{
    public function execute(Request $request): Season
    {
        $data = $request->validate(['season' => ['sometimes', 'integer', 'exists:seasons,id']]);
        $season = isset($data['season']) ? Season::findOrFail($data['season']) : Season::current();
        if ($season->status === 'draft') {
            throw ValidationException::withMessages(['season' => 'Esta temporada ainda não começou.']);
        }

        return $season;
    }
}
