<!DOCTYPE html>
<html lang="pt-BR" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LoL Dashboard — Bem-vindo</title>
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-950 font-sans antialiased relative overflow-x-hidden flex items-center justify-center min-h-screen p-4 md:p-12">

    <div class="absolute top-[-10%] left-[-10%] w-[50vw] h-[50vw] rounded-full bg-indigo-900/15 blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-[-10%] right-[-10%] w-[40vw] h-[40vw] rounded-full bg-amber-900/10 blur-[100px] pointer-events-none"></div>

    <div class="relative w-full max-w-5xl bg-slate-900/40 backdrop-blur-xl border border-slate-800/80 rounded-3xl shadow-2xl shadow-black/50 overflow-hidden grid grid-cols-1 md:grid-cols-12">
        
        <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-amber-500 via-yellow-400 to-amber-600 z-10"></div>

        <div class="p-8 sm:p-10 md:col-span-7 flex flex-col justify-center">
            
            <div class="mb-6">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-gradient-to-br from-amber-500/10 to-yellow-500/10 border border-amber-500/20 shadow-lg mb-4">
                    <span class="text-2xl">🏆</span>
                </div>
                <h1 class="text-3xl sm:text-4xl font-black tracking-tight bg-gradient-to-r from-slate-100 via-slate-200 to-slate-400 bg-clip-text text-transparent">
                    League of Legends Dashboard
                </h1>
                <p class="text-sm text-slate-400 mt-2 leading-relaxed">
                    A plataforma definitiva para gerenciar, ordenar e monitorar o elo de todos os seus invocadores em tempo real.
                </p>
            </div>

            <div class="space-y-3 my-4">
                <div class="flex items-start gap-3 p-3 bg-slate-950/30 border border-slate-850 rounded-xl">
                    <span class="text-amber-400 text-sm mt-0.5">🔗</span>
                    <div>
                        <h4 class="text-sm font-bold text-slate-200">Múltiplas Contas Sincronizadas</h4>
                        <p class="text-xs text-slate-400 mt-0.5">Adicione quantos invocadores quiser usando apenas o Riot ID, sem burocracia de senhas.</p>
                    </div>
                </div>

                <div class="flex items-start gap-3 p-3 bg-slate-950/30 border border-slate-850 rounded-xl">
                    <span class="text-purple-400 text-sm mt-0.5">📊</span>
                    <div>
                        <h4 class="text-sm font-bold text-slate-200">Ordenação de Elos em Tempo Real</h4>
                        <p class="text-xs text-slate-400 mt-0.5">Visualização dinâmica organizada via banco de dados por Tier, Divisão e PDL exato.</p>
                    </div>
                </div>

                <div class="flex items-start gap-3 p-3 bg-slate-950/30 border border-slate-850 rounded-xl">
                    <span class="text-cyan-400 text-sm mt-0.5">🛡️</span>
                    <div>
                        <h4 class="text-sm font-bold text-slate-200">Contador de Inatividade (Decay)</h4>
                        <p class="text-xs text-slate-400 mt-0.5">Evite quedas de elo indesejadas em contas Mestre+ com um alerta preciso de dias restantes.</p>
                    </div>
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-slate-800/40 flex flex-col sm:flex-row items-center gap-3">
                @if (Route::has('login'))
                    @auth
                        <a href="{{ route('dashboard') }}" class="w-full sm:w-auto px-6 py-2.5 text-center text-sm font-bold text-slate-950 bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-600 hover:to-yellow-600 rounded-xl shadow-lg transition duration-200">
                            Ir para o Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="w-full sm:w-auto px-6 py-2.5 text-center text-sm font-bold text-slate-950 bg-gradient-to-r from-amber-500 to-yellow-400 hover:from-amber-600 hover:to-yellow-500 rounded-xl shadow-lg transition duration-200">
                            Entrar no Painel
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="w-full sm:w-auto px-6 py-2.5 text-center text-sm font-bold text-slate-300 bg-slate-800/80 hover:bg-slate-700 border border-slate-700/60 rounded-xl transition duration-200">
                                Criar Nova Conta
                            </a>
                        @endif
                    @endauth
                @endif
            </div>
        </div>

        <div class="hidden md:flex md:col-span-5 bg-gradient-to-br from-indigo-950/40 to-slate-950/80 border-l border-slate-800/50 p-8 flex-col justify-between relative overflow-hidden">
            <div class="absolute top-12 right-[-20%] w-64 h-64 rounded-full bg-indigo-500/10 blur-3xl pointer-events-none"></div>
            
            <div class="space-y-4 relative z-10 my-auto">
                <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800/80 shadow-xl">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-[10px] uppercase font-bold tracking-widest text-slate-500">Invocador Ativo</span>
                        <span class="text-xs font-semibold px-2 py-0.5 bg-red-500/10 text-red-400 rounded-full border border-red-500/20">BR1</span>
                    </div>
                    <div class="text-sm font-bold text-slate-200">👑 Invocador#TAG</div>
                    <div class="text-xs font-black tracking-wide text-red-400 mt-1">GRANDMASTER — 450 LP</div>
                    <div class="w-full bg-slate-800 h-1.5 rounded-full mt-2 overflow-hidden">
                        <div class="bg-gradient-to-r from-red-500 to-rose-500 h-full" style="width: 75%"></div>
                    </div>
                </div>

                <div class="p-4 rounded-2xl bg-slate-900/40 border border-slate-850 shadow-lg scale-95 opacity-60">
                    <div class="text-sm font-bold text-slate-300">Smurf_Account#BR1</div>
                    <div class="text-xs font-black tracking-wide text-purple-400 mt-1">MASTER — 120 LP</div>
                </div>
            </div>

            <div class="text-[11px] text-slate-600 text-center relative z-10">
                Sincronizado via Riot Games API API v5
            </div>
        </div>

    </div>

</body>
</html>