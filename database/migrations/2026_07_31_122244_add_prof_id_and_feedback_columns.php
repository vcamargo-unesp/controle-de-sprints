<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comentarios', function (Blueprint $table) {
            $table->foreignId('prof_id')->nullable()->constrained('professores')->onDelete('cascade');
        });

        Schema::table('anexos', function (Blueprint $table) {
            $table->foreignId('prof_id')->nullable()->constrained('professores')->onDelete('cascade');
            $table->string('nome_original')->nullable();
        });

        Schema::table('sprints', function (Blueprint $table) {
            $table->text('feedback')->nullable();
            $table->boolean('encerrada')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('comentarios', function (Blueprint $table) {
            $table->dropForeign(['prof_id']);
            $table->dropColumn('prof_id');
        });

        Schema::table('anexos', function (Blueprint $table) {
            $table->dropForeign(['prof_id']);
            $table->dropColumn(['prof_id', 'nome_original']);
        });

        Schema::table('sprints', function (Blueprint $table) {
            $table->dropColumn(['feedback', 'encerrada']);
        });
    }
};
