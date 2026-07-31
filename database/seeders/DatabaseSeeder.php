<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Limpar tabelas mantendo integridade
        DB::table('tarefa_colunas')->delete();
        DB::table('col_sprints')->delete();
        DB::table('tarefas')->delete();
        DB::table('colunas')->delete();
        DB::table('sprints')->delete();
        DB::table('alunos')->delete();
        DB::table('equipes')->delete();
        DB::table('professores')->delete();

        // 1. Professores
        $profId = DB::table('professores')->insertGetId([
            'nome' => 'Prof. Isaac Portal Roldán',
            'email' => 'isaac@cti.feb.unesp.br'
        ]);

        // 2. Equipes
        $equipeId = DB::table('equipes')->insertGetId([
            'nome' => 'Equipe Alpha',
            'descricao' => 'Sistema de Controle de Sprints CTI',
            'ano' => 2026,
            'prof_id' => $profId
        ]);

        // 3. Alunos
        DB::table('alunos')->insert([
            ['nome' => 'Vitor Rossi', 'email' => 'vitor@cti.feb.unesp.br', 'papel' => 'PO', 'equipe_id' => $equipeId, 'ra' => '2212345'],
            ['nome' => 'Lucas Silva', 'email' => 'lucas@cti.feb.unesp.br', 'papel' => 'dev', 'equipe_id' => $equipeId, 'ra' => '2212346'],
            ['nome' => 'Mariana Souza', 'email' => 'mariana@cti.feb.unesp.br', 'papel' => 'dev', 'equipe_id' => $equipeId, 'ra' => '2212347']
        ]);

        // 4. Sprints
        $sprintId = DB::table('sprints')->insertGetId([
            'equipe_id' => $equipeId,
            'sequencia' => 1,
            'dt_inicio' => '2026-07-25',
            'dt_fim' => '2026-08-10',
            'percentual' => 50.00
        ]);

        // 5. Colunas
        $colAFazer = DB::table('colunas')->insertGetId(['titulo' => 'A Fazer', 'sequencia' => 1, 'equipe_id' => $equipeId, 'concluido' => false]);
        $colEmAndamento = DB::table('colunas')->insertGetId(['titulo' => 'Em Andamento', 'sequencia' => 2, 'equipe_id' => $equipeId, 'concluido' => false]);
        $colConcluido = DB::table('colunas')->insertGetId(['titulo' => 'Concluído', 'sequencia' => 3, 'equipe_id' => $equipeId, 'concluido' => true]);

        // 6. Relacionar Colunas à Sprint em col_sprints
        $csAFazer = DB::table('col_sprints')->insertGetId(['coluna_id' => $colAFazer, 'sprint_id' => $sprintId]);
        $csEmAndamento = DB::table('col_sprints')->insertGetId(['coluna_id' => $colEmAndamento, 'sprint_id' => $sprintId]);
        $csConcluido = DB::table('col_sprints')->insertGetId(['coluna_id' => $colConcluido, 'sprint_id' => $sprintId]);

        // 7. Tarefas
        $t1 = DB::table('tarefas')->insertGetId([
            'equipe_id' => $equipeId,
            'titulo' => 'Modelagem das Tabelas do PostgreSQL',
            'descricao' => 'Criar relacionamento de tarefas, colunas e sprints.',
            'ultimacoluna_id' => $colConcluido
        ]);

        $t2 = DB::table('tarefas')->insertGetId([
            'equipe_id' => $equipeId,
            'titulo' => 'Desenvolver Kanban no Vue 3',
            'descricao' => 'Implementar movimentação e estado das colunas do Kanban.',
            'ultimacoluna_id' => $colEmAndamento
        ]);

        $t3 = DB::table('tarefas')->insertGetId([
            'equipe_id' => $equipeId,
            'titulo' => 'Validar Permissões de Professor e Aluno',
            'descricao' => 'Restringir edições de alunos quando sprint estiver finalizada.',
            'ultimacoluna_id' => $colAFazer
        ]);

        // 8. Associar Tarefas às Colunas da Sprint (tarefa_colunas)
        DB::table('tarefa_colunas')->insert([
            ['tarefa_id' => $t1, 'colsprint_id' => $csConcluido, 'sprint_id' => $sprintId, 'sequencia' => 1],
            ['tarefa_id' => $t2, 'colsprint_id' => $csEmAndamento, 'sprint_id' => $sprintId, 'sequencia' => 1],
            ['tarefa_id' => $t3, 'colsprint_id' => $csAFazer, 'sprint_id' => $sprintId, 'sequencia' => 1],
        ]);
    }
}
