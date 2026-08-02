<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
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
  Users,
  GitCommit,
  FileText,
  Github,
  Globe,
  Pencil,
  Trash2,
  Sparkles,
  Loader2,
  Award
} from 'lucide-vue-next';

const props = defineProps({
  equipe: Object,
  abaAtiva: String,
  userRole: String,
  userId: Number,
  isOrientador: Boolean,
  isTL: Boolean,
  canManageColunas: Boolean,
  isPO: Boolean,
  sprint: Object,
  colunas: Array,
  todasColunas: Array,
  tarefasIniciais: Array,
  tarefasBacklog: Array,
  sprintsAnteriores: Array,
  sprintsAgrupadas: Object
});

// Tarefas do Kanban
const tarefas = ref([...(props.tarefasIniciais || [])]);
const tarefasSelecionadasBacklog = ref([]);

// Modais
const modalTarefaAberto = ref(false);
const modalHistoricoAberto = ref(false);
const tarefaSelecionada = ref(null);

const modalIntegrantesAberto = ref(false);

const modalEncerramentoAberto = ref(false);
const feedbackProfessorInput = ref('');
const isCarregandoIA = ref(false);
const avaliacaoSprint = ref({
  entrega_valor: 10.0,
  qualidade_tecnica: 10.0,
  processos_rituais: 10.0,
  documentacao: 10.0,
  observacoes: ''
});
const avaliacoesIndividuais = ref([]);

const modalNovaTarefaBacklogAberto = ref(false);
const novaTarefaTitulo = ref('');
const novaTarefaDescricao = ref('');

// Modal de Início de Sprint com Bimestre
const modalInicioSprintAberto = ref(false);
const bimestreSelecionadoSprint = ref(1);
const sequenciaInicialSprint = ref(1);

// Form de Comentário e Anexo no Modal
const novoComentarioText = ref('');
const arquivoAnexoInput = ref(null);

// Edição de comentário inline
const comentarioEmEdicaoId = ref(null);
const comentarioEmEdicaoTexto = ref('');

// Helper: verifica se o usuário logado é o autor do comentário
const isAutorComentario = (c) => {
  if (props.userRole === 'aluno') return c.aluno_id === props.userId;
  if (props.userRole === 'professor') return c.prof_id === props.userId;
  return false;
};

// Helper: verifica se o usuário logado é o autor do anexo
const isAutorAnexo = (a) => {
  if (props.userRole === 'aluno') return a.aluno_id === props.userId;
  if (props.userRole === 'professor') return a.prof_id === props.userId;
  return false;
};

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

// ─── Helpers para sincronizar tarefaSelecionada após updates ───────────────
const sincronizarTarefaSelecionada = (novasTarefas) => {
  if (!tarefaSelecionada.value || !modalTarefaAberto.value) return;
  const atualizada = novasTarefas?.find(t => t.id === tarefaSelecionada.value.id);
  if (!atualizada) return;
  // Preserva título e descrição que o usuário pode estar editando
  const tituloAtual = tarefaSelecionada.value.titulo;
  const descricaoAtual = tarefaSelecionada.value.descricao;
  tarefaSelecionada.value = { ...atualizada, titulo: tituloAtual, descricao: descricaoAtual };
};

// ─── Watchers: sincroniza estado local quando Inertia recarrega props ───────
watch(() => props.tarefasIniciais, (novas) => {
  tarefas.value = [...(novas || [])];
  sincronizarTarefaSelecionada(novas);
}, { deep: true });

watch(() => props.tarefasBacklog, (novas) => {
  sincronizarTarefaSelecionada(novas);
  if (novas && novas.length > 0) {
    // Pré-seleciona APENAS as tarefas que vieram da sprint encerrada imediatamente anterior
    tarefasSelecionadasBacklog.value = novas
      .filter(t => t.veio_da_sprint_anterior)
      .map(t => t.id);
  } else {
    tarefasSelecionadasBacklog.value = [];
  }
}, { deep: true, immediate: true });

// ─── Polling: simula realtime recarregando só os dados das tarefas ──────────
const isSyncing = ref(false);
let pollingTimer = null;

const iniciarPolling = () => {
  pollingTimer = setInterval(() => {
    isSyncing.value = true;
    router.reload({
      only: ['tarefasIniciais', 'tarefasBacklog', 'colunas', 'todasColunas'],
      preserveScroll: true,
      preserveState: true,
      onFinish: () => { isSyncing.value = false; }
    });
  }, 5000); // a cada 5 segundos
};

// ─── Gerenciamento de Colunas ──────────────────────────────────────────────
const ORDEM_PADRAO_TITULOS = ['A FAZER', 'FAZENDO', 'EM TESTE', 'CONCLUÍDO'];

const modalGerenciarColunasAberto = ref(false);
const colunasEditaveis = ref([]);
const novaColunasTitulo = ref('');
const erroOrdem = ref('');
const colunaDragIdx = ref(null);

const abrirGerenciarColunas = () => {
  colunasEditaveis.value = [...(props.todasColunas || [])].sort((a, b) => a.sequencia - b.sequencia);
  erroOrdem.value = '';
  novaColunasTitulo.value = '';
  modalGerenciarColunasAberto.value = true;
};

const validarOrdemLocal = (cols) => {
  if (cols[cols.length - 1]?.titulo !== 'CONCLUÍDO') {
    return 'CONCLUÍDO deve ser a última coluna.';
  }
  let prevIdx = -1;
  for (const col of cols) {
    const idx = ORDEM_PADRAO_TITULOS.indexOf(col.titulo);
    if (idx === -1) continue; // coluna custom, ignora
    if (idx <= prevIdx) return 'A ordem obrigatória é: A FAZER → FAZENDO → EM TESTE → CONCLUÍDO (colunas customizadas podem ir entre elas).';
    prevIdx = idx;
  }
  return '';
};

// Drag-drop no modal
const onColunaDragStart = (e, idx) => {
  colunaDragIdx.value = idx;
  e.dataTransfer.effectAllowed = 'move';
};
const onColunaDragOver = (e, idx) => {
  e.preventDefault();
  if (colunaDragIdx.value === null || colunaDragIdx.value === idx) return;
  const arr = [...colunasEditaveis.value];
  const [moved] = arr.splice(colunaDragIdx.value, 1);
  arr.splice(idx, 0, moved);
  const erro = validarOrdemLocal(arr);
  erroOrdem.value = erro;
  if (!erro) { colunasEditaveis.value = arr; colunaDragIdx.value = idx; }
};
const onColunaDragEnd = () => { colunaDragIdx.value = null; };

// Mover com setas (alternativa ao drag)
const moverColuna = (idx, direcao) => {
  const arr = [...colunasEditaveis.value];
  const novoIdx = idx + direcao;
  if (novoIdx < 0 || novoIdx >= arr.length) return;
  [arr[idx], arr[novoIdx]] = [arr[novoIdx], arr[idx]];
  const erro = validarOrdemLocal(arr);
  erroOrdem.value = erro;
  if (!erro) colunasEditaveis.value = arr;
};

const salvarOrdemColunas = () => {
  const erro = validarOrdemLocal(colunasEditaveis.value);
  if (erro) { erroOrdem.value = erro; return; }
  const ordem = colunasEditaveis.value.map(c => ({ id: c.id, titulo: c.titulo }));
  router.post(`/equipes/${props.equipe.id}/colunas/reordenar`, { ordem }, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => { modalGerenciarColunasAberto.value = false; }
  });
};

const adicionarColuna = () => {
  if (!novaColunasTitulo.value.trim()) return;
  router.post(`/equipes/${props.equipe.id}/colunas`, { titulo: novaColunasTitulo.value.trim() }, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      novaColunasTitulo.value = '';
      colunasEditaveis.value = [...(props.todasColunas || [])].sort((a, b) => a.sequencia - b.sequencia);
    }
  });
};

const removerColuna = (col) => {
  if (col.is_padrao) return;
  if (col.tem_tarefas) { alert('Mova todas as tarefas desta coluna antes de removê-la.'); return; }
  if (!confirm(`Remover a coluna "${col.titulo}"?`)) return;
  router.delete(`/equipes/${props.equipe.id}/colunas/${col.id}`, {}, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      colunasEditaveis.value = colunasEditaveis.value.filter(c => c.id !== col.id);
    }
  });
};

onMounted(() => iniciarPolling());
onUnmounted(() => { if (pollingTimer) clearInterval(pollingTimer); });

// ─── Abrir modal ────────────────────────────────────────────────────────────
const abrirModalTarefa = (tarefa) => {
  tarefaSelecionada.value = tarefa;
  modalTarefaAberto.value = true;
};

// ─── Responsáveis (optimistic) ───────────────────────────────────────────
const assumirTarefa = (alunoId = null) => {
  if (!tarefaSelecionada.value) return;
  // Optimistic: atualiza local imediatamente
  const aluno = props.equipe.alunos?.find(a => a.id === alunoId);
  if (aluno) {
    const idx = tarefaSelecionada.value.responsaveis.findIndex(r => r.id === aluno.id);
    if (idx > -1) tarefaSelecionada.value.responsaveis.splice(idx, 1);
    else tarefaSelecionada.value.responsaveis.push({ id: aluno.id, nome: aluno.nome });
  }
  router.post(`/kanban/assumir-tarefa/${tarefaSelecionada.value.id}`, { aluno_id: alunoId }, {
    preserveScroll: true, preserveState: true
  });
};

// ─── Comentários ─────────────────────────────────────────────────────────
const enviarComentario = () => {
  if (!novoComentarioText.value || !tarefaSelecionada.value) return;
  const texto = novoComentarioText.value;
  novoComentarioText.value = ''; // limpa input imediatamente
  router.post(`/kanban/comentario/${tarefaSelecionada.value.id}`, { texto }, {
    preserveScroll: true,
    preserveState: true
    // O watch no props.tarefasIniciais/Backlog vai sincronizar o novo comentário
  });
};

const iniciarEdicaoComentario = (c) => {
  comentarioEmEdicaoId.value = c.id;
  comentarioEmEdicaoTexto.value = c.texto;
};

const salvarEdicaoComentario = (c) => {
  if (!comentarioEmEdicaoTexto.value) return;
  const novoTexto = comentarioEmEdicaoTexto.value;
  // Optimistic: atualiza o texto localmente agora
  const comentario = tarefaSelecionada.value.comentarios.find(item => item.id === c.id);
  if (comentario) comentario.texto = novoTexto;
  comentarioEmEdicaoId.value = null;
  comentarioEmEdicaoTexto.value = '';
  router.post(`/kanban/comentario/${c.id}/editar`, { texto: novoTexto }, {
    preserveScroll: true, preserveState: true
  });
};

const cancelarEdicaoComentario = () => {
  comentarioEmEdicaoId.value = null;
  comentarioEmEdicaoTexto.value = '';
};

const deletarComentario = (c) => {
  if (!confirm('Deseja realmente apagar este comentário?')) return;
  // Optimistic: remove da lista local imediatamente
  const idx = tarefaSelecionada.value.comentarios.findIndex(item => item.id === c.id);
  if (idx > -1) tarefaSelecionada.value.comentarios.splice(idx, 1);
  // Também atualiza no array tarefas (card do kanban)
  const t = tarefas.value.find(item => item.id === tarefaSelecionada.value.id);
  if (t) { const ti = t.comentarios.findIndex(item => item.id === c.id); if (ti > -1) t.comentarios.splice(ti, 1); }
  router.delete(`/kanban/comentario/${c.id}`, {}, { preserveScroll: true, preserveState: true });
};

// ─── Anexos ───────────────────────────────────────────────────────────────
const enviarAnexo = (event) => {
  const file = event.target.files[0];
  if (!file || !tarefaSelecionada.value) return;
  const formData = new FormData();
  formData.append('arquivo', file);
  router.post(`/kanban/anexo/${tarefaSelecionada.value.id}`, formData, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => { if (arquivoAnexoInput.value) arquivoAnexoInput.value.value = ''; }
  });
};

const deletarAnexo = (a) => {
  if (!confirm(`Deseja remover o anexo "${a.nome_original}"?`)) return;
  // Optimistic: remove da lista local imediatamente
  const idx = tarefaSelecionada.value.anexos.findIndex(item => item.id === a.id);
  if (idx > -1) tarefaSelecionada.value.anexos.splice(idx, 1);
  // Também atualiza no array tarefas (card do kanban)
  const t = tarefas.value.find(item => item.id === tarefaSelecionada.value.id);
  if (t) { const ti = t.anexos.findIndex(item => item.id === a.id); if (ti > -1) t.anexos.splice(ti, 1); }
  router.delete(`/kanban/anexo/${a.id}`, {}, { preserveScroll: true, preserveState: true });
};

// ─── Tarefa (título/descrição) ─────────────────────────────────────────
const salvarEdicaoTarefa = () => {
  if (!tarefaSelecionada.value || !tarefaSelecionada.value.titulo) return;
  router.post(`/kanban/editar-tarefa/${tarefaSelecionada.value.id}`, {
    titulo: tarefaSelecionada.value.titulo,
    descricao: tarefaSelecionada.value.descricao
  }, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      const t = tarefas.value.find(item => item.id === tarefaSelecionada.value.id);
      if (t) { t.titulo = tarefaSelecionada.value.titulo; t.descricao = tarefaSelecionada.value.descricao; }
    }
  });
};

// ─── Backlog / Sprint ─────────────────────────────────────────────────
const alternarSelecaoBacklog = (id) => {
  const index = tarefasSelecionadasBacklog.value.indexOf(id);
  if (index > -1) tarefasSelecionadasBacklog.value.splice(index, 1);
  else tarefasSelecionadasBacklog.value.push(id);
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
  bimestreSelecionadoSprint.value = 1;
  sequenciaInicialSprint.value = 1;
  modalInicioSprintAberto.value = true;
};

const confirmarInicioSprint = () => {
  modalInicioSprintAberto.value = false;
  router.post(`/equipes/${props.equipe.id}/iniciar-sprint`, {
    tarefas_ids: tarefasSelecionadasBacklog.value,
    bimestre: bimestreSelecionadoSprint.value,
    sequencia_inicial: sequenciaInicialSprint.value
  });
};

const abrirModalEncerramento = () => {
  feedbackProfessorInput.value = props.sprint?.feedback || '';
  avaliacaoSprint.value = {
    entrega_valor: 10.0,
    qualidade_tecnica: 10.0,
    processos_rituais: 10.0,
    documentacao: 10.0,
    observacoes: ''
  };
  avaliacoesIndividuais.value = (props.equipe?.alunos || []).map(aluno => ({
    aluno_id: aluno.id,
    nome: aluno.nome,
    papel: aluno.pivot?.papel || 'Integrante',
    rituais: 10.0,
    tarefas: 10.0,
    postura: 10.0,
    observacoes: ''
  }));
  modalEncerramentoAberto.value = true;
};

const sugerirAvaliacaoComIA = async () => {
  if (!props.sprint?.id) return;
  isCarregandoIA.value = true;
  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const response = await fetch(`/kanban/sugerir-avaliacao/${props.sprint.id}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json'
      }
    });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const sugestao = await response.json();

    if (sugestao) {
      if (sugestao.entrega_valor !== undefined) avaliacaoSprint.value.entrega_valor = parseFloat(sugestao.entrega_valor);
      if (sugestao.qualidade_tecnica !== undefined) avaliacaoSprint.value.qualidade_tecnica = parseFloat(sugestao.qualidade_tecnica);
      if (sugestao.processos_rituais !== undefined) avaliacaoSprint.value.processos_rituais = parseFloat(sugestao.processos_rituais);
      if (sugestao.documentacao !== undefined) avaliacaoSprint.value.documentacao = parseFloat(sugestao.documentacao);
      if (sugestao.observacoes) avaliacaoSprint.value.observacoes = sugestao.observacoes;

      if (Array.isArray(sugestao.avaliacoes_individuais)) {
        sugestao.avaliacoes_individuais.forEach(ind => {
          const item = avaliacoesIndividuais.value.find(a => a.aluno_id === ind.aluno_id);
          if (item) {
            if (ind.rituais !== undefined) item.rituais = parseFloat(ind.rituais);
            if (ind.tarefas !== undefined) item.tarefas = parseFloat(ind.tarefas);
            if (ind.postura !== undefined) item.postura = parseFloat(ind.postura);
            if (ind.observacoes) item.observacoes = ind.observacoes;
          }
        });
      }
    }
  } catch (e) {
    console.error("Erro ao obter sugestão de avaliação do Gemini:", e);
  } finally {
    isCarregandoIA.value = false;
  }
};

const confirmarEncerramento = () => {
  if (!props.sprint) return;
  router.post(`/kanban/encerrar-sprint/${props.sprint.id}`, {
    feedback: feedbackProfessorInput.value,
    avaliacao_sprint: avaliacaoSprint.value,
    avaliacoes_individuais: avaliacoesIndividuais.value
  }, {
    onSuccess: () => { 
      modalEncerramentoAberto.value = false;
      router.get(`/equipes/${props.equipe.id}?aba=anteriores`);
    }
  });
};

// Helper: parseia JSON dos detalhes do histórico com segurança
const parseDetalhes = (detalhes) => {
  if (!detalhes) return null;
  try { return typeof detalhes === 'string' ? JSON.parse(detalhes) : detalhes; }
  catch { return null; }
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
        <div class="flex items-center flex-wrap gap-2 mt-1">
          <p class="text-xs text-slate-500">{{ equipe.descricao || 'Projeto de Engenharia de Software CTI' }}</p>
          <button 
            @click="modalIntegrantesAberto = true" 
            class="inline-flex items-center space-x-1 text-[11px] font-bold text-[#0F2537] bg-slate-100 hover:bg-slate-200 px-2 py-0.5 rounded border border-slate-300 transition cursor-pointer"
          >
            <Users class="w-3.5 h-3.5 text-slate-600" />
            <span>Ver {{ equipe.alunos?.length || 0 }} Integrante(s)</span>
          </button>
          <!-- Botão GitHub -->
          <a
            v-if="equipe.github"
            :href="equipe.github"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center space-x-1 text-[11px] font-bold text-slate-800 bg-slate-900 hover:bg-slate-700 text-white px-2 py-0.5 rounded border border-slate-700 transition"
          >
            <Github class="w-3.5 h-3.5" />
            <span>GitHub</span>
          </a>
          <!-- Botão Site -->
          <a
            v-if="equipe.url"
            :href="equipe.url"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center space-x-1 text-[11px] font-bold text-emerald-900 bg-emerald-100 hover:bg-emerald-200 px-2 py-0.5 rounded border border-emerald-400 transition"
          >
            <Globe class="w-3.5 h-3.5" />
            <span>Site do Projeto</span>
          </a>
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

    <!-- Indicador de Sincronização em Tempo Real -->
    <div class="flex items-center justify-end mb-2 pr-0.5">
      <div class="flex items-center space-x-1.5 text-[10px] font-semibold text-slate-400">
        <span
          :class="[
            'w-2 h-2 rounded-full transition-colors duration-500',
            isSyncing ? 'bg-emerald-500 animate-pulse' : 'bg-slate-300'
          ]"
        ></span>
        <span>{{ isSyncing ? 'Sincronizando...' : 'Atualização automática ativa' }}</span>
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
                  :checked="tarefasSelecionadasBacklog.includes(t.id)"
                  @change="alternarSelecaoBacklog(t.id)"
                  class="rounded border-slate-300 text-[#0F2537] focus:ring-[#0F2537] cursor-pointer"
                />
              </td>
              <td class="font-mono font-bold text-slate-600">#{{ t.id }}</td>
              <td class="font-bold text-slate-900">
                <div class="flex items-center space-x-2">
                  <span>{{ t.titulo }}</span>
                  <span 
                    v-if="t.veio_da_sprint_anterior" 
                    class="bg-amber-100 text-amber-900 border border-amber-300 text-[10px] font-bold px-1.5 py-0.5 rounded flex items-center space-x-1"
                    title="Tarefa não concluída devolvida da Sprint anterior"
                  >
                    <History class="w-3 h-3 text-amber-700" />
                    <span>Pendente da Sprint {{ t.sprint_anterior_numero }}</span>
                  </span>
                </div>
              </td>
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
            v-if="canManageColunas"
            @click="abrirGerenciarColunas"
            class="bg-slate-100 hover:bg-slate-200 border border-slate-300 text-slate-700 font-bold text-xs px-3 py-1.5 rounded transition flex items-center space-x-1 cursor-pointer"
          >
            <Kanban class="w-3.5 h-3.5" />
            <span>Gerenciar Colunas</span>
          </button>
          <button 
            @click="modalEncerramentoAberto = true"
            class="bg-[#9B2C2C] hover:bg-[#7B1D1D] text-white font-bold text-xs px-3 py-1.5 rounded shadow-sm transition flex items-center space-x-1 cursor-pointer"
          >
            <CheckCircle2 class="w-4 h-4" />
            <span>Encerrar Sprint</span>
          </button>
        </div>
        <!-- Botão gerenciar colunas para TL (sem ser orientador) -->
        <div v-else-if="isTL && sprint && !sprint.encerrada">
          <button
            @click="abrirGerenciarColunas"
            class="bg-slate-100 hover:bg-slate-200 border border-slate-300 text-slate-700 font-bold text-xs px-3 py-1.5 rounded transition flex items-center space-x-1 cursor-pointer"
          >
            <Kanban class="w-3.5 h-3.5" />
            <span>Gerenciar Colunas</span>
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

      <!-- Quadro Kanban Dinâmico -->
      <div 
        v-else 
        :class="[
          'grid gap-3',
          colunas.length === 1 ? 'grid-cols-1' :
          colunas.length === 2 ? 'grid-cols-1 md:grid-cols-2' :
          colunas.length === 3 ? 'grid-cols-1 md:grid-cols-3' :
          colunas.length === 4 ? 'grid-cols-1 md:grid-cols-4' :
          colunas.length === 5 ? 'grid-cols-1 md:grid-cols-5' : 'grid-cols-1 md:grid-cols-6 overflow-x-auto',
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

    <!-- ABA 3: SPRINTS ANTERIORES (HISTÓRICO SOMENTE LEITURA AGRUPADO POR BIMESTRE) -->
    <div v-else-if="abaAtiva === 'anteriores'" class="space-y-6">
      <div class="bg-white p-3.5 rounded-lg border border-slate-200 shadow-xs flex justify-between items-center">
        <div>
          <h2 class="text-sm font-bold text-slate-800 flex items-center space-x-2">
            <History class="w-4 h-4 text-blue-600" />
            <span>Histórico de Sprints Encerradas por Bimestre</span>
          </h2>
          <p class="text-xs text-slate-500">Acompanhe e transcreva as avaliações de cada bimestre letivo.</p>
        </div>
      </div>

      <!-- Mensagem quando não há histórico -->
      <div v-if="!sprintsAgrupadas || Object.keys(sprintsAgrupadas).length === 0" class="bg-white border border-slate-200 rounded-lg p-8 text-center text-xs text-slate-500">
        Nenhuma sprint finalizada até o momento nesta equipe.
      </div>

      <!-- Laço 1: Itera sobre os Bimestres (chaves do objeto) -->
      <div 
        v-for="(sprintsDoBimestre, bimestreNum) in sprintsAgrupadas" 
        :key="bimestreNum" 
        class="bg-white border border-slate-200 rounded-lg overflow-hidden shadow-xs"
      >
        <!-- Cabeçalho do Bimestre -->
        <div class="bg-[#0F2537] px-4 py-2.5 flex justify-between items-center border-b-2 border-blue-600">
          <div class="flex items-center space-x-2">
            <Calendar class="w-4 h-4 text-blue-400" />
            <h3 class="text-xs font-extrabold uppercase tracking-wider text-white">
              {{ bimestreNum }}º Bimestre Letivo
            </h3>
          </div>
          <span class="text-[11px] font-semibold text-slate-300 bg-white/10 px-2 py-0.5 rounded">
            {{ sprintsDoBimestre.length }} {{ sprintsDoBimestre.length === 1 ? 'Sprint' : 'Sprints' }}
          </span>
        </div>

        <!-- Grid das Sprints do Bimestre -->
        <div class="p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 bg-slate-50/50">
          <div 
            v-for="s in sprintsDoBimestre" 
            :key="s.id" 
            class="bg-white border border-slate-200 rounded-md p-3 shadow-2xs hover:border-slate-400 transition flex flex-col justify-between"
          >
            <div>
              <div class="flex justify-between items-center mb-1.5 pb-1.5 border-b border-slate-100">
                <span class="text-xs font-extrabold text-slate-800">Sprint {{ s.sequencia }}</span>
                <span class="text-xs font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                  {{ s.percentual || 0 }}% Concluído
                </span>
              </div>

              <div class="text-[11px] text-slate-500 space-y-0.5 mb-2">
                <p><strong>Início:</strong> {{ s.dt_inicio || 'Sem data' }}</p>
                <p><strong>Encerramento:</strong> {{ s.dt_fim || 'Sem data' }}</p>
              </div>

              <!-- Parecer/Feedback do Professor se houver -->
              <div v-if="s.feedback" class="bg-slate-50 p-2 rounded border border-slate-200 text-xs text-slate-700 mb-3">
                <strong class="text-slate-900 block font-semibold mb-0.5">Feedback:</strong>
                <p class="text-[11px] leading-relaxed">{{ s.feedback }}</p>
              </div>

              <!-- Resumo da Avaliação Global da Sprint se houver -->
              <div v-if="s.avaliacao_sprint" class="bg-blue-50/60 border border-blue-200 rounded p-2.5 mb-3 text-xs">
                <span class="font-bold text-blue-900 block mb-1.5 flex items-center space-x-1">
                  <Award class="w-3.5 h-3.5 text-blue-700" />
                  <span>Notas Globais da Sprint:</span>
                </span>
                <div class="grid grid-cols-2 gap-1.5 text-[11px]">
                  <div class="bg-white px-2 py-1 rounded border border-blue-100 flex justify-between">
                    <span class="text-slate-600">Valor:</span>
                    <strong class="text-slate-800">{{ s.avaliacao_sprint.entrega_valor ?? '-' }}</strong>
                  </div>
                  <div class="bg-white px-2 py-1 rounded border border-blue-100 flex justify-between">
                    <span class="text-slate-600">Técnica:</span>
                    <strong class="text-slate-800">{{ s.avaliacao_sprint.qualidade_tecnica ?? '-' }}</strong>
                  </div>
                  <div class="bg-white px-2 py-1 rounded border border-blue-100 flex justify-between">
                    <span class="text-slate-600">Processos:</span>
                    <strong class="text-slate-800">{{ s.avaliacao_sprint.processos_rituais ?? '-' }}</strong>
                  </div>
                  <div class="bg-white px-2 py-1 rounded border border-blue-100 flex justify-between">
                    <span class="text-slate-600">Doc:</span>
                    <strong class="text-slate-800">{{ s.avaliacao_sprint.documentacao ?? '-' }}</strong>
                  </div>
                </div>
              </div>
            </div>

            <Link 
              :href="`/equipes/${equipe.id}?aba=sprint-atual&sprint_id=${s.id}`" 
              class="w-full inline-flex justify-center items-center space-x-1 bg-[#0F2537] hover:bg-[#1A365D] text-white font-bold text-xs py-1.5 rounded transition shadow-2xs mt-2"
            >
              <Eye class="w-3.5 h-3.5" />
              <span>Ver Kanban Congelado</span>
            </Link>
          </div>
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
              @blur="salvarEdicaoTarefa"
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
              @blur="salvarEdicaoTarefa"
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
                  <!-- Ações do autor -->
                  <div v-if="isAutorComentario(c)" class="flex items-center space-x-1">
                    <button
                      @click="iniciarEdicaoComentario(c)"
                      class="text-slate-400 hover:text-blue-600 transition cursor-pointer"
                      title="Editar comentário"
                    >
                      <Pencil class="w-3 h-3" />
                    </button>
                    <button
                      @click="deletarComentario(c)"
                      class="text-slate-400 hover:text-red-600 transition cursor-pointer"
                      title="Apagar comentário"
                    >
                      <Trash2 class="w-3 h-3" />
                    </button>
                  </div>
                </div>
                <!-- Modo visualização -->
                <p v-if="comentarioEmEdicaoId !== c.id">{{ c.texto }}</p>
                <!-- Modo edição inline -->
                <div v-else class="mt-1 space-y-1">
                  <textarea
                    v-model="comentarioEmEdicaoTexto"
                    rows="2"
                    class="w-full text-xs border border-slate-300 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-slate-400"
                  ></textarea>
                  <div class="flex gap-1">
                    <button
                      @click="salvarEdicaoComentario(c)"
                      class="text-[10px] bg-[#0F2537] text-white px-2 py-0.5 rounded font-semibold cursor-pointer hover:bg-[#1A365D]"
                    >Salvar</button>
                    <button
                      @click="cancelarEdicaoComentario"
                      class="text-[10px] border border-slate-300 text-slate-600 px-2 py-0.5 rounded cursor-pointer hover:bg-slate-100"
                    >Cancelar</button>
                  </div>
                </div>
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
                <span class="font-medium text-slate-800 truncate max-w-[200px]">{{ a.nome_original }}</span>
                <div class="flex items-center space-x-2">
                  <span class="text-[10px] text-slate-500">{{ a.autor_nome }}</span>
                  <button
                    v-if="isAutorAnexo(a)"
                    @click="deletarAnexo(a)"
                    class="text-slate-400 hover:text-red-600 transition cursor-pointer"
                    title="Remover anexo"
                  >
                    <Trash2 class="w-3.5 h-3.5" />
                  </button>
                </div>
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

        <div class="bg-slate-50 px-4 py-2.5 border-t border-slate-200 flex justify-between items-center">
          <button 
            @click="modalHistoricoAberto = true" 
            class="px-3 py-1.5 rounded bg-slate-200 text-slate-800 text-xs font-bold hover:bg-slate-300 transition flex items-center space-x-1 cursor-pointer border border-slate-300"
          >
            <History class="w-3.5 h-3.5 text-slate-600" />
            <span>Ver Histórico Completo de Movimentações ({{ tarefaSelecionada?.historicos?.length || 0 }})</span>
          </button>

          <div class="flex items-center space-x-2">
            <button 
              @click="salvarEdicaoTarefa" 
              class="px-3.5 py-1.5 rounded bg-[#0F2537] text-white text-xs font-semibold hover:bg-[#1A365D] transition cursor-pointer"
            >
              Salvar Alterações
            </button>
            <button @click="modalTarefaAberto = false" class="px-3 py-1.5 rounded border border-slate-300 text-xs font-medium text-slate-700 hover:bg-slate-100 cursor-pointer">
              Fechar
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL DE HISTÓRICO HIERARQUIZADO E AUDITORIA DA TAREFA -->
    <div v-if="modalHistoricoAberto" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4">
      <div class="bg-white rounded-lg shadow-2xl w-full max-w-lg overflow-hidden border border-slate-300 flex flex-col max-h-[85vh]">
        <div class="bg-[#0F2537] px-4 py-3 text-white flex items-center justify-between">
          <div class="flex items-center space-x-2">
            <GitCommit class="w-4 h-4 text-emerald-400" />
            <h3 class="text-xs font-bold uppercase tracking-wider">Histórico & Trilha de Auditoria - Tarefa #{{ tarefaSelecionada?.id }}</h3>
          </div>
          <button @click="modalHistoricoAberto = false" class="text-slate-300 hover:text-white cursor-pointer">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="p-4 space-y-3 overflow-y-auto flex-1 bg-slate-50/50">
          <p class="text-xs text-slate-500 font-medium pb-2 border-b border-slate-200">
            Registro cronológico de todas as edições, movimentações entre colunas, responsáveis e transferências entre Sprints.
          </p>

          <div v-if="!tarefaSelecionada?.historicos || tarefaSelecionada.historicos.length === 0" class="text-center py-8 text-xs text-slate-400">
            Nenhuma movimentação registrada para esta tarefa ainda.
          </div>

          <div v-else class="relative border-l-2 border-slate-300 ml-3 pl-4 space-y-4 my-2">
            <div 
              v-for="h in tarefaSelecionada.historicos" 
              :key="h.id" 
              class="relative group"
            >
              <!-- Marcador de Ponto de Histórico -->
              <div 
                :class="[
                  'absolute -left-[23px] top-0.5 w-3 h-3 rounded-full border-2 bg-white',
                  h.tipo_acao === 'transferencia_sprint' ? 'border-amber-500 bg-amber-100' :
                  h.tipo_acao === 'movimentacao'         ? 'border-blue-500 bg-blue-100' :
                  h.tipo_acao === 'edicao'               ? 'border-purple-500 bg-purple-100' :
                  h.tipo_acao === 'responsavel'          ? 'border-emerald-500 bg-emerald-100' :
                  h.tipo_acao === 'comentario'           ? 'border-pink-500 bg-pink-100' :
                  h.tipo_acao === 'anexo'                ? 'border-orange-500 bg-orange-100' :
                  'border-slate-500'
                ]"
              ></div>

              <div class="bg-white p-2.5 rounded border border-slate-200 shadow-2xs">
                <div class="flex items-center justify-between gap-2 mb-1">
                  <span 
                    :class="[
                      'text-[10px] font-extrabold uppercase px-1.5 py-0.2 rounded border',
                      h.tipo_acao === 'transferencia_sprint' ? 'bg-amber-50 text-amber-800 border-amber-300' :
                      h.tipo_acao === 'movimentacao' ? 'bg-blue-50 text-blue-800 border-blue-300' :
                      h.tipo_acao === 'edicao' ? 'bg-purple-50 text-purple-800 border-purple-300' :
                      h.tipo_acao === 'responsavel' ? 'bg-emerald-50 text-emerald-800 border-emerald-300' : 'bg-slate-100 text-slate-700 border-slate-300'
                    ]"
                  >
                    {{ h.tipo_acao.replace('_', ' ') }}
                  </span>
                  <span class="text-[10px] font-mono text-slate-400">{{ h.data }}</span>
                </div>

                <p class="text-xs font-medium text-slate-800 leading-snug">{{ h.descricao }}</p>

                <!-- Detalhes expandidos: antes/depois (edição) ou texto apagado (deleção) -->
                <template v-if="parseDetalhes(h.detalhes)">
                  <div class="mt-1.5 space-y-1">
                    <!-- Edição de comentário: antes e depois -->
                    <template v-if="parseDetalhes(h.detalhes).antes !== undefined">
                      <div class="bg-red-50 border border-red-200 rounded px-2 py-1">
                        <span class="text-[9px] font-extrabold uppercase text-red-600 block mb-0.5">Antes</span>
                        <p class="text-[11px] text-red-800 leading-snug italic">{{ parseDetalhes(h.detalhes).antes }}</p>
                      </div>
                      <div class="bg-emerald-50 border border-emerald-200 rounded px-2 py-1">
                        <span class="text-[9px] font-extrabold uppercase text-emerald-600 block mb-0.5">Depois</span>
                        <p class="text-[11px] text-emerald-800 leading-snug">{{ parseDetalhes(h.detalhes).depois }}</p>
                      </div>
                    </template>
                    <!-- Deleção de comentário: texto apagado -->
                    <template v-else-if="parseDetalhes(h.detalhes).texto_apagado !== undefined">
                      <div class="bg-slate-100 border border-slate-300 rounded px-2 py-1">
                        <span class="text-[9px] font-extrabold uppercase text-slate-500 block mb-0.5 flex items-center space-x-1">
                          <Trash2 class="w-2.5 h-2.5" /><span>Texto apagado</span>
                        </span>
                        <p class="text-[11px] text-slate-600 leading-snug italic line-through">{{ parseDetalhes(h.detalhes).texto_apagado }}</p>
                      </div>
                    </template>
                  </div>
                </template>

                <div class="mt-1 text-[10px] text-slate-500 flex items-center justify-between border-t border-slate-100 pt-1">
                  <span>Autor: <strong class="text-slate-700">{{ h.autor_nome }}</strong></span>
                  <span v-if="h.is_orientador" class="text-amber-700 font-bold">(Orientador)</span>
                  <span v-else-if="h.is_professor" class="text-slate-500 font-semibold">(Professor)</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-slate-100 px-4 py-2.5 border-t border-slate-200 flex justify-end">
          <button @click="modalHistoricoAberto = false" class="px-3.5 py-1.5 rounded bg-[#0F2537] text-white text-xs font-semibold hover:bg-[#1A365D] cursor-pointer">
            Fechar Histórico
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL DE RITO DE ENCERRAMENTO E AVALIAÇÃO COM IA (GEMINI) -->
    <div v-if="modalEncerramentoAberto" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4">
      <div class="bg-white rounded-lg shadow-2xl w-full max-w-3xl overflow-hidden border border-slate-300 flex flex-col max-h-[90vh]">
        
        <!-- Topbar do Modal -->
        <div class="bg-[#9B2C2C] px-5 py-3 text-white flex items-center justify-between">
          <div class="flex items-center space-x-2">
            <CheckCircle2 class="w-5 h-5" />
            <h3 class="text-sm font-bold tracking-wide">Encerrar Sprint {{ sprint?.sequencia }} & Avaliação Pedagógica</h3>
          </div>
          <button @click="modalEncerramentoAberto = false" class="text-white hover:text-slate-200 cursor-pointer">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="p-5 space-y-5 overflow-y-auto flex-1 bg-slate-50/50">
          
          <!-- Banner de Ação com Botão IA Destaque -->
          <div class="bg-gradient-to-r from-purple-900 via-indigo-900 to-slate-900 rounded-lg p-4 text-white shadow-md flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
            <div>
              <h4 class="text-sm font-bold flex items-center space-x-1.5 text-purple-200">
                <Sparkles class="w-4 h-4 text-amber-300 animate-pulse" />
                <span>Avaliação Automática com Gemini AI</span>
              </h4>
              <p class="text-xs text-purple-100/80 mt-0.5">
                Analisa o histórico de tarefas concluídas, comentários, entregas e anexos da Sprint para sugerir notas.
              </p>
            </div>
            <button
              @click="sugerirAvaliacaoComIA"
              :disabled="isCarregandoIA"
              class="px-4 py-2 rounded-md bg-gradient-to-r from-amber-400 to-amber-500 hover:from-amber-300 hover:to-amber-400 text-slate-950 text-xs font-extrabold shadow cursor-pointer transition flex items-center space-x-2 shrink-0 disabled:opacity-50"
            >
              <Loader2 v-if="isCarregandoIA" class="w-4 h-4 animate-spin text-slate-950" />
              <Sparkles v-else class="w-4 h-4 text-slate-950" />
              <span>{{ isCarregandoIA ? 'Analisando Sprint...' : '✨ Sugerir Avaliação com IA' }}</span>
            </button>
          </div>

          <!-- Resumo de Conclusão da Sprint -->
          <div class="bg-white p-3.5 rounded-md border border-slate-200 shadow-2xs flex justify-between items-center">
            <div>
              <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Progresso Real Calculado</span>
              <span class="text-lg font-black text-emerald-600">{{ sprint?.percentual || 0 }}% das tarefas concluídas</span>
            </div>
            <div class="text-right text-xs text-slate-500">
              <span>Equipe: <strong class="text-slate-800">{{ equipe.nome }}</strong></span>
            </div>
          </div>

          <!-- Grade 1: Critérios Globais da Sprint (avaliacoes_sprint) -->
          <div class="bg-white p-4 rounded-md border border-slate-200 shadow-2xs space-y-3">
            <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-700 border-b pb-2 flex items-center space-x-1.5">
              <Award class="w-4 h-4 text-blue-600" />
              <span>Avaliação Global do Grupo (0.0 a 10.0)</span>
            </h4>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
              <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Entrega de Valor</label>
                <input
                  v-model.number="avaliacaoSprint.entrega_valor"
                  type="number"
                  step="0.1"
                  min="0"
                  max="10"
                  class="w-full border border-slate-300 rounded px-2.5 py-1.5 text-xs text-slate-800 font-mono font-bold focus:ring-1 focus:ring-slate-500 focus:outline-none"
                />
              </div>
              <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Qualidade Técnica</label>
                <input
                  v-model.number="avaliacaoSprint.qualidade_tecnica"
                  type="number"
                  step="0.1"
                  min="0"
                  max="10"
                  class="w-full border border-slate-300 rounded px-2.5 py-1.5 text-xs text-slate-800 font-mono font-bold focus:ring-1 focus:ring-slate-500 focus:outline-none"
                />
              </div>
              <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Processos & Rituais</label>
                <input
                  v-model.number="avaliacaoSprint.processos_rituais"
                  type="number"
                  step="0.1"
                  min="0"
                  max="10"
                  class="w-full border border-slate-300 rounded px-2.5 py-1.5 text-xs text-slate-800 font-mono font-bold focus:ring-1 focus:ring-slate-500 focus:outline-none"
                />
              </div>
              <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Documentação</label>
                <input
                  v-model.number="avaliacaoSprint.documentacao"
                  type="number"
                  step="0.1"
                  min="0"
                  max="10"
                  class="w-full border border-slate-300 rounded px-2.5 py-1.5 text-xs text-slate-800 font-mono font-bold focus:ring-1 focus:ring-slate-500 focus:outline-none"
                />
              </div>
            </div>

            <div>
              <label class="block text-[11px] font-bold text-slate-700 mb-1">Observações Globais da Sprint</label>
              <textarea
                v-model="avaliacaoSprint.observacoes"
                rows="2"
                placeholder="Observações técnicas e pedagógicas da sprint..."
                class="w-full border border-slate-300 rounded px-2.5 py-1.5 text-xs text-slate-800 focus:ring-1 focus:ring-slate-500 focus:outline-none"
              ></textarea>
            </div>
          </div>

          <!-- Grade 2: Avaliações Individuais por Aluno (avaliacoes_individuais) -->
          <div class="bg-white p-4 rounded-md border border-slate-200 shadow-2xs space-y-3">
            <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-700 border-b pb-2 flex items-center space-x-1.5">
              <Users class="w-4 h-4 text-emerald-600" />
              <span>Avaliações Individuais dos Alunos</span>
            </h4>

            <div class="space-y-3">
              <div
                v-for="alunoAv in avaliacoesIndividuais"
                :key="alunoAv.aluno_id"
                class="p-3 bg-slate-50 border border-slate-200 rounded-md space-y-2"
              >
                <div class="flex justify-between items-center">
                  <span class="text-xs font-bold text-slate-800">{{ alunoAv.nome }}</span>
                  <span class="text-[10px] font-semibold text-slate-500 bg-slate-200 px-2 py-0.5 rounded">{{ alunoAv.papel }}</span>
                </div>

                <div class="grid grid-cols-3 gap-2">
                  <div>
                    <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Rituais</label>
                    <input
                      v-model.number="alunoAv.rituais"
                      type="number"
                      step="0.1"
                      min="0"
                      max="10"
                      class="w-full border border-slate-300 rounded px-2 py-1 text-xs text-slate-800 font-mono font-bold bg-white focus:outline-none"
                    />
                  </div>
                  <div>
                    <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Tarefas</label>
                    <input
                      v-model.number="alunoAv.tarefas"
                      type="number"
                      step="0.1"
                      min="0"
                      max="10"
                      class="w-full border border-slate-300 rounded px-2 py-1 text-xs text-slate-800 font-mono font-bold bg-white focus:outline-none"
                    />
                  </div>
                  <div>
                    <label class="block text-[10px] font-semibold text-slate-600 mb-0.5">Postura</label>
                    <input
                      v-model.number="alunoAv.postura"
                      type="number"
                      step="0.1"
                      min="0"
                      max="10"
                      class="w-full border border-slate-300 rounded px-2 py-1 text-xs text-slate-800 font-mono font-bold bg-white focus:outline-none"
                    />
                  </div>
                </div>

                <div>
                  <input
                    v-model="alunoAv.observacoes"
                    type="text"
                    placeholder="Justificativa / Observação individual..."
                    class="w-full border border-slate-300 rounded px-2 py-1 text-xs text-slate-800 bg-white focus:outline-none"
                  />
                </div>
              </div>
            </div>
          </div>

          <!-- Parecer Qualitativo do Professor -->
          <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Parecer / Feedback Qualitativo Final do Professor</label>
            <textarea 
              v-model="feedbackProfessorInput"
              rows="2"
              placeholder="Digite o parecer final para registrar no encerramento da Sprint..."
              class="w-full border border-slate-300 rounded px-2.5 py-1.5 text-xs text-slate-800 focus:ring-1 focus:ring-slate-500 focus:outline-none"
            ></textarea>
          </div>

        </div>

        <!-- Footer do Modal -->
        <div class="bg-slate-50 px-5 py-3 border-t border-slate-200 flex justify-end space-x-2">
          <button 
            @click="modalEncerramentoAberto = false" 
            class="px-4 py-2 rounded border border-slate-300 text-xs font-medium text-slate-700 hover:bg-slate-100 cursor-pointer"
          >
            Cancelar
          </button>
          <button 
            @click="confirmarEncerramento" 
            class="px-4 py-2 rounded bg-[#9B2C2C] text-white text-xs font-bold hover:bg-[#7B1D1D] shadow cursor-pointer transition flex items-center space-x-1.5"
          >
            <CheckCircle2 class="w-4 h-4" />
            <span>Salvar e Fechar Sprint</span>
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
      <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl overflow-hidden border border-slate-200">
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

    <!-- MODAL: NÚMERO E BIMESTRE DA SPRINT -->
    <div v-if="modalInicioSprintAberto" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-sm overflow-hidden border border-slate-200">
        <div class="bg-[#0F2537] px-4 py-3 text-white flex items-center justify-between">
          <h3 class="text-sm font-bold flex items-center space-x-2">
            <Send class="w-4 h-4 text-emerald-400" />
            <span>Iniciar Nova Sprint</span>
          </h3>
          <button @click="modalInicioSprintAberto = false" class="text-slate-300 hover:text-white cursor-pointer">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="p-5 space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1.5">
              Bimestre Letivo <span class="text-red-500">*</span>
            </label>
            <select
              v-model.number="bimestreSelecionadoSprint"
              class="w-full border border-slate-300 rounded px-3 py-2 text-xs font-bold text-slate-800 focus:ring-1 focus:ring-slate-500 focus:outline-none bg-white"
            >
              <option :value="1">1º Bimestre</option>
              <option :value="2">2º Bimestre</option>
              <option :value="3">3º Bimestre</option>
              <option :value="4">4º Bimestre</option>
            </select>
          </div>

          <div v-if="!sprintsAnteriores || sprintsAnteriores.length === 0" class="space-y-2">
            <div class="bg-amber-50 border border-amber-200 rounded p-3 text-xs text-amber-800 leading-relaxed">
              <strong>Primeira sprint no sistema!</strong><br>
              Se a equipe já completou sprints anteriores fora do sistema, informe o número da sequência.
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                Número da Sequência Inicial
              </label>
              <input
                v-model.number="sequenciaInicialSprint"
                type="number"
                min="1"
                class="w-full border border-slate-300 rounded px-3 py-2 text-sm text-slate-800 font-mono font-bold focus:ring-1 focus:ring-slate-500 focus:outline-none"
              />
            </div>
          </div>
        </div>

        <div class="bg-slate-50 px-4 py-3 border-t border-slate-200 flex justify-end space-x-2">
          <button
            @click="modalInicioSprintAberto = false"
            class="px-3.5 py-1.5 rounded border border-slate-300 text-xs font-medium text-slate-700 hover:bg-slate-100 cursor-pointer"
          >
            Cancelar
          </button>
          <button
            @click="confirmarInicioSprint"
            :disabled="!bimestreSelecionadoSprint"
            class="px-3.5 py-1.5 rounded bg-[#0F2537] text-white text-xs font-semibold hover:bg-[#1A365D] disabled:opacity-50 cursor-pointer flex items-center space-x-1.5"
          >
            <Send class="w-3.5 h-3.5" />
            <span>Iniciar Sprint</span>
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL DE GERENCIAMENTO DE COLUNAS -->
    <div v-if="modalGerenciarColunasAberto" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4">
      <div class="bg-white rounded-lg shadow-2xl w-full max-w-lg overflow-hidden border border-slate-300 flex flex-col max-h-[85vh]">
        <div class="bg-[#0F2537] px-4 py-3 text-white flex items-center justify-between">
          <div class="flex items-center space-x-2">
            <Kanban class="w-4 h-4 text-emerald-400" />
            <h3 class="text-xs font-bold uppercase tracking-wider">Gerenciar Colunas do Kanban</h3>
          </div>
          <button @click="modalGerenciarColunasAberto = false" class="text-slate-300 hover:text-white cursor-pointer">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="p-4 space-y-4 overflow-y-auto flex-1 bg-slate-50/50">
          <!-- Alerta de erro de ordenação -->
          <div v-if="erroOrdem" class="bg-red-50 border border-red-200 text-red-700 p-2.5 rounded text-xs">
            {{ erroOrdem }}
          </div>

          <!-- Form de Criação de Coluna Customizada -->
          <div class="bg-white p-3 rounded border border-slate-200 shadow-2xs space-y-2">
            <label class="block text-xs font-bold text-slate-700">Criar Nova Coluna Customizada</label>
            <div class="flex gap-2">
              <input
                v-model="novaColunasTitulo"
                type="text"
                placeholder="Ex: EM REVISÃO, REFINAMENTO..."
                class="flex-1 border border-slate-300 rounded px-2.5 py-1 text-xs focus:ring-1 focus:ring-slate-500 focus:outline-none uppercase"
                @keyup.enter="adicionarColuna"
              />
              <button
                @click="adicionarColuna"
                :disabled="!novaColunasTitulo.trim()"
                class="bg-emerald-700 hover:bg-emerald-800 disabled:opacity-50 text-white font-bold text-xs px-3 py-1 rounded transition cursor-pointer flex items-center space-x-1"
              >
                <Plus class="w-3.5 h-3.5" />
                <span>Adicionar</span>
              </button>
            </div>
            <p class="text-[10px] text-slate-500">As novas colunas são inseridas por padrão antes da coluna <strong>CONCLUÍDO</strong>.</p>
          </div>

          <!-- Lista de Colunas Reordenáveis -->
          <div class="space-y-1.5">
            <label class="block text-xs font-bold text-slate-700">Ordem das Colunas (Arraste ou use as setas)</label>
            
            <div
              v-for="(col, idx) in colunasEditaveis"
              :key="col.id"
              draggable="true"
              @dragstart="onColunaDragStart($event, idx)"
              @dragover="onColunaDragOver($event, idx)"
              @dragend="onColunaDragEnd"
              :class="[
                'flex items-center justify-between p-2.5 rounded border text-xs font-medium bg-white transition shadow-2xs',
                col.is_padrao ? 'border-slate-300 bg-slate-50/70' : 'border-blue-200 bg-blue-50/30'
              ]"
            >
              <div class="flex items-center space-x-2">
                <span class="text-slate-400 font-mono text-[10px]">#{{ idx + 1 }}</span>
                <span class="font-bold text-slate-800">{{ col.titulo }}</span>
                <span v-if="col.is_padrao" class="text-[9px] bg-slate-200 text-slate-700 px-1.5 py-0.5 rounded font-semibold uppercase">Padrão</span>
                <span v-else class="text-[9px] bg-blue-100 text-blue-800 px-1.5 py-0.5 rounded font-semibold uppercase">Customizada</span>
              </div>

              <div class="flex items-center space-x-1">
                <!-- Botões para mover -->
                <button
                  @click="moverColuna(idx, -1)"
                  :disabled="idx === 0"
                  class="p-1 rounded text-slate-500 hover:text-slate-800 hover:bg-slate-100 disabled:opacity-30 cursor-pointer"
                  title="Mover para esquerda/cima"
                >▲</button>
                <button
                  @click="moverColuna(idx, 1)"
                  :disabled="idx === colunasEditaveis.length - 1"
                  class="p-1 rounded text-slate-500 hover:text-slate-800 hover:bg-slate-100 disabled:opacity-30 cursor-pointer"
                  title="Mover para direita/baixo"
                >▼</button>

                <!-- Botão de Deletar (somente customizáveis) -->
                <button
                  v-if="!col.is_padrao"
                  @click="removerColuna(col)"
                  class="p-1 rounded text-red-500 hover:text-red-700 hover:bg-red-50 cursor-pointer ml-1"
                  title="Remover coluna"
                >
                  <Trash2 class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-slate-50 px-4 py-3 border-t border-slate-200 flex justify-end space-x-2">
          <button
            @click="modalGerenciarColunasAberto = false"
            class="px-3.5 py-1.5 rounded border border-slate-300 text-xs font-medium text-slate-700 hover:bg-slate-100 cursor-pointer"
          >
            Cancelar
          </button>
          <button
            @click="salvarOrdemColunas"
            :disabled="!!erroOrdem"
            class="px-3.5 py-1.5 rounded bg-[#0F2537] text-white text-xs font-semibold hover:bg-[#1A365D] disabled:opacity-50 cursor-pointer flex items-center space-x-1.5"
          >
            <span>Salvar Nova Ordem</span>
          </button>
        </div>
      </div>
    </div>

  </AppLayout>
</template>
