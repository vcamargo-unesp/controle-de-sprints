<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TarefaColuna extends Model
{
    protected $table = 'tarefa_colunas';
    public $timestamps = false;
    protected $fillable = ['tarefa_id', 'colsprint_id', 'sprint_id', 'sequencia'];

    public function tarefa()
    {
        return $this->belongsTo(Tarefa::class, 'tarefa_id');
    }

    public function colSprint()
    {
        return $this->belongsTo(ColSprint::class, 'colsprint_id');
    }

    public function sprint()
    {
        return $this->belongsTo(Sprint::class, 'sprint_id');
    }
}
