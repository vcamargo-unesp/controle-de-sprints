<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import { 
  Plus, 
  Clock, 
  UserCheck, 
  MessageSquare, 
  Paperclip, 
  X, 
  Lock, 
  CheckCircle2, 
  AlertCircle, 
  Calendar,
  Send,
  Upload
} from 'lucide-vue-next';

const props = defineProps({
  userRole: {
    type: String,
    default: 'aluno'
  },
  sprint: {
    type: Object,
    default: null
  },
  colunas: {
    type: Array,
    default: () => []
  },
  tarefasIniciais: {
    type: Array,
    default: () => []
  }
});

const tarefas = ref([...props.tarefasIniciais]);
const modalAberto = ref(false);
const tarefaSelecionada = ref(null);

const modalEncerramentoAberto = ref(false);
const feedbackProfessor = ref('');

const novoComentarioText = ref('');
const novoAnexoDesc = ref('');

const draggedTaskId = ref(null);
const hoverColuna = ref(null);

const canDrag = computed(() => {
  return props.userRole === 'aluno' && props.sprint && !props.sprint.bloqueadaPorPrazo;
});

const onDragStart = (event, id) => {
  if (!canDrag.value) return;
  draggedTaskId.value = id;
  event.dataTransfer.effectAllowed = 'move';
};

const onDragOver = (colunaId) => {
  if (!canDrag.value) return;
  hoverColuna.value = colunaId;
};

const onDragLeave = (colunaId) => {
  if (hoverColuna.value === colunaId) {
    hoverColuna.value = null;
  }
};

const onDrop = (colunaId) => {
  if (!canDrag.value || !draggedTaskId.value) return;
  const target = tarefas.value.find(t => t.id === draggedTaskId.value);
  if (target) {
    target.coluna_id = colunaId;

    // Persistir via Inertia
    router.post('/kanban/mover', {
      tarefa_id: target.id,
      coluna_id: colunaId,
      sprint_id: props.sprint.id
    }, { preserveState: true, preserveScroll: true });
  }
  hoverColuna.value = null;
  draggedTaskId.value = null;
};

const abrirModalDetalhes = (tarefa) => {
  tarefaSelecionada.value = { ...tarefa };
  modalAberto.value = true;
};

const enviarComentario = () => {
  if (!novoComentarioText.value || !tarefaSelecionada.value) return;
  router.post(`/kanban/comentario/${tarefaSelecionada.value.id}`, {
    texto: novoComentarioText.value
  }, {
    preserveScroll: true,
    onSuccess: () => {
      novoComentarioText.value = '';
    }
  });
};

const enviarAnexo = () => {
  if (!novoAnexoDesc.value || !tarefaSelecionada.value) return;
  router.post(`/kanban/anexo/${tarefaSelecionada.value.id}`, {
    descricao: novoAnexoDesc.value
  }, {
    preserveScroll: true,
    onSuccess: () => {
      novoAnexoDesc.value = '';
    }
  });
};

const confirmarEncerramento = () => {
  if (!props.sprint) return;
  router.post(`/kanban/encerrar-sprint/${props.sprint.id}`, {}, {
    onSuccess: () => {
      modalEncerramentoAberto.value = false;
    }
  });
};
</script>

<template>
  <AppLayout :userRole="userRole" userName="Prof. Isaac / Vitor (CTI Bauru)">
    <!-- Breadcrumb -->
    <Breadcrumb :items="[{ label: 'Equipes CTI', href: '/equipes' }, { label: sprint ? sprint.nome : 'Kanban' }]" />

    <!-- Bar de Informações da Sprint com Percentual Automático -->
    <div v-if="sprint" class="bg-white border border-slate-200 rounded-md p-3 mb-3 shadow-sm flex flex-wrap items-center justify-between gap-3">
      <div>
        <div class="flex items-center space-x-2">
          <h1 class="text-lg font-bold text-slate-800 tracking-tight">{{ sprint.nome }}</h1>
          <span 
            :class="[
              'text-xs font-semibold px-2 py-0.5 rounded border',
              sprint.bloqueadaPorPrazo 
                ? 'bg-amber-50 text-amber-800 border-amber-300 flex items-center space-x-1' 
                : 'bg-emerald-50 text-emerald-800 border-emerald-300'
            ]"
          >
            <Lock v-if="sprint.bloqueadaPorPrazo" class="w-3 h-3 inline mr-1" />
            {{ sprint.bloqueadaPorPrazo ? 'Sprint Expirada (Leitura)' : 'Sprint Ativa' }}
          </span>
        </div>
        <div class="flex items-center space-x-4 text-xs text-slate-500 mt-1">
          <span class="flex items-center space-x-1">
            <Calendar class="w-3.5 h-3.5 text-slate-400" />
            <span>Período: <strong>{{ sprint.dt_inicio }}</strong> a <strong>{{ sprint.dt_fim }}</strong></span>
          </span>
          <span class="font-bold text-slate-700">
            Conclusão: {{ sprint.percentual || 0 }}%
          </span>
        </div>
      </div>

      <!-- Fase 5: Modal do Professor - Rito de Encerramento -->
      <div v-if="userRole === 'professor'" class="flex items-center space-x-2">
        <button 
          @click="modalEncerramentoAberto = true"
          class="bg-[#9B2C2C] hover:bg-[#7B1D1D] text-white font-bold text-xs px-3 py-1.5 rounded shadow-sm transition flex items-center space-x-1 cursor-pointer"
        >
          <CheckCircle2 class="w-4 h-4" />
          <span>Encerrar Sprint & Repassar Pendências</span>
        </button>
      </div>
    </div>

    <!-- Quadro Kanban de Alta Densidade -->
    <div 
      :class="[
        'grid grid-cols-1 md:grid-cols-3 gap-3',
        sprint && sprint.bloqueadaPorPrazo ? 'opacity-75' : ''
      ]"
    >
      <div 
        v-for="coluna in colunas" 
        :key="coluna.id"
        @dragover.prevent="onDragOver(coluna.id)"
        @dragleave="onDragLeave(coluna.id)"
        @drop="onDrop(coluna.id)"
        :class="[
          'bg-slate-200/70 border rounded-md p-2.5 flex flex-col min-h-[450px] transition-colors',
          hoverColuna === coluna.id ? 'bg-blue-100/70 border-blue-400 ring-2 ring-blue-300' : 'border-slate-300/80'
        ]"
      >
        <!-- Header da Coluna -->
        <div class="flex items-center justify-between pb-2 mb-2 border-b border-slate-300">
          <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700">
            {{ coluna.titulo }}
          </h2>
          <span class="bg-slate-300 text-slate-800 text-[11px] font-bold px-1.5 py-0.5 rounded font-mono">
            {{ tarefas.filter(t => t.coluna_id === coluna.id).length }}
          </span>
        </div>

        <!-- Cards da Coluna -->
        <div class="flex-1 space-y-2">
          <div 
            v-for="tarefa in tarefas.filter(t => t.coluna_id === coluna.id)" 
            :key="tarefa.id"
            :draggable="canDrag"
            @dragstart="onDragStart($event, tarefa.id)"
            @click="abrirModalDetalhes(tarefa)"
            :class="[
              'bg-white border border-slate-200 rounded p-2.5 shadow-sm hover:shadow transition cursor-pointer select-none',
              !canDrag ? 'cursor-not-allowed' : ''
            ]"
          >
            <div class="flex items-center justify-between mb-1">
              <span class="text-[10px] font-mono font-bold text-slate-500">#{{ tarefa.id }}</span>
              <span class="text-[9px] font-extrabold uppercase px-1.5 py-0.2 rounded border bg-blue-50 text-blue-800 border-blue-300">
                {{ tarefa.papel }}
              </span>
            </div>

            <h3 class="text-xs font-semibold text-slate-900 leading-tight mb-2">
              {{ tarefa.titulo }}
            </h3>

            <div class="flex items-center justify-between text-[11px] text-slate-500 pt-1.5 border-t border-slate-100">
              <span class="flex items-center space-x-1 font-medium text-slate-700">
                <UserCheck class="w-3 h-3 text-slate-400" />
                <span>{{ tarefa.responsavel }}</span>
              </span>

              <div class="flex items-center space-x-2">
                <span v-if="tarefa.comentarios_count > 0" class="flex items-center space-x-0.5">
                  <MessageSquare class="w-3 h-3 text-slate-400" />
                  <span>{{ tarefa.comentarios_count }}</span>
                </span>
                <span v-if="tarefa.anexos_count > 0" class="flex items-center space-x-0.5">
                  <Paperclip class="w-3 h-3 text-slate-400" />
                  <span>{{ tarefa.anexos_count }}</span>
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal de Detalhes, Anexos e Comentários -->
    <div v-if="modalAberto" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-lg overflow-hidden border border-slate-200">
        <div class="bg-[#0F2537] px-4 py-3 text-white flex items-center justify-between">
          <h3 class="text-sm font-bold flex items-center space-x-2">
            <span>Tarefa #{{ tarefaSelecionada?.id }} - {{ tarefaSelecionada?.titulo }}</span>
          </h3>
          <button @click="modalAberto = false" class="text-slate-300 hover:text-white cursor-pointer">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="p-4 space-y-4 max-h-[70vh] overflow-y-auto">
          <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Descrição</label>
            <p class="text-xs text-slate-600 bg-slate-50 p-2 rounded border border-slate-200">
              {{ tarefaSelecionada?.descricao || 'Sem descrição cadastrada.' }}
            </p>
          </div>

          <!-- Seção de Comentários -->
          <div class="border-t pt-3">
            <h4 class="text-xs font-bold text-slate-800 mb-2 flex items-center space-x-1">
              <MessageSquare class="w-3.5 h-3.5 text-slate-500" />
              <span>Comentários</span>
            </h4>

            <div class="flex gap-2">
              <input 
                v-model="novoComentarioText" 
                type="text" 
                placeholder="Escreva um comentário..."
                class="flex-1 border border-slate-300 rounded px-2.5 py-1 text-xs focus:ring-1 focus:ring-slate-500 focus:outline-none"
              />
              <button 
                @click="enviarComentario" 
                class="bg-[#0F2537] text-white px-3 py-1 rounded text-xs font-semibold hover:bg-[#1A365D]"
              >
                Comentar
              </button>
            </div>
          </div>

          <!-- Seção de Anexos -->
          <div class="border-t pt-3">
            <h4 class="text-xs font-bold text-slate-800 mb-2 flex items-center space-x-1">
              <Paperclip class="w-3.5 h-3.5 text-slate-500" />
              <span>Anexos da Tarefa</span>
            </h4>

            <div class="flex gap-2">
              <input 
                v-model="novoAnexoDesc" 
                type="text" 
                placeholder="Descrição do anexo..."
                class="flex-1 border border-slate-300 rounded px-2.5 py-1 text-xs focus:ring-1 focus:ring-slate-500 focus:outline-none"
              />
              <button 
                @click="enviarAnexo" 
                class="bg-slate-700 text-white px-3 py-1 rounded text-xs font-semibold hover:bg-slate-800"
              >
                Anexar
              </button>
            </div>
          </div>
        </div>

        <div class="bg-slate-50 px-4 py-2.5 border-t border-slate-200 flex justify-end">
          <button @click="modalAberto = false" class="px-3 py-1.5 rounded border border-slate-300 text-xs font-medium text-slate-700 hover:bg-slate-100 cursor-pointer">
            Fechar
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Rito de Encerramento (Professor) -->
    <div v-if="modalEncerramentoAberto" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md overflow-hidden border border-slate-200">
        <div class="bg-[#9B2C2C] px-4 py-3 text-white flex items-center justify-between">
          <h3 class="text-sm font-bold">Rito de Encerramento da Sprint</h3>
          <button @click="modalEncerramentoAberto = false" class="text-white cursor-pointer">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="p-4 space-y-3">
          <p class="text-xs text-slate-600">
            Ao encerrar a <strong>{{ sprint?.nome }}</strong>, o sistema executará uma transação no banco de dados para salvar os resultados e repassar automaticamente todas as tarefas <strong>não concluídas</strong> para a próxima Sprint.
          </p>

          <div class="bg-slate-50 p-2.5 rounded border border-slate-200">
            <span class="text-xs font-bold text-slate-800">Percentual de Conclusão Calculado:</span>
            <span class="text-sm font-extrabold text-emerald-700 block mt-0.5">{{ sprint?.percentual || 0 }}%</span>
          </div>
        </div>

        <div class="bg-slate-50 px-4 py-2.5 border-t border-slate-200 flex justify-end space-x-2">
          <button @click="modalEncerramentoAberto = false" class="px-3 py-1.5 rounded border border-slate-300 text-xs font-medium text-slate-700 hover:bg-slate-100 cursor-pointer">
            Cancelar
          </button>
          <button @click="confirmarEncerramento" class="px-3 py-1.5 rounded bg-[#9B2C2C] text-white text-xs font-semibold hover:bg-[#7B1D1D] cursor-pointer">
            Confirmar Encerramento
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
