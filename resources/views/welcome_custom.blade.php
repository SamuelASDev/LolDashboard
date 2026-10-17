<!DOCTYPE html>
<html lang="pt-BR" class="text-slate-100">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="dark">
    <title>LoL Dashboard · Seu jogo, em perspectiva</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen antialiased">
    <nav class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-7" aria-label="Navegação principal"><span class="text-lg font-bold tracking-tight">LoL<span class="text-amber-400"> Dashboard</span></span><a class="btn-secondary" href="{{ auth()->check() ? route('dashboard') : route('login') }}">{{ auth()->check() ? 'Abrir painel' : 'Entrar' }}</a></nav>
    <main class="mx-auto max-w-6xl px-6 py-12 sm:py-24">
        <div class="max-w-3xl"><p class="eyebrow text-amber-400">Todas as suas contas. Um só lugar.</p><h1 class="mt-6 font-display text-4xl font-bold leading-tight tracking-tight sm:text-6xl">Seu jogo,<br><span class="text-amber-300">em perspectiva.</span></h1><p class="mt-6 max-w-xl text-base leading-relaxed text-slate-400">Organize suas contas de League of Legends, acompanhe o ranking e descubra como seus campeões se saem nas partidas Solo/Duo e Flex.</p>
            <div class="mt-8 flex flex-wrap gap-3"><a class="btn-primary" href="{{ auth()->check() ? route('dashboard') : route('register') }}">{{ auth()->check() ? 'Ver minhas contas' : 'Criar meu painel' }}</a><a class="btn-secondary" href="{{ route('login') }}">Já tenho acesso</a></div><p class="mt-4 text-xs text-slate-500">Adicione um Riot ID. Nenhuma senha Riot necessária.</p>
        </div>
        <section class="mt-20 grid gap-4 md:grid-cols-3" aria-label="Recursos do painel">
            @foreach([['01','Contas organizadas','Ranking por tier, divisão e LP. Um cadastro independente para cada identidade.'],['02','Histórico com contexto','Vitórias, KDA e campeões por período, com partidas confirmadas pela Riot.'],['03','Você continua no controle','Sincronização em segundo plano, progresso visível e opção de cancelar.']] as [$number,$title,$description])
                <article class="panel p-6"><span class="text-xs font-bold text-amber-400">{{ $number }}</span><h2 class="mt-6 text-lg font-semibold">{{ $title }}</h2><p class="mt-3 text-sm leading-relaxed text-slate-400">{{ $description }}</p></article>
            @endforeach
        </section>
    </main>
    <footer class="mx-auto max-w-6xl border-t border-violet-400/15 px-6 py-7 text-xs leading-relaxed text-slate-500">LoL Dashboard não é endossado pela Riot Games e não reflete suas opiniões. League of Legends e Riot Games são marcas de seus respectivos titulares. Dados dependem da disponibilidade da API; este painel não prevê decay nem promoções.</footer>
</body>
</html>
