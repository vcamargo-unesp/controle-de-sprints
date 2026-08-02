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
    public function gerarSugestaoAvaliacao(int $sprintId): array
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
            $comentariosCount = $tarefa->comentarios->count();
            $anexosCount = $tarefa->anexos->count();

            $dadosTarefas[] = [
                'id' => $tarefa->id,
                'titulo' => $tarefa->titulo,
                'coluna_atual' => $colSprint->coluna->titulo ?? 'Desconhecida',
                'concluida' => $isConcluido,
                'responsaveis' => $responsaveis,
                'total_comentarios' => $comentariosCount,
                'total_anexos_doc' => $anexosCount,
            ];
        }

        $percentualConclusao = $totalTarefas > 0 ? round(($concluidasCount / $totalTarefas) * 100, 1) : 0;

        // Coleta de dados individuais dos alunos
        $dadosAlunos = [];
        foreach ($alunos as $aluno) {
            $tarefasAssumidas = 0;
            $tarefasConcluidasAluno = 0;
            $comentariosFeitos = 0;
            $anexosEnviados = 0;

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

                $comentariosFeitos += $t->comentarios->where('aluno_id', $aluno->id)->count();
                $anexosEnviados += $t->anexos->where('aluno_id', $aluno->id)->count();
            }

            $dadosAlunos[] = [
                'aluno_id' => $aluno->id,
                'nome' => $aluno->nome,
                'papel' => $aluno->pivot->papel ?? 'Integrante',
                'tarefas_assumidas' => $tarefasAssumidas,
                'tarefas_concluidas' => $tarefasConcluidasAluno,
                'comentarios_interacoes' => $comentariosFeitos,
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
            'tarefas' => $dadosTarefas,
            'alunos' => $dadosAlunos
        ];

        return $this->chamarGeminiApi($promptData, $sprintId, $dadosAlunos, $percentualConclusao);
    }

    private function chamarGeminiApi(array $promptData, int $sprintId, array $dadosAlunos, float $percentualConclusao): array
    {
        $apiKey = env('GEMINI_API_KEY');

        $systemPrompt = "Atue como um assistente de avaliação técnica e pedagógica para um colégio técnico. "
            . "Analise os dados reais de desempenho de uma Sprint de desenvolvimento de software fornecidos a seguir. "
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
            . '      "observacoes": "string com justificativa sucinta do aluno"' . "\n"
            . "    }\n"
            . "  ]\n"
            . "}\n\n"
            . "Dados da Sprint:\n" . json_encode($promptData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

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
                    ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}", [
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
            $notaTarefas = $a['tarefas_assumidas'] > 0 
                ? round(min(10.0, max(5.0, ($a['tarefas_concluidas'] / $a['tarefas_assumidas']) * 10)), 1)
                : 7.0;

            $notaRituais = round(min(10.0, max(6.0, 7.0 + ($a['comentarios_interacoes'] * 0.5))), 1);
            $notaPostura = 8.5;

            $avaliacoesIndividuais[] = [
                'aluno_id' => $a['aluno_id'],
                'rituais' => $notaRituais,
                'tarefas' => $notaTarefas,
                'postura' => $notaPostura,
                'observacoes' => "Aluno {$a['nome']} ({$a['papel']}) concluiu {$a['tarefas_concluidas']} de {$a['tarefas_assumidas']} tarefas e registrou {$a['comentarios_interacoes']} interações."
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
}
