<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tarefa extends Model
{
    protected $table = 'tarefas';
    public $timestamps = false;
    protected $fillable = ['equipe_id', 'titulo', 'descricao', 'ultimacoluna_id'];

    public function equipe()
    {
        return $this->belongsTo(Equipe::class, 'equipe_id');
    }

    public function ultimaColuna()
    {
        return $this->belongsTo(Coluna::class, 'ultimacoluna_id');
    }

    public function responsaveis()
    {
        return $this->belongsToMany(Aluno::class, 'responsaveis', 'tarefa_id', 'aluno_id');
    }

    public function comentarios()
    {
        return $this->hasMany(Comentario::class, 'tarefa_id');
    }

    public function anexos()
    {
        return $this->hasMany(Anexo::class, 'tarefa_id');
    }
}
