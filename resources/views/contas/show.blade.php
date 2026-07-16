@extends('layouts.main')

@section('content')

<div class="mb-6">
    <a href="{{ route('dashboard') }}" class="text-xs font-semibold px-4 py-2 bg-slate-850 hover:bg-slate-800 text-slate-400 hover:text-slate-200 border border-slate-700/60 rounded-xl transition inline-flex items-center gap-2">
        ⬅️ Voltar ao Dashboard
    </a>
</div>

<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
    <div>
        <div class="flex items-center gap-3">
            <span class="text-xs font-extrabold px-2.5 py-1 bg-indigo-500/10 text-indigo-400 rounded-full border border-indigo-500/20 uppercase tracking-wider">
                {{ $conta->regiao }}
            </span>
            <h2 class="text-3xl font-black text-slate-100 tracking-tight">{{ $conta->riot_id }}</h2>
        </div>
        <p class="text-sm text-slate-400 mt-1">Análise aprofundada de estatísticas e winrate da conta.</p>
    </div>
    
    <div class="bg-slate-900 border border-slate-800 px-4 py-2.5 rounded-xl text-right">
        <span class="text-xs text-slate-500 block uppercase font-bold tracking-wider">Elo Registrado</span>
        <span class="text-base font-black text-amber-400">{{ $conta->elo ?? 'UNRANKED' }} <span class="text-xs text-slate-400 font-medium">({{ $conta->pdl ?? 0 }} LP)</span></span>
    </div>
</div>

<div class="bg-gradient-to-b from-slate-900 to-slate-950 border border-slate-800 rounded-2xl p-6 shadow-2xl relative overflow-hidden mb-8">
    <div class="absolute top-0 left-0 right-0 h-[1px] bg-gradient-to-r from-slate-800 via-indigo-500/30 to-slate-800"></div>

    <h3 class="text-lg font-bold text-slate-200 mb-6 tracking-wide flex items-center gap-2">
        <span>📊</span> Métricas Gerais da Conta (Banco de Partidas)
    </h3>
    
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="p-4 bg-slate-950/50 border border-slate-900 rounded-xl shadow-inner">
            <span class="text-xs text-slate-500 uppercase tracking-wider block mb-1">Jogos Analisados</span>
            <span class="text-2xl font-black text-slate-200">{{ $totalGames }}</span>
        </div>
        <div class="p-4 bg-slate-950/50 border border-slate-900 rounded-xl shadow-inner">
            <span class="text-xs text-slate-500 uppercase tracking-wider block mb-1">Vitórias / Derrotas</span>
            <span class="text-xl font-black text-slate-200 text-emerald-400">{{ $totalWins }}W <span class="text-slate-600">/</span> <span class="text-red-400">{{ $totalLosses }}L</span></span>
        </div>
        <div class="p-4 bg-slate-950/50 border border-slate-900 rounded-xl shadow-inner">
            <span class="text-xs text-slate-500 uppercase tracking-wider block mb-1">Taxa de Vitória</span>
            <span class="text-2xl font-black {{ $winrate >= 55 ? 'text-emerald-400' : ($winrate < 50 ? 'text-red-400' : 'text-amber-400') }}">{{ $winrate }}%</span>
        </div>
        <div class="p-4 bg-slate-950/50 border border-slate-900 rounded-xl shadow-inner">
            <span class="text-xs text-slate-500 uppercase tracking-wider block mb-1">KDA Médio</span>
            <span class="text-2xl font-black text-indigo-400">{{ $kda }}</span>
            <span class="text-[10px] text-slate-500 block mt-1">Estatísticas acumuladas</span>
        </div>
    </div>
    
    <div class="pt-6 mt-6 border-t border-slate-800/60">
        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 text-left flex items-center gap-1.5">
            <span class="text-amber-500">🔥</span> Desempenho de Todos os Campeões Jogados nesta Conta
        </h4>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
            @forelse($campeoesConta as $camp)
                <div class="p-3 bg-slate-950/40 border border-slate-900 rounded-xl flex flex-col justify-between hover:border-slate-800 transition">
                    <div class="flex items-center gap-2 mb-2 min-w-0">
                        <img src="https://cdn.communitydragon.org/latest/champion/{{ str_replace(' ', '', $camp['champion_name']) }}/square" 
                             alt="{{ $camp['champion_name'] }}" 
                             class="w-7 h-7 rounded border border-slate-700/80 shadow-md flex-shrink-0 object-cover bg-slate-800"
                             onerror="this.style.display='none';">
                        <span class="text-xs sm:text-sm font-bold text-slate-200 truncate" title="{{ $camp['champion_name'] }}">{{ $camp['champion_name'] }}</span>
                    </div>

                    <div class="mt-1 space-y-0.5">
                        <div class="flex justify-between text-[11px]">
                            <span class="text-slate-500">Jogos:</span>
                            <span class="text-slate-300 font-semibold">{{ $camp['total_jogos'] }}</span>
                        </div>
                        <div class="flex justify-between text-[11px]">
                            <span class="text-slate-500">Winrate:</span>
                            <span class="font-bold {{ $camp['winrate'] >= 55 ? 'text-emerald-400' : ($camp['winrate'] < 50 ? 'text-red-400' : 'text-amber-400') }}">
                                {{ $camp['winrate'] }}%
                            </span>
                        </div>
                        <div class="flex justify-between text-[11px] pt-1 mt-1 border-t border-slate-900">
                            <span class="text-slate-500">KDA:</span>
                            <span class="text-indigo-400 font-bold">{{ $camp['kda'] }}</span>
                        </div>
                        <div class="text-[9px] text-slate-600 text-center pt-0.5">
                            {{ $camp['avg_kills'] }}/{{ $camp['avg_deaths'] }}/{{ $camp['avg_assists'] }}
                        </div>
                    </div>
                </div>
            @empty
                <p class="col-span-full text-xs text-slate-500 italic py-4 text-center">Nenhuma partida registrada para esta conta.</p>
            @endforelse
        </div>
    </div>
</div>

@endsection