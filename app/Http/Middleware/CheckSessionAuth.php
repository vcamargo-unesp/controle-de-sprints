<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSessionAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        // Se não houver a variável 'is_logged_in' na sessão, manda de volta pro login
        if (!session()->has('is_logged_in')) {
            return redirect('/')->with('error', 'Você precisa fazer login primeiro.');
        }

        // Se for um aluno tentando acessar a listagem global de equipes (que é só do prof)
        if (session('user_type') === 'aluno' && $request->routeIs('equipes.index')) {
            return redirect()->route('equipes.show', ['id' => session('equipe_id')]);
        }

        return $next($request);
    }
}
