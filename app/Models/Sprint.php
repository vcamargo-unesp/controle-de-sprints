<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Sprint extends Model
{
    protected $table = 'sprints';
    public $timestamps = false;
    protected $fillable = ['equipe_id', 'sequencia', 'dt_inicio', 'dt_fim', 'percentual', 'feedback', 'encerrada'];

    public function equipe()
    {
        return $this->belongsTo(Equipe::class, 'equipe_id');
    }

    public function colSprints()
    {
        return $this->hasMany(ColSprint::class, 'sprint_id');
    }

    /**
     * Calc percentual de conclusão baseado nas tarefas na coluna marcada como concluído = true
     */
    public function calcularPercentualConclusao(): float
    {
        $totalTarefas = DB::table('tarefa_colunas')
            ->where('sprint_id', $this->id)
            ->count();

        if ($totalTarefas === 0) {
            return 0.0;
        }

        $tarefasConcluidas = DB::table('tarefa_colunas')
            ->join('col_sprints', 'tarefa_colunas.colsprint_id', '=', 'col_sprints.id')
            ->join('colunas', 'col_sprints.coluna_id', '=', 'colunas.id')
            ->where('tarefa_colunas.sprint_id', $this->id)
            ->where('colunas.concluido', true)
            ->count();

        $percentual = round(($tarefasConcluidas / $totalTarefas) * 100, 2);

        $this->update(['percentual' => $percentual]);

        return $percentual;
    }
}
