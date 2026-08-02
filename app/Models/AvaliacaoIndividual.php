<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvaliacaoIndividual extends Model
{
    protected $table = 'avaliacoes_individuais';
    public $timestamps = false;

    protected $fillable = [
        'sprint_id',
        'aluno_id',
        'rituais',
        'tarefas',
        'postura',
        'observacoes',
    ];

    public function sprint()
    {
        return $this->belongsTo(Sprint::class, 'sprint_id');
    }

    public function aluno()
    {
        return $this->belongsTo(Aluno::class, 'aluno_id');
    }
}
