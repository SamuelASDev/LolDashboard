@extends('layouts.main')

@section('content')
<div class="mb-8">
    <h2 class="text-2xl font-bold text-slate-100">Controle de Usuários</h2>
    <p class="text-sm text-slate-400">Gerencie os acessos, altere permissões de moderadores e remova contas do sistema.</p>
</div>

<div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-950/60 border-b border-slate-800 text-xs font-semibold text-slate-400 uppercase tracking-wider">
                    <th class="px-6 py-4">Usuário</th>
                    <th class="px-6 py-4">E-mail</th>
                    <th class="px-6 py-4 text-center">Contas Vinculadas</th>
                    <th class="px-6 py-4">Nível de Acesso (Role)</th>
                    <th class="px-6 py-4 text-right">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 text-sm text-slate-300">
                @forelse($usuarios as $usuario)
                    <tr class="hover:bg-slate-850/40 transition">
                        <td class="px-6 py-4 font-semibold text-slate-200">
                            {{ $usuario->name }}
                        </td>
                        
                        <td class="px-6 py-4 text-slate-400">
                            {{ $usuario->email }}
                        </td>
                        
                        <td class="px-6 py-4 text-center font-bold text-amber-400">
                            {{ $usuario->contas_count }}
                        </td>
                        
                        <td class="px-6 py-4">
                            <form method="POST" action="{{ route('admin.updateRole', $usuario->id) }}" class="flex items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <select name="role" onchange="this.form.submit()" 
                                        class="bg-slate-950 border border-slate-800 text-xs rounded-xl px-3 py-1.5 focus:outline-none focus:border-amber-500/50 cursor-pointer">
                                    <option value="user" {{ $usuario->role === 'user' ? 'selected' : '' }}>👤 User (Padrão)</option>
                                    <option value="mod" {{ $usuario->role === 'mod' ? 'selected' : '' }}>⚔️ Moderador</option>
                                </select>
                            </form>
                        </td>
                        
                        <td class="px-6 py-4 text-right">
                            <form method="POST" action="{{ route('admin.destroy', $usuario->id) }}" 
                                  onsubmit="return confirm('ATENÇÃO: Deletar o usuário apagará permanently todas as contas vinculadas dele. Continuar?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-3 py-1.5 bg-red-950/30 hover:bg-red-950/60 border border-red-900/40 text-red-400 font-medium text-xs rounded-lg transition">
                                    🗑️ Banir Sistema
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-slate-500">
                            🧊 Nenhum outro usuário cadastrado no sistema além de você.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection