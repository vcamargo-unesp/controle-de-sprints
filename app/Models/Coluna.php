<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coluna extends Model
{
    protected $table = 'colunas';
    public $timestamps = false;
    protected $fillable = ['titulo', 'sequencia', 'descricao', 'equipe_id', 'concluido'];

    public function equipe()
    {
        return $this->belongsTo(Equipe::class, 'equipe_id');
    }
}
