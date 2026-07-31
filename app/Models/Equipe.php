<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Equipe extends Model
{
    protected $table = 'equipes';
    public $timestamps = false;
    protected $fillable = ['nome', 'descricao', 'ano', 'url', 'github', 'prof_id'];

    public function professor()
    {
        return $this->belongsTo(Professor::class, 'prof_id');
    }

    public function alunos()
    {
        return $this->hasMany(Aluno::class, 'equipe_id');
    }

    public function sprints()
    {
        return $this->hasMany(Sprint::class, 'equipe_id');
    }

    public function tarefas()
    {
        return $this->hasMany(Tarefa::class, 'equipe_id');
    }
}
