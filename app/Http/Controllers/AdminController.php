<?php

namespace App\Http\Controllers;

use App\Actions\Sync\RefreshRankCutoffs;
use App\Actions\Users\ManageUser;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function refreshCutoffs(RefreshRankCutoffs $refresh): RedirectResponse
    {
        $status = $refresh->execute(force: true);

        return to_route('admin.index')->with(in_array($status, ['failed', 'partial'], true) ? 'error' : 'status', match ($status) {
            'updated' => 'Cortes atualizados com sucesso.',
            'busy' => 'Outra atualização dos cortes está em andamento. Aguarde e recarregue a página.',
            'partial' => 'Algumas regiões não puderam ser atualizadas. Os últimos valores dessas regiões foram preservados.',
            default => 'Não foi possível atualizar os cortes. Os últimos valores foram preservados. Tente novamente mais tarde.',
        });
    }

    public function index(Request $request): View
    {
        return view('admin.index', [
            'usuarios' => User::withCount('contas')->orderBy('name')->paginate(20),
            'events' => $request->user()->role === 'admin' ? AuditEvent::latest('id')->limit(20)->get() : collect(),
        ]);
    }

    public function updateRole(Request $request, int $id, ManageUser $users): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', 'in:user,mod']]);
        $users->changeRole($request->user(), User::findOrFail($id), $data['role']);

        return back()->with('success', 'Permissão atualizada e registrada.');
    }

    public function destroy(Request $request, int $id, ManageUser $users): RedirectResponse
    {
        $users->delete($request->user(), User::findOrFail($id));

        return back()->with('success', 'Usuário e dados vinculados excluídos definitivamente.');
    }
}
