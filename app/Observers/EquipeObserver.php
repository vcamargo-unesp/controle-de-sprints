<?php

namespace App\Observers;

use App\Models\Equipe;
use App\Models\Coluna;

class EquipeObserver
{
    /**
     * Handle the Equipe "created" event.
     * Cria automaticamente as colunas padrão do Kanban (A FAZER, FAZENDO, EM TESTE, CONCLUÍDO)
     */
    public function created(Equipe $equipe): void
    {
        Coluna::create(['titulo' => 'A FAZER', 'sequencia' => 1, 'equipe_id' => $equipe->id, 'concluido' => false]);
        Coluna::create(['titulo' => 'FAZENDO', 'sequencia' => 2, 'equipe_id' => $equipe->id, 'concluido' => false]);
        Coluna::create(['titulo' => 'EM TESTE', 'sequencia' => 3, 'equipe_id' => $equipe->id, 'concluido' => false]);
        Coluna::create(['titulo' => 'CONCLUÍDO', 'sequencia' => 4, 'equipe_id' => $equipe->id, 'concluido' => true]);
    }
}
