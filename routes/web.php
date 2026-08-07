<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\EquipeController;
use App\Http\Controllers\KanbanController;
use App\Http\Controllers\ImportacaoController;
use Inertia\Inertia;

// Rota pública de Login
Route::get('/', function () {
    return Inertia::render('Login');
})->name('login');

// Rotas do Socialite
Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);
Route::post('/logout', [GoogleAuthController::class, 'logout'])->name('logout');

// Rotas protegidas pela Trava (Middleware)
Route::middleware(['session.auth'])->group(function () {
    
    // Visão do Professor & Cadastro / Edição de Equipes
    Route::get('/equipes', [EquipeController::class, 'index'])->name('equipes.index');
    Route::post('/equipes', [EquipeController::class, 'store'])->name('equipes.store');
    Route::put('/equipes/{id}', [EquipeController::class, 'update'])->name('equipes.update');
    Route::post('/equipes/{id}/assentos', [EquipeController::class, 'salvarAssentos'])->name('equipes.assentos');
    
    // Importação de Alunos via CSV
    Route::post('/importar-alunos', [ImportacaoController::class, 'importarAlunos'])->name('importar.alunos');

    // Visão da Equipe (Onde o aluno cai)
    Route::get('/equipes/{id}', [KanbanController::class, 'show'])->name('equipes.show');
    
    // Operações da Equipe & Kanban
    Route::post('/equipes/{equipe_id}/backlog/criar', [KanbanController::class, 'criarTarefaBacklog']);
    Route::post('/equipes/{equipe_id}/iniciar-sprint', [KanbanController::class, 'iniciarSprint']);
    Route::post('/kanban/mover', [KanbanController::class, 'mover']);
    Route::post('/kanban/editar-tarefa/{tarefaId}', [KanbanController::class, 'editarTarefa']);
    Route::post('/kanban/assumir-tarefa/{tarefaId}', [KanbanController::class, 'assumirTarefa']);
    Route::post('/kanban/comentario/{tarefaId}', [KanbanController::class, 'adicionarComentario']);
    Route::post('/kanban/comentario/{comentarioId}/editar', [KanbanController::class, 'editarComentario']);
    Route::delete('/kanban/comentario/{comentarioId}', [KanbanController::class, 'deletarComentario']);
    Route::post('/kanban/anexo/{tarefaId}', [KanbanController::class, 'adicionarAnexo']);
    Route::delete('/kanban/anexo/{anexoId}', [KanbanController::class, 'deletarAnexo']);
    Route::post('/kanban/encerrar-sprint/{sprintId}', [KanbanController::class, 'encerrarSprint']);
    Route::post('/kanban/sugerir-avaliacao/{sprintId}', [KanbanController::class, 'sugerirAvaliacao']);

    // Gerenciamento de Colunas (TL / Orientador)
    Route::post('/equipes/{equipeId}/colunas', [KanbanController::class, 'criarColuna']);
    Route::post('/equipes/{equipeId}/colunas/reordenar', [KanbanController::class, 'reordenarColunas']);
    Route::delete('/equipes/{equipeId}/colunas/{colunaId}', [KanbanController::class, 'deletarColuna']);

    // Painel de Notas & Resumos com IA
    Route::get('/notas', [\App\Http\Controllers\NotasController::class, 'index'])->name('notas.index');
    Route::post('/notas/pesos', [\App\Http\Controllers\NotasController::class, 'salvarPesos'])->name('notas.pesos');
    Route::post('/notas/aluno/{alunoId}/resumo/{bimestre}', [\App\Http\Controllers\NotasController::class, 'gerarResumoGemini'])->name('notas.resumo');
    Route::post('/notas/aluno/{alunoId}/resumo/{bimestre}/aprovar', [\App\Http\Controllers\NotasController::class, 'aprovarResumoGemini'])->name('notas.aprovar-resumo');
    Route::get('/minhas-notas', [\App\Http\Controllers\NotasController::class, 'minhasNotas'])->name('minhas.notas');
});
