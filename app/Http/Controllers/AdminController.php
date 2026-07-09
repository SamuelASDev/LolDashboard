<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Lista todos os usuários do sistema (menos o admin logado)
     */
    public function index()
    {
        // Pega todos os usuários, trazendo junto a contagem de contas vinculadas de cada um
        $usuarios = User::where('id', '!=', auth()->id())
            ->withCount('contas')
            ->orderBy('name', 'asc')
            ->get();

        return view('admin.index', compact('usuarios'));
    }

    /**
     * Atualiza o cargo (role) do usuário (User ou Mod)
     */
    public function updateRole(Request $request, $id)
    {
        $request->validate([
            'role' => 'required|in:user,mod'
        ]);

        $usuario = User::findOrFail($id);
        $usuario->update(['role' => $request->role]);

        return back()->with('success', "Cargo de {$usuario->name} atualizado com sucesso!");
    }

    /**
     * Deleta o usuário e todas as suas contas vinculadas (Cascade automático)
     */
    public function destroy($id)
    {
        $usuario = User::findOrFail($id);
        $usuario->delete(); // O relacionamento configurado na migration vai apagar as contas dele também!

        return back()->with('success', "O usuário {$usuario->name} foi banido do sistema!");
    }
}