<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ColSprint extends Model
{
    protected $table = 'col_sprints';
    public $timestamps = false;
    protected $fillable = ['coluna_id', 'sprint_id'];

    public function coluna()
    {
        return $this->belongsTo(Coluna::class, 'coluna_id');
    }

    public function sprint()
    {
        return $this->belongsTo(Sprint::class, 'sprint_id');
    }
}
