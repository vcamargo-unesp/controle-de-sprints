<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PesoTurma extends Model
{
    protected $table = 'pesos_turma';
    public $timestamps = false;

    protected $fillable = [
        'ano',
        'turma',
        'bimestre',
        'peso',
    ];
}
