<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SprintController extends Controller
{
    public function index(Request $request)
    {
        $role = $request->query('role', 'aluno');
        $equipeId = $request->query('equipe_id', 1);

        // Buscar Equipe
        $equipe = DB::table('equipes')->where('id', $equipeId)->first();

        // Buscar Sprint Ativa
        $sprint = DB::table('sprints')
            ->where('equipe_id', $equipeId)
            ->orderBy('sequencia', 'desc')
            ->first();

        if (!$sprint) {
            return Inertia::render('Kanban', [
                'userRole' => $role,
                'sprint' => null,
                'colunas' => [],
                'tarefasIniciais' => []
            ]);
        }

        $hoje = date('Y-m-d');
        $bloqueadaPorPrazo = $sprint->dt_fim ? ($sprint->dt_fim < $hoje) : false;

        // Buscar Colunas vinculadas a esta Sprint através de col_sprints
        $colunasSprint = DB::table('col_sprints')
            ->join('colunas', 'col_sprints.coluna_id', '=', 'colunas.id')
            ->where('col_sprints.sprint_id', $sprint->id)
            ->orderBy('colunas.sequencia', 'asc')
            ->select('col_sprints.id as colsprint_id', 'colunas.id as coluna_id', 'colunas.titulo', 'colunas.concluido')
            ->get();

        // Buscar Tarefas da Sprint via tarefa_colunas
        $tarefasRaw = DB::table('tarefa_colunas')
            ->join('tarefas', 'tarefa_colunas.tarefa_id', '=', 'tarefas.id')
            ->where('tarefa_colunas.sprint_id', $sprint->id)
            ->select(
                'tarefas.id',
                'tarefas.titulo',
                'tarefas.descricao',
                'tarefa_colunas.colsprint_id',
                'tarefa_colunas.sequencia'
            )
            ->get();

        // Mapear tarefas para o formato esperado no Vue
        $tarefas = $tarefasRaw->map(function ($t) use ($colunasSprint) {
            $coluna = $colunasSprint->firstWhere('colsprint_id', $t->colsprint_id);
            return [
                'id' => $t->id,
                'titulo' => $t->titulo,
                'descricao' => $t->descricao ?? '',
                'colsprint_id' => $t->colsprint_id,
                'coluna_id' => $coluna ? (string)$coluna->coluna_id : 'a_fazer',
                'papel' => 'Dev',
                'responsavel' => 'Aluno CTI',
                'estimativa_horas' => 4,
                'comentarios_count' => 0,
                'anexos_count' => 0,
            ];
        });

        // Formatar colunas para o frontend
        $colunasFormatted = $colunasSprint->map(function ($c) {
            return [
                'id' => (string)$c->coluna_id,
                'colsprint_id' => $c->colsprint_id,
                'titulo' => $c->titulo
            ];
        });

        return Inertia::render('Kanban', [
            'userRole' => $role,
            'sprint' => [
                'id' => $sprint->id,
                'nome' => 'Sprint ' . $sprint->sequencia . ' - ' . ($equipe->nome ?? 'Equipe'),
                'dt_inicio' => $sprint->dt_inicio,
                'dt_fim' => $sprint->dt_fim,
                'bloqueadaPorPrazo' => $bloqueadaPorPrazo
            ],
            'colunas' => $colunasFormatted,
            'tarefasIniciais' => $tarefas
        ]);
    }

    public function moverTarefa(Request $request)
    {
        $tarefaId = $request->input('tarefa_id');
        $novaColunaId = $request->input('coluna_id');
        $sprintId = $request->input('sprint_id');

        // Buscar colsprint_id correspondente
        $colSprint = DB::table('col_sprints')
            ->where('sprint_id', $sprintId)
            ->where('coluna_id', $novaColunaId)
            ->first();

        if ($colSprint) {
            DB::table('tarefa_colunas')
                ->where('sprint_id', $sprintId)
                ->where('tarefa_id', $tarefaId)
                ->update(['colsprint_id' => $colSprint->id]);

            DB::table('tarefas')
                ->where('id', $tarefaId)
                ->update(['ultimacoluna_id' => $novaColunaId]);
        }

        return back();
    }
}
