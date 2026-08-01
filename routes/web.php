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

    // Gerenciamento de Colunas (TL / Orientador)
    Route::post('/equipes/{equipeId}/colunas', [KanbanController::class, 'criarColuna']);
    Route::post('/equipes/{equipeId}/colunas/reordenar', [KanbanController::class, 'reordenarColunas']);
    Route::delete('/equipes/{equipeId}/colunas/{colunaId}', [KanbanController::class, 'deletarColuna']);
});
