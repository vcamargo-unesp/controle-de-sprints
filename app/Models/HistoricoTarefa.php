<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoricoTarefa extends Model
{
    protected $table = 'historicos_tarefas';
    public $timestamps = false; // usamos apenas created_at

    protected $fillable = [
        'tarefa_id',
        'aluno_id',
        'prof_id',
        'tipo_acao',
        'descricao',
        'detalhes',
        'created_at'
    ];

    protected $casts = [
        'detalhes' => 'array',
        'created_at' => 'datetime'
    ];

    public function tarefa()
    {
        return $this->belongsTo(Tarefa::class, 'tarefa_id');
    }

    public function aluno()
    {
        return $this->belongsTo(Aluno::class, 'aluno_id');
    }

    public function professor()
    {
        return $this->belongsTo(Professor::class, 'prof_id');
    }
}
