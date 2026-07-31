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
use App\Models\HistoricoTarefa;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class KanbanController extends Controller
{
    private function registrarHistorico($tarefaId, $tipoAcao, $descricao, $detalhes = null)
    {
        $role = session('user_type', 'aluno');
        $userId = session('user_id');

        HistoricoTarefa::create([
            'tarefa_id' => $tarefaId,
            'aluno_id' => $role === 'aluno' ? $userId : null,
            'prof_id' => $role === 'professor' ? $userId : null,
            'tipo_acao' => $tipoAcao,
            'descricao' => $descricao,
            'detalhes' => $detalhes,
            'created_at' => now()
        ]);
    }

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

        // Map closure para formatar dados completos da tarefa (com histórico)
        $formatarTarefa = function ($tarefa, $colunaId = null, $colsprintId = null) use ($equipe) {
            return [
                'id' => $tarefa->id,
                'titulo' => $tarefa->titulo,
                'descricao' => $tarefa->descricao ?? '',
                'colsprint_id' => $colsprintId,
                'coluna_id' => (string)$colunaId,
                'responsaveis' => $tarefa->responsaveis->map(fn($r) => ['id' => $r->id, 'nome' => $r->nome]),
                'comentarios' => $tarefa->comentarios->map(function($c) use ($equipe) {
                    return [
                        'id' => $c->id,
                        'texto' => $c->texto,
                        'autor_nome' => $c->prof_id ? ($c->professor?->nome ?? 'Professor') : ($c->aluno?->nome ?? 'Aluno'),
                        'is_professor' => !is_null($c->prof_id),
                        'is_orientador' => !is_null($c->prof_id) && $c->prof_id == $equipe->prof_id
                    ];
                }),
                'anexos' => $tarefa->anexos->map(function($a) use ($equipe) {
                    return [
                        'id' => $a->id,
                        'caminho' => $a->caminho,
                        'nome_original' => $a->nome_original ?? 'Arquivo',
                        'autor_nome' => $a->prof_id ? ($a->professor?->nome ?? 'Professor') : ($a->aluno?->nome ?? 'Aluno'),
                        'is_professor' => !is_null($a->prof_id),
                        'is_orientador' => !is_null($a->prof_id) && $a->prof_id == $equipe->prof_id
                    ];
                }),
                'historicos' => $tarefa->historicos->map(function($h) use ($equipe) {
                    return [
                        'id' => $h->id,
                        'tipo_acao' => $h->tipo_acao,
                        'descricao' => $h->descricao,
                        'detalhes' => $h->detalhes,
                        'data' => $h->created_at ? $h->created_at->format('d/m/Y H:i') : '',
                        'autor_nome' => $h->prof_id ? ($h->professor?->nome ?? 'Professor') : ($h->aluno?->nome ?? 'Aluno/Sistema'),
                        'is_professor' => !is_null($h->prof_id),
                        'is_orientador' => !is_null($h->prof_id) && $h->prof_id == $equipe->prof_id
                    ];
                })
            ];
        };

        // 1. Aba Backlog
        $tarefasBacklogRaw = Tarefa::with([
            'responsaveis', 'comentarios.aluno', 'comentarios.professor',
            'anexos.aluno', 'anexos.professor', 'historicos.aluno', 'historicos.professor'
        ])
        ->where('equipe_id', $equipeId)
        ->whereNotIn('id', function($query) {
            $query->select('tarefa_id')->from('tarefa_colunas');
        })
        ->get();

        $tarefasBacklog = $tarefasBacklogRaw->map(fn($t) => $formatarTarefa($t));

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
                'tarefa.anexos.professor',
                'tarefa.historicos.aluno',
                'tarefa.historicos.professor'
            ])
            ->where('sprint_id', $sprint->id)
            ->get();

            $tarefasFormatted = $tarefasColuna->map(function ($tc) use ($colunasSprint, $formatarTarefa) {
                $tarefa = $tc->tarefa;
                $colSprint = $colunasSprint->firstWhere('id', $tc->colsprint_id);
                $colunaId = $colSprint ? (string)$colSprint->coluna_id : '1';
                return $formatarTarefa($tarefa, $colunaId, $tc->colsprint_id);
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

        $tarefa = Tarefa::create([
            'equipe_id' => $equipeId,
            'titulo' => $request->input('titulo'),
            'descricao' => $request->input('descricao')
        ]);

        $this->registrarHistorico(
            $tarefa->id,
            'criacao',
            "Tarefa criada no Backlog com título '{$tarefa->titulo}'."
        );

        return back();
    }

    public function editarTarefa(Request $request, $tarefaId)
    {
        $request->validate([
            'titulo' => 'required|string|max:255',
            'descricao' => 'nullable|string'
        ]);

        $tarefa = Tarefa::findOrFail($tarefaId);
        $alteracoes = [];

        if ($tarefa->titulo !== $request->input('titulo')) {
            $alteracoes[] = "Título alterado de '{$tarefa->titulo}' para '{$request->input('titulo')}'";
        }

        $novaDesc = $request->input('descricao') ?? '';
        $descAntiga = $tarefa->descricao ?? '';
        if ($descAntiga !== $novaDesc) {
            $alteracoes[] = "Descrição da tarefa atualizada";
        }

        $tarefa->update([
            'titulo' => $request->input('titulo'),
            'descricao' => $request->input('descricao')
        ]);

        if (!empty($alteracoes)) {
            $this->registrarHistorico(
                $tarefa->id,
                'edicao',
                implode('. ', $alteracoes) . '.'
            );
        }

        return back();
    }

    public function assumirTarefa(Request $request, $tarefaId)
    {
        $tarefa = Tarefa::findOrFail($tarefaId);
        $alunoId = $request->input('aluno_id', session('user_id'));

        if ($alunoId) {
            $aluno = \App\Models\Aluno::find($alunoId);
            $nomeAluno = $aluno ? $aluno->nome : 'Aluno';

            if ($tarefa->responsaveis()->where('aluno_id', $alunoId)->exists()) {
                $tarefa->responsaveis()->detach($alunoId);
                $this->registrarHistorico(
                    $tarefa->id,
                    'responsavel',
                    "Removida a atribuição do responsável {$nomeAluno}."
                );
            } else {
                $tarefa->responsaveis()->attach($alunoId);
                $this->registrarHistorico(
                    $tarefa->id,
                    'responsavel',
                    "Atribuído o responsável {$nomeAluno} à tarefa."
                );
            }
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

        $this->registrarHistorico(
            $tarefaId,
            'comentario',
            "Novo comentário adicionado à tarefa."
        );

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

        $this->registrarHistorico(
            $tarefaId,
            'anexo',
            "Novo arquivo anexado: '{$nomeOriginal}'."
        );

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

        // Se for a primeira sprint e foi informada uma sequência inicial, usa ela
        if ($ultimaSprint === 0 && $request->filled('sequencia_inicial')) {
            $proximaSequencia = max(1, (int) $request->input('sequencia_inicial'));
        } else {
            $proximaSequencia = $ultimaSprint + 1;
        }

        DB::transaction(function () use ($equipeId, $proximaSequencia, $tarefasIds) {
            $novaSprint = Sprint::create([
                'equipe_id' => $equipeId,
                'sequencia' => $proximaSequencia,
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

                $this->registrarHistorico(
                    $tId,
                    'transferencia_sprint',
                    "Tarefa transferida do Backlog para a Sprint {$novaSprint->sequencia}."
                );
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
            $colunaDestino = Coluna::find($novaColunaId);
            $nomeColuna = $colunaDestino ? $colunaDestino->titulo : 'Nova Coluna';

            TarefaColuna::where('sprint_id', $sprintId)
                ->where('tarefa_id', $tarefaId)
                ->update(['colsprint_id' => $colSprint->id]);

            Tarefa::where('id', $tarefaId)->update(['ultimacoluna_id' => $novaColunaId]);

            $this->registrarHistorico(
                $tarefaId,
                'movimentacao',
                "Tarefa movida para a coluna '{$nomeColuna}' na Sprint {$sprint->sequencia}."
            );

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
            $tarefasNaoConcluidas = TarefaColuna::join('col_sprints', 'tarefa_colunas.colsprint_id', '=', 'col_sprints.id')
                ->join('colunas', 'col_sprints.coluna_id', '=', 'colunas.id')
                ->where('tarefa_colunas.sprint_id', $sprintAtual->id)
                ->where('colunas.concluido', false)
                ->select('tarefa_colunas.id', 'tarefa_colunas.tarefa_id')
                ->get();

            foreach ($tarefasNaoConcluidas as $tc) {
                $this->registrarHistorico(
                    $tc->tarefa_id,
                    'transferencia_sprint',
                    "Sprint {$sprintAtual->sequencia} encerrada com pendência: Tarefa devolvida automaticamente da Sprint {$sprintAtual->sequencia} para o Backlog."
                );
            }

            TarefaColuna::whereIn('id', $tarefasNaoConcluidas->pluck('id'))->delete();
        });

        return back();
    }
}
