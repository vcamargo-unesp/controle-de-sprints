<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import { 
  Award, 
  Calendar, 
  ChevronDown, 
  ChevronUp, 
  Sparkles, 
  CheckCircle2, 
  User, 
  Users, 
  FileText,
  Clock
} from 'lucide-vue-next';

const props = defineProps({
  aluno: Object,
  turma: String,
  ano: String,
  bimestres: Object
});

// Controle de accordions abertos/fechados por bimestre
const bimestresAbertos = ref({
  1: true,
  2: false,
  3: false,
  4: false
});

const toggleAccordion = (b) => {
  bimestresAbertos.value[b] = !bimestresAbertos.value[b];
};

const getNotaBadgeClass = (nota) => {
  if (nota === null || nota === undefined) return 'bg-slate-100 text-slate-400 border-slate-200';
  const val = Number(nota);
  if (val >= 7.0) return 'bg-emerald-50 text-emerald-800 border-emerald-300 font-extrabold';
  if (val >= 5.0) return 'bg-amber-50 text-amber-800 border-amber-300 font-extrabold';
  return 'bg-red-50 text-red-800 border-red-300 font-extrabold';
};
</script>

<template>
  <Head title="Minhas Notas & Acompanhamento" />

  <AppLayout>
    <div class="max-w-5xl mx-auto space-y-6 p-4 sm:p-6">
      
      <!-- Topbar / Cabeçalho do Aluno -->
      <div>
        <Breadcrumb :items="[{ label: 'Minha Equipe', url: `/equipes` }, { label: 'Minhas Notas' }]" />
        <div class="bg-gradient-to-r from-[#0F2537] to-[#1A365D] rounded-xl p-6 text-white shadow-md flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mt-2">
          <div class="flex items-center space-x-4">
            <div class="w-12 h-12 rounded-full bg-emerald-500/20 border border-emerald-400/40 flex items-center justify-center font-bold text-emerald-300 text-lg">
              {{ aluno.n_chamada || '#' }}
            </div>
            <div>
              <h1 class="text-xl font-bold tracking-wide">{{ aluno.nome }}</h1>
              <p class="text-xs text-slate-300 mt-0.5">
                {{ aluno.equipe_nome }} • Turma {{ ano }} ({{ turma }}) • Papel: <strong>{{ aluno.papel || 'Dev' }}</strong>
              </p>
            </div>
          </div>

          <div class="bg-white/10 px-4 py-2 rounded-lg border border-white/10 text-right">
            <span class="text-[10px] font-bold text-slate-300 uppercase block">Painel Transparente</span>
            <span class="text-xs text-emerald-400 font-bold">Acompanhamento Individual</span>
          </div>
        </div>
      </div>

      <!-- Grid de Cartões por Bimestre -->
      <div class="space-y-4">
        
        <div 
          v-for="bData in bimestres" 
          :key="bData.bimestre"
          class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden transition"
        >
          <!-- Cabeçalho do Bloco do Bimestre (Clicável) -->
          <div 
            @click="toggleAccordion(bData.bimestre)"
            class="bg-slate-50 p-4 flex items-center justify-between cursor-pointer hover:bg-slate-100/80 transition border-b border-slate-200 select-none"
          >
            <div class="flex items-center space-x-3">
              <div class="w-8 h-8 rounded-lg bg-blue-100 border border-blue-200 flex items-center justify-center font-extrabold text-blue-800 text-xs">
                {{ bData.bimestre }}º
              </div>
              <div>
                <h3 class="text-sm font-bold text-slate-800">
                  {{ bData.bimestre }}º Bimestre Letivo
                </h3>
                <span class="text-xs text-slate-500">
                  Peso configurado na média final: <strong>{{ bData.peso }}%</strong>
                </span>
              </div>
            </div>

            <div class="flex items-center space-x-3">
              <!-- Badge da Nota Consolidada -->
              <div class="text-right">
                <span class="text-[10px] font-bold text-slate-400 uppercase block">Média no Bimestre</span>
                <span 
                  :class="[
                    'px-3 py-0.5 rounded text-sm font-black border shadow-2xs inline-block',
                    getNotaBadgeClass(bData.media_consolidada)
                  ]"
                >
                  {{ bData.media_consolidada !== null ? bData.media_consolidada : 'Sem Nota' }}
                </span>
              </div>

              <ChevronUp v-if="bimestresAbertos[bData.bimestre]" class="w-5 h-5 text-slate-400" />
              <ChevronDown v-else class="w-5 h-5 text-slate-400" />
            </div>
          </div>

          <!-- Corpo Expansível (Accordion) do Bimestre -->
          <div v-if="bimestresAbertos[bData.bimestre]" class="p-5 space-y-4 bg-white">
            
            <!-- Resumo IA Gemini se houver -->
            <div v-if="bData.resumo_ia" class="bg-gradient-to-r from-purple-900 via-indigo-900 to-slate-900 rounded-lg p-4 text-white shadow-xs space-y-2">
              <h4 class="text-xs font-extrabold uppercase tracking-wider text-purple-200 flex items-center space-x-1.5">
                <Sparkles class="w-4 h-4 text-amber-300" />
                <span>Parecer Sintético da Inteligência Artificial</span>
              </h4>
              <p class="text-xs text-purple-100 leading-relaxed italic">
                "{{ bData.resumo_ia }}"
              </p>
            </div>

            <!-- Lista de Sprints do Bimestre -->
            <div class="space-y-3">
              <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-700 flex items-center space-x-1.5">
                <FileText class="w-4 h-4 text-blue-600" />
                <span>Detalhamento por Sprint</span>
              </h4>

              <div 
                v-if="!bData.sprints || bData.sprints.length === 0"
                class="bg-slate-50 border border-slate-200 rounded-lg p-6 text-center text-xs text-slate-500"
              >
                Nenhuma sprint encerrada foi computada para este bimestre ainda.
              </div>

              <div 
                v-for="sprint in bData.sprints" 
                :key="sprint.id"
                class="bg-slate-50/70 border border-slate-200 rounded-lg p-4 space-y-3 shadow-2xs"
              >
                <!-- Header da Sprint -->
                <div class="flex justify-between items-center border-b border-slate-200 pb-2">
                  <div class="flex items-center space-x-2">
                    <span class="text-xs font-extrabold text-slate-800">Sprint {{ sprint.sequencia }}</span>
                    <span class="text-[11px] bg-emerald-100 text-emerald-800 font-bold px-2 py-0.5 rounded">
                      {{ sprint.percentual }}% Concluída
                    </span>
                  </div>
                  <span class="text-xs font-extrabold text-slate-800">
                    Nota Consolidada da Sprint: <strong class="text-emerald-700 text-sm">{{ sprint.nota_consolidada }}</strong>
                  </span>
                </div>

                <!-- Detalhamento de Critérios -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                  
                  <!-- Avaliação do Grupo -->
                  <div v-if="sprint.avaliacao_sprint" class="bg-white p-3 rounded border border-blue-100 text-xs space-y-1.5 shadow-2xs">
                    <span class="font-bold text-blue-900 block flex items-center space-x-1">
                      <Award class="w-3.5 h-3.5 text-blue-700" />
                      <span>Critérios Globais do Grupo:</span>
                    </span>
                    <div class="grid grid-cols-2 gap-1.5 text-[11px]">
                      <div class="bg-blue-50/50 p-1.5 rounded flex justify-between">
                        <span class="text-slate-600">Entrega de Valor:</span>
                        <strong class="text-slate-800">{{ sprint.avaliacao_sprint.entrega_valor }}</strong>
                      </div>
                      <div class="bg-blue-50/50 p-1.5 rounded flex justify-between">
                        <span class="text-slate-600">Qualidade Técnica:</span>
                        <strong class="text-slate-800">{{ sprint.avaliacao_sprint.qualidade_tecnica }}</strong>
                      </div>
                      <div class="bg-blue-50/50 p-1.5 rounded flex justify-between">
                        <span class="text-slate-600">Processos:</span>
                        <strong class="text-slate-800">{{ sprint.avaliacao_sprint.processos_rituais }}</strong>
                      </div>
                      <div class="bg-blue-50/50 p-1.5 rounded flex justify-between">
                        <span class="text-slate-600">Documentação:</span>
                        <strong class="text-slate-800">{{ sprint.avaliacao_sprint.documentacao }}</strong>
                      </div>
                    </div>
                  </div>

                  <!-- Avaliação Individual do Aluno -->
                  <div v-if="sprint.avaliacao_individual" class="bg-white p-3 rounded border border-emerald-100 text-xs space-y-1.5 shadow-2xs">
                    <span class="font-bold text-emerald-900 block flex items-center space-x-1">
                      <User class="w-3.5 h-3.5 text-emerald-700" />
                      <span>Seus Critérios Individuais:</span>
                    </span>
                    <div class="grid grid-cols-3 gap-1.5 text-[11px] text-center">
                      <div class="bg-emerald-50/50 p-1.5 rounded">
                        <span class="text-slate-500 block text-[9px]">Rituais</span>
                        <strong>{{ sprint.avaliacao_individual.rituais }}</strong>
                      </div>
                      <div class="bg-emerald-50/50 p-1.5 rounded">
                        <span class="text-slate-500 block text-[9px]">Tarefas</span>
                        <strong>{{ sprint.avaliacao_individual.tarefas }}</strong>
                      </div>
                      <div class="bg-emerald-50/50 p-1.5 rounded">
                        <span class="text-slate-500 block text-[9px]">Postura</span>
                        <strong>{{ sprint.avaliacao_individual.postura }}</strong>
                      </div>
                    </div>
                    <p v-if="sprint.avaliacao_individual.observacoes" class="text-[11px] text-emerald-900 italic mt-1">
                      "{{ sprint.avaliacao_individual.observacoes }}"
                    </p>
                  </div>

                </div>

                <!-- Feedback do Orientador -->
                <div v-if="sprint.feedback_professor" class="bg-white p-3 rounded border border-slate-200 text-xs text-slate-700 shadow-2xs">
                  <strong class="text-slate-900 block font-semibold mb-0.5">Orientação/Feedback do Orientador:</strong>
                  <p class="text-[11px] leading-relaxed">{{ sprint.feedback_professor }}</p>
                </div>

              </div>
            </div>

          </div>
        </div>

      </div>

    </div>
  </AppLayout>
</template>
