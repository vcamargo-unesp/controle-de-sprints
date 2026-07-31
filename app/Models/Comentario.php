<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comentario extends Model
{
    protected $table = 'comentarios';
    public $timestamps = false;
    protected $fillable = ['tarefa_id', 'aluno_id', 'prof_id', 'texto'];

    public function aluno()
    {
        return $this->belongsTo(Aluno::class, 'aluno_id');
    }

    public function professor()
    {
        return $this->belongsTo(Professor::class, 'prof_id');
    }
}
