@extends('layouts.main')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-xl relative overflow-hidden">
        <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-blue-500 to-cyan-400"></div>

        <div class="mb-6 text-center">
            <h3 class="text-2xl font-black tracking-wide text-slate-100">✏️ Editar Configurações</h3>
            <p class="text-sm text-slate-400 mt-1">Atualize as informações de identificação ou região da conta.</p>
        </div>

        <form method="POST" action="{{ route('contas.update', $conta->id) }}" class="space-y-5">
            @csrf
            @method('PUT') {{-- Essencial para rotas de update no Laravel --}}

            <div>
                <label class="block text-sm font-semibold text-slate-300 mb-2">Nick de Identificação Interna</label>
                <input type="text" name="nick" value="{{ $conta->nick }}"
                       class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-slate-200 placeholder-slate-600 focus:outline-none focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/30 transition text-sm"
                       required>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-300 mb-2">Riot ID</label>
                <input type="text" name="riot_id" value="{{ $conta->riot_id }}"
                       class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-slate-200 placeholder-slate-600 focus:outline-none focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/30 transition text-sm"
                       required>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-300 mb-2">Região da Conta</label>
                <div class="relative">
                    <select name="regiao" 
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-slate-200 focus:outline-none focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/30 transition text-sm appearance-none cursor-pointer" required>
                        <option value="br1" {{ $conta->regiao == 'br1' ? 'selected' : '' }}>🇧🇷 Brasil (BR1)</option>
                        <option value="na1" {{ $conta->regiao == 'na1' ? 'selected' : '' }}>🇺🇸 América do Norte (NA1)</option>
                        <option value="euw1" {{ $conta->regiao == 'euw1' ? 'selected' : '' }}>🇪🇺 Europa West (EUW1)</option>
                        <option value="eun1" {{ $conta->regiao == 'eun1' ? 'selected' : '' }}>🇪🇺 Europa Nordic (EUN1)</option>
                        <option value="kr" {{ $conta->regiao == 'kr' ? 'selected' : '' }}>🇰🇷 Coreia (KR)</option>
                        <option value="jp1" {{ $conta->regiao == 'jp1' ? 'selected' : '' }}>🇯🇵 Japão (JP1)</option>
                    </select>
                    <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-slate-500 text-xs">
                        ▼
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between gap-4 pt-4 border-t border-slate-800/60">
                <a href="{{ route('dashboard') }}" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 text-sm font-semibold rounded-xl transition">
                    Cancelar
                </a>
                <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-blue-500 to-cyan-500 hover:from-blue-600 hover:to-cyan-600 text-slate-950 font-bold text-sm rounded-xl transition shadow-lg shadow-blue-500/10">
                    💾 Atualizar Dados
                </button>
            </div>
        </form>
    </div>
</div>
@endsection