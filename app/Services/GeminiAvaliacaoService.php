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
            'INSTRUCAO_CRITICA_DO_PROFESSOR_ORIENTADOR' => $contextoProfessor ?: 'Nenhuma observação extra.',
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
            'alunos' => $dadosAlunos,
            'tarefas' => $dadosTarefas
        ];

        return $this->chamarGeminiApi($promptData, $sprintId, $dadosAlunos, $percentualConclusao, $contextoProfessor);
    }

    private function chamarGeminiApi(array $promptData, int $sprintId, array $dadosAlunos, float $percentualConclusao, ?string $contextoProfessor = null): array
    {
        $apiKey = env('GEMINI_API_KEY');

        $systemPrompt = "ATENÇÃO: Você é um sistema de atribuição de notas. Sua principal tarefa é AJUSTAR AS NOTAS NUMÉRICAS com base no relato do professor abaixo.\n\n"
            . "REGRAS MANDATÓRIAS PARA AS NOTAS NUMÉRICAS (0.0 a 10.0):\n"
            . "1. Se o campo 'RELATO_DO_PROFESSOR_ORIENTADOR' contiver uma MENÇÃO A NOTA ESPECÍFICA (ex: 'nota 4 para Fulano', 'dar 5 para Beltrano', 'nota 2', 'zerar', 'descontar 3 pontos'), VOCÊ DEVE DEFINIR AS NOTAS NUMÉRICAS DESTE ALUNO EXATAMENTE COM O VALOR SOLICITADO PELO PROFESSOR!\n"
            . "2. Se o relato contiver reclamação ou crítica sobre um aluno (ex: 'faltou', 'veio pouco', 'não produziu', 'atrasado', 'conflito', 'desatento', 'não ajudou'), as notas de 'postura', 'rituais' e 'tarefas' DESTE aluno DEVEM SER BAIXAS (valores entre 1.0 e 5.0). JAMAIS atribua nota 8, 9 ou 10 para um aluno criticado pelo professor!\n"
            . "3. Se o relato contiver elogios para um aluno (ex: 'destaque', 'excelente', 'liderou', 'parabéns'), as notas de 'postura', 'rituais' e 'tarefas' DESTE aluno DEVEM SER ALTAS (valores entre 9.0 e 10.0).\n"
            . "4. Se o relato contiver crítica ou elogio geral para a equipe inteira (ex: 'grupo ruim', 'equipe desfocada', 'ótima entrega'), altere as notas da sprint ('entrega_valor', 'qualidade_tecnica', 'processos_rituais', 'documentacao') para refletir o parecer.\n"
            . "5. REGRA DE PRIVACIDADE: O campo 'observacoes' de cada aluno deve conter o motivo da sua nota sem citar nomes dos outros colegas de classe.\n\n"
            . "Retorne EXCLUSIVAMENTE um objeto JSON válido (sem qualquer bloco markdown ```json):\n"
            . "{\n"
            . '  "entrega_valor": number,' . "\n"
            . '  "qualidade_tecnica": number,' . "\n"
            . '  "processos_rituais": number,' . "\n"
            . '  "documentacao": number,' . "\n"
            . '  "observacoes": "resumo geral da sprint levando em conta o relato",' . "\n"
            . '  "avaliacoes_individuais": [' . "\n"
            . "    {\n"
            . '      "aluno_id": integer,' . "\n"
            . '      "rituais": number,' . "\n"
            . '      "tarefas": number,' . "\n"
            . '      "postura": number,' . "\n"
            . '      "observacoes": "justificativa do aluno levando em conta a instrucao do professor"' . "\n"
            . "    }\n"
            . "  ]\n"
            . "}\n\n"
            . "RELATO_DO_PROFESSOR_ORIENTADOR: \"" . ($contextoProfessor ?: 'Sem observações extras') . "\"\n\n"
            . "DADOS DA SPRINT E ALUNOS:\n" . json_encode($promptData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

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
        return $this->gerarAvaliacaoFallback($dadosAlunos, $percentualConclusao, $contextoProfessor);
    }

    private function gerarAvaliacaoFallback(array $dadosAlunos, float $percentualConclusao, ?string $contextoProfessor = null): array
    {
        $baseNota = round(min(10.0, max(4.0, ($percentualConclusao / 10))), 1);
        $temContexto = !empty(trim($contextoProfessor ?? ''));

        $avaliacoesIndividuais = [];
        foreach ($dadosAlunos as $a) {
            $totalInteracoes = count($a['comentarios_interacoes_textos'] ?? []);
            $notaTarefas = $a['tarefas_assumidas'] > 0 
                ? round(min(10.0, max(5.0, ($a['tarefas_concluidas'] / $a['tarefas_assumidas']) * 10)), 1)
                : 7.0;

            $notaRituais = round(min(10.0, max(6.0, 7.0 + ($totalInteracoes * 0.5))), 1);
            $notaPostura = 8.5;
            $obsAluno = "Aluno {$a['nome']} ({$a['papel']}) concluiu {$a['tarefas_concluidas']} de {$a['tarefas_assumidas']} tarefas.";

            // Se o orientador forneceu observações qualitativas no relato, analisa menções a ESTE aluno ou orientações gerais
            if ($temContexto) {
                $primeiroNome = explode(' ', $a['nome'])[0];
                $frases = preg_split('/[.;\n]+/', $contextoProfessor);
                $frasesDoAluno = [];

                foreach ($frases as $frase) {
                    $fraseTrim = trim($frase);
                    if (empty($fraseTrim)) continue;

                    if (stripos($fraseTrim, $primeiroNome) !== false || stripos($fraseTrim, $a['nome']) !== false) {
                        $frasesDoAluno[] = $fraseTrim;
                    }
                }

                $textoAnalise = !empty($frasesDoAluno) ? implode('. ', $frasesDoAluno) : $contextoProfessor;
                if (!empty($frasesDoAluno)) {
                    $obsAluno .= " Parecer do orientador: \"{$textoAnalise}\".";
                }

                // 1. Verifica se há pedido explícito de nota numérica no texto para o aluno
                if (preg_match('/(?:nota|dar|atribuir|ficou com)\s*([0-9]+(?:\.[0-9]+)?)/i', $textoAnalise, $matchNota)) {
                    $valNota = floatval($matchNota[1]);
                    $valNota = min(10.0, max(0.0, $valNota));
                    $notaPostura = $valNota;
                    $notaRituais = $valNota;
                    $notaTarefas = $valNota;
                } elseif (preg_match('/(zerar|zero|sem nota)/i', $textoAnalise)) {
                    $notaPostura = 0.0;
                    $notaRituais = 0.0;
                    $notaTarefas = 0.0;
                } else {
                    // 2. Ajuste de notas com base na análise de palavras-chave / relato do professor
                    if (preg_match('/(ausente|conflito|atraso|dificuldade|desatento|faltou|ruim|baixo|diminu|desconto|prejudic|não fez|nao fez|fraco|mal)/i', $textoAnalise)) {
                        $notaPostura = max(2.0, $notaPostura - 4.0);
                        $notaRituais = max(2.0, $notaRituais - 3.5);
                        $notaTarefas = max(2.0, $notaTarefas - 3.0);
                    }
                    if (preg_match('/(liderou|proativ|excelente|ajudou|dedicou|destacou|ótimo|otimo|parabéns|parabens|bom|alto|bônus|bonus)/i', $textoAnalise)) {
                        $notaPostura = min(10.0, $notaPostura + 1.5);
                        $notaRituais = min(10.0, $notaRituais + 1.5);
                    }
                }
            }

            $avaliacoesIndividuais[] = [
                'aluno_id' => $a['aluno_id'],
                'rituais' => $notaRituais,
                'tarefas' => $notaTarefas,
                'postura' => $notaPostura,
                'observacoes' => $obsAluno
            ];
        }

        $obsGeral = "Sprint concluída com {$percentualConclusao}% das tarefas finalizadas.";
        $entregaValor = $baseNota;
        $qualidadeTecnica = round(min(10.0, $baseNota + 0.5), 1);
        $processosRituais = round(min(10.0, $baseNota), 1);
        $documentacao = round(min(10.0, $baseNota - 0.5), 1);

        if ($temContexto) {
            $obsGeral .= " Relato pedagógico do orientador: \"{$contextoProfessor}\".";
            if (preg_match('/(ruim|baixo|diminu|desconto|prejudic|fraco|atraso|conflito)/i', $contextoProfessor)) {
                $entregaValor = max(3.0, $entregaValor - 2.0);
                $qualidadeTecnica = max(3.0, $qualidadeTecnica - 2.0);
                $processosRituais = max(3.0, $processosRituais - 2.0);
            }
            if (preg_match('/(excelente|ótimo|otimo|parabéns|parabens|destacou|proativ)/i', $contextoProfessor)) {
                $entregaValor = min(10.0, $entregaValor + 1.0);
                $qualidadeTecnica = min(10.0, $qualidadeTecnica + 1.0);
            }
        }

        return [
            'entrega_valor' => $entregaValor,
            'qualidade_tecnica' => $qualidadeTecnica,
            'processos_rituais' => $processosRituais,
            'documentacao' => $documentacao,
            'observacoes' => $obsGeral,
            'avaliacoes_individuais' => $avaliacoesIndividuais
        ];
    }

    /**
     * Gera o resumo de desempenho pedagógico de um aluno em um determinado bimestre (Fase 5).
     */
    public function gerarResumoAlunoBimestre(object $aluno, int $bimestre, array $sprintsDetalhadas): string
    {
        $apiKey = env('GEMINI_API_KEY');
        $primeiroNome = explode(' ', $aluno->nome)[0];

        // Sanitizar os dados ANTES de enviar ao Gemini: remover menções a outros alunos
        $sprintsSanitizadas = $this->sanitizarSprintsParaAluno($sprintsDetalhadas, $aluno->nome, $primeiroNome);

        // Buscar resumos anteriores aprovados pelo professor (para treinamento de poucas amostras / few-shot learning)
        $exemplosAprovados = \App\Models\ResumoGemini::where('aprovado', true)
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($r) => "- Parecer Aprovado: \"" . ($r->texto_editado ?: $r->texto_resumo) . "\"")
            ->implode("\n");

        $promptExemplos = !empty($exemplosAprovados)
            ? "\n\nEXEMPLOS DE RESUMOS ANTERIORES JÁ REVISADOS E APROVADOS PELO PROFESSOR (Use como modelo de estilo, tom pedagógico e estrutura):\n" . $exemplosAprovados . "\n"
            : "";

        $prompt = "Atue como um orientador e coordenador pedagógico sênior de um colégio técnico de excelência.\n"
            . "Sua tarefa é escrever um parecer pedagógico HIPER-PERSONALIZADO, humano e analítico sobre o desempenho real do aluno durante o {$bimestre}º Bimestre.\n\n"
            . "REGRAS RÍGIDAS E INEGOCIÁVEIS:\n"
            . "1. NUNCA use frases genéricas ou clichês acadêmicos padrão.\n"
            . "2. Cite OBRIGATORIAMENTE o papel do aluno (" . ($aluno->papel ?? 'Integrante') . ") e os NOMES EXATOS das tarefas.\n"
            . "3. REGRA DE PRIVACIDADE: Este parecer é EXCLUSIVAMENTE sobre o aluno {$aluno->nome}. NUNCA mencione nomes, condutas ou avaliações de outros colegas de equipe. Se os dados contiverem referências a outros alunos, IGNORE-AS completamente.\n"
            . "4. Estruture o texto em 3 a 5 frases fluidas: Entregas Técnicas, Desempenho Atitudinal e Recomendação Prática."
            . $promptExemplos . "\n\n"
            . "Dados do Aluno:\n"
            . "Nome: {$aluno->nome}\n"
            . "Papel: " . ($aluno->papel ?? 'Integrante') . "\n"
            . "Sprints & Tarefas do Bimestre: " . json_encode($sprintsSanitizadas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if (!empty($apiKey) && $apiKey !== 'sua_chave_gerada_aqui') {
            try {
                $result = Gemini::generativeModel('gemini-2.0-flash')->generateContent($prompt);
                $text = trim($result->text());
                if (!empty($text)) {
                    return $text;
                }
            } catch (\Throwable $e) {
                Log::warning("Chamada Facade Gemini para resumo do aluno falhou: " . $e->getMessage());
                try {
                    $response = Http::withHeaders(['Content-Type' => 'application/json'])
                        ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$apiKey}", [
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

        foreach ($sprintsSanitizadas as $sp) {
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

        // Construir texto corrido e natural
        $papelStr = $aluno->papel ?? 'Integrante';
        $totalTarefas = count($todasTarefas);
        $totalSprints = count($sprintsSanitizadas);

        // Frase de abertura
        $texto = "Durante o {$bimestre}º Bimestre, {$aluno->nome} exerceu o papel de {$papelStr} da equipe";
        $texto .= $totalSprints > 0 ? ", participando de {$totalSprints} sprint(s). " : ". ";

        // Frase sobre entregas (com nomes reais das tarefas)
        if ($totalTarefas > 0) {
            $nomesTarefas = array_map(function ($t) {
                return $t;
            }, array_slice($todasTarefas, 0, 3));
            $listaNatural = implode(', ', $nomesTarefas);

            if ($totalConcluidas === $totalTarefas) {
                $texto .= "Entregou com sucesso todas as {$totalConcluidas} tarefa(s) sob sua responsabilidade: {$listaNatural}. ";
            } elseif ($totalConcluidas > 0) {
                $texto .= "Concluiu {$totalConcluidas} de {$totalTarefas} tarefa(s) atribuídas, incluindo {$listaNatural}. ";
            } else {
                $texto .= "Teve {$totalTarefas} tarefa(s) atribuída(s) — {$listaNatural} — porém nenhuma foi finalizada neste período. ";
            }
        } else {
            $texto .= "Não teve tarefas diretamente atribuídas neste período, atuando em atividades de suporte à equipe. ";
        }

        // Frase de fechamento contextual
        if ($totalConcluidas > 0 && $totalConcluidas === $totalTarefas) {
            $texto .= "O aproveitamento integral das entregas demonstra comprometimento sólido com os prazos e objetivos da sprint.";
        } elseif ($totalConcluidas > 0) {
            $texto .= "Há espaço para evolução no cumprimento integral dos prazos e na finalização das entregas pendentes.";
        } else {
            $texto .= "Recomenda-se maior protagonismo na execução das tarefas e participação ativa nos rituais da equipe no próximo ciclo.";
        }

        return $texto;
    }

    /**
     * Sanitiza os dados das sprints para remover qualquer menção a outros alunos,
     * preservando apenas os trechos que dizem respeito ao aluno em questão.
     */
    private function sanitizarSprintsParaAluno(array $sprintsDetalhadas, string $nomeCompleto, string $primeiroNome): array
    {
        foreach ($sprintsDetalhadas as &$sp) {
            // Sanitizar observações do professor na avaliação individual
            if (!empty($sp['avaliacao_individual']['observacoes_professor'])) {
                $sp['avaliacao_individual']['observacoes_professor'] = $this->extrairFrasesDoAluno(
                    $sp['avaliacao_individual']['observacoes_professor'],
                    $nomeCompleto,
                    $primeiroNome
                );
            }
            // Sanitizar observações gerais
            if (!empty($sp['avaliacao_individual']['observacoes'])) {
                $sp['avaliacao_individual']['observacoes'] = $this->extrairFrasesDoAluno(
                    $sp['avaliacao_individual']['observacoes'],
                    $nomeCompleto,
                    $primeiroNome
                );
            }
            // Sanitizar feedback geral do professor
            if (!empty($sp['feedback_geral_professor'])) {
                $sp['feedback_geral_professor'] = $this->extrairFrasesDoAluno(
                    $sp['feedback_geral_professor'],
                    $nomeCompleto,
                    $primeiroNome
                );
            }
        }
        return $sprintsDetalhadas;
    }

    /**
     * Extrai apenas as frases de um texto que mencionam o aluno especificado.
     * Frases que mencionam outros nomes ou não mencionam ninguém são preservadas
     * apenas se NÃO contiverem nomes próprios de terceiros.
     */
    private function extrairFrasesDoAluno(string $texto, string $nomeCompleto, string $primeiroNome): string
    {
        $frases = preg_split('/(?<=[.;!?])\s+|[\n]+/', $texto);
        $resultado = [];

        foreach ($frases as $frase) {
            $fraseTrim = trim($frase);
            if (empty($fraseTrim)) continue;

            $mencionaAluno = (stripos($fraseTrim, $primeiroNome) !== false || stripos($fraseTrim, $nomeCompleto) !== false);

            if ($mencionaAluno) {
                $resultado[] = $fraseTrim;
            }
        }

        return !empty($resultado) ? implode(' ', $resultado) : '';
    }
}
