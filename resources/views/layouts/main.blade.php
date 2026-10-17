<!DOCTYPE html>
<html lang="pt-BR" class="text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="dark">
    <title>@yield('title', 'Visão geral') · LoL Dashboard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen antialiased" x-data="{ menuOpen: false, desktop: window.innerWidth >= 1024 }" @resize.window="desktop = window.innerWidth >= 1024">
    <a href="#content" class="sr-only focus:not-sr-only focus:fixed focus:z-50 focus:bg-amber-400 focus:p-4 focus:text-slate-950">Pular para o conteúdo</a>
    <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-violet-400/15 bg-[#100b19]/95 px-5 backdrop-blur lg:hidden">
        <a href="{{ route('dashboard') }}" class="font-bold tracking-tight">LoL<span class="text-amber-400"> Dashboard</span></a>
        <button type="button" class="btn-secondary" @click="menuOpen = !menuOpen; if (menuOpen) $nextTick(() => $refs.closeMenu.focus())" :aria-expanded="menuOpen" x-ref="menuTrigger" aria-controls="sidebar">Menu</button>
    </header>
    <div x-cloak x-show="menuOpen" class="fixed inset-0 z-30 bg-black/60 lg:hidden" @click="menuOpen = false" aria-hidden="true"></div>
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r rift-sidebar p-5 transition-transform -translate-x-full data-[open=true]:translate-x-0 lg:translate-x-0" :data-open="menuOpen" @keydown.escape.window="if (menuOpen) { menuOpen = false; $refs.menuTrigger.focus() }" :inert="!menuOpen && !desktop">
        <button x-ref="closeMenu" type="button" class="mb-2 self-end text-xs text-slate-400 lg:hidden" @click="menuOpen = false; $refs.menuTrigger.focus()">Fechar menu</button>
        <a href="{{ route('dashboard') }}" class="mb-10 mt-3 flex items-center gap-3 px-2 text-lg font-bold tracking-tight">
            <span class="brand-mark" aria-hidden="true"><span class="font-display text-lg font-bold">L</span></span>
            LoL Dashboard
        </a>
        <p class="mb-3 px-3 text-[10px] font-bold uppercase tracking-[0.2em] text-violet-300/60">Central do invocador</p>
        <nav aria-label="Navegação principal" class="space-y-1">
            <a class="nav-item {{ request()->routeIs('dashboard') ? 'nav-active' : '' }}" href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif>Visão geral</a>
            <a class="nav-item {{ request()->routeIs('contas.create') ? 'nav-active' : '' }}" href="{{ route('contas.create') }}">Adicionar conta</a>
            <a class="nav-item {{ request()->routeIs('profile.*') ? 'nav-active' : '' }}" href="{{ route('profile.edit') }}">Meu perfil</a>
            @if(in_array(auth()->user()->role, ['admin', 'mod'], true))
                <a class="nav-item {{ request()->routeIs('admin.index') ? 'nav-active' : '' }}" href="{{ route('admin.index') }}">Usuários {{ auth()->user()->role === 'mod' ? '· Consulta' : '' }}</a>
            @endif
            @if(auth()->user()->role === 'admin')
                <a class="nav-item {{ request()->routeIs('admin.seasons.*') ? 'nav-active' : '' }}" href="{{ route('admin.seasons.index') }}">Temporadas</a>
            @endif
        </nav>
        <div class="mt-auto border-t border-violet-400/15 pt-5">
            <p class="truncate px-3 text-sm font-semibold">{{ auth()->user()->name }}</p>
            <p class="mb-4 truncate px-3 text-xs text-slate-500">{{ auth()->user()->email }}</p>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="nav-item w-full text-left text-rose-400" type="submit">Sair</button></form>
        </div>
    </aside>
    <main :inert="menuOpen && !desktop" id="content" class="min-w-0 px-5 py-8 sm:px-8 lg:ml-64 lg:px-10 lg:py-10">
        <div class="mx-auto max-w-[1440px]">
            @foreach(['success', 'error', 'status'] as $flash)
                @if(session($flash))
                    <div role="status" class="notice mb-6">{{ session($flash) }}</div>
                @endif
            @endforeach
            @if($errors->any())
                <div role="alert" class="mb-6 rounded-xl border border-rose-900 bg-rose-950/40 p-4 text-sm text-rose-200">
                    <p class="mb-2 font-semibold">Revise as informações:</p>
                    <ul class="list-inside list-disc space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            @yield('content')
            <footer class="mt-12 border-t border-violet-400/15 pt-5 text-xs leading-relaxed text-slate-500">
                Dados consultados na Riot Games. Indicadores refletem o histórico importado e o período selecionado.
                <p class="mt-2">LoL Dashboard não é endossado pela Riot Games e não reflete suas opiniões. League of Legends e Riot Games são marcas de seus respectivos titulares.</p>
            </footer>
        </div>
    </main>
</body>
</html>
