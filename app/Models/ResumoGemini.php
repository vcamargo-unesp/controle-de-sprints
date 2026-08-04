<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResumoGemini extends Model
{
    protected $table = 'resumos_gemini';

    protected $fillable = [
        'aluno_id',
        'bimestre',
        'texto_resumo',
        'aprovado',
        'texto_editado',
        'aprovado_em',
    ];

    protected $casts = [
        'aprovado' => 'boolean',
        'aprovado_em' => 'datetime',
    ];

    public function aluno()
    {
        return $this->belongsTo(Aluno::class, 'aluno_id');
    }
}
