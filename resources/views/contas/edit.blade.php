@extends('layouts.main')
@section('title', 'Editar conta')
@section('content')
<div class="mx-auto max-w-xl">
    <p class="eyebrow text-amber-400">Preferências da conta</p><h1 class="mt-2 text-3xl font-semibold tracking-tight">Editar cadastro</h1><p class="mt-3 text-sm text-slate-400">Mantenha suas contas organizadas sem misturar identidades.</p>
    <form class="panel mt-8 space-y-6 p-6 sm:p-8" method="POST" action="{{ route('contas.update', $conta) }}">@csrf @method('PUT') @include('contas.partials.form')</form>
</div>
@endsection
