<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import { 
  Users, 
  ArrowRight, 
  Upload, 
  FileSpreadsheet, 
  X, 
  CheckCircle2, 
  Plus, 
  ExternalLink, 
  Github, 
  UserCheck,
  Pencil
} from 'lucide-vue-next';

const props = defineProps({
  equipes: Array,
  professores: Array,
  userRole: String,
  errors: Object,
  flash: Object
});

const modalImportarAberto = ref(false);
const arquivoCsvInput = ref(null);

const modalNovaEquipeAberto = ref(false);
const novaEquipeNome = ref('');
const novaEquipeDescricao = ref('');
const novaEquipeAno = ref(new Date().getFullYear());
const novaEquipeUrl = ref('');
const novaEquipeGithub = ref('');
const novaEquipeProfId = ref(props.professores?.[0]?.id || '');

// Estado de Edição de Equipe
const modalEditarEquipeAberto = ref(false);
const equipeEditandoId = ref(null);
const editEquipeNome = ref('');
const editEquipeDescricao = ref('');
const editEquipeAno = ref(new Date().getFullYear());
const editEquipeUrl = ref('');
const editEquipeGithub = ref('');
const editEquipeProfId = ref('');

const abrirModalEdicao = (equipe) => {
  equipeEditandoId.value = equipe.id;
  editEquipeNome.value = equipe.nome;
  editEquipeDescricao.value = equipe.descricao || '';
  editEquipeAno.value = equipe.ano;
  editEquipeUrl.value = equipe.url || '';
  editEquipeGithub.value = equipe.github || '';
  editEquipeProfId.value = equipe.prof_id || props.professores?.[0]?.id;
  modalEditarEquipeAberto.value = true;
};

const submeterEdicaoEquipe = () => {
  if (!editEquipeNome.value || !equipeEditandoId.value) return;
  router.put(`/equipes/${equipeEditandoId.value}`, {
    nome: editEquipeNome.value,
    descricao: editEquipeDescricao.value,
    ano: editEquipeAno.value,
    url: editEquipeUrl.value,
    github: editEquipeGithub.value,
    prof_id: editEquipeProfId.value
  }, {
    onSuccess: () => {
      modalEditarEquipeAberto.value = false;
      equipeEditandoId.value = null;
    }
  });
};

const submeterNovaEquipe = () => {
  if (!novaEquipeNome.value || !novaEquipeProfId.value) return;
  router.post('/equipes', {
    nome: novaEquipeNome.value,
    descricao: novaEquipeDescricao.value,
    ano: novaEquipeAno.value,
    url: novaEquipeUrl.value,
    github: novaEquipeGithub.value,
    prof_id: novaEquipeProfId.value
  }, {
    onSuccess: () => {
      modalNovaEquipeAberto.value = false;
      novaEquipeNome.value = '';
      novaEquipeDescricao.value = '';
      novaEquipeUrl.value = '';
      novaEquipeGithub.value = '';
    }
  });
};

const submeterImportacao = (event) => {
  const file = event.target.files[0];
  if (!file) return;

  const formData = new FormData();
  formData.append('arquivo_csv', file);

  router.post('/importar-alunos', formData, {
    onSuccess: () => {
      modalImportarAberto.value = false;
      if (arquivoCsvInput.value) arquivoCsvInput.value.value = '';
    }
  });
};
</script>

<template>
  <Head title="Equipes Cadastradas - CTI Bauru" />

  <AppLayout :userRole="userRole" userName="Prof. Isaac Portal Roldán">
    <Breadcrumb :items="[{ label: 'Visão Geral das Equipes' }]" />

    <div class="mt-2 space-y-4">
      <!-- Topbar com Botão de Importação CSV e Nova Equipe -->
      <div class="bg-white p-4 rounded-md border border-slate-200 shadow-sm flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-lg font-bold text-slate-800 tracking-tight">Painel de Equipes (Visão do Professor)</h1>
          <p class="text-xs text-slate-500">Lista ordenada por ano mais recente e ordem alfabética da equipe.</p>
        </div>

        <div class="flex items-center space-x-2">
          <button 
            @click="modalNovaEquipeAberto = true"
            class="bg-[#0F2537] hover:bg-[#1A365D] text-white font-bold text-xs px-3.5 py-2 rounded shadow-sm transition flex items-center space-x-1.5 cursor-pointer"
          >
            <Plus class="w-4 h-4 text-white" />
            <span>Nova Equipe</span>
          </button>

          <button 
            @click="modalImportarAberto = true"
            class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs px-3.5 py-2 rounded shadow-sm transition flex items-center space-x-1.5 cursor-pointer"
          >
            <FileSpreadsheet class="w-4 h-4" />
            <span>Importar Alunos (CSV)</span>
          </button>

          <span class="bg-slate-200 text-slate-800 text-xs font-bold px-3 py-2 rounded">
            {{ equipes.length }} Equipe(s)
          </span>
        </div>
      </div>

      <!-- Erros de Formulário/Importação -->
      <div v-if="errors?.equipe || errors?.prof_id" class="bg-red-50 text-red-700 text-xs p-3 rounded border border-red-200 font-semibold">
        {{ errors.equipe || errors.prof_id }}
      </div>

      <!-- Lista de Equipes com Exibição de Orientador, GitHub, URL e Botão Editar -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
        <div 
          v-for="equipe in equipes" 
          :key="equipe.id"
          class="bg-white border border-slate-200 rounded-md shadow-sm hover:shadow transition p-4 flex flex-col justify-between"
        >
          <div>
            <div class="flex items-center justify-between mb-2">
              <div class="text-xs font-mono font-extrabold text-[#9B2C2C] bg-red-50 border border-red-200 px-2 py-0.5 rounded" :title="`ID: ${equipe.id}`">
                {{ equipe.ano }} &bull; {{ equipe.nome }}
              </div>

              <!-- Badges de Links Externos (URL, GitHub) & Botão Editar -->
              <div class="flex items-center space-x-1">
                <a 
                  v-if="equipe.url" 
                  :href="equipe.url" 
                  target="_blank" 
                  title="Acessar Sistema Produção/Homologação" 
                  class="p-1 text-blue-600 hover:text-blue-800 bg-blue-50 rounded border border-blue-200 transition"
                >
                  <ExternalLink class="w-3.5 h-3.5" />
                </a>
                <a 
                  v-if="equipe.github" 
                  :href="equipe.github" 
                  target="_blank" 
                  title="Repositório GitHub" 
                  class="p-1 text-slate-800 hover:text-black bg-slate-100 rounded border border-slate-300 transition"
                >
                  <Github class="w-3.5 h-3.5" />
                </a>

                <button 
                  @click="abrirModalEdicao(equipe)"
                  title="Editar Dados da Equipe"
                  class="p-1 text-amber-700 hover:text-amber-900 bg-amber-50 hover:bg-amber-100 rounded border border-amber-300 transition cursor-pointer"
                >
                  <Pencil class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>

            <h2 class="text-base font-bold text-slate-900 leading-tight mb-1">
              {{ equipe.nome }}
            </h2>
            <p class="text-xs text-slate-600 mb-2 line-clamp-2">
              {{ equipe.descricao || 'Sem descrição cadastrada.' }}
            </p>

            <!-- Exibição do Professor Orientador (prof_id) -->
            <div class="text-[11px] text-slate-600 mb-3 flex items-center space-x-1">
              <UserCheck class="w-3.5 h-3.5 text-amber-700" />
              <span>Orientador: <strong class="text-slate-800 font-semibold">{{ equipe.professor_nome }}</strong></span>
            </div>

            <div class="space-y-1 mb-4 bg-slate-50 p-2 rounded border border-slate-100">
              <div class="flex justify-between text-[11px] font-semibold text-slate-600">
                <span>{{ equipe.sprint_ativa_nome }}</span>
                <span>{{ equipe.percentual }}% Concluído</span>
              </div>
              <div class="w-full bg-slate-200 rounded-full h-1.5 overflow-hidden">
                <div class="bg-[#0F2537] h-full" :style="{ width: equipe.percentual + '%' }"></div>
              </div>
            </div>
          </div>

          <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
            <span class="text-xs text-slate-500 flex items-center space-x-1">
              <Users class="w-3.5 h-3.5 text-slate-400" />
              <span>{{ equipe.integrantes_count }} Alunos</span>
            </span>

            <Link 
              :href="`/equipes/${equipe.id}`" 
              class="bg-[#0F2537] hover:bg-[#1A365D] text-white font-semibold text-xs px-3 py-1.5 rounded transition flex items-center space-x-1"
            >
              <span>Acessar Ambiente</span>
              <ArrowRight class="w-3.5 h-3.5" />
            </Link>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL DE IMPORTAÇÃO DE ALUNOS VIA CSV -->
    <div v-if="modalImportarAberto" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md overflow-hidden border border-slate-200">
        <div class="bg-[#0F2537] px-4 py-3 text-white flex items-center justify-between">
          <h3 class="text-sm font-bold flex items-center space-x-2">
            <FileSpreadsheet class="w-4 h-4 text-emerald-400" />
            <span>Importação de Alunos via CSV</span>
          </h3>
          <button @click="modalImportarAberto = false" class="text-slate-300 hover:text-white cursor-pointer">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="p-5 space-y-4">
          <p class="text-xs text-slate-600 leading-relaxed">
            Selecione a planilha <strong>.csv</strong> exportada do sistema acadêmico. O sistema lerá e atualizará os dados dos alunos e suas respectivas equipes automaticamente.
          </p>

          <div class="bg-slate-50 p-3 rounded border border-slate-200 text-[11px] text-slate-600 font-mono">
            <strong>Formato do CSV:</strong><br>
            nome;email;ra;n_chamada;papel;equipe_id
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Arquivo Planilha (.csv)</label>
            <input 
              ref="arquivoCsvInput"
              type="file" 
              accept=".csv,.txt"
              @change="submeterImportacao"
              class="w-full text-xs text-slate-600 file:mr-3 file:py-2 file:px-3 file:rounded file:border-0 file:text-xs file:font-bold file:bg-[#0F2537] file:text-white hover:file:bg-[#1A365D] cursor-pointer border border-slate-300 rounded p-1"
            />
          </div>
        </div>

        <div class="bg-slate-50 px-4 py-3 border-t border-slate-200 flex justify-end">
          <button @click="modalImportarAberto = false" class="px-3.5 py-1.5 rounded border border-slate-300 text-xs font-medium text-slate-700 hover:bg-slate-100 cursor-pointer">
            Fechar
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL DE CRIAR NOVA EQUIPE -->
    <div v-if="modalNovaEquipeAberto" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md overflow-hidden border border-slate-200">
        <div class="bg-[#0F2537] px-4 py-3 text-white flex items-center justify-between">
          <h3 class="text-sm font-bold flex items-center space-x-2">
            <Plus class="w-4 h-4 text-emerald-400" />
            <span>Cadastrar Nova Equipe</span>
          </h3>
          <button @click="modalNovaEquipeAberto = false" class="text-slate-300 hover:text-white cursor-pointer">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="p-5 space-y-3">
          <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Nome da Equipe</label>
            <input 
              v-model="novaEquipeNome"
              type="text" 
              placeholder="Ex: Equipe Omega"
              class="w-full border border-slate-300 rounded px-2.5 py-1.5 text-xs text-slate-800 focus:ring-1 focus:ring-slate-500 focus:outline-none"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Professor Orientador (prof_id)</label>
            <select 
              v-model="novaEquipeProfId"
              class="w-full border border-slate-300 rounded px-2.5 py-1.5 text-xs text-slate-800 focus:ring-1 focus:ring-slate-500 focus:outline-none"
            >
              <option v-for="p in professores" :key="p.id" :value="p.id">
                {{ p.nome }}
              </option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1">Ano Letivo</label>
              <input 
                v-model="novaEquipeAno"
                type="number" 
                class="w-full border border-slate-300 rounded px-2.5 py-1.5 text-xs text-slate-800"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1">Link do Sistema (URL)</label>
              <input 
                v-model="novaEquipeUrl"
                type="url" 
                placeholder="https://meuprojeto.com.br"
                class="w-full border border-slate-300 rounded px-2.5 py-1.5 text-xs text-slate-800"
              />
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Repositório GitHub (URL)</label>
            <input 
              v-model="novaEquipeGithub"
              type="url" 
              placeholder="https://github.com/usuario/projeto"
              class="w-full border border-slate-300 rounded px-2.5 py-1.5 text-xs text-slate-800"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Descrição do Projeto</label>
            <textarea 
              v-model="novaEquipeDescricao"
              rows="2"
              placeholder="Ex: Desenvolvendo o sistema de automação comercial CTI..."
              class="w-full border border-slate-300 rounded px-2.5 py-1.5 text-xs text-slate-800 focus:ring-1 focus:ring-slate-500 focus:outline-none"
            ></textarea>
          </div>
        </div>

        <div class="bg-slate-50 px-4 py-3 border-t border-slate-200 flex justify-end space-x-2">
          <button @click="modalNovaEquipeAberto = false" class="px-3.5 py-1.5 rounded border border-slate-300 text-xs font-medium text-slate-700 hover:bg-slate-100 cursor-pointer">
            Cancelar
          </button>
          <button @click="submeterNovaEquipe" class="px-3.5 py-1.5 rounded bg-[#0F2537] text-white text-xs font-semibold hover:bg-[#1A365D] cursor-pointer">
            Cadastrar Equipe
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL DE EDITAR DADOS DA EQUIPE -->
    <div v-if="modalEditarEquipeAberto" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md overflow-hidden border border-slate-200">
        <div class="bg-[#0F2537] px-4 py-3 text-white flex items-center justify-between">
          <h3 class="text-sm font-bold flex items-center space-x-2">
            <Pencil class="w-4 h-4 text-amber-400" />
            <span>Editar Dados da Equipe</span>
          </h3>
          <button @click="modalEditarEquipeAberto = false" class="text-slate-300 hover:text-white cursor-pointer">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="p-5 space-y-3">
          <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Nome da Equipe</label>
            <input 
              v-model="editEquipeNome"
              type="text" 
              class="w-full border border-slate-300 rounded px-2.5 py-1.5 text-xs text-slate-800 focus:ring-1 focus:ring-slate-500 focus:outline-none"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Professor Orientador (prof_id)</label>
            <select 
              v-model="editEquipeProfId"
              class="w-full border border-slate-300 rounded px-2.5 py-1.5 text-xs text-slate-800 focus:ring-1 focus:ring-slate-500 focus:outline-none"
            >
              <option v-for="p in professores" :key="p.id" :value="p.id">
                {{ p.nome }}
              </option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1">Ano Letivo</label>
              <input 
                v-model="editEquipeAno"
                type="number" 
                class="w-full border border-slate-300 rounded px-2.5 py-1.5 text-xs text-slate-800"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1">Link do Sistema (URL)</label>
              <input 
                v-model="editEquipeUrl"
                type="url" 
                placeholder="https://meuprojeto.com.br"
                class="w-full border border-slate-300 rounded px-2.5 py-1.5 text-xs text-slate-800"
              />
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Repositório GitHub (URL)</label>
            <input 
              v-model="editEquipeGithub"
              type="url" 
              placeholder="https://github.com/usuario/projeto"
              class="w-full border border-slate-300 rounded px-2.5 py-1.5 text-xs text-slate-800"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Descrição do Projeto</label>
            <textarea 
              v-model="editEquipeDescricao"
              rows="2"
              class="w-full border border-slate-300 rounded px-2.5 py-1.5 text-xs text-slate-800 focus:ring-1 focus:ring-slate-500 focus:outline-none"
            ></textarea>
          </div>
        </div>

        <div class="bg-slate-50 px-4 py-3 border-t border-slate-200 flex justify-end space-x-2">
          <button @click="modalEditarEquipeAberto = false" class="px-3.5 py-1.5 rounded border border-slate-300 text-xs font-medium text-slate-700 hover:bg-slate-100 cursor-pointer">
            Cancelar
          </button>
          <button @click="submeterEdicaoEquipe" class="px-3.5 py-1.5 rounded bg-amber-600 text-white text-xs font-semibold hover:bg-amber-700 cursor-pointer">
            Salvar Alterações
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
