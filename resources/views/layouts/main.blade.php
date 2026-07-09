<!DOCTYPE html>
<html lang="pt-BR" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LoL Dashboard</title>
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans antialiased bg-slate-950">

    <div class="flex h-screen overflow-hidden">
        
        <aside class="w-64 bg-slate-900 border-r border-slate-800 flex flex-col justify-between p-4">
            <div>
                <div class="flex items-center gap-2 px-2 py-4 border-b border-slate-800 mb-6">
                    <span class="text-2xl">🏆</span>
                    <h1 class="text-xl font-bold tracking-wider bg-gradient-to-r from-amber-400 to-yellow-500 bg-clip-text text-transparent">
                        LoL Dash
                    </h1>
                </div>

                <nav class="space-y-1">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('dashboard') ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' }}">
                        📊 Dashboard
                    </a>
                    <a href="{{ route('contas.create') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('contas.create') ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' }}">
                        ➕ Nova Conta
                    </a>
                    @if(auth()->user()->role === 'admin')
                        <div class="pt-4 mt-4 border-t border-slate-800">
                            <p class="px-3 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Painel de Controle</p>
                            <a href="{{ route('admin.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.*') ? 'bg-red-500/10 text-red-400 border border-red-500/20' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' }}">
                                👑 Gerenciar Usuários
                            </a>
                        </div>
                    @endif
                </nav>
            </div>

            <div class="border-t border-slate-800 pt-4 flex flex-col gap-2">
                <div class="px-2">
                    <p class="text-xs text-slate-500">Logado como</p>
                    <p class="text-sm font-medium text-slate-300 truncate">{{ auth()->user()->name }}</p>
                </div>
                
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full flex items-center gap-3 px-3 py-2 text-xs font-medium text-red-400 hover:bg-red-500/10 rounded-lg transition">
                        🚪 Sair da Conta
                    </button>
                </form>
            </div>
        </aside>

        <main class="flex-1 overflow-y-auto bg-slate-950 p-8">
            @if(session('success'))
                <div class="mb-6 p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm rounded-xl">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 p-4 bg-red-500/10 border border-red-500/20 text-red-400 text-sm rounded-xl">
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </main>

    </div>

</body>
</html>