<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Criar a Função da Trigger no PostgreSQL
        DB::unprepared("
            CREATE OR REPLACE FUNCTION fn_criar_colunas_padrao_equipe()
            RETURNS TRIGGER AS $$
            BEGIN
                INSERT INTO colunas (titulo, sequencia, equipe_id, concluido) VALUES
                ('A FAZER', 1, NEW.id, false),
                ('FAZENDO', 2, NEW.id, false),
                ('EM TESTE', 3, NEW.id, false),
                ('CONCLUÍDO', 4, NEW.id, true);
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // 2. Criar a Trigger na Tabela 'equipes'
        DB::unprepared("
            DROP TRIGGER IF EXISTS trg_criar_colunas_padrao_equipe ON equipes;
            CREATE TRIGGER trg_criar_colunas_padrao_equipe
            AFTER INSERT ON equipes
            FOR EACH ROW
            EXECUTE FUNCTION fn_criar_colunas_padrao_equipe();
        ");
    }

    public function down(): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS trg_criar_colunas_padrao_equipe ON equipes;");
        DB::unprepared("DROP FUNCTION IF EXISTS fn_criar_colunas_padrao_equipe();");
    }
};
