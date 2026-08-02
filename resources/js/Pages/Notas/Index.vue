<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import { 
  Calculator, 
  Users, 
  Award, 
  Sparkles, 
  Loader2, 
  Save, 
  X, 
  Calendar, 
  CheckCircle2, 
  FileText, 
  ChevronRight,
  ChevronDown,
  RefreshCw,
  Clock,
  User,
  History
} from 'lucide-vue-next';

const props = defineProps({
  turmasAtivas: Array,
  turmaSelecionada: Object,
  pesos: Object,
  alunosNotas: Array
});

// Controle de Turmas e Anos
const mostrarAnosAnteriores = ref(false);

const anoAtual = computed(() => {
  if (!props.turmasAtivas || props.turmasAtivas.length === 0) return new Date().getFullYear();
  return Math.max(...props.turmasAtivas.map(t => Number(t.ano)));
});

const turmasAnoAtual = computed(() => {
  return props.turmasAtivas.filter(t => Number(t.ano) === anoAtual.value);
});

const turmasAnosAnteriores = computed(() => {
  return props.turmasAtivas.filter(t => Number(t.ano) < anoAtual.value);
});

// Estado reativo dos pesos (para cálculo instantâneo em tela)
const pesosInput = ref({
  1: props.pesos[1] || 1,
  2: props.pesos[2] || 1,
  3: props.pesos[3] || 1,
  4: props.pesos[4] || 1,
});

const salvandoPesos = ref(false);

const selecionarTurma = (t) => {
  router.get('/notas', { ano: t.ano, turma: t.turma }, { preserveState: false });
};

const salvarPesos = () => {
  salvandoPesos.value = true;
  router.post('/notas/pesos', {
    ano: props.turmaSelecionada.ano,
    turma: props.turmaSelecionada.turma,
    pesos: pesosInput.value
  }, {
    onFinish: () => { salvandoPesos.value = false; }
  });
};

// Soma total dos pesos informados
const somaPesos = computed(() => {
  return Number(pesosInput.value[1] || 0) + 
         Number(pesosInput.value[2] || 0) + 
         Number(pesosInput.value[3] || 0) + 
         Number(pesosInput.value[4] || 0);
});

// Calcula os pontos exatos de contribuição do bimestre (Nota * Peso / 10)
const calcularContribuição = (nota, peso) => {
  if (nota === null || nota === undefined || !peso) return null;
  return ((Number(nota) * Number(peso)) / 10).toFixed(2);
};

// Função reativa para calcular a Média Final Ponderada (Base 10) de um aluno
const calcularMediaFinalAluno = (medias) => {
  let somaContribuição = 0;
  let somaPesosValidos = 0;

  for (let b = 1; b <= 4; b++) {
    const nota = medias[b];
    const peso = Number(pesosInput.value[b] || 0);
    if (nota !== null && nota !== undefined && peso > 0) {
      somaContribuição += ((Number(nota) * peso) / 10);
      somaPesosValidos += peso;
    }
  }

  if (somaPesosValidos === 0) return null;
  return ((somaContribuição / somaPesosValidos) * 10).toFixed(1);
};

// Modal Slide-over de Raio-X do Aluno
const modalRaioXAberto = ref(false);
const alunoSelecionado = ref(null);
const bimestreSelecionadoRaioX = ref(1);
const resumoGeminiTexto = ref('');
const carregandoResumoGemini = ref(false);

const abrirRaioX = async (aluno, bimestre) => {
  alunoSelecionado.value = aluno;
  bimestreSelecionadoRaioX.value = bimestre;
  modalRaioXAberto.value = true;
  resumoGeminiTexto.value = '';

  await carregarResumoGemini(aluno.id, bimestre, false);
};

const carregarResumoGemini = async (alunoId, bimestre, regerar = false) => {
  carregandoResumoGemini.value = true;
  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const res = await fetch(`/notas/aluno/${alunoId}/resumo/${bimestre}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json'
      },
      body: JSON.stringify({ regerar })
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    resumoGeminiTexto.value = data.texto_resumo || 'Nenhum resumo gerado.';
  } catch (e) {
    console.error("Erro ao carregar resumo do Gemini:", e);
    resumoGeminiTexto.value = "Não foi possível carregar a síntese pedagógica no momento.";
  } finally {
    carregandoResumoGemini.value = false;
  }
};

const getBadgeNotaClass = (nota) => {
  if (nota === null || nota === undefined) return 'bg-slate-100 text-slate-400 border-slate-200';
  const val = Number(nota);
  if (val >= 7.0) return 'bg-emerald-50 text-emerald-800 border-emerald-300 font-extrabold';
  if (val >= 5.0) return 'bg-amber-50 text-amber-800 border-amber-300 font-extrabold';
  return 'bg-red-50 text-red-800 border-red-300 font-extrabold';
};
</script>

<template>
  <Head title="Painel de Notas & Desempenho" />

  <AppLayout>
    <div class="max-w-7xl mx-auto space-y-5 p-4 sm:p-6">
      
      <!-- Breadcrumb e Título -->
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div>
          <Breadcrumb :items="[{ label: 'Equipes', url: '/equipes' }, { label: 'Painel de Notas' }]" />
          <h1 class="text-xl font-bold text-slate-900 flex items-center space-x-2 mt-1">
            <Calculator class="w-6 h-6 text-blue-600" />
            <span>Painel de Notas & Matriz Pedagógica</span>
          </h1>
        </div>

        <!-- Seletor de Turma por Ano -->
        <div class="flex flex-wrap items-center gap-2 bg-white p-1.5 rounded-lg border border-slate-200 shadow-2xs">
          <span class="text-xs font-bold text-slate-600 px-2">Turmas {{ anoAtual }}:</span>
          
          <!-- Botões expostos apenas do Ano Atual -->
          <button
            v-for="t in turmasAnoAtual"
            :key="t.ano + '-' + t.turma"
            @click="selecionarTurma(t)"
            :class="[
              'px-3 py-1 rounded text-xs font-bold transition cursor-pointer',
              turmaSelecionada.ano == t.ano && turmaSelecionada.turma == t.turma
                ? 'bg-[#0F2537] text-white shadow-2xs'
                : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
            ]"
          >
            Turma {{ t.turma }}
          </button>

          <!-- Botão expansível para Anos Anteriores -->
          <div v-if="turmasAnosAnteriores.length > 0" class="relative">
            <button
              @click="mostrarAnosAnteriores = !mostrarAnosAnteriores"
              :class="[
                'px-3 py-1 rounded text-xs font-bold transition cursor-pointer flex items-center space-x-1.5 border',
                turmaSelecionada.ano < anoAtual
                  ? 'bg-amber-700 text-white border-amber-800'
                  : 'bg-slate-100 text-slate-700 hover:bg-slate-200 border-slate-300'
              ]"
            >
              <History class="w-3.5 h-3.5" />
              <span>Anos Anteriores</span>
              <ChevronDown class="w-3.5 h-3.5" />
            </button>

            <!-- Dropdown das Turmas de Anos Anteriores -->
            <div
              v-if="mostrarAnosAnteriores"
              class="absolute right-0 mt-1.5 w-48 bg-white rounded-md border border-slate-200 shadow-lg z-50 p-1 space-y-1"
            >
              <button
                v-for="t in turmasAnosAnteriores"
                :key="t.ano + '-' + t.turma"
                @click="selecionarTurma(t); mostrarAnosAnteriores = false;"
                class="w-full text-left px-3 py-1.5 text-xs rounded font-medium hover:bg-slate-100 transition flex justify-between items-center"
              >
                <span>{{ t.ano }} &bull; Turma {{ t.turma }}</span>
                <span v-if="turmaSelecionada.ano == t.ano && turmaSelecionada.turma == t.turma" class="text-emerald-600 font-bold">✓</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Barra de Controle de Pesos / Coeficientes da Disciplina -->
      <div class="bg-white p-4 rounded-lg border border-slate-200 shadow-xs flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-800 flex items-center space-x-1.5">
            <Award class="w-4 h-4 text-amber-500" />
            <span>Peso / Coeficiente das Sprints na Nota da Disciplina (Turma {{ turmaSelecionada.ano }} - {{ turmaSelecionada.turma }})</span>
          </h3>
          <p class="text-xs text-slate-500 mt-0.5">
            Defina o multiplicador/peso do bimestre para calcular os pontos de contribuição (Nota × Peso) e a Média Ponderada da disciplina.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
          <!-- Inputs de Pesos (Coeficientes) -->
          <div class="grid grid-cols-4 gap-2 bg-slate-50 p-2 rounded-md border border-slate-200">
            <div v-for="b in 4" :key="b" class="text-center">
              <span class="block text-[10px] font-bold text-slate-500 uppercase">Peso {{ b }}º Bim</span>
              <input
                v-model.number="pesosInput[b]"
                type="number"
                min="0"
                step="0.5"
                class="w-14 text-center border border-slate-300 rounded px-1 py-0.5 text-xs font-mono font-bold bg-white text-slate-800 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
            </div>
          </div>

          <div class="text-xs font-bold text-slate-600">
            Soma dos Pesos: <span class="text-blue-700 font-extrabold">{{ somaPesos }}</span>
          </div>

          <button
            @click="salvarPesos"
            :disabled="salvandoPesos"
            class="px-3.5 py-1.5 rounded bg-[#0F2537] text-white text-xs font-bold hover:bg-[#1A365D] transition cursor-pointer flex items-center space-x-1.5 shadow-2xs disabled:opacity-50"
          >
            <Loader2 v-if="salvandoPesos" class="w-3.5 h-3.5 animate-spin" />
            <Save v-else class="w-3.5 h-3.5" />
            <span>Salvar Pesos</span>
          </button>
        </div>
      </div>

      <!-- Tabela Reativa de Alunos e Notas -->
      <div class="bg-white rounded-lg border border-slate-200 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-[#0F2537] text-white text-[11px] font-extrabold uppercase tracking-wider">
                <th class="py-3 px-4 w-12 text-center">Nº</th>
                <th class="py-3 px-4">Aluno</th>
                <th class="py-3 px-4">Equipe / Projeto</th>
                <th v-for="b in 4" :key="b" class="py-3 px-3 text-center">
                  <div>{{ b }}º Bimestre</div>
                  <div class="text-[9px] font-normal text-amber-300 lowercase">(peso: {{ pesosInput[b] || 1 }})</div>
                </th>
                <th class="py-3 px-4 text-center bg-[#1A365D]">Média Ponderada</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 text-xs">
              <tr 
                v-for="aluno in alunosNotas" 
                :key="aluno.id"
                class="hover:bg-slate-50/80 transition"
              >
                <td class="py-2.5 px-4 text-center font-mono font-bold text-slate-500">
                  {{ aluno.n_chamada || '-' }}
                </td>

                <td class="py-2.5 px-4">
                  <div class="font-bold text-slate-900">{{ aluno.nome }}</div>
                  <div class="text-[10px] text-slate-500 font-mono">{{ aluno.email }}</div>
                </td>

                <td class="py-2.5 px-4">
                  <span class="bg-slate-100 border border-slate-200 px-2 py-0.5 rounded text-slate-700 font-semibold">
                    {{ aluno.equipe_nome }}
                  </span>
                </td>

                <!-- Colunas dos Bimestres 1 a 4 (Exibe Nota + Pontos Ponderados Contribuídos) -->
                <td v-for="b in 4" :key="b" class="py-2.5 px-3 text-center">
                  <div class="flex flex-col items-center">
                    <button
                      @click="abrirRaioX(aluno, b)"
                      :class="[
                        'px-2.5 py-1 rounded border text-xs cursor-pointer transition inline-flex items-center space-x-1 shadow-2xs hover:scale-105',
                        getBadgeNotaClass(aluno.medias[b])
                      ]"
                      :title="aluno.medias[b] !== null ? `Nota Sprints: ${aluno.medias[b]} | Contribuição na Nota: (${aluno.medias[b]} × ${pesosInput[b] || 1}) / 10 = ${calcularContribuição(aluno.medias[b], pesosInput[b] || 1)} pts` : 'Clique para abrir o Raio-X'"
                    >
                      <span>{{ aluno.medias[b] !== null ? aluno.medias[b] : '-' }}</span>
                    </button>
                    <!-- Subtexto informando os pontos distribuídos/ponderados -->
                    <span v-if="aluno.medias[b] !== null" class="text-[9px] font-mono text-slate-600 font-bold mt-0.5" title="Pontos adicionados à nota final da disciplina">
                      +{{ calcularContribuição(aluno.medias[b], pesosInput[b] || 1) }} pts
                    </span>
                  </div>
                </td>

                <!-- Média Final Ponderada Calculada Reativamente -->
                <td class="py-2.5 px-4 text-center bg-slate-50/50">
                  <span 
                    :class="[
                      'px-3 py-1 rounded text-xs border font-black shadow-2xs inline-block',
                      getBadgeNotaClass(calcularMediaFinalAluno(aluno.medias))
                    ]"
                  >
                    {{ calcularMediaFinalAluno(aluno.medias) !== null ? calcularMediaFinalAluno(aluno.medias) : '-' }}
                  </span>
                </td>
              </tr>

              <tr v-if="!alunosNotas || alunosNotas.length === 0">
                <td colspan="8" class="text-center py-10 text-slate-500 font-medium">
                  Nenhum aluno encontrado para a turma selecionada.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- MODAL SLIDE-OVER: RAIO-X DO ALUNO (SÍNTESE DA IA + SPRINTS) -->
    <div v-if="modalRaioXAberto" class="fixed inset-0 z-50 overflow-hidden bg-slate-900/60 backdrop-blur-xs flex justify-end">
      <div class="w-full max-w-2xl bg-white h-full shadow-2xl flex flex-col transform transition-all duration-300">
        
        <!-- Header do Slide-over -->
        <div class="bg-[#0F2537] px-6 py-4 text-white flex items-center justify-between">
          <div class="flex items-center space-x-3">
            <User class="w-6 h-6 text-emerald-400" />
            <div>
              <h3 class="text-sm font-extrabold tracking-wide">{{ alunoSelecionado?.nome }}</h3>
              <p class="text-xs text-slate-300">
                Raio-X do <strong>{{ bimestreSelecionadoRaioX }}º Bimestre</strong> • {{ alunoSelecionado?.equipe_nome }}
              </p>
            </div>
          </div>
          <button @click="modalRaioXAberto = false" class="text-slate-300 hover:text-white cursor-pointer p-1">
            <X class="w-6 h-6" />
          </button>
        </div>

        <!-- Conteúdo Scrollável -->
        <div class="p-6 space-y-6 overflow-y-auto flex-1 bg-slate-50/50">
          
          <!-- Bloco 1: Síntese Pedagógica Gerada pelo Gemini AI -->
          <div class="bg-gradient-to-r from-purple-900 via-indigo-900 to-slate-900 rounded-xl p-5 text-white shadow-lg space-y-3">
            <div class="flex justify-between items-center border-b border-purple-800/60 pb-2.5">
              <h4 class="text-xs font-extrabold uppercase tracking-wider text-purple-200 flex items-center space-x-2">
                <Sparkles class="w-4 h-4 text-amber-300 animate-pulse" />
                <span>Síntese Analítica do Gemini AI</span>
              </h4>
              <button
                @click="carregarResumoGemini(alunoSelecionado.id, bimestreSelecionadoRaioX, true)"
                :disabled="carregandoResumoGemini"
                class="px-2.5 py-1 rounded bg-white/10 hover:bg-white/20 text-purple-200 text-[11px] font-bold transition flex items-center space-x-1 cursor-pointer disabled:opacity-50"
              >
                <RefreshCw :class="['w-3 h-3', carregandoResumoGemini ? 'animate-spin' : '']" />
                <span>Regerar IA</span>
              </button>
            </div>

            <div v-if="carregandoResumoGemini" class="py-4 text-center text-xs text-purple-200/80 flex items-center justify-center space-x-2">
              <Loader2 class="w-4 h-4 animate-spin text-amber-300" />
              <span>Analisando histórico e compilando observações do aluno...</span>
            </div>

            <p v-else class="text-xs text-purple-100 leading-relaxed font-sans italic">
              "{{ resumoGeminiTexto }}"
            </p>
          </div>

          <!-- Bloco 2: Média do Bimestre e Resumo das Sprints -->
          <div class="bg-white p-4 rounded-lg border border-slate-200 shadow-xs flex justify-between items-center">
            <div>
              <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Nota Média do Bimestre</span>
              <span 
                :class="[
                  'text-2xl font-black block mt-0.5',
                  getBadgeNotaClass(alunoSelecionado?.medias[bimestreSelecionadoRaioX]).includes('emerald') ? 'text-emerald-600' : 'text-amber-600'
                ]"
              >
                {{ alunoSelecionado?.medias[bimestreSelecionadoRaioX] ?? '-' }}
              </span>
            </div>
            <div class="text-right text-xs text-slate-500">
              <span>Sprints Realizadas: <strong>{{ alunoSelecionado?.sprints_detalhadas[bimestreSelecionadoRaioX]?.length || 0 }}</strong></span>
            </div>
          </div>

          <!-- Bloco 3: Lista Detalhada das Sprints do Bimestre -->
          <div class="space-y-4">
            <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-700 flex items-center space-x-1.5">
              <FileText class="w-4 h-4 text-blue-600" />
              <span>Composição Detalhada das Sprints</span>
            </h4>

            <div 
              v-if="!alunoSelecionado?.sprints_detalhadas[bimestreSelecionadoRaioX] || alunoSelecionado?.sprints_detalhadas[bimestreSelecionadoRaioX].length === 0"
              class="bg-white border border-slate-200 rounded-lg p-6 text-center text-xs text-slate-500"
            >
              Nenhuma sprint encerrada foi registrada para este aluno no {{ bimestreSelecionadoRaioX }}º Bimestre.
            </div>

            <div 
              v-for="sprintDet in alunoSelecionado?.sprints_detalhadas[bimestreSelecionadoRaioX]" 
              :key="sprintDet.sprint_id"
              class="bg-white border border-slate-200 rounded-lg p-4 shadow-2xs space-y-3"
            >
              <!-- Cabeçalho da Sprint -->
              <div class="flex justify-between items-center border-b pb-2">
                <span class="text-xs font-extrabold text-slate-800">Sprint {{ sprintDet.sequencia }}</span>
                <span class="text-xs font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                  Média Final: {{ sprintDet.nota_consolidada }}
                </span>
              </div>

              <!-- Grade de Critérios Globais da Equipe -->
              <div v-if="sprintDet.avaliacao_sprint" class="bg-blue-50/60 p-3 rounded-md border border-blue-200 text-xs space-y-1.5">
                <span class="font-bold text-blue-900 block flex items-center space-x-1">
                  <Award class="w-3.5 h-3.5 text-blue-700" />
                  <span>Avaliação do Grupo:</span>
                </span>
                <div class="grid grid-cols-4 gap-1.5 text-[11px] text-center">
                  <div class="bg-white p-1 rounded border border-blue-100">
                    <span class="text-slate-500 block text-[9px]">Valor</span>
                    <strong>{{ sprintDet.avaliacao_sprint.entrega_valor }}</strong>
                  </div>
                  <div class="bg-white p-1 rounded border border-blue-100">
                    <span class="text-slate-500 block text-[9px]">Técnica</span>
                    <strong>{{ sprintDet.avaliacao_sprint.qualidade_tecnica }}</strong>
                  </div>
                  <div class="bg-white p-1 rounded border border-blue-100">
                    <span class="text-slate-500 block text-[9px]">Rituais</span>
                    <strong>{{ sprintDet.avaliacao_sprint.processos_rituais }}</strong>
                  </div>
                  <div class="bg-white p-1 rounded border border-blue-100">
                    <span class="text-slate-500 block text-[9px]">Doc</span>
                    <strong>{{ sprintDet.avaliacao_sprint.documentacao }}</strong>
                  </div>
                </div>
              </div>

              <!-- Grade de Critérios Individuais do Aluno -->
              <div v-if="sprintDet.avaliacao_individual" class="bg-emerald-50/60 p-3 rounded-md border border-emerald-200 text-xs space-y-1.5">
                <span class="font-bold text-emerald-900 block flex items-center space-x-1">
                  <Users class="w-3.5 h-3.5 text-emerald-700" />
                  <span>Avaliação Individual do Aluno:</span>
                </span>
                <div class="grid grid-cols-3 gap-2 text-[11px] text-center">
                  <div class="bg-white p-1 rounded border border-emerald-100">
                    <span class="text-slate-500 block text-[9px]">Rituais</span>
                    <strong>{{ sprintDet.avaliacao_individual.rituais }}</strong>
                  </div>
                  <div class="bg-white p-1 rounded border border-emerald-100">
                    <span class="text-slate-500 block text-[9px]">Tarefas</span>
                    <strong>{{ sprintDet.avaliacao_individual.tarefas }}</strong>
                  </div>
                  <div class="bg-white p-1 rounded border border-emerald-100">
                    <span class="text-slate-500 block text-[9px]">Postura</span>
                    <strong>{{ sprintDet.avaliacao_individual.postura }}</strong>
                  </div>
                </div>
                <p v-if="sprintDet.avaliacao_individual.observacoes" class="text-[11px] text-emerald-950 italic mt-1">
                  "{{ sprintDet.avaliacao_individual.observacoes }}"
                </p>
              </div>

              <!-- Parecer/Feedback do Professor -->
              <div v-if="sprintDet.feedback_professor" class="bg-slate-50 p-2.5 rounded border border-slate-200 text-xs text-slate-700">
                <strong class="text-slate-900 block font-semibold mb-0.5">Parecer do Professor:</strong>
                <p class="text-[11px] leading-relaxed">{{ sprintDet.feedback_professor }}</p>
              </div>

            </div>
          </div>

        </div>

        <!-- Footer do Slide-over -->
        <div class="bg-slate-100 px-6 py-3 border-t border-slate-200 flex justify-end">
          <button @click="modalRaioXAberto = false" class="px-4 py-2 rounded bg-[#0F2537] text-white text-xs font-bold hover:bg-[#1A365D] cursor-pointer">
            Fechar Raio-X
          </button>
        </div>

      </div>
    </div>

  </AppLayout>
</template>
