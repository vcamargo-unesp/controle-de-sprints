<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import { 
  ListTodo, 
  Kanban, 
  History, 
  Plus, 
  CheckCircle2, 
  Lock, 
  Calendar, 
  Clock, 
  UserCheck, 
  Send,
  AlertCircle,
  Eye,
  MessageSquare,
  Paperclip,
  X,
  Upload,
  UserPlus,
  Users
} from 'lucide-vue-next';

const props = defineProps({
  equipe: Object,
  abaAtiva: String,
  userRole: String,
  isOrientador: Boolean,
  isPO: Boolean,
  sprint: Object,
  colunas: Array,
  tarefasIniciais: Array,
  tarefasBacklog: Array,
  sprintsAnteriores: Array
});

// Tarefas do Kanban
const tarefas = ref([...(props.tarefasIniciais || [])]);
const tarefasSelecionadasBacklog = ref([]);

// Modais
const modalTarefaAberto = ref(false);
const tarefaSelecionada = ref(null);

const modalIntegrantesAberto = ref(false);

const modalEncerramentoAberto = ref(false);
const feedbackProfessorInput = ref('');

const modalNovaTarefaBacklogAberto = ref(false);
const novaTarefaTitulo = ref('');
const novaTarefaDescricao = ref('');

// Form de Comentário e Anexo no Modal
const novoComentarioText = ref('');
const arquivoAnexoInput = ref(null);

// Drag & Drop
const draggedTaskId = ref(null);
const hoverColuna = ref(null);

const canDrag = computed(() => {
  return props.userRole === 'aluno' && props.sprint && !props.sprint.bloqueadaPorPrazo && props.abaAtiva === 'sprint-atual';
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

    router.post('/kanban/mover', {
      tarefa_id: target.id,
      coluna_id: colunaId,
      sprint_id: props.sprint.id
    }, { preserveState: true, preserveScroll: true });
  }
  hoverColuna.value = null;
  draggedTaskId.value = null;
};

const abrirModalTarefa = (tarefa) => {
  tarefaSelecionada.value = tarefa;
  modalTarefaAberto.value = true;
};

const assumirTarefa = (alunoId = null) => {
  if (!tarefaSelecionada.value) return;
  router.post(`/kanban/assumir-tarefa/${tarefaSelecionada.value.id}`, {
    aluno_id: alunoId
  }, {
    preserveScroll: true,
    onSuccess: () => {
      // Atualiza responsaveis localmente no estado para reflexão instantânea
      const aluno = props.equipe.alunos?.find(a => a.id === alunoId);
      if (aluno) {
        const idx = tarefaSelecionada.value.responsaveis.findIndex(r => r.id === aluno.id);
        if (idx > -1) {
          tarefaSelecionada.value.responsaveis.splice(idx, 1);
        } else {
          tarefaSelecionada.value.responsaveis.push({ id: aluno.id, nome: aluno.nome });
        }
      }
    }
  });
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

const salvarEdicaoTarefa = () => {
  if (!tarefaSelecionada.value || !tarefaSelecionada.value.titulo) return;
  router.post(`/kanban/editar-tarefa/${tarefaSelecionada.value.id}`, {
    titulo: tarefaSelecionada.value.titulo,
    descricao: tarefaSelecionada.value.descricao
  }, {
    preserveScroll: true,
    preserveState: true
  });
};

const enviarAnexo = (event) => {
  const file = event.target.files[0];
  if (!file || !tarefaSelecionada.value) return;

  const formData = new FormData();
  formData.append('arquivo', file);

  router.post(`/kanban/anexo/${tarefaSelecionada.value.id}`, formData, {
    preserveScroll: true,
    onSuccess: () => {
      if (arquivoAnexoInput.value) arquivoAnexoInput.value.value = '';
    }
  });
};

const alternarSelecaoBacklog = (id) => {
  const index = tarefasSelecionadasBacklog.value.indexOf(id);
  if (index > -1) {
    tarefasSelecionadasBacklog.value.splice(index, 1);
  } else {
    tarefasSelecionadasBacklog.value.push(id);
  }
};

const criarTarefaBacklog = () => {
  if (!novaTarefaTitulo.value) return;
  router.post(`/equipes/${props.equipe.id}/backlog/criar`, {
    titulo: novaTarefaTitulo.value,
    descricao: novaTarefaDescricao.value
  }, {
    onSuccess: () => {
      novaTarefaTitulo.value = '';
      novaTarefaDescricao.value = '';
      modalNovaTarefaBacklogAberto.value = false;
    }
  });
};

const iniciarNovaSprint = () => {
  if (tarefasSelecionadasBacklog.value.length === 0) return;
  router.post(`/equipes/${props.equipe.id}/iniciar-sprint`, {
    tarefas_ids: tarefasSelecionadasBacklog.value
  });
};

const confirmarEncerramento = () => {
  if (!props.sprint) return;
  router.post(`/kanban/encerrar-sprint/${props.sprint.id}`, {
    feedback: feedbackProfessorInput.value
  }, {
    onSuccess: () => {
      modalEncerramentoAberto.value = false;
    }
  });
};
</script>

<template>
  <Head :title="`${equipe.nome} - CTI Bauru`" />

  <AppLayout :userRole="userRole" :userName="`Equipe: ${equipe.nome}`">
    <!-- Breadcrumb -->
    <Breadcrumb :items="[{ label: 'Equipes', href: '/equipes' }, { label: equipe.nome }]" />

    <!-- Header do Ambiente de Trabalho com Alta Densidade SIEP -->
    <div class="bg-white border border-slate-200 rounded-md p-3 mb-3 shadow-sm flex flex-wrap items-center justify-between gap-3">
      <div>
        <div class="flex items-center space-x-2">
          <h1 class="text-xl font-extrabold text-slate-800 tracking-tight">{{ equipe.nome }}</h1>
          <span class="text-xs font-mono font-bold bg-slate-100 text-slate-700 px-2 py-0.5 rounded border">
            {{ equipe.ano || 2026 }}
          </span>
        </div>
        <div class="flex items-center space-x-2 mt-1">
          <p class="text-xs text-slate-500">{{ equipe.descricao || 'Projeto de Engenharia de Software CTI' }}</p>
          <button 
            @click="modalIntegrantesAberto = true" 
            class="inline-flex items-center space-x-1 text-[11px] font-bold text-[#0F2537] bg-slate-100 hover:bg-slate-200 px-2 py-0.5 rounded border border-slate-300 transition cursor-pointer"
          >
            <Users class="w-3.5 h-3.5 text-slate-600" />
            <span>Ver {{ equipe.alunos?.length || 0 }} Integrante(s)</span>
          </button>
        </div>
      </div>

      <!-- Menu Principal de Navegação (As 3 Abas Estritas) -->
      <div class="flex items-center bg-slate-100 p-1 rounded-lg border border-slate-200 text-xs font-bold">
        <Link 
          :href="`/equipes/${equipe.id}?aba=backlog`" 
          :class="[
            'px-3 py-1.5 rounded transition flex items-center space-x-1.5',
            abaAtiva === 'backlog' ? 'bg-[#0F2537] text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'
          ]"
        >
          <ListTodo class="w-3.5 h-3.5" />
          <span>1. Backlog</span>
        </Link>

        <Link 
          :href="`/equipes/${equipe.id}?aba=sprint-atual`" 
          :class="[
            'px-3 py-1.5 rounded transition flex items-center space-x-1.5',
            abaAtiva === 'sprint-atual' ? 'bg-[#0F2537] text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'
          ]"
        >
          <Kanban class="w-3.5 h-3.5" />
          <span>2. Sprint Atual</span>
        </Link>

        <Link 
          :href="`/equipes/${equipe.id}?aba=anteriores`" 
          :class="[
            'px-3 py-1.5 rounded transition flex items-center space-x-1.5',
            abaAtiva === 'anteriores' ? 'bg-[#0F2537] text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'
          ]"
        >
          <History class="w-3.5 h-3.5" />
          <span>3. Sprints Anteriores</span>
        </Link>
      </div>
    </div>

    <!-- Conteúdo por Abas -->

    <!-- ABA 1: BACKLOG -->
    <div v-if="abaAtiva === 'backlog'" class="bg-white rounded-md border border-slate-200 shadow-sm overflow-hidden">
      <div class="bg-slate-50 border-b border-slate-200 p-3 flex flex-wrap items-center justify-between gap-2">
        <div>
          <h2 class="text-sm font-bold text-slate-800">Backlog de Tarefas</h2>
          <p class="text-xs text-slate-500">Tarefas não atribuídas a nenhuma Sprint. Apenas o PO/Professor pode criar e gerenciar.</p>
        </div>

        <div v-if="isPO || isOrientador" class="flex items-center space-x-2">
          <button 
            @click="modalNovaTarefaBacklogAberto = true"
            class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs px-3 py-1.5 rounded transition flex items-center space-x-1 cursor-pointer"
          >
            <Plus class="w-3.5 h-3.5" />
            <span>Nova Tarefa</span>
          </button>

          <button 
            @click="iniciarNovaSprint"
            :disabled="tarefasSelecionadasBacklog.length === 0"
            class="bg-[#0F2537] hover:bg-[#1A365D] disabled:opacity-50 text-white font-bold text-xs px-3 py-1.5 rounded shadow-sm transition flex items-center space-x-1 cursor-pointer"
          >
            <Send class="w-3.5 h-3.5" />
            <span>Puxar Selecionadas para Nova Sprint</span>
          </button>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse siep-table-compact">
          <thead>
            <tr class="bg-slate-100 text-slate-700 uppercase font-bold text-[11px] tracking-wider border-b border-slate-200">
              <th v-if="isPO || isOrientador" class="w-10 text-center py-2">Sel.</th>
              <th class="w-16">#ID</th>
              <th>Título da Tarefa</th>
              <th>Descrição</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 text-xs">
            <tr 
              v-for="t in tarefasBacklog" 
              :key="t.id" 
              @click="abrirModalTarefa(t)"
              class="even:bg-slate-50 hover:bg-blue-50/50 cursor-pointer"
            >
              <td v-if="isPO || isOrientador" class="text-center" @click.stop>
                <input 
                  type="checkbox" 
                  :value="t.id" 
                  @change="alternarSelecaoBacklog(t.id)"
                  class="rounded border-slate-300 text-[#0F2537]"
                />
              </td>
              <td class="font-mono font-bold text-slate-600">#{{ t.id }}</td>
              <td class="font-bold text-slate-900">{{ t.titulo }}</td>
              <td class="text-slate-600">{{ t.descricao || 'Sem descrição' }}</td>
            </tr>
            <tr v-if="!tarefasBacklog || tarefasBacklog.length === 0">
              <td colspan="4" class="text-center py-6 text-slate-500 font-medium">
                Nenhuma tarefa no Backlog no momento.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ABA 2: SPRINT ATUAL (KANBAN CLÁSSICO) -->
    <div v-else-if="abaAtiva === 'sprint-atual'">
      <div v-if="sprint" class="bg-white border border-slate-200 rounded-md p-3 mb-3 shadow-sm flex flex-wrap items-center justify-between gap-3">
        <div>
          <div class="flex items-center space-x-2">
            <h2 class="text-base font-bold text-slate-800">{{ sprint.nome }}</h2>
            <span :class="['text-xs font-semibold px-2 py-0.5 rounded border', sprint.bloqueadaPorPrazo ? 'bg-amber-50 text-amber-800 border-amber-300' : 'bg-emerald-50 text-emerald-800 border-emerald-300']">
              {{ sprint.bloqueadaPorPrazo ? 'Sprint Finalizada (Somente Leitura)' : 'Sprint Ativa' }}
            </span>
          </div>
          <p class="text-xs text-slate-500 mt-0.5">Progresso automático: <strong>{{ sprint.percentual }}%</strong></p>
        </div>

        <div v-if="isOrientador && !sprint.encerrada" class="flex items-center space-x-2">
          <button 
            @click="modalEncerramentoAberto = true"
            class="bg-[#9B2C2C] hover:bg-[#7B1D1D] text-white font-bold text-xs px-3 py-1.5 rounded shadow-sm transition flex items-center space-x-1 cursor-pointer"
          >
            <CheckCircle2 class="w-4 h-4" />
            <span>Encerrar Sprint e Avaliar</span>
          </button>
        </div>
      </div>

      <div v-if="!sprint" class="bg-amber-50 border border-amber-200 rounded-md p-6 text-center">
        <AlertCircle class="w-8 h-8 text-amber-600 mx-auto mb-2" />
        <h3 class="text-sm font-bold text-amber-900">Nenhuma Sprint Ativa no Momento</h3>
        <p class="text-xs text-amber-700 mt-1 mb-3">Vá até a aba Backlog para selecionar tarefas e iniciar uma nova Sprint.</p>
        
        <Link 
          :href="`/equipes/${equipe.id}?aba=backlog`" 
          class="inline-flex items-center space-x-1.5 bg-[#0F2537] hover:bg-[#1A365D] text-white font-bold text-xs px-3.5 py-2 rounded shadow-sm transition"
        >
          <ListTodo class="w-4 h-4 text-emerald-400" />
          <span>Ir para o Backlog e Iniciar Sprint</span>
        </Link>
      </div>

      <!-- Quadro Kanban de 4 Colunas Clássicas -->
      <div 
        v-else 
        :class="[
          'grid grid-cols-1 md:grid-cols-4 gap-3',
          sprint.bloqueadaPorPrazo ? 'opacity-75' : ''
        ]"
      >
        <div 
          v-for="coluna in colunas" 
          :key="coluna.id"
          @dragover.prevent="onDragOver(coluna.id)"
          @dragleave="onDragLeave(coluna.id)"
          @drop="onDrop(coluna.id)"
          :class="[
            'bg-slate-200/70 border rounded-md p-2.5 flex flex-col min-h-[450px]',
            hoverColuna === coluna.id ? 'bg-blue-100/70 border-blue-400 ring-2 ring-blue-300' : 'border-slate-300/80'
          ]"
        >
          <div class="flex items-center justify-between pb-2 mb-2 border-b border-slate-300">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">{{ coluna.titulo }}</h3>
            <span class="bg-slate-300 text-slate-800 text-[11px] font-bold px-1.5 py-0.5 rounded font-mono">
              {{ tarefas.filter(t => t.coluna_id === coluna.id).length }}
            </span>
          </div>

          <div class="flex-1 space-y-2">
            <div 
              v-for="tarefa in tarefas.filter(t => t.coluna_id === coluna.id)" 
              :key="tarefa.id"
              :draggable="canDrag"
              @dragstart="onDragStart($event, tarefa.id)"
              @click="abrirModalTarefa(tarefa)"
              :class="[
                'bg-white border border-slate-200 rounded p-2.5 shadow-sm hover:shadow transition select-none cursor-pointer',
                !canDrag ? 'cursor-not-allowed' : ''
              ]"
            >
              <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] font-mono font-bold text-slate-500">#{{ tarefa.id }}</span>
                <span class="text-[9px] font-extrabold uppercase px-1.5 py-0.2 rounded border bg-blue-50 text-blue-800 border-blue-300">Dev</span>
              </div>
              <h4 class="text-xs font-semibold text-slate-900 leading-tight mb-2">{{ tarefa.titulo }}</h4>
              
              <div class="flex items-center justify-between text-[11px] text-slate-500 pt-1.5 border-t border-slate-100">
                <span class="flex items-center space-x-1 font-medium text-slate-700">
                  <UserCheck class="w-3 h-3 text-slate-400" />
                  <span>{{ tarefa.responsaveis?.[0]?.nome || 'Sem responsável' }}</span>
                </span>
                <div class="flex items-center space-x-2">
                  <span v-if="tarefa.comentarios?.length > 0" class="flex items-center space-x-0.5">
                    <MessageSquare class="w-3 h-3 text-slate-400" />
                    <span>{{ tarefa.comentarios.length }}</span>
                  </span>
                  <span v-if="tarefa.anexos?.length > 0" class="flex items-center space-x-0.5">
                    <Paperclip class="w-3 h-3 text-slate-400" />
                    <span>{{ tarefa.anexos.length }}</span>
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ABA 3: SPRINTS ANTERIORES (HISTÓRICO SOMENTE LEITURA) -->
    <div v-else-if="abaAtiva === 'anteriores'" class="space-y-3">
      <div class="bg-white p-3 rounded border border-slate-200 shadow-sm">
        <h2 class="text-sm font-bold text-slate-800">Arquivo Histórico de Sprints Encerradas</h2>
        <p class="text-xs text-slate-500">Visualize os Kanbans encerrados no modo Somente Leitura e o feedback anotado.</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div 
          v-for="s in sprintsAnteriores" 
          :key="s.id" 
          class="bg-white border border-slate-200 rounded-md p-3 shadow-sm hover:border-slate-400 transition flex flex-col justify-between"
        >
          <div>
            <div class="flex justify-between items-center mb-1">
              <span class="text-xs font-bold text-slate-800">Sprint {{ s.sequencia }}</span>
              <span class="text-xs font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                {{ s.percentual || 0 }}% Concluído
              </span>
            </div>

            <p class="text-xs text-slate-500 mb-2">Encerrada em: <strong>{{ s.dt_fim || 'Sem data' }}</strong></p>

            <div v-if="s.feedback" class="bg-slate-50 p-2 rounded border border-slate-200 text-xs text-slate-700 mb-3">
              <strong class="text-slate-900 block font-semibold mb-0.5">Feedback do Professor:</strong>
              {{ s.feedback }}
            </div>
          </div>

          <Link 
            :href="`/equipes/${equipe.id}?aba=sprint-atual&sprint_id=${s.id}`" 
            class="w-full inline-flex justify-center items-center space-x-1 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs py-1.5 rounded transition"
          >
            <Eye class="w-3.5 h-3.5" />
            <span>Ver Kanban Congelado</span>
          </Link>
        </div>
      </div>
    </div>

    <!-- FASE 4: MODAL DE TAREFA E COLABORAÇÃO -->
    <div v-if="modalTarefaAberto" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-xl overflow-hidden border border-slate-200 flex flex-col max-h-[85vh]">
        <!-- Topbar do Modal com Título Editável -->
        <div class="bg-[#0F2537] px-4 py-3 text-white flex items-center justify-between gap-3">
          <div class="flex items-center space-x-2 flex-1">
            <span class="text-xs font-mono font-bold text-slate-300">#{{ tarefaSelecionada?.id }}</span>
            <input 
              v-model="tarefaSelecionada.titulo"
              @change="salvarEdicaoTarefa"
              type="text"
              placeholder="Título da Tarefa"
              class="bg-white/10 hover:bg-white/20 focus:bg-white focus:text-slate-900 text-white font-bold text-sm px-2 py-1 rounded border border-white/20 focus:outline-none w-full transition"
            />
          </div>
          <button @click="modalTarefaAberto = false" class="text-slate-300 hover:text-white cursor-pointer shrink-0">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="p-4 space-y-4 overflow-y-auto flex-1">
          <!-- Descrição Editável -->
          <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Descrição (Editável)</label>
            <textarea 
              v-model="tarefaSelecionada.descricao"
              @change="salvarEdicaoTarefa"
              rows="3"
              placeholder="Digite os detalhes e orientações desta tarefa..."
              class="w-full text-xs text-slate-800 bg-slate-50 p-2.5 rounded border border-slate-300 focus:bg-white focus:ring-1 focus:ring-slate-500 focus:outline-none transition"
            ></textarea>
          </div>

          <!-- Seção de Atribuição de Responsáveis da Equipe -->
          <div class="bg-blue-50/70 p-3 rounded border border-blue-200 space-y-2">
            <div class="flex items-center justify-between">
              <label class="text-xs font-bold text-slate-800 flex items-center space-x-1">
                <UserCheck class="w-4 h-4 text-blue-700" />
                <span>Atribuir Alunos Responsáveis</span>
              </label>
              <span class="text-[10px] text-slate-500 font-mono">
                {{ tarefaSelecionada?.responsaveis?.length || 0 }} Selecionado(s)
              </span>
            </div>

            <!-- Lista de Alunos da Equipe com Clicks/Checkboxes -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 pt-1">
              <button 
                v-for="aluno in equipe.alunos" 
                :key="aluno.id"
                @click="assumirTarefa(aluno.id)"
                :class="[
                  'text-left text-xs px-2.5 py-1.5 rounded border transition flex items-center justify-between cursor-pointer select-none',
                  tarefaSelecionada?.responsaveis?.some(r => r.id === aluno.id)
                    ? 'bg-[#0F2537] text-white border-[#0F2537] font-bold shadow-xs'
                    : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100 font-medium'
                ]"
              >
                <div class="flex items-center space-x-1.5 truncate">
                  <span class="font-mono text-[10px] opacity-75">#{{ aluno.n_chamada || '-' }}</span>
                  <span class="truncate">{{ aluno.nome }}</span>
                </div>
                <CheckCircle2 v-if="tarefaSelecionada?.responsaveis?.some(r => r.id === aluno.id)" class="w-3.5 h-3.5 text-emerald-400 shrink-0" />
              </button>
            </div>
          </div>

          <!-- Timeline Unificada (Comentários com Destaque para Professor) -->
          <div class="border-t pt-3 space-y-2">
            <h4 class="text-xs font-bold text-slate-800 flex items-center space-x-1">
              <MessageSquare class="w-3.5 h-3.5 text-slate-500" />
              <span>Timeline de Comentários</span>
            </h4>

            <div class="space-y-2 max-h-40 overflow-y-auto p-1">
              <div 
                v-for="c in tarefaSelecionada?.comentarios" 
                :key="c.id"
                :class="[
                  'p-2 rounded text-xs border',
                  c.is_professor ? 'bg-amber-50 border-amber-300 text-amber-950 font-medium' : 'bg-slate-50 border-slate-200 text-slate-800'
                ]"
              >
                <div class="flex items-center justify-between text-[10px] font-bold mb-0.5">
                  <span :class="c.is_professor ? 'text-amber-900' : 'text-slate-600'">
                    {{ c.autor_nome }} {{ c.is_professor ? '(Orientação do Professor)' : '' }}
                  </span>
                </div>
                <p>{{ c.texto }}</p>
              </div>
            </div>

            <div class="flex gap-2 pt-1">
              <input 
                v-model="novoComentarioText" 
                type="text" 
                placeholder="Escreva uma orientação ou comentário..."
                class="flex-1 border border-slate-300 rounded px-2.5 py-1 text-xs focus:ring-1 focus:ring-slate-500 focus:outline-none"
              />
              <button 
                @click="enviarComentario" 
                class="bg-[#0F2537] text-white px-3 py-1 rounded text-xs font-semibold hover:bg-[#1A365D]"
              >
                Enviar
              </button>
            </div>
          </div>

          <!-- Anexos Livres com Preservação do Nome Original -->
          <div class="border-t pt-3 space-y-2">
            <h4 class="text-xs font-bold text-slate-800 flex items-center space-x-1">
              <Paperclip class="w-3.5 h-3.5 text-slate-500" />
              <span>Anexos da Tarefa</span>
            </h4>

            <div class="space-y-1">
              <div v-for="a in tarefaSelecionada?.anexos" :key="a.id" class="text-xs flex items-center justify-between bg-slate-50 p-2 rounded border border-slate-200">
                <span class="font-medium text-slate-800 truncate max-w-[250px]">{{ a.nome_original }}</span>
                <span class="text-[10px] text-slate-500">Enviado por: {{ a.autor_nome }}</span>
              </div>
            </div>

            <div class="pt-1">
              <input 
                ref="arquivoAnexoInput"
                type="file" 
                @change="enviarAnexo"
                class="text-xs text-slate-600 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 cursor-pointer"
              />
            </div>
          </div>
        </div>

        <div class="bg-slate-50 px-4 py-2.5 border-t border-slate-200 flex justify-end">
          <button @click="modalTarefaAberto = false" class="px-3 py-1.5 rounded border border-slate-300 text-xs font-medium text-slate-700 hover:bg-slate-100 cursor-pointer">
            Fechar
          </button>
        </div>
      </div>
    </div>

    <!-- FASE 5: MODAL DE RITO DE ENCERRAMENTO (REUNIÃO DE REVIEW DO PROFESSOR) -->
    <div v-if="modalEncerramentoAberto" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md overflow-hidden border border-slate-200">
        <div class="bg-[#9B2C2C] px-4 py-3 text-white flex items-center justify-between">
          <h3 class="text-sm font-bold">Rito de Encerramento e Avaliação</h3>
          <button @click="modalEncerramentoAberto = false" class="text-white cursor-pointer">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="p-4 space-y-3">
          <p class="text-xs text-slate-600">
            Confirme os resultados da <strong>{{ sprint?.nome }}</strong>. A Sprint será movida para as "Sprints Anteriores" em modo leitura e as tarefas pendentes serão repassadas ao Backlog.
          </p>

          <div class="bg-slate-50 p-3 rounded border border-slate-200">
            <span class="text-xs font-bold text-slate-800">Percentual de Conclusão Calculado:</span>
            <span class="text-base font-extrabold text-emerald-700 block mt-0.5">{{ sprint?.percentual || 0 }}%</span>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Feedback Qualitativo do Professor</label>
            <textarea 
              v-model="feedbackProfessorInput"
              rows="3"
              placeholder="Digite suas orientações e parecer qualitativo para a equipe..."
              class="w-full border border-slate-300 rounded px-2.5 py-1.5 text-xs text-slate-800 focus:ring-1 focus:ring-slate-500 focus:outline-none"
            ></textarea>
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

    <!-- MODAL DE CRIAR TAREFA NO BACKLOG -->
    <div v-if="modalNovaTarefaBacklogAberto" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md overflow-hidden border border-slate-200">
        <div class="bg-[#0F2537] px-4 py-3 text-white flex items-center justify-between">
          <h3 class="text-sm font-bold">Nova Tarefa no Backlog</h3>
          <button @click="modalNovaTarefaBacklogAberto = false" class="text-white cursor-pointer">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="p-4 space-y-3">
          <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Título da Tarefa</label>
            <input 
              v-model="novaTarefaTitulo" 
              type="text" 
              placeholder="Ex: Criar Diagrama de Entidade-Relacionamento"
              class="w-full border border-slate-300 rounded px-2.5 py-1.5 text-xs text-slate-800"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Descrição</label>
            <textarea 
              v-model="novaTarefaDescricao" 
              rows="3" 
              placeholder="Detalhes adicionais..."
              class="w-full border border-slate-300 rounded px-2.5 py-1.5 text-xs text-slate-800"
            ></textarea>
          </div>
        </div>

        <div class="bg-slate-50 px-4 py-2.5 border-t border-slate-200 flex justify-end space-x-2">
          <button @click="modalNovaTarefaBacklogAberto = false" class="px-3 py-1.5 rounded border border-slate-300 text-xs font-medium text-slate-700 hover:bg-slate-100 cursor-pointer">
            Cancelar
          </button>
          <button @click="criarTarefaBacklog" class="px-3 py-1.5 rounded bg-emerald-700 text-white text-xs font-semibold hover:bg-emerald-800 cursor-pointer">
            Salvar no Backlog
          </button>
        </div>
      </div>
    </div>
    <!-- MODAL DE INTEGRANTES DA EQUIPE -->
    <div v-if="modalIntegrantesAberto" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-lg overflow-hidden border border-slate-200">
        <div class="bg-[#0F2537] px-4 py-3 text-white flex items-center justify-between">
          <h3 class="text-sm font-bold flex items-center space-x-2">
            <Users class="w-4 h-4 text-emerald-400" />
            <span>Integrantes da {{ equipe.nome }}</span>
          </h3>
          <button @click="modalIntegrantesAberto = false" class="text-slate-300 hover:text-white cursor-pointer">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="p-4 overflow-y-auto max-h-[60vh]">
          <table class="w-full text-left border-collapse siep-table-compact">
            <thead>
              <tr class="bg-slate-100 text-slate-700 uppercase font-bold text-[10px] tracking-wider border-b border-slate-200">
                <th class="w-10 text-center py-2">Nº</th>
                <th>Nome do Aluno</th>
                <th>E-mail Institucional</th>
                <th class="text-center">Papel</th>
                <th class="text-center">RA</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 text-xs">
              <tr v-for="aluno in equipe.alunos" :key="aluno.id" class="hover:bg-slate-50">
                <td class="text-center font-mono font-bold text-slate-500 py-2">{{ aluno.n_chamada || '-' }}</td>
                <td class="font-bold text-slate-900">{{ aluno.nome }}</td>
                <td class="text-slate-600 font-mono text-[11px]">{{ aluno.email }}</td>
                <td class="text-center">
                  <span :class="[
                    'text-[10px] font-extrabold uppercase px-1.5 py-0.5 rounded border',
                    aluno.papel === 'PO' ? 'bg-amber-50 text-amber-800 border-amber-300' : 'bg-slate-100 text-slate-700 border-slate-300'
                  ]">
                    {{ aluno.papel || 'Dev' }}
                  </span>
                </td>
                <td class="text-center font-mono text-[11px] text-slate-500">{{ aluno.ra || '-' }}</td>
              </tr>
              <tr v-if="!equipe.alunos || equipe.alunos.length === 0">
                <td colspan="5" class="text-center py-6 text-slate-500 font-medium">
                  Nenhum aluno vinculado a esta equipe no momento.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="bg-slate-50 px-4 py-2.5 border-t border-slate-200 flex justify-end">
          <button @click="modalIntegrantesAberto = false" class="px-3.5 py-1.5 rounded border border-slate-300 text-xs font-medium text-slate-700 hover:bg-slate-100 cursor-pointer">
            Fechar
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
