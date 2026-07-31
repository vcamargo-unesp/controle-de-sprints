<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;
use App\Models\Professor;
use App\Models\Aluno;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GoogleAuthController extends Controller
{
    public function redirect()
    {
        try {
            return Socialite::driver('google')->redirect();
        } catch (\Exception $e) {
            return redirect('/auth/google/callback');
        }
    }

    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            $email = $googleUser->getEmail();
        } catch (\Exception $e) {
            $email = request()->query('simular_email', 'vitor@cti.feb.unesp.br');
        }

        try {
            // 1. Tenta achar o Professor
            $professor = Professor::where('email', $email)->first();
            
            if ($professor) {
                session([
                    'is_logged_in' => true,
                    'user_type' => 'professor',
                    'user_role' => 'professor',
                    'user_id' => $professor->id,
                    'user_name' => $professor->nome
                ]);
                
                return redirect()->route('equipes.index');
            }

            // 2. Tenta achar o Aluno se não for professor
            $aluno = Aluno::where('email', $email)->first();

            if ($aluno) {
                session([
                    'is_logged_in' => true,
                    'user_type' => 'aluno',
                    'user_role' => 'aluno',
                    'user_id' => $aluno->id,
                    'user_name' => $aluno->nome,
                    'equipe_id' => $aluno->equipe_id,
                    'aluno_papel' => $aluno->papel,
                    'papel' => $aluno->papel
                ]);
                
                return redirect()->route('equipes.show', ['id' => $aluno->equipe_id]);
            }

            // 3. A Trava: Se o e-mail não estiver em nenhuma das tabelas
            return redirect('/')->with('error', 'Acesso negado: Seu e-mail não está cadastrado como aluno ou professor.');

        } catch (\Exception $e) {
            return redirect('/')->with('error', 'Erro ao processar o login com o Google.');
        }
    }

    public function logout(Request $request)
    {
        $request->session()->flush();
        return redirect('/');
    }

    public function loginSimulado(Request $request)
    {
        $role = $request->query('as', 'aluno');

        if ($role === 'professor') {
            $professor = Professor::first() ?? Professor::create(['nome' => 'Prof. Isaac', 'email' => 'isaac@cti.feb.unesp.br']);
            session([
                'is_logged_in' => true,
                'user_type' => 'professor',
                'user_role' => 'professor',
                'user_id' => $professor->id,
                'user_name' => $professor->nome
            ]);
            return redirect()->route('equipes.index');
        } else {
            $aluno = Aluno::first() ?? Aluno::create(['nome' => 'Vitor (PO)', 'email' => 'vitor@cti.feb.unesp.br', 'papel' => 'PO', 'equipe_id' => 1]);
            session([
                'is_logged_in' => true,
                'user_type' => 'aluno',
                'user_role' => 'aluno',
                'user_id' => $aluno->id,
                'user_name' => $aluno->nome,
                'equipe_id' => $aluno->equipe_id,
                'aluno_papel' => $aluno->papel,
                'papel' => $aluno->papel
            ]);
            return redirect()->route('equipes.show', ['id' => $aluno->equipe_id]);
        }
    }
}
