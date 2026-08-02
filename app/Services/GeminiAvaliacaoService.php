<?php

namespace App\Services;

use App\Models\Sprint;
use App\Models\Equipe;
use App\Models\TarefaColuna;
use App\Models\ColSprint;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiAvaliacaoService
{
    /**
     * Gera uma sugestão de avaliação automatizada baseada nos dados e métricas da Sprint.
     */
    /**
     * Gera uma sugestão de avaliação automatizada baseada nos dados, métricas da Sprint e relato comportamental do orientador.
     */
    public function gerarSugestaoAvaliacao(int $sprintId, ?string $contextoProfessor = null): array
    {
        $sprint = Sprint::with(['equipe.alunos'])->findOrFail($sprintId);
        $equipe = $sprint->equipe;
        $alunos = $equipe->alunos;

        // Colunas da sprint
        $colunasSprint = ColSprint::with('coluna')
            ->where('sprint_id', $sprintId)
            ->get();

        // Tarefas da sprint
        $tarefasColuna = TarefaColuna::with([
            'tarefa.responsaveis',
            'tarefa.comentarios.aluno',
            'tarefa.comentarios.professor',
            'tarefa.anexos',
            'tarefa.historicos'
        ])
        ->where('sprint_id', $sprintId)
        ->get();

        $dadosTarefas = [];
        $totalTarefas = $tarefasColuna->count();
        $concluidasCount = 0;

        foreach ($tarefasColuna as $tc) {
            $tarefa = $tc->tarefa;
            $colSprint = $colunasSprint->firstWhere('id', $tc->colsprint_id);
            $isConcluido = $colSprint && $colSprint->coluna && $colSprint->coluna->concluido;
            if ($isConcluido) {
                $concluidasCount++;
            }

            $responsaveis = $tarefa->responsaveis->pluck('nome', 'id')->toArray();
            
            // Comentários dos professores na tarefa
            $comentariosProfessores = $tarefa->comentarios
                ->filter(fn($c) => !empty($c->prof_id))
                ->map(fn($c) => [
                    'professor' => $c->professor?->nome ?? 'Orientador',
                    'texto' => $c->texto
                ])->values()->toArray();

            // Comentários dos alunos na tarefa
            $comentariosAlunos = $tarefa->comentarios
                ->filter(fn($c) => !empty($c->aluno_id))
                ->map(fn($c) => [
                    'aluno' => $c->aluno?->nome ?? 'Aluno',
                    'texto' => $c->texto
                ])->values()->toArray();

            // Histórico auditável de movimentações e ações na tarefa
            $historicoAcoes = $tarefa->historicos
                ->take(10)
                ->map(fn($h) => [
                    'autor' => $h->aluno?->nome ?? $h->professor?->nome ?? 'Sistema',
                    'acao' => $h->descricao,
                    'data' => $h->created_at ? $h->created_at->format('d/m/Y H:i') : null
                ])->values()->toArray();

            $dadosTarefas[] = [
                'id' => $tarefa->id,
                'titulo' => $tarefa->titulo,
                'coluna_atual' => $colSprint->coluna->titulo ?? 'Desconhecida',
                'concluida' => $isConcluido,
                'responsaveis' => $responsaveis,
                'comentarios_professores' => $comentariosProfessores,
                'comentarios_alunos' => $comentariosAlunos,
                'historico_movimentacoes' => $historicoAcoes,
                'total_anexos_doc' => $tarefa->anexos->count(),
            ];
        }

        $percentualConclusao = $totalTarefas > 0 ? round(($concluidasCount / $totalTarefas) * 100, 1) : 0;

        // Coleta de dados individuais dos alunos
        $dadosAlunos = [];
        foreach ($alunos as $aluno) {
            $tarefasAssumidas = 0;
            $tarefasConcluidasAluno = 0;
            $comentariosFeitos = [];
            $anexosEnviados = 0;
            $historicoAcoesAluno = [];

            foreach ($tarefasColuna as $tc) {
                $t = $tc->tarefa;
                $colSprint = $colunasSprint->firstWhere('id', $tc->colsprint_id);
                $isConcluido = $colSprint && $colSprint->coluna && $colSprint->coluna->concluido;

                if ($t->responsaveis->contains('id', $aluno->id)) {
                    $tarefasAssumidas++;
                    if ($isConcluido) {
                        $tarefasConcluidasAluno++;
                    }
                }

                // Coletar textos reais dos comentários do aluno
                foreach ($t->comentarios->where('aluno_id', $aluno->id) as $com) {
                    $comentariosFeitos[] = "Na tarefa «{$t->titulo}»: \"{$com->texto}\"";
                }

                // Coletar histórico de ações do aluno
                foreach ($t->historicos->where('aluno_id', $aluno->id)->take(5) as $hist) {
                    $historicoAcoesAluno[] = "Na tarefa «{$t->titulo}»: {$hist->descricao}";
                }

                $anexosEnviados += $t->anexos->where('aluno_id', $aluno->id)->count();
            }

            $dadosAlunos[] = [
                'aluno_id' => $aluno->id,
                'nome' => $aluno->nome,
                'papel' => $aluno->papel ?? 'Integrante',
                'tarefas_assumidas' => $tarefasAssumidas,
                'tarefas_concluidas' => $tarefasConcluidasAluno,
                'comentarios_interacoes_textos' => $comentariosFeitos,
                'historico_movimentacoes_aluno' => $historicoAcoesAluno,
                'documentos_anexados' => $anexosEnviados
            ];
        }

        $promptData = [
            'sprint' => [
                'id' => $sprint->id,
                'sequencia' => $sprint->sequencia,
                'bimestre' => $sprint->bimestre ?? 1,
                'dt_inicio' => $sprint->dt_inicio,
                'dt_fim' => $sprint->dt_fim,
                'total_tarefas' => $totalTarefas,
                'tarefas_concluidas' => $concluidasCount,
                'percentual_conclusao' => $percentualConclusao . '%'
            ],
            'equipe' => [
                'nome' => $equipe->nome,
                'projeto' => $equipe->projeto
            ],
            'observacoes_relato_professor_interpessoal' => $contextoProfessor ?: 'Nenhuma observação extra relatada.',
            'tarefas' => $dadosTarefas,
            'alunos' => $dadosAlunos
        ];

        return $this->chamarGeminiApi($promptData, $sprintId, $dadosAlunos, $percentualConclusao, $contextoProfessor);
    }

    private function chamarGeminiApi(array $promptData, int $sprintId, array $dadosAlunos, float $percentualConclusao, ?string $contextoProfessor = null): array
    {
        $apiKey = env('GEMINI_API_KEY');

        $systemPrompt = "Atue como um assistente de avaliação técnica e pedagógica para um colégio técnico. "
            . "Analise os dados reais de desempenho de uma Sprint e leve em consideração OBRIGATORIAMENTE o relato contextual do professor orientador sobre relacionamento interpessoal, comportamento em sala de aula e dinâmica de equipe fornecido a seguir.\n"
            . "Pondere fortemente esses fatores qualitativos do professor para ajustar com precisão as notas de Postura, Rituais e observações individuais dos alunos.\n\n"
            . "Retorne EXCLUSIVAMENTE um objeto JSON válido, sem formatação markdown ou textos adicionais fora do JSON, contendo a seguinte estrutura exata:\n"
            . "{\n"
            . '  "entrega_valor": number, // nota de 0.0 a 10.0 baseada em % de conclusao e entregas' . "\n"
            . '  "qualidade_tecnica": number, // nota de 0.0 a 10.0' . "\n"
            . '  "processos_rituais": number, // nota de 0.0 a 10.0 baseada na organizacao e comentarios' . "\n"
            . '  "documentacao": number, // nota de 0.0 a 10.0 baseada nos anexos e especificacoes' . "\n"
            . '  "observacoes": "string explicativa sintetizando os pontos fortes e fracos da sprint",' . "\n"
            . '  "avaliacoes_individuais": [' . "\n"
            . "    {\n"
            . '      "aluno_id": integer,' . "\n"
            . '      "rituais": number, // nota de 0.0 a 10.0' . "\n"
            . '      "tarefas": number, // nota de 0.0 a 10.0' . "\n"
            . '      "postura": number, // nota de 0.0 a 10.0' . "\n"
            . '      "observacoes": "string com justificativa sucinta do aluno levando em conta a atuacao tecnica e comportamental"' . "\n"
            . "    }\n"
            . "  ]\n"
            . "}\n\n"
            . "Dados da Sprint & Relato do Professor:\n" . json_encode($promptData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if (!empty($apiKey) && $apiKey !== 'sua_chave_gerada_aqui') {
            try {
                // Tenta via Facade da biblioteca Gemini
                $result = Gemini::generativeModel('gemini-1.5-flash')->generateContent($systemPrompt);
                $rawText = $result->text();
                
                $cleanedJson = trim(preg_replace('/^```(?:json)?|```$/m', '', $rawText));
                $decoded = json_decode($cleanedJson, true);

                if (is_array($decoded) && isset($decoded['entrega_valor'])) {
                    return $decoded;
                }
            } catch (\Throwable $e) {
                Log::warning("Chamada Facade Gemini falhou, tentando requisição REST: " . $e->getMessage());
                try {
                    $response = Http::withHeaders([
                        'Content-Type' => 'application/json'
                    ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$apiKey}", [
                        'contents' => [
                            ['parts' => [['text' => $systemPrompt]]]
                        ],
                        'generationConfig' => [
                            'responseMimeType' => 'application/json',
                            'temperature' => 0.2
                        ]
                    ]);

                    if ($response->successful()) {
                        $resData = $response->json();
                        $rawText = $resData['candidates'][0]['content']['parts'][0]['text'] ?? '';
                        $cleanedJson = trim(preg_replace('/^```(?:json)?|```$/m', '', $rawText));
                        $decoded = json_decode($cleanedJson, true);

                        if (is_array($decoded) && isset($decoded['entrega_valor'])) {
                            return $decoded;
                        }
                    }
                } catch (\Throwable $ex) {
                    Log::error("Erro no fallback REST do Gemini: " . $ex->getMessage());
                }
            }
        }

        // Fallback heurístico para garantir resposta imediata caso a API Gemini não responda
        return $this->gerarAvaliacaoFallback($dadosAlunos, $percentualConclusao);
    }

    private function gerarAvaliacaoFallback(array $dadosAlunos, float $percentualConclusao): array
    {
        $baseNota = round(min(10.0, max(4.0, ($percentualConclusao / 10))), 1);

        $avaliacoesIndividuais = [];
        foreach ($dadosAlunos as $a) {
            $totalInteracoes = count($a['comentarios_interacoes_textos'] ?? []);
            $notaTarefas = $a['tarefas_assumidas'] > 0 
                ? round(min(10.0, max(5.0, ($a['tarefas_concluidas'] / $a['tarefas_assumidas']) * 10)), 1)
                : 7.0;

            $notaRituais = round(min(10.0, max(6.0, 7.0 + ($totalInteracoes * 0.5))), 1);
            $notaPostura = 8.5;

            $avaliacoesIndividuais[] = [
                'aluno_id' => $a['aluno_id'],
                'rituais' => $notaRituais,
                'tarefas' => $notaTarefas,
                'postura' => $notaPostura,
                'observacoes' => "Aluno {$a['nome']} ({$a['papel']}) concluiu {$a['tarefas_concluidas']} de {$a['tarefas_assumidas']} tarefas e registrou {$totalInteracoes} interações."
            ];
        }

        return [
            'entrega_valor' => $baseNota,
            'qualidade_tecnica' => round(min(10.0, $baseNota + 0.5), 1),
            'processos_rituais' => round(min(10.0, $baseNota), 1),
            'documentacao' => round(min(10.0, $baseNota - 0.5), 1),
            'observacoes' => "Sprint concluída com {$percentualConclusao}% das tarefas finalizadas.",
            'avaliacoes_individuais' => $avaliacoesIndividuais
        ];
    }

    /**
     * Gera o resumo de desempenho pedagógico de um aluno em um determinado bimestre (Fase 5).
     */
    public function gerarResumoAlunoBimestre(object $aluno, int $bimestre, array $sprintsDetalhadas): string
    {
        $apiKey = env('GEMINI_API_KEY');

        $prompt = "Atue como um orientador e coordenador pedagógico sênior de um colégio técnico de excelência.\n"
            . "Sua tarefa é escrever um parecer pedagógico HIPER-PERSONALIZADO, humano e analítico sobre o desempenho real do aluno durante o {$bimestre}º Bimestre.\n\n"
            . "MUITO IMPORTANTE - REGRAS RÍGIDAS:\n"
            . "1. NUNCA use frases genéricas ou clichês acadêmicos padrão (como 'mantendo boa dedicação aos rituais').\n"
            . "2. Cite OBRIGATORIAMENTE o papel do aluno (" . ($aluno->papel ?? 'Integrante') . ") e cite os NOMES EXATOS das tarefas atribuídas a ele que constam no JSON fornecido.\n"
            . "3. Analise as notas e observações comportamentais dadas pelo orientador (rituais, postura e tarefas) de forma direta.\n"
            . "4. Estruture o texto em um parecer coeso de 3 a 5 frases fluidas destacando: Entregas Técnicas Específicas, Desempenho Atitudinal/Comportamental e Recomendação Prática de Desenvolvimento.\n\n"
            . "Dados do Aluno:\n"
            . "Nome: {$aluno->nome}\n"
            . "Papel: " . ($aluno->papel ?? 'Integrante') . "\n"
            . "Sprints & Tarefas do Bimestre: " . json_encode($sprintsDetalhadas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if (!empty($apiKey) && $apiKey !== 'sua_chave_gerada_aqui') {
            try {
                $result = Gemini::generativeModel('gemini-1.5-flash')->generateContent($prompt);
                $text = trim($result->text());
                if (!empty($text)) {
                    return $text;
                }
            } catch (\Throwable $e) {
                Log::warning("Chamada Facade Gemini para resumo do aluno falhou: " . $e->getMessage());
                try {
                    $response = Http::withHeaders(['Content-Type' => 'application/json'])
                        ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}", [
                            'contents' => [['parts' => [['text' => $prompt]]]]
                        ]);
                    if ($response->successful()) {
                        $resData = $response->json();
                        $text = trim($resData['candidates'][0]['content']['parts'][0]['text'] ?? '');
                        if (!empty($text)) {
                            return $text;
                        }
                    }
                } catch (\Throwable $ex) {
                    Log::error("Erro REST no resumo do aluno Gemini: " . $ex->getMessage());
                }
            }
        }

        // Fallback textual dinâmico focado nas tarefas e notas reais do aluno
        $todasTarefas = [];
        $obsProf = [];
        $totalConcluidas = 0;

        foreach ($sprintsDetalhadas as $sp) {
            if (!empty($sp['tarefas_assumidas_pelo_aluno']) && is_array($sp['tarefas_assumidas_pelo_aluno'])) {
                foreach ($sp['tarefas_assumidas_pelo_aluno'] as $t) {
                    $todasTarefas[] = "«{$t['titulo']}» (" . ($t['concluida'] ? 'Concluída' : 'Em andamento') . ")";
                    if ($t['concluida']) $totalConcluidas++;
                }
            }
            if (!empty($sp['avaliacao_individual']['observacoes_professor'])) {
                $obsProf[] = $sp['avaliacao_individual']['observacoes_professor'];
            }
        }

        $papelStr = $aluno->papel ?? 'Integrante';
        $listaTarefasStr = !empty($todasTarefas) ? implode(', ', array_slice($todasTarefas, 0, 3)) : 'atividades do backlog';
        $obsStr = !empty($obsProf) ? ' Nota do orientador: "' . implode('; ', $obsProf) . '".' : '';

        return "No {$bimestre}º Bimestre, {$aluno->nome} atuou como {$papelStr} da equipe. Foi responsável por {$listaTarefasStr}, totalizando {$totalConcluidas} entrega(s) concluída(s).{$obsStr} Recomendação: Manter a consistência na atualização diária do quadro e focar na expansão das competências técnicas da equipe.";
    }
}
