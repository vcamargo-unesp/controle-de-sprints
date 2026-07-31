<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSessionAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        // Aceita sessões com is_logged_in OU com user_id (compatibilidade com sessões antigas)
        $isAuthenticated = session()->has('is_logged_in') || session()->has('user_id');

        if (!$isAuthenticated) {
            return redirect('/')->with('error', 'Você precisa fazer login primeiro.');
        }

        // Normaliza user_type a partir de user_role se necessário (sessões antigas)
        if (!session()->has('user_type') && session()->has('user_role')) {
            session(['user_type' => session('user_role')]);
        }

        // Se for um aluno tentando acessar a listagem global de equipes (que é só do prof)
        if (session('user_type') === 'aluno' && $request->routeIs('equipes.index')) {
            return redirect()->route('equipes.show', ['id' => session('equipe_id')]);
        }

        return $next($request);
    }
}
