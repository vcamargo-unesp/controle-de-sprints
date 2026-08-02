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
use App\Models\AvaliacaoSprint;
use App\Models\AvaliacaoIndividual;
use App\Services\GeminiAvaliacaoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class KanbanController extends Controller
{
    // Colunas padrão que não podem ser removidas nem ter a ordem relativa alterada
    private const COLUNAS_PADRAO        = ['A FAZER', 'FAZENDO', 'EM TESTE', 'CONCLUÍDO'];
    private const COLUNA_CONCLUIDO      = 'CONCLUÍDO';
    // Ordem obrigatória entre padrões: índice menor = deve vir antes
    private const ORDEM_PADRAO          = ['A FAZER' => 0, 'FAZENDO' => 1, 'EM TESTE' => 2, 'CONCLUÍDO' => 3];

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
        $isTL         = ($role === 'aluno'     && $alunoPapel === 'TL');
        $canManageColunas = $isTL || $isOrientador;

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
                        'is_orientador' => !is_null($c->prof_id) && $c->prof_id == $equipe->prof_id,
                        'aluno_id' => $c->aluno_id,
                        'prof_id' => $c->prof_id,
                    ];
                }),
                'anexos' => $tarefa->anexos->map(function($a) use ($equipe) {
                    return [
                        'id' => $a->id,
                        'caminho' => $a->caminho,
                        'nome_original' => $a->nome_original ?? 'Arquivo',
                        'autor_nome' => $a->prof_id ? ($a->professor?->nome ?? 'Professor') : ($a->aluno?->nome ?? 'Aluno'),
                        'is_professor' => !is_null($a->prof_id),
                        'is_orientador' => !is_null($a->prof_id) && $a->prof_id == $equipe->prof_id,
                        'aluno_id' => $a->aluno_id,
                        'prof_id' => $a->prof_id,
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

        // Busca a última sprint encerrada da equipe para verificar quais tarefas pertenciam a ela
        $ultimaSprintEncerrada = Sprint::where('equipe_id', $equipeId)
            ->where('encerrada', true)
            ->orderBy('sequencia', 'desc')
            ->first();

        // 1. Aba Backlog (Apenas tarefas sem Sprint ativa e que NÃO foram concluídas na Sprint mais recente)
        $tarefasBacklogRaw = Tarefa::with([
            'responsaveis', 'comentarios.aluno', 'comentarios.professor',
            'anexos.aluno', 'anexos.professor', 'historicos.aluno', 'historicos.professor'
        ])
        ->where('equipe_id', $equipeId)
        // Nao pode estar em nenhuma sprint ativa
        ->whereNotIn('id', function($query) {
            $query->select('tc.tarefa_id')
                ->from('tarefa_colunas as tc')
                ->join('col_sprints as cs', 'tc.colsprint_id', '=', 'cs.id')
                ->join('sprints as s', 'cs.sprint_id', '=', 's.id')
                ->where('s.encerrada', false);
        })
        // Na sprint mais recente em que participou, a coluna NAO pode ser concluida = true
        ->whereNotIn('id', function($query) {
            $query->select('sub.tarefa_id')
                ->from(DB::raw('(SELECT tc2.tarefa_id, c2.concluido, ROW_NUMBER() OVER (PARTITION BY tc2.tarefa_id ORDER BY s2.sequencia DESC, tc2.id DESC) as rn FROM tarefa_colunas as tc2 JOIN col_sprints as cs2 ON tc2.colsprint_id = cs2.id JOIN sprints as s2 ON cs2.sprint_id = s2.id JOIN colunas as c2 ON cs2.coluna_id = c2.id) as sub'))
                ->where('sub.rn', 1)
                ->where('sub.concluido', true);
        })
        ->get();

        $tarefasBacklog = $tarefasBacklogRaw->map(function($t) use ($formatarTarefa, $ultimaSprintEncerrada) {
            $formatted = $formatarTarefa($t);
            $veioDaSprintAnterior = false;
            $sprintAnteriorNumero = null;

            if ($ultimaSprintEncerrada) {
                $historicoTransferencia = $t->historicos->first(function($h) use ($ultimaSprintEncerrada) {
                    return $h->tipo_acao === 'transferencia_sprint' 
                        && str_contains($h->descricao, "Sprint {$ultimaSprintEncerrada->sequencia}");
                });

                if ($historicoTransferencia) {
                    $veioDaSprintAnterior = true;
                    $sprintAnteriorNumero = $ultimaSprintEncerrada->sequencia;
                }
            }

            $formatted['veio_da_sprint_anterior'] = $veioDaSprintAnterior;
            $formatted['sprint_anterior_numero'] = $sprintAnteriorNumero;
            return $formatted;
        });

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

            // Sincroniza e deduplica todas as colunas ativas da equipe para o Kanban
            $colunasEquipe = Coluna::where('equipe_id', $equipeId)->orderBy('sequencia')->get()->unique('titulo');
            foreach ($colunasEquipe as $colE) {
                ColSprint::firstOrCreate([
                    'coluna_id' => $colE->id,
                    'sprint_id' => $sprint->id
                ]);
            }

            $colunasSprint = ColSprint::with('coluna')
                ->where('sprint_id', $sprint->id)
                ->get()
                ->unique('coluna_id')
                ->unique(function ($cs) {
                    return $cs->coluna ? $cs->coluna->titulo : $cs->coluna_id;
                })
                ->sortBy(function ($cs) {
                    return $cs->coluna ? $cs->coluna->sequencia : 999;
                })
                ->values();

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

        // 3. Sprints Anteriores agrupadas por bimestre
        $sprintsAnteriores = Sprint::with(['avaliacaoSprint', 'avaliacoesIndividuais.aluno'])
            ->where('equipe_id', $equipeId)
            ->where('encerrada', true)
            ->orderBy('bimestre', 'desc')
            ->orderBy('sequencia', 'desc')
            ->get();

        $sprintsAgrupadas = $sprintsAnteriores->groupBy(function ($item) {
            return $item->bimestre ?? 1;
        });

        // 4. Todas as colunas da equipe (para painel de gerenciamento)
        $todasColunas = Coluna::where('equipe_id', $equipeId)
            ->orderBy('sequencia')
            ->get()
            ->map(function ($col) {
                $temTarefas = TarefaColuna::whereIn(
                    'colsprint_id',
                    ColSprint::where('coluna_id', $col->id)->pluck('id')
                )->exists();
                return [
                    'id'        => $col->id,
                    'titulo'    => $col->titulo,
                    'sequencia' => $col->sequencia,
                    'concluido' => $col->concluido,
                    'is_padrao' => in_array($col->titulo, self::COLUNAS_PADRAO),
                    'tem_tarefas' => $temTarefas,
                ];
            });

        return Inertia::render('Equipes/Show', [
            'equipe'           => $equipe,
            'abaAtiva'         => $aba,
            'userRole'         => $role,
            'userId'           => $userId,
            'isOrientador'     => $isOrientador,
            'isTL'             => $isTL,
            'canManageColunas' => $canManageColunas,
            'isPO'             => ($role === 'aluno' && $alunoPapel === 'PO') || $isOrientador,
            'sprint'           => $sprint ? [
                'id'             => $sprint->id,
                'nome'           => "Sprint {$sprint->sequencia}",
                'bimestre'       => $sprint->bimestre ?? 1,
                'dt_inicio'      => $sprint->dt_inicio,
                'dt_fim'         => $sprint->dt_fim,
                'percentual'     => $sprint->percentual ?? 0,
                'feedback'       => $sprint->feedback ?? '',
                'encerrada'      => $sprint->encerrada,
                'bloqueadaPorPrazo' => $bloqueadaPorPrazo
            ] : null,
            'colunas'          => $colunasFormatted,
            'todasColunas'     => $todasColunas,
            'tarefasIniciais'  => $tarefasFormatted,
            'tarefasBacklog'   => $tarefasBacklog,
            'sprintsAnteriores' => $sprintsAnteriores,
            'sprintsAgrupadas' => $sprintsAgrupadas
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

    private function tarefaEstaEmSprintEncerrada($tarefaId): bool
    {
        return TarefaColuna::join('sprints', 'tarefa_colunas.sprint_id', '=', 'sprints.id')
            ->where('tarefa_colunas.tarefa_id', $tarefaId)
            ->where('sprints.encerrada', true)
            ->exists();
    }

    public function editarTarefa(Request $request, $tarefaId)
    {
        if ($this->tarefaEstaEmSprintEncerrada($tarefaId)) {
            return back()->withErrors(['tarefa' => 'Esta tarefa pertence a uma Sprint encerrada e não pode ser editada.']);
        }

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
        if ($this->tarefaEstaEmSprintEncerrada($tarefaId)) {
            return back()->withErrors(['tarefa' => 'Esta tarefa pertence a uma Sprint encerrada e não pode ter seus responsáveis alterados.']);
        }

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
        if ($this->tarefaEstaEmSprintEncerrada($tarefaId)) {
            return back()->withErrors(['comentario' => 'Esta tarefa pertence a uma Sprint encerrada e não pode receber novos comentários.']);
        }

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
        if ($this->tarefaEstaEmSprintEncerrada($tarefaId)) {
            return back()->withErrors(['anexo' => 'Esta tarefa pertence a uma Sprint encerrada e não pode receber novos anexos.']);
        }

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

    public function editarComentario(Request $request, $comentarioId)
    {
        $comentario = Comentario::findOrFail($comentarioId);

        if ($this->tarefaEstaEmSprintEncerrada($comentario->tarefa_id)) {
            return back()->withErrors(['comentario' => 'Esta tarefa pertence a uma Sprint encerrada e seus comentários não podem ser editados.']);
        }

        $request->validate(['texto' => 'required|string']);

        $role = session('user_type', 'aluno');
        $userId = session('user_id');

        // Verificar autoria
        if ($role === 'aluno' && $comentario->aluno_id != $userId) {
            return back()->withErrors(['comentario' => 'Você só pode editar seus próprios comentários.']);
        }
        if ($role === 'professor' && $comentario->prof_id != $userId) {
            return back()->withErrors(['comentario' => 'Você só pode editar seus próprios comentários.']);
        }

        $textoAnterior = $comentario->texto;
        $textoNovo     = $request->input('texto');

        $comentario->update(['texto' => $textoNovo]);

        $this->registrarHistorico(
            $comentario->tarefa_id,
            'comentario',
            "Comentário editado pelo autor.",
            json_encode([
                'antes' => $textoAnterior,
                'depois' => $textoNovo,
            ], JSON_UNESCAPED_UNICODE)
        );

        return back();
    }

    public function deletarComentario(Request $request, $comentarioId)
    {
        $comentario = Comentario::findOrFail($comentarioId);

        if ($this->tarefaEstaEmSprintEncerrada($comentario->tarefa_id)) {
            return back()->withErrors(['comentario' => 'Esta tarefa pertence a uma Sprint encerrada e seus comentários não podem ser removidos.']);
        }

        $role = session('user_type', 'aluno');
        $userId = session('user_id');

        // Verificar autoria
        if ($role === 'aluno' && $comentario->aluno_id != $userId) {
            return back()->withErrors(['comentario' => 'Você só pode deletar seus próprios comentários.']);
        }
        if ($role === 'professor' && $comentario->prof_id != $userId) {
            return back()->withErrors(['comentario' => 'Você só pode deletar seus próprios comentários.']);
        }

        $tarefaId    = $comentario->tarefa_id;
        $textoApagado = $comentario->texto;

        $comentario->delete();

        $this->registrarHistorico(
            $tarefaId,
            'comentario',
            "Comentário removido pelo autor.",
            json_encode([
                'texto_apagado' => $textoApagado,
            ], JSON_UNESCAPED_UNICODE)
        );

        return back();
    }

    public function deletarAnexo(Request $request, $anexoId)
    {
        $anexo = Anexo::findOrFail($anexoId);

        if ($this->tarefaEstaEmSprintEncerrada($anexo->tarefa_id)) {
            return back()->withErrors(['anexo' => 'Esta tarefa pertence a uma Sprint encerrada e seus anexos não podem ser removidos.']);
        }

        $role = session('user_type', 'aluno');
        $userId = session('user_id');

        // Verificar autoria
        if ($role === 'aluno' && $anexo->aluno_id != $userId) {
            return back()->withErrors(['anexo' => 'Você só pode deletar seus próprios anexos.']);
        }
        if ($role === 'professor' && $anexo->prof_id != $userId) {
            return back()->withErrors(['anexo' => 'Você só pode deletar seus próprios anexos.']);
        }

        $tarefaId    = $anexo->tarefa_id;
        $nomeArquivo = $anexo->nome_original;

        // Remover arquivo do storage
        Storage::disk('public')->delete($anexo->caminho);
        $anexo->delete();

        $this->registrarHistorico(
            $tarefaId,
            'anexo',
            "Anexo removido pelo autor: '{$nomeArquivo}'."
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

        $request->validate([
            'tarefas_ids' => 'required|array',
            'bimestre' => 'required|integer|between:1,4'
        ]);
        $tarefasIds = $request->input('tarefas_ids');
        $bimestre = (int) $request->input('bimestre');

        $ultimaSprint = Sprint::where('equipe_id', $equipeId)->max('sequencia') ?? 0;

        // Se for a primeira sprint e foi informada uma sequência inicial, usa ela
        if ($ultimaSprint === 0 && $request->filled('sequencia_inicial')) {
            $proximaSequencia = max(1, (int) $request->input('sequencia_inicial'));
        } else {
            $proximaSequencia = $ultimaSprint + 1;
        }

        DB::transaction(function () use ($equipeId, $proximaSequencia, $bimestre, $tarefasIds) {
            $novaSprint = Sprint::create([
                'equipe_id' => $equipeId,
                'sequencia' => $proximaSequencia,
                'bimestre'  => $bimestre,
                'dt_inicio' => date('Y-m-d'),
                'dt_fim'    => date('Y-m-d', strtotime('+15 days')),
                'percentual' => 0.00,
                'encerrada' => false
            ]);

            $colunas = Coluna::where('equipe_id', $equipeId)->orderBy('sequencia')->get();
            if ($colunas->isEmpty()) {
                $c1 = Coluna::create(['titulo' => 'A FAZER', 'sequencia' => 1, 'equipe_id' => $equipeId, 'concluido' => false]);
                $c2 = Coluna::create(['titulo' => 'FAZENDO', 'sequencia' => 2, 'equipe_id' => $equipeId, 'concluido' => false]);
                $c3 = Coluna::create(['titulo' => 'EM TESTE', 'sequencia' => 3, 'equipe_id' => $equipeId, 'concluido' => false]);
                $c4 = Coluna::create(['titulo' => 'CONCLUÍDO', 'sequencia' => 4, 'equipe_id' => $equipeId, 'concluido' => true]);
                $colunas = collect([$c1, $c2, $c3, $c4]);
            }

            $primeiroColSprintId = null;
            foreach ($colunas as $col) {
                $cs = ColSprint::create(['coluna_id' => $col->id, 'sprint_id' => $novaSprint->id]);
                if ($col->titulo === 'A FAZER' || ($primeiroColSprintId === null && $col->sequencia == 1)) {
                    $primeiroColSprintId = $cs->id;
                }
            }

            // Fallback caso a coluna 'A FAZER' por algum motivo não tenha sido mapeada
            if (!$primeiroColSprintId && $colunas->isNotEmpty()) {
                $primeiraCol = ColSprint::where('sprint_id', $novaSprint->id)->first();
                $primeiroColSprintId = $primeiraCol ? $primeiraCol->id : null;
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
                'dt_fim'   => date('Y-m-d'),
                'feedback' => $request->input('feedback'),
                'encerrada' => true
            ]);

            // Salva avaliação global da sprint se fornecida
            if ($request->has('avaliacao_sprint') && is_array($request->input('avaliacao_sprint'))) {
                $avSprintData = $request->input('avaliacao_sprint');
                AvaliacaoSprint::updateOrCreate(
                    ['sprint_id' => $sprintAtual->id],
                    [
                        'entrega_valor'     => $avSprintData['entrega_valor'] ?? null,
                        'qualidade_tecnica' => $avSprintData['qualidade_tecnica'] ?? null,
                        'processos_rituais' => $avSprintData['processos_rituais'] ?? null,
                        'documentacao'      => $avSprintData['documentacao'] ?? null,
                        'observacoes'       => $avSprintData['observacoes'] ?? null,
                    ]
                );
            }

            // Salva avaliações individuais dos alunos se fornecidas
            if ($request->has('avaliacoes_individuais') && is_array($request->input('avaliacoes_individuais'))) {
                foreach ($request->input('avaliacoes_individuais') as $avInd) {
                    if (isset($avInd['aluno_id'])) {
                        AvaliacaoIndividual::updateOrCreate(
                            [
                                'sprint_id' => $sprintAtual->id,
                                'aluno_id'  => $avInd['aluno_id']
                            ],
                            [
                                'rituais'     => $avInd['rituais'] ?? null,
                                'tarefas'     => $avInd['tarefas'] ?? null,
                                'postura'     => $avInd['postura'] ?? null,
                                'observacoes' => $avInd['observacoes'] ?? null,
                            ]
                        );
                    }
                }
            }

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
                    "Sprint {$sprintAtual->sequencia} encerrada com pendência: Tarefa mantida no histórico da Sprint {$sprintAtual->sequencia} e devolvida para o Backlog."
                );
            }
        });

        return back();
    }

    /**
     * Endpoint para gerar sugestão de avaliação via Gemini AI com insumo qualitativo do professor
     */
    public function sugerirAvaliacao(Request $request, $sprintId, GeminiAvaliacaoService $geminiService)
    {
        $contextoProfessor = $request->input('contexto_professor');
        $sugestao = $geminiService->gerarSugestaoAvaliacao((int) $sprintId, $contextoProfessor);
        return response()->json($sugestao);
    }

    // =========================================================================
    // GERENCIAMENTO DE COLUNAS (Tech Leader / Orientador)
    // =========================================================================

    /**
     * Valida que a lista de colunas obedece à ordem obrigatória das padrões.
     * Retorna null se válida ou uma mensagem de erro.
     */
    private function validarOrdemColunas(array $colunas): ?string
    {
        $prevOrdem = -1;
        $ultimoTitulo = end($colunas)['titulo'] ?? null;

        if ($ultimoTitulo !== self::COLUNA_CONCLUIDO) {
            return 'A coluna “CONCLUÍDO” deve ser sempre a última.';
        }

        foreach ($colunas as $col) {
            if (!array_key_exists($col['titulo'], self::ORDEM_PADRAO)) continue;
            $ordemAtual = self::ORDEM_PADRAO[$col['titulo']];
            if ($ordemAtual <= $prevOrdem) {
                return 'A ordem das colunas padrão deve ser: A FAZER → FAZENDO → EM TESTE → CONCLUÍDO (podem haver colunas customizadas entre elas).';
            }
            $prevOrdem = $ordemAtual;
        }

        return null;
    }

    public function criarColuna(Request $request, $equipeId)
    {
        $equipe = Equipe::findOrFail($equipeId);
        $role       = session('user_type', 'aluno');
        $alunoPapel = session('papel', session('aluno_papel'));
        $userId     = session('user_id');

        $isOrientador     = ($role === 'professor' && $userId == $equipe->prof_id);
        $isTL             = ($role === 'aluno'     && $alunoPapel === 'TL');
        $canManageColunas = $isOrientador || $isTL;

        if (!$canManageColunas) {
            return back()->withErrors(['coluna' => 'Apenas o Tech Leader ou o orientador podem criar colunas.']);
        }

        $request->validate(['titulo' => 'required|string|max:100']);
        $titulo = strtoupper(trim($request->input('titulo')));

        if (in_array($titulo, self::COLUNAS_PADRAO)) {
            return back()->withErrors(['coluna' => 'Esse nome é reservado para uma coluna padrão.']);
        }

        // Inserir antes de CONCLUÍDO (que sempre fica por último)
        $concluido = Coluna::where('equipe_id', $equipeId)
            ->where('titulo', self::COLUNA_CONCLUIDO)
            ->first();

        $seqConcluido = $concluido ? $concluido->sequencia : 999;

        // Empurrar CONCLUÍDO e qualquer coluna após a posição de inserção
        Coluna::where('equipe_id', $equipeId)
            ->where('sequencia', '>=', $seqConcluido)
            ->increment('sequencia');

        $novaColuna = Coluna::create([
            'titulo'     => $titulo,
            'sequencia'  => $seqConcluido,   // ocupa o slot antes de CONCLUÍDO
            'equipe_id'  => $equipeId,
            'concluido'  => false,
        ]);

        // Adicionar à sprint ativa (se houver)
        $sprintAtiva = Sprint::where('equipe_id', $equipeId)->where('encerrada', false)->first();
        if ($sprintAtiva) {
            ColSprint::create([
                'coluna_id' => $novaColuna->id,
                'sprint_id' => $sprintAtiva->id,
            ]);
        }

        return back();
    }

    public function deletarColuna(Request $request, $equipeId, $colunaId)
    {
        $equipe = Equipe::findOrFail($equipeId);
        $role       = session('user_type', 'aluno');
        $alunoPapel = session('papel', session('aluno_papel'));
        $userId     = session('user_id');

        $isOrientador     = ($role === 'professor' && $userId == $equipe->prof_id);
        $isTL             = ($role === 'aluno'     && $alunoPapel === 'TL');
        $canManageColunas = $isOrientador || $isTL;

        if (!$canManageColunas) {
            return back()->withErrors(['coluna' => 'Acesso negado.']);
        }

        $coluna = Coluna::where('id', $colunaId)->where('equipe_id', $equipeId)->firstOrFail();

        if (in_array($coluna->titulo, self::COLUNAS_PADRAO)) {
            return back()->withErrors(['coluna' => 'Não é possível remover colunas padrão.']);
        }

        // Verificar se possui tarefas em qualquer sprint
        $colsprintIds = ColSprint::where('coluna_id', $colunaId)->pluck('id');
        $temTarefas   = TarefaColuna::whereIn('colsprint_id', $colsprintIds)->exists();
        if ($temTarefas) {
            return back()->withErrors(['coluna' => 'Não é possível remover uma coluna que ainda possui tarefas. Mova-as primeiro.']);
        }

        // Remover entradas nas sprints
        ColSprint::where('coluna_id', $colunaId)->delete();
        $coluna->delete();

        return back();
    }

    public function reordenarColunas(Request $request, $equipeId)
    {
        $equipe = Equipe::findOrFail($equipeId);
        $role       = session('user_type', 'aluno');
        $alunoPapel = session('papel', session('aluno_papel'));
        $userId     = session('user_id');

        $isOrientador     = ($role === 'professor' && $userId == $equipe->prof_id);
        $isTL             = ($role === 'aluno'     && $alunoPapel === 'TL');
        $canManageColunas = $isOrientador || $isTL;

        if (!$canManageColunas) {
            return back()->withErrors(['coluna' => 'Acesso negado.']);
        }

        $request->validate([
            'ordem'           => 'required|array',
            'ordem.*.id'      => 'required|integer',
            'ordem.*.titulo'  => 'required|string',
        ]);

        // Validar restrições de ordem
        $erro = $this->validarOrdemColunas($request->input('ordem'));
        if ($erro) {
            return back()->withErrors(['coluna' => $erro]);
        }

        DB::transaction(function () use ($request, $equipeId) {
            foreach ($request->input('ordem') as $index => $item) {
                Coluna::where('id', $item['id'])
                    ->where('equipe_id', $equipeId)
                    ->update(['sequencia' => $index + 1]);
            }
        });

        return back();
    }
}
