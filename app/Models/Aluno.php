<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Aluno extends Model
{
    protected $table = 'alunos';
    public $timestamps = false;
    protected $fillable = ['nome', 'email', 'n_chamada', 'papel', 'equipe_id', 'ra'];

    public function equipe()
    {
        return $this->belongsTo(Equipe::class, 'equipe_id');
    }
}
