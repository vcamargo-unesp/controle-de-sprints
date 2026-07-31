<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Models\Professor;
use App\Models\Aluno;
use Inertia\Inertia;

class AuthController extends Controller
{
    /**
     * Tela limpa de login
     */
    public function showLogin()
    {
        return Inertia::render('Login');
    }

    /**
     * Redireciona o usuário para o Google
     */
    public function redirectToGoogle()
    {
        try {
            return Socialite::driver('google')->redirect();
        } catch (\Exception $e) {
            // Em dev sem credenciais reais no Google Console, redireciona para a triagem simulada
            return redirect('/auth/google/callback');
        }
    }

    /**
     * Callback de triagem de login por e-mail
     */
    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            $email = $googleUser->getEmail();
        } catch (\Exception $e) {
            $email = request()->query('simular_email', 'vitor@cti.feb.unesp.br');
        }

        // 1. Verificar se é Professor
        $professor = Professor::where('email', $email)->first();
        if ($professor) {
            session([
                'user_id' => $professor->id,
                'user_name' => $professor->nome,
                'user_email' => $professor->email,
                'user_role' => 'professor'
            ]);
            return redirect('/equipes');
        }

        // 2. Verificar se é Aluno
        $aluno = Aluno::where('email', $email)->first();
        if ($aluno) {
            session([
                'user_id' => $aluno->id,
                'user_name' => $aluno->nome,
                'user_email' => $aluno->email,
                'user_role' => 'aluno',
                'equipe_id' => $aluno->equipe_id,
                'aluno_papel' => $aluno->papel
            ]);
            return redirect("/equipes/{$aluno->equipe_id}");
        }

        return redirect('/login')->withErrors(['email' => 'E-mail não autorizado na instituição.']);
    }

    /**
     * Simulação rápida de login para desenvolvimento
     */
    public function loginSimulado(Request $request)
    {
        $role = $request->query('as', 'aluno');

        if ($role === 'professor') {
            $professor = Professor::first() ?? Professor::create(['nome' => 'Prof. Isaac', 'email' => 'isaac@cti.feb.unesp.br']);
            session([
                'user_id' => $professor->id,
                'user_name' => $professor->nome,
                'user_email' => $professor->email,
                'user_role' => 'professor'
            ]);
            return redirect('/equipes');
        } else {
            $aluno = Aluno::first() ?? Aluno::create(['nome' => 'Vitor (PO)', 'email' => 'vitor@cti.feb.unesp.br', 'papel' => 'PO', 'equipe_id' => 1]);
            session([
                'user_id' => $aluno->id,
                'user_name' => $aluno->nome,
                'user_email' => $aluno->email,
                'user_role' => 'aluno',
                'equipe_id' => $aluno->equipe_id,
                'aluno_papel' => $aluno->papel
            ]);
            return redirect("/equipes/{$aluno->equipe_id}");
        }
    }
}
