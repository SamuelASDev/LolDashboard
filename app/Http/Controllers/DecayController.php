<?php

namespace App\Http\Controllers;

use App\Actions\Accounts\SaveDecayReference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DecayController extends Controller
{
    public function update(Request $request, int $conta, SaveDecayReference $save): RedirectResponse
    {
        $request->user()->contas()->findOrFail($conta);
        $data = $request->validate(['decay_days' => ['required', 'integer', 'between:0,14']]);
        $save->execute($request->user(), $conta, (int) $data['decay_days']);

        return to_route('contas.show', $conta)->with('success', 'Reserva informada. A previsão considera o tempo e as partidas Solo/Duo importadas após este ajuste.');
    }

    public function destroy(Request $request, int $conta, SaveDecayReference $save): RedirectResponse
    {
        $save->execute($request->user(), $conta, null);

        return to_route('contas.show', $conta)->with('success', 'Referência removida. Exibindo estimativa automática.');
    }
}
