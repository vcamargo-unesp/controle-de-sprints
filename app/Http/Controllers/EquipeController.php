<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Equipe;
use App\Models\Professor;
use Inertia\Inertia;

class EquipeController extends Controller
{
    /**
     * Caminho do Professor: Lista de Equipes com Ordenação Estrita:
     * 1. ano desc (mais recentes no topo)
     * 2. nome asc (ordem alfabética)
     */
    public function index()
    {
        $role = session('user_role', 'professor');

        if ($role === 'aluno') {
            $equipeId = session('equipe_id', 1);
            return redirect("/equipes/{$equipeId}");
        }

        $professores = Professor::orderBy('nome', 'asc')->get(['id', 'nome']);

        $equipes = Equipe::with(['professor', 'sprints', 'alunos'])
            ->orderBy('ano', 'desc')
            ->orderBy('nome', 'asc')
            ->get()
            ->map(function ($e) {
                $sprintAtiva = $e->sprints->sortByDesc('sequencia')->first();
                return [
                    'id' => $e->id,
                    'ano' => $e->ano ?? date('Y'),
                    'turma' => '3º Info',
                    'nome' => $e->nome,
                    'descricao' => $e->descricao,
                    'url' => $e->url,
                    'github' => $e->github,
                    'prof_id' => $e->prof_id,
                    'professor_nome' => $e->professor?->nome ?? 'Sem Orientador',
                    'integrantes_count' => $e->alunos->count(),
                    'sprint_ativa_nome' => $sprintAtiva ? "Sprint {$sprintAtiva->sequencia}" : 'Sem Sprint',
                    'percentual' => $sprintAtiva?->percentual ?? 0
                ];
            });

        return Inertia::render('Equipes/Index', [
            'equipes' => $equipes,
            'professores' => $professores,
            'userRole' => $role
        ]);
    }

    /**
     * Criar nova equipe no banco de dados com url, github e prof_id
     */
    public function store(Request $request)
    {
        if (session('user_type') !== 'professor') {
            return back()->withErrors(['equipe' => 'Apenas professores podem cadastrar equipes.']);
        }

        $request->validate([
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'ano' => 'required|integer',
            'url' => 'nullable|url',
            'github' => 'nullable|url',
            'prof_id' => 'required|exists:professores,id'
        ]);

        Equipe::create([
            'nome' => $request->input('nome'),
            'descricao' => $request->input('descricao'),
            'ano' => $request->input('ano'),
            'url' => $request->input('url'),
            'github' => $request->input('github'),
            'prof_id' => $request->input('prof_id')
        ]);

        return back()->with('success', 'Equipe cadastrada com sucesso!');
    }

    /**
     * Atualizar dados da equipe no banco (Nome, Descrição, Ano, URL, GitHub, Orientador)
     */
    public function update(Request $request, $id)
    {
        if (session('user_type') !== 'professor') {
            return back()->withErrors(['equipe' => 'Apenas professores podem editar equipes.']);
        }

        $equipe = Equipe::findOrFail($id);

        $request->validate([
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'ano' => 'required|integer',
            'url' => 'nullable|url',
            'github' => 'nullable|url',
            'prof_id' => 'required|exists:professores,id'
        ]);

        $equipe->update([
            'nome' => $request->input('nome'),
            'descricao' => $request->input('descricao'),
            'ano' => $request->input('ano'),
            'url' => $request->input('url'),
            'github' => $request->input('github'),
            'prof_id' => $request->input('prof_id')
        ]);

        return back()->with('success', 'Equipe atualizada com sucesso!');
    }
}
