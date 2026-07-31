<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sprint;
use App\Models\Tarefa;
use App\Models\Coluna;
use App\Models\ColSprint;
use App\Models\TarefaColuna;
use App\Models\Comentario;
use App\Models\Anexo;
use App\Models\Equipe;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class KanbanController extends Controller
{
    /**
     * O Ambiente de Trabalho da Equipe com 3 Abas
     */
    public function show($equipeId, Request $request)
    {
        $role = session('user_type', 'aluno');
        $alunoPapel = session('papel', session('aluno_papel', 'PO'));
        $userId = session('user_id');
        $aba = $request->query('aba', 'sprint-atual');

        $equipe = Equipe::with(['alunos' => function($query) {
            $query->orderBy('n_chamada', 'asc')->orderBy('nome', 'asc');
        }])->findOrFail($equipeId);
        $isOrientador = ($role === 'professor' && $userId == $equipe->prof_id);

        // 1. Aba Backlog
        $tarefasBacklog = Tarefa::where('equipe_id', $equipeId)
            ->whereNotIn('id', function($query) {
                $query->select('tarefa_id')->from('tarefa_colunas');
            })
            ->get();

        // 2. Sprint Atual
        $sprintIdQuery = $request->query('sprint_id');
        if ($sprintIdQuery) {
            $sprint = Sprint::find($sprintIdQuery);
        } else {
            $sprint = Sprint::where('equipe_id', $equipeId)
                ->where('encerrada', false)
                ->orderBy('sequencia', 'desc')
                ->first();
        }

        $colunasFormatted = [];
        $tarefasFormatted = [];
        $bloqueadaPorPrazo = false;

        if ($sprint) {
            $bloqueadaPorPrazo = $sprint->encerrada || $aba === 'anteriores';

            $colunasSprint = ColSprint::with('coluna')
                ->where('sprint_id', $sprint->id)
                ->get();

            $tarefasColuna = TarefaColuna::with([
                'tarefa.responsaveis', 
                'tarefa.comentarios.aluno', 
                'tarefa.comentarios.professor',
                'tarefa.anexos.aluno',
                'tarefa.anexos.professor'
            ])
            ->where('sprint_id', $sprint->id)
            ->get();

            $tarefasFormatted = $tarefasColuna->map(function ($tc) use ($colunasSprint) {
                $tarefa = $tc->tarefa;
                $colSprint = $colunasSprint->firstWhere('id', $tc->colsprint_id);
                return [
                    'id' => $tarefa->id,
                    'titulo' => $tarefa->titulo,
                    'descricao' => $tarefa->descricao ?? '',
                    'colsprint_id' => $tc->colsprint_id,
                    'coluna_id' => $colSprint ? (string)$colSprint->coluna_id : '1',
                    'responsaveis' => $tarefa->responsaveis->map(fn($r) => ['id' => $r->id, 'nome' => $r->nome]),
                    'comentarios' => $tarefa->comentarios->map(function($c) {
                        return [
                            'id' => $c->id,
                            'texto' => $c->texto,
                            'autor_nome' => $c->prof_id ? ($c->professor?->nome ?? 'Professor') : ($c->aluno?->nome ?? 'Aluno'),
                            'is_professor' => !is_null($c->prof_id)
                        ];
                    }),
                    'anexos' => $tarefa->anexos->map(function($a) {
                        return [
                            'id' => $a->id,
                            'caminho' => $a->caminho,
                            'nome_original' => $a->nome_original ?? 'Arquivo',
                            'autor_nome' => $a->prof_id ? ($a->professor?->nome ?? 'Professor') : ($a->aluno?->nome ?? 'Aluno'),
                            'is_professor' => !is_null($a->prof_id)
                        ];
                    })
                ];
            });

            $colunasFormatted = $colunasSprint->map(function ($cs) {
                return [
                    'id' => (string)$cs->coluna_id,
                    'colsprint_id' => $cs->id,
                    'titulo' => $cs->coluna->titulo ?? 'Coluna'
                ];
            });
        }

        // 3. Sprints Anteriores
        $sprintsAnteriores = Sprint::where('equipe_id', $equipeId)
            ->where('encerrada', true)
            ->orderBy('sequencia', 'desc')
            ->get();

        return Inertia::render('Equipes/Show', [
            'equipe' => $equipe,
            'abaAtiva' => $aba,
            'userRole' => $role,
            'isOrientador' => $isOrientador,
            'isPO' => ($role === 'aluno' && $alunoPapel === 'PO') || $isOrientador,
            'sprint' => $sprint ? [
                'id' => $sprint->id,
                'nome' => "Sprint {$sprint->sequencia}",
                'dt_inicio' => $sprint->dt_inicio,
                'dt_fim' => $sprint->dt_fim,
                'percentual' => $sprint->percentual ?? 0,
                'feedback' => $sprint->feedback ?? '',
                'encerrada' => $sprint->encerrada,
                'bloqueadaPorPrazo' => $bloqueadaPorPrazo
            ] : null,
            'colunas' => $colunasFormatted,
            'tarefasIniciais' => $tarefasFormatted,
            'tarefasBacklog' => $tarefasBacklog,
            'sprintsAnteriores' => $sprintsAnteriores
        ]);
    }

    public function criarTarefaBacklog(Request $request, $equipeId)
    {
        $equipe = Equipe::findOrFail($equipeId);
        $role = session('user_type', 'aluno');
        $alunoPapel = session('papel', session('aluno_papel', 'PO'));
        $userId = session('user_id');

        $isOrientador = ($role === 'professor' && $userId == $equipe->prof_id);
        $isPO = ($role === 'aluno' && $alunoPapel === 'PO');

        if (!$isOrientador && !$isPO) {
            return back()->withErrors(['backlog' => 'Apenas o orientador da equipe ou o Product Owner (PO) podem adicionar tarefas ao backlog.']);
        }

        $request->validate([
            'titulo' => 'required|string|max:255',
            'descricao' => 'nullable|string'
        ]);

        Tarefa::create([
            'equipe_id' => $equipeId,
            'titulo' => $request->input('titulo'),
            'descricao' => $request->input('descricao')
        ]);

        return back();
    }

    public function assumirTarefa(Request $request, $tarefaId)
    {
        $alunoId = session('user_id');
        if ($alunoId && session('user_type') === 'aluno') {
            $tarefa = Tarefa::findOrFail($tarefaId);
            $tarefa->responsaveis()->syncWithoutDetaching([$alunoId]);
        }
        return back();
    }

    public function adicionarComentario(Request $request, $tarefaId)
    {
        $request->validate(['texto' => 'required|string']);

        $role = session('user_type', 'aluno');
        $userId = session('user_id');

        Comentario::create([
            'tarefa_id' => $tarefaId,
            'aluno_id' => $role === 'aluno' ? $userId : null,
            'prof_id' => $role === 'professor' ? $userId : null,
            'texto' => $request->input('texto')
        ]);

        return back();
    }

    public function adicionarAnexo(Request $request, $tarefaId)
    {
        $request->validate([
            'arquivo' => 'required|file|max:10240'
        ]);

        $file = $request->file('arquivo');
        $nomeOriginal = $file->getClientOriginalName();
        $path = $file->store('anexos', 'public');

        $role = session('user_type', 'aluno');
        $userId = session('user_id');

        Anexo::create([
            'tarefa_id' => $tarefaId,
            'aluno_id' => $role === 'aluno' ? $userId : null,
            'prof_id' => $role === 'professor' ? $userId : null,
            'caminho' => $path,
            'nome_original' => $nomeOriginal
        ]);

        return back();
    }

    public function iniciarSprint(Request $request, $equipeId)
    {
        $equipe = Equipe::findOrFail($equipeId);
        $role = session('user_type', 'aluno');
        $alunoPapel = session('papel', session('aluno_papel', 'PO'));
        $userId = session('user_id');

        $isOrientador = ($role === 'professor' && $userId == $equipe->prof_id);
        $isPO = ($role === 'aluno' && $alunoPapel === 'PO');

        if (!$isOrientador && !$isPO) {
            return back()->withErrors(['sprint' => 'Apenas o orientador da equipe ou o Product Owner (PO) podem iniciar uma Sprint.']);
        }

        $request->validate(['tarefas_ids' => 'required|array']);
        $tarefasIds = $request->input('tarefas_ids');

        $ultimaSprint = Sprint::where('equipe_id', $equipeId)->max('sequencia') ?? 0;

        DB::transaction(function () use ($equipeId, $ultimaSprint, $tarefasIds) {
            $novaSprint = Sprint::create([
                'equipe_id' => $equipeId,
                'sequencia' => $ultimaSprint + 1,
                'dt_inicio' => date('Y-m-d'),
                'dt_fim' => date('Y-m-d', strtotime('+15 days')),
                'percentual' => 0.00,
                'encerrada' => false
            ]);

            $colunas = Coluna::where('equipe_id', $equipeId)->get();
            if ($colunas->isEmpty()) {
                $c1 = Coluna::create(['titulo' => 'A FAZER', 'sequencia' => 1, 'equipe_id' => $equipeId, 'concluido' => false]);
                $c2 = Coluna::create(['titulo' => 'FAZENDO', 'sequencia' => 2, 'equipe_id' => $equipeId, 'concluido' => false]);
                $c3 = Coluna::create(['titulo' => 'EM TESTE', 'sequencia' => 3, 'equipe_id' => $equipeId, 'concluido' => false]);
                $c4 = Coluna::create(['titulo' => 'CONCLUÍDO', 'sequencia' => 4, 'equipe_id' => $equipeId, 'concluido' => true]);
                $colunas = collect([$c1, $c2, $c3, $c4]);
            }

            $primeiroColSprintId = null;
            foreach ($colunas as $index => $col) {
                $cs = ColSprint::create(['coluna_id' => $col->id, 'sprint_id' => $novaSprint->id]);
                if ($index === 0) $primeiroColSprintId = $cs->id;
            }

            foreach ($tarefasIds as $tId) {
                TarefaColuna::create([
                    'tarefa_id' => $tId,
                    'colsprint_id' => $primeiroColSprintId,
                    'sprint_id' => $novaSprint->id,
                    'sequencia' => 1
                ]);
            }
        });

        return back();
    }

    public function mover(Request $request)
    {
        $tarefaId = $request->input('tarefa_id');
        $novaColunaId = $request->input('coluna_id');
        $sprintId = $request->input('sprint_id');

        $sprint = Sprint::findOrFail($sprintId);
        if ($sprint->encerrada) {
            return back()->withErrors(['sprint' => 'Sprint encerrada!']);
        }

        $colSprint = ColSprint::where('sprint_id', $sprintId)
            ->where('coluna_id', $novaColunaId)
            ->first();

        if ($colSprint) {
            TarefaColuna::where('sprint_id', $sprintId)
                ->where('tarefa_id', $tarefaId)
                ->update(['colsprint_id' => $colSprint->id]);

            Tarefa::where('id', $tarefaId)->update(['ultimacoluna_id' => $novaColunaId]);

            $sprint->calcularPercentualConclusao();
        }

        return back();
    }

    /**
     * Trava de Orientador: Apenas o professor orientador da equipe pode encerrar
     */
    public function encerrarSprint(Request $request, $sprintId)
    {
        $sprintAtual = Sprint::findOrFail($sprintId);
        $equipe = Equipe::findOrFail($sprintAtual->equipe_id);

        // Trava de Orientador: Apenas o professor orientador que bate com prof_id pode encerrar
        if (session('user_type') !== 'professor' || session('user_id') != $equipe->prof_id) {
            return back()->withErrors(['orientador' => 'Acesso negado: Apenas o professor orientador desta equipe pode encerrar a sprint.']);
        }

        DB::transaction(function () use ($sprintAtual, $request) {
            $percentualFinal = $sprintAtual->calcularPercentualConclusao();
            $sprintAtual->update([
                'dt_fim' => date('Y-m-d'),
                'feedback' => $request->input('feedback'),
                'encerrada' => true
            ]);

            // Repassar tarefas pendentes para o Backlog
            $tarefasNaoConcluidasIds = TarefaColuna::join('col_sprints', 'tarefa_colunas.colsprint_id', '=', 'col_sprints.id')
                ->join('colunas', 'col_sprints.coluna_id', '=', 'colunas.id')
                ->where('tarefa_colunas.sprint_id', $sprintAtual->id)
                ->where('colunas.concluido', false)
                ->pluck('tarefa_colunas.id');

            TarefaColuna::whereIn('id', $tarefasNaoConcluidasIds)->delete();
        });

        return back();
    }
}
