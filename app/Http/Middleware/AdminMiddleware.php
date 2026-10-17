<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // 🛡️ Se o usuário não estiver logado ou não for admin, barramos na hora!
        if (! auth()->check() || auth()->user()->role !== 'admin') {
            abort(403, 'Acesso não autorizado. Apenas administradores podem entrar aqui.');
        }

        return $next($request);
    }
}
