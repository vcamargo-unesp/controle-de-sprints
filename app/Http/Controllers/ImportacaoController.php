<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImportacaoController extends Controller
{
    public function importarAlunos(Request $request)
    {
        // Valida se apenas professor pode importar
        if (session('user_type') !== 'professor') {
            return back()->withErrors(['import' => 'Acesso negado: Apenas professores podem importar alunos.']);
        }

        // 1. Valida se o arquivo foi enviado e é um CSV
        $request->validate([
            'arquivo_csv' => 'required|mimes:csv,txt|max:2048'
        ]);

        $file = $request->file('arquivo_csv');
        $filePath = $file->getRealPath();
        
        // Abre o arquivo para leitura
        $handle = fopen($filePath, 'r');
        $header = true;

        DB::beginTransaction();

        try {
            // Lê linha por linha (ajuste o ';' de acordo com o padrão do seu CSV)
            while (($linha = fgetcsv($handle, 1000, ';')) !== false) {
                // Se a linha vier formatada com vírgula em vez de ponto e vírgula
                if (count($linha) === 1 && str_contains($linha[0], ',')) {
                    $linha = explode(',', $linha[0]);
                }

                // Pula a primeira linha se for o cabeçalho
                if ($header) {
                    $header = false;
                    continue;
                }

                if (count($linha) < 2 || empty(trim($linha[1]))) {
                    continue;
                }

                $nome = trim($linha[0]);
                $email = trim($linha[1]);
                $ra = isset($linha[2]) ? trim($linha[2]) : null;
                $n_chamada = isset($linha[3]) && $linha[3] !== '' ? (int) trim($linha[3]) : null;
                $papel = isset($linha[4]) && !empty(trim($linha[4])) ? trim($linha[4]) : 'dev';
                $equipe_id = isset($linha[5]) && $linha[5] !== '' ? (int) trim($linha[5]) : null;

                // Insere ou atualiza o aluno baseado no E-mail para evitar duplicatas
                Aluno::updateOrCreate(
                    ['email' => $email],
                    [
                        'nome' => $nome,
                        'ra' => $ra,
                        'n_chamada' => $n_chamada,
                        'papel' => $papel,
                        'equipe_id' => $equipe_id
                    ]
                );
            }

            fclose($handle);
            DB::commit();

            return redirect()->back()->with('success', 'Alunos importados com sucesso!');

        } catch (\Exception $e) {
            DB::rollBack();
            if (is_resource($handle)) {
                fclose($handle);
            }
            return redirect()->back()->withErrors(['import' => 'Erro ao importar linha: ' . $e->getMessage()]);
        }
    }
}
