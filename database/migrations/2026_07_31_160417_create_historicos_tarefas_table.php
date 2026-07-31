<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historicos_tarefas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarefa_id')->constrained('tarefas')->onDelete('cascade');
            $table->foreignId('aluno_id')->nullable()->constrained('alunos')->onDelete('set null');
            $table->foreignId('prof_id')->nullable()->constrained('professores')->onDelete('set null');
            $table->string('tipo_acao'); // 'criacao', 'edicao', 'movimentacao', 'responsavel', 'transferencia_sprint'
            $table->text('descricao');
            $table->json('detalhes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historicos_tarefas');
    }
};
