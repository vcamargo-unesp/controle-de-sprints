<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('resumos_gemini', function (Blueprint $table) {
            $table->boolean('aprovado')->default(false)->after('texto_resumo');
            $table->text('texto_editado')->nullable()->after('aprovado');
            $table->timestamp('aprovado_em')->nullable()->after('texto_editado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resumos_gemini', function (Blueprint $table) {
            $table->dropColumn(['aprovado', 'texto_editado', 'aprovado_em']);
        });
    }
};
