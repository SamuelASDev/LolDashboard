@extends('layouts.main')
@section('title', 'Usuários')
@section('content')
<header class="mb-8"><p class="eyebrow text-amber-400">Administração</p><h1 class="mt-2 text-3xl font-semibold tracking-tight">Usuários</h1><p class="mt-3 text-sm text-slate-400">Moderadores têm acesso de consulta. Administradores gerenciam usuários comuns e moderadores.</p></header>
@if(auth()->user()->role === 'admin')
<section class="panel mb-8 flex flex-wrap items-center justify-between gap-4 p-5" aria-labelledby="cutoffs-heading">
    <div><h2 id="cutoffs-heading" class="text-lg font-semibold">Cortes de Grão-Mestre e Desafiante</h2><p class="mt-2 text-sm text-slate-400">Consulte agora os cortes Solo/Duo de todas as regiões, mesmo após a consulta do dia. A rotina diária continua às 03:10 de Brasília.</p></div>
    <form method="POST" action="{{ route('admin.cutoffs.refresh') }}" x-data="{ submitting: false }" @submit="submitting = true" :aria-busy="submitting">
        @csrf
        <button type="submit" class="btn-primary" :disabled="submitting" x-text="submitting ? 'Atualizando cortes…' : 'Atualizar cortes agora'">Atualizar cortes agora</button>
    </form>
</section>
@endif
<div class="panel overflow-hidden">
    <div class="overflow-x-auto"><table class="w-full text-left text-sm"><caption class="sr-only">Usuários cadastrados</caption>
        <thead class="border-b border-slate-800 text-xs text-slate-500"><tr>@foreach(['Usuário','Contas','Permissão','Ações'] as $heading)<th scope="col" class="px-5 py-4">{{ $heading }}</th>@endforeach</tr></thead>
        <tbody class="divide-y divide-slate-800">
        @foreach($usuarios as $usuario)
            <tr><th scope="row" class="px-5 py-4"><span class="block font-semibold">{{ $usuario->name }}</span><span class="text-xs font-normal text-slate-500">{{ $usuario->email }}</span></th>
            <td class="px-5 py-4 text-slate-400">{{ $usuario->contas_count }}</td>
            <td class="px-5 py-4">
                @if(auth()->user()->role === 'admin' && $usuario->role !== 'admin')
                    <form method="POST" action="{{ route('admin.updateRole', $usuario->id) }}" class="flex items-center gap-2">@csrf @method('PATCH')
                        <label class="sr-only" for="role-{{ $usuario->id }}">Permissão de {{ $usuario->name }}</label>
                        <select class="field w-auto" name="role" id="role-{{ $usuario->id }}"><option value="user" @selected($usuario->role === 'user')>Usuário</option><option value="mod" @selected($usuario->role === 'mod')>Moderador</option></select>
                        <button type="submit" class="btn-secondary">Salvar</button>
                    </form>
                @else<span class="rounded-lg bg-slate-800 px-3 py-1 text-xs">{{ ['user' => 'Usuário', 'mod' => 'Moderador', 'admin' => 'Administrador'][$usuario->role] ?? $usuario->role }}</span>@endif
            </td>
            <td class="px-5 py-4">
                @if(auth()->user()->role === 'admin' && $usuario->role !== 'admin')
                    <form method="POST" action="{{ route('admin.destroy', $usuario->id) }}" onsubmit="return confirm('Excluir este usuário e todo o histórico vinculado? A exclusão é permanente e não impede novo cadastro.')">@csrf @method('DELETE')<button class="btn-danger" type="submit">Excluir</button></form>
                @else<span class="text-xs text-slate-500">Protegido / consulta</span>@endif
            </td></tr>
        @endforeach
        </tbody></table></div>
    <div class="border-t border-slate-800 p-5">{{ $usuarios->links() }}</div>
</div>
@if(auth()->user()->role === 'admin')
<section class="panel mt-8 p-5"><h2 class="text-lg font-semibold">Atividade administrativa recente</h2>
    <ul class="mt-4 divide-y divide-slate-800">@forelse($events as $event)<li class="flex flex-wrap justify-between gap-2 py-3 text-xs text-slate-400"><span>{{ $event->action }} · Usuário #{{ $event->subject_id }} · Responsável #{{ $event->actor_id ?? 'excluído' }}</span><time>{{ $event->created_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</time></li>@empty<li class="py-4 text-sm text-slate-500">Nenhuma ação registrada.</li>@endforelse</ul>
</section>
@endif
@endsection
