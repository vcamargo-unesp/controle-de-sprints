<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Anexo extends Model
{
    protected $table = 'anexos';
    public $timestamps = false;
    protected $fillable = ['tarefa_id', 'aluno_id', 'prof_id', 'caminho', 'nome_original'];

    public function aluno()
    {
        return $this->belongsTo(Aluno::class, 'aluno_id');
    }

    public function professor()
    {
        return $this->belongsTo(Professor::class, 'prof_id');
    }
}
