@extends('layouts.main')
@section('title', 'Adicionar conta')
@section('content')
<div class="mx-auto max-w-xl">
    <p class="eyebrow text-amber-400">Sua coleção de contas</p><h1 class="mt-2 text-3xl font-semibold tracking-tight">Adicionar conta</h1><p class="mt-3 text-sm leading-relaxed text-slate-400">Acompanhe dados públicos de um invocador. Não solicitamos a senha da sua conta Riot.</p>
    <form class="panel mt-8 space-y-6 p-6 sm:p-8" method="POST" action="{{ route('contas.store') }}">@csrf @include('contas.partials.form')</form>
</div>
@endsection
