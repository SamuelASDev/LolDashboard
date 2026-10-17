<?php

namespace App\Http\Controllers;

use App\Actions\Sync\StartSync;
use App\Queries\SelectedSeason;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class SyncController extends Controller
{
    public function store(Request $request, int $conta, StartSync $start): RedirectResponse
    {
        $conta = $request->user()->contas()->findOrFail($conta);
        $options = $request->validate(['mode' => ['sometimes', 'in:recent,full'], 'queue' => ['sometimes', 'integer', 'in:420']]);
        $season = app(SelectedSeason::class)->execute($request);
        $start->execute($conta, $options['mode'] ?? 'recent', (int) ($options['queue'] ?? 420), $season);

        return to_route('contas.show', array_merge(['conta' => $conta], $request->only('season')))->with('success', 'Sincronização agendada. O progresso aparece abaixo.');
    }

    public function storeAll(Request $request, StartSync $start): RedirectResponse
    {
        $season = app(SelectedSeason::class)->execute($request);
        $scheduled = 0;
        foreach ($request->user()->contas()->orderBy('id')->get() as $conta) {
            try {
                $start->execute($conta, 'recent', 420, $season);
                $scheduled++;
            } catch (ValidationException) {
                continue;
            }
        }

        return to_route('dashboard', $request->only('season'))->with('success', "{$scheduled} conta(s) agendada(s). Os limites por usuario podem deixar outras contas para depois.");
    }

    public function status(Request $request, int $conta): JsonResponse
    {
        $conta = $request->user()->contas()->findOrFail($conta);
        $run = $conta->latestSync;

        return response()->json(['run' => $run ? [
            'id' => $run->id, 'status' => $run->status, 'message' => $run->message,
            'processed' => $run->processed, 'imported' => $run->imported,
            'active' => $run->isActive(), 'updated_at' => $run->updated_at->toIso8601String(),
        ] : null])->header('Cache-Control', 'no-store');
    }

    public function destroy(Request $request, int $conta): RedirectResponse
    {
        $conta = $request->user()->contas()->findOrFail($conta);
        $run = $conta->syncRuns()->whereNotNull('active_conta_id')->first();
        if ($run) {
            $lock = Cache::lock('riot:run:'.$run->id, 120);
            if (! $lock->get()) {
                throw ValidationException::withMessages(['sync' => 'Uma etapa está em execução. Tente cancelar novamente em alguns segundos.']);
            }
            try {
                $run->refresh();
                if ($run->isActive()) {
                    $run->finish('cancelled', 'Cancelada pelo usuário. Dados confirmados preservados.');
                }
            } finally {
                $lock->release();
            }
        }

        return back()->with('success', 'Sincronização cancelada.');
    }
}
