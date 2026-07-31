<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tarefa;
use App\Models\Coluna;
use App\Models\Equipe;
use Inertia\Inertia;

class BacklogController extends Controller
{
    public function index(Request $request)
    {
        $role = session('user_role', 'aluno');
        $alunoPapel = session('aluno_papel', 'PO'); // Apenas PO tem escrita no Backlog

        $equipeId = session('equipe_id', 1);

        // Buscar tarefas não atribuídas ou do backlog da equipe
        $tarefas = Tarefa::where('equipe_id', $equipeId)->get();

        return Inertia::render('Backlog', [
            'userRole' => $role,
            'isPO' => ($role === 'aluno' && $alunoPapel === 'PO') || $role === 'professor',
            'tarefas' => $tarefas
        ]);
    }
}
