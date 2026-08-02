<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvaliacaoSprint extends Model
{
    protected $table = 'avaliacoes_sprint';
    public $timestamps = false;

    protected $fillable = [
        'sprint_id',
        'entrega_valor',
        'qualidade_tecnica',
        'processos_rituais',
        'documentacao',
        'observacoes',
    ];

    public function sprint()
    {
        return $this->belongsTo(Sprint::class, 'sprint_id');
    }
}
