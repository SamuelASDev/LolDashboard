<!DOCTYPE html>
<html lang="pt-BR" class="text-slate-100">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="color-scheme" content="dark">
    <title>Acesso · LoL Dashboard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col items-center justify-center px-5 py-12 antialiased">
    <a href="{{ route('home') }}" class="mb-8 font-display text-2xl font-bold tracking-tight">LoL<span class="text-amber-400"> Dashboard</span></a>
    <main class="panel w-full max-w-md p-6 sm:p-8">{{ $slot }}</main>
    <p class="mt-6 max-w-md text-center text-xs leading-relaxed text-slate-500">Seu acesso ao painel é independente da sua conta Riot. Nunca informe sua senha do jogo.</p>
</body>
</html>
