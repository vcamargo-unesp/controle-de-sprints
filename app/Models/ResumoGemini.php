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
    ];

    public function aluno()
    {
        return $this->belongsTo(Aluno::class, 'aluno_id');
    }
}
