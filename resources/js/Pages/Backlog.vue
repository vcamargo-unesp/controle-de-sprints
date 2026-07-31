<script setup>
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import { Plus, Send, CheckSquare, Square, Filter, Tag } from 'lucide-vue-next';

const props = defineProps({
  tarefas: {
    type: Array,
    default: () => [
      { id: 101, titulo: 'Modelagem do Banco de Dados Relacional (SIEP)', prioridade: 'Alta', estimativa_horas: 8, papel: 'PO', criado_em: '2026-07-28' },
      { id: 102, titulo: 'Implementar CRUD de Equipes e Alunos', prioridade: 'Média', estimativa_horas: 12, papel: 'Dev', criado_em: '2026-07-29' },
      { id: 103, titulo: 'Criar Tela de Login com Autenticação CTI', prioridade: 'Alta', estimativa_horas: 6, papel: 'Dev', criado_em: '2026-07-30' },
      { id: 104, titulo: 'Elaboração da Documentação Arquitetural', prioridade: 'Baixa', estimativa_horas: 4, papel: 'PO', criado_em: '2026-07-30' },
      { id: 105, titulo: 'Testes de Carga e Validação no Debian 13', prioridade: 'Média', estimativa_horas: 5, papel: 'Dev', criado_em: '2026-07-31' },
    ]
  },
  sprintsDisponiveis: {
    type: Array,
    default: () => [
      { id: 1, nome: 'Sprint 3 - Módulo Aluno' },
      { id: 2, nome: 'Sprint 4 - Relatórios e Notas' }
    ]
  }
});

const selecionadas = ref([]);
const sprintAlvo = ref('');

const toggleSelecionarTodas = (event) => {
  if (event.target.checked) {
    selecionadas.value = props.tarefas.map(t => t.id);
  } else {
    selecionadas.value = [];
  }
};

const toggleSelecionar = (id) => {
  const index = selecionadas.value.indexOf(id);
  if (index > -1) {
    selecionadas.value.splice(index, 1);
  } else {
    selecionadas.value.push(id);
  }
};

const enviarParaSprint = () => {
  if (!sprintAlvo.value || selecionadas.value.length === 0) return;
  alert(`Movendo ${selecionadas.value.length} tarefa(s) para a ${sprintAlvo.value}`);
};
</script>

<template>
  <AppLayout userRole="aluno" userName="Vitor - PO Equipe Alpha">
    <!-- Breadcrumb -->
    <Breadcrumb :items="[{ label: 'Equipe Alpha', href: '#' }, { label: 'Backlog de Tarefas' }]" />

    <!-- Card Principal de Alta Densidade -->
    <div class="bg-white rounded-md border border-slate-200 shadow-sm overflow-hidden mt-2">
      <!-- Topbar de Ações do Backlog -->
      <div class="bg-slate-50 border-b border-slate-200 px-4 py-2.5 flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-center space-x-2">
          <h1 class="text-base font-bold text-slate-800 tracking-tight">Backlog do Produto</h1>
          <span class="bg-slate-200 text-slate-700 text-xs font-semibold px-2 py-0.5 rounded-full">
            {{ tarefas.length }} Tarefa(s) Pendente(s)
          </span>
        </div>

        <!-- Controles para Envio Múltiplo para Sprint -->
        <div class="flex items-center space-x-2 text-xs">
          <div class="flex items-center space-x-1">
            <span class="text-slate-600 font-medium">Mover selecionadas ({{ selecionadas.length }}) para:</span>
            <select 
              v-model="sprintAlvo" 
              class="border border-slate-300 rounded px-2 py-1 bg-white text-xs text-slate-800 focus:ring-1 focus:ring-slate-500 focus:outline-none"
            >
              <option value="" disabled>Selecione a Sprint...</option>
              <option v-for="sprint in sprintsDisponiveis" :key="sprint.id" :value="sprint.nome">
                {{ sprint.nome }}
              </option>
            </select>
          </div>

          <button 
            @click="enviarParaSprint"
            :disabled="selecionadas.length === 0 || !sprintAlvo"
            class="bg-[#0F2537] hover:bg-[#1A365D] disabled:opacity-50 text-white font-semibold px-3 py-1 rounded text-xs transition flex items-center space-x-1 cursor-pointer"
          >
            <Send class="w-3.5 h-3.5" />
            <span>Atribuir à Sprint</span>
          </button>
        </div>
      </div>

      <!-- Tabela Zebrada de Alta Densidade (Estilo SIEP) -->
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse siep-table-compact">
          <thead>
            <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 uppercase font-bold text-[11px] tracking-wider">
              <th class="w-10 text-center py-2">
                <input 
                  type="checkbox" 
                  @change="toggleSelecionarTodas" 
                  :checked="selecionadas.length === tarefas.length && tarefas.length > 0"
                  class="rounded border-slate-300 text-slate-800 focus:ring-0 cursor-pointer"
                />
              </th>
              <th class="w-16">#ID</th>
              <th>Descrição da Tarefa</th>
              <th class="w-24">Papel</th>
              <th class="w-24">Prioridade</th>
              <th class="w-28 text-center">Est. (Horas)</th>
              <th class="w-28">Criado em</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200">
            <tr 
              v-for="tarefa in tarefas" 
              :key="tarefa.id" 
              class="even:bg-slate-50 hover:bg-blue-50/60 transition-colors"
            >
              <td class="text-center">
                <input 
                  type="checkbox" 
                  :value="tarefa.id" 
                  :checked="selecionadas.includes(tarefa.id)" 
                  @change="toggleSelecionar(tarefa.id)"
                  class="rounded border-slate-300 text-[#0F2537] focus:ring-0 cursor-pointer"
                />
              </td>
              <td class="font-mono font-bold text-slate-600">#{{ tarefa.id }}</td>
              <td class="font-medium text-slate-900">{{ tarefa.titulo }}</td>
              <td>
                <span 
                  :class="[
                    'inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide',
                    tarefa.papel === 'PO' ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-blue-100 text-blue-800 border border-blue-300'
                  ]"
                >
                  {{ tarefa.papel }}
                </span>
              </td>
              <td>
                <span 
                  :class="[
                    'inline-block px-2 py-0.5 rounded text-[10px] font-bold',
                    tarefa.prioridade === 'Alta' ? 'bg-red-100 text-red-700' : tarefa.prioridade === 'Média' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600'
                  ]"
                >
                  {{ tarefa.prioridade }}
                </span>
              </td>
              <td class="text-center font-mono text-xs font-semibold text-slate-700">{{ tarefa.estimativa_horas }}h</td>
              <td class="text-slate-500 font-mono text-xs">{{ tarefa.criado_em }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
