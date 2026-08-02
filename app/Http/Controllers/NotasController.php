<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Equipe;
use App\Models\Aluno;
use App\Models\Sprint;
use App\Models\PesoTurma;
use App\Models\ResumoGemini;
use App\Services\GeminiAvaliacaoService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class NotasController extends Controller
{
    /**
     * Visão do Professor: Quadro de Notas Reativo da Turma
     */
    public function index(Request $request)
    {
        $userRole = session('user_type', 'aluno');
        $userId = session('user_id');

        // Se for aluno, redireciona para a sua tela de acompanhamento
        if ($userRole === 'aluno') {
            return redirect()->route('minhas.notas');
        }

        // Buscar turmas distintas das equipes vinculadas ou disponíveis
        $queryTurmas = Equipe::select('ano', 'turma')->distinct();
        if ($userRole === 'professor') {
            $queryTurmas->where('prof_id', $userId);
        }
        $turmasAtivas = $queryTurmas->orderBy('ano', 'desc')->orderBy('turma', 'asc')->get();

        if ($turmasAtivas->isEmpty()) {
            // Fallback caso não haja filtro cadastrado
            $turmasAtivas = collect([['ano' => date('Y'), 'turma' => '3º Info']]);
        }

        $anoSelecionado = $request->query('ano', $turmasAtivas->first()['ano'] ?? date('Y'));
        $turmaSelecionada = $request->query('turma', $turmasAtivas->first()['turma'] ?? '3º Info');

        // Buscar equipes da turma
        $equipes = Equipe::where('ano', $anoSelecionado)
            ->where('turma', $turmaSelecionada)
            ->get();

        $equipesIds = $equipes->pluck('id');

        // Buscar alunos das equipes da turma
        $alunos = Aluno::with('equipe')
            ->whereIn('equipe_id', $equipesIds)
            ->orderBy('n_chamada', 'asc')
            ->orderBy('nome', 'asc')
            ->get();

        // Buscar Sprints encerradas com suas avaliações
        $sprints = Sprint::with(['avaliacaoSprint', 'avaliacoesIndividuais'])
            ->whereIn('equipe_id', $equipesIds)
            ->where('encerrada', true)
            ->orderBy('bimestre', 'asc')
            ->orderBy('sequencia', 'asc')
            ->get();

        // Buscar pesos salvos para a turma (fallback = 25.0)
        $pesosSalvos = PesoTurma::where('ano', $anoSelecionado)
            ->where('turma', $turmaSelecionada)
            ->pluck('peso', 'bimestre')
            ->toArray();

        $pesos = [
            1 => (float)($pesosSalvos[1] ?? 25.0),
            2 => (float)($pesosSalvos[2] ?? 25.0),
            3 => (float)($pesosSalvos[3] ?? 25.0),
            4 => (float)($pesosSalvos[4] ?? 25.0),
        ];

        // Processar notas consolidadas de cada aluno
        $alunosNotas = $alunos->map(function ($aluno) use ($sprints) {
            $mediasBimestrais = [1 => null, 2 => null, 3 => null, 4 => null];
            $sprintsDetalhadas = [1 => [], 2 => [], 3 => [], 4 => []];

            for ($b = 1; $b <= 4; $b++) {
                // Sprints do aluno no bimestre $b
                $sprintsDoBimestre = $sprints->filter(function ($s) use ($aluno, $b) {
                    return $s->equipe_id == $aluno->equipe_id && ($s->bimestre ?? 1) == $b;
                });

                if ($sprintsDoBimestre->isNotEmpty()) {
                    $somaNotasSprint = 0;
                    $countSprints = 0;

                    foreach ($sprintsDoBimestre as $s) {
                        $avSprint = $s->avaliacaoSprint;
                        $avInd = $s->avaliacoesIndividuais->firstWhere('aluno_id', $aluno->id);

                        // Nota global da Sprint
                        $notaSprintValor = null;
                        if ($avSprint) {
                            $notaSprintValor = ($avSprint->entrega_valor + $avSprint->qualidade_tecnica + $avSprint->processos_rituais + $avSprint->documentacao) / 4;
                        } else {
                            $notaSprintValor = ($s->percentual ?? 0) / 10;
                        }

                        // Nota individual do Aluno
                        $notaIndValor = null;
                        if ($avInd) {
                            $notaIndValor = ($avInd->rituais + $avInd->tarefas + $avInd->postura) / 3;
                        }

                        // Média final da Sprint
                        if ($notaSprintValor !== null && $notaIndValor !== null) {
                            $notaFinalSprint = ($notaSprintValor + $notaIndValor) / 2;
                        } elseif ($notaIndValor !== null) {
                            $notaFinalSprint = $notaIndValor;
                        } else {
                            $notaFinalSprint = $notaSprintValor;
                        }

                        $notaFinalSprintClean = round($notaFinalSprint, 1);
                        $somaNotasSprint += $notaFinalSprintClean;
                        $countSprints++;

                        $sprintsDetalhadas[$b][] = [
                            'sprint_id' => $s->id,
                            'sequencia' => $s->sequencia,
                            'percentual' => $s->percentual,
                            'dt_fim' => $s->dt_fim,
                            'feedback_professor' => $s->feedback,
                            'nota_sprint' => $notaSprintValor !== null ? round($notaSprintValor, 1) : null,
                            'avaliacao_sprint' => $avSprint ? [
                                'entrega_valor' => $avSprint->entrega_valor,
                                'qualidade_tecnica' => $avSprint->qualidade_tecnica,
                                'processos_rituais' => $avSprint->processos_rituais,
                                'documentacao' => $avSprint->documentacao,
                                'observacoes' => $avSprint->observacoes
                            ] : null,
                            'nota_individual' => $notaIndValor !== null ? round($notaIndValor, 1) : null,
                            'avaliacao_individual' => $avInd ? [
                                'rituais' => $avInd->rituais,
                                'tarefas' => $avInd->tarefas,
                                'postura' => $avInd->postura,
                                'observacoes' => $avInd->observacoes
                            ] : null,
                            'nota_consolidada' => $notaFinalSprintClean
                        ];
                    }

                    if ($countSprints > 0) {
                        $mediasBimestrais[$b] = round($somaNotasSprint / $countSprints, 1);
                    }
                }
            }

            return [
                'id' => $aluno->id,
                'nome' => $aluno->nome,
                'email' => $aluno->email,
                'n_chamada' => $aluno->n_chamada,
                'papel' => $aluno->papel,
                'ra' => $aluno->ra,
                'equipe_nome' => $aluno->equipe->nome ?? 'Sem equipe',
                'medias' => $mediasBimestrais,
                'sprints_detalhadas' => $sprintsDetalhadas
            ];
        });

        return Inertia::render('Notas/Index', [
            'turmasAtivas' => $turmasAtivas,
            'turmaSelecionada' => [
                'ano' => $anoSelecionado,
                'turma' => $turmaSelecionada
            ],
            'pesos' => $pesos,
            'alunosNotas' => $alunosNotas
        ]);
    }

    /**
     * Salva as configurações de pesos por bimestre para a turma
     */
    public function salvarPesos(Request $request)
    {
        $request->validate([
            'ano' => 'required|string',
            'turma' => 'required|string',
            'pesos' => 'required|array'
        ]);

        $ano = $request->input('ano');
        $turma = $request->input('turma');
        $pesos = $request->input('pesos');

        foreach ($pesos as $bimestre => $peso) {
            PesoTurma::updateOrCreate(
                [
                    'ano' => $ano,
                    'turma' => $turma,
                    'bimestre' => (int)$bimestre
                ],
                [
                    'peso' => (float)$peso
                ]
            );
        }

        return back();
    }

    /**
     * Endpoint do Gemini: Busca ou gera o resumo no cache resumos_gemini (Fase 2)
     */
    public function gerarResumoGemini(Request $request, $alunoId, $bimestre, GeminiAvaliacaoService $geminiService)
    {
        $aluno = Aluno::with('equipe')->findOrFail($alunoId);
        $regerar = $request->boolean('regerar', false);

        if (!$regerar) {
            $existente = ResumoGemini::where('aluno_id', $alunoId)
                ->where('bimestre', $bimestre)
                ->first();

            if ($existente && !empty($existente->texto_resumo)) {
                return response()->json([
                    'texto_resumo' => $existente->texto_resumo,
                    'is_cached' => true
                ]);
            }
        }

        // Busca sprints do bimestre do aluno
        $sprints = Sprint::with(['avaliacaoSprint', 'avaliacoesIndividuais'])
            ->where('equipe_id', $aluno->equipe_id)
            ->where('encerrada', true)
            ->where('bimestre', $bimestre)
            ->get();

        $sprintsDetalhadas = $sprints->map(function ($s) use ($aluno) {
            return [
                'sprint' => "Sprint {$s.sequencia}",
                'percentual' => "{$s.percentual}%",
                'feedback_professor' => $s->feedback,
                'avaliacao_sprint' => $s->avaliacaoSprint ? [
                    'entrega_valor' => $s->avaliacaoSprint->entrega_valor,
                    'qualidade_tecnica' => $s->avaliacaoSprint->qualidade_tecnica,
                    'processos_rituais' => $s->avaliacaoSprint->processos_rituais,
                    'documentacao' => $s->avaliacaoSprint->documentacao,
                    'observacoes' => $s->avaliacaoSprint->observacoes,
                ] : null,
                'avaliacao_individual' => $s->avaliacoesIndividuais->firstWhere('aluno_id', $aluno->id) ? [
                    'rituais' => $s->avaliacoesIndividuais->firstWhere('aluno_id', $aluno->id)->rituais,
                    'tarefas' => $s->avaliacoesIndividuais->firstWhere('aluno_id', $aluno->id)->tarefas,
                    'postura' => $s->avaliacoesIndividuais->firstWhere('aluno_id', $aluno->id)->postura,
                    'observacoes' => $s->avaliacoesIndividuais->firstWhere('aluno_id', $aluno->id)->observacoes,
                ] : null
            ];
        })->toArray();

        $textoResumo = $geminiService->gerarResumoAlunoBimestre($aluno, (int)$bimestre, $sprintsDetalhadas);

        ResumoGemini::updateOrCreate(
            ['aluno_id' => $alunoId, 'bimestre' => (int)$bimestre],
            ['texto_resumo' => $textoResumo]
        );

        return response()->json([
            'texto_resumo' => $textoResumo,
            'is_cached' => false
        ]);
    }

    /**
     * Visão do Aluno: Dashboard de Transparência
     */
    public function minhasNotas(Request $request)
    {
        $alunoId = session('user_id');
        $aluno = Aluno::with('equipe')->findOrFail($alunoId);
        $equipe = $aluno->equipe;

        $ano = $equipe->ano ?? date('Y');
        $turma = $equipe->turma ?? '3º Info';

        // Pesos da turma
        $pesosSalvos = PesoTurma::where('ano', $ano)
            ->where('turma', $turma)
            ->pluck('peso', 'bimestre')
            ->toArray();

        $pesos = [
            1 => (float)($pesosSalvos[1] ?? 25.0),
            2 => (float)($pesosSalvos[2] ?? 25.0),
            3 => (float)($pesosSalvos[3] ?? 25.0),
            4 => (float)($pesosSalvos[4] ?? 25.0),
        ];

        // Sprints encerradas da equipe do aluno
        $sprints = Sprint::with(['avaliacaoSprint', 'avaliacoesIndividuais'])
            ->where('equipe_id', $aluno->equipe_id)
            ->where('encerrada', true)
            ->orderBy('bimestre', 'asc')
            ->orderBy('sequencia', 'asc')
            ->get();

        // Resumos gravados da IA Gemini por bimestre
        $resumosIa = ResumoGemini::where('aluno_id', $alunoId)
            ->pluck('texto_resumo', 'bimestre')
            ->toArray();

        $bimestresDados = [];

        for ($b = 1; $b <= 4; $b++) {
            $sprintsDoBimestre = $sprints->filter(function ($s) use ($b) {
                return ($s->bimestre ?? 1) == $b;
            });

            $sprintsFormatadas = [];
            $somaNotas = 0;
            $countSprints = 0;

            foreach ($sprintsDoBimestre as $s) {
                $avSprint = $s->avaliacaoSprint;
                $avInd = $s->avaliacoesIndividuais->firstWhere('aluno_id', $alunoId);

                $notaSprintValor = $avSprint 
                    ? ($avSprint->entrega_valor + $avSprint->qualidade_tecnica + $avSprint->processos_rituais + $avSprint->documentacao) / 4
                    : ($s->percentual ?? 0) / 10;

                $notaIndValor = $avInd 
                    ? ($avInd->rituais + $avInd->tarefas + $avInd->postura) / 3
                    : null;

                if ($notaSprintValor !== null && $notaIndValor !== null) {
                    $notaFinal = ($notaSprintValor + $notaIndValor) / 2;
                } elseif ($notaIndValor !== null) {
                    $notaFinal = $notaIndValor;
                } else {
                    $notaFinal = $notaSprintValor;
                }

                $notaFinalClean = round($notaFinal, 1);
                $somaNotas += $notaFinalClean;
                $countSprints++;

                $sprintsFormatadas[] = [
                    'id' => $s->id,
                    'sequencia' => $s->sequencia,
                    'percentual' => $s->percentual,
                    'dt_fim' => $s->dt_fim,
                    'feedback_professor' => $s->feedback,
                    'nota_sprint' => $notaSprintValor !== null ? round($notaSprintValor, 1) : null,
                    'avaliacao_sprint' => $avSprint ? [
                        'entrega_valor' => $avSprint->entrega_valor,
                        'qualidade_tecnica' => $avSprint->qualidade_tecnica,
                        'processos_rituais' => $avSprint->processos_rituais,
                        'documentacao' => $avSprint->documentacao,
                        'observacoes' => $avSprint->observacoes
                    ] : null,
                    'nota_individual' => $notaIndValor !== null ? round($notaIndValor, 1) : null,
                    'avaliacao_individual' => $avInd ? [
                        'rituais' => $avInd->rituais,
                        'tarefas' => $avInd->tarefas,
                        'postura' => $avInd->postura,
                        'observacoes' => $avInd->observacoes
                    ] : null,
                    'nota_consolidada' => $notaFinalClean
                ];
            }

            $mediaBimestre = $countSprints > 0 ? round($somaNotas / $countSprints, 1) : null;

            $bimestresDados[$b] = [
                'bimestre' => $b,
                'peso' => $pesos[$b],
                'media_consolidada' => $mediaBimestre,
                'resumo_ia' => $resumosIa[$b] ?? null,
                'sprints' => $sprintsFormatadas
            ];
        }

        return Inertia::render('Notas/Aluno', [
            'aluno' => [
                'id' => $aluno->id,
                'nome' => $aluno->nome,
                'papel' => $aluno->papel,
                'n_chamada' => $aluno->n_chamada,
                'equipe_nome' => $equipe->nome ?? 'Sem equipe'
            ],
            'turma' => $turma,
            'ano' => $ano,
            'bimestres' => $bimestresDados
        ]);
    }
}
