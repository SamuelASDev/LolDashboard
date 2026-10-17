@extends('layouts.main')
@section('title', 'Meu perfil')
@section('content')
<header class="mb-8"><p class="eyebrow text-amber-400">Sua conta de acesso</p><h1 class="mt-2 text-3xl font-semibold tracking-tight">Meu perfil</h1><p class="mt-3 text-sm text-slate-400">Gerencie seus dados, segurança e preferências de acesso.</p></header>
<div class="grid gap-6 xl:grid-cols-2">
    <div class="panel p-6">@include('profile.partials.update-profile-information-form')</div>
    <div class="panel p-6">@include('profile.partials.update-password-form')</div>
    <div class="panel p-6 xl:col-span-2">@include('profile.partials.delete-user-form')</div>
</div>
@endsection
