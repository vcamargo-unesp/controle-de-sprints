<?php

namespace App\Observers;

use App\Models\TarefaColuna;
use Illuminate\Support\Facades\Log;

class TarefaColunaObserver
{
    /**
     * Handle the TarefaColuna "updated" event.
     */
    public function updated(TarefaColuna $tarefaColuna): void
    {
        Log::info("Movimentação de Tarefa registrada no Observer: Tarefa #{$tarefaColuna->tarefa_id} movida na Sprint #{$tarefaColuna->sprint_id} para a coluna de ID #{$tarefaColuna->colsprint_id}.");
    }
}
